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
    
    // FETCH Order_ID as well for linking
    $sql = "SELECT o.Order_ID, o.Order_Created_At, o.Order_Amount, o.Order_TXN_Ref, d.Donor_Name, d.Donor_Email 
            FROM orders o 
            JOIN donor d ON o.Donor_ID = d.Donor_ID 
            WHERE o.Activity_ID = $activityId 
            AND (o.Order_Status = 'Completed' OR o.Order_Status = 'Success') 
            ORDER BY o.Order_Created_At DESC";
            
    $result = $conn->query($sql);
    $donations = [];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $donations[] = [
                'id' => $row['Order_ID'],
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

    echo '<table border="1">
          <tr><th>Activity Name</th><th>Branch</th><th>Status</th><th>Start Date</th><th>End Date</th><th>Location</th><th>Target (RM)</th><th>Raised (RM)</th><th>Details</th></tr>';
    
    if ($exportResult && $exportResult->num_rows > 0) {
        while($row = $exportResult->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['Activity_Name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Branch_Name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Activity_Status']) . '</td>';
            echo '<td>' . $row['Activity_StartDate'] . '</td>';
            echo '<td>' . $row['Activity_EndDate'] . '</td>';
            echo '<td>' . htmlspecialchars($row['Activity_City'] . ', ' . $row['Activity_State']) . '</td>';
            echo '<td>' . $row['Activity_TargetAmount'] . '</td>';
            echo '<td>' . $row['Activity_GetAmount'] . '</td>';
            echo '<td>' . htmlspecialchars(substr($row['Activity_Description'] ?? '', 0, 100)) . '...</td>';
            echo '</tr>';
        }
    }
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
    $adminName = "Admin";
    $adminPosition = "Administrator";
    $adminProfilePicture = null;
}

// --- MULTI FILE UPLOAD HELPER ---
function handleMultiImageUpload($files) {
    $uploadedPaths = [];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $uploadDir = 'uploads/activities/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] == 0 && in_array($files['type'][$i], $allowedTypes)) {
            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $fileName = 'act_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $fileName)) {
                $uploadedPaths[] = $uploadDir . $fileName;
            }
        }
    }
    return $uploadedPaths;
}

// --- DATE VALIDATION HELPER ---
function checkDateConstraints($start, $end) {
    $startDate = new DateTime($start);
    $endDate = new DateTime($end);
    $now = new DateTime();
    $oneYearLater = (clone $now)->modify('+1 year');

    if ($startDate > $oneYearLater) {
        return "Upcoming activity start date cannot be more than 1 year from today.";
    }
    $diff = $startDate->diff($endDate);
    if ($diff->days > 365) {
        return "Activity duration cannot exceed 1 year.";
    }
    return true;
}

// --- ADD ACTIVITY LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_activity'])) {
    $activityName = mysqli_real_escape_string($conn, $_POST['activity_name']);
    $startDate = mysqli_real_escape_string($conn, $_POST['start_date']);
    $endDate = mysqli_real_escape_string($conn, $_POST['end_date']);
    $activityDescription = mysqli_real_escape_string($conn, $_POST['activity_description']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $branchId = mysqli_real_escape_string($conn, $_POST['branch_id']);
    
    // Address Fields
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    
    $activityStatus = mysqli_real_escape_string($conn, $_POST['activity_status']);
    
    // Validation
    if(empty($activityDescription)) {
        header("Location: activity_management.php?error=" . urlencode("Description and conditions are required."));
        exit();
    }

    $dateCheck = checkDateConstraints($startDate, $endDate);
    if ($dateCheck !== true) {
        header("Location: activity_management.php?error=" . urlencode($dateCheck));
        exit();
    }

    // Image Upload
    $imageJson = "[]";
    if (isset($_FILES['activity_images'])) {
        $uploaded = handleMultiImageUpload($_FILES['activity_images']);
        // Limit to 10
        if(count($uploaded) > 10) $uploaded = array_slice($uploaded, 0, 10);
        if (!empty($uploaded)) {
            $imageJson = json_encode($uploaded);
        }
    }

    $activityGetAmount = 0.00;

    // Insert Query - Including all address fields
    $sql = "INSERT INTO activity (Activity_Name, Activity_StartDate, Activity_EndDate, 
            Activity_Description, Activity_TargetAmount, Activity_Status, Activity_GetAmount,
            Activity_Address1, Activity_Address2, Activity_Address3, Activity_City, 
            Activity_State, Activity_PostalCode, Activity_Country, Branch_ID, Activity_Images) 
            VALUES ('$activityName', '$startDate', '$endDate', 
            '$activityDescription', '$targetAmount', '$activityStatus', '$activityGetAmount',
            '$address1', '$address2', '$address3', '$city', '$state', 
            '$postalCode', '$country', '$branchId', '$imageJson')";
    
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
    $activityDescription = mysqli_real_escape_string($conn, $_POST['activity_description']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $branchId = mysqli_real_escape_string($conn, $_POST['branch_id']);
    $activityStatus = mysqli_real_escape_string($conn, $_POST['activity_status']);
    
    // Address Fields
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);

    // Validation
    if(empty($activityDescription)) {
        header("Location: activity_management.php?error=" . urlencode("Description cannot be empty."));
        exit();
    }

    $dateCheck = checkDateConstraints($startDate, $endDate);
    if ($dateCheck !== true) {
        header("Location: activity_management.php?error=" . urlencode($dateCheck));
        exit();
    }

    // Handle Image Merge
    $existingImages = json_decode($_POST['existing_images_json'] ?? "[]", true);
    if (!$existingImages) $existingImages = [];

    $newImages = [];
    if (isset($_FILES['activity_images'])) {
        $newImages = handleMultiImageUpload($_FILES['activity_images']);
    }

    $finalImages = array_merge($existingImages, $newImages);
    if(count($finalImages) > 10) {
        $finalImages = array_slice($finalImages, 0, 10);
    }
    
    $finalJson = mysqli_real_escape_string($conn, json_encode($finalImages));

    // Update Query - Including all address fields
    $sql = "UPDATE activity SET 
            Activity_Name = '$activityName', 
            Activity_StartDate = '$startDate', 
            Activity_EndDate = '$endDate',
            Activity_Description = '$activityDescription',
            Activity_TargetAmount = '$targetAmount',
            Activity_Status = '$activityStatus',
            Activity_Address1 = '$address1',
            Activity_Address2 = '$address2',
            Activity_Address3 = '$address3',
            Activity_City = '$city',
            Activity_State = '$state',
            Activity_PostalCode = '$postalCode',
            Activity_Country = '$country',
            Branch_ID = '$branchId',
            Activity_Images = '$finalJson'
            WHERE Activity_ID = $activityId";
    
    if ($conn->query($sql)) {
        header("Location: activity_management.php?success=" . urlencode("Activity updated successfully!"));
    } else {
        header("Location: activity_management.php?error=" . urlencode("Error: " . $conn->error));
    }
    exit();
}

// --- DELETE LOGIC ---
if (isset($_GET['delete_activity_id'])) {
    $deleteId = $_GET['delete_activity_id'];
    $deleteSql = "DELETE FROM activity WHERE Activity_ID = $deleteId";
    if ($conn->query($deleteSql)) {
        header("Location: activity_management.php?success=" . urlencode("Activity deleted successfully!"));
    } else {
        header("Location: activity_management.php?error=" . urlencode("Error: " . $conn->error));
    }
    exit();
}

// --- PAGINATION & FILTERS ---
$results_per_page = 6; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$searchTerm = ""; $filterType = ""; $filterValue = ""; $whereConditions = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereConditions[] = "(a.Activity_Name LIKE '%$searchTerm%' OR a.Activity_Description LIKE '%$searchTerm%' OR b.Branch_Name LIKE '%$searchTerm%')";
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

$whereClause = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

$count_sql = "SELECT COUNT(*) as total FROM activity a LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID $whereClause";
$count_result = $conn->query($count_sql);
$total_activities_count = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_activities_count / $results_per_page);

$sql = "SELECT a.*, b.Branch_Name FROM activity a LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID $whereClause ORDER BY a.Activity_StartDate DESC LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$activities = [];
if ($result && $result->num_rows > 0) { while($row = $result->fetch_assoc()) $activities[] = $row; }

$start_record = ($total_activities_count > 0) ? $start_from + 1 : 0;
$end_record = min($page * $results_per_page, $total_activities_count);

$branches = [];
$branchResult = $conn->query("SELECT Branch_ID, Branch_Name FROM branch ORDER BY Branch_Name");
if ($branchResult && $branchResult->num_rows > 0) { while($row = $branchResult->fetch_assoc()) $branches[] = $row; }

$totalActivities = $conn->query("SELECT COUNT(*) as c FROM activity")->fetch_assoc()['c'];
$activeActivities = $conn->query("SELECT COUNT(*) as c FROM activity WHERE Activity_Status = 'Active'")->fetch_assoc()['c'];
$completedActivities = $conn->query("SELECT COUNT(*) as c FROM activity WHERE Activity_Status = 'Completed'")->fetch_assoc()['c'];
$totalDonations = $conn->query("SELECT SUM(Activity_GetAmount) as s FROM activity")->fetch_assoc()['s'] ?: 0;

$years = range(date('Y'), 2023);
$malaysiaStates = ['Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'];
$exportParams = $_GET; $exportParams['action'] = 'export_excel'; $exportUrl = "?" . http_build_query($exportParams);

// --- COLLECT IMAGES FOR LIGHTBOX JS ---
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

        .filter-search-bar { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; min-width: 140px; cursor: pointer; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; }

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
        .pagination-info { font-size: 14px; color: #666; }
        .pagination-controls { display: flex; gap: 5px; align-items: center; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; cursor: pointer; }
        .pagination-btn:hover { background-color: #e2e6ea; border-color: #dae0e5; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; border-color: #eee; }

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

        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: inline-block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        .required { color: red; margin-left: 3px; font-weight: bold; }
        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }

        .donation-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .donation-table th, .donation-table td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .donation-table th { background-color: #f8f9fa; font-weight: 600; color: #555; }
        .donation-row:hover { background-color: #f9f9f9; cursor: pointer; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; display: none; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }

        /* --- NEW: Upload Styles similar to Branch Management --- */
        .upload-container { width: 100%; }
        .upload-box { border: 2px dashed #ccc; background: #fafafa; border-radius: 8px; padding: 30px 20px; text-align: center; cursor: pointer; transition: all 0.3s; position: relative; }
        .upload-box:hover { background: #fff5f5; border-color: var(--primary); }
        .upload-box i { font-size: 32px; color: #aaa; margin-bottom: 10px; display: block; }
        .upload-box p { margin: 0; font-size: 13px; color: #666; font-weight: 500; }
        .upload-box input[type="file"] { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; z-index: 10; }
        
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 12px; margin-top: 15px; }
        .preview-item { position: relative; height: 80px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: 1px solid #eee; }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .remove-img-btn { position: absolute; top: 4px; right: 4px; background: #ff4d4d; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2); z-index: 10; }
        .remove-img-btn:hover { background: #cc0000; transform: scale(1.1); }

        /* --- LIGHTBOX (GALLERY) CSS --- */
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
        
        .lightbox-nav {
            cursor: pointer;
            position: absolute;
            top: 50%;
            width: auto;
            padding: 16px;
            margin-top: -50px;
            color: white;
            font-weight: bold;
            font-size: 30px;
            transition: 0.6s ease;
            border-radius: 0 3px 3px 0;
            user-select: none;
            z-index: 2001;
            background-color: rgba(0,0,0,0.3);
        }
        .lightbox-nav:hover { background-color: rgba(255,255,255,0.2); }
        .lightbox-prev { left: 0; border-radius: 0 3px 3px 0; }
        .lightbox-next { right: 0; border-radius: 3px 0 0 3px; }

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
                    <div id="filter_status_container" class="secondary-filter"><select name="filter_val_status" class="filter-select"><option value="">Select Status...</option><option value="Active" <?php echo ($filterValue == 'Active') ? 'selected' : ''; ?>>Active</option><option value="Upcoming" <?php echo ($filterValue == 'Upcoming') ? 'selected' : ''; ?>>Upcoming</option><option value="Completed" <?php echo ($filterValue == 'Completed') ? 'selected' : ''; ?>>Completed</option><option value="Cancelled" <?php echo ($filterValue == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option></select></div>
                    <div id="filter_branch_container" class="secondary-filter"><select name="filter_val_branch" class="filter-select"><option value="">Select Branch...</option><?php foreach($branches as $b): ?><option value="<?php echo $b['Branch_ID']; ?>" <?php echo ($filterValue == $b['Branch_ID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['Branch_Name']); ?></option><?php endforeach; ?></select></div>
                    <div id="filter_year_container" class="secondary-filter"><select name="filter_val_year" class="filter-select"><option value="">Select Year...</option><?php foreach($years as $yr): ?><option value="<?php echo $yr; ?>" <?php echo ($filterValue == $yr) ? 'selected' : ''; ?>><?php echo $yr; ?></option><?php endforeach; ?></select></div>
                    <input type="text" name="search" class="search-input" placeholder="Search activity or branch..." value="<?php echo htmlspecialchars($searchTerm); ?>">
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
                            
                            // Collect images for lightbox
                            $hasImgs = (!empty($jsonImgs) && is_array($jsonImgs));
                            if($hasImgs) {
                                $allActivityImagesMap[$activity['Activity_ID']] = $jsonImgs;
                            }
                        ?>
                        <div class="case-card" id="card-<?php echo $activity['Activity_ID']; ?>">
                            <div class="card-actions">
                                <div class="action-menu">
                                    <button class="menu-btn" onclick="toggleMenu(event, <?php echo $activity['Activity_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                    <div id="menu-<?php echo $activity['Activity_ID']; ?>" class="dropdown-content">
                                        <div onclick="openViewDonors(<?php echo $activity['Activity_ID']; ?>)"><i class="fas fa-file-invoice-dollar"></i> View Donation History</div>
                                        <a href="admin_activity_details.php?id=<?php echo $activity['Activity_ID']; ?>" target="_blank"><i class="fas fa-eye"></i> View Details</a>
                                        <div onclick='editActivity(<?php echo json_encode($activity, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="fas fa-edit"></i> Edit Details</div>
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
                                <span><i class="far fa-calendar-alt"></i> <?php echo date('d M', strtotime($activity['Activity_StartDate'])); ?> - <?php echo date('d M Y', strtotime($activity['Activity_EndDate'])); ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($activity['Activity_City']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">No activities found.</div>
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
                        
                        if ($page > 1) echo "<a href='?page=".($page-1).$search_query."' class='pagination-btn'>Previous</a>"; else echo "<span class='pagination-btn disabled'>Previous</span>";
                        if ($page < $total_pages) echo "<a href='?page=".($page+1).$search_query."' class='pagination-btn'>Next</a>"; else echo "<span class='pagination-btn disabled'>Next</span>";
                        ?>
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
                        <label class="form-label">Activity Images (Max 10) <span class="required">*</span></label>
                        <div class="upload-container"><div class="upload-box"><i class="fas fa-cloud-upload-alt"></i><p>Click or Drag images here to upload</p><input type="file" id="add_activity_images" name="activity_images[]" multiple accept="image/*" onchange="handleFileSelect(event, 'add')" required></div><div class="preview-grid" id="add_preview_container"></div></div>
                        <span class="form-guide">Accepted formats: JPG, PNG. Multiple files allowed. Max 10 images.</span>
                    </div>
                    <div class="form-group"><label class="form-label">Activity Name <span class="required">*</span></label><input type="text" name="activity_name" class="form-input" required placeholder="e.g. Annual Charity Run"></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Start Date <span class="required">*</span></label><input type="date" id="add_start_date" name="start_date" class="form-input" required></div><div class="form-group"><label class="form-label">End Date <span class="required">*</span></label><input type="date" id="add_end_date" name="end_date" class="form-input" required></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Branch <span class="required">*</span></label><select name="branch_id" class="form-select" required><option value="">Select Branch</option><?php foreach($branches as $b) echo "<option value='{$b['Branch_ID']}'>{$b['Branch_Name']}</option>"; ?></select></div><div class="form-group"><label class="form-label">Target Amount (RM) <span class="required">*</span></label><input type="number" id="add_target_amount" name="target_amount" class="form-input" step="0.01" min="0" required placeholder="0.00" oninput="validateAmount('add_target_amount', 'add_amount_error')"><div id="add_amount_error" class="error-message">Amount cannot be negative.</div></div></div>
                    <div class="form-group"><label class="form-label">Status <span class="required">*</span></label><select name="activity_status" class="form-select" required><option value="Active">Active</option><option value="Upcoming">Upcoming</option><option value="Completed">Completed</option></select></div>
                    
                    <div class="form-group"><label class="form-label">Address 1 <span class="required">*</span></label><input type="text" name="address1" class="form-input" required></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Address 2</label><input type="text" name="address2" class="form-input"></div><div class="form-group"><label class="form-label">Address 3</label><input type="text" name="address3" class="form-input"></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Postcode <span class="required">*</span></label><input type="text" id="add_postal_code" name="postal_code" class="form-input" required></div><div class="form-group"><label class="form-label">City <span class="required">*</span></label><input type="text" id="add_city" name="city" class="form-input" required></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">State <span class="required">*</span></label><select id="add_state" name="state" class="form-select" required><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div><div class="form-group"><label class="form-label">Country</label><input type="text" name="country" class="form-input" value="Malaysia" readonly></div></div>
                    
                    <div class="form-group"><label class="form-label">Description & Conditions <span class="required">*</span></label><textarea name="activity_description" class="form-textarea" required placeholder="Enter full description and conditions..."></textarea><span class="form-guide">Detailed description and any specific conditions for this activity.</span></div>
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
                    <input type="hidden" id="existing_images_json" name="existing_images_json">
                    <div class="form-group"><label class="form-label">Activity Images</label><div class="upload-container"><div class="upload-box"><i class="fas fa-cloud-upload-alt"></i><p>Add more images</p><input type="file" id="edit_activity_images" name="activity_images[]" multiple accept="image/*" onchange="handleFileSelect(event, 'edit')"></div><div class="preview-grid" id="edit_preview_container"></div></div><span class="form-guide">Accepted formats: JPG, PNG. Max 10 images total.</span></div>
                    <div class="form-group"><label class="form-label">Activity Name <span class="required">*</span></label><input type="text" id="edit_activity_name" name="activity_name" class="form-input" required></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Start Date <span class="required">*</span></label><input type="date" id="edit_start_date" name="start_date" class="form-input" required></div><div class="form-group"><label class="form-label">End Date <span class="required">*</span></label><input type="date" id="edit_end_date" name="end_date" class="form-input" required></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Branch <span class="required">*</span></label><select id="edit_branch_id" name="branch_id" class="form-select" required><?php foreach($branches as $b) echo "<option value='{$b['Branch_ID']}'>{$b['Branch_Name']}</option>"; ?></select></div><div class="form-group"><label class="form-label">Target (RM)</label><input type="number" id="edit_target_amount" name="target_amount" class="form-input" step="0.01" required><div id="edit_amount_error" class="error-message">Cannot be negative.</div></div></div>
                    <div class="form-group"><label class="form-label">Status</label><select id="edit_activity_status" name="activity_status" class="form-select" required><option value="Active">Active</option><option value="Upcoming">Upcoming</option><option value="Completed">Completed</option><option value="Cancelled">Cancelled</option></select></div>
                    
                    <div class="form-group"><label class="form-label">Address 1</label><input type="text" id="edit_address1" name="address1" class="form-input" required></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Address 2</label><input type="text" id="edit_address2" name="address2" class="form-input"></div><div class="form-group"><label class="form-label">Address 3</label><input type="text" id="edit_address3" name="address3" class="form-input"></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Postcode</label><input type="text" id="edit_postal_code" name="postal_code" class="form-input" required></div><div class="form-group"><label class="form-label">City</label><input type="text" id="edit_city" name="city" class="form-input" required></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">State</label><select id="edit_state" name="state" class="form-select" required><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div><div class="form-group"><label class="form-label">Country</label><input type="text" id="edit_country" name="country" class="form-input" value="Malaysia" readonly></div></div>
                    
                    <div class="form-group"><label class="form-label">Description & Conditions</label><textarea id="edit_activity_description" name="activity_description" class="form-textarea" required></textarea><span class="form-guide">Detailed description and any specific conditions for this activity.</span></div>
                    <div class="form-group"><button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-save"></i> Update Activity</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="viewDonorsModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Activity Donation History</h2><button class="close-btn" onclick="document.getElementById('viewDonorsModal').style.display='none'">&times;</button></div>
            <div class="modal-body">
                <table class="donation-table"><thead><tr><th>Date</th><th>Donor Name</th><th>Amount (RM)</th><th>Ref</th></tr></thead><tbody id="donorsTableBody"></tbody></table>
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
        // --- MULTI-UPLOAD & PREVIEW LOGIC ---
        let addFiles = []; 
        let editNewFiles = []; 
        let editExistingImages = []; 

        function handleFileSelect(event, mode) {
            const input = event.target;
            const newFiles = Array.from(input.files);
            
            if (mode === 'add') {
                addFiles = addFiles.concat(newFiles);
                updateFileInput('add_activity_images', addFiles);
                renderPreview('add_preview_container', addFiles, 'add');
            } else if (mode === 'edit') {
                editNewFiles = editNewFiles.concat(newFiles);
                updateFileInput('edit_activity_images', editNewFiles);
                renderEditPreviews();
            }
        }

        function removeFile(index, mode) {
            if (mode === 'add') {
                addFiles.splice(index, 1);
                updateFileInput('add_activity_images', addFiles);
                renderPreview('add_preview_container', addFiles, 'add');
            } else if (mode === 'edit') {
                editNewFiles.splice(index, 1);
                updateFileInput('edit_activity_images', editNewFiles);
                renderEditPreviews();
            }
        }

        function removeExistingImage(index) {
            editExistingImages.splice(index, 1);
            document.getElementById('existing_images_json').value = JSON.stringify(editExistingImages);
            renderEditPreviews();
        }

        function updateFileInput(inputId, fileArray) {
            const dataTransfer = new DataTransfer();
            fileArray.forEach(file => dataTransfer.items.add(file));
            document.getElementById(inputId).files = dataTransfer.files;
        }

        function renderPreview(containerId, fileArray, mode) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            
            fileArray.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.innerHTML = `
                        <img src="${e.target.result}">
                        <button type="button" class="remove-img-btn" onclick="removeFile(${index}, '${mode}')"><i class="fas fa-times"></i></button>
                    `;
                    container.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        }

        function renderEditPreviews() {
            const container = document.getElementById('edit_preview_container');
            container.innerHTML = '';

            // Existing
            editExistingImages.forEach((src, index) => {
                const item = document.createElement('div');
                item.className = 'preview-item';
                item.innerHTML = `
                    <img src="${src}">
                    <button type="button" class="remove-img-btn" onclick="removeExistingImage(${index})"><i class="fas fa-times"></i></button>
                `;
                container.appendChild(item);
            });

            // New
            editNewFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.innerHTML = `
                        <img src="${e.target.result}">
                        <button type="button" class="remove-img-btn" onclick="removeFile(${index}, 'edit')"><i class="fas fa-times"></i></button>
                    `;
                    container.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        }

        // --- Standard Logic ---
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            if (type === 'status') { document.getElementById('filter_status_container').classList.add('active'); document.getElementById('filter_status_container').querySelector('select').disabled = false; }
            else if (type === 'branch') { document.getElementById('filter_branch_container').classList.add('active'); document.getElementById('filter_branch_container').querySelector('select').disabled = false; }
            else if (type === 'year') { document.getElementById('filter_year_container').classList.add('active'); document.getElementById('filter_year_container').querySelector('select').disabled = false; }
        }

        function setupPostcodeState(postcodeId, stateSelectId) {
            const pcInput = document.getElementById(postcodeId); const stateSelect = document.getElementById(stateSelectId);
            if (!pcInput || !stateSelect) return;
            pcInput.addEventListener('input', function() {
                const val = this.value.replace(/\D/g, '');
                if (val.length >= 2) {
                    const prefix = parseInt(val.substring(0, 2)); let state = "";
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

        function showSystemError(msg) {
            const el = document.getElementById('floatingError');
            document.getElementById('msgError').innerText = msg;
            el.style.display = 'flex';
            setTimeout(() => el.style.display='none', 5000);
        }

        function validateAmount(amountId, errorId) {
            const val = document.getElementById(amountId).value;
            const errorDiv = document.getElementById(errorId);
            if(val && parseFloat(val) < 0) { errorDiv.style.display = 'block'; return false; }
            errorDiv.style.display = 'none'; return true;
        }

        function validateActivityForm(prefix) { 
            const start = document.getElementById(prefix + '_start_date').value;
            const end = document.getElementById(prefix + '_end_date').value;
            
            if(start && end) {
                const sDate = new Date(start); const eDate = new Date(end);
                const today = new Date(); const oneYearLater = new Date(); oneYearLater.setFullYear(today.getFullYear() + 1);
                if (sDate > oneYearLater) { showSystemError("Start date cannot be more than 1 year from today."); return false; }
                if(eDate < sDate) { showSystemError("End date must be after Start date."); return false; }
                const diffTime = Math.abs(eDate - sDate); const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
                if (diffDays > 365) { showSystemError("Activity duration cannot exceed 1 year."); return false; }
            }
            const amountId = prefix + '_target_amount'; const amountErrId = prefix + '_amount_error';
            return validateAmount(amountId, amountErrId);
        }

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

        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); const m = document.getElementById('menu-' + id); if (m) m.style.display = 'block'; }
        
        function openAddActivityModal() { 
            addFiles = []; 
            updateFileInput('add_activity_images', []);
            document.getElementById('add_preview_container').innerHTML = '';
            document.getElementById('addActivityModal').style.display = 'flex'; 
        }
        
        function closeAddActivityModal() { 
            document.getElementById('addActivityModal').style.display = 'none'; 
            document.getElementById('addActivityForm').reset(); 
            addFiles = [];
            document.getElementById('add_preview_container').innerHTML = ''; 
        }
        
        function closeEditActivityModal() { document.getElementById('editActivityModal').style.display = 'none'; }
        
        function editActivity(obj) {
            editNewFiles = []; 
            updateFileInput('edit_activity_images', []);
            
            document.getElementById('edit_activity_id').value = obj.Activity_ID;
            document.getElementById('edit_activity_name').value = obj.Activity_Name;
            document.getElementById('edit_start_date').value = obj.Activity_StartDate;
            document.getElementById('edit_end_date').value = obj.Activity_EndDate;
            document.getElementById('edit_activity_description').value = obj.Activity_Description;
            document.getElementById('edit_target_amount').value = obj.Activity_TargetAmount;
            document.getElementById('edit_branch_id').value = obj.Branch_ID;
            document.getElementById('edit_activity_status').value = obj.Activity_Status;
            
            // Populate Address Fields
            document.getElementById('edit_address1').value = obj.Activity_Address1 || '';
            document.getElementById('edit_address2').value = obj.Activity_Address2 || '';
            document.getElementById('edit_address3').value = obj.Activity_Address3 || '';
            document.getElementById('edit_city').value = obj.Activity_City || '';
            document.getElementById('edit_state').value = obj.Activity_State || '';
            document.getElementById('edit_postal_code').value = obj.Activity_PostalCode || '';
            document.getElementById('edit_country').value = obj.Activity_Country || 'Malaysia';
            
            // Image Logic
            try {
                editExistingImages = JSON.parse(obj.Activity_Images || "[]");
            } catch(e) { editExistingImages = []; }
            
            document.getElementById('existing_images_json').value = JSON.stringify(editExistingImages);
            renderEditPreviews();
            
            document.getElementById('edit_amount_error').style.display='none';
            document.getElementById('editActivityModal').style.display = 'flex';
        }

        function openViewDonors(activityId) {
            const modal = document.getElementById('viewDonorsModal'); const tbody = document.getElementById('donorsTableBody');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">Loading donations...</td></tr>';
            modal.style.display = 'flex';
            fetch(`activity_management.php?action=get_activity_donations&activity_id=${activityId}`).then(r => r.json()).then(data => {
                tbody.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(row => { 
                        tbody.innerHTML += `<tr class="donation-row" onclick="window.open('admin_payment_details.php?id=${row.id}', '_blank')"><td>${row.date}</td><td>${row.name}</td><td>${row.amount}</td><td style="color:#666; font-size:12px;">${row.ref}</td></tr>`; 
                    });
                } else { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#888;">No donations found.</td></tr>'; }
            });
        }

        function confirmDeleteActivity(id) { if (confirm('Delete this activity?')) window.location.href = 'activity_management.php?delete_activity_id=' + id; }

        // --- LIGHTBOX LOGIC ---
        const allActivityImages = <?php echo json_encode($allActivityImagesMap); ?>;
        let currentLightboxActivityId = null;
        let currentLightboxIndex = 0;

        function openLightbox(activityId, index) {
            if (!allActivityImages[activityId] || allActivityImages[activityId].length === 0) return;
            currentLightboxActivityId = activityId;
            currentLightboxIndex = index;
            updateLightboxImage();
            document.getElementById('imageLightbox').style.display = "flex";
        }

        function closeLightbox() {
            document.getElementById('imageLightbox').style.display = "none";
        }

        function changeLightboxImage(n) {
            if (currentLightboxActivityId === null) return;
            const images = allActivityImages[currentLightboxActivityId];
            currentLightboxIndex += n;
            if (currentLightboxIndex >= images.length) currentLightboxIndex = 0;
            else if (currentLightboxIndex < 0) currentLightboxIndex = images.length - 1;
            updateLightboxImage();
        }

        function updateLightboxImage() {
            const images = allActivityImages[currentLightboxActivityId];
            const imgElement = document.getElementById('lightboxImage');
            imgElement.src = images[currentLightboxIndex];
            const prevBtn = document.querySelector('.lightbox-prev');
            const nextBtn = document.querySelector('.lightbox-next');
            if (images.length <= 1) { prevBtn.style.display = 'none'; nextBtn.style.display = 'none'; } 
            else { prevBtn.style.display = 'block'; nextBtn.style.display = 'block'; }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            setupPostcodeState('add_postal_code', 'add_state');
            setupPostcodeState('edit_postal_code', 'edit_state');
            const s = document.getElementById('floatingSuccess'); const e = document.getElementById('floatingError');
            if (s && s.querySelector('#msgSuccess').innerText !== '') { s.style.display='flex'; setTimeout(() => s.style.display='none', 5000); }
            if (e && e.querySelector('#msgError').innerText !== '') { e.style.display='flex'; setTimeout(() => e.style.display='none', 5000); }
            window.addEventListener('click', function(event) {
                if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
                if (event.target.classList.contains('modal')) event.target.style.display = 'none';
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