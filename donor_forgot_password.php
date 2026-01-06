<?php
// Start session
session_start();

// Include database connection
include 'dataconnection.php';
include 'header_function.php';

// Define constants for site styling
define('SITE_RED', '#d32f2f');
define('SITE_RED_DARK', '#b71c1c');
define('SITE_RED_LIGHT', '#eeeeeeff');
define('RESET_TOKEN_EXPIRY_HOURS', 1); // Token valid for 1 hour
define('MAX_RESET_ATTEMPTS_PER_DAY', 5); // Maximum reset attempts per day per donor

// Initialize variables
$email = '';
$error_message = '';
$success_message = '';
$email_sent = false;
$rate_limit_exceeded = false;

// Function to log security events
function logSecurityEvent($donor_id, $action, $details = '') {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt = $conn->prepare("INSERT INTO donor_security_logs (donor_id, log_type, log_action, ip_address, user_agent, log_details) VALUES (?, 'password_reset', ?, ?, ?, ?)");
    $stmt->bind_param("issss", $donor_id, $action, $ip_address, $user_agent, $details);
    return $stmt->execute();
}

// Function to check rate limiting
function checkRateLimit($donor_id, $email) {
    global $conn;
    
    // Check if donor has exceeded daily reset attempts
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
    
    return $data['attempt_count'] < MAX_RESET_ATTEMPTS_PER_DAY;
}

// Function to invalidate old tokens
function invalidateOldTokens($donor_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE donor_password_reset 
        SET reset_status = 'invalidated', 
            reset_used = 1,
            reset_updated = NOW()
        WHERE donor_id = ? 
        AND reset_used = 0 
        AND reset_status = 'pending'
    ");
    $stmt->bind_param("i", $donor_id);
    return $stmt->execute();
}

// Function to generate secure token
function generateSecureToken() {
    return bin2hex(random_bytes(32));
}

// Function to send reset email
function sendPasswordResetEmail($donor_name, $donor_email, $reset_token) {
    $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/donor_reset_password.php?token=" . $reset_token;
    
    $subject = "Love Bridge - Password Reset Request";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f4ededff;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .header-forgot-password {
                background-color: #d32f2f;
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
            }
            .content {
                padding: 30px;
            }
            .reset-button {
                display: inline-block;
                background-color: #d32f2f;
                color: white;
                text-decoration: none;
                padding: 15px 30px;
                border-radius: 5px;
                font-weight: bold;
                margin: 20px 0;
            }
            .reset-button:hover {
                background-color: #b71c1c;
            }
            .footer {
                background-color: #f5f5f5;
                padding: 20px;
                text-align: center;
                color: #666;
                font-size: 12px;
            }
            .warning {
                background-color: #ffebee;
                border-left: 4px solid #d32f2f;
                padding: 15px;
                margin: 20px 0;
                border-radius: 0 4px 4px 0;
            }
            .token-display {
                background-color: #f5f5f5;
                padding: 15px;
                border-radius: 4px;
                word-break: break-all;
                font-family: monospace;
                margin: 15px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header-forgot-password">
                <h1>Love Bridge Donation Platform</h1>
            </div>
            <div class="content">
                <h2>Password Reset Request</h2>
                <p>Hello ' . htmlspecialchars($donor_name) . ',</p>
                <p>We received a request to reset your password for your Love Bridge donor account.</p>
                
                <div class="warning">
                    <strong>Important:</strong> This password reset link will expire in 1 hour.
                </div>
                
                <p>To reset your password, please click the button below:</p>
                
                <p style="text-align: center;">
                    <a href="' . $reset_link . '" class="reset-button">Reset My Password</a>
                </p>
                
                <p>Or copy and paste this link into your browser:</p>
                <div class="token-display">' . $reset_link . '</div>
                
                <p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
                
                <p>For security reasons, this link can only be used once.</p>
                
                <p>Best regards,<br>
                <strong>Love Bridge Foundation</strong><br>
                <em>Connecting hearts to make a difference</em></p>
            </div>
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; ' . date('Y') . ' Love Bridge Foundation. All rights reserved.</p>
                <p>Love Bridge is a registered charity in Malaysia. Registration No: PPM-123-456-789</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Love Bridge <noreply@lovebridge.org.my>" . "\r\n";
    $headers .= "Reply-To: support@lovebridge.org.my" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send email
    return mail($donor_email, $subject, $message, $headers);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // Basic validation
    if (empty($email)) {
        $error_message = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Check if email exists in donor table
        $stmt = $conn->prepare("SELECT Donor_ID, Donor_Name, donor_reset_count FROM donor WHERE Donor_Email = ? AND Is_Deleted = 0");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // Donor found
            $donor = $result->fetch_assoc();
            $donor_id = $donor['Donor_ID'];
            $donor_name = $donor['Donor_Name'];
            
            // Check rate limiting
            if (!checkRateLimit($donor_id, $email)) {
                $rate_limit_exceeded = true;
                $error_message = 'Too many password reset attempts. Please try again in 24 hours or contact support.';
                
                // Log security event
                logSecurityEvent($donor_id, 'rate_limit_exceeded', "Password reset rate limit exceeded for email: $email");
            } else {
                // Invalidate any existing tokens for this donor
                invalidateOldTokens($donor_id);
                
                // Generate new secure token
                $reset_token = generateSecureToken();
                
                // Calculate expiry time
                $expiry_time = date('Y-m-d H:i:s', strtotime('+' . RESET_TOKEN_EXPIRY_HOURS . ' hours'));
                
                // Get IP address and user agent for security logging
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                
                // Insert reset token into new table
                $insert_stmt = $conn->prepare("
                    INSERT INTO donor_password_reset 
                    (donor_id, reset_token, reset_email, reset_expires, ip_address, user_agent, reset_status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')
                ");
                $insert_stmt->bind_param("isssss", $donor_id, $reset_token, $email, $expiry_time, $ip_address, $user_agent);
                
                if ($insert_stmt->execute()) {
                    // Update donor reset count
                    $update_stmt = $conn->prepare("
                        UPDATE donor 
                        SET donor_reset_count = donor_reset_count + 1, 
                            donor_last_reset_request = NOW() 
                        WHERE Donor_ID = ?
                    ");
                    $update_stmt->bind_param("i", $donor_id);
                    $update_stmt->execute();
                    
                    // Log security event
                    logSecurityEvent($donor_id, 'reset_requested', "Password reset token generated for donor ID: $donor_id");
                    
                    // Send password reset email
                    if (sendPasswordResetEmail($donor_name, $email, $reset_token)) {
                        $email_sent = true;
                        $success_message = 'Password reset instructions have been sent to your email address. Please check your inbox (and spam folder).';
                        
                        // Log email sent event
                        logSecurityEvent($donor_id, 'reset_email_sent', "Password reset email sent successfully");
                    } else {
                        $error_message = 'Failed to send password reset email. Please try again or contact support.';
                        logSecurityEvent($donor_id, 'reset_email_failed', "Failed to send password reset email");
                    }
                } else {
                    $error_message = 'Failed to process your request. Please try again.';
                    logSecurityEvent($donor_id, 'reset_token_failed', "Failed to generate reset token");
                }
                
                $insert_stmt->close();
            }
        } else {
            // Donor not found - for security, don't reveal this
            // Instead, show success message anyway (security best practice)
            $email_sent = true;
            $success_message = 'If an account exists with this email, password reset instructions have been sent.';
            
            // Log failed attempt for security monitoring
            logSecurityEvent(NULL, 'failed_reset_attempt', "Password reset attempt for non-existent email: $email");
        }
        $stmt->close();
    }
}

// Clean up expired tokens (run as background task)
function cleanupExpiredTokens() {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE donor_password_reset 
        SET reset_status = 'expired', 
            reset_updated = NOW() 
        WHERE reset_expires < NOW() 
        AND reset_used = 0 
        AND reset_status = 'pending'
    ");
    $stmt->execute();
}

// Run cleanup
cleanupExpiredTokens();
include 'header_UI.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Love Bridge Donation Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Love Bridge Red Theme */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #fdfdfdff 0%, #f4f4f4ff 100%);
            color: #333;
            line-height: 1.6;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
           
        }

        .forgot-password-container {
            width: 100%;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(211, 47, 47, 0.15);
            overflow: hidden;
            animation: fadeIn 0.5s ease-out;
            align-items:center;
            max-width: 1000px;
            margin: 40px auto;
         
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-forgot-password {
            background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header-forgot-password::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.1;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logo {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #d32f2f;
            font-size: 36px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .header-forgot-password h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .header-forgot-password p {
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .content {
            padding: 40px;
        }

        .steps-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }

        .steps-container::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #e0e0e0;
            z-index: 1;
        }

        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }

        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e0e0e0;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 auto 10px;
            transition: all 0.3s ease;
        }

        .step.active .step-circle {
            background-color: #d32f2f;
            transform: scale(1.1);
            box-shadow: 0 0 0 5px rgba(211, 47, 47, 0.2);
        }

        .step-label {
            font-size: 12px;
            color: #757575;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .step.active .step-label {
            color: #d32f2f;
            font-weight: 600;
        }

        .form-container {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 4px solid #d32f2f;
        }

        .form-title {
            color: #b71c1c;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-title i {
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #757575;
            font-size: 18px;
        }

        .input-with-icon input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background-color: white;
        }

        .input-with-icon input:focus {
            outline: none;
            border-color: #d32f2f;
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
        }

        .input-with-icon input.error {
            border-color: #f44336;
            box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1);
        }

        .error-message {
            background-color: #ffebee;
            color: #d32f2f;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #d32f2f;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .btn {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(211, 47, 47, 0.3);
        }

        .btn:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn:disabled::before {
            display: none;
        }

        .btn i {
            margin-right: 10px;
        }

        .secondary-btn {
            background: white;
            color: #d32f2f;
            border: 2px solid #d32f2f;
            margin-top: 15px;
        }

        .secondary-btn:hover {
            background: #d32f2f;
            color: white;
        }

        .security-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 25px;
            font-size: 14px;
            color: #616161;
            text-align: center;
            border: 1px solid #e0e0e0;
        }

        .security-info i {
            color: #d32f2f;
            margin-right: 8px;
        }

        .help-links {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .help-link {
            color: #d32f2f;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .help-link:hover {
            color: #b71c1c;
            transform: translateX(5px);
        }

        /* Loading animation */
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .content {
                padding: 25px;
            }
            
            .header-forgot-password {
                padding: 30px 20px;
            }
            
            .header-forgot-password h1 {
                font-size: 24px;
            }
            
            .logo {
                width: 70px;
                height: 70px;
                font-size: 30px;
            }
            
            .help-links {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .step-label {
                font-size: 11px;
            }
        }

        /* Rate limit warning */
        .rate-limit-warning {
            background-color: #fff3e0;
            color: #e65100;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ff9800;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(255, 152, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0); }
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="header-forgot-password">
            <div class="logo">
                <i class="fas fa-heart"></i>
            </div>
            <h1>Love Bridge</h1>
            <p>Donor Password Recovery</p>
        </div>
        
        <div class="content">
            <!-- Step Indicator -->
            <div class="steps-container">
                <div class="step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Request Reset</div>
                </div>
                <div class="step <?php echo $email_sent ? 'active' : ''; ?>">
                    <div class="step-circle">2</div>
                    <div class="step-label">Check Email</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">New Password</div>
                </div>
            </div>
            
            <!-- Rate Limit Warning -->
            <?php if ($rate_limit_exceeded): ?>
                <div class="rate-limit-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Too Many Attempts</strong><br>
                        <small>You've exceeded the maximum password reset attempts. Please wait 24 hours or contact support.</small>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if (!empty($error_message) && !$rate_limit_exceeded): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>
            
            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Email Sent!</strong><br>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!$email_sent && !$rate_limit_exceeded): ?>
                <!-- Password Reset Form -->
                <div class="form-container">
                    <div class="form-title">
                        <i class="fas fa-key"></i>
                        <span>Reset Your Password</span>
                    </div>
                    
                    <form method="POST" action="" id="forgotPasswordForm">
                        <div class="form-group">
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" 
                                       placeholder="Enter your registered email address" 
                                       value="<?php echo htmlspecialchars($email); ?>" 
                                       required
                                       autocomplete="email">
                            </div>
                            <small style="display: block; margin-top: 8px; color: #757575;">
                                <i class="fas fa-info-circle"></i>
                                Enter the email address associated with your donor account
                            </small>
                        </div>
                        
                        <button type="submit" class="btn" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            <span id="btnText">Send Reset Link</span>
                            <div class="loading-spinner" id="loadingSpinner"></div>
                        </button>
                    </form>
                </div>
                
                <!-- Security Information -->
                <div class="security-info">
                    <i class="fas fa-shield-alt"></i>
                    For security reasons, password reset links expire in <?php echo RESET_TOKEN_EXPIRY_HOURS; ?> hour(s) and can only be used once.
                </div>
                
                <!-- Help Links -->
                <div class="help-links">
                    <a href="donor_login.php" class="help-link">
                        <i class="fas fa-arrow-left"></i>
                        Back to Login
                    </a>
                    <a href="contact_us.php" class="help-link">
                        <i class="fas fa-question-circle"></i>
                        Need Help?
                    </a>
                </div>
            <?php elseif ($email_sent): ?>
                <!-- After email sent -->
                <div class="form-container">
                    <div class="success-message" style="background-color: transparent; border: none; margin: 0;">
                        <i class="fas fa-envelope-open-text" style="font-size: 48px; color: #4caf50;"></i>
                        <div style="flex: 1;">
                            <h3 style="color: #2e7d32; margin-bottom: 10px;">Check Your Email</h3>
                            <p>We've sent password reset instructions to:</p>
                            <p style="background-color: #e8f5e9; padding: 10px; border-radius: 5px; margin: 10px 0; font-weight: bold;">
                                <?php echo htmlspecialchars($email); ?>
                            </p>
                            <p><small>If you don't see the email, please check your spam folder.</small></p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <p style="color: #757575; margin-bottom: 15px;">
                            Didn't receive the email? Try these steps:
                        </p>
                        <ol style="text-align: left; margin-left: 20px; color: #616161;">
                            <li>Check your spam or junk folder</li>
                            <li>Make sure you entered the correct email address</li>
                            <li>Wait a few minutes and try again</li>
                        </ol>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <a href="javascript:location.reload()" class="btn secondary-btn">
                            <i class="fas fa-redo"></i>
                            Try Another Email
                        </a>
                        <a href="donor_login.php" class="btn" style="margin-top: 10px;">
                            <i class="fas fa-sign-in-alt"></i>
                            Back to Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php include 'footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    const emailInput = document.getElementById('email');
                    const emailValue = emailInput.value.trim();
                    
                    // Simple email validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (!emailRegex.test(emailValue)) {
                        e.preventDefault();
                        showError(emailInput, 'Please enter a valid email address.');
                        return;
                    }
                    
                    // Show loading state
                    btnText.style.display = 'none';
                    loadingSpinner.style.display = 'block';
                    submitBtn.disabled = true;
                    
                    // Add some delay for better UX
                    setTimeout(() => {
                        submitBtn.style.opacity = '0.7';
                    }, 100);
                });
                
                // Real-time email validation
                const emailInput = document.getElementById('email');
                emailInput.addEventListener('blur', function() {
                    const emailValue = this.value.trim();
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (emailValue && !emailRegex.test(emailValue)) {
                        showError(this, 'Please enter a valid email address.');
                    } else {
                        clearError(this);
                    }
                });
                
                emailInput.addEventListener('input', function() {
                    clearError(this);
                });
            }
            
            function showError(input, message) {
                clearError(input);
                input.classList.add('error');
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.marginTop = '10px';
                errorDiv.style.marginBottom = '0';
                errorDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    <div>${message}</div>
                `;
                
                input.parentNode.parentNode.appendChild(errorDiv);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (errorDiv.parentNode) {
                        errorDiv.remove();
                        clearError(input);
                    }
                }, 5000);
            }
            
            function clearError(input) {
                input.classList.remove('error');
                
                // Remove any existing error messages
                const parent = input.parentNode.parentNode;
                const errorMessages = parent.querySelectorAll('.error-message');
                errorMessages.forEach(msg => {
                    if (msg.parentNode === parent) {
                        msg.remove();
                    }
                });
            }
            
            // Add focus effect to input
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentNode.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentNode.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>
