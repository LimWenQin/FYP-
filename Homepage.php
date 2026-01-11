<?php
// 1. 启动 Session (如果 header_UI.php 里没有 session_start，这里必须有)
// 为了防止重复启动报错，使用这个判断
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'dataconnection.php'; // Ensure database connection
include 'header_UI.php';

// --- 2. 检查登录状态 ---
// 请确保你在 donor_login.php 里保存的名字是 'Donor_ID'
$logged_in = isset($_SESSION['donor_id']) && !empty($_SESSION['donor_id']);

// --- 3. 获取 Special Cases (Emergency Relief) ---
$special_cases = [];
$query_sc = "SELECT * FROM special_case 
             WHERE Case_Category = 'Emergency Relief' 
             AND Case_Status = 'Active' 
             ORDER BY Created_At DESC LIMIT 5"; 

$result_sc = $conn->query($query_sc);

if ($result_sc && $result_sc->num_rows > 0) {
    while ($row = $result_sc->fetch_assoc()) {
        // Image Handling
        $image_url = "https://images.unsplash.com/photo-1579684385127-1ef15d508118?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80"; 
        if (!empty($row['Case_Images'])) {
            $decoded = json_decode($row['Case_Images'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) {
                $image_url = $decoded[0];
            }
        }

        // Calculate days active
        $created_date = new DateTime($row['Created_At']);
        $now = new DateTime();
        $interval = $created_date->diff($now);
        
        // --- 核心逻辑修复 ---
        // 在这里直接决定按钮的行为
        if ($logged_in) {
            // 如果已登录：直接跳转到支付页面，并带上 Case ID
            $target_url = "S_C_Payment_Page.php?case_id=" . $row['Case_ID'];
            $btn_onclick = "window.location.href='$target_url';";
        } else {
            // 如果未登录：触发 JS 弹窗
            $btn_onclick = "checkLogin(event);";
        }
        
        $special_cases[] = [
            "id" => $row['Case_ID'],
            "title" => $row['Case_Title'],
            "image" => $image_url,
            "description" => $row['Case_Description'],
            "raised" => floatval($row['Raised_Amount']),
            "goal" => floatval($row['Target_Amount']),
            "donors" => intval($row['Donor_Count']),
            "days_active" => $interval->days,
            "start_date" => $row['Start_Date'], 
            "end_date" => $row['End_Date'],     
            "tagline" => "EMERGENCY RELIEF",
            "btn_action" => $btn_onclick // 将生成的点击动作存入数组
        ];
    }
}

// --- 4. Fetch Upcoming Activities ---
$activities = [];
$query_act = "SELECT * FROM activity 
              WHERE Activity_Status IN ('Active', 'Upcoming') 
              ORDER BY Activity_StartDate ASC LIMIT 3";

$result_act = $conn->query($query_act);

if ($result_act && $result_act->num_rows > 0) {
    while ($row = $result_act->fetch_assoc()) {
        $activities[] = [
            "id" => $row['Activity_ID'],
            "title" => $row['Activity_Name'],
            "date" => $row['Activity_StartDate'], 
            "time" => date('h:i A', strtotime($row['Activity_StartDate'])), 
            "location" => $row['Activity_Venue'],
            "description" => $row['Activity_Description']
        ];
    }
}

// --- Helper: 底部大 CTA 按钮的逻辑 ---
$cta_url = "Payment_page.php"; // 这里可能也需要参数，视你的逻辑而定
if ($logged_in) {
    $cta_onclick = "window.location.href='$cta_url'; return false;";
} else {
    $cta_onclick = "checkLogin(event)";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Bridge - Donation System</title>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            max-width: 100%;
            margin: 0 auto;
            padding: 0;
        }

        /* --- Special Case Hero Slider --- */
        .special-case-hero {
            position: relative;
            height: 85vh; 
            min-height: 600px;
            overflow: hidden;
            margin-bottom: 60px;
            background-color: #000; 
        }

        .special-case-slides {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .special-case-slide {
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
            visibility: hidden; 
        }

        .special-case-slide.active {
            opacity: 1;
            z-index: 1;
            visibility: visible;
        }

        .special-case-image-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .special-case-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.4);
        }

        /* Progress Bar Overlay */
        .image-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 60px;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            padding: 0 40px;
            z-index: 2;
            box-sizing: border-box; 
        }

        .progress-stats {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            color: white;
        }

        .progress-left, .progress-right {
            display: flex;
            flex-direction: column;
            min-width: 120px;
        }

        .progress-right {
            text-align: right;
        }

        .progress-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
        }

        .progress-amount {
            font-size: 20px;
            font-weight: bold;
            color: white;
        }

        .progress-bar-track {
            flex: 1;
            margin: 0 30px;
            height: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #FF9800, #FF5722);
            border-radius: 5px;
            position: relative;
            width: 0%;
            transition: width 1s ease;
        }

        .progress-percentage {
            position: absolute;
            top: -30px;
            right: 0;
            background: var(--primary-red);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            transform: translateX(50%);
        }

        /* Content inside the Slider */
        .special-case-content {
            position: relative;
            z-index: 2;
            max-width: 1000px;
            padding: 0 20px;
            text-align: center;
            color: white;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .days-counter {
            font-size: 2rem;
            font-weight: 700;
            color: var(--white);
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }

        .time-units {
            display: flex;
            justify-content: center;
            gap: 20px;
            font-size: 1.2rem;
            color: #fbbf24; /* Amber color */
            font-weight: 600;
            background: rgba(0,0,0,0.5);
            padding: 10px 20px;
            border-radius: 50px;
            width: fit-content;
            margin: 0 auto;
        }

        .fund-raised-box {
            background: rgba(0, 0, 0, 0.6);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .fund-tagline {
            font-size: 1.2rem;
            color: var(--primary-red);
            margin-bottom: 10px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .fund-title {
            font-size: 2.5rem;
            color: var(--white);
            margin-bottom: 20px;
            font-weight: 700;
            line-height: 1.2;
        }

        .donate-btn {
            display: inline-block;
            background: var(--primary-red);
            color: white;
            padding: 15px 50px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .donate-btn:hover {
            background: var(--dark-red);
            transform: scale(1.05);
        }

        .special-case-description {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.9);
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Navigation Arrows */
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .nav-arrow:hover {
            background: var(--primary-red);
            border-color: var(--primary-red);
        }

        .nav-arrow.prev { left: 30px; }
        .nav-arrow.next { right: 30px; }

        /* Slider Dots */
        .slider-controls {
            position: absolute;
            bottom: 80px;
            left: 0;
            width: 100%;
            display: flex;
            justify-content: center;
            z-index: 3;
        }

        .slider-dots { display: flex; gap: 10px; }
        
        .slider-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .slider-dot.active {
            background: var(--primary-red);
            transform: scale(1.2);
        }

        /* --- Vision & Mission Section --- */
        .vision-mission-section {
            padding: 80px 40px;
            background: var(--light-gray);
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            color: var(--primary-red);
            margin-bottom: 50px;
            position: relative;
            font-weight: 800;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary-red);
        }

        .vm-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); 
            gap: 50px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .vm-card {
            background: var(--white);
            border-radius: 15px;
            padding: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .vm-card:hover { transform: translateY(-10px); }

        .vm-card h3 {
            font-size: 2rem;
            color: var(--dark-gray);
            margin-bottom: 20px;
            border-left: 5px solid var(--primary-red);
            padding-left: 15px;
        }

        .vm-points { list-style: none; padding: 0; }
        
        .vm-points li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
        }
        
        .point-icon { color: var(--primary-red); }

        /* --- Upcoming Campaigns (Activities) Section --- */
        .activities-section {
            padding: 80px 40px;
            background: var(--white);
        }

        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); 
            gap: 40px;
            max-width: 1400px;
            margin: 0 auto;
            align-items: stretch; 
        }

        .activity-card {
            background: var(--white);
            border: 1px solid #eee;
            border-radius: 15px;
            padding: 30px;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .activity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            border-color: var(--light-red);
        }

        .activity-date-badge {
            background: var(--primary-red);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            width: fit-content;
            margin-bottom: 15px;
        }

        .activity-title {
            font-size: 1.4rem;
            color: var(--text-dark);
            margin-bottom: 15px;
            font-weight: 700;
        }

        .activity-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--medium-gray);
            font-size: 0.95rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .meta-item i { color: var(--primary-red); width: 20px; text-align: center; }

        .activity-desc {
            color: var(--medium-gray);
            line-height: 1.5;
            font-size: 1rem;
        }

        /* --- CTA Section --- */
        .cta-section {
            padding: 100px 20px;
            background: linear-gradient(rgba(220, 38, 38, 0.9), rgba(185, 28, 28, 0.9)), url('https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            text-align: center;
            color: white;
        }

        .cta-content h2 { font-size: 3rem; margin-bottom: 20px; font-weight: 800; }
        .cta-content p { font-size: 1.3rem; margin-bottom: 40px; max-width: 800px; margin-left: auto; margin-right: auto; }

        .btn {
            padding: 15px 40px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: inline-block;
            margin: 10px;
        }

        .btn-primary { background: white; color: var(--primary-red); }
        .btn-primary:hover { background: #f0f0f0; transform: translateY(-3px); }

        .btn-secondary { border: 2px solid white; color: white; background: transparent; }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); transform: translateY(-3px); }

        /* Responsive */
        @media (max-width: 1024px) {
            .vm-grid { grid-template-columns: 1fr; }
            .special-case-hero { height: auto; min-height: 700px; }
            .fund-title { font-size: 2rem; }
        }

        @media (max-width: 768px) {
            .special-case-hero { min-height: 600px; }
            .fund-raised-box { padding: 25px; margin: 0 10px; }
            .time-units { font-size: 0.9rem; flex-wrap: wrap; }
            .image-progress-bar { padding: 0 20px; flex-direction: column; height: auto; padding-bottom: 10px; }
            .progress-bar-track { margin: 10px 0; width: 100%; }
            .progress-left, .progress-right { width: 100%; text-align: center; margin: 5px 0; }
        }
    </style>
</head>
<body>

    <div class="main-container">

        <section class="special-case-hero">
            <div class="special-case-slides">
                <?php if (count($special_cases) > 0): ?>
                    <?php foreach ($special_cases as $index => $case): 
                        $percentage = ($case['goal'] > 0) ? round(($case['raised'] / $case['goal']) * 100) : 0;
                    ?>
                    <div class="special-case-slide <?php echo $index === 0 ? 'active' : ''; ?>" 
                         data-index="<?php echo $index; ?>"
                         data-end-date="<?php echo $case['end_date']; ?>">
                        
                        <div class="special-case-image-container">
                            <img src="<?php echo htmlspecialchars($case['image']); ?>" alt="Campaign Image" class="special-case-image">
                            
                            <div class="image-progress-bar">
                                <div class="progress-stats">
                                    <div class="progress-left">
                                        <div class="progress-label">RAISED</div>
                                        <div class="progress-amount">RM <?php echo number_format($case['raised'], 2); ?></div>
                                    </div>
                                    <div class="progress-bar-track">
                                        <div class="progress-bar-fill" style="width: <?php echo $percentage; ?>%">
                                            <div class="progress-percentage"><?php echo $percentage; ?>%</div>
                                        </div>
                                    </div>
                                    <div class="progress-right">
                                        <div class="progress-label">TARGET</div>
                                        <div class="progress-amount">RM <?php echo number_format($case['goal'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="special-case-content">
                            <div class="days-counter">
                                TIME LEFT TO HELP
                            </div>
                            <div class="time-units countdown-timer" id="timer-<?php echo $index; ?>">
                                <span>-- Days</span> <span>-- Hours</span> <span>-- Mins</span> <span>-- Secs</span>
                            </div>

                            <div class="fund-raised-box">
                                <div class="fund-tagline"><?php echo htmlspecialchars($case['tagline']); ?></div>
                                <div class="fund-title"><?php echo htmlspecialchars($case['title']); ?></div>
                                
                                <button type="button" class="donate-btn" onclick="<?php echo $case['btn_action']; ?>">
                                    DONATE NOW
                                </button>
                                
                                <div class="special-case-description">
                                    <?php echo htmlspecialchars($case['description']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="special-case-slide active">
                        <div class="special-case-content">
                            <h2>No Active Emergency Relief Cases.</h2>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="nav-arrow prev" onclick="prevSlide()"><i class="fas fa-chevron-left"></i></div>
            <div class="nav-arrow next" onclick="nextSlide()"><i class="fas fa-chevron-right"></i></div>

            <div class="slider-controls">
                <div class="slider-dots">
                    <?php foreach ($special_cases as $index => $case): ?>
                    <div class="slider-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

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


        <section class="activities-section">
            <h2 class="section-title">Upcoming Campaigns</h2>
            <div class="activities-grid">
                <?php if(count($activities) > 0): ?>
                    <?php foreach ($activities as $act): ?>
                    <div class="activity-card">
                        <div>
                            <div class="activity-date-badge">
                                <?php echo date('d M Y', strtotime($act['date'])); ?>
                            </div>
                            <h3 class="activity-title"><?php echo htmlspecialchars($act['title']); ?></h3>
                            <div class="activity-meta">
                                <div class="meta-item">
                                    <i class="far fa-clock"></i> <?php echo $act['time']; ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($act['location']); ?>
                                </div>
                            </div>
                            <p class="activity-desc">
                                <?php echo substr(htmlspecialchars($act['description']), 0, 100) . '...'; ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; width:100%;">No upcoming campaigns scheduled at the moment.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="cta-section">
            <div class="cta-content">
                <h2>Be the Bridge of Hope</h2>
                <p>Your generosity has the power to transform lives. Join us in building bridges of compassion.</p>
                <div>
                    <a href="#" class="btn btn-primary" onclick="<?php echo $cta_onclick; ?>">Make a Donation</a>
                    <a href="Campaign_Page.php" class="btn btn-secondary">View All Campaigns</a>
                </div>
            </div>
        </section>

    </div>

    <?php include 'footer.php'; ?>

    <script>
        // --- Slider Logic ---
        let currentSlide = 0;
        const slides = document.querySelectorAll('.special-case-slide');
        const dots = document.querySelectorAll('.slider-dot');
        
        function showSlide(index) {
            slides.forEach(s => s.classList.remove('active'));
            dots.forEach(d => d.classList.remove('active'));
            
            slides[index].classList.add('active');
            dots[index].classList.add('active');
            currentSlide = index;
        }

        function nextSlide() {
            let next = (currentSlide + 1) % slides.length;
            showSlide(next);
        }

        function prevSlide() {
            let prev = (currentSlide - 1 + slides.length) % slides.length;
            showSlide(prev);
        }

        // Auto play slider
        let slideInterval = setInterval(nextSlide, 6000); // 6 seconds

        // Event Listeners for Dots
        dots.forEach(dot => {
            dot.addEventListener('click', function() {
                clearInterval(slideInterval);
                const index = parseInt(this.getAttribute('data-index'));
                showSlide(index);
                slideInterval = setInterval(nextSlide, 6000);
            });
        });

        // --- Live Countdown Logic ---
        function updateCountdowns() {
            slides.forEach(slide => {
                const endDateStr = slide.getAttribute('data-end-date');
                if (!endDateStr) return;

                const endDate = new Date(endDateStr).getTime();
                const now = new Date().getTime();
                const distance = endDate - now;

                const timerDisplay = slide.querySelector('.countdown-timer');

                if (distance < 0) {
                    timerDisplay.innerHTML = "CAMPAIGN ENDED";
                    return;
                }

                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                timerDisplay.innerHTML = `
                    <span>${days} Days</span>
                    <span>${hours} Hours</span>
                    <span>${minutes} Mins</span>
                    <span>${seconds} Secs</span>
                `;
            });
        }

        updateCountdowns();
        setInterval(updateCountdowns, 1000);

        // --- Only called if NOT logged in ---
        function checkLogin(event) {
            if(event) event.preventDefault();
            
            Swal.fire({
                title: 'Login Required',
                text: "You need to login to make a donation.",
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#757575',
                confirmButtonText: 'Login Now'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'donor_login.php'; 
                }
            });
            return false;
        }
    </script>
</body>
</html>