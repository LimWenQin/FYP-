<?php
// branch_management_page.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- Get Current User Info ---
$adminName = "User";
$adminPosition = "Role";
$adminProfilePicture = null;
$adminId = 0;
// Check if current user is staff (for UI logic later)
$isStaff = isset($_SESSION['staff_id']);

if (isset($_SESSION['admin_id'])) {
    $adminId = $_SESSION['admin_id'];
    $sql = "SELECT Admin_ProfilePicture, Admin_Role, Admin_Name FROM admin WHERE Admin_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $adminName = $row['Admin_Name'];
        $adminProfilePicture = $row['Admin_ProfilePicture'];
        $adminPosition = !empty($row['Admin_Role']) ? $row['Admin_Role'] : "Admin";
    }
    $stmt->close();
} elseif (isset($_SESSION['staff_id'])) {
    $currentStaffId = $_SESSION['staff_id'];
    $adminId = $currentStaffId; 
    $sql = "SELECT Staff_FullName, Staff_ProfilePicture, Staff_Role FROM staff WHERE Staff_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $currentStaffId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $adminName = $row['Staff_FullName'];
        $adminProfilePicture = $row['Staff_ProfilePicture'];
        $adminPosition = $row['Staff_Role'];
    }
    $stmt->close();
}

// --- SEARCH & FILTER PREPARATION ---
$search = "";
$filterType = "";
$filterValue = "";
$conditions = ["Is_Deleted = 0"];
$orderClause = "ORDER BY Branch_ID DESC"; // Default Sort
$queryParams = []; // To store params for pagination

// 1. Keyword Search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $conditions[] = "(Branch_Name LIKE '%$search%' OR Branch_City LIKE '%$search%' OR Branch_ID LIKE '%$search%')";
}

// 2. Dynamic Filters
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    
    // Status
    if ($filterType == 'status' && !empty($_GET['filter_val_status'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_status']);
        $conditions[] = "Branch_OperationalStatus = '$filterValue'";
    
    // Category
    } elseif ($filterType == 'category' && !empty($_GET['filter_val_category'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_category']);
        $conditions[] = "Branch_Type = '$filterValue'";
    
    // State
    } elseif ($filterType == 'state' && !empty($_GET['filter_val_state'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_state']);
        $conditions[] = "Branch_State = '$filterValue'";
    
    // City
    } elseif ($filterType == 'city' && !empty($_GET['filter_val_city'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_city']);
        $conditions[] = "Branch_City = '$filterValue'";

    // Phone Prefix
    } elseif ($filterType == 'phone' && !empty($_GET['filter_val_phone'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_phone']);
        $conditions[] = "Branch_ContactNumber LIKE '%$filterValue%'";
    
    // Capacity
    } elseif ($filterType == 'capacity' && !empty($_GET['filter_val_capacity'])) {
        $filterValue = $_GET['filter_val_capacity'];
        if ($filterValue == 'below_100') {
            $conditions[] = "Branch_Capacity < 100";
        } elseif ($filterValue == '100_500') {
            $conditions[] = "Branch_Capacity BETWEEN 100 AND 500";
        } elseif ($filterValue == 'above_500') {
            $conditions[] = "Branch_Capacity > 500";
        }

    // Name Sorting
    } elseif ($filterType == 'name_sort' && !empty($_GET['filter_val_name'])) {
        $filterValue = $_GET['filter_val_name'];
        if ($filterValue == 'asc') $orderClause = "ORDER BY Branch_Name ASC";
        elseif ($filterValue == 'desc') $orderClause = "ORDER BY Branch_Name DESC";
    
    // ID Sorting
    } elseif ($filterType == 'id_sort' && !empty($_GET['filter_val_id'])) {
        $filterValue = $_GET['filter_val_id'];
        if ($filterValue == 'asc') $orderClause = "ORDER BY Branch_ID ASC";
        elseif ($filterValue == 'desc') $orderClause = "ORDER BY Branch_ID DESC";
    
    // Established Date Range
    } elseif ($filterType == 'established_date') {
        if (!empty($_GET['filter_start_date'])) {
            $startDate = $conn->real_escape_string($_GET['filter_start_date']);
            $conditions[] = "Branch_EstablishedDate >= '$startDate'";
            $queryParams['filter_start_date'] = $startDate;
        }
        if (!empty($_GET['filter_end_date'])) {
            $endDate = $conn->real_escape_string($_GET['filter_end_date']);
            $conditions[] = "Branch_EstablishedDate <= '$endDate'";
            $queryParams['filter_end_date'] = $endDate;
        }
    }
}

$whereClause = "WHERE " . implode(" AND ", $conditions);

// --- EXPORT TO EXCEL ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "branch_list_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $exportSql = "SELECT b.*, 
                  COALESCE((SELECT SUM(Order_Amount) FROM orders o WHERE o.Branch_ID = b.Branch_ID AND (o.Order_Status = 'Success' OR o.Order_Status = 'Completed')), 0) as TotalRaised 
                  FROM branch b $whereClause $orderClause";
    $res = $conn->query($exportSql);

    echo '<table border="1">';
    echo '<tr><th>ID</th><th>Category</th><th>Name</th><th>Branch Contact</th><th>Branch Email</th><th>Bank Name</th><th>Bank Account</th><th>PIC Name</th><th>PIC Contact</th><th>PIC Email</th><th>Capacity</th><th>Established Date</th><th>Total Raised (RM)</th><th>Address</th><th>Status</th></tr>';
    
    if ($res && $res->num_rows > 0) {
        while($row = $res->fetch_assoc()) {
            $addr = $row['Branch_Address1'] . ", " . $row['Branch_Address2'] . ", " . $row['Branch_Address3'] . ", " . $row['Branch_PostalCode'] . " " . $row['Branch_City'] . ", " . $row['Branch_State'];
            echo "<tr>
                <td>{$row['Branch_ID']}</td>
                <td>{$row['Branch_Type']}</td>
                <td>{$row['Branch_Name']}</td>
                <td>'{$row['Branch_ContactNumber']}</td>
                <td>{$row['Branch_Email']}</td>
                <td>{$row['Branch_BankName']}</td>
                <td>'{$row['Branch_BankAccount']}</td>
                <td>{$row['Branch_Head']}</td>
                <td>'{$row['Branch_Head_Contact']}</td>
                <td>{$row['Branch_Head_Email']}</td>
                <td>{$row['Branch_Capacity']}</td>
                <td>{$row['Branch_EstablishedDate']}</td>
                <td>{$row['TotalRaised']}</td>
                <td>{$addr}</td>
                <td>{$row['Branch_OperationalStatus']}</td>
            </tr>";
        }
    }
    echo '</table>';
    exit();
}

// --- STATS LOGIC ---
function getBranchStats($conn) {
    $endOfLastMonth = date('Y-m-t 23:59:59', strtotime('last month'));
    $sqlTotalNow = "SELECT COUNT(*) as total FROM branch WHERE Is_Deleted = 0";
    $totalBranchesNow = $conn->query($sqlTotalNow)->fetch_assoc()['total'];
    
    $checkCol = $conn->query("SHOW COLUMNS FROM `branch` LIKE 'Branch_CreatedAt'");
    if($checkCol && $checkCol->num_rows > 0) {
        $sqlTotalLast = "SELECT COUNT(*) as total FROM branch WHERE Is_Deleted = 0 AND Branch_CreatedAt <= '$endOfLastMonth'"; 
        $totalBranchesLast = isset($conn->query($sqlTotalLast)->fetch_assoc()['total']) ? $conn->query($sqlTotalLast)->fetch_assoc()['total'] : 0;
    } else { $totalBranchesLast = 0; }

    $branchPercentChange = ($totalBranchesLast > 0) ? (($totalBranchesNow - $totalBranchesLast) / $totalBranchesLast) * 100 : ($totalBranchesNow > 0 ? 100 : 0);

    $sqlActiveNow = "SELECT COUNT(*) as total FROM branch WHERE Branch_OperationalStatus = 'Open' AND Is_Deleted = 0";
    $activeBranchesNow = $conn->query($sqlActiveNow)->fetch_assoc()['total'];
    $activePercentChange = 0; 

    $sqlDonationNow = "SELECT SUM(Order_Amount) as total FROM orders WHERE Branch_ID IS NOT NULL AND (Order_Status = 'Success' OR Order_Status = 'Completed')";
    $totalDonationNow = (float)($conn->query($sqlDonationNow)->fetch_assoc()['total'] ?? 0);
    $donationPercentChange = 0; 

    return [
        'totalBranches' => $totalBranchesNow,
        'branchPercentChange' => number_format(abs($branchPercentChange), 1),
        'branchTrend' => ($branchPercentChange >= 0) ? 'up' : 'down',
        'activeBranches' => $activeBranchesNow,
        'activePercentChange' => '0.0',
        'activeTrend' => 'up',
        'totalDonationAmount' => $totalDonationNow,
        'donationPercentChange' => '0.0',
        'donationTrend' => 'up'
    ];
}
$stats = getBranchStats($conn);

// --- DELETE ---
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    $conn->query("UPDATE branch SET Is_Deleted = 1 WHERE Branch_ID = $deleteId");
    header("Location: branch_management_page.php?success=" . urlencode("Branch deleted successfully!"));
    exit();
}

// --- DATA LISTS & FILTERS ---
$branchTypes = ['Headquarters', 'Branch', 'Old Folks Home', 'Orphanage', 'Disabled Care Center'];
$malaysiaStates = ['Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'];
$phonePrefixes = ['010', '011', '012', '013', '014', '015', '016', '017', '018', '019'];
$cities = [];
$cityQ = $conn->query("SELECT DISTINCT Branch_City FROM branch WHERE Is_Deleted = 0 AND Branch_City != '' ORDER BY Branch_City ASC");
if($cityQ) while($c = $cityQ->fetch_assoc()) $cities[] = $c['Branch_City'];

// --- PAGINATION & QUERY ---
$results_per_page = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$count_sql = "SELECT COUNT(*) as total FROM branch $whereClause";
$count_result = $conn->query($count_sql);
$total_records = ($count_result && $row = $count_result->fetch_assoc()) ? $row['total'] : 0;
$total_pages = ceil($total_records / $results_per_page);
if ($page > $total_pages && $total_pages > 0) { $page = $total_pages; $start_from = ($page - 1) * $results_per_page; }

$sql = "SELECT b.*, 
        COALESCE((SELECT SUM(Order_Amount) FROM orders o WHERE o.Branch_ID = b.Branch_ID AND (o.Order_Status = 'Success' OR o.Order_Status = 'Completed')), 0) as TotalDonated 
        FROM branch b $whereClause $orderClause LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$branches = [];
if ($result) { while($row = $result->fetch_assoc()) $branches[] = $row; }

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// --- COLLECT IMAGES FOR LIGHTBOX JS ---
$allBranchImagesMap = [];
$exportParams = $_GET; $exportParams['action'] = 'export_excel'; unset($exportParams['page']);
$exportUrl = "?" . http_build_query($exportParams);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Management - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .stats-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #888; margin-bottom: 5px; text-transform: uppercase; font-weight: 600; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; color: #333; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; gap: 5px; margin: 0; }
        .text-success { color: #28a745 !important; } .text-danger { color: #dc3545 !important; }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(23, 162, 184, 0.2); color: var(--info); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }

        .management-container { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; margin: 0; color: #333; }
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; font-size: 14px; text-decoration: none;}
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        .search-filter-container { margin-bottom: 25px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; background-color: white; min-width: 140px; cursor: pointer; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; background: white; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .branch-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .branch-card { background: white; border-radius: 12px; overflow: visible; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.3s ease; position: relative; display: flex; flex-direction: column; border: 1px solid #f0f0f0; }
        .branch-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        
        .card-image-container { position: relative; height: 200px; background: #f0f0f0; overflow: hidden; border-top-left-radius: 12px; border-top-right-radius: 12px; }
        .card-img { width: 100%; height: 100%; object-fit: cover; display: none; transition: opacity 0.3s; cursor: zoom-in; }
        .card-img.active { display: block; }
        
        .no-image-placeholder { width: 100%; height: 100%; background: #e9ecef; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #adb5bd; }
        .no-image-placeholder i { font-size: 48px; margin-bottom: 10px; }
        .no-image-placeholder span { font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; }

        .carousel-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; padding: 10px; cursor: pointer; z-index: 2; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; }
        .prev-btn { left: 10px; } .next-btn { right: 10px; }
        .img-counter { position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; }

        .status-badge { position: absolute; top: 15px; left: 15px; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 5; pointer-events: none; }
        .status-open { background: #d4edda; color: #155724; } .status-closed { background: #f8d7da; color: #721c24; }
        
        .card-content { padding: 20px; flex: 1; display: flex; flex-direction: column; position: relative; }
        .card-actions { position: absolute; top: 10px; right: 10px; z-index: 50; }
        .menu-btn { background-color: rgba(255, 255, 255, 0.95); border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; color: #555; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.15); transition: all 0.2s; }
        .menu-btn:hover { background-color: white; color: var(--primary); transform: scale(1.1); }
        .action-dropdown { position: absolute; top: 40px; right: 0; background: white; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); width: 190px; display: none; overflow: hidden; margin-bottom: 5px; border: 1px solid #eee; z-index: 100; }
        .action-dropdown.show { display: block; animation: fadeIn 0.2s; }
        .action-item { padding: 10px 15px; display: flex; align-items: center; gap: 8px; color: #333; cursor: pointer; font-size: 13px; transition: 0.1s; text-decoration: none; }
        .action-item:hover { background: #f8f9fa; color: var(--primary); }
        .action-item.delete { color: var(--danger); border-top: 1px solid #f0f0f0; }

        .branch-type { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 1px; margin-bottom: 5px; }
        .branch-title { font-size: 18px; font-weight: 700; margin-bottom: 15px; color: #333; padding-right: 10px; line-height: 1.3; }
        
        .info-grid { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        .info-item { display: flex; align-items: flex-start; gap: 12px; font-size: 13px; color: #555; }
        .info-icon { width: 18px; min-width: 18px; text-align: center; color: var(--primary); margin-top: 2px; }
        .info-text { word-break: break-all; }
        .info-label { font-weight: 600; font-size: 11px; color: #999; text-transform: uppercase; display: block; margin-bottom: 1px; }

        .card-stats { border-top: 1px solid #eee; padding-top: 15px; margin-top: auto; display: flex; justify-content: space-between; align-items: center; }
        .raised-amount { font-size: 16px; font-weight: 700; color: #28a745; }
        .raised-label { font-size: 11px; color: #888; display: block; }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; display: inline-block; }
        .pagination-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; border-color: #eee; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; animation: slideIn 0.3s; }
        .floating-alert div { line-height: 1.6; }
        .floating-alert i { margin-top: 4px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* LIGHTBOX */
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 2002; }
        .lightbox-nav { cursor: pointer; position: absolute; top: 50%; padding: 16px; margin-top: -50px; color: white; font-weight: bold; font-size: 30px; transition: 0.6s ease; z-index: 2001; background-color: rgba(0,0,0,0.3); }
        .lightbox-prev { left: 0; } .lightbox-next { right: 0; }

        /* Modal for Delete Confirmation (Keep simple modal for critical delete) */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; border-radius: 10px; width: 90%; max-width: 400px; padding: 20px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .modal-header { margin-bottom: 20px; }
        .modal-header h2 { margin: 0; font-size: 18px; }

        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: 1fr; }
            .search-filter-container { flex-direction: column; align-items: stretch; }
            .filter-select, .search-input { width: 100%; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="floating-alert floating-alert-success" id="floatingSuccess" style="display: <?php echo isset($_GET['success']) ? 'flex' : 'none'; ?>">
                <i class="fas fa-check-circle"></i>
                <div id="floatingSuccessText"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div>
            </div>

            <div class="floating-alert floating-alert-danger" id="floatingError" style="display: <?php echo isset($_GET['error']) ? 'flex' : 'none'; ?>">
                <i class="fas fa-exclamation-circle"></i>
                <div id="floatingErrorText"><?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?></div>
            </div>

            <div class="welcome-section"><h1>Branch Management</h1><p>Manage shelter branches, view status, and track donations.</p></div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL BRANCHES</h3><h2><?php echo $stats['totalBranches']; ?></h2>
                        <p class="<?php echo ($stats['branchTrend'] == 'down') ? 'text-danger' : 'text-success'; ?>">
                            <i class="fas fa-arrow-<?php echo ($stats['branchTrend'] == 'down') ? 'down' : 'up'; ?>"></i> <?php echo ($stats['branchTrend'] == 'down' ? '-' : '+') . $stats['branchPercentChange']; ?>% from last month
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>ACTIVE BRANCHES</h3><h2><?php echo $stats['activeBranches']; ?></h2>
                        <p class="<?php echo ($stats['activeTrend'] == 'down') ? 'text-danger' : 'text-success'; ?>">
                            <i class="fas fa-arrow-<?php echo ($stats['activeTrend'] == 'down') ? 'down' : 'up'; ?>"></i> <?php echo ($stats['activeTrend'] == 'down' ? '-' : '+') . $stats['activePercentChange']; ?>% from last month
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL DONATIONS</h3><h2>RM <?php echo number_format($stats['totalDonationAmount'], 0); ?></h2>
                        <p class="<?php echo ($stats['donationTrend'] == 'down') ? 'text-danger' : 'text-success'; ?>">
                            <i class="fas fa-arrow-<?php echo ($stats['donationTrend'] == 'down') ? 'down' : 'up'; ?>"></i> <?php echo ($stats['donationTrend'] == 'down' ? '-' : '+') . $stats['donationPercentChange']; ?>% from last month
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                </div>
            </div>

            <div class="management-container">
                <div class="section-header">
                    <h2>Branch List</h2>
                    <div class="action-buttons">
                        <a href="branch_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Branch</a>
                        <a href="<?php echo $exportUrl; ?>" class="btn btn-success" target="_blank"><i class="fas fa-download"></i> Export Data</a>
                    </div>
                </div>

                <form method="GET" class="search-filter-container">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilters()">
                            <option value="">Filter By...</option>
                            <option value="status" <?php echo ($filterType == 'status') ? 'selected' : ''; ?>>Status</option>
                            <option value="category" <?php echo ($filterType == 'category') ? 'selected' : ''; ?>>Category</option>
                            <option value="state" <?php echo ($filterType == 'state') ? 'selected' : ''; ?>>State</option>
                            <option value="city" <?php echo ($filterType == 'city') ? 'selected' : ''; ?>>City</option>
                            <option value="phone" <?php echo ($filterType == 'phone') ? 'selected' : ''; ?>>Phone Prefix</option>
                            <option value="capacity" <?php echo ($filterType == 'capacity') ? 'selected' : ''; ?>>Capacity</option>
                            <option value="name_sort" <?php echo ($filterType == 'name_sort') ? 'selected' : ''; ?>>Name Sorting</option>
                            <option value="id_sort" <?php echo ($filterType == 'id_sort') ? 'selected' : ''; ?>>Branch ID</option>
                            <option value="established_date" <?php echo ($filterType == 'established_date') ? 'selected' : ''; ?>>Established Date</option>
                        </select>
                    </div>

                    <div id="filter_status_container" class="secondary-filter"><select name="filter_val_status" class="filter-select"><option value="Open" <?php if($filterValue == 'Open') echo 'selected'; ?>>Open</option><option value="Closed" <?php if($filterValue == 'Closed') echo 'selected'; ?>>Closed</option></select></div>
                    <div id="filter_category_container" class="secondary-filter"><select name="filter_val_category" class="filter-select"><option value="">All Categories</option><?php foreach($branchTypes as $t) echo "<option value='$t' ".($filterValue==$t?'selected':'').">$t</option>"; ?></select></div>
                    <div id="filter_state_container" class="secondary-filter"><select name="filter_val_state" class="filter-select"><option value="">All States</option><?php foreach($malaysiaStates as $s) echo "<option value='$s' ".($filterValue==$s?'selected':'').">$s</option>"; ?></select></div>
                    <div id="filter_city_container" class="secondary-filter"><select name="filter_val_city" class="filter-select"><option value="">Select City...</option><?php foreach($cities as $c): ?><option value="<?php echo $c; ?>" <?php if($filterValue == $c) echo 'selected'; ?>><?php echo $c; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_phone_container" class="secondary-filter"><select name="filter_val_phone" class="filter-select"><option value="">Select Prefix...</option><?php foreach($phonePrefixes as $pp): ?><option value="<?php echo $pp; ?>" <?php if($filterValue == $pp) echo 'selected'; ?>>+6<?php echo $pp; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_capacity_container" class="secondary-filter"><select name="filter_val_capacity" class="filter-select"><option value="">Select Range...</option><option value="below_100" <?php if($filterValue == 'below_100') echo 'selected'; ?>>Below 100</option><option value="100_500" <?php if($filterValue == '100_500') echo 'selected'; ?>>100 - 500</option><option value="above_500" <?php if($filterValue == 'above_500') echo 'selected'; ?>>Above 500</option></select></div>
                    <div id="filter_name_sort_container" class="secondary-filter"><select name="filter_val_name" class="filter-select"><option value="">Select Order...</option><option value="asc" <?php if($filterValue == 'asc') echo 'selected'; ?>>Name (A-Z)</option><option value="desc" <?php if($filterValue == 'desc') echo 'selected'; ?>>Name (Z-A)</option></select></div>
                    <div id="filter_id_sort_container" class="secondary-filter"><select name="filter_val_id" class="filter-select"><option value="">Select Order...</option><option value="asc" <?php if($filterValue == 'asc') echo 'selected'; ?>>ID (Ascending)</option><option value="desc" <?php if($filterValue == 'desc') echo 'selected'; ?>>ID (Descending)</option></select></div>
                    <div id="filter_established_date_container" class="secondary-filter" style="display:none; align-items:center; gap:5px;">
                        <input type="date" name="filter_start_date" class="filter-select" value="<?php echo isset($_GET['filter_start_date']) ? $_GET['filter_start_date'] : ''; ?>">
                        <span>to</span>
                        <input type="date" name="filter_end_date" class="filter-select" value="<?php echo isset($_GET['filter_end_date']) ? $_GET['filter_end_date'] : ''; ?>">
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search branch name, city..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($search) || !empty($filterType)): ?><a href="branch_management_page.php" class="btn btn-danger" style="padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a><?php endif; ?>
                </form>

                <div class="branch-grid">
                    <?php if (count($branches) > 0): ?>
                        <?php foreach($branches as $b): ?>
                            <?php 
                                $statusClass = ($b['Branch_OperationalStatus'] == 'Open') ? 'status-open' : 'status-closed';
                                $images = json_decode($b['Branch_Images'], true);
                                $hasImages = ($images && !empty($images));
                                if($hasImages) {
                                    $allBranchImagesMap[$b['Branch_ID']] = $images;
                                }
                            ?>
                            <div class="branch-card" id="card-<?php echo $b['Branch_ID']; ?>">
                                <div class="card-actions">
                                    <div class="action-menu">
                                        <button class="menu-btn" onclick="toggleCardMenu(event, <?php echo $b['Branch_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div id="card-menu-<?php echo $b['Branch_ID']; ?>" class="action-dropdown">
                                            <a href="admin_branch_details.php?id=<?php echo $b['Branch_ID']; ?>" class="action-item"><i class="fas fa-eye"></i> View Details</a>
                                            <a href="activity_management.php?filter_type=branch&filter_val_branch=<?php echo $b['Branch_ID']; ?>" class="action-item"><i class="fas fa-calendar-alt"></i> View Activities</a>
                                            
                                            <a href="branch_edit.php?id=<?php echo $b['Branch_ID']; ?>" class="action-item"><i class="fas fa-edit"></i> Edit Branch</a>
                                            
                                            <?php if (!$isStaff): ?>
                                                <a href="branch_donation_history.php?branch_id=<?php echo $b['Branch_ID']; ?>" class="action-item"><i class="fas fa-history"></i> Donation History</a>
                                                <a href="branch_withdrawal_history.php?branch_id=<?php echo $b['Branch_ID']; ?>" class="action-item"><i class="fas fa-money-bill-wave"></i> Withdrawal History</a>
                                            <?php endif; ?>
                                            
                                            <div class="action-item delete" onclick="confirmDelete(<?php echo $b['Branch_ID']; ?>)"><i class="fas fa-trash"></i> Delete</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-image-container">
                                    <?php if($hasImages): ?>
                                        <?php foreach($images as $idx => $img): ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" class="card-img <?php echo $idx===0?'active':''; ?>" onclick="openLightbox(<?php echo $b['Branch_ID']; ?>, <?php echo $idx; ?>)">
                                        <?php endforeach; ?>
                                        <?php if(count($images) > 1): ?>
                                            <button class="carousel-btn prev-btn" onclick="moveCarousel(<?php echo $b['Branch_ID']; ?>, -1)">&#10094;</button>
                                            <button class="carousel-btn next-btn" onclick="moveCarousel(<?php echo $b['Branch_ID']; ?>, 1)">&#10095;</button>
                                            <span class="img-counter">1/<?php echo count($images); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="no-image-placeholder"><i class="fas fa-image"></i><span>No Image Available</span></div>
                                    <?php endif; ?>
                                    <div class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($b['Branch_OperationalStatus'] ?: 'Open'); ?></div>
                                </div>
                                
                                <div class="card-content">
                                    <div class="branch-type"><?php echo htmlspecialchars($b['Branch_Type']); ?></div>
                                    <div class="branch-title"><?php echo htmlspecialchars($b['Branch_Name']); ?></div>
                                    
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <div class="info-icon"><i class="fas fa-envelope"></i></div>
                                            <div class="info-text"><span class="info-label">Email</span><?php echo htmlspecialchars($b['Branch_Email']); ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-icon"><i class="fas fa-phone"></i></div>
                                            <div class="info-text"><span class="info-label">Contact</span><?php echo htmlspecialchars($b['Branch_ContactNumber']); ?></div>
                                        </div>
                                    </div>

                                    <div class="card-stats">
                                        <div><span class="raised-label">Total Donated</span><span class="raised-amount">RM <?php echo number_format($b['TotalDonated'], 2); ?></span></div>
                                        <a href="admin_branch_details.php?id=<?php echo $b['Branch_ID']; ?>" style="color:var(--primary); font-size:12px; font-weight:bold; text-decoration:none;">More Info <i class="fas fa-arrow-right"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align:center; padding:50px; color:#888;">No active branches found.</div>
                    <?php endif; ?>
                </div>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        if(!empty($search)) $queryParams['search'] = $search;
                        if(!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if($filterType == 'category' && !empty($filterValue)) $queryParams['filter_val_category'] = $filterValue;
                            elseif($filterType == 'state' && !empty($filterValue)) $queryParams['filter_val_state'] = $filterValue;
                            elseif($filterType == 'status' && !empty($filterValue)) $queryParams['filter_val_status'] = $filterValue;
                            elseif($filterType == 'name_sort' && !empty($filterValue)) $queryParams['filter_val_name'] = $filterValue;
                            elseif($filterType == 'id_sort' && !empty($filterValue)) $queryParams['filter_val_id'] = $filterValue;
                            elseif($filterType == 'phone' && !empty($filterValue)) $queryParams['filter_val_phone'] = $filterValue;
                            elseif($filterType == 'city' && !empty($filterValue)) $queryParams['filter_val_city'] = $filterValue;
                            elseif($filterType == 'capacity' && !empty($filterValue)) $queryParams['filter_val_capacity'] = $filterValue;
                        }
                        // Note: established_date query params are already added to $queryParams in the PHP logic block above if they exist
                        
                        $queryString = http_build_query($queryParams);
                        $paginationUrl = !empty($queryString) ? '&' . $queryString : '';

                        if ($page > 1) echo '<a href="?page=' . ($page - 1) . $paginationUrl . '" class="pagination-btn">Previous</a>';
                        else echo '<span class="pagination-btn disabled">Previous</span>';

                        $start_window = max(1, $page - 1);
                        $end_window = min($total_pages, $page + 1);
                        if ($page == 1) $end_window = min($total_pages, 3);
                        if ($page == $total_pages) $start_window = max(1, $total_pages - 2);

                        for ($i = $start_window; $i <= $end_window; $i++) {
                            if ($i == $page) echo '<span class="pagination-btn active">' . $i . '</span>';
                            else echo '<a href="?page=' . $i . $paginationUrl . '" class="pagination-btn">' . $i . '</a>';
                        }

                        if ($page < $total_pages) echo '<a href="?page=' . ($page + 1) . $paginationUrl . '" class="pagination-btn">Next</a>';
                        else echo '<span class="pagination-btn disabled">Next</span>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="imageLightbox" class="lightbox-modal">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <a class="lightbox-nav lightbox-prev" onclick="changeLightboxImage(-1)">&#10094;</a>
        <img class="lightbox-content" id="lightboxImage">
        <a class="lightbox-nav lightbox-next" onclick="changeLightboxImage(1)">&#10095;</a>
    </div>

    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="background-color: #dc3545; color: white; justify-content:center; padding:15px; border-radius:5px 5px 0 0; margin-top:-20px; margin-left:-20px; margin-right:-20px;">
                <h2 style="color:white; margin:0; font-size:18px;"><i class="fas fa-exclamation-triangle"></i> Delete Branch</h2>
            </div>
            <div class="modal-body" style="padding-top:20px;">
                <p style="color:#555; margin-bottom:20px;">Are you sure you want to delete this branch?<br>This action cannot be undone.</p>
                <div style="display: flex; justify-content: center; gap: 10px;">
                    <button class="btn" style="background:#eee; color:#333;" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
                    <a id="confirmDeleteBtn" href="#" class="btn btn-danger">Yes, Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.getElementById('floatingSuccess');
            const errorAlert = document.getElementById('floatingError');
            if (successAlert && successAlert.style.display === 'flex') setTimeout(() => { successAlert.style.display = 'none'; }, 5000);
            if (errorAlert && errorAlert.style.display === 'flex') setTimeout(() => { errorAlert.style.display = 'none'; }, 5000);
            toggleFilters();
        });

        function toggleFilters() {
            const type = document.getElementById('filterType').value;
            // Hide all secondary filters and disable their inputs
            document.querySelectorAll('.secondary-filter').forEach(el => { 
                el.classList.remove('active'); 
                if(el.tagName === 'DIV' && el.querySelector('select')) el.querySelector('select').disabled = true;
                if(el.tagName === 'DIV' && el.querySelector('input')) el.querySelectorAll('input').forEach(i => i.disabled = true);
            });

            if(type) {
                // Determine which container to show
                let containerId = 'filter_' + type + '_container';
                if (type === 'name_sort') containerId = 'filter_name_sort_container';
                else if (type === 'id_sort') containerId = 'filter_id_sort_container';
                
                const el = document.getElementById(containerId);
                if(el) { 
                    el.classList.add('active'); 
                    // Enable inputs within this container
                    if(el.querySelector('select')) el.querySelector('select').disabled = false;
                    if(el.querySelector('input')) el.querySelectorAll('input').forEach(i => i.disabled = false);
                    
                    // Special handling for established date range display style
                    if (type === 'established_date') {
                        el.style.display = 'flex';
                    } else {
                        // Reset style for others if needed, though class handles most
                        el.style.display = ''; 
                    }
                }
            }
        }

        function toggleCardMenu(event, id) {
            event.stopPropagation();
            document.querySelectorAll('.action-dropdown').forEach(d => { if (d.id !== 'card-menu-' + id) d.classList.remove('show'); });
            const menu = document.getElementById('card-menu-' + id);
            menu.classList.toggle('show');
        }

        window.onclick = function(event) {
            if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('show'));
            if (event.target.id == 'imageLightbox') closeLightbox();
            if (event.target.id == 'deleteModal') document.getElementById('deleteModal').style.display = 'none';
        }

        function confirmDelete(id) { 
            const link = document.getElementById('confirmDeleteBtn'); link.href = `branch_management_page.php?delete_id=${id}`; document.getElementById('deleteModal').style.display = 'flex'; 
        }

        // --- CAROUSEL & LIGHTBOX ---
        const allBranchImages = <?php echo json_encode($allBranchImagesMap); ?>;
        let currentLightboxBranchId = null; let currentLightboxIndex = 0;

        function moveCarousel(cardId, direction) {
            const card = document.getElementById('card-' + cardId);
            const images = card.querySelectorAll('.card-img');
            const counter = card.querySelector('.img-counter');
            let activeIndex = 0;
            images.forEach((img, index) => { if (img.classList.contains('active')) activeIndex = index; img.classList.remove('active'); });
            let newIndex = activeIndex + direction;
            if (newIndex >= images.length) newIndex = 0; if (newIndex < 0) newIndex = images.length - 1;
            images[newIndex].classList.add('active');
            if(counter) counter.innerText = (newIndex + 1) + '/' + images.length;
        }

        function openLightbox(branchId, index) {
            if (!allBranchImages[branchId] || allBranchImages[branchId].length === 0) return;
            currentLightboxBranchId = branchId; currentLightboxIndex = index;
            updateLightboxImage();
            document.getElementById('imageLightbox').style.display = "flex";
        }
        function closeLightbox() { document.getElementById('imageLightbox').style.display = "none"; }
        function changeLightboxImage(n) {
            if (currentLightboxBranchId === null) return;
            const images = allBranchImages[currentLightboxBranchId];
            currentLightboxIndex += n;
            if (currentLightboxIndex >= images.length) currentLightboxIndex = 0;
            else if (currentLightboxIndex < 0) currentLightboxIndex = images.length - 1;
            updateLightboxImage();
        }
        function updateLightboxImage() {
            const images = allBranchImages[currentLightboxBranchId];
            document.getElementById('lightboxImage').src = images[currentLightboxIndex];
            const prevBtn = document.querySelector('.lightbox-prev'); const nextBtn = document.querySelector('.lightbox-next');
            if (images.length <= 1) { prevBtn.style.display = 'none'; nextBtn.style.display = 'none'; } else { prevBtn.style.display = 'block'; nextBtn.style.display = 'block'; }
        }
        document.addEventListener('keydown', function(event) { if (document.getElementById('imageLightbox').style.display === "flex") { if (event.key === "Escape") closeLightbox(); if (event.key === "ArrowLeft") changeLightboxImage(-1); if (event.key === "ArrowRight") changeLightboxImage(1); } });
    </script>
</body>
</html>