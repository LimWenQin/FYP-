<?php
session_start();
include 'dataconnection.php';

// 设置默认时区
date_default_timezone_set('Asia/Kuala_Lumpur'); 

// --- 逻辑修改 1: 处理 Email 显示逻辑 (优先显示刚才输入的，其次显示Cookie) ---
$display_email = "";

// 如果有 Cookie，先赋值给 display_email
if (isset($_COOKIE['admin_remember_email'])) {
    $display_email = $_COOKIE['admin_remember_email'];
}

// 处理登录逻辑
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_btn'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password']; 
    
    // --- 逻辑修改 2: 如果用户刚刚提交了表单，无论对错，页面刷新后输入框都应该保留这个 Email ---
    $display_email = $email;

    // 检查是否勾选 Remember Me
    if (isset($_POST['remember'])) {
        setcookie('admin_remember_email', $email, time() + (86400 * 30), "/");
    } else {
        // 如果没勾选且登录成功(或者仅仅是为了清除旧cookie)，可以在这里清除
        if (isset($_COOKIE['admin_remember_email'])) {
            setcookie('admin_remember_email', '', time() - 3600, "/");
        }
    }

    // --- 1. 优先查找 Admin 表 ---
    $sql_admin = "SELECT * FROM admin WHERE Admin_Email = '$email' AND Is_Deleted = 0";
    $result_admin = mysqli_query($conn, $sql_admin);

    if (mysqli_num_rows($result_admin) > 0) {
        // === 是 Admin ===
        $row = mysqli_fetch_assoc($result_admin);
        $admin_id = $row['Admin_ID'];
        
        // --- 检查是否被冻结 (Admin Only Logic) ---
        $max_attempts = 5;
        $lockout_time = 30 * 60; // 30分钟
        $attempts = $row['Admin_LoginAttempts'];
        
        $last_failed = $row['Admin_LastFailedLogin'] ? strtotime($row['Admin_LastFailedLogin']) : 0;
        $current_time = time();

        // 如果错误次数 >= 5，检查时间差
        if ($attempts >= $max_attempts) {
            $time_since_last_fail = $current_time - $last_failed;
            
            if ($time_since_last_fail < $lockout_time) {
                // 还在冻结期内
                $remaining_minutes = ceil(($lockout_time - $time_since_last_fail) / 60);
                $error = "Account locked. Please try again in $remaining_minutes minutes.";
            } else {
                // 冻结时间已过，自动解冻 (重置 DB)
                mysqli_query($conn, "UPDATE admin SET Admin_LoginAttempts = 0 WHERE Admin_ID = $admin_id");
                $attempts = 0; 
            }
        }

        // 如果没有报错 (没被冻结)，验证密码
        if (!isset($error)) {
            if (password_verify($password, $row['Admin_Password'])) { 
                // --- Admin 登录成功 ---
                $_SESSION['admin_id'] = $row['Admin_ID'];
                $_SESSION['admin_name'] = $row['Admin_Name']; 
                $_SESSION['admin_email'] = $row['Admin_Email'];
                $_SESSION['role'] = 'Admin';
                
                // 重置错误次数为 0，并更新最后登录时间
                mysqli_query($conn, "UPDATE admin SET Admin_LastLogin = NOW(), Admin_LoginAttempts = 0 WHERE Admin_ID = $admin_id");
                
                header("Location: admin_dashboard.php");
                exit();
            } else {
                // --- Admin 密码错误 ---
                $attempts++;
                
                // 更新数据库
                mysqli_query($conn, "UPDATE admin SET Admin_LoginAttempts = $attempts, Admin_LastFailedLogin = NOW() WHERE Admin_ID = $admin_id");

                if ($attempts >= $max_attempts) {
                    $error = "Account locked for 30 minutes due to 5 failed attempts.";
                } else {
                    $remaining_attempts = $max_attempts - $attempts;
                    $error = "Incorrect password. You have $remaining_attempts attempts remaining.";
                }
            }
        }
    } else {
        // --- 2. Admin 表没找到，检查 Staff 表 ---
        // 只允许 Is_Deleted = 0 (未被 Block) 且 Staff_Status = 'Active' 的员工登录
        $sql_staff = "SELECT * FROM staff WHERE Staff_Email = '$email' AND Is_Deleted = 0 AND Staff_Status = 'Active'";
        $result_staff = mysqli_query($conn, $sql_staff);

        if (mysqli_num_rows($result_staff) > 0) {
            // === 是 Staff ===
            $row = mysqli_fetch_assoc($result_staff);

            if (password_verify($password, $row['Staff_Password'])) {
                // --- Staff 登录成功 ---
                $_SESSION['staff_id'] = $row['Staff_ID'];
                $_SESSION['staff_name'] = $row['Staff_FullName'];
                $_SESSION['role'] = 'Staff';

                // --- 关键修改：检查是否第一次登录 ---
                if ($row['Staff_IsFirstLogin'] == 1) {
                    // 设置 Flag，admin_dashboard.php 会捕捉到并弹出 Modal
                    $_SESSION['is_first_login'] = true; 
                }

                header("Location: admin_dashboard.php");
                exit();
            } else {
                // Staff 密码错误
                $error = "Incorrect password.";
            }
        } else {
            // 两个表都没找到
            $error = "Email not found or account inactive.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Bridge - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 保持你原本的 CSS 不变 */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; transition: background-color 0.8s ease, color 0.5s ease, border-color 0.5s ease; }
        :root { --bg-color: #FFF6E8; --accent-color: #D97706; --text-color: #5D4037; }
        body.theme-stray { --bg-color: #FFF6E8; --accent-color: #D97706; }    
        body.theme-disabled { --bg-color: #EFF6FF; --accent-color: #2563EB; } 
        body.theme-orphan { --bg-color: #FFF1F2; --accent-color: #E11D48; }   
        body.theme-senior { --bg-color: #F0FDF4; --accent-color: #16A34A; }   

        body { background-color: var(--bg-color); height: 100vh; display: flex; justify-content: center; align-items: center; overflow: hidden; position: relative; }
        
        .bg-circles { position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; z-index: 0; margin: 0; padding: 0; }
        .bg-circles li { position: absolute; display: block; list-style: none; width: 20px; height: 20px; background: var(--accent-color); animation: animate 25s linear infinite; bottom: -150px; opacity: 0.2; border-radius: 10%; }
        @keyframes animate { 0% { transform: translateY(0) rotate(0deg); opacity: 0.2; border-radius: 10%; } 100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; border-radius: 50%; } }

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

        .login-container { background: #fff; width: 900px; height: 550px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); display: flex; overflow: hidden; position: relative; z-index: 10; }
        .carousel-section { width: 45%; position: relative; background-color: var(--bg-color); display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; text-align: center; transition: background-color 0.8s ease; z-index: 2; }
        
        .header-logo { position: absolute; top: 30px; left: 30px; font-weight: bold; color: #444; display: flex; align-items: center; gap: 15px; font-size: 18px; letter-spacing: 1px; }
        .header-logo img { height: 75px; width: auto; object-fit: contain; }

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
    
    <ul class="bg-circles">
        <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
    </ul>

    <div class="login-container">
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

        <div class="form-section">
            <div class="decor-shape shape-1"></div>
            <div class="decor-shape shape-2"></div>
            <div class="decor-shape shape-3"></div>
            
            <div class="admin-badge">SYSTEM ACCESS</div>
            <h1>Welcome Back</h1>
            <p class="welcome-text">Login to access your dashboard.</p>
            
            <?php if(isset($error)) { echo "<div class='alert'>$error</div>"; } ?>

            <form action="" method="POST">
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Email Address" required value="<?php echo htmlspecialchars($display_email); ?>">
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                </div>
                <div class="form-options">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="remember" <?php if(isset($_COOKIE['admin_remember_email']) || (isset($_POST['remember']))) echo "checked"; ?>> Remember me
                    </label>
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