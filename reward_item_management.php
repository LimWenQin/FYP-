<?php
// reward_item_management.php
ob_start(); // Start output buffering
session_start();

// 1. Check Login
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- GET ADMIN/STAFF INFO ---
$adminName = "User";
$currentAdminId = 0;

if (isset($_SESSION['admin_id'])) {
    $currentAdminId = $_SESSION['admin_id'];
    $adminSql = "SELECT Admin_Name FROM admin WHERE Admin_ID = $currentAdminId";
    $adminResult = $conn->query($adminSql);
    if ($adminResult && $adminResult->num_rows > 0) {
        $adminData = $adminResult->fetch_assoc();
        $adminName = $adminData['Admin_Name'];
    }
} elseif (isset($_SESSION['staff_id'])) {
    $currentAdminId = $_SESSION['staff_id'];
    $staffSql = "SELECT Staff_FullName FROM staff WHERE Staff_ID = $currentAdminId";
    $staffResult = $conn->query($staffSql);
    if ($staffResult && $staffResult->num_rows > 0) {
        $staffData = $staffResult->fetch_assoc();
        $adminName = $staffData['Staff_FullName'];
    }
}

// --- HELPER: Log Admin Action ---
function logRewardAction($conn, $rewardId, $adminId, $type, $details) {
    $details = $conn->real_escape_string($details);
    $sql = "INSERT INTO reward_logs (Reward_ID, Admin_ID, Action_Type, Action_Details) 
            VALUES ('$rewardId', '$adminId', '$type', '$details')";
    $conn->query($sql);
}

// --- HELPER: Build URL for Sorting ---
function buildUrl($params) {
    $current = $_GET;
    unset($current['page']); 
    $merged = array_merge($current, $params);
    return '?' . http_build_query($merged);
}

// --- HELPER: Generate Hidden Inputs ---
function getHiddenInputs($excludeKey = []) {
    $html = '';
    $params = $_GET;
    unset($params['page']); 
    
    if (!is_array($excludeKey)) $excludeKey = [$excludeKey];
    foreach ($excludeKey as $key) { unset($params[$key]); }
    
    foreach ($params as $key => $val) {
        if (is_array($val)) {
            foreach ($val as $v) {
                $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '[]" value="' . htmlspecialchars($v) . '">';
            }
        } else {
            $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '">';
        }
    }
    return $html;
}

// --- EXPORT HANDLERS ---
if (isset($_GET['action']) && $_GET['action'] == 'export_item_history' && isset($_GET['id'])) {
    ob_end_clean(); 
    $itemId = intval($_GET['id']);
    $itemQ = $conn->query("SELECT Reward_ItemName, Reward_Code, Reward_Stock FROM reward_item WHERE Reward_ID = $itemId");
    $itemInfo = $itemQ->fetch_assoc();
    $itemNameClean = preg_replace('/[^A-Za-z0-9\-]/', '_', $itemInfo['Reward_ItemName']);
    $filename = "History_" . $itemInfo['Reward_Code'] . "_" . $itemNameClean . "_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache"); header("Expires: 0"); echo "\xEF\xBB\xBF";
    
    echo '<style>table{border-collapse:collapse;width:100%;} th{background:#f2f2f2;border:1px solid #999;padding:10px;} td{border:1px solid #999;padding:8px;}</style>';
    echo '<table><tr><td colspan="9" style="font-size:16px;font-weight:bold;">REPORT FOR: ' . $itemInfo['Reward_ItemName'] . ' (' . $itemInfo['Reward_Code'] . ')</td></tr>';
    echo '<tr><td colspan="9">Current Stock: <strong>' . $itemInfo['Reward_Stock'] . '</strong> | Generated: ' . date('d M Y, h:i A') . '</td></tr><tr><td colspan="9"></td></tr>'; 
    echo '<tr><th colspan="9" style="background:#444;color:white;">REDEMPTION HISTORY</th></tr>';
    echo '<tr><th>ID</th><th>Date</th><th>Donor</th><th>Email</th><th>Contact</th><th>IC</th><th>Points</th><th>Status</th><th>Tracking</th></tr>';
    $sqlRedeem = "SELECT r.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_ICNumber FROM redemption_order r JOIN donor d ON r.Donor_ID = d.Donor_ID WHERE r.Reward_ID = $itemId ORDER BY r.Redemption_Updated_At DESC";
    $resRedeem = $conn->query($sqlRedeem);
    if ($resRedeem && $resRedeem->num_rows > 0) {
        while($row = $resRedeem->fetch_assoc()) {
            echo '<tr><td>'.$row['Redemption_ID'].'</td><td>'.$row['Redemption_Updated_At'].'</td><td>'.htmlspecialchars($row['Donor_Name']).'</td><td>'.htmlspecialchars($row['Donor_Email']).'</td><td>'.htmlspecialchars($row['Donor_ContactNumber']).'</td><td style="mso-number-format:\'\@\'">'.htmlspecialchars($row['Donor_ICNumber']).'</td><td>'.$row['Redemption_PointsSpent'].'</td><td>'.$row['Redemption_Status'].'</td><td>'.htmlspecialchars($row['Redemption_TrackingNumber']).'</td></tr>';
        }
    } else { echo '<tr><td colspan="9" style="text-align:center;">No history.</td></tr>'; }
    echo '<tr><td colspan="9"></td></tr><tr><th colspan="9" style="background:#444;color:white;">AUDIT LOG</th></tr>';
    echo '<tr><th colspan="2">Date</th><th colspan="2">Admin</th><th colspan="2">Email</th><th>Action</th><th colspan="2">Details</th></tr>';
    $sqlLog = "SELECT l.*, a.Admin_Name, a.Admin_Email FROM reward_logs l JOIN admin a ON l.Admin_ID = a.Admin_ID WHERE l.Reward_ID = $itemId ORDER BY l.Log_Created_At DESC";
    $resLog = $conn->query($sqlLog);
    if ($resLog && $resLog->num_rows > 0) {
        while($row = $resLog->fetch_assoc()) {
            echo '<tr><td colspan="2">'.$row['Log_Created_At'].'</td><td colspan="2">'.htmlspecialchars($row['Admin_Name']).'</td><td colspan="2">'.htmlspecialchars($row['Admin_Email']).'</td><td>'.$row['Action_Type'].'</td><td colspan="2">'.htmlspecialchars($row['Action_Details']).'</td></tr>';
        }
    } else { echo '<tr><td colspan="9" style="text-align:center;">No logs.</td></tr>'; }
    echo '</table>'; exit();
}

if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    ob_end_clean();
    $filename = "All_Rewards_Report_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache"); header("Expires: 0"); echo "\xEF\xBB\xBF";
    echo '<table border="1"><tr><th>Code</th><th>Name</th><th>Category</th><th>Stock</th><th>Points</th><th>Status</th><th>Supplier</th></tr>';
    $resInv = $conn->query("SELECT * FROM reward_item ORDER BY Reward_Category ASC, Reward_ItemName ASC");
    if ($resInv) while($row = $resInv->fetch_assoc()) {
        echo '<tr><td>'.htmlspecialchars($row['Reward_Code']).'</td><td>'.htmlspecialchars($row['Reward_ItemName']).'</td><td>'.htmlspecialchars($row['Reward_Category']).'</td><td>'.$row['Reward_Stock'].'</td><td>'.$row['Reward_RequiredPoint'].'</td><td>'.$row['Reward_Status'].'</td><td>'.htmlspecialchars($row['Reward_Supplier']).'</td></tr>';
    }
    echo '</table>'; exit();
}

// --- HANDLE DELETE ---
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $name = $conn->real_escape_string($_GET['name']);
    logRewardAction($conn, $id, $currentAdminId, 'Delete', "Deleted item: $name");
    $res = $conn->query("SELECT Reward_PhotoPath FROM reward_item WHERE Reward_ID = $id");
    if ($row = $res->fetch_assoc()) {
        $path = "uploads/rewards/" . $row['Reward_PhotoPath'];
        if (file_exists($path) && !empty($row['Reward_PhotoPath'])) unlink($path);
    }
    $conn->query("DELETE FROM reward_item WHERE Reward_ID = $id");
    header("Location: reward_item_management.php?success=Item Deleted");
    exit();
}

// ==========================================
// FILTERING LOGIC (MERGED OLD & NEW)
// ==========================================
$whereConditions = ["1=1"];
$searchTerm = "";
$filterType = "";

// 1. OLD Top Bar Logic
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereConditions[] = "(Reward_ItemName LIKE '%$searchTerm%' OR Reward_Code LIKE '%$searchTerm%' OR Reward_Description LIKE '%$searchTerm%')";
}

if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    if ($filterType == 'status' && !empty($_GET['filter_val_status'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_status']);
        $whereConditions[] = "Reward_Status = '$filterValue'";
    } elseif ($filterType == 'points' && !empty($_GET['filter_val_points'])) {
        $filterValue = $_GET['filter_val_points'];
        if ($filterValue == 'low') $whereConditions[] = "Reward_RequiredPoint < 15";
        elseif ($filterValue == 'mid') $whereConditions[] = "Reward_RequiredPoint BETWEEN 15 AND 35";
        elseif ($filterValue == 'high') $whereConditions[] = "Reward_RequiredPoint > 35";
    } elseif ($filterType == 'stock' && !empty($_GET['filter_val_stock'])) {
        $filterValue = $_GET['filter_val_stock'];
        if ($filterValue == 'low') $whereConditions[] = "Reward_Stock < 10";
        elseif ($filterValue == 'mid') $whereConditions[] = "Reward_Stock BETWEEN 10 AND 200";
        elseif ($filterValue == 'high') $whereConditions[] = "Reward_Stock > 200";
    } elseif ($filterType == 'category' && !empty($_GET['filter_val_category'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_category']);
        $whereConditions[] = "Reward_Category = '$filterValue'";
    }
}

// 2. NEW Header Modal Logic (Compatible)
// Category Filter
$f_category = isset($_GET['f_category']) ? $conn->real_escape_string($_GET['f_category']) : '';
if ($f_category) {
    $whereConditions[] = "Reward_Category = '$f_category'";
}
// Points Range
$min_point = isset($_GET['min_point']) && $_GET['min_point'] !== '' ? intval($_GET['min_point']) : '';
$max_point = isset($_GET['max_point']) && $_GET['max_point'] !== '' ? intval($_GET['max_point']) : '';
if ($min_point !== '') $whereConditions[] = "Reward_RequiredPoint >= $min_point";
if ($max_point !== '') $whereConditions[] = "Reward_RequiredPoint <= $max_point";
// Stock Range
$min_stock = isset($_GET['min_stock']) && $_GET['min_stock'] !== '' ? intval($_GET['min_stock']) : '';
$max_stock = isset($_GET['max_stock']) && $_GET['max_stock'] !== '' ? intval($_GET['max_stock']) : '';
if ($min_stock !== '') $whereConditions[] = "Reward_Stock >= $min_stock";
if ($max_stock !== '') $whereConditions[] = "Reward_Stock <= $max_stock";
// Status Filter (Modal)
$f_status = isset($_GET['f_status']) ? $conn->real_escape_string($_GET['f_status']) : '';
if ($f_status) {
    $whereConditions[] = "Reward_Status = '$f_status'";
}
// Supplier Filter (Modal)
$f_supplier = isset($_GET['f_supplier']) ? $conn->real_escape_string($_GET['f_supplier']) : '';
if ($f_supplier) {
    $whereConditions[] = "Reward_Supplier = '$f_supplier'";
}

$whereClause = implode(" AND ", $whereConditions);

// --- SORTING LOGIC ---
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

$sortMap = [
    'id' => 'Reward_ID',
    'name' => 'Reward_ItemName',
    'category' => 'Reward_Category',
    'points' => 'Reward_RequiredPoint',
    'stock' => 'Reward_Stock',
    'status' => 'Reward_Status',
    'supplier' => 'Reward_Supplier'
];
$orderByCol = isset($sortMap[$sort]) ? $sortMap[$sort] : 'Reward_ID';
$dir = ($order == 'asc') ? 'ASC' : 'DESC';

// --- PAGINATION ---
$results_per_page = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$count_sql = "SELECT COUNT(*) as total FROM reward_item WHERE $whereClause";
$total_records = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_records / $results_per_page);

$sqlItems = "SELECT * FROM reward_item WHERE $whereClause ORDER BY $orderByCol $dir LIMIT $start_from, $results_per_page";
$resultItems = $conn->query($sqlItems);

// --- STATS ---
$totalItems = $conn->query("SELECT COUNT(*) as total FROM reward_item")->fetch_assoc()['total'];
$activeRewards = $conn->query("SELECT COUNT(*) as active FROM reward_item WHERE Reward_Status = 'Active'")->fetch_assoc()['active'];
$lowStockCount = $conn->query("SELECT COUNT(*) as low FROM reward_item WHERE Reward_Stock < 15")->fetch_assoc()['low'];
$totalRedemptions = $conn->query("SELECT COUNT(*) as total FROM redemption_order")->fetch_assoc()['total'];

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// Constants
$categories = ['Household', 'Apparel', 'Handicraft', 'Electronics', 'Food', 'Voucher', 'Others'];
// Fetch Suppliers
$supSql = "SELECT DISTINCT Reward_Supplier FROM reward_item WHERE Reward_Supplier IS NOT NULL AND Reward_Supplier != '' ORDER BY Reward_Supplier ASC";
$supRes = $conn->query($supSql);
$suppliers = [];
while($r = $supRes->fetch_assoc()) $suppliers[] = $r['Reward_Supplier'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reward Items - DonationMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="admin_common.css">
    <style>
        :root { --primary: #F28585; }
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #6c757d; margin-bottom: 5px; text-transform: uppercase; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; color: #343a40; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; gap: 5px; margin: 0; }
        
        .text-success { color: #28a745; } .text-danger { color: #dc3545; } .text-primary { color: #007bff; }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: #F28585; }
        .stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: #28a745; }
        .stat-card:nth-child(3) .stat-icon { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .stat-card:nth-child(4) .stat-icon { background: rgba(23, 162, 184, 0.2); color: #17a2b8; }

        .reward-management { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; color: #343a40; }

        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; font-size: 13px; display: flex; align-items: center; gap: 5px; text-decoration: none; color: white; transition: 0.3s; }
        .btn-primary { background: #F28585; } .btn-primary:hover { background: #d66565; }
        .btn-success { background: #28a745; } .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; } .btn-danger:hover { background: #c82333; }

        /* --- SEARCH & FILTER STYLES (MATCHING STAFF MANAGEMENT) --- */
        .staff-search { margin-bottom: 25px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; background-color: white; color: #555; outline: none; cursor: pointer; font-size: 14px; min-width: 140px; }
        .filter-select:focus { border-color: #F28585; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; font-size: 14px; background: white; }
        .search-input:focus { border-color: #F28585; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 768px) {
            .staff-search { flex-direction: column; align-items: stretch; }
            .filter-select, .search-input { width: 100%; margin-bottom: 5px; }
        }
        /* --- END SEARCH & FILTER STYLES --- */

        .listing-table { width: 100%; border-collapse: collapse; }
        .listing-table th, .listing-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .listing-table th { font-weight: 600; color: #6c757d; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; background-color: #fbfbfb; cursor: pointer; user-select: none; }
        .listing-table th:hover { background-color: #f8f9fa; color: #F28585; }
        
        .item-info { display: flex; align-items: center; }
        .item-thumb { width: 45px; height: 45px; border-radius: 5px; object-fit: cover; margin-right: 15px; border: 1px solid #eee; background: #f9f9f9; display: flex; align-items: center; justify-content: center; color: #ccc; cursor: pointer; }
        .item-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 5px; }
        .item-details h4 { font-size: 14px; margin-bottom: 4px; color: #343a40; font-weight: 600; margin-top: 0; }
        .code-badge { background: #eee; padding: 2px 6px; border-radius: 4px; font-size: 11px; color: #555; font-weight: bold; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-active { background-color: rgba(40, 167, 69, 0.1); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.2); }
        .status-low { background-color: rgba(255, 193, 7, 0.1); color: #856404; border: 1px solid rgba(255, 193, 7, 0.2); }
        .status-inactive { background-color: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3); }

        .action-cell { display: flex; justify-content: center; align-items: center; }
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { background-color: #f8f9fa; border: 1px solid #e9ecef; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; color: #6c757d; display: flex; align-items: center; justify-content: center; transition: all 0.2s; ease; outline: none; }
        .menu-btn:hover { background-color: #e2e6ea; color: #F28585; }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 10px; }
        .dropdown-content a:hover { background-color: #f8f9fa; color: #F28585; }
        .dropdown-divider { height: 1px; background-color: #eee; margin: 0; padding: 0 !important; }
        .text-delete { color: #dc3545 !important; }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #343a40; text-decoration: none; border-radius: 5px; font-size: 14px; transition: 0.2s; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; animation: slideIn 0.3s; background: white; border-left: 4px solid #28a745; color: #28a745; }
        .floating-alert-danger { border-left-color: #dc3545; color: #dc3545; }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Sort Modal Styles */
        .sort-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .sort-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; }
        .sort-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .sort-header h3 { margin: 0; font-size: 16px; color: #333; }
        .sort-close { cursor: pointer; font-size: 20px; color: #999; }
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #555; text-decoration: none; }
        .sort-btn:hover { background: #f8f9fa; border-color: #ddd; color: var(--primary); }
        .sort-btn i { width: 20px; text-align: center; margin-right: 8px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Modal Forms Styles */
        /* Note: Overriding generic .filter-select for modals if needed, but the main one handles it well */
        .range-inputs { display: flex; gap: 10px; margin-bottom: 10px; }
        .range-input { width: 50%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .btn-apply { width: 100%; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; }
        .sort-title { font-size: 12px; font-weight: 600; color: #888; margin-top: 10px; display: block; margin-bottom: 5px; text-transform: uppercase; }

        /* Lightbox Styles */
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 2002; }
        .close-lightbox:hover, .close-lightbox:focus { color: #bbb; text-decoration: none; cursor: pointer; }
    </style>
</head>
<body>
    
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Reward Item Management</h1>
                <p>Manage redemption items, stock levels, and view audit trails.</p>
            </div>

            <div class="floating-alert" id="alertMsg" style="display: <?php echo isset($_GET['success']) ? 'flex' : 'none'; ?>">
                <i class="fas fa-check-circle"></i>
                <div id="alertMsgText"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div>
            </div>

            <div class="floating-alert floating-alert-danger" id="alertError" style="display: <?php echo isset($_GET['error']) ? 'flex' : 'none'; ?>">
                <i class="fas fa-exclamation-circle"></i>
                <div id="alertErrorText"><?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?></div>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Items</h3>
                        <h2><?php echo number_format($totalItems); ?></h2>
                        <p class="text-success"><i class="fas fa-box"></i> Inventory</p> 
                    </div>
                    <div class="stat-icon"><i class="fas fa-cubes"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Active Rewards</h3>
                        <h2><?php echo number_format($activeRewards); ?></h2>
                        <p class="text-success"><i class="fas fa-check"></i> Visible</p> 
                    </div>
                    <div class="stat-icon"><i class="fas fa-eye"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Low Stock</h3>
                        <h2><?php echo number_format($lowStockCount); ?></h2>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Alert</p> 
                    </div>
                    <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Redemptions</h3>
                        <h2><?php echo number_format($totalRedemptions); ?></h2>
                        <p class="text-primary"><i class="fas fa-history"></i> Claims</p> 
                    </div>
                    <div class="stat-icon"><i class="fas fa-gift"></i></div>
                </div>
            </div>

            <div class="reward-management">
                <div class="section-header">
                    <h2>Reward Items List</h2>
                    <div class="action-buttons">
                        <a href="add_reward_item.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Item
                        </a>
                        <a href="reward_item_management.php?action=export_excel" class="btn btn-success" target="_blank">
                            <i class="fas fa-download"></i> Export Inventory
                        </a>
                    </div>
                </div>

                <form class="staff-search" method="GET" action="reward_item_management.php">
                    <?php echo getHiddenInputs(['search', 'filter_type', 'filter_val_status', 'filter_val_points', 'filter_val_stock', 'filter_val_category']); ?>
                    
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="status" <?php echo ($filterType == 'status') ? 'selected' : ''; ?>>Status</option>
                            <option value="points" <?php echo ($filterType == 'points') ? 'selected' : ''; ?>>Points</option>
                            <option value="stock" <?php echo ($filterType == 'stock') ? 'selected' : ''; ?>>Stock Quantity</option>
                            <option value="category" <?php echo ($filterType == 'category') ? 'selected' : ''; ?>>Category</option>
                        </select>
                    </div>

                    <div id="filter_status_container" class="secondary-filter">
                        <select name="filter_val_status" class="filter-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Low Stock">Low Stock</option>
                        </select>
                    </div>

                    <div id="filter_points_container" class="secondary-filter">
                        <select name="filter_val_points" class="filter-select">
                            <option value="low">Low (< 15)</option>
                            <option value="mid">Mid (15-35)</option>
                            <option value="high">High (> 35)</option>
                        </select>
                    </div>
                    
                    <div id="filter_stock_container" class="secondary-filter">
                        <select name="filter_val_stock" class="filter-select">
                            <option value="low">Below 10</option>
                            <option value="mid">10 - 200</option>
                            <option value="high">Above 200</option>
                        </select>
                    </div>
                    
                    <div id="filter_category_container" class="secondary-filter">
                        <select name="filter_val_category" class="filter-select">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search by name, ID code..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if(!empty($searchTerm) || !empty($filterType)): ?>
                        <a href="reward_item_management.php" class="btn btn-danger" title="Clear Filters" style="background:#dc3545; color:white; padding:10px 15px; border-radius:5px;">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>

                <table class="listing-table">
                    <thead>
                        <tr>
                            <th onclick="openModal('nameSortModal')">
                                ITEM NAME / ID
                                <?php 
                                    if($sort=='name' || $sort=='id') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); 
                                    else echo '<i class="fas fa-sort"></i>'; 
                                ?>
                            </th>
                            <th style="text-align: center;" onclick="openModal('categorySortModal')">
                                CATEGORY
                                <?php if($sort=='category') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th style="text-align: center;" onclick="openModal('pointsSortModal')">
                                POINTS REQUIRED
                                <?php if($sort=='points') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th> 
                            <th style="text-align: center;" onclick="openModal('stockSortModal')">
                                STOCK QUANTITY
                                <?php if($sort=='stock') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th style="text-align: center;" onclick="openModal('statusSortModal')">
                                STATUS
                                <?php if($sort=='status') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th style="text-align: center;" onclick="openModal('supplierSortModal')">
                                SUPPLIER
                                <?php if($sort=='supplier') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th style="text-align: center;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultItems->num_rows > 0): ?>
                            <?php while($row = $resultItems->fetch_assoc()): ?>
                                <?php 
                                    $imagePath = 'uploads/rewards/' . $row['Reward_PhotoPath'];
                                    $hasImage = !empty($row['Reward_PhotoPath']) && file_exists($imagePath);
                                    
                                    $statusClass = 'status-inactive';
                                    if($row['Reward_Status'] == 'Active') $statusClass = 'status-active';
                                    if($row['Reward_Status'] == 'Low Stock') $statusClass = 'status-low';
                                ?>
                                <tr>
                                    <td>
                                        <div class="item-info">
                                            <div class="item-thumb" onclick="openLightbox('<?php echo $hasImage ? $imagePath : ''; ?>')">
                                                <?php if($hasImage): ?>
                                                    <img src="<?php echo $imagePath; ?>" alt="Img">
                                                <?php else: ?>
                                                    <i class="fas fa-image"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-details">
                                                <h4><?php echo htmlspecialchars($row['Reward_ItemName']); ?></h4>
                                                <div style="font-size:12px; margin-top:3px; color:#888;">
                                                    ID: <span class="code-badge"><?php echo $row['Reward_Code']; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars($row['Reward_Category']); ?></td>
                                    <td style="text-align: center;">
                                        <div style="font-weight:600; color:#F28585; font-size:15px;"><?php echo $row['Reward_RequiredPoint']; ?> pts</div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="font-weight:700; color:#343a40; font-size:15px;"><?php echo $row['Reward_Stock']; ?></div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo $row['Reward_Status']; ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php echo htmlspecialchars($row['Reward_Supplier']); ?>
                                        <?php if($row['Reward_ExpiryDate']): ?>
                                            <div style="font-size:11px; color:#999;">Exp: <?php echo $row['Reward_ExpiryDate']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-cell">
                                            <div class="action-menu">
                                                <button class="menu-btn" onclick="toggleMenu(event, <?php echo $row['Reward_ID']; ?>)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div id="menu-<?php echo $row['Reward_ID']; ?>" class="dropdown-content">
                                                    <a href="reward_view_edit.php?id=<?php echo $row['Reward_ID']; ?>&mode=view">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
                                                    <a href="reward_view_edit.php?id=<?php echo $row['Reward_ID']; ?>&mode=edit">
                                                        <i class="fas fa-edit"></i> Edit Details
                                                    </a>
                                                    <a href="update_stock.php?id=<?php echo $row['Reward_ID']; ?>">
                                                        <i class="fas fa-boxes"></i> Update Stock
                                                    </a>
                                                    <a href="view_history.php?id=<?php echo $row['Reward_ID']; ?>">
                                                        <i class="fas fa-history"></i> View History
                                                    </a>
                                                    <a href="reward_item_management.php?action=export_item_history&id=<?php echo $row['Reward_ID']; ?>" target="_blank">
                                                        <i class="fas fa-file-export"></i> Export Item Data
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a href="javascript:confirmDelete(<?php echo $row['Reward_ID']; ?>, '<?php echo addslashes($row['Reward_ItemName']); ?>')" class="text-delete">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center; padding: 40px; color: #999;">No items found matching criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                            $q = $_GET; 
                            if ($page > 1) { 
                                $q['page'] = $page - 1; 
                                echo '<a href="?'.http_build_query($q).'" class="pagination-btn">Previous</a>'; 
                            } else { 
                                echo '<span class="pagination-btn disabled">Previous</span>'; 
                            }
                            
                            for($i=1; $i<=$total_pages; $i++) {
                                $q['page'] = $i;
                                $active = ($i==$page) ? 'active' : '';
                                echo '<a href="?'.http_build_query($q).'" class="pagination-btn '.$active.'">'.$i.'</a>';
                            }
                            
                            if ($page < $total_pages) { 
                                $q['page'] = $page + 1; 
                                echo '<a href="?'.http_build_query($q).'" class="pagination-btn">Next</a>'; 
                            } else { 
                                echo '<span class="pagination-btn disabled">Next</span>'; 
                            }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="nameSortModal" class="sort-modal" onclick="closeModal(event, 'nameSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort Item</h3><span class="sort-close" onclick="document.getElementById('nameSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'name', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Name (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'name', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Name (Z - A)</a>
            <hr style="border:0; border-top:1px dashed #eee; margin:10px 0;">
            <a href="<?php echo buildUrl(['sort'=>'id', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> ID (Low - High)</a>
            <a href="<?php echo buildUrl(['sort'=>'id', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> ID (High - Low)</a>
        </div>
    </div>

    <div id="categorySortModal" class="sort-modal" onclick="closeModal(event, 'categorySortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort & Filter Category</h3><span class="sort-close" onclick="document.getElementById('categorySortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'category', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Category (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'category', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Category (Z - A)</a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by Category</span>
            <form method="GET">
                <?php echo getHiddenInputs('f_category'); ?>
                <select name="f_category" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php if($f_category == $cat) echo 'selected'; ?>><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-apply">Apply Filter</button>
            </form>
        </div>
    </div>

    <div id="pointsSortModal" class="sort-modal" onclick="closeModal(event, 'pointsSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort & Filter Points</h3><span class="sort-close" onclick="document.getElementById('pointsSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'points', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Low to High</a>
            <a href="<?php echo buildUrl(['sort'=>'points', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> High to Low</a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Points Range</span>
            <form method="GET">
                <?php echo getHiddenInputs(['min_point', 'max_point']); ?>
                <div class="range-inputs">
                    <input type="number" name="min_point" class="range-input" placeholder="Min" value="<?php echo htmlspecialchars($min_point); ?>">
                    <input type="number" name="max_point" class="range-input" placeholder="Max" value="<?php echo htmlspecialchars($max_point); ?>">
                </div>
                <button type="submit" class="btn-apply">Apply Range</button>
            </form>
        </div>
    </div>

    <div id="stockSortModal" class="sort-modal" onclick="closeModal(event, 'stockSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort & Filter Stock</h3><span class="sort-close" onclick="document.getElementById('stockSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'stock', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Low to High</a>
            <a href="<?php echo buildUrl(['sort'=>'stock', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> High to Low</a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Stock Quantity Range</span>
            <form method="GET">
                <?php echo getHiddenInputs(['min_stock', 'max_stock']); ?>
                <div class="range-inputs">
                    <input type="number" name="min_stock" class="range-input" placeholder="Min" value="<?php echo htmlspecialchars($min_stock); ?>">
                    <input type="number" name="max_stock" class="range-input" placeholder="Max" value="<?php echo htmlspecialchars($max_stock); ?>">
                </div>
                <button type="submit" class="btn-apply">Apply Range</button>
            </form>
        </div>
    </div>

    <div id="statusSortModal" class="sort-modal" onclick="closeModal(event, 'statusSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort & Filter Status</h3><span class="sort-close" onclick="document.getElementById('statusSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'status', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Status (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'status', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Status (Z - A)</a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by Status</span>
            <a href="<?php echo buildUrl(['f_status'=>'Active']); ?>" class="sort-btn" style="<?php echo ($f_status=='Active')?'background:#eee;':''; ?>">Show Active Only</a>
            <a href="<?php echo buildUrl(['f_status'=>'Low Stock']); ?>" class="sort-btn" style="<?php echo ($f_status=='Low Stock')?'background:#eee;':''; ?>">Show Low Stock Only</a>
            <a href="<?php echo buildUrl(['f_status'=>'Inactive']); ?>" class="sort-btn" style="<?php echo ($f_status=='Inactive')?'background:#eee;':''; ?>">Show Inactive Only</a>
            <a href="<?php echo buildUrl(['f_status'=>'']); ?>" class="sort-btn" style="color:var(--primary); font-weight:bold;">Show All</a>
        </div>
    </div>

    <div id="supplierSortModal" class="sort-modal" onclick="closeModal(event, 'supplierSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort & Filter Supplier</h3><span class="sort-close" onclick="document.getElementById('supplierSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'supplier', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Supplier (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'supplier', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Supplier (Z - A)</a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by Supplier</span>
            <form method="GET">
                <?php echo getHiddenInputs('f_supplier'); ?>
                <select name="f_supplier" class="filter-select">
                    <option value="">All Suppliers</option>
                    <?php foreach($suppliers as $sup): ?>
                        <option value="<?php echo $sup; ?>" <?php if($f_supplier == $sup) echo 'selected'; ?>><?php echo $sup; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-apply">Apply Filter</button>
            </form>
        </div>
    </div>

    <div id="imageLightbox" class="lightbox-modal">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <img class="lightbox-content" id="lightboxImage">
    </div>

    <script>
        // --- 1. CLEAN URL PARAMS (Fix Item Not Found issue) ---
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            if (url.searchParams.has('success') || url.searchParams.has('error')) {
                url.searchParams.delete('success');
                url.searchParams.delete('error');
                window.history.replaceState(null, '', url.toString());
            }
        }

        function toggleMenu(e, id) { 
            e.stopPropagation(); 
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); 
            const menu = document.getElementById('menu-' + id); 
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block'; 
        }

        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        
        function closeModal(e, id) { 
            if(typeof e === 'string') {
                document.getElementById(e).style.display = 'none';
            } else {
                // If clicked on backdrop (the modal itself) or the close button
                if(e.target.id === id || e.target.classList.contains('sort-close')) {
                    document.getElementById(id).style.display = 'none';
                }
            }
        }

        // Lightbox functions
        function openLightbox(imageSrc) { 
            if (!imageSrc) return; 
            document.getElementById('lightboxImage').src = imageSrc; 
            document.getElementById('imageLightbox').style.display = "flex"; 
        }
        function closeLightbox() { document.getElementById('imageLightbox').style.display = "none"; }

        window.addEventListener('click', function(e) { 
            if (!e.target.matches('.menu-btn') && !e.target.matches('.menu-btn *')) { document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); } 
            
            // Close lightbox on click outside image
            if (e.target.id == 'imageLightbox') closeLightbox();
        });
        
        // Close lightbox on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape" && document.getElementById('imageLightbox').style.display === "flex") { closeLightbox(); }
        });
        
        function confirmDelete(id, name) { 
            Swal.fire({
                title: 'Delete Item?',
                text: "Are you sure you want to delete '" + name + "'? This action is logged.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `reward_item_management.php?delete_id=${id}&name=${encodeURIComponent(name)}`; 
                }
            });
        }

        function toggleFilterInputs() { 
            const val = document.getElementById('filterType').value; 
            document.querySelectorAll('.secondary-filter').forEach(el => el.classList.remove('active')); 
            if (val === 'status') document.getElementById('filter_status_container').classList.add('active'); 
            if (val === 'points') document.getElementById('filter_points_container').classList.add('active'); 
            if (val === 'stock') document.getElementById('filter_stock_container').classList.add('active');
            if (val === 'category') document.getElementById('filter_category_container').classList.add('active'); 
        }
        document.addEventListener('DOMContentLoaded', toggleFilterInputs);
        const alertBox = document.getElementById('alertMsg'); if (alertBox) setTimeout(() => { alertBox.style.display = 'none'; }, 4000);
        const alertErr = document.getElementById('alertError'); if (alertErr) setTimeout(() => { alertErr.style.display = 'none'; }, 4000);
    </script>
</body>
</html>
<?php $conn->close(); ?>