<?php
include 'dataconnection.php';
include 'header_function.php';


$categories = [
    'emergency' => [
        'db_name' => 'Emergency Relief', 
        'name' => 'Emergency Relief', 
        'icon' => 'fas fa-first-aid', 
        'color' => '#d32f2f' // 红色
    ],
    'medical' => [
        'db_name' => 'Medical', 
        'name' => 'Medical Aid', 
        'icon' => 'fas fa-heartbeat', 
        'color' => '#f57c00' // 橙色
    ],
    'disability' => [
        'db_name' => 'Disability Support', 
        'name' => 'Disability Support', 
        'icon' => 'fas fa-wheelchair', 
        'color' => '#f57c00' // 橙色
    ],
    'elderly' => [
        'db_name' => 'Elderly Care', 
        'name' => 'Elderly Care', 
        'icon' => 'fas fa-user-friends', 
        'color' => '#f57c00' // 橙色
    ],
    'children' => [
        'db_name' => 'Children Support', 
        'name' => 'Children Support', 
        'icon' => 'fas fa-child', 
        'color' => '#f57c00' // 橙色
    ],
    'other' => [
        'db_name' => 'Other Cases', 
        'name' => 'Other Cases', 
        'icon' => 'fas fa-hand-holding-heart', 
        'color' => '#f57c00' // 橙色
    ]
];

// --- 2. 创建反向映射 ---
$db_value_map = [];
foreach ($categories as $key => $data) {
    $db_value_map[$data['db_name']] = $data;
}

// 获取筛选参数
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// --- 3. 构建查询条件 (已修正) ---
// 这里加入了 "Is_Deleted = 0" 以过滤掉已删除的数据
$where_conditions = ["Case_Status IN ('Active', 'Completed')", "Is_Deleted = 0"];

if ($category_filter !== 'all') {
    if (isset($categories[$category_filter])) {
        $db_search_value = $conn->real_escape_string($categories[$category_filter]['db_name']);
        $where_conditions[] = "Case_Category = '$db_search_value'";
    }
}
$where_clause = implode(' AND ', $where_conditions);

// 分页逻辑
$cases_per_page = 4;
$offset = ($page - 1) * $cases_per_page;

// 获取总案例数
$count_query = "SELECT COUNT(*) as total FROM special_case WHERE $where_clause";
$count_result = $conn->query($count_query);
$total_cases = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_cases / $cases_per_page);

// 获取当前页的案例
$query = "SELECT * FROM special_case WHERE $where_clause ORDER BY created_at DESC LIMIT $cases_per_page OFFSET $offset";
$result = $conn->query($query);

include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Special Cases - Love Bridge Foundation</title>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-red: #e53935; 
            --dark-red: #c62828;
            --light-red: #ff5252;
            --lighter-red: #ffcdd2;
            --white: #FFFFFF;
            --light-bg: #fef7f7;
            --text: #212121;
            --shadow: rgba(0, 0, 0, 0.1);
            --progress-bg: #e0e0e0;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--light-bg); color: var(--text); line-height: 1.6; overflow-x: hidden; }
        
        /* Full Width Page Header */
        .page-header {
            width: 100%;
            background: url('https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            text-align: center;
            position: relative;
            padding: 160px 20px;
            color: white;
            border-radius: 0;
            margin-bottom: 0;
        }
        .page-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.6); z-index: 1; }
        .page-header-content { position: relative; z-index: 2; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 48px; font-weight: 700; margin-bottom: 20px; color: white; position: relative; display: inline-block; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
        .page-title::after { content: ''; position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); width: 100px; height: 4px; background: var(--primary-red); border-radius: 2px; }
        .page-description { font-size: 20px; max-width: 800px; margin: 0 auto 30px; color: rgba(255, 255, 255, 0.9); text-shadow: 1px 1px 2px rgba(0,0,0,0.5); font-weight: 500; }
        
        /* Main Container */
        .case-container { max-width: 1400px; margin: 0 auto; padding: 40px 20px; position: relative; }
        
        /* Stats */
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; margin-top: -60px; position: relative; z-index: 3; padding: 0 20px; }
        .stat-box { background: var(--white); padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid #eee; transition: transform 0.3s ease; }
        .stat-box:hover { transform: translateY(-5px); }
        .stat-number { font-size: 36px; font-weight: 700; color: var(--primary-red); display: block; margin-bottom: 10px; }
        .stat-label { font-size: 16px; color: #8a8686; text-transform: uppercase; letter-spacing: 1px; }
        
        /* Categories Grid */
        .categories-container { margin-bottom: 40px; }
        .categories-title { font-size: 24px; color: var(--primary-red); margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .categories-title i { font-size: 28px; }
        .categories-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; }
        .category-item { background: var(--white); border: 2px solid #eee; border-radius: 10px; padding: 20px 15px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--text); display: block; }
        .category-item:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .category-item.active { color: white; transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15); }
        .category-icon { font-size: 32px; margin-bottom: 10px; display: block; }
        .category-name { font-size: 14px; font-weight: 600; }
        
        /* Cases Grid */
        .cases-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; margin-bottom: 50px; }
        
        /* Case Card */
        .case-card { background: var(--white); border-radius: 15px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.05); transition: all 0.3s ease; border: 1px solid #eee; position: relative; display: flex; flex-direction: column; height: 100%; }
        .case-card:hover { transform: translateY(-10px); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1); }
        .case-image-container { position: relative; height: 250px; overflow: hidden; }
        .case-image { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; cursor: zoom-in; }
        .case-card:hover .case-image { transform: scale(1.05); }
        .case-badges { position: absolute; top: 15px; right: 15px; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
        .case-badge { padding: 6px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2); color: white; }
        .badge-urgent { background: #d32f2f; }
        .case-content { padding: 25px; flex: 1; display: flex; flex-direction: column; }
        .case-header { margin-bottom: 15px; }
        .case-title { font-size: 24px; font-weight: 700; color: var(--text); margin-bottom: 10px; line-height: 1.3; }
        .case-meta { display: flex; align-items: center; gap: 15px; color: #8a8686; font-size: 14px; }
        .case-description-container { margin-bottom: 20px; flex: 1; }
        .case-description { color: #8a8686; font-size: 16px; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; transition: all 0.3s ease; }
        .case-description.expanded { -webkit-line-clamp: unset; overflow: visible; display: block; }
        .show-more-btn { background: none; border: none; color: #888; cursor: pointer; font-weight: 600; margin-top: 10px; padding: 0; display: inline-flex; align-items: center; gap: 5px; font-size: 15px; }
        .show-more-btn:hover { text-decoration: underline; color: var(--text); }
        
        /* Progress Bar */
        .progress-section { margin-bottom: 25px; }
        .progress-info { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .progress-bar { height: 10px; background-color: var(--progress-bg); border-radius: 5px; overflow: hidden; margin-bottom: 5px; position: relative; }
        .progress-fill { height: 100%; border-radius: 5px; transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
        .progress-fill::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.3) 50%, transparent 100%); animation: shimmer 2s infinite; }
        @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        .progress-percentage { text-align: right; font-size: 14px; font-weight: 600; }
        
        .case-details { display: flex; justify-content: space-between; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .detail-item { text-align: center; }
        .detail-value { font-size: 22px; font-weight: 700; display: block; }
        .detail-label { font-size: 13px; color: #8a8686; text-transform: uppercase; letter-spacing: 1px; }
        
        /* --- 按钮部分 --- */
        .case-actions { 
            margin-top: auto; 
            display: flex; 
            gap: 12px; /* 按钮之间的间距 */
        }
        
        /* 两个按钮的基础样式 */
        .btn-donate, .btn-details { 
            flex: 1; /* 让它们平分宽度 */
            padding: 12px; 
            border-radius: 10px; 
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            text-decoration: none; 
            text-align: center; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px; 
            border: none;
        }

        /* Donate 按钮特定样式 */
        .btn-donate { 
            color: var(--white); 
        }
        .btn-donate:hover { 
            filter: brightness(0.9); 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.15); 
        }
        .btn-donate.disabled { 
            background-color: #9e9e9e !important; 
            cursor: not-allowed; 
            transform: none; 
            box-shadow: none; 
            filter: none; 
        }

        /* Detail 按钮特定样式 */
        .btn-details {
            background-color: transparent;
            border: 2px solid; /* 边框颜色由内联样式控制 */
        }
        .btn-details:hover {
            background-color: #f5f5f5; /* 鼠标悬停时的浅灰色背景 */
            transform: translateY(-2px);
        }
        
        /* No Cases */
        .no-cases { grid-column: 1 / -1; text-align: center; padding: 80px 20px; background: var(--white); border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .no-cases i { font-size: 60px; color: #ccc; margin-bottom: 20px; }
        .no-cases h3 { font-size: 28px; color: var(--text); margin-bottom: 10px; }
        .no-cases p { color: #8a8686; font-size: 18px; max-width: 600px; margin: 0 auto; }
        
        /* Pagination */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 40px; }
        .pagination a, .pagination span { padding: 10px 18px; text-decoration: none; border: 2px solid var(--primary-red); color: var(--primary-red); border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 5px; }
        .pagination a:hover { background: var(--primary-red); color: white; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(229, 57, 53, 0.3); }
        .pagination .current { background: var(--primary-red); color: white; border-color: var(--primary-red); }
        .pagination .disabled { opacity: 0.5; cursor: not-allowed; }
        
        /* Responsive */
        @media (max-width: 1200px) { .page-title { font-size: 42px; } }
        @media (max-width: 992px) { .cases-grid { grid-template-columns: 1fr; gap: 25px; } .case-image-container { height: 220px; } .page-title { font-size: 36px; } .page-description { font-size: 18px; padding: 0 20px; } .categories-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); } }
        @media (max-width: 768px) { .page-header { padding: 80px 20px; } .stats-container { grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: -40px; } .stat-box { padding: 20px 15px; } .stat-number { font-size: 28px; } }
        @media (max-width: 576px) { .page-title { font-size: 32px; } .stats-container { grid-template-columns: 1fr; } .case-image-container { height: 200px; } .categories-grid { grid-template-columns: repeat(2, 1fr); } 
            /* 手机端按钮调整为上下排列 */
            .case-actions { flex-direction: column; gap: 10px; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title">Special Cases</h1>
            <p class="page-description">These are urgent cases that need immediate support. Your donation can make a significant difference in someone's life today.</p>
        </div>
    </div>

    <div class="case-container">
        <div class="stats-container">
            <?php 
            // 修正统计逻辑：只计算未删除的
            $total_query = "SELECT COUNT(*) as total, SUM(Raised_Amount) as total_raised FROM special_case WHERE Case_Status IN ('Active', 'Completed') AND Is_Deleted = 0";
            $total_result = $conn->query($total_query);
            $stats = $total_result->fetch_assoc();
            
            $urgent_count_query = "SELECT COUNT(*) as count FROM special_case WHERE Case_Status IN ('Active', 'Completed') AND Case_Category = 'Emergency Relief' AND Is_Deleted = 0";
            $urgent_result = $conn->query($urgent_count_query);
            $urgent_count = $urgent_result->fetch_assoc()['count'];
            
            $total_donors_query = "SELECT SUM(Donor_Count) as total_donors FROM special_case WHERE Case_Status IN ('Active', 'Completed') AND Is_Deleted = 0";
            $donors_result = $conn->query($total_donors_query);
            $total_donors = $donors_result->fetch_assoc()['total_donors'] ?? 0;
            ?>
            
            <div class="stat-box">
                <span class="stat-number"><?php echo $total_cases; ?></span>
                <span class="stat-label">Active Cases</span>
            </div>
            <div class="stat-box">
                <span class="stat-number">RM <?php echo number_format($stats['total_raised'] ?? 0, 0); ?></span>
                <span class="stat-label">Total Raised</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo $urgent_count; ?></span>
                <span class="stat-label">Emergency Cases</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo $total_donors; ?></span>
                <span class="stat-label">Total Donors</span>
            </div>
        </div>
        
        <div class="categories-container">
            <h2 class="categories-title">
                <i class="fas fa-list-alt"></i> Case Categories
            </h2>
            <div class="categories-grid">
                <a href="?category=all&page=1" class="category-item <?php echo $category_filter == 'all' ? 'active' : ''; ?>" style="<?php echo $category_filter == 'all' ? 'background: #e53935; border-color: #e53935;' : ''; ?>">
                    <i class="fas fa-layer-group category-icon" style="<?php echo $category_filter == 'all' ? 'color: white;' : 'color: #e53935;'; ?>"></i>
                    <span class="category-name">All Cases</span>
                </a>
                <?php foreach($categories as $key => $category): ?>
                    <?php 
                        $isActive = ($category_filter == $key);
                        $bgStyle = $isActive ? "background: {$category['color']}; border-color: {$category['color']};" : "border-color: {$category['color']};";
                        $iconColor = $isActive ? "white" : $category['color'];
                    ?>
                    <a href="?category=<?php echo $key; ?>&page=1" class="category-item <?php echo $isActive ? 'active' : ''; ?>" style="<?php echo $bgStyle; ?>">
                        <i class="<?php echo $category['icon']; ?> category-icon" style="color: <?php echo $iconColor; ?>"></i>
                        <span class="category-name"><?php echo $category['name']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="cases-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while($case = $result->fetch_assoc()): 
                    $raised = isset($case['Raised_Amount']) ? floatval($case['Raised_Amount']) : 0;
                    $target = isset($case['Target_Amount']) && floatval($case['Target_Amount']) > 0 ? floatval($case['Target_Amount']) : 1;
                    
                    $progress = ($raised / $target) * 100;
                    $progress = min($progress, 100);
                    
                    $is_completed = ($progress >= 100) || ($case['Case_Status'] === 'Completed');

                    $db_category_value = $case['Case_Category'];
                    $current_cat_info = $categories['other'];
                    
                    if (isset($db_value_map[$db_category_value])) {
                        $current_cat_info = $db_value_map[$db_category_value];
                    }

                    $category_name = $current_cat_info['name'];
                    $category_color = $current_cat_info['color'];
                    $category_icon = $current_cat_info['icon'];
                    
                    $is_urgent = isset($case['Urgency']) && $case['Urgency'] === 'high';
                    $donor_count = isset($case['Donor_Count']) ? $case['Donor_Count'] : 0;
                    
                    $default_image = 'images/case-default.jpg';
                    $image_path = $default_image;

                    if (isset($case['Case_Images']) && !empty($case['Case_Images'])) {
                        $decoded_images = json_decode($case['Case_Images'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && count($decoded_images) > 0) {
                            $image_path = $decoded_images[0];
                        } else {
                            $image_path = $case['Case_Images'];
                        }
                    }
                ?>
                    <div class="case-card">
                        <div class="case-image-container">
                            <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                 alt="<?php echo htmlspecialchars($case['Case_Title']); ?>" 
                                 class="case-image"
                                 onclick="showImage(this.src, '<?php echo htmlspecialchars(addslashes($case['Case_Title'])); ?>')"
                                 onerror="this.src='<?php echo $default_image; ?>'">
                            
                            <div class="case-badges">
                                <span class="case-badge" style="background: <?php echo $category_color; ?>">
                                    <?php echo $category_name; ?>
                                </span>
                                <?php if ($is_urgent): ?>
                                    <span class="case-badge badge-urgent">URGENT</span>
                                <?php endif; ?>
                                <?php if ($is_completed): ?>
                                    <span class="case-badge" style="background: #2e7d32;">COMPLETED</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="case-content">
                            <div class="case-header">
                                <h3 class="case-title"><?php echo htmlspecialchars($case['Case_Title']); ?></h3>
                                <div class="case-meta">
                                    <span><i class="far fa-calendar"></i> Posted: <?php echo date('M d, Y', strtotime($case['Created_At'])); ?></span>
                                    <span><i class="<?php echo $category_icon; ?>" style="color: <?php echo $category_color; ?>"></i> <?php echo $category_name; ?></span>
                                </div>
                            </div>
                            
                            <div class="case-description-container">
                                <p class="case-description" id="desc-<?php echo $case['Case_ID']; ?>">
                                    <?php echo htmlspecialchars($case['Case_Description']); ?>
                                </p>
                                <?php if (strlen($case['Case_Description']) > 150): ?>
                                    <button class="show-more-btn" onclick="toggleDescription(<?php echo $case['Case_ID']; ?>)">
                                        <i class="fas fa-chevron-down"></i> Show more
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <div class="progress-section">
                                <div class="progress-info">
                                    <span>Raised: RM <?php echo number_format($raised, 2); ?></span>
                                    <span>Goal: RM <?php echo number_format($target, 2); ?></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%; background: linear-gradient(90deg, <?php echo $category_color; ?> 0%, <?php echo $category_color; ?> 100%);"></div>
                                </div>
                                <div class="progress-percentage" style="color: <?php echo $category_color; ?>">
                                    <?php echo number_format($progress, 1); ?>% funded
                                </div>
                            </div>
                            
                            <div class="case-details">
                                <div class="detail-item">
                                    <span class="detail-value" style="color: <?php echo $category_color; ?>"><?php echo $donor_count; ?></span>
                                    <span class="detail-label">Donors</span>
                                </div>
                                <div class="detail-item">
                                    <?php if ($is_completed): ?>
                                        <span class="detail-value" style="color: #2e7d32;">Completed</span>
                                        <span class="detail-label">Status</span>
                                    <?php else: ?>
                                        <span class="detail-value" style="color: <?php echo $category_color; ?>">
                                            <?php echo isset($case['End_Date']) ? date('M j, Y', strtotime($case['End_Date'])) : 'Ongoing'; ?>
                                        </span>
                                        <span class="detail-label">Deadline</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="case-actions">
                                <?php if ($is_completed): ?>
                                    <button class="btn-donate disabled" disabled>
                                        <i class="fas fa-check-circle"></i> Goal Reached
                                    </button>
                                <?php else: ?>
                                    <a href="S_C_Payment_Page.php?case_id=<?php echo $case['Case_ID']; ?>" 
                                       class="btn-donate" 
                                       style="background: <?php echo $category_color; ?>"
                                       onclick="return checkLogin(event, this.href)">
                                        <i class="fas fa-heart"></i> Donate Now
                                    </a>
                                <?php endif; ?>

                                <a href="Special_Case_Detail.php?case_id=<?php echo $case['Case_ID']; ?>" 
                                   class="btn-details"
                                   style="border-color: <?php echo $category_color; ?>; color: <?php echo $category_color; ?>;">
                                    Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-cases">
                    <i class="fas fa-search"></i>
                    <h3>No cases found</h3>
                    <p><?php echo $category_filter !== 'all' ? "No cases in the '" . $categories[$category_filter]['name'] . "' category." : "No special cases at the moment." ?></p>
                    <?php if ($category_filter !== 'all'): ?>
                        <p style="margin-top: 10px;">Try viewing <a href="?category=all&page=1" style="color: var(--primary-red);">all cases</a> instead.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?category=<?php echo $category_filter; ?>&page=<?php echo $page - 1; ?>">
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
                        <a href="?category=<?php echo $category_filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?category=<?php echo $category_filter; ?>&page=<?php echo $page + 1; ?>">
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

        function toggleDescription(caseId) {
            const description = document.getElementById(`desc-${caseId}`);
            const button = description.nextElementSibling;
            
            if (description.classList.contains('expanded')) {
                description.classList.remove('expanded');
                button.innerHTML = '<i class="fas fa-chevron-down"></i> Show more';
            } else {
                description.classList.add('expanded');
                button.innerHTML = '<i class="fas fa-chevron-up"></i> Show less';
            }
        }
        
        function animateProgressBars() {
            const progressBars = document.querySelectorAll('.progress-fill');
            
            progressBars.forEach(bar => {
                const rect = bar.getBoundingClientRect();
                if (rect.top < window.innerHeight - 100) {
                    bar.style.transition = 'width 1s cubic-bezier(0.4, 0, 0.2, 1)';
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', animateProgressBars);
        window.addEventListener('scroll', animateProgressBars);

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
                    cancelButtonColor: '#8a8686',
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