<?php
include 'dataconnection.php';
// include 'header_function.php'; // 如果需要

// 1. 安全检查：获取 ID
if (!isset($_GET['case_id']) || !is_numeric($_GET['case_id'])) {
    echo "<script>alert('Invalid Case ID'); window.location.href='Special_Case_Page.php';</script>";
    exit();
}

$case_id = intval($_GET['case_id']);

// 2. 数据库查询
$stmt = $conn->prepare("SELECT * FROM special_case WHERE Case_ID = ?");
$stmt->bind_param("i", $case_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<script>alert('Case not found'); window.location.href='Special_Case_Page.php';</script>";
    exit();
}

$case = $result->fetch_assoc();

// 3. 数据处理逻辑
// --- 进度条 ---
$target = $case['Target_Amount'];
$raised = $case['Raised_Amount'];
$progress = ($target > 0) ? min(($raised / $target) * 100, 100) : 0;

// --- 图片处理 (JSON 解码) ---
$defaultImage = 'images/case-default.jpg';
$images = [];
$dbImage = isset($case['Case_Images']) ? $case['Case_Images'] : '';

if (!empty($dbImage)) {
    $decoded = json_decode($dbImage, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $images = $decoded; // 这是一个数组
    } else {
        $images[] = $dbImage; // 如果不是JSON，就当单张图片处理
    }
} else {
    $images[] = $defaultImage;
}
$mainImage = $images[0]; // 默认显示第一张

// --- 状态判断 ---
// 如果状态是 Completed 或者 已筹满，显示 Completed
$isCompleted = ($case['Case_Status'] === 'Completed') || ($progress >= 100);
$currentDate = date('Y-m-d');
$endDate = $case['End_Date'];

$statusLabel = "Active";
$statusColor = "#e53935"; // 默认红色

if ($isCompleted) {
    $statusLabel = "Completed";
    $statusColor = "#2e7d32"; // 绿色
} elseif ($endDate && $currentDate > $endDate) {
    $statusLabel = "Ended";
    $statusColor = "#757575"; // 灰色
}

// --- 紧急程度颜色 ---
$urgency = ucfirst($case['Urgency']); // 首字母大写
$urgencyColor = '#757575';
if (strtolower($urgency) == 'high') $urgencyColor = '#d32f2f';
if (strtolower($urgency) == 'medium') $urgencyColor = '#f57c00';

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

        /* 布局容器 */
        .detail-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 2fr 1fr; /* 左侧占2份，右侧占1份 */
            gap: 40px;
        }

        /* --- 左侧内容区 --- */
        .content-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 30px;
        }

        /* 图片画廊 */
        .gallery-container {
            margin-bottom: 30px;
        }
        .main-image {
            width: 100%;
            height: 450px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: 0.3s;
        }
        .thumbnail-row {
            display: flex;
            gap: 10px;
            overflow-x: auto;
        }
        .thumbnail {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            opacity: 0.6;
            transition: 0.3s;
            border: 2px solid transparent;
        }
        .thumbnail:hover, .thumbnail.active {
            opacity: 1;
            border-color: var(--primary-red);
        }

        .case-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .meta-tags {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .tag {
            background: #eee;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
            color: #555;
        }
        .tag.status-badge {
            background: <?php echo $statusColor; ?>;
            color: white;
            font-weight: bold;
        }
        .tag.urgency-badge {
            border: 1px solid <?php echo $urgencyColor; ?>;
            color: <?php echo $urgencyColor; ?>;
            background: #fff;
            font-weight: 600;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin: 30px 0 15px;
            border-left: 4px solid var(--primary-red);
            padding-left: 10px;
            color: #333;
        }

        .description-text {
            line-height: 1.8;
            color: #555;
            font-size: 16px;
            white-space: pre-line; /* 保留数据库里的换行 */
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            background: #fff5f5;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .info-item label {
            display: block;
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            font-weight: bold;
        }
        .info-item span {
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }

        /* --- 右侧侧边栏 (Sticky) --- */
        .sidebar-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            position: sticky;
            top: 100px;
            height: fit-content;
            border-top: 5px solid var(--primary-red);
        }

        .progress-section { margin-bottom: 25px; }
        .progress-bar-bg {
            height: 12px;
            background: #eee;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff8a80, var(--primary-red));
            width: <?php echo $progress; ?>%;
            border-radius: 6px;
        }
        .money-stats {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .raised-amount {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary-red);
        }
        .goal-amount {
            font-size: 14px;
            color: #777;
        }

        .btn-donate-large {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 15px;
            background: var(--primary-red);
            color: white;
            border-radius: 8px;
            font-weight: bold;
            font-size: 18px;
            text-decoration: none;
            transition: 0.3s;
            margin-bottom: 15px;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(229, 57, 53, 0.4);
        }
        .btn-donate-large:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
        }
        
        .btn-disabled {
            background: #bdbdbd;
            cursor: not-allowed;
            box-shadow: none;
        }
        .btn-disabled:hover {
            transform: none;
            background: #bdbdbd;
        }

        .organizer-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .org-avatar {
            width: 50px;
            height: 50px;
            background: #ffebee;
            color: var(--primary-red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

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
        <a href="Special_case Page.php" style="display:inline-block; margin-bottom:20px; color:#666; text-decoration:none;">
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

            <h3 class="section-title">Case Details</h3>
            <div class="description-text">
                <?php echo htmlspecialchars($case['Case_Description']); ?>
            </div>

            <h3 class="section-title">Additional Information</h3>
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
                    <span><?php echo htmlspecialchars($case['Case_Address1']) . ', ' . htmlspecialchars($case['Case_City']) . ' ' . htmlspecialchars($case['Case_PostalCode']); ?></span>
                </div>
                <div class="info-item">
                    <label>Contact Person</label>
                    <span><?php echo htmlspecialchars($case['Contact_Name']); ?></span>
                </div>
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
    // 切换图片
    function changeImage(src, element) {
        document.getElementById('mainDisplay').src = src;
        document.querySelectorAll('.thumbnail').forEach(el => el.classList.remove('active'));
        element.classList.add('active');
    }

    // 放大查看图片
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

    // 检查登录状态
    function checkLogin(event) {
        const isLoggedIn = <?php echo isset($_SESSION['Donor_ID']) || isset($_SESSION['donor_id']) ? 'true' : 'false'; ?>;

        if (!isLoggedIn) {
            event.preventDefault();
            Swal.fire({
                title: 'Login Required',
                text: "You need to login to make a donation.",
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