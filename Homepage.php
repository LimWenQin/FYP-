<?php
// 1. 启动 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'dataconnection.php'; 
include 'header_UI.php';

// --- 2. 检查登录状态 ---
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
        
        // 按钮逻辑
        if ($logged_in) {
            $target_url = "S_C_Payment_Page.php?case_id=" . $row['Case_ID'];
            $btn_onclick = "window.location.href='$target_url';";
        } else {
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
            "btn_action" => $btn_onclick
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
$cta_url = "Payment_page.php"; 
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

        /* --- Special Case Hero Slider (核心修改区域) --- */
        .special-case-hero {
            position: relative;
            height: 85vh; /* 保持大屏占比 */
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
            /* [修改] 移除变暗滤镜，让照片清晰 */
            filter: none; 
        }

        /* [新增] 底部渐变遮罩，确保文字可读，但又不遮挡照片主体 */
        .slide-overlay-gradient {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 60%; /* 只遮挡下半部分 */
            background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.6) 50%, transparent 100%);
            z-index: 1;
            pointer-events: none; /* 让点击穿透 */
        }

        /* [修改] 内容容器：移到底部 */
        .special-case-content {
            position: absolute;
            bottom: 40px; /* 距离底部留出空间 */
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            width: 90%;
            max-width: 1200px;
            text-align: left; /* 改为左对齐，通常更易读 */
            color: white;
            display: flex;
            justify-content: space-between; /* 左右布局 */
            align-items: flex-end; /* 底部对齐 */
            flex-wrap: wrap;
            gap: 20px;
        }

        /* 左侧：标题和描述 */
        .slide-text-content {
            flex: 1;
            min-width: 300px;
        }

        .fund-tagline {
            font-size: 1rem;
            color: var(--primary-red);
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.8);
        }

        .fund-title {
            font-size: 3rem; /* 大标题 */
            font-weight: 800;
            color: white;
            margin-bottom: 10px;
            line-height: 1.1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.8);
        }

        .special-case-description {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.9);
            line-height: 1.5;
            max-width: 600px;
            margin-bottom: 20px;
            text-shadow: 0 1px 3px rgba(0,0,0,0.8);
            display: -webkit-box;
            -webkit-line-clamp: 2; /* 只显示两行 */
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* 右侧：倒计时和按钮 */
        .slide-action-content {
            text-align: right;
            min-width: 250px;
        }

        .days-counter {
            font-size: 0.9rem;
            font-weight: 700;
            color: #fbbf24;
            letter-spacing: 1px;
            margin-bottom: 5px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.8);
        }

        .time-units {
            font-family: 'Courier New', monospace; /* 更有倒计时感觉 */
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 15px;
            text-shadow: 0 1px 3px rgba(0,0,0,0.8);
        }

        .donate-btn {
            display: inline-block;
            background: var(--primary-red);
            color: white;
            padding: 12px 35px;
            border-radius: 5px; /* 稍微方一点更现代 */
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
        }

        .donate-btn:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
        }

        /* 底部进度条 (精简版) */
        .bottom-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 8px; /* 很细 */
            background: rgba(255,255,255,0.2);
            z-index: 3;
        }

        .bottom-progress-fill {
            height: 100%;
            background: var(--primary-red); /* 醒目的红色 */
            position: relative;
            transition: width 1s ease;
        }

        /* 悬浮在进度条上的小标签 */
        .progress-tooltip {
            position: absolute;
            right: 0;
            bottom: 12px;
            background: var(--primary-red);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .progress-tooltip::after {
            content: '';
            position: absolute;
            bottom: -4px;
            right: 10px;
            border-width: 4px 4px 0;
            border-style: solid;
            border-color: var(--primary-red) transparent transparent transparent;
        }

        /* Navigation Arrows */
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            font-size: 30px;
            cursor: pointer;
            z-index: 3;
            transition: all 0.3s ease;
            padding: 20px;
            background: transparent;
        }

        .nav-arrow:hover {
            color: white;
            background: rgba(0,0,0,0.3); /* 鼠标移上去才显现背景 */
            border-radius: 5px;
        }

        .nav-arrow.prev { left: 10px; }
        .nav-arrow.next { right: 10px; }

        /* Slider Dots */
        .slider-controls {
            position: absolute;
            bottom: 15px; /* 贴近底部 */
            left: 50%;
            transform: translateX(-50%);
            z-index: 4;
        }

        .slider-dots { display: flex; gap: 8px; }
        
        .slider-dot {
            width: 30px; /* 线条型圆点 */
            height: 4px;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .slider-dot.active {
            background: white;
            width: 45px; /* 激活时变长 */
        }

        /* --- Vision & Mission Section (保持原样) --- */
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
            .special-case-hero { height: auto; min-height: 600px; }
            .fund-title { font-size: 2.5rem; }
        }

        @media (max-width: 768px) {
            .special-case-content {
                flex-direction: column;
                align-items: flex-start;
                bottom: 80px; /* 给进度条留位置 */
            }
            .slide-text-content, .slide-action-content {
                width: 100%;
                text-align: left;
            }
            .slide-action-content { margin-top: 20px; }
            .fund-title { font-size: 2rem; }
            .nav-arrow { display: none; } /* 移动端隐藏箭头 */
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
                            <div class="slide-overlay-gradient"></div>
                        </div>

                        <div class="special-case-content">
                            <div class="slide-text-content">
                                <div class="fund-tagline"><?php echo htmlspecialchars($case['tagline']); ?></div>
                                <div class="fund-title"><?php echo htmlspecialchars($case['title']); ?></div>
                                <div class="special-case-description">
                                    <?php echo htmlspecialchars($case['description']); ?>
                                </div>
                            </div>

                            <div class="slide-action-content">
                                <div class="days-counter">TIME LEFT</div>
                                <div class="time-units countdown-timer" id="timer-<?php echo $index; ?>">
                                    --d --h --m --s
                                </div>
                                <button type="button" class="donate-btn" onclick="<?php echo $case['btn_action']; ?>">
                                    Donate Now
                                </button>
                            </div>
                        </div>
                        
                        <div class="bottom-progress-bar">
                            <div class="bottom-progress-fill" style="width: <?php echo $percentage; ?>%">
                                <div class="progress-tooltip">
                                    RM <?php echo number_format($case['raised']); ?> / <?php echo number_format($case['goal']); ?> (<?php echo $percentage; ?>%)
                                </div>
                            </div>
                        </div>

                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="special-case-slide active">
                        <div class="special-case-image-container">
                             <img src="https://images.unsplash.com/photo-1532629345422-7515f3d16bb6?auto=format&fit=crop&q=80" class="special-case-image">
                             <div class="slide-overlay-gradient"></div>
                        </div>
                        <div class="special-case-content">
                            <div class="slide-text-content">
                                <div class="fund-title">No Active Emergency Cases</div>
                                <div class="special-case-description">Thank you for your support. Please check back later or view our other campaigns.</div>
                            </div>
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
        let slideInterval = setInterval(nextSlide, 6000); 

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
                    timerDisplay.innerHTML = "ENDED";
                    return;
                }

                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                // 简化倒计时显示，适合放在侧边
                timerDisplay.innerHTML = `${days}d ${hours}h ${minutes}m ${seconds}s`;
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