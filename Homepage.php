<?php
session_start(); 

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

$mock_db_data = [
    "Donor_FName" => "Jane",
    "Donor_LName" => "Doe",
    "Donor_Email" => "jane.doe@example.com",
    "Donor_ContactNumber" => "012-3456789",
    "Donor_ICNumber" => "900101-14-5678",
    "Donor_DOB" => "1990-01-01",
    "Donor_Address" => "123 Mock St, Kuala Lumpur, 50450",
    "Donor_Description" => "Loyal donor since 2022."
];


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
   
    $donor = $mock_db_data;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard</title>
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
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            padding: 20px;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card h3 {
            margin-bottom: 15px;
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }
        
        .info-item {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }
        
        .info-label {
            font-weight: bold;
        }
        
        .card a {
            color: var(--primary);
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
            transition: color 0.3s;
        }
        
        .card a:hover {
            color: #e74c3c;
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
        
        <div class="logo">Donor Platform</div>
        
       
        <div class="header-right">
            <div class="function-links">
                <a href="Activity Page.php">Activity</a>
                <a href="History.php">History</a>
                <a href="New&Story.php">News & Story</a>
                <a href="Special_case Page.php">Special Case</a>
                <a href="Branch Page.php">Branch</a>
                <a href="Profile.php">Profile</a>
            </div>
        </div>
        
      
        <div class="header-center">
            <div class="search-box">
                <input type="text" placeholder="Search...">
            </div>
            <a href="Payment_page.php" class="donate-btn">Donate Now</a>
        </div>
    </header>

    <div class="donor-container">
        <div class="welcome-section">
            <h1>Welcome to Dashboard</h1>
            <p>Thank you for being part of our donor community</p>
        </div>
        
        <div class="dashboard-cards">
            <div class="card">
                <h3>Personal Information</h3>
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span><?php echo htmlspecialchars($donor['Donor_FName'] . ' ' . $donor['Donor_LName']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span><?php echo htmlspecialchars($donor['Donor_Email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Contact:</span>
                    <span><?php echo htmlspecialchars($donor['Donor_ContactNumber']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">IC Number:</span>
                    <span><?php echo htmlspecialchars($donor['Donor_ICNumber']); ?></span>
                </div>
            </div>
            
            <div class="card">
                <h3>Additional Details</h3>
                <div class="info-item">
                    <span class="info-label">Date of Birth:</span>
                    <span><?php echo date('F j, Y', strtotime($donor['Donor_DOB'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Address:</span>
                    <span><?php echo htmlspecialchars($donor['Donor_Address']); ?></span>
                </div>
                <?php if (!empty($donor['Donor_Description'])): ?>
                <div class="info-item">
                    <span class="info-label">Description:</span>
                    <span><?php echo htmlspecialchars($donor['Donor_Description']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            
        </div>
    </div>
</body>
</html>
