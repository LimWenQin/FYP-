<?php
// activity_management.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// --- AJAX: FETCH DONATIONS FOR A SPECIFIC ACTIVITY ---
if (isset($_GET['action']) && $_GET['action'] == 'get_activity_donations' && isset($_GET['activity_id'])) {
    $activityId = intval($_GET['activity_id']);
    
    $sql = "SELECT o.Order_Created_At, o.Order_Amount, o.Order_TXN_Ref, d.Donor_Name, d.Donor_Email 
            FROM orders o 
            JOIN donor d ON o.Donor_ID = d.Donor_ID 
            WHERE o.Activity_ID = $activityId 
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

// --- EXPORT TO EXCEL HANDLER ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "activity_list_" . date('Ymd') . ".xls";
    
    $exportSql = "SELECT a.*, b.Branch_Name 
                  FROM activity a 
                  LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID
                  ORDER BY a.Activity_StartDate DESC";
    
    $exportResult = $conn->query($exportSql);
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST']; 
    $path = dirname($_SERVER['PHP_SELF']); 
    $baseUrl = rtrim($protocol . "://" . $host . $path, '/\\') . '/';

    echo '<table border="1">';
    echo '<tr>
            <th style="background-color:#f0f0f0;">Cover Image</th>
            <th style="background-color:#f0f0f0;">Activity Name</th>
            <th style="background-color:#f0f0f0;">Branch</th>
            <th style="background-color:#f0f0f0;">Status</th>
            <th style="background-color:#f0f0f0;">Start Date</th>
            <th style="background-color:#f0f0f0;">End Date</th>
            <th style="background-color:#f0f0f0;">Location (City, State)</th>
            <th style="background-color:#f0f0f0;">Target Amount (RM)</th>
            <th style="background-color:#f0f0f0;">Raised Amount (RM)</th>
            <th style="background-color:#f0f0f0;">Details</th>
          </tr>';
    
    if ($exportResult && $exportResult->num_rows > 0) {
        while($row = $exportResult->fetch_assoc()) {
            echo '<tr>';
            echo '<td style="text-align:center; vertical-align:middle; height:80px; width:80px;">';
            if (!empty($row['Activity_Picture']) && file_exists($row['Activity_Picture'])) {
                $fullImageUrl = $baseUrl . $row['Activity_Picture'];
                echo '<img src="' . $fullImageUrl . '" width="60" height="60" style="object-fit:cover; border-radius:5px;">';
            } else { echo 'No Image'; }
            echo '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Activity_Name']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Branch_Name']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Activity_Status']) . '</td>';
            echo '<td style="vertical-align:middle;">' . $row['Activity_StartDate'] . '</td>';
            echo '<td style="vertical-align:middle;">' . $row['Activity_EndDate'] . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Activity_City'] . ', ' . $row['Activity_State']) . '</td>';
            echo '<td style="vertical-align:middle;">' . number_format($row['Activity_TargetAmount'], 2) . '</td>';
            echo '<td style="vertical-align:middle;">' . number_format($row['Activity_GetAmount'], 2) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars(substr($row['Activity_Details'], 0, 100)) . '...</td>';
            echo '</tr>';
        }
    } else { echo '<tr><td colspan="10">No activities found</td></tr>'; }
    echo '</table>';
    exit();
}

// --- ADMIN INFO ---
$adminId = $_SESSION['admin_id'];
$adminSql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $adminId";
$adminResult = $conn->query($adminSql);

if ($adminResult && $adminResult->num_rows > 0) {
    $adminData = $adminResult->fetch_assoc();
    $adminName = $adminData['Admin_Name'];
    $adminPosition = $adminData['Admin_Role']; 
    $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
} else {
    $adminName = $_SESSION['admin_name'];
    $adminPosition = "Administrator";
    $adminProfilePicture = null;
}

// --- FILE UPLOAD HELPER ---
function handleImageUpload($file) {
    if (isset($file) && $file['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowedTypes)) {
            $uploadDir = 'uploads/activities/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'act_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) return $uploadPath;
        }
    }
    return null;
}

// --- ADD ACTIVITY LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_activity'])) {
    $activityName = mysqli_real_escape_string($conn, $_POST['activity_name']);
    $startDate = mysqli_real_escape_string($conn, $_POST['start_date']);
    $endDate = mysqli_real_escape_string($conn, $_POST['end_date']);
    $activityDetails = mysqli_real_escape_string($conn, $_POST['activity_details']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $branchId = mysqli_real_escape_string($conn, $_POST['branch_id']);
    
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $activityStatus = mysqli_real_escape_string($conn, $_POST['activity_status']);
    
    $activityPicture = null;
    if (isset($_FILES['activity_picture'])) {
        $uploadedPath = handleImageUpload($_FILES['activity_picture']);
        if ($uploadedPath) $activityPicture = $uploadedPath;
    }

    $activityGetAmount = 0.00;

    $sql = "INSERT INTO activity (Activity_Name, Activity_StartDate, Activity_EndDate, 
            Activity_Details, Activity_TargetAmount, Activity_Status, Activity_GetAmount,
            Activity_Address1, Activity_Address2, Activity_Address3, Activity_City, 
            Activity_State, Activity_PostalCode, Activity_Country, Branch_ID, Activity_Picture) 
            VALUES ('$activityName', '$startDate', '$endDate', 
            '$activityDetails', '$targetAmount', '$activityStatus', '$activityGetAmount',
            '$address1', '$address2', '$address3', '$city', '$state', 
            '$postalCode', '$country', '$branchId', '$activityPicture')";
    
    if ($conn->query($sql)) {
        header("Location: activity_management.php?success=" . urlencode("Activity added successfully!"));
    } else {
        header("Location: activity_management.php?error=" . urlencode("Error: " . $conn->error));
    }
    exit();
}

// --- UPDATE ACTIVITY LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_activity'])) {
    $activityId = mysqli_real_escape_string($conn, $_POST['activity_id']);
    $activityName = mysqli_real_escape_string($conn, $_POST['activity_name']);
    $startDate = mysqli_real_escape_string($conn, $_POST['start_date']);
    $endDate = mysqli_real_escape_string($conn, $_POST['end_date']);
    $activityDetails = mysqli_real_escape_string($conn, $_POST['activity_details']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $branchId = mysqli_real_escape_string($conn, $_POST['branch_id']);
    $activityStatus = mysqli_real_escape_string($conn, $_POST['activity_status']);
    
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);

    $imageSql = "";
    if (isset($_FILES['activity_picture']) && $_FILES['activity_picture']['error'] == 0) {
        $uploadedPath = handleImageUpload($_FILES['activity_picture']);
        if ($uploadedPath) {
            $oldImgQ = $conn->query("SELECT Activity_Picture FROM activity WHERE Activity_ID = $activityId");
            if($row = $oldImgQ->fetch_assoc()) {
                if(!empty($row['Activity_Picture']) && file_exists($row['Activity_Picture'])) unlink($row['Activity_Picture']);
            }
            $imageSql = ", Activity_Picture = '$uploadedPath'";
        }
    }

    $sql = "UPDATE activity SET 
            Activity_Name = '$activityName', 
            Activity_StartDate = '$startDate', 
            Activity_EndDate = '$endDate',
            Activity_Details = '$activityDetails',
            Activity_TargetAmount = '$targetAmount',
            Activity_Status = '$activityStatus',
            Activity_Address1 = '$address1',
            Activity_Address2 = '$address2',
            Activity_Address3 = '$address3',
            Activity_City = '$city',
            Activity_State = '$state',
            Activity_PostalCode = '$postalCode',
            Activity_Country = '$country',
            Branch_ID = '$branchId'
            $imageSql
            WHERE Activity_ID = $activityId";
    
    if ($conn->query($sql)) {
        header("Location: activity_management.php?success=" . urlencode("Activity updated successfully!"));
    } else {
        header("Location: activity_management.php?error=" . urlencode("Error: " . $conn->error));
    }
    exit();
}

// --- DELETE ACTIVITY LOGIC ---
if (isset($_GET['delete_activity_id'])) {
    $deleteId = $_GET['delete_activity_id'];
    
    $checkSql = "SELECT Activity_Picture FROM activity WHERE Activity_ID = $deleteId";
    $checkResult = $conn->query($checkSql);
    if($checkResult && $row = $checkResult->fetch_assoc()){
        if(!empty($row['Activity_Picture']) && file_exists($row['Activity_Picture'])){
            unlink($row['Activity_Picture']);
        }
    }

    $deleteSql = "DELETE FROM activity WHERE Activity_ID = $deleteId";
    
    if ($conn->query($deleteSql)) {
        header("Location: activity_management.php?success=" . urlencode("Activity deleted successfully!"));
    } else {
        header("Location: activity_management.php?error=" . urlencode("Error: " . $conn->error));
    }
    exit();
}

// --- PAGINATION SETTINGS ---
$results_per_page = 6; // Set to 6 as requested
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

// --- FILTER & SEARCH ---
$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereConditions[] = "(a.Activity_Name LIKE '%$searchTerm%' OR a.Activity_Details LIKE '%$searchTerm%' OR b.Branch_Name LIKE '%$searchTerm%')";
}

if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    if ($filterType == 'status' && !empty($_GET['filter_val_status'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_status']);
        $whereConditions[] = "a.Activity_Status = '$filterValue'";
    } elseif ($filterType == 'branch' && !empty($_GET['filter_val_branch'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_branch']);
        $whereConditions[] = "a.Branch_ID = '$filterValue'";
    } elseif ($filterType == 'year' && !empty($_GET['filter_val_year'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_year']);
        $whereConditions[] = "YEAR(a.Activity_StartDate) = '$filterValue'";
    }
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// --- PAGINATION: GET TOTAL COUNT ---
$count_sql = "SELECT COUNT(*) as total FROM activity a 
              LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID 
              $whereClause";
$count_result = $conn->query($count_sql);
$total_activities_count = 0;
if ($count_result && $row = $count_result->fetch_assoc()) {
    $total_activities_count = $row['total'];
}
$total_pages = ceil($total_activities_count / $results_per_page);

// --- FETCH DATA WITH LIMIT ---
$sql = "SELECT a.*, b.Branch_Name FROM activity a 
        LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID
        $whereClause
        ORDER BY a.Activity_StartDate DESC
        LIMIT $start_from, $results_per_page";

$result = $conn->query($sql);
$activities = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) $activities[] = $row;
}

// Stats for "Showing X to Y of Z results"
$start_record = ($total_activities_count > 0) ? $start_from + 1 : 0;
$end_record = min($page * $results_per_page, $total_activities_count);

// Fetch Branches for dropdown
$branches = [];
$branchResult = $conn->query("SELECT Branch_ID, Branch_Name FROM branch ORDER BY Branch_Name");
if ($branchResult && $branchResult->num_rows > 0) {
    while($row = $branchResult->fetch_assoc()) $branches[] = $row;
}

// --- STATS (Overall, not affected by pagination limit but maybe filters? usually global) ---
$totalActivities = $conn->query("SELECT COUNT(*) as c FROM activity")->fetch_assoc()['c'];
$activeActivities = $conn->query("SELECT COUNT(*) as c FROM activity WHERE Activity_Status = 'Active'")->fetch_assoc()['c'];
$completedActivities = $conn->query("SELECT COUNT(*) as c FROM activity WHERE Activity_Status = 'Completed'")->fetch_assoc()['c'];
$totalDonations = $conn->query("SELECT SUM(Activity_GetAmount) as s FROM activity")->fetch_assoc()['s'] ?: 0;

$years = range(date('Y'), 2023);
$malaysiaStates = ['Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'];
$exportParams = $_GET; $exportParams['action'] = 'export_excel'; $exportUrl = "?" . http_build_query($exportParams);

$conn->close();
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
        /* --- COPYING STYLES FROM admin_donor_page.php FOR CONSISTENCY --- */
        
        /* Stats Cards - Matches Donor Page */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; gap: 5px; margin: 0; }
        .text-success { color: var(--success); } .text-danger { color: var(--danger); }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        /* Specific Colors */
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(23, 162, 184, 0.2); color: var(--info); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }

        /* Main Container */
        .management-content { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; }
        
        /* Buttons - Matches Donor Page */
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; text-decoration: none; font-size: 13px; }
        .btn-primary { background: var(--primary); color: white; } 
        .btn-success { background: var(--success); color: white; } 
        .btn-danger { background: var(--danger); color: white; }

        /* Filter & Search - Matching 'donor-search' style */
        .filter-search-bar { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; min-width: 140px; cursor: pointer; }
        .filter-select:focus { border-color: var(--primary); }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; }
        .search-input:focus { border-color: var(--primary); }

        /* Case Grid (Activity Specific but keeping style clean) */
        .case-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; margin-top: 10px; }
        .case-card { background: white; border-radius: 12px; position: relative; display: flex; flex-direction: column; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; transition: transform 0.3s; }
        .case-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: #eee; }
        .card-image { height: 160px; background-color: #f9f9f9; position: relative; border-radius: 12px 12px 0 0; overflow: hidden; }
        .card-image img { width: 100%; height: 100%; object-fit: cover; }
        .card-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f9f9f9; color: #ccc; }
        
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

        /* Pagination - Matches Donor Page */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-info { font-size: 14px; color: #666; }
        .pagination-controls { display: flex; gap: 5px; align-items: center; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; cursor: pointer; }
        .pagination-btn:hover { background-color: #e2e6ea; border-color: #dae0e5; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; border-color: #eee; }

        /* Modal & Forms - Matching Donor Page styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--gray-light); }
        .modal-header h2 { font-size: 18px; font-weight: 600; margin: 0; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray); }
        .modal-body { padding: 20px; background-color: #fdfdfd; }

        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-textarea { min-height: 100px; resize: vertical; }
        .form-input:read-only, .form-textarea:read-only { background-color: #f8f9fa; color: #555; cursor: default; }

        /* Updated Form Guide Style - MODIFIED AS REQUESTED */
        .form-guide { 
            font-size: 12px; 
            color: #6c757d; 
            margin-top: 5px; 
            display: inline-block; /* Changed from block to inline-block to fit text length */
            font-style: italic; 
            background: #fbfbfb; 
            padding: 4px 8px; 
            border-radius: 4px; 
            border-left: 3px solid #ddd; 
        }
        .required { color: red; margin-left: 3px; font-weight: bold; }
        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }

        /* File Upload - Updated to match Donor Page style */
        .file-upload { text-align: center; margin-bottom: 20px; }
        .profile-picture-preview { width: 200px; height: 150px; border-radius: 10px; border: 4px solid #f8f9fa; margin: 0 auto 15px; background: #eee; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .profile-picture-preview img { width: 100%; height: 100%; object-fit: cover; }
        .file-upload-label { display: inline-block; padding: 8px 15px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 5px; cursor: pointer; font-size: 13px; transition: all 0.3s; }
        .file-upload-label:hover { border-color: var(--primary); background: #fff5f5; color: var(--primary); }
        .file-upload input[type="file"] { display: none; }
        .file-info { display: none; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; background: #f1f1f1; padding: 5px 10px; border-radius: 5px; }
        .file-info.active { display: inline-flex; }
        .file-remove { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 14px; padding: 0 5px; }

        .donation-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .donation-table th, .donation-table td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .donation-table th { background-color: #f8f9fa; font-weight: 600; color: #555; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }

        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: repeat(2, 1fr); }
            .case-grid { grid-template-columns: 1fr; }
            .filter-search-bar { flex-direction: column; align-items: stretch; }
            .form-row { flex-direction: column; gap: 0; }
            .pagination-container { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['success'])): ?>
        <div class="floating-alert floating-alert-success" id="floatingSuccess"><i class="fas fa-check-circle"></i><div><?php echo htmlspecialchars($_GET['success']); ?></div></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="floating-alert floating-alert-danger" id="floatingError"><i class="fas fa-exclamation-circle"></i><div><?php echo htmlspecialchars($_GET['error']); ?></div></div>
    <?php endif; ?>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>In-Person Activity Management</h1>
                <p>Manage all physical charity activities and events.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info"><h3>TOTAL ACTIVITIES</h3><h2><?php echo $totalActivities; ?></h2><p class="text-success">All Time</p></div>
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>ACTIVE</h3><h2><?php echo $activeActivities; ?></h2><p class="text-success">Ongoing</p></div>
                    <div class="stat-icon"><i class="fas fa-running"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>COMPLETED</h3><h2><?php echo $completedActivities; ?></h2><p class="text-success">Finished</p></div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>TOTAL RAISED</h3><h2>RM <?php echo number_format($totalDonations, 2); ?></h2><p class="text-success">From Activities</p></div>
                    <div class="stat-icon"><i class="fas fa-donate"></i></div>
                </div>
            </div>

            <div class="management-content">
                <div class="section-header">
                    <h2>In-Person Activity List</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="openAddActivityModal()"><i class="fas fa-plus"></i> Add Activity</button>
                        <a href="<?php echo $exportUrl; ?>" class="btn btn-success" target="_blank"><i class="fas fa-download"></i> Export Data</a>
                    </div>
                </div>

                <form method="GET" action="activity_management.php" class="filter-search-bar">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="status" <?php echo ($filterType == 'status') ? 'selected' : ''; ?>>Status</option>
                            <option value="branch" <?php echo ($filterType == 'branch') ? 'selected' : ''; ?>>Branch</option>
                            <option value="year" <?php echo ($filterType == 'year') ? 'selected' : ''; ?>>Year</option>
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

                    <div id="filter_branch_container" class="secondary-filter">
                        <select name="filter_val_branch" class="filter-select">
                            <option value="">Select Branch...</option>
                            <?php foreach($branches as $b): ?>
                                <option value="<?php echo $b['Branch_ID']; ?>" <?php echo ($filterValue == $b['Branch_ID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['Branch_Name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="filter_year_container" class="secondary-filter">
                        <select name="filter_val_year" class="filter-select">
                            <option value="">Select Year...</option>
                            <?php foreach($years as $yr): ?><option value="<?php echo $yr; ?>" <?php echo ($filterValue == $yr) ? 'selected' : ''; ?>><?php echo $yr; ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search activity or branch..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($searchTerm) || !empty($filterType)): ?>
                        <a href="activity_management.php" class="btn btn-danger" style="background-color: #dc3545; padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>

                <div class="case-grid">
                    <?php if (count($activities) > 0): ?>
                        <?php foreach($activities as $activity): 
                            $percent = 0;
                            if ($activity['Activity_TargetAmount'] > 0) $percent = min(100, ($activity['Activity_GetAmount'] / $activity['Activity_TargetAmount']) * 100);
                            $status = $activity['Activity_Status'];
                            $today = date('Y-m-d');
                            // Dynamic status logic for display (optional override)
                            if ($status == 'Active') {
                                if ($activity['Activity_StartDate'] > $today) $status = 'Upcoming';
                                elseif ($activity['Activity_EndDate'] < $today) $status = 'Completed';
                            }
                            $statusClass = 'status-active'; $progressClass = 'active';
                            if ($status == 'Completed') { $statusClass = 'status-completed'; $progressClass = 'completed'; }
                            if ($status == 'Cancelled') { $statusClass = 'status-cancelled'; $progressClass = 'cancelled'; }
                            if ($status == 'Upcoming') { $statusClass = 'status-upcoming'; $progressClass = 'upcoming'; }
                        ?>
                        <div class="case-card">
                            <div class="card-actions">
                                <div class="action-menu">
                                    <button class="menu-btn" onclick="toggleMenu(event, <?php echo $activity['Activity_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                    <div id="menu-<?php echo $activity['Activity_ID']; ?>" class="dropdown-content">
                                        <div onclick="openViewDonors(<?php echo $activity['Activity_ID']; ?>)"><i class="fas fa-file-invoice-dollar"></i> View Donation History</div>
                                        <div onclick='openViewDetails(<?php echo json_encode($activity, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="fas fa-eye"></i> View Details</div>
                                        <div onclick='editActivity(<?php echo json_encode($activity, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="fas fa-edit"></i> Edit Details</div>
                                        <a href="javascript:confirmDeleteActivity(<?php echo $activity['Activity_ID']; ?>)" class="text-delete"><i class="fas fa-trash"></i> Delete</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-image">
                                <span class="card-status <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                <?php if (!empty($activity['Activity_Picture']) && file_exists($activity['Activity_Picture'])): ?>
                                    <img src="<?php echo $activity['Activity_Picture']; ?>" alt="Activity">
                                <?php else: ?>
                                    <div class="card-placeholder"><i class="fas fa-image fa-3x" style="font-size: 30px;"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="card-title"><?php echo htmlspecialchars($activity['Activity_Name']); ?></div>
                                <div class="card-subtitle"><i class="fas fa-building"></i> <?php echo htmlspecialchars($activity['Branch_Name']); ?></div>
                                <div class="card-desc"><?php echo htmlspecialchars($activity['Activity_Details']); ?></div>
                                <div class="progress-container">
                                    <div class="progress-labels"><span>RM <?php echo number_format($activity['Activity_GetAmount'], 0); ?></span><span><?php echo number_format($percent, 0); ?>%</span><span>RM <?php echo number_format($activity['Activity_TargetAmount'], 0); ?></span></div>
                                    <div class="progress-bar"><div class="progress-fill <?php echo $progressClass; ?>" style="width: <?php echo $percent; ?>%"></div></div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <span><i class="far fa-calendar-alt"></i> <?php echo date('d M', strtotime($activity['Activity_StartDate'])); ?> - <?php echo date('d M Y', strtotime($activity['Activity_EndDate'])); ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($activity['Activity_City']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;"><i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 10px; display:block;"></i>No activities found matching your criteria.</div>
                    <?php endif; ?>
                </div>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_activities_count; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        $queryParams = [];
                        if (!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if (!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if ($filterType == 'status' && !empty($filterValue)) $queryParams['filter_val_status'] = $filterValue;
                            if ($filterType == 'branch' && !empty($filterValue)) $queryParams['filter_val_branch'] = $filterValue;
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

    <div class="modal" id="addActivityModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Activity</h2><button class="close-btn" onclick="closeAddActivityModal()">&times;</button></div>
            <div class="modal-body">
                <form id="addActivityForm" action="activity_management.php" method="POST" enctype="multipart/form-data" onsubmit="return validateActivityForm('add')">
                    <input type="hidden" name="add_activity" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Activity Cover Image</label>
                        <div class="profile-picture-preview" id="add-preview-container"><div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div></div>
                        <div class="file-upload">
                            <label for="add_activity_picture" class="file-upload-label"><i class="fas fa-upload"></i> Choose File</label>
                            <input type="file" id="add_activity_picture" name="activity_picture" accept="image/*" onchange="previewImage(this, 'add-preview-container', 'add-file-info', 'add-file-name')">
                            <div id="add-file-info" class="file-info"><span id="add-file-name" class="file-name"></span><button type="button" class="file-remove" onclick="removeImage('add_activity_picture', 'add-preview-container', 'add-file-info')"><i class="fas fa-times"></i></button></div>
                        </div>
                        <span class="form-guide" style="text-align: center;">Recommended size: 800x600px (JPG, PNG). Max 2MB.</span>
                    </div>

                    <div class="form-group"><label class="form-label">Activity Name <span class="required">*</span></label><input type="text" name="activity_name" class="form-input" required placeholder="e.g. Annual Charity Run"></div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Date <span class="required">*</span></label>
                            <input type="date" id="add_start_date" name="start_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date <span class="required">*</span></label>
                            <input type="date" id="add_end_date" name="end_date" class="form-input" required onchange="validateDates('add_start_date', 'add_end_date', 'add_date_error')">
                            <div id="add_date_error" class="error-message">End date must be after or equal to Start date.</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Branch <span class="required">*</span></label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">Select Branch</option>
                                <?php foreach($branches as $branch): ?><option value="<?php echo htmlspecialchars($branch['Branch_ID']); ?>"><?php echo htmlspecialchars($branch['Branch_Name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Target Amount (RM) <span class="required">*</span></label>
                            <input type="number" id="add_target_amount" name="target_amount" class="form-input" step="0.01" min="0" required placeholder="0.00" oninput="validateAmount('add_target_amount', 'add_amount_error')">
                            <div id="add_amount_error" class="error-message">Amount cannot be negative.</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select name="activity_status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>

                    <div class="form-group"><label class="form-label">Address Line 1 <span class="required">*</span></label><input type="text" name="address1" class="form-input" required placeholder="Street address"></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Address Line 2</label><input type="text" name="address2" class="form-input" placeholder="Apartment, unit, etc."></div>
                        <div class="form-group"><label class="form-label">Address Line 3</label><input type="text" name="address3" class="form-input" placeholder="Optional"></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code <span class="required">*</span></label>
                            <input type="text" id="add_postal_code" name="postal_code" class="form-input" required placeholder="e.g. 50000">
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <span class="required">*</span></label>
                            <input type="text" id="add_city" name="city" class="form-input" required placeholder="City">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State <span class="required">*</span></label>
                            <select id="add_state" name="state" class="form-select" required>
                                <option value="">Select State</option>
                                <?php foreach($malaysiaStates as $state): ?><option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option><?php endforeach; ?>
                            </select>
                            <span class="form-guide">Auto-detected from Postcode</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-input" value="Malaysia" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Details</label>
                        <textarea name="activity_details" class="form-textarea" placeholder="Enter full activity description..."></textarea>
                    </div>
                    
                    <div class="form-group"><button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-save"></i> Save Activity</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="editActivityModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Activity</h2><button class="close-btn" onclick="closeEditActivityModal()">&times;</button></div>
            <div class="modal-body">
                <form id="editActivityForm" action="activity_management.php" method="POST" enctype="multipart/form-data" onsubmit="return validateActivityForm('edit')">
                    <input type="hidden" id="edit_activity_id" name="activity_id">
                    <input type="hidden" name="update_activity" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Cover Image</label>
                        <div class="profile-picture-preview" id="edit-preview-container"><div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div></div>
                        <div class="file-upload">
                            <label for="edit_activity_picture" class="file-upload-label"><i class="fas fa-upload"></i> Change Image</label>
                            <input type="file" id="edit_activity_picture" name="activity_picture" accept="image/*" onchange="previewImage(this, 'edit-preview-container', 'edit-file-info', 'edit-file-name')">
                            <div id="edit-file-info" class="file-info"><span id="edit-file-name" class="file-name"></span><button type="button" class="file-remove" id="edit-file-remove-btn"><i class="fas fa-times"></i></button></div>
                        </div>
                    </div>

                    <div class="form-group"><label class="form-label">Activity Name <span class="required">*</span></label><input type="text" id="edit_activity_name" name="activity_name" class="form-input" required></div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Date <span class="required">*</span></label>
                            <input type="date" id="edit_start_date" name="start_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date <span class="required">*</span></label>
                            <input type="date" id="edit_end_date" name="end_date" class="form-input" required onchange="validateDates('edit_start_date', 'edit_end_date', 'edit_date_error')">
                            <div id="edit_date_error" class="error-message">End date must be after or equal to Start date.</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Branch <span class="required">*</span></label>
                            <select id="edit_branch_id" name="branch_id" class="form-select" required>
                                <?php foreach($branches as $branch): ?><option value="<?php echo htmlspecialchars($branch['Branch_ID']); ?>"><?php echo htmlspecialchars($branch['Branch_Name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Target Amount (RM) <span class="required">*</span></label>
                            <input type="number" id="edit_target_amount" name="target_amount" class="form-input" step="0.01" min="0" required oninput="validateAmount('edit_target_amount', 'edit_amount_error')">
                            <div id="edit_amount_error" class="error-message">Amount cannot be negative.</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select id="edit_activity_status" name="activity_status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group"><label class="form-label">Address Line 1 <span class="required">*</span></label><input type="text" id="edit_address1" name="address1" class="form-input" required></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Address Line 2</label><input type="text" id="edit_address2" name="address2" class="form-input"></div>
                        <div class="form-group"><label class="form-label">Address Line 3</label><input type="text" id="edit_address3" name="address3" class="form-input"></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code <span class="required">*</span></label>
                            <input type="text" id="edit_postal_code" name="postal_code" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <span class="required">*</span></label>
                            <input type="text" id="edit_city" name="city" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State <span class="required">*</span></label>
                            <select id="edit_state" name="state" class="form-select" required>
                                <?php foreach($malaysiaStates as $state): ?><option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Country</label><input type="text" id="edit_country" name="country" class="form-input" value="Malaysia" readonly></div>
                    </div>
                    
                    <div class="form-group"><label class="form-label">Details</label><textarea id="edit_activity_details" name="activity_details" class="form-textarea"></textarea></div>
                    <div class="form-group"><button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-save"></i> Update Activity</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="viewDonorsModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Activity Donation History</h2><button class="close-btn" onclick="document.getElementById('viewDonorsModal').style.display='none'">&times;</button></div>
            <div class="modal-body">
                <table class="donation-table">
                    <thead><tr><th>Date</th><th>Donor Name</th><th>Amount (RM)</th><th>Reference</th></tr></thead>
                    <tbody id="donorsTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal" id="viewDetailsModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Activity Details</h2><button class="close-btn" onclick="document.getElementById('viewDetailsModal').style.display='none'">&times;</button></div>
            <div class="modal-body">
                <div class="profile-picture-preview" id="view-preview-container" style="width:100%; height:200px; margin-bottom:20px;"></div>
                <div class="form-group"><label class="form-label">Activity Name</label><input type="text" id="view_name" class="form-input" readonly></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Start Date</label><input type="text" id="view_start" class="form-input" readonly></div>
                    <div class="form-group"><label class="form-label">End Date</label><input type="text" id="view_end" class="form-input" readonly></div>
                </div>
                <div class="form-group"><label class="form-label">Full Address</label><textarea id="view_address" class="form-textarea" readonly style="min-height:80px;"></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Target Amount (RM)</label><input type="text" id="view_target" class="form-input" readonly></div>
                    <div class="form-group"><label class="form-label">Raised Amount (RM)</label><input type="text" id="view_raised" class="form-input" readonly></div>
                </div>
                <div class="form-group"><label class="form-label">Details</label><textarea id="view_details" class="form-textarea" readonly></textarea></div>
            </div>
        </div>
    </div>

    <script>
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            if (type === 'status') { document.getElementById('filter_status_container').classList.add('active'); document.getElementById('filter_status_container').querySelector('select').disabled = false; }
            else if (type === 'branch') { document.getElementById('filter_branch_container').classList.add('active'); document.getElementById('filter_branch_container').querySelector('select').disabled = false; }
            else if (type === 'year') { document.getElementById('filter_year_container').classList.add('active'); document.getElementById('filter_year_container').querySelector('select').disabled = false; }
        }

        // --- VALIDATION FUNCTIONS (Guides & Conditions) ---
        function setupPostcodeState(postcodeId, stateSelectId) {
            const pcInput = document.getElementById(postcodeId); const stateSelect = document.getElementById(stateSelectId);
            if (!pcInput || !stateSelect) return;
            pcInput.addEventListener('input', function() {
                const val = this.value.replace(/\D/g, '');
                if (val.length >= 2) {
                    const prefix = parseInt(val.substring(0, 2));
                    let state = "";
                    if (prefix >= 1 && prefix <= 2) state = "Perlis"; else if (prefix >= 5 && prefix <= 9) state = "Kedah"; else if (prefix >= 10 && prefix <= 14) state = "Penang";
                    else if (prefix >= 15 && prefix <= 18) state = "Kelantan"; else if (prefix >= 20 && prefix <= 24) state = "Terengganu"; else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                    else if (prefix >= 30 && prefix <= 36) state = "Perak"; else if (prefix >= 40 && prefix <= 48) state = "Selangor"; else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                    else if (prefix >= 62 && prefix <= 62) state = "Putrajaya"; else if (prefix >= 63 && prefix <= 68) state = "Selangor"; else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                    else if (prefix >= 75 && prefix <= 78) state = "Melaka"; else if (prefix >= 79 && prefix <= 86) state = "Johor"; else if (prefix == 87) state = "Labuan";
                    else if (prefix >= 88 && prefix <= 91) state = "Sabah"; else if (prefix >= 93 && prefix <= 98) state = "Sarawak";
                    if (state) stateSelect.value = state;
                }
            });
        }

        function validateDates(startId, endId, errorId) {
            const start = document.getElementById(startId).value;
            const end = document.getElementById(endId).value;
            const errorDiv = document.getElementById(errorId);
            
            if (start && end) {
                if (new Date(end) < new Date(start)) {
                    errorDiv.style.display = 'block';
                    return false;
                }
            }
            errorDiv.style.display = 'none';
            return true;
        }

        function validateAmount(amountId, errorId) {
            const val = document.getElementById(amountId).value;
            const errorDiv = document.getElementById(errorId);
            if(val && parseFloat(val) < 0) {
                errorDiv.style.display = 'block';
                return false;
            }
            errorDiv.style.display = 'none';
            return true;
        }

        function validateActivityForm(prefix) { // prefix is 'add' or 'edit'
            const startId = prefix + '_start_date';
            const endId = prefix + '_end_date';
            const dateErrId = prefix + '_date_error';
            const amountId = prefix + '_target_amount';
            const amountErrId = prefix + '_amount_error';

            const validDates = validateDates(startId, endId, dateErrId);
            const validAmount = validateAmount(amountId, amountErrId);

            return validDates && validAmount;
        }

        // --- IMAGE PREVIEW ---
        function previewImage(input, containerId, infoId, nameId) {
            const container = document.getElementById(containerId); const info = document.getElementById(infoId); const nameSpan = document.getElementById(nameId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { container.innerHTML = `<img src="${e.target.result}" alt="Preview">`; if(info) { info.style.display = 'inline-flex'; nameSpan.textContent = input.files[0].name; } }
                reader.readAsDataURL(input.files[0]);
            }
        }
        function removeImage(inputId, containerId, infoId, originalSrc = null) {
            const input = document.getElementById(inputId); const container = document.getElementById(containerId); const info = document.getElementById(infoId);
            input.value = ''; if(info) info.style.display = 'none';
            if (originalSrc) { container.innerHTML = `<img src="${originalSrc}" alt="Preview">`; } else { container.innerHTML = '<div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>'; }
        }

        // --- MENU & MODAL LOGIC ---
        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); const m = document.getElementById('menu-' + id); if (m) m.style.display = 'block'; }
        function openAddActivityModal() { document.getElementById('addActivityModal').style.display = 'flex'; }
        function closeAddActivityModal() { document.getElementById('addActivityModal').style.display = 'none'; document.getElementById('addActivityForm').reset(); document.getElementById('add-preview-container').innerHTML = '<div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>'; document.getElementById('add-file-info').style.display = 'none'; document.getElementById('add_date_error').style.display='none'; document.getElementById('add_amount_error').style.display='none'; }
        function closeEditActivityModal() { document.getElementById('editActivityModal').style.display = 'none'; }
        
        function editActivity(obj) {
            document.getElementById('edit_activity_id').value = obj.Activity_ID;
            document.getElementById('edit_activity_name').value = obj.Activity_Name;
            document.getElementById('edit_start_date').value = obj.Activity_StartDate;
            document.getElementById('edit_end_date').value = obj.Activity_EndDate;
            document.getElementById('edit_activity_details').value = obj.Activity_Details;
            document.getElementById('edit_target_amount').value = obj.Activity_TargetAmount;
            document.getElementById('edit_branch_id').value = obj.Branch_ID;
            document.getElementById('edit_activity_status').value = obj.Activity_Status;
            document.getElementById('edit_address1').value = obj.Activity_Address1 || '';
            document.getElementById('edit_address2').value = obj.Activity_Address2 || '';
            document.getElementById('edit_address3').value = obj.Activity_Address3 || '';
            document.getElementById('edit_city').value = obj.Activity_City || '';
            document.getElementById('edit_state').value = obj.Activity_State || '';
            document.getElementById('edit_postal_code').value = obj.Activity_PostalCode || '';
            document.getElementById('edit_country').value = obj.Activity_Country || 'Malaysia';
            
            const previewContainer = document.getElementById('edit-preview-container');
            let originalSrc = null;
            if (obj.Activity_Picture) { originalSrc = obj.Activity_Picture; previewContainer.innerHTML = `<img src="${obj.Activity_Picture}" alt="Preview">`; } else { previewContainer.innerHTML = '<div style="color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>'; }
            document.getElementById('edit_activity_picture').value = '';
            document.getElementById('edit-file-info').style.display = 'none';
            document.getElementById('edit-file-remove-btn').onclick = function() { removeImage('edit_activity_picture', 'edit-preview-container', 'edit-file-info', originalSrc); };
            
            // Clear errors
            document.getElementById('edit_date_error').style.display='none'; 
            document.getElementById('edit_amount_error').style.display='none';
            document.getElementById('editActivityModal').style.display = 'flex';
        }

        function openViewDonors(activityId) {
            const modal = document.getElementById('viewDonorsModal'); const tbody = document.getElementById('donorsTableBody');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">Loading donations...</td></tr>';
            modal.style.display = 'flex';
            fetch(`activity_management.php?action=get_activity_donations&activity_id=${activityId}`).then(r => r.json()).then(data => {
                tbody.innerHTML = '';
                if (data.length > 0) data.forEach(row => { tbody.innerHTML += `<tr><td>${row.date}</td><td>${row.name}</td><td>${row.amount}</td><td style="color:#666; font-size:12px;">${row.ref}</td></tr>`; });
                else tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#888;">No donations found for this activity yet.</td></tr>';
            });
        }

        function openViewDetails(obj) {
            document.getElementById('view_name').value = obj.Activity_Name;
            document.getElementById('view_start').value = obj.Activity_StartDate;
            document.getElementById('view_end').value = obj.Activity_EndDate;
            document.getElementById('view_target').value = parseFloat(obj.Activity_TargetAmount).toLocaleString('en-MY', { minimumFractionDigits: 2 });
            document.getElementById('view_raised').value = parseFloat(obj.Activity_GetAmount).toLocaleString('en-MY', { minimumFractionDigits: 2 });
            document.getElementById('view_details').value = obj.Activity_Details;
            let fullAddress = obj.Activity_Address1; if(obj.Activity_Address2) fullAddress += ",\n" + obj.Activity_Address2; if(obj.Activity_Address3) fullAddress += ",\n" + obj.Activity_Address3; fullAddress += `,\n${obj.Activity_PostalCode} ${obj.Activity_City},\n${obj.Activity_State}, ${obj.Activity_Country}`;
            document.getElementById('view_address').value = fullAddress;
            const previewContainer = document.getElementById('view-preview-container');
            if (obj.Activity_Picture) previewContainer.innerHTML = `<img src="${obj.Activity_Picture}" alt="Preview" style="object-fit:cover; width:100%; height:100%; border-radius:10px;">`;
            else previewContainer.innerHTML = '<div style="display:flex; align-items:center; justify-content:center; height:100%; background:#f8f9fa; color:#ccc; font-size:40px;"><i class="fas fa-image"></i></div>';
            document.getElementById('viewDetailsModal').style.display = 'flex';
        }
        function confirmDeleteActivity(id) { if (confirm('Are you sure you want to delete this activity?')) window.location.href = 'activity_management.php?delete_activity_id=' + id; }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            setupPostcodeState('add_postal_code', 'add_state');
            setupPostcodeState('edit_postal_code', 'edit_state');
            const s = document.getElementById('floatingSuccess'); const e = document.getElementById('floatingError');
            if (s) setTimeout(() => s.style.display='none', 5000); if (e) setTimeout(() => e.style.display='none', 5000);
            window.addEventListener('click', function(event) {
                if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
                if (event.target.classList.contains('modal')) event.target.style.display = 'none';
            });
        });
    </script>
</body>
</html>
