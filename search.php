<?php
// 1. 引入数据库连接和头部
include 'dataconnection.php';
include 'header_function.php'; // 包含 session_start
include 'header_UI_2.php';     // 包含导航栏

$search_query = "";
$activity_results = [];
$case_results = [];
$story_results = [];

// 2. 检查是否有搜索关键词
if (isset($_GET['query'])) {
    $search_query = trim($_GET['query']); // 获取用户输入，例如 "Old"

    // ✅ 关键步骤：把用户输入变成 SQL LIKE 的格式
    // 如果用户搜 "Old"，这里就会变成 "%Old%"
    // 意思就是：SELECT * FROM ... WHERE ... LIKE '%Old%'
    $param = "%" . $search_query . "%";

    // ---------------------------------------------------
    // 🔍 1. 搜索 Activity 表 (名字 或 详情)
    // ---------------------------------------------------
    $sql_act = "SELECT * FROM activity 
                WHERE Activity_Name LIKE ? 
                OR Activity_Details LIKE ?";
    
    if ($stmt = $conn->prepare($sql_act)) {
        // 绑定两个参数 (因为有两个问号)
        $stmt->bind_param("ss", $param, $param);
        $stmt->execute();
        $activity_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // ---------------------------------------------------
    // 🔍 2. 搜索 Special Case 表 (标题 或 描述)
    // ---------------------------------------------------
    $sql_case = "SELECT * FROM special_case 
                 WHERE Case_Title LIKE ? 
                 OR Case_Description LIKE ?";

    if ($stmt = $conn->prepare($sql_case)) {
        $stmt->bind_param("ss", $param, $param);
        $stmt->execute();
        $case_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // ---------------------------------------------------
    // 🔍 3. 搜索 Story 表 (描述)
    // ---------------------------------------------------
    $sql_story = "SELECT * FROM story 
                  WHERE Donor_Description LIKE ?";

    if ($stmt = $conn->prepare($sql_story)) {
        $stmt->bind_param("s", $param);
        $stmt->execute();
        $story_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results</title>
    <style>
        /* 简单的结果页面样式，您可以根据 donor_design.css 调整 */
        body { background-color: #FFF5E4; font-family: Arial, sans-serif; color: #4A4A4A; }
        .container { padding: 40px; max-width: 1000px; margin: 0 auto; }
        
        .section-title { 
            color: #F28585; 
            border-bottom: 2px solid #F28585; 
            padding-bottom: 10px; 
            margin-top: 40px;
            font-size: 24px;
        }

        .result-card { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 15px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
            transition: transform 0.2s;
        }
        .result-card:hover { transform: translateY(-3px); }

        .result-card h3 { margin-top: 0; color: #333; font-size: 18px; }
        .result-card p { color: #666; font-size: 14px; line-height: 1.5; }
        
        .btn-link { 
            display: inline-block; 
            margin-top: 10px; 
            color: #F28585; 
            text-decoration: none; 
            font-weight: bold; 
        }
        .btn-link:hover { text-decoration: underline; }

        .no-results { text-align: center; color: #888; margin-top: 50px; font-size: 18px; }
    </style>
</head>
<body>

<div class="container">
    <h1>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h1>

    <?php if (!empty($activity_results)): ?>
        <h2 class="section-title">Activities</h2>
        <?php foreach ($activity_results as $row): ?>
            <div class="result-card">
                <h3><?php echo htmlspecialchars($row['Activity_Name'] ?? 'Unnamed Activity'); ?></h3>
                <p><?php echo htmlspecialchars(substr($row['Activity_Details'], 0, 150)) . '...'; ?></p>
                <a href="Activity_Details.php?id=<?php echo $row['Activity_ID']; ?>" class="btn-link">View Activity &rarr;</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($case_results)): ?>
        <h2 class="section-title">Special Cases</h2>
        <?php foreach ($case_results as $row): ?>
            <div class="result-card">
                <h3><?php echo htmlspecialchars($row['Case_Title']); ?></h3>
                <p><?php echo htmlspecialchars(substr($row['Case_Description'], 0, 150)) . '...'; ?></p>
                <a href="S_C_Payment_Page.php?case_id=<?php echo $row['Case_ID']; ?>" class="btn-link">Donate Now &rarr;</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($story_results)): ?>
        <h2 class="section-title">Stories</h2>
        <?php foreach ($story_results as $row): ?>
            <div class="result-card">
                <h3>Donation Story</h3>
                <p><?php echo htmlspecialchars(substr($row['Donor_Description'], 0, 150)) . '...'; ?></p>
                <a href="New&Story.php" class="btn-link">Read More &rarr;</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($activity_results) && empty($case_results) && empty($story_results)): ?>
        <div class="no-results">
            <p>No results found matching your search.</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>