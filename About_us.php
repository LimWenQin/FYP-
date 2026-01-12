<?php
// about_us.php
session_start();
include 'dataconnection.php';
include 'header_function.php';

// 获取总部信息
$hq = null;
// 确保数据库连接存在再执行查询
if (isset($conn)) {
    $result = $conn->query("SELECT * FROM headquarters WHERE HQ_ID = 1");
    if ($result && $result->num_rows > 0) {
        $hq = $result->fetch_assoc();
    }
    if($result) $result->close();
}
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

        /* 统计部分样式 */
        .stats-section {
            background: white;
            padding: 60px 0;
            margin-bottom: 60px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
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

        /* --- Vision & Mission 核心样式修改 --- */
        /* 对应 div class="mission-vision" */
        .mission-vision {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            margin-bottom: 60px;
        }

        /* 对应 div class="mission-box" 和 "vision-box" */
        .mission-box, .vision-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .mission-box:hover, .vision-box:hover {
            transform: translateY(-5px);
        }

        .mission-box h3, .vision-box h3 {
            color: #d32f2f;
            margin-bottom: 20px;
            font-size: 1.8rem;
            border-bottom: 2px solid #ffcdd2;
            padding-bottom: 10px;
            display: inline-block;
        }

        /* 新增列表样式，让内容更好看 */
        .mission-box ul, .vision-box ul {
            list-style: none;
            padding-left: 0;
        }

        .mission-box li, .vision-box li {
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
        }

        .point-icon {
            color: #d32f2f;
            font-weight: bold;
            margin-right: 10px;
            font-size: 1.2rem;
            line-height: 1.4;
        }
        /* --- End Vision & Mission --- */

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

        @media (max-width: 768px) {
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
                <?php endif; ?>
            </div>
        </div>

        <h2 class="section-title">Our Purpose</h2>
        
        <div class="mission-vision">
            <div class="vision-box">
                <h3>Our Vision</h3>
                <p>To create a world where compassion bridges every gap, and no one is left behind in times of need. We envision communities where every individual has access to basic necessities, healthcare, education, and opportunities for a dignified life.</p>
                <ul>
                    <li><span class="point-icon">•</span> Build sustainable support systems for vulnerable communities</li>
                    <li><span class="point-icon">•</span> Create bridges of hope between donors and recipients</li>
                    <li><span class="point-icon">•</span> Foster a culture of giving and social responsibility</li>
                    <li><span class="point-icon">•</span> Ensure every donation creates maximum impact</li>
                </ul>
            </div>
            
            <div class="mission-box">
                <h3>Our Mission</h3>
                <p>To efficiently connect compassionate donors with credible causes through a transparent, secure, and user-friendly platform. We are committed to ensuring that every contribution, big or small, reaches those who need it most.</p>
                <ul>
                    <li><span class="point-icon">•</span> Provide immediate relief during emergencies and crises</li>
                    <li><span class="point-icon">•</span> Support long-term community development projects</li>
                    <li><span class="point-icon">•</span> Maintain 100% transparency in fund allocation</li>
                    <li><span class="point-icon">•</span> Engage and empower volunteers in meaningful work</li>
                    <li><span class="point-icon">•</span> Educate and raise awareness about social issues</li>
                </ul>
            </div>
        </div>

        <h2 class="section-title">Our Core Values</h2>
        <div class="values-section">
            <div class="values-grid">
                <div class="value-item">
                    <h4>Compassion</h4>
                    <p>We approach every situation with empathy, understanding, and a genuine desire to alleviate suffering.</p>
                </div>
                
                <div class="value-item">
                    <h4>Integrity</h4>
                    <p>We maintain the highest standards of transparency and accountability in all our operations.</p>
                </div>
                
                <div class="value-item">
                    <h4>Sustainability</h4>
                    <p>We create programs that provide long-term solutions, not just temporary relief.</p>
                </div>
                
                <div class="value-item">
                    <h4>Community</h4>
                    <p>We believe in the power of collective action and community-driven solutions.</p>
                </div>
                
                <div class="value-item">
                    <h4>Innovation</h4>
                    <p>We continuously seek new and better ways to serve and make a lasting impact.</p>
                </div>
                
                <div class="value-item">
                    <h4>Respect</h4>
                    <p>We honor the dignity of every individual we serve, regardless of their circumstances.</p>
                </div>
            </div>
        </div>

        <h2 class="section-title">Our Focus Areas</h2>
        <div class="values-section">
            <div class="values-grid">
                <div class="value-item">
                    <h4>Children & Orphanages</h4>
                    <p>Supporting orphanages, educational programs, nutrition initiatives, and healthcare for underprivileged children.</p>
                </div>
                
                <div class="value-item">
                    <h4>Elderly Care</h4>
                    <p>Providing companionship, medical support, and basic necessities for senior citizens in need.</p>
                </div>
                
                <div class="value-item">
                    <h4>Stray Animals</h4>
                    <p>Take care of the animals by providing them with food, shelter, and medical care.</p>
                </div>
                
                <div class="value-item">
                    <h4>Education Support</h4>
                    <p>Scholarships, school supplies, and learning facilities for children from low-income families.</p>
                </div>
                
                <div class="value-item">
                    <h4>Medical Aid</h4>
                    <p>Assistance with medical bills, surgeries, and essential healthcare services for those who cannot afford them.</p>
                </div>
                
                <div class="value-item">
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