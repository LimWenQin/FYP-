<?php
// special_case_management.php
session_start();

// --- Check Login ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

$isStaff = isset($_SESSION['staff_id']);
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- AUTOMATIC STATUS UPDATE LOGIC ---
$conn->query("UPDATE special_case SET Case_Status = 'Completed' WHERE (End_Date < CURDATE() OR Raised_Amount >= Target_Amount) AND Case_Status != 'Cancelled' AND Case_Status != 'Completed'");
$conn->query("UPDATE special_case SET Case_Status = 'Active' WHERE Start_Date <= CURDATE() AND End_Date >= CURDATE() AND Raised_Amount < Target_Amount AND Case_Status != 'Cancelled' AND Case_Status != 'Completed' AND Case_Status != 'Active'");
$conn->query("UPDATE special_case SET Case_Status = 'Upcoming' WHERE Start_Date > CURDATE() AND Case_Status != 'Cancelled' AND Case_Status != 'Completed' AND Case_Status != 'Upcoming'");

// --- DELETE ---
if (isset($_GET['delete_case_id'])) {
    $delId = intval($_GET['delete_case_id']);
    // Optional: Delete related images from folder if needed
    $conn->query("DELETE FROM special_case WHERE Case_ID = $delId");
    header("Location: special_case_management.php?success=" . urlencode("Deleted successfully!"));
    exit();
}

// --- DATA PREPARATION FOR FILTERS ---
$cities = [];
$cityQ = $conn->query("SELECT DISTINCT Case_City FROM special_case WHERE Case_City != '' ORDER BY Case_City ASC");
if($cityQ) while($c = $cityQ->fetch_assoc()) $cities[] = $c['Case_City'];

$categories = ['Medical','Disability Support','Emergency Relief','Elderly Care','Children Support','Other Cases'];
$phonePrefixes = ['010', '011', '012', '013', '014', '015', '016', '017', '018', '019'];

// --- FILTER & SEARCH ---
$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = [];
$orderClause = "ORDER BY CASE 
                WHEN Case_Status = 'Upcoming' THEN 1 
                WHEN Case_Status = 'Active' THEN 2 
                ELSE 3 END ASC, Created_At DESC";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereConditions[] = "(Case_Title LIKE '%$searchTerm%' OR Case_Description LIKE '%$searchTerm%')";
}

if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    if ($filterType == 'status' && !empty($_GET['filter_val_status'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_status']);
        $whereConditions[] = "Case_Status = '$filterValue'";
    } elseif ($filterType == 'category' && !empty($_GET['filter_val_category'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_category']);
        $whereConditions[] = "Case_Category = '$filterValue'";
    } elseif ($filterType == 'phone' && !empty($_GET['filter_val_phone'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_phone']);
        $whereConditions[] = "Contact_Number LIKE '%$filterValue%'";
    } elseif ($filterType == 'city' && !empty($_GET['filter_val_city'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_city']);
        $whereConditions[] = "Case_City = '$filterValue'";
    }
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// --- EXPORT TO EXCEL ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "special_cases_" . date('Ymd') . ".xls";
    $exportSql = "SELECT * FROM special_case $whereClause $orderClause";
    $exportResult = $conn->query($exportSql);
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr><th>Case ID</th><th>Title</th><th>Category</th><th>Description</th><th>Start Date</th><th>End Date</th><th>Target (RM)</th><th>Raised (RM)</th><th>Status</th><th>Bank Name</th><th>Bank Account</th></tr>';
    if ($exportResult && $exportResult->num_rows > 0) {
        while($row = $exportResult->fetch_assoc()) {
            echo '<tr>';
            echo '<td>'.$row['Case_ID'].'</td>';
            echo '<td>'.$row['Case_Title'].'</td>';
            echo '<td>'.$row['Case_Category'].'</td>';
            echo '<td>'.$row['Case_Description'].'</td>';
            echo '<td>'.$row['Start_Date'].'</td>';
            echo '<td>'.$row['End_Date'].'</td>';
            echo '<td>'.$row['Target_Amount'].'</td>';
            echo '<td>'.$row['Raised_Amount'].'</td>';
            echo '<td>'.$row['Case_Status'].'</td>';
            echo '<td>'.$row['Case_BankName'].'</td>';
            echo '<td>\''.$row['Case_BankAccount'].'</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    exit();
}

// --- PAGINATION ---
$results_per_page = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$count_sql = "SELECT COUNT(*) as total FROM special_case $whereClause";
$count_result = $conn->query($count_sql);
$total_records = ($count_result && $row = $count_result->fetch_assoc()) ? $row['total'] : 0;
$total_pages = ceil($total_records / $results_per_page);
if ($page > $total_pages && $total_pages > 0) { $page = $total_pages; $start_from = ($page - 1) * $results_per_page; }

$sql = "SELECT * FROM special_case $whereClause $orderClause LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$specialCases = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) $specialCases[] = $row;
}

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// --- STATS ---
$totalCases = $conn->query("SELECT COUNT(*) as c FROM special_case")->fetch_assoc()['c'];
$activeCases = $conn->query("SELECT COUNT(*) as c FROM special_case WHERE Case_Status = 'Active'")->fetch_assoc()['c'];
$completedCases = $conn->query("SELECT COUNT(*) as c FROM special_case WHERE Case_Status = 'Completed'")->fetch_assoc()['c'];
$totalRaisedLifetime = $conn->query("SELECT SUM(Raised_Amount) as s FROM special_case")->fetch_assoc()['s'] ?: 0;

$exportParams = $_GET;
$exportParams['action'] = 'export_excel';
if(isset($exportParams['page'])) unset($exportParams['page']);
$exportUrl = "?" . http_build_query($exportParams);

$allCaseImagesMap = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Special Case Management - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    
    <style>
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; animation: slideIn 0.3s; display: none; }
        .floating-alert div { line-height: 1.6; }
        .floating-alert i { margin-top: 4px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Stats & UI */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; gap: 5px; margin: 0; }
        .text-success { color: var(--success); }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(23, 162, 184, 0.2); color: var(--info); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }

        .management-content { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; }
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; text-decoration: none; font-size: 13px; color: white; }
        .btn-primary { background: var(--primary); } .btn-success { background: var(--success); } .btn-danger { background: var(--danger); }
        
        /* Filters */
        .filter-search-bar { margin-bottom: 25px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; flex-wrap: wrap; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select, .search-input { padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; }
        .search-input { flex: 1; min-width: 200px; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* Grid & Cards */
        .case-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; margin-top: 10px; margin-bottom: 30px; }
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
        .status-cancelled { background-color: #f8d7da; color: #842029; }
        .status-upcoming { background-color: #fff3cd; color: #664d03; }
        
        .card-actions { position: absolute; top: 10px; right: 10px; z-index: 50; }
        .menu-btn { background-color: rgba(255, 255, 255, 0.95); border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; color: #555; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.15); transition: all 0.2s; }
        .menu-btn:hover { background-color: white; color: var(--primary); transform: scale(1.1); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 25px rgba(0,0,0,0.2); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; text-align: left; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 13px; cursor: pointer; transition: background 0.2s; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: var(--primary); }
        .text-delete { color: var(--danger) !important; border-top: 1px solid #eee; }

        .card-body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .card-title { font-size: 16px; font-weight: 700; color: #333; margin-bottom: 2px; }
        .card-category { font-size: 11px; color: #777; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
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

        /* Lightbox CSS */
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 2002; }
        .close-lightbox:hover, .close-lightbox:focus { color: #bbb; text-decoration: none; cursor: pointer; }
        .lightbox-nav { cursor: pointer; position: absolute; top: 50%; width: auto; padding: 16px; margin-top: -50px; color: white; font-weight: bold; font-size: 30px; transition: 0.6s ease; border-radius: 0 3px 3px 0; user-select: none; z-index: 2001; background-color: rgba(0,0,0,0.3); }
        .lightbox-nav:hover { background-color: rgba(255,255,255,0.2); }
        .lightbox-prev { left: 0; border-radius: 0 3px 3px 0; }
        .lightbox-next { right: 0; border-radius: 3px 0 0 3px; }
    </style>
</head>
<body>
    <div class="floating-alert floating-alert-success" id="floatingSuccess"><i class="fas fa-check-circle"></i><div id="msgSuccess"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div></div>
    <div class="floating-alert floating-alert-danger" id="floatingError"><i class="fas fa-exclamation-circle"></i><div id="msgError"><?php echo isset($_GET['error']) ? $_GET['error'] : ''; ?></div></div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Special Case Management</h1>
                <p>Manage fundraising cases with full details like venue, organizer, and multiple photos.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info"><h3>TOTAL CASES</h3><h2><?php echo $totalCases; ?></h2><p class="text-success">All Time</p></div>
                    <div class="stat-icon"><i class="fas fa-list"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>ACTIVE</h3><h2><?php echo $activeCases; ?></h2><p class="text-success">Ongoing</p></div>
                    <div class="stat-icon"><i class="fas fa-fire"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>COMPLETED</h3><h2><?php echo $completedCases; ?></h2><p class="text-success">Finished</p></div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>TOTAL RAISED</h3><h2>RM <?php echo number_format($totalRaisedLifetime, 2); ?></h2><p class="text-success">From Cases</p></div>
                    <div class="stat-icon"><i class="fas fa-donate"></i></div>
                </div>
            </div>

            <div class="management-content">
                <div class="section-header">
                    <h2>Special Case List</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="window.location.href='special_case_add.php'">
                            <i class="fas fa-plus"></i> Add Special Case
                        </button>
                        <a href="<?php echo $exportUrl; ?>" class="btn btn-success" target="_blank">
                            <i class="fas fa-download"></i> Export Data
                        </a>
                    </div>
                </div>

                <form method="GET" action="special_case_management.php" class="filter-search-bar">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="status" <?php echo ($filterType == 'status') ? 'selected' : ''; ?>>Status</option>
                            <option value="category" <?php echo ($filterType == 'category') ? 'selected' : ''; ?>>Category</option>
                            <option value="phone" <?php echo ($filterType == 'phone') ? 'selected' : ''; ?>>Phone Prefix</option>
                            <option value="city" <?php echo ($filterType == 'city') ? 'selected' : ''; ?>>City</option>
                        </select>
                    </div>

                    <div id="filter_status_container" class="secondary-filter">
                        <select name="filter_val_status" class="filter-select">
                            <option value="">Select Status...</option>
                            <option value="Active" <?php echo ($filterValue == 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Upcoming" <?php echo ($filterValue == 'Upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="Completed" <?php echo ($filterValue == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo ($filterValue == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div id="filter_phone_container" class="secondary-filter">
                        <select name="filter_val_phone" class="filter-select">
                            <option value="">Select Prefix...</option>
                            <?php foreach($phonePrefixes as $pp): ?>
                                <option value="<?php echo $pp; ?>" <?php echo ($filterValue == $pp) ? 'selected' : ''; ?>>+6<?php echo $pp; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="filter_city_container" class="secondary-filter">
                        <select name="filter_val_city" class="filter-select">
                            <option value="">Select City...</option>
                            <?php foreach($cities as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($filterValue == $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="filter_category_container" class="secondary-filter">
                        <select name="filter_val_category" class="filter-select">
                            <option value="">Select Category...</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php echo ($filterValue == $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search by title..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($searchTerm) || !empty($filterType)): ?>
                        <a href="special_case_management.php" class="btn btn-danger"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>

                <div class="case-grid">
                    <?php if (count($specialCases) > 0): ?>
                        <?php foreach($specialCases as $case): 
                            $percent = ($case['Target_Amount'] > 0) ? ($case['Raised_Amount'] / $case['Target_Amount']) * 100 : 0;
                            $percent = min(100, $percent);
                            
                            $statusClass = 'status-active'; $progressClass = 'active';
                            if ($case['Case_Status'] == 'Completed') { $statusClass = 'status-completed'; $progressClass = 'completed'; }
                            elseif ($case['Case_Status'] == 'Cancelled') { $statusClass = 'status-cancelled'; $progressClass = 'cancelled'; }
                            elseif ($case['Case_Status'] == 'Upcoming') { $statusClass = 'status-upcoming'; $progressClass = 'upcoming'; }
                            
                            $jsonImgs = json_decode($case['Case_Images'], true);
                            if (!is_array($jsonImgs) && !empty($case['Case_Images'])) $jsonImgs = [$case['Case_Images']];
                            $hasImgs = (!empty($jsonImgs) && is_array($jsonImgs));
                            if($hasImgs) { $allCaseImagesMap[$case['Case_ID']] = $jsonImgs; }

                            $startDateStr = date('d M Y', strtotime($case['Start_Date']));
                            $endDateStr = date('d M Y', strtotime($case['End_Date']));
                            $dateDisplay = $startDateStr . ' - ' . $endDateStr;
                        ?>
                        <div class="case-card" id="card-<?php echo $case['Case_ID']; ?>">
                            <div class="card-actions">
                                <div class="action-menu">
                                    <button class="menu-btn" onclick="toggleMenu(event, <?php echo $case['Case_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                    <div id="menu-<?php echo $case['Case_ID']; ?>" class="dropdown-content">
                                        
                                        <?php if (!$isStaff): ?>
                                            <a href="special_case_donation_history.php?case_id=<?php echo $case['Case_ID']; ?>" target="_blank"><i class="fas fa-file-invoice-dollar"></i> View Donor Payment History</a>
                                            <a href="special_case_withdrawal_history.php?case_id=<?php echo $case['Case_ID']; ?>" target="_blank"><i class="fas fa-money-bill-wave"></i> Withdrawal History</a>
                                        <?php endif; ?>
                                        
                                        <div onclick="goToDetailsPage(<?php echo $case['Case_ID']; ?>)"><i class="fas fa-eye"></i> View Full Details</div>
                                        <div onclick="window.location.href='special_case_edit.php?id=<?php echo $case['Case_ID']; ?>'"><i class="fas fa-edit"></i> Edit Details</div>
                                        <a href="javascript:confirmDeleteSpecialCase(<?php echo $case['Case_ID']; ?>)" class="text-delete"><i class="fas fa-trash"></i> Delete</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-image">
                                <span class="card-status <?php echo $statusClass; ?>"><?php echo $case['Case_Status']; ?></span>
                                <?php if ($hasImgs): ?>
                                    <?php foreach($jsonImgs as $k => $path): ?>
                                        <img src="<?php echo htmlspecialchars($path); ?>" 
                                             class="card-img <?php echo $k==0?'active':''; ?>" 
                                             onclick="openLightbox(<?php echo $case['Case_ID']; ?>, <?php echo $k; ?>)"
                                             style="cursor: zoom-in;">
                                    <?php endforeach; ?>
                                    <?php if(count($jsonImgs) > 1): ?>
                                        <button class="carousel-btn prev-btn" onclick="moveCarousel(<?php echo $case['Case_ID']; ?>, -1)">&#10094;</button>
                                        <button class="carousel-btn next-btn" onclick="moveCarousel(<?php echo $case['Case_ID']; ?>, 1)">&#10095;</button>
                                        <span class="img-counter">1/<?php echo count($jsonImgs); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="card-placeholder"><i class="fas fa-image fa-3x" style="font-size: 30px;"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="card-title"><?php echo htmlspecialchars($case['Case_Title']); ?></div>
                                <div class="card-category">
                                    <i class="fas fa-tags" style="margin-right:4px;"></i><?php echo htmlspecialchars($case['Case_Category']); ?>
                                </div>
                                <div class="card-desc"><?php echo htmlspecialchars($case['Case_Description']); ?></div>
                                <div class="progress-container">
                                    <div class="progress-labels">
                                        <span>RM <?php echo number_format($case['Raised_Amount'], 0); ?></span>
                                        <span><?php echo number_format($percent, 0); ?>%</span>
                                        <span>RM <?php echo number_format($case['Target_Amount'], 0); ?></span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill <?php echo $progressClass; ?>" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <span><i class="far fa-calendar-alt"></i> <?php echo $dateDisplay; ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($case['Case_City']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">
                            <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 10px; display:block;"></i>
                            No special cases found matching your criteria.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        $queryParams = [];
                        if (!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if (!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if ($filterType == 'status' && !empty($filterValue)) $queryParams['filter_val_status'] = $filterValue;
                            if ($filterType == 'phone' && !empty($filterValue)) $queryParams['filter_val_phone'] = $filterValue;
                            if ($filterType == 'city' && !empty($filterValue)) $queryParams['filter_val_city'] = $filterValue;
                            if ($filterType == 'category' && !empty($filterValue)) $queryParams['filter_val_category'] = $filterValue;
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
        <a class="lightbox-nav lightbox-prev" onclick="changeLightboxImage(-1)">&#10094;</a>
        <img class="lightbox-content" id="lightboxImage">
        <a class="lightbox-nav lightbox-next" onclick="changeLightboxImage(1)">&#10095;</a>
    </div>

    <script>
        function goToDetailsPage(id) {
            window.open('admin_special_case_details.php?id=' + id, '_blank');
        }

        // --- FILTER LOGIC (Restored) ---
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => el.classList.remove('active'));
            if (type === 'status') document.getElementById('filter_status_container').classList.add('active');
            else if (type === 'phone') document.getElementById('filter_phone_container').classList.add('active');
            else if (type === 'city') document.getElementById('filter_city_container').classList.add('active');
            else if (type === 'category') document.getElementById('filter_category_container').classList.add('active');
        }

        // --- CAROUSEL LOGIC (Restored) ---
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

        // --- INIT ---
        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            const s = document.getElementById('floatingSuccess');
            if (s && s.querySelector('#msgSuccess').innerText.trim() !== '') { 
                s.style.display='flex'; setTimeout(() => s.style.display='none', 5000); 
            }
            
            // Lightbox keys
            document.addEventListener('keydown', function(event) {
                if (document.getElementById('imageLightbox').style.display === "flex") {
                    if (event.key === "Escape") closeLightbox();
                    if (event.key === "ArrowLeft") changeLightboxImage(-1);
                    if (event.key === "ArrowRight") changeLightboxImage(1);
                }
            });
        });

        // --- MENU LOGIC ---
        function toggleMenu(e, id) {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
            const menu = document.getElementById('menu-' + id);
            if (menu) menu.style.display = 'block';
        }
        window.onclick = function(event) {
            if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) {
                document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
            }
            if (event.target.id == 'imageLightbox') closeLightbox();
        }

        function confirmDeleteSpecialCase(id) {
            if(confirm("Are you sure you want to delete this case?")) window.location.href = `special_case_management.php?delete_case_id=${id}`;
        }

        // --- LIGHTBOX JS LOGIC (Restored) ---
        const allCaseImages = <?php echo json_encode($allCaseImagesMap); ?>;
        let currentLightboxCaseId = null;
        let currentLightboxIndex = 0;

        function openLightbox(caseId, index) {
            if (!allCaseImages[caseId] || allCaseImages[caseId].length === 0) return;
            currentLightboxCaseId = caseId;
            currentLightboxIndex = index;
            updateLightboxImage();
            document.getElementById('imageLightbox').style.display = "flex";
        }

        function closeLightbox() { 
            document.getElementById('imageLightbox').style.display = "none"; 
        }

        function changeLightboxImage(n) {
            if (currentLightboxCaseId === null) return;
            const images = allCaseImages[currentLightboxCaseId];
            currentLightboxIndex += n;
            if (currentLightboxIndex >= images.length) currentLightboxIndex = 0;
            else if (currentLightboxIndex < 0) currentLightboxIndex = images.length - 1;
            updateLightboxImage();
        }

        function updateLightboxImage() {
            const images = allCaseImages[currentLightboxCaseId];
            const imgElement = document.getElementById('lightboxImage');
            imgElement.src = images[currentLightboxIndex];
            const prevBtn = document.querySelector('.lightbox-prev');
            const nextBtn = document.querySelector('.lightbox-next');
            if (images.length <= 1) { 
                prevBtn.style.display = 'none'; 
                nextBtn.style.display = 'none'; 
            } else { 
                prevBtn.style.display = 'block'; 
                nextBtn.style.display = 'block'; 
            }
        }
    </script>
</body>
</html>