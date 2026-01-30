<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'dataconnection.php'; 
include 'header_UI.php';


$logged_in = isset($_SESSION['donor_id']) && !empty($_SESSION['donor_id']);


$auto_update_sql = "UPDATE special_case 
                    SET Case_Status = 'Completed', Completed_At = NOW() 
                    WHERE Case_Category = 'Emergency Relief' 
                    AND Case_Status = 'Active' 
                    AND Raised_Amount >= Target_Amount 
                    AND Target_Amount > 0";
$conn->query($auto_update_sql);


$special_cases = [];
$query_sc = "SELECT * FROM special_case 
             WHERE Case_Category = 'Emergency Relief' 
             AND Case_Status = 'Active' 
             ORDER BY Created_At DESC LIMIT 5"; 

$result_sc = $conn->query($query_sc);

if ($result_sc && $result_sc->num_rows > 0) {
    while ($row = $result_sc->fetch_assoc()) {
        $image_url = "https://images.unsplash.com/photo-1579684385127-1ef15d508118?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80"; 
        if (!empty($row['Case_Images'])) {
            $decoded = json_decode($row['Case_Images'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) {
                $image_url = $decoded[0];
            } else {
                $image_url = $row['Case_Images'];
            }
        }

        $created_date = new DateTime($row['Created_At']);
        $now = new DateTime();
        $interval = $created_date->diff($now);
        
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


$donation_categories = [
    'emergency' => [
        'db_name' => 'Emergency Relief', 
        'name' => 'Emergency Relief', 
        'icon' => 'fas fa-first-aid', 
        'color' => '#d32f2f'
    ],
    'medical' => [
        'db_name' => 'Medical', 
        'name' => 'Medical Aid', 
        'icon' => 'fas fa-heartbeat', 
        'color' => '#f57c00'
    ],
    'disability' => [
        'db_name' => 'Disability Support', 
        'name' => 'Disability Support', 
        'icon' => 'fas fa-wheelchair', 
        'color' => '#f57c00'
    ],
    'elderly' => [
        'db_name' => 'Elderly Care', 
        'name' => 'Elderly Care', 
        'icon' => 'fas fa-user-friends', 
        'color' => '#f57c00'
    ],
    'children' => [
        'db_name' => 'Children Support', 
        'name' => 'Children Support', 
        'icon' => 'fas fa-child', 
        'color' => '#f57c00'
    ],
    'other' => [
        'db_name' => 'Other Cases', 
        'name' => 'Other Cases', 
        'icon' => 'fas fa-hand-holding-heart', 
        'color' => '#f57c00'
    ]
];

// --- [新] 5. 获取 News & Stories (Stories) ---
$stories = [];
$query_story = "SELECT * FROM story 
                WHERE Story_Status = 'Published' 
                ORDER BY Created_At DESC LIMIT 3";
$result_story = $conn->query($query_story);

if ($result_story && $result_story->num_rows > 0) {
    while ($row = $result_story->fetch_assoc()) {
        // Story Image Handling
        $story_img = "https://images.unsplash.com/photo-1504159506876-f8338247a14a?auto=format&fit=crop&q=80"; // Default
        if (!empty($row['Story_Image'])) {
            $decoded = json_decode($row['Story_Image'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) {
                $story_img = $decoded[0];
            } else {
            
                $story_img = $row['Story_Image'];
            }
        }

        
        $date_source = !empty($row['Story_Date']) ? $row['Story_Date'] : $row['Created_At'];
        $story_date = date('d M Y', strtotime($date_source));

       
        $story_link = "New&Story.php?id=" . $row['Story_ID'];

        $stories[] = [
            "id" => $row['Story_ID'],
            "title" => $row['Story_Title'],
            "category" => $row['Story_Category'],
            "image" => $story_img,
            "date" => $story_date,
            "description" => $row['Story_Description'],
            "link" => $story_link
        ];
    }
}


$activities = [];
$today = date('Y-m-d');

$query_act = "SELECT * FROM activity 
              WHERE Activity_Status IN ('Active', 'Upcoming', 'Ongoing') 
              AND (Activity_EndDate IS NULL OR Activity_EndDate >= '$today')
              ORDER BY Activity_StartDate ASC LIMIT 3";

$result_act = $conn->query($query_act);

if ($result_act && $result_act->num_rows > 0) {
    while ($row = $result_act->fetch_assoc()) {
        $is_ongoing = ($row['Activity_StartDate'] <= $today);
        $details_link = "donor_campaign_detail.php?id=" . $row['Activity_ID'];

        $activities[] = [
            "id" => $row['Activity_ID'],
            "title" => $row['Activity_Name'],
            "date" => $row['Activity_StartDate'], 
            "display_status" => $is_ongoing ? 'Ongoing' : date('d M Y', strtotime($row['Activity_StartDate'])),
            "is_ongoing" => $is_ongoing,
            "time" => date('h:i A', strtotime($row['Activity_StartDate'])), 
            "location" => $row['Activity_Venue'],
            "description" => $row['Activity_Description'],
            "link" => $details_link
        ];
    }
}

// --- Helper: CTA Button ---
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

        
        .special-case-hero {
            position: relative;
            height: 85vh; 
            min-height: 600px;
            overflow: hidden;
            margin-bottom: 0px; 
            background-color: #000; 
        }

       
        .special-case-slides { position: relative; width: 100%; height: 100%; }
        .special-case-slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 1s ease-in-out; visibility: hidden; }
        .special-case-slide.active { opacity: 1; z-index: 1; visibility: visible; }
        .special-case-image-container { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .special-case-image { width: 100%; height: 100%; object-fit: cover; filter: none; }
        .slide-overlay-gradient { position: absolute; bottom: 0; left: 0; width: 100%; height: 70%; background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.7) 60%, transparent 100%); z-index: 1; pointer-events: none; }
        .special-case-content { position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); z-index: 2; width: 90%; max-width: 1200px; text-align: left; color: white; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 30px; padding-bottom: 110px; height: 100%; pointer-events: none; }
        .special-case-content > * { pointer-events: auto; }
        .slide-text-content { flex: 1; min-width: 300px; margin-bottom: 20px; }
        .fund-tagline { font-size: 1rem; color: var(--primary-red); font-weight: 800; letter-spacing: 2px; margin-bottom: 5px; text-shadow: 0 2px 4px rgba(0,0,0,0.8); }
        .fund-title { font-size: 3rem; font-weight: 800; color: white; margin-bottom: 10px; line-height: 1.1; text-shadow: 0 2px 10px rgba(0,0,0,0.8); }
        .special-case-description { font-size: 1.1rem; color: rgba(255,255,255,0.9); line-height: 1.5; max-width: 600px; margin-bottom: 20px; text-shadow: 0 1px 3px rgba(0,0,0,0.8); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .slide-action-content { text-align: right; min-width: 250px; margin-bottom: 20px; }
        .days-counter { font-size: 0.9rem; font-weight: 700; color: #fbbf24; letter-spacing: 1px; margin-bottom: 5px; text-shadow: 0 1px 2px rgba(0,0,0,0.8); }
        .time-units { font-family: 'Courier New', monospace; font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 15px; text-shadow: 0 1px 3px rgba(0,0,0,0.8); }
        .donate-btn { display: inline-block; background: var(--primary-red); color: white; padding: 12px 35px; border-radius: 5px; text-decoration: none; font-size: 1.1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s ease; border: none; cursor: pointer; box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4); }
        .donate-btn:hover { background: var(--dark-red); transform: translateY(-2px); }
        .bottom-progress-container { position: absolute; bottom: 40px; left: 0; width: 100%; z-index: 10; pointer-events: auto; }
        .progress-stats-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px; color: white; font-family: 'Segoe UI', sans-serif; }
        .raised-amount { font-size: 2rem; font-weight: 800; color: var(--primary-red); line-height: 1; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        .raised-label { font-size: 0.8rem; color: rgba(255,255,255,0.7); text-transform: uppercase; margin-bottom: 2px; }
        .target-amount { font-size: 1.2rem; font-weight: 600; color: rgba(255,255,255,0.9); text-align: right; }
        .target-label { font-size: 0.8rem; color: rgba(255,255,255,0.5); text-transform: uppercase; margin-bottom: 2px; text-align: right; }
        .progress-bar-track { width: 100%; height: 12px; background: rgba(255,255,255,0.2); border-radius: 6px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #ef4444, #dc2626); border-radius: 6px; position: relative; transition: width 1s ease-out; }
        .nav-arrow { position: absolute; top: 50%; transform: translateY(-50%); color: rgba(255, 255, 255, 0.5); font-size: 30px; cursor: pointer; z-index: 3; transition: all 0.3s ease; padding: 20px; background: transparent; }
        .nav-arrow:hover { color: white; background: rgba(0,0,0,0.3); border-radius: 5px; }
        .nav-arrow.prev { left: 10px; }
        .nav-arrow.next { right: 10px; }
        .slider-controls { position: absolute; bottom: 15px; left: 50%; transform: translateX(-50%); z-index: 20; }
        .slider-dots { display: flex; gap: 8px; }
        .slider-dot { width: 30px; height: 4px; background: rgba(255, 255, 255, 0.4); cursor: pointer; transition: all 0.3s ease; border-radius: 2px; }
        .slider-dot.active { background: white; width: 45px; }

        /* --- [Modified] Intro & Categories Section --- */
        .categories-section { padding: 80px 40px; background: var(--white); }
        .section-title { text-align: center; font-size: 2.5rem; color: var(--primary-red); margin-bottom: 35px; position: relative; font-weight: 800; }
        .section-title::after { content: ''; position: absolute; bottom: -15px; left: 50%; transform: translateX(-50%); width: 80px; height: 4px; background: var(--primary-red); }
        
        .intro-text-box {
            max-width: 800px;
            margin: 0 auto 50px auto; /* Margin bottom to separate from grid */
            text-align: center;
        }
        .intro-text-box p {
            font-size: 1.2rem;
            line-height: 1.6;
            color: var(--medium-gray);
        }

        .categories-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
            gap: 30px; 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        .category-card {
            background: #fff;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
            cursor: pointer;
            text-decoration: none; 
            display: block;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: var(--light-red);
        }
        .cat-icon-wrapper {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: var(--light-red);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            transition: all 0.3s ease;
        }
        .category-card:hover .cat-icon-wrapper {
            background: var(--primary-red);
        }
        .cat-icon-wrapper i {
            font-size: 1.8rem;
            color: var(--primary-red);
            transition: all 0.3s ease;
        }
        .category-card:hover .cat-icon-wrapper i {
            color: white;
        }
        .cat-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-gray);
        }

        /* --- [New] Stories Section --- */
        .stories-section { padding: 80px 40px; background: var(--light-gray); }
        .stories-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 40px; 
            max-width: 1400px; 
            margin: 0 auto; 
        }
        .story-card-link { text-decoration: none; color: inherit; display: block; }
        .story-card {
            background: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .story-card-link:hover .story-card {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        .story-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .story-content {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .story-category {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--primary-red);
            font-weight: 700;
            margin-bottom: 10px;
        }
        .story-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 10px;
            line-height: 1.4;
            color: var(--dark-gray);
        }
        .story-excerpt {
            color: var(--medium-gray);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
            flex-grow: 1;
        }
        .story-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .read-more-txt {
            color: var(--primary-red);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* --- Activities Section (Styles unchanged) --- */
        .activities-section { padding: 80px 40px; background: var(--white); }
        /* ... [保留 Activity CSS] ... */
        .activities-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 40px; max-width: 1400px; margin: 0 auto; align-items: stretch; }
        .activity-card-link { text-decoration: none; color: inherit; display: block; }
        .activity-card { background: var(--white); border: 1px solid #eee; border-radius: 15px; padding: 30px; transition: all 0.3s ease; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; height: 100%; box-sizing: border-box; }
        .activity-card-link:hover .activity-card { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); border-color: var(--light-red); }
        .activity-date-badge { background: var(--primary-red); color: white; padding: 5px 15px; border-radius: 20px; font-weight: 600; font-size: 0.9rem; width: fit-content; margin-bottom: 15px; }
        .activity-date-badge.ongoing { background: #10b981; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
        .activity-title { font-size: 1.4rem; color: var(--text-dark); margin-bottom: 15px; font-weight: 700; }
        .activity-meta { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; color: var(--medium-gray); font-size: 0.95rem; }
        .meta-item { display: flex; align-items: center; gap: 10px; }
        .meta-item i { color: var(--primary-red); width: 20px; text-align: center; }
        .activity-desc { color: var(--medium-gray); line-height: 1.5; font-size: 1rem; }

        /* --- CTA Section (Unchanged) --- */
        .cta-section { padding: 100px 20px; background: linear-gradient(rgba(220, 38, 38, 0.9), rgba(185, 28, 28, 0.9)), url('https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&q=80'); background-size: cover; background-position: center; background-attachment: fixed; text-align: center; color: white; }
        .cta-content h2 { font-size: 3rem; margin-bottom: 20px; font-weight: 800; }
        .cta-content p { font-size: 1.3rem; margin-bottom: 40px; max-width: 800px; margin-left: auto; margin-right: auto; }
        .btn { padding: 15px 40px; border-radius: 5px; text-decoration: none; font-weight: 700; font-size: 1.1rem; transition: all 0.3s ease; display: inline-block; margin: 10px; }
        .btn-primary { background: white; color: var(--primary-red); }
        .btn-primary:hover { background: #f0f0f0; transform: translateY(-3px); }
        .btn-secondary { border: 2px solid white; color: white; background: transparent; }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); transform: translateY(-3px); }

        /* Responsive (Partial) */
        @media (max-width: 768px) {
            .special-case-content { flex-direction: column; align-items: flex-start; justify-content: flex-end; padding-bottom: 140px; }
            .slide-text-content, .slide-action-content { width: 100%; text-align: left; margin-bottom: 10px; }
            .slide-action-content { margin-top: 0; display: flex; flex-direction: column; align-items: flex-start; }
            .donate-btn { width: 100%; text-align: center; }
            .fund-title { font-size: 2rem; }
            .nav-arrow { display: none; }
            .raised-amount { font-size: 1.5rem; }
            .target-amount { font-size: 1rem; }
            .bottom-progress-container { bottom: 50px; }
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
                        $display_percentage = min($percentage, 100);
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

                            <div class="bottom-progress-container">
                                <div class="progress-stats-row">
                                    <div>
                                        <div class="raised-label">RAISED</div>
                                        <div class="raised-amount">RM <?php echo number_format($case['raised']); ?></div>
                                    </div>
                                    <div>
                                        <div class="target-label">TARGET</div>
                                        <div class="target-amount">RM <?php echo number_format($case['goal']); ?></div>
                                    </div>
                                </div>
                                <div class="progress-bar-track">
                                    <div class="progress-bar-fill" style="width: <?php echo $display_percentage; ?>%"></div>
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
        
        <section class="categories-section">
            <h2 class="section-title">Our Mission</h2>
            
            <div class="intro-text-box">
                <p>Love Bridge Foundation is a non-profit organization dedicated to creating positive change through compassion, care, and community support. Since 2010, we've been connecting generous hearts with those in need.</p>
            </div>

            <div class="categories-grid">
                <?php foreach($donation_categories as $key => $cat): ?>
                <a href="Special_Case Page.php?category=<?php echo urlencode($cat['db_name']); ?>" class="category-card">
                    <div class="cat-icon-wrapper">
                        <i class="<?php echo $cat['icon']; ?>"></i>
                    </div>
                    <div class="cat-name"><?php echo $cat['name']; ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="stories-section">
            <h2 class="section-title"> News & Stories</h2>
            <div class="stories-grid">
                <?php if(count($stories) > 0): ?>
                    <?php foreach ($stories as $story): ?>
                    <a href="<?php echo $story['link']; ?>" class="story-card-link">
                        <div class="story-card">
                            <img src="<?php echo htmlspecialchars($story['image']); ?>" alt="Story Image" class="story-image">
                            <div class="story-content">
                                <div class="story-category"><?php echo htmlspecialchars($story['category']); ?></div>
                                <div class="story-title"><?php echo htmlspecialchars($story['title']); ?></div>
                                <div class="story-excerpt">
                                    <?php echo substr(strip_tags($story['description']), 0, 90) . '...'; ?>
                                </div>
                                <div class="story-footer">
                                    <span><?php echo $story['date']; ?></span>
                                    <span class="read-more-txt">Read More <i class="fas fa-arrow-right"></i></span>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; width:100%;">No news stories available yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="activities-section">
            <h2 class="section-title">Campaigns</h2>
            <div class="activities-grid">
                <?php if(count($activities) > 0): ?>
                    <?php foreach ($activities as $act): ?>
                    
                    <a href="<?php echo $act['link']; ?>" class="activity-card-link">
                        <div class="activity-card">
                            <div>
                                <?php if($act['is_ongoing']): ?>
                                    <div class="activity-date-badge ongoing">
                                        Ongoing
                                    </div>
                                <?php else: ?>
                                    <div class="activity-date-badge">
                                        <?php echo $act['display_status']; ?>
                                    </div>
                                <?php endif; ?>
                                
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
                    </a>

                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; width:100%;">No upcoming activities scheduled at the moment.</p>
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
                if (!timerDisplay) return;

                if (distance < 0) {
                    timerDisplay.innerHTML = "ENDED";
                    return;
                }

                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

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