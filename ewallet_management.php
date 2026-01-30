<?php
// ewallet_management.php
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

$adminName = "Admin";
$adminRole = "Staff";
$adminProfilePicture = null;

if ($adminResult && $adminResult->num_rows > 0) {
    $adminData = $adminResult->fetch_assoc();
    $adminName = $adminData['Admin_Name'];
    $adminRole = $adminData['Admin_Role'];
    $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
}

// ==========================================
// 1. HELPER FUNCTION (For URL Building)
// ==========================================
function buildUrl($params) {
    $current = $_GET;
    $merged = array_merge($current, $params);
    return '?' . http_build_query($merged);
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
            <th>Description</th><th>Payment Method</th><th>Target</th><th>Linked Order Ref</th>
          </tr>';
    
    // Updated SQL for export to include details
    $sql = "SELECT w.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_Wallet, o.Order_TXN_Ref, o.Order_PaymentMethod,
            b.Branch_Name, a.Activity_Name, s.Case_Title
            FROM wallet_transaction w
            JOIN donor d ON w.Donor_ID = d.Donor_ID
            LEFT JOIN orders o ON w.Order_ID = o.Order_ID
            LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
            LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
            LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
            ORDER BY w.Created_At DESC";
            
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) {
        $target = "-";
        if($row['Branch_Name']) $target = "Branch: ".$row['Branch_Name'];
        elseif($row['Activity_Name']) $target = "Activity: ".$row['Activity_Name'];
        elseif($row['Case_Title']) $target = "Case: ".$row['Case_Title'];

        $txnID = $row['Wallet_Trans_ID'];
        echo "<tr>
            <td>{$txnID}</td>
            <td>{$row['Created_At']}</td>
            <td>{$row['Donor_ID']}</td>
            <td>{$row['Donor_Name']}</td>
            <td>{$row['Donor_Email']}</td>
            <td>'{$row['Donor_ContactNumber']}</td>
            <td>{$row['Donor_Wallet']}</td>
            <td>{$row['Transaction_Type']}</td>
            <td>{$row['Amount']}</td>
            <td>{$row['Description']}</td>
            <td>{$row['Order_PaymentMethod']}</td>
            <td>{$target}</td>
            <td>{$row['Order_TXN_Ref']}</td>
        </tr>";
    }
    echo '</table>';
    exit();
}

// --- STATS CALCULATIONS ---
$totalWalletBalance = $conn->query("SELECT SUM(Donor_Wallet) as total FROM donor")->fetch_assoc()['total'] ?? 0;
$totalTopups = $conn->query("SELECT SUM(Amount) as total FROM wallet_transaction WHERE Transaction_Type = 'Credit'")->fetch_assoc()['total'] ?? 0;
$totalSpent = $conn->query("SELECT SUM(Amount) as total FROM wallet_transaction WHERE Transaction_Type = 'Debit'")->fetch_assoc()['total'] ?? 0;


// ==========================================
// 3. LIST LOGIC WITH SORTING & FILTERS
// ==========================================

// Params
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';
$search_wl = isset($_GET['search_wl']) ? mysqli_real_escape_string($conn, $_GET['search_wl']) : '';

// --- NEW FILTERS ---
$f_date_y = isset($_GET['f_date_y']) ? $_GET['f_date_y'] : '';
$f_date_m = isset($_GET['f_date_m']) ? $_GET['f_date_m'] : '';
$f_date_d = isset($_GET['f_date_d']) ? $_GET['f_date_d'] : '';
$f_type   = isset($_GET['f_type']) ? $_GET['f_type'] : ''; // Credit or Debit
$f_min    = isset($_GET['f_min']) ? $_GET['f_min'] : '';
$f_max    = isset($_GET['f_max']) ? $_GET['f_max'] : '';
$f_target_type = isset($_GET['f_target_type']) ? $_GET['f_target_type'] : ''; // branch, activity, case

// Determine active filter type for UI display
$filterType = "";
if (isset($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
} else {
    // Auto-detect if parameters are present to keep the UI open
    if ($f_date_y || $f_date_m || $f_date_d) $filterType = 'date';
    elseif ($f_type) $filterType = 'type';
    elseif ($f_target_type) $filterType = 'target';
    elseif ($f_min || $f_max) $filterType = 'amount';
}

// Pagination settings
$limit = 5;
$page_wl = isset($_GET['page_wl']) && is_numeric($_GET['page_wl']) ? (int)$_GET['page_wl'] : 1;
if ($page_wl < 1) $page_wl = 1;
$offset_wl = ($page_wl - 1) * $limit; 

// --- Build Query ---
$where_wl = "WHERE 1=1";

// 1. Text Search
if (!empty($search_wl)) {
    $where_wl .= " AND (d.Donor_Name LIKE '%$search_wl%' 
                     OR d.Donor_Email LIKE '%$search_wl%' 
                     OR w.Description LIKE '%$search_wl%'
                     OR w.Wallet_Trans_ID LIKE '%$search_wl%'
                     OR o.Order_TXN_Ref LIKE '%$search_wl%'
                     OR o.Order_PaymentMethod LIKE '%$search_wl%')";
}

// 2. Date Filtering
if (!empty($f_date_y)) {
    $where_wl .= " AND YEAR(w.Created_At) = '" . mysqli_real_escape_string($conn, $f_date_y) . "'";
}
if (!empty($f_date_m)) {
    $where_wl .= " AND MONTH(w.Created_At) = '" . mysqli_real_escape_string($conn, $f_date_m) . "'";
}
if (!empty($f_date_d)) {
    $where_wl .= " AND DAY(w.Created_At) = '" . mysqli_real_escape_string($conn, $f_date_d) . "'";
}

// 3. Type Filtering
if (!empty($f_type)) {
    $where_wl .= " AND w.Transaction_Type = '" . mysqli_real_escape_string($conn, $f_type) . "'";
}

// 4. Amount Min/Max
if (!empty($f_min)) {
    $where_wl .= " AND w.Amount >= " . (float)$f_min;
}
if (!empty($f_max)) {
    $where_wl .= " AND w.Amount <= " . (float)$f_max;
}

// 5. Target Filter (Branch, Activity, Case)
if (!empty($f_target_type)) {
    if ($f_target_type == 'branch') {
        $where_wl .= " AND o.Branch_ID IS NOT NULL";
    } elseif ($f_target_type == 'activity') {
        $where_wl .= " AND o.Activity_ID IS NOT NULL";
    } elseif ($f_target_type == 'case') {
        $where_wl .= " AND o.Case_ID IS NOT NULL";
    }
}

// --- Sorting Logic ---
$sortMap = [
    'date' => 'w.Created_At',
    'donor' => 'd.Donor_Name',
    'details' => 'w.Description', // Sort by description
    'type' => 'w.Transaction_Type',
    'amount' => 'w.Amount'
];

$orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : 'w.Created_At';
$dir = ($order == 'asc') ? 'ASC' : 'DESC';

// Pagination Count Query
$sql_wl_count = "SELECT COUNT(*) as total 
                 FROM wallet_transaction w 
                 JOIN donor d ON w.Donor_ID = d.Donor_ID 
                 LEFT JOIN orders o ON w.Order_ID = o.Order_ID 
                 $where_wl";

$total_wl_recs = $conn->query($sql_wl_count)->fetch_assoc()['total'];
$total_pages_wl = ceil($total_wl_recs / $limit);

// Main Data Query - JOIN with extra tables for details
$sql_wl = "SELECT w.*, d.Donor_Name, d.Donor_Email, d.Donor_ProfilePicture, 
           o.Order_TXN_Ref, o.Order_PaymentMethod,
           b.Branch_Name, a.Activity_Name, s.Case_Title
           FROM wallet_transaction w 
           JOIN donor d ON w.Donor_ID = d.Donor_ID 
           LEFT JOIN orders o ON w.Order_ID = o.Order_ID 
           LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
           LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
           LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
           $where_wl 
           ORDER BY $orderBy $dir 
           LIMIT $offset_wl, $limit";

$walletTransactions = $conn->query($sql_wl);
$start_wl = ($total_wl_recs > 0) ? $offset_wl + 1 : 0;
$end_wl = min($offset_wl + $limit, $total_wl_recs);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Wallet Management - Love Bridge</title>
    
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

        .btn-export { background-color: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 5px; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 5px; transition: background 0.3s; text-decoration: none; }
        .btn-export:hover { background-color: #218838; color: white; }

        /* Updated Search & Filter Container (Consistent with Payment Management) */
        .admin-search-container { display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px 15px; border-radius: 8px; border: 1px solid #eee; flex-wrap: wrap; width: 100%; margin-top: 15px; box-sizing: border-box; }
        
        /* New Filter Styles */
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
        .text-warning { color: var(--warning); }
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        .payment-content-grid { display: flex; flex-direction: column; gap: 30px; margin-bottom: 30px; width: 100%; }
        .content-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); border: 1px solid #f0f0f0; display: flex; flex-direction: column; width: 100%; box-sizing: border-box; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0px; }
        .section-header h2 { font-size: 18px; font-weight: 700; color: #333; margin: 0; }
        .header-actions { display: flex; align-items: center; }

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

        /* --- Sorting/Filter Modals Styles --- */
        .sort-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .sort-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; }
        .sort-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .sort-header h3 { margin: 0; font-size: 16px; color: #333; }
        .sort-close { cursor: pointer; font-size: 20px; color: #999; }
        
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #555; text-decoration: none; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
        .sort-btn:hover { background: #f8f9fa; border-color: #ddd; color: var(--primary); }

        /* Filter Styles inside Modal */
        .modal-filter-group { background: #f9f9f9; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #eee; }
        .modal-filter-label { font-size: 12px; font-weight: 600; color: #666; margin-bottom: 8px; display: block; }
        .modal-input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; margin-bottom: 8px; box-sizing: border-box; }
        .modal-row { display: flex; gap: 5px; }
        .modal-apply-btn { width: 100%; background: var(--primary); color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .modal-apply-btn:hover { opacity: 0.9; }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            
            <div class="welcome-section">
                <h1>E-Wallet Management</h1>
                <p>Monitor user wallet balances and activities.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info"><h3>SYSTEM WALLET FUNDS</h3><h2>RM <?php echo number_format($totalWalletBalance, 2); ?></h2><p class="text-warning">User Holdings</p></div>
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.2); color: #ffc107;"><i class="fas fa-coins"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info"><h3>TOTAL TOP-UPS</h3><h2>RM <?php echo number_format($totalTopups, 2); ?></h2><p class="text-success"><i class="fas fa-arrow-up"></i> Lifetime Credit</p></div>
                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.2); color: #28a745;"><i class="fas fa-hand-holding-usd"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info"><h3>TOTAL SPENT</h3><h2>RM <?php echo number_format($totalSpent, 2); ?></h2><p class="text-danger"><i class="fas fa-arrow-down"></i> Lifetime Debit</p></div>
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.2); color: #dc3545;"><i class="fas fa-money-bill-wave"></i></div>
                </div>
            </div>

            <div class="payment-content-grid">
                
                <div class="content-card">
                    <div class="section-header">
                        <h2><i class="fas fa-wallet" style="color: #F28585; margin-right:8px;"></i> E-Wallet Log</h2>
                        <a href="?export=ewallet" class="btn-export"><i class="fas fa-download"></i> Export</a>
                    </div>
                    
                    <form method="GET" class="admin-search-container">
                        <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                        <input type="hidden" name="order" value="<?php echo $order; ?>">
                        <?php if($page_wl > 1 && !empty($_GET['search_wl'])) echo "<input type='hidden' name='page_wl' value='1'>"; ?>
                        
                        <div class="filter-group">
                            <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                            <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                                <option value="">Filter By...</option>
                                <option value="date" <?php if($filterType == 'date') echo 'selected'; ?>>Date</option>
                                <option value="type" <?php if($filterType == 'type') echo 'selected'; ?>>Transaction Type</option>
                                <option value="target" <?php if($filterType == 'target') echo 'selected'; ?>>Context</option>
                                <option value="amount" <?php if($filterType == 'amount') echo 'selected'; ?>>Amount</option>
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

                        <div id="filter_type_container" class="secondary-filter">
                            <select name="f_type" class="filter-select">
                                <option value="">All Types</option>
                                <option value="Credit" <?php echo ($f_type=='Credit')?'selected':''; ?>>Credit (Top-up)</option>
                                <option value="Debit" <?php echo ($f_type=='Debit')?'selected':''; ?>>Debit (Spent)</option>
                            </select>
                        </div>

                        <div id="filter_target_container" class="secondary-filter">
                            <select name="f_target_type" class="filter-select">
                                <option value="">All Contexts</option>
                                <option value="branch" <?php echo ($f_target_type=='branch')?'selected':''; ?>>Branch</option>
                                <option value="activity" <?php echo ($f_target_type=='activity')?'selected':''; ?>>Activity</option>
                                <option value="case" <?php echo ($f_target_type=='case')?'selected':''; ?>>Special Case</option>
                            </select>
                        </div>

                        <div id="filter_amount_container" class="secondary-filter">
                            <input type="number" min="0" step="0.01" name="f_min" placeholder="Min RM" class="filter-select" style="width:80px; min-width:80px;" value="<?php echo $f_min; ?>">
                            <input type="number" min="0" step="0.01" name="f_max" placeholder="Max RM" class="filter-select" style="width:80px; min-width:80px;" value="<?php echo $f_max; ?>">
                        </div>

                        <input type="text" name="search_wl" class="search-input" placeholder="Search Donor, Ref, Description..." value="<?php echo htmlspecialchars($search_wl); ?>">
                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>

                        <?php 
                        // Check if any filter is active to show Clear button
                        $isFiltered = !empty($search_wl) || !empty($f_date_y) || !empty($f_type) || !empty($f_min) || !empty($f_target_type);
                        if($isFiltered): 
                        ?>
                            <a href="ewallet_management.php" class="btn-clear"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                    
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th onclick="openModal('sortDateModal')">
                                        Date 
                                        <?php if($sort=='date') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                                        <?php if($f_date_y || $f_date_m || $f_date_d) echo '<i class="fas fa-filter" style="font-size:10px; color:var(--primary);"></i>'; ?>
                                    </th>
                                    <th onclick="openModal('sortDonorModal')">
                                        Donor 
                                        <?php if($sort=='donor') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                                    </th>
                                    <th onclick="openModal('sortDetailsModal')">
                                        Transaction Details 
                                        <?php if($sort=='details') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                                        <?php if($f_target_type) echo '<i class="fas fa-filter" style="font-size:10px; color:var(--primary);"></i>'; ?>
                                    </th> 
                                    <th style="text-align: center;" onclick="openModal('sortTypeModal')">
                                        Type 
                                        <?php if($sort=='type') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                                        <?php if($f_type) echo '<i class="fas fa-filter" style="font-size:10px; color:var(--primary);"></i>'; ?>
                                    </th>
                                    <th onclick="openModal('sortAmountModal')">
                                        Amount 
                                        <?php if($sort=='amount') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                                        <?php if($f_min || $f_max) echo '<i class="fas fa-filter" style="font-size:10px; color:var(--primary);"></i>'; ?>
                                    </th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($walletTransactions && $walletTransactions->num_rows > 0): ?>
                                    <?php while($wt = $walletTransactions->fetch_assoc()): 
                                         $wtTime = new DateTime($wt['Created_At']);
                                         $wtDateStr = $wtTime->format('M d, Y');
                                         $wtTimeStr = $wtTime->format('h:i A');
                                         $isCredit = ($wt['Transaction_Type'] == 'Credit');
                                         $txnID = $wt['Wallet_Trans_ID'];
                                         $desc = !empty($wt['Description']) ? $wt['Description'] : '-';
                                         
                                         // Logic for Target Name and Payment Method
                                         $targetInfo = "";
                                         if(!empty($wt['Branch_Name'])) $targetInfo = "Branch: " . $wt['Branch_Name'];
                                         elseif(!empty($wt['Activity_Name'])) $targetInfo = "Activity: " . $wt['Activity_Name'];
                                         elseif(!empty($wt['Case_Title'])) $targetInfo = "Case: " . $wt['Case_Title'];
                                         
                                         $payMethod = !empty($wt['Order_PaymentMethod']) ? $wt['Order_PaymentMethod'] : '';
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600; color:#333; font-size:14px;"><?php echo $wtDateStr; ?></div>
                                            <div style="font-size:12px; color:#888; margin-top:3px;"><?php echo $wtTimeStr; ?></div>
                                        </td>

                                        <td>
                                            <div style="display: flex; align-items: center;">
                                                <?php if($wt['Donor_ProfilePicture']): ?>
                                                    <img src="<?php echo $wt['Donor_ProfilePicture']; ?>" style="width:35px; height:35px; border-radius:50%; margin-right:10px; object-fit:cover;">
                                                <?php else: ?>
                                                    <div style="width:35px; height:35px; border-radius:50%; background:#eee; margin-right:10px; font-size:14px; display:flex; align-items:center; justify-content:center; color:#888;"><i class="fas fa-user"></i></div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight:600; font-size:14px; color:#333;"><?php echo htmlspecialchars($wt['Donor_Name']); ?></div>
                                                    <div style="font-size:12px; color:#777; margin-top:2px;"><?php echo htmlspecialchars($wt['Donor_Email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td>
                                            <div style="font-weight:600; font-size:14px; color:#333; margin-bottom:4px;">
                                                <?php echo htmlspecialchars($desc); ?>
                                            </div>
                                            <?php if($targetInfo): ?>
                                                <div style="font-size:12px; color:#17a2b8; margin-bottom:2px;">
                                                    <i class="fas fa-tag" style="font-size:10px;"></i> <?php echo htmlspecialchars($targetInfo); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if($payMethod): ?>
                                                <div style="font-size:12px; color:#666; margin-bottom:2px;">
                                                    <i class="fas fa-credit-card" style="font-size:10px;"></i> Method: <?php echo htmlspecialchars($payMethod); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div style="font-size:12px; color:#888;">
                                                Ref: #<?php echo $txnID; ?>
                                            </div>
                                        </td>

                                        <td style="text-align: center;">
                                            <span class="badge <?php echo $isCredit ? 'badge-credit' : 'badge-debit'; ?>">
                                                <?php echo $isCredit ? 'Credit' : 'Debit'; ?>
                                            </span>
                                        </td>

                                        <td style="font-weight:700; color: <?php echo $isCredit ? '#28a745' : '#dc3545'; ?>; font-size:14px;">
                                            <?php echo $isCredit ? '+' : '-'; ?> RM <?php echo number_format($wt['Amount'], 2); ?>
                                        </td>

                                        <td>
                                            <a href="admin_ewallet_details.php?id=<?php echo $txnID; ?>" class="btn-action" title="View Wallet Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">No wallet activity found matching your criteria.</td></tr>
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

                            <?php 
                            $wl_range_start = max(1, $page_wl - 1);
                            $wl_range_end = min($total_pages_wl, $page_wl + 1);
                            
                            if($total_pages_wl >= 3) {
                                if($page_wl == 1) { $wl_range_end = 3; } 
                                elseif($page_wl == $total_pages_wl) { $wl_range_start = $total_pages_wl - 2; }
                            } else {
                                $wl_range_start = 1; $wl_range_end = $total_pages_wl;
                            }

                            for($i = $wl_range_start; $i <= $wl_range_end; $i++): 
                                $wlParams['page_wl'] = $i; 
                            ?>
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
        </div>
    </div>

    <div id="sortDateModal" class="sort-modal" onclick="closeModal('sortDateModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter & Sort Date</h3><span class="sort-close" onclick="closeModal('sortDateModal')">&times;</span></div>
            
            <form method="GET" class="modal-filter-group">
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="hidden" name="order" value="<?php echo $order; ?>">
                <input type="hidden" name="search_wl" value="<?php echo htmlspecialchars($search_wl); ?>">
                <input type="hidden" name="f_type" value="<?php echo $f_type; ?>">
                <input type="hidden" name="f_min" value="<?php echo $f_min; ?>">
                <input type="hidden" name="f_max" value="<?php echo $f_max; ?>">
                <input type="hidden" name="f_target_type" value="<?php echo $f_target_type; ?>">

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

    <div id="sortDonorModal" class="sort-modal" onclick="closeModal('sortDonorModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort Donor</h3><span class="sort-close" onclick="closeModal('sortDonorModal')">&times;</span></div>
            
            <div style="font-size:12px; color:#888; margin-bottom:15px;">
                Use the main search bar to find specific donors.
            </div>

            <a href="<?php echo buildUrl(['sort'=>'donor', 'order'=>'asc']); ?>" class="sort-btn">A to Z <i class="fas fa-sort-alpha-down"></i></a>
            <a href="<?php echo buildUrl(['sort'=>'donor', 'order'=>'desc']); ?>" class="sort-btn">Z to A <i class="fas fa-sort-alpha-up"></i></a>
        </div>
    </div>

    <div id="sortDetailsModal" class="sort-modal" onclick="closeModal('sortDetailsModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter Details</h3><span class="sort-close" onclick="closeModal('sortDetailsModal')">&times;</span></div>
            
            <form method="GET" class="modal-filter-group">
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="hidden" name="order" value="<?php echo $order; ?>">
                <input type="hidden" name="search_wl" value="<?php echo htmlspecialchars($search_wl); ?>">
                
                <input type="hidden" name="f_date_y" value="<?php echo $f_date_y; ?>">
                <input type="hidden" name="f_date_m" value="<?php echo $f_date_m; ?>">
                <input type="hidden" name="f_date_d" value="<?php echo $f_date_d; ?>">
                <input type="hidden" name="f_type" value="<?php echo $f_type; ?>">
                <input type="hidden" name="f_min" value="<?php echo $f_min; ?>">
                <input type="hidden" name="f_max" value="<?php echo $f_max; ?>">

                <span class="modal-filter-label">Filter by Target Context:</span>
                <select name="f_target_type" class="modal-input">
                    <option value="">All Contexts</option>
                    <option value="branch" <?php echo ($f_target_type=='branch')?'selected':''; ?>>Branch</option>
                    <option value="activity" <?php echo ($f_target_type=='activity')?'selected':''; ?>>Activity</option>
                    <option value="case" <?php echo ($f_target_type=='case')?'selected':''; ?>>Special Case</option>
                </select>
                <button type="submit" class="modal-apply-btn">Apply Filter</button>
            </form>

            <a href="<?php echo buildUrl(['sort'=>'details', 'order'=>'asc']); ?>" class="sort-btn">A to Z <i class="fas fa-sort-alpha-down"></i></a>
            <a href="<?php echo buildUrl(['sort'=>'details', 'order'=>'desc']); ?>" class="sort-btn">Z to A <i class="fas fa-sort-alpha-up"></i></a>
        </div>
    </div>

    <div id="sortTypeModal" class="sort-modal" onclick="closeModal('sortTypeModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter & Sort Type</h3><span class="sort-close" onclick="closeModal('sortTypeModal')">&times;</span></div>
            
            <form method="GET" class="modal-filter-group">
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="hidden" name="order" value="<?php echo $order; ?>">
                <input type="hidden" name="search_wl" value="<?php echo htmlspecialchars($search_wl); ?>">
                
                <input type="hidden" name="f_date_y" value="<?php echo $f_date_y; ?>">
                <input type="hidden" name="f_date_m" value="<?php echo $f_date_m; ?>">
                <input type="hidden" name="f_date_d" value="<?php echo $f_date_d; ?>">
                <input type="hidden" name="f_min" value="<?php echo $f_min; ?>">
                <input type="hidden" name="f_max" value="<?php echo $f_max; ?>">
                <input type="hidden" name="f_target_type" value="<?php echo $f_target_type; ?>">

                <span class="modal-filter-label">Filter by Type:</span>
                <select name="f_type" class="modal-input">
                    <option value="">All Types</option>
                    <option value="Credit" <?php echo ($f_type=='Credit')?'selected':''; ?>>Credit (Top-up)</option>
                    <option value="Debit" <?php echo ($f_type=='Debit')?'selected':''; ?>>Debit (Spent)</option>
                </select>
                <button type="submit" class="modal-apply-btn">Apply Filter</button>
            </form>

            <a href="<?php echo buildUrl(['sort'=>'type', 'order'=>'asc']); ?>" class="sort-btn">Credit First <i class="fas fa-sort-alpha-down"></i></a>
            <a href="<?php echo buildUrl(['sort'=>'type', 'order'=>'desc']); ?>" class="sort-btn">Debit First <i class="fas fa-sort-alpha-up"></i></a>
        </div>
    </div>

    <div id="sortAmountModal" class="sort-modal" onclick="closeModal('sortAmountModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter & Sort Amount</h3><span class="sort-close" onclick="closeModal('sortAmountModal')">&times;</span></div>
            
            <form method="GET" class="modal-filter-group">
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="hidden" name="order" value="<?php echo $order; ?>">
                <input type="hidden" name="search_wl" value="<?php echo htmlspecialchars($search_wl); ?>">
                
                <input type="hidden" name="f_date_y" value="<?php echo $f_date_y; ?>">
                <input type="hidden" name="f_date_m" value="<?php echo $f_date_m; ?>">
                <input type="hidden" name="f_date_d" value="<?php echo $f_date_d; ?>">
                <input type="hidden" name="f_type" value="<?php echo $f_type; ?>">
                <input type="hidden" name="f_target_type" value="<?php echo $f_target_type; ?>">

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

    <script>
        function openModal(id) { document.getElementById(id).style.display = "flex"; }
        function closeModal(id, e) {
            if (!e || e.target.id === id || e.target.classList.contains('sort-close')) {
                document.getElementById(id).style.display = "none";
            }
        }

        function updateDays(yearId, monthId, dayId) {
            const yearSelect = document.getElementById(yearId);
            const monthSelect = document.getElementById(monthId);
            const dayInput = document.getElementById(dayId);

            const year = parseInt(yearSelect.value) || new Date().getFullYear();
            const month = parseInt(monthSelect.value);

            if (month) {
                // Get last day of the month
                const daysInMonth = new Date(year, month, 0).getDate();
                dayInput.max = daysInMonth;
                
                // If current value exceeds max, reset it
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
            });

            if (type === 'date') {
                document.getElementById('filter_date_container').classList.add('active');
            } else if (type === 'type') {
                document.getElementById('filter_type_container').classList.add('active');
            } else if (type === 'target') {
                document.getElementById('filter_target_container').classList.add('active');
            } else if (type === 'amount') {
                document.getElementById('filter_amount_container').classList.add('active');
            }
        }

        // Run on load to set initial state
        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
        });
    </script>
</body>
</html>