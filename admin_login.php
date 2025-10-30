<?php
// admin_login.php - Complete Login Page
session_start();

// Database configuration
$servername = "127.0.0.1";
$username = "your_username";
$password = "your_password";
$dbname = "donation system";

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Basic validation
    if (!empty($email) && !empty($password)) {
        // Create database connection
        $conn = new mysqli($servername, $username, $password, $dbname);
        
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
            $conn->close();
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

        .login-container {
            display: flex;
            width: 900px;
            height: 500px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
            z-index: 10;
        }

        .images-section {
            flex: 1;
            position: relative;
            overflow: hidden;
            background: #f6b8b8;
        }

        .image-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            width: 100%;
            height: 100%;
            position: relative;
        }

        .grid-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: relative;
            transition: all 0.5s ease;
            filter: blur(2px) brightness(0.9);
            opacity: 0.8;
        }

        .grid-image:nth-child(1) {
            mask: linear-gradient(135deg, rgba(0,0,0,1) 0%, rgba(0,0,0,0.7) 50%, rgba(0,0,0,0) 100%);
        }

        .grid-image:nth-child(2) {
            mask: linear-gradient(225deg, rgba(0,0,0,1) 0%, rgba(0,0,0,0.7) 50%, rgba(0,0,0,0) 100%);
        }

        .grid-image:nth-child(3) {
            mask: linear-gradient(45deg, rgba(0,0,0,1) 0%, rgba(0,0,0,0.7) 50%, rgba(0,0,0,0) 100%);
        }

        .grid-image:nth-child(4) {
            mask: linear-gradient(315deg, rgba(0,0,0,1) 0%, rgba(0,0,0,0.7) 50%, rgba(0,0,0,0) 100%);
        }

        .login-section {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff5e4;
        }

        .login-box {
            width: 100%;
        }

        .login-box h2 {
            color: #4a4a4a;
            font-size: 2em;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
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
            box-shadow: 0 0 10px rgba(242, 133, 133, 0.3);
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
            background: #fff5e4;
            padding: 0 5px;
        }

        .input-box input:focus ~ label,
        .input-box input:valid ~ label {
            top: 0;
            font-size: 12px;
            color: #f28585;
            background: #fff5e4;
        }

        .input-box .icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #4a4a4a;
            font-size: 20px;
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
        }

        .remember-forgot input[type="checkbox"] {
            margin-right: 8px;
            accent-color: #f28585;
        }

        .remember-forgot a {
            color: #f28585;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .remember-forgot a:hover {
            color: #e66767;
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #f28585;
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #e66767;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(242, 133, 133, 0.4);
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
        }

        .background-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(246, 184, 184, 0.1);
        }

        .circle:nth-child(1) {
            width: 200px;
            height: 200px;
            top: 10%;
            left: 10%;
        }

        .circle:nth-child(2) {
            width: 150px;
            height: 150px;
            bottom: 15%;
            right: 10%;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                width: 90%;
                height: auto;
            }
            
            .images-section {
                height: 200px;
            }
            
            .login-section {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Background decorative elements -->
    <div class="background-elements">
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
    
    <div class="login-container">
        <!-- Images section -->
        <div class="images-section">
            <div class="image-grid">
                <img src="https://images.unsplash.com/photo-1537365587684-f490102e1225?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" alt="Elderly Home" class="grid-image">
                <img src="https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" alt="Orphanage" class="grid-image">
                <img src="https://images.unsplash.com/photo-1576675466969-38eeae4b41f6?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" alt="Disability Center" class="grid-image">
                <img src="https://images.unsplash.com/photo-1560743641-3914f2c45636?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" alt="Stray Animal Center" class="grid-image">
            </div>
        </div>
        
        <!-- Login form section -->
        <div class="login-section">
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
                        <input type="password" name="password" required>
                        <label>Password</label>
                        <span class="icon">
                            <ion-icon name="lock-closed"></ion-icon>
                        </span>
                    </div>
                    <div class="remember-forgot">
                        <label>
                            <input type="checkbox" name="remember" <?php echo !empty($saved_email) ? 'checked' : ''; ?>> Remember Me
                        </label>
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn">Login</button>
                    
                    <div class="system-info">
                        Donation Management System v1.0 - Supporting Elderly Homes, Orphanages, Disability Centers, and Stray Animal Centers
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>