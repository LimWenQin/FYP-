<?php
session_start(); 
include 'dataconnection.php';

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;


$donor = [
    "Donor_FName" => "Guest",
    "Donor_LName" => "",
    "Donor_Email" => "N/A",
    "Donor_ContactNumber" => "N/A",
    "Donor_ICNumber" => "N/A",
    "Donor_DOB" => "2000-01-01",
    "Donor_Address" => "N/A",
    "Donor_Description" => ""
];


if ($logged_in && isset($_SESSION['donor_id'])) {
    $query = "SELECT * FROM donor WHERE Donor_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['donor_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $donor = $result->fetch_assoc();
    }
    $stmt->close();
}


$update_success = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $logged_in) {
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    $description = $_POST['description'];
    
    $update_query = "UPDATE donor SET Donor_FName = ?, Donor_LName = ?, Donor_Email = ?, Donor_ContactNumber = ?, Donor_Address = ?, Donor_Description = ? WHERE Donor_ID = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssssssi", $fname, $lname, $email, $contact, $address, $description, $_SESSION['donor_id']);
    
    if ($stmt->execute()) {
        $update_success = true;
       
        $_SESSION['donor_name'] = $fname . ' ' . $lname;
        
        $donor['Donor_FName'] = $fname;
        $donor['Donor_LName'] = $lname;
        $donor['Donor_Email'] = $email;
        $donor['Donor_ContactNumber'] = $contact;
        $donor['Donor_Address'] = $address;
        $donor['Donor_Description'] = $description;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Donor Platform</title>
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
        
        .page-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 32px;
            color: var(--text);
        }
        
        .profile-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary);
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: var(--text);
            margin-right: 20px;
        }
        
        .profile-info h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .profile-info p {
            color: var(--light-text);
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-section h3 {
            margin-bottom: 15px;
            color: var(--primary);
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .submit-btn {
            background-color: var(--primary);
            color: var(--text);
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            font-size: 16px;
        }
        
        .submit-btn:hover {
            background-color: #f0a8a8;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .login-prompt {
            text-align: center;
            padding: 40px;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
        }
        
        .login-prompt a {
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
        }
        
        .login-prompt a:hover {
            text-decoration: underline;
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
            
            .header-top, .header {
                padding: 15px 20px;
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
            
            .page-container {
                padding: 15px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
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
                <a href="profile.php" style="color: #e74c3c;">Profile</a>
            </div>
        </div>
        
        <div class="header-center">
            <div class="search-box">
                <input type="text" placeholder="Search...">
            </div>
            <a href="Payment_page.php" class="donate-btn">Donate Now</a>
        </div>
    </header>

    <div class="page-container">
        <h1 class="page-title">My Profile</h1>
        
        <?php if ($logged_in): ?>
            <div class="profile-container">
                <?php if ($update_success): ?>
                    <div class="success-message">
                        Profile updated successfully!
                    </div>
                <?php endif; ?>
                
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($donor['Donor_FName'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($donor['Donor_FName'] . ' ' . $donor['Donor_LName']); ?></h2>
                        <p>Member since <?php echo date('F Y', strtotime('-6 months')); ?></p>
                    </div>
                </div>
                
                <form method="POST" action="profile.php">
                    <div class="form-section">
                        <h3>Personal Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fname">First Name</label>
                                <input type="text" id="fname" name="fname" value="<?php echo htmlspecialchars($donor['Donor_FName']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="lname">Last Name</label>
                                <input type="text" id="lname" name="lname" value="<?php echo htmlspecialchars($donor['Donor_LName']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($donor['Donor_Email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact">Contact Number</label>
                            <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($donor['Donor_ContactNumber']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Additional Information</h3>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address"><?php echo htmlspecialchars($donor['Donor_Address']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">About Me</label>
                            <textarea id="description" name="description" placeholder="Tell us a little about yourself..."><?php echo htmlspecialchars($donor['Donor_Description']); ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">Update Profile</button>
                </form>
            </div>
        <?php else: ?>
            <div class="login-prompt">
                <h2>Please log in to view and edit your profile</h2>
                <p>You need to be logged in to access your personal information.</p>
                <a href="donor_login.php" class="auth-btn login-btn" style="margin-top: 20px; display: inline-block;">Login Now</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
