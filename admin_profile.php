<?php
// admin_profile.php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 包含数据库连接
include 'dataconnection.php';

$adminId = $_SESSION['admin_id'];
$message = '';
$error = '';

// 检查是否是编辑模式
$editMode = isset($_GET['edit']) && $_GET['edit'] == 'true';

// 获取当前管理员信息
$sql = "SELECT * FROM admin WHERE Admin_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// 处理表单提交（仅当在编辑模式时）
if ($editMode && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $email = $_POST['email'];
    $contact = $_POST['contact_number'];
    $address = $_POST['address'];
    $icNumber = $_POST['ic_number'];
    $dateOfBirth = $_POST['date_of_birth'];
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.(com|net|org|edu|gov)$/i', $email)) {
        $error = "Invalid email format. Email must contain @ and end with .com, .net, .org, .edu, or .gov";
    }
    
    // 验证年龄是否满18岁
    $dob = new DateTime($dateOfBirth);
    $today = new DateTime();
    $age = $today->diff($dob)->y;
    if ($age < 18) {
        $error = "You must be at least 18 years old to register.";
    }
    
    // 处理密码更改
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
        // 验证当前密码
        if ($currentPassword !== $admin['Admin_Password']) {
            $error = "Current password is incorrect.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New password and confirmation do not match.";
        } elseif (empty($newPassword)) {
            $error = "New password cannot be empty.";
        } else {
            // 更新密码
            $passwordUpdateSql = "UPDATE admin SET Admin_Password = ? WHERE Admin_ID = ?";
            $stmt = $conn->prepare($passwordUpdateSql);
            $stmt->bind_param("si", $newPassword, $adminId);
            if (!$stmt->execute()) {
                $error = "Failed to update password: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    // 处理文件上传
    if (empty($error)) {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $fileType = $_FILES['profile_picture']['type'];
            
            if (in_array($fileType, $allowedTypes)) {
                $uploadDir = 'uploads/profiles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $fileName = 'admin_' . $adminId . '_' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                    // 删除旧的头像文件
                    if (!empty($admin['Admin_ProfilePicture']) && file_exists($admin['Admin_ProfilePicture'])) {
                        unlink($admin['Admin_ProfilePicture']);
                    }
                    $profilePicture = $uploadPath;
                } else {
                    $error = "Failed to upload profile picture.";
                }
            } else {
                $error = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
            }
        } else {
            $profilePicture = $admin['Admin_ProfilePicture'];
        }
    }
    
    if (empty($error)) {
        // 更新数据库
        $updateSql = "UPDATE admin SET 
                     Admin_FName = ?, 
                     Admin_LName = ?, 
                     Admin_Email = ?, 
                     Admin_ContactNumber = ?, 
                     Admin_Address = ?, 
                     Admin_ProfilePicture = ?,
                     Admin_ICNumber = ?,
                     Admin_DOB = ?
                     WHERE Admin_ID = ?";
        
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ssssssssi", $firstName, $lastName, $email, $contact, $address, $profilePicture, $icNumber, $dateOfBirth, $adminId);
        
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            // 更新会话中的姓名
            $_SESSION['admin_name'] = $firstName . ' ' . $lastName;
            $_SESSION['admin_email'] = $email;
            // 重新获取管理员信息
            $result = $conn->query("SELECT * FROM admin WHERE Admin_ID = $adminId");
            $admin = $result->fetch_assoc();
            // 退出编辑模式
            $editMode = false;
        } else {
            $error = "Failed to update profile: " . $conn->error;
        }
        $stmt->close();
    }
}

$conn->close();

// 设置页面标题和模式
$pageTitle = $editMode ? "Edit Profile" : "View Profile";
$pageDescription = $editMode ? "Update your personal information and profile picture" : "Your personal information and account details";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            margin: 0 auto 15px;
            background: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-picture .default-avatar {
            font-size: 48px;
            color: var(--primary);
        }

        .profile-header h1 {
            margin-bottom: 5px;
            font-size: 28px;
        }

        .profile-header p {
            opacity: 0.9;
            font-size: 16px;
        }

        .profile-form {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-control:read-only {
            background-color: #f8f9fa;
            color: #6c757d;
            border-color: #e9ecef;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .file-upload-container {
            position: relative;
            margin-bottom: 20px;
        }

        .file-upload-label {
            display: block;
            padding: 12px 15px;
            background: var(--light);
            border: 1px dashed var(--gray);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload-label:hover {
            border-color: var(--primary);
            background: rgba(242, 133, 133, 0.1);
        }

        .file-upload input[type="file"] {
            position: absolute;
            left: -9999px;
        }

        .file-info {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: var(--light);
            border-radius: 8px;
            border: 1px solid var(--gray-light);
        }

        .file-info.active {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .file-details {
            display: flex;
            align-items: center;
        }

        .file-icon {
            margin-right: 10px;
            font-size: 20px;
            color: var(--primary);
        }

        .file-name {
            font-weight: 500;
        }

        .file-remove {
            background: none;
            border: none;
            color: var(--danger);
            font-size: 18px;
            cursor: pointer;
            padding: 5px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #e07575;
        }

        .btn-secondary {
            background: var(--gray-light);
            color: var(--dark);
            margin-right: 10px;
        }

        .btn-secondary:hover {
            background: #d8d8d8;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 16px;
        }

        .password-toggle:hover {
            color: var(--dark);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-light);
            color: var(--primary);
        }

        .age-error {
            display: none;
            color: var(--danger);
            font-size: 12px;
            margin-top: 5px;
        }

        .age-error.show {
            display: block;
        }

        .info-text {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
            padding: 10px;
            background: rgba(242, 133, 133, 0.05);
            border-radius: 8px;
        }

        /* Center the welcome section */
        .welcome-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            font-size: 32px;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .welcome-section p {
            font-size: 16px;
            color: var(--gray);
        }

        /* Dashboard Header Styles */
        .user-profile {
            position: relative;
            margin-left: 20px;
            cursor: pointer;
        }

        .user-profile-with-avatar {
            display: flex;
            align-items: center;
            padding: 8px 15px;
            border-radius: 8px;
            transition: background 0.3s;
            cursor: pointer;
        }

        .user-profile-with-avatar:hover {
            background: var(--gray-light);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            min-width: 200px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 10px 0;
            z-index: 1001;
            display: none;
        }

        .user-profile-dropdown.show {
            display: block;
        }

        .user-profile-dropdown a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: background 0.3s;
        }

        .user-profile-dropdown a:hover {
            background: var(--gray-light);
        }

        .user-profile-dropdown i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
        }

        .user-profile-dropdown .divider {
            height: 1px;
            background: var(--gray-light);
            margin: 5px 0;
        }

        /* Notification Dropdown Styles */
        .notification {
            position: relative;
            margin-left: 20px;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            width: 350px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            z-index: 1001;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 16px;
            font-weight: 600;
        }

        .notification-header a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-light);
            cursor: pointer;
            transition: background 0.3s;
        }

        .notification-item:hover {
            background: var(--gray-light);
        }

        .notification-item.unread {
            background: rgba(242, 133, 133, 0.05);
        }

        .notification-content {
            display: flex;
            align-items: flex-start;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .notification-icon.success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .notification-icon.warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .notification-icon.info {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        .notification-icon.danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .notification-details h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .notification-details p {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 4px;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 12px;
            color: var(--gray);
        }

        .notification-footer {
            padding: 15px 20px;
            text-align: center;
            border-top: 1px solid var(--gray-light);
        }

        .notification-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-secondary {
                margin-right: 0;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar collapsed" id="sidebar">
        <div class="sidebar-menu">
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="admin_donor_page.php"><i class="fas fa-users"></i> <span>Donor Management</span></a></li>
                <li><a href="staff_management_page.php"><i class="fas fa-user-tie"></i> <span>Staff Management</span></a></li>
                <li><a href="admin_management_page.php"><i class="fas fa-user-shield"></i> <span>Admin Management</span></a></li>
                <li><a href="branch_management_page.php"><i class="fas fa-map-marker-alt"></i> <span>Branch Management</span></a></li>
                <li><a href="activity_management.php"><i class="fas fa-calendar-alt"></i> <span>Activity Management</span></a></li>
                <li><a href="payment_management.php"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div class="nav-left">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo">
                    <a href="admin_dashboard.php">
                        <img src="logo.jpg" alt="Logo">
                        <h1>DonationMS</h1>
                    </a>
                </div>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
            </div>
            <div class="nav-right">
                <div class="notification" id="notificationDropdown">
                    <i class="far fa-bell"></i>
                    <span class="notification-count">5</span>
                    <div class="notification-dropdown" id="notificationMenu">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <a href="#" onclick="markAllAsRead()">Mark all as read</a>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <!-- Notifications will be loaded here -->
                        </div>
                        <div class="notification-footer">
                            <a href="notifications.php">View All Notifications</a>
                        </div>
                    </div>
                </div>
                <div class="user-profile" id="userProfileDropdown">
                    <div class="user-profile-with-avatar">
                        <div class="user-avatar">
                            <?php if (!empty($admin['Admin_ProfilePicture'])): ?>
                                <img src="<?php echo htmlspecialchars($admin['Admin_ProfilePicture']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <?php echo substr($admin['Admin_FName'], 0, 1); ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($admin['Admin_FName'] . ' ' . $admin['Admin_LName']); ?></div>
                            <div class="user-role">System Administrator</div>
                        </div>
                        <i class="fas fa-chevron-down" style="margin-left: 10px; font-size: 12px;"></i>
                    </div>
                    <div class="user-profile-dropdown" id="userProfileMenu">
                        <a href="admin_profile.php">
                            <i class="fas fa-user"></i> View Profile
                        </a>
                        <a href="admin_profile.php?edit=true">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                        <div class="divider"></div>
                        <a href="admin_logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="dashboard-content">
            <div class="welcome-section">
                <h1><?php echo $pageTitle; ?></h1>
                <p><?php echo $pageDescription; ?></p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-picture">
                        <?php if (!empty($admin['Admin_ProfilePicture'])): ?>
                            <img src="<?php echo htmlspecialchars($admin['Admin_ProfilePicture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <div class="default-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h1><?php echo htmlspecialchars($admin['Admin_FName'] . ' ' . $admin['Admin_LName']); ?></h1>
                    <p>System Administrator</p>
                </div>

                <?php if ($editMode): ?>
                    <!-- Edit Mode Form -->
                    <form class="profile-form" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="profile_picture">Profile Picture</label>
                            <div class="file-upload">
                                <label for="profile_picture" class="file-upload-label" id="fileUploadLabel">
                                    <i class="fas fa-upload"></i> Choose Profile Picture
                                </label>
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                                <div class="file-info" id="fileInfo">
                                    <div class="file-details">
                                        <div class="file-icon">
                                            <i class="fas fa-image"></i>
                                        </div>
                                        <div class="file-name" id="fileName">File Name</div>
                                    </div>
                                    <button type="button" class="file-remove" id="fileRemove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <h3 class="section-title">Personal Information</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin['Admin_FName']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin['Admin_LName']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($admin['Admin_Email']); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_number">Contact Number</label>
                                <input type="tel" id="contact_number" name="contact_number" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin['Admin_ContactNumber']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="ic_number">IC Number</label>
                                <input type="text" id="ic_number" name="ic_number" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin['Admin_ICNumber'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                   value="<?php echo htmlspecialchars($admin['Admin_DOB'] ?? ''); ?>" required>
                            <div class="age-error" id="ageError">You must be at least 18 years old.</div>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="4" required><?php echo htmlspecialchars($admin['Admin_Address']); ?></textarea>
                        </div>

                        <h3 class="section-title">Change Password</h3>
                        <p style="margin-bottom: 20px; color: var(--gray); font-size: 14px;">Leave password fields blank if you don't want to change your password.</p>

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <div class="password-container">
                                <input type="password" id="current_password" name="current_password" class="form-control">
                                <button type="button" class="password-toggle" id="toggleCurrentPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="password-container">
                                <input type="password" id="new_password" name="new_password" class="form-control">
                                <button type="button" class="password-toggle" id="toggleNewPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="password-container">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_profile.php'">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                Update Profile
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- View Mode Form -->
                    <div class="profile-form">
                        <div class="info-text">
                            <i class="fas fa-info-circle"></i> This is a view-only page. To edit your profile, please use the "Edit Profile" option in the user menu.
                        </div>

                        <h3 class="section-title">Personal Information</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin['Admin_FName']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin['Admin_LName']); ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($admin['Admin_Email']); ?>" readonly>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_number">Contact Number</label>
                                <input type="tel" id="contact_number" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin['Admin_ContactNumber']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="ic_number">IC Number</label>
                                <input type="text" id="ic_number" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin['Admin_ICNumber'] ?? 'Not provided'); ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="text" id="date_of_birth" class="form-control" 
                                   value="<?php echo !empty($admin['Admin_DOB']) ? date('F j, Y', strtotime($admin['Admin_DOB'])) : 'Not provided'; ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" class="form-control" rows="4" readonly><?php echo htmlspecialchars($admin['Admin_Address']); ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_dashboard.php'">
                                Back to Dashboard
                            </button>
                            <button type="button" class="btn btn-primary" onclick="window.location.href='admin_profile.php?edit=true'">
                                Edit Profile
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            const icon = menuToggle.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-bars';
            } else {
                icon.className = 'fas fa-times';
            }
        });

        <?php if ($editMode): ?>
        // Password toggle functionality
        function setupPasswordToggle(passwordId, toggleId) {
            const passwordInput = document.getElementById(passwordId);
            const toggleButton = document.getElementById(toggleId);
            
            toggleButton.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle eye icon
                const icon = toggleButton.querySelector('i');
                if (type === 'password') {
                    icon.className = 'fas fa-eye';
                } else {
                    icon.className = 'fas fa-eye-slash';
                }
            });
        }

        // Setup password toggles
        setupPasswordToggle('current_password', 'toggleCurrentPassword');
        setupPasswordToggle('new_password', 'toggleNewPassword');
        setupPasswordToggle('confirm_password', 'toggleConfirmPassword');

        // Phone number formatting
        const contactInput = document.getElementById('contact_number');
        contactInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.startsWith('60')) {
                value = value.replace(/^60/, '+60');
            }
            
            // Format: +60-11-1234567
            if (value.length > 3) {
                value = value.substring(0, 3) + '-' + value.substring(3);
            }
            if (value.length > 6) {
                value = value.substring(0, 6) + '-' + value.substring(6);
            }
            
            e.target.value = value;
        });

        // IC number formatting
        const icInput = document.getElementById('ic_number');
        icInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Format: 000000-00-0000
            if (value.length > 6) {
                value = value.substring(0, 6) + '-' + value.substring(6);
            }
            if (value.length > 9) {
                value = value.substring(0, 9) + '-' + value.substring(9);
            }
            
            e.target.value = value;
        });

        // Age validation
        const dobInput = document.getElementById('date_of_birth');
        const ageError = document.getElementById('ageError');
        
        dobInput.addEventListener('change', function() {
            const dob = new Date(this.value);
            const today = new Date();
            const age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            if (age < 18) {
                ageError.classList.add('show');
            } else {
                ageError.classList.remove('show');
            }
        });

        // File upload functionality
        const fileInput = document.getElementById('profile_picture');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileRemove = document.getElementById('fileRemove');
        const fileUploadLabel = document.getElementById('fileUploadLabel');
        const profilePicture = document.querySelector('.profile-picture');
        
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Show file info
                fileName.textContent = file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
                fileInfo.classList.add('active');
                fileUploadLabel.style.display = 'none';
                
                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Remove default avatar if exists
                    const defaultAvatar = profilePicture.querySelector('.default-avatar');
                    if (defaultAvatar) {
                        profilePicture.removeChild(defaultAvatar);
                    }
                    
                    // Remove existing image if exists
                    const existingImg = profilePicture.querySelector('img');
                    if (existingImg) {
                        profilePicture.removeChild(existingImg);
                    }
                    
                    // Create new image
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Profile Picture';
                    profilePicture.appendChild(img);
                }
                reader.readAsDataURL(file);
            }
        });
        
        fileRemove.addEventListener('click', function() {
            fileInput.value = '';
            fileInfo.classList.remove('active');
            fileUploadLabel.style.display = 'block';
            
            // Reset profile picture to default
            const img = profilePicture.querySelector('img');
            if (img) {
                profilePicture.removeChild(img);
            }
            
            const defaultAvatar = document.createElement('div');
            defaultAvatar.className = 'default-avatar';
            defaultAvatar.innerHTML = '<i class="fas fa-user"></i>';
            profilePicture.appendChild(defaultAvatar);
        });

        // Form validation
        const form = document.querySelector('.profile-form');
        form.addEventListener('submit', function(e) {
            const emailInput = document.getElementById('email');
            const email = emailInput.value;
            
            // Email validation
            if (!email.includes('@') || !email.match(/\.(com|net|org|edu|gov)$/i)) {
                e.preventDefault();
                alert('Please enter a valid email address with a proper domain (e.g., .com, .net, .org)');
                emailInput.focus();
                return;
            }
            
            // Age validation
            const dob = new Date(dobInput.value);
            const today = new Date();
            const age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            if (age < 18) {
                e.preventDefault();
                alert('You must be at least 18 years old to update your profile.');
                dobInput.focus();
                return;
            }
            
            // Password validation
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if ((newPassword || confirmPassword) && !currentPassword) {
                e.preventDefault();
                alert('Please enter your current password to change your password.');
                document.getElementById('current_password').focus();
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation do not match.');
                document.getElementById('new_password').focus();
                return;
            }
        });
        <?php endif; ?>

        // Add active class to sidebar menu items on click
        document.querySelectorAll('.sidebar-menu a').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.sidebar-menu a').forEach(link => {
                    link.classList.remove('active');
                });
                this.classList.add('active');
            });
        });

        // Dropdown functionality
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationMenu = document.getElementById('notificationMenu');
        const userProfileDropdown = document.getElementById('userProfileDropdown');
        const userProfileMenu = document.getElementById('userProfileMenu');

        // Toggle notification dropdown
        notificationDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu.classList.toggle('show');
            userProfileMenu.classList.remove('show');
        });

        // Toggle user profile dropdown
        userProfileDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            userProfileMenu.classList.toggle('show');
            notificationMenu.classList.remove('show');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            notificationMenu.classList.remove('show');
            userProfileMenu.classList.remove('show');
        });

        // Load notifications
        function loadNotifications() {
            const notificationList = document.getElementById('notificationList');
            const notifications = [
                {
                    type: 'success',
                    icon: 'fas fa-donate',
                    title: 'New Donation Received',
                    message: 'John Smith donated RM 500.00',
                    time: '5 minutes ago',
                    unread: true
                },
                {
                    type: 'info',
                    icon: 'fas fa-user-plus',
                    title: 'New Donor Registered',
                    message: 'Sarah Johnson registered as a new donor',
                    time: '1 hour ago',
                    unread: true
                },
                {
                    type: 'warning',
                    icon: 'fas fa-exclamation-triangle',
                    title: 'Low Stock Alert',
                    message: 'Reward items are running low',
                    time: '2 hours ago',
                    unread: false
                },
                {
                    type: 'danger',
                    icon: 'fas fa-times-circle',
                    title: 'Payment Failed',
                    message: 'A recurring donation payment failed',
                    time: '1 day ago',
                    unread: false
                },
                {
                    type: 'info',
                    icon: 'fas fa-calendar-check',
                    title: 'Activity Reminder',
                    message: 'Charity event starts tomorrow',
                    time: '2 days ago',
                    unread: false
                }
            ];

            let html = '';
            notifications.forEach(notification => {
                html += `
                    <div class="notification-item ${notification.unread ? 'unread' : ''}">
                        <div class="notification-content">
                            <div class="notification-icon ${notification.type}">
                                <i class="${notification.icon}"></i>
                            </div>
                            <div class="notification-details">
                                <h4>${notification.title}</h4>
                                <p>${notification.message}</p>
                                <div class="notification-time">${notification.time}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            notificationList.innerHTML = html;
        }

        // Mark all as read function
        function markAllAsRead() {
            const notificationItems = document.querySelectorAll('.notification-item.unread');
            notificationItems.forEach(item => {
                item.classList.remove('unread');
            });
            
            // Update notification count
            const notificationCount = document.querySelector('.notification-count');
            notificationCount.textContent = '0';
            notificationCount.style.display = 'none';
            
            // Close dropdown
            notificationMenu.classList.remove('show');
        }

        // Load notifications when page loads
        document.addEventListener('DOMContentLoaded', loadNotifications);
    </script>
</body>
</html>