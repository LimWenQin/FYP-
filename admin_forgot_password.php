<?php
// admin_forgot_password.php
session_start();
include 'dataconnection.php';

// 引入 PHPMailer 类
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$alertType = ""; // success 或 error
$alertMsg = "";

// 处理表单提交
if (isset($_POST['reset_request'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // 1. 检查邮箱是否存在
    $check_sql = "SELECT * FROM admin WHERE Admin_Email = '$email'";
    $result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($result) > 0) {
        // 2. 生成 Token
        $token = bin2hex(random_bytes(32)); 
        $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // 3. 存入数据库
        mysqli_query($conn, "DELETE FROM password_resets WHERE email='$email'");
        $insert_sql = "INSERT INTO password_resets (email, token, expires_at) VALUES ('$email', '$token', '$expires')";
        
        if(mysqli_query($conn, $insert_sql)) {
            // 4. 发送邮件
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                
                // --- 邮箱配置 ---
                $mail->Username   = 'lovebridge1201@gmail.com'; 
                $mail->Password   = 'odaj iwrz gfrt vven';      
                // ------------------

                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom('lovebridge1201@gmail.com', 'Love Bridge Admin');
                $mail->addAddress($email);

                $base_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
                $reset_link = $base_url . "/admin_reset_password.php?token=" . $token . "&email=" . $email;

                $mail->isHTML(true);
                $mail->Subject = 'Reset Your Password - Love Bridge';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                        <div style='background-color: white; padding: 20px; border-radius: 10px; max-width: 500px; margin: auto;'>
                            <h2 style='color: #D97706;'>Password Reset Request</h2>
                            <p>Click the button below to reset your password:</p>
                            <a href='$reset_link' style='display: inline-block; padding: 10px 20px; background-color: #D97706; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a>
                            <p style='margin-top: 20px; font-size: 12px; color: #888;'>Expires in 1 hour.</p>
                        </div>
                    </div>
                ";

                $mail->send();
                $alertType = "success";
                $alertMsg = "Reset link has been sent to your email!";
            } catch (Exception $e) {
                $alertType = "error";
                $alertMsg = "Mailer Error: {$mail->ErrorInfo}";
            }
        }
    } else {
        $alertType = "error";
        $alertMsg = "Email not found in our system.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        
        body { background-color: #FFF6E8; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .container { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 400px; text-align: center; }
        h2 { color: #5D4037; margin-bottom: 10px; }
        p { color: #888; font-size: 14px; margin-bottom: 25px; }
        
        .input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        
        .input-group input { width: 100%; padding: 12px 15px 12px 45px; border: 1px solid #eee; border-radius: 8px; outline: none; background: #fafafa; }
        .input-group input:focus { border-color: #D97706; background: #fff; }
        
        button { width: 100%; padding: 12px; background: #D97706; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 16px; transition: 0.3s; }
        button:hover { opacity: 0.9; }
        
        .back-link { display: block; margin-top: 20px; color: #666; text-decoration: none; font-size: 13px; }
        .back-link:hover { color: #D97706; }
        
        /* Floating Alert Styles */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; animation: slideIn 0.3s; }
        .floating-alert div { line-height: 1.6; font-size: 14px; }
        .floating-alert i { margin-top: 4px; }
        .floating-alert-success { background: white; color: #28a745; border-left: 4px solid #28a745; }
        .floating-alert-danger { background: white; color: #dc3545; border-left: 4px solid #dc3545; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>
    
    <?php if ($alertMsg != ""): ?>
    <div class="floating-alert <?php echo ($alertType == 'success') ? 'floating-alert-success' : 'floating-alert-danger'; ?>" id="systemAlert">
        <i class="fas <?php echo ($alertType == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <div><?php echo $alertMsg; ?></div>
    </div>
    <script>
        setTimeout(() => { 
            const alert = document.getElementById('systemAlert'); 
            if(alert) alert.style.display = 'none'; 
        }, 5000);
    </script>
    <?php endif; ?>

    <div class="container">
        <i class="fas fa-lock" style="font-size: 40px; color: #D97706; margin-bottom: 20px;"></i>
        <h2>Forgot Password?</h2>
        <p>Enter your email and we'll send you a reset link.</p>
        
        <form method="POST">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Enter your email address" required>
            </div>
            <button type="submit" name="reset_request">Send Reset Link</button>
        </form>

        <a href="admin_login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</body>
</html>