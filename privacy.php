<?php
include 'dataconnection.php';
include 'header_function.php';

$policy = null; // 初始化为 null

// 准备查询
$stmt = $conn->prepare("SELECT Page_Content, Last_Updated FROM system_pages WHERE Page_Key = 'privacy_policy'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $db_policy = $result->fetch_assoc();
    $stmt->close();

    if ($db_policy) {
        $policy = [
            'version' => '1.0', // 数据库没有版本号字段，默认 1.0
            'content' => $db_policy['Page_Content'],
            'effective_date' => $db_policy['Last_Updated']
        ];
    }
}

// 如果数据库里没找到，或者查询失败，使用默认内容
if (!$policy) {
    $policy = [
        'version' => '1.0',
        'content' => '<h2>Love Bridge Donation Platform - Privacy Policy</h2>
        <p><strong>Effective Date:</strong> ' . date('F j, Y') . '</p>
        
        <h3>1. Information We Collect</h3>
        <p>We collect information you provide directly, such as when you make a donation, including:</p>
        <ul>
            <li>Personal identification information (name, email address, phone number)</li>
            <li>Payment information (credit card details, billing address)</li>
            <li>Donation history and preferences</li>
            <li>Communication preferences</li>
        </ul>
        
        <h3>2. How We Use Your Information</h3>
        <p>We use the information we collect to:</p>
        <ul>
            <li>Process your donations and send receipts</li>
            <li>Send updates about our charitable programs</li>
            <li>Improve our platform and services</li>
            <li>Comply with legal obligations</li>
            <li>Respond to your inquiries</li>
        </ul>
        
        <h3>3. Information Sharing</h3>
        <p>We do not sell, trade, or rent your personal information to third parties. We may share information with:</p>
        <ul>
            <li>Payment processors to complete transactions</li>
            <li>Service providers who assist our operations</li>
            <li>Legal authorities when required by law</li>
        </ul>
        
        <h3>4. Data Security</h3>
        <p>We implement security measures to protect your information, including encryption and secure servers. However, no method of transmission over the Internet is 100% secure.</p>
        
        <h3>5. Your Rights and Choices</h3>
        <p>You have the right to:</p>
        <ul>
            <li>Access the personal information we hold about you</li>
            <li>Correct inaccurate information</li>
            <li>Request deletion of your information</li>
            <li>Opt out of promotional communications</li>
            <li>Withdraw consent where applicable</li>
        </ul>
        
        <h3>6. Cookies and Tracking</h3>
        <p>We use cookies to improve your experience on our platform. You can control cookie settings through your browser.</p>
        
        <h3>7. Changes to This Policy</h3>
        <p>We may update this policy periodically. We will notify you of material changes via email or through our platform.</p>',
        'effective_date' => date('Y-m-d')
    ];
}

include 'header_UI.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | Love Bridge Donation Platform</title>
    <link rel="stylesheet" href="style.css">
    <style>

        * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
    color: #333;
    background-color: #f9f9f9;
}

.red-header {
    background-color: #d32f2f; /* Primary red */
    color: white;
    padding: 20px 0;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.red-header h1 {
    font-size: 2.5rem;
    margin-bottom: 10px;
    font-weight: 700;
}

.red-header p {
    font-size: 1.1rem;
    opacity: 0.9;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.content-box {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
    padding: 40px;
    margin: 40px auto;
    max-width: 1000px;
}

.content-box h2 {
    color: #d32f2f; /* Red for headings */
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #ffcdd2; /* Light red border */
}

.content-box h3 {
    color: #b71c1c; /* Darker red for subheadings */
    margin: 25px 0 15px 0;
}

.content-box p {
    margin-bottom: 15px;
    color: #444;
}

.content-box ul, .content-box ol {
    margin-left: 25px;
    margin-bottom: 20px;
}

.content-box li {
    margin-bottom: 10px;
}

.highlight {
    background-color: #ffebee; /* Very light red background */
    border-left: 4px solid #d32f2f;
    padding: 15px;
    margin: 20px 0;
}

.meta-info {
    background-color: #f5f5f5;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 30px;
    font-size: 0.9rem;
}

.meta-info strong {
    color: #d32f2f;
}

.navigation {
    display: flex;
    justify-content: center;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.btn {
    display: inline-block;
    background-color: #d32f2f;
    color: white;
    padding: 12px 30px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    margin: 0 10px;
}

.btn:hover {
    background-color: #b71c1c;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.btn-outline {
    background-color: transparent;
    color: #d32f2f;
    border: 2px solid #d32f2f;
}

.btn-outline:hover {
    background-color: #d32f2f;
    color: white;
}

.footer-info {
    text-align: center;
    padding: 20px;
    color: #777;
    font-size: 0.9rem;
    background-color: #f5f5f5;
    margin-top: 40px;
}

.footer-info a {
    color: #d32f2f;
    text-decoration: none;
}

.footer-info a:hover {
    text-decoration: underline;
}

/* Responsive design */
@media (max-width: 768px) {
    .content-box {
        padding: 25px;
        margin: 20px auto;
    }
    
    .red-header h1 {
        font-size: 2rem;
    }
    
    .navigation {
        flex-direction: column;
        align-items: center;
    }
    
    .btn {
        margin: 10px 0;
        width: 100%;
        text-align: center;
    }
}
        /* Additional inline styles for privacy page */
        .data-types {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 20px 0;
        }
        
        .data-card {
            flex: 1;
            min-width: 200px;
            background-color: #ffebee;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #d32f2f;
        }
        
        .data-card h4 {
            color: #b71c1c;
            margin-bottom: 10px;
        }
        
        .privacy-highlight {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            margin: 25px 0;
            border-radius: 5px;
        }
        
        .privacy-highlight h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .contact-box {
            background-color: #f5f5f5;
            padding: 25px;
            border-radius: 8px;
            margin-top: 30px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="red-header">
        <div class="container">
            <h1>Love Bridge Donation Platform</h1>
            <p>Your privacy is our priority</p>
        </div>
    </div>
    
    <div class="container">
        <div class="content-box">
            <div class="meta-info">
                <p><strong>Document Version:</strong> <span class="effective-date"><?php echo htmlspecialchars($policy['version']); ?></span> <span class="version-badge">v<?php echo htmlspecialchars($policy['version']); ?></span></p>
                <p><strong>Effective Date:</strong> <?php echo date('F j, Y', strtotime($policy['effective_date'])); ?></p>
                <p><strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($policy['effective_date'])); ?></p>
            </div>
            
            <h2>Privacy Policy <span class="version-badge">Version <?php echo htmlspecialchars($policy['version']); ?></span></h2>
            
            <?php 
            // Display privacy policy content
            echo $policy['content'];
            ?>
            
            <div class="privacy-highlight">
                <h4>Your Privacy Rights</h4>
                <p>At Love Bridge, we believe in transparency and giving you control over your personal information. You have the right to access, correct, or delete your personal data. You can also object to or restrict certain processing activities.</p>
                <p>To exercise any of these rights, please contact our Privacy Officer using the contact information provided above.</p>
            </div>
            
            <div class="data-types">
                <div class="data-card">
                    <h4>Information We Collect</h4>
                    <ul>
                        <li>Contact details</li>
                        <li>Payment information</li>
                        <li>Donation history</li>
                        <li>Communication preferences</li>
                    </ul>
                </div>
                
                <div class="data-card">
                    <h4>How We Use Information</h4>
                    <ul>
                        <li>Process donations</li>
                        <li>Send receipts</li>
                        <li>Provide updates</li>
                        <li>Improve our services</li>
                    </ul>
                </div>
                
                <div class="data-card">
                    <h4>Information Protection</h4>
                    <ul>
                        <li>Encryption</li>
                        <li>Secure servers</li>
                        <li>Access controls</li>
                        <li>Regular audits</li>
                    </ul>
                </div>
            </div>
            
            <div class="contact-box">
                <h3>Contact Our Privacy Officer</h3>
                <p>If you have any questions, concerns, or requests regarding your privacy or this Privacy Policy, please contact our dedicated Privacy Officer:</p>
                <p><strong>Email:</strong> lovebridge1201@gmail.com</p>
                <p><strong>Phone:</strong> +60 11-1119 0233</p>
                
                <p>We typically respond to privacy inquiries within 3-5 business days.</p>
            </div>
        </div>
        
        <div class="navigation">
            <a href="terms&condition.php" class="btn">View Terms & Conditions</a>
            <a href="Homepage.php" class="btn btn-outline">Return to Homepage</a>
        </div>
    </div>
    
    <div class="footer-info">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Love Bridge Donation Platform. All rights reserved.</p>
            <p>Love Bridge is committed to protecting your privacy and personal information.</p>
            <p><a href="terms&condition.php">Terms & Conditions</a> | <a href="privacy.php">Privacy Policy</a> | <a href="contact.php">Contact Us</a></p>
        </div>
    </div>
     <?php include 'footer.php'; ?>
</body>
</html>