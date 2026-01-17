<?php
// Branch_Selection.php
session_start();
include 'dataconnection.php';

// 1. 登录检查
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

// ==========================================
// 2. 初始化流程数据 (Step 1)
// ==========================================
// 如果是从 Homepage 或其它地方直接进入，初始化捐赠数据 Session
if (!isset($_SESSION['donation_data'])) {
    $_SESSION['donation_data'] = [
        'amount' => '',
        'type' => 'one-time',
        'tax_receipt' => '0',
        'case_id' => '',
        'activity_id' => '',
        'branch_id' => ''
    ];
}

// 检查是否有通过 GET 传来的特殊个案或活动 ID (从详情页点进来的情况)
if (isset($_GET['case_id'])) {
    $_SESSION['donation_data']['case_id'] = $_GET['case_id'];
    $_SESSION['donation_data']['activity_id'] = ''; // 清除活动ID
}
if (isset($_GET['activity_id'])) {
    $_SESSION['donation_data']['activity_id'] = $_GET['activity_id'];
    $_SESSION['donation_data']['case_id'] = ''; // 清除个案ID
}

$pre_selected_branch = $_SESSION['donation_data']['branch_id'] ?? '';

// ==========================================
// 3. 获取总部 (HQ) 数据
// ==========================================
$hq_sql = "SELECT HQ_Name, HQ_Description, HQ_Image, Headquarters_State FROM headquarters WHERE HQ_ID = 1 LIMIT 1";
$hq_result = $conn->query($hq_sql);
$hq_data = $hq_result->fetch_assoc();

$hq_branch = null;
if ($hq_data) {
    $hq_branch = [
        'Branch_ID' => '0', // 总部 ID 约定为 0
        'Branch_Name' => $hq_data['HQ_Name'],
        'Branch_Type' => 'Headquarters', 
        'Branch_Description' => $hq_data['HQ_Description'],
        'Branch_City' => $hq_data['Headquarters_State'], 
        'decoded_images' => [!empty($hq_data['HQ_Image']) ? $hq_data['HQ_Image'] : 'images/hero_3.jpg']
    ];
}

// ==========================================
// 4. 获取其他分行 (Branches) 数据
// ==========================================
$sql = "SELECT Branch_ID, Branch_Name, Branch_Type, Branch_Description, Branch_City, Branch_Images 
        FROM branch 
        WHERE Is_Deleted = 0 AND Branch_OperationalStatus = 'Open'
        ORDER BY Branch_ID ASC";
$result = $conn->query($sql);
$other_branches = [];
while($row = $result->fetch_assoc()) {
    $row['decoded_images'] = !empty($row['Branch_Images']) ? json_decode($row['Branch_Images'], true) : ['images/hero_3.jpg'];
    $other_branches[] = $row;
}

include 'header_UI.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* Hero Banner */
    .hero-wrap {
        height: 350px;
        position: relative;
        background-image: url('images/hero_2.jpg');
        background-size: cover;
        background-position: center;
        display: flex; align-items: center; justify-content: center; text-align: center;
    }
    .hero-wrap .overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); }
    .hero-content { position: relative; z-index: 2; max-width: 800px; }
    .hero-content h1 { font-family: 'Segoe UI', sans-serif; color: #fff; font-size: 3.5rem; margin-bottom: 10px; }
    .hero-content p { font-size: 1.2rem; color: rgba(255, 255, 255, 0.9); }

    .branch-container {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .branch-option {
        display: block;
        position: relative;
        cursor: pointer;
        margin: 0;
    }
    .branch-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }

    .branch-card {
        background: #fff;
        border-radius: 12px;
        border: 2px solid #eee;
        overflow: hidden;
        display: flex;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        height: 280px; 
    }

    .branch-option:hover .branch-card {
        border-color: #ffcccc;
        box-shadow: 0 8px 25px rgba(220, 38, 38, 0.15);
        transform: translateY(-2px);
    }
    
    .branch-option input[type="radio"]:checked + .branch-card {
        border-color: #dc2626;
        background-color: #fffafa;
    }

    .branch-carousel {
        width: 40%; 
        position: relative;
        overflow: hidden;
        background: #f9f9f9;
    }
    .carousel-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        position: absolute;
        top: 0;
        left: 0;
        opacity: 0;
        transition: opacity 1s ease-in-out; 
    }
    .carousel-img.active {
        opacity: 1;
        z-index: 1;
    }
    .default-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 1;
    }

    .branch-info {
        width: 60%;
        padding: 25px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .info-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }
    .branch-title { font-size: 1.5rem; font-weight: 700; color: #333; margin: 0; }
    .branch-type { 
        background: #e0f2fe; color: #0284c7; 
        padding: 5px 12px; border-radius: 20px; 
        font-size: 0.8rem; font-weight: 600; 
        white-space: nowrap;
    }
    .type-hq { background: #fef3c7; color: #d97706; }

    .branch-desc {
        color: #666; font-size: 0.95rem; line-height: 1.6;
        flex-grow: 1;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 4; 
        -webkit-box-orient: vertical; 
        margin-bottom: 15px;
    }

    .branch-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid #eee;
        padding-top: 15px;
    }
    .branch-location { font-size: 0.9rem; color: #888; display: flex; align-items: center; gap: 5px; }
    
    .btn-group-actions {
        display: flex;
        gap: 10px;
    }

    .select-btn {
        background: #fff;
        border: 2px solid #ccc;
        color: #555;
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 600;
        transition: 0.3s;
        text-align: center;
        cursor: pointer;
    }

    .details-btn {
        background: #f8f9fa;
        border: 1px solid #ddd;
        color: #666;
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 600;
        transition: 0.3s;
        text-decoration: none !important;
        cursor: pointer;
    }

    .details-btn:hover {
        background: #e9ecef;
        color: #dc2626;
        border-color: #dc2626;
    }
    
    .branch-option:hover .select-btn {
        background: #dc2626;
        border-color: #dc2626;
        color: white;
    }

    /* ⭐ [新增] 顶部橙色 Previous 按钮样式 ⭐ */
    .top-nav-container {
        margin-bottom: 25px;
        text-align: left;
    }
    .btn-orange-back {
        display: inline-flex;
        align-items: center;
        gap: 12px;                  /* 增加图标和文字之间的间距 */
        background-color: #f79c34; 
        color: white !important;
        padding: 15px 35px;         /* ⭐ 加大数值：15px是上下，35px是左右 */
        border-radius: 10px;        /* 稍微加大圆角，配合大按钮更协调 */
        font-weight: 700;           /* 加粗字体 */
        font-size: 1.2rem;          /* ⭐ 加大字体：从默认大小增加到 1.2rem */
        text-decoration: none !important;
        transition: all 0.3s ease;
        box-shadow: 0 4px 8px rgba(247, 156, 52, 0.3);
    }

    .btn-orange-back:hover {
        background-color: #e68a20;
        transform: translateX(-8px); /* 悬停时移动的距离也稍微加大 */
        box-shadow: 0 6px 15px rgba(247, 156, 52, 0.4);
    }
    
    /* 调整图标大小以适应大按钮 */
    .btn-orange-back i {
        font-size: 1.3rem; 
    }
    /* Responsive */
    @media (max-width: 768px) {
        .branch-card { flex-direction: column; height: auto; }
        .branch-carousel { width: 100%; height: 200px; }
        .branch-info { width: 100%; padding: 20px; }
    }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Choose Your Destination</h1>
        <p>Select the branch or fund you wish to support.</p>
    </div>
</div>

<?php 
    $current_step = 1; 
    $flow_type = 'standard'; 
    include 'stepper.php'; 
?>

<div class="site-section" style="padding: 4em 0;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <h2 class="text-center font-weight-bold mb-2" style="color: #111;">Where should your love land?</h2>
                <p class="text-center text-muted mb-5">Your donation will be directly allocated to the selected center's operating fund.</p>

                <form action="Payment_Page.php" method="POST" id="branchForm">
                    <div class="branch-container">

                        <?php if ($hq_branch): ?>
                        <label class="branch-option">
                            <input type="radio" name="branch_id" value="0" onclick="this.form.submit()">
                            <div class="branch-card">
                                <div class="branch-carousel">
                                    <img src="<?php echo htmlspecialchars($hq_branch['decoded_images'][0]); ?>" class="carousel-img active" alt="HQ">
                                </div>
                                <div class="branch-info">
                                    <div class="info-header">
                                        <h4 class="branch-title"><?php echo htmlspecialchars($hq_branch['Branch_Name']); ?></h4>
                                        <span class="branch-type" style="background:#fef3c7; color:#d97706;">Headquarters</span>
                                    </div>
                                    <p class="branch-desc"><?php echo htmlspecialchars($hq_branch['Branch_Description']); ?></p>
                                    <div class="branch-footer">
                                        <div class="branch-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($hq_branch['Branch_City']); ?></div>
                                        <div>
                                            <a href="Branch_Details.php?id=0" class="details-btn" onclick="event.stopPropagation();">Read More</a>
                                            <span class="select-btn">Support HQ <i class="fas fa-arrow-right ml-2"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </label>
                        <?php endif; ?>

                        <?php foreach ($other_branches as $row): ?>
                        <label class="branch-option">
                            <input type="radio" name="branch_id" value="<?php echo $row['Branch_ID']; ?>" onclick="this.form.submit()">
                            <div class="branch-card">
                                <div class="branch-carousel" id="carousel-<?php echo $row['Branch_ID']; ?>">
                                    <?php foreach ($row['decoded_images'] as $idx => $img): ?>
                                        <img src="<?php echo htmlspecialchars($img); ?>" class="carousel-img <?php echo $idx==0?'active':''; ?>">
                                    <?php endforeach; ?>
                                </div>
                                <div class="branch-info">
                                    <div class="info-header">
                                        <h4 class="branch-title"><?php echo htmlspecialchars($row['Branch_Name']); ?></h4>
                                        <span class="branch-type"><?php echo htmlspecialchars($row['Branch_Type']); ?></span>
                                    </div>
                                    <p class="branch-desc"><?php echo htmlspecialchars($row['Branch_Description']); ?></p>
                                    <div class="branch-footer">
                                        <div class="branch-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['Branch_City']); ?></div>
                                        <div>
                                            <a href="Branch_Details.php?id=<?php echo $row['Branch_ID']; ?>" class="details-btn" onclick="event.stopPropagation();">Read More</a>
                                            <span class="select-btn">Donate to Branch <i class="fas fa-arrow-right ml-2"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>

                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
    // 简单的轮播图逻辑
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.branch-carousel').forEach(carousel => {
            const imgs = carousel.querySelectorAll('.carousel-img');
            if (imgs.length > 1) {
                let cur = 0;
                setInterval(() => {
                    imgs[cur].classList.remove('active');
                    cur = (cur + 1) % imgs.length;
                    imgs[cur].classList.add('active');
                }, 4000);
            }
        });
    });
</script>

<?php include 'footer.php'; ?>