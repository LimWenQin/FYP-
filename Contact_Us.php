<?php 
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI.php';

// Check if user is logged in
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Donor Platform</title>
    <style>
        :root {
            --primary: #F6B8B8;
            --secondary: #FFF5E4;
            --text: #4A4A4A;
            --light-text: #777777;
            --white: #FFFFFF;
            --shadow: rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: var(--secondary);
            color: var(--text);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .header {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            align-items: center;
            background-color: var(--primary);
            padding: 20px 50px;
            box-shadow: 0 2px 6px var(--shadow);
            gap: 20px;
        }
        
        .logo {
            font-weight: bold;
            font-size: 36px;
            color: var(--text);
        }
        
        .header-center {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background-color: var(--white);
            border-radius: 4px;
            padding: 5px 10px;
            width: 70%;
        }
        
        .search-box input {
            border: none;
            background: transparent;
            padding: 8px;
            width: 100%;
            outline: none;
        }
        
        .donate-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            white-space: nowrap;
        }
        
        .donate-btn:hover {
            background-color: #c0392b;
        }
        
        .header-right {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
        }
        
        .function-links {
            display: flex;
            gap: 15px;
        }
        
        .function-links a {
            text-decoration: none;
            font-size: 18px;
            color: var(--text);
            font-weight: bold;
            transition: opacity 0.3s;
            white-space: nowrap;
        }
        
        .function-links a:hover {
            opacity: 0.8;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-name {
            font-weight: bold;
        }
        
        .login-btn {
            background-color: var(--white);
            color: var(--text);
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .login-btn:hover {
            background-color: #f0a8a8;
        }
        
        .conatct-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
            flex: 1;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-title h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .contact-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .contact-info {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            padding: 30px;
            margin:0 80px 0 100px;
        }
        
        .contact-info h3 {
            margin-bottom: 20px;
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }
        
        .contact-details {
            margin-bottom: 20px;
        }
        
        .contact-details p {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .contact-details i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .contact-form {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            padding: 30px;
            margin:0 80px 0 100px;
        }
        
        .contact-form h3 {
            margin-bottom: 20px;
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        
        .submit-btn {
            background-color: var(--primary);
            color: var(--text);
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #f0a8a8;
        }
        
        @media (max-width: 1024px) {
            .header {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: auto auto;
                gap: 15px;
            }
            
            .logo {
                grid-column: 1;
            }
            
            .header-center {
                grid-column: 1 / span 2;
                grid-row: 2;
                width: 100%;
            }
            
            .header-right {
                grid-column: 2;
                justify-content: flex-end;
            }
            
            .contact-content {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
                gap: 15px;
            }
            
            .logo {
                font-size: 28px;
                text-align: center;
                grid-column: 1;
            }
            
            .header-center {
                grid-column: 1;
                grid-row: 2;
            }
            
            .header-right {
                grid-column: 1;
                grid-row: 3;
                justify-content: center;
                flex-direction: column;
                gap: 10px;
            }
            
            .function-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .contactcontainer {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
   

    <div class="contact-container">
        <div class="page-title">
            <h1>Contact Us</h1>
            <p>We'd love to hear from you. Get in touch with us!</p>
        </div>
        
        <div class="contact-content">
            <div class="contact-info">
                <h3>Contact Information</h3>
                <div class="contact-details">
                    <p><i>📧</i> Email: support@donorplatform.com</p>
                    <p><i>📞</i> Phone: +1 (555) 123-4567</p>
                    <p><i>📍</i> Address: 123 Charity Street, Compassion City, CC 12345</p>
                    <p><i>🕒</i> Business Hours: Monday - Friday, 9:00 AM - 6:00 PM</p>
                </div>
                <h3>Frequently Asked Questions</h3>
                <p>Before contacting us, you might find answers to your questions in our <a href="faq.php">FAQ section</a>.</p>
            </div>
            
            <div class="contact-form">
                <h3>Send Us a Message</h3>
                <form action="submit_contact.php" method="POST">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Your Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Your Message</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <button type="submit" class="submit-btn">Send Message</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Include Footer -->
    <?php include 'footer.php'; ?>
</body>
</html>