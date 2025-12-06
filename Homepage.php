<?php
session_start(); 
include 'header_UI.php';

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// 模拟捐赠活动数据 - 每张显示5秒
$campaigns = [
    [
        "id" => 1,
        "title" => "Emergency Medical and Contingency Reserve Fund",
        "image" => "https://images.unsplash.com/photo-1579684385127-1ef15d508118?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80",
        "description" => "It's not how much we give but how much love we put into giving!",
        "raised" => 998676.99,
        "goal" => 2000000.00,
        "donors" => 5432,
        "days_active" => 365,
        "tagline" => "FUND RAISED"
    ],
    [
        "id" => 2,
        "title" => "Children Education Support Program",
        "image" => "https://images.unsplash.com/photo-1503676260728-1c00da094a0b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80",
        "description" => "Education is the most powerful weapon which you can use to change the world.",
        "raised" => 756432.50,
        "goal" => 1500000.00,
        "donors" => 3215,
        "days_active" => 240,
        "tagline" => "FUND RAISED"
    ],
    [
        "id" => 3,
        "title" => "Food Security and Nutrition Initiative",
        "image" => "https://images.unsplash.com/photo-1554672408-730436b60dde?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80",
        "description" => "No one should go to bed hungry. Together we can end food insecurity.",
        "raised" => 543210.25,
        "goal" => 1000000.00,
        "donors" => 2876,
        "days_active" => 180,
        "tagline" => "FUND RAISED"
    ],
    [
        "id" => 4,
        "title" => "Disaster Relief and Recovery Fund",
        "image" => "https://images.unsplash.com/photo-1627637454031-58f5ac6a45b9?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80",
        "description" => "Helping communities rebuild stronger after natural disasters strike.",
        "raised" => 1234567.89,
        "goal" => 2500000.00,
        "donors" => 6543,
        "days_active" => 120,
        "tagline" => "FUND RAISED"
    ],
    [
        "id" => 5,
        "title" => "Healthcare Access for Underprivileged",
        "image" => "https://images.unsplash.com/photo-1551601651-2a8555f1a136?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80",
        "description" => "Quality healthcare should be accessible to everyone, regardless of income.",
        "raised" => 876543.21,
        "goal" => 1800000.00,
        "donors" => 4321,
        "days_active" => 300,
        "tagline" => "FUND RAISED"
    ]
];

// 模拟即将举行的活动
$upcoming_activities = [
    [
        "title" => "Charity Run 2024",
        "date" => "2024-06-15",
        "time" => "7:00 AM",
        "location" => "KL City Park",
        "description" => "Annual 5K charity run to raise funds for medical assistance"
    ],
    [
        "title" => "Food Distribution Day",
        "date" => "2024-06-22",
        "time" => "9:00 AM",
        "location" => "Community Hall",
        "description" => "Volunteer to distribute food packages to 500 families"
    ],
    [
        "title" => "Blood Donation Camp",
        "date" => "2024-06-29",
        "time" => "10:00 AM - 4:00 PM",
        "location" => "Red Crescent Center",
        "description" => "Join our blood donation campaign with target of 500 donors"
    ],
    [
        "title" => "Annual Gala Dinner",
        "date" => "2024-07-05",
        "time" => "7:30 PM",
        "location" => "Grand Hotel Ballroom",
        "description" => "Fundraising dinner with special guests and auction"
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Bridge - Donation System</title>
    <style>
        :root {
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-red: #fee2e2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --medium-gray: #737373;
            --dark-gray: #262626;
            --text-dark: #171717;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--white);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        .main-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0;
        }

        /* Hero Campaign Slider */
        .campaign-hero {
            position: relative;
            height: 70vh;
            min-height: 100%;
            overflow: hidden;
            margin-bottom: 40px;
        }

        .campaign-slides {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .campaign-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .campaign-slide.active {
            opacity: 1;
            z-index: 1;
        }

        .campaign-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.4);
        }

        .campaign-content {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            padding: 0 40px;
            text-align: center;
            color: white;
        }

        .days-counter {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .time-units {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 5px;
            font-size: 1rem;
            color: var(--white);
            font-weight: 500;
        }

        .fund-raised {
            background: rgba(0, 0, 0, 0.6);
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
            border-left: 5px solid var(--primary-red);
        }

        .fund-tagline {
            font-size: 1.5rem;
            color: var(--white);
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 700;
        }

        .fund-amount {
            font-size: 4rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 15px;
        }

        .fund-title {
            font-size: 1.2rem;
            color: var(--white);
            margin-bottom: 30px;
            font-weight: 600;
            line-height: 1.4;
        }

        .donate-btn {
            display: inline-block;
            background: var(--primary-red);
            color: white;
            padding: 15px 40px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            margin-bottom: 30px;
        }

        .donate-btn:hover {
            background: var(--dark-red);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .campaign-description {
            font-size: 1.3rem;
            color: var(--white);
            font-style: italic;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Slider Controls */
        .slider-controls {
            position: absolute;
            bottom: 8px;
            left: 0;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 3;
            gap: 20px;
        }

        .slider-dots {
            display: flex;
            gap: 10px;
        }

        .slider-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .slider-dot.active {
            background: var(--primary-red);
            border-color: var(--primary-red);
            transform: scale(1.2);
        }

        .slider-dot:hover {
            background: var(--primary-red);
            border-color: white;
        }

        /* Progress Bar */
        .progress-bar-container {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            z-index: 3;
        }

        .progress-bar {
            height: 100%;
            background: var(--primary-red);
            width: 0%;
            transition: width 5s linear;
        }

        .progress-bar.active {
            width: 100%;
        }

        /* Campaign Stats */
        .campaign-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            background: rgba(220, 38, 38, 0.1);
            padding: 15px 25px;
            border-radius: 5px;
            min-width: 140px;
            border: 1px solid rgba(220, 38, 38, 0.3);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Section Titles */
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            color: var(--primary-red);
            margin-bottom: 40px;
            position: relative;
            font-weight: 800;
            padding-bottom: 15px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--primary-red);
            border-radius: 2px;
        }

        /* Vision & Mission Section */
        .vision-mission-section {
            padding: 60px 40px;
            background: var(--light-gray);
            margin-bottom: 60px;
        }

        .vm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .vm-card {
            background: var(--white);
            border-radius: 10px;
            padding: 40px;
            border-left: 5px solid var(--primary-red);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .vm-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.1);
        }

        .vm-card h3 {
            font-size: 2rem;
            color: var(--primary-red);
            margin-bottom: 20px;
            font-weight: 700;
        }

        .vm-card p {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-dark);
            margin-bottom: 25px;
        }

        .vm-points {
            list-style: none;
            padding: 0;
        }

        .vm-points li {
            padding: 12px 0;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-dark);
            font-size: 1.1rem;
        }

        .vm-points li:last-child {
            border-bottom: none;
        }

        .point-icon {
            color: var(--primary-red);
            font-size: 1.2rem;
            font-weight: bold;
        }

        /* Upcoming Activities */
        .activities-section {
            padding: 60px 40px;
            background: var(--white);
            margin-bottom: 60px;
        }

        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .activity-card {
            background: var(--light-gray);
            border-radius: 10px;
            padding: 30px;
            border-left: 5px solid var(--primary-red);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .activity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .activity-date {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--primary-red);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .activity-title {
            font-size: 1.5rem;
            color: var(--text-dark);
            margin-bottom: 20px;
            font-weight: 700;
            padding-right: 100px;
        }

        .activity-details {
            margin-bottom: 20px;
        }

        .activity-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: var(--medium-gray);
            font-size: 1rem;
        }

        .activity-description {
            color: var(--medium-gray);
            line-height: 1.6;
            font-size: 1rem;
        }

        /* Call to Action */
        .cta-section {
            padding: 80px 40px;
            background: var(--primary-red);
            text-align: center;
            margin-bottom: 0;
        }

        .cta-content {
            max-width: 900px;
            margin: 0 auto;
        }

        .cta-section h2 {
            font-size: 3rem;
            color: white;
            margin-bottom: 25px;
            font-weight: 800;
        }

        .cta-section p {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 50px;
            line-height: 1.8;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 35px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 180px;
        }

        .btn-primary {
            background: white;
            color: var(--primary-red);
        }

        .btn-primary:hover {
            background: var(--light-gray);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
        }

        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto 60px;
            padding: 0 40px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            border-top: 5px solid var(--primary-red);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .stat-card .number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary-red);
            margin-bottom: 10px;
        }

        .stat-card .label {
            font-size: 1.2rem;
            color: var(--text-dark);
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .campaign-hero {
                height: 65vh;
                min-height: 450px;
            }
        }

        @media (max-width: 1024px) {
            .days-counter {
                font-size: 3rem;
            }
            
            .fund-amount {
                font-size: 3.5rem;
            }
            
            .vm-grid {
                grid-template-columns: 1fr;
            }
            
            .campaign-hero {
                height: 60vh;
                min-height: 400px;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 768px) {
            .days-counter {
                font-size: 2.5rem;
            }
            
            .time-units {
                gap: 20px;
                font-size: 0.9rem;
                flex-wrap: wrap;
            }
            
            .fund-amount {
                font-size: 2.5rem;
            }
            
            .fund-title {
                font-size: 1.5rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .vm-card {
                padding: 30px;
            }
            
            .campaign-stats {
                gap: 20px;
            }
            
            .stat-item {
                min-width: 120px;
                padding: 12px 20px;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .donate-btn {
                padding: 12px 30px;
                font-size: 1.1rem;
            }
            
            .cta-section h2 {
                font-size: 2.5rem;
            }
            
            .btn {
                padding: 14px 30px;
                min-width: 160px;
            }
            
            .slider-controls {
                bottom: 15px;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
                padding: 0 20px;
            }
        }

        @media (max-width: 576px) {
            .campaign-hero {
                height: 55vh;
                min-height: 350px;
            }
            
            .days-counter {
                font-size: 2rem;
            }
            
            .fund-raised {
                padding: 25px;
            }
            
            .fund-amount {
                font-size: 2rem;
            }
            
            .donate-btn {
                padding: 12px 25px;
                font-size: 1rem;
            }
            
            .cta-section h2 {
                font-size: 2rem;
            }
            
            .btn {
                padding: 12px 25px;
                min-width: 100%;
            }
            
            .vm-grid {
                grid-template-columns: 1fr;
            }
            
            .activities-grid {
                grid-template-columns: 1fr;
            }
            
            .vm-card, .activity-card {
                padding: 25px;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Hero Campaign Slider -->
        <section class="campaign-hero">
            <div class="campaign-slides">
                <?php foreach ($campaigns as $index => $campaign): ?>
                <div class="campaign-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>">
                    <img src="<?php echo $campaign['image']; ?>" alt="<?php echo $campaign['title']; ?>" class="campaign-image">
                    <div class="campaign-content">
                        <div class="days-counter">
                            <?php echo $campaign['days_active']; ?> Days
                        </div>
                        <div class="time-units">
                            <span>Hours | 15 Hours</span>
                            <span>49 Mins</span>
                            <span>27 Seconds</span>
                        </div>
                        
                        <div class="fund-raised">
                            <div class="fund-tagline"><?php echo $campaign['tagline']; ?></div>
                            <div class="fund-amount">RM <?php echo number_format($campaign['raised'], 2); ?></div>
                            <div class="fund-title"><?php echo $campaign['title']; ?></div>
                            <button class="donate-btn" onclick="window.location.href='donate.php?campaign=<?php echo $campaign['id']; ?>'">
                                DONATE NOW
                            </button>
                            <div class="campaign-description">
                                <?php echo $campaign['description']; ?>
                            </div>
                        </div>
                        
                        <div class="campaign-stats">
                            <div class="stat-item">
                                <div class="stat-value">RM <?php echo number_format($campaign['goal']); ?></div>
                                <div class="stat-label">Target Goal</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($campaign['donors']); ?></div>
                                <div class="stat-label">Total Donors</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo round(($campaign['raised'] / $campaign['goal']) * 100); ?>%</div>
                                <div class="stat-label">Progress</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-bar-container">
                <div class="progress-bar <?php echo 'active'; ?>"></div>
            </div>
            
            <!-- Slider Controls -->
            <div class="slider-controls">
                <div class="slider-dots">
                    <?php foreach ($campaigns as $index => $campaign): ?>
                    <div class="slider-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="number">5,432</div>
                <div class="label">Active Donors</div>
            </div>
            <div class="stat-card">
                <div class="number">RM 4.5M</div>
                <div class="label">Total Raised</div>
            </div>
            <div class="stat-card">
                <div class="number">127</div>
                <div class="label">Projects Completed</div>
            </div>
            <div class="stat-card">
                <div class="number">23</div>
                <div class="label">Active Campaigns</div>
            </div>
        </div>

        <!-- Vision & Mission Section -->
        <section class="vision-mission-section">
            <h2 class="section-title">Our Purpose</h2>
            <div class="vm-grid">
                <div class="vm-card">
                    <h3>Our Vision</h3>
                    <p>To create a world where compassion bridges every gap, and no one is left behind in times of need. We envision communities where every individual has access to basic necessities, healthcare, education, and opportunities for a dignified life.</p>
                    <ul class="vm-points">
                        <li><span class="point-icon">•</span> Build sustainable support systems for vulnerable communities</li>
                        <li><span class="point-icon">•</span> Create bridges of hope between donors and recipients</li>
                        <li><span class="point-icon">•</span> Foster a culture of giving and social responsibility</li>
                        <li><span class="point-icon">•</span> Ensure every donation creates maximum impact</li>
                    </ul>
                </div>
                
                <div class="vm-card">
                    <h3>Our Mission</h3>
                    <p>To efficiently connect compassionate donors with credible causes through a transparent, secure, and user-friendly platform. We are committed to ensuring that every contribution, big or small, reaches those who need it most.</p>
                    <ul class="vm-points">
                        <li><span class="point-icon">•</span> Provide immediate relief during emergencies and crises</li>
                        <li><span class="point-icon">•</span> Support long-term community development projects</li>
                        <li><span class="point-icon">•</span> Maintain 100% transparency in fund allocation</li>
                        <li><span class="point-icon">•</span> Engage and empower volunteers in meaningful work</li>
                        <li><span class="point-icon">•</span> Educate and raise awareness about social issues</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Upcoming Activities -->
        <section class="activities-section">
            <h2 class="section-title">Upcoming Activities</h2>
            <div class="activities-grid">
                <?php foreach ($upcoming_activities as $activity): ?>
                <div class="activity-card">
                    <div class="activity-date">
                        <?php echo date('M j', strtotime($activity['date'])); ?>
                    </div>
                    <h3 class="activity-title"><?php echo $activity['title']; ?></h3>
                    <div class="activity-details">
                        <div class="activity-detail">
                            <span><?php echo $activity['time']; ?></span>
                        </div>
                        <div class="activity-detail">
                            <span><?php echo $activity['location']; ?></span>
                        </div>
                    </div>
                    <p class="activity-description"><?php echo $activity['description']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Call to Action -->
        <section class="cta-section">
            <div class="cta-content">
                <h2>Be the Bridge of Hope</h2>
                <p>Your generosity has the power to transform lives. Whether through donations, volunteering, or spreading awareness, you can be part of creating positive change in our community. Join us in building bridges of compassion.</p>
                <div class="cta-buttons">
                    <a href="donate.php" class="btn btn-primary">
                        Make a Donation
                    </a>
                    <a href="volunteer.php" class="btn btn-secondary">
                        Join as Volunteer
                    </a>
                    <a href="campaigns.php" class="btn btn-secondary">
                        View All Campaigns
                    </a>
                </div>
            </div>
        </section>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.campaign-slide');
            const dots = document.querySelectorAll('.slider-dot');
            const progressBar = document.querySelector('.progress-bar');
            
            let currentSlide = 0;
            let slideInterval;
            const slideDuration = 5000; // 5 seconds
            
            function showSlide(index) {
                // Hide all slides
                slides.forEach(slide => {
                    slide.classList.remove('active');
                });
                dots.forEach(dot => {
                    dot.classList.remove('active');
                });
                
                // Show current slide
                slides[index].classList.add('active');
                dots[index].classList.add('active');
                currentSlide = index;
                
                // Reset and restart progress bar animation
                progressBar.classList.remove('active');
                void progressBar.offsetWidth; // Trigger reflow
                progressBar.classList.add('active');
            }
            
            function nextSlide() {
                currentSlide = (currentSlide + 1) % slides.length;
                showSlide(currentSlide);
            }
            
            function startSlider() {
                slideInterval = setInterval(nextSlide, slideDuration);
            }
            
            function stopSlider() {
                clearInterval(slideInterval);
            }
            
            // Initialize
            showSlide(currentSlide);
            startSlider();
            
            // Dot click events
            dots.forEach(dot => {
                dot.addEventListener('click', (e) => {
                    stopSlider();
                    const index = parseInt(e.target.getAttribute('data-index'));
                    showSlide(index);
                    startSlider();
                });
            });
            
            // Pause on hover
            const heroSection = document.querySelector('.campaign-hero');
            heroSection.addEventListener('mouseenter', stopSlider);
            heroSection.addEventListener('mouseleave', startSlider);
            
            // Progress bar animation
            progressBar.style.transition = `width ${slideDuration}ms linear`;
        });
        
        // Update time units every second
        function updateTime() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            
            // Update all time displays
            document.querySelectorAll('.time-units').forEach(timeUnit => {
                timeUnit.innerHTML = `
                    <span>Hours | ${hours} Hours</span>
                    <span>${minutes} Mins</span>
                    <span>${seconds} Seconds</span>
                `;
            });
        }
        
        // Initial call and set interval
        updateTime();
        setInterval(updateTime, 1000);
    </script>
</body>
</html>