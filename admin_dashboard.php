<?php
// admin_dashboard.php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 包含数据库连接
include 'dataconnection.php';

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

function getTotalDonations($conn) {
    $sql = "SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus = 'Completed'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'] ? number_format($row['total'], 2) : '0.00';
    }
    return '0.00';
}

function getTotalBranches($conn) {
    $sql = "SELECT COUNT(*) as total FROM branch";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getTotalActivities($conn) {
    $sql = "SELECT COUNT(*) as total FROM activity";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getRecentDonors($conn) {
    $sql = "SELECT d.Donor_FName, d.Donor_LName, d.Donor_Email, 
                   o.Order_Amount, o.Order_Created_At, 
                   CASE WHEN o.Order_Created_At >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Active' ELSE 'Inactive' END as status
            FROM donor d
            JOIN orders o ON d.Donor_ID = o.Donor_ID
            ORDER BY o.Order_Created_At DESC
            LIMIT 3";
    
    $result = $conn->query($sql);
    $donors = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $donors[] = $row;
        }
    }
    return $donors;
}

function getActiveStaff($conn) {
    $sql = "SELECT COUNT(*) as total FROM staff";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getAdminCount($conn) {
    $sql = "SELECT COUNT(*) as total FROM admin";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getDonationTrends($conn) {
    $sql = "SELECT DAYNAME(Order_Created_At) as day, SUM(Order_Amount) as amount
            FROM orders 
            WHERE Order_Created_At >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND Order_PaymentStatus = 'Completed'
            GROUP BY DAYNAME(Order_Created_At)
            ORDER BY Order_Created_At";
    
    $result = $conn->query($sql);
    $trends = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $trends[$row['day']] = $row['amount'];
        }
    }
    
    // 确保7天都有数据，缺失的填0
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $data = [];
    foreach($days as $day) {
        $data[] = isset($trends[$day]) ? $trends[$day] : 0;
    }
    
    return $data;
}

// 获取数据
$totalDonors = getTotalDonors($conn);
$totalDonations = getTotalDonations($conn);
$totalBranches = getTotalBranches($conn);
$totalActivities = getTotalActivities($conn);
$recentDonors = getRecentDonors($conn);
$activeStaff = getActiveStaff($conn);
$adminCount = getAdminCount($conn);
$donationTrends = getDonationTrends($conn);

// 获取管理员信息
$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];
$adminEmail = $_SESSION['admin_email'];
$adminPosition = "System Administrator";

// 获取管理员头像
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

// 关闭数据库连接
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Dashboard Specific Styles */
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

        .stat-card:nth-child(3) .stat-icon {
            background: rgba(23, 162, 184, 0.2);
            color: var(--info);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: rgba(255, 193, 7, 0.2);
            color: var(--warning);
        }

        /* Charts and Tables Section */
        .charts-tables {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container, .recent-donors, .staff-overview {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
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

        .section-header a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }

        .chart-wrapper {
            height: 250px;
            position: relative;
        }

        /* Recent Donors Table */
        .recent-donors table {
            width: 100%;
            border-collapse: collapse;
        }

        .recent-donors th, .recent-donors td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }

        .recent-donors th {
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

        /* Staff Overview */
        .staff-overview {
            grid-column: 1 / -1;
        }

        .staff-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .staff-stat {
            display: flex;
            align-items: center;
            padding: 15px;
            background: var(--light);
            border-radius: 8px;
        }

        .staff-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }

        .staff-stat:nth-child(1) .staff-stat-icon {
            background: rgba(242, 133, 133, 0.2);
            color: var(--primary);
        }

        .staff-stat:nth-child(2) .staff-stat-icon {
            background: rgba(23, 162, 184, 0.2);
            color: var(--info);
        }

        .staff-stat-info h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .staff-stat-info p {
            font-size: 14px;
            color: var(--gray);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .action-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            background: rgba(242, 133, 133, 0.2);
            color: var(--primary);
        }

        .action-card h3 {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .action-card p {
            font-size: 14px;
            color: var(--gray);
        }

        /* Time Filter Buttons */
        .time-filter {
            background: none;
            border: 1px solid var(--gray-light);
            padding: 5px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            margin-left: 10px;
            transition: all 0.3s;
        }

        .time-filter.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Dropdown Menu Styles */
        .user-profile {
            position: relative;
            margin-left: 20px;
            cursor: pointer;
        }

        .user-profile-with-avatar {
            display: flex;
            align-items: center;
            padding: 8px 15px;
            border-radius: 8px;
            transition: background 0.3s;
            cursor: pointer;
        }

        .user-profile-with-avatar:hover {
            background: var(--gray-light);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            min-width: 200px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 10px 0;
            z-index: 1001;
            display: none;
        }

        .user-profile-dropdown.show {
            display: block;
        }

        .user-profile-dropdown a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: background 0.3s;
        }

        .user-profile-dropdown a:hover {
            background: var(--gray-light);
        }

        .user-profile-dropdown i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
        }

        .user-profile-dropdown .divider {
            height: 1px;
            background: var(--gray-light);
            margin: 5px 0;
        }

        /* Notification Dropdown Styles */
        .notification {
            position: relative;
            margin-left: 20px;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            width: 350px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            z-index: 1001;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 16px;
            font-weight: 600;
        }

        .notification-header a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-light);
            cursor: pointer;
            transition: background 0.3s;
        }

        .notification-item:hover {
            background: var(--gray-light);
        }

        .notification-item.unread {
            background: rgba(242, 133, 133, 0.05);
        }

        .notification-content {
            display: flex;
            align-items: flex-start;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .notification-icon.success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .notification-icon.warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .notification-icon.info {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        .notification-icon.danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .notification-details h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .notification-details p {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 4px;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 12px;
            color: var(--gray);
        }

        .notification-footer {
            padding: 15px 20px;
            text-align: center;
            border-top: 1px solid var(--gray-light);
        }

        .notification-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }

        /* Responsive Styles for Dashboard */
        @media (max-width: 992px) {
            .charts-tables {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .staff-stats {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .notification-dropdown {
                width: 300px;
                right: -50px;
            }
        }

        @media (max-width: 576px) {
            .stats-cards, .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .notification-dropdown {
                width: 280px;
                right: -80px;
            }
            
            .user-profile-dropdown {
                right: -50px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar collapsed" id="sidebar">
        <div class="sidebar-menu">
            <ul>
                <li><a href="admin_dashboard.php" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="admin_donor_page.php"><i class="fas fa-users"></i> <span>Donor Management</span></a></li>
                <li><a href="staff_management_page.php"><i class="fas fa-user-tie"></i> <span>Staff Management</span></a></li>
                <li><a href="admin_management_page.php"><i class="fas fa-user-shield"></i> <span>Admin Management</span></a></li>
                <li><a href="branch_management_page.php"><i class="fas fa-map-marker-alt"></i> <span>Branch Management</span></a></li>
                <li><a href="activity_management.php"><i class="fas fa-calendar-alt"></i> <span>Activity Management</span></a></li>
                <li><a href="payment_management.php"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
            </ul>
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
                <h1>System Overview</h1>
                <p>Welcome back, <?php echo htmlspecialchars($adminName); ?>, here is what's happening today.</p>
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
                        <h3>TOTAL DONATIONS</h3>
                        <h2>RM <?php echo $totalDonations; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +8.2% from last month</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>BRANCH LOCATIONS</h3>
                        <h2><?php echo $totalBranches; ?></h2>
                        <p><i class="fas fa-map-marker-alt"></i> All active</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL ACTIVITIES</h3>
                        <h2><?php echo $totalActivities; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> 3% from last month</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>

            <!-- Charts and Tables Section -->
            <div class="charts-tables">
                <!-- Donation Trends Chart -->
                <div class="chart-container">
                    <div class="section-header">
                        <h2>Donation Trends</h2>
                        <div>
                            <button class="time-filter active" data-period="weekly">Weekly</button>
                            <button class="time-filter" data-period="monthly">Monthly</button>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="donationChart"></canvas>
                    </div>
                </div>

                <!-- Recent Donors -->
                <div class="recent-donors">
                    <div class="section-header">
                        <h2>Recent Donors</h2>
                        <a href="admin_donor_page.php">View All</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>DONOR NAME</th>
                                <th>STATUS</th>
                                <th>LAST DONATION</th>
                                <th>TOTAL DONATED</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentDonors) > 0): ?>
                                <?php foreach($recentDonors as $donor): ?>
                                <tr>
                                    <td>
                                        <div class="donor-info">
                                            <div class="donor-avatar"><?php echo substr($donor['Donor_FName'], 0, 1); ?></div>
                                            <div class="donor-details">
                                                <h4><?php echo htmlspecialchars($donor['Donor_FName'] . ' ' . $donor['Donor_LName']); ?></h4>
                                                <p><?php echo htmlspecialchars($donor['Donor_Email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="status-badge <?php echo $donor['status'] === 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $donor['status']; ?></span></td>
                                    <td><?php echo date('M j', strtotime($donor['Order_Created_At'])); ?></td>
                                    <td>RM <?php echo number_format($donor['Order_Amount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 20px;">No recent donors found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Staff Overview -->
                <div class="staff-overview">
                    <div class="section-header">
                        <h2>Staff Overview</h2>
                        <a href="staff_management_page.php">Manage Staff</a>
                    </div>
                    <div class="staff-stats">
                        <div class="staff-stat">
                            <div class="staff-stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="staff-stat-info">
                                <h3><?php echo $activeStaff; ?></h3>
                                <p>Active Staff</p>
                            </div>
                        </div>
                        <div class="staff-stat">
                            <div class="staff-stat-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="staff-stat-info">
                                <h3><?php echo $adminCount; ?></h3>
                                <p>Admins</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="action-card" onclick="window.location.href='#'">
                    <div class="action-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <h3>Download Report</h3>
                    <p>Generate and download system reports</p>
                </div>
                <div class="action-card" onclick="window.location.href='#'">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h3>New Donation</h3>
                    <p>Record a new donation</p>
                </div>
                <div class="action-card" onclick="window.location.href='admin_donor_page.php'">
                    <div class="action-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Add Donor</h3>
                    <p>Register a new donor</p>
                </div>
                <div class="action-card" onclick="window.location.href='activity_management.php'">
                    <div class="action-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <h3>Schedule Activity</h3>
                    <p>Plan a new donation activity</p>
                </div>
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

        // Chart.js for Donation Trends
        const ctx = document.getElementById('donationChart').getContext('2d');
        const donationChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Income (RM)',
                    data: <?php echo json_encode($donationTrends); ?>,
                    borderColor: '#f28585',
                    backgroundColor: 'rgba(242, 133, 133, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return 'RM ' + value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Time filter buttons
        document.querySelectorAll('.time-filter').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.time-filter').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // In a real application, you would update the chart data here via AJAX
                // For now, we'll just update the chart with sample data
                const period = this.getAttribute('data-period');
                
                if (period === 'monthly') {
                    // Update chart for monthly view
                    donationChart.data.labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
                    donationChart.data.datasets[0].data = [12500, 15200, 11000, 13500];
                } else {
                    // Update chart for weekly view
                    donationChart.data.labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    donationChart.data.datasets[0].data = <?php echo json_encode($donationTrends); ?>;
                }
                donationChart.update();
            });
        });

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
                },
                {
                    type: 'danger',
                    icon: 'fas fa-times-circle',
                    title: 'Payment Failed',
                    message: 'A recurring donation payment failed',
                    time: '1 day ago',
                    unread: false
                },
                {
                    type: 'info',
                    icon: 'fas fa-calendar-check',
                    title: 'Activity Reminder',
                    message: 'Charity event starts tomorrow',
                    time: '2 days ago',
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
            
            // Update notification count
            const notificationCount = document.querySelector('.notification-count');
            notificationCount.textContent = '0';
            notificationCount.style.display = 'none';
            
            // Close dropdown
            notificationMenu.classList.remove('show');
        }

        // Load notifications when page loads
        document.addEventListener('DOMContentLoaded', loadNotifications);
    </script>
</body>
</html>
