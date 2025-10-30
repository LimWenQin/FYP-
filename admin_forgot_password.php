<?php
// forgot_password.php - Forgot Password Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Donation Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #fff5e4, #f6b8b8);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .forgot-password-container {
            max-width: 500px;
            width: 90%;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .logo {
            font-size: 24px;
            color: #f28585;
            margin-bottom: 20px;
            font-weight: bold;
        }

        h2 {
            color: #4a4a4a;
            margin-bottom: 20px;
            font-weight: 600;
        }

        p {
            color: #7f8c8d;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .contact-info {
            background: #fff5e4;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .contact-item:last-child {
            margin-bottom: 0;
        }

        .contact-icon {
            font-size: 20px;
            color: #f28585;
            margin-right: 15px;
            width: 30px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #f28585;
            text-decoration: none;
            padding: 10px 20px;
            border: 2px solid #f28585;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: #f28585;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(242, 133, 133, 0.4);
        }

        .background-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(246, 184, 184, 0.1);
        }

        .circle:nth-child(1) {
            width: 200px;
            height: 200px;
            top: 10%;
            left: 10%;
        }

        .circle:nth-child(2) {
            width: 150px;
            height: 150px;
            bottom: 15%;
            right: 10%;
        }

        .support-hours {
            margin-top: 20px;
            font-size: 14px;
            color: #95a5a6;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .forgot-password-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Background decorative elements -->
    <div class="background-elements">
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
    
    <div class="forgot-password-container">
        <div class="logo">Donation Management System</div>
        <h2>Forgot Password</h2>
        <p>If you've forgotten your password, please contact the system administrator to reset your account.</p>
        
        <div class="contact-info">
            <div class="contact-item">
                <div class="contact-icon">
                    <ion-icon name="mail"></ion-icon>
                </div>
                <div>
                    <strong>Email Address</strong><br>
                    admin@donationsystem.com
                </div>
            </div>
            <div class="contact-item">
                <div class="contact-icon">
                    <ion-icon name="call"></ion-icon>
                </div>
                <div>
                    <strong>Phone Number</strong><br>
                    +60 12-345 6789
                </div>
            </div>
            <div class="contact-item">
                <div class="contact-icon">
                    <ion-icon name="location"></ion-icon>
                </div>
                <div>
                    <strong>Office Address</strong><br>
                    Donation Management Center, Kuala Lumpur, Malaysia
                </div>
            </div>
        </div>
        
        <div class="support-hours">
            <strong>Support Hours:</strong> Monday - Friday 9:00 AM - 6:00 PM
        </div>
        
        <a href="admin_login.php" class="back-link">
            <ion-icon name="arrow-back"></ion-icon>
            Back to Login
        </a>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>