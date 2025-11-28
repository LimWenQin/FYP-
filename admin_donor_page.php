<?php
// admin_donor_page.php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 包含数据库连接
include 'dataconnection.php';

// 处理添加捐赠者
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_donor'])) {
    // 获取表单数据
    $firstName = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lastName = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    $icNumber = mysqli_real_escape_string($conn, $_POST['ic_number']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $password = $_POST['password'];
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.(com|net|org|edu|gov)$/i', $email)) {
        $errorMessage = "Invalid email format. Email must end with .com, .net, .org, .edu or .gov";
    }
    // 验证密码强度 - 放宽特殊字符要求
    elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>])[A-Za-z\d!@#$%^&*()\-_=+{};:,<.>]{8,15}$/', $password)) {
        $errorMessage = "Password must be 8-15 characters long and include uppercase, lowercase, number and special character";
    }
    // 验证年龄
    else {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        
        if ($age < 18) {
            $errorMessage = "Donor must be at least 18 years old";
        }
        // 检查邮箱是否已存在
        else {
            $checkEmailSql = "SELECT Donor_ID FROM donor WHERE Donor_Email = '$email'";
            $emailResult = $conn->query($checkEmailSql);
            
            if ($emailResult && $emailResult->num_rows > 0) {
                $errorMessage = "Email already exists in the system";
            } else {
                // 哈希密码
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // 插入新捐赠者
                $sql = "INSERT INTO donor (Donor_FName, Donor_LName, Donor_ContactNumber, Donor_ICNumber, Donor_Email, Donor_Password, Donor_Address, Donor_DOB, Donor_Description) 
                        VALUES ('$firstName', '$lastName', '$contact', '$icNumber', '$email', '$hashedPassword', '$address', '$dob', '')";
                
                if ($conn->query($sql)) {
                    $successMessage = "Donor added successfully!";
                } else {
                    $errorMessage = "Error adding donor: " . $conn->error;
                }
            }
        }
    }
    
    // 如果有成功消息或错误消息，设置重定向参数
    if (!empty($successMessage)) {
        header("Location: admin_donor_page.php?success=" . urlencode($successMessage));
        exit();
    } elseif (!empty($errorMessage)) {
        header("Location: admin_donor_page.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// 分页设置
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // 确保页码至少为1
$start_from = ($page - 1) * $results_per_page;

// 处理搜索
$searchTerm = "";
$donors = [];
$total_donors = 0;

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $_GET['search'];
    $searchTerm = mysqli_real_escape_string($conn, $searchTerm);
    
    $sql = "SELECT * FROM donor 
            WHERE Donor_FName LIKE '%$searchTerm%' 
            OR Donor_LName LIKE '%$searchTerm%' 
            OR Donor_Email LIKE '%$searchTerm%' 
            OR Donor_ID LIKE '%$searchTerm%'
            ORDER BY Donor_FName
            LIMIT $start_from, $results_per_page";
    
    // 获取总记录数用于分页
    $count_sql = "SELECT COUNT(*) as total FROM donor 
                  WHERE Donor_FName LIKE '%$searchTerm%' 
                  OR Donor_LName LIKE '%$searchTerm%' 
                  OR Donor_Email LIKE '%$searchTerm%' 
                  OR Donor_ID LIKE '%$searchTerm%'";
    $count_result = $conn->query($count_sql);
    if ($count_result && $count_result->num_rows > 0) {
        $row = $count_result->fetch_assoc();
        $total_donors = $row['total'];
    }
} else {
    $sql = "SELECT * FROM donor ORDER BY Donor_FName LIMIT $start_from, $results_per_page";
    
    // 获取总记录数
    $count_result = $conn->query("SELECT COUNT(*) as total FROM donor");
    if ($count_result && $count_result->num_rows > 0) {
        $row = $count_result->fetch_assoc();
        $total_donors = $row['total'];
    }
}

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $donors[] = $row;
    }
}

// 计算总页数
$total_pages = ceil($total_donors / $results_per_page);

// 处理删除donor
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    $deleteSql = "DELETE FROM donor WHERE Donor_ID = $deleteId";
    
    if ($conn->query($deleteSql)) {
        $successMessage = "Donor deleted successfully!";
        // 刷新页面以显示更新后的列表
        header("Location: admin_donor_page.php?success=" . urlencode($successMessage));
        exit();
    } else {
        $errorMessage = "Error deleting donor: " . $conn->error;
    }
}

// 获取统计数据
function getTotalDonors($conn) {
    $sql = "SELECT COUNT(*) as total FROM donor";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getActiveDonors($conn) {
    $sql = "SELECT COUNT(DISTINCT d.Donor_ID) as total 
            FROM donor d 
            JOIN orders o ON d.Donor_ID = o.Donor_ID 
            WHERE o.Order_Created_At >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

$totalDonors = getTotalDonors($conn);
$activeDonors = getActiveDonors($conn);

// 获取管理员信息
$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];
$adminEmail = $_SESSION['admin_email'];
$adminPosition = "System Administrator";

// 关闭数据库连接
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Donor Management Specific Styles */
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
            background: rgba(40, 167, 69, 0.2);
            color: var(--success);
        }

        /* Donor Management Section */
        .donor-management {
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

        /* Donor Search */
        .donor-search {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--primary);
        }

        /* Donor Table */
        .donor-table {
            width: 100%;
            border-collapse: collapse;
        }

        .donor-table th, .donor-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }

        .donor-table th {
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
        }

        .donor-info {
            display: flex;
            align-items: center;
        }

        .donor-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin-right: 10px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .donor-details h4 {
            font-size: 14px;
            margin-bottom: 2px;
        }

        .donor-details p {
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

        .status-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
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
            max-width: 600px;
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

        /* Add New Donor Form */
        .add-donor-form {
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

        .password-input-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray);
        }

        .error-message {
            color: var(--danger);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        /* Form Guide Text */
        .form-guide {
            font-size: 11px;
            color: var(--gray);
            margin-top: 3px;
            display: block;
        }

        /* Password Requirements */
        .password-requirements {
            margin-top: 8px;
            font-size: 12px;
        }

        .requirement-item {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
            transition: color 0.3s;
        }

        .requirement-item.valid {
            color: var(--success);
        }

        .requirement-item.invalid {
            color: var(--gray);
        }

        .requirement-icon {
            margin-right: 5px;
            font-size: 10px;
            width: 12px;
            text-align: center;
        }

        .requirement-text {
            font-size: 11px;
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
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
        }

        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid var(--gray-light);
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
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

        .pagination-btn.disabled {
            background: var(--gray-light);
            color: var(--gray);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .pagination-info {
            font-size: 14px;
            color: var(--gray);
            margin: 0 15px;
        }

        /* Responsive Styles for Donor Page */
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
            
            .pagination {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .donor-table {
                display: block;
                overflow-x: auto;
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
                <li><a href="admin_donor_page.php" class="active"><i class="fas fa-users"></i> <span>Donor Management</span></a></li>
                <li><a href="staff_management_page.php"><i class="fas fa-user-tie"></i> <span>Staff Management</span></a></li>
                <li><a href="admin_management_page.php"><i class="fas fa-user-shield"></i> <span>Admin Management</span></a></li>
                <li><a href="branch_management_page.php"><i class="fas fa-map-marker-alt"></i> <span>Branch Management</span></a></li>
                <li><a href="activity_management.php"><i class="fas fa-calendar-alt"></i> <span>Activity Management</span></a></li>
                <li><a href="payment_management.php"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
            </ul>
            <div class="logout-container">
                <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div class="nav-left">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
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
                <div class="notification">
                    <i class="far fa-bell"></i>
                    <span class="notification-count">5</span>
                </div>
                <div class="user-profile">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($adminName); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($adminPosition); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Donor Management</h1>
                <p>Manage all donors, view details, and track donations.</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL DONORS</h3>
                        <h2><?php echo $totalDonors; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +12% from last month</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>ACTIVE DONORS</h3>
                        <h2><?php echo $activeDonors; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +8.2% from last month</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>

            <!-- Donor Management Section -->
            <div class="donor-management">
                <div class="section-header">
                    <h2>Donor List</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="openAddDonorModal()">
                            <i class="fas fa-plus"></i> Add New Donor
                        </button>
                        <button class="btn btn-success">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                    </div>
                </div>

                <!-- Search Form -->
                <form method="GET" action="admin_donor_page.php" class="donor-search">
                    <input type="text" name="search" class="search-input" placeholder="Search donors by name, ID or email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($searchTerm)): ?>
                        <a href="admin_donor_page.php" class="btn btn-danger">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Donor Table -->
                <table class="donor-table">
                    <thead>
                        <tr>
                            <th>DONOR NAME</th>
                            <th>CONTACT INFO</th>
                            <th>STATUS</th>
                            <th>LAST DONATION</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($donors) > 0): ?>
                            <?php foreach($donors as $donor): ?>
                            <tr>
                                <td>
                                    <div class="donor-info">
                                        <div class="donor-avatar"><?php echo substr($donor['Donor_FName'], 0, 1); ?></div>
                                        <div class="donor-details">
                                            <h4><?php echo htmlspecialchars($donor['Donor_FName'] . ' ' . $donor['Donor_LName']); ?></h4>
                                            <p>ID: <?php echo htmlspecialchars($donor['Donor_ID']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="donor-details">
                                        <p><?php echo htmlspecialchars($donor['Donor_Email']); ?></p>
                                        <p><?php echo htmlspecialchars($donor['Donor_ContactNumber']); ?></p>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    // Determine status based on last donation (simplified logic)
                                    $status = "Active";
                                    ?>
                                    <span class="status-badge status-active"><?php echo $status; ?></span>
                                </td>
                                <td>N/A</td>
                                <td>
                                    <div class="action-cell">
                                        <button class="action-btn edit-btn">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $donor['Donor_ID']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">
                                    <?php if (!empty($searchTerm)): ?>
                                        No donors found matching your search criteria.
                                    <?php else: ?>
                                        No donors found in the system.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination - Always Displayed -->
                <div class="pagination">
                    <!-- Previous Button -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" class="pagination-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <div class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                    </div>

                    <!-- Next Button -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" class="pagination-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Donor Modal -->
    <div class="modal" id="addDonorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Donor</h2>
                <button class="close-btn" onclick="closeAddDonorModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addDonorForm" action="admin_donor_page.php" method="POST" onsubmit="return validateForm()">
                    <input type="hidden" name="add_donor" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" required>
                            <span class="form-guide">Enter donor's first name (e.g., John)</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" required>
                            <span class="form-guide">Enter donor's last name (e.g., Smith)</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-input" required onblur="validateEmail()">
                            <span class="form-guide">Enter valid email ending with .com, .net, .org, .edu or .gov</span>
                            <div id="emailError" class="error-message">Please enter a valid email address (must end with .com, .net, .org, .edu or .gov)</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="contact">Contact Number</label>
                            <input type="text" id="contact" name="contact" class="form-input" required oninput="formatPhoneNumber()">
                            <span class="form-guide">Format: +60-XXX-XXXXXXXX (e.g., +60-12-34567890)</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="ic_number">IC Number</label>
                            <input type="text" id="ic_number" name="ic_number" class="form-input" required maxlength="14" oninput="formatICNumber()">
                            <span class="form-guide">Format: XXXXXX-XX-XXXX (e.g., 900101-01-1234)</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="form-input" required onchange="validateAge()">
                            <span class="form-guide">Donor must be at least 18 years old</span>
                            <div id="ageError" class="error-message">Donor must be at least 18 years old</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="address">Address</label>
                        <textarea id="address" name="address" class="form-input" rows="3" required></textarea>
                        <span class="form-guide">Enter complete address including street, city, and postcode</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="password-input-container">
                            <input type="password" id="password" name="password" class="form-input" required oninput="validatePasswordRequirements()">
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement-item invalid" id="lengthReq">
                                <span class="requirement-icon"><i class="fas fa-times"></i></span>
                                <span class="requirement-text">8-15 characters</span>
                            </div>
                            <div class="requirement-item invalid" id="uppercaseReq">
                                <span class="requirement-icon"><i class="fas fa-times"></i></span>
                                <span class="requirement-text">One uppercase letter</span>
                            </div>
                            <div class="requirement-item invalid" id="lowercaseReq">
                                <span class="requirement-icon"><i class="fas fa-times"></i></span>
                                <span class="requirement-text">One lowercase letter</span>
                            </div>
                            <div class="requirement-item invalid" id="numberReq">
                                <span class="requirement-icon"><i class="fas fa-times"></i></span>
                                <span class="requirement-text">One number</span>
                            </div>
                            <div class="requirement-item invalid" id="specialReq">
                                <span class="requirement-icon"><i class="fas fa-times"></i></span>
                                <span class="requirement-text">One special character (!@#$%^&*()-_=+{};:,<.>)</span>
                            </div>
                        </div>
                        <div id="passwordError" class="error-message">Password must meet all requirements</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <div class="password-input-container">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required oninput="validatePasswordMatch()">
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="confirmPasswordError" class="error-message">Passwords do not match</div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Donor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Change icon based on state
            const icon = menuToggle.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-bars';
            } else {
                icon.className = 'fas fa-times';
            }
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
                }, 5000); // Hide after 5 seconds
            }
            
            if (errorAlert) {
                setTimeout(() => {
                    errorAlert.style.opacity = '0';
                    setTimeout(() => {
                        errorAlert.style.display = 'none';
                    }, 300);
                }, 8000); // Error messages stay longer (8 seconds)
            }
        }

        // Call the function when page loads
        document.addEventListener('DOMContentLoaded', hideFloatingAlerts);

        // Modal Functions
        function openAddDonorModal() {
            document.getElementById('addDonorModal').style.display = 'flex';
        }

        function closeAddDonorModal() {
            document.getElementById('addDonorModal').style.display = 'none';
            resetForm();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addDonorModal');
            if (event.target === modal) {
                closeAddDonorModal();
            }
        }

        // Reset form when modal is closed
        function resetForm() {
            document.getElementById('addDonorForm').reset();
            hideAllErrors();
            resetPasswordRequirements();
        }

        // Hide all error messages
        function hideAllErrors() {
            document.getElementById('emailError').style.display = 'none';
            document.getElementById('ageError').style.display = 'none';
            document.getElementById('passwordError').style.display = 'none';
            document.getElementById('confirmPasswordError').style.display = 'none';
        }

        // Reset password requirements
        function resetPasswordRequirements() {
            const requirements = document.querySelectorAll('.requirement-item');
            requirements.forEach(req => {
                req.classList.remove('valid');
                req.classList.add('invalid');
                const icon = req.querySelector('.requirement-icon i');
                icon.className = 'fas fa-times';
            });
        }

        // Confirm Delete Function
        function confirmDelete(donorId) {
            if (confirm('Are you sure you want to delete this donor? This action cannot be undone.')) {
                window.location.href = 'admin_donor_page.php?delete_id=' + donorId;
            }
        }

        // Toggle password visibility
        function togglePasswordVisibility(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleButton = passwordField.nextElementSibling;
            const icon = toggleButton.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // Format phone number - updated for longer numbers
        function formatPhoneNumber() {
            const phoneInput = document.getElementById('contact');
            let value = phoneInput.value.replace(/\D/g, '');
            
            if (value.length > 0) {
                // Format as +60-XXX-XXXXXXXX
                if (value.startsWith('60')) {
                    value = value.substring(2);
                }
                
                if (value.length <= 3) {
                    phoneInput.value = '+60-' + value;
                } else if (value.length <= 11) {
                    phoneInput.value = '+60-' + value.substring(0, 3) + '-' + value.substring(3);
                } else {
                    phoneInput.value = '+60-' + value.substring(0, 3) + '-' + value.substring(3, 11);
                }
            }
        }

        // Format IC number
        function formatICNumber() {
            const icInput = document.getElementById('ic_number');
            let value = icInput.value.replace(/\D/g, '');
            
            if (value.length > 0) {
                // Format as XXXXXX-XX-XXXX
                if (value.length <= 6) {
                    icInput.value = value;
                } else if (value.length <= 8) {
                    icInput.value = value.substring(0, 6) + '-' + value.substring(6);
                } else {
                    icInput.value = value.substring(0, 6) + '-' + value.substring(6, 8) + '-' + value.substring(8, 12);
                }
            }
        }

        // Validate email format
        function validateEmail() {
            const emailInput = document.getElementById('email');
            const emailError = document.getElementById('emailError');
            const email = emailInput.value;
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov)$/i;
            
            if (email && !emailRegex.test(email)) {
                emailError.style.display = 'block';
                return false;
            } else {
                emailError.style.display = 'none';
                return true;
            }
        }

        // Validate age (must be at least 18)
        function validateAge() {
            const dobInput = document.getElementById('dob');
            const ageError = document.getElementById('ageError');
            
            if (dobInput.value) {
                const birthDate = new Date(dobInput.value);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                if (age < 18) {
                    ageError.style.display = 'block';
                    return false;
                } else {
                    ageError.style.display = 'none';
                    return true;
                }
            }
            
            return true;
        }

        // Validate password requirements with visual feedback
        function validatePasswordRequirements() {
            const password = document.getElementById('password').value;
            const passwordError = document.getElementById('passwordError');
            
            // Check each requirement
            const lengthValid = password.length >= 8 && password.length <= 15;
            const uppercaseValid = /[A-Z]/.test(password);
            const lowercaseValid = /[a-z]/.test(password);
            const numberValid = /\d/.test(password);
            const specialValid = /[!@#$%^&*()\-_=+{};:,<.>]/.test(password);
            
            // Update visual indicators
            updateRequirement('lengthReq', lengthValid);
            updateRequirement('uppercaseReq', uppercaseValid);
            updateRequirement('lowercaseReq', lowercaseValid);
            updateRequirement('numberReq', numberValid);
            updateRequirement('specialReq', specialValid);
            
            // Check if all requirements are met
            const allValid = lengthValid && uppercaseValid && lowercaseValid && numberValid && specialValid;
            
            if (!allValid && password) {
                passwordError.style.display = 'block';
                return false;
            } else {
                passwordError.style.display = 'none';
                return true;
            }
        }

        // Update individual requirement indicator
        function updateRequirement(reqId, isValid) {
            const reqElement = document.getElementById(reqId);
            const icon = reqElement.querySelector('.requirement-icon i');
            
            if (isValid) {
                reqElement.classList.remove('invalid');
                reqElement.classList.add('valid');
                icon.className = 'fas fa-check';
            } else {
                reqElement.classList.remove('valid');
                reqElement.classList.add('invalid');
                icon.className = 'fas fa-times';
            }
        }

        // Validate password match
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const confirmPasswordError = document.getElementById('confirmPasswordError');
            
            if (password && confirmPassword && password !== confirmPassword) {
                confirmPasswordError.style.display = 'block';
                return false;
            } else {
                confirmPasswordError.style.display = 'none';
                return true;
            }
        }

        // Main form validation
        function validateForm() {
            const isEmailValid = validateEmail();
            const isAgeValid = validateAge();
            const isPasswordValid = validatePasswordRequirements();
            const isPasswordMatch = validatePasswordMatch();
            
            return isEmailValid && isAgeValid && isPasswordValid && isPasswordMatch;
        }

        // Add event listeners for real-time validation
        document.getElementById('confirm_password').addEventListener('input', validatePasswordMatch);

        // Add active class to sidebar menu items on click
        document.querySelectorAll('.sidebar-menu a').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.sidebar-menu a').forEach(link => {
                    link.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>
