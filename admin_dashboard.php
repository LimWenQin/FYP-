<?php
// admin_dashboard.php
session_start();

// --- 检查权限：Admin 或 Staff 均可进入 ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- 动态获取当前登录者的信息 (用于 Dashboard 欢迎语) ---
$currentUserId = 0;
$currentUserRole = "";
$currentUserName = "User";
$currentUserPic = null;
$isFirstLogin = 0; // 默认不是首次登录

if (isset($_SESSION['admin_id'])) {
    // 如果是 Admin
    $currentUserId = $_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT Admin_Name, Admin_ProfilePicture, Admin_Role, Admin_IsFirstLogin FROM admin WHERE Admin_ID = ?");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $currentUserName = $row['Admin_Name'];
        $currentUserPic = $row['Admin_ProfilePicture'];
        $currentUserRole = $row['Admin_Role'];
        $isFirstLogin = $row['Admin_IsFirstLogin'];
    }
    $stmt->close();
} elseif (isset($_SESSION['staff_id'])) {
    // 如果是 Staff
    $currentUserId = $_SESSION['staff_id'];
    $stmt = $conn->prepare("SELECT Staff_FullName, Staff_ProfilePicture, Staff_Role, Staff_IsFirstLogin FROM staff WHERE Staff_ID = ?");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $currentUserName = $row['Staff_FullName'];
        $currentUserPic = $row['Staff_ProfilePicture'];
        $currentUserRole = $row['Staff_Role'];
        $isFirstLogin = $row['Staff_IsFirstLogin'];
    }
    $stmt->close();
}

// ==========================================
// 处理强制修改密码逻辑
// ==========================================
$passwordMessage = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_update_password'])) {
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];

    // 后端再次验证正则，防止绕过前端
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>])[A-Za-z\d!@#$%^&*()\-_=+{};:,<.>]{8,15}$/';

    if ($newPass !== $confirmPass) {
        $passwordMessage = "Passwords do not match.";
    } elseif (!preg_match($pattern, $newPass)) {
        $passwordMessage = "Password does not meet the security requirements.";
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        
        if (isset($_SESSION['admin_id'])) {
            // 更新 Admin
            $updateStmt = $conn->prepare("UPDATE admin SET Admin_Password = ?, Admin_IsFirstLogin = 0 WHERE Admin_ID = ?");
            $updateStmt->bind_param("si", $newHash, $currentUserId);
        } elseif (isset($_SESSION['staff_id'])) {
            // 更新 Staff
            $updateStmt = $conn->prepare("UPDATE staff SET Staff_Password = ?, Staff_IsFirstLogin = 0 WHERE Staff_ID = ?");
            $updateStmt->bind_param("si", $newHash, $currentUserId);
        }

        if ($updateStmt->execute()) {
            $isFirstLogin = 0; // 更新当前变量，关闭模态框
            $passwordMessage = "Password updated successfully! Welcome.";
        } else {
            $passwordMessage = "Error updating password. Please try again.";
        }
        $updateStmt->close();
    }
}

// ==========================================
// 1. HANDLE ADD DONATION LOGIC
// ==========================================
$msg = "";
$msgType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_donation_submit'])) {
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
$allBranches = $conn->query("SELECT Branch_ID, Branch_Name FROM branch ORDER BY Branch_Name ASC");

$malaysiaStates = [ 'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu' ];
$branches = []; 
while($bRow = $allBranches->fetch_assoc()) $branches[] = $bRow; 

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
    <title>Dashboard - Love Bridge</title>
    
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
        
        .amount-right { text-align: right; }
        .redemption-table th, .redemption-table td { text-align: center !important; }

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
        
        /* Floating Alert */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1300; display: flex; align-items: center; gap: 10px; animation: slideIn 0.5s ease-out; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Select2 Fix */
        .select2-container .select2-selection--single { height: 42px !important; border: 1px solid #ddd !important; border-radius: 5px !important; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
        
        /* --- Enhanced Force Password Change Modal Styles --- */
        .force-modal { 
            display: <?php echo ($isFirstLogin == 1) ? 'flex' : 'none'; ?>; 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.9); z-index: 9999; 
            justify-content: center; align-items: center; 
        }
        .force-modal-content { 
            background: white; width: 450px; padding: 40px; 
            border-radius: 12px; text-align: center; 
            box-shadow: 0 15px 40px rgba(0,0,0,0.5); 
        }
        .force-modal h2 { color: #333; margin-bottom: 10px; font-size: 22px; }
        .force-modal p { color: #666; margin-bottom: 25px; font-size: 14px; line-height: 1.5; }
        
        /* New Password Input Group with Toggle */
        .fp-input-group {
            position: relative;
            display: flex;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            margin-bottom: 10px;
        }
        .fp-input-group input {
            flex: 1;
            border-radius: 8px 0 0 8px;
            border: 1px solid #ddd;
            border-right: none;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .fp-input-group input:focus {
            border-color: #F28585;
            box-shadow: 0 0 0 3px rgba(242, 133, 133, 0.1);
            z-index: 2;
        }
        .fp-input-group .toggle-btn {
            border: 1px solid #ddd;
            border-left: none;
            border-radius: 0 8px 8px 0;
            background: #f9f9f9;
            cursor: pointer;
            padding: 0 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            transition: background 0.2s;
        }
        .fp-input-group .toggle-btn:hover {
            background-color: #eee;
        }

        /* Requirements List */
        .fp-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: left;
            margin-bottom: 20px;
            font-size: 12px;
            border: 1px solid #eee;
        }
        .fp-req-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            color: #dc3545; /* Default Red */
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .fp-req-item i {
            width: 20px;
            text-align: center;
            margin-right: 5px;
        }
        /* Green state for valid items */
        .fp-req-item.valid {
            color: #28a745;
        }

        /* Match Message */
        .match-message {
            font-size: 12px;
            text-align: left;
            margin-top: 5px;
            display: block;
            min-height: 18px;
        }
        .match-success { color: #28a745; }
        .match-error { color: #dc3545; }

        .force-btn { 
            width: 100%; padding: 12px; background: #F28585; 
            color: white; border: none; border-radius: 6px; 
            font-size: 16px; font-weight: 600; cursor: pointer; 
            transition: 0.3s; margin-top: 15px;
        }
        .force-btn:hover { background: #e07474; }
        .alert-message { 
            display: block; margin-bottom: 15px; font-size: 14px; font-weight: 500; 
        }

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
        <script>
            // 5秒后自动消失
            setTimeout(() => { 
                const el = document.getElementById('floatingAlert');
                if(el) el.style.display='none'; 
            }, 5000);
        </script>
    <?php endif; ?>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>System Overview</h1>
                <p>Welcome back, <?php echo htmlspecialchars($currentUserName); ?>.</p>
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

    <div class="force-modal" id="forcePasswordModal">
        <div class="force-modal-content">
            <i class="fas fa-lock" style="font-size: 40px; color: #F28585; margin-bottom: 20px;"></i>
            <h2>Security Update Required</h2>
            <p>For the security of your account, you must update your password before proceeding further.</p>
            
            <?php if (!empty($passwordMessage)): ?>
                <span class="alert-message" style="color: <?php echo strpos($passwordMessage, 'successfully') !== false ? 'green' : 'red'; ?>;">
                    <?php echo $passwordMessage; ?>
                </span>
                <?php if (strpos($passwordMessage, 'successfully') !== false): ?>
                    <script>setTimeout(() => { document.querySelector('.force-modal').style.display = 'none'; }, 1500);</script>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST" action="admin_dashboard.php" onsubmit="return validateForceForm()">
                <input type="hidden" name="force_update_password" value="1">
                
                <div class="fp-input-group">
                    <input type="password" name="new_password" id="fp_new_password" placeholder="New Password (Min 8 chars)" oninput="validateForceRequirements()">
                    <button type="button" class="toggle-btn" onclick="toggleForceVisibility('fp_new_password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <div class="fp-requirements">
                    <div id="fp_lengthReq" class="fp-req-item"><i class="fas fa-times"></i> Min 8-15 characters</div>
                    <div id="fp_uppercaseReq" class="fp-req-item"><i class="fas fa-times"></i> At least 1 Uppercase (A-Z)</div>
                    <div id="fp_lowercaseReq" class="fp-req-item"><i class="fas fa-times"></i> At least 1 Lowercase (a-z)</div>
                    <div id="fp_numberReq" class="fp-req-item"><i class="fas fa-times"></i> At least 1 Number (0-9)</div>
                    <div id="fp_specialReq" class="fp-req-item"><i class="fas fa-times"></i> At least 1 Symbol (!@#...)</div>
                </div>

                <div class="fp-input-group">
                    <input type="password" name="confirm_password" id="fp_confirm_password" placeholder="Confirm New Password" oninput="validateForceMatch()">
                    <button type="button" class="toggle-btn" onclick="toggleForceVisibility('fp_confirm_password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <span id="fp_match_msg" class="match-message"></span>

                <button type="submit" class="force-btn" id="fp_submit_btn">Update Password</button>
            </form>
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
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Amount (RM) <span style="color:red">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="1" required>
                </div>
                <button type="submit" name="add_donation_submit" class="btn-submit">Confirm Donation</button>
            </form>
        </div>
    </div>

    <div class="modal" id="addDonorModal">
         <div class="modal-content">
             <div class="modal-header"><h2>Add New Donor</h2><button class="close-btn" onclick="closeAddDonorModal()">&times;</button></div>
             <div class="modal-body">
                 <form id="addDonorForm" action="admin_donor_page.php" method="POST" enctype="multipart/form-data">
                     <input type="hidden" name="add_donor" value="1">
                     <p style="text-align:center; color:#666;">(Form content managed in Donor Page)</p>
                     <div class="form-group"><button type="submit" class="btn btn-primary" onclick="window.location.href='admin_donor_page.php'; return false;">Go to Donor Management</button></div>
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

        // --- MODAL CONTROLS ---
        function openActivitySelectionModal() { document.getElementById('activitySelectionModal').style.display = 'flex'; }
        function closeActivitySelectionModal() { document.getElementById('activitySelectionModal').style.display = 'none'; }
        
        function openAddActivityModal() { closeActivitySelectionModal(); window.location.href='activity_management.php?open_add=1'; }
        function openAddSpecialCaseModal() { closeActivitySelectionModal(); window.location.href='special_case_management.php?open_add=1'; }

        function openDonationModal() { document.getElementById('addDonationModal').style.display = 'flex'; }
        function closeDonationModal() { document.getElementById('addDonationModal').style.display = 'none'; }

        function openAddDonorModal() { window.location.href='admin_donor_page.php?open_add=1'; } 

        window.onclick = function(event) {
            if (event.target == document.getElementById('activitySelectionModal')) closeActivitySelectionModal();
            if (event.target == document.getElementById('addDonationModal')) closeDonationModal();
        }

        // Initialize Select2 & Helpers
        $(document).ready(function() {
            $('#donorSelect').select2({ placeholder: "-- Select Existing Donor --", allowClear: true, dropdownParent: $('#addDonationModal') });
        });

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

        // ==========================================
        // Force Password Change Logic (JS)
        // ==========================================
        function toggleForceVisibility(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        function validateForceRequirements() {
            const pw = document.getElementById('fp_new_password').value;
            const items = {
                lengthReq: pw.length >= 8 && pw.length <= 15,
                uppercaseReq: /[A-Z]/.test(pw),
                lowercaseReq: /[a-z]/.test(pw),
                numberReq: /\d/.test(pw),
                specialReq: /[!@#$%^&*()\-=_+{}[\]:;"'<>,.?/|\\]/.test(pw)
            };

            let allValid = true;
            for (const [key, isValid] of Object.entries(items)) {
                const el = document.getElementById('fp_' + key);
                const icon = el.querySelector('i');
                if (isValid) {
                    el.classList.add('valid');
                    icon.className = 'fas fa-check';
                } else {
                    el.classList.remove('valid');
                    icon.className = 'fas fa-times';
                    allValid = false;
                }
            }
            // Trigger match check as well
            if(document.getElementById('fp_confirm_password').value) validateForceMatch();
            
            return allValid;
        }

        function validateForceMatch() {
            const p1 = document.getElementById('fp_new_password').value;
            const p2 = document.getElementById('fp_confirm_password').value;
            const msg = document.getElementById('fp_match_msg');

            if (!p2) {
                msg.textContent = "";
                return false;
            }

            if (p1 === p2) {
                msg.textContent = "Passwords match!";
                msg.className = "match-message match-success";
                return true;
            } else {
                msg.textContent = "Passwords do not match.";
                msg.className = "match-message match-error";
                return false;
            }
        }

        function validateForceForm() {
            const reqValid = validateForceRequirements();
            const matchValid = validateForceMatch();
            if (!reqValid || !matchValid) {
                alert("Please ensure all requirements are met and passwords match.");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>