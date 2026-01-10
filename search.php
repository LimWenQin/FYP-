<?php
// search.php
// 1. 引入数据库连接和头部
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI.php';

$search_query = "";
$activity_results = [];
$case_results = [];
$story_results = [];

// 2. 执行搜索
if (isset($_GET['query'])) {
    $search_query = trim($_GET['query']);

    if (!empty($search_query)) {
        $param = "%" . $search_query . "%";

        // --- A. 搜索 Activities (包含 Activity_Description) ---
        $sql_act = "SELECT * FROM activity 
                    WHERE Activity_Name LIKE ? 
                    OR Activity_Description LIKE ? 
                    AND Activity_Status = 'Active'";
        
        if ($stmt = $conn->prepare($sql_act)) {
            $stmt->bind_param("ss", $param, $param);
            $stmt->execute();
            $activity_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        // --- B. 搜索 Special Cases (包含 Case_Description) ---
        $sql_case = "SELECT * FROM special_case 
                     WHERE Case_Title LIKE ? 
                     OR Case_Description LIKE ? 
                     AND Case_Status = 'Active'";

        if ($stmt = $conn->prepare($sql_case)) {
            $stmt->bind_param("ss", $param, $param);
            $stmt->execute();
            $case_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        // --- C. 搜索 Stories (包含 Story_Description) ---
        $sql_story = "SELECT * FROM story 
                      WHERE Story_Title LIKE ? 
                      OR Story_Description LIKE ? 
                      AND Story_Status = 'Published'";

        if ($stmt = $conn->prepare($sql_story)) {
            $stmt->bind_param("ss", $param, $param);
            $stmt->execute();
            $story_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results - Love Bridge</title>
    <style>
        /* 全局样式 */
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; }
        
        .search-header {
            background-color: #333;
            background-image: url('images/hero_1.jpg'); /* 可以换成你的 hero banner */
            background-size: cover;
            background-position: center;
            padding: 60px 0;
            text-align: center;
            color: white;
            margin-bottom: 40px;
            position: relative;
        }
        .search-header::before { content: ''; position: absolute; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.6); }
        .search-header h1 { position: relative; z-index: 1; font-size: 2.5rem; font-weight: 700; }
        .search-header p { position: relative; z-index: 1; font-size: 1.2rem; color: #ddd; }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        .section-heading {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            border-left: 5px solid #dc2626;
            padding-left: 15px;
            margin-top: 40px;
        }

        /* 卡片网格布局 */
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        /* 卡片样式 (模仿 Homepage) */
        .card-item {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid #eee;
        }
        .card-item:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.15); }

        .card-img-wrap { height: 220px; overflow: hidden; position: relative; }
        .card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .card-item:hover .card-img { transform: scale(1.05); }

        .card-body { padding: 25px; flex-grow: 1; display: flex; flex-direction: column; }
        
        .badge-tag {
            display: inline-block; padding: 5px 12px; border-radius: 20px; 
            font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; width: fit-content;
        }
        .tag-case { background: #fee2e2; color: #dc2626; }
        .tag-activity { background: #dbeafe; color: #2563eb; }
        .tag-story { background: #d1fae5; color: #059669; }

        .card-title { font-size: 1.25rem; font-weight: 700; color: #222; margin: 0 0 10px 0; line-height: 1.4; }
        .card-desc { font-size: 0.95rem; color: #666; line-height: 1.6; margin-bottom: 20px; flex-grow: 1; 
                     display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

        /* 进度条样式 */
        .progress-container { margin-bottom: 20px; }
        .progress-bar-bg { height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
        .progress-fill { height: 100%; border-radius: 4px; }
        .fill-red { background: #dc2626; }
        .fill-blue { background: #2563eb; }
        
        .fund-stats { display: flex; justify-content: space-between; font-size: 0.9rem; font-weight: 600; color: #444; }
        .raised-txt { color: #dc2626; }

        /* 按钮样式 */
        .btn-view {
            display: block; width: 100%; padding: 12px; text-align: center;
            border-radius: 8px; font-weight: 600; text-decoration: none; transition: 0.2s;
            margin-top: auto;
        }
        .btn-case { background: #fff; border: 2px solid #dc2626; color: #dc2626; }
        .btn-case:hover { background: #dc2626; color: white; }
        
        .btn-activity { background: #fff; border: 2px solid #2563eb; color: #2563eb; }
        .btn-activity:hover { background: #2563eb; color: white; }

        .btn-story { background: #fff; border: 2px solid #059669; color: #059669; }
        .btn-story:hover { background: #059669; color: white; }

        /* 无结果样式 */
        .no-results { text-align: center; padding: 80px 20px; color: #888; }
        .no-results i { font-size: 60px; margin-bottom: 20px; color: #ddd; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="search-header">
    <div class="container">
        <h1>Search Results</h1>
        <p>Showing results for "<?php echo htmlspecialchars($search_query); ?>"</p>
    </div>
</div>

<div class="container" style="padding-bottom: 80px;">

    <?php if (!empty($case_results)): ?>
        <h2 class="section-heading">Fundraising Cases</h2>
        <div class="results-grid">
            <?php foreach ($case_results as $row): 
                // 计算进度条
                $percent = 0;
                if ($row['Target_Amount'] > 0) {
                    $percent = ($row['Raised_Amount'] / $row['Target_Amount']) * 100;
                    $percent = min(100, $percent); // 不超过100%
                }
                $img = !empty($row['Case_Image']) ? $row['Case_Image'] : 'images/default_case.jpg';
            ?>
            <div class="card-item">
                <div class="card-img-wrap">
                    <img src="<?php echo htmlspecialchars($img); ?>" class="card-img" alt="Case">
                </div>
                <div class="card-body">
                    <span class="badge-tag tag-case">Urgent Case</span>
                    <h3 class="card-title"><?php echo htmlspecialchars($row['Case_Title']); ?></h3>
                    
                    <div class="progress-container">
                        <div class="progress-bar-bg">
                            <div class="progress-fill fill-red" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                        <div class="fund-stats">
                            <span class="raised-txt">RM <?php echo number_format($row['Raised_Amount']); ?></span>
                            <span style="color:#999;">Goal: RM <?php echo number_format($row['Target_Amount']); ?></span>
                        </div>
                    </div>

                    <p class="card-desc"><?php echo htmlspecialchars(strip_tags($row['Case_Description'])); ?></p>
                    
                    <a href="Special_case Page.php?case_id=<?php echo $row['Case_ID']; ?>" class="btn-view btn-case">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($activity_results)): ?>
        <h2 class="section-heading">Campaigns & Activities</h2>
        <div class="results-grid">
            <?php foreach ($activity_results as $row): 
                // 处理图片：如果是 JSON 数组，取第一张；如果是路径字符串，直接用
                $img = 'images/activity_default.jpg';
                if (!empty($row['Activity_Images'])) {
                    $decoded = json_decode($row['Activity_Images'], true);
                    if (is_array($decoded) && !empty($decoded)) {
                        $img = $decoded[0];
                    } else {
                        // 兼容旧数据格式
                        $img = $row['Activity_Images'];
                    }
                } elseif (!empty($row['Activity_Picture'])) {
                    $img = $row['Activity_Picture'];
                }

                // 计算进度条
                $percent = 0;
                if ($row['Activity_TargetAmount'] > 0) {
                    $percent = ($row['Activity_GetAmount'] / $row['Activity_TargetAmount']) * 100;
                    $percent = min(100, $percent);
                }
            ?>
            <div class="card-item">
                <div class="card-img-wrap">
                    <img src="<?php echo htmlspecialchars($img); ?>" class="card-img" alt="Activity">
                </div>
                <div class="card-body">
                    <span class="badge-tag tag-activity">Campaign</span>
                    <h3 class="card-title"><?php echo htmlspecialchars($row['Activity_Name']); ?></h3>
                    
                    <div class="progress-container">
                        <div class="progress-bar-bg">
                            <div class="progress-fill fill-blue" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                        <div class="fund-stats">
                            <span style="color:#2563eb;">RM <?php echo number_format($row['Activity_GetAmount']); ?></span>
                            <span style="color:#999;">Goal: RM <?php echo number_format($row['Activity_TargetAmount']); ?></span>
                        </div>
                    </div>

                    <p class="card-desc"><?php echo htmlspecialchars(strip_tags($row['Activity_Description'])); ?></p>
                    
                    <a href="Campaign_Page.php?id=<?php echo $row['Activity_ID']; ?>" class="btn-view btn-activity">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($story_results)): ?>
        <h2 class="section-heading">Stories & Updates</h2>
        <div class="results-grid">
            <?php foreach ($story_results as $row): 
                $img = !empty($row['Story_Image']) ? $row['Story_Image'] : 'images/default_story.jpg';
            ?>
            <div class="card-item">
                <div class="card-img-wrap">
                    <img src="<?php echo htmlspecialchars($img); ?>" class="card-img" alt="Story">
                </div>
                <div class="card-body">
                    <span class="badge-tag tag-story"><?php echo htmlspecialchars($row['Story_Category'] ?? 'Story'); ?></span>
                    <h3 class="card-title"><?php echo htmlspecialchars($row['Story_Title']); ?></h3>
                    <p class="card-desc"><?php echo htmlspecialchars(strip_tags($row['Story_Description'])); ?></p>
                    
                    <a href="New&Story.php" class="btn-view btn-story">
                        Read Story <i class="fas fa-book-open"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($activity_results) && empty($case_results) && empty($story_results)): ?>
        <div class="no-results">
            <i class="fas fa-search-minus"></i>
            <h3>No results found</h3>
            <p>We couldn't find anything matching "<?php echo htmlspecialchars($search_query); ?>".<br>Try different keywords or check for spelling errors.</p>
            <br>
            <a href="Homepage.php" class="btn-view btn-case" style="max-width: 200px; margin: 0 auto;">Back to Home</a>
        </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>

</body>
</html>