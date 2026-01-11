<?php
// reward_item_management.php
session_start();

// 1. Check Login (Modified to allow Staff)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- GET ADMIN/STAFF INFO ---
$adminName = "User";
$adminPosition = "Role";
$adminProfilePicture = null;
$currentAdminId = 0;

if (isset($_SESSION['admin_id'])) {
    $currentAdminId = $_SESSION['admin_id'];
    $adminSql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
    $adminResult = $conn->query($adminSql);

    if ($adminResult && $adminResult->num_rows > 0) {
        $adminData = $adminResult->fetch_assoc();
        $adminName = $adminData['Admin_Name'];
        $adminPosition = $adminData['Admin_Role']; 
        $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
    }
} elseif (isset($_SESSION['staff_id'])) {
    $currentAdminId = $_SESSION['staff_id'];
    $staffSql = "SELECT Staff_FullName, Staff_ProfilePicture, Staff_Role FROM staff WHERE Staff_ID = $currentAdminId";
    $staffResult = $conn->query($staffSql);

    if ($staffResult && $staffResult->num_rows > 0) {
        $staffData = $staffResult->fetch_assoc();
        $adminName = $staffData['Staff_FullName'];
        $adminPosition = $staffData['Staff_Role'];
        $adminProfilePicture = $staffData['Staff_ProfilePicture'];
    }
}

// --- CONFIG: CATEGORY ID PREFIXES ---
$categoryPrefixes = [
    'Household' => 'HO',
    'Apparel' => 'AP',
    'Handicraft' => 'HA',
    'Electronics' => 'EL',
    'Food' => 'FD',
    'Voucher' => 'VO',
    'Others' => 'OT'
];

// --- CONSTANT: MAX STOCK ---
define('MAX_STOCK_LIMIT', 500);

// --- HELPER: Generate Unique Reward Code ---
function generateRewardCode($conn, $category, $prefixes) {
    $prefix = isset($prefixes[$category]) ? $prefixes[$category] : 'OT';
    $sql = "SELECT Reward_Code FROM reward_item WHERE Reward_Code LIKE '$prefix-%' ORDER BY LENGTH(Reward_Code) DESC, Reward_Code DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $parts = explode('-', $row['Reward_Code']);
        $newNumber = intval($parts[1]) + 1;
    } else {
        $newNumber = 1;
    }
    return $prefix . '-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT); 
}

// --- HELPER: Log Admin Action ---
function logRewardAction($conn, $rewardId, $adminId, $type, $details) {
    $details = $conn->real_escape_string($details);
    $sql = "INSERT INTO reward_logs (Reward_ID, Admin_ID, Action_Type, Action_Details) 
            VALUES ('$rewardId', '$adminId', '$type', '$details')";
    $conn->query($sql);
}

// --- AUTO-FIX: Generate IDs for Old Data ---
$checkNullSql = "SELECT Reward_ID, Reward_Category FROM reward_item WHERE Reward_Code IS NULL OR Reward_Code = ''";
$nullResult = $conn->query($checkNullSql);
if ($nullResult && $nullResult->num_rows > 0) {
    while($row = $nullResult->fetch_assoc()) {
        $newCode = generateRewardCode($conn, $row['Reward_Category'], $categoryPrefixes);
        $rid = $row['Reward_ID'];
        $conn->query("UPDATE reward_item SET Reward_Code = '$newCode' WHERE Reward_ID = $rid");
    }
}

// --- HELPER: Handle Image Upload ---
function handleImageUpload($file) {
    if (isset($file) && $file['error'] == 0) {
        $targetDir = "uploads/rewards/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = "reward_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($file['tmp_name'], $targetDir . $fileName)) return $fileName;
    }
    return null;
}

// --- HANDLE EXPORT: SINGLE ITEM HISTORY (DETAILED) ---
if (isset($_GET['action']) && $_GET['action'] == 'export_item_history' && isset($_GET['id'])) {
    $itemId = intval($_GET['id']);
    
    // Get Item Details
    $itemQ = $conn->query("SELECT Reward_ItemName, Reward_Code, Reward_Stock FROM reward_item WHERE Reward_ID = $itemId");
    $itemInfo = $itemQ->fetch_assoc();
    $itemNameClean = preg_replace('/[^A-Za-z0-9\-]/', '_', $itemInfo['Reward_ItemName']);
    
    $filename = "History_" . $itemInfo['Reward_Code'] . "_" . $itemNameClean . "_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['REPORT FOR:', $itemInfo['Reward_ItemName'] . ' (' . $itemInfo['Reward_Code'] . ')']);
    fputcsv($output, ['Current Stock:', $itemInfo['Reward_Stock']]);
    fputcsv($output, []);

    // 1. Redemption History (Detailed)
    fputcsv($output, ['--- REDEMPTION HISTORY (DONORS) ---']);
    fputcsv($output, ['Redemption ID', 'Date', 'Donor Name', 'Email', 'Contact', 'IC Number', 'Points Spent', 'Status', 'Tracking No']);
    
    $sqlRedeem = "SELECT r.Redemption_ID, r.Redemption_Updated_At, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_ICNumber,
                  r.Redemption_PointsSpent, r.Redemption_Status, r.Redemption_TrackingNumber
                  FROM redemption_order r 
                  JOIN donor d ON r.Donor_ID = d.Donor_ID 
                  WHERE r.Reward_ID = $itemId ORDER BY r.Redemption_Updated_At DESC";
    $resRedeem = $conn->query($sqlRedeem);
    while($row = $resRedeem->fetch_assoc()) {
        fputcsv($output, [
            $row['Redemption_ID'], 
            $row['Redemption_Updated_At'], 
            $row['Donor_Name'], 
            $row['Donor_Email'], 
            $row['Donor_ContactNumber'], 
            $row['Donor_ICNumber'],
            $row['Redemption_PointsSpent'], 
            $row['Redemption_Status'],
            $row['Redemption_TrackingNumber']
        ]);
    }
    
    fputcsv($output, []);
    
    // 2. Admin Audit Log
    fputcsv($output, ['--- ADMIN AUDIT LOG (STOCK & UPDATES) ---']);
    fputcsv($output, ['Date', 'Admin Name', 'Admin Email', 'Action Type', 'Detailed Description']);
    
    $sqlLog = "SELECT l.Log_Created_At, a.Admin_Name, a.Admin_Email, l.Action_Type, l.Action_Details 
               FROM reward_logs l 
               JOIN admin a ON l.Admin_ID = a.Admin_ID 
               WHERE l.Reward_ID = $itemId ORDER BY l.Log_Created_At DESC";
    $resLog = $conn->query($sqlLog);
    while($row = $resLog->fetch_assoc()) {
        fputcsv($output, [
            $row['Log_Created_At'], 
            $row['Admin_Name'], 
            $row['Admin_Email'], 
            $row['Action_Type'], 
            $row['Action_Details']
        ]);
    }
    
    fclose($output);
    exit();
}

// --- HANDLE EXPORT: ALL DATA ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "All_Rewards_Report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['ID Code', 'Item Name', 'Category', 'Stock Qty', 'Points Required', 'Status', 'Supplier']);
    $sqlInv = "SELECT Reward_Code, Reward_ItemName, Reward_Category, Reward_Stock, Reward_RequiredPoint, Reward_Status, Reward_Supplier 
               FROM reward_item ORDER BY Reward_Category ASC";
    $resInv = $conn->query($sqlInv);
    while($row = $resInv->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// --- AJAX: Get History (Updated to include ID for linking) ---
if (isset($_GET['action']) && $_GET['action'] == 'get_full_history' && isset($_GET['item_id'])) {
    $itemId = intval($_GET['item_id']);
    
    $redemptions = [];
    $sqlR = "SELECT r.Redemption_ID, r.Redemption_Updated_At as date, d.Donor_Name as user, r.Redemption_PointsSpent as info, r.Redemption_Status as status 
             FROM redemption_order r JOIN donor d ON r.Donor_ID = d.Donor_ID WHERE r.Reward_ID = $itemId ORDER BY r.Redemption_Updated_At DESC";
    $resR = $conn->query($sqlR);
    if($resR) while($row = $resR->fetch_assoc()) $redemptions[] = $row;

    $logs = [];
    $sqlL = "SELECT l.Log_Created_At as date, a.Admin_Name as user, l.Action_Details as info, l.Action_Type as status 
             FROM reward_logs l JOIN admin a ON l.Admin_ID = a.Admin_ID WHERE l.Reward_ID = $itemId ORDER BY l.Log_Created_At DESC";
    $resL = $conn->query($sqlL);
    if($resL) while($row = $resL->fetch_assoc()) $logs[] = $row;

    echo json_encode(['redemptions' => $redemptions, 'logs' => $logs]);
    exit();
}

// --- HANDLE QUICK STOCK UPDATE (WITH LIMIT) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quick_stock_update'])) {
    $id = intval($_POST['stock_reward_id']);
    $addQty = intval($_POST['add_stock_qty']); 
    
    $checkResult = $conn->query("SELECT Reward_Stock, Reward_ItemName FROM reward_item WHERE Reward_ID = $id");
    if ($checkResult && $checkResult->num_rows > 0) {
        $row = $checkResult->fetch_assoc();
        $currentStock = intval($row['Reward_Stock']);
        
        // 1. Calculate Limit
        $finalStock = $currentStock + $addQty;
        
        if ($finalStock > MAX_STOCK_LIMIT) {
            $allowedToAdd = MAX_STOCK_LIMIT - $currentStock;
            $exceededBy = $finalStock - MAX_STOCK_LIMIT;
            $errorMsg = "Stock Limit Exceeded (Max 500). Current: $currentStock. You can only add $allowedToAdd more.";
            header("Location: reward_item_management.php?error=" . urlencode($errorMsg));
            exit();
        }

        if ($finalStock < 0) {
             header("Location: reward_item_management.php?error=Stock cannot be negative.");
             exit();
        }

        $statusUpdate = ($finalStock == 0) ? "Inactive" : (($finalStock < 10) ? "Low Stock" : "Active");
        
        $sql = "UPDATE reward_item SET Reward_Stock = $finalStock, Reward_Status = '$statusUpdate' WHERE Reward_ID = $id";
        
        if ($conn->query($sql)) {
            logRewardAction($conn, $id, $currentAdminId, 'Stock Update', "Added $addQty qty. New Total: $finalStock");
            header("Location: reward_item_management.php?success=Stock Updated.");
        } else {
            header("Location: reward_item_management.php?error=Database Error");
        }
    }
    exit();
}

// --- HANDLE ADD REWARD (WITH LIMIT) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_reward'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $category = $conn->real_escape_string($_POST['category']); 
    $supplier = $conn->real_escape_string($_POST['supplier']); 
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL; 
    $desc = $conn->real_escape_string($_POST['description']);
    $points = (int)$_POST['points'];
    $stock = (int)$_POST['stock'];
    
    // 1. Validate Stock Limit
    if ($stock > MAX_STOCK_LIMIT) {
        $errorMsg = "Cannot create item. Initial stock ($stock) exceeds maximum limit of " . MAX_STOCK_LIMIT . ".";
        header("Location: reward_item_management.php?error=" . urlencode($errorMsg));
        exit();
    }
    
    if (empty($_FILES['photo']['name'])) {
        header("Location: reward_item_management.php?error=Image is required.");
        exit();
    }

    $status = ($stock == 0) ? 'Inactive' : (($stock < 10) ? 'Low Stock' : 'Active');
    $imageName = handleImageUpload($_FILES['photo']);
    $expiryVal = $expiryDate ? "'$expiryDate'" : "NULL";

    $newCode = generateRewardCode($conn, $category, $categoryPrefixes);

    $sql = "INSERT INTO reward_item (Reward_Code, Reward_ItemName, Reward_Category, Reward_Description, Reward_RequiredPoint, Reward_Supplier, Reward_Stock, Reward_ExpiryDate, Reward_Status, Reward_PhotoPath) 
            VALUES ('$newCode', '$name', '$category', '$desc', '$points', '$supplier', '$stock', $expiryVal, '$status', '$imageName')";
    
    if ($conn->query($sql)) {
        $newId = $conn->insert_id;
        logRewardAction($conn, $newId, $currentAdminId, 'Create', "Created item $newCode ($name). Initial Stock: $stock");
        header("Location: reward_item_management.php?success=Item Added Successfully");
    } else {
        header("Location: reward_item_management.php?error=" . urlencode($conn->error));
    }
    exit();
}

// --- HANDLE EDIT REWARD (WITH LIMIT) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_reward'])) {
    $id = (int)$_POST['reward_id'];
    $name = $conn->real_escape_string($_POST['name']);
    $category = $conn->real_escape_string($_POST['category']); 
    $supplier = $conn->real_escape_string($_POST['supplier']); 
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL; 
    $desc = $conn->real_escape_string($_POST['description']);
    $points = (int)$_POST['points'];
    $stock = (int)$_POST['stock'];
    $status = $_POST['status']; 

    // 1. Validate Stock Limit (Replaced alert with header redirect)
    if ($stock > MAX_STOCK_LIMIT) {
        $errorMsg = "Cannot update item. Stock ($stock) exceeds maximum limit of " . MAX_STOCK_LIMIT . ".";
        header("Location: reward_item_management.php?error=" . urlencode($errorMsg));
        exit();
    }

    if ($status != 'Inactive') {
        $status = ($stock < 10) ? 'Low Stock' : 'Active';
    }

    $imageSql = "";
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $uploaded = handleImageUpload($_FILES['photo']);
        if ($uploaded) {
            $imageSql = ", Reward_PhotoPath = '$uploaded'";
        }
    }

    $expiryVal = $expiryDate ? "'$expiryDate'" : "NULL";

    $sql = "UPDATE reward_item SET 
            Reward_ItemName='$name', 
            Reward_Category='$category',
            Reward_Description='$desc', 
            Reward_RequiredPoint='$points', 
            Reward_Supplier='$supplier',
            Reward_Stock='$stock', 
            Reward_ExpiryDate=$expiryVal,
            Reward_Status='$status' 
            $imageSql 
            WHERE Reward_ID=$id";
    
    if ($conn->query($sql)) {
        logRewardAction($conn, $id, $currentAdminId, 'Update', "Updated details for $name. Stock set to $stock");
        header("Location: reward_item_management.php?success=Item Updated Successfully");
    } else {
        header("Location: reward_item_management.php?error=" . urlencode($conn->error));
    }
    exit();
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

// --- SEARCH & FILTER ---
$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = ["1=1"];

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
        if ($filterValue == 'low') $whereConditions[] = "Reward_RequiredPoint < 150";
        elseif ($filterValue == 'mid') $whereConditions[] = "Reward_RequiredPoint BETWEEN 150 AND 300";
        elseif ($filterValue == 'high') $whereConditions[] = "Reward_RequiredPoint > 300";
    } elseif ($filterType == 'category' && !empty($_GET['filter_val_category'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_category']);
        $whereConditions[] = "Reward_Category = '$filterValue'";
    }
}
$whereClause = implode(" AND ", $whereConditions);

// --- PAGINATION ---
$results_per_page = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$count_sql = "SELECT COUNT(*) as total FROM reward_item WHERE $whereClause";
$total_records = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_records / $results_per_page);

$sqlItems = "SELECT * FROM reward_item WHERE $whereClause ORDER BY Reward_ID DESC LIMIT $start_from, $results_per_page";
$resultItems = $conn->query($sqlItems);

// --- STATS ---
$totalItems = $conn->query("SELECT COUNT(*) as total FROM reward_item")->fetch_assoc()['total'];
$activeRewards = $conn->query("SELECT COUNT(*) as active FROM reward_item WHERE Reward_Status = 'Active'")->fetch_assoc()['active'];
$lowStockCount = $conn->query("SELECT COUNT(*) as low FROM reward_item WHERE Reward_Stock < 10")->fetch_assoc()['low'];
$totalRedemptions = $conn->query("SELECT COUNT(*) as total FROM redemption_order")->fetch_assoc()['total'];

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

$categories = array_keys($categoryPrefixes); 
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
        /* Shared Styles */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #6c757d; margin-bottom: 5px; text-transform: uppercase; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; color: #343a40; }
        .stat-info p { font-size: 12px; font-weight: 500; margin: 0; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-warning { color: #ffc107; }
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
        .btn-warning { background: #ffc107; color: #333;} .btn-warning:hover { background: #e0a800; }

        /* Search Filter */
        .search-filter-bar { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; background: #f8f9fa; padding: 10px; border-radius: 8px; align-items: center; }
        .filter-select, .search-input { padding: 10px; border: 1px solid #ced4da; border-radius: 5px; outline: none; background: white; font-size: 14px; }
        .filter-select { min-width: 140px; cursor: pointer; }
        .search-input { flex: 1; min-width: 200px; }
        .filter-select:focus, .search-input:focus { border-color: #F28585; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* Table */
        .listing-table { width: 100%; border-collapse: collapse; }
        .listing-table th, .listing-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .listing-table th { font-weight: 600; color: #6c757d; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; background-color: #fbfbfb; }
        
        .item-info { display: flex; align-items: center; }
        .item-thumb { width: 45px; height: 45px; border-radius: 5px; object-fit: cover; margin-right: 15px; border: 1px solid #eee; background: #f9f9f9; display: flex; align-items: center; justify-content: center; color: #ccc; cursor: pointer; transition: transform 0.2s; }
        .item-thumb:hover { transform: scale(1.05); border-color: #F28585; }
        .item-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 5px; }
        
        .item-details h4 { font-size: 14px; margin-bottom: 4px; color: #343a40; font-weight: 600; margin-top: 0; }
        .item-details p { font-size: 12px; color: #888; margin: 0; }
        .code-badge { background: #eee; padding: 2px 6px; border-radius: 4px; font-size: 11px; color: #555; font-weight: bold; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-active { background-color: rgba(40, 167, 69, 0.1); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.2); }
        .status-low { background-color: rgba(255, 193, 7, 0.1); color: #856404; border: 1px solid rgba(255, 193, 7, 0.2); }
        
        /* [MODIFIED] Inactive color changed to Red */
        .status-inactive { background-color: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3); }

        .action-cell { display: flex; justify-content: center; align-items: center; }
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { background-color: #f8f9fa; border: 1px solid #e9ecef; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; color: #6c757d; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; outline: none; }
        .menu-btn:hover { background-color: #e2e6ea; color: #F28585; }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 10px; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: #F28585; }
        .dropdown-divider { height: 1px; background-color: #eee; margin: 0; padding: 0 !important; }
        .text-delete { color: #dc3545 !important; }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #343a40; text-decoration: none; border-radius: 5px; font-size: 14px; transition: 0.2s; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 600px; padding: 0; box-shadow: 0 4px 20px rgba(0,0,0,0.15); animation: slideIn 0.3s; position: relative; }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 18px; margin: 0; font-weight: 600; color: #343a40; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #6c757d; }
        .modal-body { padding: 20px; max-height: 80vh; overflow-y: auto; }
        
        /* --- LIGHTBOX (IMAGE VIEWER) CSS [FROM DONOR PAGE] --- */
        .lightbox-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            padding-top: 50px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: rgba(0, 0, 0, 0.9);
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .lightbox-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 80vh;
            border-radius: 5px;
            box-shadow: 0 0 20px rgba(255,255,255,0.1);
            object-fit: contain;
            animation: zoomIn 0.3s;
        }
        @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        
        .close-lightbox {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 2002;
        }
        .close-lightbox:hover, .close-lightbox:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }

        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; color: #343a40; }
        .form-control, .form-select { width: 100%; padding: 10px 15px; border: 1px solid #ced4da; border-radius: 5px; outline: none; font-size: 14px; }
        .form-control:focus, .form-select:focus { border-color: #F28585; }
        
        /* Guide & Error Styles [Added per request] */
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        .error-message { color: #dc3545; font-size: 12px; margin-top: 5px; display: none; }

        .file-upload { text-align: center; margin-bottom: 10px; margin-top: 10px; }
        .reward-img-preview { width: 150px; height: 150px; border-radius: 8px; border: 2px dashed #ddd; margin: 0 auto 15px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .reward-img-preview img { width: 100%; height: 100%; object-fit: cover; }
        .file-upload-label { display: inline-block; padding: 8px 15px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 5px; cursor: pointer; font-size: 13px; transition: 0.2s; }
        .file-upload-label:hover { background: #eee; border-color: #bbb; }
        .file-upload input[type="file"] { display: none; }
        .file-info { display: none; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; background: #f1f1f1; padding: 5px 10px; border-radius: 5px; font-size: 12px; }
        .file-remove { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 14px; }

        .btn-submit { width: 100%; background: #F28585; color: white; padding: 12px; border: none; border-radius: 5px; font-weight: 600; cursor: pointer; margin-top: 10px; font-size: 14px; transition: 0.2s;}
        .btn-submit:hover { background: #d66565; }
        
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s; background: white; border-left: 4px solid #28a745; color: #28a745; }
        .floating-alert-danger { border-left-color: #dc3545; color: #dc3545; }
        
        .history-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .history-table th, .history-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 13px; }
        .history-table th { background: #f8f9fa; font-weight: 600; color: #6c757d; }
        .section-title { font-size: 14px; font-weight: 700; color: #343a40; margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }

        .required-star { color: red; margin-left: 3px; font-weight: bold; }
        .small-modal-content { max-width: 400px; }

        /* History Tabs */
        .history-tabs { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 15px; }
        .tab-btn { padding: 10px 20px; border: none; background: none; cursor: pointer; font-weight: 600; color: #6c757d; border-bottom: 2px solid transparent; transition: all 0.2s; }
        .tab-btn.active { color: #F28585; border-bottom-color: #F28585; }
        .tab-btn:hover { color: #343a40; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .view-btn { padding: 4px 8px; font-size: 11px; background: #17a2b8; color: white; border-radius: 4px; text-decoration: none; }
        .view-btn:hover { background: #138496; }

        @media (max-width: 768px) {
            .search-filter-bar { flex-direction: column; align-items: stretch; }
            .filter-group { flex-wrap: wrap; }
            .stats-cards { grid-template-columns: repeat(2, 1fr); }
            .form-row { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>
    
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Reward Inventory</h1>
                <p>Manage redemption items, stock levels, and view audit trails.</p>
            </div>

            <div class="floating-alert" id="alertMsg" style="display: <?php echo isset($_GET['success']) ? 'flex' : 'none'; ?>">
                <i class="fas fa-check-circle"></i>
                <span id="alertMsgText"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></span>
            </div>

            <div class="floating-alert floating-alert-danger" id="alertError" style="display: <?php echo isset($_GET['error']) ? 'flex' : 'none'; ?>">
                <i class="fas fa-exclamation-circle"></i>
                <span id="alertErrorText"><?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?></span>
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
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add New Item
                        </button>
                        <a href="reward_item_management.php?action=export_excel" class="btn btn-success" target="_blank">
                            <i class="fas fa-download"></i> Export Inventory
                        </a>
                    </div>
                </div>

                <form method="GET" style="display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; background: #f8f9fa; padding: 10px; border-radius: 8px;">
                    <div class="filter-group">
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="status" <?php echo ($filterType == 'status') ? 'selected' : ''; ?>>Status</option>
                            <option value="points" <?php echo ($filterType == 'points') ? 'selected' : ''; ?>>Points</option>
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
                            <option value="low">Low (< 150)</option>
                            <option value="mid">Mid (150-300)</option>
                            <option value="high">High (> 300)</option>
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
                            <th>ITEM NAME / ID</th>
                            <th style="text-align: center;">CATEGORY</th>
                            <th style="text-align: center;">POINTS REQUIRED</th> 
                            <th style="text-align: center;">STOCK QTY</th>
                            <th style="text-align: center;">STATUS</th>
                            <th style="text-align: center;">SUPPLIER</th>
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
                                    
                                    $rowData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
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
                                                    <div onclick='openEditModal(<?php echo $rowData; ?>)'>
                                                        <i class="fas fa-edit"></i> Edit Details
                                                    </div>
                                                    <div onclick="openStockModal(<?php echo $row['Reward_ID']; ?>, <?php echo $row['Reward_Stock']; ?>, '<?php echo addslashes($row['Reward_ItemName']); ?>')">
                                                        <i class="fas fa-boxes"></i> Update Stock
                                                    </div>
                                                    <div onclick="openHistoryModal(<?php echo $row['Reward_ID']; ?>, '<?php echo addslashes($row['Reward_ItemName']); ?>', '<?php echo $row['Reward_Code']; ?>')">
                                                        <i class="fas fa-history"></i> View History
                                                    </div>
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
                            <tr><td colspan="7" style="text-align: center; padding: 40px; color: #999;">No items found.</td></tr>
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

    <div id="imageLightbox" class="lightbox-modal">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <img class="lightbox-content" id="lightboxImage">
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Reward</h2><button class="close-btn" onclick="closeModal('addModal')">&times;</button></div>
            <div class="modal-body">
                <form id="addForm" method="POST" enctype="multipart/form-data" onsubmit="return validateRewardForm('add')" novalidate>
                    <input type="hidden" name="add_reward" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Reward Image <span class="required-star">*</span></label>
                        <div class="reward-img-preview" id="add-preview-container">
                            <span><i class="fas fa-image" style="font-size:24px; color:#ccc;"></i></span>
                        </div>
                        <div class="file-upload">
                            <label for="add_photo" class="file-upload-label">
                                <i class="fas fa-upload"></i> Choose Image
                            </label>
                            <input type="file" id="add_photo" name="photo" accept="image/*" required onchange="previewImage(this, 'add-preview-container', 'add-file-info', 'add-file-name')">
                            <div id="add-file-info" class="file-info">
                                <span id="add-file-name"></span>
                                <button type="button" class="file-remove" onclick="removeImage('add_photo', 'add-preview-container', 'add-file-info')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <span class="form-guide">Format: JPG, PNG. Max size 2MB. Clear high-quality images preferred.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Item Name <span class="required-star">*</span></label>
                        <input type="text" name="name" id="add_name" class="form-control" required placeholder="e.g. Handmade Soap">
                        <span class="form-guide">Unique name for the reward. Avoid duplicate names.</span>
                        <div id="add_name_error" class="error-message">Name cannot contain special symbols.</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Category (Will generate ID) <span class="required-star">*</span></label>
                        <select name="category" id="add_category" class="form-select" required onchange="toggleExpiryField('add_category', 'add_expiry_container')">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-guide">Select the most relevant category to auto-generate the Item Code.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Points Required <span class="required-star">*</span></label>
                            <input type="number" name="points" id="add_points" class="form-control" required placeholder="0" min="0" oninput="validity.valid||(value='');">
                            <span class="form-guide">Points needed to redeem one unit.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Quantity (Max 500) <span class="required-star">*</span></label>
                            <input type="number" name="stock" id="add_stock" class="form-control" required placeholder="0" min="0" max="500" oninput="validity.valid||(value='');">
                            <span class="form-guide">Initial inventory count. Cannot exceed 500.</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Supplier / Source</label>
                            <input type="text" name="supplier" class="form-control" placeholder="e.g. Public Donation">
                            <span class="form-guide">Where this item originated from (Donor, Vendor, etc.).</span>
                        </div>
                        <div class="form-group" id="add_expiry_container" style="display:none;">
                            <label class="form-label">Expiry Date <span class="required-star">*</span></label>
                            <input type="date" name="expiry_date" id="add_expiry" class="form-control">
                            <span class="form-guide">Required for Food or Voucher categories.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description <span class="required-star">*</span></label>
                        <textarea name="description" id="add_desc" class="form-control" rows="4" style="resize: vertical;" required placeholder="Enter full details about this item..."></textarea>
                        <span class="form-guide">Include dimensions, material, weight, usage instructions, and any important terms. Be as detailed as possible.</span>
                    </div>

                    <button type="submit" class="btn-submit"><i class="fas fa-check"></i> Add Item</button>
                </form>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Reward</h2><button class="close-btn" onclick="closeModal('editModal')">&times;</button></div>
            <div class="modal-body">
                <form id="editForm" method="POST" enctype="multipart/form-data" onsubmit="return validateRewardForm('edit')" novalidate>
                    <input type="hidden" name="update_reward" value="1">
                    <input type="hidden" name="reward_id" id="edit_id">
                    
                    <div class="form-group">
                        <label class="form-label">Reward Image</label>
                        <div class="reward-img-preview" id="edit-preview-container">
                            <span>No Image</span>
                        </div>
                        <div class="file-upload">
                            <label for="edit_photo" class="file-upload-label">
                                <i class="fas fa-upload"></i> Change Image
                            </label>
                            <input type="file" id="edit_photo" name="photo" accept="image/*" onchange="previewImage(this, 'edit-preview-container', 'edit-file-info', 'edit-file-name')">
                            <div id="edit-file-info" class="file-info">
                                <span id="edit-file-name"></span>
                                <button type="button" class="file-remove" id="edit-file-remove-btn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <span class="form-guide">Leave empty to keep current image.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Item Name <span class="required-star">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                        <span class="form-guide">Update item name if necessary.</span>
                        <div id="edit_name_error" class="error-message">Name cannot contain special symbols.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category <span class="required-star">*</span></label>
                        <select name="category" id="edit_category" class="form-select" required onchange="toggleExpiryField('edit_category', 'edit_expiry_container')">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-guide">Changing category will NOT change the existing Item Code.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Points Required <span class="required-star">*</span></label>
                            <input type="number" name="points" id="edit_points" class="form-control" required min="0" oninput="validity.valid||(value='');">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Quantity (Max 500) <span class="required-star">*</span></label>
                            <input type="number" name="stock" id="edit_stock" class="form-control" required min="0" max="500" oninput="validity.valid||(value='');">
                            <span class="form-guide">Update physical inventory count.</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Supplier / Source</label>
                            <input type="text" name="supplier" id="edit_supplier" class="form-control">
                        </div>
                        <div class="form-group" id="edit_expiry_container" style="display:none;">
                            <label class="form-label">Expiry Date <span class="required-star">*</span></label>
                            <input type="date" name="expiry_date" id="edit_expiry" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Low Stock">Low Stock</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                        <span class="form-guide">Manually override status if needed.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description <span class="required-star">*</span></label>
                        <textarea name="description" id="edit_desc" class="form-control" rows="4" style="resize: vertical;" required></textarea>
                        <span class="form-guide">Ensure description is accurate and up-to-date.</span>
                    </div>

                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Changes</button>
                </form>
            </div>
        </div>
    </div>

    <div id="stockModal" class="modal">
        <div class="modal-content small-modal-content">
            <div class="modal-header">
                <h2>Add Stock</h2>
                <button class="close-btn" onclick="closeModal('stockModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" onsubmit="return validateStockAdd()" novalidate>
                    <input type="hidden" name="quick_stock_update" value="1">
                    <input type="hidden" name="stock_reward_id" id="stock_reward_id">
                    
                    <div style="background:#f8f9fa; padding:15px; border-radius:5px; margin-bottom:15px; border:1px solid #eee;">
                        <h3 id="stock_item_name" style="margin-top:0; font-size:16px; color:#343a40;">Item Name</h3>
                        <div style="display:flex; justify-content:space-between; margin-top:5px; font-size:14px;">
                            <span style="color:#6c757d;">Current Stock:</span>
                            <strong style="color:#F28585;" id="display_current_stock">0</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-top:5px; font-size:14px;">
                            <span style="color:#6c757d;">Max Allowed:</span>
                            <strong style="color:#28a745;" id="display_max_stock">500</strong>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Quantity to Add <span class="required-star">*</span></label>
                        <input type="number" name="add_stock_qty" id="add_stock_qty" class="form-control" required min="1" placeholder="e.g. 10">
                        <span class="form-guide">Enter the number of new units arriving.</span>
                    </div>
                    
                    <button type="submit" class="btn-submit"><i class="fas fa-plus-circle"></i> Add to Inventory</button>
                </form>
            </div>
        </div>
    </div>

    <div id="historyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 id="historyTitle">Item History</h2>
                    <span id="historySubtitle" style="font-size:12px; color:#888;"></span>
                </div>
                <button class="close-btn" onclick="closeModal('historyModal')">&times;</button>
            </div>
            <div class="modal-body">
                
                <div class="history-tabs">
                    <button class="tab-btn active" onclick="switchHistoryTab('redeem')">Redemptions</button>
                    <button class="tab-btn" onclick="switchHistoryTab('audit')">Audit Log</button>
                </div>

                <div id="tab-redeem" class="tab-content active">
                    <table class="history-table">
                        <thead><tr><th>Date</th><th>Donor</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody id="historyBodyRedeem"></tbody>
                    </table>
                </div>

                <div id="tab-audit" class="tab-content">
                    <table class="history-table">
                        <thead><tr><th>Date</th><th>Admin</th><th>Action</th><th>Details</th></tr></thead>
                        <tbody id="historyBodyLog"></tbody>
                    </table>
                </div>
                
            </div>
        </div>
    </div>

    <script>
        // --- ALERT SYSTEM ---
        function showSystemError(msg) {
            const errBox = document.getElementById('alertError');
            const errText = document.getElementById('alertErrorText');
            if(errBox && errText) {
                errText.innerText = msg;
                errBox.style.display = 'flex';
                setTimeout(() => { errBox.style.display = 'none'; }, 5000);
            } else {
                console.error("Error Box not found in DOM");
            }
        }

        function openAddModal() { 
            document.getElementById('addModal').style.display = 'flex'; 
            document.getElementById('addForm').reset();
            document.getElementById('add-preview-container').innerHTML = '<span><i class="fas fa-image" style="font-size:24px; color:#ccc;"></i></span>';
            document.getElementById('add-file-info').style.display = 'none';
            document.getElementById('add_name_error').style.display = 'none';
            toggleExpiryField('add_category', 'add_expiry_container');
        }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        // --- NEW LIGHTBOX FUNCTIONS ---
        function openLightbox(src) {
            if(!src) return;
            document.getElementById('lightboxImage').src = src;
            document.getElementById('imageLightbox').style.display = 'flex';
        }

        function closeLightbox() {
            document.getElementById('imageLightbox').style.display = 'none';
        }

        function switchHistoryTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tabName).style.display = 'block';
            const btns = document.querySelectorAll('.tab-btn');
            if(tabName === 'redeem') btns[0].classList.add('active');
            else btns[1].classList.add('active');
        }

        function openStockModal(id, currentStock, itemName) {
            document.getElementById('stock_reward_id').value = id;
            document.getElementById('stock_item_name').innerText = itemName;
            document.getElementById('display_current_stock').innerText = currentStock;
            document.getElementById('add_stock_qty').value = ''; 
            document.getElementById('stockModal').style.display = 'flex';
        }

        // [MODIFIED] Replaced alert() with showSystemError()
        function validateStockAdd() {
            const current = parseInt(document.getElementById('display_current_stock').innerText);
            const qtyInput = document.getElementById('add_stock_qty');
            
            if (!qtyInput.value) {
                showSystemError("Please enter a quantity.");
                return false;
            }

            const added = parseInt(qtyInput.value);
            const max = 500;
            const total = current + added;

            if (total > max) {
                const allowed = max - current;
                showSystemError(`Stock Limit Exceeded! Max: ${max}, Current: ${current}. You tried adding ${added}. Only ${allowed} allowed.`);
                return false;
            }
            return true;
        }

        // [NEW] Validate Form (Similar to Donor Page)
        function validateRewardForm(type) {
            let errors = [];
            const prefix = (type === 'add') ? 'add' : 'edit';
            
            // Get Elements
            const elName = document.getElementById(prefix + '_name');
            const elPoints = document.getElementById(prefix + '_points');
            const elStock = document.getElementById(prefix + '_stock');
            const elDesc = document.getElementById(prefix + '_desc');
            const elCat = document.getElementById(prefix + '_category');
            
            // Check Required
            if (!elName.value.trim()) errors.push("Item Name is required.");
            if (!elCat.value) errors.push("Category is required.");
            if (!elPoints.value) errors.push("Points Required is required.");
            if (!elStock.value) errors.push("Stock Quantity is required.");
            if (!elDesc.value.trim()) errors.push("Description is required.");

            // Image Check (Add only)
            if (type === 'add') {
                const elPhoto = document.getElementById('add_photo');
                if (!elPhoto.files || elPhoto.files.length === 0) {
                    errors.push("Reward Image is required.");
                }
            }

            // Expiry Check (If visible/required based on category)
            const catVal = elCat.value;
            if (['Food', 'Voucher'].includes(catVal)) {
                const elExpiry = document.getElementById(prefix + '_expiry');
                if (!elExpiry.value) errors.push("Expiry Date is required for this category.");
            }

            // Regex Check for Name
            const nameRegex = /^[a-zA-Z0-9\s\-]+$/;
            const errorDiv = document.getElementById(prefix + '_name_error');
            
            if (elName.value.trim() && !nameRegex.test(elName.value.trim())) {
                errors.push("Name can only contain letters, numbers, spaces and hyphens.");
                if(errorDiv) errorDiv.style.display = 'block';
            } else {
                if(errorDiv) errorDiv.style.display = 'none';
            }

            if (errors.length > 0) {
                showSystemError("Validation Error: " + errors.join(" "));
                return false;
            }

            return true;
        }

        function previewImage(input, containerId, infoId, nameId) {
            const container = document.getElementById(containerId);
            const info = document.getElementById(infoId);
            const nameSpan = document.getElementById(nameId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { 
                    container.innerHTML = `<img src="${e.target.result}" alt="Preview">`; 
                    if(info) { info.style.display = 'inline-flex'; if(nameSpan) nameSpan.textContent = input.files[0].name; }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage(inputId, containerId, infoId, originalSrc = null) {
            document.getElementById(inputId).value = ''; 
            if(infoId) document.getElementById(infoId).style.display = 'none';
            const container = document.getElementById(containerId);
            if (originalSrc) { container.innerHTML = `<img src="${originalSrc}" alt="Preview">`; } 
            else { container.innerHTML = '<span><i class="fas fa-image" style="font-size:24px; color:#ccc;"></i></span>'; }
        }

        function toggleExpiryField(selectId, containerId) {
            const category = document.getElementById(selectId).value;
            const container = document.getElementById(containerId);
            const expiryInput = container.querySelector('input');
            const expiringCategories = ['Food', 'Voucher']; 
            if (expiringCategories.includes(category)) {
                container.style.display = 'block';
                expiryInput.required = true;
            } else {
                container.style.display = 'none';
                expiryInput.value = ''; expiryInput.required = false;
            }
        }

        function toggleMenu(e, id) { 
            e.stopPropagation(); 
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); 
            const menu = document.getElementById('menu-' + id); 
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block'; 
        }

        window.addEventListener('click', function(e) { 
            if (!e.target.matches('.menu-btn') && !e.target.matches('.menu-btn *')) { document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); } 
            if (e.target.classList.contains('modal')) { e.target.style.display = 'none'; } 
            
            // Close lightbox on outside click
            if (e.target.id == 'imageLightbox') closeLightbox();
        });
        
        // Keyboard Support for Lightbox
        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape" && document.getElementById('imageLightbox').style.display === "flex") {
                closeLightbox();
            }
        });
        
        // [MODIFIED] Replaced standard confirm() with SweetAlert2
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
            if (val === 'category') document.getElementById('filter_category_container').classList.add('active'); 
        }
        document.addEventListener('DOMContentLoaded', toggleFilterInputs);
        const alertBox = document.getElementById('alertMsg'); if (alertBox) setTimeout(() => { alertBox.style.display = 'none'; }, 4000);
        const alertErr = document.getElementById('alertError'); if (alertErr) setTimeout(() => { alertErr.style.display = 'none'; }, 4000);

        function openEditModal(data) {
            document.getElementById('edit_id').value = data.Reward_ID;
            document.getElementById('edit_name').value = data.Reward_ItemName;
            document.getElementById('edit_desc').value = data.Reward_Description;
            document.getElementById('edit_points').value = data.Reward_RequiredPoint;
            document.getElementById('edit_stock').value = data.Reward_Stock;
            document.getElementById('edit_status').value = data.Reward_Status;
            
            const categorySelect = document.getElementById('edit_category');
            categorySelect.value = data.Reward_Category || 'Others';
            
            document.getElementById('edit_supplier').value = data.Reward_Supplier || '';
            document.getElementById('edit_expiry').value = data.Reward_ExpiryDate || '';
            
            const previewContainer = document.getElementById('edit-preview-container');
            let originalSrc = null;
            if (data.Reward_PhotoPath) { 
                originalSrc = "uploads/rewards/" + data.Reward_PhotoPath;
                previewContainer.innerHTML = `<img src="${originalSrc}" alt="Current">`; 
            } else { previewContainer.innerHTML = '<span>No Image</span>'; }
            
            document.getElementById('edit_photo').value = '';
            document.getElementById('edit-file-info').style.display = 'none';
            document.getElementById('edit_name_error').style.display = 'none';
            document.getElementById('edit-file-remove-btn').onclick = function() { removeImage('edit_photo', 'edit-preview-container', 'edit-file-info', originalSrc); };
            
            document.getElementById('editModal').style.display = 'flex';
            toggleExpiryField('edit_category', 'edit_expiry_container');
        }

        function openHistoryModal(id, itemName, itemCode) {
            document.getElementById('historyTitle').innerText = 'History: ' + itemName;
            document.getElementById('historySubtitle').innerText = 'Item ID: ' + itemCode;
            
            const bodyRedeem = document.getElementById('historyBodyRedeem');
            const bodyLog = document.getElementById('historyBodyLog');
            
            bodyRedeem.innerHTML = '<tr><td colspan="4" style="text-align:center">Loading...</td></tr>';
            bodyLog.innerHTML = '<tr><td colspan="4" style="text-align:center">Loading...</td></tr>';
            
            // Reset to first tab
            switchHistoryTab('redeem');

            document.getElementById('historyModal').style.display = 'flex';
            
            fetch(`reward_item_management.php?action=get_full_history&item_id=${id}`)
            .then(r => r.json())
            .then(data => {
                bodyRedeem.innerHTML = '';
                bodyLog.innerHTML = '';

                // Render Redemptions (With Link to Details)
                if (data.redemptions.length === 0) {
                    bodyRedeem.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999; font-size:12px;">No redemptions found.</td></tr>';
                } else {
                    data.redemptions.forEach(row => {
                        let color = (row.status.toLowerCase() === 'completed') ? '#28a745' : '#ffc107';
                        let viewLink = `<a href="admin_redemption_details.php?id=${row.Redemption_ID}" target="_blank" class="view-btn">View</a>`;
                        
                        bodyRedeem.innerHTML += `<tr>
                            <td>${row.date}</td>
                            <td>${row.user}</td>
                            <td style="color:${color};font-weight:600">${row.status}</td>
                            <td>${viewLink}</td>
                        </tr>`;
                    });
                }

                // Render Admin Logs
                if (data.logs.length === 0) {
                    bodyLog.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999; font-size:12px;">No updates recorded.</td></tr>';
                } else {
                    data.logs.forEach(row => {
                        let actionColor = '#666';
                        if(row.status === 'Create') actionColor = 'blue';
                        if(row.status === 'Delete') actionColor = 'red';
                        if(row.status === 'Stock Update') actionColor = 'green';
                        
                        bodyLog.innerHTML += `<tr><td>${row.date}</td><td>${row.user}</td><td style="color:${actionColor};font-weight:600">${row.status}</td><td style=\"font-size:11px;\">${row.info}</td></tr>`;
                    });
                }
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>