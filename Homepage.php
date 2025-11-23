<?php
session_start(); 
include 'dataconnection.php';

// Check if user is logged in
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Default donor data (for guests)
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

// If user logged in → fetch real data
if ($logged_in && isset($_SESSION['donor_id'])) {

    // IMPORTANT: Change TABLE NAME here to the REAL name in your DB
    $query = "SELECT * FROM donor WHERE Donor_ID = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['donor_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    // If record exists
    if ($result->num_rows > 0) {
        $donor = $result->fetch_assoc();
    }

    $stmt->close();
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-name {
            font-weight: bold;
        }
        
        .login-btn {
            background-color: var(--white);
            color: var(--text);
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .login-btn:hover {
            background-color: #f0a8a8;
        }
        
        .container {
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
                padding: 15px;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">Donor Platform</div>
        <div class="function-links">
            <a href="index.php">Home</a>
            <a href="projects.php">Donation Projects</a>
            <a href="history.php">Donation History</a>
            <a href="profile.php">My Profile</a>
        </div>
        <div class="user-info">
            <span class="user-name">Welcome, 
    <?php echo isset($_SESSION['donor_name']) ? $_SESSION['donor_name'] : "Guest"; ?>
</span>

            <a href="donor_login.php" class="login-btn">Login</a>
        </div>
    </header>

    <div class="container">
        <div class="welcome-section">
            <h1>Welcome to Your Dashboard</h1>
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
            
            <div class="card">
                <h3>Quick Actions</h3>
                <p><a href="profile.php">Edit Profile</a></p>
                <p><a href="projects.php">Browse Donation Projects</a></p>
                <p><a href="history.php">View Donation History</a></p>
                <p><a href="contact.php">Contact Support</a></p>
            </div>
        </div>
    </div>
</body>
</html>