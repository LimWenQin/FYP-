<?php
include 'dataconnection.php';
include 'header_function.php';

$terms = null;

// Prepare query
$stmt = $conn->prepare("SELECT Page_Content, Last_Updated FROM system_pages WHERE Page_Key = 'terms_condition'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $db_terms = $result->fetch_assoc();
    $stmt->close();

    if ($db_terms) {
        $terms = [
            'version' => '1.0',
            'content' => $db_terms['Page_Content'],
            'effective_date' => $db_terms['Last_Updated']
        ];
    }
}

// If no data found in database, use default content
if (!$terms) {
    $terms = [
        'version' => '2.1',
        'content' => 'DEFAULT_CONTENT', // Placeholder, will be replaced with our structured content
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
    <title>Terms & Conditions | Love Bridge Donation Platform</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Love Bridge Red Theme Styles */
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

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .content-box {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            padding: 40px;
            margin: 30px auto;
        }

        .content-box h2 {
            color: #d32f2f;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ffcdd2;
            text-align: center;
        }

        /* Terms containers */
        .term-container {
            background-color: #fff;
            border-left: 4px solid #d32f2f;
            border-radius: 5px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .term-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .term-number {
            display: inline-block;
            background-color: #d32f2f;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            text-align: center;
            line-height: 36px;
            font-weight: bold;
            margin-right: 15px;
            font-size: 1.1rem;
        }

        .term-title {
            color: #b71c1c;
            display: inline-block;
            font-size: 1.3rem;
            margin-bottom: 15px;
        }

        .term-content {
            color: #444;
            line-height: 1.7;
            margin-top: 15px;
            padding-left: 10px;
            border-left: 2px solid #ffcdd2;
            padding-left: 15px;
        }

        .term-content ul {
            margin-left: 20px;
            margin-top: 10px;
        }

        .term-content li {
            margin-bottom: 8px;
        }

        .highlight-box {
            background-color: #ffebee;
            border-left: 4px solid #d32f2f;
            padding: 20px;
            margin: 30px 0;
            border-radius: 5px;
        }

        .highlight-box strong {
            color: #b71c1c;
        }

        .meta-info {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .meta-item {
            margin: 5px 15px 5px 0;
        }

        .meta-item strong {
            color: #d32f2f;
        }

        .version-badge {
            display: inline-block;
            background-color: #ffcdd2;
            color: #b71c1c;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            margin-left: 10px;
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
            
            .term-container {
                padding: 20px;
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
            
            .meta-info {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="red-header">
        <div class="main-container">
            <h1>Love Bridge Donation Platform</h1>
            <p>Connecting hearts to make a difference</p>
        </div>
    </div>
    
    <div class="main-container">
        <div class="content-box">
            <div class="meta-info">
                <div class="meta-item">
                    <strong>Document Version:</strong> <?php echo htmlspecialchars($terms['version']); ?> <span class="version-badge">v<?php echo htmlspecialchars($terms['version']); ?></span>
                </div>
                <div class="meta-item">
                    <strong>Effective Date:</strong> <?php echo date('F j, Y', strtotime($terms['effective_date'])); ?>
                </div>
                <div class="meta-item">
                    <strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($terms['effective_date'])); ?>
                </div>
            </div>
            
            <h2>Terms & Conditions <span class="version-badge">Version <?php echo htmlspecialchars($terms['version']); ?></span></h2>
            
            <!-- Donation Policy Section with 10+ terms -->
            <h3 style="color: #b71c1c; margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 1px solid #ffcdd2;">Donation Terms & Conditions</h3>
            
            <!-- Term 1: Donation Eligibility -->
            <div class="term-container">
                <div class="term-number">1</div>
                <div class="term-title">Donation Eligibility</div>
                <div class="term-content">
                    <p>Donors must be at least 18 years of age or have parental/guardian consent to make donations. By making a donation, you confirm that you have the legal capacity to enter into this agreement and that all information provided is accurate and complete.</p>
                    <p>Love Bridge reserves the right to refuse any donation that does not comply with our policies or applicable laws.</p>
                </div>
            </div>
            
            <!-- Term 2: Donation Types -->
            <div class="term-container">
                <div class="term-number">2</div>
                <div class="term-title">Types of Donations Accepted</div>
                <div class="term-content">
                    <p>Love Bridge accepts the following types of donations:</p>
                    <ul>
                        <li>One-time donations (single contributions)</li>
                        <li>Recurring donations (monthly, quarterly, or annual)</li>
                        <li>In-kind donations (subject to prior approval)</li>
                        <li>Corporate matching gifts</li>
                        <li>Legacy or planned giving</li>
                    </ul>
                    <p>All donations must be made through our secure payment gateway. Cash donations are only accepted at authorized Love Bridge events.</p>
                </div>
            </div>
            
            <!-- Term 3: Donation Allocation -->
            <div class="term-container">
                <div class="term-number">3</div>
                <div class="term-title">Donation Allocation and Use</div>
                <div class="term-content">
                    <p>Unless specifically designated for a particular program or project, donations will be allocated to Love Bridge's general fund to support our charitable activities where needed most.</p>
                    <p>Designated donations will be used as specified, provided the project remains active and feasible. If circumstances prevent the designated use, Love Bridge will redirect funds to similar charitable purposes and notify donors whenever possible.</p>
                </div>
            </div>
            
            <!-- Term 4: Payment Processing -->
            <div class="term-container">
                <div class="term-number">4</div>
                <div class="term-title">Payment Processing</div>
                <div class="term-content">
                    <p>All donations are processed through secure payment gateways. Love Bridge does not store complete credit card information on our servers.</p>
                    <p>Transaction processing fees may apply and are typically deducted from the donation amount. Donors have the option to cover these fees to ensure 100% of their intended donation amount supports our programs.</p>
                </div>
            </div>
            
            <!-- Term 5: Recurring Donations -->
            <div class="term-container">
                <div class="term-number">5</div>
                <div class="term-title">Recurring Donations</div>
                <div class="term-content">
                    <p>Recurring donations will continue according to the selected frequency until cancelled by the donor. Donors may modify or cancel recurring donations at any time through their account settings or by contacting our donor support team.</p>
                    <p>A minimum 3-day notice is required to process cancellation requests before the next scheduled donation date.</p>
                </div>
            </div>
            
            <!-- Term 6: Refund Policy -->
            <div class="term-container">
                <div class="term-number">6</div>
                <div class="term-title">Refund Policy</div>
                <div class="term-content">
                    <p>Donations to Love Bridge are generally non-refundable as they are considered charitable contributions. Exceptions may be made in cases of:</p>
                    <ul>
                        <li>Processing errors (duplicate charges, incorrect amounts)</li>
                        <li>Unauthorized transactions</li>
                        <li>Donations made under false pretenses or misinformation</li>
                    </ul>
                    <p>Refund requests must be submitted within 30 days of the donation date and will be evaluated on a case-by-case basis.</p>
                </div>
            </div>
            
            <!-- Term 7: Tax Deductibility -->
            <div class="term-container">
                <div class="term-number">7</div>
                <div class="term-title">Tax Deductibility</div>
                <div class="term-content">
                    <p>Love Bridge is a registered charitable organization. Donations may be tax-deductible according to applicable tax laws in your jurisdiction.</p>
                    <p>Official donation receipts will be issued for all donations of RM30 or more, or upon request for smaller amounts. Receipts are typically issued within 30 days of the donation date.</p>
                    <p>Donors are responsible for consulting with their tax advisors regarding the deductibility of their contributions.</p>
                </div>
            </div>
            
            <!-- Term 8: Donor Privacy -->
            <div class="term-container">
                <div class="term-number">8</div>
                <div class="term-title">Donor Information and Privacy</div>
                <div class="term-content">
                    <p>Love Bridge respects donor privacy and handles personal information in accordance with our Privacy Policy. Donor information is used for:</p>
                    <ul>
                        <li>Processing donations and issuing receipts</li>
                        <li>Communicating about our programs and impact</li>
                        <li>Compliance with legal requirements</li>
                        <li>Improving donor experience</li>
                    </ul>
                    <p>Donors may opt out of non-essential communications at any time while still receiving required transactional communications.</p>
                </div>
            </div>
            
            <!-- Term 9: Minimum and Maximum Donations -->
            <div class="term-container">
                <div class="term-number">9</div>
                <div class="term-title">Minimum and Maximum Donations</div>
                <div class="term-content">
                    <p>Love Bridge accepts donations with MYR through our online platform. There is no maximum donation limit, though exceptionally large donations may require additional verification for security purposes.</p>
                    <p>For donations exceeding RM10,000, please contact our donor relations team for personalized assistance and additional payment options.</p>
                </div>
            </div>
            
            <!-- Term 10: International Donations -->
            <div class="term-container">
                <div class="term-number">10</div>
                <div class="term-title">International Donations</div>
                <div class="term-content">
                    <p>Love Bridge accepts donations from international donors. International transactions may be subject to:</p>
                    <ul>
                        <li>Currency conversion fees</li>
                        <li>International transaction fees</li>
                        <li>Country-specific restrictions</li>
                    </ul>
                    <p>Donors are responsible for any fees or taxes imposed by their financial institutions or countries of residence. Love Bridge cannot guarantee the tax deductibility of donations outside of Malaysia.</p>
                </div>
            </div>
            
            <!-- Term 11: Donation Restrictions -->
            <div class="term-container">
                <div class="term-number">11</div>
                <div class="term-title">Donation Restrictions</div>
                <div class="term-content">
                    <p>Love Bridge reserves the right to refuse donations that:</p>
                    <ul>
                        <li>Originate from illegal activities</li>
                        <li>Conflict with our organizational values</li>
                        <li>Come with conditions that compromise our independence</li>
                        <li>Require administrative costs disproportionate to the donation amount</li>
                    </ul>
                    <p>In such cases, donations will be returned to the donor whenever possible.</p>
                </div>
            </div>
            
            <!-- Term 12: Acknowledgement and Recognition -->
            <div class="term-container">
                <div class="term-number">12</div>
                <div class="term-title">Donor Acknowledgement and Recognition</div>
                <div class="term-content">
                    <p>Love Bridge may publicly acknowledge donors unless anonymity is requested. Donors may specify their preference for recognition during the donation process.</p>
                    <p>Recognition methods may include (with donor consent):</p>
                    <ul>
                        <li>Listing in annual reports</li>
                        <li>Acknowledgement on our website</li>
                        <li>Recognition at events</li>
                        <li>Thank you communications</li>
                    </ul>
                </div>
            </div>
            
            <!-- Additional Important Information -->
            <div class="highlight-box">
                <p><strong>Important Notice:</strong> By making a donation to Love Bridge, you acknowledge that you have read, understood, and agree to these Terms & Conditions. If you do not agree to these terms, please do not proceed with your donation.</p>
                <p>Love Bridge reserves the right to modify these terms at any time. Significant changes will be communicated to donors through our website or email notifications.</p>
            </div>
            
            <!-- Governing Law -->
            <div class="term-container" style="background-color: #f9f9f9;">
                <div class="term-number">★</div>
                <div class="term-title">Governing Law and Contact Information</div>
                <div class="term-content">
                    <p>These Terms & Conditions are governed by the laws of Malaysia. Any disputes shall be subject to the exclusive jurisdiction of the courts of Malaysia.</p>
                    <p>For questions about these Terms & Conditions or our donation policies, please contact us:</p>
                    <p><strong>Email:</strong> lovebridge1201@gmail.com<br>
                    <strong>Phone:</strong> +60 11-1119 0233<br>
                    <strong>Address:</strong> Love Bridge Foundation, 123, Jalan Love Bridge, 75450 Melaka, Malaysia</p>
                </div>
            </div>
        </div>
        
        <div class="navigation">
            <a href="privacy.php" class="btn">View Privacy Policy</a>
            <a href="Homepage.php" class="btn btn-outline">Return to Homepage</a>
        </div>
    </div>
    
    <div class="footer-info">
        <div class="main-container">
            <p>&copy; <?php echo date('Y'); ?> Love Bridge Donation Platform. All rights reserved.</p>
            <p>Love Bridge is a registered charity organization in Malaysia. Registration No: PPM-123-456-789</p>
            <p><a href="terms&condition.php">Terms & Conditions</a> | <a href="privacy.php">Privacy Policy</a> | <a href="Contact_us.php">Contact Us</a></p>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>