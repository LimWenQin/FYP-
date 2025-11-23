<?php
session_start();

include 'dataconnection.php';

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
                
                // Redirect to dashboard
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
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary);
            padding: 20px 50px;
            box-shadow: 0 2px 6px var(--shadow);
        }
        
        .logo {
            font-weight: bold;
            font-size: 36px;
            color: var(--text);
        }
        
        .header .function-links a {
            margin-left: 15px;
            text-decoration: none;
            font-size: 20px;
            color: var(--text);
            font-weight: bold;
            transition: opacity 0.3s;
        }
        
        .header .function-links a:hover {
            opacity: 0.8;
        }
        
        .search {
            padding: 6px;
            font-size: 16px;
            border: 1px solid var(--primary);
            border-radius: 4px;
            outline: none;
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
    <header class="header">
        <div class="logo">Donor Platform</div>
        <div class="function-links">
            <a href="index.php">Home</a>
            <a href="about.php">About Us</a>
            <a href="projects.php">Donation Projects</a>
            <a href="contact.php">Contact</a>
        </div>
        <input type="text" class="search" placeholder="Search...">
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