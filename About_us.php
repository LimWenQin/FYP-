<?php
// about_us.php
session_start();
include 'dataconnection.php';
include 'header_function.php';

// 获取总部信息
$hq = null;
$result = $conn->query("SELECT * FROM headquarters WHERE HQ_ID = 1");
if ($result && $result->num_rows > 0) {
    $hq = $result->fetch_assoc();
}
$result->close();

// 获取统计数据
$total_donations = ['total' => 0];
$total_donors = ['total' => 0];
$total_cases = ['total' => 0];
$total_activities = ['total' => 0];

// 总捐款金额
$result = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus = 'Success'");
if ($result && $result->num_rows > 0) {
    $total_donations = $result->fetch_assoc();
}
$result->close();

// 总捐赠者数量
$result = $conn->query("SELECT COUNT(*) as total FROM donor");
if ($result && $result->num_rows > 0) {
    $total_donors = $result->fetch_assoc();
}
$result->close();

// 成功案例数量
$result = $conn->query("SELECT COUNT(*) as total FROM special_case WHERE Case_Status = 'Completed'");
if ($result && $result->num_rows > 0) {
    $total_cases = $result->fetch_assoc();
}
$result->close();

// 活动数量
$result = $conn->query("SELECT COUNT(*) as total FROM activity WHERE Activity_Status = 'Completed'");
if ($result && $result->num_rows > 0) {
    $total_activities = $result->fetch_assoc();
}
$result->close();

include 'header_UI.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Love Bridge Foundation</title>
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

        .hero-section {
            background: linear-gradient(135deg, rgba(211, 47, 47, 0.9) 0%, rgba(183, 28, 28, 0.9) 100%), url('images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            text-align: center;
            margin-bottom: 60px;
        }

        .hero-section h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero-section p {
            font-size: 1.3rem;
            max-width: 800px;
            margin: 0 auto 30px;
            opacity: 0.95;
        }

        .btn-primary {
            background: white;
            color: #d32f2f;
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            background: #ffebee;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .stats-section {
            background: white;
            padding: 60px 0;
            margin-bottom: 60px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            text-align: center;
        }

        .stat-item {
            padding: 30px;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: #d32f2f;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .about-content {
            margin-bottom: 60px;
        }

        .section-title {
            color: #d32f2f;
            font-size: 2.5rem;
            margin-bottom: 40px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ffcdd2;
            text-align: center;
        }

        .mission-vision {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            margin-bottom: 60px;
        }

        .mission-box, .vision-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .mission-box h3, .vision-box h3 {
            color: #d32f2f;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }

        .mission-box .icon, .vision-box .icon {
            font-size: 3rem;
            color: #d32f2f;
            margin-bottom: 20px;
        }

        .values-section {
            margin-bottom: 60px;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .value-item {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .value-item:hover {
            transform: translateY(-10px);
        }

        .value-icon {
            font-size: 2.5rem;
            color: #d32f2f;
            margin-bottom: 20px;
        }

        .value-item h4 {
            color: #d32f2f;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .story-section {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            padding: 60px 0;
            margin-bottom: 60px;
            border-radius: 10px;
        }

        .story-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .story-content h2 {
            color: #d32f2f;
            margin-bottom: 30px;
        }

        .story-content p {
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .team-section {
            margin-bottom: 60px;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .team-member {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .team-img {
            height: 250px;
            background: #ddd;
            background-size: cover;
            background-position: center;
        }

        .team-info {
            padding: 25px;
        }

        .team-info h4 {
            color: #d32f2f;
            margin-bottom: 10px;
        }

        .team-info p {
            color: #666;
            font-style: italic;
        }

        .cta-section {
            background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            border-radius: 10px;
        }

        .cta-section h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .cta-section p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 40px;
            opacity: 0.9;
        }

        .btn-white {
            background: white;
            color: #d32f2f;
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-white:hover {
            background: #ffebee;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .mission-vision {
                grid-template-columns: 1fr;
            }
            
            .values-grid {
                grid-template-columns: 1fr;
            }
            
            .team-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-section h1 {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="container">
            <h1>Building Bridges of Love and Hope</h1>
            <p>Love Bridge Foundation is a non-profit organization dedicated to creating positive change through compassion, care, and community support. Since 2010, we've been connecting generous hearts with those in need.</p>
           
        </div>
    </div>

   

    <div class="container about-content">
        <h2 class="section-title">Our Story</h2>
        <div class="story-section">
            <div class="story-content">
                <?php if ($hq): ?>
                    <h2><?php echo htmlspecialchars($hq['HQ_Name']); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars($hq['HQ_Story'])); ?></p>
                    <p><strong>Founded:</strong> <?php echo date('F j, Y', strtotime($hq['HQ_FoundingDate'])); ?></p>
                <?php else: ?>
                    <h2>Love Bridge Foundation</h2>
                    <p>Founded in 2010, Love Bridge started as a small community initiative by a group of passionate volunteers who believed in the power of collective kindness. What began as a simple act of helping a few families in need has grown into a nationwide movement touching thousands of lives.</p>
                    <p>Our journey began when our founder, Dr. Aminah Hassan, witnessed the struggles of underprivileged communities during her medical missions. She realized that while medical help was crucial, what people needed most was a bridge - a connection to resources, support, and most importantly, hope.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mission-vision">
            <div class="mission-box">
                <div class="icon"></div>
                <h3>Our Mission</h3>
                <p>To connect compassionate individuals with those in need through sustainable programs that provide immediate relief while creating long-term solutions for underprivileged communities across Malaysia.</p>
            </div>
            
            <div class="vision-box">
                <div class="icon"></div>
                <h3>Our Vision</h3>
                <p>A Malaysia where no one is left behind, where every individual has access to basic needs, education, healthcare, and opportunities to thrive in a compassionate and supportive community.</p>
            </div>
        </div>

        <h2 class="section-title">Our Core Values</h2>
        <div class="values-section">
            <div class="values-grid">
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Compassion</h4>
                    <p>We approach every situation with empathy, understanding, and a genuine desire to alleviate suffering.</p>
                </div>
                
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Integrity</h4>
                    <p>We maintain the highest standards of transparency and accountability in all our operations.</p>
                </div>
                
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Sustainability</h4>
                    <p>We create programs that provide long-term solutions, not just temporary relief.</p>
                </div>
                
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Community</h4>
                    <p>We believe in the power of collective action and community-driven solutions.</p>
                </div>
                
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Innovation</h4>
                    <p>We continuously seek new and better ways to serve and make a lasting impact.</p>
                </div>
                
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Respect</h4>
                    <p>We honor the dignity of every individual we serve, regardless of their circumstances.</p>
                </div>
            </div>
        </div>

        <h2 class="section-title">Our Focus Areas</h2>
        <div class="values-section">
            <div class="values-grid">
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Children & Orphanages</h4>
                    <p>Supporting orphanages, educational programs, nutrition initiatives, and healthcare for underprivileged children.</p>
                </div>
                
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Elderly Care</h4>
                    <p>Providing companionship, medical support, and basic necessities for senior citizens in need.</p>
                </div>
                
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Stary Cat</h4>
                    <p>Take care of the animal by providing them with food, shelter, and medical care.</p>
                </div>
                
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Education Support</h4>
                    <p>Scholarships, school supplies, and learning facilities for children from low-income families.</p>
                </div>
                
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Medical Aid</h4>
                    <p>Assistance with medical bills, surgeries, and essential healthcare services for those who cannot afford them.</p>
                </div>
                
                <div class="value-item">
                    <div class="value-icon"></div>
                    <h4>Community Development</h4>
                    <p>Empowering communities through skills training, micro-enterprises, and infrastructure projects.</p>
                </div>
            </div>
        </div>

        <h2 class="section-title">Our Team</h2>
        <div class="team-section">
            <div class="team-grid">
                <div class="team-member">
                    <div class="team-img" style="background-color: #ffcdd2;"></div>
                    <div class="team-info">
                        <h4>Mr. Lim Wen Qin</h4>
                        <p>Founder & Chairperson</p>
                        <p>Medical doctor with 20+ years of humanitarian experience</p>
                    </div>
                </div>
                
                <div class="team-member">
                    <div class="team-img" style="background-color: #ffcdd2;"></div>
                    <div class="team-info">
                        <h4>Mr. Thong Yuen Zhen</h4>
                        <p>Executive Director</p>
                        <p>Former corporate leader turned full-time humanitarian</p>
                    </div>
                </div>
                
                <div class="team-member">
                    <div class="team-img" style="background-color: #ffcdd2;"></div>
                    <div class="team-info">
                        <h4>Mr. Ronald Tan Bin Hong</h4>
                        <p>Program Director</p>
                        <p>Specialized in community development and disaster response</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="cta-section" id="donate">
        <div class="container">
            <h2>Be Part of Our Journey</h2>
            <p>Your support can change lives. Whether through donations, volunteering, or spreading awareness, you can help us build more bridges of love and hope.</p>
          
        </div>
    </div>
     <?php include 'footer.php'; ?>
</body>
</html>