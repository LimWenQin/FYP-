<?php
// payment_management.php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 包含数据库连接
include 'dataconnection.php';

// 获取统计数据
function getTotalRevenue($conn) {
    $sql = "SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus = 'Completed'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'] ? number_format($row['total'], 2) : '0.00';
    }
    return '0.00';
}

function getPendingPayments($conn) {
    $sql = "SELECT COUNT(*) as count FROM orders WHERE Order_PaymentStatus = 'Pending'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    return 0;
}

function getPointsDistributed($conn) {
    $sql = "SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus = 'Completed'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'] ? number_format($row['total']) : '0';
    }
    return '0';
}

function getSuccessRate($conn) {
    $sql = "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN Order_PaymentStatus = 'Completed' THEN 1 ELSE 0 END) as successful_orders
            FROM orders";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['total_orders'] > 0) {
            return round(($row['successful_orders'] / $row['total_orders']) * 100, 1);
        }
    }
    return 0;
}

function getPaymentMethodsDistribution($conn) {
    $sql = "SELECT Order_PaymentMethod, COUNT(*) as count 
            FROM orders 
            WHERE Order_PaymentStatus = 'Completed'
            GROUP BY Order_PaymentMethod";
    $result = $conn->query($sql);
    $methods = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $methods[$row['Order_PaymentMethod']] = $row['count'];
        }
    }
    return $methods;
}

function getRecentTransactions($conn, $limit = 25) {
    $sql = "SELECT o.Order_ID, o.Order_TXN_Ref, 
                   d.Donor_FName, d.Donor_LName, d.Donor_Email,
                   o.Order_Amount, o.Order_Points_Earned,
                   o.Order_Created_At, o.Order_PaymentMethod, o.Order_PaymentStatus
            FROM orders o
            JOIN donor d ON o.Donor_ID = d.Donor_ID
            ORDER BY o.Order_Created_At DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
    $stmt->close();
    return $transactions;
}

function getRevenuePointsTrend($conn) {
    $sql = "SELECT 
            DATE(Order_Created_At) as date,
            SUM(Order_Amount) as revenue,
            SUM(Order_Amount) as points  -- 1 MYR = 1 Point
            FROM orders 
            WHERE Order_Created_At >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND Order_PaymentStatus = 'Completed'
            GROUP BY DATE(Order_Created_At)
            ORDER BY date";
    
    $result = $conn->query($sql);
    $trends = [
        'dates' => [],
        'revenue' => [],
        'points' => []
    ];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $trends['dates'][] = date('M j', strtotime($row['date']));
            $trends['revenue'][] = $row['revenue'];
            $trends['points'][] = $row['points'];
        }
    }
    
    // 确保有7天的数据
    while (count($trends['dates']) < 7) {
        $trends['dates'][] = '';
        $trends['revenue'][] = 0;
        $trends['points'][] = 0;
    }
    
    return $trends;
}

// 获取数据
$totalRevenue = getTotalRevenue($conn);
$pendingPayments = getPendingPayments($conn);
$pointsDistributed = getPointsDistributed($conn);
$successRate = getSuccessRate($conn);
$paymentMethods = getPaymentMethodsDistribution($conn);
$recentTransactions = getRecentTransactions($conn);
$revenuePointsTrend = getRevenuePointsTrend($conn);

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
    <title>Payment Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Payment Management Specific Styles */
        .payment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .payment-stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }

        .payment-stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-main {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-info h3 {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .stat-info h2 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            font-size: 12px;
            font-weight: 500;
        }

        .stat-trend.up {
            color: var(--success);
        }

        .stat-trend.down {
            color: var(--danger);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-card-1 .stat-icon {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .stat-card-2 .stat-icon {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .stat-card-3 .stat-icon {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        .stat-card-4 .stat-icon {
            background: rgba(242, 133, 133, 0.1);
            color: var(--primary);
        }

        .stat-footer {
            border-top: 1px solid var(--gray-light);
            padding-top: 15px;
            font-size: 12px;
            color: var(--gray);
        }

        /* Main Content Layout */
        .payment-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container, .recent-transactions, .payment-methods, .system-logic {
            background: white;
            border-radius: 10px;
            padding: 25px;
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
            height: 300px;
            position: relative;
        }

        /* Payment Methods */
        .payment-method-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .payment-method-item:last-child {
            border-bottom: none;
        }

        .method-info {
            display: flex;
            align-items: center;
        }

        .method-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 18px;
            background: var(--gray-light);
            color: var(--dark);
        }

        .method-details h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .method-details p {
            font-size: 12px;
            color: var(--gray);
        }

        .method-percentage {
            font-weight: 600;
            font-size: 14px;
        }

        /* System Logic */
        .system-logic {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }

        .system-logic h3 {
            font-size: 16px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .logic-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .logic-item:last-child {
            margin-bottom: 0;
        }

        .logic-icon {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            background: rgba(255, 255, 255, 0.2);
            font-size: 14px;
        }

        .logic-text {
            font-size: 14px;
        }

        /* Recent Transactions Table */
        .recent-transactions {
            grid-column: 1 / -1;
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transactions-table th, .transactions-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }

        .transactions-table th {
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
            background: var(--light);
        }

        .donor-info {
            display: flex;
            flex-direction: column;
        }

        .donor-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .donor-email {
            font-size: 12px;
            color: var(--gray);
        }

        .amount-points {
            display: flex;
            flex-direction: column;
        }

        .amount {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .points {
            font-size: 12px;
            color: var(--primary);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .status-failed {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-light);
        }

        .pagination-info {
            font-size: 14px;
            color: var(--gray);
        }

        .pagination-controls {
            display: flex;
            gap: 5px;
        }

        .page-btn {
            padding: 8px 12px;
            border: 1px solid var(--gray-light);
            background: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .page-btn:hover {
            background: var(--gray-light);
        }

        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .payment-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .payment-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .transactions-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .payment-stats {
                grid-template-columns: 1fr;
            }
            
            .stat-main {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stat-icon {
                margin-top: 10px;
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
                <li><a href="payment_management.php" class="active"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
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
                <h1>Payment Management</h1>
                <p>Track donations, manage transactions, and monitor point distribution.</p>
            </div>

            <!-- Stats Cards -->
            <div class="payment-stats">
                <div class="payment-stat-card stat-card-1">
                    <div class="stat-main">
                        <div class="stat-info">
                            <h3>Total Revenue</h3>
                            <h2>RM <?php echo $totalRevenue; ?></h2>
                            <div class="stat-trend up">
                                <i class="fas fa-arrow-up"></i> +12.5%
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <span id="pendingCount"><?php echo $pendingPayments; ?></span> awaiting payments
                    </div>
                </div>
                <div class="payment-stat-card stat-card-2">
                    <div class="stat-main">
                        <div class="stat-info">
                            <h3>Pending Payments</h3>
                            <h2>RM 0</h2>
                            <div class="stat-trend down">
                                <i class="fas fa-arrow-down"></i> -2.1%
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        All payments will be processed within 24 hours
                    </div>
                </div>
                <div class="payment-stat-card stat-card-3">
                    <div class="stat-main">
                        <div class="stat-info">
                            <h3>Points Distributed</h3>
                            <h2><?php echo $pointsDistributed; ?> pts</h2>
                            <div class="stat-trend up">
                                <i class="fas fa-arrow-up"></i> +12.5%
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        1 MYR = 1 Point conversion rate
                    </div>
                </div>
                <div class="payment-stat-card stat-card-4">
                    <div class="stat-main">
                        <div class="stat-info">
                            <h3>Success Rate</h3>
                            <h2><?php echo $successRate; ?>%</h2>
                            <div class="stat-trend up">
                                <i class="fas fa-arrow-up"></i> +3.2%
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        Based on last 30 days performance
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="payment-content">
                <!-- Revenue & Points Chart -->
                <div class="chart-container">
                    <div class="section-header">
                        <h2>Revenue & Points Overview</h2>
                        <div>
                            <span style="font-size: 14px; color: var(--gray);">Comparing donation income vs points generated (7 Days)</span>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="revenuePointsChart"></canvas>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="payment-methods">
                    <div class="section-header">
                        <h2>Payment Methods</h2>
                        <a href="#">Distribution by channel</a>
                    </div>
                    <div class="methods-list">
                        <div class="payment-method-item">
                            <div class="method-info">
                                <div class="method-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div class="method-details">
                                    <h4>FPX Banking</h4>
                                    <p>Online Banking</p>
                                </div>
                            </div>
                            <div class="method-percentage">
                                <?php 
                                $totalMethods = array_sum($paymentMethods);
                                $fpxPercentage = isset($paymentMethods['FPX']) ? round(($paymentMethods['FPX'] / $totalMethods) * 100, 1) : 0;
                                echo $fpxPercentage . '%';
                                ?>
                            </div>
                        </div>
                        <div class="payment-method-item">
                            <div class="method-info">
                                <div class="method-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="method-details">
                                    <h4>Credit Card</h4>
                                    <p>Visa, Mastercard</p>
                                </div>
                            </div>
                            <div class="method-percentage">
                                <?php 
                                $ccPercentage = isset($paymentMethods['Credit Card']) ? round(($paymentMethods['Credit Card'] / $totalMethods) * 100, 1) : 0;
                                echo $ccPercentage . '%';
                                ?>
                            </div>
                        </div>
                        <div class="payment-method-item">
                            <div class="method-info">
                                <div class="method-icon">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div class="method-details">
                                    <h4>E-Wallet</h4>
                                    <p>Touch 'n Go, GrabPay</p>
                                </div>
                            </div>
                            <div class="method-percentage">
                                <?php 
                                $ewalletPercentage = isset($paymentMethods['E-Wallet']) ? round(($paymentMethods['E-Wallet'] / $totalMethods) * 100, 1) : 0;
                                echo $ewalletPercentage . '%';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Logic -->
                <div class="system-logic">
                    <h3>SYSTEM LOGIC</h3>
                    <div class="logic-item">
                        <div class="logic-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="logic-text">
                            1.00 MYR Donation = 1 Point
                        </div>
                    </div>
                    <div class="logic-item">
                        <div class="logic-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="logic-text">
                            Points are auto-credited upon completion.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="recent-transactions">
                <div class="section-header">
                    <h2>Recent Transactions</h2>
                    <a href="#">Export Report</a>
                </div>
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>TRANSACTION ID</th>
                            <th>DONOR</th>
                            <th>AMOUNT / POINTS</th>
                            <th>DATE</th>
                            <th>METHOD</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentTransactions) > 0): ?>
                            <?php foreach($recentTransactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['Order_TXN_Ref']); ?></td>
                                <td>
                                    <div class="donor-info">
                                        <div class="donor-name">
                                            <?php echo htmlspecialchars($transaction['Donor_FName'] . ' ' . $transaction['Donor_LName']); ?>
                                        </div>
                                        <div class="donor-email">
                                            <?php echo htmlspecialchars($transaction['Donor_Email']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="amount-points">
                                        <div class="amount">RM <?php echo number_format($transaction['Order_Amount'], 2); ?></div>
                                        <div class="points">+<?php echo $transaction['Order_Amount']; ?> pts</div>
                                    </div>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($transaction['Order_Created_At'])); ?></td>
                                <td><?php echo htmlspecialchars($transaction['Order_PaymentMethod']); ?></td>
                                <td>
                                    <?php 
                                    $statusClass = '';
                                    switch($transaction['Order_PaymentStatus']) {
                                        case 'Completed':
                                            $statusClass = 'status-completed';
                                            break;
                                        case 'Pending':
                                            $statusClass = 'status-pending';
                                            break;
                                        case 'Failed':
                                            $statusClass = 'status-failed';
                                            break;
                                        default:
                                            $statusClass = 'status-pending';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($transaction['Order_PaymentStatus']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px;">
                                    No transactions found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing 1 to <?php echo min(25, count($recentTransactions)); ?> of <?php echo count($recentTransactions); ?> results
                    </div>
                    <div class="pagination-controls">
                        <button class="page-btn">Previous</button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <button class="page-btn">Next</button>
                    </div>
                </div>
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

        // Revenue & Points Chart
        const revenuePointsCtx = document.getElementById('revenuePointsChart').getContext('2d');
        const revenuePointsChart = new Chart(revenuePointsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($revenuePointsTrend['dates']); ?>,
                datasets: [
                    {
                        label: 'Revenue (RM)',
                        data: <?php echo json_encode($revenuePointsTrend['revenue']); ?>,
                        borderColor: '#f28585',
                        backgroundColor: 'rgba(242, 133, 133, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Points',
                        data: <?php echo json_encode($revenuePointsTrend['points']); ?>,
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (RM)'
                        },
                        grid: {
                            drawBorder: false
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Points'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
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
