<?php


include 'dataconnection.php';
include 'header_function.php';


$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        // Check if user exists
        $query = "SELECT Donor_ID, Donor_FName, Donor_LName, Donor_Password FROM donor WHERE Donor_Email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['Donor_Password'])) {
                // Set session variables
                $_SESSION['donor_id'] = $user['Donor_ID'];
                $_SESSION['donor_name'] = $user['Donor_FName'] . ' ' . $user['Donor_LName'];
                $_SESSION['logged_in'] = true;
                
                
                header("Location:homepage.php");
                exit();
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Login</title>
    <link rel="stylesheet" href="donor_design.css">
    <link rel="stylesheet" href="donor_header.css">
    <style>
        :root {
            --primary: #F6B8B8;
            --secondary: #FFF5E4;
            --text: #4A4A4A;
            --light-text: #777777;
            --white: #FFFFFF;
            --shadow: rgba(0, 0, 0, 0.1);
            --error: #e74c3c;
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
            background-color: var(--text);
            color: var(--white);
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
        
        .logo {
            font-weight: bold;
            font-size: 36px;
            color: var(--text);
            text-align: left; 
            grid-column: 1;
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
        
        
        .donor-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .welcome-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .welcome-section h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .container {
            display: flex;
            flex-direction: row;
            padding: 20px;
            min-height: calc(100vh - 120px);
            align-items: center;
            justify-content: center;
        }
        
        .form-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            padding: 30px;
            width: 100%;
            max-width: 450px;
            margin: 20px;
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 25px;
            color: var(--text);
            font-size: 28px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text);
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(246, 184, 184, 0.3);
            outline: none;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: var(--text);
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        
        .btn:hover {
            background-color: #f0a8a8;
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .form-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            color: var(--error);
            font-size: 14px;
            margin-top: 5px;
            padding: 8px;
            background-color: rgba(231, 76, 60, 0.1);
            border-radius: 4px;
            display: <?php echo !empty($error_message) ? 'block' : 'none'; ?>;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            .logo {
                font-size: 28px;
            }
            
            .container {
                padding: 10px;
            }
            
            .form-container {
                margin: 10px;
                padding: 20px;
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
        
        <div class="logo">Donor Platform</div>
        
       
        <div class="header-right">
            <div class="function-links">
                <a href="Activity Page.php">Activity</a>
                <a href="History.php">History</a>
                <a href="New&Story.php">News & Story</a>
                <a href="Special_case Page.php">Special Case</a>
                <a href="Branch Page.php">Branch</a>
                <a href="profile.php">Profile</a>
            </div>
        </div>
        
      
        <div class="header-center">
            <div class="search-box">
                <input type="text" placeholder="Search...">
            </div>
            <a href="Payment_page.php" class="donate-btn">Donate Now</a>
        </div>
    </header>
        

    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Login to Your Account</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="donor_login.php" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="form-footer">
                <p>Don't have an account? <a href="donor_register.php">Register here</a></p>
                <p><a href="forgot_password.php">Forgot your password?</a></p>
            </div>
        </div>
    </div>
</body>
</html>
