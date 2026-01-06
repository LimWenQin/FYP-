<?php
// admin_dashboard.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// ==========================================
// 1. HANDLE ADD DONATION LOGIC (From Payment Management)
// 保留你原本的 Add Donation 逻辑，因为你只要求改 Donor/Activity/Case
// ==========================================
$msg = "";
$msgType = "";

// --- Helper: File Upload Function (Reused) ---
function handleFileUpload($file, $targetDir) {
    if (isset($file) && $file['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowedTypes)) {
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $targetDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) return $uploadPath;
        }
    }
    return null;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- A. ADD DONATION (保留不动) ---
    if (isset($_POST['add_donation_submit'])) {
        $donor_id = $_POST['donor_id'];
        $amount = $_POST['amount'];
        $payment_method = $_POST['payment_method']; 
        $donation_frequency = $_POST['donation_frequency']; 
        
        $target_category = $_POST['target_category']; 
        $branch_id = ($target_category == 'branch') ? $_POST['target_branch_id'] : null;
        $activity_id = ($target_category == 'activity') ? $_POST['target_activity_id'] : null;
        $case_id = ($target_category == 'case') ? $_POST['target_case_id'] : null;

        $conn->begin_transaction();
        try {
            $donorQ = $conn->query("SELECT * FROM donor WHERE Donor_ID = '$donor_id'");
            if ($donorQ->num_rows == 0) throw new Exception("Selected donor not found.");
            $donorData = $donorQ->fetch_assoc();
            
            $d_name = $donorData['Donor_Name'];
            $d_email = $donorData['Donor_Email'];
            $d_phone = $donorData['Donor_ContactNumber'];
            $d_ic = $donorData['Donor_ICNumber'];

            $txn_ref = "MAN-IN-" . date('YmdHis') . "-" . rand(100, 999); 
            $paySql = "INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) 
                        VALUES (?, 'Success', ?, ?, NOW(), 'Manual Entry', 'N/A', NOW())";
            $stmtPay = $conn->prepare($paySql);
            $stmtPay->bind_param("ssd", $payment_method, $txn_ref, $amount);
            $stmtPay->execute();
            $payment_insert_id = $conn->insert_id;
            $stmtPay->close();

            $points_earned = floor($amount / 10);
            $orderSql = "INSERT INTO orders (Order_Name, Order_ContactNumber, Order_ICNumber, Order_Email, Order_Amount, Order_Points_Earned, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, Order_Admin_Status, Order_TXN_Ref, Order_Type, Order_Status, Is_Deleted, Order_Created_At, Order_Updated_At, Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID) 
                          VALUES (?, ?, ?, ?, ?, ?, 'MYR', ?, 'Success', 'Completed', ?, ?, 'Completed', 0, NOW(), NOW(), ?, ?, ?, ?, ?)";
            
            $stmtOrder = $conn->prepare($orderSql);
            $stmtOrder->bind_param("ssssdissiiiii", 
                $d_name, $d_phone, $d_ic, $d_email, 
                $amount, $points_earned, $payment_method, $txn_ref, $donation_frequency,
                $donor_id, $payment_insert_id, $branch_id, $activity_id, $case_id
            );
            $stmtOrder->execute();
            $stmtOrder->close();

            if ($points_earned > 0) {
                $ptCheck = $conn->query("SELECT * FROM point WHERE Donor_ID = '$donor_id'");
                if ($ptCheck->num_rows > 0) {
                    $conn->query("UPDATE point SET Points_Total = Points_Total + $points_earned, Points_Earned = Points_Earned + $points_earned, Points_Updated_At = NOW() WHERE Donor_ID = '$donor_id'");
                } else {
                    $conn->query("INSERT INTO point (Points_Earned, Points_Total, Points_Updated_At, Donor_ID) VALUES ($points_earned, $points_earned, NOW(), '$donor_id')");
                }
            }

            $conn->commit();
            $msg = "Donation successfully recorded!"; $msgType = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Error: " . $e->getMessage(); $msgType = "error";
        }
    }
}

// ==========================================
// ⬇️ STATS & DATA FETCHING ⬇️
// ==========================================

// 1. Get Total Donors
function getTotalDonors($conn) {
    $sql = "SELECT COUNT(*) as total FROM donor WHERE Is_Deleted = 0";
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? $row['total'] : 0;
}

// 2. Get Total Donations
function getTotalDonations($conn) {
    $sql = "SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus = 'Success' OR Order_PaymentStatus = 'Completed'"; 
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? number_format((float)$row['total'], 2) : '0.00';
}

// 3. Get Total Branches
function getTotalBranches($conn) {
    $sql = "SELECT COUNT(*) as total FROM branch";
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? $row['total'] : 0;
}

// 4. Get Total Activities
function getTotalActivities($conn) {
    $sql = "SELECT COUNT(*) as total FROM activity";
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? $row['total'] : 0;
}

// 5. Calculate Growth Rate
function getGrowthRate($conn, $table, $dateColumn, $sumColumn = null, $extraCondition = "") {
    $currentMonthSQL = "SELECT " . ($sumColumn ? "SUM($sumColumn)" : "COUNT(*)") . " as val 
                        FROM $table 
                        WHERE MONTH($dateColumn) = MONTH(CURRENT_DATE()) 
                        AND YEAR($dateColumn) = YEAR(CURRENT_DATE()) $extraCondition";
    
    $lastMonthSQL = "SELECT " . ($sumColumn ? "SUM($sumColumn)" : "COUNT(*)") . " as val 
                     FROM $table 
                     WHERE MONTH($dateColumn) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
                     AND YEAR($dateColumn) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) $extraCondition";

    $currentVal = 0; $lastVal = 0;

    $resCurrent = $conn->query($currentMonthSQL);
    if ($resCurrent && $row = $resCurrent->fetch_assoc()) $currentVal = $row['val'] ? $row['val'] : 0;

    $resLast = $conn->query($lastMonthSQL);
    if ($resLast && $row = $resLast->fetch_assoc()) $lastVal = $row['val'] ? $row['val'] : 0;

    if ($lastVal == 0) {
        return ($currentVal == 0) ? 0 : 100;
    }
    return (($currentVal - $lastVal) / $lastVal) * 100;
}

// 6. Format Growth HTML
function formatGrowthHtml($percent) {
    $percent = round($percent, 2);
    
    if ($percent >= 0) {
        return '<p style="color: #28a745; display: flex; align-items: center;"><i class="fas fa-arrow-up" style="margin-right:5px;"></i> +' . $percent . '% from last month</p>';
    } else {
        return '<p style="color: #dc3545; display: flex; align-items: center;"><i class="fas fa-arrow-down" style="margin-right:5px;"></i> ' . $percent . '% from last month</p>';
    }
}

// 7. Get Recent Donations (Limit 3)
function getRecentDonations($conn) {
    $sql = "SELECT d.Donor_Name, d.Donor_Email, o.Order_Amount, o.Order_Created_At, o.Order_PaymentStatus as status, d.Donor_ProfilePicture 
            FROM orders o 
            JOIN donor d ON o.Donor_ID = d.Donor_ID 
            ORDER BY o.Order_Created_At DESC 
            LIMIT 3"; 
    $result = $conn->query($sql);
    $donations = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }
    }
    return $donations;
}

// 8. Get Recent Redemptions (Limit 5)
function getRecentRedemptions($conn) {
    $sql = "SELECT r.Redemption_Updated_At, d.Donor_Name, i.Reward_ItemName, r.Redemption_PointsSpent, r.Redemption_Status 
            FROM redemption_order r 
            JOIN donor d ON r.Donor_ID = d.Donor_ID
            JOIN reward_item i ON r.Reward_ID = i.Reward_ID
            ORDER BY r.Redemption_Updated_At DESC 
            LIMIT 5"; 
    $result = $conn->query($sql);
    $redemptions = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $redemptions[] = $row;
        }
    }
    return $redemptions;
}

// 9. Get Dynamic Donation Trends
function getDynamicDonationTrends($conn) {
    $data = [];
    $labels = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $displayDate = date('D', strtotime($date)); 
        $sql = "SELECT SUM(Order_Amount) as total FROM orders 
                WHERE DATE(Order_Created_At) = '$date' 
                AND (Order_PaymentStatus = 'Success' OR Order_PaymentStatus = 'Completed')";
        $result = $conn->query($sql);
        $total = ($result && $row = $result->fetch_assoc()) ? (float)$row['total'] : 0;
        $labels[] = $displayDate;
        $data[] = $total;
    }
    return ['labels' => $labels, 'data' => $data];
}

// Fetch Dropdown Data for Modals
$donorsList = $conn->query("SELECT Donor_ID, Donor_Name, Donor_Email FROM donor WHERE Is_Deleted = 0 ORDER BY Donor_Name ASC");
$branchesList = $conn->query("SELECT Branch_ID, Branch_Name FROM branch WHERE Branch_OperationalStatus = 'Open'");
$activitiesList = $conn->query("SELECT Activity_ID, Activity_Name FROM activity WHERE Activity_Status != 'Cancelled' AND Activity_Status != 'Completed' ORDER BY Activity_StartDate DESC");
$casesList = $conn->query("SELECT Case_ID, Case_Title FROM special_case WHERE Case_Status = 'Active'");
$allBranches = $conn->query("SELECT Branch_ID, Branch_Name FROM branch ORDER BY Branch_Name ASC"); // For activity branch select

$malaysiaStates = [ 'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu' ];
$branches = []; 
while($bRow = $allBranches->fetch_assoc()) $branches[] = $bRow; // Cache for Activity Modal

// Admin Info
$adminId = $_SESSION['admin_id'];
$adminName = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin'; 
$adminPosition = 'Admin';
$adminProfilePicture = null;

$stmt = $conn->prepare("SELECT Admin_ProfilePicture, Admin_Name, Admin_Role FROM admin WHERE Admin_ID = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $row = $res->fetch_assoc()) {
    $adminProfilePicture = $row['Admin_ProfilePicture'];
    $adminName = $row['Admin_Name'];
    $adminPosition = $row['Admin_Role']; 
}
$stmt->close();

// Fetch Statistics
$totalDonors = getTotalDonors($conn);
$totalDonations = getTotalDonations($conn);
$totalBranches = getTotalBranches($conn);
$totalActivities = getTotalActivities($conn);

// Growth Rates
$donorGrowth = getGrowthRate($conn, 'donor', 'Donor_RegisteredAt');
$donationGrowth = getGrowthRate($conn, 'orders', 'Order_Created_At', 'Order_Amount', "AND (Order_PaymentStatus = 'Success' OR Order_PaymentStatus = 'Completed')");
$activityGrowth = getGrowthRate($conn, 'activity', 'Activity_StartDate'); 
$branchGrowth = 0; 

// Fetch Table Data
$recentDonations = getRecentDonations($conn); 
$recentRedemptions = getRecentRedemptions($conn); 
$chartData = getDynamicDonationTrends($conn); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Love Bridge</title>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    
    <style>
        /* Dashboard Styles */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; margin: 0; }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(23, 162, 184, 0.2); color: var(--info); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }
        
        .charts-tables { display: grid; grid-template-columns: 2fr 1.2fr; gap: 20px; margin-bottom: 30px; }
        .chart-container, .recent-list { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; flex-direction: column; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; margin: 0; }
        .section-header a, .section-header button { color: var(--primary); text-decoration: none; font-size: 13px; font-weight: 500; border: none; background: none; cursor: pointer; }
        
        .chart-wrapper { height: 250px; position: relative; width: 100%; }
        
        /* Table Styles - General */
        table { width: 100%; border-collapse: collapse; }
        th, td { 
            padding: 12px 10px; 
            text-align: left; 
            vertical-align: middle; 
            border-bottom: 1px solid #f0f0f0; 
            font-size: 13px; 
        }
        th { font-weight: 600; color: #888; text-transform: uppercase; font-size: 12px; }
        
        /* 1. DATE FORMAT STYLING (3 LINES) */
        .date-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 1.2;
            color: #555;
            min-width: 50px;
        }
        .d-year { font-size: 12px; font-weight: bold; color: #333; }
        .d-month { font-size: 11px; text-transform: uppercase; margin: 2px 0; }
        .d-day { font-size: 14px; font-weight: bold; color: #333; }
        
        /* 2. AMOUNT ALIGN RIGHT */
        .amount-right { text-align: right; }

        /* 3. REDEMPTION TABLE ALIGN CENTRE */
        .redemption-table th, 
        .redemption-table td { text-align: center !important; }

        .info-cell { display: flex; align-items: center; text-align: left;}
        .avatar-circle { width: 32px; height: 32px; border-radius: 50%; background: #eee; color: #555; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; font-size: 12px; flex-shrink: 0; overflow: hidden; }
        .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .text-info h4 { margin: 0; font-size: 13px; color: #333; }
        .text-info p { margin: 0; font-size: 11px; color: #888; }
        
        .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .status-success { background: #e6f9ed; color: #28a745; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-shipped { background: #cce5ff; color: #004085; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-failed { background: #ffe6e6; color: #dc3545; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .action-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); text-align: center; cursor: pointer; transition: all 0.3s; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1); }
        .action-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 20px; background: rgba(242, 133, 133, 0.1); color: var(--primary); }
        .action-card h3 { font-size: 15px; margin-bottom: 5px; color: #333; }
        .action-card p { font-size: 13px; color: #888; margin: 0; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1200; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 600px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .modal-btn-group { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        .modal-btn { padding: 12px; border-radius: 8px; border: 1px solid #eee; background: #f8f9fa; cursor: pointer; font-weight: 600; color: #333; transition: all 0.2s; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .modal-btn:hover { background: #F28585; color: white; border-color: #F28585; }
        .modal-title { margin-bottom: 10px; font-size: 18px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-close { position: absolute; top: 15px; right: 15px; cursor: pointer; font-size: 20px; color: #aaa; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray); float: right; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-header h2 { margin: 0; font-size: 18px; }
        .modal-body { padding: 10px 0; }

        /* Form Styles */
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; }
        .form-control, .form-select, .form-input, .form-textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn-submit, .btn-primary { background: #F28585; color: white; border: none; padding: 10px; width: 100%; border-radius: 5px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .row-group, .form-row { display: flex; gap: 15px; }
        .row-group .form-group, .form-row .form-group { flex: 1; }
        .form-guide { font-size: 12px; color: #666; margin-top: 5px; display: block; background: #f9f9f9; padding: 8px; border-radius: 4px; border-left: 3px solid #ccc; line-height: 1.4; }
        .required { color: red; }

        /* Styles from Donor/Activity/Case Management for correct display */
        .profile-picture-preview { width: 120px; height: 120px; border-radius: 50%; border: 4px solid #f8f9fa; margin: 0 auto 15px; background: #eee; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .case-preview-box { width: 200px; height: 150px; border-radius: 10px; } 
        .profile-picture-preview img { width: 100%; height: 100%; object-fit: cover; }
        .file-upload { text-align: center; margin-bottom: 20px; }
        .file-upload-label { display: inline-block; padding: 8px 15px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 5px; cursor: pointer; font-size: 13px; transition: all 0.3s; }
        .file-upload-label:hover { border-color: var(--primary); background: #fff5f5; color: var(--primary); }
        .file-upload input[type="file"] { display: none; }
        .file-info { display: none; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; background: #f1f1f1; padding: 5px 10px; border-radius: 5px; }
        .file-remove { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 14px; padding: 0 5px; }
        
        /* Password */
        .password-input-group { display: flex; width: 100%; }
        .password-input-container { flex: 1; display: flex; position: relative; }
        .password-input-container input { flex: 1; border-radius: 5px 0 0 5px; border-right: none; }
        .toggle-password { border: 1px solid #ddd; border-left: none; background: white; padding: 0 10px; cursor: pointer; }
        .btn-small { padding: 0 12px; border-radius: 0 5px 5px 0; border: 1px solid #ddd; border-left: none; background: #f8f9fa; cursor: pointer; font-size: 12px; font-weight: 500; color: var(--primary); }
        .password-requirements { margin-top: 8px; font-size: 12px; }
        .requirement-item { display: flex; align-items: center; margin-bottom: 3px; color: #888; }
        .requirement-item.valid { color: var(--success); } .requirement-item.invalid { color: var(--gray); } 
        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid #ddd; border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); font-weight: bold; }
        .phone-input { border-radius: 0 5px 5px 0 !important; }
        .confirm-check { position: absolute; right: 50px; top: 50%; transform: translateY(-50%); color: var(--success); font-size: 14px; display: none; z-index: 2; }
        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }
        .hidden-field { display: none; }

        /* Floating Alert */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1300; display: flex; align-items: center; gap: 10px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }

        /* Select2 Fix */
        .select2-container .select2-selection--single { height: 42px !important; border: 1px solid #ddd !important; border-radius: 5px !important; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }

        @media (max-width: 1024px) { .charts-tables { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .stats-cards, .quick-actions { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    
    <?php if (!empty($msg)): ?>
        <div class="floating-alert <?php echo $msgType == 'success' ? 'floating-alert-success' : 'floating-alert-danger'; ?>" id="floatingAlert">
            <i class="fas <?php echo $msgType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <div><?php echo $msg; ?></div>
        </div>
        <script>setTimeout(() => { document.getElementById('floatingAlert').style.display='none'; }, 5000);</script>
    <?php endif; ?>

    <?php include 'admin_sidebar.css'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>System Overview</h1>
                <p>Welcome back, <?php echo htmlspecialchars($adminName); ?>.</p>
            </div>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL DONORS</h3>
                        <h2><?php echo $totalDonors; ?></h2>
                        <?php echo formatGrowthHtml($donorGrowth); ?>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL DONATIONS</h3>
                        <h2>RM <?php echo $totalDonations; ?></h2>
                        <?php echo formatGrowthHtml($donationGrowth); ?>
                    </div>
                    <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>BRANCH LOCATIONS</h3>
                        <h2><?php echo $totalBranches; ?></h2>
                        <?php echo formatGrowthHtml($branchGrowth); ?>
                    </div>
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL ACTIVITIES</h3>
                        <h2><?php echo $totalActivities; ?></h2>
                        <?php echo formatGrowthHtml($activityGrowth); ?>
                    </div>
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                </div>
            </div>

            <div class="charts-tables">
                
                <div class="chart-container">
                    <div class="section-header">
                        <h2>Donation Trends (Last 7 Days)</h2>
                        <button onclick="exportChartData()" title="Generate Excel"><i class="fas fa-file-excel"></i> Export Data</button>
                    </div>
                    <div class="chart-wrapper"><canvas id="donationChart"></canvas></div>
                </div>

                <div class="recent-list">
                    <div class="section-header">
                        <h2>Recent Donations</h2>
                        <a href="payment_management.php">View All</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="amount-right">Amount (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentDonations) > 0): ?>
                                <?php foreach($recentDonations as $d): 
                                    $statusClass = 'status-pending';
                                    if ($d['status'] == 'Success' || $d['status'] == 'Completed') $statusClass = 'status-success';
                                    elseif ($d['status'] == 'Failed') $statusClass = 'status-failed';
                                    
                                    // 1. DATE FORMAT (Year / Month / Day in 3 lines)
                                    $dateObj = new DateTime($d['Order_Created_At']);
                                ?>
                                <tr>
                                    <td>
                                        <div class="info-cell">
                                            <div class="avatar-circle">
                                                <?php if(!empty($d['Donor_ProfilePicture'])): ?>
                                                    <img src="<?php echo $d['Donor_ProfilePicture']; ?>" alt="Pic">
                                                <?php else: echo substr($d['Donor_Name'], 0, 1); endif; ?>
                                            </div>
                                            <div class="text-info"><h4><?php echo htmlspecialchars($d['Donor_Name']); ?></h4></div>
                                        </div>
                                    </td>
                                    <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $d['status']; ?></span></td>
                                    <td>
                                        <div class="date-box">
                                            <span class="d-year"><?php echo $dateObj->format('Y'); ?></span>
                                            <span class="d-month"><?php echo $dateObj->format('M'); ?></span>
                                            <span class="d-day"><?php echo $dateObj->format('d'); ?></span>
                                        </div>
                                    </td>
                                    <td class="amount-right"><?php echo number_format($d['Order_Amount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align: center;">No recent donations.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="recent-list" style="grid-column: 1 / -1;">
                    <div class="section-header">
                        <h2>Recent Redemptions (Recurring Order)</h2>
                        <a href="redemption_order_management.php">View All</a>
                    </div>
                    <table class="redemption-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Donor Name</th>
                                <th>Reward Item</th>
                                <th>Points Used</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentRedemptions) > 0): ?>
                                <?php foreach($recentRedemptions as $r): 
                                    $rStatusClass = 'status-pending'; 
                                    $status = $r['Redemption_Status'];
                                    if ($status == 'Completed') $rStatusClass = 'status-completed';
                                    elseif ($status == 'Shipped') $rStatusClass = 'status-shipped'; 
                                    elseif ($status == 'Cancelled') $rStatusClass = 'status-cancelled';
                                    
                                    $rDate = new DateTime($r['Redemption_Updated_At']);
                                ?>
                                <tr>
                                    <td>
                                        <div class="date-box" style="margin: 0 auto;">
                                            <span class="d-year"><?php echo $rDate->format('Y'); ?></span>
                                            <span class="d-month"><?php echo $rDate->format('M'); ?></span>
                                            <span class="d-day"><?php echo $rDate->format('d'); ?></span>
                                        </div>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($r['Donor_Name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($r['Reward_ItemName']); ?></td>
                                    <td style="color: #555; font-weight: bold;">-<?php echo $r['Redemption_PointsSpent']; ?> pts</td>
                                    <td><span class="status-badge <?php echo $rStatusClass; ?>"><?php echo $r['Redemption_Status']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No redemption history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="quick-actions">
                <div class="action-card"><div class="action-icon"><i class="fas fa-file-alt"></i></div><h3>Download Report</h3><p>Generate summary</p></div>
                
                <div class="action-card" onclick="openDonationModal()">
                    <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                    <h3>New Donation</h3>
                    <p>Record manual entry</p>
                </div>
                
                <div class="action-card" onclick="openAddDonorModal()">
                    <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                    <h3>Add Donor</h3>
                    <p>Register new donor</p>
                </div>
                
                <div class="action-card" onclick="openActivitySelectionModal()">
                    <div class="action-icon"><i class="fas fa-calendar-plus"></i></div>
                    <h3>Schedule Activity</h3>
                    <p>Create new event</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="activitySelectionModal">
        <div class="modal-content" style="position: relative; max-width:400px;">
            <span class="modal-close" onclick="closeActivitySelectionModal()">&times;</span>
            <h3 class="modal-title">Select Activity Type</h3>
            <p style="color:#666; margin-bottom:20px;">What kind of activity would you like to schedule?</p>
            <div class="modal-btn-group">
                <button type="button" class="modal-btn" onclick="openAddActivityModal()">
                    <i class="fas fa-running" style="color: var(--primary);"></i> In-Person Activity
                </button>
                <button type="button" class="modal-btn" onclick="openAddSpecialCaseModal()">
                    <i class="fas fa-heartbeat" style="color: var(--danger);"></i> Special Case
                </button>
            </div>
        </div>
    </div>

    <div class="modal" id="addDonationModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeDonationModal()">&times;</span>
            <h2 class="modal-title">Add New Donation</h2>
            <form method="POST" action="">
                <h4 style="color:#666; margin-bottom:10px;">Select Donor</h4>
                <div class="form-group">
                    <label>Registered Donor <span style="color:red">*</span></label>
                    <select name="donor_id" id="donorSelect" class="form-control" required style="width: 100%;">
                        <option value="">-- Select Existing Donor --</option>
                        <?php while($d = $donorsList->fetch_assoc()): ?>
                            <option value="<?php echo $d['Donor_ID']; ?>">
                                <?php echo htmlspecialchars($d['Donor_Name']) . " (" . htmlspecialchars($d['Donor_Email']) . ")"; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <span class="form-guide">Select the donor who is making this donation. Use search to find by name or email.</span>
                </div>
                <hr style="border:0; border-top:1px solid #eee; margin:15px 0;">
                <h4 style="color:#666; margin-bottom:10px;">Donation Details</h4>
                <div class="row-group">
                    <div class="form-group">
                        <label>Target Category</label>
                        <select name="target_category" id="targetCategory" class="form-control" onchange="updateTargetOptions()" required>
                            <option value="other">Other / General Fund</option>
                            <option value="branch">Specific Branch</option>
                            <option value="activity">Specific Activity</option>
                            <option value="case">Special Case</option>
                        </select>
                        <span class="form-guide">If this donation is for a specific cause, select the category here.</span>
                    </div>
                    <div class="form-group" id="targetSelectContainer">
                        <label>Select Item</label>
                        <input type="text" class="form-control" value="General Fund" disabled id="otherInput">
                        <select name="target_branch_id" id="selectBranch" class="form-control" style="display:none;">
                            <?php while($b = $branchesList->fetch_assoc()): ?>
                                <option value="<?php echo $b['Branch_ID']; ?>"><?php echo htmlspecialchars($b['Branch_Name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <select name="target_activity_id" id="selectActivity" class="form-control" style="display:none;">
                            <?php while($a = $activitiesList->fetch_assoc()): ?>
                                <option value="<?php echo $a['Activity_ID']; ?>"><?php echo htmlspecialchars($a['Activity_Name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <select name="target_case_id" id="selectCase" class="form-control" style="display:none;">
                            <?php while($c = $casesList->fetch_assoc()): ?>
                                <option value="<?php echo $c['Case_ID']; ?>"><?php echo htmlspecialchars($c['Case_Title']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="row-group">
                    <div class="form-group">
                        <label>Frequency</label>
                        <select name="donation_frequency" class="form-control">
                            <option value="One-Time">One-Time</option>
                            <option value="Recurring">Monthly</option>
                        </select>
                        <span class="form-guide">Is this a one-time donation or a recurring monthly pledge?</span>
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                        <span class="form-guide">Method used for this payment (e.g. Cash, Bank Transfer).</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Amount (RM) <span style="color:red">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="1" required>
                    <span class="form-guide">The total amount in MYR.</span>
                </div>
                <button type="submit" name="add_donation_submit" class="btn-submit">Confirm Donation</button>
            </form>
        </div>
    </div>

    <div class="modal" id="addDonorModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Donor</h2><button class="close-btn" onclick="closeAddDonorModal()">&times;</button></div>
            <div class="modal-body">
                <form id="addDonorForm" action="admin_donor_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <input type="hidden" name="add_donor" value="1">
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <div class="profile-picture-preview" id="add-preview-container"><div style="font-size:48px; color:#ccc;"><i class="fas fa-user"></i></div></div>
                        <div class="file-upload">
                            <label for="add_profile_picture" class="file-upload-label"><i class="fas fa-upload"></i> Choose Profile Picture</label>
                            <input type="file" id="add_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'add-preview-container', 'add-file-info', 'add-file-name')">
                            <div id="add-file-info" class="file-info"><span id="add-file-name" class="file-name"></span><button type="button" class="file-remove" onclick="removeImage('add_profile_picture', 'add-preview-container', 'add-file-info')"><i class="fas fa-times"></i></button></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="donor_name" class="form-input" required oninput="validateName(this)" placeholder="e.g. John Doe">
                        <span class="form-guide">Enter full name as per IC. English letters only.</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-input" required onblur="validateEmail()" placeholder="e.g. user@example.com">
                            <span class="form-guide">Valid email address (e.g. name@domain.com, must contain @ and domain).</span>
                            <div id="emailError" class="error-message">Invalid email format.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format"><span class="phone-prefix">+60</span><input type="text" id="contact" name="contact" class="form-input phone-input" required placeholder="11-12345678" maxlength="11"></div>
                            <span class="form-guide">Format: 12-3456789 or 11-12345678 (No need for +60).</span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number</label>
                            <input type="text" id="ic_number" name="ic_number" class="form-input" placeholder="XXXXXX-XX-XXXX" maxlength="14">
                            <span class="form-guide">Format: YYMMDD-PB-#### (e.g. 990101-07-1234).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="form-input" onchange="validateAge()">
                            <div id="ageError" class="error-message">Must be 18+</div>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Address Line 1</label><input type="text" name="address1" class="form-input" placeholder="e.g. No. 123, Jalan Example"><span class="form-guide">House unit no., floor, building, street name.</span></div>
                    <div class="form-group"><label class="form-label">Address Line 2</label><input type="text" name="address2" class="form-input" placeholder="e.g. Taman Sri"><span class="form-guide">Residential area, village, or section.</span></div>
                    <div class="form-group"><label class="form-label">Address Line 3</label><input type="text" name="address3" class="form-input" placeholder="Address Line 3 (Optional)"></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Postal Code</label><input type="text" id="postal_code" name="postal_code" class="form-input" placeholder="e.g. 50000"></div><div class="form-group"><label class="form-label">City</label><input type="text" name="city" class="form-input" placeholder="e.g. Kuala Lumpur"></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">State</label><select id="state_select" name="state" class="form-select"><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div><div class="form-group"><label class="form-label">Country</label><input type="text" name="country" class="form-input" value="Malaysia" readonly></div></div>
                    <div class="form-group"><label class="form-label">Remarks / Description</label><textarea name="description" class="form-textarea" rows="2"></textarea><span class="form-guide">Optional: Enter remarks, preferences, or important notes about this donor.</span></div>
                    <div class="form-group">
                        <label class="form-label">Password <span class="required">*</span></label>
                        <div class="password-input-group">
                            <div class="password-input-container"><input type="password" id="password" name="password" class="form-input" required oninput="validatePasswordRequirements()"><button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')"><i class="fas fa-eye"></i></button></div>
                            <button type="button" class="btn-small" onclick="generateStrongPassword('password', 'confirm_password')">Auto Generate</button>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement-item invalid" id="lengthReq"><i class="fas fa-times"></i> Must be 8-15 characters long</div>
                            <div class="requirement-item invalid" id="uppercaseReq"><i class="fas fa-times"></i> Must contain at least one Uppercase letter</div>
                            <div class="requirement-item invalid" id="lowercaseReq"><i class="fas fa-times"></i> Must contain at least one Lowercase letter</div>
                            <div class="requirement-item invalid" id="numberReq"><i class="fas fa-times"></i> Must contain at least one Number</div>
                            <div class="requirement-item invalid" id="specialReq"><i class="fas fa-times"></i> Must contain at least one Special character (e.g. !@#)</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password <span class="required">*</span></label>
                        <div class="password-input-container"><input type="password" id="confirm_password" name="confirm_password" class="form-input" required oninput="validatePasswordMatch()"><i id="password-match-icon" class="fas fa-check-circle confirm-check"></i><button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')"><i class="fas fa-eye"></i></button></div>
                        <div id="confirmPasswordError" class="error-message">Passwords do not match</div>
                    </div>
                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Donor</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="addActivityModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Activity</h2><button class="close-btn" onclick="closeAddActivityModal()">&times;</button></div>
            <div class="modal-body">
                <form id="addActivityForm" action="activity_management.php" method="POST" enctype="multipart/form-data" onsubmit="return validateActivityForm('add')">
                    <input type="hidden" name="add_activity" value="1">
                    <div class="form-group">
                        <label class="form-label">Activity Cover Image</label>
                        <div class="profile-picture-preview case-preview-box" id="add-act-preview-container"><div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div></div>
                        <div class="file-upload">
                            <label for="add_activity_picture" class="file-upload-label"><i class="fas fa-upload"></i> Choose File</label>
                            <input type="file" id="add_activity_picture" name="activity_picture" accept="image/*" onchange="previewImage(this, 'add-act-preview-container', 'add-act-file-info', 'add-act-file-name')">
                            <div id="add-act-file-info" class="file-info"><span id="add-act-file-name" class="file-name"></span><button type="button" class="file-remove" onclick="removeImage('add_activity_picture', 'add-act-preview-container', 'add-act-file-info')"><i class="fas fa-times"></i></button></div>
                        </div>
                        <span class="form-guide" style="text-align: center;">Recommended size: 800x600px (JPG, PNG). Max 2MB.</span>
                    </div>
                    <div class="form-group"><label class="form-label">Activity Name <span class="required">*</span></label><input type="text" name="activity_name" class="form-input" required placeholder="e.g. Annual Charity Run"></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Start Date <span class="required">*</span></label><input type="date" id="add_start_date" name="start_date" class="form-input" required></div>
                        <div class="form-group">
                            <label class="form-label">End Date <span class="required">*</span></label>
                            <input type="date" id="add_end_date" name="end_date" class="form-input" required onchange="validateDates('add_start_date', 'add_end_date', 'add_date_error')">
                            <div id="add_date_error" class="error-message">End date must be after or equal to Start date.</div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Branch <span class="required">*</span></label><select name="branch_id" class="form-select" required><option value="">Select Branch</option><?php foreach($branches as $branch): ?><option value="<?php echo htmlspecialchars($branch['Branch_ID']); ?>"><?php echo htmlspecialchars($branch['Branch_Name']); ?></option><?php endforeach; ?></select></div>
                        <div class="form-group">
                            <label class="form-label">Target Amount (RM) <span class="required">*</span></label>
                            <input type="number" id="add_target_amount" name="target_amount" class="form-input" step="0.01" min="0" required placeholder="0.00" oninput="validateActAmount('add_target_amount', 'add_amount_error')">
                            <div id="add_amount_error" class="error-message">Amount cannot be negative.</div>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Status <span class="required">*</span></label><select name="activity_status" class="form-select" required><option value="Active">Active</option><option value="Upcoming">Upcoming</option><option value="Completed">Completed</option></select></div>
                    <div class="form-group"><label class="form-label">Address Line 1 <span class="required">*</span></label><input type="text" name="address1" class="form-input" required placeholder="Street address"></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Address Line 2</label><input type="text" name="address2" class="form-input" placeholder="Apartment, unit, etc."></div><div class="form-group"><label class="form-label">Address Line 3</label><input type="text" name="address3" class="form-input" placeholder="Optional"></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Postal Code <span class="required">*</span></label><input type="text" id="add_act_postal_code" name="postal_code" class="form-input" required placeholder="e.g. 50000"></div><div class="form-group"><label class="form-label">City <span class="required">*</span></label><input type="text" id="add_city" name="city" class="form-input" required placeholder="City"></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">State <span class="required">*</span></label><select id="add_act_state" name="state" class="form-select" required><option value="">Select State</option><?php foreach($malaysiaStates as $state): ?><option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option><?php endforeach; ?></select><span class="form-guide">Auto-detected from Postcode</span></div><div class="form-group"><label class="form-label">Country</label><input type="text" name="country" class="form-input" value="Malaysia" readonly></div></div>
                    <div class="form-group"><label class="form-label">Details</label><textarea name="activity_details" class="form-textarea" placeholder="Enter full activity description..."></textarea></div>
                    <div class="form-group"><button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-save"></i> Save Activity</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="addSpecialCaseModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add Special Case</h2><button class="close-btn" onclick="closeAddSpecialCaseModal()">&times;</button></div>
            <div class="modal-body">
                <form id="addSpecialCaseForm" action="special_case_management.php" method="POST" enctype="multipart/form-data" onsubmit="return validateCaseForm('addSpecialCaseForm')">
                    <input type="hidden" name="add_special_case" value="1">
                    <div class="form-group">
                        <label class="form-label">Case Image (Banner) <span class="required">*</span></label>
                        <div class="profile-picture-preview case-preview-box" id="add-case-preview-container"><div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div></div>
                        <div class="file-upload">
                            <label for="add_case_image" class="file-upload-label"><i class="fas fa-upload"></i> Choose File</label>
                            <input type="file" id="add_case_image" name="case_image" accept="image/jpeg,image/png,image/jpg" required onchange="previewImage(this, 'add-case-preview-container', 'add-case-file-info', 'add-case-file-name')">
                            <div id="add-case-file-info" class="file-info"><span id="add-case-file-name" class="file-name"></span><button type="button" class="file-remove" onclick="removeImage('add_case_image', 'add-case-preview-container', 'add-case-file-info')"><i class="fas fa-times"></i></button></div>
                        </div>
                        <div style="text-align: center;"><span class="form-guide" style="font-size: 11px;">Only JPG or PNG files allowed. Max size 2MB recommended.</span></div>
                    </div>
                    <div class="form-group"><label class="form-label">Case Title <span class="required">*</span></label><input type="text" name="case_title" class="form-input" required maxlength="100" placeholder="e.g., Emergency Heart Surgery Fund"><span class="form-guide">Keep it short and catchy. Max 100 characters.</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Target Amount (RM) <span class="required">*</span></label>
                            <input type="number" id="add_case_target_amount" name="target_amount" class="form-input" step="0.01" min="1000" required oninput="validateCaseAmount('add_case_target_amount', 'addCaseAmountError')">
                            <div id="addCaseAmountError" class="error-message">Target amount must be at least RM 1,000.00.</div>
                            <span class="form-guide">Minimum target amount is RM 1,000.00.</span>
                        </div>
                        <div class="form-group"><label class="form-label">Initial Status</label><select name="case_status" id="add_case_status" class="form-select" onchange="toggleAddDate()"><option value="Active">Active</option><option value="Upcoming">Upcoming</option></select></div>
                    </div>
                    <div class="form-group hidden-field" id="add_case_date_container"><label class="form-label">Start Date <span class="required">*</span></label><input type="date" name="start_date" id="add_case_start_date" class="form-input"><span class="form-guide">When will this campaign effectively start?</span></div>
                    <div class="form-group">
                        <label class="form-label">Description / Story <span class="required">*</span></label>
                        <textarea name="case_description" id="add_case_description" class="form-textarea" required minlength="20" placeholder="Explain the background story, the need, and how funds will be used..." oninput="validateDescription('add_case_description', 'addCaseDescError')"></textarea>
                        <div id="addCaseDescError" class="error-message">Description must be at least 20 characters long.</div>
                        <span class="form-guide">Detailed explanation helps donors trust the cause (Min 20 chars).</span>
                    </div>
                    <div class="form-group"><button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-save"></i> Save Case</button></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // --- CHART JS ---
        const ctx = document.getElementById('donationChart');
        const chartDataRaw = <?php echo json_encode($chartData); ?>;

        if(ctx) {
            new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: chartDataRaw.labels,
                    datasets: [{ 
                        label: 'Donations (RM)', 
                        data: chartDataRaw.data, 
                        borderColor: '#f28585', 
                        backgroundColor: 'rgba(242, 133, 133, 0.1)', 
                        borderWidth: 2, 
                        fill: true, 
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#f28585'
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { legend: { display: false } }, 
                    scales: { y: { beginAtZero: true, grid: { borderDash: [5, 5] } }, x: { grid: { display: false } } } 
                }
            });
        }

        // --- EXPORT FUNCTION ---
        function exportChartData() {
            let csvContent = "data:text/csv;charset=utf-8,Date,Amount (RM)\n";
            chartDataRaw.labels.forEach((label, index) => {
                let row = label + "," + chartDataRaw.data[index];
                csvContent += row + "\r\n";
            });
            var encodedUri = encodeURI(csvContent);
            var link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "donation_trends.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // --- SHARED MODAL FUNCTIONS ---
        function previewImage(input, containerId, infoId, nameId) {
            const container = document.getElementById(containerId); const info = document.getElementById(infoId); const nameSpan = document.getElementById(nameId);
            if (input.files && input.files[0]) { const reader = new FileReader(); reader.onload = function(e) { container.innerHTML = `<img src="${e.target.result}" alt="Preview">`; if(info) { info.style.display = 'inline-flex'; nameSpan.textContent = input.files[0].name; } }; reader.readAsDataURL(input.files[0]); }
        }
        function removeImage(inputId, containerId, infoId, originalSrc = null) {
            document.getElementById(inputId).value = ''; if(infoId) document.getElementById(infoId).style.display = 'none'; 
            const container = document.getElementById(containerId); 
            if (originalSrc) { container.innerHTML = `<img src="${originalSrc}" alt="Preview">`; } 
            else { 
                if(containerId.includes('case') || containerId.includes('act')) container.innerHTML = '<div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>';
                else container.innerHTML = '<div style="font-size:48px; color:#ccc;"><i class="fas fa-user"></i></div>';
            } 
        }
        function setupPostcodeState(postcodeId, stateSelectId) {
            const pcInput = document.getElementById(postcodeId); const stateSelect = document.getElementById(stateSelectId); if (!pcInput || !stateSelect) return;
            pcInput.addEventListener('input', function() {
                const val = this.value.replace(/\D/g, '');
                if (val.length >= 2) {
                    const prefix = parseInt(val.substring(0, 2)); let state = "";
                    if (prefix >= 1 && prefix <= 2) state = "Perlis"; else if (prefix >= 5 && prefix <= 9) state = "Kedah"; else if (prefix >= 10 && prefix <= 14) state = "Penang";
                    else if (prefix >= 15 && prefix <= 18) state = "Kelantan"; else if (prefix >= 20 && prefix <= 24) state = "Terengganu"; else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                    else if (prefix >= 30 && prefix <= 36) state = "Perak"; else if (prefix >= 40 && prefix <= 48) state = "Selangor"; else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                    else if (prefix >= 62 && prefix <= 62) state = "Putrajaya"; else if (prefix >= 63 && prefix <= 68) state = "Selangor"; else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                    else if (prefix >= 75 && prefix <= 78) state = "Melaka"; else if (prefix >= 79 && prefix <= 86) state = "Johor"; else if (prefix == 87) state = "Labuan";
                    else if (prefix >= 88 && prefix <= 91) state = "Sabah"; else if (prefix >= 93 && prefix <= 98) state = "Sarawak";
                    if (state) stateSelect.value = state;
                }
            });
        }
        
        // --- MODAL CONTROLS ---
        function openActivitySelectionModal() { document.getElementById('activitySelectionModal').style.display = 'flex'; }
        function closeActivitySelectionModal() { document.getElementById('activitySelectionModal').style.display = 'none'; }
        
        function openAddActivityModal() { closeActivitySelectionModal(); document.getElementById('addActivityModal').style.display = 'flex'; }
        function closeAddActivityModal() { document.getElementById('addActivityModal').style.display = 'none'; document.getElementById('addActivityForm').reset(); document.getElementById('add-act-preview-container').innerHTML = '<div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>'; document.getElementById('add-act-file-info').style.display = 'none'; document.getElementById('add_date_error').style.display='none'; document.getElementById('add_amount_error').style.display='none'; }

        function openAddSpecialCaseModal() { closeActivitySelectionModal(); document.getElementById('addSpecialCaseModal').style.display = 'flex'; toggleAddDate(); }
        function closeAddSpecialCaseModal() { document.getElementById('addSpecialCaseModal').style.display = 'none'; document.getElementById('addSpecialCaseForm').reset(); document.getElementById('add-case-preview-container').innerHTML = '<div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>'; document.getElementById('add-case-file-info').style.display = 'none'; document.getElementById('addCaseAmountError').style.display = 'none'; document.getElementById('addCaseDescError').style.display = 'none'; toggleAddDate(); }

        function openDonationModal() { document.getElementById('addDonationModal').style.display = 'flex'; }
        function closeDonationModal() { document.getElementById('addDonationModal').style.display = 'none'; }

        function openAddDonorModal() { document.getElementById('addDonorModal').style.display = 'flex'; }
        function closeAddDonorModal() { document.getElementById('addDonorModal').style.display = 'none'; document.getElementById('addDonorForm').reset(); document.getElementById('add-preview-container').innerHTML = '<div style="font-size:48px; color:#ccc;"><i class="fas fa-user"></i></div>'; document.getElementById('add-file-info').style.display = 'none'; document.querySelectorAll('.requirement-item').forEach(el => { el.className = 'requirement-item invalid'; el.querySelector('i').className = 'fas fa-times'; }); }

        window.onclick = function(event) {
            if (event.target == document.getElementById('activitySelectionModal')) closeActivitySelectionModal();
            if (event.target == document.getElementById('addActivityModal')) closeAddActivityModal();
            if (event.target == document.getElementById('addSpecialCaseModal')) closeAddSpecialCaseModal();
            if (event.target == document.getElementById('addDonationModal')) closeDonationModal();
            if (event.target == document.getElementById('addDonorModal')) closeAddDonorModal();
        }

        // --- VALIDATION & LOGIC (COPIED FROM SOURCE FILES) ---
        // DONOR
        function setupPhoneInput(inputId) { const input = document.getElementById(inputId); if(!input) return; input.addEventListener('input', function(e) { let val = this.value.replace(/\D/g, ''); if (val.length > 11) val = val.substring(0, 11); let newVal = val; if (val.length > 2) newVal = val.substring(0, 2) + '-' + val.substring(2); this.value = newVal; }); }
        function setupICInput(inputId, dobInputId) { const input = document.getElementById(inputId); const dobInput = document.getElementById(dobInputId); if(!input) return; input.addEventListener('input', function(e) { let val = this.value.replace(/\D/g, ''); if (val.length > 12) val = val.substring(0, 12); let newVal = ''; newVal += val.substring(0, 6); if (val.length > 6) newVal += '-' + val.substring(6, 8); if (val.length > 8) newVal += '-' + val.substring(8, 12); this.value = newVal; if (val.length >= 6 && dobInput) { const yy = parseInt(val.substring(0, 2)); const mm = val.substring(2, 4); const dd = val.substring(4, 6); const prefix = (yy > (new Date().getFullYear() % 100)) ? '19' : '20'; const fullDate = `${prefix}${val.substring(0, 2)}-${mm}-${dd}`; const dateObj = new Date(fullDate); if (!isNaN(dateObj.getTime())) { dobInput.value = fullDate; validateAge(); } } }); }
        function generateStrongPassword(passId, confirmId) { const upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; const lower = "abcdefghijklmnopqrstuvwxyz"; const numbers = "0123456789"; const specials = "!@#$%^&*"; const all = upper + lower + numbers + specials; let password = ""; password += upper[Math.floor(Math.random() * upper.length)]; password += lower[Math.floor(Math.random() * lower.length)]; password += numbers[Math.floor(Math.random() * numbers.length)]; password += specials[Math.floor(Math.random() * specials.length)]; for (let i = 4; i < 12; i++) { password += all[Math.floor(Math.random() * all.length)]; } password = password.split('').sort(() => 0.5 - Math.random()).join(''); document.getElementById(passId).value = password; if(confirmId) document.getElementById(confirmId).value = password; const passInput = document.getElementById(passId); passInput.type = "text"; const toggleBtn = passInput.nextElementSibling; if(toggleBtn) toggleBtn.querySelector('i').className = 'fas fa-eye-slash'; if(confirmId) { const confirmInput = document.getElementById(confirmId); confirmInput.type = "text"; const confirmToggle = confirmInput.nextElementSibling; if(confirmToggle) confirmToggle.querySelector('i').className = 'fas fa-eye-slash'; } if(passId === 'password') validatePasswordRequirements(); if(confirmId) validatePasswordMatch(); }
        function validateName(input) { input.value = input.value.replace(/[^a-zA-Z\s]/g, ''); }
        function validateEmail() { const val = document.getElementById('email').value; const v = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|my)$/i.test(val); document.getElementById('emailError').style.display = v ? 'none' : 'block'; return v; }
        function validateAge() { const d = document.getElementById('dob').value; if(!d) return true; return (new Date().getFullYear() - new Date(d).getFullYear()) >= 18; }
        function validatePasswordRequirements() { const pw = document.getElementById('password').value; const reqs = { lengthReq: pw.length >= 8 && pw.length <= 15, uppercaseReq: /[A-Z]/.test(pw), lowercaseReq: /[a-z]/.test(pw), numberReq: /\d/.test(pw), specialReq: /[!@#$%^&*]/.test(pw) }; let allValid = true; for (const [id, valid] of Object.entries(reqs)) { const el = document.getElementById(id); const icon = el.querySelector('i'); if (valid) { el.className = 'requirement-item valid'; icon.className = 'fas fa-check'; } else { el.className = 'requirement-item invalid'; icon.className = 'fas fa-times'; allValid = false; } } if(document.getElementById('confirm_password').value) validatePasswordMatch(); return allValid; }
        function validatePasswordMatch() { const m = document.getElementById('password').value === document.getElementById('confirm_password').value; document.getElementById('confirmPasswordError').style.display = m ? 'none' : 'block'; document.getElementById('password-match-icon').style.display = m ? 'block' : 'none'; return m; }
        function togglePasswordVisibility(id) { const f = document.getElementById(id); const toggleBtn = f.nextElementSibling; if(f.type === 'password') { f.type = 'text'; if(toggleBtn) toggleBtn.querySelector('i').className = 'fas fa-eye-slash'; } else { f.type = 'password'; if(toggleBtn) toggleBtn.querySelector('i').className = 'fas fa-eye'; } }
        function validateForm() { return validateEmail() && validatePasswordRequirements() && validatePasswordMatch(); }

        // ACTIVITY
        function validateDates(startId, endId, errorId) { const start = document.getElementById(startId).value; const end = document.getElementById(endId).value; const errorDiv = document.getElementById(errorId); if (start && end) { if (new Date(end) < new Date(start)) { errorDiv.style.display = 'block'; return false; } } errorDiv.style.display = 'none'; return true; }
        function validateActAmount(amountId, errorId) { const val = document.getElementById(amountId).value; const errorDiv = document.getElementById(errorId); if(val && parseFloat(val) < 0) { errorDiv.style.display = 'block'; return false; } errorDiv.style.display = 'none'; return true; }
        function validateActivityForm(prefix) { const startId = prefix + '_start_date'; const endId = prefix + '_end_date'; const dateErrId = prefix + '_date_error'; const amountId = prefix + '_target_amount'; const amountErrId = prefix + '_amount_error'; return validateDates(startId, endId, dateErrId) && validateActAmount(amountId, amountErrId); }

        // SPECIAL CASE
        function toggleAddDate() { const status = document.getElementById('add_case_status').value; const container = document.getElementById('add_case_date_container'); const input = document.getElementById('add_case_start_date'); if (status === 'Upcoming') { container.style.display = 'block'; input.required = true; } else { container.style.display = 'none'; input.required = false; } }
        function validateCaseAmount(inputId, errorId) { const val = parseFloat(document.getElementById(inputId).value); const errorEl = document.getElementById(errorId); if (isNaN(val) || val < 1000) { errorEl.style.display = 'block'; return false; } else { errorEl.style.display = 'none'; return true; } }
        function validateDescription(inputId, errorId) { const val = document.getElementById(inputId).value; const errorEl = document.getElementById(errorId); if (val.length < 20) { errorEl.style.display = 'block'; return false; } else { errorEl.style.display = 'none'; return true; } }
        function validateCaseForm(formId) { let amountId = 'add_case_target_amount'; let amountError = 'addCaseAmountError'; let descId = 'add_case_description'; let descError = 'addCaseDescError'; let isValid = true; if (!validateCaseAmount(amountId, amountError)) isValid = false; if (!validateDescription(descId, descError)) isValid = false; return isValid; }

        // Initialize Select2 & Helpers
        $(document).ready(function() {
            $('#donorSelect').select2({ placeholder: "-- Select Existing Donor --", allowClear: true, dropdownParent: $('#addDonationModal') });
            setupPostcodeState('postal_code', 'state_select'); // Donor
            setupPostcodeState('add_act_postal_code', 'add_act_state'); // Activity
            setupPhoneInput('contact'); 
            setupICInput('ic_number', 'dob');
        });

        // Dynamic Dropdowns for Donation Modal
        function updateTargetOptions() {
            const cat = document.getElementById('targetCategory').value;
            const other = document.getElementById('otherInput');
            const br = document.getElementById('selectBranch');
            const act = document.getElementById('selectActivity');
            const cs = document.getElementById('selectCase');
            other.style.display='none'; br.style.display='none'; act.style.display='none'; cs.style.display='none';
            if(cat==='other') other.style.display='block';
            else if(cat==='branch') br.style.display='block';
            else if(cat==='activity') act.style.display='block';
            else if(cat==='case') cs.style.display='block';
        }
    </script>
</body>
</html>
