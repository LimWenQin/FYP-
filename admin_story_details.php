<?php
// admin_story_details.php
session_start();

// 检查权限
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    die("Access Denied.");
}

include 'dataconnection.php';

if (!isset($_GET['id'])) {
    die("Invalid Story ID.");
}

$id = intval($_GET['id']);

// 获取 Story Data
$sql = "SELECT * FROM story WHERE Story_ID = $id";
$result = $conn->query($sql);
if ($result->num_rows == 0) die("Story not found.");
$story = $result->fetch_assoc();

// --- 处理多张图片 ---
// 逻辑：尝试解码 JSON。如果解码失败（旧数据是单张路径），则手动封装成数组。
$images = [];
$rawImage = $story['Story_Image'];

if (!empty($rawImage)) {
    $decoded = json_decode($rawImage, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // 是 JSON 数组 (新格式)
        $images = $decoded;
    } else {
        // 是普通字符串 (旧格式)
        $images = [$rawImage];
    }
}

$hasImages = !empty($images);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($story['Story_Title']); ?> - Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px 20px; }
        .detail-wrapper { max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        
        .top-bar { display: flex; justify-content: space-between; align-items: center; }
        .back-link { 
            text-decoration: none; color: #555; font-weight: 600; font-size: 14px; 
            display: inline-flex; align-items: center; gap: 8px; 
            padding: 8px 16px; background: white; border-radius: 50px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: 0.2s;
            cursor: pointer;
        }
        .back-link:hover { background: #F28585; color: white; transform: translateY(-2px); }

        .profile-card { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); overflow: hidden; }
        
        /* HEADER SECTION */
        .profile-header { padding: 30px; border-bottom: 1px solid #eee; }
        .h-main h1 { margin: 10px 0; font-size: 28px; color: #333; font-weight: 800; line-height: 1.3; }
        .badge-cat { background: #e3f2fd; color: #1976d2; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; }
        
        /* 时间显示：修改为显示具体时间点 */
        .pub-date { font-size: 14px; color: #666; display: flex; align-items: center; gap: 8px; margin-top: 8px; font-weight: 500;}
        .pub-date i { color: #F28585; }

        /* GALLERY SECTION (PREVIEW) - 复用 Branch Details 样式 */
        .gallery-container { height: 400px; position: relative; background: #f8f9fa; border-bottom: 1px solid #eee; overflow: hidden; }
        .gallery-img { width: 100%; height: 100%; object-fit: cover; display: none; cursor: zoom-in; transition: opacity 0.3s; }
        .gallery-img:hover { opacity: 0.95; }
        .gallery-img.active { display: block; }
        
        .nav-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.8); color: #333; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 18px; transition: 0.2s; display: flex; align-items: center; justify-content: center; z-index: 10; }
        .nav-btn:hover { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .prev { left: 20px; } .next { right: 20px; }
        
        .no-image-box { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #ccc; }
        .no-image-box i { font-size: 50px; margin-bottom: 15px; }

        /* CONTENT BODY */
        .content-body { padding: 40px 30px; }
        .description-box { font-size: 16px; line-height: 1.8; color: #444; white-space: pre-line; text-align: justify; }
        
        /* LIGHTBOX MODAL */
        .modal {
            display: none; position: fixed; z-index: 10000; left: 0; top: 0;
            width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.95);
            align-items: center; justify-content: center;
        }
        .modal.show { display: flex; }
        .modal-content { max-width: 90%; max-height: 90vh; border-radius: 4px; box-shadow: 0 0 20px rgba(0,0,0,0.5); animation: zoom 0.3s; object-fit: contain; }
        @keyframes zoom { from {transform:scale(0.9); opacity: 0} to {transform:scale(1); opacity: 1} }
        .close-modal { position: absolute; top: 20px; right: 30px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 10002; line-height: 1; }
        .close-modal:hover { color: #F28585; }
        .modal-nav-btn { position: absolute; top: 50%; transform: translateY(-50%); color: rgba(255, 255, 255, 0.7); font-size: 50px; font-weight: bold; cursor: pointer; padding: 20px; user-select: none; z-index: 10001; transition: 0.3s; text-decoration: none; }
        .modal-nav-btn:hover { color: #fff; background: rgba(0,0,0,0.2); border-radius: 5px; }
        .modal-prev { left: 20px; } .modal-next { right: 20px; }

        @media (max-width: 768px) {
            .gallery-container { height: 250px; }
            .content-body { padding: 20px; }
        }
    </style>
</head>
<body>

    <div class="detail-wrapper">
        <div class="top-bar">
            <a href="javascript:void(0);" onclick="goBackAndClose()" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Story Management
            </a>
            <div style="font-size:12px; color:#999;">Story ID: #<?php echo str_pad($story['Story_ID'], 4, '0', STR_PAD_LEFT); ?></div>
        </div>

        <div class="profile-card">
            
            <div class="gallery-container">
                <?php if ($hasImages): ?>
                    <?php foreach($images as $idx => $img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" 
                             class="gallery-img <?php echo $idx===0?'active':''; ?>"
                             onclick="openLightbox(<?php echo $idx; ?>)"
                             alt="Story Image">
                    <?php endforeach; ?>
                    
                    <?php if(count($images) > 1): ?>
                        <button class="nav-btn prev" onclick="changeSlide(-1); event.stopPropagation();"><i class="fas fa-chevron-left"></i></button>
                        <button class="nav-btn next" onclick="changeSlide(1); event.stopPropagation();"><i class="fas fa-chevron-right"></i></button>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-image-box">
                        <i class="fas fa-image" style="opacity: 0.3;"></i>
                        <span>No Featured Images</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-header">
                <span class="badge-cat">
                    <?php echo isset($story['Story_Category']) ? htmlspecialchars($story['Story_Category']) : 'General Story'; ?>
                </span>
                <h1><?php echo htmlspecialchars($story['Story_Title']); ?></h1>
                
                <div class="pub-date">
                    <i class="far fa-clock"></i> 
                    Published on: <?php echo date('d M Y, h:i A', strtotime($story['Created_At'])); ?>
                </div>
            </div>

            <div class="content-body">
                <div class="description-box">
                    <?php echo nl2br(htmlspecialchars($story['Story_Description'])); ?>
                </div>
            </div>

        </div>
    </div>

    <div id="lightboxModal" class="modal">
        <span class="close-modal" onclick="closeLightbox()">&times;</span>
        <?php if(count($images) > 1): ?>
            <a class="modal-nav-btn modal-prev" onclick="changeLightboxSlide(-1)">&#10094;</a>
            <a class="modal-nav-btn modal-next" onclick="changeLightboxSlide(1)">&#10095;</a>
        <?php endif; ?>
        <img class="modal-content" id="lightboxImage" src="">
    </div>

    <script>
        function goBackAndClose() {
            window.close();
            setTimeout(function() {
                if (!window.closed) {
                    window.location.href = 'admin_manage_stories.php';
                }
            }, 100);
        }

        // Preview Slider Logic
        let slideIndex = 0;
        const slides = document.querySelectorAll('.gallery-img');
        
        function changeSlide(n) {
            if(slides.length === 0) return;
            slides[slideIndex].classList.remove('active');
            slideIndex += n;
            if (slideIndex >= slides.length) slideIndex = 0;
            if (slideIndex < 0) slideIndex = slides.length - 1;
            slides[slideIndex].classList.add('active');
        }

        // Lightbox Logic
        const imageList = <?php echo json_encode($images); ?>;
        let lightboxIndex = 0;
        const modal = document.getElementById('lightboxModal');
        const modalImg = document.getElementById('lightboxImage');

        function openLightbox(index) {
            if(imageList.length === 0) return;
            lightboxIndex = index;
            modal.classList.add('show');
            updateLightboxImage();
            document.addEventListener('keydown', handleKeydown);
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            modal.classList.remove('show');
            document.removeEventListener('keydown', handleKeydown);
            document.body.style.overflow = 'auto';
        }

        function changeLightboxSlide(n) {
            lightboxIndex += n;
            if (lightboxIndex >= imageList.length) lightboxIndex = 0;
            if (lightboxIndex < 0) lightboxIndex = imageList.length - 1;
            updateLightboxImage();
        }

        function updateLightboxImage() {
            modalImg.src = imageList[lightboxIndex];
        }

        function handleKeydown(e) {
            if (modal.classList.contains('show')) {
                if (e.key === "ArrowLeft") changeLightboxSlide(-1);
                if (e.key === "ArrowRight") changeLightboxSlide(1);
                if (e.key === "Escape") closeLightbox();
            }
        }

        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeLightbox();
        });
    </script>
</body>
</html>