<?php
// admin_reset_password.php
session_start();
include 'dataconnection.php';

$msg = "";
// 使用 null coalescing operator 防止 undefined index warning
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// 1. 验证 URL 参数是否存在
if (empty($token) || empty($email)) {
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>Invalid request. Missing token or email.</div>");
}

// 2. 检查 Token 是否在数据库中有效且未过期
$sql = "SELECT * FROM password_resets WHERE token='$token' AND email='$email' AND expires_at > NOW()";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 0) {
    // 如果找不到记录或者已经过期
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>Invalid or expired token. <a href='admin_forgot_password.php'>Try again</a></div>");
}

// 3. 处理表单提交 (更新密码)
if (isset($_POST['update_password'])) {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    // 后端再次验证密码复杂度 (防止绕过前端)
    $uppercase = preg_match('@[A-Z]@', $pass1);
    $lowercase = preg_match('@[a-z]@', $pass1);
    $number    = preg_match('@[0-9]@', $pass1);
    $special   = preg_match('@[^\w]@', $pass1); // 检查非单词字符 (即符号)
    $length    = strlen($pass1);

    if(!$uppercase || !$lowercase || !$number || !$special || $length < 8 || $length > 15) {
        $msg = "<div class='error'>Password does not meet the requirements.</div>";
    } elseif ($pass1 !== $pass2) {
        $msg = "<div class='error'>Passwords do not match.</div>";
    } else {
        // --- 密码加密 ---
        // 根据你的 SQL 文件，密码是加密存储的。这里使用 password_hash。
        // 如果你坚持要明文，请把下面这行改成: $new_pass = $pass1;
        $new_pass = password_hash($pass1, PASSWORD_DEFAULT);

        // 更新 admin 表中的密码
        $update_sql = "UPDATE admin SET Admin_Password='$new_pass' WHERE Admin_Email='$email'";
        
        if(mysqli_query($conn, $update_sql)) {
            // 更新成功后，删除 Token (确保一次性使用)
            mysqli_query($conn, "DELETE FROM password_resets WHERE email='$email'");
            
            $msg = "<div class='success'>Password updated successfully! <br><a href='admin_login.php' class='btn-login'>Login Now</a></div>";
        } else {
            $msg = "<div class='error'>Database error: " . mysqli_error($conn) . "</div>";
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
        
        /* 输入框容器，用于定位眼睛图标 */
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        input { 
            width: 100%; 
            padding: 14px 45px 14px 15px; /* 右侧留出空间给眼睛图标 */
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

        /* 眼睛图标样式 */
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
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* 验证条件列表样式 */
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
            color: #dc3545; /* 默认红色 */
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }
        .requirement-list li i {
            margin-right: 8px;
            width: 15px;
            text-align: center;
        }
        
        /* 验证通过的样式 */
        .requirement-list li.valid {
            color: #198754; /* 绿色 */
        }

        .success { 
            color: #155724; 
            background-color: #d4edda; 
            border-color: #c3e6cb; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            text-align: center;
        }
        .error { 
            color: #721c24; 
            background-color: #f8d7da; 
            border-color: #f5c6cb; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            text-align: center;
        }
        .btn-login {
            display: inline-block;
            margin-top: 10px;
            color: #155724;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        
        <?php echo $msg; ?>
        
        <?php if(strpos($msg, 'successfully') === false): ?>
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