<?php
// 1. PHP 逻辑部分：检查登录状态
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$donor_name = isset($_SESSION['donor_name']) ? htmlspecialchars($_SESSION['donor_name']) : "Guest";
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
            --primary-color: #c82e03ff; /* Colorlib 绿色 */
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
        }
        .header-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* 新增：左侧信息容器样式 */
        .header-info {
            display: flex;
            align-items: center;
            gap: 20px; /* 信息之间的间隔 */
        }
        .header-info a {
            color: var(--light-text);
            margin-right: 10px;
        }
        .header-info span {
            margin-right: 5px; /* 增加图标和文本的间距 */
        }


        .header-auth-links a {
            color: var(--light-text);
            margin-left: 15px;
            font-weight: bold;
        }
        .header-auth-links a:hover { opacity: 0.8; }
        
        /* 新增：右侧用户/认证容器样式 */
        .header-user-auth {
            display: flex;
            align-items: center;
            gap: 15px; /* 欢迎信息和登录按钮之间的间隔 */
        }


        /* --- 2. 主导航栏 (Main Navbar) --- */
        .header-navbar {
            background: #fff;
            padding: 20px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); /* 底部阴影 */
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Logo 样式 */
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

        /* 菜单链接 */
        .header-menu {
            display: flex;
            gap: 25px;
        }
        .header-menu a {
            color: #333;
            font-weight: 500;
            font-size: 16px;
            padding: 10px 5px;
            position: relative;
        }
        .header-menu a:hover { color: var(--primary-color); }

        /* 搜索框 (嵌入式) */
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

        /* 捐款按钮 */
        .header-donate-btn {
            background-color: var(--primary-color);
            color: #fff !important;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: bold;
            margin-left: 15px;
            box-shadow: 0 4px 10px rgba(209, 47, 2, 0.3);
        }
        .header-donate-btn:hover {
            background-color: var(--hover-color);
            transform: translateY(-2px);
        }

        /* 响应式: 平板/手机端调整 */
        @media (max-width: 900px) {
            .header-menu { display: none; } /* 暂时隐藏菜单，可后续加汉堡菜单 */
            .header-search { display: none; }
            
            /* 手机上隐藏顶部信息以节省空间 */
            .header-info { display: none; }
            .header-user-auth { 
                gap: 10px; 
            }
            .header-welcome {
                 font-size: 12px;
            }
        }
    </style>
</head>
<body>

   <div class="header-top-bar">
        <div class="header-container">
            <div class="header-info">
                <a href="tel:+60123456789"><span>📞</span> +60 12-345 6789</a>
                <a href="mailto:info@lovebridge.org"><span>✉️</span> info@lovebridge.org</a>
            </div>
            
            <div class="header-user-auth">
                 <div class="header-welcome">
                    Welcome, <b><?php echo $donor_name; ?></b>
                </div>
                
                <div class="header-auth-links">
                    <?php if ($logged_in): ?>
                        <a href="donor_logout.php">Log out</a>
                    <?php else: ?>
                        <a href="donor_login.php">Login</a> | <a href="donor_register.php">Register</a>
                    <?php endif; ?>
                </div>
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
                    <li><a href="Profile.php">Profile</a></li>
                </ul>
            </nav>

            <div style="display:flex; align-items:center;">
                <form action="search_results.php" method="GET" class="header-search">
                    <input type="text" name="query" placeholder="Search...">
                    <button type="submit">🔍</button>
                </form>

                <a href="Payment_page.php" class="header-donate-btn">Donate</a>
            </div>

        </div>
    </header>
</body>
</html>