<?php
// Branch_Selection.php
session_start();
include 'dataconnection.php';

// 登录检查
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

// ==========================================
// 1. 接收上一页数据并存入 Session
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['donation_data'] = [
        'amount' => $_POST['amount'],
        'type' => $_POST['donation_type'],
        'tax_receipt' => $_POST['tax_receipt'],
        'case_id' => $_POST['case_id'] ?? '',
        'activity_id' => $_POST['activity_id'] ?? '',
        'branch_id' => $_SESSION['donation_data']['branch_id'] ?? ''
    ];
}

// 安全检查
if (empty($_SESSION['donation_data'])) {
    header("Location: Donate_Page.php");
    exit();
}

// 从 Session 获取之前选中的分行（如果有）
$pre_selected_branch = $_SESSION['donation_data']['branch_id'];

// ==========================================
// 2. [FIXED] 获取总部 (HQ) 数据
// ==========================================
// 直接从 headquarters 表读取，不再混淆 branch 表
$hq_sql = "SELECT HQ_Name, HQ_Description, HQ_Image, Headquarters_State 
           FROM headquarters 
           WHERE HQ_ID = 1 LIMIT 1";
$hq_result = $conn->query($hq_sql);
$hq_data = $hq_result->fetch_assoc();

$hq_branch = null;

if ($hq_data) {
    // 将 HQ 数据格式化为与 Branch 通用的结构，方便下方 HTML 调用
    $hq_branch = [
        'Branch_ID' => '', // HQ 的 Branch_ID 设为空，表示 General Fund
        'Branch_Name' => $hq_data['HQ_Name'],
        'Branch_Type' => 'Headquarters', // 显示标签
        'Branch_Description' => $hq_data['HQ_Description'],
        'Branch_City' => $hq_data['Headquarters_State'], // 对应数据库字段
        'decoded_images' => [] 
    ];

    // 处理图片：如果数据库有图片路径，放入数组；否则用默认图
    if (!empty($hq_data['HQ_Image'])) {
        $hq_branch['decoded_images'][] = $hq_data['HQ_Image'];
    } else {
        $hq_branch['decoded_images'][] = 'images/hero_3.jpg'; // 默认备用图
    }
}

// ==========================================
// 3. [FIXED] 获取其他分行 (Branches) 数据
// ==========================================
// 从 branch 表读取所有开启的分行
$sql = "SELECT Branch_ID, Branch_Name, Branch_Type, Branch_Description, Branch_Address1, Branch_City, Branch_Images 
        FROM branch 
        WHERE Is_Deleted = 0 AND Branch_OperationalStatus = 'Open'
        ORDER BY Branch_ID ASC";
$result = $conn->query($sql);

$other_branches = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // 解码 Branch 表的 JSON 图片路径
        $row['decoded_images'] = !empty($row['Branch_Images']) ? json_decode($row['Branch_Images'], true) : [];
        
        // 如果解码失败或没有图片，给一个空数组，HTML里会处理显示默认图
        if (!is_array($row['decoded_images'])) {
            $row['decoded_images'] = [];
        }
        
        $other_branches[] = $row;
    }
}

include 'header_UI.php'; 
?>

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

    /* Branch Container */
    .branch-container {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    /* Branch Option Wrapper */
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

    /* Branch Card Design */
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

    /* Hover & Selected States */
    .branch-option:hover .branch-card {
        border-color: #ffcccc;
        box-shadow: 0 8px 25px rgba(220, 38, 38, 0.15);
        transform: translateY(-2px);
    }
    
    .branch-option input[type="radio"]:checked + .branch-card {
        border-color: #dc2626;
        background-color: #fffafa;
    }

    /* Left Side: Image Carousel */
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

    /* Right Side: Information */
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
    /* Special color for HQ tag */
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
    
    .select-btn {
        background: #fff;
        border: 2px solid #ccc;
        color: #555;
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 600;
        transition: 0.3s;
        text-align: center;
    }
    
    .branch-option:hover .select-btn {
        background: #dc2626;
        border-color: #dc2626;
        color: white;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .branch-card { flex-direction: column; height: auto; }
        .branch-carousel { width: 100%; height: 200px; }
        .branch-info { width: 100%; padding: 20px; }
    }

    /* Navigation Buttons */
    .nav-buttons { display: flex; gap: 20px; margin-top: 40px; justify-content: center; }
    .btn-nav { padding: 15px 40px; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; border: none; text-align: center; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 10px; transition: 0.2s; }
    .btn-prev { background: #e5e7eb; color: #374151; border: 1px solid #d1d5db; }
    .btn-prev:hover { background: #d1d5db; color: #111; }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Select Beneficiary</h1>
        <p>Choose where your donation goes.</p>
    </div>
</div>

<?php 
    $current_step = 2; 
    $flow_type = 'standard'; 
    include 'stepper.php'; 
?>

<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <h3 class="text-cursive mb-4 text-center" style="color: #dc2626;">Choose a Branch to Proceed</h3>
                <p class="text-center text-muted mb-5">Click on any branch card to select and continue to payment.</p>

                <form action="Payment_Ways_Page.php" method="POST" id="branchForm">
                    <div class="branch-container">

                        <label class="branch-option">
                            <input type="radio" name="branch_id" value="" 
                                <?php echo ($pre_selected_branch == '') ? 'checked' : ''; ?>
                                onclick="this.form.submit()">
                            
                            <div class="branch-card">
                                <div class="branch-carousel" id="carousel-hq">
                                    <?php 
                                        // 这里的逻辑已统一，无论是 HQ 还是 Branch 都使用 decoded_images
                                        if ($hq_branch && !empty($hq_branch['decoded_images'])) {
                                            foreach ($hq_branch['decoded_images'] as $index => $img_path) {
                                                $activeClass = ($index === 0) ? 'active' : '';
                                                echo '<img src="'.htmlspecialchars($img_path).'" class="carousel-img '.$activeClass.'" alt="HQ Image">';
                                            }
                                        } else {
                                            echo '<img src="images/hero_3.jpg" class="default-img" alt="General Fund">';
                                        }
                                    ?>
                                </div>
                                <div class="branch-info">
                                    <div>
                                        <div class="info-header">
                                            <h4 class="branch-title">
                                                <?php echo $hq_branch ? htmlspecialchars($hq_branch['Branch_Name']) : 'Love Bridge HQ'; ?>
                                            </h4>
                                            <span class="branch-type type-hq">Headquarters</span>
                                        </div>
                                        <p class="branch-desc">
                                            <?php 
                                                if ($hq_branch && !empty($hq_branch['Branch_Description'])) {
                                                    echo nl2br(htmlspecialchars($hq_branch['Branch_Description']));
                                                } else {
                                                    echo "Donating to the General Fund allows us to allocate resources where they are needed most urgently across all our branches and initiatives.";
                                                }
                                            ?>
                                        </p>
                                    </div>
                                    <div class="branch-footer">
                                        <div class="branch-location">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo $hq_branch ? htmlspecialchars($hq_branch['Branch_City']) : 'Kuala Lumpur'; ?>
                                        </div>
                                        <div class="select-btn">Select & Pay <i class="fas fa-chevron-right" style="font-size: 0.8em; margin-left: 5px;"></i></div>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <?php if (!empty($other_branches)): ?>
                            <?php foreach ($other_branches as $row): ?>
                                <label class="branch-option">
                                    <input type="radio" name="branch_id" value="<?php echo $row['Branch_ID']; ?>"
                                        <?php echo ($pre_selected_branch == $row['Branch_ID']) ? 'checked' : ''; ?>
                                        onclick="this.form.submit()">
                                    
                                    <div class="branch-card">
                                        <div class="branch-carousel" id="carousel-<?php echo $row['Branch_ID']; ?>">
                                            <?php 
                                                $images = $row['decoded_images'];
                                                if (!empty($images) && is_array($images)) {
                                                    foreach ($images as $index => $img_path) {
                                                        $activeClass = ($index === 0) ? 'active' : '';
                                                        echo '<img src="'.htmlspecialchars($img_path).'" class="carousel-img '.$activeClass.'" alt="Branch Image">';
                                                    }
                                                } else {
                                                    echo '<img src="images/hero_3.jpg" class="default-img" alt="No Image">';
                                                }
                                            ?>
                                        </div>

                                        <div class="branch-info">
                                            <div>
                                                <div class="info-header">
                                                    <h4 class="branch-title"><?php echo htmlspecialchars($row['Branch_Name']); ?></h4>
                                                    <span class="branch-type"><?php echo htmlspecialchars($row['Branch_Type']); ?></span>
                                                </div>
                                                <p class="branch-desc"><?php echo nl2br(htmlspecialchars($row['Branch_Description'])); ?></p>
                                            </div>
                                            <div class="branch-footer">
                                                <div class="branch-location">
                                                    <i class="fas fa-map-marker-alt"></i> 
                                                    <?php echo htmlspecialchars($row['Branch_City']); ?>
                                                </div>
                                                <div class="select-btn">Select & Pay <i class="fas fa-chevron-right" style="font-size: 0.8em; margin-left: 5px;"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>

                    <div class="nav-buttons">
                        <a href="Payment_Page.php" class="btn-nav btn-prev">
                            <i class="fas fa-arrow-left"></i> Previous Step
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Image Carousel Logic
        const carousels = document.querySelectorAll('.branch-carousel');

        carousels.forEach(carousel => {
            const images = carousel.querySelectorAll('.carousel-img');
            
            if (images.length > 1) {
                let currentIndex = 0;

                setInterval(() => {
                    images[currentIndex].classList.remove('active');
                    currentIndex = (currentIndex + 1) % images.length;
                    images[currentIndex].classList.add('active');
                }, 5000); 
            }
        });
    });
</script>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>