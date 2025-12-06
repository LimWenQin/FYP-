<?php
// admin_profile.php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

$adminId = $_SESSION['admin_id'];
$message = '';
$error = '';

// 检查是否是编辑模式
$editMode = isset($_GET['edit']) && $_GET['edit'] == 'true';

// 获取管理员信息
$sql = "SELECT * FROM admin WHERE Admin_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// 处理表单提交
if ($editMode && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullName = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // 组合电话号码: +601 + 用户输入部分
    $contactRaw = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $contact = "+601" . $contactRaw;

    // 获取分栏地址
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);

    $icNumber = mysqli_real_escape_string($conn, $_POST['ic_number']);
    $dateOfBirth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    
    // 验证
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // 处理密码
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!empty($newPassword)) {
            if ($newPassword !== $confirmPassword) {
                $error = "Passwords do not match.";
            } else {
                // 更新密码 (这里假设无加密，如有请加 password_hash)
                $conn->query("UPDATE admin SET Admin_Password = '$newPassword' WHERE Admin_ID = $adminId");
            }
        }
    }
    
    // 处理头像上传
    if (empty($error) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $uploadDir = 'uploads/profiles/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $fileName = 'admin_' . $adminId . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
            // 删除旧头像
            if (!empty($admin['Admin_ProfilePicture']) && file_exists($admin['Admin_ProfilePicture'])) {
                unlink($admin['Admin_ProfilePicture']);
            }
            $conn->query("UPDATE admin SET Admin_ProfilePicture = '" . $uploadPath . "' WHERE Admin_ID = $adminId");
        }
    }
    
    if (empty($error)) {
        // 更新数据库
        $updateSql = "UPDATE admin SET 
                     Admin_Name = ?, 
                     Admin_Email = ?, 
                     Admin_ContactNumber = ?, 
                     Admin_Address1 = ?, 
                     Admin_Address2 = ?, 
                     Admin_Address3 = ?, 
                     Admin_City = ?, 
                     Admin_State = ?, 
                     Admin_PostalCode = ?, 
                     Admin_Country = ?,
                     Admin_ICNUMBER = ?,
                     Admin_DOB = ?
                     WHERE Admin_ID = ?";
        
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ssssssssssssi", $fullName, $email, $contact, $address1, $address2, $address3, $city, $state, $postalCode, $country, $icNumber, $dateOfBirth, $adminId);
        
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $_SESSION['admin_name'] = $fullName; 
            
            // 刷新数据
            $result = $conn->query("SELECT * FROM admin WHERE Admin_ID = $adminId");
            $admin = $result->fetch_assoc();
            $editMode = false; 
        } else {
            $error = "Failed to update profile: " . $conn->error;
        }
        $stmt->close();
    }
}

$conn->close();

// 辅助：从数据库格式 (+601XXXXXXXX) 提取显示格式 (XXXXXXXX)
$displayPhone = isset($admin['Admin_ContactNumber']) ? str_replace('+601', '', $admin['Admin_ContactNumber']) : '';

// 马来西亚州属列表
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
        /* 复用 Donor Page 的设计风格 */
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-select {
            width: 100%; padding: 10px 15px; border: 1px solid var(--gray-light);
            border-radius: 5px; outline: none; font-size: 14px; transition: border-color 0.3s;
        }
        .form-input:focus, .form-select:focus { border-color: var(--primary); }
        .form-input:read-only { background-color: #f8f9fa; color: #6c757d; cursor: default; }
        .form-row { display: flex; gap: 20px; }
        .form-row .form-group { flex: 1; }

        .profile-picture-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-picture-preview {
            width: 120px; height: 120px; border-radius: 50%; border: 4px solid #f8f9fa;
            margin-bottom: 15px; background: #eee; display: flex; align-items: center;
            justify-content: center; overflow: hidden; position: relative;
        }
        .profile-picture-preview img { width: 100%; height: 100%; object-fit: cover; }
        .default-avatar-icon { font-size: 48px; color: #ccc; }

        .file-upload-label {
            display: inline-block; padding: 8px 15px; background: #fff;
            border: 1px solid var(--gray-light); border-radius: 5px; cursor: pointer;
            font-size: 13px; transition: all 0.3s; color: var(--dark);
        }
        .file-upload-label:hover { border-color: var(--primary); color: var(--primary); background: #fff5f5; }
        input[type="file"] { display: none; }

        .file-info {
            display: none; align-items: center; justify-content: center;
            gap: 10px; margin-top: 10px; background: #f1f1f1;
            padding: 5px 10px; border-radius: 5px;
        }
        .file-info.active { display: inline-flex; }
        .file-name { font-size: 12px; color: #555; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-remove { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 14px; padding: 0 5px; }

        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn { padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; font-size: 14px; transition: all 0.3s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: var(--gray-light); color: var(--dark); }
        .btn-secondary:hover { background: #dcdcdc; }

        /* 【新增】样式：Helper Text & Requirements */
        .form-guide { font-size: 11px; color: #888; margin-top: 4px; display: block; }
        .password-requirements { margin-top: 8px; font-size: 12px; background: #f9f9f9; padding: 10px; border-radius: 5px; }
        .requirement-item { display: flex; align-items: center; margin-bottom: 3px; color: #666; }
        .requirement-item.valid { color: var(--success); }
        .requirement-item.invalid { color: #aaa; }
        .requirement-item i { margin-right: 5px; font-size: 10px; }

        /* 【新增】样式：电话号码前缀组合 */
        .phone-format { display: flex; align-items: center; }
        .phone-prefix {
            padding: 10px 12px; background: #f8f9fa; border: 1px solid var(--gray-light);
            border-right: none; border-radius: 5px 0 0 5px; color: #666; font-size: 14px;
        }
        .phone-input { border-radius: 0 5px 5px 0 !important; }

        .password-container { position: relative; }
        .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #888; }
        
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @media (max-width: 768px) { .form-row { flex-direction: column; gap: 0; } }
    </style>
</head>
<body>
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
                <li><a href="reward_item_management.php"><i class="fas fa-gift"></i> <span>Reward Items</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="top-nav">
            <div class="nav-left">
                <div class="logo">
                    <a href="admin_dashboard.php">
                        <img src="logo.jpg" alt="Logo">
                        <h1>Love Bridge</h1>
                    </a>
                </div>
            </div>
            <div class="nav-right"></div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1><?php echo $editMode ? "Edit Profile" : "My Profile"; ?></h1>
                <p>Manage your account settings and preferences.</p>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

            <div class="profile-container">
                <form method="POST" enctype="multipart/form-data">
                    
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
                        <?php endif; ?>
                    </div>

                    <h3 class="section-title">Personal Information</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['Admin_Name']); ?>" 
                               <?php echo $editMode ? 'required' : 'readonly'; ?>>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_Email']); ?>" 
                                   <?php echo $editMode ? 'required' : 'readonly'; ?>>
                            <?php if ($editMode): ?>
                                <span class="form-guide">Email must include '@' and end with .com, .net, .org, .edu, .gov or .my</span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number</label>
                            <?php if ($editMode): ?>
                                <div class="phone-format">
                                    <span class="phone-prefix">+601</span>
                                    <input type="text" name="contact_number" class="form-input phone-input" 
                                           value="<?php echo htmlspecialchars($displayPhone); ?>" 
                                           required placeholder="X-XXXXXXX" maxlength="9">
                                </div>
                                <span class="form-guide">(e.g., +6012-3456789)</span>
                            <?php else: ?>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars($admin['Admin_ContactNumber']); ?>" readonly>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number</label>
                            <input type="text" name="ic_number" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_ICNUMBER']); ?>" 
                                   <?php echo $editMode ? 'required' : 'readonly'; ?>>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_DOB']); ?>" 
                                   <?php echo $editMode ? 'required' : 'readonly'; ?>>
                        </div>
                    </div>

                    <h3 class="section-title" style="margin-top: 30px;">Address Details</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" name="address1" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['Admin_Address1']); ?>" 
                               <?php echo $editMode ? 'required' : 'readonly'; ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" name="address2" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['Admin_Address2']); ?>" 
                               <?php echo $editMode ? '' : 'readonly'; ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['Admin_Address3']); ?>" 
                               <?php echo $editMode ? '' : 'readonly'; ?>>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_City']); ?>" 
                                   <?php echo $editMode ? 'required' : 'readonly'; ?>>
                        </div>
                        <div class="form-group">
                            <label class="form-label">State</label>
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
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_PostalCode']); ?>" 
                                   <?php echo $editMode ? 'required' : 'readonly'; ?>>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-input" 
                                   value="<?php echo htmlspecialchars($admin['Admin_Country']); ?>" readonly>
                        </div>
                    </div>

                    <?php if ($editMode): ?>
                        <h3 class="section-title" style="margin-top: 30px;">Change Password</h3>
                        <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Leave blank if you don't want to change password.</p>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <div class="password-container">
                                    <input type="password" name="new_password" id="new_password" class="form-input" oninput="validatePasswordRequirements()">
                                    <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-requirements">
                                    <div class="requirement-item" id="lengthReq"><i class="fas fa-circle"></i> 8-15 characters</div>
                                    <div class="requirement-item" id="upperReq"><i class="fas fa-circle"></i> One Uppercase</div>
                                    <div class="requirement-item" id="lowerReq"><i class="fas fa-circle"></i> One Lowercase</div>
                                    <div class="requirement-item" id="numReq"><i class="fas fa-circle"></i> One Number</div>
                                    <div class="requirement-item" id="specialReq"><i class="fas fa-circle"></i> Special character (!@#)</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <div class="password-container">
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-input">
                                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_profile.php'">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    <?php else: ?>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_dashboard.php'">Back to Dashboard</button>
                            <button type="button" class="btn btn-primary" onclick="window.location.href='admin_profile.php?edit=true'">Edit Profile</button>
                        </div>
                    <?php endif; ?>

                </form>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        sidebar.addEventListener('mouseenter', () => { sidebar.classList.remove('collapsed'); mainContent.classList.add('expanded'); });
        sidebar.addEventListener('mouseleave', () => { sidebar.classList.add('collapsed'); mainContent.classList.remove('expanded'); });

        const originalImageSrc = "<?php echo !empty($admin['Admin_ProfilePicture']) ? htmlspecialchars($admin['Admin_ProfilePicture']) : ''; ?>";

        function previewImage(input) {
            const container = document.getElementById('preview-container');
            const fileInfo = document.getElementById('file-info');
            const fileName = document.getElementById('file-name');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    container.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    if (fileInfo) {
                        fileInfo.style.display = 'inline-flex';
                        fileName.textContent = input.files[0].name;
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage() {
            const input = document.getElementById('profile_picture');
            const container = document.getElementById('preview-container');
            const fileInfo = document.getElementById('file-info');
            input.value = '';
            if (fileInfo) fileInfo.style.display = 'none';
            if (originalImageSrc) {
                container.innerHTML = `<img src="${originalImageSrc}" alt="Profile">`;
            } else {
                container.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            }
        }

        function togglePassword(id) {
            const input = document.getElementById(id);
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
        }

        // 密码强度验证逻辑
        function validatePasswordRequirements() {
            const pw = document.getElementById('new_password').value;
            
            // Requirements
            const len = pw.length >= 8 && pw.length <= 15;
            const up = /[A-Z]/.test(pw);
            const low = /[a-z]/.test(pw);
            const num = /\d/.test(pw);
            const spec = /[!@#$%^&*()\-_=+{};:,<.>]/.test(pw);

            updateReqItem('lengthReq', len);
            updateReqItem('upperReq', up);
            updateReqItem('lowerReq', low);
            updateReqItem('numReq', num);
            updateReqItem('specialReq', spec);
        }

        function updateReqItem(id, isValid) {
            const el = document.getElementById(id);
            if (!el) return;
            if (isValid) {
                el.classList.add('valid');
                el.classList.remove('invalid');
                el.querySelector('i').className = 'fas fa-check';
            } else {
                el.classList.remove('valid');
                el.classList.add('invalid');
                el.querySelector('i').className = 'fas fa-circle';
            }
        }
    </script>
</body>
</html>
