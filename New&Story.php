<?php
include 'dataconnection.php';
include 'header_function.php';

// 获取所有故事，按日期倒序排列（最新的在前）
$query = "SELECT * FROM story ORDER BY Story_Date DESC";
$result = $conn->query($query);

// 获取故事总数
$totalStories = $result->num_rows;

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
            
            /* 新增蓝色变量 */
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
        
        /* Header Styles */
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
            width: 120%;
            height: 120%;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5); 
            background-size: 120px;
        }
        
        .page-header-content {
            position: relative;
            z-index: 2;
        }
        
        .page-title {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
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
        
        /* --- Filter Buttons Styles (New) --- */
        .filter-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 40px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            border: none;
            outline: none;
            padding: 10px 25px;
            background-color: white;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            border-radius: 30px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            color: #555;
        }

        .filter-btn:hover {
            background-color: #f0f0f0;
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background-color: var(--primary-red);
            color: white;
            box-shadow: 0 4px 15px rgba(229, 57, 53, 0.3);
        }

        /* Filter Logic: Hidden Elements */
        .story-item.hide {
            display: none;
        }

        /* Main Container */
        .stories-container {
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 40px 60px 40px; /* Top padding reduced */
            width: 100%;
        }
        
        /* Story Grid */
        .stories-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
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
            <div class="filter-container">
                <button class="filter-btn active" onclick="filterSelection('all')">Show All</button>
                <button class="filter-btn" onclick="filterSelection('news')">News</button>
                <button class="filter-btn" onclick="filterSelection('story')">Donor Stories</button>
            </div>

            <div class="stories-grid">
                <?php if ($totalStories > 0): 
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

                        // --- 逻辑 1: Filter Tag & Filter Category ---
                        // 如果 Category 包含 "News" (不区分大小写)，设为 News 类型，否则为 Story 类型
                        if (stripos($category, 'News') !== false) {
                            $badgeClass = 'badge-blue';
                            $filterCategory = 'news'; // 用于 Filter
                        } else {
                            $badgeClass = 'badge-red'; 
                            $filterCategory = 'story'; // 用于 Filter
                        }
                ?>
                    <div class="story-item" data-category="<?php echo $filterCategory; ?>">
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
                        <h3>No Stories Available</h3>
                        <p>We're currently gathering inspiring stories from our community. Check back soon.</p>
                    </div>
                <?php endif; ?>
            </div>
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

        // --- Filter Logic (筛选逻辑) ---
        function filterSelection(c) {
            var x, i;
            x = document.getElementsByClassName("story-item");
            
            // 切换按钮的 active 状态
            var btns = document.getElementsByClassName("filter-btn");
            for (i = 0; i < btns.length; i++) {
                // 如果当前按钮被点击
                if (btns[i].innerText.toLowerCase().includes(c) || (c === 'all' && btns[i].innerText === 'Show All') || (c === 'story' && btns[i].innerText === 'Donor Stories')) {
                    // 简单的遍历移除 active 再添加有点麻烦，这里直接用 event.target 逻辑会更好
                    // 但为了简单适配 onclick，我们用一个专门的循环处理 Button active
                }
            }
            
            // 执行筛选
            if (c == "all") c = "";
            for (i = 0; i < x.length; i++) {
                removeClass(x[i], "hide"); // 先移除隐藏
                let itemCategory = x[i].getAttribute('data-category');
                
                // 如果不匹配，则隐藏
                if (c !== "" && itemCategory !== c) {
                    addClass(x[i], "hide");
                }
            }
            
            // 重新触发进场动画
            animateStoryCards();
        }

        // 辅助函数：添加类
        function addClass(element, name) {
            var arr1, arr2;
            arr1 = element.className.split(" ");
            arr2 = name.split(" ");
            for (var i = 0; i < arr2.length; i++) {
                if (arr1.indexOf(arr2[i]) == -1) {element.className += " " + arr2[i];}
            }
        }

        // 辅助函数：移除类
        function removeClass(element, name) {
            var arr1, arr2;
            arr1 = element.className.split(" ");
            arr2 = name.split(" ");
            for (var i = 0; i < arr2.length; i++) {
                while (arr1.indexOf(arr2[i]) > -1) {
                    arr1.splice(arr1.indexOf(arr2[i]), 1);     
                }
            }
            element.className = arr1.join(" ");
        }

        // 处理按钮 Active 样式
        var btnContainer = document.querySelector(".filter-container");
        var btns = btnContainer.getElementsByClassName("filter-btn");
        for (var i = 0; i < btns.length; i++) {
            btns[i].addEventListener("click", function(){
                var current = document.getElementsByClassName("active");
                if (current.length > 0) { 
                    current[0].className = current[0].className.replace(" active", "");
                }
                this.className += " active";
            });
        }

        // --- Animation Logic ---
        function animateStoryCards() {
            const storyCards = document.querySelectorAll('.story-card');
            
            // 重置状态
            storyCards.forEach(card => {
                // 如果父级被隐藏了，就不管它
                if(!card.parentElement.classList.contains('hide')) {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                }
            });

            // 稍微延迟后开始动画
            setTimeout(() => {
                storyCards.forEach((card, index) => {
                    // 只动画显示的元素
                    if(!card.parentElement.classList.contains('hide')) {
                        const rect = card.getBoundingClientRect();
                        // 简单的依次显示，或者视口检测
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, index * 100); 
                    }
                });
            }, 100);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            const storyCards = document.querySelectorAll('.story-card');
            storyCards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
            });
            
            // Trigger initial animation
            setTimeout(animateStoryCards, 100);
        
        });
    </script>
</body>
</html>