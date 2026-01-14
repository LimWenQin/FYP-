<?php
// admin_reset_password.php
session_start();
include 'dataconnection.php';

$alertType = "";
$alertMsg = "";
$isValidRequest = true; // 标记请求是否有效

// 使用 null coalescing operator 防止 undefined index warning
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// 1. 验证 URL 参数是否存在
if (empty($token) || empty($email)) {
    $isValidRequest = false;
    $alertType = "error";
    $alertMsg = "Invalid request. Missing token or email.";
} else {
    // 2. 检查 Token 是否在数据库中有效且未过期
    $sql = "SELECT * FROM password_resets WHERE token='$token' AND email='$email' AND expires_at > NOW()";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) == 0) {
        $isValidRequest = false;
        $alertType = "error";
        $alertMsg = "Invalid or expired token. Please request a new link.";
    }
}

// 3. 处理表单提交 (更新密码)
if ($isValidRequest && isset($_POST['update_password'])) {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    // 后端验证密码复杂度
    $uppercase = preg_match('@[A-Z]@', $pass1);
    $lowercase = preg_match('@[a-z]@', $pass1);
    $number    = preg_match('@[0-9]@', $pass1);
    $special   = preg_match('@[^\w]@', $pass1); 
    $length    = strlen($pass1);

    if(!$uppercase || !$lowercase || !$number || !$special || $length < 8 || $length > 15) {
        $alertType = "error";
        $alertMsg = "Password does not meet the requirements.";
    } elseif ($pass1 !== $pass2) {
        $alertType = "error";
        $alertMsg = "Passwords do not match.";
    } else {
        // --- 关键修改：检查新密码是否与旧密码相同 ---
        $check_old_sql = "SELECT Admin_Password FROM admin WHERE Admin_Email = '$email'";
        $old_res = mysqli_query($conn, $check_old_sql);
        
        if ($old_res && mysqli_num_rows($old_res) > 0) {
            $old_row = mysqli_fetch_assoc($old_res);
            $current_hash = $old_row['Admin_Password'];
            
            // 使用 password_verify 比对新密码和数据库中的旧Hash
            if (password_verify($pass1, $current_hash)) {
                $alertType = "error";
                $alertMsg = "New password cannot be the same as your current password.";
            } else {
                // 如果不一样，才进行更新
                $new_pass = password_hash($pass1, PASSWORD_DEFAULT);

                $update_sql = "UPDATE admin SET Admin_Password='$new_pass' WHERE Admin_Email='$email'";
                
                if(mysqli_query($conn, $update_sql)) {
                    // 更新成功后，删除 Token
                    mysqli_query($conn, "DELETE FROM password_resets WHERE email='$email'");
                    
                    $alertType = "success";
                    $alertMsg = "Password updated successfully! Redirecting to login...";
                    
                    // 为了让用户看到成功提示，延迟跳转
                    echo "<script>setTimeout(function(){ window.location.href = 'admin_login.php'; }, 3000);</script>";
                    // 防止表单再次显示
                    $isValidRequest = false; 
                } else {
                    $alertType = "error";
                    $alertMsg = "Database error: " . mysqli_error($conn);
                }
            }
        } else {
            $alertType = "error";
            $alertMsg = "User not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Love Bridge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            font-family: 'Segoe UI', sans-serif; 
            margin: 0;
            padding: 20px;
        }
        .container { 
            background: white; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
            width: 100%;
            max-width: 450px; 
        }
        h2 { 
            color: #333; 
            margin-bottom: 25px; 
            text-align: center; 
            font-weight: 700;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        input { 
            width: 100%; 
            padding: 14px 45px 14px 15px; 
            border: 2px solid #e1e1e1; 
            border-radius: 10px; 
            outline: none; 
            box-sizing: border-box; 
            font-size: 15px;
            transition: border-color 0.3s;
        }
        input:focus {
            border-color: #2563EB;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
            font-size: 16px;
        }
        .toggle-password:hover {
            color: #2563EB;
        }

        button { 
            width: 100%; 
            padding: 14px; 
            background: #2563EB; 
            color: white; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 16px;
            transition: background 0.3s;
            margin-top: 10px;
        }
        button:hover {
            background: #1d4ed8;
        }

        .requirements-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        .requirements-title {
            font-size: 13px;
            font-weight: bold;
            color: #555;
            margin-bottom: 8px;
            display: block;
        }
        .requirement-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .requirement-list li {
            font-size: 13px;
            margin-bottom: 5px;
            color: #dc3545; 
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }
        .requirement-list li i {
            margin-right: 8px;
            width: 15px;
            text-align: center;
        }
        .requirement-list li.valid {
            color: #198754; 
        }

        /* Floating Alert Styles */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; animation: slideIn 0.3s; }
        .floating-alert div { line-height: 1.6; font-size: 14px; }
        .floating-alert i { margin-top: 4px; }
        .floating-alert-success { background: white; color: #28a745; border-left: 4px solid #28a745; }
        .floating-alert-danger { background: white; color: #dc3545; border-left: 4px solid #dc3545; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .btn-login {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563EB;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-login:hover { text-decoration: underline; }
        
        .error-placeholder {
            text-align: center;
            padding: 20px;
            color: #555;
        }
    </style>
</head>
<body>
    
    <?php if ($alertMsg != ""): ?>
    <div class="floating-alert <?php echo ($alertType == 'success') ? 'floating-alert-success' : 'floating-alert-danger'; ?>" id="systemAlert">
        <i class="fas <?php echo ($alertType == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <div><?php echo $alertMsg; ?></div>
    </div>
    <script>
        // Auto-hide alert after 5 seconds if not a critical request error
        <?php if($isValidRequest || $alertType == 'success'): ?>
        setTimeout(() => { 
            const alert = document.getElementById('systemAlert'); 
            if(alert) alert.style.display = 'none'; 
        }, 5000);
        <?php endif; ?>
    </script>
    <?php endif; ?>

    <div class="container">
        <h2>Reset Password</h2>
        
        <?php if($isValidRequest): ?>
        <form method="POST" id="resetForm">
            
            <div class="input-group">
                <input type="password" name="pass1" id="pass1" placeholder="New Password" required>
                <i class="fa fa-eye toggle-password" onclick="togglePassword('pass1', this)"></i>
            </div>

            <div class="requirements-box">
                <span class="requirements-title">Password must contain:</span>
                <ul class="requirement-list">
                    <li id="req-length"><i class="fas fa-times"></i> Between 8 and 15 characters</li>
                    <li id="req-upper"><i class="fas fa-times"></i> At least one uppercase letter (A-Z)</li>
                    <li id="req-lower"><i class="fas fa-times"></i> At least one lowercase letter (a-z)</li>
                    <li id="req-number"><i class="fas fa-times"></i> At least one number (0-9)</li>
                    <li id="req-special"><i class="fas fa-times"></i> At least one special character (!@#$%^&*)</li>
                </ul>
            </div>

            <div class="input-group">
                <input type="password" name="pass2" id="pass2" placeholder="Confirm Password" required>
                <i class="fa fa-eye toggle-password" onclick="togglePassword('pass2', this)"></i>
            </div>
            
            <div id="match-message" style="font-size:13px; margin-bottom:15px; display:none;"></div>

            <button type="submit" name="update_password" id="submitBtn">Update Password</button>
        </form>
        <?php else: ?>
            <div class="error-placeholder">
                <i class="fas fa-link-slash" style="font-size: 40px; color: #dc3545; margin-bottom: 15px;"></i>
                <p>Wait... Something went wrong with your request.</p>
                <a href="admin_forgot_password.php" class="btn-login">Request New Link</a>
                <a href="admin_login.php" class="btn-login" style="margin-top:10px; color:#666;">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // 切换密码显示/隐藏
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        const pass1 = document.getElementById('pass1');
        const pass2 = document.getElementById('pass2');
        const submitBtn = document.getElementById('submitBtn');
        const matchMsg = document.getElementById('match-message');

        // 获取条件元素
        const reqLength = document.getElementById('req-length');
        const reqUpper = document.getElementById('req-upper');
        const reqLower = document.getElementById('req-lower');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');

        function updateRequirement(element, isValid) {
            const icon = element.querySelector('i');
            if (isValid) {
                element.classList.add('valid');
                element.classList.remove('invalid');
                icon.className = 'fas fa-check';
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                icon.className = 'fas fa-times';
            }
        }

        function validatePassword() {
            if(!pass1) return false;
            const val = pass1.value;
            let allValid = true;

            // 1. 长度检查 (8-15)
            if (val.length >= 8 && val.length <= 15) {
                updateRequirement(reqLength, true);
            } else {
                updateRequirement(reqLength, false);
                allValid = false;
            }

            // 2. 大写字母
            if (/[A-Z]/.test(val)) {
                updateRequirement(reqUpper, true);
            } else {
                updateRequirement(reqUpper, false);
                allValid = false;
            }

            // 3. 小写字母
            if (/[a-z]/.test(val)) {
                updateRequirement(reqLower, true);
            } else {
                updateRequirement(reqLower, false);
                allValid = false;
            }

            // 4. 数字
            if (/[0-9]/.test(val)) {
                updateRequirement(reqNumber, true);
            } else {
                updateRequirement(reqNumber, false);
                allValid = false;
            }

            // 5. 特殊符号 (包括标点符号)
            if (/[^A-Za-z0-9]/.test(val)) {
                updateRequirement(reqSpecial, true);
            } else {
                updateRequirement(reqSpecial, false);
                allValid = false;
            }
            
            return allValid;
        }

        function checkMatch() {
            if(!pass2) return false;
            if (pass2.value === "") {
                matchMsg.style.display = 'none';
                return false;
            }
            if (pass1.value === pass2.value) {
                matchMsg.style.display = 'block';
                matchMsg.style.color = '#198754';
                matchMsg.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
                return true;
            } else {
                matchMsg.style.display = 'block';
                matchMsg.style.color = '#dc3545';
                matchMsg.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
                return false;
            }
        }

        // 监听输入事件
        if(pass1) {
            pass1.addEventListener('input', () => {
                validatePassword();
                if(pass2.value !== "") checkMatch();
            });

            pass2.addEventListener('input', checkMatch);

            // 提交前拦截
            document.getElementById('resetForm').addEventListener('submit', function(e) {
                const isFormatValid = validatePassword();
                const isMatch = pass1.value === pass2.value;

                if (!isFormatValid || !isMatch) {
                    e.preventDefault(); // 阻止提交
                    if(!isMatch) alert("Passwords do not match.");
                    else alert("Please fulfill all password requirements.");
                }
            });
        }
    </script>
</body>
</html>