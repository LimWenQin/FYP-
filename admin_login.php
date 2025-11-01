<?php
// admin_login.php - Complete Login Page
session_start();

// Include database connection
include 'dataconnection.php';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Basic validation
    if (!empty($email) && !empty($password)) {
        // Check if connection is successful
        if (!$conn->connect_error) {
            // Prevent SQL injection
            $email = $conn->real_escape_string($email);
            $password = $conn->real_escape_string($password);
            
            // Query admin information
            $sql = "SELECT * FROM admin WHERE Admin_Email = '$email' AND Admin_Password = '$password'";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                // Login successful
                $admin = $result->fetch_assoc();
                
                // Set session variables
                $_SESSION['admin_id'] = $admin['Admin_ID'];
                $_SESSION['admin_name'] = $admin['Admin_FName'] . ' ' . $admin['Admin_LName'];
                $_SESSION['admin_email'] = $admin['Admin_Email'];
                $_SESSION['login_time'] = time();
                
                // Handle "Remember Me" functionality
                if ($remember) {
                    setcookie('admin_email', $email, time() + (86400 * 30), "/");
                } else {
                    setcookie('admin_email', '', time() - 3600, "/");
                }
                
                // Redirect to dashboard
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = 'Invalid email or password!';
            }
        } else {
            $error = 'Database connection failed!';
        }
    } else {
        $error = 'Please enter both email and password!';
    }
}

// Check if already logged in, redirect if so
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Get saved email (if any)
$saved_email = $_COOKIE['admin_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Donation Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #fff5e4, #f6b8b8);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .login-wrapper {
            display: flex;
            width: 900px;
            height: 500px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
            z-index: 100; /* 提高z-index确保在动画元素之上 */
        }

        .logo-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .logo-container {
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .logo-image {
            max-width: 100%;
            max-height: 250px;
            object-fit: contain;
            margin-bottom: 20px;
            transition: transform 0.5s ease;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.1));
        }

        .logo-image:hover {
            transform: scale(1.05) rotate(2deg);
        }

        .logo-text {
            font-size: 24px;
            color: #f28585;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .logo-subtext {
            font-size: 14px;
            color: #7f8c8d;
            max-width: 300px;
            margin: 0 auto;
            line-height: 1.5;
        }

        .login-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            position: relative;
            background: linear-gradient(135deg, #fff5e4, #f6b8b8);
        }

        .login-container {
            width: 100%;
            max-width: 350px;
            position: relative;
            z-index: 10; /* 确保表单内容在动画之上 */
        }

        .login-box {
            position: relative;
            z-index: 20; /* 进一步提高表单内容的层级 */
        }

        .login-box h2 {
            color: #4a4a4a;
            font-size: 2em;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
            position: relative;
        }

        .login-box h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #f28585, #f6b8b8);
            border-radius: 2px;
        }

        .input-box {
            position: relative;
            margin: 25px 0;
        }

        .input-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            background: #fff;
            border: 2px solid #f6b8b8;
            border-radius: 10px;
            font-size: 16px;
            color: #4a4a4a;
            outline: none;
            transition: all 0.3s ease;
        }

        .input-box input:focus {
            border-color: #f28585;
            background: #fff;
            box-shadow: 0 0 15px rgba(242, 133, 133, 0.3);
            transform: translateY(-2px);
        }

        .input-box label {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #4a4a4a;
            font-size: 16px;
            pointer-events: none;
            transition: all 0.3s ease;
            background: #fff;
            padding: 0 5px;
        }

        .input-box input:focus ~ label,
        .input-box input:valid ~ label {
            top: 0;
            font-size: 12px;
            color: #f28585;
            background: #fff;
        }

        .input-box .icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #4a4a4a;
            font-size: 20px;
            cursor: pointer;
            transition: color 0.3s ease;
            z-index: 5; /* 确保图标可以点击 */
        }

        .input-box .icon:hover {
            color: #f28585;
            transform: translateY(-50%) scale(1.1);
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            font-size: 14px;
        }

        .remember-forgot label {
            display: flex;
            align-items: center;
            color: #4a4a4a;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .remember-forgot label:hover {
            color: #f28585;
        }

        .remember-forgot input[type="checkbox"] {
            margin-right: 8px;
            accent-color: #f28585;
            transform: scale(1.1);
            transition: transform 0.2s ease;
            cursor: pointer;
        }

        .remember-forgot input[type="checkbox"]:hover {
            transform: scale(1.2);
        }

        .remember-forgot a {
            color: #f28585;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .remember-forgot a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 1px;
            background: #f28585;
            transition: width 0.3s ease;
        }

        .remember-forgot a:hover {
            color: #e66767;
        }

        .remember-forgot a:hover::after {
            width: 100%;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #f28585, #e66767);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(242, 133, 133, 0.4);
        }

        .btn:hover::before {
            left: 100%;
        }

        .error-message {
            color: #e74c3c;
            background: #fadbd8;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #f5b7b1;
            font-size: 14px;
            animation: fadeIn 0.5s ease;
        }

        .system-info {
            margin-top: 20px;
            text-align: center;
            color: #7f8c8d;
            font-size: 12px;
            line-height: 1.5;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* 修复：所有动画元素必须设置 pointer-events: none */
        .floating-elements,
        .floating-element,
        .heart,
        .donation-icon,
        .sparkle,
        .background-elements,
        .circle,
        .background-animations,
        .bg-floating-element,
        .bg-heart,
        .bg-donation-icon,
        .pulse-ring,
        .wave {
            pointer-events: none !important;
        }

        /* Floating animation elements for both sides */
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            overflow: hidden;
            z-index: 1;
        }

        .floating-element {
            position: absolute;
            border-radius: 50%;
            background: rgba(242, 133, 133, 0.1);
            animation: float 6s ease-in-out infinite;
        }

        /* Left side floating elements */
        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            width: 60px;
            height: 60px;
            top: 70%;
            left: 15%;
            animation-delay: 1s;
        }

        .floating-element:nth-child(3) {
            width: 40px;
            height: 40px;
            top: 40%;
            left: 5%;
            animation-delay: 2s;
        }

        /* Right side floating elements */
        .floating-element:nth-child(4) {
            width: 70px;
            height: 70px;
            top: 20%;
            right: 10%;
            animation-delay: 3s;
        }

        .floating-element:nth-child(5) {
            width: 50px;
            height: 50px;
            top: 60%;
            right: 15%;
            animation-delay: 4s;
        }

        /* Additional elements for pink area */
        .floating-element:nth-child(6) {
            width: 90px;
            height: 90px;
            top: 30%;
            right: 5%;
            animation-delay: 5s;
            background: rgba(255, 255, 255, 0.2);
        }

        .floating-element:nth-child(7) {
            width: 30px;
            height: 30px;
            bottom: 20%;
            right: 8%;
            animation-delay: 6s;
            background: rgba(255, 255, 255, 0.3);
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.7;
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
                opacity: 1;
            }
            100% {
                transform: translateY(0) rotate(360deg);
                opacity: 0.7;
            }
        }

        /* Pulse animation for logo section */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(242, 133, 133, 0.4);
            }
            70% {
                box-shadow: 0 0 0 20px rgba(242, 133, 133, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(242, 133, 133, 0);
            }
        }

        .logo-section::before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(242, 133, 133, 0.1);
            top: -50px;
            right: -50px;
            z-index: 1;
            animation: pulse 4s infinite;
        }

        .logo-section::after {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(246, 184, 184, 0.1);
            bottom: -30px;
            left: -30px;
            z-index: 1;
            animation: pulse 5s infinite 1s;
        }

        /* Heart animation for pink area */
        .heart {
            position: absolute;
            font-size: 20px;
            color: rgba(242, 133, 133, 0.7);
            animation: heartFloat 8s linear infinite;
            z-index: 1;
        }

        .heart:nth-child(1) {
            top: 15%;
            left: 10%;
            animation-delay: 0s;
        }

        .heart:nth-child(2) {
            top: 25%;
            right: 15%;
            animation-delay: 2s;
        }

        .heart:nth-child(3) {
            bottom: 30%;
            left: 20%;
            animation-delay: 4s;
        }

        .heart:nth-child(4) {
            bottom: 15%;
            right: 25%;
            animation-delay: 6s;
        }

        @keyframes heartFloat {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Bouncing donation icons */
        .donation-icon {
            position: absolute;
            font-size: 24px;
            color: rgba(242, 133, 133, 0.8);
            animation: bounce 3s ease-in-out infinite;
            z-index: 1;
        }

        .donation-icon:nth-child(1) {
            top: 20%;
            right: 20%;
            animation-delay: 0s;
        }

        .donation-icon:nth-child(2) {
            bottom: 25%;
            left: 15%;
            animation-delay: 1.5s;
        }

        .donation-icon:nth-child(3) {
            top: 40%;
            right: 30%;
            animation-delay: 3s;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-15px);
            }
        }

        /* Sparkle effect */
        .sparkle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            animation: sparkle 2s linear infinite;
            z-index: 1;
        }

        .sparkle:nth-child(1) {
            top: 30%;
            left: 25%;
            animation-delay: 0s;
        }

        .sparkle:nth-child(2) {
            top: 60%;
            right: 20%;
            animation-delay: 0.5s;
        }

        .sparkle:nth-child(3) {
            bottom: 40%;
            left: 30%;
            animation-delay: 1s;
        }

        .sparkle:nth-child(4) {
            top: 50%;
            right: 35%;
            animation-delay: 1.5s;
        }

        @keyframes sparkle {
            0%, 100% {
                opacity: 0;
                transform: scale(0);
            }
            50% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Background animation elements (outside login form) */
        .background-animations {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            overflow: hidden;
        }

        .bg-floating-element {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: bgFloat 15s linear infinite;
        }

        @keyframes bgFloat {
            0% {
                transform: translateY(100vh) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.7;
            }
            90% {
                opacity: 0.7;
            }
            100% {
                transform: translateY(-100px) translateX(100px) rotate(360deg);
                opacity: 0;
            }
        }

        .bg-heart {
            position: absolute;
            font-size: 24px;
            color: rgba(242, 133, 133, 0.3);
            animation: bgHeartFloat 12s linear infinite;
            z-index: 1;
        }

        @keyframes bgHeartFloat {
            0% {
                transform: translateY(100vh) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.5;
            }
            90% {
                opacity: 0.5;
            }
            100% {
                transform: translateY(-100px) translateX(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        .bg-donation-icon {
            position: absolute;
            font-size: 28px;
            color: rgba(255, 255, 255, 0.4);
            animation: bgDonationFloat 20s linear infinite;
            z-index: 1;
        }

        @keyframes bgDonationFloat {
            0% {
                transform: translateY(100vh) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
            }
            90% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-100px) translateX(150px) rotate(360deg);
                opacity: 0;
            }
        }

        .pulse-ring {
            position: absolute;
            border: 2px solid rgba(242, 133, 133, 0.3);
            border-radius: 50%;
            animation: pulseRing 4s linear infinite;
        }

        @keyframes pulseRing {
            0% {
                width: 0;
                height: 0;
                opacity: 1;
            }
            100% {
                width: 200px;
                height: 200px;
                opacity: 0;
            }
        }

        .wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100px;
            background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 1200 120" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="%23f28585"/></svg>');
            background-size: 1200px 100px;
            animation: wave 10s linear infinite;
        }

        .wave:nth-child(2) {
            animation-delay: -5s;
            opacity: 0.5;
        }

        @keyframes wave {
            0% {
                background-position-x: 0;
            }
            100% {
                background-position-x: 1200px;
            }
        }

        .background-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(246, 184, 184, 0.1);
            animation: float 8s ease-in-out infinite;
        }

        .circle:nth-child(1) {
            width: 200px;
            height: 200px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .circle:nth-child(2) {
            width: 150px;
            height: 150px;
            bottom: 15%;
            right: 10%;
            animation-delay: 2s;
        }

        .circle:nth-child(3) {
            width: 100px;
            height: 100px;
            top: 60%;
            left: 5%;
            animation-delay: 4s;
        }

        .circle:nth-child(4) {
            width: 120px;
            height: 120px;
            top: 20%;
            right: 5%;
            animation-delay: 6s;
        }

        @media (max-width: 950px) {
            .login-wrapper {
                width: 90%;
                height: auto;
                flex-direction: column;
            }
            
            .logo-section, .login-section {
                padding: 30px 20px;
            }
            
            .logo-image {
                max-height: 200px;
            }
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 20px 15px;
            }
            
            .remember-forgot {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .floating-element, .heart, .donation-icon, .sparkle {
                display: none;
            }
            
            .bg-floating-element, .bg-heart, .bg-donation-icon {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .login-wrapper {
                width: 95%;
            }
            
            .logo-section, .login-section {
                padding: 20px 15px;
            }
            
            .logo-text {
                font-size: 20px;
            }
            
            .login-box h2 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <!-- Background animations (outside login form) -->
    <div class="background-animations" id="backgroundAnimations">
        <!-- Waves at the bottom -->
        <div class="wave"></div>
        <div class="wave"></div>
    </div>
    
    <!-- Background decorative elements -->
    <div class="background-elements">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
    
    <div class="login-wrapper">
        <!-- Floating animation elements -->
        <div class="floating-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>
        
        <!-- Heart animations for pink area -->
        <div class="heart">❤</div>
        <div class="heart">❤</div>
        <div class="heart">❤</div>
        <div class="heart">❤</div>
        
        <!-- Donation icons -->
        <div class="donation-icon">🎁</div>
        <div class="donation-icon">🤝</div>
        <div class="donation-icon">💝</div>
        
        <!-- Sparkle effects -->
        <div class="sparkle"></div>
        <div class="sparkle"></div>
        <div class="sparkle"></div>
        <div class="sparkle"></div>
        
        <!-- Logo section -->
        <div class="logo-section">
            <div class="logo-container">
                <img src="picture/logo.png" alt="Donation Management System Logo" class="logo-image">
                <div class="logo-text">Donation Management System</div>
                <div class="logo-subtext">Bringing Hope to Those in Need</div>
            </div>
        </div>
        
        <!-- Login form section -->
        <div class="login-section">
            <div class="login-container">
                <div class="login-box">
                    <form method="POST" action="">
                        <h2>Admin Login</h2>
                        
                        <?php if (isset($error)): ?>
                        <div class="error-message">
                            <?php echo $error; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="input-box">
                            <input type="email" name="email" value="<?php echo htmlspecialchars($saved_email); ?>" required>
                            <label>Email Address</label>
                            <span class="icon">
                                <ion-icon name="mail"></ion-icon>
                            </span>
                        </div>
                        <div class="input-box">
                            <input type="password" name="password" id="password" required>
                            <label>Password</label>
                            <span class="icon" id="togglePassword">
                                <ion-icon name="eye-off"></ion-icon>
                            </span>
                        </div>
                        <div class="remember-forgot">
                            <label>
                                <input type="checkbox" name="remember" <?php echo !empty($saved_email) ? 'checked' : ''; ?>> Remember Me
                            </label>
                            <a href="admin_forgot_password.php">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn">Login</button>
                        
                        <div class="system-info">
                            Donation Management System v1.0 - Supporting Elderly Homes, Orphanages, Disability Centers, and Stray Animal Centers
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    
    <script>
        // Password visibility toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const eyeIcon = togglePassword.querySelector('ion-icon');
        
        togglePassword.addEventListener('click', function() {
            // Toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Toggle the eye icon
            if (type === 'password') {
                eyeIcon.setAttribute('name', 'eye-off');
            } else {
                eyeIcon.setAttribute('name', 'eye');
            }
        });
        
        // Optional: Add focus effect to password field when clicking the icon
        togglePassword.addEventListener('mousedown', function(e) {
            e.preventDefault(); // Prevent losing focus from the password field
        });

        // Add subtle animation to form inputs when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.input-box input');
            inputs.forEach((input, index) => {
                input.style.animation = `fadeIn 0.5s ease ${index * 0.1}s both`;
            });
        });

        // Create additional floating elements dynamically for login section
        document.addEventListener('DOMContentLoaded', function() {
            const loginSection = document.querySelector('.login-section');
            for (let i = 0; i < 5; i++) {
                const element = document.createElement('div');
                element.className = 'floating-element';
                element.style.width = `${Math.random() * 40 + 20}px`;
                element.style.height = element.style.width;
                element.style.left = `${Math.random() * 80 + 10}%`;
                element.style.top = `${Math.random() * 80 + 10}%`;
                element.style.animationDelay = `${Math.random() * 6}s`;
                element.style.background = `rgba(255, 255, 255, ${Math.random() * 0.3 + 0.1})`;
                loginSection.appendChild(element);
            }
        });

        // Create background animation elements
        document.addEventListener('DOMContentLoaded', function() {
            const backgroundAnimations = document.getElementById('backgroundAnimations');
            const icons = ['❤', '🎁', '🤝', '💝', '🌟', '✨', '🕊️'];
            
            // Create floating background elements
            for (let i = 0; i < 15; i++) {
                const element = document.createElement('div');
                element.className = 'bg-floating-element';
                element.style.width = `${Math.random() * 60 + 20}px`;
                element.style.height = element.style.width;
                element.style.left = `${Math.random() * 100}%`;
                element.style.animationDelay = `${Math.random() * 15}s`;
                element.style.animationDuration = `${Math.random() * 10 + 10}s`;
                backgroundAnimations.appendChild(element);
            }
            
            // Create background hearts
            for (let i = 0; i < 10; i++) {
                const heart = document.createElement('div');
                heart.className = 'bg-heart';
                heart.innerHTML = '❤';
                heart.style.left = `${Math.random() * 100}%`;
                heart.style.fontSize = `${Math.random() * 20 + 16}px`;
                heart.style.animationDelay = `${Math.random() * 12}s`;
                heart.style.animationDuration = `${Math.random() * 8 + 10}s`;
                backgroundAnimations.appendChild(heart);
            }
            
            // Create background donation icons
            for (let i = 0; i < 8; i++) {
                const icon = document.createElement('div');
                icon.className = 'bg-donation-icon';
                icon.innerHTML = icons[Math.floor(Math.random() * icons.length)];
                icon.style.left = `${Math.random() * 100}%`;
                icon.style.animationDelay = `${Math.random() * 20}s`;
                icon.style.animationDuration = `${Math.random() * 10 + 15}s`;
                backgroundAnimations.appendChild(icon);
            }
            
            // Create pulse rings
            for (let i = 0; i < 5; i++) {
                const ring = document.createElement('div');
                ring.className = 'pulse-ring';
                ring.style.left = `${Math.random() * 100}%`;
                ring.style.top = `${Math.random() * 100}%`;
                ring.style.animationDelay = `${Math.random() * 4}s`;
                backgroundAnimations.appendChild(ring);
            }
        });
    </script>
</body>
</html>
