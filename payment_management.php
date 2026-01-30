<?php
// payment_management.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

$currentAdminId = $_SESSION['admin_id'];

// ==========================================
// 1. EXPORT LOGIC (Kept as requested)
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

$totalRevenue = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success')")->fetch_assoc()['total'] ?? 0;
$recurringRevenue = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success') AND Order_Type = 'Recurring'")->fetch_assoc()['total'] ?? 0;
$pendingCount = $conn->query("SELECT COUNT(*) as count FROM orders WHERE Order_PaymentStatus = 'Pending'")->fetch_assoc()['count'];

// ==========================================
// 2. LIST LOGIC WITH TABS, SORTING & NEW FILTERS
// ==========================================

function buildUrl($params) {
    $current = $_GET;
    // Handle array parameters like f_role[]
    foreach ($params as $key => $value) {
        $current[$key] = $value;
    }
    return '?' . http_build_query($current);
}

// Params
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';
$search_tx = isset($_GET['search_tx']) ? mysqli_real_escape_string($conn, $_GET['search_tx']) : '';

// --- NEW FILTERS ---
$f_date_y = isset($_GET['f_date_y']) ? $_GET['f_date_y'] : '';
$f_date_m = isset($_GET['f_date_m']) ? $_GET['f_date_m'] : '';
$f_date_d = isset($_GET['f_date_d']) ? $_GET['f_date_d'] : '';

// Role is now an array for multi-select
$f_role = isset($_GET['f_role']) ? $_GET['f_role'] : []; 

$f_target = isset($_GET['f_target']) ? $_GET['f_target'] : ''; // branch, activity, case
$f_min    = isset($_GET['f_min']) ? $_GET['f_min'] : '';
$f_max    = isset($_GET['f_max']) ? $_GET['f_max'] : '';
$f_method = isset($_GET['f_method']) ? $_GET['f_method'] : '';

// Determine active filter type for UI display
$filterType = "";
if (isset($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
} else {
    // Auto-detect if parameters are present to keep the UI open
    if ($f_date_y || $f_date_m || $f_date_d) $filterType = 'date';
    elseif (!empty($f_role)) $filterType = 'role';
    elseif ($f_target) $filterType = 'target';
    elseif ($f_min || $f_max) $filterType = 'amount';
    elseif ($f_method) $filterType = 'method';
}

$limit = 5; 
$page_tx = isset($_GET['page_tx']) && is_numeric($_GET['page_tx']) ? (int)$_GET['page_tx'] : 1;
if ($page_tx < 1) $page_tx = 1;
$offset_tx = ($page_tx - 1) * $limit;

// --- Base Queries ---
// Added 'Target_Sort_Name' for sorting capability on Target Column

// Income (Donations) Query
$qIncome = "SELECT 
        o.Order_ID as ID, 
        o.Order_TXN_Ref as Ref, 
        d.Donor_Name as Name, 
        d.Donor_Email as Email,
        'Donor' as User_Role, 
        o.Order_Amount as Amount, 
        o.Order_Created_At as Date, 
        o.Order_PaymentMethod as Method, 
        'Income' as Type,
        o.Order_Status as Status_Text,
        b.Branch_Name, a.Activity_Name, s.Case_Title,
        COALESCE(b.Branch_Name, a.Activity_Name, s.Case_Title, 'General') as Target_Sort_Name,
        o.Order_Type, o.Branch_ID, o.Activity_ID, o.Case_ID
       FROM orders o
       JOIN donor d ON o.Donor_ID = d.Donor_ID
       LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
       LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
       LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
       WHERE o.Order_PaymentStatus IN ('Success', 'Completed')";

// Expense (Withdrawals) Query
$qExpense = "SELECT 
        w.Withdrawal_ID as ID, 
        CONCAT('WDR-', w.Withdrawal_ID) as Ref, 
        adm.Admin_Name as Name, 
        adm.Admin_Email as Email,
        adm.Admin_Role as User_Role, 
        w.Amount as Amount, 
        w.Request_Date as Date, 
        'Bank Transfer' as Method, 
        'Withdrawal' as Type,
        w.Status as Status_Text,
        b.Branch_Name, a.Activity_Name, s.Case_Title,
        COALESCE(b.Branch_Name, a.Activity_Name, s.Case_Title, 'General') as Target_Sort_Name,
        'Withdrawal' as Order_Type, w.Branch_ID, w.Activity_ID, w.Case_ID
       FROM withdrawals w
       JOIN admin adm ON w.Admin_ID = adm.Admin_ID
       LEFT JOIN branch b ON w.Branch_ID = b.Branch_ID
       LEFT JOIN activity a ON w.Activity_ID = a.Activity_ID
       LEFT JOIN special_case s ON w.Case_ID = s.Case_ID
       WHERE 1=1";

// --- Tab Construction Logic ---
$base_combined = "";

if ($activeTab == 'all') {
    $base_combined = "SELECT * FROM ($qIncome UNION ALL $qExpense) AS Combined_Tx WHERE 1=1";
} elseif ($activeTab == 'withdrawals') {
    $base_combined = "SELECT * FROM ($qExpense) AS Combined_Tx WHERE 1=1";
} elseif ($activeTab == 'onetime') {
    $qIncome .= " AND o.Order_Type != 'Recurring'"; 
    $base_combined = "SELECT * FROM ($qIncome) AS Combined_Tx WHERE 1=1";
} elseif ($activeTab == 'monthly') {
    $qIncome .= " AND o.Order_Type = 'Recurring'";
    $base_combined = "SELECT * FROM ($qIncome) AS Combined_Tx WHERE 1=1";
} elseif ($activeTab == 'branch') {
    $qIncome .= " AND o.Branch_ID IS NOT NULL";
    $base_combined = "SELECT * FROM ($qIncome) AS Combined_Tx WHERE 1=1";
} elseif ($activeTab == 'activity') {
    $qIncome .= " AND o.Activity_ID IS NOT NULL";
    $base_combined = "SELECT * FROM ($qIncome) AS Combined_Tx WHERE 1=1";
} elseif ($activeTab == 'case') {
    $qIncome .= " AND o.Case_ID IS NOT NULL";
    $base_combined = "SELECT * FROM ($qIncome) AS Combined_Tx WHERE 1=1";
} else {
    $base_combined = "SELECT * FROM ($qIncome UNION ALL $qExpense) AS Combined_Tx WHERE 1=1";
}

$final_sql = $base_combined;

// --- Applying Filters ---

// 1. Search Text
if (!empty($search_tx)) {
    $final_sql .= " AND (Ref LIKE '%$search_tx%' OR Name LIKE '%$search_tx%' OR Email LIKE '%$search_tx%' OR Method LIKE '%$search_tx%')";
}

// 2. Date Filtering
if (!empty($f_date_y)) {
    $final_sql .= " AND YEAR(Date) = '" . mysqli_real_escape_string($conn, $f_date_y) . "'";
}
if (!empty($f_date_m)) {
    $final_sql .= " AND MONTH(Date) = '" . mysqli_real_escape_string($conn, $f_date_m) . "'";
}
if (!empty($f_date_d)) {
    $final_sql .= " AND DAY(Date) = '" . mysqli_real_escape_string($conn, $f_date_d) . "'";
}

// 3. Role Filtering (Updated for Multi-Select)
if (!empty($f_role)) {
    // $f_role is an array. We need to build a string for SQL IN clause
    // Sanitize each role
    $escaped_roles = array_map(function($r) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $r) . "'";
    }, $f_role);
    
    $role_str = implode(',', $escaped_roles);
    $final_sql .= " AND User_Role IN ($role_str)";
}

// 4. Target Type Filtering
if (!empty($f_target)) {
    if ($f_target == 'branch') {
        $final_sql .= " AND Branch_ID IS NOT NULL";
    } elseif ($f_target == 'activity') {
        $final_sql .= " AND Activity_ID IS NOT NULL";
    } elseif ($f_target == 'case') {
        $final_sql .= " AND Case_ID IS NOT NULL";
    }
}

// 5. Amount Min/Max
if (!empty($f_min)) {
    $final_sql .= " AND Amount >= " . (float)$f_min;
}
if (!empty($f_max)) {
    $final_sql .= " AND Amount <= " . (float)$f_max;
}

// 6. Payment Method
if (!empty($f_method)) {
    $final_sql .= " AND Method = '" . mysqli_real_escape_string($conn, $f_method) . "'";
}


// --- Sorting ---
$sortMap = [
    'date' => 'Date',
    'id' => 'ID',
    'ref' => 'Ref',
    'amount' => 'Amount',
    'name' => 'Name',
    'method' => 'Method',
    'target' => 'Target_Sort_Name' // Added Target Sort
];
$orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : 'Date';
$dir = ($order == 'asc') ? 'ASC' : 'DESC';

$final_sql .= " ORDER BY $orderBy $dir";

// Pagination
$count_res = $conn->query("SELECT COUNT(*) as total FROM ($final_sql) as Cnt");
$total_tx_recs = $count_res->fetch_assoc()['total'];
$total_pages_tx = ceil($total_tx_recs / $limit);

$final_sql .= " LIMIT $offset_tx, $limit";
$recentTransactions = $conn->query($final_sql);

$start_tx = ($total_tx_recs > 0) ? $offset_tx + 1 : 0;
$end_tx = min($offset_tx + $limit, $total_tx_recs);

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
    <title>Transaction History - Love Bridge</title>
    
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
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 9999; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; animation: slideIn 0.3s; }
        .floating-alert i { margin-top: 3px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .btn-export { background-color: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 5px; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 5px; transition: background 0.3s; text-decoration: none; }
        .btn-export:hover { background-color: #218838; color: white; }
        .btn-withdraw { background-color: #fd7e14; color: white; border: none; padding: 8px 15px; border-radius: 5px; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 5px; transition: background 0.3s; text-decoration: none; margin-right: 8px; }
        .btn-withdraw:hover { background-color: #e6700c; color: white; }

        /* Updated Search & Filter Container */
        .admin-search-container { display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px 15px; border-radius: 8px; border: 1px solid #eee; flex-wrap: wrap; width: 100%; margin-top: 15px; box-sizing: border-box; }
        
        /* New Filter Styles (Matches Admin Management) */
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; background-color: white; color: #555; outline: none; cursor: pointer; font-size: 13px; min-width: 130px; }
        .filter-select:focus { border-color: var(--primary); }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; display: flex; gap: 5px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .search-input { flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; outline: none; background: white; min-width: 200px; font-size: 13px; }
        .search-input:focus { border-color: var(--primary); }
        .btn-search { background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 13px; display:flex; align-items:center; gap:5px; }
        .btn-clear { background: var(--danger); color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; text-decoration: none; display: flex; align-items: center; font-size: 13px; }

        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #6c757d; margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; margin: 0; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .text-success { color: var(--success); } .text-info { color: var(--info); } .text-danger { color: var(--danger); }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        .content-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); border: 1px solid #f0f0f0; display: flex; flex-direction: column; width: 100%; box-sizing: border-box; margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0px; }
        .section-header h2 { font-size: 18px; font-weight: 700; color: #333; margin: 0; }
        .header-actions { display: flex; align-items: center; }

        .tabs { display: flex; border-bottom: 2px solid #eee; margin-bottom: 25px; margin-top: 20px; }
        .tab-item { padding: 15px 30px; font-size: 15px; font-weight: 600; color: #888; text-decoration: none; cursor: pointer; transition: 0.3s; position: relative; }
        .tab-item:hover { color: #555; background: #f9f9f9; }
        .tab-item.active { color: var(--primary); }
        .tab-item.active::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 2px; background: var(--primary); }

        .table-container { flex: 1; overflow-x: auto; margin-top: 20px; }
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .custom-table thead th { color: #8898aa; font-weight: 600; text-transform: uppercase; font-size: 13px; padding: 0 15px 10px 15px; text-align: left; white-space: nowrap; cursor: pointer; user-select: none; }
        .custom-table thead th:hover { background-color: #fcfcfc; color: #333; }
        .custom-table tbody tr { background: white; transition: transform 0.2s; }
        .custom-table tbody tr:hover { background-color: #fcfcfc; }
        .custom-table td { padding: 15px; vertical-align: top; color: #525f7f; font-size: 14px; border-top: 1px solid #f5f5f5; border-bottom: 1px solid #f5f5f5; }
        .custom-table td:first-child { border-left: 1px solid #f5f5f5; border-radius: 8px 0 0 8px; }
        .custom-table td:last-child { border-right: 1px solid #f5f5f5; border-radius: 0 8px 8px 0; }

        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; display: inline-block; }
        .badge-role { background: #e3f2fd; color: #0d47a1; margin-left: 5px; font-size: 10px; }

        .btn-action { display: inline-flex; align-items: center; justify-content: center; width: 35px; height: 35px; background-color: #e3f2fd; color: #1976d2; border-radius: 50%; text-decoration: none; transition: all 0.2s; border: 1px solid #bbdefb; }
        .btn-action:hover { background-color: #1976d2; color: white; transform: translateY(-2px); }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: auto; }
        .pagination-info { font-size: 13px; color: #8898aa; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 6px 12px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 13px; }
        .pagination-btn:hover { background-color: #e9ecef; }
        .pagination-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; pointer-events: none; }

        /* Sorting/Filter Modals Styles */
        .sort-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .sort-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; }
        .sort-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .sort-header h3 { margin: 0; font-size: 16px; color: #333; }
        .sort-close { cursor: pointer; font-size: 20px; color: #999; }
        
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #555; text-decoration: none; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
        .sort-btn:hover { background: #f8f9fa; border-color: #ddd; color: var(--primary); }

        /* New Filter Styles inside Modal */
        .modal-filter-group { background: #f9f9f9; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #eee; }
        .modal-filter-label { font-size: 12px; font-weight: 600; color: #666; margin-bottom: 8px; display: block; }
        .modal-input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; margin-bottom: 8px; box-sizing: border-box; }
        .modal-row { display: flex; gap: 5px; }
        .modal-apply-btn { width: 100%; background: var(--primary); color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .modal-apply-btn:hover { opacity: 0.9; }
        
        /* Checkbox Style */
        .checkbox-group { display: flex; flex-direction: column; gap: 6px; }
        .checkbox-label { display: flex; align-items: center; font-size: 13px; color: #333; cursor: pointer; }
        .checkbox-label input { margin-right: 8px; }
    </style>
</head>
<body>

    <div class="floating-alert floating-alert-success" id="floatingSuccess" style="display: <?php echo isset($_GET['success']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-check-circle"></i>
        <div id="floatingSuccessText"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div>
    </div>

    <div class="floating-alert floating-alert-danger" id="floatingError" style="display: <?php echo isset($_GET['error']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-exclamation-circle"></i>
        <div id="floatingErrorText"><?php echo isset($_GET['error']) ? $_GET['error'] : ''; ?></div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            
            <div class="welcome-section">
                <h1>Transaction History</h1>
                <p>Monitor donation revenue and withdrawal requests.</p>
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
                    <div class="stat-info"><h3>PENDING</h3><h2><?php echo $pendingCount; ?></h2><p class="text-danger">Needs Action</p></div>
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.2); color: #dc3545;"><i class="fas fa-exclamation"></i></div>
                </div>
            </div>

            <div class="content-card">
                <div class="section-header">
                    <h2>Transactions & Withdrawals</h2>
                    <div class="header-actions">
                        <a href="admin_withdrawal_add.php" class="btn-withdraw"><i class="fas fa-money-bill-wave"></i> Withdraw</a>
                        <a href="?export=income" class="btn-export"><i class="fas fa-download"></i> Export</a>
                    </div>
                </div>
                
                <div class="tabs">
                    <a href="<?php echo buildUrl(['tab'=>'all', 'page_tx'=>1]); ?>" class="tab-item <?php echo ($activeTab=='all')?'active':''; ?>">All</a>
                    <a href="<?php echo buildUrl(['tab'=>'withdrawals', 'page_tx'=>1]); ?>" class="tab-item <?php echo ($activeTab=='withdrawals')?'active':''; ?>">Withdrawals</a>
                    <a href="<?php echo buildUrl(['tab'=>'onetime', 'page_tx'=>1]); ?>" class="tab-item <?php echo ($activeTab=='onetime')?'active':''; ?>">One-Time</a>
                    <a href="<?php echo buildUrl(['tab'=>'monthly', 'page_tx'=>1]); ?>" class="tab-item <?php echo ($activeTab=='monthly')?'active':''; ?>">Monthly</a>
                    <a href="<?php echo buildUrl(['tab'=>'branch', 'page_tx'=>1]); ?>" class="tab-item <?php echo ($activeTab=='branch')?'active':''; ?>">Branch</a>
                    <a href="<?php echo buildUrl(['tab'=>'activity', 'page_tx'=>1]); ?>" class="tab-item <?php echo ($activeTab=='activity')?'active':''; ?>">Activity</a>
                    <a href="<?php echo buildUrl(['tab'=>'case', 'page_tx'=>1]); ?>" class="tab-item <?php echo ($activeTab=='case')?'active':''; ?>">Special Case</a>
                </div>

                <form method="GET" class="admin-search-container" style="margin-top:0;">
                    <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                    <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                    <input type="hidden" name="order" value="<?php echo $order; ?>">
                    
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="date" <?php if($filterType == 'date') echo 'selected'; ?>>Date</option>
                            <option value="role" <?php if($filterType == 'role') echo 'selected'; ?>>Role</option>
                            <option value="target" <?php if($filterType == 'target') echo 'selected'; ?>>Target Type</option>
                            <option value="amount" <?php if($filterType == 'amount') echo 'selected'; ?>>Amount</option>
                            <option value="method" <?php if($filterType == 'method') echo 'selected'; ?>>Payment Method</option>
                        </select>
                    </div>

                    <div id="filter_date_container" class="secondary-filter">
                        <select name="f_date_y" id="main_f_date_y" class="filter-select" style="width: 80px; min-width:80px;" onchange="updateDays('main_f_date_y', 'main_f_date_m', 'main_f_date_d')">
                            <option value="">Year...</option>
                            <?php 
                            $currentYear = date('Y');
                            for($y = 2021; $y <= $currentYear + 1; $y++) {
                                $sel = ($f_date_y == $y) ? 'selected' : '';
                                echo "<option value='$y' $sel>$y</option>";
                            }
                            ?>
                        </select>
                        <select name="f_date_m" id="main_f_date_m" class="filter-select" style="width: 100px; min-width:100px;" onchange="updateDays('main_f_date_y', 'main_f_date_m', 'main_f_date_d')">
                            <option value="">Month...</option>
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo ($f_date_m==$m)?'selected':''; ?>><?php echo date('M', mktime(0,0,0,$m,10)); ?></option>
                            <?php endfor; ?>
                        </select>
                        <input type="number" name="f_date_d" id="main_f_date_d" placeholder="Day" class="filter-select" style="width: 60px; min-width:60px;" value="<?php echo $f_date_d; ?>" min="1" max="31">
                    </div>

                    <div id="filter_role_container" class="secondary-filter">
                        <select name="f_role[]" class="filter-select">
                            <option value="">Select Role...</option>
                            <option value="Donor" <?php echo (in_array('Donor', $f_role)) ? 'selected' : ''; ?>>Donor</option>
                            <option value="Admin" <?php echo (in_array('Admin', $f_role)) ? 'selected' : ''; ?>>Admin</option>
                            <option value="Super Admin" <?php echo (in_array('Super Admin', $f_role)) ? 'selected' : ''; ?>>Super Admin</option>
                        </select>
                    </div>

                    <div id="filter_target_container" class="secondary-filter">
                        <select name="f_target" class="filter-select">
                            <option value="">All Targets</option>
                            <option value="branch" <?php echo ($f_target=='branch')?'selected':''; ?>>Branch</option>
                            <option value="activity" <?php echo ($f_target=='activity')?'selected':''; ?>>Activity</option>
                            <option value="case" <?php echo ($f_target=='case')?'selected':''; ?>>Special Case</option>
                        </select>
                    </div>

                    <div id="filter_amount_container" class="secondary-filter">
                        <input type="number" min="0" step="0.01" name="f_min" placeholder="Min RM" class="filter-select" style="width:80px; min-width:80px;" value="<?php echo $f_min; ?>">
                        <input type="number" min="0" step="0.01" name="f_max" placeholder="Max RM" class="filter-select" style="width:80px; min-width:80px;" value="<?php echo $f_max; ?>">
                    </div>

                    <div id="filter_method_container" class="secondary-filter">
                        <select name="f_method" class="filter-select">
                            <option value="">All Methods</option>
                            <option value="FPX" <?php echo ($f_method=='FPX')?'selected':''; ?>>FPX</option>
                            <option value="Card" <?php echo ($f_method=='Card')?'selected':''; ?>>Credit/Debit Card</option>
                            <option value="E-Wallet" <?php echo ($f_method=='E-Wallet')?'selected':''; ?>>E-Wallet</option>
                            <option value="Bank Transfer" <?php echo ($f_method=='Bank Transfer')?'selected':''; ?>>Bank Transfer</option>
                        </select>
                    </div>

                    <input type="text" name="search_tx" class="search-input" placeholder="Search Ref, Name, Email, Method..." value="<?php echo htmlspecialchars($search_tx); ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                    
                    <?php 
                    // Check if any filter is active to show Clear button
                    $isFiltered = !empty($search_tx) || $activeTab != 'all' || !empty($f_date_y) || !empty($f_role) || !empty($f_target) || !empty($f_min) || !empty($f_method);
                    if($isFiltered): 
                    ?>
                        <a href="payment_management.php" class="btn-clear"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>

                <div class="table-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th onclick="openModal('sortDateModal')">
                                    Date / Ref No 
                                    <?php if($sort=='date') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                                    <?php if($f_date_y || $f_date_m || $f_date_d) echo '<i class="fas fa-filter" style="font-size:10px; color:var(--primary);"></i>'; ?>
                                </th>
                                <th onclick="openModal('sortNameModal')">
                                    Donor / Entity 
                                    <?php if($sort=='name') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                                    <?php if(!empty($f_role)) echo '<i class="fas fa-filter" style="font-size:10px; color:var(--primary);"></i>'; ?>
                                </th>
                                <th onclick="openModal('sortTargetModal')">
                                    Target 
                                    <?php if($sort=='target') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                                    <?php if($f_target) echo '<i class="fas fa-filter" style="font-size:10px; color:var(--primary);"></i>'; ?>
                                </th>
                                <th onclick="openModal('sortAmountModal')">
                                    Amount 
                                    <?php if($sort=='amount') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                                    <?php if($f_min || $f_max) echo '<i class="fas fa-filter" style="font-size:10px; color:var(--primary);"></i>'; ?>
                                </th>
                                <th onclick="openModal('sortMethodModal')">
                                    Method 
                                    <?php if($sort=='method') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                                    <?php if($f_method) echo '<i class="fas fa-filter" style="font-size:10px; color:var(--primary);"></i>'; ?>
                                </th>
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
                                    
                                    $dateTimeObj = new DateTime($txn['Date']);
                                    $dateStr = $dateTimeObj->format('M d, Y');
                                    $timeStr = $dateTimeObj->format('h:i A');

                                    $isWithdrawal = ($txn['Type'] == 'Withdrawal');
                                    $amountColor = $isWithdrawal ? '#dc3545' : '#28a745';
                                    $amountPrefix = $isWithdrawal ? '-' : '';
                                    $detailsLink = $isWithdrawal ? "admin_withdrawal_details.php?id=" . $txn['ID'] : "admin_payment_details.php?id=" . $txn['ID'];
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600; color:#333; font-size:14px;"><?php echo $txn['Ref']; ?></div>
                                        <div style="font-size:12px; color:#888; margin-top:3px;"><?php echo $dateStr . ' ' . $timeStr; ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600; font-size:14px;">
                                            <?php echo htmlspecialchars($txn['Name']); ?>
                                            <?php if($isWithdrawal && !empty($txn['User_Role'])): ?>
                                                <span class="badge badge-role"><?php echo $txn['User_Role']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size:12px; color:#888; margin-top:3px;"><?php echo htmlspecialchars($txn['Email']); ?></div>
                                    </td>
                                    <td><span style="font-size:13px; display:block; max-width:150px; white-space:normal; line-height:1.4;"><?php echo htmlspecialchars($targetName); ?></span></td>
                                    <td style="font-weight:700; color:<?php echo $amountColor; ?>; font-size:14px;">
                                        <?php echo $amountPrefix; ?> RM <?php echo number_format($txn['Amount'], 2); ?>
                                    </td>
                                    <td><span style="font-size:13px; color:#555;"><?php echo $txn['Method']; ?></span></td>
                                    <td>
                                        <a href="<?php echo $detailsLink; ?>" class="btn-action" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">No transaction records found matching your filters.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_tx; ?> - <?php echo $end_tx; ?> of <?php echo $total_tx_recs; ?></div>
                    <div class="pagination-controls">
                        <?php $pgParams = $_GET; ?>
                        <?php if ($page_tx > 1): $pgParams['page_tx'] = $page_tx - 1; ?>
                            <a href="?<?php echo buildUrl($pgParams); ?>" class="pagination-btn">Previous</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Previous</span>
                        <?php endif; ?>

                        <?php 
                        $tx_range_start = max(1, $page_tx - 1);
                        $tx_range_end = min($total_pages_tx, $page_tx + 1);
                        
                        if($total_pages_tx >= 3) {
                            if($page_tx == 1) { $tx_range_end = 3; } 
                            elseif($page_tx == $total_pages_tx) { $tx_range_start = $total_pages_tx - 2; }
                        } else {
                            $tx_range_start = 1; $tx_range_end = $total_pages_tx;
                        }

                        for($i = $tx_range_start; $i <= $tx_range_end; $i++): 
                            $pgParams['page_tx'] = $i; 
                        ?>
                            <a href="?<?php echo buildUrl($pgParams); ?>" class="pagination-btn <?php echo ($i==$page_tx)?'active':''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($page_tx < $total_pages_tx): $pgParams['page_tx'] = $page_tx + 1; ?>
                            <a href="?<?php echo buildUrl($pgParams); ?>" class="pagination-btn">Next</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="content-card chart-section">
                <div class="section-header"><h2>Revenue Analytics</h2></div>
                <div style="height: 350px;"><canvas id="revenueChart"></canvas></div>
            </div>

        </div>
    </div>

    <div id="sortDateModal" class="sort-modal" onclick="closeModal('sortDateModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter & Sort Date</h3><span class="sort-close" onclick="closeModal('sortDateModal')">&times;</span></div>
            
            <form method="GET" class="modal-filter-group">
                <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="hidden" name="order" value="<?php echo $order; ?>">
                <input type="hidden" name="search_tx" value="<?php echo htmlspecialchars($search_tx); ?>">
                <?php if(!empty($f_role)): foreach($f_role as $r): ?>
                    <input type="hidden" name="f_role[]" value="<?php echo htmlspecialchars($r); ?>">
                <?php endforeach; endif; ?>
                <input type="hidden" name="f_target" value="<?php echo $f_target; ?>">
                <input type="hidden" name="f_min" value="<?php echo $f_min; ?>">
                <input type="hidden" name="f_max" value="<?php echo $f_max; ?>">
                <input type="hidden" name="f_method" value="<?php echo $f_method; ?>">

                <span class="modal-filter-label">Filter by Specific Date:</span>
                <div class="modal-row">
                    <select name="f_date_y" id="f_date_y" class="modal-input" onchange="updateDays('f_date_y', 'f_date_m', 'f_date_d')">
                        <option value="">Year...</option>
                        <?php 
                        $currentYear = date('Y');
                        for($y = 2021; $y <= $currentYear + 1; $y++) {
                            $sel = ($f_date_y == $y) ? 'selected' : '';
                            echo "<option value='$y' $sel>$y</option>";
                        }
                        ?>
                    </select>
                    <input type="number" name="f_date_d" id="f_date_d" placeholder="Day" class="modal-input" value="<?php echo $f_date_d; ?>" min="1" max="31">
                </div>
                <select name="f_date_m" id="f_date_m" class="modal-input" onchange="updateDays('f_date_y', 'f_date_m', 'f_date_d')">
                    <option value="">Month...</option>
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($f_date_m==$m)?'selected':''; ?>><?php echo date('F', mktime(0,0,0,$m,10)); ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="modal-apply-btn">Apply Filter</button>
            </form>

            <a href="<?php echo buildUrl(['sort'=>'date', 'order'=>'desc']); ?>" class="sort-btn">Newest to Oldest <i class="fas fa-sort-amount-down"></i></a>
            <a href="<?php echo buildUrl(['sort'=>'date', 'order'=>'asc']); ?>" class="sort-btn">Oldest to Newest <i class="fas fa-sort-amount-up"></i></a>
        </div>
    </div>

    <div id="sortNameModal" class="sort-modal" onclick="closeModal('sortNameModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter & Sort Name</h3><span class="sort-close" onclick="closeModal('sortNameModal')">&times;</span></div>
            
            <form method="GET" class="modal-filter-group">
                <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="hidden" name="order" value="<?php echo $order; ?>">
                <input type="hidden" name="search_tx" value="<?php echo htmlspecialchars($search_tx); ?>">
                
                <input type="hidden" name="f_date_y" value="<?php echo $f_date_y; ?>">
                <input type="hidden" name="f_date_m" value="<?php echo $f_date_m; ?>">
                <input type="hidden" name="f_date_d" value="<?php echo $f_date_d; ?>">
                <input type="hidden" name="f_target" value="<?php echo $f_target; ?>">
                <input type="hidden" name="f_min" value="<?php echo $f_min; ?>">
                <input type="hidden" name="f_max" value="<?php echo $f_max; ?>">
                <input type="hidden" name="f_method" value="<?php echo $f_method; ?>">

                <span class="modal-filter-label">Filter by Role:</span>
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="f_role[]" value="Donor" <?php echo (in_array('Donor', $f_role)) ? 'checked' : ''; ?>> Donor
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="f_role[]" value="Admin" <?php echo (in_array('Admin', $f_role)) ? 'checked' : ''; ?>> Admin
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="f_role[]" value="Super Admin" <?php echo (in_array('Super Admin', $f_role)) ? 'checked' : ''; ?>> Super Admin
                    </label>
                </div>
                <br>
                <button type="submit" class="modal-apply-btn">Apply Filter</button>
            </form>

            <a href="<?php echo buildUrl(['sort'=>'name', 'order'=>'asc']); ?>" class="sort-btn">A to Z <i class="fas fa-sort-alpha-down"></i></a>
            <a href="<?php echo buildUrl(['sort'=>'name', 'order'=>'desc']); ?>" class="sort-btn">Z to A <i class="fas fa-sort-alpha-up"></i></a>
        </div>
    </div>

    <div id="sortTargetModal" class="sort-modal" onclick="closeModal('sortTargetModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter & Sort Target</h3><span class="sort-close" onclick="closeModal('sortTargetModal')">&times;</span></div>
            
            <form method="GET" class="modal-filter-group">
                <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="hidden" name="order" value="<?php echo $order; ?>">
                <input type="hidden" name="search_tx" value="<?php echo htmlspecialchars($search_tx); ?>">
                
                <input type="hidden" name="f_date_y" value="<?php echo $f_date_y; ?>">
                <input type="hidden" name="f_date_m" value="<?php echo $f_date_m; ?>">
                <input type="hidden" name="f_date_d" value="<?php echo $f_date_d; ?>">
                <?php if(!empty($f_role)): foreach($f_role as $r): ?>
                    <input type="hidden" name="f_role[]" value="<?php echo htmlspecialchars($r); ?>">
                <?php endforeach; endif; ?>
                <input type="hidden" name="f_min" value="<?php echo $f_min; ?>">
                <input type="hidden" name="f_max" value="<?php echo $f_max; ?>">
                <input type="hidden" name="f_method" value="<?php echo $f_method; ?>">

                <span class="modal-filter-label">Filter by Target Type:</span>
                <select name="f_target" class="modal-input">
                    <option value="">All Targets</option>
                    <option value="branch" <?php echo ($f_target=='branch')?'selected':''; ?>>Branch</option>
                    <option value="activity" <?php echo ($f_target=='activity')?'selected':''; ?>>Activity</option>
                    <option value="case" <?php echo ($f_target=='case')?'selected':''; ?>>Special Case</option>
                </select>
                <button type="submit" class="modal-apply-btn">Apply Filter</button>
            </form>

            <a href="<?php echo buildUrl(['sort'=>'target', 'order'=>'asc']); ?>" class="sort-btn">A to Z <i class="fas fa-sort-alpha-down"></i></a>
            <a href="<?php echo buildUrl(['sort'=>'target', 'order'=>'desc']); ?>" class="sort-btn">Z to A <i class="fas fa-sort-alpha-up"></i></a>
        </div>
    </div>

    <div id="sortAmountModal" class="sort-modal" onclick="closeModal('sortAmountModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter & Sort Amount</h3><span class="sort-close" onclick="closeModal('sortAmountModal')">&times;</span></div>
            
            <form method="GET" class="modal-filter-group">
                <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="hidden" name="order" value="<?php echo $order; ?>">
                <input type="hidden" name="search_tx" value="<?php echo htmlspecialchars($search_tx); ?>">
                
                <input type="hidden" name="f_date_y" value="<?php echo $f_date_y; ?>">
                <input type="hidden" name="f_date_m" value="<?php echo $f_date_m; ?>">
                <input type="hidden" name="f_date_d" value="<?php echo $f_date_d; ?>">
                <?php if(!empty($f_role)): foreach($f_role as $r): ?>
                    <input type="hidden" name="f_role[]" value="<?php echo htmlspecialchars($r); ?>">
                <?php endforeach; endif; ?>
                <input type="hidden" name="f_target" value="<?php echo $f_target; ?>">
                <input type="hidden" name="f_method" value="<?php echo $f_method; ?>">

                <span class="modal-filter-label">Amount Range (RM):</span>
                <div class="modal-row">
                    <input type="number" min="0" step="0.01" name="f_min" placeholder="Min" class="modal-input" value="<?php echo $f_min; ?>">
                    <input type="number" min="0" step="0.01" name="f_max" placeholder="Max" class="modal-input" value="<?php echo $f_max; ?>">
                </div>
                <button type="submit" class="modal-apply-btn">Apply Filter</button>
            </form>

            <a href="<?php echo buildUrl(['sort'=>'amount', 'order'=>'desc']); ?>" class="sort-btn">High to Low <i class="fas fa-sort-numeric-down-alt"></i></a>
            <a href="<?php echo buildUrl(['sort'=>'amount', 'order'=>'asc']); ?>" class="sort-btn">Low to High <i class="fas fa-sort-numeric-up"></i></a>
        </div>
    </div>

    <div id="sortMethodModal" class="sort-modal" onclick="closeModal('sortMethodModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter & Sort Method</h3><span class="sort-close" onclick="closeModal('sortMethodModal')">&times;</span></div>
            
            <form method="GET" class="modal-filter-group">
                <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="hidden" name="order" value="<?php echo $order; ?>">
                <input type="hidden" name="search_tx" value="<?php echo htmlspecialchars($search_tx); ?>">
                
                <input type="hidden" name="f_date_y" value="<?php echo $f_date_y; ?>">
                <input type="hidden" name="f_date_m" value="<?php echo $f_date_m; ?>">
                <input type="hidden" name="f_date_d" value="<?php echo $f_date_d; ?>">
                <?php if(!empty($f_role)): foreach($f_role as $r): ?>
                    <input type="hidden" name="f_role[]" value="<?php echo htmlspecialchars($r); ?>">
                <?php endforeach; endif; ?>
                <input type="hidden" name="f_target" value="<?php echo $f_target; ?>">
                <input type="hidden" name="f_min" value="<?php echo $f_min; ?>">
                <input type="hidden" name="f_max" value="<?php echo $f_max; ?>">

                <span class="modal-filter-label">Filter by Method:</span>
                <select name="f_method" class="modal-input">
                    <option value="">All Methods</option>
                    <option value="FPX" <?php echo ($f_method=='FPX')?'selected':''; ?>>FPX</option>
                    <option value="Card" <?php echo ($f_method=='Card')?'selected':''; ?>>Credit/Debit Card</option>
                    <option value="E-Wallet" <?php echo ($f_method=='E-Wallet')?'selected':''; ?>>E-Wallet</option>
                    <option value="Bank Transfer" <?php echo ($f_method=='Bank Transfer')?'selected':''; ?>>Bank Transfer (Withdrawal)</option>
                </select>
                <button type="submit" class="modal-apply-btn">Apply Filter</button>
            </form>

            <a href="<?php echo buildUrl(['sort'=>'method', 'order'=>'asc']); ?>" class="sort-btn">A to Z <i class="fas fa-sort-alpha-down"></i></a>
            <a href="<?php echo buildUrl(['sort'=>'method', 'order'=>'desc']); ?>" class="sort-btn">Z to A <i class="fas fa-sort-alpha-up"></i></a>
        </div>
    </div>

    <script>
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

        // Modals Logic
        function openModal(id) { document.getElementById(id).style.display = "flex"; }
        function closeModal(id, e) {
            if (!e || e.target.id === id || e.target.classList.contains('sort-close')) {
                document.getElementById(id).style.display = "none";
            }
        }

        setTimeout(() => {
            const success = document.getElementById('floatingSuccess');
            const error = document.getElementById('floatingError');
            if(success) success.style.display = 'none';
            if(error) error.style.display = 'none';
        }, 5000);

        // Date Picker Logic
        function updateDays(yearId, monthId, dayId) {
            const yearSelect = document.getElementById(yearId);
            const monthSelect = document.getElementById(monthId);
            const dayInput = document.getElementById(dayId);

            const year = parseInt(yearSelect.value) || new Date().getFullYear();
            const month = parseInt(monthSelect.value);

            if (month) {
                // Get last day of the month (Day 0 of next month is last day of current)
                const daysInMonth = new Date(year, month, 0).getDate();
                dayInput.max = daysInMonth;
                
                // Reset value if it exceeds new max
                if (dayInput.value > daysInMonth) {
                    dayInput.value = daysInMonth;
                }
            } else {
                dayInput.max = 31;
            }
        }

        // --- NEW Toggle Filter Inputs Logic ---
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            // Hide all secondary containers first
            document.querySelectorAll('.secondary-filter').forEach(el => { 
                el.classList.remove('active'); 
                // We do NOT disable inputs here because this form submits everything.
                // If we disable them, the values won't be sent.
                // Instead, we just hide them visually.
            });

            if (type === 'date') {
                document.getElementById('filter_date_container').classList.add('active');
            } else if (type === 'role') {
                document.getElementById('filter_role_container').classList.add('active');
            } else if (type === 'target') {
                document.getElementById('filter_target_container').classList.add('active');
            } else if (type === 'amount') {
                document.getElementById('filter_amount_container').classList.add('active');
            } else if (type === 'method') {
                document.getElementById('filter_method_container').classList.add('active');
            }
        }

        // Run on load to set initial state
        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
        });
    </script>
</body>
</html>