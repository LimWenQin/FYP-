<?php
// admin_donor_management.php - Donor Management Page
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new donor
    if (isset($_POST['add_donor'])) {
        $fname = $_POST['donor_fname'];
        $lname = $_POST['donor_lname'];
        $contact = $_POST['donor_contact'];
        $icnumber = $_POST['donor_icnumber'];
        $email = $_POST['donor_email'];
        $password = $_POST['donor_password'];
        $address = $_POST['donor_address'];
        $dob = $_POST['donor_dob'];
        $description = $_POST['donor_description'];
        
        $sql = "INSERT INTO donor (Donor_FName, Donor_LName, Donor_ContactNumber, Donor_ICNumber, 
                Donor_Email, Donor_Password, Donor_Address, Donor_DOB, Donor_Description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssss", $fname, $lname, $contact, $icnumber, $email, $password, $address, $dob, $description);
        
        if ($stmt->execute()) {
            $success_message = "Donor added successfully!";
        } else {
            $error_message = "Error adding donor: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Update donor
    if (isset($_POST['update_donor'])) {
        $donor_id = $_POST['donor_id'];
        $fname = $_POST['donor_fname'];
        $lname = $_POST['donor_lname'];
        $contact = $_POST['donor_contact'];
        $icnumber = $_POST['donor_icnumber'];
        $email = $_POST['donor_email'];
        $password = $_POST['donor_password'];
        $address = $_POST['donor_address'];
        $dob = $_POST['donor_dob'];
        $description = $_POST['donor_description'];
        
        $sql = "UPDATE donor SET 
                Donor_FName = ?, Donor_LName = ?, Donor_ContactNumber = ?, Donor_ICNumber = ?,
                Donor_Email = ?, Donor_Password = ?, Donor_Address = ?, Donor_DOB = ?, Donor_Description = ?
                WHERE Donor_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssi", $fname, $lname, $contact, $icnumber, $email, $password, $address, $dob, $description, $donor_id);
        
        if ($stmt->execute()) {
            $success_message = "Donor updated successfully!";
        } else {
            $error_message = "Error updating donor: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Delete donor
    if (isset($_POST['delete_donor'])) {
        $donor_id = $_POST['donor_id'];
        
        $sql = "DELETE FROM donor WHERE Donor_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $donor_id);
        
        if ($stmt->execute()) {
            $success_message = "Donor deleted successfully!";
        } else {
            $error_message = "Error deleting donor: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle search
$search_query = "";
if (isset($_GET['search'])) {
    $search_query = $_GET['search'];
    $sql = "SELECT * FROM donor WHERE 
            Donor_FName LIKE ? OR 
            Donor_LName LIKE ? OR 
            Donor_Email LIKE ? OR 
            Donor_ContactNumber LIKE ? OR 
            Donor_ICNumber LIKE ?
            ORDER BY Donor_ID DESC";
    $stmt = $conn->prepare($sql);
    $search_param = "%" . $search_query . "%";
    $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Get all donors
    $sql = "SELECT * FROM donor ORDER BY Donor_ID DESC";
    $result = $conn->query($sql);
}

// Get statistics for the page
$total_donors = 0;
$sql_count = "SELECT COUNT(*) as count FROM donor";
$result_count = $conn->query($sql_count);
if ($result_count) {
    $row = $result_count->fetch_assoc();
    $total_donors = $row['count'];
}

$total_donations = 0;
$sql_donations = "SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus = 'completed'";
$result_donations = $conn->query($sql_donations);
if ($result_donations) {
    $row = $result_donations->fetch_assoc();
    $total_donations = $row['total'] ?: 0;
}

$total_branches = 0;
$sql_branches = "SELECT COUNT(*) as count FROM branch";
$result_branches = $conn->query($sql_branches);
if ($result_branches) {
    $row = $result_branches->fetch_assoc();
    $total_branches = $row['count'];
}

$total_activities = 0;
$sql_activities = "SELECT COUNT(*) as count FROM activity";
$result_activities = $conn->query($sql_activities);
if ($result_activities) {
    $row = $result_activities->fetch_assoc();
    $total_activities = $row['count'];
}

$total_items = 0;
$sql_items = "SELECT COUNT(*) as count FROM item_donation";
$result_items = $conn->query($sql_items);
if ($result_items) {
    $row = $result_items->fetch_assoc();
    $total_items = $row['count'];
}

$total_rewards = 0;
$sql_rewards = "SELECT COUNT(*) as count FROM reward_item";
$result_rewards = $conn->query($sql_rewards);
if ($result_rewards) {
    $row = $result_rewards->fetch_assoc();
    $total_rewards = $row['count'];
}

$recent_activities = [];
$sql_ra = "SELECT a.Activity_ID, a.Activity_Date, a.Activity_Details, a.Activity_Status, b.Branch_Name 
        FROM activity a 
        JOIN branch b ON a.Branch_ID = b.Branch_ID 
        ORDER BY a.Activity_Date DESC 
        LIMIT 5";
$result_ra = $conn->query($sql_ra);
if ($result_ra && $result_ra->num_rows > 0) {
    while ($row = $result_ra->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Management - Donation Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f9f9f9;
            color: #4a4a4a;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 70px;
            background: #f28585;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar.expanded {
            width: 250px;
        }

        .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logo:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            padding: 5px;
        }

        .logo-text {
            font-size: 20px;
            font-weight: bold;
            margin-top: 10px;
            opacity: 0;
            height: 0;
            overflow: hidden;
            transition: opacity 0.3s ease;
        }

        .sidebar.expanded .logo-text {
            opacity: 1;
            height: auto;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            white-space: nowrap;
            text-decoration: none;
            color: white;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid white;
        }

        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid white;
        }

        .menu-item ion-icon {
            font-size: 20px;
            margin-right: 15px;
            min-width: 20px;
        }

        .menu-text {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar.expanded .menu-text {
            opacity: 1;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            margin-left: 0;
        }

        .header {
            background: #f28585;
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 70px;
            height: 100%;
            background: #e66767;
            transition: width 0.3s ease;
            z-index: 1;
        }

        .sidebar.expanded + .main-content .header::before {
            width: 250px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            z-index: 2;
        }

        .menu-toggle {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 24px;
            height: 18px;
            cursor: pointer;
        }

        .menu-toggle span {
            display: block;
            height: 3px;
            width: 100%;
            background-color: white;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: white;
        }

        .header-logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            padding: 5px;
        }

        .header-logo-text {
            font-size: 20px;
            font-weight: bold;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 2;
        }

        .welcome {
            font-size: 18px;
            font-weight: 600;
        }

        .logout {
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .logout:hover {
            background: rgba(255,255,255,0.3);
        }

        .dashboard {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .page-title {
            font-size: 28px;
            margin-bottom: 30px;
            color: #4a4a4a;
            border-bottom: 2px solid #f6b8b8;
            padding-bottom: 10px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 4px solid #f28585;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: #f28585;
        }

        .stat-number {
            font-size: 2.5em;
            color: #f28585;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 16px;
            color: #7f8c8d;
        }

        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .main-content-section {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .section-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .section-card:hover {
            transform: translateY(-5px);
        }

        .section-title {
            font-size: 20px;
            margin-bottom: 15px;
            color: #4a4a4a;
            display: flex;
            align-items: center;
        }

        .section-title ion-icon {
            margin-right: 10px;
            color: #f28585;
        }

        .section-list {
            list-style: none;
        }

        .section-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-list li:last-child {
            border-bottom: none;
        }

        .section-list a {
            color: #f28585;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .section-list a:hover {
            color: #e66767;
            text-decoration: underline;
        }

        .badge {
            background: #f6b8b8;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .action-btn {
            background: #f28585;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .action-btn:hover {
            background: #e66767;
            transform: translateY(-2px);
        }

        .activity-item {
            padding: 15px;
            border-left: 4px solid #f28585;
            background: #fff5e4;
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
        }

        .activity-date {
            font-weight: bold;
            color: #f28585;
        }

        .activity-details {
            margin: 5px 0;
        }

        .activity-branch {
            color: #7f8c8d;
            font-size: 14px;
        }

        .activity-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-top: 5px;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-upcoming {
            background: #cce7ff;
            color: #004085;
        }

        .status-ongoing {
            background: #fff3cd;
            color: #856404;
        }

        /* Donor Management Specific Styles */
        .donor-management {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .management-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 300px;
        }

        .search-box button {
            background: #f28585;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .add-donor-btn {
            background: #f28585;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .donor-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .donor-table th,
        .donor-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .donor-table th {
            background-color: #f9f9f9;
            font-weight: 600;
            color: #4a4a4a;
        }

        .donor-table tr:hover {
            background-color: #f5f5f5;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .edit-btn, .delete-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .edit-btn {
            background: #4CAF50;
            color: white;
        }

        .delete-btn {
            background: #f44336;
            color: white;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 600px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .modal-title {
            font-size: 24px;
            color: #4a4a4a;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #7f8c8d;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .submit-btn {
            background: #f28585;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
            width: 100%;
        }

        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 1024px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-left, .header-right {
                width: 100%;
                justify-content: center;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: flex;
            }
            
            .header::before {
                display: none;
            }
            
            .management-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .donor-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        @media (min-width: 769px) {
            .menu-toggle {
                display: none;
            }
            
            .sidebar {
                position: fixed;
                height: 100vh;
            }
            
            .main-content {
                margin-left: 70px;
                transition: margin-left 0.3s ease;
            }
            
            .sidebar.expanded + .main-content {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo" id="sidebarToggle">
            <img src="picture" alt="Logo">
            <div class="logo-text">DonationMS</div>
        </div>
        
        <div class="sidebar-menu">
            <a href="admin_dashboard.php" class="menu-item">
                <ion-icon name="grid"></ion-icon>
                <div class="menu-text">Dashboard</div>
            </a>
            <a href="admin_donor_management.php" class="menu-item active">
                <ion-icon name="people"></ion-icon>
                <div class="menu-text">Donor Management</div>
            </a>
            <a href="#" class="menu-item">
                <ion-icon name="person-circle"></ion-icon>
                <div class="menu-text">Staff Management</div>
            </a>
        </div>
    </aside>
    
    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <div class="menu-toggle" id="menuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                
                <a href="admin_dashboard.php" class="header-logo">
                    <img src="picture" alt="Logo">
                    <div class="header-logo-text">DonationMS</div>
                </a>
            </div>
            
            <div class="header-right">
                <div class="welcome">Welcome back, <?php echo $_SESSION['admin_name']; ?>!</div>
                <a href="admin_logout.php" class="logout">Logout</a>
            </div>
        </div>
        
        <div class="dashboard">
            <h1 class="page-title">Donor Management</h1>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="people"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_donors; ?></div>
                    <div class="stat-label">Total Donors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="cash"></ion-icon>
                    </div>
                    <div class="stat-number">RM <?php echo number_format($total_donations, 2); ?></div>
                    <div class="stat-label">Total Donations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="business"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_branches; ?></div>
                    <div class="stat-label">Branch Locations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="calendar"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_activities; ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>
            </div>
            
            <div class="dashboard-content">
                <div class="main-content-section">
                    <div class="donor-management">
                        <div class="management-header">
                            <div class="search-box">
                                <form method="GET" action="">
                                    <input type="text" name="search" placeholder="Search donors..." value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button type="submit">Search</button>
                                </form>
                            </div>
                            <button class="add-donor-btn" onclick="openAddModal()">Add New Donor</button>
                        </div>

                        <?php if (isset($success_message)): ?>
                            <div class="message success"><?php echo $success_message; ?></div>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                            <div class="message error"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <?php if ($result && $result->num_rows > 0): ?>
                            <table class="donor-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Email</th>
                                        <th>IC Number</th>
                                        <th>Date of Birth</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['Donor_ID']; ?></td>
                                            <td><?php echo $row['Donor_FName'] . ' ' . $row['Donor_LName']; ?></td>
                                            <td><?php echo $row['Donor_ContactNumber']; ?></td>
                                            <td><?php echo $row['Donor_Email']; ?></td>
                                            <td><?php echo $row['Donor_ICNumber']; ?></td>
                                            <td><?php echo $row['Donor_DOB']; ?></td>
                                            <td class="action-buttons">
                                                <button class="edit-btn" onclick="openEditModal(<?php echo $row['Donor_ID']; ?>)">Edit</button>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="donor_id" value="<?php echo $row['Donor_ID']; ?>">
                                                    <button type="submit" name="delete_donor" class="delete-btn" onclick="return confirm('Are you sure you want to delete this donor?')">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">
                                <h3>No donors found</h3>
                                <p>There are no donors in the database. Click "Add New Donor" to add one.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="sidebar-content">
                    <div class="section-card">
                        <h2 class="section-title">
                            <ion-icon name="time"></ion-icon>
                            Recent Activities
                        </h2>
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-date">
                                        <?php echo date('M j, Y', strtotime($activity['Activity_Date'])); ?>
                                    </div>
                                    <div class="activity-details">
                                        <?php echo substr($activity['Activity_Details'], 0, 50); ?>...
                                    </div>
                                    <div class="activity-branch">
                                        <?php echo $activity['Branch_Name']; ?>
                                    </div>
                                    <div class="activity-status <?php echo 'status-' . strtolower($activity['Activity_Status']); ?>">
                                        <?php echo $activity['Activity_Status']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No recent activities found.</p>
                        <?php endif; ?>
                        <div class="quick-actions">
                            <a href="#" class="action-btn">View All Activities</a>
                        </div>
                    </div>
                    
                    <div class="section-card">
                        <h2 class="section-title">
                            <ion-icon name="bar-chart"></ion-icon>
                            Quick Stats
                        </h2>
                        <ul class="section-list">
                            <li>
                                <span>Item Donations</span>
                                <span class="badge"><?php echo $total_items; ?></span>
                            </li>
                            <li>
                                <span>Reward Items</span>
                                <span class="badge"><?php echo $total_rewards; ?></span>
                            </li>
                            <li>
                                <span>Recurring Donations</span>
                                <span class="badge">0</span>
                            </li>
                            <li>
                                <span>Achievements</span>
                                <span class="badge">0</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Donor Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Donor</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="donor_fname">First Name</label>
                        <input type="text" id="donor_fname" name="donor_fname" required>
                    </div>
                    <div class="form-group">
                        <label for="donor_lname">Last Name</label>
                        <input type="text" id="donor_lname" name="donor_lname" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="donor_contact">Contact Number</label>
                    <input type="text" id="donor_contact" name="donor_contact" required>
                </div>
                <div class="form-group">
                    <label for="donor_icnumber">IC Number</label>
                    <input type="text" id="donor_icnumber" name="donor_icnumber" required>
                </div>
                <div class="form-group">
                    <label for="donor_email">Email</label>
                    <input type="email" id="donor_email" name="donor_email" required>
                </div>
                <div class="form-group">
                    <label for="donor_password">Password</label>
                    <input type="password" id="donor_password" name="donor_password" required>
                </div>
                <div class="form-group">
                    <label for="donor_address">Address</label>
                    <textarea id="donor_address" name="donor_address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="donor_dob">Date of Birth</label>
                    <input type="date" id="donor_dob" name="donor_dob" required>
                </div>
                <div class="form-group">
                    <label for="donor_description">Description</label>
                    <textarea id="donor_description" name="donor_description" rows="3"></textarea>
                </div>
                <button type="submit" name="add_donor" class="submit-btn">Add Donor</button>
            </form>
        </div>
    </div>

    <!-- Edit Donor Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Donor</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_donor_id" name="donor_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_donor_fname">First Name</label>
                        <input type="text" id="edit_donor_fname" name="donor_fname" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_donor_lname">Last Name</label>
                        <input type="text" id="edit_donor_lname" name="donor_lname" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_donor_contact">Contact Number</label>
                    <input type="text" id="edit_donor_contact" name="donor_contact" required>
                </div>
                <div class="form-group">
                    <label for="edit_donor_icnumber">IC Number</label>
                    <input type="text" id="edit_donor_icnumber" name="donor_icnumber" required>
                </div>
                <div class="form-group">
                    <label for="edit_donor_email">Email</label>
                    <input type="email" id="edit_donor_email" name="donor_email" required>
                </div>
                <div class="form-group">
                    <label for="edit_donor_password">Password</label>
                    <input type="password" id="edit_donor_password" name="donor_password" required>
                </div>
                <div class="form-group">
                    <label for="edit_donor_address">Address</label>
                    <textarea id="edit_donor_address" name="donor_address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_donor_dob">Date of Birth</label>
                    <input type="date" id="edit_donor_dob" name="donor_dob" required>
                </div>
                <div class="form-group">
                    <label for="edit_donor_description">Description</label>
                    <textarea id="edit_donor_description" name="donor_description" rows="3"></textarea>
                </div>
                <button type="submit" name="update_donor" class="submit-btn">Update Donor</button>
            </form>
        </div>
    </div>

    <script>
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');
            
            // Toggle sidebar on menu button click (mobile)
            menuToggle.addEventListener('click', function() {
                if (window.innerWidth < 769) {
                    sidebar.classList.toggle('active');
                }
            });
            
            // Desktop hover functionality
            if (window.innerWidth >= 769) {
                let hoverTimeout;
                
                sidebar.addEventListener('mouseenter', function() {
                    clearTimeout(hoverTimeout);
                    sidebar.classList.add('expanded');
                });
                
                sidebar.addEventListener('mouseleave', function() {
                    hoverTimeout = setTimeout(function() {
                        sidebar.classList.remove('expanded');
                    }, 300);
                });
                
                // Keep sidebar expanded when hovering over menu items
                const menuItems = document.querySelectorAll('.menu-item');
                menuItems.forEach(item => {
                    item.addEventListener('mouseenter', function() {
                        clearTimeout(hoverTimeout);
                    });
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 769 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 769) {
                    sidebar.classList.remove('active');
                } else {
                    sidebar.classList.remove('expanded');
                }
            });
        });

        // Modal functionality
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(donorId) {
            // In a real implementation, you would fetch donor data via AJAX
            // For this example, we'll just show the modal
            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('edit_donor_id').value = donorId;
            
            // In a real implementation, you would populate the form with donor data
            // For this example, we'll just show the modal
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            
            if (event.target === addModal) {
                addModal.style.display = 'none';
            }
            
            if (event.target === editModal) {
                editModal.style.display = 'none';
            }
        }
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>