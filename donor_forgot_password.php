<?php
// Start session
session_start();

// --- 1. 手动引入 PHPMailer 文件 ---
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include database connection
include 'dataconnection.php';
include 'header_function.php';

// Define constants for site styling
define('SITE_RED', '#d32f2f');
define('SITE_RED_DARK', '#b71c1c');
define('RESET_TOKEN_EXPIRY_MINUTES', 15); // Token 有效期 15 分钟
define('MAX_RESET_ATTEMPTS_PER_DAY', 5);  // 每天最多重置 5 次
define('RESEND_COOLDOWN_SECONDS', 60);    // 【新增】Resend 冷却时间 60秒

// Initialize variables
$email = '';
$error_message = '';
$success_message = '';
$email_sent = false;
$rate_limit_exceeded = false;

// 【新增】计算剩余冷却时间
$seconds_remaining = 0;
if (isset($_SESSION['last_mail_time'])) {
    $elapsed = time() - $_SESSION['last_mail_time'];
    if ($elapsed < RESEND_COOLDOWN_SECONDS) {
        $seconds_remaining = RESEND_COOLDOWN_SECONDS - $elapsed;
    } else {
        unset($_SESSION['last_mail_time']); // 超过时间清除session
    }
}

// Function to log security events
function logSecurityEvent($donor_id, $action, $details = '') {
    global $conn;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt = $conn->prepare("INSERT INTO donor_security_logs (donor_id, log_type, log_action, ip_address, user_agent, log_details) VALUES (?, 'password_reset', ?, ?, ?, ?)");
    $stmt->bind_param("issss", $donor_id, $action, $ip_address, $user_agent, $details);
    $return = $stmt->execute();
    $stmt->close();
    return $return;
}

// Function to check rate limiting
function checkRateLimit($donor_id, $email) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM donor_password_reset 
        WHERE reset_email = ? 
        AND reset_created >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND reset_status = 'pending'
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data['attempt_count'] < MAX_RESET_ATTEMPTS_PER_DAY;
}

// Function to invalidate old tokens
function invalidateOldTokens($donor_id) {
    global $conn;
    $stmt = $conn->prepare("
        DELETE FROM donor_password_reset 
        WHERE donor_id = ? 
        AND reset_status = 'pending'
    ");
    $stmt->bind_param("i", $donor_id);
    $return = $stmt->execute();
    $stmt->close();
    return $return;
}

// Function to generate secure token
function generateSecureToken() {
    return bin2hex(random_bytes(32));
}

// --- 2. 发送邮件函数 ---
function sendPasswordResetEmail($donor_name, $donor_email, $reset_token) {
    $mail = new PHPMailer(true);

    try {
        // --- SMTP 配置 ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';        
        $mail->SMTPAuth   = true;
        $mail->Username   = 'qinwenlin989@gmail.com';  // 你的 Gmail
        $mail->Password   = 'awrf mmum famw rxii';     // 你的应用密码
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // --- 发件人和收件人 ---
        $mail->setFrom('qinwenlin989@gmail.com', 'Love Bridge Admin');
        $mail->addAddress($donor_email, $donor_name);

        // --- 生成正确的链接 ---
        $domain = $_SERVER['HTTP_HOST'];
        $folder_path = "/FYP/FYP-"; 
        $base_url = "http://" . $domain . $folder_path;
        $reset_link = $base_url . "/donor_reset_password.php?token=" . $reset_token;
        
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Password - Love Bridge';
        
        // --- 邮件内容 ---
        $email_content = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
            <h2 style='color: #d32f2f; text-align: center;'>Password Reset Request</h2>
            <p>Hello <strong>$donor_name</strong>,</p>
            <p>We received a request to reset your password for your Love Bridge donor account.</p>
            <p>Click the button below to reset it (link expires in 15 minutes):</p>
            <p style='text-align: center;'>
                <a href='$reset_link' style='background-color: #d32f2f; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Reset My Password</a>
            </p>
            <p>Or copy this link: <br><small>$reset_link</small></p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #777; text-align: center;'>If you did not request this, please ignore this email.</p>
        </div>";

        $mail->Body    = $email_content;
        $mail->AltBody = "Hello $donor_name, Copy this link to reset your password: $reset_link";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // 【新增】PHP端检查冷却时间 (防止绕过前端JS)
    if (isset($_SESSION['last_mail_time']) && (time() - $_SESSION['last_mail_time'] < RESEND_COOLDOWN_SECONDS)) {
        $seconds_wait = RESEND_COOLDOWN_SECONDS - (time() - $_SESSION['last_mail_time']);
        $error_message = "Please wait $seconds_wait seconds before sending another email.";
        $email_sent = true; // 保持停留在 sent 界面
    } 
    elseif (empty($email)) {
        $error_message = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT Donor_ID, Donor_Name, donor_reset_count FROM donor WHERE Donor_Email = ? AND Is_Deleted = 0");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $donor = $result->fetch_assoc();
            $donor_id = $donor['Donor_ID'];
            $donor_name = $donor['Donor_Name'];
            
            if (!checkRateLimit($donor_id, $email)) {
                $rate_limit_exceeded = true;
                $error_message = 'Too many password reset attempts. Please try again in 24 hours.';
                logSecurityEvent($donor_id, 'rate_limit_exceeded', "Limit exceeded for: $email");
            } else {
                invalidateOldTokens($donor_id);
                $reset_token = generateSecureToken();
                
                $expiry_time = date('Y-m-d H:i:s', strtotime('+' . RESET_TOKEN_EXPIRY_MINUTES . ' minutes'));
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                
                $insert_stmt = $conn->prepare("INSERT INTO donor_password_reset (donor_id, reset_token, reset_email, reset_expires, ip_address, user_agent, reset_status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $insert_stmt->bind_param("isssss", $donor_id, $reset_token, $email, $expiry_time, $ip_address, $user_agent);
                
                if ($insert_stmt->execute()) {
                    // Update count
                    $conn->query("UPDATE donor SET donor_reset_count = donor_reset_count + 1 WHERE Donor_ID = $donor_id");
                    
                    if (sendPasswordResetEmail($donor_name, $email, $reset_token)) {
                        $email_sent = true;
                        // 【新增】设置发送时间 Session
                        $_SESSION['last_mail_time'] = time();
                        // 重新计算剩余时间传给前端
                        $seconds_remaining = RESEND_COOLDOWN_SECONDS;
                        
                        $success_message = 'Password reset instructions have been sent to your email.';
                        logSecurityEvent($donor_id, 'reset_email_sent', "Success");
                    } else {
                        $error_message = 'Failed to send email. Please check your SMTP configuration.';
                        logSecurityEvent($donor_id, 'reset_email_failed', "Failed to send");
                    }
                } else {
                    $error_message = 'System error. Please try again.';
                }
                $insert_stmt->close();
            }
        } else {
            // Security: Don't reveal user doesn't exist
            $email_sent = true;
            // Fake timer logic for security (optional, but good practice)
            $_SESSION['last_mail_time'] = time();
            $seconds_remaining = RESEND_COOLDOWN_SECONDS;
            
            $success_message = 'If an account exists with this email, password reset instructions have been sent.';
            logSecurityEvent(NULL, 'failed_reset_attempt', "Non-existent: $email");
        }
        $stmt->close();
    }
}

// Clean up expired tokens
$conn->query("UPDATE donor_password_reset SET reset_status = 'expired' WHERE reset_expires < NOW() AND reset_status = 'pending'");

include 'header_UI.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Love Bridge</title>
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
        
        .forgot-password-container {
            width: 100%;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 500px;
            margin: 50px auto;
        }

        .header-forgot-password {
            background-color: #d32f2f;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .logo {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .content { padding: 30px; }

        .form-container { margin-top: 10px; }

        .form-group { margin-bottom: 20px; }

        .input-with-icon { position: relative; }
        .input-with-icon i {
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
        .input-with-icon input:focus {
            outline: none;
            border-color: #d32f2f;
        }

        /* Message Styles */
        .error-message {
            background-color: #ffebee;
            color: #d32f2f;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex; gap: 10px; align-items: center;
        }
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex; gap: 10px; align-items: center;
        }

        /* Button Styles */
        .btn {
            display: block;
            width: 100%;
            background-color: #d32f2f;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #b71c1c; 
        }

        .btn:disabled, .btn.disabled {
            background: #cccccc !important;
            color: #666 !important;
            cursor: not-allowed !important;
            border-color: #cccccc !important;
        }

        .secondary-btn {
            background: white;
            color: #d32f2f;
            border: 1px solid #d32f2f;
            margin-top: 15px;
        }
        .secondary-btn:hover {
            background: #fdeaea;
        }

        .security-info {
            font-size: 12px;
            color: #777;
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .help-links {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 14px;
        }
        .help-link { color: #555; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .help-link:hover { color: #d32f2f; }

        /* Spinner */
        .loading-spinner {
            display: none;
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        /* Specific spinner for secondary btn (since bg is white) */
        .secondary-btn .loading-spinner {
            border: 2px solid rgba(211, 47, 47, 0.3);
            border-top-color: #d32f2f;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="header-forgot-password">
            <div class="logo"><i class="fas fa-heart"></i></div>
            <h2>Password Recovery</h2>
        </div>
        
        <div class="content">
            <?php if ($rate_limit_exceeded): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    Too many attempts. Please try again later.
                </div>
                <div style="text-align:center;">
                    <a href="donor_login.php" class="btn">Back to Login</a>
                </div>
            <?php elseif (!empty($error_message) && !$email_sent): // 只有在没有成功发送邮件时显示大错误框 ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <div class="form-container">
                    <form method="POST" action="" id="retryForm">
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:500;">Email Address</label>
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="Enter your email">
                            </div>
                        </div>
                        <button type="submit" class="btn" id="retrySubmitBtn">
                            <span id="retryBtnText">Try Again</span>
                            <div class="loading-spinner" id="retryLoadingSpinner"></div>
                        </button>
                    </form>
                </div>

            <?php elseif (!empty($success_message) || $email_sent): ?>
                
                <?php if(!empty($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-clock"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($success_message)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo htmlspecialchars($success_message); ?></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!$email_sent && !$rate_limit_exceeded && empty($error_message)): ?>
                <div class="form-container">
                    <form method="POST" action="" id="forgotPasswordForm">
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:500;">Email Address</label>
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="Enter your email">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn" id="submitBtn">
                            <span id="btnText">Send Reset Link</span>
                            <div class="loading-spinner" id="loadingSpinner"></div>
                        </button>
                    </form>
                </div>
                
                <div class="security-info">
                    <i class="fas fa-shield-alt"></i> Reset link expires in <?php echo RESET_TOKEN_EXPIRY_MINUTES; ?> minutes.
                </div>
                
                <div class="help-links">
                    <a href="donor_login.php" class="help-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
                    <a href="contact_us.php" class="help-link">Need Help?</a>
                </div>

            <?php elseif ($email_sent): ?>
                <div style="text-align: center;">
                    <i class="fas fa-envelope-open-text" style="font-size: 50px; color: #4caf50; margin-bottom: 20px;"></i>
                    <p style="margin-bottom: 20px;">We sent instructions to <strong><?php echo htmlspecialchars($email); ?></strong></p>
                    
                    <form method="POST" action="" id="resendForm" style="margin-bottom: 15px;">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <button type="submit" class="btn secondary-btn" id="resendBtn">
                            <span id="resendBtnText">Resend Email</span>
                            <div class="loading-spinner" id="resendSpinner"></div>
                        </button>
                    </form>

                    <a href="donor_login.php" class="btn" style="margin-top: 10px;">Back to Login</a>
                    
                    <div style="margin-top: 15px;">
                        <a href="donor_forgot_password.php" style="color: #777; font-size: 14px; text-decoration: none;">Entered wrong email? Try again</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script>
        // 获取 PHP 传递的剩余秒数
        let cooldownSeconds = <?php echo $seconds_remaining; ?>;
        
        // Handle standard submit
        document.getElementById('forgotPasswordForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            const txt = document.getElementById('btnText');
            const spin = document.getElementById('loadingSpinner');
            
            btn.disabled = true;
            btn.style.opacity = '0.8';
            txt.style.display = 'none';
            spin.style.display = 'block';
        });

        // Handle Resend submit
        document.getElementById('resendForm')?.addEventListener('submit', function(e) {
            // 前端再次检查，防止手快
            if(cooldownSeconds > 0) {
                e.preventDefault();
                return false;
            }

            const btn = document.getElementById('resendBtn');
            const txt = document.getElementById('resendBtnText');
            const spin = document.getElementById('resendSpinner');
            
            btn.disabled = true;
            btn.style.opacity = '0.8';
            txt.style.display = 'none';
            spin.style.display = 'block';
        });

        // Handle Retry submit (if error occurred)
        document.getElementById('retryForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('retrySubmitBtn');
            const txt = document.getElementById('retryBtnText');
            const spin = document.getElementById('retryLoadingSpinner');
            
            btn.disabled = true;
            btn.style.opacity = '0.8';
            txt.style.display = 'none';
            spin.style.display = 'block';
        });

        // --- 倒计时逻辑 ---
        document.addEventListener('DOMContentLoaded', function() {
            const resendBtn = document.getElementById('resendBtn');
            const resendBtnText = document.getElementById('resendBtnText');

            if (resendBtn && cooldownSeconds > 0) {
                // 冻结按钮
                disableResendButton();
                
                // 开始倒计时
                const timer = setInterval(function() {
                    cooldownSeconds--;
                    resendBtnText.innerText = `Resend in ${cooldownSeconds}s`;
                    
                    if (cooldownSeconds <= 0) {
                        clearInterval(timer);
                        enableResendButton();
                    }
                }, 1000);
            }

            function disableResendButton() {
                resendBtn.disabled = true;
                resendBtn.classList.add('disabled'); // Add helper class
                resendBtnText.innerText = `Resend in ${cooldownSeconds}s`;
            }

            function enableResendButton() {
                resendBtn.disabled = false;
                resendBtn.classList.remove('disabled');
                resendBtnText.innerText = "Resend Email";
            }
        });
    </script>
</body>
</html>