<?php
include 'dataconnection.php';
include 'header_function.php'; 

// 定义一个辅助函数来显示错误并跳转 (使用 SweetAlert)
function showErrorAndRedirect($title, $text, $redirectUrl) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: "error",
                title: "' . addslashes($title) . '",
                text: "' . addslashes($text) . '",
                confirmButtonColor: "#e53935"
            }).then(() => {
                window.location.href = "' . $redirectUrl . '";
            });
        </script>
    </body>
    </html>';
    exit();
}

// 1. 获取 ID
if (!isset($_GET['case_id']) || !is_numeric($_GET['case_id'])) {
    showErrorAndRedirect('Invalid Request', 'Invalid Case ID provided.', 'Special_Case Page.php');
}

$case_id = intval($_GET['case_id']);

// 2. 处理评论提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['donor_id'])) {
        // 后端校验：未登录试图评论 (虽然前端有JS拦截，这里做双重保险)
        // 使用 Session 存储错误信息，或者直接跳转登录
        header("Location: donor_login.php");
        exit();
    } else {
        $comment = trim($_POST['comment']);
        $donor_id = $_SESSION['donor_id'];
        
        if (!empty($comment)) {
            $stmt = $conn->prepare("INSERT INTO case_comments (Case_ID, Donor_ID, Comment_Text) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $case_id, $donor_id, $comment);
            if ($stmt->execute()) {
                // 刷新页面以显示新评论
                header("Location: Special_Case_Detail.php?case_id=" . $case_id);
                exit();
            }
            $stmt->close();
        }
    }
}

// 3. 查询案例详情
$stmt = $conn->prepare("SELECT * FROM special_case WHERE Case_ID = ?");
$stmt->bind_param("i", $case_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    showErrorAndRedirect('Not Found', 'The requested case could not be found.', 'Special_Case Page.php');
}

$case = $result->fetch_assoc();

// 4. 查询捐赠者列表 (只显示成功的支付)
$donor_query = "SELECT o.Order_Name, o.Order_Amount, o.Order_Created_At 
                FROM orders o 
                WHERE o.Case_ID = ? AND o.Order_PaymentStatus = 'Success' 
                ORDER BY o.Order_Created_At DESC LIMIT 20";
$stmt_donors = $conn->prepare($donor_query);
$stmt_donors->bind_param("i", $case_id);
$stmt_donors->execute();
$donors_result = $stmt_donors->get_result();

// 5. 查询评论列表
$comment_query = "SELECT c.*, d.Donor_Name, d.Donor_ProfilePicture 
                  FROM case_comments c 
                  JOIN donor d ON c.Donor_ID = d.Donor_ID 
                  WHERE c.Case_ID = ? 
                  ORDER BY c.Created_At DESC";
$stmt_comments = $conn->prepare($comment_query);
$stmt_comments->bind_param("i", $case_id);
$stmt_comments->execute();
$comments_result = $stmt_comments->get_result();

// 数据处理逻辑 (进度条、图片等)
$target = $case['Target_Amount'];
$raised = $case['Raised_Amount'];
$progress = ($target > 0) ? min(($raised / $target) * 100, 100) : 0;

$defaultImage = 'images/case-default.jpg';
$images = [];
$dbImage = isset($case['Case_Images']) ? $case['Case_Images'] : '';

if (!empty($dbImage)) {
    $decoded = json_decode($dbImage, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $images = $decoded;
    } else {
        $images[] = $dbImage;
    }
} else {
    $images[] = $defaultImage;
}
$mainImage = $images[0];

$isCompleted = ($case['Case_Status'] === 'Completed') || ($progress >= 100);
$statusLabel = $isCompleted ? "Completed" : "Active";
$statusColor = $isCompleted ? "#2e7d32" : "#e53935";

$urgency = ucfirst($case['Urgency']);
$urgencyColor = ($urgency == 'High' || $urgency == 'Critical') ? '#d32f2f' : '#757575';

include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($case['Case_Title']); ?> - Love Bridge</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #e53935;
            --dark-red: #c62828;
            --light-gray: #f5f5f5;
            --text-color: #333;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f9f9f9;
            color: var(--text-color);
        }

        .detail-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 30px;
        }

        .gallery-container { margin-bottom: 30px; }
        .main-image { width: 100%; height: 450px; object-fit: cover; border-radius: 10px; margin-bottom: 10px; cursor: pointer; transition: 0.3s; }
        .thumbnail-row { display: flex; gap: 10px; overflow-x: auto; }
        .thumbnail { width: 80px; height: 60px; object-fit: cover; border-radius: 5px; cursor: pointer; opacity: 0.6; transition: 0.3s; border: 2px solid transparent; }
        .thumbnail:hover, .thumbnail.active { opacity: 1; border-color: var(--primary-red); }

        .case-title { font-size: 32px; font-weight: 800; margin-bottom: 15px; line-height: 1.2; }

        .meta-tags { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .tag { background: #eee; padding: 5px 12px; border-radius: 20px; font-size: 13px; display: flex; align-items: center; gap: 5px; color: #555; }
        .tag.status-badge { background: <?php echo $statusColor; ?>; color: white; font-weight: bold; }
        .tag.urgency-badge { border: 1px solid <?php echo $urgencyColor; ?>; color: <?php echo $urgencyColor; ?>; background: #fff; font-weight: 600; }

        .section-title { font-size: 20px; font-weight: 700; margin: 30px 0 15px; border-left: 4px solid var(--primary-red); padding-left: 10px; color: #333; }
        .description-text { line-height: 1.8; color: #555; font-size: 16px; white-space: pre-line; }

        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; background: #fff5f5; padding: 20px; border-radius: 10px; margin-top: 20px; }
        .info-item label { display: block; font-size: 12px; color: #888; text-transform: uppercase; font-weight: bold; }
        .info-item span { font-weight: 600; color: #333; font-size: 15px; }

        /* Sidebar */
        .sidebar-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); position: sticky; top: 100px; height: fit-content; border-top: 5px solid var(--primary-red); }
        .progress-section { margin-bottom: 25px; }
        .progress-bar-bg { height: 12px; background: #eee; border-radius: 6px; overflow: hidden; margin-bottom: 8px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #ff8a80, var(--primary-red)); width: <?php echo $progress; ?>%; border-radius: 6px; }
        .money-stats { display: flex; justify-content: space-between; align-items: flex-end; }
        .raised-amount { font-size: 28px; font-weight: 800; color: var(--primary-red); }
        .goal-amount { font-size: 14px; color: #777; }

        .btn-donate-large { display: flex; justify-content: center; align-items: center; width: 100%; padding: 15px; background: var(--primary-red); color: white; border-radius: 8px; font-weight: bold; font-size: 18px; text-decoration: none; transition: 0.3s; margin-bottom: 15px; gap: 10px; box-shadow: 0 4px 15px rgba(229, 57, 53, 0.4); }
        .btn-donate-large:hover { background: var(--dark-red); transform: translateY(-2px); }
        .btn-disabled { background: #bdbdbd; cursor: not-allowed; box-shadow: none; }
        .btn-disabled:hover { transform: none; background: #bdbdbd; }

        .organizer-info { display: flex; align-items: center; gap: 15px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
        .org-avatar { width: 50px; height: 50px; background: #ffebee; color: var(--primary-red); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; }

        /* Medical Report Button */
        .medical-report-btn {
            display: inline-flex; align-items: center; gap: 10px;
            background: white; border: 2px solid var(--primary-red); color: var(--primary-red);
            padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none;
            transition: 0.3s; margin-top: 10px;
        }
        .medical-report-btn:hover { background: var(--primary-red); color: white; }

        /* Tabs */
        .tabs { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 20px; }
        .tab-btn { padding: 10px 20px; cursor: pointer; font-weight: 600; color: #777; border-bottom: 3px solid transparent; transition: 0.3s; }
        .tab-btn.active { color: var(--primary-red); border-bottom-color: var(--primary-red); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Donor List */
        .donor-item { display: flex; align-items: center; gap: 15px; padding: 15px; border-bottom: 1px solid #f0f0f0; }
        .donor-info h4 { font-size: 14px; margin: 0; color: #333; }
        .donor-info span { font-size: 12px; color: #888; }
        .donor-amount { margin-left: auto; font-weight: bold; color: var(--primary-red); }

        /* Comments */
        .comment-box { margin-bottom: 20px; }
        .comment-input { width: 100%; padding: 15px; border: 1px solid #ddd; border-radius: 8px; resize: none; font-family: inherit; margin-bottom: 10px; }
        .comment-submit { background: var(--primary-red); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .comment-item { display: flex; gap: 15px; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .comment-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .comment-body h4 { font-size: 14px; margin: 0 0 5px; color: #333; }
        .comment-date { font-size: 11px; color: #999; margin-left: 10px; font-weight: normal; }
        .comment-text { font-size: 14px; color: #555; line-height: 1.5; }

        @media (max-width: 900px) {
            .detail-container { grid-template-columns: 1fr; }
            .sidebar-card { position: static; margin-bottom: 30px; order: -1; }
            .main-image { height: 300px; }
        }
    </style>
</head>
<body>

<div class="detail-container">
    
    <div class="left-col">
        <a href="Special_Case Page.php" style="display:inline-block; margin-bottom:20px; color:#666; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Special Cases
        </a>

        <div class="content-card">
            <div class="gallery-container">
                <img src="<?php echo htmlspecialchars($mainImage); ?>" id="mainDisplay" class="main-image" onclick="viewImage(this.src)">
                
                <?php if(count($images) > 1): ?>
                <div class="thumbnail-row">
                    <?php foreach($images as $idx => $img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" 
                             class="thumbnail <?php echo $idx===0?'active':''; ?>" 
                             onclick="changeImage(this.src, this)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <h1 class="case-title"><?php echo htmlspecialchars($case['Case_Title']); ?></h1>

            <div class="meta-tags">
                <div class="tag status-badge"><?php echo $statusLabel; ?></div>
                <div class="tag urgency-badge"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($urgency); ?></div>
                <div class="tag"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($case['Case_Category']); ?></div>
                <div class="tag"><i class="far fa-calendar-plus"></i> Posted: <?php echo date('d M Y', strtotime($case['Created_At'])); ?></div>
            </div>

            <h3 class="section-title">Case Story</h3>
            <div class="description-text">
                <?php echo htmlspecialchars($case['Case_Description']); ?>
            </div>

            <?php if (!empty($case['Case_Medical_Report'])): ?>
            <h3 class="section-title">Medical Documents</h3>
            <div style="background: #f0f7ff; padding: 15px; border-radius: 8px; border-left: 4px solid #2196f3;">
                <p style="margin: 0 0 10px; color: #0d47a1; font-size: 14px;">
                    <i class="fas fa-file-medical"></i> Verified Medical Report available for this case.
                </p>
                <a href="<?php echo htmlspecialchars($case['Case_Medical_Report']); ?>" target="_blank" class="medical-report-btn">
                    <i class="fas fa-download"></i> View Medical Report
                </a>
            </div>
            <?php endif; ?>

            <h3 class="section-title">Case Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Category</label>
                    <span><?php echo htmlspecialchars($case['Case_Category']); ?></span>
                </div>
                <div class="info-item">
                    <label>Posted Date</label>
                    <span><?php echo date('d M Y', strtotime($case['Created_At'])); ?></span>
                </div>
                <div class="info-item">
                    <label>Deadline</label>
                    <span><?php echo isset($case['End_Date']) ? date('d M Y', strtotime($case['End_Date'])) : 'Ongoing'; ?></span>
                </div>
                <div class="info-item">
                    <label>Donors</label>
                    <span><?php echo number_format($case['Donor_Count']); ?> People Supported</span>
                </div>
                <div class="info-item">
                    <label>Full Address</label>
                    <span><?php echo htmlspecialchars($case['Case_Address1']) . ', ' . htmlspecialchars($case['Case_City']); ?></span>
                </div>
                <div class="info-item">
                    <label>Contact Person</label>
                    <span><?php echo htmlspecialchars($case['Contact_Name']); ?></span>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="tabs">
                <div class="tab-btn active" onclick="switchTab('donors')">Recent Donors</div>
                <div class="tab-btn" onclick="switchTab('comments')">Comments (<?php echo $comments_result->num_rows; ?>)</div>
            </div>

            <div id="donors" class="tab-content active">
                <?php if ($donors_result->num_rows > 0): ?>
                    <?php while($donor = $donors_result->fetch_assoc()): ?>
                    <div class="donor-item">
                        <div class="donor-info">
                            <h4><?php echo htmlspecialchars($donor['Order_Name']); ?></h4>
                            <span><?php echo date('M d, Y h:i A', strtotime($donor['Order_Created_At'])); ?></span>
                        </div>
                        <div class="donor-amount">RM <?php echo number_format($donor['Order_Amount'], 2); ?></div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align:center; color:#888; padding:20px;">Be the first to donate!</p>
                <?php endif; ?>
            </div>

            <div id="comments" class="tab-content">
                <div class="comment-box">
                    <form method="POST" action="" onsubmit="return checkLogin(event)">
                        <textarea name="comment" class="comment-input" rows="3" placeholder="Write some words of encouragement..." required></textarea>
                        <button type="submit" name="submit_comment" class="comment-submit">Post Comment</button>
                    </form>
                </div>

                <?php if ($comments_result->num_rows > 0): ?>
                    <?php while($comment = $comments_result->fetch_assoc()): 
                        $profilePic = !empty($comment['Donor_ProfilePicture']) ? $comment['Donor_ProfilePicture'] : 'images/default-avatar.png';
                    ?>
                    <div class="comment-item">
                        <img src="<?php echo htmlspecialchars($profilePic); ?>" class="comment-avatar" onerror="this.src='images/default-avatar.png'">
                        <div class="comment-body">
                            <h4>
                                <?php echo htmlspecialchars($comment['Donor_Name']); ?>
                                <span class="comment-date"><?php echo date('M d, Y', strtotime($comment['Created_At'])); ?></span>
                            </h4>
                            <p class="comment-text"><?php echo nl2br(htmlspecialchars($comment['Comment_Text'])); ?></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align:center; color:#888; padding:20px;">No comments yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="right-col">
        <div class="sidebar-card">
            <div class="money-stats">
                <div class="raised-amount">RM <?php echo number_format($raised, 2); ?></div>
                <div class="goal-amount">raised of RM <?php echo number_format($target); ?></div>
            </div>

            <div class="progress-section">
                <div class="progress-bar-bg">
                    <div class="progress-fill"></div>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px; color:#666;">
                    <span><?php echo number_format($progress, 0); ?>% Funded</span>
                    <span><i class="fas fa-hands-helping"></i> Help them now</span>
                </div>
            </div>

            <?php if (!$isCompleted): ?>
                <a href="S_C_Payment_Page.php?case_id=<?php echo $case_id; ?>" 
                   class="btn-donate-large"
                   onclick="return checkLogin(event)">
                    <i class="fas fa-heart"></i> Donate Now
                </a>
            <?php else: ?>
                <button class="btn-donate-large btn-disabled" disabled>
                    <i class="fas fa-check-circle"></i> Goal Reached / Completed
                </button>
            <?php endif; ?>

            <div class="organizer-info">
                <div class="org-avatar"><i class="fas fa-building"></i></div>
                <div>
                    <div style="font-size:12px; color:#888;">Organized by</div>
                    <div style="font-weight:bold;"><?php echo htmlspecialchars($case['Case_Organizer']); ?></div>
                    <div style="font-size:12px; color:#666;"><?php echo htmlspecialchars($case['Contact_Email']); ?></div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>

<script>
    // Tab Switching
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        
        document.getElementById(tabName).classList.add('active');
        // Find the button that was clicked (simple way)
        const btns = document.querySelectorAll('.tab-btn');
        if(tabName === 'donors') btns[0].classList.add('active');
        else btns[1].classList.add('active');
    }

    // Change Image
    function changeImage(src, element) {
        document.getElementById('mainDisplay').src = src;
        document.querySelectorAll('.thumbnail').forEach(el => el.classList.remove('active'));
        element.classList.add('active');
    }

    // View Image
    function viewImage(src) {
        Swal.fire({
            imageUrl: src,
            width: 800,
            showConfirmButton: false,
            showCloseButton: true,
            padding: 0,
            background: 'transparent',
            backdrop: 'rgba(0,0,0,0.9)'
        });
    }

    // Login Check
    function checkLogin(event) {
        const isLoggedIn = <?php echo isset($_SESSION['Donor_ID']) || isset($_SESSION['donor_id']) ? 'true' : 'false'; ?>;

        if (!isLoggedIn) {
            event.preventDefault();
            Swal.fire({
                title: 'Login Required',
                text: "You need to login to perform this action.",
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