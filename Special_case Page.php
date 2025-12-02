<?php
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI.php';



// 分页逻辑
$cases_per_page = 4; // 每页显示4个案例(2行)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $cases_per_page;

// 获取总案例数
$count_query = "SELECT COUNT(*) as total FROM special_case WHERE Case_Status = 'active'";
$count_result = $conn->query($count_query);
$total_cases = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_cases / $cases_per_page);

// 获取当前页的案例
$query = "SELECT * FROM special_case WHERE Case_Status = 'active' ORDER BY created_at DESC LIMIT $cases_per_page OFFSET $offset";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Special Cases - Donation Platform</title>
    <style>
        :root {
            --primary: #F6B8B8;
            --secondary: #FFF5E4;
            --text: #4A4A4A;
            --light-text: #777777;
            --white: #FFFFFF;
            --shadow: rgba(0, 0, 0, 0.1);
            --progress-bg: #e0e0e0;
            --progress-fill: #F6B8B8;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: var(--secondary);
            color: var(--text);
            line-height: 1.6;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #434141;
            padding: 6px 50px;
            box-shadow: 0 2px 4px var(--shadow);
        }
        
        .welcome-text {
            font-size: 16px;
            font-weight: bold;
            color: white;
        }
        
        .auth-buttons {
            display: flex;
            gap: 10px;
        }
        
        .auth-btn {
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            text-decoration: none;
            font-size: 14px;
        }
        
        .login-btn {
            background-color: white;
            color: black;
            border: none;
        }
        
        .login-btn:hover {
            background-color: #938e8eff;
        }
        
        .register-btn {
            background-color: var(--text);
            color: var(--white);
            border: none;
        }
        
        .register-btn:hover {
            background-color: #333333;
        }
        
        .header {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr; 
            align-items: center;
            background-color: var(--primary);
            padding: 20px 50px;
            box-shadow: 0 2px 6px var(--shadow);
            gap: 20px;
        }
        
        .logo {
            font-weight: bold;
            font-size: 36px;
            color: var(--text);
            text-align: left; 
            grid-column: 1;
        }
        
        .header-right { 
            grid-column: 2;
            display: flex;
            align-items: center;
            justify-content: center; 
            gap: 15px;
        }

        .function-links {
            display: flex;
            gap: 15px;
        }
        
        .function-links a {
            text-decoration: none;
            font-size: 18px;
            color: var(--text);
            font-weight: bold;
            transition: opacity 0.3s;
            white-space: nowrap;
        }
        
        .function-links a:hover {
            opacity: 0.8;
        }

        .header-center { 
            grid-column: 3;
            display: flex;
            align-items: center;
            justify-content: flex-end; 
            gap: 15px;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background-color: var(--white);
            border-radius: 4px;
            padding: 4px 8px;
            width: auto; 
            max-width: 250px;
        }
        
        .search-box input {
            border: none;
            background: transparent;
            padding: 6px;
            width: 100%;
            outline: none;
        }
        
        .donate-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 6px 18px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            white-space: nowrap;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .donate-btn:hover {
            background-color: #c0392b;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-name {
            font-weight: bold;
        }
        
        .case-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 36px;
        }
        
        .page-description {
            text-align: center;
            margin-bottom: 40px;
            font-size: 18px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cases-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .case-card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            overflow: hidden;
            transition: transform 0.3s;
            position: relative;
        }
        
        .case-card:hover {
            transform: translateY(-5px);
        }
        
        .case-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .case-content {
            padding: 20px;
        }
        
        .case-title {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text);
        }
        
        .case-description {
            margin-bottom: 15px;
            color: var(--light-text);
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .case-description.expanded {
            -webkit-line-clamp: unset;
            overflow: visible;
            display: block;
        }
        
        .show-more-btn {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-weight: bold;
            margin-top: 5px;
            padding: 0;
        }
        
        .show-more-btn:hover {
            text-decoration: underline;
        }
        
        .progress-container {
            margin-bottom: 15px;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .progress-bar {
            height: 10px;
            background-color: var(--progress-bg);
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var(--progress-fill);
            border-radius: 5px;
            transition: width 0.5s ease-in-out;
        }
        
        .progress-percentage {
            text-align: right;
            font-size: 12px;
            color: var(--light-text);
        }
        
        .case-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .case-amount {
            font-weight: bold;
            color: var(--primary);
        }
        
        .case-deadline {
            color: var(--light-text);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--text);
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            text-decoration: none;
            text-align: center;
        }
        
        .btn:hover {
            background-color: #f0a8a8;
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-full {
            display: block;
            width: 100%;
        }
        
        .no-cases {
            text-align: center;
            padding: 40px;
            grid-column: 1 / -1;
        }
        
        .urgent-tag {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: #ff5252;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 30px;
            gap: 10px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #ddd;
            color: var(--text);
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .pagination a:hover {
            background-color: var(--primary);
        }
        
        .pagination .current {
            background-color: var(--primary);
            color: white;
            border: 1px solid var(--primary);
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            .logo {
                font-size: 28px;
            }
            
            .case-container {
                padding: 15px;
            }
            
            .cases-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Your header content here -->

    <div class="case-container">
        <h1 class="page-title">Special Cases</h1>
        <p class="page-description">These are urgent cases that need immediate support. Your donation can make a significant difference in someone's life today.</p>
        
        <div class="cases-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while($case = $result->fetch_assoc()): 
                    // 计算进度百分比
                    $raised = isset($case['Raised_Amount']) ? floatval($case['Raised_Amount']) : 0;
                    $target = isset($case['Target_Amount']) && floatval($case['Target_Amount']) > 0 ? floatval($case['Target_Amount']) : 1;
                    
                    $progress = ($raised / $target) * 100;
                    $progress = min($progress, 100); // 确保不超过100%
                    
                    $is_urgent = isset($case['Urgency']) && $case['Urgency'] === 'high';
                    $donor_count = isset($case['Donor_Count']) ? $case['Donor_Count'] : 0;
                ?>
                    <div class="case-card">
                        <?php if ($is_urgent): ?>
                            <div class="urgent-tag">URGENT</div>
                        <?php endif; ?>
                        
                        <img src="<?php echo !empty($case['image']) ? htmlspecialchars($case['image']) : 'picture/orphan.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($case['Case_Title']); ?>" 
                             class="case-image">
                             
                        <div class="case-content">
                            <h3 class="case-title"><?php echo htmlspecialchars($case['Case_Title']); ?></h3>
                            <div class="case-description-container">
                                <p class="case-description" id="desc-<?php echo $case['Case_ID']; ?>">
                                    <?php echo htmlspecialchars($case['Case_Description']); ?>
                                </p>
                                <?php if (strlen($case['Case_Description']) > 150): ?>
                                    <button class="show-more-btn" onclick="toggleDescription(<?php echo $case['Case_ID']; ?>)">Show more</button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- 进度条部分 -->
                            <div class="progress-container">
                                <div class="progress-info">
                                    <span>Raised: RM <?php echo number_format($raised, 2); ?></span>
                                    <span>Goal: RM <?php echo number_format($target, 2); ?></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <div class="progress-percentage">
                                    <?php echo number_format($progress, 1); ?>% funded
                                </div>
                                <div class="progress-info">
                                    <span><?php echo $donor_count; ?> donors</span>
                                    <span class="case-deadline">
                                        Deadline: <?php echo date('M j, Y', strtotime($case['Case_Deadline'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <a href="S_C_Payment_Page.php?case_id=<?php echo $case['Case_ID']; ?>" class="btn btn-full">Donate Now</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-cases">
                    <h3>No special cases at the moment</h3>
                    <p>All urgent cases have been resolved. Check back later for new cases.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleDescription(caseId) {
            const description = document.getElementById(`desc-${caseId}`);
            const button = description.nextElementSibling;
            
            if (description.classList.contains('expanded')) {
                description.classList.remove('expanded');
                button.textContent = 'Show more';
            } else {
                description.classList.add('expanded');
                button.textContent = 'Show less';
            }
        }
    </script>
</body>
</html>