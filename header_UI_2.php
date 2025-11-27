<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Platform</title>
    <link rel="stylesheet" href="donor_design.css">
    <style>
        :root {
            --primary: #F6B8B8;
            --secondary: #FFF5E4;
            --text: #4A4A4A;
            --light-text: #777777;
            --white: #FFFFFF;
            --shadow: rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: var(--secondary);
            color: var(--text);
            line-height: 1.6;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #434141;
            padding: 6px 50px;
            box-shadow: 0 2px 4px var(--shadow);
        }
        
        .welcome-text {
            font-size: 16px;
            font-weight: bold;
            color: white;
        }
        
        .auth-buttons {
            display: flex;
            gap: 10px;
        }
        
        .auth-btn {
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            text-decoration: none;
            font-size: 14px;
        }
        
        .login-btn {
            background-color: white;
            color: black;
            border: none;
        }
        
        .login-btn:hover {
            background-color: #938e8eff;
        }
        
        .register-btn {
            background-color: white;
            color: black;
            border: none;
        }
        
        .register-btn:hover {
            background-color: #333333;
        }
        
        .header {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr; 
            align-items: center;
            background-color: var(--primary);
            padding: 20px 50px;
            box-shadow: 0 2px 6px var(--shadow);
            gap: 20px;
        }
        
        .logo 
    {
        display: flex;          /*变成弹性盒子Flexbox，自动变成一行排列*/
        align-items: center;    /* 垂直居中 */
        font-weight: bold;      /*粗体*/
        font-size: 36px;        /*字体大小*/
        gap: 10px;              /* 图片与文字之间的间距 */
    }
        
        .header-right { 
            grid-column: 2;
            display: flex;
            align-items: center;
            justify-content: center; 
            gap: 15px;
        }

        .function-links {
            display: flex;
            gap: 15px;
        }
        
        .function-links a {
            text-decoration: none;
            font-size: 18px;
            color: var(--text);
            font-weight: bold;
            transition: opacity 0.3s;
            white-space: nowrap;
        }
        
        .function-links a:hover {
            opacity: 0.8;
        }

        .header-center { 
            grid-column: 3;
            display: flex;
            align-items: center;
            justify-content: flex-end; 
            gap: 15px;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background-color: var(--white);
            border-radius: 4px;
            padding: 4px 8px;
            width: auto; 
            max-width: 250px;
        }
        
        .search-box input {
            border: none;
            background: transparent;
            padding: 6px;
            width: 100%;
            outline: none;
        }
        
        .donate-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 6px 18px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            white-space: nowrap;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .donate-btn:hover {
            background-color: #c0392b;
        }
        
        @media (max-width: 1024px) {
            .header {
                grid-template-columns: 1fr 1fr; 
                grid-template-rows: auto auto; 
                gap: 15px;
            }
            
            .logo { 
                grid-column: 1;
                grid-row: 1;
                text-align: left;
            }
            
            .header-right { 
                grid-column: 2;
                grid-row: 1;
                justify-content: flex-end; 
            }

            .header-center { 
                grid-column: 1 / span 2;
                grid-row: 2;
                justify-content: center; 
            }
        }
        
        @media (max-width: 768px) {
            .header {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
            }
            
            .logo { 
                grid-row: 1; 
                text-align: center;
            }
            
            .header-right { 
                grid-row: 2; 
                justify-content: center;
            }

            .header-center { 
                grid-row: 3; 
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header-top">
        <div class="welcome-text">
            Welcome, <?php echo isset($_SESSION['donor_name']) ? $_SESSION['donor_name'] : "Guest"; ?>
        </div>
        <div class="auth-buttons">
            <?php if ($logged_in): ?>
                <a href="donor_logout.php" class="auth-btn login-btn">Log out</a>
            <?php else: ?>
                <a href="donor_login.php" class="auth-btn login-btn">Login</a>
                <a href="donor_register.php" class="auth-btn register-btn">Register</a>
            <?php endif; ?>
        </div>
    </header>
    
    <header class="header">
       <div class="logo">
        <img src="logo.jpg" alt="Logo" width="80" height="80">
        LOVE BRIDGE
    </div>
        
        <div class="header-right">
            <div class="function-links">
                <a href="Activity Page.php">Activity</a>
                <a href="History.php">History</a>
                <a href="New&Story.php">News & Story</a>
                <a href="Special_case Page.php">Special Case</a>
                <a href="Profile.php">Profile</a>
            </div>
        </div>
        
        <div class="header-center">
            <div class="search-box">
                <input type="text" placeholder="Search...">
            </div>
        </div>

    </header>
