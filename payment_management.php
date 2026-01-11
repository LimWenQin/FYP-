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
    $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
}

// ==========================================
// 1. HANDLE EXPORT: INCOME
// ==========================================
if (isset($_GET['export']) && $_GET['export'] == 'income') {
    $filename = "report_transaction_history_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr>
            <th>Order ID</th><th>Ref No</th><th>Date & Time</th>
            <th>Donor ID</th><th>Donor Name</th><th>Donor Email</th><th>Donor Contact</th><th>Donor IC</th>
            <th>Target Type</th><th>Target Name</th>
            <th>Amount (RM)</th><th>Payment Method</th><th>Payment Status</th><th>Admin Status</th>
          </tr>';
    
    $sql = "SELECT o.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_ICNumber,
            b.Branch_Name, a.Activity_Name, s.Case_Title
            FROM orders o
            JOIN donor d ON o.Donor_ID = d.Donor_ID
            LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
            LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
            LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
            ORDER BY o.Order_Created_At DESC";
            
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) {
        $targetType = "General";
        $targetName = "-";
        if($row['Branch_Name']) { $targetType = "Branch"; $targetName = $row['Branch_Name']; }
        elseif($row['Activity_Name']) { $targetType = "Activity"; $targetName = $row['Activity_Name']; }
        elseif($row['Case_Title']) { $targetType = "Case"; $targetName = $row['Case_Title']; }
        
        echo "<tr>
            <td>{$row['Order_ID']}</td>
            <td>{$row['Order_TXN_Ref']}</td>
            <td>{$row['Order_Created_At']}</td>
            <td>{$row['Donor_ID']}</td>
            <td>{$row['Donor_Name']}</td>
            <td>{$row['Donor_Email']}</td>
            <td>'{$row['Donor_ContactNumber']}</td>
            <td>'{$row['Donor_ICNumber']}</td>
            <td>{$targetType}</td>
            <td>{$targetName}</td>
            <td>{$row['Order_Amount']}</td>
            <td>{$row['Order_PaymentMethod']}</td>
            <td>{$row['Order_PaymentStatus']}</td>
            <td>{$row['Order_Admin_Status']}</td>
        </tr>";
    }
    echo '</table>';
    exit();
}

// ==========================================
// 2. HANDLE EXPORT: E-WALLET
// ==========================================
if (isset($_GET['export']) && $_GET['export'] == 'ewallet') {
    $filename = "report_ewallet_log_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr>
            <th>Txn ID</th><th>Date & Time</th>
            <th>Donor ID</th><th>Donor Name</th><th>Donor Email</th><th>Donor Contact</th>
            <th>Current Wallet Balance (RM)</th>
            <th>Transaction Type</th><th>Amount (RM)</th>
            <th>Description</th><th>Linked Order Ref</th>
          </tr>';
    
    $sql = "SELECT w.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_Wallet, o.Order_TXN_Ref
            FROM wallet_transaction w
            JOIN donor d ON w.Donor_ID = d.Donor_ID
            LEFT JOIN orders o ON w.Order_ID = o.Order_ID
            ORDER BY w.Created_At DESC";
            
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) {
        echo "<tr>
            <td>{$row['id']}</td>
            <td>{$row['Created_At']}</td>
            <td>{$row['Donor_ID']}</td>
            <td>{$row['Donor_Name']}</td>
            <td>{$row['Donor_Email']}</td>
            <td>'{$row['Donor_ContactNumber']}</td>
            <td>{$row['Donor_Wallet']}</td>
            <td>{$row['Transaction_Type']}</td>
            <td>{$row['Amount']}</td>
            <td>{$row['Description']}</td>
            <td>{$row['Order_TXN_Ref']}</td>
        </tr>";
    }
    echo '</table>';
    exit();
}

// --- Stats Functions ---
$totalRevenue = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success')")->fetch_assoc()['total'] ?? 0;
$recurringRevenue = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success') AND Order_Type = 'Recurring'")->fetch_assoc()['total'] ?? 0;
$totalPoints = floor($totalRevenue / 10);
$pendingCount = $conn->query("SELECT COUNT(*) as count FROM orders WHERE Order_PaymentStatus = 'Pending'")->fetch_assoc()['count'];

// ==========================================
// LIST 1: TRANSACTION HISTORY (Logic)
// ==========================================
$limit = 5; 
$page_tx = isset($_GET['page_tx']) && is_numeric($_GET['page_tx']) ? (int)$_GET['page_tx'] : 1;
if ($page_tx < 1) $page_tx = 1;
$offset_tx = ($page_tx - 1) * $limit;

// Input Variables
$search_tx = isset($_GET['search_tx']) ? mysqli_real_escape_string($conn, $_GET['search_tx']) : '';
$filter_type_tx = isset($_GET['filter_type_tx']) ? $_GET['filter_type_tx'] : '';
$filter_date_tx = isset($_GET['filter_date_tx']) ? $_GET['filter_date_tx'] : '';
$filter_method_tx = isset($_GET['filter_method_tx']) ? $_GET['filter_method_tx'] : '';

// Build Query
$where_tx = "WHERE 1=1";

// 1. Filter Logic based on Type
if (!empty($filter_type_tx)) {
    if ($filter_type_tx == 'date' && !empty($filter_date_tx)) {
        $where_tx .= " AND DATE(o.Order_Created_At) = '$filter_date_tx'";
    }
    elseif ($filter_type_tx == 'donor_name' && !empty($search_tx)) {
        $where_tx .= " AND d.Donor_Name LIKE '%$search_tx%'";
    }
    elseif ($filter_type_tx == 'email' && !empty($search_tx)) {
        $where_tx .= " AND d.Donor_Email LIKE '%$search_tx%'";
    }
    elseif ($filter_type_tx == 'target' && !empty($search_tx)) {
        $where_tx .= " AND (b.Branch_Name LIKE '%$search_tx%' OR a.Activity_Name LIKE '%$search_tx%' OR s.Case_Title LIKE '%$search_tx%')";
    }
    elseif ($filter_type_tx == 'amount' && !empty($search_tx)) {
        $where_tx .= " AND o.Order_Amount = '$search_tx'";
    }
    elseif ($filter_type_tx == 'method' && !empty($filter_method_tx)) {
        $where_tx .= " AND o.Order_PaymentMethod = '$filter_method_tx'";
    }
} else {
    // Global Search if no filter selected
    if (!empty($search_tx)) {
        $where_tx .= " AND (o.Order_TXN_Ref LIKE '%$search_tx%' OR d.Donor_Name LIKE '%$search_tx%' OR d.Donor_Email LIKE '%$search_tx%' OR o.Order_PaymentMethod LIKE '%$search_tx%')";
    }
}

$sql_tx_count = "SELECT COUNT(*) as total FROM orders o JOIN donor d ON o.Donor_ID = d.Donor_ID LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID LEFT JOIN special_case s ON o.Case_ID = s.Case_ID $where_tx";
$total_tx_recs = $conn->query($sql_tx_count)->fetch_assoc()['total'];
$total_pages_tx = ceil($total_tx_recs / $limit);

$sql_tx = "SELECT o.Order_ID, o.Order_TXN_Ref, o.Order_Type, d.Donor_Name, d.Donor_Email, o.Order_Amount, o.Order_Created_At, o.Order_PaymentMethod, o.Order_PaymentStatus,
            b.Branch_Name, a.Activity_Name, s.Case_Title
            FROM orders o
            JOIN donor d ON o.Donor_ID = d.Donor_ID
            LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
            LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
            LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
            $where_tx
            ORDER BY o.Order_Created_At DESC 
            LIMIT $offset_tx, $limit";
$recentTransactions = $conn->query($sql_tx);

$start_tx = ($total_tx_recs > 0) ? $offset_tx + 1 : 0;
$end_tx = min($offset_tx + $limit, $total_tx_recs);

// ==========================================
// LIST 2: E-WALLET LOG (Logic)
// ==========================================
$page_wl = isset($_GET['page_wl']) && is_numeric($_GET['page_wl']) ? (int)$_GET['page_wl'] : 1;
if ($page_wl < 1) $page_wl = 1;
$offset_wl = ($page_wl - 1) * $limit; 

// Input Variables
$search_wl = isset($_GET['search_wl']) ? mysqli_real_escape_string($conn, $_GET['search_wl']) : '';
$filter_type_wl = isset($_GET['filter_type_wl']) ? $_GET['filter_type_wl'] : '';
$filter_date_wl = isset($_GET['filter_date_wl']) ? $_GET['filter_date_wl'] : '';
$filter_trans_type_wl = isset($_GET['filter_trans_type_wl']) ? $_GET['filter_trans_type_wl'] : '';

// Build Query
$where_wl = "WHERE 1=1";

if (!empty($filter_type_wl)) {
    if ($filter_type_wl == 'donor_name' && !empty($search_wl)) {
        $where_wl .= " AND d.Donor_Name LIKE '%$search_wl%'";
    }
    elseif ($filter_type_wl == 'email' && !empty($search_wl)) {
        $where_wl .= " AND d.Donor_Email LIKE '%$search_wl%'";
    }
    elseif ($filter_type_wl == 'date' && !empty($filter_date_wl)) {
        $where_wl .= " AND DATE(w.Created_At) = '$filter_date_wl'";
    }
    elseif ($filter_type_wl == 'type' && !empty($filter_trans_type_wl)) {
        $where_wl .= " AND w.Transaction_Type = '$filter_trans_type_wl'";
    }
    elseif ($filter_type_wl == 'amount' && !empty($search_wl)) {
        $where_wl .= " AND w.Amount = '$search_wl'";
    }
} else {
    // Global Search
    if (!empty($search_wl)) {
        $where_wl .= " AND (d.Donor_Name LIKE '%$search_wl%' OR d.Donor_Email LIKE '%$search_wl%' OR o.Order_TXN_Ref LIKE '%$search_wl%')";
    }
}

$sql_wl_count = "SELECT COUNT(*) as total 
                 FROM wallet_transaction w
                 JOIN donor d ON w.Donor_ID = d.Donor_ID
                 LEFT JOIN orders o ON w.Order_ID = o.Order_ID
                 $where_wl";
$total_wl_recs = $conn->query($sql_wl_count)->fetch_assoc()['total'];
$total_pages_wl = ceil($total_wl_recs / $limit);

$sql_wl = "SELECT w.*, d.Donor_Name, d.Donor_Email, d.Donor_ProfilePicture, o.Order_TXN_Ref
           FROM wallet_transaction w
           JOIN donor d ON w.Donor_ID = d.Donor_ID
           LEFT JOIN orders o ON w.Order_ID = o.Order_ID
           $where_wl
           ORDER BY w.Created_At DESC 
           LIMIT $offset_wl, $limit";
$walletTransactions = $conn->query($sql_wl);

$start_wl = ($total_wl_recs > 0) ? $offset_wl + 1 : 0;
$end_wl = min($offset_wl + $limit, $total_wl_recs);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - Love Bridge</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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
            --gray-light: #f8f9fa;
        }
        body { background-color: #f4f6f9; }
        .dashboard-content { padding: 25px; }

        /* Floating Alerts */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; max-width: 400px; animation: slideIn 0.3s; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Header Actions (Export Btn) */
        .btn-export { 
            background-color: #28a745; 
            color: white; 
            border: none;
            padding: 8px 15px;
            border-radius: 5px; 
            font-size: 14px;
            font-weight: 500; 
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: background 0.3s;
            text-decoration: none;
        }
        .btn-export:hover { background-color: #218838; color: white; }

        /* --- Full Width Search Bar Style (Like Staff Management) --- */
        .admin-search-container { 
            display: flex; 
            gap: 10px; 
            align-items: center; 
            background: #f8f9fa; 
            padding: 10px 15px; 
            border-radius: 8px; 
            border: 1px solid #eee; 
            flex-wrap: wrap; 
            width: 100%; 
            margin-top: 15px;
            box-sizing: border-box; /* Ensure padding doesn't overflow width */
        }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; outline: none; background-color: white; min-width: 150px; cursor: pointer; font-size: 13px; }
        .search-input { flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; outline: none; background: white; min-width: 200px; font-size: 13px; }
        .search-input:focus, .filter-select:focus { border-color: var(--primary); }
        
        .btn-search { background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 13px; display:flex; align-items:center; gap:5px; }
        .btn-clear { background: var(--danger); color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; text-decoration: none; display: flex; align-items: center; font-size: 13px; }
        
        .secondary-filter { display: none; animation: fadeIn 0.3s; flex: 1; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* Stats Cards */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #6c757d; margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; margin: 0; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .text-success { color: var(--success); } .text-info { color: var(--info); } .text-warning { color: var(--warning); } .text-danger { color: var(--danger); }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        /* Layout: Top Down */
        .payment-content-grid { display: flex; flex-direction: column; gap: 30px; margin-bottom: 30px; width: 100%; }
        .content-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); border: 1px solid #f0f0f0; display: flex; flex-direction: column; width: 100%; box-sizing: border-box; }

        /* Modified Header to just have Title and Export */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0px; /* Remove bottom margin to sit flush with search bar margin */ }
        .section-header h2 { font-size: 18px; font-weight: 700; color: #333; margin: 0; }

        /* Tables - Increased Font Sizes as requested */
        .table-container { flex: 1; overflow-x: auto; margin-top: 20px; }
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .custom-table thead th { color: #8898aa; font-weight: 600; text-transform: uppercase; font-size: 13px; padding: 0 15px 10px 15px; text-align: left; white-space: nowrap; }
        .custom-table tbody tr { background: white; transition: transform 0.2s; }
        .custom-table tbody tr:hover { background-color: #fcfcfc; }
        .custom-table td { padding: 15px; vertical-align: top; color: #525f7f; font-size: 14px; border-top: 1px solid #f5f5f5; border-bottom: 1px solid #f5f5f5; }
        .custom-table td:first-child { border-left: 1px solid #f5f5f5; border-radius: 8px 0 0 8px; }
        .custom-table td:last-child { border-right: 1px solid #f5f5f5; border-radius: 0 8px 8px 0; }

        /* Badges */
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff8e1; color: #ffca28; }
        .badge-failed { background: #fee2e2; color: #f5365c; }
        .badge-credit { background: #e6f4ea; color: #1e7e34; }
        .badge-debit { background: #fce8e6; color: #c5221f; }

        .btn-action { display: inline-flex; align-items: center; justify-content: center; width: 35px; height: 35px; background-color: #e3f2fd; color: #1976d2; border-radius: 50%; text-decoration: none; transition: all 0.2s; border: 1px solid #bbdefb; }
        .btn-action:hover { background-color: #1976d2; color: white; transform: translateY(-2px); }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: auto; }
        .pagination-info { font-size: 13px; color: #8898aa; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 6px 12px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 13px; }
        .pagination-btn:hover { background-color: #e9ecef; }
        .pagination-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; pointer-events: none; }
    </style>
</head>
<body>

    <div class="floating-alert floating-alert-success" id="floatingSuccess" style="display: <?php echo isset($_GET['success']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-check-circle"></i>
        <div id="floatingSuccessText"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div>
    </div>

    <div class="floating-alert floating-alert-danger" id="floatingError" style="display: <?php echo isset($_GET['error']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-exclamation-circle"></i>
        <div id="floatingErrorText"><?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?></div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            
            <div class="welcome-section">
                <h1>Payment Management</h1>
                <p>Monitor revenue, wallet usage, and donation records.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info"><h3>TOTAL REVENUE</h3><h2>RM <?php echo number_format($totalRevenue, 2); ?></h2><p class="text-success"><i class="fas fa-arrow-up"></i> Lifetime</p></div>
                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.2); color: #28a745;"><i class="fas fa-wallet"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>RECURRING</h3><h2>RM <?php echo number_format($recurringRevenue, 2); ?></h2><p class="text-info">Recurring</p></div>
                    <div class="stat-icon" style="background: rgba(23, 162, 184, 0.2); color: #17a2b8;"><i class="fas fa-sync"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>POINTS ISSUED</h3><h2><?php echo number_format($totalPoints); ?></h2><p class="text-warning">RM 10 = 1 Point</p></div>
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.2); color: #ffc107;"><i class="fas fa-star"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>PENDING</h3><h2><?php echo $pendingCount; ?></h2><p class="text-danger">Needs Action</p></div>
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.2); color: #dc3545;"><i class="fas fa-exclamation"></i></div>
                </div>
            </div>

            <div class="payment-content-grid">
                
                <div class="content-card">
                    <div class="section-header">
                        <h2>Transaction History</h2>
                        <a href="?export=income" class="btn-export"><i class="fas fa-download"></i> Export</a>
                    </div>
                    
                    <form method="GET" class="admin-search-container">
                        <?php if($page_wl > 1) echo "<input type='hidden' name='page_wl' value='$page_wl'>"; ?>
                        <?php if($search_wl) echo "<input type='hidden' name='search_wl' value='".htmlspecialchars($search_wl)."'>"; ?>
                        <?php if($filter_type_wl) echo "<input type='hidden' name='filter_type_wl' value='".htmlspecialchars($filter_type_wl)."'>"; ?>
                        <?php if($filter_date_wl) echo "<input type='hidden' name='filter_date_wl' value='".htmlspecialchars($filter_date_wl)."'>"; ?>
                        <?php if($filter_trans_type_wl) echo "<input type='hidden' name='filter_trans_type_wl' value='".htmlspecialchars($filter_trans_type_wl)."'>"; ?>

                        <div class="filter-group">
                            <i class="fas fa-filter" style="color:#666;"></i>
                            <select name="filter_type_tx" id="filterTypeTx" class="filter-select" onchange="toggleTxFilters()">
                                <option value="">Filter By...</option>
                                <option value="date" <?php echo ($filter_type_tx == 'date') ? 'selected' : ''; ?>>Date</option>
                                <option value="donor_name" <?php echo ($filter_type_tx == 'donor_name') ? 'selected' : ''; ?>>Donor Name</option>
                                <option value="email" <?php echo ($filter_type_tx == 'email') ? 'selected' : ''; ?>>Email</option>
                                <option value="target" <?php echo ($filter_type_tx == 'target') ? 'selected' : ''; ?>>Target (Branch/Act)</option>
                                <option value="amount" <?php echo ($filter_type_tx == 'amount') ? 'selected' : ''; ?>>Amount</option>
                                <option value="method" <?php echo ($filter_type_tx == 'method') ? 'selected' : ''; ?>>Method</option>
                            </select>
                        </div>

                        <div id="filter_date_tx" class="secondary-filter">
                            <input type="date" name="filter_date_tx" class="search-input" value="<?php echo htmlspecialchars($filter_date_tx); ?>">
                        </div>

                        <div id="filter_method_tx" class="secondary-filter">
                            <select name="filter_method_tx" class="search-input">
                                <option value="">Select Method</option>
                                <option value="Card" <?php echo ($filter_method_tx == 'Card') ? 'selected' : ''; ?>>Card</option>
                                <option value="FPX" <?php echo ($filter_method_tx == 'FPX') ? 'selected' : ''; ?>>FPX</option>
                                <option value="E-Wallet" <?php echo ($filter_method_tx == 'E-Wallet') ? 'selected' : ''; ?>>E-Wallet</option>
                            </select>
                        </div>
                        
                        <div id="filter_text_tx" class="secondary-filter active">
                            <input type="text" name="search_tx" class="search-input" placeholder="Search keyword..." value="<?php echo htmlspecialchars($search_tx); ?>">
                        </div>

                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                        
                        <?php if(!empty($search_tx) || !empty($filter_type_tx)): ?>
                            <a href="payment_management.php?<?php 
                                $cleanParams = $_GET;
                                unset($cleanParams['search_tx'], $cleanParams['filter_type_tx'], $cleanParams['filter_date_tx'], $cleanParams['filter_method_tx'], $cleanParams['page_tx']);
                                echo http_build_query($cleanParams);
                            ?>" class="btn-clear"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>

                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Date / Ref No</th>
                                    <th>Donor</th>
                                    <th>Target</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($recentTransactions->num_rows > 0): ?>
                                    <?php while($txn = $recentTransactions->fetch_assoc()): 
                                        $targetName = "General Fund";
                                        if ($txn['Case_Title']) $targetName = "Case: " . $txn['Case_Title'];
                                        elseif ($txn['Activity_Name']) $targetName = "Act: " . $txn['Activity_Name'];
                                        elseif ($txn['Branch_Name']) $targetName = "Branch: " . $txn['Branch_Name'];
                                        
                                        $dateTimeObj = new DateTime($txn['Order_Created_At']);
                                        $dateStr = $dateTimeObj->format('M d, Y');
                                        $timeStr = $dateTimeObj->format('h:i A');
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600; color:#333; font-size:14px;"><?php echo $txn['Order_TXN_Ref']; ?></div>
                                            <div style="font-size:12px; color:#888; margin-top:3px;"><?php echo $dateStr . ' ' . $timeStr; ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($txn['Donor_Name']); ?></div>
                                            <div style="font-size:12px; color:#888; margin-top:3px;"><?php echo htmlspecialchars($txn['Donor_Email']); ?></div>
                                        </td>
                                        <td><span style="font-size:13px; display:block; max-width:150px; white-space:normal; line-height:1.4;"><?php echo htmlspecialchars($targetName); ?></span></td>
                                        <td style="font-weight:700; color:#28a745; font-size:14px;">RM <?php echo number_format($txn['Order_Amount'], 2); ?></td>
                                        <td><span style="font-size:13px; color:#555;"><?php echo $txn['Order_PaymentMethod']; ?></span></td>
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
                        <div class="pagination-info">Showing <?php echo $start_tx; ?> - <?php echo $end_tx; ?> of <?php echo $total_tx_recs; ?></div>
                        <div class="pagination-controls">
                            <?php $pgParams = $_GET; ?>
                            <?php if ($page_tx > 1): $pgParams['page_tx'] = $page_tx - 1; ?>
                                <a href="?<?php echo http_build_query($pgParams); ?>" class="pagination-btn">Previous</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Previous</span>
                            <?php endif; ?>

                            <?php for($i=1; $i<=$total_pages_tx; $i++): $pgParams['page_tx'] = $i; ?>
                                <a href="?<?php echo http_build_query($pgParams); ?>" class="pagination-btn <?php echo ($i==$page_tx)?'active':''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page_tx < $total_pages_tx): $pgParams['page_tx'] = $page_tx + 1; ?>
                                <a href="?<?php echo http_build_query($pgParams); ?>" class="pagination-btn">Next</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="section-header">
                        <h2><i class="fas fa-wallet" style="color: #F28585; margin-right:8px;"></i> E-Wallet Log</h2>
                        <a href="?export=ewallet" class="btn-export"><i class="fas fa-download"></i> Export</a>
                    </div>
                    
                    <form method="GET" class="admin-search-container">
                        <?php if($page_tx > 1) echo "<input type='hidden' name='page_tx' value='$page_tx'>"; ?>
                        <?php if($search_tx) echo "<input type='hidden' name='search_tx' value='".htmlspecialchars($search_tx)."'>"; ?>
                        <?php if($filter_type_tx) echo "<input type='hidden' name='filter_type_tx' value='".htmlspecialchars($filter_type_tx)."'>"; ?>
                        <?php if($filter_date_tx) echo "<input type='hidden' name='filter_date_tx' value='".htmlspecialchars($filter_date_tx)."'>"; ?>
                        <?php if($filter_method_tx) echo "<input type='hidden' name='filter_method_tx' value='".htmlspecialchars($filter_method_tx)."'>"; ?>

                        <div class="filter-group">
                            <i class="fas fa-filter" style="color:#666;"></i>
                            <select name="filter_type_wl" id="filterTypeWl" class="filter-select" onchange="toggleWlFilters()">
                                <option value="">Filter By...</option>
                                <option value="donor_name" <?php echo ($filter_type_wl == 'donor_name') ? 'selected' : ''; ?>>Donor Name</option>
                                <option value="email" <?php echo ($filter_type_wl == 'email') ? 'selected' : ''; ?>>Email</option>
                                <option value="date" <?php echo ($filter_type_wl == 'date') ? 'selected' : ''; ?>>Date</option>
                                <option value="type" <?php echo ($filter_type_wl == 'type') ? 'selected' : ''; ?>>Type</option>
                                <option value="amount" <?php echo ($filter_type_wl == 'amount') ? 'selected' : ''; ?>>Amount</option>
                            </select>
                        </div>

                        <div id="filter_date_wl" class="secondary-filter">
                            <input type="date" name="filter_date_wl" class="search-input" value="<?php echo htmlspecialchars($filter_date_wl); ?>">
                        </div>

                        <div id="filter_type_wl_select" class="secondary-filter">
                            <select name="filter_trans_type_wl" class="search-input">
                                <option value="">Select Type</option>
                                <option value="Credit" <?php echo ($filter_trans_type_wl == 'Credit') ? 'selected' : ''; ?>>Credit (+)</option>
                                <option value="Debit" <?php echo ($filter_trans_type_wl == 'Debit') ? 'selected' : ''; ?>>Debit (-)</option>
                            </select>
                        </div>

                        <div id="filter_text_wl" class="secondary-filter active">
                            <input type="text" name="search_wl" class="search-input" placeholder="Search keyword..." value="<?php echo htmlspecialchars($search_wl); ?>">
                        </div>

                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>

                        <?php if(!empty($search_wl) || !empty($filter_type_wl)): ?>
                            <a href="payment_management.php?<?php 
                                $cleanParams = $_GET;
                                unset($cleanParams['search_wl'], $cleanParams['filter_type_wl'], $cleanParams['filter_date_wl'], $cleanParams['filter_trans_type_wl'], $cleanParams['page_wl']);
                                echo http_build_query($cleanParams);
                            ?>" class="btn-clear"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                    
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Donor / Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($walletTransactions && $walletTransactions->num_rows > 0): ?>
                                    <?php while($wt = $walletTransactions->fetch_assoc()): 
                                         $wtTime = new DateTime($wt['Created_At']);
                                         $wtDateStr = $wtTime->format('M d');
                                         $wtTimeStr = $wtTime->format('h:i A');
                                         $isCredit = ($wt['Transaction_Type'] == 'Credit');
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; margin-bottom:3px;">
                                                <?php if($wt['Donor_ProfilePicture']): ?>
                                                    <img src="<?php echo $wt['Donor_ProfilePicture']; ?>" style="width:30px; height:30px; border-radius:50%; margin-right:8px; object-fit:cover;">
                                                <?php else: ?>
                                                    <div style="width:30px; height:30px; border-radius:50%; background:#eee; margin-right:8px; font-size:12px; display:flex; align-items:center; justify-content:center;"><i class="fas fa-user"></i></div>
                                                <?php endif; ?>
                                                <span style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($wt['Donor_Name']); ?></span>
                                            </div>
                                            <div style="font-size:12px; color:#888; margin-left:38px;"><?php echo $wtDateStr . ' | ' . $wtTimeStr; ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $isCredit ? 'badge-credit' : 'badge-debit'; ?>">
                                                <?php echo $isCredit ? 'Credit' : 'Debit'; ?>
                                            </span>
                                        </td>
                                        <td style="font-weight:700; color: <?php echo $isCredit ? '#28a745' : '#dc3545'; ?>; font-size:14px;">
                                            <?php echo $isCredit ? '+' : '-'; ?> RM <?php echo number_format($wt['Amount'], 2); ?>
                                        </td>
                                        <td>
                                            <a href="admin_ewallet_details.php?id=<?php echo $wt['id']; ?>" class="btn-action" target="_blank" title="View Wallet Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">No wallet activity found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-container">
                        <div class="pagination-info">Showing <?php echo $start_wl; ?> - <?php echo $end_wl; ?> of <?php echo $total_wl_recs; ?></div>
                        <div class="pagination-controls">
                            <?php $wlParams = $_GET; ?>
                            <?php if ($page_wl > 1): $wlParams['page_wl'] = $page_wl - 1; ?>
                                <a href="?<?php echo http_build_query($wlParams); ?>" class="pagination-btn">Previous</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Previous</span>
                            <?php endif; ?>

                            <?php for($i=1; $i<=$total_pages_wl; $i++): $wlParams['page_wl'] = $i; ?>
                                <a href="?<?php echo http_build_query($wlParams); ?>" class="pagination-btn <?php echo ($i==$page_wl)?'active':''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page_wl < $total_pages_wl): $wlParams['page_wl'] = $page_wl + 1; ?>
                                <a href="?<?php echo http_build_query($wlParams); ?>" class="pagination-btn">Next</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card chart-section">
                <div class="section-header"><h2>Revenue Analytics</h2></div>
                <div style="height: 350px;"><canvas id="revenueChart"></canvas></div>
            </div>

        </div>
    </div>

    <script>
        // Chart Config
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

        // Toggle Logic for Transaction Filters
        function toggleTxFilters() {
            const type = document.getElementById('filterTypeTx').value;
            // Hide all inputs
            document.querySelectorAll('#filter_date_tx, #filter_method_tx, #filter_text_tx').forEach(el => {
                el.classList.remove('active');
            });
            
            // Enable global text input by default
            const textInput = document.getElementById('filter_text_tx');
            const dateInput = document.getElementById('filter_date_tx');
            const methodInput = document.getElementById('filter_method_tx');

            if (type === 'date') {
                dateInput.classList.add('active');
            } else if (type === 'method') {
                methodInput.classList.add('active');
            } else {
                // For All, Donor, Email, Target, Amount -> Use Text Input
                textInput.classList.add('active');
            }
        }
        // Run on load
        toggleTxFilters();

        // Toggle Logic for Wallet Filters
        function toggleWlFilters() {
            const type = document.getElementById('filterTypeWl').value;
            
            document.querySelectorAll('#filter_date_wl, #filter_type_wl_select, #filter_text_wl').forEach(el => {
                el.classList.remove('active');
            });

            const textInput = document.getElementById('filter_text_wl');
            const dateInput = document.getElementById('filter_date_wl');
            const typeInput = document.getElementById('filter_type_wl_select');

            if (type === 'date') {
                dateInput.classList.add('active');
            } else if (type === 'type') {
                typeInput.classList.add('active');
            } else {
                textInput.classList.add('active');
            }
        }
        toggleWlFilters();

        // Auto hide floating alert after 5 seconds
        setTimeout(() => {
            const success = document.getElementById('floatingSuccess');
            const error = document.getElementById('floatingError');
            if(success) success.style.display = 'none';
            if(error) error.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>