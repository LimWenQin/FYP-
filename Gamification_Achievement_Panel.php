<?php
// 1. 开启 Session
session_start();

// 引入数据库和统一的头部 (注意：这里改为 header_UI.php 以保持和 Homepage 一致)
include 'dataconnection.php';

// 2. 检查登录状态
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

// 4. 定义徽章逻辑
$badges = [
    'first_donation' => ($total_earned > 0),      
    'rm500_donor'    => ($total_earned >= 50),    
    'monthly_hero'   => ($total_earned >= 100)    
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Achievements - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* --- 引入 Homepage 核心变量 --- */
        :root {
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-red: #fee2e2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --medium-gray: #737373;
            --dark-gray: #262626;
            --text-dark: #171717;
            --gold: #fbbf24;
            --silver: #9ca3af;
            --bronze: #78350f;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-gray);
            color: var(--text-dark);
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            min-height: 80vh;
        }

        /* 标题部分 */
        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .page-title {
            font-size: 2.5rem;
            color: var(--primary-red);
            font-weight: 800;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary-red);
            border-radius: 2px;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--medium-gray);
            max-width: 600px;
            margin: 0 auto;
        }

        /* --- 积分卡片 (Hero Stats) --- */
        .points-hero {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            border-radius: 15px;
            padding: 40px;
            color: white;
            text-align: center;
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.3);
            margin-bottom: 50px;
            position: relative;
            overflow: hidden;
        }

        .points-hero::before {
            content: '\f005'; /* Star icon */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 15rem;
            opacity: 0.1;
            color: white;
        }

        .points-value {
            font-size: 5rem;
            font-weight: 800;
            margin: 10px 0;
            line-height: 1;
        }

        .points-label {
            font-size: 1.2rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.9;
        }

        .points-sub {
            background: rgba(255, 255, 255, 0.2);
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            margin-top: 15px;
            font-size: 0.9rem;
        }

        /* --- Section Styling --- */
        .section-title {
            font-size: 1.8rem;
            color: var(--text-dark);
            margin-bottom: 25px;
            border-left: 5px solid var(--primary-red);
            padding-left: 15px;
            font-weight: 700;
        }

        /* --- Badges Grid --- */
        .badge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 60px;
        }

        .badge-card {
            background: var(--white);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid #eee;
            position: relative;
        }

        .badge-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        /* 图标容器 */
        .badge-icon-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: var(--light-gray);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            transition: all 0.3s;
        }

        .badge-card h4 {
            margin: 0 0 10px;
            font-size: 1.2rem;
            color: var(--text-dark);
        }

        .badge-card p {
            color: var(--medium-gray);
            font-size: 0.9rem;
            margin: 0;
        }

        /* 状态逻辑 */
        .badge-card.earned .badge-icon-wrapper {
            background: #fee2e2; /* Light Red */
            color: var(--primary-red);
            box-shadow: 0 0 0 5px rgba(220, 38, 38, 0.1);
        }

        .badge-card.locked {
            opacity: 0.7;
        }
        
        .badge-card.locked .badge-icon-wrapper {
            background: #f3f4f6;
            color: #d1d5db;
        }

        .badge-card.locked h4 { color: var(--medium-gray); }
        
        .lock-icon {
            position: absolute;
            top: 15px;
            right: 15px;
            color: var(--medium-gray);
            font-size: 1.2rem;
        }

        /* --- Achievement Wall --- */
        .wall-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .achievement-item {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .achievement-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .achievement-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .achievement-content {
            padding: 20px;
        }

        .achievement-text {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .share-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-share {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            color: var(--medium-gray);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-share:hover {
            background: var(--light-gray);
            color: var(--primary-red);
            border-color: var(--primary-red);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .points-value { font-size: 3.5rem; }
            .badge-grid, .wall-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

<?php include 'header_UI.php'; ?>

<div class="main-container">

    <div class="page-header">
        <h1 class="page-title">My Gamification Panel</h1>
        <p class="page-subtitle">Track your impact, earn badges, and share your journey.</p>
    </div>

    <div class="points-hero">
        <div class="points-label">Current Balance</div>
        <div class="points-value"><?php echo number_format($current_points); ?></div>
        <div class="points-label">Points</div>
        <div class="points-sub">
            Lifetime Earned: <strong><?php echo number_format($total_earned); ?> PTS</strong>
        </div>
    </div>

    <h3 class="section-title">Your Badges</h3>
    <div class="badge-grid">
        
        <div class="badge-card <?php echo $badges['first_donation'] ? 'earned' : 'locked'; ?>">
            <?php if(!$badges['first_donation']): ?><i class="fas fa-lock lock-icon"></i><?php endif; ?>
            <div class="badge-icon-wrapper">
                <i class="fas fa-heart"></i>
            </div>
            <h4>First Donation</h4>
            <p><?php echo $badges['first_donation'] ? 'Unlocked!' : 'Donate once to unlock'; ?></p>
        </div>

        <div class="badge-card <?php echo $badges['rm500_donor'] ? 'earned' : 'locked'; ?>">
            <?php if(!$badges['rm500_donor']): ?><i class="fas fa-lock lock-icon"></i><?php endif; ?>
            <div class="badge-icon-wrapper">
                <i class="fas fa-crown"></i>
            </div>
            <h4>RM 500 Donor</h4>
            <p><?php echo $badges['rm500_donor'] ? 'Unlocked!' : 'Earn 50 points to unlock'; ?></p>
        </div>

        <div class="badge-card <?php echo $badges['monthly_hero'] ? 'earned' : 'locked'; ?>">
            <?php if(!$badges['monthly_hero']): ?><i class="fas fa-lock lock-icon"></i><?php endif; ?>
            <div class="badge-icon-wrapper">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h4>Monthly Hero</h4>
            <p><?php echo $badges['monthly_hero'] ? 'Unlocked!' : 'Earn 100 points to unlock'; ?></p>
        </div>

    </div>

    <h3 class="section-title">Achievements Wall</h3>
    <div class="wall-grid">

        <div class="achievement-item">
            <img src="https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Orphanage Support" class="achievement-img">
            <div class="achievement-content">
                <div class="achievement-text">Supported Education for Orphanage</div>
                <div class="share-buttons">
                    <button class="btn-share"><i class="fab fa-whatsapp"></i> Share</button>
                    <button class="btn-share"><i class="fab fa-facebook"></i> Share</button>
                </div>
            </div>
        </div>

        <div class="achievement-item">
            <img src="https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Medical Support" class="achievement-img">
            <div class="achievement-content">
                <div class="achievement-text">Elderly Medical Care Sponsor</div>
                <div class="share-buttons">
                    <button class="btn-share"><i class="fab fa-whatsapp"></i> Share</button>
                    <button class="btn-share"><i class="fab fa-facebook"></i> Share</button>
                </div>
            </div>
        </div>

        <div class="achievement-item">
            <img src="https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Charity Run" class="achievement-img">
            <div class="achievement-content">
                <div class="achievement-text">Annual Charity Run Participant</div>
                <div class="share-buttons">
                    <button class="btn-share"><i class="fab fa-whatsapp"></i> Share</button>
                    <button class="btn-share"><i class="fab fa-facebook"></i> Share</button>
                </div>
            </div>
        </div>

    </div>

</div>

<?php include 'footer.php'; ?>

</body>
</html>