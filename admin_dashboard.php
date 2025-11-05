<?php
// admin_dashboard.php - Admin Dashboard
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';


$total_donors = 0;
$total_donations = 0;
$total_branches = 0;
$total_activities = 0;
$total_items = 0;
$total_rewards = 0;

// Get total donors
$sql = "SELECT COUNT(*) as count FROM donor";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $total_donors = $row['count'];
}

// Get total donations (from orders table)
$sql = "SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus = 'completed'";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $total_donations = $row['total'] ?: 0;
}

// Get total branches
$sql = "SELECT COUNT(*) as count FROM branch";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $total_branches = $row['count'];
}

// Get total activities
$sql = "SELECT COUNT(*) as count FROM activity";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $total_activities = $row['count'];
}

// Get total item donations
$sql = "SELECT COUNT(*) as count FROM item_donation";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $total_items = $row['count'];
}

// Get total rewards
$sql = "SELECT COUNT(*) as count FROM reward_item";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $total_rewards = $row['count'];
}

// Get recent activities
$recent_activities = [];
$sql = "SELECT a.Activity_ID, a.Activity_Date, a.Activity_Details, a.Activity_Status, b.Branch_Name 
        FROM activity a 
        JOIN branch b ON a.Branch_ID = b.Branch_ID 
        ORDER BY a.Activity_Date DESC 
        LIMIT 5";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Donation Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f9f9f9;
            color: #4a4a4a;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 70px;
            background: #f28585;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar.expanded {
            width: 250px;
        }

        .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logo:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            padding: 5px;
        }

        .logo-text {
            font-size: 20px;
            font-weight: bold;
            margin-top: 10px;
            opacity: 0;
            height: 0;
            overflow: hidden;
            transition: opacity 0.3s ease;
        }

        .sidebar.expanded .logo-text {
            opacity: 1;
            height: auto;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            white-space: nowrap;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid white;
        }

        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid white;
        }

        .menu-item ion-icon {
            font-size: 20px;
            margin-right: 15px;
            min-width: 20px;
        }

        .menu-text {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar.expanded .menu-text {
            opacity: 1;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            margin-left: 0;
        }

        .header {
            background: #f28585;
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            position: relative;
        }

        /* Add a decorative line that matches the sidebar width */
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 70px;
            height: 100%;
            background: #e66767;
            transition: width 0.3s ease;
            z-index: 1;
        }

        .sidebar.expanded + .main-content .header::before {
            width: 250px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            z-index: 2;
        }

        .menu-toggle {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 24px;
            height: 18px;
            cursor: pointer;
        }

        .menu-toggle span {
            display: block;
            height: 3px;
            width: 100%;
            background-color: white;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: white;
        }

        .header-logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            padding: 5px;
        }

        .header-logo-text {
            font-size: 20px;
            font-weight: bold;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 2;
        }

        .welcome {
            font-size: 18px;
            font-weight: 600;
        }

        .logout {
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .logout:hover {
            background: rgba(255,255,255,0.3);
        }

        .dashboard {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .page-title {
            font-size: 28px;
            margin-bottom: 30px;
            color: #4a4a4a;
            border-bottom: 2px solid #f6b8b8;
            padding-bottom: 10px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 4px solid #f28585;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: #f28585;
        }

        .stat-number {
            font-size: 2.5em;
            color: #f28585;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 16px;
            color: #7f8c8d;
        }

        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .main-content-section {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .section-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .section-card:hover {
            transform: translateY(-5px);
        }

        .section-title {
            font-size: 20px;
            margin-bottom: 15px;
            color: #4a4a4a;
            display: flex;
            align-items: center;
        }

        .section-title ion-icon {
            margin-right: 10px;
            color: #f28585;
        }

        .section-list {
            list-style: none;
        }

        .section-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-list li:last-child {
            border-bottom: none;
        }

        .section-list a {
            color: #f28585;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .section-list a:hover {
            color: #e66767;
            text-decoration: underline;
        }

        .badge {
            background: #f6b8b8;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .action-btn {
            background: #f28585;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .action-btn:hover {
            background: #e66767;
            transform: translateY(-2px);
        }

        .activity-item {
            padding: 15px;
            border-left: 4px solid #f28585;
            background: #fff5e4;
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
        }

        .activity-date {
            font-weight: bold;
            color: #f28585;
        }

        .activity-details {
            margin: 5px 0;
        }

        .activity-branch {
            color: #7f8c8d;
            font-size: 14px;
        }

        .activity-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-top: 5px;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-upcoming {
            background: #cce7ff;
            color: #004085;
        }

        .status-ongoing {
            background: #fff3cd;
            color: #856404;
        }

        @media (max-width: 1024px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-left, .header-right {
                width: 100%;
                justify-content: center;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: flex;
            }
            
            .header::before {
                display: none;
            }
        }
        
        @media (min-width: 769px) {
            .menu-toggle {
                display: none;
            }
            
            .sidebar {
                position: fixed;
                height: 100vh;
            }
            
            .main-content {
                margin-left: 70px;
                transition: margin-left 0.3s ease;
            }
            
            .sidebar.expanded + .main-content {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo" id="sidebarToggle">
            <img src="picture" alt="Logo">
            <div class="logo-text">DonationMS</div>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-item active">
                <ion-icon name="people"></ion-icon>
                <div class="menu-text">Donor Management</div>
            </div>
            <div class="menu-item">
                <ion-icon name="person-circle"></ion-icon>
                <div class="menu-text">Staff Management</div>
            </div>
        </div>
    </aside>
    
    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <!-- Three-line menu toggle -->
                <div class="menu-toggle" id="menuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                
                <!-- Logo that links to homepage -->
                <a href="admin_dashboard.php" class="header-logo">
                    <img src="picture" alt="Logo">
                    <div class="header-logo-text">DonationMS</div>
                </a>
            </div>
            
            <div class="header-right">
                <div class="welcome">Welcome back, <?php echo $_SESSION['admin_name']; ?>!</div>
                <a href="admin_logout.php" class="logout">Logout</a>
            </div>
        </div>
        
        <div class="dashboard">
            <h1 class="page-title">System Overview</h1>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="people"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_donors; ?></div>
                    <div class="stat-label">Total Donors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="cash"></ion-icon>
                    </div>
                    <div class="stat-number">RM <?php echo number_format($total_donations, 2); ?></div>
                    <div class="stat-label">Total Donations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="business"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_branches; ?></div>
                    <div class="stat-label">Branch Locations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="calendar"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_activities; ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>
            </div>
            
            <div class="dashboard-content">
                <div class="main-content-section">
                    <div class="section-card">
                        <h2 class="section-title">
                            <ion-icon name="people-circle"></ion-icon>
                            Donor Management
                        </h2>
                        <ul class="section-list">
                            <li>
                                <a href="#">View All Donors</a>
                                <span class="badge"><?php echo $total_donors; ?></span>
                            </li>
                            <li><a href="#">Add New Donor</a></li>
                            <li><a href="#">Donor Points Management</a></li>
                            <li><a href="#">Donation History</a></li>
                        </ul>
                        <div class="quick-actions">
                            <a href="#" class="action-btn">Add Donor</a>
                            <a href="#" class="action-btn">Export Data</a>
                        </div>
                    </div>
                    
                    <div class="section-card">
                        <h2 class="section-title">
                            <ion-icon name="business"></ion-icon>
                            Branch Management
                        </h2>
                        <ul class="section-list">
                            <li>
                                <a href="#">View All Branches</a>
                                <span class="badge"><?php echo $total_branches; ?></span>
                            </li>
                            <li><a href="#">Elderly Homes</a></li>
                            <li><a href="#">Orphanages</a></li>
                            <li><a href="#">Disability Centers</a></li>
                            <li><a href="#">Stray Animal Centers</a></li>
                        </ul>
                        <div class="quick-actions">
                            <a href="#" class="action-btn">Add Branch</a>
                            <a href="#" class="action-btn">Branch Reports</a>
                        </div>
                    </div>
                </div>
                
                <div class="sidebar-content">
                    <div class="section-card">
                        <h2 class="section-title">
                            <ion-icon name="time"></ion-icon>
                            Recent Activities
                        </h2>
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-date">
                                        <?php echo date('M j, Y', strtotime($activity['Activity_Date'])); ?>
                                    </div>
                                    <div class="activity-details">
                                        <?php echo substr($activity['Activity_Details'], 0, 50); ?>...
                                    </div>
                                    <div class="activity-branch">
                                        <?php echo $activity['Branch_Name']; ?>
                                    </div>
                                    <div class="activity-status <?php echo 'status-' . strtolower($activity['Activity_Status']); ?>">
                                        <?php echo $activity['Activity_Status']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No recent activities found.</p>
                        <?php endif; ?>
                        <div class="quick-actions">
                            <a href="#" class="action-btn">View All Activities</a>
                        </div>
                    </div>
                    
                    <div class="section-card">
                        <h2 class="section-title">
                            <ion-icon name="bar-chart"></ion-icon>
                            Quick Stats
                        </h2>
                        <ul class="section-list">
                            <li>
                                <span>Item Donations</span>
                                <span class="badge"><?php echo $total_items; ?></span>
                            </li>
                            <li>
                                <span>Reward Items</span>
                                <span class="badge"><?php echo $total_rewards; ?></span>
                            </li>
                            <li>
                                <span>Recurring Donations</span>
                                <span class="badge">0</span>
                            </li>
                            <li>
                                <span>Achievements</span>
                                <span class="badge">0</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');
            
            // Toggle sidebar on menu button click (mobile)
            menuToggle.addEventListener('click', function() {
                if (window.innerWidth < 769) {
                    sidebar.classList.toggle('active');
                }
            });
            
            // Desktop hover functionality
            if (window.innerWidth >= 769) {
                let hoverTimeout;
                
                sidebar.addEventListener('mouseenter', function() {
                    clearTimeout(hoverTimeout);
                    sidebar.classList.add('expanded');
                });
                
                sidebar.addEventListener('mouseleave', function() {
                    hoverTimeout = setTimeout(function() {
                        sidebar.classList.remove('expanded');
                    }, 300);
                });
                
                // Keep sidebar expanded when hovering over menu items
                const menuItems = document.querySelectorAll('.menu-item');
                menuItems.forEach(item => {
                    item.addEventListener('mouseenter', function() {
                        clearTimeout(hoverTimeout);
                    });
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 769 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            });
            
            // Handle menu item clicks
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    menuItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    
                    // On mobile, close sidebar after selecting a menu item
                    if (window.innerWidth < 769) {
                        sidebar.classList.remove('active');
                    }
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 769) {
                    sidebar.classList.remove('active');
                } else {
                    sidebar.classList.remove('expanded');
                }
            });
        });
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>
