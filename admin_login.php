<?php
session_start();
include 'dataconnection.php';

// 处理登录逻辑
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_btn'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password']; 

    $sql = "SELECT * FROM admin WHERE Admin_Email = '$email'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if ($password == $row['Admin_Password']) { 
            $_SESSION['admin_id'] = $row['Admin_ID'];
            $_SESSION['admin_name'] = $row['Admin_FName'] . ' ' . $row['Admin_LName'];
            $_SESSION['admin_email'] = $row['Admin_Email'];
            
            mysqli_query($conn, "UPDATE admin SET Admin_LastLogin = NOW() WHERE Admin_ID = " . $row['Admin_ID']);
            
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "Email not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Bridge - Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 全局重置 & 盒模型修复 */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; transition: background-color 0.8s ease, color 0.5s ease, border-color 0.5s ease; }
        
        /* CSS Variables */
        :root { --bg-color: #FFF6E8; --accent-color: #D97706; --text-color: #5D4037; }
        
        /* 4个主题颜色定义 */
        body.theme-stray { --bg-color: #FFF6E8; --accent-color: #D97706; }    /* 橙色 */
        body.theme-disabled { --bg-color: #EFF6FF; --accent-color: #2563EB; } /* 蓝色 */
        body.theme-orphan { --bg-color: #FFF1F2; --accent-color: #E11D48; }   /* 红色 */
        body.theme-senior { --bg-color: #F0FDF4; --accent-color: #16A34A; }   /* 绿色 */

        body { 
            background-color: var(--bg-color); 
            height: 100vh; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            overflow: hidden; 
            position: relative; 
        }
        
        /* --- 核心动画背景 (几何图形) --- */
        .bg-circles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0; /* 最底层 */
            margin: 0;
            padding: 0;
        }

        .bg-circles li {
            position: absolute;
            display: block;
            list-style: none;
            width: 20px;
            height: 20px;
            background: var(--accent-color); /* 纯色背景，跟随主题变色 */
            animation: animate 25s linear infinite;
            bottom: -150px;
            opacity: 0.2; /* 降低透明度 */
            border-radius: 10%; /* 初始形状：圆角矩形 */
        }

        @keyframes animate {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.2;
                border-radius: 10%;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
                border-radius: 50%;
            }
        }

        /* 粒子随机大小和位置 */
        .bg-circles li:nth-child(1) { left: 25%; width: 80px; height: 80px; animation-delay: 0s; }
        .bg-circles li:nth-child(2) { left: 10%; width: 20px; height: 20px; animation-delay: 2s; animation-duration: 12s; }
        .bg-circles li:nth-child(3) { left: 70%; width: 20px; height: 20px; animation-delay: 4s; }
        .bg-circles li:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 18s; }
        .bg-circles li:nth-child(5) { left: 65%; width: 20px; height: 20px; animation-delay: 0s; }
        .bg-circles li:nth-child(6) { left: 75%; width: 110px; height: 110px; animation-delay: 3s; }
        .bg-circles li:nth-child(7) { left: 35%; width: 150px; height: 150px; animation-delay: 7s; }
        .bg-circles li:nth-child(8) { left: 50%; width: 25px; height: 25px; animation-delay: 15s; animation-duration: 45s; }
        .bg-circles li:nth-child(9) { left: 20%; width: 15px; height: 15px; animation-delay: 2s; animation-duration: 35s; }
        .bg-circles li:nth-child(10){ left: 85%; width: 150px; height: 150px; animation-delay: 0s; animation-duration: 11s; }


        /* --- 登录框 (白色盒子) --- */
        .login-container { 
            background: #fff; 
            width: 900px; 
            height: 550px; 
            border-radius: 20px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
            display: flex; 
            overflow: hidden; 
            position: relative; 
            z-index: 10; 
        }
        
        /* Left Side */
        .carousel-section { width: 45%; position: relative; background-color: var(--bg-color); display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; text-align: center; transition: background-color 0.8s ease; z-index: 2; }
        
        /* --- 修正后的 Header Logo 样式 (大尺寸) --- */
        .header-logo {
            position: absolute; 
            top: 30px; 
            left: 30px; 
            font-weight: bold; 
            color: #444; 
            display: flex; 
            align-items: center; 
            gap: 15px; /* 图片和文字的间距 */
            font-size: 18px; /* 文字稍微大一点以匹配Logo */
            letter-spacing: 1px;
        }

        .header-logo img {
            height: 75px; 
            width: auto;  
            object-fit: contain;
        }

        .slide { display: none; flex-direction: column; align-items: center; animation: fadeIn 1s ease; }
        .slide.active { display: flex; }
        .logo-circle { width: 120px; height: 120px; background: rgba(255,255,255,0.6); border-radius: 50%; display: flex; justify-content: center; align-items: center; margin-bottom: 20px; font-size: 50px; color: var(--accent-color); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .slide h2 { font-size: 22px; color: #333; margin-bottom: 10px; font-weight: 700; }
        .slide p.subtitle { font-size: 14px; color: #666; margin-bottom: 30px; }
        .quote-box { background: rgba(255,255,255,0.5); padding: 20px; border-radius: 10px; font-size: 12px; font-style: italic; color: #555; text-align: left; position: relative; }
        .quote-author { display: block; margin-top: 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; color: #888; }
        .dots-container { position: absolute; bottom: 30px; display: flex; gap: 8px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #ccc; cursor: pointer; transition: 0.3s; }
        .dot.active { background: var(--accent-color); width: 20px; border-radius: 10px; }

        /* Right Side */
        .form-section { width: 55%; padding: 50px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .admin-badge { background: #f0f0f0; padding: 5px 10px; border-radius: 15px; font-size: 10px; font-weight: bold; color: #666; width: fit-content; margin-bottom: 10px; text-transform: uppercase; z-index: 2; }
        .form-section h1 { font-size: 28px; margin-bottom: 5px; color: #222; z-index: 2; position: relative; }
        .form-section p.welcome-text { color: #888; font-size: 14px; margin-bottom: 30px; z-index: 2; position: relative; }
        .input-group { position: relative; margin-bottom: 20px; z-index: 2; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .input-group input { width: 100%; padding: 12px 15px 12px 45px; border: 1px solid #eee; border-radius: 8px; outline: none; font-size: 14px; background: #fafafa; }
        .input-group input:focus { border-color: var(--accent-color); background: #fff; }
        .toggle-password { left: auto !important; right: 15px; cursor: pointer; }
        .form-options { display: flex; justify-content: space-between; align-items: center; font-size: 12px; margin-bottom: 25px; color: #666; z-index: 2; position: relative; }
        .form-options a { color: #666; text-decoration: none; font-weight: 600; }
        .form-options a:hover { color: var(--accent-color); }
        .login-btn { width: 100%; padding: 12px; border: none; border-radius: 8px; background-color: var(--accent-color); color: white; font-size: 16px; font-weight: bold; cursor: pointer; transition: background-color 0.5s ease; z-index: 2; position: relative; }
        .login-btn:hover { opacity: 0.9; }
        .footer-text { margin-top: auto; text-align: center; font-size: 10px; color: #aaa; z-index: 2; position: relative; }
        .alert { padding: 10px; background: #fee2e2; color: #b91c1c; border-radius: 5px; font-size: 12px; margin-bottom: 15px; text-align: center; z-index: 2; position: relative; }

        /* Form 内部的装饰 */
        .decor-shape { position: absolute; border-radius: 50%; background-color: var(--accent-color); opacity: 0.1; z-index: 1; filter: blur(40px); }
        .shape-1 { width: 300px; height: 300px; top: -100px; right: -100px; animation: float 6s ease-in-out infinite; }
        .shape-2 { width: 200px; height: 200px; bottom: -50px; right: -50px; animation: float 8s ease-in-out infinite reverse; opacity: 0.15; }
        .shape-3 { width: 100px; height: 100px; top: 40%; right: 10%; opacity: 0.08; animation: pulse 4s infinite; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(20px); } 100% { transform: translateY(0px); } }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    </style>
</head>
<body class="theme-stray">
    
    <!-- 背景层：简单的几何图形漂浮 -->
    <ul class="bg-circles">
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
    </ul>

    <div class="login-container">
        <!-- 左侧轮播图 -->
        <div class="carousel-section">
            <div class="header-logo">
                <img src="logo.jpg" alt="Logo">
                LOVE BRIDGE
            </div>

            <div class="slide active" data-theme="theme-stray">
                <div class="logo-circle"><i class="fas fa-dog"></i></div>
                <h2>Sheltering Strays</h2>
                <p class="subtitle">A warm bed and a loving heart for every animal.</p>
                <div class="quote-box">"Welcome to Love Bridge! Your dedication is a beacon of kindness..."<span class="quote-author">— LOVE BRIDGE</span></div>
            </div>
            <div class="slide" data-theme="theme-disabled">
                <div class="logo-circle"><i class="fas fa-wheelchair"></i></div>
                <h2>Supporting Abilities</h2>
                <p class="subtitle">Dignity, accessibility, and endless potential.</p>
                <div class="quote-box">"We believe in a world where everyone has the opportunity to shine..."<span class="quote-author">— LOVE BRIDGE</span></div>
            </div>
            <div class="slide" data-theme="theme-orphan">
                <div class="logo-circle"><i class="fas fa-child-reaching"></i></div>
                <h2>Nurturing Orphans</h2>
                <p class="subtitle">Building bright futures with love and hope.</p>
                <div class="quote-box">"Every child deserves a home, a family, and a future..."<span class="quote-author">— LOVE BRIDGE</span></div>
            </div>
            <div class="slide" data-theme="theme-senior">
                <div class="logo-circle"><i class="fas fa-hands-holding-circle"></i></div>
                <h2>Honoring Seniors</h2>
                <p class="subtitle">Wisdom, respect, and gentle care for our elders.</p>
                <div class="quote-box">"Our elders are the foundation of our society..."<span class="quote-author">— LOVE BRIDGE</span></div>
            </div>

            <div class="dots-container">
                <span class="dot active" onclick="manualSlide(0)"></span>
                <span class="dot" onclick="manualSlide(1)"></span>
                <span class="dot" onclick="manualSlide(2)"></span>
                <span class="dot" onclick="manualSlide(3)"></span>
            </div>
        </div>

        <!-- 右侧表单 -->
        <div class="form-section">
            <div class="decor-shape shape-1"></div>
            <div class="decor-shape shape-2"></div>
            <div class="decor-shape shape-3"></div>
            
            <div class="admin-badge">ADMIN ACCESS</div>
            <h1>Welcome Home</h1>
            <p class="welcome-text">Thank you for being the heart of our mission.</p>
            <?php if(isset($error)) { echo "<div class='alert'>$error</div>"; } ?>

            <form action="" method="POST">
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Email Address" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                </div>
                <div class="form-options">
                    <label style="display: flex; align-items: center; gap: 5px;"><input type="checkbox" name="remember"> Remember me</label>
                    <a href="admin_forgot_password.php">Forgot Password?</a>
                </div>
                <button type="submit" name="login_btn" class="login-btn">Login</button>
            </form>
            <div class="footer-text">© 2025 Love Bridge. Compassion in action.</div>
        </div>
    </div>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.dot');
        const body = document.body;
        const slideInterval = 10000; 

        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            slides[index].classList.add('active');
            dots[index].classList.add('active');
            const theme = slides[index].getAttribute('data-theme');
            body.className = theme;
        }
        function nextSlide() { currentSlide = (currentSlide + 1) % slides.length; showSlide(currentSlide); }
        function manualSlide(index) { currentSlide = index; showSlide(currentSlide); resetTimer(); }
        let timer = setInterval(nextSlide, slideInterval);
        function resetTimer() { clearInterval(timer); timer = setInterval(nextSlide, slideInterval); }
        function togglePassword() {
            const pwdInput = document.getElementById('password');
            const icon = document.querySelector('.toggle-password');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash');
            } else {
                pwdInput.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
