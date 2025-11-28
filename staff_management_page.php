<?php
// staff_management_page.php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 包含数据库连接
include 'dataconnection.php';

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

// 处理搜索
$search = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
}

// 获取staff数据
$sql = "SELECT * FROM staff";
if (!empty($search)) {
    $sql .= " WHERE Staff_FName LIKE '%$search%' OR 
              Staff_LName LIKE '%$search%' OR 
              Staff_Email LIKE '%$search%' OR 
              Staff_ICNumber LIKE '%$search%'";
}
$sql .= " ORDER BY Staff_ID ASC";

$result = $conn->query($sql);
$staffMembers = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $staffMembers[] = $row;
    }
}

// 获取统计数据
function getTotalStaff($conn) {
    $sql = "SELECT COUNT(*) as total FROM staff";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getActiveStaff($conn) {
    $sql = "SELECT COUNT(*) as total FROM staff WHERE Staff_Status = 'Active'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getTotalRoles($conn) {
    $sql = "SELECT COUNT(DISTINCT Staff_Role) as total FROM staff";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

$totalStaff = getTotalStaff($conn);
$activeStaff = getActiveStaff($conn);
$inactiveStaff = $totalStaff - $activeStaff;
$totalRoles = getTotalRoles($conn);

// 关闭数据库连接
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Staff Management Specific Styles */
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
            background: rgba(220, 53, 69, 0.2);
            color: var(--danger);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: rgba(23, 162, 184, 0.2);
            color: var(--info);
        }

        /* Search Bar */
        .search-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }

        .search-input:focus {
            border-color: var(--primary);
        }

        .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            margin-left: 10px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .search-btn:hover {
            background: var(--primary-light);
        }

        /* Staff Table */
        .staff-table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .staff-table {
            width: 100%;
            border-collapse: collapse;
        }

        .staff-table th, .staff-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }

        .staff-table th {
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
            background: var(--light);
        }

        .staff-info {
            display: flex;
            align-items: center;
        }

        .staff-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            overflow: hidden;
        }

        .staff-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .staff-details h4 {
            font-size: 14px;
            margin-bottom: 2px;
        }

        .staff-details p {
            font-size: 12px;
            color: var(--gray);
        }

        .contact-info {
            display: flex;
            flex-direction: column;
        }

        .contact-info a {
            color: var(--dark);
            text-decoration: none;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .contact-info a:hover {
            color: var(--primary);
        }

        .ic-dob {
            display: flex;
            flex-direction: column;
        }

        .ic-dob span {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .role-status {
            display: flex;
            flex-direction: column;
        }

        .role {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .status {
            display: flex;
            align-items: center;
            font-size: 12px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-active .status-dot {
            background: var(--success);
        }

        .status-inactive .status-dot {
            background: var(--danger);
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .action-btn:hover {
            background: var(--gray-light);
        }

        .edit-btn {
            color: var(--info);
        }

        .delete-btn {
            color: var(--danger);
        }

        /* Pagination */
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
        }

        .pagination-btn:hover {
            background: var(--light);
        }

        .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Add Staff Button */
        .add-staff-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
            margin-bottom: 20px;
        }

        .add-staff-btn:hover {
            background: var(--primary-light);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .staff-table {
                min-width: 700px;
            }
            
            .actions {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .pagination {
                flex-direction: column;
                gap: 15px;
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
                <li><a href="staff_management_page.php" class="active"><i class="fas fa-user-tie"></i> <span>Staff Management</span></a></li>
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

        <!-- Staff Management Content -->
        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Staff Management</h1>
                <p>Manage your staff members, roles, and permissions.</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>ACTIVE STAFF</h3>
                        <h2><?php echo $activeStaff; ?></h2>
                        <p><i class="fas fa-user-check"></i> Currently working</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>INACTIVE STAFF</h3>
                        <h2><?php echo $inactiveStaff; ?></h2>
                        <p><i class="fas fa-user-slash"></i> Not active</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL STAFF</h3>
                        <h2><?php echo $totalStaff; ?></h2>
                        <p><i class="fas fa-users"></i> All staff members</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL ROLES</h3>
                        <h2><?php echo $totalRoles; ?></h2>
                        <p><i class="fas fa-user-tag"></i> Different roles</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-tag"></i>
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="search-container">
                <form class="search-form" method="GET" action="staff_management_page.php">
                    <input type="text" name="search" class="search-input" placeholder="Search by name, email or IC..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>

            <!-- Add Staff Button -->
            <button class="add-staff-btn" onclick="window.location.href='add_staff.php'">
                <i class="fas fa-plus"></i> Add New Staff
            </button>

            <!-- Staff Table -->
            <div class="staff-table-container">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>NAME</th>
                            <th>CONTACT INFO</th>
                            <th>IC / DOB</th>
                            <th>ROLE & STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staffMembers) > 0): ?>
                            <?php foreach($staffMembers as $staff): ?>
                            <tr>
                                <td>
                                    <div class="staff-info">
                                        <div class="staff-avatar">
                                            <?php if (!empty($staff['Staff_ProfilePicture'])): ?>
                                                <img src="<?php echo htmlspecialchars($staff['Staff_ProfilePicture']); ?>" alt="Profile Picture">
                                            <?php else: ?>
                                                <?php echo substr($staff['Staff_FName'], 0, 1); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="staff-details">
                                            <h4><?php echo htmlspecialchars($staff['Staff_FName'] . ' ' . $staff['Staff_LName']); ?></h4>
                                            <p>ID: #<?php echo str_pad($staff['Staff_ID'], 4, '0', STR_PAD_LEFT); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <a href="mailto:<?php echo htmlspecialchars($staff['Staff_Email']); ?>">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['Staff_Email']); ?>
                                        </a>
                                        <a href="tel:<?php echo htmlspecialchars($staff['Staff_ContactNumber']); ?>">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($staff['Staff_ContactNumber']); ?>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <div class="ic-dob">
                                        <span><?php echo htmlspecialchars($staff['Staff_ICNumber']); ?></span>
                                        <span><?php echo date('Y-m-d', strtotime($staff['Staff_DOB'])); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="role-status">
                                        <div class="role"><?php echo htmlspecialchars($staff['Staff_Role'] ?? 'Staff'); ?></div>
                                        <div class="status <?php echo ($staff['Staff_Status'] == 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                            <span class="status-dot"></span>
                                            <?php echo htmlspecialchars($staff['Staff_Status'] ?? 'Active'); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="action-btn edit-btn" title="Edit Staff">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete-btn" title="Delete Staff">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 30px;">
                                    No staff members found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <div class="pagination-info">
                        Showing 1 to <?php echo count($staffMembers); ?> of <?php echo $totalStaff; ?> results
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn">Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">3</button>
                        <button class="pagination-btn">Next</button>
                    </div>
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
                    icon: 'fas fa-user-plus',
                    title: 'New Staff Added',
                    message: 'A new staff member has been added to the system',
                    time: '2 hours ago',
                    unread: true
                },
                {
                    type: 'info',
                    icon: 'fas fa-user-edit',
                    title: 'Staff Profile Updated',
                    message: 'Staff profile information has been updated',
                    time: '1 day ago',
                    unread: true
                },
                {
                    type: 'warning',
                    icon: 'fas fa-exclamation-triangle',
                    title: 'Staff Status Changed',
                    message: 'A staff member status has been changed to inactive',
                    time: '2 days ago',
                    unread: false
                },
                {
                    type: 'info',
                    icon: 'fas fa-calendar-check',
                    title: 'Staff Meeting Reminder',
                    message: 'Monthly staff meeting scheduled for tomorrow',
                    time: '3 days ago',
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

        // Edit and Delete button functionality
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const staffRow = this.closest('tr');
                const staffName = staffRow.querySelector('.staff-details h4').textContent;
                alert(`Edit staff: ${staffName}`);
                // In a real application, you would redirect to an edit page or open a modal
            });
        });

        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const staffRow = this.closest('tr');
                const staffName = staffRow.querySelector('.staff-details h4').textContent;
                
                if (confirm(`Are you sure you want to delete ${staffName}?`)) {
                    // In a real application, you would send an AJAX request to delete the staff
                    staffRow.remove();
                    alert(`${staffName} has been deleted.`);
                }
            });
        });
    </script>
</body>
</html>
