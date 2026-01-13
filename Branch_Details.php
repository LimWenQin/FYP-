<?php
// Branch_Details.php
include 'dataconnection.php';
include 'header_UI.php';

// 1. 获取 ID
$branch_id = isset($_GET['id']) ? $_GET['id'] : '';

// 检查是否是从 HQ (id=0 或为空) 进入
if ($branch_id === '0' || empty($branch_id)) {
    // 获取总部数据
    $sql = "SELECT * FROM headquarters WHERE HQ_ID = 1";
    $res = $conn->query($sql);
    $data = $res->fetch_assoc();

    // 根据 SQL 文件定义的字段名
    $name    = $data['HQ_Name'] ?? 'Love Bridge International Headquarters';
    $desc    = $data['HQ_Description'] ?? '';
    $type    = "Headquarters";
    $images  = !empty($data['HQ_Image']) ? [$data['HQ_Image']] : ['images/hero_3.jpg'];
    
    // 修正：数据库字段名为 HQ_Address 和 HQ_ContactNumber
    $address = $data['HQ_Address'] ?? 'N/A';
    $city    = $data['Headquarters_State'] ?? 'Kuala Lumpur';
    $email   = $data['HQ_Email'] ?? 'hq@lovebridge.org.my';
    $phone   = $data['HQ_ContactNumber'] ?? 'N/A';
    $head    = "Management Team"; 
} else {
    // 2. 获取分行详细信息
    $stmt = $conn->prepare("SELECT * FROM branch WHERE Branch_ID = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if (!$data) { 
        echo "<script>alert('Branch not found.'); window.location.href='Branch_Selection.php';</script>"; 
        exit; 
    }

    $name    = $data['Branch_Name'];
    $desc    = $data['Branch_Description'];
    $type    = $data['Branch_Type'];
    // 分行图片是 JSON 格式
    $images  = json_decode($data['Branch_Images'], true) ?: ['images/hero_3.jpg'];
    $address = $data['Branch_Address1'] . ' ' . $data['Branch_Address2'] . ' ' . $data['Branch_Address3'];
    $city    = $data['Branch_City'];
    $email   = $data['Branch_Email'];
    $phone   = $data['Branch_ContactNumber'];
    $head    = $data['Branch_Head'];
    $capacity = $data['Branch_Capacity'];
    $date    = $data['Branch_EstablishedDate'];
    
    $head_phone = $data['Branch_Head_Contact'];
    $head_email = $data['Branch_Head_Email'];
}
?>

<style>
    .details-container { padding: 60px 0; background: #fdfdfd; }
    .info-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 40px; }
    .detail-label { color: #dc2626; font-weight: bold; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }
    .detail-value { font-size: 1.1rem; color: #333; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
    
    /* Carousel Container for Details Page */
    .branch-carousel {
        width: 100%;
        height: 600px;
        position: relative;
        overflow: hidden;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
    
    .btn-back-custom { 
        display: inline-flex; 
        align-items: center; 
        gap: 10px;
        margin-bottom: 30px; 
        background-color: #fff;
        color: #dc2626; 
        border: 2px solid #dc2626;
        padding: 10px 25px;
        border-radius: 50px;
        font-weight: 700; 
        transition: 0.3s;
        text-decoration: none !important;
    }
    .btn-back-custom:hover { 
        background-color: #dc2626;
        color: #fff; 
        transform: translateX(-5px); 
    }
    
    .tag-type { background: #fee2e2; color: #dc2626; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; }
</style>

<div class="details-container">
    <div class="container">
        <a href="Branch_Selection.php" class="btn-back-custom">
            <i class="fas fa-arrow-left"></i> Back to Branch Selection
        </a>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="branch-carousel" id="detail-carousel">
                    <?php 
                        if (!empty($images) && is_array($images)) {
                            foreach ($images as $index => $img_path) {
                                $activeClass = ($index === 0) ? 'active' : '';
                                echo '<img src="'.htmlspecialchars($img_path).'" class="carousel-img '.$activeClass.'" alt="Gallery Image">';
                            }
                        } else {
                            echo '<img src="images/hero_3.jpg" class="carousel-img active" alt="Default Image">';
                        }
                    ?>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="info-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 style="font-weight: 800; color: #222; margin: 0;"><?php echo htmlspecialchars($name); ?></h2>
                        <span class="tag-type"><?php echo htmlspecialchars($type); ?></span>
                    </div>

                    <p class="text-muted mb-5" style="line-height: 1.8; font-size: 1.05rem;">
                        <?php echo nl2br(htmlspecialchars($desc)); ?>
                    </p>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-label">Person In Charge</div>
                            <div class="detail-value"><?php echo htmlspecialchars($head); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div class="detail-label">Branch Contact</div>
                            <div class="detail-value"><?php echo htmlspecialchars($phone); ?></div>
                        </div>

                        <?php if(!empty($head_phone)): ?>
                        <div class="col-md-6">
                            <div class="detail-label">Head Contact</div>
                            <div class="detail-value"><?php echo htmlspecialchars($head_phone); ?></div>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <div class="detail-label">Email Address</div>
                            <div class="detail-value" style="font-size: 0.95rem;"><?php echo htmlspecialchars($email); ?></div>
                        </div>

                        <?php if(!empty($capacity)): ?>
                        <div class="col-md-6">
                            <div class="detail-label">Capacity</div>
                            <div class="detail-value"><?php echo htmlspecialchars($capacity); ?> People</div>
                        </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <div class="detail-label">Full Address</div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($address); ?><br>
                                <?php echo htmlspecialchars($data['Branch_PostalCode'] ?? '') . ' ' . htmlspecialchars($city); ?>
                            </div>
                        </div>

                        <?php if(!empty($date) && $date != '0000-00-00'): ?>
                        <div class="col-md-6">
                            <div class="detail-label">Established Since</div>
                            <div class="detail-value"><?php echo date('d M Y', strtotime($date)); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Find the carousel container
        const carousel = document.querySelector('.branch-carousel');
        if (carousel) {
            const images = carousel.querySelectorAll('.carousel-img');
            
            // Only start interval if there is more than 1 image
            if (images.length > 1) {
                let currentIndex = 0;

                setInterval(() => {
                    images[currentIndex].classList.remove('active');
                    currentIndex = (currentIndex + 1) % images.length;
                    images[currentIndex].classList.add('active');
                }, 5000); // 5 seconds interval
            }
        }
    });
</script>

<?php include 'footer.php'; ?>