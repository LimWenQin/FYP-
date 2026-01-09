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
    }
    $stmt->close();
}

// 处理表单提交 - 更新个人资料
$update_success = false;
$update_message = "";
$upload_error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $logged_in) {
    
    // 处理头像上传
    $profile_picture = $donor['Donor_ProfilePicture']; // 保留现有头像
    
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
            if ($file_size <= 2097152) { // 2MB
                $new_file_name = 'profile_' . $_SESSION['donor_id'] . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    // 删除旧头像
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
    
    // 更新数据库
    if (isset($_POST['update_profile'])) {
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
        $country = $_POST['country'];
        $description = $_POST['description'];
        
        // 更新 Query
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
            
            // 刷新页面数据
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
}

// 初始计算完成度
function calculateProfileCompletion($donor) {
    $total_fields = 11; 
    $completed_fields = 0;
    
    $fields_to_check = [
        'Donor_Name', 'Donor_Email', 'Donor_ContactNumber', 
        'Donor_ICNumber', 'Donor_DOB', 'Donor_Address1',
        'Donor_City', 'Donor_State', 'Donor_PostalCode',
        'Donor_Country', 'Donor_ProfilePicture'
    ];
    
    foreach ($fields_to_check as $field) {
        if (!empty($donor[$field]) && trim($donor[$field]) !== '') {
            $completed_fields++;
        }
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
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-red: #fee2e2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --medium-gray: #737373;
            --dark-gray: #262626;
            --text-dark: #171717;
            --border-color: #e5e5e5;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arial', sans-serif; }
        
        body { background-color: var(--light-gray); color: var(--text-dark); line-height: 1.6; }
        
        .page-container { padding: 30px; max-width: 1000px; margin: 0 auto; }
        
        .page-title { text-align: center; margin-bottom: 30px; font-size: 32px; color: var(--dark-red); font-weight: bold; }
        
        /* Progress Bar Card */
        .completion-card {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.1);
            margin-bottom: 30px;
        }
        
        .completion-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .completion-title { font-size: 20px; font-weight: bold; }
        .percentage { font-size: 28px; font-weight: bold; color: white; }
        
        .progress-bar-bg {
            width: 100%; height: 12px; background-color: rgba(255, 255, 255, 0.3);
            border-radius: 6px; overflow: hidden; margin-bottom: 15px;
        }
        
        .progress {
            height: 100%; background-color: white; border-radius: 6px;
            width: 0%; transition: width 0.5s ease;
        }
        
        /* Profile Form Container */
        .profile-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 30px;
            border-top: 4px solid var(--primary-red);
        }
        
        .profile-header {
            display: flex; align-items: center; margin-bottom: 25px;
            padding-bottom: 20px; border-bottom: 2px solid var(--light-red);
        }
        
        .profile-avatar-container { margin-right: 20px; text-align: center; }
        
        .profile-avatar {
            width: 120px; height: 120px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            display: flex; align-items: center; justify-content: center;
            font-size: 48px; color: white; font-weight: bold;
            overflow: hidden; border: 4px solid var(--light-red);
            margin-bottom: 10px; object-fit: cover;
        }
        
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .change-picture-text { color: var(--primary-red); font-size: 14px; font-weight: bold; cursor: pointer; text-decoration: none; }
        .file-input { display: none; } /* Hidden, triggered by link */
        
        .profile-info h2 { font-size: 24px; margin-bottom: 5px; color: var(--dark-gray); }
        .profile-info p { color: var(--medium-gray); }
        
        /* Form Styling */
        .form-section { margin-bottom: 25px; }
        .form-section h3 { margin-bottom: 15px; color: var(--dark-red); border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--dark-gray); }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 12px 15px; border: 2px solid var(--border-color);
            border-radius: 6px; font-size: 16px; transition: all 0.3s ease;
        }
        .form-group input:focus { border-color: var(--primary-red); outline: none; }
        .form-group input[readonly] { background-color: #f0f0f0; cursor: pointer; color: #555; }
        
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        
        /* New CSS for Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white; border: none; padding: 12px 25px;
            border-radius: 6px; cursor: pointer; font-weight: bold;
            font-size: 16px; flex: 2; transition: transform 0.2s;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(220,38,38,0.3); }

        .change-pass-btn {
            background: linear-gradient(135deg, #737373, #404040);
            color: white; border: none; padding: 12px 25px;
            border-radius: 6px; cursor: pointer; font-weight: bold;
            font-size: 16px; flex: 1; text-align: center; text-decoration: none;
            transition: transform 0.2s;
        }
        .change-pass-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }

        /* Red Note CSS */
        .tax-note {
            color: #dc2626; /* Red color */
            font-size: 12px;
            font-weight: bold;
            margin-top: 5px;
            display: block;
        }
        
        .success-message { background-color: var(--light-red); color: var(--dark-red); padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
            .profile-header { flex-direction: column; text-align: center; }
            .profile-avatar-container { margin-right: 0; margin-bottom: 15px; }
            .action-buttons { flex-direction: column; }
            .submit-btn, .change-pass-btn { width: 100%; }
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
                            <label for="icnumber">IC Number </label>
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
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_City']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="state">State</label>
                            <input type="text" id="state" name="state" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_State']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="postalcode">Postal Code</label>
                            <input type="text" id="postalcode" name="postalcode" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_PostalCode']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Country']); ?>">
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
                    <a href="donor_change_password.php" class="change-pass-btn">Change Password</a>
                </div>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; ?>

    <script>
        // --- 1. Auto Detect Date from IC Logic ---
        function handleICInput(ic) {
            // Remove non-digit characters
            const cleanIC = ic.replace(/[^0-9]/g, '');
            
            // Need at least 6 digits to determine date (YYMMDD)
            if (cleanIC.length >= 6) {
                const yearShort = cleanIC.substring(0, 2);
                const month = cleanIC.substring(2, 4);
                const day = cleanIC.substring(4, 6);
                
                // Simple validation for month and day
                if (month > 0 && month <= 12 && day > 0 && day <= 31) {
                    const currentYearShort = new Date().getFullYear() % 100;
                    let fullYear = '';
                    
                    if (parseInt(yearShort) > currentYearShort) {
                        fullYear = '19' + yearShort;
                    } else {
                        fullYear = '20' + yearShort;
                    }
                    
                    // Auto-fill the DOB field
                    const dateString = `${fullYear}-${month}-${day}`;
                    document.getElementById('dob').value = dateString;
                }
            }
            updateProgress();
        }

        // --- 2. Image Preview Logic ---
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

        // --- 3. Real-time Progress Bar Logic ---
        function updateProgress() {
            const fields = [
                'name', 'email', 'contact', 'icnumber', 'dob',
                'address1', 'city', 'state', 'postalcode', 'country'
            ];
            
            let filledCount = 0;
            const totalFields = fields.length + 1; // +1 for profile picture
            
            fields.forEach(id => {
                const el = document.getElementById(id);
                if (el && el.value.trim() !== '') {
                    filledCount++;
                }
            });
            
            const avatarDisplay = document.getElementById('avatar-display');
            const fileInput = document.getElementById('profile_picture');
            const hasImgTag = avatarDisplay.querySelector('img') !== null;
            const hasFile = fileInput.files.length > 0;
            
            if (hasImgTag || hasFile) {
                filledCount++;
            }
            
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
            updateProgress();
        });
    </script>
</body>
</html>