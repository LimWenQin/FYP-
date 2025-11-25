<?php
// branch_management_page.php - Branch Management Page
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// Handle AJAX request for branch data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_branch_data'])) {
    $branch_id = $_POST['branch_id'];
    $sql = "SELECT * FROM branch WHERE Branch_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $branch_data = $result->fetch_assoc();
        echo json_encode(['success' => true, 'branch' => $branch_data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Branch not found']);
    }
    $stmt->close();
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new branch
    if (isset($_POST['add_branch'])) {
        $name = $_POST['branch_name'];
        $type = $_POST['branch_type'];
        $address = $_POST['branch_address'];
        $contact = $_POST['branch_contact'];
        $description = $_POST['branch_description'];
        $admin_id = $_SESSION['admin_id'];
        
        $sql = "INSERT INTO branch (Branch_Name, Branch_Type, Branch_Address, Branch_ContactNumber, 
                Branch_Description, Admin_ID) 
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
    
    // Update branch
    if (isset($_POST['update_branch'])) {
        $branch_id = $_POST['branch_id'];
        $name = $_POST['branch_name'];
        $type = $_POST['branch_type'];
        $address = $_POST['branch_address'];
        $contact = $_POST['branch_contact'];
        $description = $_POST['branch_description'];
        
        $sql = "UPDATE branch SET 
                Branch_Name = ?, Branch_Type = ?, Branch_Address = ?, 
                Branch_ContactNumber = ?, Branch_Description = ?
                WHERE Branch_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $name, $type, $address, $contact, $description, $branch_id);
        
        if ($stmt->execute()) {
            $success_message = "Branch updated successfully!";
        } else {
            $error_message = "Error updating branch: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Delete branch
    if (isset($_POST['delete_branch'])) {
        $branch_id = $_POST['branch_id'];
        
        $sql = "DELETE FROM branch WHERE Branch_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $branch_id);
        
        if ($stmt->execute()) {
            $success_message = "Branch deleted successfully!";
        } else {
            $error_message = "Error deleting branch: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle search
$search_query = "";
if (isset($_GET['search'])) {
    $search_query = $_GET['search'];
    $sql = "SELECT * FROM branch WHERE 
            Branch_Name LIKE ? OR 
            Branch_Type LIKE ? OR 
            Branch_Address LIKE ? OR 
            Branch_ContactNumber LIKE ?
            ORDER BY Branch_ID DESC";
    $stmt = $conn->prepare($sql);
    $search_param = "%" . $search_query . "%";
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Get all branches
    $sql = "SELECT * FROM branch ORDER BY Branch_ID DESC";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Management - Donation Management System</title>
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Branch Management Specific Styles */
        .branch-management {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .branch-management::before {
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

        .add-branch-btn {
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

        .add-branch-btn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 157, 0.4);
        }

        .branch-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .branch-table th,
        .branch-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .branch-table th {
            background-color: #f9f9f9;
            font-weight: 600;
            color: var(--text-dark);
        }

        .branch-table tr:hover {
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
            
            .branch-table {
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
            <a href="admin_dashboard.php" class="menu-item">
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
            <a href="branch_management_page.php" class="menu-item active">
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
            <h1 class="page-title">Branch Management</h1>
            
            <div class="branch-management">
                <div class="management-header">
                    <div class="search-box">
                        <form method="GET" action="" id="searchForm">
                            <input type="text" name="search" id="searchInput" placeholder="Search branches..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit">Search</button>
                            <button type="button" class="clear-search-btn" onclick="clearSearch()">Clear</button>
                        </form>
                    </div>
                    <button class="add-branch-btn" onclick="openAddModal()">Add New Branch</button>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="message success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="message error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if ($result && $result->num_rows > 0): ?>
                    <table class="branch-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['Branch_ID']; ?></td>
                                    <td><?php echo $row['Branch_Name']; ?></td>
                                    <td><?php echo $row['Branch_Type']; ?></td>
                                    <td><?php echo $row['Branch_ContactNumber']; ?></td>
                                    <td><?php echo strlen($row['Branch_Address']) > 50 ? substr($row['Branch_Address'], 0, 50) . '...' : $row['Branch_Address']; ?></td>
                                    <td class="action-buttons">
                                        <button class="edit-btn" onclick="openEditModal(<?php echo $row['Branch_ID']; ?>)">Edit</button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="branch_id" value="<?php echo $row['Branch_ID']; ?>">
                                            <button type="submit" name="delete_branch" class="delete-btn" onclick="return confirm('Are you sure you want to delete this branch?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <h3>No branches found</h3>
                        <p>There are no branches in the database. Click "Add New Branch" to add one.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Branch Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Branch</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
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
                        <option value="Headquarters">Headquarters</option>
                        <option value="Regional">Regional</option>
                        <option value="Local">Local</option>
                        <option value="Community Center">Community Center</option>
                        <option value="Collection Point">Collection Point</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="branch_contact">Contact Number</label>
                    <input type="text" id="branch_contact" name="branch_contact" required>
                </div>
                <div class="form-group">
                    <label for="branch_address">Address</label>
                    <textarea id="branch_address" name="branch_address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="branch_description">Description</label>
                    <textarea id="branch_description" name="branch_description" rows="3"></textarea>
                </div>
                <button type="submit" name="add_branch" class="submit-btn">Add Branch</button>
            </form>
        </div>
    </div>

    <!-- Edit Branch Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Branch</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_branch_id" name="branch_id">
                <div class="form-group">
                    <label for="edit_branch_name">Branch Name</label>
                    <input type="text" id="edit_branch_name" name="branch_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_branch_type">Branch Type</label>
                    <select id="edit_branch_type" name="branch_type" required>
                        <option value="">Select Type</option>
                        <option value="Headquarters">Headquarters</option>
                        <option value="Regional">Regional</option>
                        <option value="Local">Local</option>
                        <option value="Community Center">Community Center</option>
                        <option value="Collection Point">Collection Point</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_branch_contact">Contact Number</label>
                    <input type="text" id="edit_branch_contact" name="branch_contact" required>
                </div>
                <div class="form-group">
                    <label for="edit_branch_address">Address</label>
                    <textarea id="edit_branch_address" name="branch_address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_branch_description">Description</label>
                    <textarea id="edit_branch_description" name="branch_description" rows="3"></textarea>
                </div>
                <button type="submit" name="update_branch" class="submit-btn">Update Branch</button>
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

        function openEditModal(branchId) {
            // Fetch branch data via AJAX
            fetchBranchData(branchId);
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

        function fetchBranchData(branchId) {
            // Create a FormData object to send the request
            const formData = new FormData();
            formData.append('get_branch_data', 'true');
            formData.append('branch_id', branchId);
            
            fetch('branch_management_page.php', {
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
                    // Populate the edit form with branch data
                    document.getElementById('edit_branch_id').value = data.branch.Branch_ID;
                    document.getElementById('edit_branch_name').value = data.branch.Branch_Name;
                    document.getElementById('edit_branch_type').value = data.branch.Branch_Type;
                    document.getElementById('edit_branch_contact').value = data.branch.Branch_ContactNumber;
                    document.getElementById('edit_branch_address').value = data.branch.Branch_Address;
                    document.getElementById('edit_branch_description').value = data.branch.Branch_Description;
                    
                    // Show the edit modal
                    document.getElementById('editModal').style.display = 'flex';
                } else {
                    alert('Error fetching branch data: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching branch data. Please check the console for details.');
            });
        }
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>