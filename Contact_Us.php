<?php
// contact_us.php
session_start();
include 'dataconnection.php';
include 'header_function.php';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $title = htmlspecialchars(trim($_POST['title']));
    $message = htmlspecialchars(trim($_POST['message']));
    
    // 验证必填字段
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    if (empty($errors)) {
        // 插入到 contact_messages 表
        $stmt = $conn->prepare("INSERT INTO contact_messages 
            (Name, Email, Phone, Title, Message, Status, Created_At) 
            VALUES (?, ?, ?, ?, ?, 'New', NOW())");
        $stmt->bind_param("sssss", $name, $email, $phone, $title, $message);
        
       if ($stmt->execute()) {
            // 获取最后插入的ID
            $contact_id = $conn->insert_id;
            
            // 获取所有活跃的管理员
            $admin_result = $conn->query("SELECT Admin_ID FROM admin WHERE Admin_Status = 'Active'");
            
            // 1. 准备 SQL：只包含 Message, Contact_ID, Type (去掉了 Reference_Type)
            $notify_stmt = $conn->prepare("INSERT INTO admin_notifications 
                (Message, Contact_ID, Type, Is_Read, Created_At) 
                VALUES (?, ?, ?, 0, NOW())");

            // 2. 定义变量 (bind_param 必须传变量，不能传字符串)
            $type_val = 'New_Contact';

            while ($admin = $admin_result->fetch_assoc()) {
                $notify_message = "New contact form submission from " . $name;
                
                // 3. 绑定参数：对应 SQL 中的三个问号 (?, ?, ?) -> (string, int, string)
                $notify_stmt->bind_param("sis", $notify_message, $contact_id, $type_val);
                
                $notify_stmt->execute();
            }
            $notify_stmt->close();
            
            // 发送邮件通知
            $admin_email = "admin@lovebridge.org.my";
            $email_title = "New Contact Form Submission: " . $title;
            $email_body = "A new contact form has been submitted:\n\n";
            $email_body .= "Name: $name\n";
            $email_body .= "Email: $email\n";
            $email_body .= "Phone: " . ($phone ?: 'Not provided') . "\n";
            $email_body .= "Title: $title\n";
            $email_body .= "Message:\n$message\n\n";
            $email_body .= "Login to admin panel to view details.";
            
            // 记录邮件日志
            $email_stmt = $conn->prepare("INSERT INTO email_logs 
                (To_Email, Title, Content, Status, Sent_At, Created_At) 
                VALUES (?, ?, ?, 'Sent', NOW(), NOW())");
            $email_stmt->bind_param("sss", $admin_email, $email_title, $email_body);
            $email_stmt->execute();
            $email_stmt->close();
            
            // 发送邮件
            //mail($admin_email, $email_title, $email_body);
            
            $_SESSION['success_message'] = "Thank you for contacting us! We'll get back to you soon.";
            
            // 清除表单
            $name = $email = $phone = $title = $message = '';
            
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

include 'header_UI.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Love Bridge Foundation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            background-color: #f8f9fa;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .contact-header {
            background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
            color: white;
            padding: 60px 0;
            text-align: center;
            margin-bottom: 40px;
        }

        .contact-header h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .contact-header p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.9;
        }

        .contact-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        .contact-info {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .contact-info h2 {
            color: #d32f2f;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ffcdd2;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .info-icon {
            background: #ffebee;
            color: #d32f2f;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }

        .info-details h4 {
            color: #d32f2f;
            margin-bottom: 5px;
        }

        .contact-form {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .contact-form h2 {
            color: #d32f2f;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ffcdd2;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #d32f2f;
            outline: none;
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .btn-submit {
            background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #b71c1c 0%, #8b0000 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(211, 47, 47, 0.4);
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .map-section {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }

        .map-section h2 {
            color: #d32f2f;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ffcdd2;
        }

        .map-container {
            height: 400px;
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .contact-content {
                grid-template-columns: 1fr;
            }
            
            .contact-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="contact-header">
        <div class="container">
            <h1>Get in Touch</h1>
            <p>We'd love to hear from you. Whether you have questions about donations, want to volunteer, or need assistance, our team is here to help.</p>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="contact-content">
            <div class="contact-info">
                <h2>Contact Information</h2>
                
                <div class="info-item">
                    <div class="info-icon">📍</div>
                    <div class="info-details">
                        <h4>Our Headquarters</h4>
                        <p>Level 11, Menara Love Bridge<br>Jalan Charity, 50450<br>Melaka, Malaysia</p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">📞</div>
                    <div class="info-details">
                        <h4>Phone Number</h4>
                      <a href="https://wa.me/601111190233" target="_blank">
                    
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  
                    +60 11-1119 0233
                </a><br>Monday - Friday, 9:00 AM - 6:00 PM</p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">✉️</div>
                    <div class="info-details">
                        <h4>Email Address</h4>
                        <a href="mailto:lovebridge1201@gmail.com">lovebridge1201@gmail.com</a>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">⏰</div>
                    <div class="info-details">
                        <h4>Working Hours</h4>
                        <p>Monday - Friday: 9:00 AM - 6:00 PM<br>Saturday: 9:00 AM - 1:00 PM<br>Sunday: Closed</p>
                    </div>
                </div>
            </div>

            <div class="contact-form">
                <h2>Send Us a Message</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" class="form-control" required><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">Send Message</button>
                </form>
            </div>
        </div>

        <div class="map-section">
            <h2>Find Us Here</h2>
            <div class="map-container">
                <!-- Google Map Embed -->
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3799.865088653103!2d102.27353867474017!3d2.249493497730707!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d1e56b9710cf4b%3A0x66b6b12b75469278!2sMultimedia%20University!5e1!3m2!1sen!2smy!4v1767627412112!5m2!1sen!2smy" 
                    width="100%" 
                    height="100%" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>

       
    </div>
     <?php include 'footer.php'; ?>
</body>
</html>