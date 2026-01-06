<?php
// 1. 包含数据库连接和 Header
include 'dataconnection.php'; // 确保路径对
include 'admin_header.php';  // 包含你刚才修改过的 header

// 2. 获取搜索关键词
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$searchTerm = "%" . $keyword . "%";

// 初始化结果数组
$donorResults = [];
$donationResults = [];
$storyResults = [];

if ($keyword) {
    // --- 搜索 Donors (用户表) ---
    // 假设表名是 users，字段有 id, full_name, email, phone
    $stmt = $conn->prepare("SELECT * FROM users WHERE full_name LIKE ? OR email LIKE ? OR phone LIKE ?");
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $donorResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // --- 搜索 Donations (捐款记录) ---
    // 假设表名是 donations，字段有 donation_id, donor_name, amount, reference_no
    $stmt = $conn->prepare("SELECT * FROM donations WHERE reference_no LIKE ? OR donor_name LIKE ?");
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $donationResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // --- 搜索 Stories/Activities ---
    // 假设表名是 stories，字段有 title, description
    $stmt = $conn->prepare("SELECT * FROM stories WHERE title LIKE ? OR description LIKE ?");
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $storyResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Search Results - Love Bridge</title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        .search-container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .result-section { margin-bottom: 30px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .section-title { font-size: 18px; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px; color: #333; }
        .result-item { padding: 10px 0; border-bottom: 1px solid #f9f9f9; display: flex; justify-content: space-between; align-items: center; }
        .result-item:last-child { border-bottom: none; }
        .result-item h4 { margin: 0 0 5px; font-size: 16px; color: #0056b3; }
        .result-item p { margin: 0; color: #666; font-size: 14px; }
        .btn-view { padding: 5px 15px; background: #e0e0e0; color: #333; text-decoration: none; border-radius: 4px; font-size: 12px; }
        .btn-view:hover { background: #d0d0d0; }
        .no-results { color: #888; font-style: italic; }
    </style>
</head>
<body>

<div class="main-content">
    <div class="search-container">
        <h2>Search Results for: "<?php echo htmlspecialchars($keyword); ?>"</h2>
        
        <div class="result-section">
            <h3 class="section-title">Donors Found (<?php echo count($donorResults); ?>)</h3>
            <?php if (count($donorResults) > 0): ?>
                <?php foreach ($donorResults as $donor): ?>
                    <div class="result-item">
                        <div>
                            <h4><?php echo htmlspecialchars($donor['full_name']); ?></h4>
                            <p>Email: <?php echo htmlspecialchars($donor['email']); ?> | Phone: <?php echo htmlspecialchars($donor['phone']); ?></p>
                        </div>
                        <a href="admin_view_donor.php?id=<?php echo $donor['id']; ?>" class="btn-view">View Profile</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-results">No donors found matching criteria.</p>
            <?php endif; ?>
        </div>

        <div class="result-section">
            <h3 class="section-title">Donations Found (<?php echo count($donationResults); ?>)</h3>
            <?php if (count($donationResults) > 0): ?>
                <?php foreach ($donationResults as $donation): ?>
                    <div class="result-item">
                        <div>
                            <h4>RM <?php echo htmlspecialchars($donation['amount']); ?></h4>
                            <p>Ref: <?php echo htmlspecialchars($donation['reference_no']); ?> | Date: <?php echo $donation['created_at']; ?></p>
                        </div>
                        <a href="admin_view_donation.php?id=<?php echo $donation['donation_id']; ?>" class="btn-view">View Receipt</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-results">No donation records found.</p>
            <?php endif; ?>
        </div>

        <div class="result-section">
            <h3 class="section-title">Stories/Activities Found (<?php echo count($storyResults); ?>)</h3>
            <?php if (count($storyResults) > 0): ?>
                <?php foreach ($storyResults as $story): ?>
                    <div class="result-item">
                        <div>
                            <h4><?php echo htmlspecialchars($story['title']); ?></h4>
                            <p><?php echo substr(htmlspecialchars(strip_tags($story['description'])), 0, 100); ?>...</p>
                        </div>
                        <a href="admin_edit_story.php?id=<?php echo $story['id']; ?>" class="btn-view">Edit Story</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-results">No stories found.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>