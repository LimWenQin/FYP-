<?php
// payment_management.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- Get Current Admin Info ---
$currentAdminId = $_SESSION['admin_id'];
$adminSql = "SELECT Admin_Name, Admin_Role, Admin_ProfilePicture FROM admin WHERE Admin_ID = $currentAdminId";
$adminResult = $conn->query($adminSql);

if ($adminResult && $adminResult->num_rows > 0) {
    $adminData = $adminResult->fetch_assoc();
    $adminName = $adminData['Admin_Name'];
    $adminPosition = $adminData['Admin_Role']; 
    $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
} else {
    $adminName = "Admin";
    $adminPosition = "Admin"; 
    $adminProfilePicture = null;
}

// ==========================================
// 0. HANDLE EXPORT TO EXCEL
// ==========================================
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    // 只保留 income 的导出逻辑
    if ($type == 'income') {
        $filename = "report_income_" . date('Ymd') . ".xls";
        
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo '<table border="1">';
        echo '<tr><th>Ref ID</th><th>Date</th><th>Donor Name</th><th>Donor Email</th><th>Target</th><th>Amount</th><th>Method</th><th>Status</th></tr>';
        $sql = "SELECT o.Order_TXN_Ref, o.Order_Created_At, d.Donor_Name, d.Donor_Email, o.Order_Amount, o.Order_PaymentMethod, o.Order_PaymentStatus,
                b.Branch_Name, a.Activity_Name, s.Case_Title
                FROM orders o
                JOIN donor d ON o.Donor_ID = d.Donor_ID
                LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
                LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
                LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
                ORDER BY o.Order_Created_At DESC";
        $res = $conn->query($sql);
        while($row = $res->fetch_assoc()) {
            $target = "General (Other)";
            if($row['Branch_Name']) $target = "Branch: ".$row['Branch_Name'];
            elseif($row['Activity_Name']) $target = "Activity: ".$row['Activity_Name'];
            elseif($row['Case_Title']) $target = "Case: ".$row['Case_Title'];
            
            echo "<tr>
                <td>{$row['Order_TXN_Ref']}</td>
                <td>{$row['Order_Created_At']}</td>
                <td>{$row['Donor_Name']}</td>
                <td>{$row['Donor_Email']}</td>
                <td>{$target}</td>
                <td>{$row['Order_Amount']}</td>
                <td>{$row['Order_PaymentMethod']}</td>
                <td>{$row['Order_PaymentStatus']}</td>
            </tr>";
        }
        echo '</table>';
        exit();
    }
}

// ==========================================
// 1. HANDLE ADD DONATION (Select Existing Donor)
// ==========================================
$msg = "";
$msgType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_donation_submit'])) {
    
    // 1. Get Selected Donor ID
    $donor_id = $_POST['donor_id'];
    
    // 2. Donation Info
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method']; 
    $donation_frequency = $_POST['donation_frequency']; 
    
    // 3. Target Logic
    $target_category = $_POST['target_category']; 
    $branch_id = ($target_category == 'branch') ? $_POST['target_branch_id'] : null;
    $activity_id = ($target_category == 'activity') ? $_POST['target_activity_id'] : null;
    $case_id = ($target_category == 'case') ? $_POST['target_case_id'] : null;

    $conn->begin_transaction();
    try {
        // A. Fetch Donor Details from Database (Since we only got ID)
        $donorQ = $conn->query("SELECT * FROM donor WHERE Donor_ID = '$donor_id'");
        if ($donorQ->num_rows == 0) {
            throw new Exception("Selected donor not found.");
        }
        $donorData = $donorQ->fetch_assoc();
        
        // Data for Orders table
        $d_name = $donorData['Donor_Name'];
        $d_email = $donorData['Donor_Email'];
        $d_phone = $donorData['Donor_ContactNumber'];
        $d_ic = $donorData['Donor_ICNumber'];

        // B. Create Payment Record
        $txn_ref = "MAN-IN-" . date('YmdHis') . "-" . rand(100, 999); 
        $paySql = "INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) 
                   VALUES (?, 'Success', ?, ?, NOW(), 'Manual Entry', 'N/A', NOW())";
        $stmtPay = $conn->prepare($paySql);
        
        $stmtPay->bind_param("ssd", $payment_method, $txn_ref, $amount);
        $stmtPay->execute();
        $payment_insert_id = $conn->insert_id;
        $stmtPay->close();

        // C. Create Order Record
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

        // D. Update Points
        if ($points_earned > 0) {
            $ptCheck = $conn->query("SELECT * FROM point WHERE Donor_ID = '$donor_id'");
            if ($ptCheck->num_rows > 0) {
                $conn->query("UPDATE point SET Points_Total = Points_Total + $points_earned, Points_Earned = Points_Earned + $points_earned, Points_Updated_At = NOW() WHERE Donor_ID = '$donor_id'");
            } else {
                $conn->query("INSERT INTO point (Points_Earned, Points_Total, Points_Updated_At, Donor_ID) VALUES ($points_earned, $points_earned, NOW(), '$donor_id')");
            }
        }

        $conn->commit();
        $msg = "Donation successfully recorded for " . htmlspecialchars($d_name) . "!";
        $msgType = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
        $msgType = "error";
    }
}

// --- Fetch Data for Dropdowns ---
// Donors List for Select Box
$donorsList = $conn->query("SELECT Donor_ID, Donor_Name, Donor_Email, Donor_ICNumber FROM donor ORDER BY Donor_Name ASC");

$branchesList = $conn->query("SELECT Branch_ID, Branch_Name FROM branch WHERE Branch_OperationalStatus = 'Open'");
$activitiesList = $conn->query("SELECT Activity_ID, Activity_Name FROM activity WHERE Activity_Status != 'Cancelled' AND Activity_Status != 'Completed' ORDER BY Activity_StartDate DESC");
$casesList = $conn->query("SELECT Case_ID, Case_Title FROM special_case WHERE Case_Status = 'Active'");

// --- Stats Functions ---
$totalRevenue = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success')")->fetch_assoc()['total'] ?? 0;
$recurringRevenue = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success') AND Order_Type = 'Recurring'")->fetch_assoc()['total'] ?? 0;
$totalPoints = floor($totalRevenue / 10);
$pendingCount = $conn->query("SELECT COUNT(*) as count FROM orders WHERE Order_PaymentStatus = 'Pending'")->fetch_assoc()['count'];

// ==========================================
// PAGINATION LOGIC (INCOME ONLY)
// ==========================================
$limit = 6; // Income per page

// Income Pagination
$page_inc = isset($_GET['page_inc']) && is_numeric($_GET['page_inc']) ? (int)$_GET['page_inc'] : 1;
if ($page_inc < 1) $page_inc = 1;
$offset_inc = ($page_inc - 1) * $limit;

$sql_inc_count = "SELECT COUNT(*) as total FROM orders";
$total_inc_recs = $conn->query($sql_inc_count)->fetch_assoc()['total'];
$total_pages_inc = ceil($total_inc_recs / $limit);

$sql_inc = "SELECT o.Order_ID, o.Order_TXN_Ref, o.Order_Type, d.Donor_Name, d.Donor_Email, o.Order_Amount, o.Order_Created_At, o.Order_PaymentMethod, o.Order_PaymentStatus,
            b.Branch_Name, a.Activity_Name, s.Case_Title
            FROM orders o
            JOIN donor d ON o.Donor_ID = d.Donor_ID
            LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
            LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
            LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
            ORDER BY o.Order_Created_At DESC LIMIT $offset_inc, $limit";
$recentTransactions = $conn->query($sql_inc);

$start_inc = ($total_inc_recs > 0) ? $offset_inc + 1 : 0;
$end_inc = min($offset_inc + $limit, $total_inc_recs);

// 3. Top Donors (Limit 10)
$topDonors = $conn->query("SELECT d.Donor_Name, d.Donor_ProfilePicture, SUM(o.Order_Amount) as total_donated
                           FROM orders o JOIN donor d ON o.Donor_ID = d.Donor_ID
                           WHERE o.Order_PaymentStatus IN ('Completed', 'Success')
                           GROUP BY o.Donor_ID ORDER BY total_donated DESC LIMIT 10");

// Chart Data
function getMonthlyRevenueChartData($conn) {
    $data = [];
    for ($i = 5; $i >= 0; $i--) {
        $monthLabel = date('M Y', strtotime("-$i months"));
        $monthStart = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime("-$i months"));
        $sql = "SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success') AND Order_Created_At BETWEEN '$monthStart 00:00:00' AND '$monthEnd 23:59:59'";
        $data['labels'][] = $monthLabel;
        $data['revenue'][] = $conn->query($sql)->fetch_assoc()['total'] ?? 0;
    }
    return $data;
}
$chartData = getMonthlyRevenueChartData($conn);

// ---------------------------------------------------------
// ⚠️ 重要：删除了 $conn->close(); 以修复 sidebar 报错的问题
// ---------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - Love Bridge</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    
    <style>
        :root {
            --primary: #F28585; 
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --dark: #333;
            --info: #17a2b8;
            --warning: #ffc107;
        }
        body { background-color: #f4f6f9; }
        .dashboard-content { padding: 25px; }

        /* Floating Alert Styles */
        .floating-alert { 
            position: fixed; top: 20px; right: 20px; padding: 15px 20px; 
            border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); 
            z-index: 9999; display: flex; align-items: center; gap: 15px; 
            animation: slideIn 0.5s ease-out;
            min-width: 300px;
        }
        .floating-alert-success { background: white; color: var(--success); border-left: 5px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 5px solid var(--danger); }
        .floating-alert i { font-size: 20px; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* --- 1. Header Styling --- */
        .page-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .welcome-text h1 { font-size: 28px; color: #333; font-weight: 700; margin-bottom: 5px; margin-top: 0; }
        .welcome-text p { color: #777; font-size: 14px; margin: 0; }
        
        .header-btn-group { display: flex; gap: 10px; }
        
        /* Buttons */
        .btn-custom {
            background-color: var(--primary); 
            color: white; 
            border: none;
            padding: 10px 20px;
            border-radius: 5px; 
            font-size: 14px;
            font-weight: 600; 
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.3s;
            text-decoration: none;
            height: 42px; 
            min-width: 180px; 
        }
        .btn-custom:hover { background-color: #e07474; color: white; }
        
        .btn-export { background-color: #28a745; height: 36px; padding: 5px 15px; font-size: 12px; min-width: auto; color: white !important; }
        .btn-export:hover { background-color: #218838; }

        /* Stats Cards */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #6c757d; margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; margin: 0; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        
        .text-success { color: var(--success); }
        .text-info { color: var(--info); }
        .text-warning { color: var(--warning); }
        .text-danger { color: var(--danger); }

        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        /* --- Grid Layout --- */
        .payment-content-grid {
            display: grid;
            grid-template-columns: 2.5fr 1fr; 
            gap: 25px;
            margin-bottom: 30px;
            align-items: stretch; 
        }

        .left-column {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* --- Content Card & Fixed Height for Lists --- */
        .content-card {
            background: white; border-radius: 12px; padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); border: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column; 
            height: 100%; 
        }

        .table-responsive {
            min-height: 420px; 
            display: flex;
            flex-direction: column;
            justify-content: space-between; 
        }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-header h2 { font-size: 18px; font-weight: 700; color: #333; margin: 0; }

        /* Tables */
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .custom-table thead th { color: #8898aa; font-weight: 600; text-transform: uppercase; font-size: 11px; padding: 0 15px 10px 15px; text-align: left; }
        .custom-table tbody tr { background: white; transition: transform 0.2s; }
        .custom-table tbody tr:hover { background-color: #fcfcfc; }
        .custom-table td { padding: 15px; vertical-align: middle; color: #525f7f; font-size: 13px; border-top: 1px solid #f5f5f5; border-bottom: 1px solid #f5f5f5; }
        .custom-table td:first-child { border-left: 1px solid #f5f5f5; border-radius: 8px 0 0 8px; }
        .custom-table td:last-child { border-right: 1px solid #f5f5f5; border-radius: 0 8px 8px 0; }

        .badge { padding: 5px 10px; border-radius: 20px; font-size: 10px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff8e1; color: #ffca28; }
        .badge-failed { background: #fee2e2; color: #f5365c; }
        
        /* New Action Button Style */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background-color: #e3f2fd;
            color: #1976d2;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid #bbdefb;
        }
        .btn-action:hover {
            background-color: #1976d2;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        /* Pagination Controls */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: auto; }
        .pagination-info { font-size: 13px; color: #8898aa; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 6px 12px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 13px; }
        .pagination-btn:hover { background-color: #e9ecef; }
        .pagination-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; pointer-events: none; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 25px; border-radius: 12px; width: 600px; max-width: 90%; position: relative; max-height: 90vh; overflow-y: auto;}
        .close-modal { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa; }
        .close-modal:hover { color: black; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; background: #f9f9f9; }
        .form-control:focus { border-color: var(--primary); outline: none; background: white; }
        .row-group { display: flex; gap: 15px; }
        .row-group .form-group { flex: 1; }
        
        /* Form Guide Text */
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }

        /* REGISTER LINK BOX */
        .register-hint {
            margin-top: 5px;
            padding: 10px;
            background-color: #f0f8ff;
            border-left: 3px solid #17a2b8;
            font-size: 13px;
            color: #555;
            border-radius: 4px;
        }
        .register-hint a {
            color: #F28585; /* Pink */
            font-weight: 700;
            text-decoration: underline;
            margin-left: 5px;
        }
        .register-hint a:hover { color: #d65f5f; }

        /* Top Donors */
        .rank-box { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 10px; border-radius: 50%; color: white; }
        .rank-1 { background: #FFD700; } .rank-2 { background: #C0C0C0; } .rank-3 { background: #CD7F32; } .rank-other { background: #e9ecef; color: #8898aa; }

        /* Custom Style for Select2 to match theme */
        .select2-container .select2-selection--single {
            height: 42px !important;
            border: 1px solid #ddd !important;
            border-radius: 6px !important;
            display: flex;
            align-items: center;
            background-color: #f9f9f9;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
        }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: #F28585 !important;
            color: white !important;
        }

        @media (max-width: 1200px) { .payment-content-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <?php if (!empty($msg)): ?>
        <div class="floating-alert <?php echo $msgType == 'success' ? 'floating-alert-success' : 'floating-alert-danger'; ?>" id="floatingAlert">
            <i class="fas <?php echo $msgType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <div><?php echo $msg; ?></div>
        </div>
    <?php endif; ?>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            
            <div class="page-header-flex">
                <div class="welcome-text">
                    <h1>Payment Management</h1>
                    <p>Monitor revenue and record donations.</p>
                </div>
                <div class="header-btn-group">
                    <button class="btn-custom" onclick="openDonationModal()">
                        <i class="fas fa-plus-circle"></i> Add Donation
                    </button>
                    </div>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL REVENUE</h3>
                        <h2>RM <?php echo number_format($totalRevenue, 2); ?></h2>
                        <p class="text-success"><i class="fas fa-arrow-up"></i> Lifetime</p>
                    </div>
                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.2); color: #28a745;"><i class="fas fa-wallet"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>RECURRING</h3>
                        <h2>RM <?php echo number_format($recurringRevenue, 2); ?></h2>
                        <p class="text-info">Recurring</p>
                    </div>
                    <div class="stat-icon" style="background: rgba(23, 162, 184, 0.2); color: #17a2b8;"><i class="fas fa-sync"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>POINTS ISSUED</h3>
                        <h2><?php echo number_format($totalPoints); ?></h2>
                        <p class="text-warning">RM 10 = 1 Point</p>
                    </div>
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.2); color: #ffc107;"><i class="fas fa-star"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>PENDING</h3>
                        <h2><?php echo $pendingCount; ?></h2>
                        <p class="text-danger">Needs Action</p>
                    </div>
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.2); color: #dc3545;"><i class="fas fa-exclamation"></i></div>
                </div>
            </div>

            <div class="payment-content-grid">
                
                <div class="left-column">
                    
                    <div class="content-card">
                        <div class="section-header">
                            <h2>Transaction History (Income)</h2>
                            <a href="?export=income" class="btn-custom btn-export"><i class="fas fa-download"></i> Export Data</a>
                        </div>

                        <div class="table-responsive">
                            <div style="overflow-x: auto;">
                                <table class="custom-table">
                                    <thead><tr><th>Ref / Date</th><th>Donor</th><th>Target</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php if($recentTransactions->num_rows > 0): ?>
                                            <?php while($txn = $recentTransactions->fetch_assoc()): 
                                                $targetName = "General (Other)";
                                                if ($txn['Case_Title']) $targetName = "Case: " . $txn['Case_Title'];
                                                elseif ($txn['Activity_Name']) $targetName = "Act: " . $txn['Activity_Name'];
                                                elseif ($txn['Branch_Name']) $targetName = "Branch: " . $txn['Branch_Name'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:600; color:#333;"><?php echo $txn['Order_TXN_Ref']; ?></div>
                                                    <div style="font-size:11px; color:#888;"><?php echo date('M d, Y', strtotime($txn['Order_Created_At'])); ?></div>
                                                </td>
                                                <td>
                                                    <div style="font-weight:600;"><?php echo htmlspecialchars($txn['Donor_Name']); ?></div>
                                                    <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($txn['Donor_Email']); ?></div>
                                                </td>
                                                <td><span style="font-size:11px; display:block; max-width:120px;"><?php echo htmlspecialchars($targetName); ?></span></td>
                                                <td style="font-weight:700; color:#28a745;">RM <?php echo number_format($txn['Order_Amount'], 2); ?></td>
                                                <td><span class="badge <?php echo ($txn['Order_PaymentStatus']=='Success')?'badge-success':'badge-pending'; ?>"><?php echo $txn['Order_PaymentStatus']; ?></span></td>
                                                <td>
                                                    <a href="admin_payment_details.php?id=<?php echo $txn['Order_ID']; ?>" class="btn-action" target="_blank" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">No transaction records found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="pagination-container">
                                <div class="pagination-info">Showing <?php echo $start_inc; ?> - <?php echo $end_inc; ?> of <?php echo $total_inc_recs; ?></div>
                                <div class="pagination-controls">
                                    <?php if ($page_inc > 1): ?>
                                        <a href="?page_inc=<?php echo $page_inc-1; ?>" class="pagination-btn">Previous</a>
                                    <?php else: ?>
                                        <span class="pagination-btn disabled">Previous</span>
                                    <?php endif; ?>

                                    <?php for($i=1; $i<=$total_pages_inc; $i++): ?>
                                        <a href="?page_inc=<?php echo $i; ?>" class="pagination-btn <?php echo ($i==$page_inc)?'active':''; ?>"><?php echo $i; ?></a>
                                    <?php endfor; ?>

                                    <?php if ($page_inc < $total_pages_inc): ?>
                                        <a href="?page_inc=<?php echo $page_inc+1; ?>" class="pagination-btn">Next</a>
                                    <?php else: ?>
                                        <span class="pagination-btn disabled">Next</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div>

                <div class="right-column content-card">
                    <div class="section-header">
                        <h2><i class="fas fa-trophy" style="color: #FFD700;"></i> Top 10 Donors</h2>
                    </div>
                    
                    <div class="top-donors-container">
                        <table class="custom-table">
                            <thead><tr><th>Rank</th><th>Donor</th><th style="text-align:right;">Total</th></tr></thead>
                            <tbody>
                                <?php $rank = 1; while($donor = $topDonors->fetch_assoc()): ?>
                                <tr>
                                    <td width="30"><div class="rank-box <?php echo $rank <= 3 ? 'rank-'.$rank : 'rank-other'; ?>"><?php echo $rank; ?></div></td>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <?php if($donor['Donor_ProfilePicture']): ?>
                                                <img src="<?php echo $donor['Donor_ProfilePicture']; ?>" style="width:25px; height:25px; border-radius:50%; margin-right:8px;">
                                            <?php else: ?>
                                                <div style="width:25px; height:25px; border-radius:50%; background:#eee; margin-right:8px; text-align:center; line-height:25px; font-size:10px;"><i class="fas fa-user"></i></div>
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($donor['Donor_Name']); ?></span>
                                        </div>
                                    </td>
                                    <td style="text-align:right; font-weight:bold; color:#28a745;">RM <?php echo number_format($donor['total_donated']); ?></td>
                                </tr>
                                <?php $rank++; endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="content-card chart-section">
                <div class="section-header"><h2>Revenue Analytics</h2></div>
                <div style="height: 350px;"><canvas id="revenueChart"></canvas></div>
            </div>

        </div>
    </div>

    <div id="addDonationModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDonationModal()">&times;</span>
            <h2 style="color: black; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Add New Donation</h2>
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
                    
                    <div class="register-hint">
                        <i class="fas fa-info-circle"></i> 
                        Donor doesn't have an account? 
                        <a href="admin_donor_page.php">Register Donor Here</a>
                    </div>
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
                    <span class="form-guide">Total donation amount in MYR.</span>
                </div>

                <button type="submit" name="add_donation_submit" class="btn-custom" style="width:100%; justify-content:center;">Confirm Donation</button>
            </form>
        </div>
    </div>

    <script>
        // Initialize Select2 when document is ready
        $(document).ready(function() {
            $('#donorSelect').select2({
                placeholder: "-- Select Existing Donor --",
                allowClear: true,
                dropdownParent: $('#addDonationModal') // Important for proper focus in modal
            });
        });

        // Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartData['labels']); ?>,
                datasets: [{
                    label: 'Revenue (RM)',
                    data: <?php echo json_encode($chartData['revenue']); ?>,
                    borderColor: '#F28585', backgroundColor: 'rgba(242, 133, 133, 0.1)',
                    fill: true, tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Auto hide floating alert after 5 seconds
        setTimeout(() => {
            const alert = document.getElementById('floatingAlert');
            if(alert) {
                alert.style.display = 'none';
            }
        }, 5000);

        // Modals
        function openDonationModal() { document.getElementById('addDonationModal').style.display = 'block'; }
        function closeDonationModal() { document.getElementById('addDonationModal').style.display = 'none'; }
        
        window.onclick = function(e) {
            if(e.target == document.getElementById('addDonationModal')) closeDonationModal();
        }

        // Dynamic Dropdowns for Donation
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

        // Expense Related JS Removed
    </script>
</body>
</html>