<?php
// view_history.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// Check ID
if (!isset($_GET['id']) || $_GET['id'] === '') {
    header("Location: reward_item_management.php");
    exit();
}

$rewardId = intval($_GET['id']);
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'redemptions';

// Fetch Item Info
$itemSql = "SELECT Reward_ItemName, Reward_Code, Reward_Stock FROM reward_item WHERE Reward_ID = $rewardId";
$itemRes = $conn->query($itemSql);

if (!$itemRes || $itemRes->num_rows == 0) {
    header("Location: reward_item_management.php?error=Item Not Found");
    exit();
}
$item = $itemRes->fetch_assoc();

// ==========================================
// 1. REDEMPTION LOGIC
// ==========================================
$searchRedeem = isset($_GET['search_redeem']) ? trim($_GET['search_redeem']) : '';
$sortRedeem = isset($_GET['sort_redeem']) ? $_GET['sort_redeem'] : 'date';
$orderRedeem = isset($_GET['order_redeem']) ? $_GET['order_redeem'] : 'desc';

// Date Filters
$f_date_redeem = isset($_GET['f_date_redeem']) ? $_GET['f_date_redeem'] : '';
$f_year_redeem = isset($_GET['f_year_redeem']) ? $_GET['f_year_redeem'] : '';
$f_month_redeem = isset($_GET['f_month_redeem']) ? $_GET['f_month_redeem'] : '';

// Quantity Range
$min_qty = isset($_GET['min_qty']) && $_GET['min_qty'] !== '' ? intval($_GET['min_qty']) : '';
$max_qty = isset($_GET['max_qty']) && $_GET['max_qty'] !== '' ? intval($_GET['max_qty']) : '';

// Status Filter (Multi-select array)
$f_status_redeem = isset($_GET['f_status_redeem']) ? $_GET['f_status_redeem'] : [];
// Ensure it's an array
if (!is_array($f_status_redeem) && $f_status_redeem !== '') {
    $f_status_redeem = explode(',', $f_status_redeem);
} elseif ($f_status_redeem === '') {
    $f_status_redeem = [];
}

// Sort Mapping
$sortMapRedeem = [
    'date' => 'r.Redemption_Updated_At',
    'id' => 'r.Redemption_ID',
    'donor' => 'd.Donor_Name',
    'qty' => 'r.Redemption_Quantity',
    'status' => 'r.Redemption_Status'
];
$orderByRedeem = isset($sortMapRedeem[$sortRedeem]) ? $sortMapRedeem[$sortRedeem] : 'r.Redemption_Updated_At';
$dirRedeem = ($orderRedeem == 'asc') ? 'ASC' : 'DESC';

// Build Query
$whereRedeem = "WHERE r.Reward_ID = $rewardId";

// Search
if ($searchRedeem) {
    $s = $conn->real_escape_string($searchRedeem);
    $whereRedeem .= " AND (r.Redemption_ID LIKE '%$s%' OR d.Donor_Name LIKE '%$s%' OR r.Redemption_Status LIKE '%$s%')";
}

// Status (Multi-select)
if (!empty($f_status_redeem)) {
    $statusStr = "";
    foreach ($f_status_redeem as $st) {
        $statusStr .= "'" . $conn->real_escape_string($st) . "',";
    }
    $statusStr = rtrim($statusStr, ',');
    $whereRedeem .= " AND r.Redemption_Status IN ($statusStr)";
}

// Date Logic
if (!empty($f_date_redeem)) {
    $d = $conn->real_escape_string($f_date_redeem);
    $whereRedeem .= " AND DATE(r.Redemption_Updated_At) = '$d'";
} else {
    if (!empty($f_year_redeem)) {
        $y = $conn->real_escape_string($f_year_redeem);
        $whereRedeem .= " AND YEAR(r.Redemption_Updated_At) = '$y'";
    }
    if (!empty($f_month_redeem)) {
        $m = $conn->real_escape_string($f_month_redeem);
        $whereRedeem .= " AND MONTH(r.Redemption_Updated_At) = '$m'";
    }
}

// Quantity Range
if ($min_qty !== '') {
    $whereRedeem .= " AND r.Redemption_Quantity >= $min_qty";
}
if ($max_qty !== '') {
    $whereRedeem .= " AND r.Redemption_Quantity <= $max_qty";
}

$sqlRedeem = "SELECT r.Redemption_ID, r.Redemption_Updated_At, d.Donor_Name, r.Redemption_PointsSpent, r.Redemption_Status, r.Redemption_Quantity
              FROM redemption_order r 
              JOIN donor d ON r.Donor_ID = d.Donor_ID 
              $whereRedeem 
              ORDER BY $orderByRedeem $dirRedeem";
$resRedeem = $conn->query($sqlRedeem);
$redemptions = [];
if ($resRedeem) while($row = $resRedeem->fetch_assoc()) $redemptions[] = $row;


// ==========================================
// 2. AUDIT LOG LOGIC
// ==========================================
$searchAudit = isset($_GET['search_audit']) ? trim($_GET['search_audit']) : '';
$sortAudit = isset($_GET['sort_audit']) ? $_GET['sort_audit'] : 'date';
$orderAudit = isset($_GET['order_audit']) ? $_GET['order_audit'] : 'desc';

// Filters
$f_date_audit = isset($_GET['f_date_audit']) ? $_GET['f_date_audit'] : '';
$f_year_audit = isset($_GET['f_year_audit']) ? $_GET['f_year_audit'] : '';
$f_month_audit = isset($_GET['f_month_audit']) ? $_GET['f_month_audit'] : '';
$f_role_audit = isset($_GET['f_role_audit']) ? $_GET['f_role_audit'] : '';

// Sort Mapping
$sortMapAudit = [
    'date' => 'l.Log_Created_At',
    'action' => 'l.Action_Type',
    'user' => 'User_Name'
];

if($sortAudit == 'user') $orderByAudit = 'a.Admin_Name'; 
else $orderByAudit = isset($sortMapAudit[$sortAudit]) ? $sortMapAudit[$sortAudit] : 'l.Log_Created_At';

$dirAudit = ($orderAudit == 'asc') ? 'ASC' : 'DESC';

// Build Query
$whereAudit = "WHERE l.Reward_ID = $rewardId";

if ($searchAudit) {
    $s = $conn->real_escape_string($searchAudit);
    $whereAudit .= " AND (l.Action_Type LIKE '%$s%' OR l.Action_Details LIKE '%$s%' OR a.Admin_Name LIKE '%$s%' OR s.Staff_FullName LIKE '%$s%')";
}

// Date Logic
if (!empty($f_date_audit)) {
    $d = $conn->real_escape_string($f_date_audit);
    $whereAudit .= " AND DATE(l.Log_Created_At) = '$d'";
} else {
    if (!empty($f_year_audit)) {
        $y = $conn->real_escape_string($f_year_audit);
        $whereAudit .= " AND YEAR(l.Log_Created_At) = '$y'";
    }
    if (!empty($f_month_audit)) {
        $m = $conn->real_escape_string($f_month_audit);
        $whereAudit .= " AND MONTH(l.Log_Created_At) = '$m'";
    }
}

// Role Logic
if (!empty($f_role_audit)) {
    $role = $conn->real_escape_string($f_role_audit);
    if ($role == 'Staff') {
        $whereAudit .= " AND l.Admin_ID IN (SELECT Staff_ID FROM staff)";
    } elseif ($role == 'Super Admin') {
        $whereAudit .= " AND a.Admin_Role = 'Super Admin'";
    } elseif ($role == 'Admin') {
        $whereAudit .= " AND a.Admin_Role = 'Admin'";
    }
}

$sqlLog = "SELECT l.Log_ID, l.Log_Created_At, a.Admin_Name, a.Admin_Role, s.Staff_FullName, l.Action_Type, l.Action_Details 
           FROM reward_logs l 
           LEFT JOIN admin a ON l.Admin_ID = a.Admin_ID 
           LEFT JOIN staff s ON l.Admin_ID = s.Staff_ID
           $whereAudit 
           ORDER BY $orderByAudit $dirAudit";
$resLog = $conn->query($sqlLog);
$logs = [];
if ($resLog) {
    while($row = $resLog->fetch_assoc()) {
        $row['User_Name'] = !empty($row['Admin_Name']) ? $row['Admin_Name'] : (!empty($row['Staff_FullName']) ? $row['Staff_FullName'] : 'Unknown User');
        
        // Determine Role for Display
        if (!empty($row['Admin_Role'])) $row['Display_Role'] = $row['Admin_Role'];
        elseif (!empty($row['Staff_FullName'])) $row['Display_Role'] = 'Staff';
        else $row['Display_Role'] = '-';

        $logs[] = $row;
    }
}

// Helper Arrays
$years = range(date('Y'), 2024);
$months = [
    '1'=>'January','2'=>'February','3'=>'March','4'=>'April',
    '5'=>'May','6'=>'June','7'=>'July','8'=>'August',
    '9'=>'September','10'=>'October','11'=>'November','12'=>'December'
];

function buildUrl($params) {
    $current = $_GET;
    $merged = array_merge($current, $params);
    return '?' . http_build_query($merged);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item History - DonationMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        :root { --primary: #F28585; }
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; cursor: pointer; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        
        .content-container { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        .item-summary { display: flex; gap: 20px; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px; align-items: center; }
        .summary-badge { background: #fff; padding: 5px 10px; border-radius: 4px; border: 1px solid #eee; font-size: 13px; font-weight: 600; color: #555; }
        
        /* Tabs */
        .tabs { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 20px; }
        .tab-btn { padding: 12px 25px; border: none; background: none; cursor: pointer; font-weight: 600; color: #6c757d; border-bottom: 3px solid transparent; transition: all 0.2s; font-size: 14px; }
        .tab-btn:hover { color: #343a40; background: #fdfdfd; }
        .tab-btn.active { color: #F28585; border-bottom-color: #F28585; }
        .tab-content { display: none; animation: fadeIn 0.3s; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Search Bar */
        .search-container { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; width: 100%; }
        .search-form { display: flex; flex: 1; gap: 10px; position: relative; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ced4da; border-radius: 5px; font-size: 14px; outline: none; width: 100%; box-sizing: border-box; }
        .search-input:focus { border-color: #F28585; box-shadow: 0 0 0 2px rgba(242, 133, 133, 0.1); }
        .btn-search { padding: 0 20px; background: #F28585; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; white-space: nowrap; }
        .btn-search:hover { background: #d66565; }

        /* Tables & Headers */
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { padding: 15px; background: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #eee; cursor: pointer; user-select: none; text-align: left; }
        .history-table th:hover { background: #e9ecef; color: #333; }
        .history-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 14px; vertical-align: middle; }
        
        .status-pill { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        
        /* Action Button (Eye Icon) */
        .btn-icon-view { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: #e3f2fd; color: #0d6efd; border-radius: 5px; text-decoration: none; transition: 0.2s; border: 1px solid #cff4fc; }
        .btn-icon-view:hover { background: #0d6efd; color: white; border-color: #0d6efd; }

        /* Modals */
        .sort-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .sort-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; }
        .sort-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .sort-header h3 { margin: 0; font-size: 16px; color: #333; }
        .sort-close { cursor: pointer; font-size: 20px; color: #999; }
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #555; text-decoration: none; display: flex; justify-content: space-between; align-items: center; }
        .sort-btn:hover { background: #f8f9fa; border-color: #ddd; color: var(--primary); }
        .sort-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        /* Filter Styles in Modal */
        .filter-row { display: flex; gap: 5px; margin-bottom: 5px; }
        .filter-select { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; width: 100%; margin-bottom: 8px; }
        .filter-input { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        .btn-apply-full { width: 100%; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 5px; }
        .sort-title { font-size: 12px; font-weight: 600; color: #888; margin-top: 10px; display: block; margin-bottom: 5px; text-transform: uppercase; }

        /* Custom Checkbox Style for Status */
        .checkbox-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
        .checkbox-item { display: flex; align-items: center; gap: 10px; font-size: 14px; color: #555; cursor: pointer; }
        .checkbox-item input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }

        /* NEW: Vertical Layout for Quantity (Matches Branch History) */
        .amount-input-group { display: flex; flex-direction: column; gap: 10px; }
        .amount-row { display: flex; align-items: center; gap: 10px; }
        .amount-label { width: 60px; font-size: 13px; color: #666; font-weight: 600; }

        .no-data { text-align: center; padding: 30px; color: #999; font-style: italic; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="javascript:void(0);" onclick="goBackOrClose()" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                <div style="flex:1; text-align:center; padding-right:80px;"><h1>Item History</h1></div>
            </div>

            <div class="content-container">
                <div class="item-summary">
                    <div><strong>Item Name:</strong> <?php echo htmlspecialchars($item['Reward_ItemName']); ?></div>
                    <div class="summary-badge">Code: <?php echo $item['Reward_Code']; ?></div>
                    <div class="summary-badge">Current Stock: <?php echo $item['Reward_Stock']; ?></div>
                </div>

                <div class="tabs">
                    <button class="tab-btn <?php echo ($activeTab=='redemptions')?'active':''; ?>" onclick="openTab('redemptions')">Redemption History</button>
                    <button class="tab-btn <?php echo ($activeTab=='audit')?'active':''; ?>" onclick="openTab('audit')">Admin Audit Log</button>
                </div>

                <div id="redemptions" class="tab-content <?php echo ($activeTab=='redemptions')?'active':''; ?>">
                    
                    <div class="search-container">
                        <form method="GET" class="search-form">
                            <input type="hidden" name="id" value="<?php echo $rewardId; ?>">
                            <input type="hidden" name="tab" value="redemptions">
                            <input type="text" name="search_redeem" class="search-input" placeholder="Search Order ID, Donor Name or Status..." value="<?php echo htmlspecialchars($searchRedeem); ?>">
                            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                            <?php if($searchRedeem || $f_status_redeem || $f_date_redeem || $min_qty): ?>
                                <a href="view_history.php?id=<?php echo $rewardId; ?>&tab=redemptions" class="back-btn" style="background:#6c757d; color:white; border-color:#6c757d;"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <table class="history-table">
                        <thead>
                            <tr>
                                <th onclick="openModal('redeemDateModal')">Date <?php if($sortRedeem=='date') echo ($orderRedeem=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th onclick="openModal('redeemIdModal')">Order ID <?php if($sortRedeem=='id') echo ($orderRedeem=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th onclick="openModal('redeemDonorModal')">Donor <?php if($sortRedeem=='donor') echo ($orderRedeem=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th style="text-align:center;" onclick="openModal('redeemQtyModal')">Quantity <?php if($sortRedeem=='qty') echo ($orderRedeem=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th style="text-align:center;">Points Spent</th>
                                <th onclick="openModal('redeemStatusModal')">Status <?php if($sortRedeem=='status') echo ($orderRedeem=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($redemptions) > 0): ?>
                                <?php foreach($redemptions as $row): ?>
                                    <?php 
                                        $s = $row['Redemption_Status'];
                                        $color = '#6c757d'; $bg = '#eee';
                                        if($s=='Completed' || $s=='Shipped') { $color='#155724'; $bg='#d4edda'; }
                                        elseif($s=='Pending') { $color='#856404'; $bg='#fff3cd'; }
                                        elseif($s=='Cancelled') { $color='#721c24'; $bg='#f8d7da'; }
                                    ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($row['Redemption_Updated_At'])); ?></td>
                                        <td>#<?php echo $row['Redemption_ID']; ?></td>
                                        <td><?php echo htmlspecialchars($row['Donor_Name']); ?></td>
                                        <td style="text-align:center; font-weight:600;"><?php echo $row['Redemption_Quantity']; ?></td>
                                        <td style="text-align:center; font-weight:600; color:#F28585;"><?php echo $row['Redemption_PointsSpent']; ?></td>
                                        <td><span class="status-pill" style="color:<?php echo $color; ?>; background:<?php echo $bg; ?>;"><?php echo $s; ?></span></td>
                                        <td style="text-align:center;">
                                            <a href="admin_redemption_details.php?id=<?php echo $row['Redemption_ID']; ?>&from_item=<?php echo $rewardId; ?>" class="btn-icon-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="no-data">No redemptions found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="audit" class="tab-content <?php echo ($activeTab=='audit')?'active':''; ?>">
                    
                    <div class="search-container">
                        <form method="GET" class="search-form">
                            <input type="hidden" name="id" value="<?php echo $rewardId; ?>">
                            <input type="hidden" name="tab" value="audit">
                            <input type="text" name="search_audit" class="search-input" placeholder="Search Action, Details or User..." value="<?php echo htmlspecialchars($searchAudit); ?>">
                            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                            <?php if($searchAudit || $f_date_audit || $f_role_audit): ?>
                                <a href="view_history.php?id=<?php echo $rewardId; ?>&tab=audit" class="back-btn" style="background:#6c757d; color:white; border-color:#6c757d;"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <table class="history-table">
                        <thead>
                            <tr>
                                <th onclick="openModal('auditDateModal')">Date & Time <?php if($sortAudit=='date') echo ($orderAudit=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th onclick="openModal('auditUserModal')">Admin / Staff <?php if($sortAudit=='user') echo ($orderAudit=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th onclick="openModal('auditActionModal')">Action Type <?php if($sortAudit=='action') echo ($orderAudit=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th>Details</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logs) > 0): ?>
                                <?php foreach($logs as $row): ?>
                                    <?php 
                                        $cls = '#333';
                                        if($row['Action_Type'] == 'Create') $cls = '#007bff';
                                        elseif(strpos($row['Action_Type'], 'Update') !== false) $cls = '#28a745';
                                        elseif($row['Action_Type'] == 'Delete') $cls = '#dc3545';
                                    ?>
                                    <tr>
                                        <td><?php echo $row['Log_Created_At']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['User_Name']); ?>
                                            <div style="font-size:11px; color:#888;">(<?php echo $row['Display_Role']; ?>)</div>
                                        </td>
                                        <td style="color:<?php echo $cls; ?>; font-weight:600;"><?php echo $row['Action_Type']; ?></td>
                                        <td><?php echo htmlspecialchars(mb_strimwidth($row['Action_Details'], 0, 50, "...")); ?></td>
                                        <td style="text-align:center;">
                                            <a href="admin_audit_details.php?id=<?php echo $row['Log_ID']; ?>" target="_blank" class="btn-icon-view" title="View Log Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="no-data">No audit logs found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="redeemDateModal" class="sort-modal" onclick="closeModal('redeemDateModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort & Filter Date</h3><span class="sort-close" onclick="closeModal('redeemDateModal')">&times;</span></div>
            <a href="<?php echo buildUrl(['tab'=>'redemptions', 'sort_redeem'=>'date', 'order_redeem'=>'desc']); ?>" class="sort-btn">Newest to Oldest <i class="fas fa-sort-amount-down"></i></a>
            <a href="<?php echo buildUrl(['tab'=>'redemptions', 'sort_redeem'=>'date', 'order_redeem'=>'asc']); ?>" class="sort-btn">Oldest to Newest <i class="fas fa-sort-amount-up"></i></a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by Specific Date</span>
            <form method="GET">
                <input type="hidden" name="id" value="<?php echo $rewardId; ?>">
                <input type="hidden" name="tab" value="redemptions">
                <input type="date" name="f_date_redeem" class="filter-input" style="width:100%; margin-bottom:10px;" value="<?php echo htmlspecialchars($f_date_redeem); ?>">
                <button type="submit" class="btn-apply-full">Apply Date</button>
            </form>
            
            <span class="sort-title" style="margin-top:15px;">Filter by Month & Year</span>
            <form method="GET">
                <input type="hidden" name="id" value="<?php echo $rewardId; ?>">
                <input type="hidden" name="tab" value="redemptions">
                <select name="f_year_redeem" class="filter-select">
                    <option value="">All Years</option>
                    <?php foreach($years as $y) echo "<option value='$y' ".($f_year_redeem==$y?'selected':'').">$y</option>"; ?>
                </select>
                <select name="f_month_redeem" class="filter-select">
                    <option value="">All Months</option>
                    <?php foreach($months as $k => $v) echo "<option value='$k' ".($f_month_redeem==$k?'selected':'').">$v</option>"; ?>
                </select>
                <button type="submit" class="btn-apply-full">Apply Month/Year</button>
            </form>
        </div>
    </div>

    <div id="redeemIdModal" class="sort-modal" onclick="closeModal('redeemIdModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort Order ID</h3><span class="sort-close" onclick="closeModal('redeemIdModal')">&times;</span></div>
            <a href="<?php echo buildUrl(['tab'=>'redemptions', 'sort_redeem'=>'id', 'order_redeem'=>'desc']); ?>" class="sort-btn">High to Low <i class="fas fa-sort-numeric-down-alt"></i></a>
            <a href="<?php echo buildUrl(['tab'=>'redemptions', 'sort_redeem'=>'id', 'order_redeem'=>'asc']); ?>" class="sort-btn">Low to High <i class="fas fa-sort-numeric-up"></i></a>
        </div>
    </div>

    <div id="redeemDonorModal" class="sort-modal" onclick="closeModal('redeemDonorModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort Donor Name</h3><span class="sort-close" onclick="closeModal('redeemDonorModal')">&times;</span></div>
            <a href="<?php echo buildUrl(['tab'=>'redemptions', 'sort_redeem'=>'donor', 'order_redeem'=>'asc']); ?>" class="sort-btn">A to Z <i class="fas fa-sort-alpha-down"></i></a>
            <a href="<?php echo buildUrl(['tab'=>'redemptions', 'sort_redeem'=>'donor', 'order_redeem'=>'desc']); ?>" class="sort-btn">Z to A <i class="fas fa-sort-alpha-up"></i></a>
        </div>
    </div>

    <div id="redeemQtyModal" class="sort-modal" onclick="closeModal('redeemQtyModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort & Filter Quantity</h3><span class="sort-close" onclick="closeModal('redeemQtyModal')">&times;</span></div>
            <a href="<?php echo buildUrl(['tab'=>'redemptions', 'sort_redeem'=>'qty', 'order_redeem'=>'desc']); ?>" class="sort-btn">Highest Qty <i class="fas fa-sort-numeric-down-alt"></i></a>
            <a href="<?php echo buildUrl(['tab'=>'redemptions', 'sort_redeem'=>'qty', 'order_redeem'=>'asc']); ?>" class="sort-btn">Lowest Qty <i class="fas fa-sort-numeric-up"></i></a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Quantity Range</span>
            <form class="amount-input-group" method="GET">
                <?php foreach($_GET as $key => $val): 
                    if(!in_array($key, ['min_qty', 'max_qty', 'tab', 'id'])) { 
                         if(is_array($val)) {
                             foreach($val as $v) echo "<input type='hidden' name='{$key}[]' value='{$v}'>";
                         } else {
                             echo "<input type='hidden' name='$key' value='$val'>";
                         }
                    }
                endforeach; ?>
                <input type="hidden" name="id" value="<?php echo $rewardId; ?>">
                <input type="hidden" name="tab" value="redemptions">
                
                <div class="amount-row">
                    <span class="amount-label">Min:</span>
                    <input type="number" name="min_qty" class="filter-input" value="<?php echo $min_qty; ?>">
                </div>
                <div class="amount-row">
                    <span class="amount-label">Max:</span>
                    <input type="number" name="max_qty" class="filter-input" value="<?php echo $max_qty; ?>">
                </div>
                <button type="submit" class="btn-apply-full">Apply Range</button>
            </form>
        </div>
    </div>

    <div id="redeemStatusModal" class="sort-modal" onclick="closeModal('redeemStatusModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter Status (Multi-select)</h3><span class="sort-close" onclick="closeModal('redeemStatusModal')">&times;</span></div>
            <a href="<?php echo buildUrl(['tab'=>'redemptions', 'sort_redeem'=>'status', 'order_redeem'=>'asc']); ?>" class="sort-btn" style="margin-bottom:15px;">Sort Status A-Z <i class="fas fa-sort-alpha-down"></i></a>
            
            <form method="GET">
                <input type="hidden" name="id" value="<?php echo $rewardId; ?>">
                <input type="hidden" name="tab" value="redemptions">
                
                <div class="checkbox-group">
                    <label class="checkbox-item">
                        <input type="checkbox" name="f_status_redeem[]" value="Pending" <?php if(in_array('Pending', $f_status_redeem)) echo 'checked'; ?>>
                        Pending
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="f_status_redeem[]" value="Approved" <?php if(in_array('Approved', $f_status_redeem)) echo 'checked'; ?>>
                        Approved / Processing
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="f_status_redeem[]" value="Shipped" <?php if(in_array('Shipped', $f_status_redeem)) echo 'checked'; ?>>
                        Shipped
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="f_status_redeem[]" value="Completed" <?php if(in_array('Completed', $f_status_redeem)) echo 'checked'; ?>>
                        Completed
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="f_status_redeem[]" value="Cancelled" <?php if(in_array('Cancelled', $f_status_redeem)) echo 'checked'; ?>>
                        Rejected / Cancelled
                    </label>
                </div>
                
                <button type="submit" class="btn-apply-full">Apply Filters</button>
            </form>
        </div>
    </div>


    <div id="auditDateModal" class="sort-modal" onclick="closeModal('auditDateModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort & Filter Date</h3><span class="sort-close" onclick="closeModal('auditDateModal')">&times;</span></div>
            <a href="<?php echo buildUrl(['tab'=>'audit', 'sort_audit'=>'date', 'order_audit'=>'desc']); ?>" class="sort-btn">Newest to Oldest <i class="fas fa-sort-amount-down"></i></a>
            <a href="<?php echo buildUrl(['tab'=>'audit', 'sort_audit'=>'date', 'order_audit'=>'asc']); ?>" class="sort-btn">Oldest to Newest <i class="fas fa-sort-amount-up"></i></a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by Specific Date</span>
            <form method="GET">
                <input type="hidden" name="id" value="<?php echo $rewardId; ?>">
                <input type="hidden" name="tab" value="audit">
                <input type="date" name="f_date_audit" class="filter-input" style="width:100%; margin-bottom:10px;" value="<?php echo htmlspecialchars($f_date_audit); ?>">
                <button type="submit" class="btn-apply-full">Apply Date</button>
            </form>
            
            <span class="sort-title" style="margin-top:15px;">Filter by Month & Year</span>
            <form method="GET">
                <input type="hidden" name="id" value="<?php echo $rewardId; ?>">
                <input type="hidden" name="tab" value="audit">
                <select name="f_year_audit" class="filter-select">
                    <option value="">All Years</option>
                    <?php foreach($years as $y) echo "<option value='$y' ".($f_year_audit==$y?'selected':'').">$y</option>"; ?>
                </select>
                <select name="f_month_audit" class="filter-select">
                    <option value="">All Months</option>
                    <?php foreach($months as $k => $v) echo "<option value='$k' ".($f_month_audit==$k?'selected':'').">$v</option>"; ?>
                </select>
                <button type="submit" class="btn-apply-full">Apply Month/Year</button>
            </form>
        </div>
    </div>

    <div id="auditUserModal" class="sort-modal" onclick="closeModal('auditUserModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter User Role</h3><span class="sort-close" onclick="closeModal('auditUserModal')">&times;</span></div>
            <a href="<?php echo buildUrl(['tab'=>'audit', 'sort_audit'=>'user', 'order_audit'=>'asc']); ?>" class="sort-btn">Name A to Z <i class="fas fa-sort-alpha-down"></i></a>
            <a href="<?php echo buildUrl(['tab'=>'audit', 'sort_audit'=>'user', 'order_audit'=>'desc']); ?>" class="sort-btn">Name Z to A <i class="fas fa-sort-alpha-up"></i></a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by Role</span>
            <a href="<?php echo buildUrl(['tab'=>'audit', 'f_role_audit'=>'Super Admin']); ?>" class="sort-btn">Super Admin Only</a>
            <a href="<?php echo buildUrl(['tab'=>'audit', 'f_role_audit'=>'Admin']); ?>" class="sort-btn">Admin Only</a>
            <a href="<?php echo buildUrl(['tab'=>'audit', 'f_role_audit'=>'Staff']); ?>" class="sort-btn">Staff Only</a>
            <a href="<?php echo buildUrl(['tab'=>'audit', 'f_role_audit'=>'']); ?>" class="sort-btn" style="background:#eee;">Show All</a>
        </div>
    </div>

    <div id="auditActionModal" class="sort-modal" onclick="closeModal('auditActionModal', event)">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort Action</h3><span class="sort-close" onclick="closeModal('auditActionModal')">&times;</span></div>
            <a href="<?php echo buildUrl(['tab'=>'audit', 'sort_audit'=>'action', 'order_audit'=>'asc']); ?>" class="sort-btn">Action A-Z <i class="fas fa-sort-alpha-down"></i></a>
            <a href="<?php echo buildUrl(['tab'=>'audit', 'sort_audit'=>'action', 'order_audit'=>'desc']); ?>" class="sort-btn">Action Z-A <i class="fas fa-sort-alpha-up"></i></a>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.replaceState(null, '', url); 
            
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            document.getElementById(tabName).style.display = 'block';
            document.querySelectorAll('.tab-btn').forEach(btn => {
                if(btn.getAttribute('onclick').includes(tabName)) btn.classList.add('active');
            });
        }

        function openModal(id) {
            document.getElementById(id).style.display = "flex";
        }

        function closeModal(id, e) {
            if (!e || e.target.id === id || e.target.classList.contains('close-filter') || e.target.classList.contains('sort-close')) {
                document.getElementById(id).style.display = "none";
            }
        }

        function goBackOrClose() {
            if (window.opener) {
                window.close();
            } else {
                window.location.href = 'reward_item_management.php';
            }
        }
    </script>
</body>
</html>