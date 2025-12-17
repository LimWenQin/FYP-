<?php
include 'dataconnection.php';
include 'header_function.php';

// 检查是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: donor_login.php");
    exit();
}

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // 验证新密码
    if (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } else {
        // 验证当前密码
        $password_query = "SELECT Donor_Password FROM donor WHERE Donor_ID = ?";
        $stmt = $conn->prepare($password_query);
        $stmt->bind_param("i", $_SESSION['donor_id']);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();
        
        if (password_verify($current_password, $hashed_password)) {
            // 更新密码
            $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $update_password_query = "UPDATE donor SET Donor_Password = ? WHERE Donor_ID = ?";
            $stmt = $conn->prepare($update_password_query);
            $stmt->bind_param("si", $new_hashed_password, $_SESSION['donor_id']);
            
            if ($stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Error updating password. Please try again.";
            }
            $stmt->close();
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
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
            max-width: 600px;
            margin: 0 auto;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 32px;
            color: var(--dark-red);
            font-weight: bold;
        }
        
        .password-form-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 40px;
            border-top: 4px solid var(--primary-red);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px var(--light-red);
        }
        
        .form-group input.error {
            border-color: var(--error-red);
        }
        
        .password-strength {
            height: 5px;
            background: var(--light-gray);
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 3px;
        }
        
        .strength-weak {
            background-color: var(--primary-red);
            width: 25%;
        }
        
        .strength-medium {
            background-color: #f59e0b;
            width: 50%;
        }
        
        .strength-strong {
            background-color: var(--success-green);
            width: 75%;
        }
        
        .strength-very-strong {
            background-color: #059669;
            width: 100%;
        }

        .password-requirements {
            background-color: var(--light-gray);
            border-radius: 6px;
            padding: 15px;
            margin-top: 10px;
            border: 1px solid var(--border-color);
        }

        .requirement-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: var(--medium-gray);
        }

        .requirement-item:last-child {
            margin-bottom: 0;
        }

        .requirement-item i {
            margin-right: 8px;
            font-size: 0.8rem;
            width: 16px;
        }

        .requirement-item.valid {
            color: var(--success-green);
        }

        .requirement-item.valid i {
            color: var(--success-green);
        }

        .requirement-item.invalid {
            color: var(--medium-gray);
        }

        .requirement-item.invalid i {
            color: var(--medium-gray);
        }

        .error-message {
            background-color: var(--light-red);
            color: var(--dark-red);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-red);
            font-weight: 500;
        }

        .success-message {
            background-color: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid var(--success-green);
            font-weight: 500;
        }
        
        .form-help {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-top: 5px;
            display: block;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            font-size: 16px;
            width: 100%;
            margin-top: 10px;
        }
        
        .submit-btn:hover {
            background: linear-gradient(135deg, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }
        
        .submit-btn:disabled {
            background: var(--border-color);
            color: var(--medium-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .back-btn {
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
            margin-top: 15px;
            width: 100%;
        }
        
        .back-btn:hover {
            background: linear-gradient(135deg, var(--dark-gray), var(--medium-gray));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(115, 115, 115, 0.3);
            text-decoration: none;
            color: white;
        }
        
        .match-error {
            color: var(--error-red);
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
        }
        
        .match-error.show {
            display: block;
        }
        
        .match-success {
            color: var(--success-green);
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
        }
        
        .match-success.show {
            display: block;
        }
        
        @media (max-width: 768px) {
            .page-container {
                padding: 15px;
            }
            
            .password-form-container {
                padding: 25px;
            }
            
            .page-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <h1 class="page-title">Change Password</h1>
        
        <div class="password-form-container">
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="change_password.php" id="passwordForm">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
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
                    <input type="password" id="confirm_password" name="confirm_password" required>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.getElementById('passwordForm');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            const submitBtn = document.getElementById('submitBtn');
            const passwordMatchError = document.getElementById('passwordMatchError');
            const passwordMatchSuccess = document.getElementById('passwordMatchSuccess');
            
            // 密码要求元素
            const lengthReq = document.getElementById('lengthReq');
            const uppercaseReq = document.getElementById('uppercaseReq');
            const lowercaseReq = document.getElementById('lowercaseReq');
            const numberReq = document.getElementById('numberReq');
            const specialReq = document.getElementById('specialReq');
            
            // 初始化按钮状态
            submitBtn.disabled = true;
            
            // 更新密码要求指示器
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
            
            // 检查所有密码要求是否满足
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
            
            // 检查密码是否匹配
            function checkPasswordMatch() {
                const newPasswordValue = newPassword.value;
                const confirmPasswordValue = confirmPassword.value;
                
                if (confirmPasswordValue === '') {
                    // 如果确认密码为空，隐藏所有提示
                    passwordMatchError.classList.remove('show');
                    passwordMatchSuccess.classList.remove('show');
                    confirmPassword.classList.remove('error');
                    return false;
                }
                
                if (newPasswordValue === confirmPasswordValue) {
                    // 密码匹配
                    passwordMatchError.classList.remove('show');
                    passwordMatchSuccess.classList.add('show');
                    confirmPassword.classList.remove('error');
                    return true;
                } else {
                    // 密码不匹配
                    passwordMatchError.classList.add('show');
                    passwordMatchSuccess.classList.remove('show');
                    confirmPassword.classList.add('error');
                    return false;
                }
            }
            
            // 更新提交按钮状态
            function updateSubmitButton() {
                const requirementsMet = checkAllRequirements();
                const passwordsMatch = checkPasswordMatch();
                
                if (requirementsMet && passwordsMatch && newPassword.value !== '') {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }
            
            // 密码强度检查
            newPassword.addEventListener('input', function() {
                const value = newPassword.value;
                let strength = 0;
                
                // Check requirements
                const hasLength = value.length >= 8 && value.length <= 15;
                const hasUppercase = /[A-Z]/.test(value);
                const hasLowercase = /[a-z]/.test(value);
                const hasNumber = /[0-9]/.test(value);
                const hasSpecial = /[^A-Za-z0-9]/.test(value);
                
                // 更新要求指示器
                updateRequirement(lengthReq, hasLength);
                updateRequirement(uppercaseReq, hasUppercase);
                updateRequirement(lowercaseReq, hasLowercase);
                updateRequirement(numberReq, hasNumber);
                updateRequirement(specialReq, hasSpecial);
                
                // Calculate strength
                if (hasLength) strength++;
                if (hasUppercase) strength++;
                if (hasLowercase) strength++;
                if (hasNumber) strength++;
                if (hasSpecial) strength++;
                
                // Reset classes
                passwordStrengthBar.className = 'password-strength-bar';
                
                // Set strength level
                if (strength <= 1) {
                    passwordStrengthBar.classList.add('strength-weak');
                } else if (strength === 2) {
                    passwordStrengthBar.classList.add('strength-medium');
                } else if (strength === 3 || strength === 4) {
                    passwordStrengthBar.classList.add('strength-strong');
                } else if (strength >= 5) {
                    passwordStrengthBar.classList.add('strength-very-strong');
                }
                
                // 更新提交按钮状态
                updateSubmitButton();
                
                // 如果确认密码已有值，重新检查匹配
                if (confirmPassword.value !== '') {
                    checkPasswordMatch();
                }
            });
            
            // 确认密码输入监听
            confirmPassword.addEventListener('input', function() {
                checkPasswordMatch();
                updateSubmitButton();
            });
            
            // 表单提交验证
            passwordForm.addEventListener('submit', function(e) {
                const newPasswordValue = newPassword.value;
                const confirmPasswordValue = confirmPassword.value;
                const currentPasswordValue = document.getElementById('current_password').value;
                
                // 检查当前密码是否为空
                if (currentPasswordValue === '') {
                    alert('Please enter your current password.');
                    e.preventDefault();
                    return false;
                }
                
                // 检查所有密码要求
                if (!checkAllRequirements()) {
                    alert('Please ensure all password requirements are met.');
                    e.preventDefault();
                    return false;
                }
                
                // 检查密码是否匹配
                if (newPasswordValue !== confirmPasswordValue) {
                    alert('New passwords do not match.');
                    e.preventDefault();
                    return false;
                }
            });
            
            // 页面加载时检查初始状态
            updateSubmitButton();
        });
    </script>
</body>
</html>