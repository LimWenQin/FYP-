<?php
include 'dataconnection.php';
include 'header_function.php'; 

// --- 1. 获取全局统计数据 (不受分页影响) ---
// 因为分页后每次只查6条，无法在循环中计算总金额和总数量，所以需要单独查询
$stats_query = "SELECT 
    COUNT(*) as total_campaigns,
    SUM(Activity_GetAmount) as total_raised,
    SUM(CASE 
        WHEN Activity_Status = 'Active' AND Activity_StartDate <= CURDATE() AND Activity_EndDate >= CURDATE() THEN 1 
        ELSE 0 
    END) as active_now_count
FROM activity";
$stats_result = $conn->query($stats_query);
$stats_data = $stats_result->fetch_assoc();

$total_campaigns_stat = $stats_data['total_campaigns'] ?? 0;
$total_raised_stat = $stats_data['total_raised'] ?? 0;
$active_now_stat = $stats_data['active_now_count'] ?? 0;


// --- 2. 处理筛选和分页参数 ---
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 6; // 一页 6 个
$offset = ($page - 1) * $items_per_page;


// --- 3. 构建查询条件 (WHERE Clause) ---
$where_clause = "1=1"; // 默认查询全部

switch ($filter) {
    case 'ongoing':
        $where_clause = "Activity_Status = 'Active' AND Activity_StartDate <= CURDATE() AND Activity_EndDate >= CURDATE()";
        break;
    case 'upcoming':
        $where_clause = "Activity_Status = 'Active' AND Activity_StartDate > CURDATE()";
        break;
    case 'past':
        // Past 定义：状态为Completed 或者 日期已过
        $where_clause = "(Activity_Status = 'Completed' OR Activity_EndDate < CURDATE())";
        break;
    default: // 'all'
        $where_clause = "1=1";
        break;
}


// --- 4. 获取当前筛选条件下的总数量 (用于计算页数) ---
$count_sql = "SELECT COUNT(*) as total FROM activity WHERE $where_clause";
$count_res = $conn->query($count_sql);
$total_items = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);


// --- 5. 获取当前页数据 ---
// 保持你原本的排序逻辑：进行中 -> 即将开始 -> 已结束
$query = "SELECT * FROM activity 
          WHERE $where_clause 
          ORDER BY 
            CASE 
                WHEN Activity_Status = 'Active' AND Activity_StartDate <= CURDATE() AND Activity_EndDate >= CURDATE() THEN 1
                WHEN Activity_Status = 'Active' AND Activity_StartDate > CURDATE() THEN 2
                WHEN Activity_Status = 'Completed' OR Activity_EndDate < CURDATE() THEN 3
                ELSE 4
            END,
            Activity_StartDate ASC
          LIMIT $items_per_page OFFSET $offset";

$result = $conn->query($query);

include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaigns - Donation Platform</title>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-red: #e53935;
            --dark-red: #c62828;
            --light-red: #ff5252;
            --lighter-red: #ffcdd2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --medium-gray: #e0e0e0;
            --dark-gray: #757575;
            --text: #212121;
            --shadow: rgba(0, 0, 0, 0.1);
            --success: #4CAF50;
            --warning: #FF9800;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fef7f7;
            color: var(--text);
        }
        
        /* Header Styles */
        .campaign-header {
            background: linear-gradient(rgb(108 99 98 / 85%), rgb(108 99 98 / 85%)), url(https://images.unsplash.com/photo-1559027615-cd4628902d4a?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: white;
            padding: 160px 20px 80px 20px; /* 增加顶部 padding 避开 Navbar */
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .campaign-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M50,0 C77.6,0 100,22.4 100,50 C100,77.6 77.6,100 50,100 C22.4,100 0,77.6 0,50 C0,22.4 22.4,0 50,0 Z" fill="white" fill-opacity="0.05"/></svg>');
            background-size: 120px;
            opacity: 0.1;
        }
        
        .page-title {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 15px;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .page-description {
            font-size: 20px;
            max-width: 700px;
            margin: 0 auto 30px;
            opacity: 0.95;
            position: relative;
            font-weight: 500;
        }
        
        /* Campaign Stats */
        .campaign-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            position: relative;
        }
        
        .stat-box {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-radius: 15px;
            min-width: 180px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        
        /* Main Container */
        .campaign-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        /* Filters */
        .filters-container {
            background: var(--white);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 5px 20px var(--shadow);
            border: 1px solid var(--medium-gray);
        }
        
        .filters-title {
            font-size: 24px;
            color: var(--primary-red);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filters-title i {
            font-size: 28px;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        /* 这里的 filter-btn 现在是 <a> 标签 */
        .filter-btn {
            background: var(--white);
            border: 2px solid var(--primary-red);
            color: var(--primary-red);
            padding: 12px 28px;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none; /* 去除下划线 */
        }
        
        .filter-btn i {
            font-size: 18px;
        }
        
        .filter-btn.active {
            background: var(--primary-red);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 57, 53, 0.3);
        }
        
        .filter-btn:hover:not(.active) {
            background: var(--lighter-red);
            transform: translateY(-2px);
        }
        
        /* Campaign Grid */
        .campaign-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        /* Campaign Card */
        .campaign-card {
            background: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px var(--shadow);
            border: 1px solid var(--medium-gray);
            /* 移除 JS 动画所需的初始样式，防止加载闪烁 */
            transition: transform 0.4s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .campaign-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(229, 57, 53, 0.15);
            border-color: var(--primary-red);
        }
        
        .campaign-image-wrapper {
            position: relative;
            height: 250px;
            overflow: hidden;
        }

        .campaign-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
            cursor: zoom-in;
        }
        
        .campaign-card:hover .campaign-image {
            transform: scale(1.05);
        }
        
        .campaign-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }
        
        .badge-ongoing { background: var(--success); color: white; }
        .badge-upcoming { background: var(--warning); color: white; }
        .badge-past { background: var(--dark-gray); color: white; }
        
        .campaign-content { 
            padding: 25px; 
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .campaign-dates {
            color: var(--dark-gray);
            font-size: 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .campaign-dates i { color: var(--primary-red); }
        
        .campaign-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 15px;
            line-height: 1.3;
            /* 控制标题高度 */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .campaign-details {
            color: var(--dark-gray);
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }
        
        .campaign-location {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark-gray);
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .campaign-location i { color: var(--primary-red); }
        
        /* Progress Bar */
        .progress-container { margin-bottom: 20px; }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .progress-bar {
            height: 8px;
            background: var(--light-gray);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--light-red) 0%, var(--primary-red) 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .campaign-stats-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .stat-item { text-align: center; }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-red);
            display: block;
        }
        
        .stat-text {
            font-size: 13px;
            color: var(--dark-gray);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .campaign-actions { 
            display: flex; 
            gap: 10px; 
            margin-top: auto;
        }
        
        .btn-donate {
            flex: 1;
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-donate:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 57, 53, 0.3);
        }
        
        .btn-details {
            background: transparent;
            border: 2px solid var(--primary-red);
            color: var(--primary-red);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .btn-details:hover { background: var(--lighter-red); }
        
        /* Empty State */
        .no-campaigns {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }
        
        .no-campaigns i {
            font-size: 60px;
            color: var(--medium-gray);
            margin-bottom: 20px;
        }
        
        .no-campaigns h3 {
            font-size: 24px;
            color: var(--dark-gray);
            margin-bottom: 10px;
        }
        
        .no-campaigns p {
            color: var(--dark-gray);
            font-size: 16px;
        }

        /* --- Pagination Style --- */
        .pagination { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            gap: 10px; 
            margin-top: 40px; 
        }
        .pagination a, .pagination span { 
            padding: 10px 18px; 
            text-decoration: none; 
            border: 2px solid var(--primary-red); 
            color: var(--primary-red); 
            border-radius: 8px; 
            font-weight: 600; 
            transition: all 0.3s ease; 
            display: inline-flex; 
            align-items: center; 
            gap: 5px; 
        }
        .pagination a:hover { 
            background: var(--primary-red); 
            color: white; 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(229, 57, 53, 0.3); 
        }
        .pagination .current { 
            background: var(--primary-red); 
            color: white; 
            border-color: var(--primary-red); 
        }
        .pagination .disabled { 
            opacity: 0.5; 
            cursor: not-allowed; 
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .campaign-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .page-title { font-size: 36px; }
            .page-description { font-size: 18px; padding: 0 20px; }
            .campaign-stats { gap: 20px; }
            .stat-box { min-width: 140px; padding: 15px 20px; }
            .stat-number { font-size: 28px; }
            .filters { justify-content: center; }
            .filter-btn { padding: 10px 20px; font-size: 14px; }
            .campaign-grid { grid-template-columns: 1fr; gap: 20px; }
            .campaign-container { padding: 20px; }
        }
        
        @media (max-width: 480px) {
            .campaign-header { padding: 120px 0 40px 0; }
            .page-title { font-size: 28px; }
            .campaign-stats { flex-direction: column; align-items: center; gap: 15px; }
            .stat-box { width: 100%; max-width: 250px; }
            .filters { justify-content: space-around; }
            .filter-btn { flex: 1; justify-content: center; padding: 12px; font-size: 13px; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="campaign-header">
        <h1 class="page-title">Our Campaigns</h1>
        <p class="page-description">Join us in making a difference through our various community engagement programs and fundraising events.</p>
        
        <div class="campaign-stats">
            <div class="stat-box">
                <span class="stat-number" id="totalCampaigns">0</span>
                <span class="stat-label">Total Campaigns</span>
            </div>
            <div class="stat-box">
                <span class="stat-number" id="activeCampaigns">0</span>
                <span class="stat-label">Active Now</span>
            </div>
            <div class="stat-box">
                <span class="stat-number" id="totalRaised">RM 0</span>
                <span class="stat-label">Total Raised</span>
            </div>
        </div>
    </div>

    <div class="campaign-container">
        <div class="filters-container">
            <h2 class="filters-title">
                <i class="fas fa-filter"></i> Filter Campaigns
            </h2>
            <div class="filters">
                <a href="?filter=all&page=1" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i> All Campaigns
                </a>
                <a href="?filter=ongoing&page=1" class="filter-btn <?php echo $filter == 'ongoing' ? 'active' : ''; ?>">
                    <i class="fas fa-play-circle"></i> Ongoing
                </a>
                <a href="?filter=upcoming&page=1" class="filter-btn <?php echo $filter == 'upcoming' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> Upcoming
                </a>
                <a href="?filter=past&page=1" class="filter-btn <?php echo $filter == 'past' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> Past Campaigns
                </a>
            </div>
        </div>

        <div class="campaign-grid" id="campaignGrid">
            <?php 
            if ($result->num_rows > 0): 
                while($campaign = $result->fetch_assoc()): 
                    $currentDate = date('Y-m-d');
                    $startDate = $campaign['Activity_StartDate'];
                    $endDate = $campaign['Activity_EndDate'];
                    $status = $campaign['Activity_Status'];
                    
                    // 计算状态和 Badge (虽然SQL已经筛选过，但为了 Badge 样式还是需要判断)
                    if ($status == 'Active') {
                        if ($currentDate >= $startDate && $currentDate <= $endDate) {
                            $campaignStatus = 'ongoing';
                            $badgeClass = 'badge-ongoing';
                            $badgeText = 'Ongoing';
                        } elseif ($currentDate < $startDate) {
                            $campaignStatus = 'upcoming';
                            $badgeClass = 'badge-upcoming';
                            $badgeText = 'Upcoming';
                        } else {
                            $campaignStatus = 'past';
                            $badgeClass = 'badge-past';
                            $badgeText = 'Completed';
                        }
                    } else {
                        $campaignStatus = 'past';
                        $badgeClass = 'badge-past';
                        $badgeText = 'Completed';
                    }
                    
                    $targetAmount = $campaign['Activity_TargetAmount'];
                    $raisedAmount = $campaign['Activity_GetAmount'];
                    $progress = ($targetAmount > 0) ? min(($raisedAmount / $targetAmount) * 100, 100) : 0;
            ?>
                    
                    <div class="campaign-card">
                        <div class="campaign-image-wrapper">
                            <?php 
                            // JSON 图片处理
                            $defaultImage = 'images/campaign-default.jpg';
                            $imagePath = $defaultImage;
                            
                            $dbImage = isset($campaign['Activity_Images']) ? $campaign['Activity_Images'] : (isset($campaign['Activity_Picture']) ? $campaign['Activity_Picture'] : '');

                            if (!empty($dbImage)) {
                                $decoded = json_decode($dbImage, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) {
                                    $imagePath = $decoded[0];
                                } else {
                                    $imagePath = $dbImage;
                                }
                            }
                            ?>
                            
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                 alt="<?php echo htmlspecialchars($campaign['Activity_Name']); ?>" 
                                 class="campaign-image"
                                 onclick="showImage(this.src, '<?php echo htmlspecialchars(addslashes($campaign['Activity_Name'])); ?>')"
                                 onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                            
                            <span class="campaign-badge <?php echo $badgeClass; ?>">
                                <?php echo $badgeText; ?>
                            </span>
                        </div>
                        
                        <div class="campaign-content">
                            <div class="campaign-dates">
                                <i class="far fa-calendar"></i>
                                <?php 
                                    $startFormatted = date('M d, Y', strtotime($startDate));
                                    $endFormatted = date('M d, Y', strtotime($endDate));
                                    echo $startFormatted . ' - ' . $endFormatted;
                                ?>
                            </div>
                            
                            <h3 class="campaign-title" title="<?php echo htmlspecialchars($campaign['Activity_Name']); ?>">
                                <?php echo htmlspecialchars($campaign['Activity_Name']); ?>
                            </h3>
                            
                            <p class="campaign-details"><?php echo htmlspecialchars($campaign['Activity_Description']); ?></p>
                            
                            <div class="campaign-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php 
                                    echo htmlspecialchars($campaign['Activity_City']) . ', ' . 
                                         htmlspecialchars($campaign['Activity_State']);
                                ?>
                            </div>
                            
                            <div class="progress-container">
                                <div class="progress-label">
                                    <span>Raised: RM <?php echo number_format($raisedAmount, 2); ?></span>
                                    <span>Goal: RM <?php echo number_format($targetAmount, 2); ?></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="campaign-stats-row">
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo number_format($progress, 0); ?>%</span>
                                    <span class="stat-text">Funded</span>
                                </div>
                            </div>
                            
                            <div class="campaign-actions">
                                <?php if ($campaignStatus == 'ongoing'): ?>
                                    <a href="S_C_Payment_Page.php?activity_id=<?php echo $campaign['Activity_ID']; ?>" 
                                       class="btn-donate"
                                       onclick="return checkLogin(event, this.href)">
                                        <i class="fas fa-heart"></i> Donate Now
                                    </a>
                                <?php endif; ?>
                                
                                <a href="donor_campaign_detail.php?id=<?php echo $campaign['Activity_ID']; ?>" 
                                   class="btn-details"
                                   style="flex: 1;">
                                    Details
                                </a>
                            </div>
                        </div>
                    </div>
            <?php 
                endwhile;
            else: 
            ?>
                <div class="no-campaigns" style="display: block;">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No campaigns found</h3>
                    <p>There are no campaigns in this category currently.</p>
                    <?php if($filter !== 'all'): ?>
                        <p style="margin-top: 10px;"><a href="?filter=all" style="color: var(--primary-red); font-weight: bold;">View All Campaigns</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                <?php endif; ?>
                
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $start_page + 4);
                $start_page = max(1, $end_page - 4);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
            
    <?php include 'footer.php'; ?>

    <script>
        // 图片点击放大函数
        function showImage(src, title) {
            Swal.fire({
                imageUrl: src,
                imageAlt: title,
                title: title,
                width: 600,
                padding: '1em',
                background: '#fff',
                showConfirmButton: false,
                showCloseButton: true,
                backdrop: `rgba(0,0,0,0.8)`
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // 使用 PHP 传递的统计数据
            const totalCampaigns = <?php echo $total_campaigns_stat; ?>;
            const activeCampaigns = <?php echo $active_now_stat; ?>;
            const totalRaised = <?php echo $total_raised_stat; ?>;
            
            animateCounter('totalCampaigns', 0, totalCampaigns, 1000);
            animateCounter('activeCampaigns', 0, activeCampaigns, 1000);
            animateCurrencyCounter('totalRaised', 0, totalRaised, 1500);
        });
        
        function animateCounter(elementId, start, end, duration) {
            const element = document.getElementById(elementId);
            if(!element) return;
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                element.textContent = value;
                if (progress < 1) window.requestAnimationFrame(step);
            };
            window.requestAnimationFrame(step);
        }
        
        function animateCurrencyCounter(elementId, start, end, duration) {
            const element = document.getElementById(elementId);
            if(!element) return;
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                element.textContent = 'RM ' + value.toLocaleString();
                if (progress < 1) window.requestAnimationFrame(step);
            };
            window.requestAnimationFrame(step);
        }

        // Login Check Function
        function checkLogin(event, url) {
            const isLoggedIn = <?php echo isset($_SESSION['Donor_ID']) || isset($_SESSION['donor_id']) ? 'true' : 'false'; ?>;

            if (!isLoggedIn) {
                event.preventDefault(); 
                
                Swal.fire({
                    title: 'Login Required',
                    text: "You need to login to make a donation.",
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#e53935', 
                    cancelButtonColor: '#757575',
                    confirmButtonText: 'Login Now',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'donor_login.php'; 
                    }
                });
                return false;
            }
            return true;
        }
    </script>
</body>
</html>