<?php
// 1. 开启 Session
session_start();

// 引入数据库和头部
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

// ==========================================
// 3. 获取基础数据 (积分)
// ==========================================
$current_points = 0;
$total_points_earned = 0;

$sql_pts = "SELECT Points_Total, Points_Earned FROM point WHERE Donor_ID = ?";
if ($stmt = $conn->prepare($sql_pts)) {
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $current_points = $row['Points_Total'];
        $total_points_earned = $row['Points_Earned'];
    }
    $stmt->close();
}

// ==========================================
// 4. 获取高级统计数据 (用于计算成就)
// ==========================================

// A. 总捐款次数 & 终身累计捐款总额 (Lifetime Amount)
$total_donations_count = 0;
$lifetime_amount = 0.00;

$sql_stats = "SELECT COUNT(*) as cnt, SUM(Order_Amount) as total_amt 
              FROM orders 
              WHERE Donor_ID = ? AND Order_Status IN ('Success', 'Completed')";
if ($stmt = $conn->prepare($sql_stats)) {
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $total_donations_count = $row['cnt'];
        $lifetime_amount = $row['total_amt'] ?? 0;
    }
    $stmt->close();
}

// B. 单月最高捐款额 (Max Monthly Donation)
$max_monthly_amount = 0;
$sql_max_month = "SELECT SUM(Order_Amount) as month_total 
                  FROM orders 
                  WHERE Donor_ID = ? AND Order_Status IN ('Success', 'Completed')
                  GROUP BY DATE_FORMAT(Order_Created_At, '%Y-%m') 
                  ORDER BY month_total DESC LIMIT 1";
if ($stmt = $conn->prepare($sql_max_month)) {
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $max_monthly_amount = $row['month_total'];
    $stmt->close();
}

// C. 过去一年累计捐款 (Annual Accumulation)
$annual_total = 0;
$sql_annual = "SELECT SUM(Order_Amount) as year_total 
               FROM orders 
               WHERE Donor_ID = ? AND Order_Status IN ('Success', 'Completed')
               AND Order_Created_At >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
if ($stmt = $conn->prepare($sql_annual)) {
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $annual_total = $row['year_total'];
    $stmt->close();
}

// D. 连续捐款时长 (月捐计划持续时间)
$recurring_months = 0;
$sql_rec = "SELECT Recurring_StartDate FROM recurring_donation 
            WHERE Donor_ID = ? AND Recurring_Status = 'Active' 
            ORDER BY Recurring_StartDate ASC LIMIT 1";
if ($stmt = $conn->prepare($sql_rec)) {
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $start_date = new DateTime($row['Recurring_StartDate']);
        $now = new DateTime();
        $interval = $start_date->diff($now);
        $recurring_months = ($interval->y * 12) + $interval->m;
    }
    $stmt->close();
}

// ==========================================
// 5. 定义所有徽章逻辑 (统一列表)
// ==========================================
$badges = [
    // --- 频次类 ---
    'first_donation' => [
        'unlocked' => ($total_donations_count >= 1),
        'title' => 'First Step',
        'desc' => 'Make your first donation.',
        'icon' => 'fa-hand-holding-heart',
        'class' => ''
    ],
    '3_donations' => [
        'unlocked' => ($total_donations_count >= 3),
        'title' => 'Rising Star',
        'desc' => 'Donate 3 times to unlock.',
        'icon' => 'fa-star',
        'class' => ''
    ],
    '10_donations' => [
        'unlocked' => ($total_donations_count >= 10),
        'title' => 'Philanthropist',
        'desc' => 'Donate 10 times to unlock.',
        'icon' => 'fa-users-rays',
        'class' => ''
    ],

    // --- 月捐类 ---
    'rec_3months' => [
        'unlocked' => ($recurring_months >= 3),
        'title' => 'Quarterly Guardian',
        'desc' => 'Active recurring donor for 3 months.',
        'icon' => 'fa-shield-heart',
        'class' => ''
    ],
    'rec_1year' => [
        'unlocked' => ($recurring_months >= 12),
        'title' => 'Loyalty Legend',
        'desc' => 'Active recurring donor for 1 year.',
        'icon' => 'fa-calendar-check',
        'class' => ''
    ],

    // --- 累计金额类 (Lifetime) ---
    'life_2k' => [
        'unlocked' => ($lifetime_amount >= 2000),
        'title' => 'Big Heart',
        'desc' => 'Total lifetime donation reaches RM 2,000.',
        'icon' => 'fa-heart-circle-plus',
        'class' => ''
    ],
    'life_20k' => [
        'unlocked' => ($lifetime_amount >= 20000),
        'title' => 'Community Hero',
        'desc' => 'Total lifetime donation reaches RM 20,000.',
        'icon' => 'fa-building-ngo',
        'class' => ''
    ],

    // --- 单月/年度大额类 (Special) ---
    'monthly_1k' => [
        'unlocked' => ($max_monthly_amount >= 1000),
        'title' => 'Bronze Patron',
        'desc' => 'Donate RM 1,000+ in a single month.',
        'icon' => 'fa-medal',
        'class' => 'bronze'
    ],
    'monthly_5k' => [
        'unlocked' => ($max_monthly_amount >= 5000),
        'title' => 'Silver Patron',
        'desc' => 'Donate RM 5,000+ in a single month.',
        'icon' => 'fa-award',
        'class' => 'silver'
    ],
    'monthly_10k' => [
        'unlocked' => ($max_monthly_amount >= 10000),
        'title' => 'Gold Patron',
        'desc' => 'Donate RM 10,000+ in a single month.',
        'icon' => 'fa-crown',
        'class' => 'gold'
    ],
    'annual_50k' => [
        'unlocked' => ($annual_total >= 50000),
        'title' => 'Diamond Partner',
        'desc' => 'Accumulate RM 50,000 in 1 year.',
        'icon' => 'fa-trophy',
        'class' => 'diamond'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Achievements - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- 核心变量 --- */
        :root {
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-gray: #f5f5f5;
            --white: #ffffff;
            --medium-gray: #737373;
            --text-dark: #171717;
            
            /* 勋章颜色 */
            --bronze-color: #cd7f32;
            --silver-color: #94a3b8;
            --gold-color: #fbbf24;
            --diamond-color: #3b82f6;
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
        .page-header { text-align: center; margin-bottom: 50px; }
        .page-title {
            font-size: 2.5rem; color: var(--primary-red); font-weight: 800;
            margin-bottom: 15px; position: relative; display: inline-block;
        }
        .page-title::after {
            content: ''; position: absolute; bottom: -10px; left: 50%;
            transform: translateX(-50%); width: 80px; height: 4px;
            background: var(--primary-red); border-radius: 2px;
        }
        .page-subtitle { font-size: 1.1rem; color: var(--medium-gray); }

        /* --- 积分卡片 (Hero Stats) --- */
        .points-hero {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            border-radius: 16px;
            padding: 40px;
            color: white;
            text-align: center;
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.3);
            margin-bottom: 50px;
            position: relative;
            overflow: hidden;
        }
        .points-hero::before {
            content: '\f005'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; top: -20px; right: -20px; font-size: 15rem;
            opacity: 0.1; color: white;
        }
        .points-value { font-size: 4.5rem; font-weight: 800; margin: 10px 0; line-height: 1; }
        .points-label { font-size: 1.2rem; text-transform: uppercase; letter-spacing: 2px; opacity: 0.9; }
        .points-sub {
            background: rgba(255, 255, 255, 0.2); display: inline-block;
            padding: 5px 15px; border-radius: 20px; margin-top: 15px; font-size: 0.9rem;
        }

        /* --- Section Styling --- */
        .section-title {
            font-size: 1.8rem; color: var(--text-dark); margin-bottom: 25px;
            border-left: 5px solid var(--primary-red); padding-left: 15px; font-weight: 700;
        }

        /* --- Badges Grid --- */
        .badge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* 自适应网格 */
            gap: 25px;
            margin-bottom: 60px;
        }

        .badge-card {
            background: var(--white);
            border-radius: 16px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid #eee;
            position: relative;
            height: 100%; /* 等高 */
            display: flex; flex-direction: column; justify-content: center; align-items: center;
        }
        .badge-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }

        /* 图标圆圈 */
        .badge-icon-wrapper {
            width: 80px; height: 80px;
            margin: 0 auto 20px;
            background: var(--light-gray);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem;
            transition: all 0.3s;
            color: #9ca3af;
        }

        .badge-card h4 { margin: 0 0 8px; font-size: 1.1rem; color: var(--text-dark); font-weight: 700; }
        .badge-card p { color: var(--medium-gray); font-size: 0.85rem; margin: 0; line-height: 1.4; }

        /* --- 解锁状态 --- */
        .badge-card.earned .badge-icon-wrapper {
            background: #fee2e2;
            color: var(--primary-red);
            box-shadow: 0 0 0 6px rgba(220, 38, 38, 0.05);
        }
        .badge-card.earned h4 { color: var(--primary-red); }

        /* 特殊颜色勋章 */
        .badge-card.bronze.earned .badge-icon-wrapper { color: var(--bronze-color); background: #fff7ed; }
        .badge-card.silver.earned .badge-icon-wrapper { color: var(--silver-color); background: #f1f5f9; }
        .badge-card.gold.earned .badge-icon-wrapper { color: var(--gold-color); background: #fefce8; }
        .badge-card.diamond.earned .badge-icon-wrapper { color: var(--diamond-color); background: #eff6ff; }

        /* 锁定状态 */
        .badge-card.locked { opacity: 0.6; filter: grayscale(1); }
        .lock-icon {
            position: absolute; top: 15px; right: 15px;
            color: #cbd5e1; font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .points-value { font-size: 3.5rem; }
        }
    </style>
</head>

<body>

<?php include 'header_UI.php'; ?>

<div class="main-container">

    <div class="page-header">
        <h1 class="page-title">My Achievements</h1>
        <p class="page-subtitle">Track your impact, earn badges, and celebrate your generosity.</p>
    </div>

    <div class="points-hero">
        <div class="points-label">Current Balance</div>
        <div class="points-value"><?php echo number_format($current_points); ?></div>
        <div class="points-label">Points</div>
        <div class="points-sub">
            Lifetime Earned: <strong><?php echo number_format($total_points_earned); ?> PTS</strong>
        </div>
    </div>

    <h3 class="section-title">Badge Collection</h3>
    <div class="badge-grid">
        
        <?php foreach ($badges as $key => $badge): ?>
            <div class="badge-card <?php echo $badge['class']; ?> <?php echo $badge['unlocked'] ? 'earned' : 'locked'; ?>">
                <?php if(!$badge['unlocked']): ?>
                    <i class="fas fa-lock lock-icon"></i>
                <?php endif; ?>
                
                <div class="badge-icon-wrapper">
                    <i class="fas <?php echo $badge['icon']; ?>"></i>
                </div>
                
                <h4><?php echo $badge['title']; ?></h4>
                <p><?php echo $badge['unlocked'] ? 'Unlocked!' : $badge['desc']; ?></p>
            </div>
        <?php endforeach; ?>

    </div>

</div>

<?php include 'footer.php'; ?>

</body>
</html>