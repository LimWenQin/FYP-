<?php
// admin_management_page.php - Admin Management Page
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// Handle AJAX request for admin data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_admin_data'])) {
    $admin_id = $_POST['admin_id'];
    $sql = "SELECT * FROM admin WHERE Admin_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin_data = $result->fetch_assoc();
        echo json_encode(['success' => true, 'admin' => $admin_data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
    }
    $stmt->close();
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $description = $_POST['admin_description'];
        
        $sql = "INSERT INTO admin (Admin_FName, Admin_LName, Admin_ContactNumber, Admin_ICNUMBER, 
                Admin_Email, Admin_Password, Admin_Address, Admin_DOB, Admin_Commnent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssss", $fname, $lname, $contact, $icnumber, $email, $password, $address, $dob, $description);
        
        if ($stmt->execute()) {
            $success_message = "Admin added successfully!";
        } else {
            $error_message = "Error adding admin: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Update admin
    if (isset($_POST['update_admin'])) {
        $admin_id = $_POST['admin_id'];
        $fname = $_POST['admin_fname'];
        $lname = $_POST['admin_lname'];
        $contact = $_POST['admin_contact'];
        $icnumber = $_POST['admin_icnumber'];
        $email = $_POST['admin_email'];
        $password = $_POST['admin_password'];
        $address = $_POST['admin_address'];
        $dob = $_POST['admin_dob'];
        $description = $_POST['admin_description'];
        
        // If password is empty, don't update it
        if (empty($password)) {
            $sql = "UPDATE admin SET 
                    Admin_FName = ?, Admin_LName = ?, Admin_ContactNumber = ?, Admin_ICNUMBER = ?,
                    Admin_Email = ?, Admin_Address = ?, Admin_DOB = ?, Admin_Commnent = ?
                    WHERE Admin_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssi", $fname, $lname, $contact, $icnumber, $email, $address, $dob, $description, $admin_id);
        } else {
            $sql = "UPDATE admin SET 
                    Admin_FName = ?, Admin_LName = ?, Admin_ContactNumber = ?, Admin_ICNUMBER = ?,
                    Admin_Email = ?, Admin_Password = ?, Admin_Address = ?, Admin_DOB = ?, Admin_Commnent = ?
                    WHERE Admin_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssi", $fname, $lname, $contact, $icnumber, $email, $password, $address, $dob, $description, $admin_id);
        }
        
        if ($stmt->execute()) {
            $success_message = "Admin updated successfully!";
        } else {
            $error_message = "Error updating admin: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Delete admin
    if (isset($_POST['delete_admin'])) {
        $admin_id = $_POST['admin_id'];
        
        // Prevent deleting the currently logged in admin
        if ($admin_id == $_SESSION['admin_id']) {
            $error_message = "You cannot delete your own account!";
        } else {
            $sql = "DELETE FROM admin WHERE Admin_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $admin_id);
            
            if ($stmt->execute()) {
                $success_message = "Admin deleted successfully!";
            } else {
                $error_message = "Error deleting admin: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle search
$search_query = "";
if (isset($_GET['search'])) {
    $search_query = $_GET['search'];
    $sql = "SELECT * FROM admin WHERE 
            Admin_FName LIKE ? OR 
            Admin_LName LIKE ? OR 
            Admin_Email LIKE ? OR 
            Admin_ContactNumber LIKE ? OR 
            Admin_ICNUMBER LIKE ?
            ORDER BY Admin_ID DESC";
    $stmt = $conn->prepare($sql);
    $search_param = "%" . $search_query . "%";
    $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Get all admins
    $sql = "SELECT * FROM admin ORDER BY Admin_ID DESC";
    $result = $conn->query($sql);
}

// Get current page for sidebar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - Donation Management System</title>
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Admin Management Specific Styles */
        .admin-management {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .admin-management::before {
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

        .add-admin-btn {
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

        .add-admin-btn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 157, 0.4);
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .admin-table th {
            background-color: #f9f9f9;
            font-weight: 600;
            color: var(--text-dark);
        }

        .admin-table tr:hover {
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
            
            .admin-table {
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
            <h1 class="page-title">Admin Management</h1>
            
            <div class="admin-management">
                <div class="management-header">
                    <div class="search-box">
                        <form method="GET" action="" id="searchForm">
                            <input type="text" name="search" id="searchInput" placeholder="Search admins..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit">Search</button>
                            <button type="button" class="clear-search-btn" onclick="clearSearch()">Clear</button>
                        </form>
                    </div>
                    <button class="add-admin-btn" onclick="openAddModal()">Add New Admin</button>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="message success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="message error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if ($result && $result->num_rows > 0): ?>
                    <table class="admin-table">
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
                                    <td><?php echo $row['Admin_ID']; ?></td>
                                    <td><?php echo $row['Admin_FName'] . ' ' . $row['Admin_LName']; ?></td>
                                    <td><?php echo $row['Admin_ContactNumber']; ?></td>
                                    <td><?php echo $row['Admin_Email']; ?></td>
                                    <td><?php echo $row['Admin_ICNUMBER']; ?></td>
                                    <td><?php echo $row['Admin_DOB']; ?></td>
                                    <td class="action-buttons">
                                        <button class="edit-btn" onclick="openEditModal(<?php echo $row['Admin_ID']; ?>)">Edit</button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="admin_id" value="<?php echo $row['Admin_ID']; ?>">
                                            <button type="submit" name="delete_admin" class="delete-btn" onclick="return confirm('Are you sure you want to delete this admin?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <h3>No admins found</h3>
                        <p>There are no admins in the database. Click "Add New Admin" to add one.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Admin</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
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
                    <label for="admin_description">Description</label>
                    <textarea id="admin_description" name="admin_description" rows="3"></textarea>
                </div>
                <button type="submit" name="add_admin" class="submit-btn">Add Admin</button>
            </form>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Admin</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_admin_id" name="admin_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_admin_fname">First Name</label>
                        <input type="text" id="edit_admin_fname" name="admin_fname" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_admin_lname">Last Name</label>
                        <input type="text" id="edit_admin_lname" name="admin_lname" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_admin_contact">Contact Number</label>
                    <input type="text" id="edit_admin_contact" name="admin_contact" required>
                </div>
                <div class="form-group">
                    <label for="edit_admin_icnumber">IC Number</label>
                    <input type="text" id="edit_admin_icnumber" name="admin_icnumber" required>
                </div>
                <div class="form-group">
                    <label for="edit_admin_email">Email</label>
                    <input type="email" id="edit_admin_email" name="admin_email" required>
                </div>
                <div class="form-group">
                    <label for="edit_admin_password">Password</label>
                    <input type="password" id="edit_admin_password" name="admin_password" placeholder="Leave blank to keep current password">
                </div>
                <div class="form-group">
                    <label for="edit_admin_address">Address</label>
                    <textarea id="edit_admin_address" name="admin_address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_admin_dob">Date of Birth</label>
                    <input type="date" id="edit_admin_dob" name="admin_dob" required>
                </div>
                <div class="form-group">
                    <label for="edit_admin_description">Description</label>
                    <textarea id="edit_admin_description" name="admin_description" rows="3"></textarea>
                </div>
                <button type="submit" name="update_admin" class="submit-btn">Update Admin</button>
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

        function openEditModal(adminId) {
            // Fetch admin data via AJAX
            fetchAdminData(adminId);
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

        function fetchAdminData(adminId) {
            // Create a FormData object to send the request
            const formData = new FormData();
            formData.append('get_admin_data', 'true');
            formData.append('admin_id', adminId);
            
            fetch('admin_management_page.php', {
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
                    // Populate the edit form with admin data
                    document.getElementById('edit_admin_id').value = data.admin.Admin_ID;
                    document.getElementById('edit_admin_fname').value = data.admin.Admin_FName;
                    document.getElementById('edit_admin_lname').value = data.admin.Admin_LName;
                    document.getElementById('edit_admin_contact').value = data.admin.Admin_ContactNumber;
                    document.getElementById('edit_admin_icnumber').value = data.admin.Admin_ICNUMBER;
                    document.getElementById('edit_admin_email').value = data.admin.Admin_Email;
                    document.getElementById('edit_admin_address').value = data.admin.Admin_Address;
                    document.getElementById('edit_admin_dob').value = data.admin.Admin_DOB;
                    document.getElementById('edit_admin_description').value = data.admin.Admin_Commnent;
                    
                    // Show the edit modal
                    document.getElementById('editModal').style.display = 'flex';
                } else {
                    alert('Error fetching admin data: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching admin data. Please check the console for details.');
            });
        }
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>