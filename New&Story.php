<?php
include 'dataconnection.php';
include 'header_function.php';

// --- 1. 定义分类配置 ---
// 仿照 Special Case 的结构定义分类
$categories = [
    'news' => [
        'name' => 'News',
        'icon' => 'fas fa-newspaper',
        'color' => '#1976d2', 
        'sql_condition' => "Story_Category LIKE '%News%'"
    ],
    'story' => [
        'name' => 'Donor Stories',
        'icon' => 'fas fa-heart',
        'color' => '#e53935', 
        'sql_condition' => "Story_Category NOT LIKE '%News%'" // 假设非News的都是Story
    ]
];


$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;


$where_clause = "1=1"; 

if ($category_filter !== 'all' && isset($categories[$category_filter])) {
    $where_clause = $categories[$category_filter]['sql_condition'];
}

// 分页逻辑
$items_per_page = 4; 
$offset = ($page - 1) * $items_per_page;

// --- 3. 获取总数 (用于分页) ---
$count_query = "SELECT COUNT(*) as total FROM story WHERE $where_clause";
$count_result = $conn->query($count_query);
$total_stories = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_stories / $items_per_page);

// --- 4. 获取当前页数据 ---

$query = "SELECT * FROM story WHERE $where_clause ORDER BY Story_Date DESC LIMIT $items_per_page OFFSET $offset";
$result = $conn->query($query);

include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Stories - Love Bridge Foundation</title>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-red: #e53935;
            --dark-red: #c62828;
            --light-red: #ff5252;
            --lighter-red: #ffcdd2;
            
           
            --primary-blue: #1976d2; 
            
            --white: #ffffff;
            --light-bg: #fef7f7;
            --text: #212121;
            
            --shadow: rgba(229, 57, 53, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text);
            line-height: 1.6;
        }
        
        /* Full Page Layout */
        .stories-fullpage {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .stories-header {
            background: url('https://images.unsplash.com/photo-1531206715517-5c0ba140b2b8?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80'); 
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: white;
            padding: 160px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .stories-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); 
            z-index: 1;
        }
        
        .page-header-content {
            position: relative;
            z-index: 2;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-title {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--primary-red);
            border-radius: 2px;
        }
        
        .page-description {
            font-size: 22px;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.95;
            position: relative;
            line-height: 1.6;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
            font-weight: 500;
        }
        
        /* Main Container */
        .stories-container {
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px 60px 20px;
            width: 100%;
        }

        /* --- Categories Grid (从 Special Case 移植过来的样式) --- */
        .categories-container { 
            margin-bottom: 40px; 
            margin-top: 20px;
        }
        .categories-title { 
            font-size: 24px; 
            color: var(--primary-red); 
            margin-bottom: 25px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        /* 调整Grid布局，适应News只有2-3个分类的情况，居中显示 */
        .categories-grid { 
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .category-item { 
            background: var(--white); 
            border: 2px solid #eee; 
            border-radius: 10px; 
            padding: 15px 30px; 
            text-align: center; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            text-decoration: none; 
            color: var(--text); 
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 150px;
            justify-content: center;
        }
        .category-item:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
        }
        .category-item.active { 
            color: white; 
            transform: translateY(-5px); 
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15); 
        }
        .category-icon { 
            font-size: 20px; 
        }
        .category-name { 
            font-size: 16px; 
            font-weight: 600; 
        }
        
        /* Story Grid */
        .stories-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            margin-bottom: 50px;
        }
        
        /* Story Card */
        .story-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px var(--shadow);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--lighter-red);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .story-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(229, 57, 53, 0.2);
        }
        
        .story-image-container {
            position: relative;
            height: 300px;
            overflow: hidden;
        }
        
        .story-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
            cursor: zoom-in;
        }
        
        .story-card:hover .story-image {
            transform: scale(1.05);
        }
        
        /* Badge Styles */
        .story-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }

        /* 红色 Badge (Story) */
        .badge-red {
            background: var(--primary-red);
        }

        /* 蓝色 Badge (News) */
        .badge-blue {
            background: var(--primary-blue);
        }
        
        .story-content {
            padding: 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .story-header {
            margin-bottom: 20px;
        }
        
        .story-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 12px;
            color: #3b3b3b;
            font-size: 14px;
        }
        
        .story-meta i {
            color: var(--primary-red);
        }
        
        .story-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 10px;
            line-height: 1.3;
        }
        
        .story-author {
            color: var(--primary-red);
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .story-author i {
            font-size: 16px;
        }
        
        .story-description-container {
            margin-bottom: 20px;
            flex: 1;
        }
        
        .story-description {
            color: #3b3b3b;
            font-size: 17px;
            line-height: 1.8;
            /* 保持文字截断，不提供展开功能 */
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .story-footer {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--lighter-red);
            display: flex;
            justify-content: flex-end; /* 让按钮靠右 */
        }
        
        /* 底部 Read Full Story 按钮样式 */
        .read-more-link {
            text-decoration: none;
            color: var(--primary-red);
            font-weight: 700;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .read-more-link:hover {
            color: var(--dark-red);
            transform: translateX(5px);
        }
        
        /* Empty State */
        .no-stories {
            text-align: center;
            padding: 100px 20px;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 10px 30px var(--shadow);
            grid-column: 1 / -1;
        }
        
        .no-stories i {
            font-size: 80px;
            color: var(--lighter-red);
            margin-bottom: 30px;
        }
        
        .no-stories h3 {
            font-size: 32px;
            color: var(--text);
            margin-bottom: 15px;
        }
        
        .no-stories p {
            color: #3b3b3b;
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* --- Pagination (从 Special Case 移植) --- */
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
            .stories-container { padding: 50px 30px; }
            .page-title { font-size: 48px; }
            .story-title { font-size: 24px; }
        }
        
        @media (max-width: 992px) {
            .stories-grid { grid-template-columns: 1fr; gap: 30px; }
            .page-title { font-size: 42px; }
            .page-description { font-size: 20px; padding: 0 20px; }
            .story-image-container { height: 250px; }
        }
        
        @media (max-width: 768px) {
            .stories-header { padding: 60px 0 40px; }
            .stories-container { padding: 40px 20px; }
            .page-title { font-size: 36px; }
            .story-content { padding: 25px; }
            .story-title { font-size: 22px; }
            .story-description { font-size: 16px; }
            .categories-grid { flex-direction: column; }
        }
        
        @media (max-width: 576px) {
            .page-title { font-size: 32px; }
            .page-description { font-size: 18px; }
            .story-image-container { height: 200px; }
            .story-meta { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
        
        @media (max-width: 480px) {
            .stories-header { padding: 50px 0 30px; }
            .page-title { font-size: 28px; }
            .stories-container { padding: 30px 15px; }
            .story-content { padding: 20px; }
            .story-description { font-size: 15px; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="stories-fullpage">
        <div class="stories-header">
            <div class="page-header-content">
                <h1 class="page-title">News & Stories</h1>
                <p class="page-description">Discover inspiring stories of hope, resilience, and transformation from our community. Each story represents a life touched by generosity.</p>
            </div>
        </div>

        <div class="stories-container">
            
            <div class="categories-container">
                <div class="categories-grid">
                    <a href="?category=all&page=1" class="category-item <?php echo $category_filter == 'all' ? 'active' : ''; ?>" style="<?php echo $category_filter == 'all' ? 'background: #e53935; border-color: #e53935;' : ''; ?>">
                        <i class="fas fa-layer-group category-icon" style="<?php echo $category_filter == 'all' ? 'color: white;' : 'color: #e53935;'; ?>"></i>
                        <span class="category-name">Show All</span>
                    </a>

                    <?php foreach($categories as $key => $cat_data): ?>
                        <?php 
                            $isActive = ($category_filter == $key);
                            $bgStyle = $isActive ? "background: {$cat_data['color']}; border-color: {$cat_data['color']};" : "border-color: {$cat_data['color']};";
                            $iconColor = $isActive ? "white" : $cat_data['color'];
                        ?>
                        <a href="?category=<?php echo $key; ?>&page=1" class="category-item <?php echo $isActive ? 'active' : ''; ?>" style="<?php echo $bgStyle; ?>">
                            <i class="<?php echo $cat_data['icon']; ?> category-icon" style="color: <?php echo $iconColor; ?>"></i>
                            <span class="category-name"><?php echo $cat_data['name']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stories-grid">
                <?php if ($result->num_rows > 0): 
                    while($story = $result->fetch_assoc()): 
                        $storyDate = date('F d, Y', strtotime($story['Story_Date']));
                        
                        // --- 图片路径处理 ---
                        $defaultImage = 'images/story-default.jpg';
                        $imagePath = $defaultImage;
                        
                        if (!empty($story['Story_Image'])) {
                            $decoded = json_decode($story['Story_Image'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) {
                                $imagePath = $decoded[0];
                            } else {
                                $imagePath = $story['Story_Image'];
                            }
                        }

                        $title = !empty($story['Story_Title']) ? $story['Story_Title'] : "Story #" . $story['Story_ID'];
                        $author = !empty($story['Story_Author']) ? $story['Story_Author'] : "Anonymous";
                        $category = !empty($story['Story_Category']) ? $story['Story_Category'] : "General";
                        $description = $story['Story_Description'];

                        // 设置 Badge 颜色
                        if (stripos($category, 'News') !== false) {
                            $badgeClass = 'badge-blue';
                        } else {
                            $badgeClass = 'badge-red'; 
                        }
                ?>
                    <div class="story-item">
                        <div class="story-card">
                            <div class="story-image-container">
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($title); ?>" 
                                     class="story-image"
                                     onclick="showImage(this.src, '<?php echo htmlspecialchars(addslashes($title)); ?>')"
                                     onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>';">
                                <span class="story-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($category); ?></span>
                            </div>
                            
                            <div class="story-content">
                                <div class="story-header">
                                    <div class="story-meta">
                                        <span><i class="far fa-calendar"></i> <?php echo $storyDate; ?></span>
                                    </div>
                                    <h3 class="story-title"><?php echo htmlspecialchars($title); ?></h3>
                                    <div class="story-author">
                                        <i class="fas fa-user-edit"></i> By <?php echo htmlspecialchars($author); ?>
                                    </div>
                                </div>
                                
                                <div class="story-description-container">
                                    <p class="story-description">
                                        <?php echo nl2br(htmlspecialchars($description)); ?>
                                    </p>
                                </div>
                                
                                <div class="story-footer">
                                    <a href="story_detail.php?id=<?php echo $story['Story_ID']; ?>" class="read-more-link">
                                        Read Full Story <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-stories">
                        <i class="fas fa-book-open"></i>
                        <h3>No Stories Found</h3>
                        <p>We couldn't find any stories in this category.</p>
                        <?php if ($category_filter !== 'all'): ?>
                            <p style="margin-top: 15px;"><a href="?category=all" style="color: var(--primary-red); font-weight: bold;">View All Stories</a></p>
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
    </div>

    <script>
        // --- 图片点击放大函数 ---
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
        
        // 进场动画
        document.addEventListener('DOMContentLoaded', function() {
            const storyCards = document.querySelectorAll('.story-card');
            storyCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>