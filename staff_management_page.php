<?php
// staff_management_page.php - Staff Management Page
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// Handle AJAX request for staff data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_staff_data'])) {
    $staff_id = $_POST['staff_id'];
    $sql = "SELECT * FROM staff WHERE Staff_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $staff_data = $result->fetch_assoc();
        echo json_encode(['success' => true, 'staff' => $staff_data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
    }
    $stmt->close();
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $description = $_POST['staff_description'];
        $admin_id = $_SESSION['admin_id'];
        
        $sql = "INSERT INTO staff (Staff_FName, Staff_LName, Staff_ContactNumber, Staff_ICNumber, 
                Staff_Email, Staff_Password, Staff_Address, Staff_DOB, Staff_Commnent, Admin_ID) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssi", $fname, $lname, $contact, $icnumber, $email, $password, $address, $dob, $description, $admin_id);
        
        if ($stmt->execute()) {
            $success_message = "Staff added successfully!";
        } else {
            $error_message = "Error adding staff: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Update staff
    if (isset($_POST['update_staff'])) {
        $staff_id = $_POST['staff_id'];
        $fname = $_POST['staff_fname'];
        $lname = $_POST['staff_lname'];
        $contact = $_POST['staff_contact'];
        $icnumber = $_POST['staff_icnumber'];
        $email = $_POST['staff_email'];
        $password = $_POST['staff_password'];
        $address = $_POST['staff_address'];
        $dob = $_POST['staff_dob'];
        $description = $_POST['staff_description'];
        
        // If password is empty, don't update it
        if (empty($password)) {
            $sql = "UPDATE staff SET 
                    Staff_FName = ?, Staff_LName = ?, Staff_ContactNumber = ?, Staff_ICNumber = ?,
                    Staff_Email = ?, Staff_Address = ?, Staff_DOB = ?, Staff_Commnent = ?
                    WHERE Staff_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssi", $fname, $lname, $contact, $icnumber, $email, $address, $dob, $description, $staff_id);
        } else {
            $sql = "UPDATE staff SET 
                    Staff_FName = ?, Staff_LName = ?, Staff_ContactNumber = ?, Staff_ICNumber = ?,
                    Staff_Email = ?, Staff_Password = ?, Staff_Address = ?, Staff_DOB = ?, Staff_Commnent = ?
                    WHERE Staff_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssi", $fname, $lname, $contact, $icnumber, $email, $password, $address, $dob, $description, $staff_id);
        }
        
        if ($stmt->execute()) {
            $success_message = "Staff updated successfully!";
        } else {
            $error_message = "Error updating staff: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Delete staff
    if (isset($_POST['delete_staff'])) {
        $staff_id = $_POST['staff_id'];
        
        $sql = "DELETE FROM staff WHERE Staff_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $staff_id);
        
        if ($stmt->execute()) {
            $success_message = "Staff deleted successfully!";
        } else {
            $error_message = "Error deleting staff: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle search
$search_query = "";
if (isset($_GET['search'])) {
    $search_query = $_GET['search'];
    $sql = "SELECT * FROM staff WHERE 
            Staff_FName LIKE ? OR 
            Staff_LName LIKE ? OR 
            Staff_Email LIKE ? OR 
            Staff_ContactNumber LIKE ? OR 
            Staff_ICNumber LIKE ?
            ORDER BY Staff_ID DESC";
    $stmt = $conn->prepare($sql);
    $search_param = "%" . $search_query . "%";
    $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Get all staff
    $sql = "SELECT * FROM staff ORDER BY Staff_ID DESC";
    $result = $conn->query($sql);
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Donation Management System</title>
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Staff Management Specific Styles */
        .staff-management {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .staff-management::before {
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

        .add-staff-btn {
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

        .add-staff-btn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 157, 0.4);
        }

        .staff-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .staff-table th,
        .staff-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .staff-table th {
            background-color: #f9f9f9;
            font-weight: 600;
            color: var(--text-dark);
        }

        .staff-table tr:hover {
            background-color: #f5f5f5;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .edit-btn, .delete-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .edit-btn {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            box-shadow: 0 2px 5px rgba(76, 175, 80, 0.3);
        }

        .edit-btn:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(76, 175, 80, 0.4);
        }

        .delete-btn {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
            box-shadow: 0 2px 5px rgba(244, 67, 54, 0.3);
        }

        .delete-btn:hover {
            background: linear-gradient(135deg, #d32f2f, #f44336);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(244, 67, 54, 0.4);
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
            
            .staff-table {
                display: block;
                overflow-x: auto;
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
            <h1 class="page-title">Staff Management</h1>
            
            <div class="staff-management">
                <div class="management-header">
                    <div class="search-box">
                        <form method="GET" action="" id="searchForm">
                            <input type="text" name="search" id="searchInput" placeholder="Search staff..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit">Search</button>
                            <button type="button" class="clear-search-btn" onclick="clearSearch()">Clear</button>
                        </form>
                    </div>
                    <button class="add-staff-btn" onclick="openAddModal()">Add New Staff</button>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="message success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="message error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if ($result && $result->num_rows > 0): ?>
                    <table class="staff-table">
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
                                    <td><?php echo $row['Staff_ID']; ?></td>
                                    <td><?php echo $row['Staff_FName'] . ' ' . $row['Staff_LName']; ?></td>
                                    <td><?php echo $row['Staff_ContactNumber']; ?></td>
                                    <td><?php echo $row['Staff_Email']; ?></td>
                                    <td><?php echo $row['Staff_ICNumber']; ?></td>
                                    <td><?php echo $row['Staff_DOB']; ?></td>
                                    <td class="action-buttons">
                                        <button class="edit-btn" onclick="openEditModal(<?php echo $row['Staff_ID']; ?>)">Edit</button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="staff_id" value="<?php echo $row['Staff_ID']; ?>">
                                            <button type="submit" name="delete_staff" class="delete-btn" onclick="return confirm('Are you sure you want to delete this staff?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <h3>No staff found</h3>
                        <p>There are no staff in the database. Click "Add New Staff" to add one.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Staff</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
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
                    <label for="staff_description">Description</label>
                    <textarea id="staff_description" name="staff_description" rows="3"></textarea>
                </div>
                <button type="submit" name="add_staff" class="submit-btn">Add Staff</button>
            </form>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Staff</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_staff_id" name="staff_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_staff_fname">First Name</label>
                        <input type="text" id="edit_staff_fname" name="staff_fname" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_staff_lname">Last Name</label>
                        <input type="text" id="edit_staff_lname" name="staff_lname" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_staff_contact">Contact Number</label>
                    <input type="text" id="edit_staff_contact" name="staff_contact" required>
                </div>
                <div class="form-group">
                    <label for="edit_staff_icnumber">IC Number</label>
                    <input type="text" id="edit_staff_icnumber" name="staff_icnumber" required>
                </div>
                <div class="form-group">
                    <label for="edit_staff_email">Email</label>
                    <input type="email" id="edit_staff_email" name="staff_email" required>
                </div>
                <div class="form-group">
                    <label for="edit_staff_password">Password</label>
                    <input type="password" id="edit_staff_password" name="staff_password" placeholder="Leave blank to keep current password">
                </div>
                <div class="form-group">
                    <label for="edit_staff_address">Address</label>
                    <textarea id="edit_staff_address" name="staff_address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_staff_dob">Date of Birth</label>
                    <input type="date" id="edit_staff_dob" name="staff_dob" required>
                </div>
                <div class="form-group">
                    <label for="edit_staff_description">Description</label>
                    <textarea id="edit_staff_description" name="staff_description" rows="3"></textarea>
                </div>
                <button type="submit" name="update_staff" class="submit-btn">Update Staff</button>
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

        function openEditModal(staffId) {
            // Fetch staff data via AJAX
            fetchStaffData(staffId);
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Clear search functionality
        function clearSearch() {
            // Clear the search input and submit the form
            document.getElementById('searchInput').value = '';
            document.getElementById('searchForm').submit();
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

        function fetchStaffData(staffId) {
            // Create a FormData object to send the request
            const formData = new FormData();
            formData.append('get_staff_data', 'true');
            formData.append('staff_id', staffId);
            
            fetch('staff_management_page.php', {
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
                    // Populate the edit form with staff data
                    document.getElementById('edit_staff_id').value = data.staff.Staff_ID;
                    document.getElementById('edit_staff_fname').value = data.staff.Staff_FName;
                    document.getElementById('edit_staff_lname').value = data.staff.Staff_LName;
                    document.getElementById('edit_staff_contact').value = data.staff.Staff_ContactNumber;
                    document.getElementById('edit_staff_icnumber').value = data.staff.Staff_ICNumber;
                    document.getElementById('edit_staff_email').value = data.staff.Staff_Email;
                    document.getElementById('edit_staff_address').value = data.staff.Staff_Address;
                    document.getElementById('edit_staff_dob').value = data.staff.Staff_DOB;
                    document.getElementById('edit_staff_description').value = data.staff.Staff_Commnent;
                    
                    // Show the edit modal
                    document.getElementById('editModal').style.display = 'flex';
                } else {
                    alert('Error fetching staff data: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching staff data. Please check the console for details.');
            });
        }
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>