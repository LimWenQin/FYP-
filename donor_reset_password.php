<?php
session_start();
include 'dataconnection.php';
include 'header_function.php'; 

// 定义常量
define('SITE_RED', '#d32f2f');

$token = $_GET['token'] ?? '';
$error_message = '';
$success_message = '';
$token_valid = false;
$donor_id = null;

// 1. 验证 Token
if (!empty($token)) {
    $stmt = $conn->prepare("
        SELECT reset_id, donor_id, reset_expires 
        FROM donor_password_reset 
        WHERE reset_token = ? 
        AND reset_status = 'pending' 
        AND reset_expires > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $donor_id = $row['donor_id'];
        $token_valid = true;
    } else {
        $error_message = "Invalid or expired password reset link. Please request a new one.";
    }
    $stmt->close();
} else {
    $error_message = "Missing reset token.";
}

// 2. 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $post_token = $_POST['token'];

    if ($post_token !== $token) {
        $error_message = "Invalid token submission.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($new_password) < 8 || strlen($new_password) > 15) {
        $error_message = "Password must be between 8-15 characters.";
    } elseif (!preg_match("/[A-Z]/", $new_password)) {
        $error_message = "Password must contain at least one Uppercase letter.";
    } elseif (!preg_match("/[a-z]/", $new_password)) {
        $error_message = "Password must contain at least one Lowercase letter.";
    } elseif (!preg_match("/[0-9]/", $new_password)) {
        $error_message = "Password must contain at least one Number.";
    } elseif (!preg_match("/[^A-Za-z0-9]/", $new_password)) {
        $error_message = "Password must contain at least one Special character.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $conn->begin_transaction();

        try {
            $update_stmt = $conn->prepare("UPDATE donor SET Donor_Password = ? WHERE Donor_ID = ?");
            $update_stmt->bind_param("si", $hashed_password, $donor_id);
            $update_stmt->execute();

            $token_stmt = $conn->prepare("UPDATE donor_password_reset SET reset_status = 'used', reset_used = 1, reset_updated = NOW() WHERE reset_token = ?");
            $token_stmt->bind_param("s", $token);
            $token_stmt->execute();

            $ip = $_SERVER['REMOTE_ADDR'];
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $log_stmt = $conn->prepare("INSERT INTO donor_security_logs (donor_id, log_type, log_action, ip_address, user_agent, log_details) VALUES (?, 'password_reset', 'reset_success', ?, ?, 'Password successfully reset via email token')");
            $log_stmt->bind_param("iss", $donor_id, $ip, $ua);
            $log_stmt->execute();

            $conn->commit();
            $success_message = "Your password has been successfully reset!";
            $token_valid = false; 

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "System error updating password. Please try again.";
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
    <title>Reset Password | Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f4f4;
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .reset-container {
            width: 100%;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 500px;
            margin: 50px auto;
        }

        .header-reset {
            background-color: #d32f2f;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .logo { font-size: 40px; margin-bottom: 10px; }
        .content { padding: 30px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }

        .input-with-icon { position: relative; }
        .input-with-icon i.input-icon { /* Note: Added class to distinguish from requirement icons */
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #757575;
        }
        .input-with-icon input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .input-with-icon input:focus { outline: none; border-color: #d32f2f; }
        .input-with-icon input.error { border-color: #d32f2f; background-color: #fff0f0; }
        .input-with-icon input.success { border-color: #4caf50; background-color: #f0fff4; }

        /* Password Strength Bar */
        .password-strength { height: 4px; background: #eee; margin-top: 8px; border-radius: 2px; overflow: hidden; display: flex; margin-bottom: 15px; }
        .strength-bar { height: 100%; width: 0%; transition: width 0.3s, background-color 0.3s; }
        
        /* New Requirement Item Design */
        .password-requirements {
            background-color: #fafafa;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #eee;
        }

        .requirement-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #666;
            transition: color 0.3s;
        }
        
        .requirement-item:last-child { margin-bottom: 0; }

        .requirement-item i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            font-size: 0.8rem;
        }

        /* Invalid State (Default) */
        .requirement-item.invalid i { color: #999; }
        
        /* Valid State */
        .requirement-item.valid { color: #2e7d32; font-weight: 500; }
        .requirement-item.valid i { color: #4caf50; }

        /* Messages */
        .error-message { background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
        .success-message { background-color: #e8f5e9; color: #2e7d32; padding: 20px; border-radius: 6px; text-align: center; }

        /* Buttons */
        .btn {
            display: block; width: 100%; background-color: #d32f2f; color: white; border: none;
            padding: 14px; border-radius: 6px; font-size: 16px; font-weight: 600;
            cursor: pointer; text-align: center; text-decoration: none; transition: background-color 0.3s;
        }
        .btn:hover { background-color: #b71c1c; }
        .btn:disabled { background-color: #ccc; cursor: not-allowed; }

        .back-btn { margin-top: 15px; background: white; color: #d32f2f; border: 1px solid #d32f2f; }
        .back-btn:hover { background: #fdeaea; }
    </style>
</head>
<body>

    <div class="reset-container">
        <div class="header-reset">
            <div class="logo"><i class="fas fa-lock-open"></i></div>
            <h2>Create New Password</h2>
        </div>

        <div class="content">
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                    <h3>Success!</h3>
                    <p><?php echo $success_message; ?></p>
                    <a href="donor_login.php" class="btn" style="margin-top: 20px;">Login with New Password</a>
                </div>
            
            <?php elseif (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $error_message; ?></div>
                </div>
                <?php if (!$token_valid): ?>
                    <a href="donor_forgot_password.php" class="btn secondary-btn">Request New Link</a>
                <?php endif; ?>

            <?php elseif ($token_valid): ?>
                <form method="POST" action="" id="resetForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-key input-icon"></i>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                        </div>
                        
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
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
                        <label>Confirm Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                        </div>
                        <div id="match-msg" style="font-size: 12px; margin-top: 5px;"></div>
                    </div>

                    <button type="submit" class="btn" id="submitBtn" disabled>Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        const form = document.getElementById('resetForm');
        if (form) {
            const passInput = document.getElementById('new_password');
            const confirmInput = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            const strengthBar = document.getElementById('strengthBar');
            const matchMsg = document.getElementById('match-msg');

            // 5 个具体规则的正则
            const rules = {
                lengthReq: /^.{8,15}$/,
                uppercaseReq: /[A-Z]/,
                lowercaseReq: /[a-z]/,
                numberReq: /[0-9]/,
                specialReq: /[^A-Za-z0-9]/
            };

            function validatePassword() {
                const val = passInput.value;
                let validCount = 0;

                // 遍历每个 ID，检查对应的正则
                for (const [id, regex] of Object.entries(rules)) {
                    const el = document.getElementById(id);
                    const icon = el.querySelector('i');

                    if (regex.test(val)) {
                        el.classList.remove('invalid');
                        el.classList.add('valid');
                        icon.className = 'fas fa-check'; // 满足时打钩
                        validCount++;
                    } else {
                        el.classList.remove('valid');
                        el.classList.add('invalid');
                        icon.className = 'fas fa-times'; // 不满足时打叉
                    }
                }

                // 更新强度条 (5分制)
                const width = (validCount / 5) * 100;
                strengthBar.style.width = width + '%';
                
                if (validCount <= 2) strengthBar.style.backgroundColor = '#d32f2f';
                else if (validCount <= 4) strengthBar.style.backgroundColor = '#ff9800';
                else strengthBar.style.backgroundColor = '#4caf50';

                // 只有 5 个全部通过，且密码匹配时，才允许提交
                checkMatch(validCount);
            }

            function checkMatch(validCount = null) {
                // 如果没有传入 validCount，重新计算一下
                if (validCount === null) {
                    const val = passInput.value;
                    let count = 0;
                    for (const [id, regex] of Object.entries(rules)) {
                        if (regex.test(val)) count++;
                    }
                    validCount = count;
                }

                const p1 = passInput.value;
                const p2 = confirmInput.value;

                if (p2 === "") {
                    matchMsg.textContent = "";
                    confirmInput.classList.remove('error', 'success');
                    submitBtn.disabled = true;
                    return;
                }

                if (p1 === p2) {
                    matchMsg.textContent = "Passwords match";
                    matchMsg.style.color = "#2e7d32";
                    confirmInput.classList.add('success');
                    confirmInput.classList.remove('error');
                    
                    // 只有当密码匹配 + 5个规则全部满足 (validCount === 5)
                    if (validCount === 5) {
                        submitBtn.disabled = false;
                    } else {
                        submitBtn.disabled = true;
                    }
                } else {
                    matchMsg.textContent = "Passwords do not match";
                    matchMsg.style.color = "#d32f2f";
                    confirmInput.classList.add('error');
                    confirmInput.classList.remove('success');
                    submitBtn.disabled = true;
                }
            }

            passInput.addEventListener('input', validatePassword);
            confirmInput.addEventListener('input', () => checkMatch());
        }
    </script>
</body>
</html>