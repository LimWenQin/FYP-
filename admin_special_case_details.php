<?php
// admin_special_case_details.php
session_start();

// Check Login
if (!isset($_SESSION['admin_id'])) {
    die("Access Denied.");
}

include 'dataconnection.php';

if (!isset($_GET['id'])) {
    die("Invalid Case ID.");
}

$id = intval($_GET['id']);

// Fetch Case Data
$sql = "SELECT * FROM special_case WHERE Case_ID = $id";
$result = $conn->query($sql);

if ($result->num_rows == 0) die("Case not found.");
$case = $result->fetch_assoc();

// Calculate Percentage
$percent = 0;
if ($case['Target_Amount'] > 0) {
    $percent = min(100, ($case['Raised_Amount'] / $case['Target_Amount']) * 100);
}

// Handle Images JSON
$images = json_decode($case['Case_Images'], true);
// Compatibility check: if old data was a single string, make it an array
if (!is_array($images) && !empty($case['Case_Images'])) {
    $images = [$case['Case_Images']];
}
$hasImages = ($images && !empty($images));
if (!$hasImages) $images = [];

// Format Address
$fullAddress = $case['Case_Address1'];
if(!empty($case['Case_Address2'])) $fullAddress .= "<br>" . $case['Case_Address2'];
if(!empty($case['Case_Address3'])) $fullAddress .= "<br>" . $case['Case_Address3'];
$fullAddress .= "<br>" . $case['Case_PostalCode'] . " " . $case['Case_City'];
$fullAddress .= "<br>" . $case['Case_State'] . ", " . $case['Case_Country'];

// Determine Status Colors
$statusClass = 'status-active';
if ($case['Case_Status'] == 'Completed') $statusClass = 'status-completed';
if ($case['Case_Status'] == 'Cancelled') $statusClass = 'status-cancelled';
if ($case['Case_Status'] == 'Upcoming') $statusClass = 'status-upcoming';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($case['Case_Title']); ?> - Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px 20px; margin: 0; }
        .detail-wrapper { max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        
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
        .profile-header { padding: 30px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: flex-start; }
        .h-main h1 { margin: 0; font-size: 28px; color: #333; font-weight: 800; }
        .h-main .badge-category { background: #fff3cd; color: #856404; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; margin-bottom: 8px; display: inline-block; }
        .date-range { font-size: 13px; color: #666; margin-top: 5px; }

        .status-pill { padding: 6px 14px; border-radius: 30px; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .status-active { background: #cff4fc; color: #055160; border: 1px solid #b6effb; }
        .status-completed { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .status-upcoming { background: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
        .status-cancelled { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

        /* GALLERY SECTION - MODIFIED FOR FULL FILL */
        .gallery-container { height: 350px; position: relative; background: #f8f9fa; border-bottom: 1px solid #eee; overflow: hidden; }
        /* Changed object-fit to cover and removed background: black */
        .gallery-img { width: 100%; height: 100%; object-fit: cover; display: none; cursor: zoom-in; transition: opacity 0.3s; }
        .gallery-img:hover { opacity: 0.95; }
        .gallery-img.active { display: block; }
        
        .nav-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.8); color: #333; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 18px; transition: 0.2s; display: flex; align-items: center; justify-content: center; z-index: 10; }
        .nav-btn:hover { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .prev { left: 20px; } .next { right: 20px; }
        
        .no-image-box { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #ccc; }
        .no-image-box i { font-size: 50px; margin-bottom: 15px; }

        /* CONTENT */
        .content-body { padding: 30px; display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .section-title { font-size: 16px; font-weight: 700; color: #333; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; }
        .section-title i { color: #F28585; }
        
        .description-box { font-size: 15px; line-height: 1.7; color: #555; white-space: pre-line; margin-bottom: 30px; text-align: justify; }

        /* STATS & INFO CARDS */
        .info-card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 20px; }
        .info-row { display: flex; gap: 15px; margin-bottom: 18px; align-items: flex-start; }
        .info-row:last-child { margin-bottom: 0; }
        .ir-icon { width: 32px; height: 32px; background: #fff0f0; border-radius: 8px; color: #F28585; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .ir-data { flex: 1; }
        .ir-data h5 { margin: 0 0 3px 0; font-size: 11px; color: #999; text-transform: uppercase; }
        .ir-data p { margin: 0; font-size: 14px; font-weight: 600; color: #333; line-height: 1.4; word-break: break-word; }

        /* FINANCIAL CARD */
        .financial-card { background: #fdfdfd; padding: 20px; border-radius: 12px; border: 1px solid #eee; margin-bottom: 20px; }
        .fin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .fin-item span:first-child { font-size: 11px; color: #888; text-transform: uppercase; display: block; }
        .fin-item span:last-child { font-size: 16px; font-weight: 800; color: #333; }
        .p-bar-bg { background: #e9ecef; height: 10px; border-radius: 10px; overflow: hidden; }
        .p-bar-fill { height: 100%; background: #F28585; transition: width 0.5s ease; }
        .p-text { text-align: right; font-size: 12px; color: #666; margin-top: 5px; font-weight: 600; }

        /* LIGHTBOX */
        .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.95); align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { max-width: 90%; max-height: 90vh; border-radius: 4px; object-fit: contain; animation: zoom 0.3s; }
        @keyframes zoom { from {transform:scale(0.9); opacity: 0} to {transform:scale(1); opacity: 1} }
        .close-modal { position: absolute; top: 20px; right: 30px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 10002; }
        .modal-nav-btn { position: absolute; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.7); font-size: 50px; font-weight: bold; cursor: pointer; padding: 20px; z-index: 10001; }
        .modal-nav-btn:hover { color: #fff; background: rgba(0,0,0,0.2); }
        .modal-prev { left: 20px; } .modal-next { right: 20px; }

        @media (max-width: 768px) { .content-body { grid-template-columns: 1fr; } .gallery-container { height: 220px; } }
    </style>
</head>
<body>

    <div class="detail-wrapper">
        <div class="top-bar">
            <a href="javascript:void(0);" onclick="goBackAndClose()" class="back-link"><i class="fas fa-arrow-left"></i> Back to Management</a>
            <div style="font-size:12px; color:#999;">Case ID: #<?php echo str_pad($case['Case_ID'], 4, '0', STR_PAD_LEFT); ?></div>
        </div>

        <div class="profile-card">
            <div class="gallery-container">
                <?php if ($hasImages): ?>
                    <?php foreach($images as $idx => $img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" class="gallery-img <?php echo $idx===0?'active':''; ?>" onclick="openLightbox(<?php echo $idx; ?>)">
                    <?php endforeach; ?>
                    <?php if(count($images) > 1): ?>
                        <button class="nav-btn prev" onclick="changeSlide(-1); event.stopPropagation();"><i class="fas fa-chevron-left"></i></button>
                        <button class="nav-btn next" onclick="changeSlide(1); event.stopPropagation();"><i class="fas fa-chevron-right"></i></button>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-image-box"><i class="fas fa-image"></i><span>No Images</span></div>
                <?php endif; ?>
            </div>

            <div class="profile-header">
                <div class="h-main">
                    <span class="badge-category"><i class="fas fa-tags"></i> <?php echo htmlspecialchars($case['Case_Category']); ?></span>
                    <h1><?php echo htmlspecialchars($case['Case_Title']); ?></h1>
                    <div class="date-range">
                        <i class="far fa-calendar-alt"></i> 
                        <?php 
                            if($case['Start_Date']) echo date('d M Y', strtotime($case['Start_Date'])); 
                            else echo 'TBA';
                        ?> 
                        - 
                        <?php 
                            if($case['End_Date']) echo date('d M Y', strtotime($case['End_Date'])); 
                            else echo 'Ongoing';
                        ?>
                    </div>
                </div>
                <div class="status-pill <?php echo $statusClass; ?>"><?php echo $case['Case_Status']; ?></div>
            </div>

            <div class="content-body">
                <div class="left-col">
                    <div class="section-title"><i class="fas fa-align-left"></i> Description & Story</div>
                    <div class="description-box"><?php echo nl2br(htmlspecialchars($case['Case_Description'])); ?></div>

                    <div class="section-title"><i class="fas fa-info-circle"></i> Case Logistics</div>
                    <div class="info-card">
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-map-marked-alt"></i></div>
                            <div class="ir-data"><h5>Venue / Location Name</h5><p><?php echo htmlspecialchars($case['Case_Venue'] ?? 'N/A'); ?></p></div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-clock"></i></div>
                            <div class="ir-data"><h5>Created At</h5><p><?php echo date('d M Y, h:i A', strtotime($case['Created_At'])); ?></p></div>
                        </div>
                        <?php if($case['Cancel_Reason']): ?>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-ban"></i></div>
                            <div class="ir-data"><h5>Cancellation Reason</h5><p style="color:red;"><?php echo htmlspecialchars($case['Cancel_Reason']); ?></p></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="right-col">
                    <div class="financial-card">
                        <div class="fin-grid">
                            <div class="fin-item"><span>Target</span><span>RM <?php echo number_format($case['Target_Amount'], 2); ?></span></div>
                            <div class="fin-item"><span>Raised</span><span>RM <?php echo number_format($case['Raised_Amount'], 2); ?></span></div>
                        </div>
                        <div class="p-bar-bg"><div class="p-bar-fill" style="width: <?php echo $percent; ?>%"></div></div>
                        <div class="p-text"><?php echo number_format($percent, 1); ?>% Funded</div>
                    </div>

                    <div class="section-title"><i class="fas fa-user-tie"></i> Organizer / Contact</div>
                    <div class="info-card">
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-sitemap"></i></div>
                            <div class="ir-data"><h5>Organizer</h5><p><?php echo htmlspecialchars($case['Case_Organizer'] ?? 'Love Bridge HQ'); ?></p></div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-user"></i></div>
                            <div class="ir-data"><h5>Contact Person</h5><p><?php echo htmlspecialchars($case['Contact_Name'] ?? 'N/A'); ?></p></div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-phone"></i></div>
                            <div class="ir-data"><h5>Phone</h5><p><?php echo htmlspecialchars($case['Contact_Number'] ?? 'N/A'); ?></p></div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-envelope"></i></div>
                            <div class="ir-data"><h5>Email</h5><p><?php echo htmlspecialchars($case['Contact_Email'] ?? 'N/A'); ?></p></div>
                        </div>
                    </div>

                    <div class="section-title"><i class="fas fa-map-marker-alt"></i> Address</div>
                    <div class="info-card">
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-map"></i></div>
                            <div class="ir-data"><h5>Location</h5><p><?php echo $fullAddress; ?></p></div>
                        </div>
                    </div>
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
        // Logic to close tab and focus back on parent, or redirect
        function goBackAndClose() {
            window.close();
            // Fallback if window.close() is blocked by browser (only works if script opened the window)
            setTimeout(function() { 
                if (!window.closed) window.location.href = 'special_case_management.php'; 
            }, 100);
        }

        // Gallery Slider Logic
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
            document.body.style.overflow = 'hidden'; // Prevent scrolling
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

        function updateLightboxImage() { modalImg.src = imageList[lightboxIndex]; }

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