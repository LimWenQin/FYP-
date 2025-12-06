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

// Get admin information
$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];
$adminEmail = $_SESSION['admin_email'];
$adminPosition = "System Administrator";

// Get admin profile picture
$adminProfilePicture = null;
$sql = "SELECT Admin_ProfilePicture FROM admin WHERE Admin_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $adminProfilePicture = $row['Admin_ProfilePicture'];
}
$stmt->close();

// 处理添加活动
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_activity'])) {
    $activityName = mysqli_real_escape_string($conn, $_POST['activity_name']);
    $startDate = mysqli_real_escape_string($conn, $_POST['start_date']);
    $endDate = mysqli_real_escape_string($conn, $_POST['end_date']);
    $activityDetails = mysqli_real_escape_string($conn, $_POST['activity_details']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $branchId = mysqli_real_escape_string($conn, $_POST['branch_id']);
    
    // 获取详细地址数据
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    
    $activityStatus = "Active";
    $activityGetAmount = 0.00;

    // 插入新活动
    $sql = "INSERT INTO activity (Activity_Name, Activity_StartDate, Activity_EndDate, 
            Activity_Details, Activity_TargetAmount, Activity_Status, Activity_GetAmount,
            Activity_Address1, Activity_Address2, Activity_Address3, Activity_City, 
            Activity_State, Activity_PostalCode, Activity_Country, Branch_ID) 
            VALUES ('$activityName', '$startDate', '$endDate', 
            '$activityDetails', '$targetAmount', '$activityStatus', '$activityGetAmount',
            '$address1', '$address2', '$address3', '$city', '$state', 
            '$postalCode', '$country', '$branchId')";
    
    if ($conn->query($sql)) {
        $successMessage = "Activity added successfully!";
    } else {
        $errorMessage = "Error adding activity: " . $conn->error;
    }
    
    if (!empty($successMessage)) {
        header("Location: activity_management.php?success=" . urlencode($successMessage));
        exit();
    } elseif (!empty($errorMessage)) {
        header("Location: activity_management.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// 处理添加特殊活动 (special_case)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_special_case'])) {
    $caseTitle = mysqli_real_escape_string($conn, $_POST['case_title']);
    $caseDescription = mysqli_real_escape_string($conn, $_POST['case_description']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $caseStatus = "Active";

    $sql = "INSERT INTO special_case (Case_Title, Case_Description, Target_Amount, Case_Status) 
            VALUES ('$caseTitle', '$caseDescription', '$targetAmount', '$caseStatus')";
    
    if ($conn->query($sql)) {
        $successMessage = "Special case added successfully!";
    } else {
        $errorMessage = "Error adding special case: " . $conn->error;
    }
    
    if (!empty($successMessage)) {
        header("Location: activity_management.php?success=" . urlencode($successMessage));
        exit();
    } elseif (!empty($errorMessage)) {
        header("Location: activity_management.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// 处理更新活动
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_activity'])) {
    $activityId = mysqli_real_escape_string($conn, $_POST['activity_id']);
    $activityName = mysqli_real_escape_string($conn, $_POST['activity_name']);
    $startDate = mysqli_real_escape_string($conn, $_POST['start_date']);
    $endDate = mysqli_real_escape_string($conn, $_POST['end_date']);
    $activityDetails = mysqli_real_escape_string($conn, $_POST['activity_details']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $branchId = mysqli_real_escape_string($conn, $_POST['branch_id']);
    $activityStatus = mysqli_real_escape_string($conn, $_POST['activity_status']);
    
    // 获取详细地址数据
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);

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
            WHERE Activity_ID = $activityId";
    
    if ($conn->query($sql)) {
        $successMessage = "Activity updated successfully!";
    } else {
        $errorMessage = "Error updating activity: " . $conn->error;
    }
    
    if (!empty($successMessage)) {
        header("Location: activity_management.php?success=" . urlencode($successMessage));
        exit();
    } elseif (!empty($errorMessage)) {
        header("Location: activity_management.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// 处理更新特殊活动
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_special_case'])) {
    $caseId = mysqli_real_escape_string($conn, $_POST['case_id']);
    $caseTitle = mysqli_real_escape_string($conn, $_POST['case_title']);
    $caseDescription = mysqli_real_escape_string($conn, $_POST['case_description']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $caseStatus = mysqli_real_escape_string($conn, $_POST['case_status']);

    $sql = "UPDATE special_case SET 
            Case_Title = '$caseTitle',
            Case_Description = '$caseDescription',
            Target_Amount = '$targetAmount',
            Case_Status = '$caseStatus'
            WHERE Case_ID = $caseId";
    
    if ($conn->query($sql)) {
        $successMessage = "Special case updated successfully!";
    } else {
        $errorMessage = "Error updating special case: " . $conn->error;
    }
    
    if (!empty($successMessage)) {
        header("Location: activity_management.php?success=" . urlencode($successMessage));
        exit();
    } elseif (!empty($errorMessage)) {
        header("Location: activity_management.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// 处理删除活动
if (isset($_GET['delete_activity_id'])) {
    $deleteId = $_GET['delete_activity_id'];
    $deleteSql = "DELETE FROM activity WHERE Activity_ID = $deleteId";
    
    if ($conn->query($deleteSql)) {
        $successMessage = "Activity deleted successfully!";
    } else {
        $errorMessage = "Error deleting activity: " . $conn->error;
    }
    
    if (!empty($successMessage)) {
        header("Location: activity_management.php?success=" . urlencode($successMessage));
        exit();
    } elseif (!empty($errorMessage)) {
        header("Location: activity_management.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// 处理删除特殊活动
if (isset($_GET['delete_case_id'])) {
    $deleteId = $_GET['delete_case_id'];
    $deleteSql = "DELETE FROM special_case WHERE Case_ID = $deleteId";
    
    if ($conn->query($deleteSql)) {
        $successMessage = "Special case deleted successfully!";
    } else {
        $errorMessage = "Error deleting special case: " . $conn->error;
    }
    
    if (!empty($successMessage)) {
        header("Location: activity_management.php?success=" . urlencode($successMessage));
        exit();
    } elseif (!empty($errorMessage)) {
        header("Location: activity_management.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// 分页设置
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$start_from = ($page - 1) * $results_per_page;

// 获取所有分支
$branches = [];
$branchResult = $conn->query("SELECT Branch_ID, Branch_Name FROM branch ORDER BY Branch_Name");
if ($branchResult && $branchResult->num_rows > 0) {
    while($row = $branchResult->fetch_assoc()) {
        $branches[] = $row;
    }
}

// 处理搜索
$searchTerm = "";
$activities = [];
$specialCases = [];
$total_activities = 0;
$total_special_cases = 0;

// 获取普通活动
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $_GET['search'];
    $searchTerm = mysqli_real_escape_string($conn, $searchTerm);
    
    $sql = "SELECT a.*, b.Branch_Name FROM activity a 
            LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID
            WHERE a.Activity_Name LIKE '%$searchTerm%' 
            OR a.Activity_Details LIKE '%$searchTerm%'
            OR b.Branch_Name LIKE '%$searchTerm%'
            ORDER BY a.Activity_StartDate DESC
            LIMIT $start_from, $results_per_page";
    
    $count_sql = "SELECT COUNT(*) as total FROM activity a 
                  LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID
                  WHERE a.Activity_Name LIKE '%$searchTerm%' 
                  OR a.Activity_Details LIKE '%$searchTerm%'
                  OR b.Branch_Name LIKE '%$searchTerm%'";
} else {
    $sql = "SELECT a.*, b.Branch_Name FROM activity a 
            LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID
            ORDER BY a.Activity_StartDate DESC 
            LIMIT $start_from, $results_per_page";
    
    $count_sql = "SELECT COUNT(*) as total FROM activity";
}

$count_result = $conn->query($count_sql);
if ($count_result && $count_result->num_rows > 0) {
    $row = $count_result->fetch_assoc();
    $total_activities = $row['total'];
}

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
}

// 获取特殊活动 (special_case) - 不分页
$specialCaseSql = "SELECT * FROM special_case ORDER BY Created_At DESC";
$specialCaseResult = $conn->query($specialCaseSql);
if ($specialCaseResult && $specialCaseResult->num_rows > 0) {
    while($row = $specialCaseResult->fetch_assoc()) {
        $specialCases[] = $row;
    }
    $total_special_cases = $specialCaseResult->num_rows;
}

// 计算总页数
$total_pages = ceil($total_activities / $results_per_page);

// 计算显示的记录范围
$start_record = ($page - 1) * $results_per_page + 1;
$end_record = min($page * $results_per_page, $total_activities);

// 获取活动统计信息
function getTotalActivities($conn) {
    $sql = "SELECT COUNT(*) as total FROM activity";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getActiveActivities($conn) {
    $sql = "SELECT COUNT(*) as total FROM activity WHERE Activity_Status = 'Active'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getCompletedActivities($conn) {
    $sql = "SELECT COUNT(*) as total FROM activity WHERE Activity_Status = 'Completed'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getTotalSpecialCases($conn) {
    $sql = "SELECT COUNT(*) as total FROM special_case";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getTotalDonations($conn) {
    $sql = "SELECT SUM(Activity_GetAmount) as total FROM activity";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'] ?: 0;
    }
    return 0;
}

$totalActivities = getTotalActivities($conn);
$activeActivities = getActiveActivities($conn);
$completedActivities = getCompletedActivities($conn);
$totalSpecialCases = getTotalSpecialCases($conn);
$totalDonations = getTotalDonations($conn);

// 马来西亚州列表
$malaysiaStates = [
    'Johor',
    'Kedah',
    'Kelantan',
    'Kuala Lumpur',
    'Labuan',
    'Melaka',
    'Negeri Sembilan',
    'Pahang',
    'Penang',
    'Perak',
    'Perlis',
    'Putrajaya',
    'Sabah',
    'Sarawak',
    'Selangor',
    'Terengganu'
];

// 格式化地址显示函数
function formatActivityAddress($activity) {
    $address = '';
    
    if (!empty($activity['Activity_Address1'])) {
        $address .= $activity['Activity_Address1'];
    }
    
    if (!empty($activity['Activity_Address2'])) {
        $address .= $address ? ",\n" . $activity['Activity_Address2'] : $activity['Activity_Address2'];
    }
    
    if (!empty($activity['Activity_Address3'])) {
        $address .= $address ? ",\n" . $activity['Activity_Address3'] : $activity['Activity_Address3'];
    }
    
    if (!empty($activity['Activity_City']) || !empty($activity['Activity_PostalCode']) || !empty($activity['Activity_State'])) {
        $cityPart = '';
        if (!empty($activity['Activity_PostalCode'])) {
            $cityPart .= $activity['Activity_PostalCode'];
        }
        if (!empty($activity['Activity_City'])) {
            $cityPart .= $cityPart ? ' ' . $activity['Activity_City'] : $activity['Activity_City'];
        }
        if (!empty($activity['Activity_State'])) {
            $cityPart .= $cityPart ? ', ' . $activity['Activity_State'] : $activity['Activity_State'];
        }
        
        if ($cityPart) {
            $address .= $address ? ",\n" . $cityPart : $cityPart;
        }
    }
    
    if (!empty($activity['Activity_Country']) && $activity['Activity_Country'] != 'Malaysia') {
        $address .= $address ? ",\n" . $activity['Activity_Country'] : $activity['Activity_Country'];
    }
    
    return $address;
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Activity Management Specific Styles */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-info h3 {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .stat-info h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 12px;
            color: var(--success);
            display: flex;
            align-items: center;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: rgba(242, 133, 133, 0.2);
            color: var(--primary);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: rgba(255, 193, 7, 0.2);
            color: var(--warning);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: rgba(40, 167, 69, 0.2);
            color: var(--success);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: rgba(23, 162, 184, 0.2);
            color: var(--info);
        }

        .stat-card:nth-child(5) .stat-icon {
            background: rgba(108, 117, 125, 0.2);
            color: var(--secondary);
        }

        /* Activity Management Section */
        .activity-management {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 18px;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #e07575;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        /* Activity Search */
        .activity-search {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--primary);
        }

        /* Activity Table */
        .activity-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .activity-table th, .activity-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }

        .activity-table th {
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
        }

        .activity-info {
            display: flex;
            align-items: center;
        }

        .activity-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin-right: 10px;
            background: var(--info);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .activity-details h4 {
            font-size: 14px;
            margin-bottom: 2px;
        }

        .activity-details p {
            font-size: 12px;
            color: var(--gray);
        }

        .address-display {
            font-size: 12px;
            color: var(--gray);
            white-space: pre-line;
            line-height: 1.4;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }

        .progress-fill {
            height: 100%;
            background: var(--success);
            border-radius: 4px;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--gray);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-completed {
            background: rgba(108, 117, 125, 0.1);
            color: var(--secondary);
        }

        .status-upcoming {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        .action-cell {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .edit-btn {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        .edit-btn:hover {
            background: rgba(23, 162, 184, 0.2);
        }

        .delete-btn {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .delete-btn:hover {
            background: rgba(220, 53, 69, 0.2);
        }

        /* Special Cases Section */
        .special-cases-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .special-case-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--warning);
        }

        .special-case-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .special-case-title {
            font-weight: 600;
            font-size: 16px;
            color: var(--dark);
        }

        .special-case-description {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .special-case-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--gray);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--gray-light);
        }

        .modal-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: var(--danger);
        }

        .modal-body {
            padding: 20px;
        }

        /* Activity Form */
        .activity-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--primary);
        }

        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            outline: none;
            background: white;
        }

        .form-select:focus {
            border-color: var(--primary);
        }

        .form-textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            outline: none;
            resize: vertical;
            min-height: 80px;
        }

        .form-textarea:focus {
            border-color: var(--primary);
        }

        .form-guide {
            font-size: 11px;
            color: var(--gray);
            margin-top: 3px;
            display: block;
        }

        /* Floating Alert Messages */
        .floating-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1100;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 400px;
            transition: all 0.3s ease;
        }

        .floating-alert-success {
            background: white;
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .floating-alert-danger {
            background: white;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 15px 0;
        }

        .pagination-info {
            font-size: 14px;
            color: var(--gray);
        }

        .pagination-controls {
            display: flex;
            gap: 10px;
        }

        .pagination-btn {
            padding: 8px 15px;
            border: 1px solid var(--gray-light);
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            text-decoration: none;
            color: inherit;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .pagination-btn:hover:not(.disabled) {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-btn.disabled {
            background: var(--gray-light);
            color: var(--gray);
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .activity-search {
                flex-direction: column;
            }
            
            .search-input {
                min-width: 100%;
            }
            
            .pagination {
                flex-direction: column;
                gap: 15px;
            }
            
            .activity-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .pagination-controls {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .special-case-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Alert Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="floating-alert floating-alert-success" id="floatingSuccess">
            <i class="fas fa-check-circle"></i>
            <div><?php echo htmlspecialchars($_GET['success']); ?></div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="floating-alert floating-alert-danger" id="floatingError">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php echo htmlspecialchars($_GET['error']); ?></div>
        </div>
    <?php endif; ?>

    <!-- Sidebar -->
    <div class="sidebar collapsed" id="sidebar">
        <div class="sidebar-menu">
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="admin_donor_page.php"><i class="fas fa-users"></i> <span>Donor Management</span></a></li>
                <li><a href="staff_management_page.php"><i class="fas fa-user-tie"></i> <span>Staff Management</span></a></li>
                <li><a href="admin_management_page.php"><i class="fas fa-user-shield"></i> <span>Admin Management</span></a></li>
                <li><a href="branch_management_page.php"><i class="fas fa-map-marker-alt"></i> <span>Branch Management</span></a></li>
                <li><a href="activity_management.php" class="active"><i class="fas fa-calendar-alt"></i> <span>Activity Management</span></a></li>
                <li><a href="payment_management.php"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
                <li><a href="reward_item_management.php"><i class="fas fa-gift"></i> <span>Reward Items</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div class="nav-left">
                <div class="logo">
                    <a href="admin_dashboard.php">
                        <img src="logo.jpg" alt="Logo">
                        <h1>DonationMS</h1>
                    </a>
                </div>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
            </div>
            <div class="nav-right">
                <div class="notification" id="notificationDropdown">
                    <i class="far fa-bell"></i>
                    <span class="notification-count">5</span>
                    <div class="notification-dropdown" id="notificationMenu">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <a href="#" onclick="markAllAsRead()">Mark all as read</a>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <!-- Notifications will be loaded here -->
                        </div>
                        <div class="notification-footer">
                            <a href="notifications.php">View All Notifications</a>
                        </div>
                    </div>
                </div>
                <div class="user-profile" id="userProfileDropdown">
                    <div class="user-profile-with-avatar">
                        <div class="user-avatar">
                            <?php if (!empty($adminProfilePicture)): ?>
                                <img src="<?php echo htmlspecialchars($adminProfilePicture); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <?php echo substr($adminName, 0, 1); ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($adminName); ?></div>
                            <div class="user-role"><?php echo htmlspecialchars($adminPosition); ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="margin-left: 10px; font-size: 12px;"></i>
                    </div>
                    <div class="user-profile-dropdown" id="userProfileMenu">
                        <a href="admin_profile.php">
                            <i class="fas fa-user"></i> View Profile
                        </a>
                        <a href="admin_profile.php?edit=true">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                        <div class="divider"></div>
                        <a href="admin_logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Activity Management</h1>
                <p>Manage all charity activities and special cases.</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL ACTIVITIES</h3>
                        <h2><?php echo $totalActivities; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +8% from last month</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>ACTIVE ACTIVITIES</h3>
                        <h2><?php echo $activeActivities; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +12% from last month</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-running"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>COMPLETED ACTIVITIES</h3>
                        <h2><?php echo $completedActivities; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +5% from last month</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>SPECIAL CASES</h3>
                        <h2><?php echo $totalSpecialCases; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +3 from last month</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL DONATIONS</h3>
                        <h2>RM <?php echo number_format($totalDonations, 2); ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +15% from last month</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-donate"></i>
                    </div>
                </div>
            </div>

            <!-- Activity Management Section -->
            <div class="activity-management">
                <div class="section-header">
                    <h2>Activity List</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="openAddActivityModal()">
                            <i class="fas fa-plus"></i> Add New Activity
                        </button>
                        <button class="btn btn-info" onclick="openAddSpecialCaseModal()">
                            <i class="fas fa-plus"></i> Add Special Case
                        </button>
                        <button class="btn btn-success">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                    </div>
                </div>

                <!-- Search Form -->
                <form method="GET" action="activity_management.php" class="activity-search">
                    <input type="text" name="search" class="search-input" placeholder="Search activities by name, details or branch..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($searchTerm)): ?>
                        <a href="activity_management.php" class="btn btn-danger">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Activity Table -->
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>ACTIVITY NAME</th>
                            <th>DATES</th>
                            <th>BRANCH</th>
                            <th>LOCATION</th>
                            <th>PROGRESS</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($activities) > 0): ?>
                            <?php foreach($activities as $activity): 
                                $progress = 0;
                                if ($activity['Activity_TargetAmount'] > 0) {
                                    $progress = ($activity['Activity_GetAmount'] / $activity['Activity_TargetAmount']) * 100;
                                }
                                $progress = min(100, $progress);
                                
                                // Determine status
                                $today = date('Y-m-d');
                                $status = $activity['Activity_Status'];
                                if ($status == 'Active') {
                                    if ($activity['Activity_StartDate'] > $today) {
                                        $status = 'Upcoming';
                                    } elseif ($activity['Activity_EndDate'] < $today) {
                                        $status = 'Completed';
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="activity-info">
                                        <div class="activity-avatar"><?php echo substr($activity['Activity_Name'], 0, 1); ?></div>
                                        <div class="activity-details">
                                            <h4><?php echo htmlspecialchars($activity['Activity_Name']); ?></h4>
                                            <p>ID: <?php echo htmlspecialchars($activity['Activity_ID']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="activity-details">
                                        <p><strong>Start:</strong> <?php echo date('M j, Y', strtotime($activity['Activity_StartDate'])); ?></p>
                                        <p><strong>End:</strong> <?php echo date('M j, Y', strtotime($activity['Activity_EndDate'])); ?></p>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($activity['Branch_Name'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <div class="address-display">
                                        <?php echo htmlspecialchars(formatActivityAddress($activity)); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <div class="progress-info">
                                        <span>RM <?php echo number_format($activity['Activity_GetAmount'], 2); ?></span>
                                        <span>RM <?php echo number_format($activity['Activity_TargetAmount'], 2); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $statusClass = '';
                                    switch($status) {
                                        case 'Active':
                                            $statusClass = 'status-active';
                                            break;
                                        case 'Completed':
                                            $statusClass = 'status-completed';
                                            break;
                                        case 'Upcoming':
                                            $statusClass = 'status-upcoming';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="action-btn edit-btn" onclick="editActivity(<?php echo $activity['Activity_ID']; ?>, '<?php echo htmlspecialchars($activity['Activity_Name']); ?>', '<?php echo $activity['Activity_StartDate']; ?>', '<?php echo $activity['Activity_EndDate']; ?>', '<?php echo htmlspecialchars($activity['Activity_Details']); ?>', '<?php echo $activity['Activity_TargetAmount']; ?>', '<?php echo $activity['Branch_ID']; ?>', '<?php echo $activity['Activity_Status']; ?>', '<?php echo htmlspecialchars($activity['Activity_Address1']); ?>', '<?php echo htmlspecialchars($activity['Activity_Address2']); ?>', '<?php echo htmlspecialchars($activity['Activity_Address3']); ?>', '<?php echo htmlspecialchars($activity['Activity_City']); ?>', '<?php echo htmlspecialchars($activity['Activity_State']); ?>', '<?php echo htmlspecialchars($activity['Activity_PostalCode']); ?>', '<?php echo htmlspecialchars($activity['Activity_Country']); ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="action-btn delete-btn" onclick="confirmDeleteActivity(<?php echo $activity['Activity_ID']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px;">
                                    <?php if (!empty($searchTerm)): ?>
                                        No activities found matching your search criteria.
                                    <?php else: ?>
                                        No activities found in the system.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_activities; ?> results
                    </div>
                    <div class="pagination-controls">
                        <!-- Previous Button -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" class="pagination-btn">
                                Previous
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                Previous
                            </span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="?page=1' . (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '') . '" class="pagination-btn">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="pagination-btn disabled">...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="pagination-btn active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" class="pagination-btn"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="pagination-btn disabled">...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $total_pages; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" class="pagination-btn"><?php echo $total_pages; ?></a>
                        <?php endif; ?>

                        <!-- Next Button -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" class="pagination-btn">
                                Next
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                Next
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Special Cases Section -->
            <div class="special-cases-section">
                <div class="section-header">
                    <h2>Special Cases</h2>
                    <div class="action-buttons">
                        <button class="btn btn-info" onclick="openAddSpecialCaseModal()">
                            <i class="fas fa-plus"></i> Add Special Case
                        </button>
                    </div>
                </div>

                <?php if (count($specialCases) > 0): ?>
                    <?php foreach($specialCases as $case): 
                        $progress = 0;
                        if ($case['Target_Amount'] > 0) {
                            $progress = ($case['Raised_Amount'] / $case['Target_Amount']) * 100;
                        }
                        $progress = min(100, $progress);
                    ?>
                    <div class="special-case-card">
                        <div class="special-case-header">
                            <div class="special-case-title"><?php echo htmlspecialchars($case['Case_Title']); ?></div>
                            <div class="action-cell">
                                <button class="action-btn edit-btn" onclick="editSpecialCase(<?php echo $case['Case_ID']; ?>, '<?php echo htmlspecialchars($case['Case_Title']); ?>', '<?php echo htmlspecialchars($case['Case_Description']); ?>', '<?php echo $case['Target_Amount']; ?>', '<?php echo $case['Case_Status']; ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="action-btn delete-btn" onclick="confirmDeleteSpecialCase(<?php echo $case['Case_ID']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <div class="special-case-description">
                            <?php echo htmlspecialchars($case['Case_Description']); ?>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <div class="special-case-footer">
                            <div>
                                <strong>Raised:</strong> RM <?php echo number_format($case['Raised_Amount'], 2); ?> 
                                <strong>Target:</strong> RM <?php echo number_format($case['Target_Amount'], 2); ?>
                            </div>
                            <div>
                                <span class="status-badge <?php echo $case['Case_Status'] == 'Active' ? 'status-active' : 'status-completed'; ?>">
                                    <?php echo $case['Case_Status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: var(--gray);">
                        No special cases found in the system.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add New Activity Modal -->
    <div class="modal" id="addActivityModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Activity</h2>
                <button class="close-btn" onclick="closeAddActivityModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addActivityForm" action="activity_management.php" method="POST">
                    <input type="hidden" name="add_activity" value="1">
                    <div class="form-group">
                        <label class="form-label" for="activity_name">Activity Name</label>
                        <input type="text" id="activity_name" name="activity_name" class="form-input" required>
                        <span class="form-guide">Enter the name of the activity</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="branch_id">Branch</label>
                        <select id="branch_id" name="branch_id" class="form-select" required>
                            <option value="">Select Branch</option>
                            <?php foreach($branches as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch['Branch_ID']); ?>"><?php echo htmlspecialchars($branch['Branch_Name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="target_amount">Target Amount (RM)</label>
                        <input type="number" id="target_amount" name="target_amount" class="form-input" step="0.01" min="0" required>
                    </div>
                    
                    <!-- Address Fields -->
                    <div class="form-group">
                        <label class="form-label" for="address1">Address Line 1</label>
                        <input type="text" id="address1" name="address1" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="address2">Address Line 2</label>
                        <input type="text" id="address2" name="address2" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="address3">Address Line 3</label>
                        <input type="text" id="address3" name="address3" class="form-input">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="city">City</label>
                            <input type="text" id="city" name="city" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="state">State</label>
                            <select id="state" name="state" class="form-select" required>
                                <option value="">Select State</option>
                                <?php foreach($malaysiaStates as $state): ?>
                                    <option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="country">Country</label>
                            <input type="text" id="country" name="country" class="form-input" value="Malaysia" required readonly>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="activity_details">Activity Details</label>
                        <textarea id="activity_details" name="activity_details" class="form-textarea" placeholder="Enter activity details..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Activity
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Activity Modal -->
    <div class="modal" id="editActivityModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Activity</h2>
                <button class="close-btn" onclick="closeEditActivityModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editActivityForm" action="activity_management.php" method="POST">
                    <input type="hidden" id="edit_activity_id" name="activity_id">
                    <input type="hidden" name="update_activity" value="1">
                    <div class="form-group">
                        <label class="form-label" for="edit_activity_name">Activity Name</label>
                        <input type="text" id="edit_activity_name" name="activity_name" class="form-input" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="edit_start_date">Start Date</label>
                            <input type="date" id="edit_start_date" name="start_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_end_date">End Date</label>
                            <input type="date" id="edit_end_date" name="end_date" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_branch_id">Branch</label>
                        <select id="edit_branch_id" name="branch_id" class="form-select" required>
                            <option value="">Select Branch</option>
                            <?php foreach($branches as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch['Branch_ID']); ?>"><?php echo htmlspecialchars($branch['Branch_Name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_target_amount">Target Amount (RM)</label>
                        <input type="number" id="edit_target_amount" name="target_amount" class="form-input" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_activity_status">Status</label>
                        <select id="edit_activity_status" name="activity_status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <!-- Address Fields -->
                    <div class="form-group">
                        <label class="form-label" for="edit_address1">Address Line 1</label>
                        <input type="text" id="edit_address1" name="address1" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_address2">Address Line 2</label>
                        <input type="text" id="edit_address2" name="address2" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_address3">Address Line 3</label>
                        <input type="text" id="edit_address3" name="address3" class="form-input">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="edit_city">City</label>
                            <input type="text" id="edit_city" name="city" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_state">State</label>
                            <select id="edit_state" name="state" class="form-select" required>
                                <option value="">Select State</option>
                                <?php foreach($malaysiaStates as $state): ?>
                                    <option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="edit_postal_code">Postal Code</label>
                            <input type="text" id="edit_postal_code" name="postal_code" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_country">Country</label>
                            <input type="text" id="edit_country" name="country" class="form-input" value="Malaysia" required readonly>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_activity_details">Activity Details</label>
                        <textarea id="edit_activity_details" name="activity_details" class="form-textarea" placeholder="Enter activity details..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Activity
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Special Case Modal -->
    <div class="modal" id="addSpecialCaseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Special Case</h2>
                <button class="close-btn" onclick="closeAddSpecialCaseModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addSpecialCaseForm" action="activity_management.php" method="POST">
                    <input type="hidden" name="add_special_case" value="1">
                    <div class="form-group">
                        <label class="form-label" for="case_title">Case Title</label>
                        <input type="text" id="case_title" name="case_title" class="form-input" required>
                        <span class="form-guide">Enter a descriptive title for the special case</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="case_description">Case Description</label>
                        <textarea id="case_description" name="case_description" class="form-textarea" placeholder="Describe the special case..." required></textarea>
                        <span class="form-guide">Provide details about this special case and why it needs support</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="special_target_amount">Target Amount (RM)</label>
                        <input type="number" id="special_target_amount" name="target_amount" class="form-input" step="0.01" min="0" required>
                        <span class="form-guide">Set the fundraising target for this special case</span>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Special Case
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Special Case Modal -->
    <div class="modal" id="editSpecialCaseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Special Case</h2>
                <button class="close-btn" onclick="closeEditSpecialCaseModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editSpecialCaseForm" action="activity_management.php" method="POST">
                    <input type="hidden" id="edit_case_id" name="case_id">
                    <input type="hidden" name="update_special_case" value="1">
                    <div class="form-group">
                        <label class="form-label" for="edit_case_title">Case Title</label>
                        <input type="text" id="edit_case_title" name="case_title" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_case_description">Case Description</label>
                        <textarea id="edit_case_description" name="case_description" class="form-textarea" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_special_target_amount">Target Amount (RM)</label>
                        <input type="number" id="edit_special_target_amount" name="target_amount" class="form-input" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_case_status">Status</label>
                        <select id="edit_case_status" name="case_status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Special Case
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Sidebar hover functionality
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        sidebar.addEventListener('mouseenter', function() {
            sidebar.classList.remove('collapsed');
            mainContent.classList.add('expanded');
        });

        sidebar.addEventListener('mouseleave', function() {
            sidebar.classList.add('collapsed');
            mainContent.classList.remove('expanded');
        });

        // Floating Alert Auto Hide
        function hideFloatingAlerts() {
            const successAlert = document.getElementById('floatingSuccess');
            const errorAlert = document.getElementById('floatingError');
            
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    setTimeout(() => {
                        successAlert.style.display = 'none';
                    }, 300);
                }, 5000);
            }
            
            if (errorAlert) {
                setTimeout(() => {
                    errorAlert.style.opacity = '0';
                    setTimeout(() => {
                        errorAlert.style.display = 'none';
                    }, 300);
                }, 8000);
            }
        }

        document.addEventListener('DOMContentLoaded', hideFloatingAlerts);

        // Modal Functions
        function openAddActivityModal() {
            document.getElementById('addActivityModal').style.display = 'flex';
        }

        function closeAddActivityModal() {
            document.getElementById('addActivityModal').style.display = 'none';
            document.getElementById('addActivityForm').reset();
        }

        function openEditActivityModal() {
            document.getElementById('editActivityModal').style.display = 'flex';
        }

        function closeEditActivityModal() {
            document.getElementById('editActivityModal').style.display = 'none';
        }

        function openAddSpecialCaseModal() {
            document.getElementById('addSpecialCaseModal').style.display = 'flex';
        }

        function closeAddSpecialCaseModal() {
            document.getElementById('addSpecialCaseModal').style.display = 'none';
            document.getElementById('addSpecialCaseForm').reset();
        }

        function openEditSpecialCaseModal() {
            document.getElementById('editSpecialCaseModal').style.display = 'flex';
        }

        function closeEditSpecialCaseModal() {
            document.getElementById('editSpecialCaseModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['addActivityModal', 'editActivityModal', 'addSpecialCaseModal', 'editSpecialCaseModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    if (modalId === 'addActivityModal') closeAddActivityModal();
                    if (modalId === 'editActivityModal') closeEditActivityModal();
                    if (modalId === 'addSpecialCaseModal') closeAddSpecialCaseModal();
                    if (modalId === 'editSpecialCaseModal') closeEditSpecialCaseModal();
                }
            });
        }

        // Confirm Delete Functions
        function confirmDeleteActivity(activityId) {
            if (confirm('Are you sure you want to delete this activity? This action cannot be undone.')) {
                window.location.href = 'activity_management.php?delete_activity_id=' + activityId;
            }
        }

        function confirmDeleteSpecialCase(caseId) {
            if (confirm('Are you sure you want to delete this special case? This action cannot be undone.')) {
                window.location.href = 'activity_management.php?delete_case_id=' + caseId;
            }
        }

        // Edit Activity Function
        function editActivity(activityId, activityName, startDate, endDate, activityDetails, targetAmount, branchId, activityStatus, address1, address2, address3, city, state, postalCode, country) {
            document.getElementById('edit_activity_id').value = activityId;
            document.getElementById('edit_activity_name').value = activityName;
            document.getElementById('edit_start_date').value = startDate;
            document.getElementById('edit_end_date').value = endDate;
            document.getElementById('edit_activity_details').value = activityDetails;
            document.getElementById('edit_target_amount').value = targetAmount;
            document.getElementById('edit_branch_id').value = branchId;
            document.getElementById('edit_activity_status').value = activityStatus;
            
            document.getElementById('edit_address1').value = address1 || '';
            document.getElementById('edit_address2').value = address2 || '';
            document.getElementById('edit_address3').value = address3 || '';
            document.getElementById('edit_city').value = city || '';
            document.getElementById('edit_state').value = state || '';
            document.getElementById('edit_postal_code').value = postalCode || '';
            document.getElementById('edit_country').value = country || 'Malaysia';
            
            openEditActivityModal();
        }

        // Edit Special Case Function
        function editSpecialCase(caseId, caseTitle, caseDescription, targetAmount, caseStatus) {
            document.getElementById('edit_case_id').value = caseId;
            document.getElementById('edit_case_title').value = caseTitle;
            document.getElementById('edit_case_description').value = caseDescription;
            document.getElementById('edit_special_target_amount').value = targetAmount;
            document.getElementById('edit_case_status').value = caseStatus;
            
            openEditSpecialCaseModal();
        }

        // Add active class to sidebar menu items on click
        document.querySelectorAll('.sidebar-menu a').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.sidebar-menu a').forEach(link => {
                    link.classList.remove('active');
                });
                this.classList.add('active');
            });
        });

        // Dropdown functionality
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationMenu = document.getElementById('notificationMenu');
        const userProfileDropdown = document.getElementById('userProfileDropdown');
        const userProfileMenu = document.getElementById('userProfileMenu');

        // Toggle notification dropdown
        notificationDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu.classList.toggle('show');
            userProfileMenu.classList.remove('show');
        });

        // Toggle user profile dropdown
        userProfileDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            userProfileMenu.classList.toggle('show');
            notificationMenu.classList.remove('show');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            notificationMenu.classList.remove('show');
            userProfileMenu.classList.remove('show');
        });

        // Load notifications
        function loadNotifications() {
            const notificationList = document.getElementById('notificationList');
            const notifications = [
                {
                    type: 'success',
                    icon: 'fas fa-donate',
                    title: 'New Donation Received',
                    message: 'John Smith donated RM 500.00',
                    time: '5 minutes ago',
                    unread: true
                },
                {
                    type: 'info',
                    icon: 'fas fa-user-plus',
                    title: 'New Donor Registered',
                    message: 'Sarah Johnson registered as a new donor',
                    time: '1 hour ago',
                    unread: true
                },
                {
                    type: 'warning',
                    icon: 'fas fa-exclamation-triangle',
                    title: 'Low Stock Alert',
                    message: 'Reward items are running low',
                    time: '2 hours ago',
                    unread: false
                }
            ];

            let html = '';
            notifications.forEach(notification => {
                html += `
                    <div class="notification-item ${notification.unread ? 'unread' : ''}">
                        <div class="notification-content">
                            <div class="notification-icon ${notification.type}">
                                <i class="${notification.icon}"></i>
                            </div>
                            <div class="notification-details">
                                <h4>${notification.title}</h4>
                                <p>${notification.message}</p>
                                <div class="notification-time">${notification.time}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            notificationList.innerHTML = html;
        }

        // Mark all as read function
        function markAllAsRead() {
            const notificationItems = document.querySelectorAll('.notification-item.unread');
            notificationItems.forEach(item => {
                item.classList.remove('unread');
            });
            
            const notificationCount = document.querySelector('.notification-count');
            notificationCount.textContent = '0';
            notificationCount.style.display = 'none';
            
            notificationMenu.classList.remove('show');
        }

        // Load notifications when page loads
        document.addEventListener('DOMContentLoaded', loadNotifications);
    </script>
</body>
</html>
