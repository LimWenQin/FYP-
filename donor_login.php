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
include 'header_UI.php';
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

