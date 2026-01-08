<?php
// admin_profile.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- AJAX: Verify Current Password (For Step 1) ---
if (isset($_POST['verify_current_password_ajax'])) {
    header('Content-Type: application/json');
    $adminId = $_SESSION['admin_id'];
    $currentPass = $_POST['current_password'];
    
    $stmt = $conn->prepare("SELECT Admin_Password FROM admin WHERE Admin_ID = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $dbPass = $row['Admin_Password'];
        
        // Verify Password
        if (password_verify($currentPass, $dbPass) || ($currentPass === $dbPass)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    }
    $stmt->close();
    exit();
}

$adminId = $_SESSION['admin_id'];
$message = '';
$error = '';

// Check Edit Mode
$editMode = isset($_GET['edit']) && $_GET['edit'] == 'true';

// Get Current Admin Info
$sql = "SELECT * FROM admin WHERE Admin_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Variables for Header
$adminProfilePicture = $admin['Admin_ProfilePicture'];
$adminName = $admin['Admin_Name'];
$adminPosition = $admin['Admin_Role'];

// --- Handle Profile Update (Excluding Password) ---
if ($editMode && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullName = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    $contactRaw = mysqli_real_escape_string($conn, $_POST['contact_number']);
    // Ensure formatting logic matches your DB preference
    $contact = "+601" . $contactRaw;

    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $icNumber = mysqli_real_escape_string($conn, $_POST['ic_number']);
    $dateOfBirth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Handle Avatar Upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $uploadDir = 'uploads/profiles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $fileName = 'admin_' . $adminId . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                if (!empty($admin['Admin_ProfilePicture']) && file_exists($admin['Admin_ProfilePicture'])) {
                    unlink($admin['Admin_ProfilePicture']);
                }
                $conn->query("UPDATE admin SET Admin_ProfilePicture = '" . $uploadPath . "' WHERE Admin_ID = $adminId");
            }
        }
        
        $updateSql = "UPDATE admin SET 
                      Admin_Name = ?, Admin_Email = ?, Admin_ContactNumber = ?, 
                      Admin_Address1 = ?, Admin_Address2 = ?, Admin_Address3 = ?, 
                      Admin_City = ?, Admin_State = ?, Admin_PostalCode = ?, Admin_Country = ?,
                      Admin_ICNUMBER = ?, Admin_DOB = ?
                      WHERE Admin_ID = ?";
        
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ssssssssssssi", $fullName, $email, $contact, $address1, $address2, $address3, $city, $state, $postalCode, $country, $icNumber, $dateOfBirth, $adminId);
        
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $_SESSION['admin_name'] = $fullName; 
            // Refresh Data
            $result = $conn->query("SELECT * FROM admin WHERE Admin_ID = $adminId");
            $admin = $result->fetch_assoc();
            $adminName = $admin['Admin_Name'];
            $adminProfilePicture = $admin['Admin_ProfilePicture']; 
            $editMode = false; 
        } else {
            $error = "Failed to update profile: " . $conn->error;
        }
        $stmt->close();
    }
}

// --- Handle Change Password Logic ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password_action'])) {
    $currentPass = $_POST['current_password'];
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_new_password'];

    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>])[A-Za-z\d!@#$%^&*()\-_=+{};:,<.>]{8,15}$/';

    if ($newPass !== $confirmPass) {
        $error = "New passwords do not match.";
    } elseif (!preg_match($pattern, $newPass)) {
        $error = "Password does not meet requirements.";
    } else {
        $stmt = $conn->prepare("SELECT Admin_Password FROM admin WHERE Admin_ID = ?");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $dbPass = $row['Admin_Password'];

            if (password_verify($currentPass, $dbPass) || ($currentPass === $dbPass)) {
                $newPassHash = password_hash($newPass, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE admin SET Admin_Password = ? WHERE Admin_ID = ?");
                $updateStmt->bind_param("si", $newPassHash, $adminId);
                
                if ($updateStmt->execute()) {
                    $message = "Password updated successfully!";
                } else {
                    $error = "Error updating password.";
                }
                $updateStmt->close();
            } else {
                $error = "Current password is incorrect.";
            }
        }
        $stmt->close();
    }
}

$displayPhone = isset($admin['Admin_ContactNumber']) ? str_replace('+601', '', $admin['Admin_ContactNumber']) : '';
$malaysiaStates = ['Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editMode ? "Edit Profile" : "View Profile"; ?> - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .profile-container { max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); padding: 30px; }
        .section-title { font-size: 18px; font-weight: 600; color: var(--dark); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-select { width: 100%; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; font-size: 14px; transition: border-color 0.3s; }
        .form-input:focus, .form-select:focus { border-color: var(--primary); }
        .form-input:read-only { background-color: #f8f9fa; color: #6c757d; cursor: default; }
        .form-row { display: flex; gap: 20px; }
        .form-row .form-group { flex: 1; }
        
        .profile-picture-section { display: flex; flex-direction: column; align-items: center; margin-bottom: 30px; }
        .profile-picture-preview { width: 120px; height: 120px; border-radius: 50%; border: 4px solid #f8f9fa; margin-bottom: 15px; background: #eee; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .profile-picture-preview img { width: 100%; height: 100%; object-fit: cover; }
        .default-avatar-icon { font-size: 48px; color: #ccc; }
        .file-upload-label { display: inline-block; padding: 8px 15px; background: #fff; border: 1px solid var(--gray-light); border-radius: 5px; cursor: pointer; font-size: 13px; transition: all 0.3s; color: var(--dark); }
        .file-upload-label:hover { border-color: var(--primary); color: var(--primary); background: #fff5f5; }
        input[type="file"] { display: none; }
        .file-info { display: none; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; background: #f1f1f1; padding: 5px 10px; border-radius: 5px; }
        .file-name { font-size: 12px; color: #555; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-remove { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 14px; padding: 0 5px; }

        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn { padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; font-size: 14px; transition: all 0.3s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: var(--gray-light); color: var(--dark); }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1100; justify-content: center; align-items: center; backdrop-filter: blur(2px); }
        .modal-content { background-color: white; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); animation: slideDown 0.3s ease-out; overflow: hidden; }
        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; border-bottom: 1px solid #f0f0f0; background: #fff; }
        .modal-header h2 { font-size: 18px; font-weight: 700; margin: 0; color: #333; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; transition: color 0.2s; }
        .close-btn:hover { color: #333; }
        .modal-body { padding: 25px; }

        .password-input-container { position: relative; display: flex; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .password-input-container input { flex: 1; border-radius: 8px 0 0 8px; border: 1px solid #ddd; border-right: none; padding: 12px 15px; font-size: 15px; transition: all 0.3s; }
        .password-input-container input:focus { border-color: #F28585; box-shadow: 0 0 0 3px rgba(242, 133, 133, 0.1); z-index: 2; }
        .password-input-container .toggle-password { border: 1px solid #ddd; border-left: none; border-radius: 0 8px 8px 0; background: #f9f9f9; cursor: pointer; padding: 0 15px; display: flex; align-items: center; justify-content: center; color: #666; transition: background 0.2s; }
        .password-input-container .toggle-password:hover { background-color: #eee; }
        
        .password-requirements { margin-top: 10px; font-size: 12px; background: #f8f9fa; padding: 10px; border-radius: 6px; }
        .requirement-item { display: flex; align-items: center; margin-bottom: 4px; color: #888; transition: color 0.3s; }
        .requirement-item.valid { color: #28a745; }
        .requirement-item.invalid { color: #aaa; } 
        .requirement-item i { width: 20px; text-align: center; margin-right: 5px; }
        .modal-btn-primary { width: 100%; padding: 12px; background: #F28585; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.3s, transform 0.1s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .modal-btn-primary:hover { background: #e07474; }
        .modal-btn-secondary { background: #f1f1f1; color: #333; width: 30%; }
        .modal-btn-secondary:hover { background: #e4e4e4; }
        .spinner { border: 2px solid rgba(255,255,255,0.3); border-top: 2px solid white; border-radius: 50%; width: 16px; height: 16px; animation: spin 0.8s linear infinite; display: none; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: #666; font-size: 14px; }
        .phone-input { border-radius: 0 5px 5px 0 !important; }
        
        @media (max-width: 768px) { .form-row { flex-direction: column; gap: 0; } }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1><?php echo $editMode ? "Edit Profile" : "My Profile"; ?></h1>
                <p>Manage your account settings and preferences.</p>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

            <div class="profile-container">
                <form method="POST" enctype="multipart/form-data" onsubmit="return validateProfileForm()">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="profile-picture-section">
                        <div class="profile-picture-preview" id="preview-container">
                            <?php if (!empty($admin['Admin_ProfilePicture'])): ?>
                                <img src="<?php echo htmlspecialchars($admin['Admin_ProfilePicture']); ?>" alt="Profile">
                            <?php else: ?>
                                <div class="default-avatar-icon"><i class="fas fa-user"></i></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($editMode): ?>
                            <label for="profile_picture" class="file-upload-label">
                                <i class="fas fa-camera"></i> Change Photo
                            </label>
                            <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this)">
                            
                            <div id="file-info" class="file-info">
                                <span id="file-name" class="file-name">filename.jpg</span>
                                <button type="button" class="file-remove" onclick="removeImage()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <span class="form-guide">Recommended: Square JPG/PNG, max 2MB.</span>
                        <?php endif; ?>
                    </div>

                    <h3 class="section-title">Personal Information</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <?php if($editMode) echo '<span class="required">*</span>'; ?></label>
                        <input type="text" name="full_name" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['Admin_Name']); ?>" 
                               <?php echo $editMode ? 'required oninput="validateName(this)"' : 'readonly'; ?>>
                        <?php if($editMode): ?>
                            <span class="form-guide">Enter your full name as per IC. Letters and spaces only.</span>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email Address <?php if($editMode) echo '<span class="required">*</span>'; ?></label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_Email']); ?>" 
                                   <?php echo $editMode ? 'required onblur="validateEmail()"' : 'readonly'; ?>>
                            <?php if($editMode): ?>
                                <span class="form-guide">Valid email address (e.g. name@domain.com).</span>
                                <div id="emailError" style="color:red; font-size:11px; display:none;">Invalid email format</div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <?php if($editMode) echo '<span class="required">*</span>'; ?></label>
                            <?php if ($editMode): ?>
                                <div class="phone-format">
                                    <span class="phone-prefix">+601</span>
                                    <input type="text" id="contact" name="contact_number" class="form-input phone-input" 
                                           value="<?php echo htmlspecialchars($displayPhone); ?>" 
                                           required maxlength="11" oninput="formatPhone(this)">
                                </div>
                                <span class="form-guide">Format: 2-3456789 or 11-12345678 (No need for +601).</span>
                            <?php else: ?>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars($admin['Admin_ContactNumber']); ?>" readonly>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number <?php if($editMode) echo '<span class="required">*</span>'; ?></label>
                            <input type="text" name="ic_number" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_ICNUMBER']); ?>" 
                                   <?php echo $editMode ? 'required' : 'readonly'; ?>>
                            <?php if($editMode): ?>
                                <span class="form-guide">Format: YYMMDD-PB-#### (e.g. 900101-01-1234).</span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth <?php if($editMode) echo '<span class="required">*</span>'; ?></label>
                            <input type="date" name="date_of_birth" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_DOB']); ?>" 
                                   <?php echo $editMode ? 'required' : 'readonly'; ?>>
                            <?php if($editMode): ?>
                                <span class="form-guide">Select your date of birth.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h3 class="section-title" style="margin-top: 30px;">Address Details</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Address Line 1 <?php if($editMode) echo '<span class="required">*</span>'; ?></label>
                        <input type="text" name="address1" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['Admin_Address1']); ?>" 
                               <?php echo $editMode ? 'required' : 'readonly'; ?>>
                        <?php if($editMode): ?>
                            <span class="form-guide">House unit no., floor, building, street name.</span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" name="address2" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['Admin_Address2']); ?>" 
                               <?php echo $editMode ? '' : 'readonly'; ?>>
                        <?php if($editMode): ?>
                            <span class="form-guide">Residential area, village, or section.</span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['Admin_Address3']); ?>" 
                               <?php echo $editMode ? '' : 'readonly'; ?>>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code <?php if($editMode) echo '<span class="required">*</span>'; ?></label>
                            <input type="text" name="postal_code" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_PostalCode']); ?>" 
                                   <?php echo $editMode ? 'required' : 'readonly'; ?>>
                            <?php if($editMode): ?>
                                <span class="form-guide">Enter postal code (e.g. 50000).</span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <?php if($editMode) echo '<span class="required">*</span>'; ?></label>
                            <input type="text" name="city" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_City']); ?>" 
                                   <?php echo $editMode ? 'required' : 'readonly'; ?>>
                            <?php if($editMode): ?>
                                <span class="form-guide">City or Municipality.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State <?php if($editMode) echo '<span class="required">*</span>'; ?></label>
                            <?php if ($editMode): ?>
                                <select name="state" class="form-select" required>
                                    <option value="">Select State</option>
                                    <?php foreach($malaysiaStates as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo ($admin['Admin_State'] == $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars($admin['Admin_State']); ?>" readonly>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-input" 
                                   value="Malaysia" readonly>
                        </div>
                    </div>

                    <?php if ($editMode): ?>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_profile.php'">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    <?php else: ?>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_dashboard.php'">Back to Dashboard</button>
                            <button type="button" class="btn btn-primary" onclick="window.location.href='admin_profile.php?edit=true'">Edit Profile</button>
                            <button type="button" class="btn btn-primary" onclick="openChangePasswordModal()" style="margin-left: 10px; background-color: #6c757d; border-color: #6c757d;">Change Password</button>
                        </div>
                    <?php endif; ?>

                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="changePasswordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Change Password</h2>
                <button class="close-btn" onclick="closeChangePasswordModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form action="admin_profile.php" method="POST" id="passwordForm" onsubmit="return validateForm()">
                    <input type="hidden" name="change_password_action" value="1">
                    
                    <div id="step-1">
                        <p style="color:#666; margin-bottom:15px; font-size:14px;">To continue, please verify it's you by entering your current password.</p>
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <div class="password-input-container">
                                <input type="password" name="current_password" id="current_password" required placeholder="Enter current password">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('current_password')"><i class="fas fa-eye"></i></button>
                            </div>
                            <small id="verifyError" style="color:#dc3545; display:none; margin-top:5px; font-weight:600;"><i class="fas fa-exclamation-circle"></i> Incorrect password.</small>
                        </div>
                        
                        <button type="button" class="modal-btn-primary" id="btn-next" onclick="verifyCurrentPass()">
                            Next <div class="spinner" id="verifySpinner"></div>
                        </button>
                    </div>

                    <div id="step-2" style="display:none;">
                        <div class="form-group">
                            <label class="form-label">New Password <span class="required" style="color:red">*</span></label>
                            <div class="password-input-container">
                                <input type="password" id="new_password" name="new_password" placeholder="Min 8 characters" oninput="validatePasswordRequirements()">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('new_password')"><i class="fas fa-eye"></i></button>
                            </div>
                            <div class="password-requirements">
                                <div class="requirement-item invalid" id="lengthReq"><i class="fas fa-times"></i> Min 8-15 characters</div>
                                <div class="requirement-item invalid" id="uppercaseReq"><i class="fas fa-times"></i> At least 1 Uppercase (A-Z)</div>
                                <div class="requirement-item invalid" id="lowercaseReq"><i class="fas fa-times"></i> At least 1 Lowercase (a-z)</div>
                                <div class="requirement-item invalid" id="numberReq"><i class="fas fa-times"></i> At least 1 Number (0-9)</div>
                                <div class="requirement-item invalid" id="specialReq"><i class="fas fa-times"></i> At least 1 Symbol (!@#...)</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm New Password <span class="required" style="color:red">*</span></label>
                            <div class="password-input-container">
                                <input type="password" id="confirm_new_password" name="confirm_new_password" placeholder="Re-enter new password" oninput="validatePasswordMatch()">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_new_password')"><i class="fas fa-eye"></i></button>
                            </div>
                            <small id="passError" style="color:red; display:none; margin-top:5px;">Passwords do not match</small>
                        </div>
                        
                        <div style="display:flex; gap:10px;">
                            <button type="button" class="modal-btn-primary modal-btn-secondary" onclick="goBackToStep1()">Back</button>
                            <button type="submit" id="btn-update" class="modal-btn-primary" style="flex:1;">Update Password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const container = document.getElementById('preview-container');
            const fileInfo = document.getElementById('file-info');
            const fileName = document.getElementById('file-name');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    container.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    if (fileInfo) { fileInfo.style.display = 'inline-flex'; fileName.textContent = input.files[0].name; }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        function removeImage() {
            document.getElementById('profile_picture').value = '';
            document.getElementById('file-info').style.display = 'none';
            document.getElementById('preview-container').innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
        }

        function validateName(input) { input.value = input.value.replace(/[^a-zA-Z\s]/g, ''); }
        function validateEmail() { 
            const v = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|my)$/i.test(document.getElementById('email').value); 
            document.getElementById('emailError').style.display = v ? 'none' : 'block'; 
            return v; 
        }
        function formatPhone(input) {
            let val = input.value.replace(/\D/g, ''); 
            if (val.length > 0) input.value = val.substring(0, 1) + (val.length >= 1 ? '-' : '') + (val.length > 1 ? val.substring(1, 10) : '');
        }
        function validateProfileForm() {
            <?php if($editMode): ?>
            return validateEmail();
            <?php else: ?>
            return true;
            <?php endif; ?>
        }

        function openChangePasswordModal() { 
            document.getElementById('changePasswordModal').style.display = 'flex'; 
            goBackToStep1(); 
            document.getElementById('passwordForm').reset();
            setTimeout(() => document.getElementById('current_password').focus(), 100);
        }
        function closeChangePasswordModal() { document.getElementById('changePasswordModal').style.display = 'none'; }

        function verifyCurrentPass() {
            const currentPass = document.getElementById('current_password').value;
            const errorMsg = document.getElementById('verifyError');
            const spinner = document.getElementById('verifySpinner');
            
            if(!currentPass) { document.getElementById('current_password').focus(); return; }

            errorMsg.style.display = 'none';
            spinner.style.display = 'inline-block';

            const formData = new FormData();
            formData.append('verify_current_password_ajax', '1');
            formData.append('current_password', currentPass);

            fetch('admin_profile.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                spinner.style.display = 'none';
                if(data.status === 'success') {
                    document.getElementById('step-1').style.display = 'none';
                    document.getElementById('step-2').style.display = 'block';
                    document.getElementById('new_password').required = true;
                    document.getElementById('confirm_new_password').required = true;
                    setTimeout(() => document.getElementById('new_password').focus(), 100);
                } else {
                    errorMsg.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
                    errorMsg.style.display = 'block';
                }
            })
            .catch(error => {
                spinner.style.display = 'none';
                errorMsg.innerHTML = "An error occurred. Please try again.";
                errorMsg.style.display = 'block';
            });
        }

        function goBackToStep1() {
            document.getElementById('step-1').style.display = 'block';
            document.getElementById('step-2').style.display = 'none';
            document.getElementById('new_password').required = false;
            document.getElementById('confirm_new_password').required = false;
            document.getElementById('verifyError').style.display = 'none';
        }

        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const button = input.nextElementSibling; 
            const icon = button.querySelector('i');
            if (input.type === 'password') { input.type = 'text'; icon.className = 'fas fa-eye-slash'; } 
            else { input.type = 'password'; icon.className = 'fas fa-eye'; }
        }

        function validatePasswordRequirements() {
            const pw = document.getElementById('new_password').value;
            const reqs = {
                lengthReq: pw.length >= 8 && pw.length <= 15,
                uppercaseReq: /[A-Z]/.test(pw),
                lowercaseReq: /[a-z]/.test(pw),
                numberReq: /\d/.test(pw),
                specialReq: /[!@#$%^&*()\-=_+{}[\]:;"'<>,.?/|\\]/.test(pw)
            };
            let allValid = true;
            for (const [id, valid] of Object.entries(reqs)) {
                const el = document.getElementById(id);
                const icon = el.querySelector('i');
                if (valid) { el.className = 'requirement-item valid'; icon.className = 'fas fa-check'; } 
                else { el.className = 'requirement-item invalid'; icon.className = 'fas fa-times'; allValid = false; }
            }
            if(document.getElementById('confirm_new_password').value) validatePasswordMatch();
            return allValid;
        }

        function validatePasswordMatch() {
            const p1 = document.getElementById("new_password").value;
            const p2 = document.getElementById("confirm_new_password").value;
            const match = p1 === p2 && p1 !== "";
            document.getElementById("passError").style.display = match ? "none" : (p2 ? "block" : "none");
            return match;
        }

        function validateForm() { return validatePasswordRequirements() && validatePasswordMatch(); }

        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'change_password') { openChangePasswordModal(); }
            const currentPassInput = document.getElementById('current_password');
            if(currentPassInput) {
                currentPassInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); verifyCurrentPass(); }
                });
            }
        });
    </script>
</body>
</html>