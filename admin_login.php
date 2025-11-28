<?php
// admin_login.php - Admin Login Page
session_start();

// Include database connection
include 'dataconnection.php';

// Create login_attempts table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL,
    INDEX idx_email (email),
    INDEX idx_time (attempt_time)
)";

if ($conn) {
    $conn->query($create_table_sql);
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Get client IP address for tracking
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // Basic validation
    if (!empty($email) && !empty($password)) {
        if ($conn) {
            // Check if this EMAIL is currently locked out (only check by email, not IP)
            $lock_check_sql = "SELECT * FROM login_attempts 
                              WHERE email = ? 
                              AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                              AND status = 'locked'
                              ORDER BY attempt_time DESC 
                              LIMIT 1";
            $lock_stmt = $conn->prepare($lock_check_sql);
            $lock_stmt->bind_param("s", $email);
            $lock_stmt->execute();
            $lock_result = $lock_stmt->get_result();
            
            if ($lock_result && $lock_result->num_rows > 0) {
                $lock_data = $lock_result->fetch_assoc();
                $remaining_time = strtotime($lock_data['attempt_time']) + (30 * 60) - time();
                $remaining_minutes = ceil($remaining_time / 60);
                
                if ($remaining_time > 0) {
                    $error = "Account temporarily locked. Please try again in " . $remaining_minutes . " minutes.";
                    $lock_stmt->close();
                } else {
                    // Lock expired, delete the lock record
                    $delete_lock_sql = "DELETE FROM login_attempts WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_lock_sql);
                    $delete_stmt->bind_param("i", $lock_data['id']);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    $lock_stmt->close();
                }
            } else {
                $lock_stmt->close();
            }
            
            // If there's no lock or lock expired, proceed with login
            if (!isset($error)) {
                // Query admin information using prepared statements
                $sql = "SELECT * FROM admin WHERE Admin_Email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $admin = $result->fetch_assoc();
                    
                    // Simple password verification (no hashing)
                    if ($password === $admin['Admin_Password']) {
                        // Login successful - reset attempt counter for this EMAIL
                        $reset_sql = "DELETE FROM login_attempts WHERE email = ?";
                        $reset_stmt = $conn->prepare($reset_sql);
                        $reset_stmt->bind_param("s", $email);
                        $reset_stmt->execute();
                        $reset_stmt->close();
                        
                        // Set session variables
                        $_SESSION['admin_id'] = $admin['Admin_ID'];
                        $_SESSION['admin_name'] = $admin['Admin_FName'] . ' ' . $admin['Admin_LName'];
                        $_SESSION['admin_email'] = $admin['Admin_Email'];
                        $_SESSION['login_time'] = time();
                        
                        // Handle "Remember me" feature
                        if ($remember) {
                            setcookie('admin_email', $email, time() + (86400 * 30), "/");
                        } else {
                            setcookie('admin_email', '', time() - 3600, "/");
                        }
                        
                        // Redirect to dashboard
                        header("Location: admin_dashboard.php");
                        exit();
                    } else {
                        // Login failed - record the attempt for this EMAIL
                        $record_sql = "INSERT INTO login_attempts (email, ip_address, attempt_time, status) 
                                      VALUES (?, ?, NOW(), 'failed')";
                        $record_stmt = $conn->prepare($record_sql);
                        $record_stmt->bind_param("ss", $email, $ip_address);
                        $record_stmt->execute();
                        $record_stmt->close();
                        
                        // Check if we need to lock the account for this EMAIL
                        $count_sql = "SELECT COUNT(*) as attempt_count FROM login_attempts 
                                     WHERE email = ? 
                                     AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
                        $count_stmt = $conn->prepare($count_sql);
                        $count_stmt->bind_param("s", $email);
                        $count_stmt->execute();
                        $count_result = $count_stmt->get_result();
                        $count_data = $count_result->fetch_assoc();
                        $attempt_count = $count_data['attempt_count'];
                        $count_stmt->close();
                        
                        if ($attempt_count >= 5) {
                            // Lock the account for this EMAIL
                            $lock_sql = "INSERT INTO login_attempts (email, ip_address, attempt_time, status) 
                                        VALUES (?, ?, NOW(), 'locked')";
                            $lock_stmt = $conn->prepare($lock_sql);
                            $lock_stmt->bind_param("ss", $email, $ip_address);
                            $lock_stmt->execute();
                            $lock_stmt->close();
                            
                            $error = 'Too many failed login attempts. Your account has been locked for 30 minutes.';
                        } else {
                            $remaining_attempts = 5 - $attempt_count;
                            $error = 'Invalid email or password! ' . $remaining_attempts . ' attempts remaining.';
                        }
                    }
                } else {
                    // Email not found
                    $error = 'Invalid email or password!';
                }
                $stmt->close();
            }
        } else {
            $error = 'Database connection failed! Please check system configuration.';
        }
    } else {
        $error = 'Please enter email and password!';
    }
}

// Check if already logged in, if yes then redirect
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Get saved email (if any)
$saved_email = $_COOKIE['admin_email'] ?? '';

// Check for existing lock on page load for this EMAIL
$lock_message = '';
if ($conn && !empty($saved_email)) {
    $lock_check_sql = "SELECT * FROM login_attempts 
                      WHERE email = ? 
                      AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                      AND status = 'locked'
                      ORDER BY attempt_time DESC 
                      LIMIT 1";
    $lock_stmt = $conn->prepare($lock_check_sql);
    $lock_stmt->bind_param("s", $saved_email);
    $lock_stmt->execute();
    $lock_result = $lock_stmt->get_result();
    
    if ($lock_result && $lock_result->num_rows > 0) {
        $lock_data = $lock_result->fetch_assoc();
        $remaining_time = strtotime($lock_data['attempt_time']) + (30 * 60) - time();
        $remaining_minutes = ceil($remaining_time / 60);
        
        if ($remaining_time > 0) {
            $lock_message = "Your account is temporarily locked. Please try again in " . $remaining_minutes . " minutes.";
        }
    }
    $lock_stmt->close();
}
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
            z-index: 100;
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
            z-index: 10;
        }

        .login-box {
            position: relative;
            z-index: 20;
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

        .input-box input:disabled {
            background-color: #f5f5f5;
            border-color: #ddd;
            color: #999;
            cursor: not-allowed;
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
            z-index: 5;
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

        .btn:disabled {
            background: linear-gradient(135deg, #cccccc, #aaaaaa);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn:disabled::before {
            display: none;
        }

        .btn:disabled:hover {
            transform: none;
            box-shadow: none;
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

        .btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(242, 133, 133, 0.4);
        }

        .btn:hover:not(:disabled)::before {
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

        .lock-message {
            color: #e67e22;
            background: #fdebd0;
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

        /* Fix: All animated elements must have pointer-events: none */
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
            bottom: 0;
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

        /* Improved waves at the bottom */
        .wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 150px;
            background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 1200 120" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="%23f28585"/><path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="%23f28585"/><path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="%23f28585"/></svg>');
            background-size: 1200px 150px;
            animation: wave 12s linear infinite;
            transform: rotate(180deg);
        }

        .wave:nth-child(2) {
            animation: wave-reverse 16s linear infinite;
            opacity: 0.7;
            background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 1200 120" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="%23f6b8b8"/><path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="%23f6b8b8"/><path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="%23f6b8b8"/></svg>');
            background-size: 1200px 150px;
        }

        .wave:nth-child(3) {
            animation: wave 20s linear infinite;
            opacity: 0.4;
            background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 1200 120" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="%23fff5e4"/><path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="%23fff5e4"/><path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="%23fff5e4"/></svg>');
            background-size: 1200px 150px;
        }

        @keyframes wave {
            0% {
                background-position-x: 0;
            }
            100% {
                background-position-x: 1200px;
            }
        }

        @keyframes wave-reverse {
            0% {
                background-position-x: 1200px;
            }
            100% {
                background-position-x: 0;
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
        <!-- Improved waves at the bottom -->
        <div class="wave"></div>
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
                <img src="logo.jpg" alt="Donation Management System Logo" class="logo-image">
                <div class="logo-text">LOVE BRIDGE</div>
                <div class="logo-subtext">Donation Management System<br>Bringing hope to those in need</div>
            </div>
        </div>
        
        <!-- Login form section -->
        <div class="login-section">
            <div class="login-container">
                <div class="login-box">
                    <form method="POST" action="admin_login.php" id="loginForm">
                        <h2>Admin Login</h2>
                        
                        <?php if (!empty($lock_message)): ?>
                        <div class="lock-message" id="lockMessage">
                            <?php echo $lock_message; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error)): ?>
                        <div class="error-message">
                            <?php echo $error; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="input-box">
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($saved_email); ?>" 
                                   <?php echo !empty($lock_message) ? 'disabled' : 'required'; ?>>
                            <label>Email Address</label>
                            <span class="icon">
                                <ion-icon name="mail"></ion-icon>
                            </span>
                        </div>
                        <div class="input-box">
                            <input type="password" name="password" id="password" 
                                   <?php echo !empty($lock_message) ? 'disabled' : 'required'; ?>>
                            <label>Password</label>
                            <span class="icon" id="togglePassword">
                                <ion-icon name="eye-off"></ion-icon>
                            </span>
                        </div>
                        <div class="remember-forgot">
                            <label>
                                <input type="checkbox" name="remember" <?php echo !empty($saved_email) ? 'checked' : ''; ?>
                                       <?php echo !empty($lock_message) ? 'disabled' : ''; ?>> Remember me
                            </label>
                        </div>
                        <button type="submit" class="btn" id="loginBtn" 
                                <?php echo !empty($lock_message) ? 'disabled' : ''; ?>>
                            <?php echo !empty($lock_message) ? 'Account Locked' : 'Login'; ?>
                        </button>
                        
                        <div class="system-info">
                            Donation Management System v1.0 - Supporting nursing homes, orphanages, disability centers, and stray animal centers
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
        
        if (togglePassword && password) {
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
        }

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

        // Auto-refresh lock status every minute
        setInterval(function() {
            const lockMessage = document.getElementById('lockMessage');
            const loginBtn = document.getElementById('loginBtn');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const rememberCheckbox = document.querySelector('input[name="remember"]');
            
            if (lockMessage) {
                // Reload the page to check if lock has expired
                location.reload();
            }
        }, 60000); // Check every minute

        // Form submission handler
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            // Form will submit normally to admin_login.php
            console.log('Login form submitted');
            // No need to prevent default - let it submit normally
        });
    </script>
</body>
</html>
