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
$error_message = '';
$success_message = '';
$activities = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new activity
    if (isset($_POST['add_activity'])) {
        $activity_date = $_POST['activity_date'];
        $activity_details = $_POST['activity_details'];
        $activity_status = $_POST['activity_status'];
        $activity_getamount = $_POST['activity_getamount'];
        $branch_id = $_POST['branch_id'];
        
        // Handle image upload
        $activity_picture = '';
        if (isset($_FILES['activity_picture']) && $_FILES['activity_picture']['error'] == 0) {
            $target_dir = "uploads/activity/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["activity_picture"]["name"], PATHINFO_EXTENSION);
            $new_filename = "activity_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // Check if image file is an actual image
            $check = getimagesize($_FILES["activity_picture"]["tmp_name"]);
            if ($check !== false) {
                if (move_uploaded_file($_FILES["activity_picture"]["tmp_name"], $target_file)) {
                    $activity_picture = $target_file;
                } else {
                    $error_message = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error_message = "File is not an image.";
            }
        }
        
        $sql = "INSERT INTO activity (Activity_Date, Activity_Details, Activity_Picture, Activity_Status, Activity_GetAmount, Branch_ID) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssdi", $activity_date, $activity_details, $activity_picture, $activity_status, $activity_getamount, $branch_id);
        
        if ($stmt->execute()) {
            $success_message = "Activity added successfully!";
            // Refresh the page to show the new activity
            echo "<script>window.location.href = 'activity_management.php?success=1';</script>";
            exit();
        } else {
            $error_message = "Error adding activity: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Update activity
    if (isset($_POST['update_activity'])) {
        $activity_id = $_POST['activity_id'];
        $activity_date = $_POST['activity_date'];
        $activity_details = $_POST['activity_details'];
        $activity_status = $_POST['activity_status'];
        $activity_getamount = $_POST['activity_getamount'];
        $branch_id = $_POST['branch_id'];
        
        // Handle image upload
        $activity_picture = $_POST['current_picture'];
        if (isset($_FILES['activity_picture']) && $_FILES['activity_picture']['error'] == 0) {
            $target_dir = "uploads/activity/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["activity_picture"]["name"], PATHINFO_EXTENSION);
            $new_filename = "activity_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // Check if image file is an actual image
            $check = getimagesize($_FILES["activity_picture"]["tmp_name"]);
            if ($check !== false) {
                if (move_uploaded_file($_FILES["activity_picture"]["tmp_name"], $target_file)) {
                    // Delete old picture if exists
                    if (!empty($_POST['current_picture']) && file_exists($_POST['current_picture'])) {
                        unlink($_POST['current_picture']);
                    }
                    $activity_picture = $target_file;
                } else {
                    $error_message = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error_message = "File is not an image.";
            }
        }
        
        $sql = "UPDATE activity SET Activity_Date = ?, Activity_Details = ?, Activity_Picture = ?, 
                Activity_Status = ?, Activity_GetAmount = ?, Branch_ID = ? WHERE Activity_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssdii", $activity_date, $activity_details, $activity_picture, 
                         $activity_status, $activity_getamount, $branch_id, $activity_id);
        
        if ($stmt->execute()) {
            $success_message = "Activity updated successfully!";
            // Refresh the page
            echo "<script>window.location.href = 'activity_management.php?success=1';</script>";
            exit();
        } else {
            $error_message = "Error updating activity: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Delete activity
    if (isset($_POST['delete_activity'])) {
        $activity_id = $_POST['activity_id'];
        
        // Get activity picture path to delete the file
        $sql = "SELECT Activity_Picture FROM activity WHERE Activity_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        $stmt->bind_result($picture_path);
        $stmt->fetch();
        $stmt->close();
        
        // Delete activity
        $sql = "DELETE FROM activity WHERE Activity_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $activity_id);
        
        if ($stmt->execute()) {
            // Delete picture file if exists
            if (!empty($picture_path) && file_exists($picture_path)) {
                unlink($picture_path);
            }
            $success_message = "Activity deleted successfully!";
            // Refresh the page
            echo "<script>window.location.href = 'activity_management.php?success=1';</script>";
            exit();
        } else {
            $error_message = "Error deleting activity: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all activities
$sql = "SELECT a.*, b.Branch_Name, b.Branch_Type 
        FROM activity a 
        JOIN branch b ON a.Branch_ID = b.Branch_ID 
        ORDER BY a.Activity_Date DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
}

// Fetch branches for dropdown
$branches = [];
$sql = "SELECT Branch_ID, Branch_Name, Branch_Type FROM branch ORDER BY Branch_Name";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $branches[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Management - Donation Management System</title>
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .page-title {
            font-size: 28px;
            margin-bottom: 30px;
            color: var(--text-dark);
            border-bottom: 2px solid var(--light-pink);
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .add-btn {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(255, 107, 157, 0.3);
        }

        .add-btn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 157, 0.4);
        }

        .activities-table {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .table-header {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 1fr 1fr 1fr 1fr;
            background: linear-gradient(90deg, var(--primary-pink), var(--warm-peach));
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }

        .table-row {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 1fr 1fr 1fr 1fr;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }

        .table-row:nth-child(even) {
            background: rgba(255, 182, 193, 0.05);
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .table-row:hover {
            background: rgba(255, 182, 193, 0.1);
        }

        .activity-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--light-pink);
        }

        .no-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: var(--very-light-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 12px;
            text-align: center;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .edit-btn {
            background: linear-gradient(135deg, #a8e6cf, #6dd5a8);
            color: #155724;
        }

        .edit-btn:hover {
            background: linear-gradient(135deg, #6dd5a8, #a8e6cf);
        }

        .delete-btn {
            background: linear-gradient(135deg, #ff8fab, #ff6b9d);
            color: white;
        }

        .delete-btn:hover {
            background: linear-gradient(135deg, #ff6b9d, #ff8fab);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
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

        .status-cancelled {
            background: linear-gradient(135deg, #ffb3c6, #ff6b9d);
            color: #721c24;
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

        .no-data-message {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
            font-size: 16px;
        }

        .no-data-message a {
            color: var(--primary-pink);
            text-decoration: none;
            font-weight: 500;
        }

        .no-data-message a:hover {
            text-decoration: underline;
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
            width: 700px;
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

        .image-preview {
            margin-top: 10px;
            text-align: center;
        }

        .image-preview img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid var(--light-pink);
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

        .delete-modal-content {
            text-align: center;
        }

        .delete-modal-content p {
            margin-bottom: 20px;
            font-size: 18px;
        }

        .delete-confirm-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .cancel-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .cancel-btn:hover {
            background: #5a6268;
        }

        .confirm-delete-btn {
            background: linear-gradient(135deg, #ff6b9d, #ff4757);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .confirm-delete-btn:hover {
            background: linear-gradient(135deg, #ff4757, #ff6b9d);
        }

        @media (max-width: 1024px) {
            .table-header, .table-row {
                grid-template-columns: 1fr 2fr 1fr 1fr 1fr;
            }
            
            .activity-image, .no-image {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .table-header, .table-row {
                grid-template-columns: 1fr 2fr 1fr;
            }
            
            .activity-getamount, .activity-branch {
                display: none;
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
            <a href="branch_management_page.php" class="menu-item">
                <ion-icon name="business"></ion-icon>
                <div class="menu-text">Branch Management</div>
            </a>
            <a href="activity_management.php" class="menu-item active">
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
            <h1 class="page-title">
                Activity Management
                <button class="add-btn" onclick="openModal('addActivityModal')">
                    <ion-icon name="add-circle"></ion-icon> Add New Activity
                </button>
            </h1>
            
            <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="error-message">
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($success_message) && !empty($success_message)): ?>
            <div class="success-message">
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <div class="activities-table">
                <?php if (!empty($activities)): ?>
                    <div class="table-header">
                        <div>Image</div>
                        <div>Details</div>
                        <div>Date</div>
                        <div>Status</div>
                        <div>Amount Raised</div>
                        <div>Branch</div>
                        <div>Actions</div>
                    </div>
                    
                    <?php foreach ($activities as $activity): ?>
                        <div class="table-row">
                            <div>
                                <?php if (!empty($activity['Activity_Picture'])): ?>
                                    <img src="<?php echo $activity['Activity_Picture']; ?>" alt="Activity Image" class="activity-image">
                                <?php else: ?>
                                    <div class="no-image">No Image</div>
                                <?php endif; ?>
                            </div>
                            <div><?php echo substr($activity['Activity_Details'], 0, 100) . (strlen($activity['Activity_Details']) > 100 ? '...' : ''); ?></div>
                            <div><?php echo date('M j, Y', strtotime($activity['Activity_Date'])); ?></div>
                            <div>
                                <span class="status-badge status-<?php echo strtolower($activity['Activity_Status']); ?>">
                                    <?php echo $activity['Activity_Status']; ?>
                                </span>
                            </div>
                            <div class="activity-getamount">RM <?php echo number_format($activity['Activity_GetAmount'], 2); ?></div>
                            <div class="activity-branch"><?php echo $activity['Branch_Name']; ?></div>
                            <div class="action-buttons">
                                <button class="action-btn edit-btn" onclick="editActivity(<?php echo $activity['Activity_ID']; ?>)">
                                    <ion-icon name="create"></ion-icon> Edit
                                </button>
                                <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $activity['Activity_ID']; ?>, '<?php echo addslashes($activity['Activity_Details']); ?>')">
                                    <ion-icon name="trash"></ion-icon> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data-message">
                        No activities found. <a href="#" onclick="openModal('addActivityModal')">Add your first activity</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Activity Modal -->
    <div id="addActivityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Activity</h2>
                <button class="close-btn" onclick="closeModal('addActivityModal')">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="addActivityForm">
                <div class="form-group">
                    <label for="activity_date">Activity Date</label>
                    <input type="date" id="activity_date" name="activity_date" required>
                </div>
                <div class="form-group">
                    <label for="activity_details">Activity Details</label>
                    <textarea id="activity_details" name="activity_details" rows="4" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="activity_status">Activity Status</label>
                        <select id="activity_status" name="activity_status" required>
                            <option value="">Select Status</option>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="activity_getamount">Amount Raised (RM)</label>
                        <input type="number" id="activity_getamount" name="activity_getamount" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="branch_id">Branch</label>
                    <select id="branch_id" name="branch_id" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['Branch_ID']; ?>">
                                <?php echo $branch['Branch_Name'] . ' (' . $branch['Branch_Type'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="activity_picture">Activity Picture</label>
                    <input type="file" id="activity_picture" name="activity_picture" accept="image/*">
                    <div class="image-preview" id="imagePreviewAdd"></div>
                </div>
                <button type="submit" name="add_activity" class="submit-btn">Add Activity</button>
            </form>
        </div>
    </div>

    <!-- Edit Activity Modal -->
    <div id="editActivityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Activity</h2>
                <button class="close-btn" onclick="closeModal('editActivityModal')">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="editActivityForm">
                <input type="hidden" id="edit_activity_id" name="activity_id">
                <input type="hidden" id="edit_current_picture" name="current_picture">
                <div class="form-group">
                    <label for="edit_activity_date">Activity Date</label>
                    <input type="date" id="edit_activity_date" name="activity_date" required>
                </div>
                <div class="form-group">
                    <label for="edit_activity_details">Activity Details</label>
                    <textarea id="edit_activity_details" name="activity_details" rows="4" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_activity_status">Activity Status</label>
                        <select id="edit_activity_status" name="activity_status" required>
                            <option value="">Select Status</option>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_activity_getamount">Amount Raised (RM)</label>
                        <input type="number" id="edit_activity_getamount" name="activity_getamount" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_branch_id">Branch</label>
                    <select id="edit_branch_id" name="branch_id" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['Branch_ID']; ?>">
                                <?php echo $branch['Branch_Name'] . ' (' . $branch['Branch_Type'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_activity_picture">Activity Picture</label>
                    <input type="file" id="edit_activity_picture" name="activity_picture" accept="image/*">
                    <div class="image-preview" id="imagePreviewEdit"></div>
                </div>
                <button type="submit" name="update_activity" class="submit-btn">Update Activity</button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteActivityModal" class="modal">
        <div class="modal-content delete-modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Delete</h2>
                <button class="close-btn" onclick="closeModal('deleteActivityModal')">&times;</button>
            </div>
            <p>Are you sure you want to delete the activity: <strong id="deleteActivityName"></strong>?</p>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" id="delete_activity_id" name="activity_id">
                <div class="delete-confirm-buttons">
                    <button type="button" class="cancel-btn" onclick="closeModal('deleteActivityModal')">Cancel</button>
                    <button type="submit" name="delete_activity" class="confirm-delete-btn">Delete</button>
                </div>
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

            // Image preview for add form
            document.getElementById('activity_picture').addEventListener('change', function(e) {
                const preview = document.getElementById('imagePreviewAdd');
                preview.innerHTML = '';
                
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        preview.appendChild(img);
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });

            // Image preview for edit form
            document.getElementById('edit_activity_picture').addEventListener('change', function(e) {
                const preview = document.getElementById('imagePreviewEdit');
                preview.innerHTML = '';
                
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        preview.appendChild(img);
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });

            // Reset add form when modal is closed
            document.getElementById('addActivityModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    resetAddForm();
                }
            });

            // Reset edit form when modal is closed
            document.getElementById('editActivityModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    resetEditForm();
                }
            });
        });

        // Modal functionality
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            
            // Reset forms when closing modals
            if (modalId === 'addActivityModal') {
                resetAddForm();
            } else if (modalId === 'editActivityModal') {
                resetEditForm();
            }
        }

        // Reset add form
        function resetAddForm() {
            document.getElementById('addActivityForm').reset();
            document.getElementById('imagePreviewAdd').innerHTML = '';
        }

        // Reset edit form
        function resetEditForm() {
            document.getElementById('editActivityForm').reset();
            document.getElementById('imagePreviewEdit').innerHTML = '';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    
                    // Reset forms when closing modals by clicking outside
                    if (modal.id === 'addActivityModal') {
                        resetAddForm();
                    } else if (modal.id === 'editActivityModal') {
                        resetEditForm();
                    }
                }
            });
        }

        // Edit activity function
        function editActivity(activityId) {
            // In a real application, you would fetch the activity data via AJAX
            // For this example, we'll assume the data is already available in the page
            // and we'll find it in the activities array
            const activity = <?php echo json_encode($activities); ?>.find(a => a.Activity_ID == activityId);
            
            if (activity) {
                document.getElementById('edit_activity_id').value = activity.Activity_ID;
                document.getElementById('edit_activity_date').value = activity.Activity_Date;
                document.getElementById('edit_activity_details').value = activity.Activity_Details;
                document.getElementById('edit_activity_status').value = activity.Activity_Status;
                document.getElementById('edit_activity_getamount').value = activity.Activity_GetAmount;
                document.getElementById('edit_branch_id').value = activity.Branch_ID;
                document.getElementById('edit_current_picture').value = activity.Activity_Picture;
                
                // Show current image preview
                const preview = document.getElementById('imagePreviewEdit');
                preview.innerHTML = '';
                if (activity.Activity_Picture) {
                    const img = document.createElement('img');
                    img.src = activity.Activity_Picture;
                    preview.appendChild(img);
                }
                
                openModal('editActivityModal');
            }
        }

        // Delete confirmation function
        function confirmDelete(activityId, activityName) {
            document.getElementById('delete_activity_id').value = activityId;
            document.getElementById('deleteActivityName').textContent = activityName.substring(0, 50) + (activityName.length > 50 ? '...' : '');
            openModal('deleteActivityModal');
        }

        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);

        // Check if we should show success message from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            // You can add additional success handling here if needed
            console.log('Activity operation completed successfully');
        }
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>