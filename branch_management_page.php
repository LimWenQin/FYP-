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

// --- 辅助函数：获取默认分类 (已移除 Community Center) ---
function getBranchTypes() {
    return ['Orphanage', 'Elderly Home', 'Disabled Care', 'Stray Animal Center'];
}
$branchTypes = getBranchTypes();

// 马来西亚州列表
$malaysiaStates = [
    'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan',
    'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'
];

// --- 处理添加分支 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_branch'])) {
    $branchName = mysqli_real_escape_string($conn, $_POST['branch_name']);
    $branchType = mysqli_real_escape_string($conn, $_POST['branch_type']);
    
    // 电话号码处理：拼接 +60
    $contactRaw = $_POST['contact_number'];
    // 移除可能用户不小心输入的前缀，确保干净
    $contactRaw = preg_replace('/^\+60/', '', $contactRaw);
    $contactNumber = "+60" . $contactRaw;
    $contactNumber = mysqli_real_escape_string($conn, $contactNumber);
    
    // 获取详细地址数据
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    // 验证电话号码格式 (允许 1-3 位区号，总长度符合大马格式)
    // +60 后面跟 1-3位数字, 然后是 - 然后是 7-8位数字
    // Regex explanation: Starts with +60, then 1-3 digits (area code), then hyphen, then 7-8 digits.
    if (!preg_match('/^\+60[0-9]{1,3}-[0-9]{7,8}$/', $contactNumber)) {
        $errorMessage = "Invalid phone number format. Format must be like 3-12345678 or 12-3456789.";
    } else {
        $sql = "INSERT INTO branch (Branch_Name, Branch_Type, Branch_ContactNumber, 
                Branch_Address1, Branch_Address2, Branch_Address3, Branch_City, Branch_State, 
                Branch_PostalCode, Branch_Country, Branch_Description, Admin_ID) 
                VALUES ('$branchName', '$branchType', '$contactNumber', 
                '$address1', '$address2', '$address3', '$city', '$state', 
                '$postalCode', '$country', '$description', $adminId)";
        
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
    
    // 电话号码处理
    $contactRaw = $_POST['contact_number'];
    $contactRaw = preg_replace('/^\+60/', '', $contactRaw);
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
    
    if (!preg_match('/^\+60[0-9]{1,3}-[0-9]{7,8}$/', $contactNumber)) {
        $errorMessage = "Invalid phone number format. Format must be like 3-12345678.";
    } else {
        $sql = "UPDATE branch SET 
                Branch_Name = '$branchName', 
                Branch_Type = '$branchType', 
                Branch_ContactNumber = '$contactNumber',
                Branch_Address1 = '$address1',
                Branch_Address2 = '$address2', 
                Branch_Address3 = '$address3',
                Branch_City = '$city',
                Branch_State = '$state',
                Branch_PostalCode = '$postalCode',
                Branch_Country = '$country',
                Branch_Description = '$description'
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

// 处理删除分支
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    $deleteSql = "DELETE FROM branch WHERE Branch_ID = $deleteId";
    
    if ($conn->query($deleteSql)) {
        header("Location: branch_management_page.php?success=" . urlencode("Branch deleted successfully!"));
        exit();
    } else {
        header("Location: branch_management_page.php?error=" . urlencode("Error deleting branch: " . $conn->error));
        exit();
    }
}

// 分页设置
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$start_from = ($page - 1) * $results_per_page;

// --- 搜索与筛选逻辑 (UPDATED to match Donor Page style) ---
$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = [];

// 1. 关键字搜索
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = mysqli_real_escape_string($conn, $_GET['search']);
    $whereConditions[] = "(Branch_Name LIKE '%$searchTerm%' 
                           OR Branch_ID LIKE '%$searchTerm%')";
}

// 2. 动态筛选
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    
    // Category Filter
    if ($filterType == 'category' && isset($_GET['filter_val_category']) && !empty($_GET['filter_val_category'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_category']);
        $whereConditions[] = "Branch_Type = '$filterValue'";
    }
    // State Filter
    elseif ($filterType == 'state' && isset($_GET['filter_val_state']) && !empty($_GET['filter_val_state'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_state']);
        $whereConditions[] = "Branch_State = '$filterValue'";
    }
}

// 组合 SQL
$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// 获取总数
$count_sql = "SELECT COUNT(*) as total FROM branch $whereClause";
$count_result = $conn->query($count_sql);
$total_branches = 0;
if ($count_result && $count_result->num_rows > 0) {
    $row = $count_result->fetch_assoc();
    $total_branches = $row['total'];
}

// 计算分页
$total_pages = ceil($total_branches / $results_per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $start_from = ($page - 1) * $results_per_page;
}

// 获取数据
$sql = "SELECT * FROM branch $whereClause ORDER BY Branch_Name LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$branches = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $branches[] = $row;
    }
}

$start_record = ($total_branches > 0) ? $start_from + 1 : 0;
$end_record = min($page * $results_per_page, $total_branches);

// 统计数据函数
function getTotalBranches($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM branch");
    return ($result && $row = $result->fetch_assoc()) ? $row['total'] : 0;
}

function getUrgentAttentionBranches($conn) {
    $sql = "SELECT COUNT(*) as total FROM branch 
            WHERE Branch_ID NOT IN (
                SELECT DISTINCT Branch_ID FROM activity 
                WHERE Activity_StartDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            )";
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? $row['total'] : 0;
}

function getTotalBeneficiaries($conn) {
    $sql = "SELECT SUM(CASE 
                WHEN Branch_Type = 'Orphanage' THEN 50
                WHEN Branch_Type = 'Elderly Home' THEN 30
                WHEN Branch_Type = 'Disabled Care' THEN 40
                WHEN Branch_Type = 'Stray Animal Center' THEN 100
                ELSE 25 END) as total FROM branch";
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? ($row['total'] ?: 0) : 0;
}

function getAllCategories($conn) {
    $result = $conn->query("SELECT COUNT(DISTINCT Branch_Type) as total FROM branch");
    return ($result && $row = $result->fetch_assoc()) ? $row['total'] : 0;
}

// 格式化地址
function formatAddress($branch) {
    $parts = [];
    if (!empty($branch['Branch_Address1'])) $parts[] = $branch['Branch_Address1'];
    
    $line2 = [];
    if (!empty($branch['Branch_Address2'])) $line2[] = $branch['Branch_Address2'];
    if (!empty($branch['Branch_Address3'])) $line2[] = $branch['Branch_Address3'];
    if (!empty($line2)) $parts[] = implode(', ', $line2);
    
    $cityPart = '';
    if (!empty($branch['Branch_PostalCode'])) $cityPart .= $branch['Branch_PostalCode'];
    if (!empty($branch['Branch_City'])) $cityPart .= ($cityPart ? ' ' : '') . $branch['Branch_City'];
    if (!empty($branch['Branch_State'])) $cityPart .= ($cityPart ? ', ' : '') . $branch['Branch_State'];
    if (!empty($cityPart)) $parts[] = $cityPart;
    
    if (!empty($branch['Branch_Country']) && $branch['Branch_Country'] != 'Malaysia') {
        $parts[] = $branch['Branch_Country'];
    }
    
    return implode(",\n", $parts);
}

$totalBranches = getTotalBranches($conn);
$urgentAttention = getUrgentAttentionBranches($conn);
$totalBeneficiaries = getTotalBeneficiaries($conn);
$allCategories = getAllCategories($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Branch Management Specific Styles */
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

        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; color: var(--success); display: flex; align-items: center; }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(23, 162, 184, 0.2); color: var(--info); }

        .branch-management {
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

        .section-header h2 { font-size: 18px; font-weight: 600; }
        .action-buttons { display: flex; gap: 10px; }
        
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #e07575; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }

        /* Updated Search & Filter Layout (Matching Donor Page) */
        .branch-search { 
            margin-bottom: 20px; 
            display: flex; 
            gap: 10px; 
            align-items: center; 
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; }
        .search-input:focus { border-color: var(--primary); }
        
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select {
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            outline: none;
            background-color: white;
            min-width: 140px;
            cursor: pointer;
        }
        .filter-select:focus { border-color: var(--primary); }
        
        /* Secondary Filters (Hidden by Default) */
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* Branch Table */
        .branch-table { width: 100%; border-collapse: collapse; }
        .branch-table th, .branch-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--gray-light); }
        .branch-table th { font-weight: 600; color: var(--gray); font-size: 14px; }
        
        .branch-info { display: flex; align-items: center; }
        .branch-avatar {
            width: 35px; height: 35px; border-radius: 50%; margin-right: 10px;
            background: var(--info); display: flex; align-items: center; justify-content: center;
            color: white; font-weight: bold;
        }
        
        .branch-details h4 { font-size: 14px; margin-bottom: 2px; }
        .branch-details p { font-size: 12px; color: var(--gray); }
        .address-display { font-size: 12px; color: var(--gray); white-space: pre-line; line-height: 1.4; }
        
        .status-badge { padding: 4px 8px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-active { background: rgba(40, 167, 69, 0.1); color: var(--success); }
        
        .action-cell { display: flex; gap: 5px; }
        .action-btn { padding: 5px 10px; border-radius: 4px; border: none; cursor: pointer; font-size: 12px; transition: all 0.3s; }
        .edit-btn { background: rgba(23, 162, 184, 0.1); color: var(--info); }
        .edit-btn:hover { background: rgba(23, 162, 184, 0.2); }
        .delete-btn { background: rgba(220, 53, 69, 0.1); color: var(--danger); }
        .delete-btn:hover { background: rgba(220, 53, 69, 0.2); }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--gray-light); }
        .modal-header h2 { font-size: 18px; font-weight: 600; margin: 0; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray); transition: color 0.3s; }
        .close-btn:hover { color: var(--danger); }
        .modal-body { padding: 20px; }
        
        .form-group { margin-bottom: 15px; }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark); }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-textarea { resize: vertical; min-height: 80px; }
        
        /* Guide and Error Styles (Matches Donor Page) */
        .form-guide { font-size: 11px; color: #6c757d; margin-top: 3px; display: block; font-style: italic; }
        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }
        .required { color: red; margin-left: 3px; font-weight: bold; }

        /* Phone number format display (Matching Donor Design) */
        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); }
        .phone-input { border-radius: 0 5px 5px 0 !important; }

        /* Alerts */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; max-width: 400px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }

        /* Pagination Styles */
        .pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 15px 0; border-top: 1px solid #eee; }
        .pagination-info { font-size: 14px; color: var(--gray); }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 8px 14px; border: 1px solid var(--gray-light); background: #f8f9fa; border-radius: 5px; cursor: pointer; transition: all 0.3s; font-size: 14px; text-decoration: none; color: inherit; }
        .pagination-btn:hover:not(.disabled):not(.active) { background: #e2e6ea; }
        .pagination-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .pagination-btn.disabled { background: var(--gray-light); color: var(--gray); cursor: not-allowed; opacity: 0.6; }

        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: repeat(2, 1fr); }
            .form-row { flex-direction: column; gap: 0; }
            .branch-search { flex-direction: column; align-items: stretch; }
            .filter-select { width: 100%; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
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

    <div class="sidebar collapsed" id="sidebar">
        <div class="sidebar-menu">
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="admin_donor_page.php"><i class="fas fa-users"></i> <span>Donor Management</span></a></li>
                <li><a href="staff_management_page.php"><i class="fas fa-user-tie"></i> <span>Staff Management</span></a></li>
                <li><a href="admin_management_page.php"><i class="fas fa-user-shield"></i> <span>Admin Management</span></a></li>
                <li><a href="branch_management_page.php" class="active"><i class="fas fa-map-marker-alt"></i> <span>Branch Management</span></a></li>
                <li><a href="activity_management.php"><i class="fas fa-calendar-alt"></i> <span>Activity Management</span></a></li>
                <li><a href="payment_management.php"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
                <li><a href="reward_item_management.php"><i class="fas fa-gift"></i> <span>Reward Items</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content" id="mainContent">
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
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Branch Management</h1>
                <p>Manage all aid centers, shelters, and care homes.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL BRANCHES</h3>
                        <h2><?php echo $totalBranches; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +12% from last month</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>URGENT ATTENTION</h3>
                        <h2><?php echo $urgentAttention; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +8.2% from last month</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL BENEFICIARIES</h3>
                        <h2><?php echo $totalBeneficiaries; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +15% from last month</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>ALL CATEGORIES</h3>
                        <h2><?php echo $allCategories; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +2 from last month</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-tag"></i></div>
                </div>
            </div>

            <div class="branch-management">
                <div class="section-header">
                    <h2>Branch List</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="openAddBranchModal()">
                            <i class="fas fa-plus"></i> Add New Branch
                        </button>
                        <button class="btn btn-success">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                    </div>
                </div>

                <form method="GET" action="branch_management_page.php" class="branch-search">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="category" <?php echo ($filterType == 'category') ? 'selected' : ''; ?>>Category</option>
                            <option value="state" <?php echo ($filterType == 'state') ? 'selected' : ''; ?>>State</option>
                        </select>
                    </div>

                    <div id="filter_category_container" class="secondary-filter">
                        <select name="filter_val_category" class="filter-select">
                            <option value="">Select Category...</option>
                            <?php foreach($branchTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($filterValue == $type && $filterType == 'category') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="filter_state_container" class="secondary-filter">
                        <select name="filter_val_state" class="filter-select">
                            <option value="">Select State...</option>
                            <?php foreach($malaysiaStates as $state): ?>
                                <option value="<?php echo htmlspecialchars($state); ?>" <?php echo ($filterValue == $state && $filterType == 'state') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($state); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search branches by name or ID..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if (!empty($searchTerm) || !empty($filterType)): ?>
                        <a href="branch_management_page.php" class="btn btn-danger" title="Clear Filters">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>

                <table class="branch-table">
                    <thead>
                        <tr>
                            <th>BRANCH NAME</th>
                            <th>CONTACT INFO</th>
                            <th>ADDRESS</th>
                            <th>TYPE</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($branches) > 0): ?>
                            <?php foreach($branches as $branch): ?>
                            <tr>
                                <td>
                                    <div class="branch-info">
                                        <div class="branch-avatar"><?php echo substr($branch['Branch_Name'], 0, 1); ?></div>
                                        <div class="branch-details">
                                            <h4><?php echo htmlspecialchars($branch['Branch_Name']); ?></h4>
                                            <p>ID: <?php echo htmlspecialchars($branch['Branch_ID']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="branch-details">
                                        <p><?php echo htmlspecialchars($branch['Branch_ContactNumber']); ?></p>
                                    </div>
                                </td>
                                <td>
                                    <div class="address-display"><?php echo htmlspecialchars(formatAddress($branch)); ?></div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($branch['Branch_Type']); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-active">Active</span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="action-btn edit-btn" onclick="editBranch(<?php echo htmlspecialchars(json_encode($branch)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $branch['Branch_ID']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">
                                    No branches found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_branches; ?> results
                    </div>
                    <div class="pagination-controls">
                        <?php 
                        // Build query params
                        $queryParams = [];
                        if(!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if(!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if($filterType == 'category') $queryParams['filter_val_category'] = $filterValue;
                            if($filterType == 'state') $queryParams['filter_val_state'] = $filterValue;
                        }
                        $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                        
                        if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1 . $queryString; ?>" class="pagination-btn">Previous</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Previous</span>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="pagination-btn active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i . $queryString; ?>" class="pagination-btn"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1 . $queryString; ?>" class="pagination-btn">Next</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="addBranchModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Branch</h2>
                <button class="close-btn" onclick="closeAddBranchModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addBranchForm" action="branch_management_page.php" method="POST">
                    <input type="hidden" name="add_branch" value="1">
                    <div class="form-group">
                        <label class="form-label" for="branch_name">Branch Name <span class="required">*</span></label>
                        <input type="text" id="branch_name" name="branch_name" class="form-input" required placeholder="e.g. Hope Orphanage Center">
                        <span class="form-guide">The official registered name of the branch.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="branch_type">Branch Type <span class="required">*</span></label>
                            <select id="branch_type" name="branch_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <?php foreach($branchTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-guide">Select the category that best describes this branch.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="contact_number">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="contact_number" name="contact_number" class="form-input phone-input" required placeholder="3-12345678" maxlength="11">
                            </div>
                            <span class="form-guide">e.g., 3-12345678 or 12-3456789 (Do not include +60)</span>
                            <div id="phoneError" class="error-message">Invalid phone number</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="address1">Address Line 1 <span class="required">*</span></label>
                        <input type="text" id="address1" name="address1" class="form-input" required placeholder="e.g. No. 123, Jalan Charity">
                        <span class="form-guide">House/Lot number, street name.</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="address2">Address Line 2</label>
                        <input type="text" id="address2" name="address2" class="form-input" placeholder="e.g. Taman Sejahtera">
                        <span class="form-guide">Area, residential name (Optional).</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="address3">Address Line 3</label>
                        <input type="text" id="address3" name="address3" class="form-input" placeholder="Address Line 3 (Optional)">
                        <span class="form-guide">Additional address details (Optional).</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="city">City <span class="required">*</span></label>
                            <input type="text" id="city" name="city" class="form-input" required placeholder="e.g. Kuala Lumpur">
                            <span class="form-guide">The city where the branch is located.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="state">State <span class="required">*</span></label>
                            <select id="state" name="state" class="form-select" required>
                                <option value="">Select State</option>
                                <?php foreach($malaysiaStates as $state): ?>
                                    <option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-guide">Select the state.</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="postal_code">Postal Code <span class="required">*</span></label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input" required placeholder="e.g. 50000">
                            <span class="form-guide">5-digit postal code.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="country">Country</label>
                            <input type="text" id="country" name="country" class="form-input" value="Malaysia" required readonly>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea id="description" name="description" class="form-textarea" placeholder="Enter branch description..."></textarea>
                        <span class="form-guide">Brief description about what this branch does or its mission.</span>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Branch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="editBranchModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Branch</h2>
                <button class="close-btn" onclick="closeEditBranchModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editBranchForm" action="branch_management_page.php" method="POST">
                    <input type="hidden" id="edit_branch_id" name="branch_id">
                    <input type="hidden" name="update_branch" value="1">
                    <div class="form-group">
                        <label class="form-label" for="edit_branch_name">Branch Name <span class="required">*</span></label>
                        <input type="text" id="edit_branch_name" name="branch_name" class="form-input" required placeholder="e.g. Hope Orphanage Center">
                        <span class="form-guide">The official registered name of the branch.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="edit_branch_type">Branch Type <span class="required">*</span></label>
                            <select id="edit_branch_type" name="branch_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <?php foreach($branchTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-guide">Select the category that best describes this branch.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_contact_number">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="edit_contact_number" name="contact_number" class="form-input phone-input" required placeholder="3-12345678" maxlength="11">
                            </div>
                            <span class="form-guide">e.g., 3-12345678 or 12-3456789 (Do not include +60)</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_address1">Address Line 1 <span class="required">*</span></label>
                        <input type="text" id="edit_address1" name="address1" class="form-input" required placeholder="e.g. No. 123, Jalan Charity">
                        <span class="form-guide">House/Lot number, street name.</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_address2">Address Line 2</label>
                        <input type="text" id="edit_address2" name="address2" class="form-input" placeholder="e.g. Taman Sejahtera">
                        <span class="form-guide">Area, residential name (Optional).</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_address3">Address Line 3</label>
                        <input type="text" id="edit_address3" name="address3" class="form-input" placeholder="Address Line 3 (Optional)">
                        <span class="form-guide">Additional address details (Optional).</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="edit_city">City <span class="required">*</span></label>
                            <input type="text" id="edit_city" name="city" class="form-input" required placeholder="e.g. Kuala Lumpur">
                            <span class="form-guide">The city where the branch is located.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_state">State <span class="required">*</span></label>
                            <select id="edit_state" name="state" class="form-select" required>
                                <option value="">Select State</option>
                                <?php foreach($malaysiaStates as $state): ?>
                                    <option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-guide">Select the state.</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="edit_postal_code">Postal Code <span class="required">*</span></label>
                            <input type="text" id="edit_postal_code" name="postal_code" class="form-input" required placeholder="e.g. 50000">
                            <span class="form-guide">5-digit postal code.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_country">Country</label>
                            <input type="text" id="edit_country" name="country" class="form-input" value="Malaysia" required readonly>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" class="form-textarea" placeholder="Enter branch description..."></textarea>
                        <span class="form-guide">Brief description about what this branch does or its mission.</span>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Branch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // --- Sidebar & UI Scripts ---
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        sidebar.addEventListener('mouseenter', () => { sidebar.classList.remove('collapsed'); mainContent.classList.add('expanded'); });
        sidebar.addEventListener('mouseleave', () => { sidebar.classList.add('collapsed'); mainContent.classList.remove('expanded'); });

        // Auto hide alerts
        const successAlert = document.getElementById('floatingSuccess');
        const errorAlert = document.getElementById('floatingError');
        if (successAlert) setTimeout(() => { successAlert.style.opacity = '0'; setTimeout(() => successAlert.style.display = 'none', 300); }, 5000);
        if (errorAlert) setTimeout(() => { errorAlert.style.opacity = '0'; setTimeout(() => errorAlert.style.display = 'none', 300); }, 8000);

        // --- FILTER SCRIPT (New - Matching Donor Page) ---
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            
            // Hide all secondary filters
            document.querySelectorAll('.secondary-filter').forEach(el => {
                el.classList.remove('active');
                el.querySelector('select').disabled = true; // Disable to prevent URL clutter
            });

            // Show selected filter
            if (type === 'category') {
                const el = document.getElementById('filter_category_container');
                el.classList.add('active');
                el.querySelector('select').disabled = false;
            } else if (type === 'state') {
                const el = document.getElementById('filter_state_container');
                el.classList.add('active');
                el.querySelector('select').disabled = false;
            }
        }
        
        // Initialize filters on load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            setupPhoneInput('contact_number');
            setupPhoneInput('edit_contact_number');
        });

        // --- PHONE INPUT LOGIC (Auto-dash, Validation) ---
        function setupPhoneInput(inputId) {
            const input = document.getElementById(inputId);
            if(!input) return;

            // Handle backspace near the dash
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.selectionStart === 2 && this.value.charAt(1) === '-') {
                    e.preventDefault(); 
                    let raw = this.value.replace(/\D/g, '');
                    if (raw.length > 0) {
                        let newRaw = raw.substring(1); 
                        // Simplified logic for standard format
                        this.value = newRaw.length > 0 ? (newRaw.length > 1 ? newRaw.substring(0, 1) + '-' + newRaw.substring(1) : newRaw.substring(0, 1) + '-') : '';
                        this.setSelectionRange(0, 0);
                    }
                }
            });

            // Handle normal input (Auto insert dash)
            input.addEventListener('input', function(e) {
                let val = this.value.replace(/\D/g, ''); 
                if (val.length > 0) {
                    // Assuming format X-XXXXXXXX (Area code 1 digit or more)
                    // Simple heuristic: after 1st digit, add dash if it doesn't exist
                    this.value = val.substring(0, 1) + (val.length >= 1 ? '-' : '') + (val.length > 1 ? val.substring(1, 10) : '');
                }
            });
        }

        // --- Modal Scripts ---
        function openAddBranchModal() { document.getElementById('addBranchModal').style.display = 'flex'; }
        function closeAddBranchModal() { 
            document.getElementById('addBranchModal').style.display = 'none';
            document.getElementById('addBranchForm').reset();
        }
        function closeEditBranchModal() { document.getElementById('editBranchModal').style.display = 'none'; }

        // Window click to close modals
        window.onclick = function(event) {
            const addModal = document.getElementById('addBranchModal');
            const editModal = document.getElementById('editBranchModal');
            if (event.target === addModal) closeAddBranchModal();
            if (event.target === editModal) closeEditBranchModal();
        }

        function confirmDelete(branchId) {
            if (confirm('Are you sure you want to delete this branch?')) {
                window.location.href = 'branch_management_page.php?delete_id=' + branchId;
            }
        }

        // Edit Branch: Populate Data
        function editBranch(branch) {
            document.getElementById('edit_branch_id').value = branch.Branch_ID;
            document.getElementById('edit_branch_name').value = branch.Branch_Name;
            document.getElementById('edit_branch_type').value = branch.Branch_Type;
            
            // Handle Phone Number: Strip +60 prefix for display in input
            let contact = branch.Branch_ContactNumber;
            if (contact && contact.startsWith('+60')) {
                contact = contact.substring(3); // Remove +60
            }
            document.getElementById('edit_contact_number').value = contact;
            // Trigger input event to format with dash if needed
            document.getElementById('edit_contact_number').dispatchEvent(new Event('input'));
            
            document.getElementById('edit_address1').value = branch.Branch_Address1;
            document.getElementById('edit_address2').value = branch.Branch_Address2;
            document.getElementById('edit_address3').value = branch.Branch_Address3;
            document.getElementById('edit_city').value = branch.Branch_City;
            document.getElementById('edit_state').value = branch.Branch_State;
            document.getElementById('edit_postal_code').value = branch.Branch_PostalCode;
            document.getElementById('edit_country').value = branch.Branch_Country;
            document.getElementById('edit_description').value = branch.Branch_Description;
            
            document.getElementById('editBranchModal').style.display = 'flex';
        }
    </script>
</body>
</html>
