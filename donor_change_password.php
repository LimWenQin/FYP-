<?php
include 'dataconnection.php';
include 'header_function.php';

// 检查是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // 1. 获取数据库中当前的密码哈希
    $query = "SELECT Donor_Password FROM donor WHERE Donor_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['donor_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $db_password_hash = $row['Donor_Password'];
        
        // 2. 验证用户输入的 "Current Password" 是否匹配数据库
        if (password_verify($current_password, $db_password_hash)) {
            
            // 3. 验证新密码规则
            if ($current_password == $new_password) {
                // 【新增】检查新旧密码是否相同
                $error_message = "New password cannot be the same as your current password.";
            } elseif (strlen($new_password) < 8 || strlen($new_password) > 15) {
                $error_message = "New password must be between 8 and 15 characters.";
            } elseif (!preg_match("/[A-Z]/", $new_password)) {
                $error_message = "Password must contain at least one uppercase letter.";
            } elseif (!preg_match("/[a-z]/", $new_password)) {
                $error_message = "Password must contain at least one lowercase letter.";
            } elseif (!preg_match("/[0-9]/", $new_password)) {
                $error_message = "Password must contain at least one number.";
            } elseif (!preg_match("/[^A-Za-z0-9]/", $new_password)) {
                $error_message = "Password must contain at least one special character.";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match.";
            } else {
                // 4. 一切正常，加密新密码并更新数据库
                $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                
                $update_query = "UPDATE donor SET Donor_Password = ? WHERE Donor_ID = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("si", $new_hashed_password, $_SESSION['donor_id']);
                
                if ($update_stmt->execute()) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Database error: Could not update password.";
                }
                $update_stmt->close();
            }
            
        } else {
            // 当前密码错误
            $error_message = "Current password is incorrect.";
        }
    } else {
        $error_message = "User account not found.";
    }
    $stmt->close();
}

include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Love Bridge</title>
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arial', sans-serif; }
        
        body { background-color: var(--light-gray); color: var(--text-dark); line-height: 1.6; }
        
        .page-container { padding: 30px; max-width: 600px; margin: 0 auto; }
        
        .page-title { text-align: center; margin-bottom: 30px; font-size: 32px; color: var(--dark-red); font-weight: bold; }
        
        .password-form-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 40px;
            border-top: 4px solid var(--primary-red);
        }
        
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dark-gray); }
        
        .form-group input {
            width: 100%; padding: 12px 15px; border: 2px solid var(--border-color);
            border-radius: 6px; font-size: 16px; transition: all 0.3s ease;
        }
        
        .form-group input:focus { outline: none; border-color: var(--primary-red); box-shadow: 0 0 0 3px var(--light-red); }
        .form-group input.error { border-color: var(--error-red); }
        
        /* Password Strength Bars */
        .password-strength { height: 5px; background: var(--light-gray); border-radius: 3px; margin-top: 5px; overflow: hidden; }
        .password-strength-bar { height: 100%; width: 0%; transition: width 0.3s ease; border-radius: 3px; }
        .strength-weak { background-color: var(--primary-red); width: 25%; }
        .strength-medium { background-color: #f59e0b; width: 50%; }
        .strength-strong { background-color: var(--success-green); width: 75%; }
        .strength-very-strong { background-color: #059669; width: 100%; }

        /* Requirements List */
        .password-requirements {
            background-color: var(--light-gray); border-radius: 6px; padding: 15px;
            margin-top: 10px; border: 1px solid var(--border-color);
        }
        .requirement-item { display: flex; align-items: center; margin-bottom: 8px; font-size: 0.85rem; color: var(--medium-gray); }
        .requirement-item i { margin-right: 8px; font-size: 0.8rem; width: 16px; }
        .requirement-item.valid { color: var(--success-green); }
        .requirement-item.valid i { color: var(--success-green); }
        .requirement-item.invalid { color: var(--medium-gray); }
        
        /* Messages */
        .error-message { background-color: var(--light-red); color: var(--dark-red); padding: 15px; border-radius: 6px; margin-bottom: 25px; border-left: 4px solid var(--primary-red); font-weight: 500; }
        .success-message { background-color: #d1fae5; color: #065f46; padding: 15px; border-radius: 6px; margin-bottom: 25px; border-left: 4px solid var(--success-green); font-weight: 500; }
        
        /* Buttons */
        .submit-btn {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white; border: none; padding: 14px 30px; border-radius: 6px;
            cursor: pointer; font-weight: bold; font-size: 16px; width: 100%; margin-top: 10px; transition: all 0.3s ease;
        }
        .submit-btn:hover { background: linear-gradient(135deg, var(--dark-red), var(--primary-red)); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3); }
        .submit-btn:disabled { background: var(--border-color); color: var(--medium-gray); cursor: not-allowed; transform: none; box-shadow: none; }
        
        .back-btn {
            background: linear-gradient(135deg, var(--medium-gray), var(--dark-gray));
            color: white; border: none; padding: 12px 25px; border-radius: 6px;
            cursor: pointer; font-weight: bold; font-size: 16px; text-decoration: none;
            display: inline-block; text-align: center; margin-top: 15px; width: 100%; transition: all 0.3s ease;
        }
        .back-btn:hover { background: linear-gradient(135deg, var(--dark-gray), var(--medium-gray)); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(115, 115, 115, 0.3); color: white; }
        
        /* Validation Text */
        .match-error { color: var(--error-red); font-size: 0.85rem; margin-top: 5px; display: none; }
        .match-error.show { display: block; }
        .match-success { color: var(--success-green); font-size: 0.85rem; margin-top: 5px; display: none; }
        .match-success.show { display: block; }
        
        @media (max-width: 768px) {
            .page-container { padding: 15px; }
            .password-form-container { padding: 25px; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <h1 class="page-title">Change Password</h1>
        
        <div class="password-form-container">
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="passwordForm">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required placeholder="Enter your current password">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password">
                    
                    <div class="match-error" id="samePasswordError">
                        <i class="fas fa-exclamation-circle"></i> New password cannot be the same as current password
                    </div>

                    <div class="password-strength">
                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                    </div>
                    <div class="password-requirements">
                        <div class="requirement-item invalid" id="lengthReq">
                            <i class="fas fa-times"></i> Must be 8-15 characters long
                        </div>
                        <div class="requirement-item invalid" id="uppercaseReq">
                            <i class="fas fa-times"></i> Must contain at least one Uppercase letter
                        </div>
                        <div class="requirement-item invalid" id="lowercaseReq">
                            <i class="fas fa-times"></i> Must contain at least one Lowercase letter
                        </div>
                        <div class="requirement-item invalid" id="numberReq">
                            <i class="fas fa-times"></i> Must contain at least one Number
                        </div>
                        <div class="requirement-item invalid" id="specialReq">
                            <i class="fas fa-times"></i> Must contain at least one Special character (e.g. !@#)
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter new password">
                    <div class="match-error" id="passwordMatchError">
                        <i class="fas fa-exclamation-circle"></i> Passwords do not match
                    </div>
                    <div class="match-success" id="passwordMatchSuccess">
                        <i class="fas fa-check-circle"></i> Passwords match
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">Change Password</button>
                <a href="profile.php" class="back-btn">Back to Profile</a>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.getElementById('passwordForm');
            const currentPassword = document.getElementById('current_password'); // 【新增】
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            const submitBtn = document.getElementById('submitBtn');
            const passwordMatchError = document.getElementById('passwordMatchError');
            const passwordMatchSuccess = document.getElementById('passwordMatchSuccess');
            const samePasswordError = document.getElementById('samePasswordError'); // 【新增】
            
            // Requirements Elements
            const lengthReq = document.getElementById('lengthReq');
            const uppercaseReq = document.getElementById('uppercaseReq');
            const lowercaseReq = document.getElementById('lowercaseReq');
            const numberReq = document.getElementById('numberReq');
            const specialReq = document.getElementById('specialReq');
            
            // Init Button State
            submitBtn.disabled = true;
            
            function updateRequirement(element, isValid) {
                if (isValid) {
                    element.classList.remove('invalid');
                    element.classList.add('valid');
                    element.querySelector('i').className = 'fas fa-check';
                } else {
                    element.classList.remove('valid');
                    element.classList.add('invalid');
                    element.querySelector('i').className = 'fas fa-times';
                }
            }
            
            function checkAllRequirements() {
                const value = newPassword.value;
                const hasLength = value.length >= 8 && value.length <= 15;
                const hasUppercase = /[A-Z]/.test(value);
                const hasLowercase = /[a-z]/.test(value);
                const hasNumber = /[0-9]/.test(value);
                const hasSpecial = /[^A-Za-z0-9]/.test(value);
                
                updateRequirement(lengthReq, hasLength);
                updateRequirement(uppercaseReq, hasUppercase);
                updateRequirement(lowercaseReq, hasLowercase);
                updateRequirement(numberReq, hasNumber);
                updateRequirement(specialReq, hasSpecial);
                
                return hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;
            }
            
            // 【新增】检查新密码是否与旧密码相同
            function checkSamePassword() {
                const currentVal = currentPassword.value;
                const newVal = newPassword.value;

                if (currentVal !== '' && newVal !== '' && currentVal === newVal) {
                    samePasswordError.classList.add('show');
                    return true; // 是相同的（有错误）
                } else {
                    samePasswordError.classList.remove('show');
                    return false; // 不相同（没有错误）
                }
            }
            
            function checkPasswordMatch() {
                const newPasswordValue = newPassword.value;
                const confirmPasswordValue = confirmPassword.value;
                
                if (confirmPasswordValue === '') {
                    passwordMatchError.classList.remove('show');
                    passwordMatchSuccess.classList.remove('show');
                    confirmPassword.classList.remove('error');
                    return false;
                }
                
                if (newPasswordValue === confirmPasswordValue) {
                    passwordMatchError.classList.remove('show');
                    passwordMatchSuccess.classList.add('show');
                    confirmPassword.classList.remove('error');
                    return true;
                } else {
                    passwordMatchError.classList.add('show');
                    passwordMatchSuccess.classList.remove('show');
                    confirmPassword.classList.add('error');
                    return false;
                }
            }
            
            function updateSubmitButton() {
                const requirementsMet = checkAllRequirements();
                const passwordsMatch = checkPasswordMatch();
                const isSamePassword = checkSamePassword(); // 【新增】
                
                // 只有当：要求满足 + 确认密码匹配 + 新密码不为空 + 新旧密码不相同 时，按钮才可用
                if (requirementsMet && passwordsMatch && newPassword.value !== '' && !isSamePassword) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }
            
            // 【新增】监听 Current Password 输入
            currentPassword.addEventListener('input', function() {
                checkSamePassword();
                updateSubmitButton();
            });

            // Strength Checker & Same Password Checker
            newPassword.addEventListener('input', function() {
                const value = newPassword.value;
                let strength = 0;
                
                const hasLength = value.length >= 8 && value.length <= 15;
                const hasUppercase = /[A-Z]/.test(value);
                const hasLowercase = /[a-z]/.test(value);
                const hasNumber = /[0-9]/.test(value);
                const hasSpecial = /[^A-Za-z0-9]/.test(value);
                
                updateRequirement(lengthReq, hasLength);
                updateRequirement(uppercaseReq, hasUppercase);
                updateRequirement(lowercaseReq, hasLowercase);
                updateRequirement(numberReq, hasNumber);
                updateRequirement(specialReq, hasSpecial);
                
                if (hasLength) strength++;
                if (hasUppercase) strength++;
                if (hasLowercase) strength++;
                if (hasNumber) strength++;
                if (hasSpecial) strength++;
                
                passwordStrengthBar.className = 'password-strength-bar';
                if (strength <= 1) passwordStrengthBar.classList.add('strength-weak');
                else if (strength === 2) passwordStrengthBar.classList.add('strength-medium');
                else if (strength === 3 || strength === 4) passwordStrengthBar.classList.add('strength-strong');
                else if (strength >= 5) passwordStrengthBar.classList.add('strength-very-strong');
                
                checkSamePassword(); // 【新增】检查是否相同
                updateSubmitButton();
                if (confirmPassword.value !== '') checkPasswordMatch();
            });
            
            confirmPassword.addEventListener('input', function() {
                checkPasswordMatch();
                updateSubmitButton();
            });
            
            passwordForm.addEventListener('submit', function(e) {
                const currentPasswordValue = document.getElementById('current_password').value;
                
                if (currentPasswordValue === '') {
                    alert('Please enter your current password.');
                    e.preventDefault();
                    return false;
                }

                // 【新增】提交前再次检查
                if (currentPasswordValue === newPassword.value) {
                    alert('New password cannot be the same as your current password.');
                    e.preventDefault();
                    return false;
                }
                
                if (!checkAllRequirements()) {
                    alert('Please ensure all password requirements are met.');
                    e.preventDefault();
                    return false;
                }
                
                if (newPassword.value !== confirmPassword.value) {
                    alert('New passwords do not match.');
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>