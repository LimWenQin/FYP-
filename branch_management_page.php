<?php
// branch_management_page.php
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

// --- 修改开始：从数据库获取真实的 Role 和 Profile Picture ---
$adminPosition = "Admin"; // 默认值，防止数据库查询失败
$adminProfilePicture = null;

// 同时查询头像和角色
$sql = "SELECT Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $adminProfilePicture = $row['Admin_ProfilePicture'];
    
    // 这里获取数据库里的 Admin_Role (例如 'Super Admin')
    if (!empty($row['Admin_Role'])) {
        $adminPosition = $row['Admin_Role'];
    }
}
$stmt->close();
// --- 修改结束 ---

// --- EXPORT TO EXCEL HANDLER ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "branch_list_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // 导出时过滤掉已删除的分支 (Is_Deleted = 0)
    $exportSql = "SELECT b.*, 
                  COALESCE((SELECT SUM(Order_Amount) FROM orders o WHERE o.Branch_ID = b.Branch_ID AND o.Order_Status = 'Success'), 0) as TotalDonated 
                  FROM branch b 
                  WHERE b.Is_Deleted = 0
                  ORDER BY b.Branch_ID DESC";
    
    $exportResult = $conn->query($exportSql);
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST']; 
    $path = dirname($_SERVER['PHP_SELF']); 
    $baseUrl = rtrim($protocol . "://" . $host . $path, '/\\') . '/';

    echo '<table border="1">';
    echo '<tr>
            <th style="width: 80px;">Profile Picture</th>
            <th>ID</th>
            <th>Branch Name</th>
            <th>Type</th>
            <th>Status</th>
            <th>Contact Number</th>
            <th>Email</th>
            <th>City</th>
            <th>State</th>
            <th>Full Address</th>
            <th>Target Amount (RM)</th>
            <th>Total Raised (RM)</th>
          </tr>';
    
    if ($exportResult && $exportResult->num_rows > 0) {
        while($row = $exportResult->fetch_assoc()) {
            echo '<tr>';
            echo '<td style="text-align:center; vertical-align:middle; height:80px;">';
            if (!empty($row['Branch_ProfilePicture']) && file_exists($row['Branch_ProfilePicture'])) {
                $fullImageUrl = $baseUrl . $row['Branch_ProfilePicture'];
                echo '<img src="' . $fullImageUrl . '" width="60" height="60" style="object-fit:cover; border-radius:50%;">';
            } else {
                echo 'No Image';
            }
            echo '</td>';
            echo '<td style="vertical-align:middle;">' . $row['Branch_ID'] . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Branch_Name']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Branch_Type']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Branch_OperationalStatus']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Branch_ContactNumber']) . '&nbsp;</td>';
            $email = isset($row['Branch_Email']) ? htmlspecialchars($row['Branch_Email']) : '-';
            echo '<td style="vertical-align:middle;">' . $email . '</td>';
            
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Branch_City']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Branch_State']) . '</td>';
            
            $fullAddr = $row['Branch_Address1'];
            if($row['Branch_Address2']) $fullAddr .= ", " . $row['Branch_Address2'];
            if($row['Branch_Address3']) $fullAddr .= ", " . $row['Branch_Address3'];
            $fullAddr .= ", " . $row['Branch_PostalCode'];
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($fullAddr) . '</td>';

            echo '<td style="vertical-align:middle;">' . number_format($row['Branch_TargetAmount'], 2) . '</td>';
            echo '<td style="vertical-align:middle;">' . number_format($row['TotalDonated'], 2) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="12">No records found</td></tr>';
    }
    echo '</table>';
    exit();
}

// --- STATS LOGIC ---
function getBranchStats($conn) {
    // 1. Calculate the timestamp for the end of last month
    $endOfLastMonth = date('Y-m-t 23:59:59', strtotime('last month'));

    // --- A. TOTAL BRANCHES (Check Is_Deleted = 0) ---
    $sqlTotalNow = "SELECT COUNT(*) as total FROM branch WHERE Is_Deleted = 0";
    $resTotalNow = $conn->query($sqlTotalNow);
    $totalBranchesNow = ($resTotalNow) ? $resTotalNow->fetch_assoc()['total'] : 0;

    $sqlTotalLast = "SELECT COUNT(*) as total FROM branch WHERE Is_Deleted = 0 AND Branch_CreatedAt <= '$endOfLastMonth'";
    $resTotalLast = $conn->query($sqlTotalLast);
    $totalBranchesLast = ($resTotalLast) ? $resTotalLast->fetch_assoc()['total'] : 0;

    $branchPercentChange = 0;
    if ($totalBranchesLast > 0) {
        $branchPercentChange = (($totalBranchesNow - $totalBranchesLast) / $totalBranchesLast) * 100;
    } elseif ($totalBranchesNow > 0) {
        $branchPercentChange = 100;
    }

    // --- B. ACTIVE BRANCHES (Check Is_Deleted = 0) ---
    $sqlActiveNow = "SELECT COUNT(*) as total FROM branch WHERE Branch_OperationalStatus = 'Open' AND Is_Deleted = 0";
    $resActiveNow = $conn->query($sqlActiveNow);
    $activeBranchesNow = ($resActiveNow) ? $resActiveNow->fetch_assoc()['total'] : 0;

    $sqlActiveLast = "SELECT COUNT(*) as total FROM branch WHERE Branch_OperationalStatus = 'Open' AND Is_Deleted = 0 AND Branch_CreatedAt <= '$endOfLastMonth'";
    $resActiveLast = $conn->query($sqlActiveLast);
    $activeBranchesLast = ($resActiveLast) ? $resActiveLast->fetch_assoc()['total'] : 0;

    $activePercentChange = 0;
    if ($activeBranchesLast > 0) {
        $activePercentChange = (($activeBranchesNow - $activeBranchesLast) / $activeBranchesLast) * 100;
    } elseif ($activeBranchesNow > 0) {
        $activePercentChange = 100;
    }

    // --- C. TOTAL DONATIONS ---
    $sqlDonationNow = "SELECT SUM(Order_Amount) as total FROM orders WHERE Branch_ID IS NOT NULL AND Order_Status = 'Success'";
    $resDonationNow = $conn->query($sqlDonationNow);
    $totalDonationNow = ($resDonationNow && $row = $resDonationNow->fetch_assoc()) ? (float)$row['total'] : 0;

    $sqlDonationLast = "SELECT SUM(Order_Amount) as total FROM orders WHERE Branch_ID IS NOT NULL AND Order_Status = 'Success' AND Order_Created_At <= '$endOfLastMonth'";
    $resDonationLast = $conn->query($sqlDonationLast);
    $totalDonationLast = ($resDonationLast && $row = $resDonationLast->fetch_assoc()) ? (float)$row['total'] : 0;

    $donationPercentChange = 0;
    if ($totalDonationLast > 0) {
        $donationPercentChange = (($totalDonationNow - $totalDonationLast) / $totalDonationLast) * 100;
    } elseif ($totalDonationNow > 0) {
        $donationPercentChange = 100; 
    }

    $getTrend = function($pct) {
        if ($pct > 0) return 'up';
        if ($pct < 0) return 'down';
        return 'flat';
    };

    return [
        'totalBranches' => $totalBranchesNow,
        'branchPercentChange' => number_format(abs($branchPercentChange), 1),
        'branchTrend' => $getTrend($branchPercentChange),
        
        'activeBranches' => $activeBranchesNow,
        'activePercentChange' => number_format(abs($activePercentChange), 1),
        'activeTrend' => $getTrend($activePercentChange),
        
        'totalDonationAmount' => $totalDonationNow,
        'donationPercentChange' => number_format(abs($donationPercentChange), 1),
        'donationTrend' => $getTrend($donationPercentChange)
    ];
}

$stats = getBranchStats($conn);

// --- 辅助函数：处理图片上传 ---
function handleBranchImageUpload($file) {
    if (isset($file) && $file['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowedTypes)) {
            $uploadDir = 'uploads/branches/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'branch_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return $uploadPath;
            }
        }
    }
    return null;
}

// Helper Arrays
function getBranchTypes() {
    return ['Orphanage', 'Elderly Home', 'Disabled Care', 'Stray Animal Center', 'Headquarters', 'Regional Center'];
}
$branchTypes = getBranchTypes();

$malaysiaStates = [
    'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan',
    'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'
];

// --- 处理添加分支 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_branch'])) {
    $branchName = mysqli_real_escape_string($conn, $_POST['branch_name']);
    $branchType = mysqli_real_escape_string($conn, $_POST['branch_type']);
    $operationalStatus = mysqli_real_escape_string($conn, $_POST['operational_status']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']); 
    
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    $contactRaw = $_POST['contact_number'];
    $contactNumber = "+60" . $contactRaw;
    $contactNumber = mysqli_real_escape_string($conn, $contactNumber);
    
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $profilePicture = null;
    if (isset($_FILES['branch_image'])) {
        $uploadedPath = handleBranchImageUpload($_FILES['branch_image']);
        if ($uploadedPath) {
            $profilePicture = $uploadedPath;
        }
    }
    
    if (!preg_match('/^\+60[0-9]{1,2}-[0-9]{7,10}$/', $contactNumber)) {
        $errorMessage = "Invalid phone number format.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format.";
    } else {
        // SQL Insert (No Is_Deleted needed, default is 0)
        $sql = "INSERT INTO branch (Branch_Name, Branch_Type, Branch_OperationalStatus, Branch_TargetAmount, Branch_ContactNumber, Branch_Email,
                Branch_Address1, Branch_Address2, Branch_Address3, Branch_City, Branch_State, 
                Branch_PostalCode, Branch_Country, Branch_Description, Branch_ProfilePicture, Admin_ID) 
                VALUES ('$branchName', '$branchType', '$operationalStatus', '$targetAmount', '$contactNumber', '$email',
                '$address1', '$address2', '$address3', '$city', '$state', 
                '$postalCode', '$country', '$description', '$profilePicture', $adminId)";
        
        if ($conn->query($sql)) {
            $successMessage = "Branch added successfully!";
        } else {
            $errorMessage = "Error adding branch: " . $conn->error;
        }
    }
    
    if (!empty($successMessage)) { header("Location: branch_management_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: branch_management_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// --- 处理更新分支 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_branch'])) {
    $branchId = mysqli_real_escape_string($conn, $_POST['branch_id']);
    $branchName = mysqli_real_escape_string($conn, $_POST['branch_name']);
    $branchType = mysqli_real_escape_string($conn, $_POST['branch_type']);
    $operationalStatus = mysqli_real_escape_string($conn, $_POST['operational_status']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']); 
    
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    $contactRaw = $_POST['contact_number'];
    if (strpos($contactRaw, '+60') === 0) {
         $contactNumber = $contactRaw;
    } else {
         $contactNumber = "+60" . $contactRaw;
    }
    $contactNumber = mysqli_real_escape_string($conn, $contactNumber);
    
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $picSql = "";
    if (isset($_FILES['branch_image']) && $_FILES['branch_image']['error'] == 0) {
        $uploadedPath = handleBranchImageUpload($_FILES['branch_image']);
        if ($uploadedPath) {
            $oldPicQ = $conn->query("SELECT Branch_ProfilePicture FROM branch WHERE Branch_ID = $branchId");
            if ($oldRow = $oldPicQ->fetch_assoc()) {
                if (!empty($oldRow['Branch_ProfilePicture']) && file_exists($oldRow['Branch_ProfilePicture'])) {
                    unlink($oldRow['Branch_ProfilePicture']);
                }
            }
            $picSql = ", Branch_ProfilePicture = '$uploadedPath'";
        }
    }
    
    if (empty($branchName) || empty($branchType)) {
        $errorMessage = "Name and Category are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format.";
    } else {
        $sql = "UPDATE branch SET 
                Branch_Name = '$branchName', 
                Branch_Type = '$branchType', 
                Branch_OperationalStatus = '$operationalStatus',
                Branch_TargetAmount = '$targetAmount',
                Branch_ContactNumber = '$contactNumber',
                Branch_Email = '$email',
                Branch_Address1 = '$address1',
                Branch_Address2 = '$address2', 
                Branch_Address3 = '$address3',
                Branch_City = '$city',
                Branch_State = '$state',
                Branch_PostalCode = '$postalCode',
                Branch_Country = '$country',
                Branch_Description = '$description'
                $picSql
                WHERE Branch_ID = $branchId";
        
        if ($conn->query($sql)) {
            $successMessage = "Branch updated successfully!";
        } else {
            $errorMessage = "Error updating branch: " . $conn->error;
        }
    }
    
    if (!empty($successMessage)) { header("Location: branch_management_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: branch_management_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// 处理删除 (Soft Delete)
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    // Update Is_Deleted to 1
    $deleteSql = "UPDATE branch SET Is_Deleted = 1 WHERE Branch_ID = $deleteId";
    if ($conn->query($deleteSql)) {
        header("Location: branch_management_page.php?success=" . urlencode("Branch deleted successfully!"));
        exit();
    } else {
        header("Location: branch_management_page.php?error=" . urlencode("Error deleting branch: " . $conn->error));
        exit();
    }
}

// --- 分页与筛选 ---
$results_per_page = 4;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = max(0, ($page - 1) * $results_per_page);

$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = [];

// 默认只显示未删除的分支
$whereConditions[] = "Is_Deleted = 0";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = mysqli_real_escape_string($conn, $_GET['search']);
    $whereConditions[] = "(Branch_Name LIKE '%$searchTerm%' OR Branch_City LIKE '%$searchTerm%')";
}
if (isset($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    if ($filterType == 'category' && !empty($_GET['filter_val_category'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_category']);
        $whereConditions[] = "Branch_Type = '$filterValue'";
    } elseif ($filterType == 'state' && !empty($_GET['filter_val_state'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_state']);
        $whereConditions[] = "Branch_State = '$filterValue'";
    } elseif ($filterType == 'status' && !empty($_GET['filter_val_status'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_status']);
        $whereConditions[] = "Branch_OperationalStatus = '$filterValue'";
    }
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Count Total
$count_result = $conn->query("SELECT COUNT(*) as total FROM branch $whereClause");
$total_branches_count = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_branches_count / $results_per_page);

// Fetch Data
$sql = "SELECT b.*, 
        COALESCE((SELECT SUM(Order_Amount) FROM orders o WHERE o.Branch_ID = b.Branch_ID AND o.Order_Status = 'Success'), 0) as TotalDonated 
        FROM branch b 
        $whereClause 
        ORDER BY b.Branch_ID DESC 
        LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$branches = [];
if ($result) { while($row = $result->fetch_assoc()) $branches[] = $row; }

$conn->close();

// Prepare Export URL
$exportParams = $_GET;
$exportParams['action'] = 'export_excel';
unset($exportParams['page']);
$exportUrl = "?" . http_build_query($exportParams);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Management - DonationMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Specific Styles for Branch Page Content only */
        .stats-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #888; margin-bottom: 5px; text-transform: uppercase; font-weight: 600; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; color: #333; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; gap: 5px; margin: 0; }
        
        /* Updated Text Colors */
        .text-success { color: #28a745 !important; }
        .text-danger { color: #dc3545 !important; }
        .text-muted { color: #888 !important; }

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

        .search-filter-container { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; background-color: white; min-width: 140px; cursor: pointer; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; background: white; }
        .secondary-filter { display: none; }
        .secondary-filter.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .branch-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; margin-bottom: 30px; }
        
        /* Updated Branch Card Style */
        .branch-card { 
            background: white; 
            border-radius: 12px; 
            overflow: visible; /* Changed to visible so dropdown can show */
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            transition: transform 0.3s ease; 
            position: relative; 
            display: flex; 
            flex-direction: column; 
            border: 1px solid #f0f0f0; 
        }
        .branch-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        
        .card-image { 
            height: 180px; 
            width: 100%; 
            position: relative; 
            background: #eee; 
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            overflow: hidden;
        }
        .card-image img { width: 100%; height: 100%; object-fit: cover; }
        .status-badge { position: absolute; top: 15px; left: 15px; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .status-open { background: #d4edda; color: #155724; }
        .status-closed { background: #f8d7da; color: #721c24; }
        
        .card-content { padding: 20px; flex: 1; display: flex; flex-direction: column; position: relative; }

        /* --- ACTION MENU STYLES --- */
        .card-actions { 
            position: absolute; 
            top: 10px; 
            right: 10px; 
            z-index: 50; 
        }
        .action-menu { position: relative; display: inline-block; }
        .menu-btn {
            background-color: rgba(255, 255, 255, 0.95); border: none; cursor: pointer;
            width: 32px; height: 32px; border-radius: 50%; color: #555;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.15); transition: all 0.2s;
        }
        .menu-btn:hover { background-color: white; color: var(--primary); transform: scale(1.1); }

        .action-dropdown { 
            position: absolute; 
            top: 40px; 
            right: 0; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.15); 
            width: 150px; 
            display: none; 
            overflow: hidden; 
            margin-bottom: 5px; 
            border: 1px solid #eee;
            z-index: 100;
        }
        .action-dropdown.show { display: block; animation: fadeIn 0.2s; }
        
        .action-item { padding: 10px 15px; display: flex; align-items: center; gap: 8px; color: #333; cursor: pointer; font-size: 13px; transition: 0.1s; }
        .action-item:hover { background: #f8f9fa; color: var(--primary); }
        .action-item.delete { color: var(--danger); border-top: 1px solid #f0f0f0; }
        
        /* ---------------------------------------------------- */

        .branch-type { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 1px; margin-bottom: 5px; }
        .branch-title { font-size: 18px; font-weight: 700; margin-bottom: 10px; color: #333; padding-right: 10px; }
        .branch-desc { font-size: 13px; color: #666; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 38px;}
        .info-row { display: flex; align-items: center; gap: 8px; color: #555; font-size: 12px; margin-bottom: 5px; }
        .info-row i { width: 16px; text-align: center; color: var(--primary); }
        .progress-section { margin-top: auto; padding-top: 15px; border-top: 1px solid #f0f0f0; }
        .progress-labels { display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #444; }
        .progress-track { width: 100%; height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #F28585, #d9534f); border-radius: 3px; transition: width 0.5s ease; }
        .progress-text { font-size: 11px; color: #888; margin-top: 3px; text-align: right; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; border-radius: 10px; width: 90%; max-width: 650px; max-height: 90vh; overflow-y: auto; padding: 0; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .modal-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fff; position: sticky; top: 0; z-index: 5; }
        .modal-header h2 { font-size: 18px; margin: 0; font-weight: 600; color: #333; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; transition: 0.2s; }
        .close-btn:hover { color: var(--danger); }
        .modal-body { padding: 25px; }
        .form-group { margin-bottom: 18px; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        .form-label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #444; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; transition: border 0.2s; outline: none; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(242, 133, 133, 0.1); }
        .form-textarea { resize: vertical; min-height: 80px; font-family: inherit; }
        .form-input[readonly] { background-color: #f8f9fa; color: #666; cursor: default; }
        .required { color: var(--danger); margin-left: 3px; }
        
        /* --- UPDATED FORM GUIDE STYLE (MATCHING DONOR PAGE) --- */
        .form-guide { 
            font-size: 12px; 
            color: #6c757d; 
            margin-top: 5px; 
            display: block; 
            font-style: italic; 
            background: #fbfbfb; 
            padding: 4px 8px; 
            border-radius: 4px; 
            border-left: 3px solid #ddd; 
        }
        
        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid #ddd; border-right: none; border-radius: 6px 0 0 6px; color: #666; font-size: 14px; font-weight: bold; }
        .phone-input { border-radius: 0 6px 6px 0 !important; }

        .image-preview-box { width: 100%; height: 160px; background: #f8f9fa; border: 2px dashed #ddd; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px; position: relative; }
        .image-preview-box img { width: 100%; height: 100%; object-fit: cover; }
        .btn-upload { background: #fff; border: 1px solid #ccc; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; display: inline-block; transition: 0.2s; }
        .btn-upload:hover { background: #f0f0f0; border-color: #bbb; }
        .file-input-wrapper { text-align: center; }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-link { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; background: white; }
        .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }

        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: 1fr; }
            .form-row { flex-direction: column; gap: 0; }
            .search-filter-container { flex-direction: column; align-items: stretch; }
            .filter-group { flex-wrap: wrap; }
            .filter-select { width: 100%; }
        }
    </style>
</head>
<body>
    
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <?php if (isset($_GET['success'])): ?>
                <div style="background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:5px; border-left:4px solid #28a745;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div style="background:#f8d7da; color:#721c24; padding:15px; margin-bottom:20px; border-radius:5px; border-left:4px solid #dc3545;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <div class="welcome-section">
                <h1>Branch Management</h1>
                <p>Manage shelter branches, view status, and track donations.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL BRANCHES</h3>
                        <h2><?php echo $stats['totalBranches']; ?></h2>
                        <?php 
                            // 【修改逻辑】如果趋势是 'down'，显示红色；否则（up 或 flat）都显示绿色
                            $bTrendClass = ($stats['branchTrend'] == 'down') ? 'text-danger' : 'text-success';
                        ?>
                        <p class="<?php echo $bTrendClass; ?>">
                            <?php if($stats['branchTrend'] == 'down'): ?>
                                <i class="fas fa-arrow-down"></i> -<?php echo $stats['branchPercentChange']; ?>% from last month
                            <?php else: ?>
                                <i class="fas fa-arrow-up"></i> +<?php echo $stats['branchPercentChange']; ?>% from last month
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>ACTIVE BRANCHES</h3>
                        <h2><?php echo $stats['activeBranches']; ?></h2>
                        <?php 
                            // 【修改逻辑】同上，非降即升/平（显示绿色）
                            $aTrendClass = ($stats['activeTrend'] == 'down') ? 'text-danger' : 'text-success';
                        ?>
                        <p class="<?php echo $aTrendClass; ?>">
                            <?php if($stats['activeTrend'] == 'down'): ?>
                                <i class="fas fa-arrow-down"></i> -<?php echo $stats['activePercentChange']; ?>% from last month
                            <?php else: ?>
                                <i class="fas fa-arrow-up"></i> +<?php echo $stats['activePercentChange']; ?>% from last month
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL DONATIONS</h3>
                        <h2>RM <?php echo number_format($stats['totalDonationAmount'], 0); ?></h2>
                        <?php 
                            // 保持你原本的 Donation 逻辑 (这也是一样的效果)
                            $dTrendClass = ($stats['donationTrend'] == 'down') ? 'text-danger' : 'text-success';
                        ?>
                        <p class="<?php echo $dTrendClass; ?>">
                            <?php if($stats['donationTrend'] == 'down'): ?>
                                <i class="fas fa-arrow-down"></i> -<?php echo $stats['donationPercentChange']; ?>% from last month
                            <?php else: ?>
                                <i class="fas fa-arrow-up"></i> +<?php echo $stats['donationPercentChange']; ?>% from last month
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                </div>
            </div>

            <div class="management-container">
                <div class="section-header">
                    <h2>Branch List</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add New Branch
                        </button>
                        <a href="<?php echo $exportUrl; ?>" class="btn btn-success" target="_blank">
                            <i class="fas fa-download"></i> Export Data
                        </a>
                    </div>
                </div>

                <form method="GET" class="search-filter-container">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilters()">
                            <option value="">Filter By...</option>
                            <option value="category" <?php echo ($filterType == 'category') ? 'selected' : ''; ?>>Category</option>
                            <option value="state" <?php echo ($filterType == 'state') ? 'selected' : ''; ?>>State</option>
                            <option value="status" <?php echo ($filterType == 'status') ? 'selected' : ''; ?>>Status</option>
                        </select>
                    </div>

                    <div id="filter_category" class="secondary-filter">
                        <select name="filter_val_category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach($branchTypes as $t) echo "<option value='$t'>$t</option>"; ?>
                        </select>
                    </div>
                    
                    <div id="filter_state" class="secondary-filter">
                        <select name="filter_val_state" class="filter-select">
                            <option value="">All States</option>
                            <?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?>
                        </select>
                    </div>

                    <div id="filter_status" class="secondary-filter">
                        <select name="filter_val_status" class="filter-select">
                            <option value="Open">Open</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search branch name..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    
                    <?php if(!empty($searchTerm) || !empty($filterType)): ?>
                        <a href="branch_management_page.php" class="btn btn-danger" style="background-color: #dc3545; padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>

                <div class="branch-grid">
                    <?php if (count($branches) > 0): ?>
                        <?php foreach($branches as $b): ?>
                            <?php 
                                // Progress Bar Logic
                                $target = isset($b['Branch_TargetAmount']) && $b['Branch_TargetAmount'] > 0 ? $b['Branch_TargetAmount'] : 10000;
                                $raised = $b['TotalDonated'];
                                $percent = ($raised / $target) * 100;
                                if($percent > 100) $percent = 100;
                                
                                $statusClass = ($b['Branch_OperationalStatus'] == 'Open') ? 'status-open' : 'status-closed';
                                $imgSrc = !empty($b['Branch_ProfilePicture']) ? $b['Branch_ProfilePicture'] : 'default_branch.jpg'; 
                            ?>
                            <div class="branch-card">
                                
                                <div class="card-actions">
                                    <div class="action-menu">
                                        <button class="menu-btn" onclick="toggleCardMenu(event, <?php echo $b['Branch_ID']; ?>)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div id="card-menu-<?php echo $b['Branch_ID']; ?>" class="action-dropdown">
                                            <div class="action-item" onclick="openViewBranchModal(<?php echo htmlspecialchars(json_encode($b)); ?>)">
                                                <i class="fas fa-eye"></i> View Details
                                            </div>
                                            <div class="action-item" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($b)); ?>)">
                                                <i class="fas fa-edit"></i> Edit Branch
                                            </div>
                                            <div class="action-item delete" onclick="confirmDelete(<?php echo $b['Branch_ID']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-image">
                                    <?php if(!empty($b['Branch_ProfilePicture'])): ?>
                                        <img src="<?php echo htmlspecialchars($b['Branch_ProfilePicture']); ?>" alt="Branch Image">
                                    <?php else: ?>
                                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#ccc; background:#f9f9f9;">
                                            <i class="fas fa-image fa-3x"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($b['Branch_OperationalStatus'] ?: 'Open'); ?>
                                    </div>
                                </div>
                                
                                <div class="card-content">
                                    <div class="branch-type"><?php echo htmlspecialchars($b['Branch_Type']); ?></div>
                                    <div class="branch-title"><?php echo htmlspecialchars($b['Branch_Name']); ?></div>
                                    
                                    <div class="info-row">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <span><?php echo htmlspecialchars($b['Branch_City'] . ', ' . $b['Branch_State']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <i class="fas fa-phone"></i> 
                                        <span><?php echo htmlspecialchars($b['Branch_ContactNumber']); ?></span>
                                    </div>
                                    
                                    <div class="branch-desc" style="margin-top:10px;">
                                        <?php echo htmlspecialchars($b['Branch_Description']); ?>
                                    </div>

                                    <div class="progress-section">
                                        <div class="progress-labels">
                                            <span>Raised: RM <?php echo number_format($raised); ?></span>
                                            <span style="color:#888;">Target: RM <?php echo number_format($target); ?></span>
                                        </div>
                                        <div class="progress-track">
                                            <div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div>
                                        </div>
                                        <div class="progress-text"><?php echo number_format($percent, 1); ?>% Funded</div>
                                        
                                        </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align:center; padding:50px; color:#888;">No active branches found.</div>
                    <?php endif; ?>
                </div>

                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php 
                    $qs = "";
                    if(!empty($searchTerm)) $qs .= "&search=".urlencode($searchTerm);
                    if(!empty($filterType)) $qs .= "&filter_type=$filterType&filter_val_category=$filterValue"; 
                    ?>
                    <?php if($page > 1): ?><a href="?page=<?php echo $page-1 . $qs; ?>" class="page-link">&laquo; Prev</a><?php endif; ?>
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <a href="?page=<?php echo $i . $qs; ?>" class="page-link <?php echo ($i==$page)?'active':''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if($page < $total_pages): ?><a href="?page=<?php echo $page+1 . $qs; ?>" class="page-link">Next &raquo;</a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Branch</h2>
                <button class="close-btn" onclick="closeModal('addModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="branch_management_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateEmail('add_email', 'addEmailError')">
                    <input type="hidden" name="add_branch" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Branch Image</label>
                        <div class="image-preview-box" id="add_img_preview">
                            <div style="text-align:center; color:#aaa;"><i class="fas fa-cloud-upload-alt fa-2x"></i><br>Preview</div>
                        </div>
                        <div class="file-input-wrapper">
                            <label class="btn-upload">
                                <i class="fas fa-camera"></i> Choose File 
                                <input type="file" name="branch_image" accept="image/*" style="display:none;" onchange="previewImage(this, 'add_img_preview')">
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Branch Name <span class="required">*</span></label>
                        <input type="text" name="branch_name" class="form-input" required placeholder="e.g. Sunny Shelter">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <select name="branch_type" class="form-select" required>
                                <option value="">Select Category...</option>
                                <?php foreach($branchTypes as $t) echo "<option value='$t'>$t</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <select name="operational_status" class="form-select" required>
                                <option value="Open">Open</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="add_email" class="form-input" required placeholder="e.g. branch@lovebridge.org.my">
                        <span class="form-guide">Valid email address (e.g. name@domain.com).</span>
                        <div id="addEmailError" class="error-message">Invalid email format.</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" name="contact_number" id="add_contact" class="form-input phone-input" placeholder="11-12345678" required maxlength="11">
                            </div>
                            <span class="form-guide">Format: 12-3456789 or 11-12345678 (No need for +60).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Funding Target (RM) <span class="required">*</span></label>
                            <input type="number" name="target_amount" class="form-input" placeholder="e.g. 10000.00" step="0.01" value="10000.00" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1 <span class="required">*</span></label>
                        <input type="text" name="address1" class="form-input" required placeholder="House No, Street Name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" name="address2" class="form-input" placeholder="Apartment / Unit (Optional)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" class="form-input" placeholder="Area / Taman (Optional)">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postcode <span class="required">*</span></label>
                            <input type="text" name="postal_code" id="postal_code" class="form-input" required placeholder="e.g. 50450">
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <span class="required">*</span></label>
                            <input type="text" name="city" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State <span class="required">*</span></label>
                            <select name="state" id="state" class="form-select" required>
                                <option value="">Select State...</option>
                                <?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-input" value="Malaysia" readonly style="background:#f8f9fa;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" placeholder="Describe the mission and needs of this branch..."></textarea>
                        <span class="form-guide">Optional: Enter remarks, mission details, or important notes about this branch.</span>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">Save Branch</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Branch</h2>
                <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="branch_management_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateEmail('edit_email', 'editEmailError')">
                    <input type="hidden" name="update_branch" value="1">
                    <input type="hidden" name="branch_id" id="edit_branch_id">
                    
                    <div class="form-group">
                        <label class="form-label">Branch Image</label>
                        <div class="image-preview-box" id="edit_img_preview"></div>
                        <div class="file-input-wrapper">
                            <label class="btn-upload">
                                <i class="fas fa-camera"></i> Change File 
                                <input type="file" name="branch_image" accept="image/*" style="display:none;" onchange="previewImage(this, 'edit_img_preview')">
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Branch Name <span class="required">*</span></label>
                        <input type="text" name="branch_name" id="edit_branch_name" class="form-input" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <select name="branch_type" id="edit_branch_type" class="form-select" required>
                                <?php foreach($branchTypes as $t) echo "<option value='$t'>$t</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <select name="operational_status" id="edit_operational_status" class="form-select" required>
                                <option value="Open">Open</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-input" required>
                        <span class="form-guide">Valid email address (e.g. name@domain.com).</span>
                        <div id="editEmailError" class="error-message">Invalid email format.</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" name="contact_number" id="edit_contact_number" class="form-input phone-input" required maxlength="11">
                            </div>
                            <span class="form-guide">Format: 12-3456789 or 11-12345678 (No need for +60).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Funding Target (RM) <span class="required">*</span></label>
                            <input type="number" name="target_amount" id="edit_target_amount" class="form-input" step="0.01" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address 1 <span class="required">*</span></label>
                        <input type="text" name="address1" id="edit_address1" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address 2</label>
                        <input type="text" name="address2" id="edit_address2" class="form-input" placeholder="Line 2">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address 3</label>
                        <input type="text" name="address3" id="edit_address3" class="form-input" placeholder="Line 3">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postcode <span class="required">*</span></label>
                            <input type="text" name="postal_code" id="edit_postal_code" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <span class="required">*</span></label>
                            <input type="text" name="city" id="edit_city" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State <span class="required">*</span></label>
                            <select name="state" id="edit_state" class="form-select" required>
                                <?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Country</label><input type="text" name="country" id="edit_country" class="form-input" readonly style="background:#f8f9fa;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-textarea"></textarea>
                        <span class="form-guide">Optional: Enter remarks, mission details, or important notes about this branch.</span>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">Update Branch</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="viewBranchModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>View Branch Details</h2>
                <button class="close-btn" onclick="closeModal('viewBranchModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Branch Image</label>
                    <div class="image-preview-box" id="view_img_preview" style="border-style:solid;"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Branch Name</label>
                    <input type="text" id="view_branch_name" class="form-input" readonly>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" id="view_branch_type" class="form-input" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <input type="text" id="view_operational_status" class="form-input" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="text" id="view_email" class="form-input" readonly>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="text" id="view_contact_number" class="form-input" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Funding Target (RM)</label>
                        <input type="text" id="view_target_amount" class="form-input" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Full Address</label>
                    <textarea id="view_full_address" class="form-input" readonly rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="view_description" class="form-textarea" readonly></textarea>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleFilters() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            if(type) {
                const el = document.getElementById('filter_' + type);
                if(el) { el.classList.add('active'); el.querySelector('select').disabled = false; }
            }
        }
        document.addEventListener('DOMContentLoaded', toggleFilters);

        function toggleCardMenu(event, id) {
            event.stopPropagation();
            // Close all others
            document.querySelectorAll('.action-dropdown').forEach(d => {
                if (d.id !== 'card-menu-' + id) d.classList.remove('show');
            });
            const menu = document.getElementById('card-menu-' + id);
            menu.classList.toggle('show');
        }

        window.onclick = function(event) {
            // Updated selector check for the new menu button class
            if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) {
                document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('show'));
            }
            if (event.target.classList.contains('modal')) event.target.style.display = 'none';
        }

        function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openEditModal(branch) {
            document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('show')); 
            
            document.getElementById('edit_branch_id').value = branch.Branch_ID;
            document.getElementById('edit_branch_name').value = branch.Branch_Name;
            document.getElementById('edit_branch_type').value = branch.Branch_Type;
            document.getElementById('edit_operational_status').value = branch.Branch_OperationalStatus || 'Open';
            document.getElementById('edit_target_amount').value = branch.Branch_TargetAmount || '10000.00';
            
            // Set Email
            document.getElementById('edit_email').value = branch.Branch_Email || '';

            // Handle Contact number (Strip +60 if present)
            let phone = branch.Branch_ContactNumber;
            if(phone && phone.startsWith('+60')) phone = phone.substring(3);
            document.getElementById('edit_contact_number').value = phone;

            document.getElementById('edit_address1').value = branch.Branch_Address1;
            document.getElementById('edit_address2').value = branch.Branch_Address2;
            document.getElementById('edit_address3').value = branch.Branch_Address3;
            document.getElementById('edit_city').value = branch.Branch_City;
            document.getElementById('edit_state').value = branch.Branch_State;
            document.getElementById('edit_postal_code').value = branch.Branch_PostalCode;
            document.getElementById('edit_country').value = branch.Branch_Country;
            document.getElementById('edit_description').value = branch.Branch_Description;

            const previewBox = document.getElementById('edit_img_preview');
            if(branch.Branch_ProfilePicture) {
                previewBox.innerHTML = `<img src="${branch.Branch_ProfilePicture}" alt="Preview">`;
            } else {
                previewBox.innerHTML = '<div style="text-align:center; color:#aaa;"><i class="fas fa-image fa-2x"></i><br>No Image</div>';
            }
            document.getElementById('editModal').style.display = 'flex';
        }

        function openViewBranchModal(branch) {
            document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('show')); 

            document.getElementById('view_branch_name').value = branch.Branch_Name;
            document.getElementById('view_branch_type').value = branch.Branch_Type;
            document.getElementById('view_operational_status').value = branch.Branch_OperationalStatus || 'Open';
            document.getElementById('view_email').value = branch.Branch_Email || '-';
            document.getElementById('view_contact_number').value = branch.Branch_ContactNumber;
            document.getElementById('view_target_amount').value = branch.Branch_TargetAmount || '0.00';
            
            let addr = branch.Branch_Address1;
            if(branch.Branch_Address2) addr += ", " + branch.Branch_Address2;
            if(branch.Branch_Address3) addr += ", " + branch.Branch_Address3;
            addr += "\n" + branch.Branch_PostalCode + " " + branch.Branch_City + ", " + branch.Branch_State;
            document.getElementById('view_full_address').value = addr;

            document.getElementById('view_description').value = branch.Branch_Description;

            const previewBox = document.getElementById('view_img_preview');
            if(branch.Branch_ProfilePicture) {
                previewBox.innerHTML = `<img src="${branch.Branch_ProfilePicture}" alt="Preview">`;
            } else {
                previewBox.innerHTML = '<div style="text-align:center; color:#aaa;"><i class="fas fa-image fa-2x"></i><br>No Image</div>';
            }

            document.getElementById('viewBranchModal').style.display = 'flex';
        }

        function confirmDelete(id) {
            if(confirm("Delete this branch? Associated data might be affected.")) {
                window.location.href = `branch_management_page.php?delete_id=${id}`;
            }
        }

        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function setupPhoneInput(inputId) {
            const input = document.getElementById(inputId);
            if(!input) return;
            input.addEventListener('input', function(e) {
                let val = this.value.replace(/\D/g, ''); 
                if (val.length > 11) val = val.substring(0, 11);
                let newVal = val;
                if (val.length > 2) {
                    newVal = val.substring(0, 2) + '-' + val.substring(2);
                }
                this.value = newVal;
            });
        }
        setupPhoneInput('add_contact');
        setupPhoneInput('edit_contact_number');

        // Email Validation Logic
        function validateEmail(inputId, errorId) { 
            const val = document.getElementById(inputId).value;
            // Check for @ AND a domain extension (basic check matching donor page)
            const v = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|my)$/i.test(val); 
            document.getElementById(errorId).style.display = v ? 'none' : 'block'; 
            return v; 
        }

        // Add Listeners for immediate feedback
        document.getElementById('add_email').addEventListener('blur', function() { validateEmail('add_email', 'addEmailError'); });
        document.getElementById('edit_email').addEventListener('blur', function() { validateEmail('edit_email', 'editEmailError'); });

        function setupPostcodeState(postcodeId, stateSelectId) {
            const pcInput = document.getElementById(postcodeId);
            const stateSelect = document.getElementById(stateSelectId);
            if (!pcInput || !stateSelect) return;

            pcInput.addEventListener('input', function() {
                const val = this.value.replace(/\D/g, '');
                if (val.length >= 2) {
                    const prefix = parseInt(val.substring(0, 2));
                    let state = "";
                    if (prefix >= 1 && prefix <= 2) state = "Perlis";
                    else if (prefix >= 5 && prefix <= 9) state = "Kedah";
                    else if (prefix >= 10 && prefix <= 14) state = "Penang";
                    else if (prefix >= 15 && prefix <= 18) state = "Kelantan";
                    else if (prefix >= 20 && prefix <= 24) state = "Terengganu";
                    else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                    else if (prefix >= 30 && prefix <= 36) state = "Perak";
                    else if (prefix >= 40 && prefix <= 48) state = "Selangor";
                    else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                    else if (prefix >= 62 && prefix <= 62) state = "Putrajaya";
                    else if (prefix >= 63 && prefix <= 68) state = "Selangor";
                    else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                    else if (prefix >= 75 && prefix <= 78) state = "Melaka";
                    else if (prefix >= 79 && prefix <= 86) state = "Johor";
                    else if (prefix == 87) state = "Labuan";
                    else if (prefix >= 88 && prefix <= 91) state = "Sabah";
                    else if (prefix >= 93 && prefix <= 98) state = "Sarawak";
                    if (state) stateSelect.value = state;
                }
            });
        }
        setupPostcodeState('postal_code', 'state');
        setupPostcodeState('edit_postal_code', 'edit_state');
    </script>
</body>
</html>
