<?php
// about_us.php
session_start();
include 'dataconnection.php';
include 'header_function.php';

// 1. Fetch Dynamic Content from about_us_info
$pageData = [];
$check = $conn->query("SELECT * FROM about_us_info LIMIT 1");
if ($check && $check->num_rows > 0) {
    $pageData = $check->fetch_assoc();
} else {
    // Fallback if table is empty (prevent crash)
    $pageData = [
        'hero_title' => 'Building Bridges of Love and Hope',
        'hero_description' => 'Love Bridge Foundation is a non-profit organization...',
        'story_title' => 'Our Story',
        'story_content' => 'Founded in 2010...',
        'vision_desc' => '',
        'vision_points' => '[]',
        'mission_desc' => '',
        'mission_points' => '[]',
        'core_values' => '[]',
        'focus_areas' => '[]'
    ];
}

// Decode JSON Fields
$visionPoints = json_decode($pageData['vision_points'], true) ?? [];
$missionPoints = json_decode($pageData['mission_points'], true) ?? [];
$coreValues = json_decode($pageData['core_values'], true) ?? [];
$focusAreas = json_decode($pageData['focus_areas'], true) ?? [];

include 'header_UI.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Love Bridge Foundation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; line-height: 1.6; background-color: #f8f9fa; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        .hero-section {
            background: linear-gradient(135deg, rgba(211, 47, 47, 0.9) 0%, rgba(183, 28, 28, 0.9) 100%), url('images/hero-bg.jpg');
            background-size: cover; background-position: center;
            color: white; padding: 100px 0; text-align: center; margin-bottom: 60px;
        }
        .hero-section h1 { font-size: 3.5rem; margin-bottom: 20px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
        .hero-section p { font-size: 1.3rem; max-width: 800px; margin: 0 auto 30px; opacity: 0.95; }

        .about-content { margin-bottom: 60px; }
        .section-title { color: #d32f2f; font-size: 2.5rem; margin-bottom: 40px; padding-bottom: 15px; border-bottom: 2px solid #ffcdd2; text-align: center; }

        /* --- Vision & Mission --- */
        .mission-vision { display: grid; grid-template-columns: repeat(2, 1fr); gap: 40px; margin-bottom: 60px; }
        .mission-box, .vision-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transition: transform 0.3s ease; }
        .mission-box:hover, .vision-box:hover { transform: translateY(-5px); }
        .mission-box h3, .vision-box h3 { color: #d32f2f; margin-bottom: 20px; font-size: 1.8rem; border-bottom: 2px solid #ffcdd2; padding-bottom: 10px; display: inline-block; }
        .mission-box ul, .vision-box ul { list-style: none; padding-left: 0; margin-top: 15px; }
        .mission-box li, .vision-box li { margin-bottom: 12px; display: flex; align-items: flex-start; }
        .point-icon { color: #d32f2f; font-weight: bold; margin-right: 10px; font-size: 1.2rem; line-height: 1.4; }

        .values-section { margin-bottom: 60px; }
        .values-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; }
        .value-item { background: white; padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transition: transform 0.3s ease; }
        .value-item:hover { transform: translateY(-10px); }
        .value-item h4 { color: #d32f2f; margin-bottom: 15px; font-size: 1.3rem; }

        .story-section { background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); padding: 60px 0; margin-bottom: 60px; border-radius: 10px; }
        .story-content { max-width: 800px; margin: 0 auto; text-align: center; }
        .story-content h2 { color: #d32f2f; margin-bottom: 30px; }

        .team-section { margin-bottom: 60px; }
        .team-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; }
        .team-member { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; }
        .team-img { height: 250px; background: #ddd; width: 100%; position: relative; }
        .team-img img {  height: 100%; object-fit: cover; }
        .team-info { padding: 25px; }
        .team-info h4 { color: #d32f2f; margin-bottom: 10px; }
        
        .cta-section { background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); color: white; padding: 80px 0; text-align: center; border-radius: 10px; }
        .cta-section h2 { font-size: 2.5rem; margin-bottom: 20px; }

        @media (max-width: 768px) {
            .mission-vision, .values-grid, .team-grid { grid-template-columns: 1fr; }
            .hero-section h1 { font-size: 2.5rem; }
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="container">
            <h1><?php echo htmlspecialchars($pageData['hero_title']); ?></h1>
            <p><?php echo nl2br(htmlspecialchars($pageData['hero_description'])); ?></p>
        </div>
    </div>

    <div class="container about-content">
        <h2 class="section-title">Our Story</h2>
        <div class="story-section">
            <div class="story-content">
                <h2><?php echo htmlspecialchars($pageData['story_title']); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($pageData['story_content'])); ?></p>
            </div>
        </div>

        <h2 class="section-title">Our Purpose</h2>
        
        <div class="mission-vision">
            <div class="vision-box">
                <h3>Our Vision</h3>
                <p><?php echo nl2br(htmlspecialchars($pageData['vision_desc'])); ?></p>
                <ul>
                    <?php foreach($visionPoints as $point): ?>
                        <li><span class="point-icon">•</span> <?php echo htmlspecialchars($point); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="mission-box">
                <h3>Our Mission</h3>
                <p><?php echo nl2br(htmlspecialchars($pageData['mission_desc'])); ?></p>
                <ul>
                    <?php foreach($missionPoints as $point): ?>
                        <li><span class="point-icon">•</span> <?php echo htmlspecialchars($point); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <h2 class="section-title">Our Core Values</h2>
        <div class="values-section">
            <div class="values-grid">
                <?php foreach($coreValues as $val): ?>
                <div class="value-item">
                    <h4><?php echo htmlspecialchars($val['title']); ?></h4>
                    <p><?php echo htmlspecialchars($val['desc']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <h2 class="section-title">Our Focus Areas</h2>
        <div class="values-section">
            <div class="values-grid">
                <?php foreach($focusAreas as $area): ?>
                <div class="value-item">
                    <h4><?php echo htmlspecialchars($area['title']); ?></h4>
                    <p><?php echo htmlspecialchars($area['desc']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <h2 class="section-title">Our Founder</h2>
        <div class="team-section">
            <div class="team-grid">
                <?php
                if (isset($conn)) {
                    $team_query = "SELECT * FROM team_members";
                    $team_result = $conn->query($team_query);

                    if ($team_result && $team_result->num_rows > 0) {
                        while ($member = $team_result->fetch_assoc()) {
                            $display_image = 'images/default_user.jpg';
                            if (!empty($member['Images'])) {
                                $decoded_images = json_decode($member['Images'], true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && !empty($decoded_images)) {
                                    $display_image = $decoded_images[0];
                                }
                            }
                            ?>
                            <div class="team-member">
                                <div class="team-img">
                                    <img src="<?php echo htmlspecialchars($display_image); ?>" 
                                         alt="<?php echo htmlspecialchars($member['Name']); ?>"
                                         onerror="this.onerror=null;this.src='images/default_user.jpg';">
                                </div>
                                <div class="team-info">
                                    <h4><?php echo htmlspecialchars($member['Name']); ?></h4>
                                    <p style="color: #d32f2f; font-weight: bold; margin-bottom: 5px;">
                                        <?php echo htmlspecialchars($member['Position']); ?>
                                    </p>
                                    <p><?php echo htmlspecialchars($member['Description']); ?></p>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<p style='text-align:center; grid-column: 1/-1;'>No founder found.</p>";
                    }
                }
                ?>
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