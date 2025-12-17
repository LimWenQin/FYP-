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
    <style>
        :root {
            --primary-red: #e53935;
            --dark-red: #c62828;
            --light-red: #ff5252;
            --lighter-red: #ffcdd2;
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
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
            color: white;
            padding: 80px 0 60px;
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
            right: 0;
            bottom: 0;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M50,0 C77.6,0 100,22.4 100,50 C100,77.6 77.6,100 50,100 C22.4,100 0,77.6 0,50 C0,22.4 22.4,0 50,0 Z" fill="white" fill-opacity="0.05"/></svg>');
            background-size: 120px;
            opacity: 0.1;
        }
        
        .page-title {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .page-description {
            font-size: 22px;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.9;
            position: relative;
            line-height: 1.6;
        }
        
        /* Main Container */
        .stories-container {
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 60px 40px;
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
        }
        
        .story-card:hover .story-image {
            transform: scale(1.05);
        }
        
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
            background: var(--primary-red);
            color: white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
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
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .story-description.expanded {
            -webkit-line-clamp: unset;
            overflow: visible;
            display: block;
        }
        
        .show-more-btn {
            background: none;
            border: none;
            color: var(--primary-red);
            cursor: pointer;
            font-weight: 600;
            margin-top: 15px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .show-more-btn:hover {
            transform: translateX(5px);
        }
        
        .show-more-btn i {
            font-size: 14px;
            transition: transform 0.3s ease;
        }
        
        .story-footer {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--lighter-red);
        }
        
        .story-category {
            display: inline-block;
            padding: 6px 15px;
            background: var(--lighter-red);
            color: var(--primary-red);
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
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
            .stories-container {
                padding: 50px 30px;
            }
            
            .page-title {
                font-size: 48px;
            }
            
            .story-title {
                font-size: 24px;
            }
        }
        
        @media (max-width: 992px) {
            .stories-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .page-title {
                font-size: 42px;
            }
            
            .page-description {
                font-size: 20px;
                padding: 0 20px;
            }
            
            .story-image-container {
                height: 250px;
            }
        }
        
        @media (max-width: 768px) {
            .stories-header {
                padding: 60px 0 40px;
            }
            
            .stories-container {
                padding: 40px 20px;
            }
            
            .page-title {
                font-size: 36px;
            }
            
            .story-content {
                padding: 25px;
            }
            
            .story-title {
                font-size: 22px;
            }
            
            .story-description {
                font-size: 16px;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 32px;
            }
            
            .page-description {
                font-size: 18px;
            }
            
            .story-image-container {
                height: 200px;
            }
            
            .story-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .stories-header {
                padding: 50px 0 30px;
            }
            
            .page-title {
                font-size: 28px;
            }
            
            .stories-container {
                padding: 30px 15px;
            }
            
            .story-content {
                padding: 20px;
            }
            
            .story-description {
                font-size: 15px;
            }
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="stories-fullpage">
        <!-- Stories Header -->
        <div class="stories-header">
            <h1 class="page-title">News & Stories</h1>
            <p class="page-description">Discover inspiring stories of hope, resilience, and transformation from our community. Each story represents a life touched by generosity.</p>
        </div>

        <!-- Main Content -->
        <div class="stories-container">
            <div class="stories-grid">
                <?php if ($totalStories > 0): 
                    while($story = $result->fetch_assoc()): 
                        $storyDate = date('F d, Y', strtotime($story['Story_Date']));
                        $imagePath = !empty($story['Story_Image']) ? $story['Story_Image'] : 'images/story-default.jpg';
                        $title = !empty($story['Story_Title']) ? $story['Story_Title'] : "Story #" . $story['Story_ID'];
                        $author = !empty($story['Story_Author']) ? $story['Story_Author'] : "Anonymous";
                        $category = !empty($story['Story_Category']) ? $story['Story_Category'] : "General";
                        
                        // 检查描述长度
                        $description = $story['Donor_Description'];
                        $descriptionLength = strlen($description);
                        $hasLongDescription = $descriptionLength > 200;
                ?>
                    <div class="story-card">
                        <!-- Story Image -->
                        <div class="story-image-container">
                            <img src="<?php echo $imagePath; ?>" 
                                 alt="<?php echo htmlspecialchars($title); ?>" 
                                 class="story-image"
                                 onerror="this.src='images/story-default.jpg'">
                            <span class="story-badge"><?php echo htmlspecialchars($category); ?></span>
                        </div>
                        
                        <!-- Story Content -->
                        <div class="story-content">
                            <!-- Story Header -->
                            <div class="story-header">
                                <div class="story-meta">
                                    <span><i class="far fa-calendar"></i> <?php echo $storyDate; ?></span>
                                    <span><i class="far fa-clock"></i> <?php echo ceil($descriptionLength / 1000); ?> min read</span>
                                </div>
                                <h3 class="story-title"><?php echo htmlspecialchars($title); ?></h3>
                                <div class="story-author">
                                    <i class="fas fa-user-edit"></i> By <?php echo htmlspecialchars($author); ?>
                                </div>
                            </div>
                            
                            <!-- Story Description -->
                            <div class="story-description-container">
                                <p class="story-description" id="story-desc-<?php echo $story['Story_ID']; ?>">
                                    <?php echo nl2br(htmlspecialchars($description)); ?>
                                </p>
                                <?php if ($hasLongDescription): ?>
                                    <button class="show-more-btn" onclick="toggleStoryDescription(<?php echo $story['Story_ID']; ?>)">
                                        <i class="fas fa-chevron-down"></i> Show more
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Story Footer -->
                            <div class="story-footer">
                                <span class="story-category"><?php echo htmlspecialchars($category); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-stories">
                        <i class="fas fa-book-open"></i>
                        <h3>No Stories Available</h3>
                        <p>We're currently gathering inspiring stories from our community. Check back soon to read about the impact of your generosity and the lives we've touched together.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php include 'footer.php'; ?>
    </div>

    <script>
        // Toggle story description expand/collapse
        function toggleStoryDescription(storyId) {
            const description = document.getElementById(`story-desc-${storyId}`);
            const button = description.nextElementSibling;
            
            if (description.classList.contains('expanded')) {
                description.classList.remove('expanded');
                button.innerHTML = '<i class="fas fa-chevron-down"></i> Show more';
                button.style.transform = 'translateX(0)';
            } else {
                description.classList.add('expanded');
                button.innerHTML = '<i class="fas fa-chevron-up"></i> Show less';
                button.style.transform = 'translateX(5px)';
            }
        }
        
        // Add animation to story cards on scroll
        function animateStoryCards() {
            const storyCards = document.querySelectorAll('.story-card');
            
            storyCards.forEach((card, index) => {
                const rect = card.getBoundingClientRect();
                if (rect.top < window.innerHeight - 100) {
                    // Add delay for staggered animation
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 100);
                }
            });
        }
        
        // Initialize story cards with hidden state
        document.addEventListener('DOMContentLoaded', function() {
            const storyCards = document.querySelectorAll('.story-card');
            storyCards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            });
            
            // Trigger initial animation
            setTimeout(animateStoryCards, 100);
            
            // Animate on scroll
            window.addEventListener('scroll', animateStoryCards);
        });
    </script>
</body>
</html>