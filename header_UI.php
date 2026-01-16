<?php
// 1. PHP 逻辑部分
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 引入数据库连接
include_once 'dataconnection.php'; 

// 强制转换布尔值
$logged_in = (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true);
$donor_name = isset($_SESSION['donor_name']) ? htmlspecialchars($_SESSION['donor_name']) : "Guest";
$wallet_balance = 0.00;

// [新增] 通知的变量初始化
$notifications = [];
$notification_count = 0;

if ($logged_in && isset($_SESSION['donor_id'])) {
    if (isset($conn)) {
        // A. 获取钱包余额
        $stmt = $conn->prepare("SELECT Donor_Wallet FROM donor WHERE Donor_ID = ?");
        $stmt->bind_param("i", $_SESSION['donor_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $wallet_balance = $row['Donor_Wallet'];
        }
        $stmt->close();

        // B. [新增] 获取即将到期的 Subscription (未来7天内扣款)
        // 逻辑：状态为 Active，且 Recurring_Deduction_Date 在今天和未来7天之间
        $notif_sql = "SELECT Recurring_Amount, Recurring_Deduction_Date, Recurring_Type 
                      FROM recurring_donation 
                      WHERE Donor_ID = ? 
                      AND Recurring_Status = 'Active' 
                      AND Recurring_Deduction_Date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                      ORDER BY Recurring_Deduction_Date ASC";
        
        // 注意：如果您的表里没有 Recurring_Type，请从 SQL 中去掉它。这里假设用金额和日期展示。
        // 根据您提供的 SQL dump，表里只有 Recurring_Amount 和 Recurring_Deduction_Date 是关键。
        
        $stmt_notif = $conn->prepare("SELECT Recurring_ID, Recurring_Amount, Recurring_Deduction_Date FROM recurring_donation WHERE Donor_ID = ? AND Recurring_Status = 'Active' AND Recurring_Deduction_Date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        
        if ($stmt_notif) {
            $stmt_notif->bind_param("i", $_SESSION['donor_id']);
            $stmt_notif->execute();
            $result_notif = $stmt_notif->get_result();
            while ($row_notif = $result_notif->fetch_assoc()) {
                $notifications[] = $row_notif;
            }
            $notification_count = count($notifications);
            $stmt_notif->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Bridge</title>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* --- 基础重置 --- */
        body { margin: 0; padding: 0; font-family: 'Arial', sans-serif; }
        * { box-sizing: border-box; }
        a { text-decoration: none; transition: 0.3s; }
        ul { list-style: none; padding: 0; margin: 0; }

        /* --- 颜色变量 --- */
        :root {
            --primary-color: #e16161ff;
            --dark-bg: #333;
            --light-text: #fff;
            --hover-color: #8b1a00ff;
        }

        /* --- 1. 顶部深色条 (Top Bar) --- */
        .header-top-bar {
            background-color: var(--primary-color);
            color: var(--light-text);
            padding: 10px 0;
            font-size: 14px;
            position: relative;
            z-index: 2000; 
        }
        .header-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-info {
            display: flex;
            align-items: center;
        }
        
        .header-info a {
            color: var(--light-text);
            margin-right: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .header-info a:hover {
            opacity: 0.8;
        }

        /* 用户认证区域样式 */
        .header-user-auth {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .header-auth-links {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-auth-links a {
            color: var(--light-text);
            font-weight: bold;
        }

        /* --- 钱包样式 --- */
        .wallet-display {
            display: flex;
            align-items: center;
            color: var(--light-text);
            background: rgba(0, 0, 0, 0.1); 
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            gap: 8px;
            transition: 0.3s;
            margin-right: 5px; 
        }
        .wallet-display:hover {
            background: rgba(0, 0, 0, 0.2);
            transform: translateY(-1px);
        }
        .wallet-amount {
            font-family: 'Segoe UI', sans-serif; 
        }

        /* --- [新增] Notification 样式 --- */
        .notification-container {
            position: relative;
            display: flex;
            align-items: center;
            margin-right: 10px; /* 与 Account 的间距 */
        }

        .notification-trigger {
            cursor: pointer;
            position: relative;
            padding: 5px;
            display: flex;
            align-items: center;
        }
        
        .notification-trigger:hover svg {
            opacity: 0.8;
        }

        /* 红色数字角标 */
        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background-color: #ffcc00; /* 黄色或白色醒目 */
            color: #d12f02;
            font-size: 10px;
            font-weight: bold;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #d12f02;
        }

        /* 通知的下拉框 */
        .notification-dropdown {
            position: absolute;
            top: 130%;
            right: -50px; /* 稍微往左一点 */
            width: 300px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border-radius: 8px;
            z-index: 2001;
            display: none;
            color: #333;
            overflow: hidden;
        }

        /* 下拉框的小箭头 */
        .notification-dropdown::before {
            content: '';
            position: absolute;
            top: -10px;
            right: 55px;
            border-width: 0 10px 10px 10px;
            border-style: solid;
            border-color: transparent transparent white transparent;
        }

        .notification-dropdown.active {
            display: block;
        }

        .notification-header {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            font-weight: bold;
            font-size: 14px;
            background-color: #f9f9f9;
            color: var(--primary-color);
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column;
            gap: 4px;
            transition: background 0.2s;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background-color: #fff8f8;
        }

        .notif-title {
            font-size: 13px;
            font-weight: bold;
            color: #333;
        }
        
        .notif-desc {
            font-size: 12px;
            color: #666;
        }
        
        .notif-date {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }

        .no-notif {
            padding: 20px;
            text-align: center;
            font-size: 13px;
            color: #888;
        }

        /* --- 用户 Account 区域 --- */
        .user-profile-container {
            position: relative;
            display: inline-block;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            cursor: pointer;
            gap: 8px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .profile-trigger:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* --- 通用 SVG 图标样式 --- */
        .icon-svg {
            width: 18px;
            height: 18px;
            min-width: 18px; 
            flex-shrink: 0;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .account-text {
            font-weight: bold;
            font-size: 14px;
            user-select: none;
        }

        /* --- Account 下拉菜单 --- */
        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            background: white;
            min-width: 200px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border-radius: 8px;
            padding: 15px 0;
            display: none;
            z-index: 9999; 
            color: #333;
        }

        .profile-dropdown.active {
            display: block;
        }

        .profile-dropdown::before {
            content: '';
            position: absolute;
            top: -10px;
            right: 15px;
            border-width: 0 10px 10px 10px;
            border-style: solid;
            border-color: transparent transparent white transparent;
        }

        .welcome-message {
            padding: 0 15px 10px 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
            color: #333;
            font-size: 14px;
        }

        .welcome-message b {
            color: var(--primary-color);
        }

        .dropdown-item {
            display: block;
            padding: 10px 15px;
            color: #333;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: #f5f5f5;
            color: var(--primary-color);
            padding-left: 20px;
        }

        /* --- Logout 按钮样式 --- */
        .logout-btn {
            background: transparent;
            color: var(--light-text);
            padding: 5px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-left: 10px;
            transition: all 0.3s ease;
            display: flex; 
            align-items: center;
            gap: 6px; 
        }

        .logout-btn:hover {
            background: var(--light-text);
            color: var(--primary-color);
        }

        /* --- 2. 主导航栏 (Main Navbar) --- */
        .header-navbar {
            background: #fff;
            padding: 20px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-logo a {
            font-size: 28px;
            font-weight: 900;
            color: var(--primary-color);
            text-transform: uppercase;
            display: flex;
            align-items: center;
        }
        .header-logo img {
            height: 50px;
            margin-right: 10px;
        }

        .header-menu {
            display: flex;
            gap: 25px;
        }
        .header-menu a {
            color: #333;
            font-weight: 500;
            font-size: 16px;
            padding: 10px 5px;
        }
        .header-menu a:hover { color: var(--primary-color); }

        .header-search {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 30px;
            padding: 5px 15px;
            margin-left: 20px;
        }
        .header-search input {
            border: none;
            outline: none;
            font-size: 14px;
            width: 120px;
        }
        .header-search button {
            background: none;
            border: none;
            cursor: pointer;
            color: #777;
        }

        .header-donate-btn {
            background-color: #ffbb6dff;
            color: #fff !important;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: bold;
            margin-left: 15px;
            box-shadow: 0 4px 10px rgba(209, 47, 2, 0.3);
            cursor: pointer;
        }
        .header-donate-btn:hover {
            background-color: #f79c34ff ;
            transform: translateY(-2px);
        }

        @media (max-width: 900px) {
            .header-menu { display: none; }
            .header-search { display: none; }
            .header-info { display: none; }
            .header-user-auth { gap: 10px; }
        }
    </style>
</head>
<body>

    <div class="header-top-bar">
        <div class="header-container">
            <div class="header-info">
                <a href="https://wa.me/601111190233" target="_blank">
                    <svg class="icon-svg" viewBox="0 0 24 24">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    +60 11-1119 0233
                </a>
                
                <a href="mailto:lovebridge1201@gmail.com">
                    <svg class="icon-svg" viewBox="0 0 24 24">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    lovebridge1201@gmail.com
                </a>
            </div>
            
            <div class="header-user-auth">
                <?php if ($logged_in): ?>
                    <a href="E_Wallet.php" class="wallet-display" title="View Wallet">
                        <svg class="icon-svg" viewBox="0 0 24 24">
                            <path d="M21 12V7H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h16"></path>
                            <path d="M3 6v13a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5"></path>
                            <rect x="16" y="10" width="6" height="6" rx="1"></rect>
                        </svg>
                        <span class="wallet-amount">RM <?php echo number_format($wallet_balance, 2); ?></span>
                    </a>

                    <div class="notification-container">
                        <div class="notification-trigger" onclick="toggleNotification()">
                            <svg class="icon-svg" viewBox="0 0 24 24">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <?php if ($notification_count > 0): ?>
                                <span class="notification-badge"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">Notifications</div>
                            <div class="notification-list">
                                <?php if ($notification_count > 0): ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <div class="notification-item">
                                            <div class="notif-title">Subscription Renewal</div>
                                            <div class="notif-desc">
                                                Your monthly donation of <b>RM <?php echo number_format($notif['Recurring_Amount'], 2); ?></b> is scheduled for deduction.
                                            </div>
                                            <div class="notif-date">
                                                Due: <?php echo date("d M Y", strtotime($notif['Recurring_Deduction_Date'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-notif">No new notifications</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="user-profile-container">
                        <div class="profile-trigger" onclick="toggleDropdown()">
                            <svg class="icon-svg" viewBox="0 0 24 24">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M20 21a8 8 0 1 0-16 0"></path>
                            </svg>
                            <span class="account-text">Account</span>
                        </div>

                        <div class="profile-dropdown" id="profileDropdown">
                            <div class="welcome-message">
                                Welcome, <b><?php echo $donor_name; ?></b>
                            </div>
                            <a href="Profile.php" class="dropdown-item">Profile</a>
                            <a href="Gamification_Achievement_Panel.php" class="dropdown-item">Points</a>
                            <a href="Redemption_Page.php" class="dropdown-item">Redemption</a>
                            <a href="Track_Records.php" class="dropdown-item">Donation Records</a>
                            <a href="Recurring_Donation_Management_Panel.php" class="dropdown-item">Donation Management</a>
                            
                        </div>
                    </div>
                    
                    <a href="donor_logout.php" class="logout-btn" onclick="confirmLogout(event)">
                        <svg class="icon-svg" viewBox="0 0 24 24">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Log out</span>
                    </a>

                <?php else: ?>
                    <div class="header-auth-links">
                        <a href="donor_login.php">Login</a> | 
                        <a href="donor_register.php">Register</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <header class="header-navbar">
        <div class="header-container header-nav-content">
            <div class="header-logo">
                <a href="Homepage.php">
                    <img src="logo.jpg" alt="Logo" onerror="this.style.display='none'"> 
                    Love Bridge
                </a>
            </div>

            <nav>
                <ul class="header-menu">
                    <li><a href="Homepage.php">Home</a></li>
                    <li><a href="Campaign_Page.php">Campaign</a></li>
                    <li><a href="New&Story.php">News & Story</a></li>
                    <li><a href="Special_case Page.php">Special Case</a></li>
                </ul>
            </nav>

            <div style="display:flex; align-items:center;">
                <form action="search.php" method="GET" class="header-search">
                    <input type="text" name="query" placeholder="Search...">
                    <button type="submit">🔍</button>
                </form>

                <?php if ($logged_in): ?>
                    <a href="Branch_Selection.php" class="header-donate-btn">Donate</a>
                <?php else: ?>
                    <a href="#" class="header-donate-btn" onclick="showLoginAlert(event)">Donate</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <script>
        // 1. Account Dropdown Logic
        function toggleDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            const notifDropdown = document.getElementById('notificationDropdown');
            
            // 关闭通知，打开 Account
            if(notifDropdown) notifDropdown.classList.remove('active');
            dropdown.classList.toggle('active');
        }

        // [新增] 2. Notification Dropdown Logic
        function toggleNotification() {
            const notifDropdown = document.getElementById('notificationDropdown');
            const accountDropdown = document.getElementById('profileDropdown');

            // 关闭 Account，打开通知
            if(accountDropdown) accountDropdown.classList.remove('active');
            notifDropdown.classList.toggle('active');
        }

        // 3. Global Click Listener (Close dropdowns when clicking outside)
        document.addEventListener('click', function(event) {
            const profileDropdown = document.getElementById('profileDropdown');
            const profileTrigger = document.querySelector('.profile-trigger');
            
            const notifDropdown = document.getElementById('notificationDropdown');
            const notifTrigger = document.querySelector('.notification-trigger');

            // 处理 Account 点击外部关闭
            if (profileTrigger && !profileTrigger.contains(event.target) && !profileDropdown.contains(event.target)) {
                profileDropdown.classList.remove('active');
            }

            // [新增] 处理 Notification 点击外部关闭
            if (notifTrigger && !notifTrigger.contains(event.target) && !notifDropdown.contains(event.target)) {
                notifDropdown.classList.remove('active');
            }
        });

        // 阻止下拉框内部点击冒泡
        document.getElementById('profileDropdown')?.addEventListener('click', function(event) {
            event.stopPropagation();
        });
        document.getElementById('notificationDropdown')?.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        // 4. 显示登录提示 (Donate 按钮)
        function showLoginAlert(event) {
            event.preventDefault(); 

            Swal.fire({
                icon: 'info',
                title: 'Login Required',
                text: 'You need to login to make a donation.',
                showCancelButton: true,
                confirmButtonColor: '#e16161', 
                cancelButtonColor: '#6c757d', 
                confirmButtonText: 'Login Now',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "donor_login.php";
                }
            });
        }

        // 5. 登出确认函数
        function confirmLogout(event) {
            event.preventDefault(); 

            Swal.fire({
                title: 'Are you sure?',
                text: "You will be logged out of your account.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e16161', 
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Log out',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'donor_logout.php';
                }
            });
        }
    </script>
</body>
</html>