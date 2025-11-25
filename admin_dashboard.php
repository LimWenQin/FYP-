<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// Initialize variables
$total_donors = 0;
$total_donations = 0;
$total_branches = 0;
$total_activities = 0;
$total_items = 0;
$total_rewards = 0;
$total_staff = 0;
$total_admins = 0;

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
    
    // Add new staff
    if (isset($_POST['add_staff'])) {
        $fname = $_POST['staff_fname'];
        $lname = $_POST['staff_lname'];
        $contact = $_POST['staff_contact'];
        $icnumber = $_POST['staff_icnumber'];
        $email = $_POST['staff_email'];
        $password = $_POST['staff_password'];
        $address = $_POST['staff_address'];
        $dob = $_POST['staff_dob'];
        $comment = $_POST['staff_comment'];
        $admin_id = $_SESSION['admin_id'];
        
        $sql = "INSERT INTO staff (Staff_FName, Staff_LName, Staff_ContactNumber, Staff_ICNumber, 
                Staff_Email, Staff_Password, Staff_Address, Staff_DOB, Staff_Commnent, Admin_ID) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssi", $fname, $lname, $contact, $icnumber, $email, $password, $address, $dob, $comment, $admin_id);
        
        if ($stmt->execute()) {
            $success_message = "Staff added successfully!";
        } else {
            $error_message = "Error adding staff: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Add new admin
    if (isset($_POST['add_admin'])) {
        $fname = $_POST['admin_fname'];
        $lname = $_POST['admin_lname'];
        $contact = $_POST['admin_contact'];
        $icnumber = $_POST['admin_icnumber'];
        $email = $_POST['admin_email'];
        $password = $_POST['admin_password'];
        $address = $_POST['admin_address'];
        $dob = $_POST['admin_dob'];
        $comment = $_POST['admin_comment'];
        
        $sql = "INSERT INTO admin (Admin_FName, Admin_LName, Admin_ContactNumber, Admin_ICNUMBER, 
                Admin_Email, Admin_Password, Admin_Address, Admin_DOB, Admin_Commnent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssss", $fname, $lname, $contact, $icnumber, $email, $password, $address, $dob, $comment);
        
        if ($stmt->execute()) {
            $success_message = "Admin added successfully!";
        } else {
            $error_message = "Error adding admin: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Add new branch
    if (isset($_POST['add_branch'])) {
        $name = $_POST['branch_name'];
        $type = $_POST['branch_type'];
        $address = $_POST['branch_address'];
        $contact = $_POST['branch_contact'];
        $description = $_POST['branch_description'];
        $admin_id = $_SESSION['admin_id'];
        
        $sql = "INSERT INTO branch (Branch_Name, Branch_Type, Branch_Address, Branch_ContactNumber, Branch_Description, Admin_ID) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $name, $type, $address, $contact, $description, $admin_id);
        
        if ($stmt->execute()) {
            $success_message = "Branch added successfully!";
        } else {
            $error_message = "Error adding branch: " . $conn->error;
        }
        $stmt->close();
    }
}

// Check database connection
if ($conn) {
    // Get total donors
    $sql = "SELECT COUNT(*) as count FROM donor";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $total_donors = $row['count'];
    }

    // Get total donations (from orders table)
    $sql = "SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus = 'completed'";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $total_donations = $row['total'] ?: 0;
    }

    // Get total branches
    $sql = "SELECT COUNT(*) as count FROM branch";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $total_branches = $row['count'];
    }

    // Get total activities
    $sql = "SELECT COUNT(*) as count FROM activity";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $total_activities = $row['count'];
    }

    // Get total item donations
    $sql = "SELECT COUNT(*) as count FROM item_donation";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $total_items = $row['count'];
    }

    // Get total rewards
    $sql = "SELECT COUNT(*) as count FROM reward_item";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $total_rewards = $row['count'];
    }

    // Get total staff
    $sql = "SELECT COUNT(*) as count FROM staff";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $total_staff = $row['count'];
    }

    // Get total admins
    $sql = "SELECT COUNT(*) as count FROM admin";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $total_admins = $row['count'];
    }

    // Get recent activities
    $recent_activities = [];
    $sql = "SELECT a.Activity_ID, a.Activity_Date, a.Activity_Details, a.Activity_Status, b.Branch_Name 
            FROM activity a 
            JOIN branch b ON a.Branch_ID = b.Branch_ID 
            ORDER BY a.Activity_Date DESC 
            LIMIT 5";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }

    $conn->close();
} else {
    // Handle database connection error
    $error_message = "Unable to connect to database. Please check system configuration.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Donation Management System</title>
    <link rel="stylesheet" href="admin_common.css">
    <style>
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
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-pink), var(--warm-orange), var(--warm-yellow));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255, 107, 157, 0.25);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-number {
            font-size: 2.5em;
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 16px;
            color: var(--text-light);
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
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .section-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-pink), var(--warm-peach));
        }

        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255, 107, 157, 0.2);
        }

        .section-title {
            font-size: 20px;
            margin-bottom: 15px;
            color: var(--text-dark);
            display: flex;
            align-items: center;
        }

        .section-title ion-icon {
            margin-right: 10px;
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
            color: var(--primary-pink);
            text-decoration: none;
            transition: color 0.3s ease;
            font-weight: 500;
        }

        .section-list a:hover {
            color: var(--warm-orange);
            text-decoration: underline;
        }

        .badge {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .action-btn {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(255, 107, 157, 0.3);
        }

        .action-btn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 157, 0.4);
        }

        .activity-item {
            padding: 15px;
            border-left: 4px solid var(--primary-pink);
            background: linear-gradient(90deg, rgba(255, 182, 193, 0.1) 0%, rgba(255, 255, 255, 0.5) 100%);
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
        }

        .activity-date {
            font-weight: bold;
            color: var(--primary-pink);
        }

        .activity-details {
            margin: 5px 0;
        }

        .activity-branch {
            color: var(--text-light);
            font-size: 14px;
        }

        .activity-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-top: 5px;
            font-weight: 600;
        }

        .status-completed {
            background: linear-gradient(135deg, #a8e6cf, #6dd5a8);
            color: #155724;
        }

        .status-upcoming {
            background: linear-gradient(135deg, #a8d8ea, #6da8ff);
            color: #004085;
        }

        .status-ongoing {
            background: linear-gradient(135deg, #ffd3b6, #ffaa6d);
            color: #856404;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
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
            border-radius: 15px;
            width: 600px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--card-shadow);
            position: relative;
        }

        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-pink), var(--warm-peach));
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
            color: var(--text-dark);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: var(--primary-pink);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 2px rgba(255, 107, 157, 0.2);
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
            width: 100%;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(255, 107, 157, 0.3);
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 157, 0.4);
        }

        @media (max-width: 1024px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo" id="sidebarToggle">
            <img src="picture/logo.png" alt="Logo">
            <div class="logo-text">DonationMS</div>
        </div>
        
        <div class="sidebar-menu">
            <a href="admin_dashboard.php" class="menu-item active">
                <ion-icon name="grid"></ion-icon>
                <div class="menu-text">Dashboard</div>
            </a>
            <a href="admin_donor_page.php" class="menu-item">
                <ion-icon name="people"></ion-icon>
                <div class="menu-text">Donor Management</div>
            </a>
            <a href="staff_management_page.php" class="menu-item">
                <ion-icon name="person-circle"></ion-icon>
                <div class="menu-text">Staff Management</div>
            </a>
            <a href="admin_management_page.php" class="menu-item">
                <ion-icon name="shield-checkmark"></ion-icon>
                <div class="menu-text">Admin Management</div>
            </a>
            <a href="branch_management_page.php" class="menu-item">
                <ion-icon name="business"></ion-icon>
                <div class="menu-text">Branch Management</div>
            </a>
            <a href="activity_management.php" class="menu-item">
                <ion-icon name="calendar"></ion-icon>
                <div class="menu-text">Activity Management</div>
            </a>
            <a href="payment_management.php" class="menu-item">
                <ion-icon name="card"></ion-icon>
                <div class="menu-text">Payment Management</div>
            </a>
        </div>
    </aside>
    
    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <!-- Three-line menu toggle -->
                <div class="menu-toggle" id="menuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                
                <!-- Logo that links to homepage -->
                <a href="admin_dashboard.php" class="header-logo">
                    <img src="picture/logo.png" alt="Logo">
                    <div class="header-logo-text">DonationMS</div>
                </a>
            </div>
            
            <div class="header-right">
                <div class="welcome">Welcome back, <?php echo $_SESSION['admin_name']; ?>!</div>
                <a href="admin_logout.php" class="logout">Logout</a>
            </div>
        </div>
        
        <div class="dashboard">
            <h1 class="page-title">System Overview</h1>
            
            <?php if (isset($error_message)): ?>
            <div class="error-message">
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
            <div class="success-message">
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <div class="stats">
                <a href="admin_donor_page.php" class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="people"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_donors; ?></div>
                    <div class="stat-label">Total Donors</div>
                </a>
                <a href="admin_donor_page.php" class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="cash"></ion-icon>
                    </div>
                    <div class="stat-number">RM <?php echo number_format($total_donations, 2); ?></div>
                    <div class="stat-label">Total Donations</div>
                </a>
                <a href="branch_management_page.php" class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="business"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_branches; ?></div>
                    <div class="stat-label">Branch Locations</div>
                </a>
                <a href="activity_management.php" class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="calendar"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_activities; ?></div>
                    <div class="stat-label">Total Activities</div>
                </a>
                <a href="staff_management_page.php" class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="person-circle"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_staff; ?></div>
                    <div class="stat-label">Total Staff</div>
                </a>
                <a href="admin_management_page.php" class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="shield-checkmark"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $total_admins; ?></div>
                    <div class="stat-label">Total Admins</div>
                </a>
            </div>
            
            <div class="dashboard-content">
                <div class="main-content-section">
                    <div class="section-card">
                        <h2 class="section-title">
                            <ion-icon name="people-circle"></ion-icon>
                            Donor Management
                        </h2>
                        <ul class="section-list">
                            <li>
                                <a href="admin_donor_page.php">View All Donors</a>
                                <span class="badge"><?php echo $total_donors; ?></span>
                            </li>
                            <li><a href="admin_donor_page.php">Add New Donor</a></li>
                            <li><a href="admin_donor_page.php">Donor Points Management</a></li>
                            <li><a href="admin_donor_page.php">Donation History</a></li>
                        </ul>
                        <div class="quick-actions">
                            <button class="action-btn" onclick="openModal('donorModal')">Add Donor</button>
                            <a href="admin_donor_page.php" class="action-btn">Export Data</a>
                        </div>
                    </div>
                    
                    <div class="section-card">
                        <h2 class="section-title">
                            <ion-icon name="business"></ion-icon>
                            Branch Management
                        </h2>
                        <ul class="section-list">
                            <li>
                                <a href="branch_management_page.php">View All Branches</a>
                                <span class="badge"><?php echo $total_branches; ?></span>
                            </li>
                            <li><a href="branch_management_page.php?type=elderly">Elderly Homes</a></li>
                            <li><a href="branch_management_page.php?type=orphanage">Orphanages</a></li>
                            <li><a href="branch_management_page.php?type=disability">Disability Centers</a></li>
                            <li><a href="branch_management_page.php?type=animal">Stray Animal Centers</a></li>
                        </ul>
                        <div class="quick-actions">
                            <button class="action-btn" onclick="openModal('branchModal')">Add Branch</button>
                            <a href="branch_management_page.php" class="action-btn">Branch Reports</a>
                        </div>
                    </div>

                    <div class="section-card">
                        <h2 class="section-title">
                            <ion-icon name="person-circle"></ion-icon>
                            Staff Management
                        </h2>
                        <ul class="section-list">
                            <li>
                                <a href="staff_management_page.php">View All Staff</a>
                                <span class="badge"><?php echo $total_staff; ?></span>
                            </li>
                            <li><a href="staff_management_page.php?action=add">Add New Staff</a></li>
                            <li><a href="staff_management_page.php?view=roles">Staff Roles</a></li>
                            <li><a href="staff_management_page.php?view=performance">Staff Performance</a></li>
                        </ul>
                        <div class="quick-actions">
                            <button class="action-btn" onclick="openModal('staffModal')">Add Staff</button>
                            <a href="staff_management_page.php" class="action-btn">Staff Reports</a>
                        </div>
                    </div>

                    <div class="section-card">
                        <h2 class="section-title">
                            <ion-icon name="shield-checkmark"></ion-icon>
                            Admin Management
                        </h2>
                        <ul class="section-list">
                            <li>
                                <a href="admin_management_page.php">View All Admins</a>
                                <span class="badge"><?php echo $total_admins; ?></span>
                            </li>
                            <li><a href="admin_management_page.php?action=add">Add New Admin</a></li>
                            <li><a href="admin_management_page.php?view=roles">Admin Roles</a></li>
                            <li><a href="admin_management_page.php?view=activity">Admin Activity Log</a></li>
                        </ul>
                        <div class="quick-actions">
                            <button class="action-btn" onclick="openModal('adminModal')">Add Admin</button>
                            <a href="admin_management_page.php" class="action-btn">Admin Reports</a>
                        </div>
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
                            <a href="activity_management.php" class="action-btn">View All Activities</a>
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
    <div id="donorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Donor</h2>
                <button class="close-btn" onclick="closeModal('donorModal')">&times;</button>
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

    <!-- Add Staff Modal -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Staff</h2>
                <button class="close-btn" onclick="closeModal('staffModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="staff_fname">First Name</label>
                        <input type="text" id="staff_fname" name="staff_fname" required>
                    </div>
                    <div class="form-group">
                        <label for="staff_lname">Last Name</label>
                        <input type="text" id="staff_lname" name="staff_lname" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="staff_contact">Contact Number</label>
                    <input type="text" id="staff_contact" name="staff_contact" required>
                </div>
                <div class="form-group">
                    <label for="staff_icnumber">IC Number</label>
                    <input type="text" id="staff_icnumber" name="staff_icnumber" required>
                </div>
                <div class="form-group">
                    <label for="staff_email">Email</label>
                    <input type="email" id="staff_email" name="staff_email" required>
                </div>
                <div class="form-group">
                    <label for="staff_password">Password</label>
                    <input type="password" id="staff_password" name="staff_password" required>
                </div>
                <div class="form-group">
                    <label for="staff_address">Address</label>
                    <textarea id="staff_address" name="staff_address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="staff_dob">Date of Birth</label>
                    <input type="date" id="staff_dob" name="staff_dob" required>
                </div>
                <div class="form-group">
                    <label for="staff_comment">Comment</label>
                    <textarea id="staff_comment" name="staff_comment" rows="3"></textarea>
                </div>
                <button type="submit" name="add_staff" class="submit-btn">Add Staff</button>
            </form>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div id="adminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Admin</h2>
                <button class="close-btn" onclick="closeModal('adminModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="admin_fname">First Name</label>
                        <input type="text" id="admin_fname" name="admin_fname" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_lname">Last Name</label>
                        <input type="text" id="admin_lname" name="admin_lname" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="admin_contact">Contact Number</label>
                    <input type="text" id="admin_contact" name="admin_contact" required>
                </div>
                <div class="form-group">
                    <label for="admin_icnumber">IC Number</label>
                    <input type="text" id="admin_icnumber" name="admin_icnumber" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">Email</label>
                    <input type="email" id="admin_email" name="admin_email" required>
                </div>
                <div class="form-group">
                    <label for="admin_password">Password</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>
                <div class="form-group">
                    <label for="admin_address">Address</label>
                    <textarea id="admin_address" name="admin_address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="admin_dob">Date of Birth</label>
                    <input type="date" id="admin_dob" name="admin_dob" required>
                </div>
                <div class="form-group">
                    <label for="admin_comment">Comment</label>
                    <textarea id="admin_comment" name="admin_comment" rows="3"></textarea>
                </div>
                <button type="submit" name="add_admin" class="submit-btn">Add Admin</button>
            </form>
        </div>
    </div>

    <!-- Add Branch Modal -->
    <div id="branchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Branch</h2>
                <button class="close-btn" onclick="closeModal('branchModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="branch_name">Branch Name</label>
                    <input type="text" id="branch_name" name="branch_name" required>
                </div>
                <div class="form-group">
                    <label for="branch_type">Branch Type</label>
                    <select id="branch_type" name="branch_type" required>
                        <option value="">Select Type</option>
                        <option value="elderly">Elderly Home</option>
                        <option value="orphanage">Orphanage</option>
                        <option value="disability">Disability Center</option>
                        <option value="animal">Stray Animal Center</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="branch_address">Address</label>
                    <textarea id="branch_address" name="branch_address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="branch_contact">Contact Number</label>
                    <input type="text" id="branch_contact" name="branch_contact" required>
                </div>
                <div class="form-group">
                    <label for="branch_description">Description</label>
                    <textarea id="branch_description" name="branch_description" rows="3"></textarea>
                </div>
                <button type="submit" name="add_branch" class="submit-btn">Add Branch</button>
            </form>
        </div>
    </div>

    <script>
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
            
            // Handle menu item clicks
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    menuItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    
                    // On mobile, close sidebar after selecting a menu item
                    if (window.innerWidth < 769) {
                        sidebar.classList.remove('active');
                    }
                });
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
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>
