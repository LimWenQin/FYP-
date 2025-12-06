<?php
include 'dataconnection.php';
// Assuming header_function.php is still needed to start the session, etc.
include 'header_function.php'; 

$error_message = ""; // 通用错误信息（仅用于空字段或数据库连接错误）
$email_error = ""; // 邮箱验证错误信息
$password_error = ""; // 密码验证错误信息
$email_value = ""; // 用于存储在表单中回填的邮箱值

// Start session if not already started by header_function.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']); // 检查是否勾选了 Remember me
    
    // -----------------------------------------------------------------
    // 1. 检查是否为空字段 (通用错误)
    // -----------------------------------------------------------------
    if (empty($email) || empty($password)) {
        // 确保空字段时，即使没有通用错误信息，也会触发字段级别的错误样式
        if (empty($email)) {
            $email_error = "Please enter your email address.";
        }
        if (empty($password)) {
            $password_error = "Please enter your password.";
        }
        $error_message = "Please enter both email and password."; // 保留通用信息
        
        // 登录失败，根据 remember me 决定是否保留 email
        if ($remember && !empty($email)) {
            $email_value = htmlspecialchars($email);
        } else {
            $email_value = "";
        }
    } else {
        // -----------------------------------------------------------------
        // 2. 执行登录验证 (细化错误)
        // -----------------------------------------------------------------
        
        if (!isset($conn)) {
             $error_message = "Database connection error.";
             if ($remember) {
                $email_value = htmlspecialchars($email);
             }
        } else {
            $query = "SELECT Donor_ID, Donor_Name, Donor_Password FROM donor WHERE Donor_Email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['Donor_Password'])) {
                    // 登录成功
                    $_SESSION['donor_id'] = $user['Donor_ID'];
                    $_SESSION['donor_name'] = $user['Donor_Name'];
                    $_SESSION['logged_in'] = true;
                    
                    header("Location: homepage.php");
                    exit();
                } else {
                    // 密码错误
                    // *** 关键修改: 明确只显示密码错误信息 ***
                    $password_error = "Incorrect password."; 
                    
                    // 登录失败，根据 remember me 决定是否保留 email
                    if ($remember) {
                        $email_value = htmlspecialchars($email);
                    } else {
                        $email_value = "";
                    }
                }
            } else {
                // 邮箱未找到
                // *** 关键修改: 明确只显示邮箱错误信息 ***
                $email_error = "Email address not found.";
                $email_value = ""; // 邮箱未找到，强制清除回填值
            }
            $stmt->close();
        }
    }
}
// 登录成功后，header() 已经执行，否则继续显示 UI
include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Login - Love Bridge</title>
    <style>
        /* CSS 样式保持不变，以保持页面风格 */
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
            --error-red: #ef4444;
            --success-green: #10b981;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-gray);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 200px);
            padding: 40px 20px;
        }

        .form-container {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 50px;
            width: 100%;
            max-width: 450px;
            border-top: 6px solid var(--primary-red);
        }

        .form-title {
            color: var(--primary-red);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-align: center;
        }

        .form-subtitle {
            color: var(--medium-gray);
            text-align: center;
            margin-bottom: 30px;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-gray);
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control.error {
            border-color: var(--error-red);
            background-color: var(--light-red);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px var(--light-red);
        }

        .form-control:focus.error {
            border-color: var(--error-red);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
        }

        .error-message {
            background-color: var(--light-red);
            color: var(--dark-red);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid var(--error-red);
            font-weight: 500;
        }

        .field-error {
            color: var(--error-red);
            font-size: 0.85rem;
            margin-top: 5px;
            display: block;
            font-weight: 500;
        }

        .btn {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            padding: 16px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        .btn:hover {
            background: linear-gradient(135deg, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .form-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--medium-gray);
        }

        .form-footer a {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .form-footer p {
            margin: 10px 0;
        }
        
        .love-bridge-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .love-bridge-icon span {
            font-size: 3rem;
            color: var(--primary-red);
            display: inline-block;
            animation: heartbeat 1.5s infinite;
        }

        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .remember-me input[type="checkbox"] {
            accent-color: var(--primary-red);
        }

        .forgot-password {
            font-size: 0.9rem;
        }

        .success-notice {
            background-color: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid var(--success-green);
            font-weight: 500;
        }

        .help-links {
            text-align: center;
            margin-top: 20px;
        }

        .help-links a {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
            margin: 0 10px;
        }

        .help-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="form-container">
        <div class="love-bridge-icon">
            <span>❤️</span>
        </div>
        <h2 class="form-title">Login to Love Bridge</h2>
        <p class="form-subtitle">Welcome back! Please enter your credentials</p>
        
        <?php 
        // 集中处理通用错误信息（仅用于“请填写所有字段”或“数据库连接错误”）
        if (!empty($error_message) && (empty($email_error) || empty($password_error))): 
        ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success'): ?>
            <div class="success-notice">
                Registration successful! You can now login with your credentials.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
            <div class="success-notice">
                Password reset successful! You can now login with your new password.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="donor_login.php" id="loginForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" class="form-control <?php echo !empty($email_error) ? 'error' : ''; ?>" 
                        id="email" name="email" 
                        value="<?php echo $email_value; ?>" 
                        required placeholder="Enter your email address">
                
                <?php if (!empty($email_error)): ?>
                    <span class="field-error"><?php echo $email_error == "Please enter your email address." ? $email_error : "Invalid email address."; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group" id="password-group">
                <label for="password">Password</label>
                <input type="password" class="form-control <?php echo !empty($password_error) ? 'error' : ''; ?>" 
                        id="password" name="password" 
                        required placeholder="Enter your password">
                
                <?php if (!empty($password_error)): ?>
                    <span class="field-error"><?php echo $password_error == "Please enter your password." ? $password_error : "Invalid password."; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="remember-forgot">
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember" 
                            <?php echo (isset($_POST['remember']) && $_POST['remember']) ? 'checked' : ''; ?>>
                    <label for="remember">Remember me</label>
                </div>
                <div class="forgot-password">
                    <a href="forgot_password.php">Forgot password?</a> 
                </div>
            </div>
            
            <button type="submit" class="btn">Login to Account</button>
        </form>
        
        <div class="help-links">
            <a href="donor_register.php">Create New Account</a>
        </div>
        
        <div class="form-footer">
            <p>By logging in, you agree to our <a href="terms.php" style="color: var(--primary-red);">Terms</a> and <a href="privacy.php" style="color: var(--primary-red);">Privacy Policy</a></p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        
        // 清除错误样式当用户开始输入时
        function clearErrorOnInput() {
            this.classList.remove('error');
            const errorSpan = this.nextElementSibling;
            if (errorSpan && errorSpan.classList.contains('field-error')) {
                errorSpan.style.display = 'none';
            }
        }

        emailInput.addEventListener('input', clearErrorOnInput);
        passwordInput.addEventListener('input', clearErrorOnInput);
        
        // 输入框焦点效果
        const inputs = [emailInput, passwordInput];
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                if (!this.classList.contains('error')) {
                    this.style.borderColor = 'var(--primary-red)';
                    this.style.boxShadow = '0 0 0 3px var(--light-red)';
                } else {
                    this.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.2)';
                }
            });
            
            input.addEventListener('blur', function() {
                this.style.boxShadow = 'none';
                if (!this.classList.contains('error')) {
                    this.style.borderColor = 'var(--border-color)';
                }
            });
        });
        
        // 确保 password 字段在浏览器自动填充时不会被记住（尽管这很大程度上依赖于浏览器设置，但添加autocomplete="off"有助于防止自动回填）
        passwordInput.setAttribute('autocomplete', 'off');

        // 表单提交前的客户端验证
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // 检查邮箱格式 (如果服务器端验证失败，这里将提供视觉反馈)
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            // 检查邮箱是否为空或格式不正确
            if (emailInput.value.length === 0) {
                 // 允许服务器端处理 "Please enter..." 错误
            } else if (!emailRegex.test(emailInput.value)) {
                emailInput.classList.add('error');
                let errorSpan = emailInput.nextElementSibling;
                if (!errorSpan || !errorSpan.classList.contains('field-error')) {
                    errorSpan = document.createElement('span');
                    errorSpan.className = 'field-error';
                    emailInput.parentNode.insertBefore(errorSpan, emailInput.nextSibling);
                }
                // 使用更通用的客户端错误信息，避免与服务器端冲突
                errorSpan.textContent = 'Please enter a valid email format.';
                errorSpan.style.display = 'block';
                isValid = false;
            }
            
            // 检查密码是否为空
            if (passwordInput.value.length === 0) {
                // 允许服务器端处理 "Please enter..." 错误
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>