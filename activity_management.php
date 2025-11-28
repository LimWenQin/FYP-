<?php
// activity_management.php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 包含数据库连接
include 'dataconnection.php';

// 获取统计数据
function getTotalRaised($conn) {
    $sql = "SELECT SUM(Activity_GetAmount) as total FROM activity";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'] ? number_format($row['total'], 2) : '0.00';
    }
    return '0.00';
}

function getActiveEvents($conn) {
    $sql = "SELECT COUNT(*) as total FROM activity WHERE Activity_Status = 'Ongoing'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getUpcomingEvents($conn) {
    $sql = "SELECT COUNT(*) as total FROM activity WHERE Activity_Status = 'Upcoming'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getCompletedEvents($conn) {
    $sql = "SELECT COUNT(*) as total FROM activity WHERE Activity_Status = 'Completed'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// 获取活动数据
function getActivities($conn, $status = 'all', $search = '') {
    $sql = "SELECT a.*, b.Branch_Name 
            FROM activity a 
            LEFT JOIN branch b ON a.Branch_ID = b.Branch_ID 
            WHERE 1=1";
    
    if ($status !== 'all') {
        $sql .= " AND a.Activity_Status = '$status'";
    }
    
    if (!empty($search)) {
        $sql .= " AND (a.Activity_Name LIKE '%$search%' OR a.Activity_Details LIKE '%$search%')";
    }
    
    // 修复：使用正确的日期字段进行排序
    $sql .= " ORDER BY COALESCE(a.Activity_StartDate, a.Activity_Date) DESC";
    
    $result = $conn->query($sql);
    $activities = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
    }
    return $activities;
}

// 获取数据
$totalRaised = getTotalRaised($conn);
$activeEvents = getActiveEvents($conn);
$upcomingEvents = getUpcomingEvents($conn);
$completedEvents = getCompletedEvents($conn);

// 获取筛选参数
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// 获取活动列表
$activities = getActivities($conn, $statusFilter, $searchQuery);

// 获取管理员信息
$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];
$adminEmail = $_SESSION['admin_email'];

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

        /* Search and Filter Section */
        .search-filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .search-container {
            display: flex;
            margin-bottom: 20px;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: var(--gray-light);
            border-radius: 8px;
            padding: 10px 15px;
            flex: 1;
            margin-right: 15px;
        }

        .search-bar input {
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
            margin-left: 10px;
            font-size: 14px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
        }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 20px;
            background: var(--gray-light);
            color: var(--dark);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .filter-tab.active {
            background: var(--primary);
            color: white;
        }

        /* Activity Cards */
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .activity-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }

        .activity-card:hover {
            transform: translateY(-5px);
        }

        .activity-image {
            height: 180px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }

        .activity-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .activity-content {
            padding: 20px;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .activity-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .activity-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-ongoing {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-upcoming {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        .status-completed {
            background: rgba(108, 117, 125, 0.1);
            color: var(--gray);
        }

        .activity-description {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .activity-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }

        .activity-detail {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: var(--dark);
        }

        .activity-detail i {
            margin-right: 8px;
            width: 16px;
            color: var(--primary);
        }

        .activity-progress {
            margin-bottom: 15px;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .progress-bar {
            height: 6px;
            background: var(--gray-light);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
        }

        .activity-actions {
            display: flex;
            justify-content: space-between;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #e07575;
        }

        .btn-secondary {
            background: var(--gray-light);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: #d8d8d8;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--gray-light);
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .empty-state p {
            color: var(--gray);
            margin-bottom: 20px;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .activities-grid {
                grid-template-columns: 1fr;
            }
            
            .search-container {
                flex-direction: column;
            }
            
            .search-bar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .filter-tabs {
                overflow-x: auto;
                padding-bottom: 10px;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
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
                            <div class="user-role">System Administrator</div>
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
                <p>Manage your campaigns, track progress, and organize events.</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL RAISED</h3>
                        <h2>$<?php echo $totalRaised; ?></h2>
                        <p><i class="fas fa-arrow-up"></i> +12.5%</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>ACTIVE EVENTS</h3>
                        <h2><?php echo $activeEvents; ?></h2>
                        <p><i class="fas fa-calendar-check"></i> Ongoing</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>UPCOMING</h3>
                        <h2><?php echo $upcomingEvents; ?></h2>
                        <p><i class="fas fa-clock"></i> Scheduled</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>COMPLETED</h3>
                        <h2><?php echo $completedEvents; ?></h2>
                        <p><i class="fas fa-check-circle"></i> Finished</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-filter-section">
                <form method="GET" action="activity_management.php">
                    <div class="search-container">
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search activities..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                    <div class="filter-tabs">
                        <a href="activity_management.php?status=all&search=<?php echo urlencode($searchQuery); ?>" class="filter-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
                        <a href="activity_management.php?status=Ongoing&search=<?php echo urlencode($searchQuery); ?>" class="filter-tab <?php echo $statusFilter === 'Ongoing' ? 'active' : ''; ?>">Ongoing</a>
                        <a href="activity_management.php?status=Upcoming&search=<?php echo urlencode($searchQuery); ?>" class="filter-tab <?php echo $statusFilter === 'Upcoming' ? 'active' : ''; ?>">Upcoming</a>
                        <a href="activity_management.php?status=Completed&search=<?php echo urlencode($searchQuery); ?>" class="filter-tab <?php echo $statusFilter === 'Completed' ? 'active' : ''; ?>">Completed</a>
                    </div>
                </form>
            </div>

            <!-- Activities Grid -->
            <?php if (count($activities) > 0): ?>
                <div class="activities-grid">
                    <?php foreach($activities as $activity): ?>
                        <?php
                        // 确定状态类
                        $statusClass = '';
                        if ($activity['Activity_Status'] === 'Ongoing') {
                            $statusClass = 'status-ongoing';
                        } elseif ($activity['Activity_Status'] === 'Upcoming') {
                            $statusClass = 'status-upcoming';
                        } else {
                            $statusClass = 'status-completed';
                        }
                        
                        // 计算进度百分比
                        $targetAmount = isset($activity['Activity_TargetAmount']) ? $activity['Activity_TargetAmount'] : 10000;
                        $raisedAmount = $activity['Activity_GetAmount'] ? $activity['Activity_GetAmount'] : 0;
                        $progressPercentage = $targetAmount > 0 ? min(100, ($raisedAmount / $targetAmount) * 100) : 0;
                        
                        // 格式化日期显示
                        $dateDisplay = '';
                        if (!empty($activity['Activity_StartDate']) && !empty($activity['Activity_EndDate'])) {
                            $dateDisplay = date('m/d/Y', strtotime($activity['Activity_StartDate'])) . ' - ' . date('m/d/Y', strtotime($activity['Activity_EndDate']));
                        } else {
                            $dateDisplay = date('m/d/Y', strtotime($activity['Activity_Date']));
                        }
                        
                        // 活动名称
                        $activityName = isset($activity['Activity_Name']) ? $activity['Activity_Name'] : 'Activity';
                        ?>
                        <div class="activity-card">
                            <div class="activity-image">
                                <?php if (!empty($activity['Activity_Picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($activity['Activity_Picture']); ?>" alt="<?php echo htmlspecialchars($activityName); ?>">
                                <?php else: ?>
                                    <i class="fas fa-calendar-alt"></i>
                                <?php endif; ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-header">
                                    <div>
                                        <h3 class="activity-title"><?php echo htmlspecialchars($activityName); ?></h3>
                                    </div>
                                    <span class="activity-status <?php echo $statusClass; ?>"><?php echo $activity['Activity_Status']; ?></span>
                                </div>
                                <p class="activity-description"><?php echo htmlspecialchars($activity['Activity_Details']); ?></p>
                                
                                <div class="activity-details">
                                    <div class="activity-detail">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo $dateDisplay; ?></span>
                                    </div>
                                    <?php if (!empty($activity['Branch_Name'])): ?>
                                        <div class="activity-detail">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($activity['Branch_Name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($activity['Activity_Status'] === 'Ongoing' || $activity['Activity_Status'] === 'Completed'): ?>
                                    <div class="activity-progress">
                                        <div class="progress-info">
                                            <span>Raised: $<?php echo number_format($raisedAmount, 2); ?></span>
                                            <span>Goal: $<?php echo number_format($targetAmount, 2); ?></span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $progressPercentage; ?>%"></div>
                                        </div>
                                        <div class="progress-info">
                                            <span><?php echo number_format($progressPercentage, 0); ?>% Funded</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="activity-actions">
                                    <button class="btn btn-secondary">View Details</button>
                                    <button class="btn btn-primary">Manage</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Activities Found</h3>
                    <p>No activities match your current search criteria.</p>
                    <button class="btn btn-primary" onclick="window.location.href='activity_management.php'">Clear Filters</button>
                </div>
            <?php endif; ?>
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
