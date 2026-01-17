<?php
// Branch_Details.php
include 'dataconnection.php';

// 1. 获取 ID 并查询数据库
$branch_id = isset($_GET['id']) ? $_GET['id'] : '';

if ($branch_id === '0' || empty($branch_id)) {
    // 总部逻辑
    $sql = "SELECT * FROM headquarters WHERE HQ_ID = 1";
    $res = $conn->query($sql);
    $data = $res->fetch_assoc();

    $name       = $data['HQ_Name'] ?? 'Love Bridge International Headquarters';
    $desc       = $data['HQ_Story'] ?? $data['HQ_Description']; 
    $type       = "Headquarters";
    $images     = !empty($data['HQ_Image']) ? [$data['HQ_Image']] : ['images/hero_3.jpg'];
    $address    = $data['HQ_Address'] ?? 'N/A';
    $city       = $data['Headquarters_State'] ?? 'Kuala Lumpur';
    $state      = 'Kuala Lumpur';
    $zip        = '50450';
    $country    = "Malaysia";
    $email      = $data['HQ_Email'] ?? 'hq@lovebridge.org.my';
    $phone      = $data['HQ_ContactNumber'] ?? 'N/A';
    $head       = "Management Team"; 
    $capacity   = 1000;
    $date       = $data['HQ_FoundingDate'] ?? '2010-01-01';
    $head_phone = $phone;
    $head_email = $email;
} else {
    // 分行逻辑
    $stmt = $conn->prepare("SELECT * FROM branch WHERE Branch_ID = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if (!$data) { 
        echo "<script>alert('Branch not found.'); window.location.href='Branch_Selection.php';</script>"; 
        exit; 
    }

    $name       = $data['Branch_Name'];
    $desc       = $data['Branch_Description'];
    $type       = $data['Branch_Type'];
    $images     = json_decode($data['Branch_Images'], true) ?: ['images/hero_3.jpg'];
    $address    = $data['Branch_Address1'];
    $address2   = $data['Branch_Address2'];
    $address3   = $data['Branch_Address3'];
    $city       = $data['Branch_City'];
    $state      = $data['Branch_State'];
    $zip        = $data['Branch_PostalCode'];
    $country    = $data['Branch_Country'] ?? 'Malaysia';
    $email      = $data['Branch_Email'];
    $phone      = $data['Branch_ContactNumber'];
    $head       = $data['Branch_Head'];
    $capacity   = $data['Branch_Capacity'];
    $date       = $data['Branch_EstablishedDate'];
    $head_phone = $data['Branch_Head_Contact'];
    $head_email = $data['Branch_Head_Email'];
}

include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($name); ?> - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* 引入管理员端的色彩与排版风格 */
        :root {
            --primary-red: #F28585; /* 使用管理员端的特色红 */
            --bg-gray: #f0f2f5;
            --text-dark: #333;
            --text-muted: #888;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        body { background: var(--bg-gray); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-dark); }
        .detail-wrapper { max-width: 1000px; margin: 40px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 20px; }

        /* 返回按钮 - 胶囊风格 */
        .top-bar { display: flex; justify-content: space-between; align-items: center; }
        .back-link { 
            text-decoration: none; color: #555; font-weight: 600; font-size: 14px; 
            display: inline-flex; align-items: center; gap: 8px; 
            padding: 8px 16px; background: white; border-radius: 50px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: 0.2s;
        }
        .back-link:hover { background: var(--primary-red); color: white; transform: translateY(-2px); }

        .profile-card { background: white; border-radius: 16px; box-shadow: var(--card-shadow); overflow: hidden; }

        /* 画廊部分 */
        .gallery-container { height: 380px; position: relative; background: #f8f9fa; border-bottom: 1px solid #eee; overflow: hidden; }
        .gallery-img { width: 100%; height: 100%; object-fit: cover; display: none; cursor: zoom-in; }
        .gallery-img.active { display: block; }
        .nav-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.8); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; transition: 0.2s; }
        .nav-btn:hover { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .prev { left: 20px; } .next { right: 20px; }

        /* 头部信息 */
        .profile-header { padding: 30px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: flex-start; }
        .h-main h1 { margin: 0; font-size: 28px; color: #333; font-weight: 800; }
        .badge-type { background: #e3f2fd; color: #1976d2; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; margin-bottom: 8px; display: inline-block; }
        .est-date { font-size: 13px; color: var(--text-muted); margin-top: 5px; }
        
        .status-pill { padding: 6px 14px; border-radius: 30px; font-weight: 700; font-size: 12px; text-transform: uppercase; }
        .status-open { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

        /* 内容布局 */
        .content-body { padding: 30px; display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 40px; }
        .section-title { font-size: 16px; font-weight: 700; color: #333; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .section-title i { color: var(--primary-red); }
        .description-box { font-size: 15px; line-height: 1.7; color: #555; white-space: pre-line; margin-bottom: 30px; text-align: justify; }

        /* 信息卡片 */
        .info-card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 20px; }
        .info-header { font-size: 12px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px dashed #eee; }
        .info-row { display: flex; gap: 15px; margin-bottom: 18px; align-items: flex-start; }
        .info-row:last-child { margin-bottom: 0; }
        .ir-icon { width: 32px; height: 32px; background: #fff0f0; border-radius: 8px; color: var(--primary-red); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .ir-data h5 { margin: 0 0 3px 0; font-size: 11px; color: #999; text-transform: uppercase; }
        .ir-data p { margin: 0; font-size: 14px; font-weight: 600; color: #333; line-height: 1.4; }

        /* Lightbox 弹窗 */
        .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { max-width: 90%; max-height: 80vh; object-fit: contain; animation: zoom 0.3s; }
        @keyframes zoom { from {transform:scale(0.8); opacity:0} to {transform:scale(1); opacity:1} }
        .close-modal { position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; cursor: pointer; }

        @media (max-width: 850px) { .content-body { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="detail-wrapper">
    <div class="top-bar">
        <a href="Branch_Selection.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Selection
        </a>
        <div style="font-size:12px; color:#999;">Type: <?php echo $type; ?></div>
    </div>

    <div class="profile-card">
        <div class="gallery-container">
            <?php foreach($images as $idx => $img): ?>
                <img src="<?php echo htmlspecialchars($img); ?>" 
                     class="gallery-img <?php echo $idx===0?'active':''; ?>"
                     onclick="openLightbox(<?php echo $idx; ?>)">
            <?php endforeach; ?>
            
            <?php if(count($images) > 1): ?>
                <button class="nav-btn prev" onclick="changeSlide(-1)"><i class="fas fa-chevron-left"></i></button>
                <button class="nav-btn next" onclick="changeSlide(1)"><i class="fas fa-chevron-right"></i></button>
            <?php endif; ?>
        </div>

        <div class="profile-header">
            <div class="h-main">
                <span class="badge-type"><?php echo htmlspecialchars($type); ?></span>
                <h1><?php echo htmlspecialchars($name); ?></h1>
                <div class="est-date"><i class="far fa-calendar-alt"></i> Established since <?php echo date('d M Y', strtotime($date)); ?></div>
            </div>
            <div class="status-pill status-open">Open</div>
        </div>

        <div class="content-body">
            <div class="left-col">
                <div class="section-title"><i class="fas fa-align-left"></i> About This Center</div>
                <div class="description-box">
                    <?php echo nl2br(htmlspecialchars($desc)); ?>
                </div>

                <div class="info-card" style="background: #f8f9fa;">
                    <div class="info-row">
                        <div class="ir-icon"><i class="fas fa-users"></i></div>
                        <div class="ir-data">
                            <h5>Center Capacity</h5>
                            <p><?php echo $capacity; ?> People</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="right-col">
                <div class="section-title"><i class="fas fa-info-circle"></i> Contact & Location</div>
                
                <div class="info-card">
                    <div class="info-header">Location Details</div>
                    <div class="info-row">
                        <div class="ir-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="ir-data">
                            <h5>Full Address</h5>
                            <p>
                                <?php echo htmlspecialchars($address); ?><br>
                                <?php if(!empty($address2)) echo htmlspecialchars($address2) . "<br>"; ?>
                                <?php echo htmlspecialchars($zip . " " . $city); ?><br>
                                <?php echo htmlspecialchars($state . ", " . $country); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-header">Communication</div>
                    <div class="info-row">
                        <div class="ir-icon"><i class="fas fa-phone"></i></div>
                        <div class="ir-data"><h5>Office Phone</h5><p><?php echo htmlspecialchars($phone); ?></p></div>
                    </div>
                    <div class="info-row">
                        <div class="ir-icon"><i class="fas fa-envelope"></i></div>
                        <div class="ir-data"><h5>Official Email</h5><p><?php echo htmlspecialchars($email); ?></p></div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-header">Person In Charge</div>
                    <div class="info-row">
                        <div class="ir-icon"><i class="fas fa-user-tie"></i></div>
                        <div class="ir-data"><h5>Manager Name</h5><p><?php echo htmlspecialchars($head); ?></p></div>
                    </div>
                    <?php if(!empty($head_phone)): ?>
                    <div class="info-row">
                        <div class="ir-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="ir-data"><h5>Manager Contact</h5><p><?php echo htmlspecialchars($head_phone); ?></p></div>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($head_email)): ?>
                    <div class="info-row">
                        <div class="ir-icon"><i class="far fa-envelope"></i></div>
                        <div class="ir-data"><h5>Manager Email</h5><p><?php echo htmlspecialchars($head_email); ?></p></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="lightboxModal" class="modal">
    <span class="close-modal" onclick="closeLightbox()">&times;</span>
    <img class="modal-content" id="lightboxImage" src="">
</div>

<script>
    // 画廊逻辑
    let slideIndex = 0;
    const slides = document.querySelectorAll('.gallery-img');
    
    function changeSlide(n) {
        if(slides.length <= 1) return;
        slides[slideIndex].classList.remove('active');
        slideIndex = (slideIndex + n + slides.length) % slides.length;
        slides[slideIndex].classList.add('active');
    }

    // Lightbox 逻辑
    const imageList = <?php echo json_encode($images); ?>;
    const modal = document.getElementById('lightboxModal');
    const modalImg = document.getElementById('lightboxImage');

    function openLightbox(index) {
        modalImg.src = imageList[index];
        modal.classList.add('show');
    }

    function closeLightbox() {
        modal.classList.remove('show');
    }

    // 点击背景关闭
    modal.onclick = function(e) {
        if(e.target === modal) closeLightbox();
    }
</script>

<?php include 'footer.php'; ?>
</body>
</html>