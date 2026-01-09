<?php
// 1. PHP 逻辑部分
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 引入数据库连接 (确保路径正确)
include_once 'dataconnection.php'; 

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$donor_name = isset($_SESSION['donor_name']) ? htmlspecialchars($_SESSION['donor_name']) : "Guest";
$wallet_balance = 0.00;

// [新增] 如果已登录，从数据库获取最新余额
if ($logged_in && isset($_SESSION['donor_id'])) {
    $stmt = $conn->prepare("SELECT Donor_Wallet FROM donor WHERE Donor_ID = ?");
    $stmt->bind_param("i", $_SESSION['donor_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $wallet_balance = $row['Donor_Wallet'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Bridge</title>
    
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

        /* --- [新增] 钱包样式 --- */
        .wallet-display {
            display: flex;
            align-items: center;
            color: var(--light-text);
            background: rgba(0, 0, 0, 0.1); /* 轻微背景区分 */
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            gap: 8px;
            transition: 0.3s;
            margin-right: 10px; /* 与 Account 分开一点 */
        }
        .wallet-display:hover {
            background: rgba(0, 0, 0, 0.2);
            transform: translateY(-1px);
        }
        .wallet-amount {
            font-family: 'Segoe UI', sans-serif; /* 数字显示更好看 */
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

        /* --- 下拉菜单 --- */
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
                <a href="tel:+60123456789">
                    <svg class="icon-svg" viewBox="0 0 24 24">
                        <path d="M22 16.92v3a2 2 0 0 1-2-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    +60 12-345 6789
                </a>
                
                <a href="mailto:info@lovebridge.org">
                    <svg class="icon-svg" viewBox="0 0 24 24">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    info@lovebridge.org
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
                            <a href="Gamification_Achievement_Panel.php" class="dropdown-item">Points</a>
                            <a href="Track_Records.php" class="dropdown-item">Track Record</a>
                            <a href="Profile.php" class="dropdown-item">Profile</a>
                        </div>
                    </div>
                    
                    <a href="donor_logout.php" class="logout-btn">
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
                    <?php if ($logged_in): ?>
                        <li><a href="Profile.php">Profile</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div style="display:flex; align-items:center;">
                <form action="search.php" method="GET" class="header-search">
                    <input type="text" name="query" placeholder="Search...">
                    <button type="submit">🔍</button>
                </form>

                <a href="Payment_page.php" class="header-donate-btn">Donate</a>
            </div>
        </div>
    </header>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const profileTrigger = document.querySelector('.profile-trigger');
            
            if (profileTrigger && !profileTrigger.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        document.getElementById('profileDropdown').addEventListener('click', function(event) {
            event.stopPropagation();
        });
    </script>
</body>
</html>
