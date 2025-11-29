<?php
// 1. 引入必要文件和开启 Session
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI_2.php';

// 2. 检查登录状态 (必须登录才能看积分)
if (!isset($_SESSION['donor_id'])) {
    echo "<script>
            alert('Please login first to view your achievements.');
            window.location.href = 'donor_login.php';
          </script>";
    exit();
}

$donor_id = $_SESSION['donor_id'];
$current_points = 0;
$total_earned = 0;

// 3. 从数据库获取积分
$sql = "SELECT Points_Total, Points_Earned FROM point WHERE Donor_ID = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $current_points = $row['Points_Total'];
        $total_earned = $row['Points_Earned'];
    }
    $stmt->close();
}

// 4. 定义徽章逻辑 (示例规则)
// 这里的 'active' 类名用于 CSS 高亮，您可以根据需要调整阈值
$badges = [
    'first_donation' => ($total_earned > 0),      // 只要赚过分就算第一次捐款
    'rm500_donor'    => ($total_earned >= 50),    // 假设 RM500 = 50分
    'monthly_hero'   => ($total_earned >= 100)    // 假设月捐或累计达标
];

?>

<!DOCTYPE html>
<html>
<head>
<title>Gamification Achievements</title>
<style>
    body {
        background-color: #FFF5E4;
        margin: 0;
        font-family: Arial;
        color: #4A4A4A;
    }

    /* ---------------- HEADER ---------------- */
    /* 由于使用了 header_UI_2.php，这里的 header 样式主要是为了覆盖默认样式，使其匹配您的设计 */
    .header {
        background: linear-gradient(180deg, #ff6b9d 0%, #ff8fab 50%, #ffb3c6 100%) !important;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .header .function-links a, .header .logo {
        color: #ffffff !important;
    }

    /* ---------------- CONTENT ---------------- */
    .container {
        padding: 30px 50px;
    }

    h2 {
        color: #F28585;
    }

    /* Points Card */
    .points-box {
        background: white;
        padding: 25px;
        border-radius: 15px;
        width: 350px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        margin-bottom: 25px;
        text-align: center;
    }

    .points-box h3 {
        color: #F28585;
        font-size: 22px;
    }

    .points-value {
        font-size: 48px;
        color: #91C8A8;
        font-weight: bold;
        margin-top: 10px;
    }

    /* Badges Section */
    .badges-container {
        margin-top: 30px;
    }

    .badge-grid {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .badge {
        background: white;
        width: 150px;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        transition: transform 0.2s, opacity 0.3s;
        
        /* 默认灰显未获得的徽章 */
        opacity: 0.5;
        filter: grayscale(100%);
    }

    /* 获得的徽章高亮显示 */
    .badge.earned {
        opacity: 1;
        filter: grayscale(0%);
        border: 2px solid #91C8A8;
    }

    .badge:hover {
        transform: translateY(-5px);
    }

    .badge img {
        width: 80px;
        height: 80px;
        margin-bottom: 10px;
    }

    /* Achievement Wall */
    .wall {
        margin-top: 40px;
    }

    .wall-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-top: 20px;
    }

    .achievement-card {
        background: white;
        border-radius: 12px;
        padding: 15px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        text-align: center;
    }

    .achievement-card img {
        width: 100%;
        border-radius: 10px;
        margin-bottom: 10px;
    }

    .social-share {
        margin-top: 10px;
        display: flex;
        justify-content: center;
        gap: 10px;
    }

    .social-share button {
        background-color: #A8D5BA;
        border: none;
        padding: 8px 12px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
        color: #4A4A4A;
    }

    .social-share button:hover {
        background-color: #91C8A8;
    }

</style>
</head>

<body>

<div class="container">

    <h2>My Gamification Panel</h2>
    <p>查看您的积分、捐款勋章以及个人成就墙。</p>

    <div class="points-box">
        <h3>My Points</h3>
        <div class="points-value"><?php echo number_format($current_points); ?></div>
        <p style="font-size: 14px; color: #888;">Total Earned: <?php echo number_format($total_earned); ?></p>
    </div>

    <div class="badges-container">
        <h2>My Badges</h2>

        <div class="badge-grid">
            <div class="badge <?php echo $badges['first_donation'] ? 'earned' : ''; ?>">
                <img src="badge1.png" alt="First Donation Badge">
                <p>First Donation</p>
                <?php if(!$badges['first_donation']): ?>
                    <small style="color:#999; font-size:12px;">Donate once to unlock</small>
                <?php endif; ?>
            </div>

            <div class="badge <?php echo $badges['rm500_donor'] ? 'earned' : ''; ?>">
                <img src="badge2.png" alt="RM500 Badge">
                <p>RM 500 Donor</p>
                <?php if(!$badges['rm500_donor']): ?>
                    <small style="color:#999; font-size:12px;">Reach 50 pts to unlock</small>
                <?php endif; ?>
            </div>

            <div class="badge <?php echo $badges['monthly_hero'] ? 'earned' : ''; ?>">
                <img src="badge3.png" alt="Monthly Hero Badge">
                <p>Monthly Hero</p>
                <?php if(!$badges['monthly_hero']): ?>
                    <small style="color:#999; font-size:12px;">Reach 100 pts to unlock</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="wall">
        <h2>Achievements Wall</h2>
        <p>仅自己可见 · 可分享至社交媒体</p>

        <div class="wall-grid">

            <div class="achievement-card">
                <img src="img1.jpg" alt="achievement">
                <p>帮助孤儿院购买学习用品</p>

                <div class="social-share">
                    <button>WhatsApp</button>
                    <button>Facebook</button>
                    <button>Instagram</button>
                </div>
            </div>

            <div class="achievement-card">
                <img src="img2.jpg" alt="achievement">
                <p>赞助老人院医疗支援</p>

                <div class="social-share">
                    <button>WhatsApp</button>
                    <button>Facebook</button>
                    <button>Instagram</button>
                </div>
            </div>

            <div class="achievement-card">
                <img src="img3.jpg" alt="achievement">
                <p>参与年度慈善义跑</p>

                <div class="social-share">
                    <button>WhatsApp</button>
                    <button>Facebook</button>
                    <button>Instagram</button>
                </div>
            </div>

        </div>
    </div>

</div>

</body>
</html>