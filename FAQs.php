<?php
include 'dataconnection.php';
include 'header_function.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

include 'header_UI.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        
        /* --- FAQ页面主要内容 --- */
        .faq-hero {
            background: linear-gradient(rgba(200, 46, 3, 0.9), rgba(139, 26, 0, 0.9)), url('https://images.unsplash.com/photo-1523580494863-6f3031224c94?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 80px 0;
            margin-bottom: 60px;
        }
        
        .faq-hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .faq-hero h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 800;
        }
        
        .faq-hero p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        /* FAQ内容区域 */
        .faq-container {
            max-width: 1000px;
            margin: 0 auto 80px;
            padding: 0 20px;
        }
        
        .faq-category {
            margin-bottom: 50px;
        }
        
        .category-title {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--primary-color);
        }
        
        .category-icon {
            background-color: var(--primary-color);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.3rem;
        }
        
        .category-title h2 {
            font-size: 1.8rem;
            color: var(--primary-dark);
            margin: 0;
        }
        
        /* FAQ手风琴样式 */
        .faq-item {
            margin-bottom: 15px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
        }
        
        .faq-question {
            background-color: white;
            padding: 22px 30px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .faq-question:hover {
            background-color: #fff5f2;
        }
        
        .faq-question.active {
            background-color: #fff5f2;
            color: var(--primary-dark);
        }
        
        .faq-question i {
            color: var(--primary-color);
            transition: transform 0.3s;
        }
        
        .faq-question.active i {
            transform: rotate(180deg);
            color: var(--primary-dark);
        }
        
        .faq-answer {
            background-color: white;
            padding: 0 30px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease, padding 0.5s ease;
        }
        
        .faq-answer.open {
            padding: 25px 30px;
            max-height: 500px;
            border-top: 1px solid var(--gray-border);
        }
        
        .faq-answer p {
            margin-bottom: 15px;
        }
        
        .faq-answer ul {
            list-style-type: disc;
            padding-left: 20px;
            margin-bottom: 15px;
        }
        
        .faq-answer li {
            margin-bottom: 8px;
        }
        
        /* 联系部分 */
        .contact-section {
            background-color: var(--primary-color);
            color: white;
            padding: 60px 0;
            text-align: center;
            margin-top: 60px;
        }
        
        .contact-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .contact-content h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .contact-content p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .contact-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .contact-btn {
            background-color: white;
            color: var(--primary-color);
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .contact-btn:hover {
            background-color: #f0f0f0;
            transform: translateY(-3px);
        }
        
        /* 响应式设计 */
        @media (max-width: 900px) {
            .header-menu { display: none; }
            .header-search { display: none; }
            .header-info { display: none; }
            .header-user-auth { gap: 10px; }
            .header-welcome { font-size: 12px; }
            
            .faq-hero h1 { font-size: 2.5rem; }
            .category-title h2 { font-size: 1.5rem; }
            .contact-content h2 { font-size: 2rem; }
            
            .contact-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .contact-btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
        
        @media (max-width: 600px) {
            .faq-hero { padding: 60px 0; }
            .faq-hero h1 { font-size: 2rem; }
            .faq-question { padding: 18px 20px; font-size: 1rem; }
            .faq-answer.open { padding: 20px; }
        }
    </style>
</head>
<body>
    
    <main>
        <section class="faq-hero">
            <div class="faq-hero-content">
                <h1>Frequently Asked Questions</h1>
                <p>Find answers to common questions about Love Bridge donations, campaigns, and how you can make a difference.</p>
            </div>
        </section>

        <div class="faq-container">
            <div class="faq-category">
                <div class="category-title">
                    <div class="category-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <h2>Donation Questions</h2>
                </div>
                
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I make a donation?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Making a donation is simple and accessible from anywhere on our site:</p>
                            <ul>
                                <li><strong>Quick Donate:</strong> Click the "Donate" button located at the <strong>top right corner</strong> of the navigation bar.</li>
                                <li><strong>Specific Causes:</strong> Browse our <strong>Campaign</strong> or <strong>Special Case</strong> pages and click the "Donate" button on the specific cause you wish to support.</li>
                            </ul>
                            <p>Follow the on-screen instructions to complete your contribution securely.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>What payment methods do you accept?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>We offer a variety of secure payment options:</p>
                            <ul>
                                <li><strong>Credit & Debit Cards</strong> (Visa, MasterCard, etc.)</li>
                                <li><strong>E-Wallets</strong> (Touch 'n Go, GrabPay, Boost, etc.)</li>
                                <li><strong>Love Bridge Wallet</strong> (Use your account balance directly)</li>
                               
                            </ul>
                            <p>Choose the method that is most convenient for you at checkout.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How to request a tax exemption?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Yes, Love Bridge is a registered non-profit organization. To request a Tax Exemption receipt, please follow these steps:</p>
                            <ol>
                                <li><strong>Complete Your Profile:</strong> Ensure your user profile is updated with your full <strong>IC Number</strong> and <strong>Mailing Address</strong>.</li>
                                <li><strong>Select Option:</strong> When making a donation, check the box that says <strong>"Request Tax Exemption"</strong>.</li>
                            </ol>
                            <p>The receipt will be generated based on your profile details.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>Can I set up recurring donations?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Absolutely! We offer monthly recurring donation options. When making a donation, simply select the "Make this a monthly donation" option during the payment process.</p>
                            <p>You can manage or cancel your recurring donations at any time through your donor profile.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            
                
              
            
            <div class="faq-category">
                <div class="category-title">
                    <div class="category-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h2>Account & Profile Questions</h2>
                </div>
                
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I update my profile information?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To update your profile:</p>
                            <ul>
                                <li>Log in to your Love Bridge account</li>
                                <li>Click on "Account" in the navigation header</li>
                                <li>Select "Profile"</li>
                                <li>Update your information and click "Update Profile"</li>
                            </ul>
                            <p>If you have trouble accessing your account, use the "Forgot Password" link on the login page.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How can I see my donation record?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Your complete donation history is available in your profile:</p>
                            <ol>
                                <li>Log in to your account</li>
                                <li>Go to "Profile" and select "Donation History"</li>
                                <li>You'll see a list of all your donations with dates, amounts, and campaign details</li>
                                <li>You can receive your donation history as a PDF in your email</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I delete my account?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>We're sorry to see you go! To delete your account:</p>
                            <ul>
                                <li>Log in to your profile</li>
                                <li>Go to "Account"</li>
                                <li>Select Profile</li>
                                <li>Scroll to the bottom and click "Delete Account"</li>
                                <li>Confirm your decision</li>
                            </ul>
                            <p>Please note: Account deletion is permanent and cannot be undone. Your past donations will remain in our records for financial reporting purposes, but your personal information will be removed from our active databases.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <section class="contact-section">
            <div class="contact-content">
                <h2>Still Have Questions?</h2>
                <p>Our support team is here to help you with any questions you may have about Love Bridge.</p>
                
                <div class="contact-buttons">
                    <a href="mailto:support@lovebridge.org" class="contact-btn">
                        <i class="fas fa-envelope"></i> Email Us
                    </a>
                    <a href="Contact_Us.php" class="contact-btn">
                        <i class="fas fa-comments"></i> Contact Us
                    </a>
                </div>
            </div>
        </section>
         <?php include 'footer.php'; ?>
    </main>

    <script>
        // FAQ手风琴功能
        document.addEventListener('DOMContentLoaded', function() {
            const faqQuestions = document.querySelectorAll('.faq-question');
            
            faqQuestions.forEach(question => {
                question.addEventListener('click', () => {
                    const answer = question.nextElementSibling;
                    const isOpen = answer.classList.contains('open');
                    
                    // 关闭所有其他FAQ
                    document.querySelectorAll('.faq-answer').forEach(item => {
                        item.classList.remove('open');
                    });
                    
                    document.querySelectorAll('.faq-question').forEach(item => {
                        item.classList.remove('active');
                    });
                    
                    // 如果当前FAQ未打开，则打开它
                    if (!isOpen) {
                        answer.classList.add('open');
                        question.classList.add('active');
                    }
                });
            });
            
            // 页面加载时，如果有URL参数，自动展开对应的FAQ
            const urlParams = new URLSearchParams(window.location.search);
            const expandFaq = urlParams.get('expand');
            if (expandFaq) {
                // 查找包含特定文本的FAQ并展开
                const faqItems = document.querySelectorAll('.faq-item');
                faqItems.forEach(item => {
                    const question = item.querySelector('.faq-question span').textContent.toLowerCase();
                    if (question.includes(expandFaq.toLowerCase())) {
                        item.querySelector('.faq-question').click();
                        // 滚动到该元素
                        item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }
        });
    </script>
</body>
</html>