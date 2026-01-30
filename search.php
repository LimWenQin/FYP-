<?php
// search.php
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI.php';

// ==========================================
// ⚠️ 时间修正 (TIME FIX)
// ==========================================
// 你的数据库活动都在 2026年。为了测试看到效果，我们这里强制设定“今天”是 2026年1月30日。
// 上线时，请把下面这行改成: $currentDate = date('Y-m-d');
$currentDate = '2026-01-30'; 
// ==========================================

$search_query = "";
$activity_results = [];
$case_results = [];
$story_results = [];

// 2. 执行搜索
if (isset($_GET['query'])) {
    $search_query = trim($_GET['query']);

    if (!empty($search_query)) {
        $param = "%" . $search_query . "%";

        // --- A. 搜索 Activities ---
        // 修正：移除了 'Is_Deleted' 防止报错 (因为你的 Activity 表没有这个栏位)
        $sql_act = "SELECT * FROM activity 
                    WHERE (Activity_Name LIKE ? OR Activity_Description LIKE ?) 
                    AND Activity_Status = 'Active'";
        
        if ($stmt = $conn->prepare($sql_act)) {
            $stmt->bind_param("ss", $param, $param);
            $stmt->execute();
            $activity_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        // --- B. 搜索 Special Cases ---
        // 注意：如果你已经在 special_case 表加了 Is_Deleted 字段，请取消下面那行的注释
        $sql_case = "SELECT * FROM special_case 
                     WHERE (Case_Title LIKE ? OR Case_Description LIKE ?) 
                     AND Case_Status IN ('Active', 'Completed')";
                     // AND Is_Deleted = 0"; // <--- 如果你有加这个字段，请取消注释

        if ($stmt = $conn->prepare($sql_case)) {
            $stmt->bind_param("ss", $param, $param);
            $stmt->execute();
            $case_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        // --- C. 搜索 Stories ---
        $sql_story = "SELECT * FROM story 
                      WHERE (Story_Title LIKE ? OR Story_Description LIKE ?) 
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
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; }
        .search-header {
            background-color: #333;
            background-image: url('https://images.unsplash.com/photo-1559027615-cd4628902d4a?auto=format&fit=crop&w=1950&q=80'); 
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
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
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
        /* Dynamic Status Colors */
        .tag-urgent { background: #fee2e2; color: #dc2626; }
        .tag-normal { background: #f3f4f6; color: #4b5563; }
        .tag-ongoing { background: #dcfce7; color: #166534; }
        .tag-upcoming { background: #ffedd5; color: #9a3412; }
        .tag-ended { background: #f3f4f6; color: #4b5563; }
        .tag-story { background: #d1fae5; color: #059669; }

        .card-title { font-size: 1.25rem; font-weight: 700; color: #222; margin: 0 0 10px 0; line-height: 1.4; }
        .card-desc { font-size: 0.95rem; color: #666; line-height: 1.6; margin-bottom: 20px; flex-grow: 1; 
                      display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .progress-container { margin-bottom: 20px; }
        .progress-bar-bg { height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
        .progress-fill { height: 100%; border-radius: 4px; }
        .fill-red { background: #dc2626; }
        .fill-blue { background: #2563eb; }
        .fund-stats { display: flex; justify-content: space-between; font-size: 0.9rem; font-weight: 600; color: #444; }
        .raised-txt { color: #dc2626; }
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
                // 图片处理逻辑 (支持 JSON 和 字符串)
                $img = 'images/case-default.jpg';
                if (!empty($row['Case_Images'])) {
                    $decoded = json_decode($row['Case_Images'], true);
                    if (is_array($decoded) && !empty($decoded)) {
                        $img = $decoded[0]; // 如果是 JSON 数组，取第一张
                    } else {
                        $img = $row['Case_Images']; // 如果是普通路径，直接使用
                    }
                }

                // 进度条
                $percent = 0;
                $raised = $row['Raised_Amount'] ?? 0;
                $target = $row['Target_Amount'] ?? 1;
                if ($target > 0) {
                    $percent = ($raised / $target) * 100;
                    $percent = min(100, $percent);
                }

                // Status Badge 逻辑
                $badgeText = "Special Case";
                $badgeClass = "tag-normal";
                
                if (isset($row['Urgency']) && $row['Urgency'] == 'high') {
                    $badgeText = "Urgent Case";
                    $badgeClass = "tag-urgent";
                }
                if ($row['Case_Status'] == 'Completed' || $percent >= 100) {
                    $badgeText = "Completed";
                    $badgeClass = "tag-ended";
                }
            ?>
            <div class="card-item">
                <div class="card-img-wrap">
                    <img src="<?php echo htmlspecialchars($img); ?>" 
                         class="card-img" 
                         alt="Case"
                         onerror="this.src='images/case-default.jpg'"> 
                </div>
                <div class="card-body">
                    <span class="badge-tag <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                    <h3 class="card-title"><?php echo htmlspecialchars($row['Case_Title']); ?></h3>
                    <div class="progress-container">
                        <div class="progress-bar-bg">
                            <div class="progress-fill fill-red" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                        <div class="fund-stats">
                            <span class="raised-txt">RM <?php echo number_format($raised); ?></span>
                            <span style="color:#999;">Goal: RM <?php echo number_format($target); ?></span>
                        </div>
                    </div>
                    <p class="card-desc"><?php echo htmlspecialchars(strip_tags($row['Case_Description'])); ?></p>
                    <a href="Special_Case_Detail.php?case_id=<?php echo $row['Case_ID']; ?>" class="btn-view btn-case">
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
                // Activity 图片处理 (支持 JSON 和 Picture 字段)
                $img = 'images/campaign-default.jpg';
                if (!empty($row['Activity_Images'])) {
                    $decoded = json_decode($row['Activity_Images'], true);
                    if (is_array($decoded) && !empty($decoded)) {
                        $img = $decoded[0];
                    } else {
                        $img = $row['Activity_Images'];
                    }
                } elseif (!empty($row['Activity_Picture'])) {
                    $img = $row['Activity_Picture'];
                }

                $percent = 0;
                $raised = $row['Activity_GetAmount'] ?? 0;
                $target = $row['Activity_TargetAmount'] ?? 1;
                if ($target > 0) {
                    $percent = ($raised / $target) * 100;
                    $percent = min(100, $percent);
                }

                // 动态 Status Badge 逻辑 (加入时间判断)
                $statusText = "Completed";
                $statusClass = "tag-ended";
                $start = $row['Activity_StartDate'];
                $end = $row['Activity_EndDate'];

                if ($row['Activity_Status'] == 'Active') {
                    if ($currentDate < $start) {
                        $statusText = "Upcoming";
                        $statusClass = "tag-upcoming";
                    } elseif ($currentDate > $end) {
                        $statusText = "Completed";
                        $statusClass = "tag-ended";
                    } else {
                        $statusText = "Ongoing";
                        $statusClass = "tag-ongoing";
                    }
                }
            ?>
            <div class="card-item">
                <div class="card-img-wrap">
                    <img src="<?php echo htmlspecialchars($img); ?>" 
                         class="card-img" 
                         alt="Activity"
                         onerror="this.src='images/campaign-default.jpg'"> </div>
                <div class="card-body">
                    <span class="badge-tag <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    <h3 class="card-title"><?php echo htmlspecialchars($row['Activity_Name']); ?></h3>
                    <div class="progress-container">
                        <div class="progress-bar-bg">
                            <div class="progress-fill fill-blue" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                        <div class="fund-stats">
                            <span style="color:#2563eb;">RM <?php echo number_format($raised); ?></span>
                            <span style="color:#999;">Goal: RM <?php echo number_format($target); ?></span>
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
                // --- Story 图片处理逻辑 (支持 JSON 和 字符串) ---
                $img = 'images/default_story.jpg';
                if (!empty($row['Story_Image'])) {
                    $decoded = json_decode($row['Story_Image'], true);
                    if (is_array($decoded) && !empty($decoded)) {
                        $img = $decoded[0]; // JSON 数组取第一张
                    } else {
                        $img = $row['Story_Image']; // 直接路径
                    }
                }
            ?>
            <div class="card-item">
                <div class="card-img-wrap">
                    <img src="<?php echo htmlspecialchars($img); ?>" 
                         class="card-img" 
                         alt="Story"
                         onerror="this.src='images/default_story.jpg'">
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
            <p>We couldn't find anything matching "<?php echo htmlspecialchars($search_query); ?>".<br>Try searching by title or keywords.</p>
            <br>
            <a href="Homepage.php" class="btn-view btn-case" style="max-width: 200px; margin: 0 auto;">Back to Home</a>
        </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>

</body>
</html>