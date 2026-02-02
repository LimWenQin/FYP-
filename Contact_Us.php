<?php
// contact_us.php
session_start();
include 'dataconnection.php';
include 'header_function.php';

// --- 1. 获取动态联系信息 (Contact Info) ---
$settings_query = "SELECT * FROM contact_settings LIMIT 1";
$settings_result = $conn->query($settings_query);
$site_info = $settings_result->fetch_assoc();

if (!$site_info) {
    $site_info = [
        'Address' => 'Address not set',
        'Phone' => 'Not set',
        'Whatsapp_Link' => '#',
        'Email' => 'admin@example.com',
        'Working_Hours' => 'Not set',
        'Map_Embed_Src' => ''
    ];
}

// --- 2. 处理表单提交 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $title = htmlspecialchars(trim($_POST['title'])); 
    $message = htmlspecialchars(trim($_POST['message']));
    
    $errors = [];
    
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    if (empty($title)) $errors[] = "Title is required";
    if (empty($message)) $errors[] = "Message is required";
    
    // --- 3. 处理文件上传 ---
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
        $filename = $_FILES['attachment']['name'];
        $filetype = $_FILES['attachment']['type'];
        $filesize = $_FILES['attachment']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid file type. Only JPG, PNG, PDF, DOC allowed.";
        }
        if ($filesize > 5 * 1024 * 1024) {
            $errors[] = "File size is too large. Max limit is 5MB.";
        }

        if (empty($errors)) {
            $upload_dir = 'uploads/attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = uniqid() . "." . $ext;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destination)) {
                $attachment_path = $destination;
            } else {
                $errors[] = "Failed to upload file.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO contact_messages 
            (Name, Email, Phone, Title, Message, Attachment, Status, Created_At) 
            VALUES (?, ?, ?, ?, ?, ?, 'New', NOW())");
        
        $stmt->bind_param("ssssss", $name, $email, $phone, $title, $message, $attachment_path);
        
        if ($stmt->execute()) {
            $contact_id = $conn->insert_id;
            
            // --- 管理员通知 ---
            $admin_result = $conn->query("SELECT Admin_ID FROM admin WHERE Admin_Status = 'Active'");
            $notify_stmt = $conn->prepare("INSERT INTO admin_notifications 
                (Message, Contact_ID, Type, Is_Read, Created_At) 
                VALUES (?, ?, ?, 0, NOW())");

            $type_val = 'New_Contact';

            while ($admin = $admin_result->fetch_assoc()) {
                $notify_message = "New contact form submission from " . $name;
                $notify_stmt->bind_param("sis", $notify_message, $contact_id, $type_val);
                $notify_stmt->execute();
            }
            $notify_stmt->close();
            
            // --- 邮件日志 ---
            $admin_email = "admin@lovebridge.org.my";
            $email_title = "New Contact: " . $title;
            $email_body = "Name: $name\nEmail: $email\nPhone: $phone\nTitle: $title\nMessage:\n$message";
            if($attachment_path) {
                $email_body .= "\n\n(User attached a file: $attachment_path)";
            }
            
            $email_stmt = $conn->prepare("INSERT INTO email_logs 
                (To_Email, Title, Content, Status, Sent_At, Created_At) 
                VALUES (?, ?, ?, 'Sent', NOW(), NOW())");
            $email_stmt->bind_param("sss", $admin_email, $email_title, $email_body);
            $email_stmt->execute();
            $email_stmt->close();
            
            $_SESSION['success_message'] = "Thank you! We received your message.";
            
            $name = $email = $phone = $title = $message = '';
            
            header("Location: contact_us.php");
            exit();
            
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
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; line-height: 1.6; background-color: #f8f9fa; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .contact-header { background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); color: white; padding: 60px 0; text-align: center; margin-bottom: 40px; }
        .contact-header h1 { font-size: 3rem; margin-bottom: 20px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
        .contact-header p { font-size: 1.2rem; max-width: 600px; margin: 0 auto; opacity: 0.9; }
        .contact-content { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 60px; }
        .contact-info, .contact-form, .map-section { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .contact-info h2, .contact-form h2, .map-section h2 { color: #d32f2f; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 2px solid #ffcdd2; }
        .info-item { display: flex; align-items: flex-start; margin-bottom: 25px; }
        .info-icon { background: #ffebee; color: #d32f2f; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 1.2rem; flex-shrink: 0; }
        .info-details h4 { color: #d32f2f; margin-bottom: 5px; }
        
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        .form-control { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 1rem; transition: all 0.3s ease; }
        .form-control:focus { border-color: #d32f2f; outline: none; box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1); }
        textarea.form-control { min-height: 150px; resize: vertical; }
        
        /* --- 文件上传容器样式 --- */
        .upload-container {
            border: 2px dashed #ccc; /* 默认虚线 */
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: block; /* 让label像div一样显示 */
        }

        .upload-container:hover {
            border-color: #d32f2f;
            background-color: #fff0f0;
        }

        /* 选中文件后的容器样式 (JS添加) */
        .upload-container.has-file {
            border-style: solid; /* 变成实线 */
            border-color: #d32f2f;
            background-color: #fff5f5;
        }

        .upload-container input[type="file"] {
            display: none; 
        }

        /* 默认视图元素 */
        .upload-icon {
            font-size: 48px;
            color: #999;
            margin-bottom: 15px;
            display: block;
            margin-left: auto; /* 居中 */
            margin-right: auto;
        }
        
        /* 选中后的图标颜色 */
        .has-file .upload-icon {
            color: #d32f2f;
        }

        .upload-text-main {
            font-size: 16px;
            color: #333;
            font-weight: 600;
            display: block;
            margin-bottom: 15px;
        }
        
        /* 选中后的主文字变色 */
        .has-file .upload-text-main {
            color: #d32f2f;
        }

        .upload-text-sub {
            font-size: 13px;
            color: #777;
            line-height: 1.5;
            display: block;
            border-top: 1px solid #eee;
            padding-top: 10px;
            margin-top: 10px;
        }

        /* 选中文件时，隐藏默认视图，显示文件视图 */
        #file-view {
            display: none;
        }
        /* --- 样式结束 --- */

        .btn-submit { background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); color: white; padding: 15px 30px; border: none; border-radius: 6px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; width: 100%; }
        .btn-submit:hover { background: linear-gradient(135deg, #b71c1c 0%, #8b0000 100%); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(211, 47, 47, 0.4); }
        
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .map-container { height: 400px; width: 100%; border-radius: 8px; overflow: hidden; }
        
        @media (max-width: 768px) {
            .contact-content { grid-template-columns: 1fr; }
            .contact-header h1 { font-size: 2rem; }
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
                        <p>
      
        Level 12, Menara Love Bridge, Jalan Charity,<br>
        50450 Kuala Lumpur, Malaysia
    </p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">📞</div>
                    <div class="info-details">
                        <h4>Phone Number</h4>
                        <a href="<?php echo $site_info['Whatsapp_Link']; ?>" target="_blank" style="text-decoration: none; color: inherit;">
                            <?php echo $site_info['Phone']; ?>
                        </a>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">✉️</div>
                    <div class="info-details">
                        <h4>Email Address</h4>
                        <a href="mailto:<?php echo $site_info['Email']; ?>"><?php echo $site_info['Email']; ?></a>
                    </div>
                </div>
               
            </div>

            <div class="contact-form">
                <h2>Send Us a Message</h2>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Attach File (Optional)</label>
                        
                        <label for="attachment" class="upload-container" id="upload-label-container">
                            
                            <input type="file" id="attachment" name="attachment" onchange="toggleUploadView(this)">
                            
                            <div id="default-view">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <span class="upload-text-main">Click here to upload file</span>
                                <span class="upload-text-sub">
                                    Supported formats: JPG, PNG, PDF, DOC.<br>
                                    Max size: 5MB
                                </span>
                            </div>

                            <div id="file-view">
                                <i class="fas fa-file-alt upload-icon" style="color: #d32f2f;"></i>
                                <span id="display-filename" class="upload-text-main" style="word-break: break-all;"></span>
                                <span class="upload-text-sub" style="color: #d32f2f; border-top: none;">
                                    (Click to change file)
                                </span>
                            </div>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
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
                <?php if (!empty($site_info['Map_Embed_Src'])): ?>
                     <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3799.865088653103!2d102.27353867474017!3d2.249493497730707!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d1e56b9710cf4b%3A0x66b6b12b75469278!2sMultimedia%20University!5e1!3m2!1sen!2smy!4v1767627412112!5m2!1sen!2smy"
                    width="100%"
                    height="100%"
                    style="border:0;"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"></iframe>
                <?php else: ?>
                    <p style="text-align:center; padding: 100px;">Map not available.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
    <?php include 'footer.php'; ?>

    <script>
        // 这个函数负责切换视图
        function toggleUploadView(input) {
            var container = document.getElementById('upload-label-container');
            var defaultView = document.getElementById('default-view');
            var fileView = document.getElementById('file-view');
            var filenameText = document.getElementById('display-filename');

            if (input.files && input.files.length > 0) {
                // 如果有文件：
                // 1. 获取文件名
                filenameText.textContent = input.files[0].name;
                
                // 2. 隐藏默认云朵，显示文件信息
                defaultView.style.display = 'none';
                fileView.style.display = 'block';

                // 3. 给容器加上实线边框样式
                container.classList.add('has-file');
            } else {
                // 如果用户取消了选择：
                // 恢复原状
                defaultView.style.display = 'block';
                fileView.style.display = 'none';
                container.classList.remove('has-file');
            }
        }
    </script>
</body>
</html>