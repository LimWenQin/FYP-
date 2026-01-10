<?php
include 'dataconnection.php';
include 'header_function.php';

// 检查是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// 默认用户数据
$donor = [
    "Donor_Name" => "",
    "Donor_ContactNumber" => "",
    "Donor_ICNumber" => "",
    "Donor_Email" => "",
    "Donor_Address1" => "",
    "Donor_Address2" => "",
    "Donor_Address3" => "",
    "Donor_City" => "",
    "Donor_State" => "",
    "Donor_PostalCode" => "",
    "Donor_Country" => "Malaysia",
    "Donor_DOB" => "",
    "Donor_Description" => "",
    "Donor_ProfilePicture" => ""
];

// 获取真实数据
if ($logged_in && isset($_SESSION['donor_id'])) {
    $query = "SELECT * FROM donor WHERE Donor_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['donor_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $donor = $result->fetch_assoc();
        if ($donor['Is_Deleted'] == 1) {
             session_destroy();
             echo "<script>alert('This account has been deleted.'); window.location.href='donor_login.php';</script>";
             exit();
        }
    }
    $stmt->close();
}

// --- 处理逻辑：删除账号 (Soft Delete) ---
$delete_success = false; // 新增变量，用于控制成功弹窗
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    $delete_query = "UPDATE donor SET Is_Deleted = 1 WHERE Donor_ID = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $_SESSION['donor_id']);
    
    if ($stmt->execute()) {
        // 删除成功
        $delete_success = true; 
        // 注意：这里不再 session_destroy 和 exit，而是让页面继续加载以显示弹窗
        // 我们会在 JS 中处理跳转
    } else {
        echo "<script>alert('Error deleting account.');</script>";
    }
    $stmt->close();
}

// --- 处理逻辑：更新个人资料 ---
$update_success = false;
$update_message = "";
$upload_error = "";

// 只有在没有删除成功的情况下才处理更新
if (!$delete_success && $_SERVER['REQUEST_METHOD'] == 'POST' && $logged_in && isset($_POST['update_profile'])) {
    
    // 处理头像上传
    $profile_picture = $donor['Donor_ProfilePicture']; 
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = $_FILES['profile_picture']['name'];
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_ext)) {
            if ($file_size <= 2097152) { 
                $new_file_name = 'profile_' . $_SESSION['donor_id'] . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    if (!empty($donor['Donor_ProfilePicture']) && file_exists($donor['Donor_ProfilePicture'])) {
                        unlink($donor['Donor_ProfilePicture']);
                    }
                    $profile_picture = $destination;
                } else {
                    $upload_error = "Failed to upload profile picture.";
                }
            } else {
                $upload_error = "File size too large (Max 2MB).";
            }
        } else {
            $upload_error = "Invalid file type.";
        }
    }
    
    $name = $_POST['name'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $ic_number = $_POST['icnumber']; 
    $dob = $_POST['dob'];             
    $address1 = $_POST['address1'];
    $address2 = $_POST['address2'];
    $address3 = $_POST['address3'];
    $city = $_POST['city'];
    $state = $_POST['state']; 
    $postalcode = $_POST['postalcode'];
    $country = "Malaysia"; 
    $description = $_POST['description'];
    
    $update_query = "UPDATE donor SET 
        Donor_Name = ?, 
        Donor_Email = ?, 
        Donor_ContactNumber = ?, 
        Donor_ICNumber = ?,
        Donor_DOB = ?,
        Donor_Address1 = ?,
        Donor_Address2 = ?,
        Donor_Address3 = ?,
        Donor_City = ?,
        Donor_State = ?,
        Donor_PostalCode = ?,
        Donor_Country = ?,
        Donor_Description = ?,
        Donor_ProfilePicture = ?
        WHERE Donor_ID = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssssssssssssssi", 
        $name, $email, $contact, $ic_number, $dob, 
        $address1, $address2, $address3,
        $city, $state, $postalcode, $country, $description,
        $profile_picture, $_SESSION['donor_id']
    );
    
    if ($stmt->execute()) {
        $update_success = true;
        $update_message = "Profile updated successfully!";
        $_SESSION['donor_name'] = $name;
        
        $donor['Donor_Name'] = $name;
        $donor['Donor_Email'] = $email;
        $donor['Donor_ContactNumber'] = $contact;
        $donor['Donor_ICNumber'] = $ic_number;
        $donor['Donor_DOB'] = $dob;
        $donor['Donor_Address1'] = $address1;
        $donor['Donor_Address2'] = $address2;
        $donor['Donor_Address3'] = $address3;
        $donor['Donor_City'] = $city;
        $donor['Donor_State'] = $state;
        $donor['Donor_PostalCode'] = $postalcode;
        $donor['Donor_Country'] = $country;
        $donor['Donor_Description'] = $description;
        $donor['Donor_ProfilePicture'] = $profile_picture;
    } else {
        $update_message = "Error updating profile: " . $conn->error;
    }
    $stmt->close();
}

function calculateProfileCompletion($donor) {
    $total_fields = 11; 
    $completed_fields = 0;
    $fields_to_check = ['Donor_Name', 'Donor_Email', 'Donor_ContactNumber', 'Donor_ICNumber', 'Donor_DOB', 'Donor_Address1', 'Donor_City', 'Donor_State', 'Donor_PostalCode', 'Donor_Country', 'Donor_ProfilePicture'];
    foreach ($fields_to_check as $field) {
        if (!empty($donor[$field]) && trim($donor[$field]) !== '') { $completed_fields++; }
    }
    return ($completed_fields / $total_fields) * 100;
}

$completion_percentage = calculateProfileCompletion($donor);
include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2563eb;
            --dark-blue: #1e40af;
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-red: #fee2e2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --medium-gray: #737373;
            --dark-gray: #262626;
            --text-dark: #171717;
            --border-color: #e5e5e5;
            --primary-green: #16a34a; /* Success Green */
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arial', sans-serif; }
        
        body { background-color: var(--light-gray); color: var(--text-dark); line-height: 1.6; }
        .page-container { padding: 30px; max-width: 1000px; margin: 0 auto; }
        .page-title { text-align: center; margin-bottom: 30px; font-size: 32px; color: var(--dark-red); font-weight: bold; }
        
        /* Progress Bar Card */
        .completion-card {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white; border-radius: 12px; padding: 25px;
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.1); margin-bottom: 30px;
        }
        .completion-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .completion-title { font-size: 20px; font-weight: bold; }
        .percentage { font-size: 28px; font-weight: bold; color: white; }
        .progress-bar-bg { width: 100%; height: 12px; background-color: rgba(255, 255, 255, 0.3); border-radius: 6px; overflow: hidden; margin-bottom: 15px; }
        .progress { height: 100%; background-color: white; border-radius: 6px; width: 0%; transition: width 0.5s ease; }
        
        /* Profile Form Container */
        .profile-container { background-color: var(--white); border-radius: 12px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); padding: 30px; border-top: 4px solid var(--primary-red); }
        .profile-header { display: flex; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid var(--light-red); }
        .profile-avatar-container { margin-right: 20px; text-align: center; }
        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-red), var(--dark-red)); display: flex; align-items: center; justify-content: center; font-size: 48px; color: white; font-weight: bold; overflow: hidden; border: 4px solid var(--light-red); margin-bottom: 10px; object-fit: cover; }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .change-picture-text { color: var(--primary-red); font-size: 14px; font-weight: bold; cursor: pointer; text-decoration: none; }
        .file-input { display: none; }
        .profile-info h2 { font-size: 24px; margin-bottom: 5px; color: var(--dark-gray); }
        .profile-info p { color: var(--medium-gray); }
        
        /* Form Styling */
        .form-section { margin-bottom: 25px; }
        .form-section h3 { margin-bottom: 15px; color: var(--dark-red); border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--dark-gray); }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 15px; border: 2px solid var(--border-color); border-radius: 6px; font-size: 16px; transition: all 0.3s ease; }
        .form-group input:focus { border-color: var(--primary-red); outline: none; }
        .form-group input[readonly] { background-color: #e9e9e9; cursor: not-allowed; color: #555; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        
        /* Buttons */
        .action-buttons { display: flex; gap: 15px; margin-top: 25px; }
        .submit-btn { background: linear-gradient(135deg, #f79c34ff); color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; flex: 1; transition: transform 0.2s; }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3); }
        .delete-btn { background: linear-gradient(135deg, var(--primary-red), var(--dark-red)); color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; flex: 1; transition: transform 0.2s; }
        .delete-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(220,38,38,0.3); }
        
        /* Link */
        .change-pass-link { display: block; text-align: center; margin-top: 20px; color: var(--medium-gray); font-weight: bold; font-size: 16px; text-decoration: underline; transition: all 0.3s ease; }
        .change-pass-link:hover { color: var(--dark-blue); transform: scale(1.02); }

        .tax-note { color: #dc2626; font-size: 12px; font-weight: bold; margin-top: 5px; display: block; }
        .success-message { background-color: var(--light-red); color: var(--dark-red); padding: 15px; border-radius: 6px; margin-bottom: 20px; }

        /* --- Custom Modal Styles --- */
        .modal-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            animation: fadeIn 0.3s ease;
        }

        .modal-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .modal-icon.warning { color: var(--primary-red); }
        .modal-icon.success { color: var(--primary-green); }

        .modal-title {
            font-size: 22px;
            font-weight: bold;
            color: var(--dark-gray);
            margin-bottom: 10px;
        }

        .modal-text {
            color: var(--medium-gray);
            margin-bottom: 25px;
            font-size: 16px;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center; /* Center the buttons */
        }

        .btn-cancel {
            background-color: #e5e5e5;
            color: var(--text-dark);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            flex: 1;
            transition: background 0.2s;
        }
        .btn-cancel:hover { background-color: #d4d4d4; }

        .btn-confirm-delete {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            flex: 1;
            transition: transform 0.2s;
        }
        .btn-confirm-delete:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(220,38,38,0.3); }

        .btn-ok {
            background: linear-gradient(135deg, var(--primary-green), #14532d);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            width: 100%; /* Full width for single OK button */
            transition: transform 0.2s;
        }
        .btn-ok:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(22,163,74,0.3); }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
            .profile-header { flex-direction: column; text-align: center; }
            .profile-avatar-container { margin-right: 0; margin-bottom: 15px; }
            .action-buttons { flex-direction: column; }
            .submit-btn, .delete-btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <h1 class="page-title">My Profile</h1>
        
        <?php if (!empty($update_message)): ?>
            <div class="success-message"><?php echo $update_message; ?></div>
        <?php endif; ?>
        
        <div class="completion-card">
            <div class="completion-header">
                <div class="completion-title">Profile Completion</div>
                <div class="percentage" id="progress-text"><?php echo round($completion_percentage); ?>%</div>
            </div>
            <div class="progress-bar-bg">
                <div class="progress" id="progress-bar" style="width: <?php echo $completion_percentage; ?>%"></div>
            </div>
            <div class="completion-message">Complete your details to help us serve you better!</div>
        </div>
        
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar-container">
                    <div class="profile-avatar" id="avatar-display">
                        <?php if (!empty($donor['Donor_ProfilePicture'])): ?>
                            <img src="<?php echo htmlspecialchars($donor['Donor_ProfilePicture']); ?>" id="avatar-image">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <a href="#" class="change-picture-text" onclick="document.getElementById('profile_picture').click(); return false;">Change Picture</a>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($donor['Donor_Name']); ?></h2>
                    <p><?php echo htmlspecialchars($donor['Donor_Email']); ?></p>
                </div>
            </div>
            
            <form method="POST" action="profile.php" enctype="multipart/form-data" id="profileForm">
                <input type="hidden" name="update_profile" value="1">
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="file-input" onchange="previewImage(event)">
                
                <div class="form-section">
                    <h3>Personal Information</h3>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Name']); ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="contact">Contact Number</label>
                            <input type="text" id="contact" name="contact" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_ContactNumber']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="icnumber">IC Number (Optional)</label>
                            <input type="text" id="icnumber" name="icnumber" class="track-field" 
                                   value="<?php echo htmlspecialchars($donor['Donor_ICNumber']); ?>" 
                                   placeholder="Example: 000101-01-1234" 
                                   oninput="handleICInput(this.value)">
                            <span class="tax-note">* Required For Tax Exemption</span>
                        </div>
                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="track-field" 
                                   value="<?php echo $donor['Donor_DOB']; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Address Information</h3>
                    <div class="form-group">
                        <label for="address1">Address Line 1</label>
                        <input type="text" id="address1" name="address1" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Address1']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="address2">Address Line 2</label>
                        <input type="text" id="address2" name="address2" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Address2']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="address3">Address Line 3</label>
                        <input type="text" id="address3" name="address3" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Address3']); ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="postalcode">Postal Code</label>
                            <input type="text" id="postalcode" name="postalcode" class="track-field" 
                                   value="<?php echo htmlspecialchars($donor['Donor_PostalCode']); ?>" 
                                   oninput="handlePostcodeInput(this.value)" placeholder="Enter Postcode">
                        </div>
                        <div class="form-group">
                            <label for="state">State (Auto-filled)</label>
                            <input type="text" id="state" name="state" class="track-field" 
                                   value="<?php echo htmlspecialchars($donor['Donor_State']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_City']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" class="track-field" 
                                   value="Malaysia" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>About Me</h3>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="track-field"><?php echo htmlspecialchars($donor['Donor_Description']); ?></textarea>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="submit-btn">Update Profile</button>
                    <button type="button" class="delete-btn" onclick="openDeleteModal()">Delete Account</button>
                </div>
                
                <a href="donor_change_password.php" class="change-pass-link">Change Password</a>

            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-icon warning">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h3 class="modal-title">Delete Account?</h3>
            <p class="modal-text">Are you sure you want to delete your account? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button type="button" class="btn-confirm-delete" onclick="confirmDelete()">Yes, Delete</button>
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="successModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="modal-title">Account Deleted</h3>
            <p class="modal-text">Your account has been deleted successfully.</p>
            <div class="modal-buttons">
                <button type="button" class="btn-ok" onclick="handleSuccessRedirect()">OK</button>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // --- Modal Logic ---
        function openDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function confirmDelete() {
            const form = document.getElementById('profileForm');
            // Create hidden input to simulate delete_account button press
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_account';
            hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            form.submit();
        }

        // 处理 PHP 传递过来的成功状态
        // 这里的逻辑是：如果 PHP 变量 $delete_success 为 true，则在页面加载时直接打开 success modal
        <?php if ($delete_success): ?>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById('successModal').style.display = 'flex';
            });
        <?php endif; ?>

        function handleSuccessRedirect() {
            // 点击 OK 后，跳转到登录页面，并执行 logout 操作
            // 因为 PHP 里其实还没有 destroy session (为了显示这个页面)，我们可以在这里跳到一个 logout 脚本，或者直接回 login 
            // 在 PHP 逻辑里，最好有一个专门的 logout.php 来处理 session_destroy()
            // 或者我们可以用 js 发送请求。
            
            // 简单做法：这里跳转到 donor_login.php。
            // 由于 session 还没销毁，我们可以通过 URL 参数通知 donor_login.php 执行销毁，或者
            // 在 PHP 头部判断如果 $delete_success，其实可以不必刷新页面直接显示。
            // 但为了安全和逻辑闭环，我们跳转到一个会销毁 Session 的地方。
            
            // 更好的做法：创建一个 logout.php
            window.location.href = 'donor_logout.php'; // 假设你有 logout 页面
            // 如果没有 logout.php，可以直接跳回 login，但在 login 页面要做判断清除 session
            // window.location.href = 'donor_login.php?action=logout'; 
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            // Success modal should ideally NOT close on outside click, forcing user to click OK
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

        // --- 1. Auto Detect Date from IC Logic ---
        function handleICInput(ic) {
            const cleanIC = ic.replace(/[^0-9]/g, '');
            const dobInput = document.getElementById('dob');
            
            if (cleanIC.length >= 6) {
                const yearShort = cleanIC.substring(0, 2);
                const month = cleanIC.substring(2, 4);
                const day = cleanIC.substring(4, 6);
                
                if (month > 0 && month <= 12 && day > 0 && day <= 31) {
                    const currentYearShort = new Date().getFullYear() % 100;
                    let fullYear = '';
                    if (parseInt(yearShort) > currentYearShort) { fullYear = '19' + yearShort; } else { fullYear = '20' + yearShort; }
                    
                    const dateString = `${fullYear}-${month}-${day}`;
                    dobInput.value = dateString;
                    dobInput.setAttribute('readonly', true);
                }
            } else {
                dobInput.removeAttribute('readonly');
            }
            updateProgress();
        }

        // --- 2. Auto Detect State from Postcode Logic ---
        function handlePostcodeInput(postcode) {
            const stateInput = document.getElementById('state');
            const pc = parseInt(postcode, 10);

            if (postcode.length >= 2 && !isNaN(pc)) {
                let state = "";
                if (pc >= 1000 && pc <= 2999) { state = "Perlis"; }
                else if (pc >= 5000 && pc <= 9999) { state = "Kedah"; }
                else if (pc >= 10000 && pc <= 14999) { state = "Penang"; }
                else if (pc >= 30000 && pc <= 36999) { state = "Perak"; }
                else if (pc >= 39000 && pc <= 39999) { state = "Cameron Highlands (Pahang)"; } 
                else if (pc >= 40000 && pc <= 48999) { state = "Selangor"; }
                else if (pc >= 60000 && pc <= 68999) { state = "Selangor"; } 
                else if (pc >= 50000 && pc <= 60000) { state = "Kuala Lumpur"; }
                else if (pc >= 62000 && pc <= 62999) { state = "Putrajaya"; }
                else if (pc >= 70000 && pc <= 73999) { state = "Negeri Sembilan"; }
                else if (pc >= 75000 && pc <= 78999) { state = "Melaka"; }
                else if (pc >= 80000 && pc <= 86999) { state = "Johor"; }
                else if (pc >= 25000 && pc <= 28999) { state = "Pahang"; }
                else if (pc >= 20000 && pc <= 24999) { state = "Terengganu"; }
                else if (pc >= 15000 && pc <= 19999) { state = "Kelantan"; }
                else if (pc >= 87000 && pc <= 87999) { state = "Labuan"; }
                else if (pc >= 88000 && pc <= 91999) { state = "Sabah"; }
                else if (pc >= 93000 && pc <= 98999) { state = "Sarawak"; }
                
                if (state !== "") { stateInput.value = state; }
            } else if (postcode.length === 0) {
                stateInput.value = "";
            }
            updateProgress();
        }

        // --- 3. Image Preview Logic ---
        function previewImage(event) {
            const input = event.target;
            const avatarDisplay = document.getElementById('avatar-display');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarDisplay.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">`;
                    updateProgress(); 
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // --- 4. Real-time Progress Bar Logic ---
        function updateProgress() {
            const fields = ['name', 'email', 'contact', 'icnumber', 'dob', 'address1', 'city', 'state', 'postalcode', 'country'];
            let filledCount = 0;
            const totalFields = fields.length + 1; 
            fields.forEach(id => {
                const el = document.getElementById(id);
                if (el && el.value.trim() !== '') { filledCount++; }
            });
            const avatarDisplay = document.getElementById('avatar-display');
            const fileInput = document.getElementById('profile_picture');
            const hasImgTag = avatarDisplay.querySelector('img') !== null;
            const hasFile = fileInput.files.length > 0;
            if (hasImgTag || hasFile) { filledCount++; }
            const percentage = Math.round((filledCount / totalFields) * 100);
            document.getElementById('progress-bar').style.width = percentage + '%';
            document.getElementById('progress-text').innerText = percentage + '%';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.track-field');
            inputs.forEach(input => {
                input.addEventListener('input', updateProgress);
                input.addEventListener('change', updateProgress);
            });
            const icInput = document.getElementById('icnumber');
            if (icInput.value.trim() !== '') { handleICInput(icInput.value); }
            updateProgress();
        });
    </script>
</body>
</html>