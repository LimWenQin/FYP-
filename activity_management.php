<?php
// activity_management.php
session_start();

// --- Check Login ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// --- Determine User Role ---
$isStaff = isset($_SESSION['staff_id']);

// --- SEARCH & FILTER PREPARATION ---
$searchTerm = "";
$filterType = "";
$filterValue = "";

// Date specific variables (Creation Date / Start Date)
$filterDateDay = "";
$filterDateMonth = "";
$filterDateYear = "";

// End Date specific variables
$filterEndDateDay = "";
$filterEndDateMonth = "";
$filterEndDateYear = "";

$conditions = []; 
$orderClause = "ORDER BY a.Activity_StartDate DESC"; 

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $conditions[] = "(a.Activity_Name LIKE '%$searchTerm%' OR a.Activity_Description LIKE '%$searchTerm%' OR b.Branch_Name LIKE '%$searchTerm%' OR a.Activity_City LIKE '%$searchTerm%')";
}

if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    if ($filterType == 'status' && !empty($_GET['filter_val_status'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_status']);
        $conditions[] = "a.Activity_Status = '$filterValue'";
    } elseif ($filterType == 'branch' && !empty($_GET['filter_val_branch'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_branch']);
        $conditions[] = "a.Branch_ID = '$filterValue'";
    } elseif ($filterType == 'year' && !empty($_GET['filter_val_year'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_year']);
        $conditions[] = "YEAR(a.Activity_StartDate) = '$filterValue'";
    } elseif ($filterType == 'phone' && !empty($_GET['filter_val_phone'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_phone']);
        $conditions[] = "a.Activity_Contact_Number LIKE '%$filterValue%'";
    } elseif ($filterType == 'city' && !empty($_GET['filter_val_city'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_city']);
        $conditions[] = "a.Activity_City = '$filterValue'";
    } elseif ($filterType == 'capacity' && !empty($_GET['filter_val_capacity'])) {
        $filterValue = $_GET['filter_val_capacity'];
        if ($filterValue == 'below_100') {
            $conditions[] = "a.Activity_Max_Participants < 100 AND a.Activity_Max_Participants > 0";
        } elseif ($filterValue == '100_500') {
            $conditions[] = "a.Activity_Max_Participants BETWEEN 100 AND 500";
        } elseif ($filterValue == 'above_500') {
            $conditions[] = "a.Activity_Max_Participants > 500";
        }
    } elseif ($filterType == 'branch_sort' && !empty($_GET['filter_val_bsort'])) {
        $filterValue = $_GET['filter_val_bsort'];
        if ($filterValue == 'asc') $orderClause = "ORDER BY b.Branch_Name ASC";
        elseif ($filterValue == 'desc') $orderClause = "ORDER BY b.Branch_Name DESC";
    } elseif ($filterType == 'date') {
        // Creation Date Logic (Using Activity_StartDate as creation reference based on previous context)
        if (isset($_GET['filter_val_date_day']) && $_GET['filter_val_date_day'] !== '') {
            $filterDateDay = $conn->real_escape_string($_GET['filter_val_date_day']);
            $conditions[] = "DAY(a.Activity_StartDate) = '$filterDateDay'";
        }
        if (isset($_GET['filter_val_date_month']) && $_GET['filter_val_date_month'] !== '') {
            $filterDateMonth = $conn->real_escape_string($_GET['filter_val_date_month']);
            $conditions[] = "MONTH(a.Activity_StartDate) = '$filterDateMonth'";
        }
        if (isset($_GET['filter_val_date_year']) && $_GET['filter_val_date_year'] !== '') {
            $filterDateYear = $conn->real_escape_string($_GET['filter_val_date_year']);
            $conditions[] = "YEAR(a.Activity_StartDate) = '$filterDateYear'";
        }
    } elseif ($filterType == 'end_date') {
        // End Date Logic
        if (isset($_GET['filter_val_end_date_day']) && $_GET['filter_val_end_date_day'] !== '') {
            $filterEndDateDay = $conn->real_escape_string($_GET['filter_val_end_date_day']);
            $conditions[] = "DAY(a.Activity_EndDate) = '$filterEndDateDay'";
        }
        if (isset($_GET['filter_val_end_date_month']) && $_GET['filter_val_end_date_month'] !== '') {
            $filterEndDateMonth = $conn->real_escape_string($_GET['filter_val_end_date_month']);
            $conditions[] = "MONTH(a.Activity_EndDate) = '$filterEndDateMonth'";
        }
        if (isset($_GET['filter_val_end_date_year']) && $_GET['filter_val_end_date_year'] !== '') {
            $filterEndDateYear = $conn->real_escape_string($_GET['filter_val_end_date_year']);
            $conditions[] = "YEAR(a.Activity_EndDate) = '$filterEndDateYear'";
        }
    }
}

$whereClause = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

// --- EXPORT TO EXCEL ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "activity_list_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $exportSql = "SELECT a.*, b.Branch_Name FROM activity a LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID $whereClause $orderClause";
    $exportResult = $conn->query($exportSql);
    echo '<table border="1"><tr><th>ID</th><th>Name</th><th>Venue</th><th>Specific Date</th><th>Start Date</th><th>End Date</th><th>Organizer</th><th>Contact Name</th><th>Contact Phone</th><th>Contact Email</th><th>Max Participants</th><th>Status</th><th>Target (RM)</th><th>Raised (RM)</th><th>Address</th><th>City</th><th>State</th><th>Postcode</th><th>Country</th><th>Branch</th><th>Bank Name</th><th>Bank Account</th></tr>';
    if ($exportResult && $exportResult->num_rows > 0) {
        while($row = $exportResult->fetch_assoc()) {
            echo '<tr><td>'.$row['Activity_ID'].'</td><td>'.htmlspecialchars($row['Activity_Name']).'</td><td>'.htmlspecialchars($row['Activity_Venue']).'</td><td>'.$row['Activity_Date'].'</td><td>'.$row['Activity_StartDate'].'</td><td>'.$row['Activity_EndDate'].'</td><td>'.htmlspecialchars($row['Activity_Organizer']).'</td><td>'.htmlspecialchars($row['Activity_Contact_Name']).'</td><td>'.htmlspecialchars($row['Activity_Contact_Number']).'</td><td>'.htmlspecialchars($row['Activity_Contact_Email']).'</td><td>'.$row['Activity_Max_Participants'].'</td><td>'.htmlspecialchars($row['Activity_Status']).'</td><td>'.$row['Activity_TargetAmount'].'</td><td>'.$row['Activity_GetAmount'].'</td><td>'.htmlspecialchars($row['Activity_Address1'].' '.$row['Activity_Address2'].' '.$row['Activity_Address3']).'</td><td>'.htmlspecialchars($row['Activity_City']).'</td><td>'.htmlspecialchars($row['Activity_State']).'</td><td>'.htmlspecialchars($row['Activity_PostalCode']).'</td><td>'.htmlspecialchars($row['Activity_Country']).'</td><td>'.htmlspecialchars($row['Branch_Name']).'</td><td>'.htmlspecialchars($row['Activity_BankName']).'</td><td>'.htmlspecialchars($row['Activity_BankAccount']).'</td></tr>';
        }
    }
    echo '</table>';
    exit();
}

// --- DELETE ---
if (isset($_GET['delete_activity_id'])) {
    $deleteId = $_GET['delete_activity_id'];
    $deleteSql = "DELETE FROM activity WHERE Activity_ID = $deleteId";
    if ($conn->query($deleteSql)) header("Location: activity_management.php?success=" . urlencode("Activity deleted successfully!"));
    else header("Location: activity_management.php?error=" . urlencode("Error: " . $conn->error));
    exit();
}

// --- PAGINATION & QUERY ---
$results_per_page = 6; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$count_sql = "SELECT COUNT(*) as total FROM activity a LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID $whereClause";
$count_result = $conn->query($count_sql);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $results_per_page);
if ($page > $total_pages && $total_pages > 0) { $page = $total_pages; $start_from = ($page - 1) * $results_per_page; }

$sql = "SELECT a.*, b.Branch_Name FROM activity a LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID $whereClause $orderClause LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$activities = [];
if ($result && $result->num_rows > 0) { while($row = $result->fetch_assoc()) $activities[] = $row; }

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// --- LOAD DATA ---
$branches = [];
$branchResult = $conn->query("SELECT Branch_ID, Branch_Name FROM branch ORDER BY Branch_Name");
if ($branchResult) { while($row = $branchResult->fetch_assoc()) $branches[] = $row; }

$cities = [];
$cityResult = $conn->query("SELECT DISTINCT Activity_City FROM activity WHERE Activity_City IS NOT NULL AND Activity_City != '' ORDER BY Activity_City");
if ($cityResult) { while($row = $cityResult->fetch_assoc()) $cities[] = $row['Activity_City']; }

$totalActivities = $conn->query("SELECT COUNT(*) as c FROM activity")->fetch_assoc()['c'];
$activeActivities = $conn->query("SELECT COUNT(*) as c FROM activity WHERE Activity_Status = 'Active'")->fetch_assoc()['c'];
$completedActivities = $conn->query("SELECT COUNT(*) as c FROM activity WHERE Activity_Status = 'Completed'")->fetch_assoc()['c'];
$totalDonations = $conn->query("SELECT SUM(Activity_GetAmount) as s FROM activity")->fetch_assoc()['s'] ?: 0;

$years = range(date('Y'), 2023);
$phonePrefixes = ['010', '011', '012', '013', '014', '015', '016', '017', '018', '019'];

$exportParams = $_GET; $exportParams['action'] = 'export_excel'; unset($exportParams['page']);
$exportUrl = "?" . http_build_query($exportParams);

$allActivityImagesMap = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>In-Person Activity - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; gap: 5px; margin: 0; }
        .text-success { color: var(--success); } .text-danger { color: var(--danger); }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(23, 162, 184, 0.2); color: var(--info); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }

        .management-content { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; }
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; text-decoration: none; font-size: 13px; }
        .btn-primary { background: var(--primary); color: white; } 
        .btn-success { background: var(--success); color: white; } 
        .btn-danger { background: var(--danger); color: white; }

        .filter-search-bar { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; flex-wrap: wrap; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; min-width: 140px; cursor: pointer; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: flex; gap: 5px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; min-width: 200px; }

        .case-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; margin-top: 10px; }
        .case-card { background: white; border-radius: 12px; position: relative; display: flex; flex-direction: column; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; transition: transform 0.3s; }
        .case-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: #eee; }
        
        .card-image { height: 160px; background-color: #f9f9f9; position: relative; border-radius: 12px 12px 0 0; overflow: hidden; }
        .card-img { width: 100%; height: 100%; object-fit: cover; display: none; cursor: zoom-in; }
        .card-img.active { display: block; }
        .card-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f9f9f9; color: #ccc; }
        .carousel-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; padding: 8px; cursor: pointer; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; z-index: 5; }
        .prev-btn { left: 10px; } .next-btn { right: 10px; }
        .img-counter { position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; z-index: 5; }

        .card-status { position: absolute; top: 15px; left: 15px; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .status-active { background-color: #cff4fc; color: #055160; }
        .status-completed { background-color: #d1e7dd; color: #0f5132; }
        .status-upcoming { background-color: #fff3cd; color: #664d03; }
        .status-cancelled { background-color: #f8d7da; color: #842029; }

        .card-actions { position: absolute; top: 10px; right: 10px; z-index: 50; }
        .menu-btn { background-color: rgba(255, 255, 255, 0.95); border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; color: #555; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.15); transition: all 0.2s; }
        .menu-btn:hover { background-color: white; color: var(--primary); transform: scale(1.1); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 25px rgba(0,0,0,0.2); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 13px; cursor: pointer; transition: background 0.2s; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: var(--primary); }
        .text-delete { color: var(--danger) !important; border-top: 1px solid #eee; }

        .card-body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .card-title { font-size: 16px; font-weight: 700; color: #333; margin-bottom: 5px; }
        .card-subtitle { font-size: 12px; color: var(--info); margin-bottom: 10px; font-weight: 600; }
        .card-desc { font-size: 13px; color: #666; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 38px; }
        .progress-container { margin-top: auto; }
        .progress-labels { display: flex; justify-content: space-between; font-size: 12px; color: #555; margin-bottom: 5px; font-weight: 600; }
        .progress-bar { width: 100%; height: 8px; background: #e9ecef; border-radius: 10px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 10px; }
        .progress-fill.active { background: #17a2b8; } .progress-fill.completed { background: #28a745; } .progress-fill.upcoming { background: #ffc107; } .progress-fill.cancelled { background: #dc3545; }
        .card-footer { padding: 15px 20px; border-top: 1px solid #f0f0f0; background: #fafafa; font-size: 12px; color: #888; display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 12px 12px; }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; display: inline-block; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; border-color: #eee; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; display: none; animation: slideIn 0.3s; max-width: 400px; }
        .floating-alert div { line-height: 1.6; }
        .floating-alert i { margin-top: 4px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 2002; }
        .close-lightbox:hover, .close-lightbox:focus { color: #bbb; text-decoration: none; cursor: pointer; }
        .lightbox-nav { cursor: pointer; position: absolute; top: 50%; width: auto; padding: 16px; margin-top: -50px; color: white; font-weight: bold; font-size: 30px; transition: 0.6s ease; border-radius: 0 3px 3px 0; user-select: none; z-index: 2001; background-color: rgba(0,0,0,0.3); }
        .lightbox-nav:hover { background-color: rgba(255,255,255,0.2); }
        .lightbox-prev { left: 0; border-radius: 0 3px 3px 0; }
        .lightbox-next { right: 0; border-radius: 3px 0 0 3px; }

        .section-separator { border-top: 1px dashed #ddd; margin: 25px 0; position: relative; }
        .section-separator span { position: absolute; top: -12px; left: 0; background: #fff; padding-right: 10px; font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; }

        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: repeat(2, 1fr); }
            .case-grid { grid-template-columns: 1fr; }
            .filter-search-bar { flex-direction: column; align-items: stretch; }
            .pagination-container { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="floating-alert floating-alert-success" id="floatingSuccess"><i class="fas fa-check-circle"></i><div id="msgSuccess"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div></div>
    <div class="floating-alert floating-alert-danger" id="floatingError"><i class="fas fa-exclamation-circle"></i><div id="msgError"><?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?></div></div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>In-Person Activity Management</h1>
                <p>Manage all physical charity activities and events.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card"><div class="stat-info"><h3>TOTAL ACTIVITIES</h3><h2><?php echo $totalActivities; ?></h2><p class="text-success">All Time</p></div><div class="stat-icon"><i class="fas fa-calendar-alt"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3>ACTIVE</h3><h2><?php echo $activeActivities; ?></h2><p class="text-success">Ongoing</p></div><div class="stat-icon"><i class="fas fa-running"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3>COMPLETED</h3><h2><?php echo $completedActivities; ?></h2><p class="text-success">Finished</p></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3>TOTAL RAISED</h3><h2>RM <?php echo number_format($totalDonations, 2); ?></h2><p class="text-success">From Activities</p></div><div class="stat-icon"><i class="fas fa-donate"></i></div></div>
            </div>

            <div class="management-content">
                <div class="section-header">
                    <h2>In-Person Activity List</h2>
                    <div class="action-buttons">
                        <a href="admin_activity_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Activity</a>
                        <a href="<?php echo $exportUrl; ?>" class="btn btn-success" target="_blank"><i class="fas fa-download"></i> Export Data</a>
                    </div>
                </div>

                <form method="GET" action="activity_management.php" class="filter-search-bar">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="status" <?php echo ($filterType == 'status') ? 'selected' : ''; ?>>Status</option>
                            <option value="branch" <?php echo ($filterType == 'branch') ? 'selected' : ''; ?>>Branch ID</option>
                            <option value="year" <?php echo ($filterType == 'year') ? 'selected' : ''; ?>>Year</option>
                            <option value="phone" <?php echo ($filterType == 'phone') ? 'selected' : ''; ?>>Phone Prefix</option>
                            <option value="city" <?php echo ($filterType == 'city') ? 'selected' : ''; ?>>City</option>
                            <option value="capacity" <?php echo ($filterType == 'capacity') ? 'selected' : ''; ?>>Capacity</option>
                            <option value="branch_sort" <?php echo ($filterType == 'branch_sort') ? 'selected' : ''; ?>>Branch Name Sort</option>
                            <option value="date" <?php echo ($filterType == 'date') ? 'selected' : ''; ?>>Creation Date</option>
                            <option value="end_date" <?php echo ($filterType == 'end_date') ? 'selected' : ''; ?>>End Date</option>
                        </select>
                    </div>
                    <div id="filter_status_container" class="secondary-filter"><select name="filter_val_status" class="filter-select"><option value="">Select Status...</option><option value="Active" <?php echo ($filterValue == 'Active') ? 'selected' : ''; ?>>Active</option><option value="Upcoming" <?php echo ($filterValue == 'Upcoming') ? 'selected' : ''; ?>>Upcoming</option><option value="Completed" <?php echo ($filterValue == 'Completed') ? 'selected' : ''; ?>>Completed</option><option value="Cancelled" <?php echo ($filterValue == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option></select></div>
                    <div id="filter_branch_container" class="secondary-filter"><select name="filter_val_branch" class="filter-select"><option value="">Select Branch...</option><?php foreach($branches as $b): ?><option value="<?php echo $b['Branch_ID']; ?>" <?php echo ($filterValue == $b['Branch_ID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['Branch_Name']); ?></option><?php endforeach; ?></select></div>
                    <div id="filter_year_container" class="secondary-filter"><select name="filter_val_year" class="filter-select"><option value="">Select Year...</option><?php foreach($years as $yr): ?><option value="<?php echo $yr; ?>" <?php echo ($filterValue == $yr) ? 'selected' : ''; ?>><?php echo $yr; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_phone_container" class="secondary-filter"><select name="filter_val_phone" class="filter-select"><option value="">Select Prefix...</option><?php foreach($phonePrefixes as $pp): ?><option value="<?php echo $pp; ?>" <?php if($filterValue == $pp) echo 'selected'; ?>>+6<?php echo $pp; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_city_container" class="secondary-filter"><select name="filter_val_city" class="filter-select"><option value="">Select City...</option><?php foreach($cities as $c): ?><option value="<?php echo $c; ?>" <?php if($filterValue == $c) echo 'selected'; ?>><?php echo $c; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_capacity_container" class="secondary-filter"><select name="filter_val_capacity" class="filter-select"><option value="">Select Range...</option><option value="below_100" <?php if($filterValue == 'below_100') echo 'selected'; ?>>Below 100</option><option value="100_500" <?php if($filterValue == '100_500') echo 'selected'; ?>>100 - 500</option><option value="above_500" <?php if($filterValue == 'above_500') echo 'selected'; ?>>Above 500</option></select></div>
                    <div id="filter_branch_sort_container" class="secondary-filter"><select name="filter_val_bsort" class="filter-select"><option value="">Select Order...</option><option value="asc" <?php if($filterValue == 'asc') echo 'selected'; ?>>Branch Name (A-Z)</option><option value="desc" <?php if($filterValue == 'desc') echo 'selected'; ?>>Branch Name (Z-A)</option></select></div>
                    
                    <div id="filter_date_container" class="secondary-filter">
                        <select name="filter_val_date_day" class="filter-select" style="min-width: 80px;">
                            <option value="">Day</option>
                            <?php for($i=1; $i<=31; $i++) echo "<option value='$i' ".($filterDateDay==$i?'selected':'').">$i</option>"; ?>
                        </select>
                        <select name="filter_val_date_month" class="filter-select" style="min-width: 100px;">
                            <option value="">Month</option>
                            <?php 
                            $months = [1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'May', 6=>'Jun', 7=>'Jul', 8=>'Aug', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Dec'];
                            foreach($months as $k=>$v) echo "<option value='$k' ".($filterDateMonth==$k?'selected':'').">$v</option>"; 
                            ?>
                        </select>
                        <select name="filter_val_date_year" class="filter-select" style="min-width: 90px;">
                            <option value="">Year</option>
                            <?php 
                            $currYear = date('Y');
                            for($i=$currYear; $i>=2023; $i--) echo "<option value='$i' ".($filterDateYear==$i?'selected':'').">$i</option>"; 
                            ?>
                        </select>
                    </div>

                    <div id="filter_end_date_container" class="secondary-filter">
                        <select name="filter_val_end_date_day" class="filter-select" style="min-width: 80px;">
                            <option value="">Day</option>
                            <?php for($i=1; $i<=31; $i++) echo "<option value='$i' ".($filterEndDateDay==$i?'selected':'').">$i</option>"; ?>
                        </select>
                        <select name="filter_val_end_date_month" class="filter-select" style="min-width: 100px;">
                            <option value="">Month</option>
                            <?php 
                            foreach($months as $k=>$v) echo "<option value='$k' ".($filterEndDateMonth==$k?'selected':'').">$v</option>"; 
                            ?>
                        </select>
                        <select name="filter_val_end_date_year" class="filter-select" style="min-width: 90px;">
                            <option value="">Year</option>
                            <?php 
                            for($i=$currYear; $i>=2023; $i--) echo "<option value='$i' ".($filterEndDateYear==$i?'selected':'').">$i</option>"; 
                            ?>
                        </select>
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search activity, description, city..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($searchTerm) || !empty($filterType)): ?><a href="activity_management.php" class="btn btn-danger" style="background-color: #dc3545; padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a><?php endif; ?>
                </form>

                <div class="case-grid">
                    <?php if (count($activities) > 0): ?>
                        <?php foreach($activities as $activity): 
                            $percent = ($activity['Activity_TargetAmount'] > 0) ? min(100, ($activity['Activity_GetAmount'] / $activity['Activity_TargetAmount']) * 100) : 0;
                            $status = $activity['Activity_Status']; $statusClass = 'status-active'; $progressClass = 'active';
                            if ($status == 'Completed') { $statusClass = 'status-completed'; $progressClass = 'completed'; }
                            if ($status == 'Cancelled') { $statusClass = 'status-cancelled'; $progressClass = 'cancelled'; }
                            if ($status == 'Upcoming') { $statusClass = 'status-upcoming'; $progressClass = 'upcoming'; }
                            
                            $jsonImgs = json_decode($activity['Activity_Images'], true);
                            $hasImgs = (!empty($jsonImgs) && is_array($jsonImgs));
                            if($hasImgs) { $allActivityImagesMap[$activity['Activity_ID']] = $jsonImgs; }

                            $startDateStr = date('d M Y', strtotime($activity['Activity_StartDate']));
                            $endDateStr = date('d M Y', strtotime($activity['Activity_EndDate']));
                            $dateDisplay = $startDateStr . ' - ' . $endDateStr;
                        ?>
                        <div class="case-card" id="card-<?php echo $activity['Activity_ID']; ?>">
                            <div class="card-actions">
                                <div class="action-menu">
                                    <button class="menu-btn" onclick="toggleMenu(event, <?php echo $activity['Activity_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                    <div id="menu-<?php echo $activity['Activity_ID']; ?>" class="dropdown-content">
                                        <a href="admin_activity_details.php?id=<?php echo $activity['Activity_ID']; ?>" target="_blank"><i class="fas fa-eye"></i> View Details</a>

                                        <a href="admin_activity_edit.php?id=<?php echo $activity['Activity_ID']; ?>&mode=edit"><i class="fas fa-edit"></i> Edit Details</a>

                                        <?php if (!$isStaff): ?>
                                            <a href="activity_donation_history.php?activity_id=<?php echo $activity['Activity_ID']; ?>"><i class="fas fa-file-invoice-dollar"></i> View Donation History</a>
                                            
                                            <a href="activity_withdrawal_history.php?activity_id=<?php echo $activity['Activity_ID']; ?>"><i class="fas fa-money-bill-wave"></i> Withdrawal History</a>
                                        <?php endif; ?>
                                        
                                        <a href="javascript:confirmDeleteActivity(<?php echo $activity['Activity_ID']; ?>)" class="text-delete"><i class="fas fa-trash"></i> Delete</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-image">
                                <span class="card-status <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                <?php if ($hasImgs): ?>
                                    <?php foreach($jsonImgs as $k => $path): ?>
                                        <img src="<?php echo htmlspecialchars($path); ?>" 
                                             class="card-img <?php echo $k==0?'active':''; ?>"
                                             onclick="openLightbox(<?php echo $activity['Activity_ID']; ?>, <?php echo $k; ?>)"
                                             style="cursor: zoom-in;">
                                    <?php endforeach; ?>
                                    <?php if(count($jsonImgs) > 1): ?>
                                        <button class="carousel-btn prev-btn" onclick="moveCarousel(<?php echo $activity['Activity_ID']; ?>, -1)">&#10094;</button>
                                        <button class="carousel-btn next-btn" onclick="moveCarousel(<?php echo $activity['Activity_ID']; ?>, 1)">&#10095;</button>
                                        <span class="img-counter">1/<?php echo count($jsonImgs); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="card-placeholder"><i class="fas fa-image fa-3x" style="font-size: 30px;"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="card-title"><?php echo htmlspecialchars($activity['Activity_Name']); ?></div>
                                <div class="card-subtitle"><i class="fas fa-building"></i> <?php echo htmlspecialchars($activity['Branch_Name']); ?></div>
                                <div class="card-desc"><?php echo htmlspecialchars($activity['Activity_Description'] ?? ''); ?></div>
                                <div class="progress-container">
                                    <div class="progress-labels"><span>RM <?php echo number_format($activity['Activity_GetAmount'], 0); ?></span><span><?php echo number_format($percent, 0); ?>%</span><span>RM <?php echo number_format($activity['Activity_TargetAmount'], 0); ?></span></div>
                                    <div class="progress-bar"><div class="progress-fill <?php echo $progressClass; ?>" style="width: <?php echo $percent; ?>%"></div></div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <span><i class="far fa-calendar-alt"></i> <?php echo $dateDisplay; ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($activity['Activity_City']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">No activities found.</div>
                    <?php endif; ?>
                </div>
                
                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        $queryParams = []; if(!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if(!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if ($filterType == 'status' && !empty($filterValue)) $queryParams['filter_val_status'] = $filterValue;
                            if ($filterType == 'branch' && !empty($filterValue)) $queryParams['filter_val_branch'] = $filterValue;
                            if ($filterType == 'year' && !empty($filterValue)) $queryParams['filter_val_year'] = $filterValue;
                            if ($filterType == 'phone' && !empty($filterValue)) $queryParams['filter_val_phone'] = $filterValue;
                            if ($filterType == 'city' && !empty($filterValue)) $queryParams['filter_val_city'] = $filterValue;
                            if ($filterType == 'capacity' && !empty($filterValue)) $queryParams['filter_val_capacity'] = $filterValue;
                            if ($filterType == 'branch_sort' && !empty($filterValue)) $queryParams['filter_val_bsort'] = $filterValue;
                            // Add date params to pagination
                            if ($filterType == 'date') {
                                if(!empty($filterDateDay)) $queryParams['filter_val_date_day'] = $filterDateDay;
                                if(!empty($filterDateMonth)) $queryParams['filter_val_date_month'] = $filterDateMonth;
                                if(!empty($filterDateYear)) $queryParams['filter_val_date_year'] = $filterDateYear;
                            }
                            // Add end date params to pagination
                            if ($filterType == 'end_date') {
                                if(!empty($filterEndDateDay)) $queryParams['filter_val_end_date_day'] = $filterEndDateDay;
                                if(!empty($filterEndDateMonth)) $queryParams['filter_val_end_date_month'] = $filterEndDateMonth;
                                if(!empty($filterEndDateYear)) $queryParams['filter_val_end_date_year'] = $filterEndDateYear;
                            }
                        }
                        $search_query = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                        
                        if ($page > 1) echo "<a href='?page=".($page-1).$search_query."' class='pagination-btn'>Previous</a>";
                        else echo "<span class='pagination-btn disabled'>Previous</span>";

                        $start_window = max(1, $page - 1);
                        $end_window = min($total_pages, $page + 1);
                        if ($page == 1) $end_window = min($total_pages, 3);
                        if ($page == $total_pages) $start_window = max(1, $total_pages - 2);

                        for ($i = $start_window; $i <= $end_window; $i++) {
                            if ($i == $page) echo "<span class='pagination-btn active'>$i</span>";
                            else echo "<a href='?page=$i$search_query' class='pagination-btn'>$i</a>";
                        }

                        if ($page < $total_pages) echo "<a href='?page=".($page+1).$search_query."' class='pagination-btn'>Next</a>";
                        else echo "<span class='pagination-btn disabled'>Next</span>";
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="imageLightbox" class="lightbox-modal">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <a class="lightbox-nav lightbox-prev" onclick="changeLightboxImage(-1)">&#10094;</a><img class="lightbox-content" id="lightboxImage"><a class="lightbox-nav lightbox-next" onclick="changeLightboxImage(1)">&#10095;</a>
    </div>

    <script>
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); if(el.tagName === 'DIV' && el.querySelector('select')) el.querySelector('select').disabled = true; });
            if (type === 'status') { document.getElementById('filter_status_container').classList.add('active'); document.getElementById('filter_status_container').querySelector('select').disabled = false; }
            else if (type === 'branch') { document.getElementById('filter_branch_container').classList.add('active'); document.getElementById('filter_branch_container').querySelector('select').disabled = false; }
            else if (type === 'year') { document.getElementById('filter_year_container').classList.add('active'); document.getElementById('filter_year_container').querySelector('select').disabled = false; }
            else if (type === 'phone') { document.getElementById('filter_phone_container').classList.add('active'); document.getElementById('filter_phone_container').querySelector('select').disabled = false; }
            else if (type === 'city') { document.getElementById('filter_city_container').classList.add('active'); document.getElementById('filter_city_container').querySelector('select').disabled = false; }
            else if (type === 'capacity') { document.getElementById('filter_capacity_container').classList.add('active'); document.getElementById('filter_capacity_container').querySelector('select').disabled = false; }
            else if (type === 'branch_sort') { document.getElementById('filter_branch_sort_container').classList.add('active'); document.getElementById('filter_branch_sort_container').querySelector('select').disabled = false; }
            else if (type === 'date') { 
                document.getElementById('filter_date_container').classList.add('active'); 
                const dateSelects = document.getElementById('filter_date_container').querySelectorAll('select');
                dateSelects.forEach(s => s.disabled = false);
            }
            else if (type === 'end_date') { 
                document.getElementById('filter_end_date_container').classList.add('active'); 
                const endDateSelects = document.getElementById('filter_end_date_container').querySelectorAll('select');
                endDateSelects.forEach(s => s.disabled = false);
            }
        }

        function moveCarousel(cardId, direction) {
            const card = document.getElementById('card-' + cardId); const images = card.querySelectorAll('.card-img'); const counter = card.querySelector('.img-counter');
            let activeIndex = 0; images.forEach((img, index) => { if (img.classList.contains('active')) activeIndex = index; img.classList.remove('active'); });
            let newIndex = activeIndex + direction;
            if (newIndex >= images.length) newIndex = 0; if (newIndex < 0) newIndex = images.length - 1;
            images[newIndex].classList.add('active');
            if(counter) counter.innerText = (newIndex + 1) + '/' + images.length;
        }

        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); const m = document.getElementById('menu-' + id); if (m) m.style.display = 'block'; }
        
        function confirmDeleteActivity(id) { if (confirm('Delete this activity?')) window.location.href = 'activity_management.php?delete_activity_id=' + id; }

        const allActivityImages = <?php echo json_encode($allActivityImagesMap); ?>;
        let currentLightboxActivityId = null; let currentLightboxIndex = 0;

        function openLightbox(activityId, index) { if (!allActivityImages[activityId] || allActivityImages[activityId].length === 0) return; currentLightboxActivityId = activityId; currentLightboxIndex = index; updateLightboxImage(); document.getElementById('imageLightbox').style.display = "flex"; }
        function closeLightbox() { document.getElementById('imageLightbox').style.display = "none"; }
        function changeLightboxImage(n) { if (currentLightboxActivityId === null) return; const images = allActivityImages[currentLightboxActivityId]; currentLightboxIndex += n; if (currentLightboxIndex >= images.length) currentLightboxIndex = 0; else if (currentLightboxIndex < 0) currentLightboxIndex = images.length - 1; updateLightboxImage(); }
        function updateLightboxImage() { const images = allActivityImages[currentLightboxActivityId]; document.getElementById('lightboxImage').src = images[currentLightboxIndex]; const prevBtn = document.querySelector('.lightbox-prev'); const nextBtn = document.querySelector('.lightbox-next'); if (images.length <= 1) { prevBtn.style.display = 'none'; nextBtn.style.display = 'none'; } else { prevBtn.style.display = 'block'; nextBtn.style.display = 'block'; } }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            const s = document.getElementById('floatingSuccess'); const e = document.getElementById('floatingError');
            if (s && s.querySelector('#msgSuccess').innerText !== '') { s.style.display='flex'; setTimeout(() => s.style.display='none', 5000); }
            if (e && e.querySelector('#msgError').innerText !== '') { e.style.display='flex'; setTimeout(() => e.style.display='none', 5000); }
            window.addEventListener('click', function(event) {
                if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
                if (event.target.id == 'imageLightbox') closeLightbox();
            });
            document.addEventListener('keydown', function(event) {
                if (document.getElementById('imageLightbox').style.display === "flex") {
                    if (event.key === "Escape") closeLightbox();
                    if (event.key === "ArrowLeft") changeLightboxImage(-1);
                    if (event.key === "ArrowRight") changeLightboxImage(1);
                }
            });
        });
    </script>
</body>
</html>