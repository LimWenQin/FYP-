<?php
include 'dataconnection.php';
include 'header_function.php';

// 检查是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: donor_login.php");
    exit();
}

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// 默认用户数据
$donor = [
    "Donor_Name" => "Guest",
    "Donor_ContactNumber" => "N/A",
    "Donor_ICNumber" => "N/A",
    "Donor_Email" => "N/A",
    "Donor_Address1" => "",
    "Donor_Address2" => "",
    "Donor_Address3" => "",
    "Donor_City" => "",
    "Donor_State" => "",
    "Donor_PostalCode" => "",
    "Donor_Country" => "",
    "Donor_DOB" => "2000-01-01",
    "Donor_Description" => "",
    "Donor_ProfilePicture" => ""
];

// 如果用户已登录，获取真实数据
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
    
    // 获取捐赠记录
    $donation_query = "SELECT * FROM donations WHERE Donor_ID = ? ORDER BY Donation_Date DESC LIMIT 5";
    $stmt = $conn->prepare($donation_query);
    $stmt->bind_param("i", $_SESSION['donor_id']);
    $stmt->execute();
    $donation_result = $stmt->get_result();
    $donations = [];
    while($row = $donation_result->fetch_assoc()) {
        $donations[] = $row;
    }
    $stmt->close();
    
    // 获取捐赠统计（用于显示在页面）
    $stats_query = "SELECT 
        COUNT(*) as total_donations,
        SUM(Donation_Amount) as total_amount,
        MAX(Donation_Date) as last_donation
    FROM donations WHERE Donor_ID = ?";
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("i", $_SESSION['donor_id']);
    $stmt->execute();
    $stats_result = $stmt->get_result();
    $donation_stats = $stats_result->fetch_assoc();
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
        // 检查文件是否上传成功
        $upload_dir = 'uploads/profile_pictures/';
        
        // 如果目录不存在则创建
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // 获取文件信息
        $file_name = $_FILES['profile_picture']['name'];
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_error = $_FILES['profile_picture']['error'];
        
        // 获取文件扩展名
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // 允许的文件类型
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        // 验证文件类型
        if (in_array($file_ext, $allowed_ext)) {
            // 验证文件大小 (最大2MB)
            if ($file_size <= 2097152) {
                // 生成唯一的文件名
                $new_file_name = 'profile_' . $_SESSION['donor_id'] . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                
                // 移动上传的文件
                if (move_uploaded_file($file_tmp, $destination)) {
                    // 删除旧的头像文件（如果有）
                    if (!empty($donor['Donor_ProfilePicture']) && file_exists($donor['Donor_ProfilePicture'])) {
                        unlink($donor['Donor_ProfilePicture']);
                    }
                    
                    $profile_picture = $destination;
                } else {
                    $upload_error = "Failed to upload profile picture.";
                }
            } else {
                $upload_error = "File size is too large. Maximum size is 2MB.";
            }
        } else {
            $upload_error = "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.";
        }
    }
    
    // 检查是哪个表单提交
    if (isset($_POST['update_profile'])) {
        // 更新个人资料
        $name = $_POST['name'];
        $email = $_POST['email'];
        $contact = $_POST['contact'];
        $address1 = $_POST['address1'];
        $address2 = $_POST['address2'];
        $address3 = $_POST['address3'];
        $city = $_POST['city'];
        $state = $_POST['state'];
        $postalcode = $_POST['postalcode'];
        $country = $_POST['country'];
        $description = $_POST['description'];
        
        $update_query = "UPDATE donor SET 
            Donor_Name = ?, 
            Donor_Email = ?, 
            Donor_ContactNumber = ?, 
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
        $stmt->bind_param("ssssssssssssi", 
            $name, $email, $contact, $address1, $address2, $address3,
            $city, $state, $postalcode, $country, $description,
            $profile_picture, $_SESSION['donor_id']
        );
        
        if ($stmt->execute()) {
            $update_success = true;
            $update_message = "Profile updated successfully!";
            // 更新session中的用户名
            $_SESSION['donor_name'] = $name;
            // 重新获取更新后的数据
            $donor['Donor_Name'] = $name;
            $donor['Donor_Email'] = $email;
            $donor['Donor_ContactNumber'] = $contact;
            $donor['Donor_Address1'] = $address1;
            $donor['Donor_Address2'] = $address2;
            $donor['Donor_Address3'] = $address3;
            $donor['Donor_City'] = $city;
            $donor['Donor_State'] = $state;
            $donor['Donor_PostalCode'] = $postalcode;
            $donor['Donor_Country'] = $country;
            $donor['Donor_Description'] = $description;
            $donor['Donor_ProfilePicture'] = $profile_picture;
        }
        $stmt->close();
    }
}

// 计算资料完成度
function calculateProfileCompletion($donor) {
    $total_fields = 11; // 总字段数
    $completed_fields = 0;
    
    // 检查每个字段是否填写
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
            --success-green: #10b981;
            --error-red: #dc2626;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: var(--light-gray);
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        .page-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 32px;
            color: var(--dark-red);
            font-weight: bold;
        }
        
        /* Profile Completion Card - 放在表单上方 */
        .completion-card {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.1);
            margin-bottom: 30px;
        }
        
        .completion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .completion-title {
            font-size: 20px;
            font-weight: bold;
        }
        
        .percentage {
            font-size: 28px;
            font-weight: bold;
            color: white;
        }
        
        .progress-bar {
            width: 100%;
            height: 12px;
            background-color: rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .progress {
            height: 100%;
            background-color: white;
            border-radius: 6px;
            transition: width 0.5s ease;
        }
        
        .completion-message {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Profile Container */
        .profile-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 30px;
            border-top: 4px solid var(--primary-red);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light-red);
        }
        
        .profile-avatar-container {
            position: relative;
            margin-right: 20px;
            text-align: center;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
            overflow: hidden;
            border: 4px solid var(--light-red);
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .change-picture-text {
            display: block;
            color: var(--primary-red);
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .change-picture-text:hover {
            color: var(--dark-red);
            text-decoration: underline;
        }
        
        .profile-info h2 {
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--dark-gray);
        }
        
        .profile-info p {
            color: var(--medium-gray);
        }
        
        /* Form Sections */
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-section h3 {
            margin-bottom: 15px;
            color: var(--dark-red);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px var(--light-red);
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        /* Donation Container - 可折叠 */
        .donation-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 25px;
            border-top: 4px solid var(--primary-red);
        }
        
        .donation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        
        .donation-header:hover {
            background-color: var(--light-red);
        }
        
        .donation-title {
            font-size: 20px;
            font-weight: bold;
            color: var(--dark-red);
        }
        
        .toggle-icon {
            font-size: 20px;
            color: var(--primary-red);
            transition: transform 0.3s;
        }
        
        .donation-list {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease, opacity 0.5s ease;
            opacity: 0;
        }
        
        .donation-list.expanded {
            max-height: 500px;
            opacity: 1;
            margin-top: 20px;
        }
        
        .donation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s;
        }
        
        .donation-item:hover {
            background-color: var(--light-red);
        }
        
        .donation-item:last-child {
            border-bottom: none;
        }
        
        .donation-info h4 {
            margin-bottom: 5px;
            color: var(--dark-gray);
            font-size: 16px;
        }
        
        .donation-info p {
            color: var(--medium-gray);
            font-size: 14px;
        }
        
        .donation-amount {
            font-weight: bold;
            color: var(--dark-red);
            font-size: 18px;
        }
        
        /* Buttons */
        .submit-btn {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        
        .submit-btn:hover {
            background: linear-gradient(135deg, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .change-password-btn {
            background: linear-gradient(135deg, var(--medium-gray), var(--dark-gray));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .change-password-btn:hover {
            background: linear-gradient(135deg, var(--dark-gray), var(--medium-gray));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(115, 115, 115, 0.3);
            text-decoration: none;
            color: white;
        }
        
        /* Profile Picture Upload */
        .file-input-container {
            position: relative;
            margin-top: 10px;
        }
        
        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-info {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-top: 5px;
        }
        
        /* Messages */
        .success-message {
            background-color: var(--light-red);
            color: var(--dark-red);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-red);
            font-weight: 500;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .info-message {
            background-color: var(--light-red);
            color: var(--dark-red);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 2px solid var(--border-color);
        }
        
        /* Login Prompt */
        .login-prompt {
            text-align: center;
            padding: 40px;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 50px auto;
            border-top: 4px solid var(--primary-red);
        }
        
        .login-prompt h2 {
            margin-bottom: 15px;
            color: var(--dark-red);
        }
        
        .login-prompt p {
            color: var(--medium-gray);
            margin-bottom: 25px;
        }
        
        .login-prompt .login-btn {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .login-prompt .login-btn:hover {
            background: linear-gradient(135deg, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .page-container {
                padding: 15px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar-container {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 36px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .page-title {
                font-size: 24px;
            }
            
            .completion-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
        }
        
        /* Disabled fields styling */
        input:disabled, textarea:disabled {
            background-color: #f9f9f9;
            color: #666;
            cursor: not-allowed;
        }
        
        /* Avatar preview */
        .avatar-preview {
            margin-top: 10px;
            text-align: center;
        }
        
        .avatar-preview img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 50%;
            border: 3px solid var(--light-red);
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <h1 class="page-title">My Profile</h1>
        
        <?php if ($logged_in): ?>
            <!-- Success Message -->
            <?php if (!empty($update_message)): ?>
                <div class="success-message"><?php echo $update_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($upload_error)): ?>
                <div class="error-message"><?php echo $upload_error; ?></div>
            <?php endif; ?>
            
            <!-- Profile Completion Card - 放在表单上方 -->
            <div class="completion-card">
                <div class="completion-header">
                    <div class="completion-title">Profile Completion</div>
                    <div class="percentage"><?php echo round($completion_percentage); ?>%</div>
                </div>
                <div class="progress-bar">
                    <div class="progress" style="width: <?php echo $completion_percentage; ?>%"></div>
                </div>
                <div class="completion-message">
                    <?php if ($completion_percentage < 50): ?>
                        Complete your profile to get personalized recommendations.
                    <?php elseif ($completion_percentage < 80): ?>
                        Good progress! Add more details to complete your profile.
                    <?php else: ?>
                        Excellent! Your profile is almost complete.
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <!-- Profile Information Form -->
                <div class="profile-container">
                    <div class="profile-header">
                        <div class="profile-avatar-container">
                            <div class="profile-avatar" id="avatar-display">
                                <?php if (!empty($donor['Donor_ProfilePicture'])): ?>
                                    <img src="<?php echo htmlspecialchars($donor['Donor_ProfilePicture']); ?>" alt="Profile Picture" id="avatar-image">
                                <?php else: ?>
                                    <span id="avatar-initials">
                                        <?php 
                                            $name = htmlspecialchars($donor['Donor_Name']);
                                            $names = explode(' ', $name);
                                            $initials = '';
                                            foreach ($names as $n) {
                                                if (!empty($n)) {
                                                    $initials .= strtoupper(substr($n, 0, 1));
                                                    if (strlen($initials) >= 2) break;
                                                }
                                            }
                                            if (empty($initials)) $initials = 'U';
                                            echo $initials;
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <a href="#" class="change-picture-text" onclick="document.getElementById('profile_picture').click(); return false;">
                                Change picture
                            </a>
                            <div class="file-input-container">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="file-input" onchange="previewImage(event)">
                            </div>
                            <div class="file-info">
                                <p>Supported formats: JPG, JPEG, PNG, GIF<br>Max file size: 2MB</p>
                            </div>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($donor['Donor_Name']); ?></h2>
                            <p>Donor ID: <?php echo isset($_SESSION['donor_id']) ? $_SESSION['donor_id'] : 'N/A'; ?></p>
                            <div class="avatar-preview" id="avatar-preview" style="display: none;">
                                <p>Preview:</p>
                                <img id="image-preview">
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="profile.php" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-section">
                            <h3>Personal Information</h3>
                            
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($donor['Donor_Name']); ?>" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($donor['Donor_Email']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contact">Contact Number</label>
                                    <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($donor['Donor_ContactNumber']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="icnumber">IC Number</label>
                                    <input type="text" id="icnumber" value="<?php echo htmlspecialchars($donor['Donor_ICNumber']); ?>" disabled>
                                </div>
                                
                                <div class="form-group">
                                    <label for="dob">Date of Birth</label>
                                    <input type="date" id="dob" value="<?php echo $donor['Donor_DOB']; ?>" disabled>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Address Information</h3>
                            
                            <div class="form-group">
                                <label for="address1">Address Line 1</label>
                                <input type="text" id="address1" name="address1" placeholder="House/Unit No, Street Name" value="<?php echo htmlspecialchars($donor['Donor_Address1']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="address2">Address Line 2 (Optional)</label>
                                <input type="text" id="address2" name="address2" placeholder="Apartment/Building/Floor" value="<?php echo htmlspecialchars($donor['Donor_Address2']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="address3">Address Line 3 (Optional)</label>
                                <input type="text" id="address3" name="address3" placeholder="Landmark/Additional Information" value="<?php echo htmlspecialchars($donor['Donor_Address3']); ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" placeholder="Kuala Lumpur" value="<?php echo htmlspecialchars($donor['Donor_City']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="state">State</label>
                                    <input type="text" id="state" name="state" placeholder="Selangor" value="<?php echo htmlspecialchars($donor['Donor_State']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="postalcode">Postal Code</label>
                                    <input type="text" id="postalcode" name="postalcode" placeholder="50000" value="<?php echo htmlspecialchars($donor['Donor_PostalCode']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="country">Country</label>
                                    <input type="text" id="country" name="country" placeholder="Malaysia" value="<?php echo htmlspecialchars($donor['Donor_Country']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>About Me</h3>
                            <div class="form-group">
                                <label for="description">Bio / Description</label>
                                <textarea id="description" name="description" placeholder="Tell us a little about yourself..."><?php echo htmlspecialchars($donor['Donor_Description']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="submit-btn">Update Profile</button>
                            <a href="donor_change_password.php" class="change-password-btn">Change Password</a>
                        </div>
                    </form>
                </div>
                
                <!-- Donation History - 可折叠 -->
                <div class="donation-container">
                    <div class="donation-header" id="donation-toggle">
                        <div class="donation-title">Recent Donations</div>
                        <span class="toggle-icon">▼</span>
                    </div>
                    <div class="donation-list" id="donation-list">
                        <?php if (!empty($donations)): ?>
                            <?php foreach ($donations as $donation): ?>
                                <div class="donation-item">
                                    <div class="donation-info">
                                        <h4><?php echo htmlspecialchars($donation['Project_Name'] ?? 'General Donation'); ?></h4>
                                        <p><?php echo date('M d, Y', strtotime($donation['Donation_Date'])); ?></p>
                                        <p>Status: <span style="color: var(--dark-red); font-weight: bold;">Completed</span></p>
                                    </div>
                                    <div class="donation-amount">
                                        RM <?php echo number_format($donation['Donation_Amount'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div style="text-align: center; margin-top: 20px;">
                                <a href="History.php" class="change-password-btn">View All Donations</a>
                            </div>
                        <?php else: ?>
                            <div class="info-message">
                                <p>You haven't made any donations yet.</p>
                                <a href="Payment_page.php" class="submit-btn" style="margin-top: 10px; display: inline-block;">Make Your First Donation</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <div class="login-prompt">
                <h2>Please log in to view and edit your profile</h2>
                <p>You need to be logged in to access your personal information and donation history.</p>
                <a href="donor_login.php" class="login-btn">Login Now</a>
            </div>
        <?php endif; ?>
    </div>
             <?php include 'footer.php'; ?>
    <script>
        // 实时进度条更新
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.querySelector('.progress');
            if (progressBar) {
                const percentage = <?php echo $completion_percentage; ?>;
                let currentPercentage = 0;
                
                // 动画效果
                const animateProgress = () => {
                    if (currentPercentage < percentage) {
                        currentPercentage += 1;
                        progressBar.style.width = currentPercentage + '%';
                        setTimeout(animateProgress, 10);
                    }
                };
                
                // 延迟开始动画
                setTimeout(animateProgress, 500);
            }
            
            // 可折叠的捐赠记录
            const donationToggle = document.getElementById('donation-toggle');
            const donationList = document.getElementById('donation-list');
            const toggleIcon = document.querySelector('.toggle-icon');
            
            if (donationToggle && donationList) {
                // 默认收起
                donationList.classList.remove('expanded');
                toggleIcon.textContent = '▶';
                
                donationToggle.addEventListener('click', function() {
                    donationList.classList.toggle('expanded');
                    if (donationList.classList.contains('expanded')) {
                        toggleIcon.textContent = '▼';
                    } else {
                        toggleIcon.textContent = '▶';
                    }
                });
            }
            
            // 表单验证
            const profileForm = document.querySelector('form[method="POST"]');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const email = document.getElementById('email').value;
                    const contact = document.getElementById('contact').value;
                    
                    // 简单的邮箱验证
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        alert('Please enter a valid email address.');
                        e.preventDefault();
                        return false;
                    }
                    
                    // 简单的手机号验证（马来西亚格式）
                    const contactRegex = /^01[0-9]-[0-9]{7,8}$/;
                    if (!contactRegex.test(contact)) {
                        alert('Please enter a valid contact number (format: 01X-XXXXXXX)');
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
        
        // 图片预览功能
        function previewImage(event) {
            const input = event.target;
            const preview = document.getElementById('image-preview');
            const previewContainer = document.getElementById('avatar-preview');
            const avatarDisplay = document.getElementById('avatar-display');
            const avatarImage = document.getElementById('avatar-image');
            const avatarInitials = document.getElementById('avatar-initials');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // 显示预览
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                    
                    // 更新头像显示
                    if (avatarImage) {
                        avatarImage.src = e.target.result;
                    } else {
                        // 如果还没有图片元素，创建一个
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.alt = "Profile Picture";
                        newImg.id = "avatar-image";
                        newImg.style.width = "100%";
                        newImg.style.height = "100%";
                        newImg.style.objectFit = "cover";
                        
                        // 隐藏初始字母
                        if (avatarInitials) {
                            avatarInitials.style.display = 'none';
                        }
                        
                        // 添加图片到头像显示区域
                        avatarDisplay.insertBefore(newImg, avatarDisplay.firstChild);
                    }
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>