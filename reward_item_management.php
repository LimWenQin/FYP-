<?php
// reward_item_management.php
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

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reward Items Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Reward Management Specific Styles */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
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
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 15px;
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
            background: rgba(255, 193, 7, 0.2);
            color: var(--warning);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: rgba(23, 162, 184, 0.2);
            color: var(--info);
        }

        /* Search and Filter Section */
        .search-filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-container {
            display: flex;
            align-items: center;
            background: var(--gray-light);
            border-radius: 20px;
            padding: 8px 15px;
            width: 300px;
        }

        .search-container input {
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
            margin-left: 10px;
        }

        .filter-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-filter select {
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid var(--gray-light);
            background: white;
            outline: none;
            cursor: pointer;
        }

        .add-item-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }

        .add-item-btn:hover {
            background: #e07575;
        }

        /* Items Table */
        .items-table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th, .items-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }

        .items-table th {
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
            position: sticky;
            top: 0;
            background: white;
        }

        .item-info {
            display: flex;
            align-items: center;
        }

        .item-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            margin-right: 15px;
            background: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details h4 {
            font-size: 14px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .item-details p {
            font-size: 12px;
            color: var(--gray);
            line-height: 1.4;
        }

        .points-badge {
            display: inline-block;
            padding: 5px 10px;
            background: rgba(242, 133, 133, 0.1);
            color: var(--primary);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .stock-info {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stock-low {
            color: var(--danger);
            font-weight: 600;
        }

        .stock-ok {
            color: var(--success);
            font-weight: 600;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-inactive {
            background: rgba(108, 117, 125, 0.1);
            color: var(--gray);
        }

        .status-low-stock {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.3s;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            font-weight: 600;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .search-filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-container {
                width: 100%;
            }
            
            .filter-container {
                width: 100%;
                justify-content: space-between;
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .items-table {
                min-width: 700px;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .status-filter, .add-item-btn {
                width: 100%;
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
                <li><a href="activity_management.php"><i class="fas fa-calendar-alt"></i> <span>Activity Management</span></a></li>
                <li><a href="payment_management.php"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
                <li><a href="reward_item_management.php" class="active"><i class="fas fa-gift"></i> <span>Reward Items</span></a></li>
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
                <h1>Reward Items</h1>
                <p>Manage handicrafts and rewards redeemable by donor points.</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3>TOTAL ITEMS</h3>
                        <h2>8</h2>
                        <p><i class="fas fa-arrow-up"></i> +2 from last month</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>ACTIVE REWARDS</h3>
                        <h2>6</h2>
                        <p><i class="fas fa-info-circle"></i> 2 inactive</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>LOW STOCK</h3>
                        <h2>3</h2>
                        <p><i class="fas fa-info-circle"></i> Needs restocking</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <h3>POINTS VALUE</h3>
                        <h2>8,610</h2>
                        <p><i class="fas fa-info-circle"></i> Total points required</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-filter-section">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search Items...">
                </div>
                <div class="filter-container">
                    <div class="status-filter">
                        <select>
                            <option>All Status</option>
                            <option>Active</option>
                            <option>Inactive</option>
                            <option>Low Stock</option>
                        </select>
                    </div>
                    <button class="add-item-btn">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>

            <!-- Items Table -->
            <div class="items-table-container">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>ITEM DETAILS</th>
                            <th>POINTS</th>
                            <th>STOCK</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="item-info">
                                    <div class="item-image">
                                        <i class="fas fa-image" style="color: #ccc;"></i>
                                    </div>
                                    <div class="item-details">
                                        <h4>Handwoven Rattan Basket</h4>
                                        <p>A beautiful, durable basket woven by Indonesian artisans using natural materials.</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="points-badge">150 pts</span></td>
                            <td>
                                <div class="stock-info">
                                    <span>25</span>
                                    <span class="stock-ok">In Stock</span>
                                </div>
                            </td>
                            <td><span class="status-badge status-active">Active</span></td>
                            <td>
                                <div class="action-buttons">
                                    <div class="action-btn edit-btn">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <div class="action-btn delete-btn">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="item-info">
                                    <div class="item-image">
                                        <i class="fas fa-image" style="color: #ccc;"></i>
                                    </div>
                                    <div class="item-details">
                                        <h4>Ceramic Flower Vase</h4>
                                        <p>Hand-painted ceramic vase with traditional patterns, perfect for home decoration.</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="points-badge">200 pts</span></td>
                            <td>
                                <div class="stock-info">
                                    <span>8</span>
                                    <span class="stock-low">Low Stock</span>
                                </div>
                            </td>
                            <td><span class="status-badge status-low-stock">Low Stock</span></td>
                            <td>
                                <div class="action-buttons">
                                    <div class="action-btn edit-btn">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <div class="action-btn delete-btn">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="item-info">
                                    <div class="item-image">
                                        <i class="fas fa-image" style="color: #ccc;"></i>
                                    </div>
                                    <div class="item-details">
                                        <h4>Embroidered Tote Bag</h4>
                                        <p>Cotton tote bag featuring intricate hand-embroidered designs, eco-friendly and stylish.</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="points-badge">120 pts</span></td>
                            <td>
                                <div class="stock-info">
                                    <span>15</span>
                                    <span class="stock-ok">In Stock</span>
                                </div>
                            </td>
                            <td><span class="status-badge status-active">Active</span></td>
                            <td>
                                <div class="action-buttons">
                                    <div class="action-btn edit-btn">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <div class="action-btn delete-btn">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="item-info">
                                    <div class="item-image">
                                        <i class="fas fa-image" style="color: #ccc;"></i>
                                    </div>
                                    <div class="item-details">
                                        <h4>Wooden Carved Elephant</h4>
                                        <p>Small decorative elephant carved from Indonesian teak wood, symbolic and artistic.</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="points-badge">180 pts</span></td>
                            <td>
                                <div class="stock-info">
                                    <span>5</span>
                                    <span class="stock-low">Low Stock</span>
                                </div>
                            </td>
                            <td><span class="status-badge status-low-stock">Low Stock</span></td>
                            <td>
                                <div class="action-buttons">
                                    <div class="action-btn edit-btn">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <div class="action-btn delete-btn">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="item-info">
                                    <div class="item-image">
                                        <i class="fas fa-image" style="color: #ccc;"></i>
                                    </div>
                                    <div class="item-details">
                                        <h4>Batik Scarf</h4>
                                        <p>Traditional Indonesian batik scarf with vibrant colors and intricate patterns.</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="points-badge">100 pts</span></td>
                            <td>
                                <div class="stock-info">
                                    <span>3</span>
                                    <span class="stock-low">Low Stock</span>
                                </div>
                            </td>
                            <td><span class="status-badge status-low-stock">Low Stock</span></td>
                            <td>
                                <div class="action-buttons">
                                    <div class="action-btn edit-btn">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <div class="action-btn delete-btn">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="item-info">
                                    <div class="item-image">
                                        <i class="fas fa-image" style="color: #ccc;"></i>
                                    </div>
                                    <div class="item-details">
                                        <h4>Silver Jewelry Set</h4>
                                        <p>Elegant silver jewelry set including necklace and earrings, handcrafted by local artisans.</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="points-badge">300 pts</span></td>
                            <td>
                                <div class="stock-info">
                                    <span>12</span>
                                    <span class="stock-ok">In Stock</span>
                                </div>
                            </td>
                            <td><span class="status-badge status-active">Active</span></td>
                            <td>
                                <div class="action-buttons">
                                    <div class="action-btn edit-btn">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <div class="action-btn delete-btn">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
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

        // Add item button functionality
        document.querySelector('.add-item-btn').addEventListener('click', function() {
            alert('Add Item functionality would open a form to add a new reward item.');
            // In a real implementation, this would open a modal or navigate to an add item page
        });

        // Edit and delete button functionality
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const itemName = this.closest('tr').querySelector('.item-details h4').textContent;
                alert(`Edit functionality for: ${itemName}`);
                // In a real implementation, this would open an edit form
            });
        });

        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const itemName = this.closest('tr').querySelector('.item-details h4').textContent;
                if (confirm(`Are you sure you want to delete "${itemName}"?`)) {
                    // In a real implementation, this would send a delete request to the server
                    this.closest('tr').remove();
                    alert(`"${itemName}" has been deleted.`);
                }
            });
        });
    </script>
</body>
</html>