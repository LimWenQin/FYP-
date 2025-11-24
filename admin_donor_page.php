<?php
// admin_donor_page.php - Donor Management Page
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// Set current page for sidebar highlighting
$current_page = 'admin_donor_page.php';

// Handle AJAX request for donor data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_donor_data'])) {
    $donor_id = $_POST['donor_id'];
    $sql = "SELECT * FROM donor WHERE Donor_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $donor_data = $result->fetch_assoc();
        // Remove password from response for security
        unset($donor_data['Donor_Password']);
        echo json_encode(['success' => true, 'donor' => $donor_data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Donor not found']);
    }
    $stmt->close();
    exit();
}

// Handle AJAX request for payment history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_payment_history'])) {
    $donor_id = $_POST['donor_id'];
    $sql = "SELECT o.*, p.Payment_Method, p.Payment_Status, p.Payment_Amount, p.Payment_Paid_At 
            FROM orders o 
            LEFT JOIN payment p ON o.Payment_ID = p.Payment_ID 
            WHERE o.Donor_ID = ? 
            ORDER BY o.Order_Created_At DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    echo json_encode(['success' => true, 'payments' => $payments]);
    $stmt->close();
    exit();
}

// Handle AJAX request for purchase history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_purchase_history'])) {
    $donor_id = $_POST['donor_id'];
    $sql = "SELECT ro.*, ri.Reward_ItemName, ri.Reward_Description, ri.Reward_RequiredPoint 
            FROM redemption_order ro 
            LEFT JOIN reward_item ri ON ro.Reward_ID = ri.Reward_ID 
            WHERE ro.Donor_ID = ? 
            ORDER BY ro.Redemption_Updated_At DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $purchases = [];
    while ($row = $result->fetch_assoc()) {
        $purchases[] = $row;
    }
    
    echo json_encode(['success' => true, 'purchases' => $purchases]);
    $stmt->close();
    exit();
}

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
        $address = $_POST['donor_address'];
        $dob = $_POST['donor_dob'];
        $description = $_POST['donor_description'];
        
        // Update without password and email (email is not editable)
        $sql = "UPDATE donor SET 
                Donor_FName = ?, Donor_LName = ?, Donor_ContactNumber = ?, Donor_ICNumber = ?,
                Donor_Address = ?, Donor_DOB = ?, Donor_Description = ?
                WHERE Donor_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", $fname, $lname, $contact, $icnumber, $address, $dob, $description, $donor_id);
        
        if ($stmt->execute()) {
            $success_message = "Donor updated successfully!";
        } else {
            $error_message = "Error updating donor: " . $conn->error;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Management - Donation Management System</title>
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Donor Management Specific Styles */
        .donor-management {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .donor-management::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-pink), var(--warm-peach));
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
            border-radius: 8px;
            width: 300px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 2px rgba(255, 107, 157, 0.2);
        }

        .search-box button {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(255, 107, 157, 0.3);
        }

        .search-box button:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 157, 0.4);
        }

        .clear-search-btn {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3);
        }

        .clear-search-btn:hover {
            background: linear-gradient(135deg, #5a6268, #6c757d);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(108, 117, 125, 0.4);
        }

        .add-donor-btn {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(255, 107, 157, 0.3);
        }

        .add-donor-btn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 157, 0.4);
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
            color: var(--text-dark);
        }

        .donor-table tr:hover {
            background-color: #f5f5f5;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        /* 修复的下拉菜单样式 */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropbtn {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(255, 107, 157, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            justify-content: center;
        }

        .dropbtn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 157, 0.4);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            z-index: 1000;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 8px;
            border: 1px solid #f0f0f0;
            animation: dropdownFadeIn 0.2s ease-out;
        }

        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content a {
            color: var(--text-dark);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f8f8f8;
            position: relative;
            overflow: hidden;
        }

        .dropdown-content a:last-child {
            border-bottom: none;
        }

        .dropdown-content a:hover {
            background: linear-gradient(135deg, #fff5f7, #fff0f3);
            color: var(--primary-pink);
            transform: translateX(5px);
        }

        .dropdown-content a:hover::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .dropdown-icon {
            font-size: 16px;
            width: 20px;
            text-align: center;
            color: var(--text-light);
            transition: all 0.2s ease;
        }

        .dropdown-content a:hover .dropdown-icon {
            color: var(--primary-pink);
            transform: scale(1.1);
        }

        /* 添加下拉菜单关闭延迟 */
        .dropdown-content.delayed-close {
            pointer-events: none;
            animation: dropdownFadeOut 0.3s ease-out forwards;
        }

        @keyframes dropdownFadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-10px);
            }
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
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

        .form-group input:read-only {
            background-color: #f5f5f5;
            color: #666;
            cursor: not-allowed;
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

        .message {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* History Panel Styles */
        .history-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
            transition: right 0.3s ease;
            z-index: 999;
            overflow-y: auto;
        }

        .history-panel.active {
            right: 0;
        }

        .history-header {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-title {
            font-size: 20px;
            font-weight: 600;
        }

        .history-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

        .history-content {
            padding: 20px;
        }

        .history-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }

        .history-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .history-item-title {
            font-weight: 600;
            color: var(--text-dark);
        }

        .history-item-date {
            color: var(--text-light);
            font-size: 14px;
        }

        .history-item-details {
            color: var(--text-dark);
        }

        .history-item-amount {
            font-weight: 600;
            color: var(--primary-pink);
        }

        .history-item-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
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

            .history-panel {
                width: 100%;
                right: -100%;
            }

            .dropdown-content {
                right: auto;
                left: 0;
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
            <a href="admin_dashboard.php" class="menu-item <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                <ion-icon name="grid"></ion-icon>
                <div class="menu-text">Dashboard</div>
            </a>
            <a href="admin_donor_page.php" class="menu-item <?php echo $current_page == 'admin_donor_page.php' ? 'active' : ''; ?>">
                <ion-icon name="people"></ion-icon>
                <div class="menu-text">Donor Management</div>
            </a>
            <a href="staff_management_page.php" class="menu-item <?php echo $current_page == 'staff_management_page.php' ? 'active' : ''; ?>">
                <ion-icon name="person-circle"></ion-icon>
                <div class="menu-text">Staff Management</div>
            </a>
            <a href="admin_management_page.php" class="menu-item <?php echo $current_page == 'admin_management_page.php' ? 'active' : ''; ?>">
                <ion-icon name="shield-checkmark"></ion-icon>
                <div class="menu-text">Admin Management</div>
            </a>
            <a href="branch_management_page.php" class="menu-item <?php echo $current_page == 'branch_management_page.php' ? 'active' : ''; ?>">
                <ion-icon name="business"></ion-icon>
                <div class="menu-text">Branch Management</div>
            </a>
            <a href="activity_management.php" class="menu-item <?php echo $current_page == 'activity_management.php' ? 'active' : ''; ?>">
                <ion-icon name="calendar"></ion-icon>
                <div class="menu-text">Activity Management</div>
            </a>
            <a href="payment_management.php" class="menu-item <?php echo $current_page == 'payment_management.php' ? 'active' : ''; ?>">
                <ion-icon name="card"></ion-icon>
                <div class="menu-text">Payment Management</div>
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
            <h1 class="page-title">Donor Management</h1>
            
            <div class="donor-management">
                <div class="management-header">
                    <div class="search-box">
                        <form method="GET" action="" id="searchForm">
                            <input type="text" name="search" id="searchInput" placeholder="Search donors..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit">Search</button>
                            <button type="button" class="clear-search-btn" onclick="clearSearch()">Clear</button>
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
                                        <div class="dropdown" onmouseleave="startDropdownClose(this)">
                                            <button class="dropbtn" onclick="toggleDropdown(this)">
                                                <span>Actions</span>
                                                <ion-icon name="chevron-down-outline"></ion-icon>
                                            </button>
                                            <div class="dropdown-content">
                                                <a href="#" onclick="openEditModal(<?php echo $row['Donor_ID']; ?>)">
                                                    <div class="dropdown-item">
                                                        <ion-icon name="create-outline" class="dropdown-icon"></ion-icon>
                                                        <span>Edit</span>
                                                    </div>
                                                </a>
                                                <a href="#" onclick="openViewModal(<?php echo $row['Donor_ID']; ?>)">
                                                    <div class="dropdown-item">
                                                        <ion-icon name="eye-outline" class="dropdown-icon"></ion-icon>
                                                        <span>View</span>
                                                    </div>
                                                </a>
                                                <a href="#" onclick="openPaymentHistory(<?php echo $row['Donor_ID']; ?>)">
                                                    <div class="dropdown-item">
                                                        <ion-icon name="card-outline" class="dropdown-icon"></ion-icon>
                                                        <span>Payment History</span>
                                                    </div>
                                                </a>
                                                <a href="#" onclick="openPurchaseHistory(<?php echo $row['Donor_ID']; ?>)">
                                                    <div class="dropdown-item">
                                                        <ion-icon name="bag-outline" class="dropdown-icon"></ion-icon>
                                                        <span>Purchase History</span>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
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
    </div>

    <!-- Payment History Panel -->
    <div class="history-panel" id="paymentHistoryPanel">
        <div class="history-header">
            <h3 class="history-title">Payment History</h3>
            <button class="history-close" onclick="closePaymentHistory()">&times;</button>
        </div>
        <div class="history-content" id="paymentHistoryContent">
            <!-- Payment history will be loaded here -->
        </div>
    </div>

    <!-- Purchase History Panel -->
    <div class="history-panel" id="purchaseHistoryPanel">
        <div class="history-header">
            <h3 class="history-title">Purchase History</h3>
            <button class="history-close" onclick="closePurchaseHistory()">&times;</button>
        </div>
        <div class="history-content" id="purchaseHistoryContent">
            <!-- Purchase history will be loaded here -->
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
                    <input type="email" id="edit_donor_email" name="donor_email" required readonly>
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

    <!-- View Donor Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Donor Details</h2>
                <button class="close-btn" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="form-group">
                <label>Donor ID</label>
                <input type="text" id="view_donor_id" readonly>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" id="view_donor_fname" readonly>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" id="view_donor_lname" readonly>
                </div>
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" id="view_donor_contact" readonly>
            </div>
            <div class="form-group">
                <label>IC Number</label>
                <input type="text" id="view_donor_icnumber" readonly>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="view_donor_email" readonly>
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea id="view_donor_address" rows="3" readonly></textarea>
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" id="view_donor_dob" readonly>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="view_donor_description" rows="3" readonly></textarea>
            </div>
        </div>
    </div>

    <script>
        // 下拉菜单相关功能
        let dropdownCloseTimeout;
        let activeDropdown = null;

        function toggleDropdown(button) {
            const dropdown = button.parentElement;
            const dropdownContent = dropdown.querySelector('.dropdown-content');
            
            // 关闭其他打开的下拉菜单
            closeAllDropdowns();
            
            if (dropdownContent.classList.contains('show')) {
                closeDropdown(dropdown);
            } else {
                openDropdown(dropdown);
            }
        }

        function openDropdown(dropdown) {
            const dropdownContent = dropdown.querySelector('.dropdown-content');
            dropdownContent.classList.remove('delayed-close');
            dropdownContent.classList.add('show');
            activeDropdown = dropdown;
        }

        function closeDropdown(dropdown) {
            const dropdownContent = dropdown.querySelector('.dropdown-content');
            dropdownContent.classList.add('delayed-close');
            setTimeout(() => {
                dropdownContent.classList.remove('show');
                dropdownContent.classList.remove('delayed-close');
            }, 300);
            activeDropdown = null;
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.dropdown-content.show').forEach(dropdown => {
                dropdown.classList.add('delayed-close');
                setTimeout(() => {
                    dropdown.classList.remove('show');
                    dropdown.classList.remove('delayed-close');
                }, 300);
            });
            activeDropdown = null;
        }

        function startDropdownClose(dropdown) {
            // 设置延迟关闭，给用户时间移动到下拉菜单
            dropdownCloseTimeout = setTimeout(() => {
                if (activeDropdown === dropdown) {
                    closeDropdown(dropdown);
                }
            }, 300);
        }

        function cancelDropdownClose() {
            clearTimeout(dropdownCloseTimeout);
        }

        // 点击页面其他地方关闭所有下拉菜单
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                closeAllDropdowns();
            }
        });

        // 为下拉菜单项添加鼠标事件
        document.querySelectorAll('.dropdown-content a').forEach(item => {
            item.addEventListener('mouseenter', cancelDropdownClose);
            item.addEventListener('mouseleave', function() {
                const dropdown = this.closest('.dropdown');
                startDropdownClose(dropdown);
            });
        });

        // 为下拉按钮添加鼠标事件
        document.querySelectorAll('.dropbtn').forEach(button => {
            button.addEventListener('mouseenter', cancelDropdownClose);
        });

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

            // Auto-submit when search input is cleared
            const searchInput = document.getElementById('searchInput');
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                
                // If the input is empty, submit the form after a short delay
                if (this.value === '') {
                    searchTimeout = setTimeout(function() {
                        document.getElementById('searchForm').submit();
                    }, 500);
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
            // Fetch donor data via AJAX
            fetchDonorData(donorId, 'edit');
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function openViewModal(donorId) {
            // Fetch donor data via AJAX
            fetchDonorData(donorId, 'view');
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        // Clear search functionality
        function clearSearch() {
            // Clear the search input and submit the form
            document.getElementById('searchInput').value = '';
            document.getElementById('searchForm').submit();
        }

        // Payment History functionality
        function openPaymentHistory(donorId) {
            fetchPaymentHistory(donorId);
        }

        function closePaymentHistory() {
            document.getElementById('paymentHistoryPanel').classList.remove('active');
        }

        // Purchase History functionality
        function openPurchaseHistory(donorId) {
            fetchPurchaseHistory(donorId);
        }

        function closePurchaseHistory() {
            document.getElementById('purchaseHistoryPanel').classList.remove('active');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            const viewModal = document.getElementById('viewModal');
            
            if (event.target === addModal) {
                addModal.style.display = 'none';
            }
            
            if (event.target === editModal) {
                editModal.style.display = 'none';
            }
            
            if (event.target === viewModal) {
                viewModal.style.display = 'none';
            }
        }

        function fetchDonorData(donorId, mode) {
            // Create a FormData object to send the request
            const formData = new FormData();
            formData.append('get_donor_data', 'true');
            formData.append('donor_id', donorId);
            
            fetch('admin_donor_page.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (mode === 'edit') {
                        // Populate the edit form with donor data
                        document.getElementById('edit_donor_id').value = data.donor.Donor_ID;
                        document.getElementById('edit_donor_fname').value = data.donor.Donor_FName;
                        document.getElementById('edit_donor_lname').value = data.donor.Donor_LName;
                        document.getElementById('edit_donor_contact').value = data.donor.Donor_ContactNumber;
                        document.getElementById('edit_donor_icnumber').value = data.donor.Donor_ICNumber;
                        document.getElementById('edit_donor_email').value = data.donor.Donor_Email;
                        document.getElementById('edit_donor_address').value = data.donor.Donor_Address;
                        document.getElementById('edit_donor_dob').value = data.donor.Donor_DOB;
                        document.getElementById('edit_donor_description').value = data.donor.Donor_Description;
                        
                        // Show the edit modal
                        document.getElementById('editModal').style.display = 'flex';
                    } else if (mode === 'view') {
                        // Populate the view form with donor data
                        document.getElementById('view_donor_id').value = data.donor.Donor_ID;
                        document.getElementById('view_donor_fname').value = data.donor.Donor_FName;
                        document.getElementById('view_donor_lname').value = data.donor.Donor_LName;
                        document.getElementById('view_donor_contact').value = data.donor.Donor_ContactNumber;
                        document.getElementById('view_donor_icnumber').value = data.donor.Donor_ICNumber;
                        document.getElementById('view_donor_email').value = data.donor.Donor_Email;
                        document.getElementById('view_donor_address').value = data.donor.Donor_Address;
                        document.getElementById('view_donor_dob').value = data.donor.Donor_DOB;
                        document.getElementById('view_donor_description').value = data.donor.Donor_Description;
                        
                        // Show the view modal
                        document.getElementById('viewModal').style.display = 'flex';
                    }
                } else {
                    alert('Error fetching donor data: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching donor data. Please check the console for details.');
            });
        }

        function fetchPaymentHistory(donorId) {
            const formData = new FormData();
            formData.append('get_payment_history', 'true');
            formData.append('donor_id', donorId);
            
            fetch('admin_donor_page.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayPaymentHistory(data.payments);
                    document.getElementById('paymentHistoryPanel').classList.add('active');
                } else {
                    alert('Error fetching payment history');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching payment history');
            });
        }

        function displayPaymentHistory(payments) {
            const container = document.getElementById('paymentHistoryContent');
            
            if (payments.length === 0) {
                container.innerHTML = '<div class="no-data"><p>No payment history found</p></div>';
                return;
            }
            
            let html = '';
            payments.forEach(payment => {
                const statusClass = getStatusClass(payment.Payment_Status || payment.Order_PaymentStatus);
                const amount = payment.Payment_Amount || payment.Order_Amount;
                const date = payment.Payment_Paid_At || payment.Order_Created_At;
                const method = payment.Payment_Method || payment.Order_PaymentMethod;
                
                html += `
                    <div class="history-item">
                        <div class="history-item-header">
                            <div class="history-item-title">${method}</div>
                            <div class="history-item-amount">RM ${amount}</div>
                        </div>
                        <div class="history-item-details">
                            <div>Status: <span class="history-item-status ${statusClass}">${payment.Payment_Status || payment.Order_PaymentStatus}</span></div>
                            <div>Date: ${new Date(date).toLocaleDateString()}</div>
                            <div>Order ID: ${payment.Order_ID}</div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function fetchPurchaseHistory(donorId) {
            const formData = new FormData();
            formData.append('get_purchase_history', 'true');
            formData.append('donor_id', donorId);
            
            fetch('admin_donor_page.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayPurchaseHistory(data.purchases);
                    document.getElementById('purchaseHistoryPanel').classList.add('active');
                } else {
                    alert('Error fetching purchase history');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching purchase history');
            });
        }

        function displayPurchaseHistory(purchases) {
            const container = document.getElementById('purchaseHistoryContent');
            
            if (purchases.length === 0) {
                container.innerHTML = '<div class="no-data"><p>No purchase history found</p></div>';
                return;
            }
            
            let html = '';
            purchases.forEach(purchase => {
                const statusClass = getStatusClass(purchase.Redemption_Status);
                
                html += `
                    <div class="history-item">
                        <div class="history-item-header">
                            <div class="history-item-title">${purchase.Reward_ItemName}</div>
                            <div class="history-item-amount">${purchase.Redemption_PointsSpent} pts</div>
                        </div>
                        <div class="history-item-details">
                            <div>Description: ${purchase.Reward_Description}</div>
                            <div>Status: <span class="history-item-status ${statusClass}">${purchase.Redemption_Status}</span></div>
                            <div>Date: ${new Date(purchase.Redemption_Updated_At).toLocaleDateString()}</div>
                            <div>Address: ${purchase.Redemption_Address}</div>
                            <div>Contact: ${purchase.Redemption_ContactNumber}</div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function getStatusClass(status) {
            switch (status.toLowerCase()) {
                case 'completed':
                case 'success':
                case 'paid':
                    return 'status-completed';
                case 'pending':
                case 'processing':
                    return 'status-pending';
                case 'failed':
                case 'cancelled':
                    return 'status-failed';
                default:
                    return 'status-pending';
            }
        }
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>
