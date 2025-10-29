<?php
// admin_dashboard.php - Admin Dashboard
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
$servername = "127.0.0.1";
$username = "your_username";
$password = "your_password";
$dbname = "donation system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get statistics
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
        }

        .header {
            background: #f28585;
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome {
            font-size: 24px;
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

        .main-content {
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
            
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="welcome">Welcome back, <?php echo $_SESSION['admin_name']; ?>!</div>
        <a href="admin_logout.php" class="logout">Logout</a>
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
            <div class="main-content">
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
            
            <div class="sidebar">
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

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>