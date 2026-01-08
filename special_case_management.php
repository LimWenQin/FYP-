<?php
// special_case_management.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// 设置时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- AJAX: FETCH DONATIONS FOR A SPECIFIC CASE ---
if (isset($_GET['action']) && $_GET['action'] == 'get_case_donations' && isset($_GET['case_id'])) {
    $caseId = intval($_GET['case_id']);
    
    $sql = "SELECT o.Order_Created_At, o.Order_Amount, o.Order_TXN_Ref, d.Donor_Name, d.Donor_Email 
            FROM orders o 
            JOIN donor d ON o.Donor_ID = d.Donor_ID 
            WHERE o.Case_ID = $caseId 
            AND o.Order_Status = 'Completed' 
            ORDER BY o.Order_Created_At DESC";
            
    $result = $conn->query($sql);
    $donations = [];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $donations[] = [
                'date' => date('d M Y, h:i A', strtotime($row['Order_Created_At'])),
                'name' => $row['Donor_Name'],
                'amount' => number_format($row['Order_Amount'], 2),
                'ref' => $row['Order_TXN_Ref']
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($donations);
    exit();
}

// --- 获取当前管理员信息 ---
$currentAdminId = $_SESSION['admin_id'];
$adminSql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
$adminResult = $conn->query($adminSql);

if ($adminResult && $adminResult->num_rows > 0) {
    $adminData = $adminResult->fetch_assoc();
    $adminName = $adminData['Admin_Name'];
    $adminPosition = $adminData['Admin_Role']; 
    $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
} else {
    $adminName = $_SESSION['admin_name'] ?? 'Admin';
    $adminPosition = "System Administrator";
    $adminProfilePicture = null;
}

// --- FILTER & SEARCH PREPARATION ---
$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereConditions[] = "(Case_Title LIKE '%$searchTerm%' OR Case_Description LIKE '%$searchTerm%')";
}

if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    if ($filterType == 'status' && isset($_GET['filter_val_status']) && !empty($_GET['filter_val_status'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_status']);
        $whereConditions[] = "Case_Status = '$filterValue'";
    } elseif ($filterType == 'year' && isset($_GET['filter_val_year']) && !empty($_GET['filter_val_year'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_year']);
        $whereConditions[] = "YEAR(Created_At) = '$filterValue'";
    }
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// --- EXPORT TO EXCEL ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "special_cases_" . date('Ymd') . ".xls";
    $exportSql = "SELECT * FROM special_case $whereClause ORDER BY Created_At DESC";
    $exportResult = $conn->query($exportSql);
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr><th>Case ID</th><th>Title</th><th>Description</th><th>Target (RM)</th><th>Raised (RM)</th><th>Status</th><th>Start Date</th><th>Created Date</th></tr>';
    if ($exportResult && $exportResult->num_rows > 0) {
        while($row = $exportResult->fetch_assoc()) {
            echo '<tr><td>'.$row['Case_ID'].'</td><td>'.$row['Case_Title'].'</td><td>'.$row['Case_Description'].'</td><td>'.$row['Target_Amount'].'</td><td>'.$row['Raised_Amount'].'</td><td>'.$row['Case_Status'].'</td><td>'.$row['Start_Date'].'</td><td>'.$row['Created_At'].'</td></tr>';
        }
    }
    echo '</table>';
    exit();
}

// --- FILE UPLOAD HELPER ---
function handleImageUpload($file) {
    if (isset($file) && $file['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowedTypes)) {
            $uploadDir = 'uploads/cases/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'case_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) return $uploadPath;
        }
    }
    return null;
}

// --- ADD / UPDATE / DELETE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_special_case'])) {
    $caseTitle = mysqli_real_escape_string($conn, $_POST['case_title']);
    $caseDescription = mysqli_real_escape_string($conn, $_POST['case_description']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $caseStatus = mysqli_real_escape_string($conn, $_POST['case_status']); 
    $startDate = ($caseStatus == 'Upcoming' && !empty($_POST['start_date'])) ? "'".mysqli_real_escape_string($conn, $_POST['start_date'])."'" : "NULL";
    
    $caseImage = null;
    if (isset($_FILES['case_image'])) {
        $uploadedPath = handleImageUpload($_FILES['case_image']);
        if ($uploadedPath) $caseImage = $uploadedPath;
    }

    $cols = "Case_Title, Case_Description, Target_Amount, Case_Status";
    $vals = "'$caseTitle', '$caseDescription', '$targetAmount', '$caseStatus'";
    $checkImg = $conn->query("SHOW COLUMNS FROM `special_case` LIKE 'Case_Image'");
    if ($checkImg && $checkImg->num_rows > 0) { $cols .= ", Case_Image"; $vals .= ", '$caseImage'"; }
    $checkStart = $conn->query("SHOW COLUMNS FROM `special_case` LIKE 'Start_Date'");
    if ($checkStart && $checkStart->num_rows > 0) { $cols .= ", Start_Date"; $vals .= ", $startDate"; }

    if ($conn->query("INSERT INTO special_case ($cols) VALUES ($vals)")) header("Location: special_case_management.php?success=" . urlencode("Added successfully!"));
    else header("Location: special_case_management.php?error=" . urlencode("Error: " . $conn->error));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_special_case'])) {
    $caseId = mysqli_real_escape_string($conn, $_POST['case_id']);
    $caseTitle = mysqli_real_escape_string($conn, $_POST['case_title']);
    $caseDescription = mysqli_real_escape_string($conn, $_POST['case_description']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $caseStatus = mysqli_real_escape_string($conn, $_POST['case_status']);
    
    $extraSql = "";
    $checkStart = $conn->query("SHOW COLUMNS FROM `special_case` LIKE 'Start_Date'");
    if ($checkStart && $checkStart->num_rows > 0) {
        $extraSql .= ($caseStatus == 'Upcoming' && !empty($_POST['start_date'])) ? ", Start_Date = '".mysqli_real_escape_string($conn, $_POST['start_date'])."'" : ", Start_Date = NULL";
    }
    $checkCancel = $conn->query("SHOW COLUMNS FROM `special_case` LIKE 'Cancel_Reason'");
    if ($checkCancel && $checkCancel->num_rows > 0) {
        $extraSql .= ($caseStatus == 'Cancelled' && !empty($_POST['cancel_reason'])) ? ", Cancel_Reason = '".mysqli_real_escape_string($conn, $_POST['cancel_reason'])."'" : ", Cancel_Reason = NULL";
    }
    $checkComplete = $conn->query("SHOW COLUMNS FROM `special_case` LIKE 'Completed_At'");
    if ($checkComplete && $checkComplete->num_rows > 0) {
        $prevData = $conn->query("SELECT Case_Status FROM special_case WHERE Case_ID = $caseId")->fetch_assoc();
        if ($caseStatus == 'Completed' && $prevData['Case_Status'] != 'Completed') $extraSql .= ", Completed_At = NOW()";
        elseif ($caseStatus != 'Completed') $extraSql .= ", Completed_At = NULL";
    }

    $imageSql = "";
    if (isset($_FILES['case_image']) && $_FILES['case_image']['error'] == 0) {
        $uploadedPath = handleImageUpload($_FILES['case_image']);
        if ($uploadedPath) $imageSql = ", Case_Image = '$uploadedPath'";
    }

    $sql = "UPDATE special_case SET Case_Title='$caseTitle', Case_Description='$caseDescription', Target_Amount='$targetAmount', Case_Status='$caseStatus' $extraSql $imageSql WHERE Case_ID=$caseId";
    if ($conn->query($sql)) header("Location: special_case_management.php?success=" . urlencode("Updated successfully!"));
    else header("Location: special_case_management.php?error=" . urlencode("Error: " . $conn->error));
    exit();
}

if (isset($_GET['delete_case_id'])) {
    $deleteId = $_GET['delete_case_id'];
    if ($conn->query("DELETE FROM special_case WHERE Case_ID = $deleteId")) header("Location: special_case_management.php?success=" . urlencode("Deleted successfully!"));
    else header("Location: special_case_management.php?error=" . urlencode("Error: " . $conn->error));
    exit();
}

// --- PAGINATION LOGIC ---
$results_per_page = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$count_sql = "SELECT COUNT(*) as total FROM special_case $whereClause";
$count_result = $conn->query($count_sql);
$total_records = 0;
if ($count_result && $count_result->num_rows > 0) {
    $row = $count_result->fetch_assoc();
    $total_records = $row['total'];
}

$total_pages = ceil($total_records / $results_per_page);

$sql = "SELECT * FROM special_case $whereClause ORDER BY Created_At DESC LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$specialCases = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) $specialCases[] = $row;
}

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($page * $results_per_page, $total_records);

// --- STATS LOGIC (UPDATED FOR 4 GRID SYSTEM) ---
$totalCases = $conn->query("SELECT COUNT(*) as c FROM special_case")->fetch_assoc()['c'];
$activeCases = $conn->query("SELECT COUNT(*) as c FROM special_case WHERE Case_Status = 'Active'")->fetch_assoc()['c'];
$completedCases = $conn->query("SELECT COUNT(*) as c FROM special_case WHERE Case_Status = 'Completed'")->fetch_assoc()['c'];
$totalRaisedLifetime = $conn->query("SELECT SUM(Raised_Amount) as s FROM special_case")->fetch_assoc()['s'] ?: 0;

$years = range(date('Y'), 2023); 
$exportParams = $_GET;
$exportParams['action'] = 'export_excel';
if(isset($exportParams['page'])) unset($exportParams['page']);
$exportUrl = "?" . http_build_query($exportParams);

// ⚠️ Removed $conn->close(); to allow sidebar to use connection
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
        /* --- STATS CARDS (COPIED FROM ACTIVITY MANAGEMENT) --- */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; gap: 5px; margin: 0; }
        .text-success { color: var(--success); } .text-danger { color: var(--danger); }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        /* Specific Colors matching Activity Page */
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(23, 162, 184, 0.2); color: var(--info); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }

        /* General UI Styles */
        .management-content { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; }
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; text-decoration: none; font-size: 13px; color: white; }
        .btn-primary { background: var(--primary); }
        .btn-success { background: var(--success); }
        .btn-danger { background: var(--danger); }
        
        /* Filter Styles */
        .filter-search-bar { margin-bottom: 25px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select, .search-input { padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; }
        .search-input { flex: 1; }
        .secondary-filter { display: none; }
        .secondary-filter.active { display: block; }

        /* Case Card Styles */
        .case-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; margin-top: 10px; margin-bottom: 30px; }
        .case-card { background: white; border-radius: 12px; position: relative; display: flex; flex-direction: column; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; transition: transform 0.3s, box-shadow 0.3s; }
        .case-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .card-image { height: 160px; background-color: #eee; position: relative; border-top-left-radius: 12px; border-top-right-radius: 12px; overflow: hidden; }
        .card-image img { width: 100%; height: 100%; object-fit: cover; }
        .card-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background-color: #f9f9f9; color: #ccc; font-size: 40px; }
        .card-status { position: absolute; top: 15px; left: 15px; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; z-index: 10; }
        .status-active { background-color: #cff4fc; color: #055160; }
        .status-completed { background-color: #d1e7dd; color: #0f5132; }
        .status-cancelled { background-color: #f8d7da; color: #842029; }
        .status-upcoming { background-color: #fff3cd; color: #664d03; }
        
        .card-actions { position: absolute; top: 10px; right: 10px; z-index: 50; }
        .menu-btn { background-color: rgba(255, 255, 255, 0.95); border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; color: #555; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.15); transition: all 0.2s; }
        .menu-btn:hover { background-color: white; color: var(--primary); transform: scale(1.1); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 25px rgba(0,0,0,0.2); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 13px; cursor: pointer; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: var(--primary); }
        .text-delete { color: var(--danger) !important; border-top: 1px solid #eee; }

        .card-body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .card-title { font-size: 16px; font-weight: 700; color: #333; margin-bottom: 10px; }
        .card-desc { font-size: 13px; color: #666; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 38px; }
        .progress-container { margin-top: auto; }
        .progress-labels { display: flex; justify-content: space-between; font-size: 12px; color: #555; margin-bottom: 5px; font-weight: 600; }
        .progress-bar { width: 100%; height: 8px; background: #e9ecef; border-radius: 10px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 10px; transition: width 0.5s ease; }
        .progress-fill.active { background: #007bff; }
        .progress-fill.completed { background: #28a745; }
        .progress-fill.cancelled { background: #dc3545; }
        .progress-fill.upcoming { background: #ffc107; }
        .card-footer { padding: 15px 20px; border-top: 1px solid #f0f0f0; background: #fafafa; font-size: 12px; color: #888; display: flex; justify-content: space-between; align-items: center; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; }

        /* Pagination Styles */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 15px 0; border-top: 1px solid var(--gray-light); }
        .pagination-info { font-size: 14px; color: #666; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .pagination-btn:hover { background-color: #e2e6ea; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; }

        /* Modal & Forms */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--gray-light); }
        .modal-header h2 { font-size: 18px; margin: 0; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray); }
        .modal-body { padding: 20px; }
        .donation-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .donation-table th, .donation-table td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .donation-table th { background-color: #f8f9fa; font-weight: 600; }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; }
        .form-textarea { min-height: 120px; resize: vertical; }
        .hidden-field { display: none; }
        .file-upload { text-align: center; margin-bottom: 20px; }
        .profile-picture-preview { width: 200px; height: 150px; border-radius: 10px; border: 2px solid #eee; margin: 0 auto 15px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .profile-picture-preview img { width: 100%; height: 100%; object-fit: cover; }
        .file-upload-label { display: inline-block; padding: 8px 15px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .file-upload input[type="file"] { display: none; }
        .file-info { display: none; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; background: #f1f1f1; padding: 5px 10px; border-radius: 5px; }
        .required { color: red; margin-left: 3px; font-weight: bold; }
        
        .form-guide { 
            font-size: 12px; 
            color: #6c757d; 
            margin-top: 5px; 
            display: inline-block;
            font-style: italic; 
            background: #fbfbfb; 
            padding: 4px 8px; 
            border-radius: 4px; 
            border-left: 3px solid #ddd;
            max-width: 100%; 
        }

        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }
        
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }

        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: repeat(2, 1fr); }
            .case-grid { grid-template-columns: 1fr; }
            .filter-search-bar { flex-direction: column; align-items: stretch; }
            .form-row { flex-direction: column; gap: 0; }
            .pagination-container { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['success'])): ?>
        <div class="floating-alert floating-alert-success" id="floatingSuccess">
            <i class="fas fa-check-circle"></i> <div><?php echo htmlspecialchars($_GET['success']); ?></div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="floating-alert floating-alert-danger" id="floatingError">
            <i class="fas fa-exclamation-circle"></i> <div><?php echo htmlspecialchars($_GET['error']); ?></div>
        </div>
    <?php endif; ?>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Special Case Management</h1>
                <p>Manage urgent fundraising cases and track real-time donations.</p>
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
                        <button class="btn btn-primary" onclick="openAddSpecialCaseModal()">
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
                            <option value="year" <?php echo ($filterType == 'year') ? 'selected' : ''; ?>>Created Year</option>
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

                    <div id="filter_year_container" class="secondary-filter">
                        <select name="filter_val_year" class="filter-select">
                            <option value="">Select Year...</option>
                            <?php foreach($years as $yr): ?>
                                <option value="<?php echo $yr; ?>" <?php echo ($filterValue == $yr) ? 'selected' : ''; ?>><?php echo $yr; ?></option>
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
                        ?>
                        <div class="case-card">
                            <div class="card-actions">
                                <div class="action-menu">
                                    <button class="menu-btn" onclick="toggleMenu(event, <?php echo $case['Case_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                    <div id="menu-<?php echo $case['Case_ID']; ?>" class="dropdown-content">
                                        <div onclick="openViewDonors(<?php echo $case['Case_ID']; ?>)"><i class="fas fa-hand-holding-heart"></i> View Donors</div>
                                        <div onclick="openViewDetails(<?php echo htmlspecialchars(json_encode($case)); ?>)"><i class="fas fa-eye"></i> View Details</div>
                                        <div onclick="editSpecialCase(<?php echo htmlspecialchars(json_encode($case)); ?>)"><i class="fas fa-edit"></i> Edit Details</div>
                                        <a href="javascript:confirmDeleteSpecialCase(<?php echo $case['Case_ID']; ?>)" class="text-delete"><i class="fas fa-trash"></i> Delete</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-image">
                                <span class="card-status <?php echo $statusClass; ?>"><?php echo $case['Case_Status']; ?></span>
                                <?php if (!empty($case['Case_Image']) && file_exists($case['Case_Image'])): ?>
                                    <img src="<?php echo $case['Case_Image']; ?>" alt="Case Image">
                                <?php else: ?>
                                    <div class="card-placeholder"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="card-title"><?php echo htmlspecialchars($case['Case_Title']); ?></div>
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
                                <?php if($case['Case_Status'] == 'Completed'): ?>
                                    <span><i class="fas fa-check-circle"></i> Done: <?php echo $case['Completed_At'] ? date('d M Y', strtotime($case['Completed_At'])) : '-'; ?></span>
                                <?php else: ?>
                                    <span><i class="far fa-calendar-alt"></i> Created: <?php echo date('d M Y', strtotime($case['Created_At'])); ?></span>
                                <?php endif; ?>
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
                            if ($filterType == 'year' && !empty($filterValue)) $queryParams['filter_val_year'] = $filterValue;
                        }
                        $search_query = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                        
                        if ($page > 1): 
                        ?>
                            <a href="?page=<?php echo $page - 1 . $search_query; ?>" class="pagination-btn">Previous</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Previous</span>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): 
                            if ($i == $page): ?>
                                <span class="pagination-btn active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i . $search_query; ?>" class="pagination-btn"><?php echo $i; ?></a>
                            <?php endif; 
                        endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1 . $search_query; ?>" class="pagination-btn">Next</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="viewDonorsModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Donation History</h2><button class="close-btn" onclick="document.getElementById('viewDonorsModal').style.display='none'">&times;</button></div>
            <div class="modal-body">
                <table class="donation-table"><thead><tr><th>Date</th><th>Donor Name</th><th>Amount (RM)</th><th>Reference</th></tr></thead><tbody id="donorsTableBody"></tbody></table>
            </div>
        </div>
    </div>

    <div class="modal" id="addSpecialCaseModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add Special Case</h2><button class="close-btn" onclick="closeAddSpecialCaseModal()">&times;</button></div>
            <div class="modal-body">
                <form id="addSpecialCaseForm" action="special_case_management.php" method="POST" enctype="multipart/form-data" onsubmit="return validateCaseForm('addSpecialCaseForm')">
                    <input type="hidden" name="add_special_case" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Case Image (Banner) <span class="required">*</span></label>
                        <div class="profile-picture-preview" id="add-preview-container"><div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div></div>
                        <div class="file-upload">
                            <label for="add_case_image" class="file-upload-label"><i class="fas fa-upload"></i> Choose File</label>
                            <input type="file" id="add_case_image" name="case_image" accept="image/jpeg,image/png,image/jpg" required onchange="previewImage(this, 'add-preview-container', 'add-file-info', 'add-file-name')">
                            <div id="add-file-info" class="file-info"><span id="add-file-name" class="file-name"></span><button type="button" class="file-remove" onclick="removeImage('add_case_image', 'add-preview-container', 'add-file-info')"><i class="fas fa-times"></i></button></div>
                        </div>
                        <div style="text-align: center;">
                            <span class="form-guide" style="font-size: 11px;">Only JPG or PNG files allowed. Max size 2MB recommended.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Case Title <span class="required">*</span></label>
                        <input type="text" name="case_title" class="form-input" required maxlength="100" placeholder="e.g., Emergency Heart Surgery Fund">
                        <span class="form-guide">Keep it short and catchy. Max 100 characters.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Target Amount (RM) <span class="required">*</span></label>
                            <input type="number" id="add_target_amount" name="target_amount" class="form-input" step="0.01" min="1000" required oninput="validateAmount('add_target_amount', 'addAmountError')">
                            <div id="addAmountError" class="error-message">Target amount must be at least RM 1,000.00.</div>
                            <span class="form-guide">Minimum target amount is RM 1,000.00.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Initial Status</label>
                            <select name="case_status" id="add_case_status" class="form-select" onchange="toggleAddDate()">
                                <option value="Active">Active</option>
                                <option value="Upcoming">Upcoming</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group hidden-field" id="add_date_container">
                        <label class="form-label">Start Date <span class="required">*</span></label>
                        <input type="date" name="start_date" id="add_start_date" class="form-input">
                        <span class="form-guide">When will this campaign effectively start?</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description / Story <span class="required">*</span></label>
                        <textarea name="case_description" id="add_case_description" class="form-textarea" required minlength="20" placeholder="Explain the background story, the need, and how funds will be used..." oninput="validateDescription('add_case_description', 'addDescError')"></textarea>
                        <div id="addDescError" class="error-message">Description must be at least 20 characters long.</div>
                        <span class="form-guide">Detailed explanation helps donors trust the cause (Min 20 chars).</span>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-save"></i> Save Case</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="editSpecialCaseModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Special Case</h2><button class="close-btn" onclick="closeEditSpecialCaseModal()">&times;</button></div>
            <div class="modal-body">
                <form id="editSpecialCaseForm" action="special_case_management.php" method="POST" enctype="multipart/form-data" onsubmit="return validateCaseForm('editSpecialCaseForm')">
                    <input type="hidden" id="edit_case_id" name="case_id"><input type="hidden" name="update_special_case" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Case Image</label>
                        <div class="profile-picture-preview" id="edit-preview-container"><div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div></div>
                        <div class="file-upload">
                            <label for="edit_case_image" class="file-upload-label"><i class="fas fa-upload"></i> Change Image</label>
                            <input type="file" id="edit_case_image" name="case_image" accept="image/jpeg,image/png,image/jpg" onchange="previewImage(this, 'edit-preview-container', 'edit-file-info', 'edit-file-name')">
                            <div id="edit-file-info" class="file-info"><span id="edit-file-name" class="file-name"></span><button type="button" class="file-remove" id="edit-file-remove-btn"><i class="fas fa-times"></i></button></div>
                        </div>
                        <div style="text-align: center;">
                            <span class="form-guide" style="font-size: 11px;">Leave empty to keep current image. JPG/PNG only.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Case Title <span class="required">*</span></label>
                        <input type="text" id="edit_case_title" name="case_title" class="form-input" required maxlength="100">
                        <span class="form-guide">Max 100 characters.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Target Amount (RM) <span class="required">*</span></label>
                            <input type="number" id="edit_special_target_amount" name="target_amount" class="form-input" step="0.01" min="1000" required oninput="validateAmount('edit_special_target_amount', 'editAmountError')">
                            <div id="editAmountError" class="error-message">Target amount must be at least RM 1,000.00.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <select id="edit_case_status" name="case_status" class="form-select" required onchange="toggleEditFields()">
                                <option value="Active">Active</option>
                                <option value="Upcoming">Upcoming</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group hidden-field" id="edit_date_container">
                        <label class="form-label">Start Date <span class="required">*</span></label>
                        <input type="date" name="start_date" id="edit_start_date" class="form-input">
                    </div>

                    <div class="form-group hidden-field" id="edit_reason_container">
                        <label class="form-label">Cancellation Reason <span class="required">*</span></label>
                        <textarea name="cancel_reason" id="edit_cancel_reason" class="form-textarea" placeholder="Why is this case being cancelled?"></textarea>
                        <span class="form-guide">This reason will be visible to admins.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description <span class="required">*</span></label>
                        <textarea id="edit_case_description" name="case_description" class="form-textarea" required minlength="20" oninput="validateDescription('edit_case_description', 'editDescError')"></textarea>
                        <div id="editDescError" class="error-message">Description must be at least 20 characters long.</div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-save"></i> Update Case</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="viewDetailsModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Case Details</h2><button class="close-btn" onclick="document.getElementById('viewDetailsModal').style.display='none'">&times;</button></div>
            <div class="modal-body">
                <div class="profile-picture-preview" id="view-preview-container" style="width:100%; height:200px; margin-bottom:20px;"></div>
                <div class="form-group"><label class="form-label">Case Title</label><input type="text" id="view_case_title" class="form-input" readonly></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Status</label><input type="text" id="view_status" class="form-input" readonly></div>
                    <div class="form-group"><label class="form-label" id="view_date_label">Created Date</label><input type="text" id="view_date" class="form-input" readonly></div>
                </div>
                <div class="form-group hidden-field" id="view_reason_container"><label class="form-label" style="color:var(--danger)">Cancellation Reason</label><textarea id="view_cancel_reason" class="form-textarea" readonly style="border-color:var(--danger); background:#fff5f5;"></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Target Amount (RM)</label><input type="text" id="view_target" class="form-input" readonly></div>
                    <div class="form-group"><label class="form-label">Raised Amount (RM)</label><input type="text" id="view_raised" class="form-input" readonly></div>
                </div>
                <div class="form-group"><label class="form-label">Description</label><textarea id="view_description" class="form-textarea" readonly></textarea></div>
            </div>
        </div>
    </div>

    <script>
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            if (type === 'status') { document.getElementById('filter_status_container').classList.add('active'); document.querySelector('#filter_status_container select').disabled = false; }
            else if (type === 'year') { document.getElementById('filter_year_container').classList.add('active'); document.querySelector('#filter_year_container select').disabled = false; }
        }

        function toggleAddDate() {
            const status = document.getElementById('add_case_status').value;
            const container = document.getElementById('add_date_container');
            const input = document.getElementById('add_start_date');
            if (status === 'Upcoming') { container.style.display = 'block'; input.required = true; } 
            else { container.style.display = 'none'; input.required = false; }
        }

        function toggleEditFields() {
            const status = document.getElementById('edit_case_status').value;
            const dateContainer = document.getElementById('edit_date_container');
            const dateInput = document.getElementById('edit_start_date');
            if (status === 'Upcoming') { dateContainer.style.display = 'block'; dateInput.required = true; } 
            else { dateContainer.style.display = 'none'; dateInput.required = false; }

            const reasonContainer = document.getElementById('edit_reason_container');
            const reasonInput = document.getElementById('edit_cancel_reason');
            if (status === 'Cancelled') { reasonContainer.style.display = 'block'; reasonInput.required = true; } 
            else { reasonContainer.style.display = 'none'; reasonInput.required = false; }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            const s = document.getElementById('floatingSuccess');
            const e = document.getElementById('floatingError');
            if (s) setTimeout(() => { s.style.opacity = '0'; setTimeout(() => s.style.display='none', 300); }, 5000);
            if (e) setTimeout(() => { e.style.opacity = '0'; setTimeout(() => e.style.display='none', 300); }, 8000);
            window.addEventListener('click', function(event) {
                if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
                if (event.target.classList.contains('modal')) event.target.style.display = 'none';
            });
        });

        // --- NEW VALIDATION FUNCTIONS ---
        function validateAmount(inputId, errorId) {
            const val = parseFloat(document.getElementById(inputId).value);
            const errorEl = document.getElementById(errorId);
            if (isNaN(val) || val < 1000) { 
                errorEl.style.display = 'block';
                return false;
            } else {
                errorEl.style.display = 'none';
                return true;
            }
        }

        function validateDescription(inputId, errorId) {
            const val = document.getElementById(inputId).value;
            const errorEl = document.getElementById(errorId);
            if (val.length < 20) {
                errorEl.style.display = 'block';
                return false;
            } else {
                errorEl.style.display = 'none';
                return true;
            }
        }

        function validateCaseForm(formId) {
            let isValid = true;
            
            // Map IDs based on form
            let amountId, amountError, descId, descError;
            if (formId === 'addSpecialCaseForm') {
                amountId = 'add_target_amount'; amountError = 'addAmountError';
                descId = 'add_case_description'; descError = 'addDescError';
            } else {
                amountId = 'edit_special_target_amount'; amountError = 'editAmountError';
                descId = 'edit_case_description'; descError = 'editDescError';
            }

            if (!validateAmount(amountId, amountError)) isValid = false;
            if (!validateDescription(descId, descError)) isValid = false;

            return isValid;
        }

        function toggleMenu(e, id) {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
            const menu = document.getElementById('menu-' + id);
            if (menu) menu.style.display = 'block';
        }

        function openViewDonors(caseId) {
            const modal = document.getElementById('viewDonorsModal');
            const tbody = document.getElementById('donorsTableBody');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">Loading donation history...</td></tr>';
            modal.style.display = 'flex';
            fetch(`special_case_management.php?action=get_case_donations&case_id=${caseId}`)
                .then(response => response.json())
                .then(data => {
                    tbody.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(row => {
                            tbody.innerHTML += `<tr><td>${row.date}</td><td>${row.name}</td><td>${row.amount}</td><td style="color:#666; font-size:12px;">${row.ref}</td></tr>`;
                        });
                    } else { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#888;">No donations found for this case yet.</td></tr>'; }
                })
                .catch(err => { console.error(err); tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">Failed to load data.</td></tr>'; });
        }

        function previewImage(input, containerId, infoId, nameId) {
            const container = document.getElementById(containerId);
            const info = document.getElementById(infoId);
            const nameSpan = document.getElementById(nameId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { container.innerHTML = `<img src="${e.target.result}" alt="Preview">`; if(info) { info.style.display = 'inline-flex'; nameSpan.textContent = input.files[0].name; } }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage(inputId, containerId, infoId, originalSrc = null) {
            const input = document.getElementById(inputId);
            const container = document.getElementById(containerId);
            const info = document.getElementById(infoId);
            input.value = '';
            if(info) info.style.display = 'none';
            if (originalSrc) container.innerHTML = `<img src="${originalSrc}" alt="Preview">`;
            else container.innerHTML = '<div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>';
        }

        function openAddSpecialCaseModal() { document.getElementById('addSpecialCaseModal').style.display = 'flex'; toggleAddDate(); }
        function closeAddSpecialCaseModal() { 
            document.getElementById('addSpecialCaseModal').style.display = 'none'; 
            document.getElementById('addSpecialCaseForm').reset();
            document.getElementById('add-preview-container').innerHTML = '<div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>';
            document.getElementById('add-file-info').style.display = 'none';
            // Hide errors
            document.getElementById('addAmountError').style.display = 'none';
            document.getElementById('addDescError').style.display = 'none';
            toggleAddDate();
        }

        function openEditSpecialCaseModal() { document.getElementById('editSpecialCaseModal').style.display = 'flex'; }
        function closeEditSpecialCaseModal() { document.getElementById('editSpecialCaseModal').style.display = 'none'; }

        function editSpecialCase(caseObj) {
            document.getElementById('edit_case_id').value = caseObj.Case_ID;
            document.getElementById('edit_case_title').value = caseObj.Case_Title;
            document.getElementById('edit_case_description').value = caseObj.Case_Description;
            document.getElementById('edit_special_target_amount').value = caseObj.Target_Amount;
            document.getElementById('edit_case_status').value = caseObj.Case_Status;
            
            if(caseObj.Start_Date) document.getElementById('edit_start_date').value = caseObj.Start_Date;
            if(caseObj.Cancel_Reason) document.getElementById('edit_cancel_reason').value = caseObj.Cancel_Reason;

            const previewContainer = document.getElementById('edit-preview-container');
            let originalSrc = null;
            if (caseObj.Case_Image) { originalSrc = caseObj.Case_Image; previewContainer.innerHTML = `<img src="${caseObj.Case_Image}" alt="Preview">`; } 
            else { previewContainer.innerHTML = '<div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>'; }

            document.getElementById('edit_case_image').value = '';
            document.getElementById('edit-file-info').style.display = 'none';
            document.getElementById('edit-file-remove-btn').onclick = function() { removeImage('edit_case_image', 'edit-preview-container', 'edit-file-info', originalSrc); };

            // Hide errors initially
            document.getElementById('editAmountError').style.display = 'none';
            document.getElementById('editDescError').style.display = 'none';

            toggleEditFields();
            openEditSpecialCaseModal();
        }

        function openViewDetails(caseObj) {
            document.getElementById('view_case_title').value = caseObj.Case_Title;
            document.getElementById('view_status').value = caseObj.Case_Status;
            const dateLabel = document.getElementById('view_date_label');
            const dateInput = document.getElementById('view_date');
            if (caseObj.Case_Status === 'Completed' && caseObj.Completed_At) { dateLabel.innerText = "Completion Date"; dateInput.value = caseObj.Completed_At; } 
            else if (caseObj.Case_Status === 'Upcoming' && caseObj.Start_Date) { dateLabel.innerText = "Start Date"; dateInput.value = caseObj.Start_Date; } 
            else { dateLabel.innerText = "Created Date"; dateInput.value = caseObj.Created_At; }

            const reasonContainer = document.getElementById('view_reason_container');
            if (caseObj.Case_Status === 'Cancelled' && caseObj.Cancel_Reason) { reasonContainer.style.display = 'block'; document.getElementById('view_cancel_reason').value = caseObj.Cancel_Reason; } 
            else { reasonContainer.style.display = 'none'; }

            document.getElementById('view_target').value = parseFloat(caseObj.Target_Amount).toLocaleString('en-MY', { minimumFractionDigits: 2 });
            document.getElementById('view_raised').value = parseFloat(caseObj.Raised_Amount).toLocaleString('en-MY', { minimumFractionDigits: 2 });
            document.getElementById('view_description').value = caseObj.Case_Description;
            
            const previewContainer = document.getElementById('view-preview-container');
            if (caseObj.Case_Image) previewContainer.innerHTML = `<img src="${caseObj.Case_Image}" alt="Preview" style="object-fit:cover; width:100%; height:100%; border-radius:10px;">`;
            else previewContainer.innerHTML = '<div style="display:flex; align-items:center; justify-content:center; height:100%; background:#f8f9fa; color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>';
            
            document.getElementById('viewDetailsModal').style.display = 'flex';
        }

        function confirmDeleteSpecialCase(id) {
            if (confirm('Are you sure you want to delete this case? This action cannot be undone.')) window.location.href = 'special_case_management.php?delete_case_id=' + id;
        }
    </script>
</body>
</html>