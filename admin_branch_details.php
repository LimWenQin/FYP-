<?php
// admin_branch_details.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    die("Access Denied.");
}

include 'dataconnection.php';

if (!isset($_GET['id'])) {
    die("Invalid Branch ID.");
}

$id = intval($_GET['id']);

// Fetch Branch Data
$sql = "SELECT * FROM branch WHERE Branch_ID = $id";
$result = $conn->query($sql);
if ($result->num_rows == 0) die("Branch not found.");
$branch = $result->fetch_assoc();

// Calculate Total Donated
$statsSql = "SELECT COUNT(*) as donorCount, SUM(Order_Amount) as totalRaised 
             FROM orders 
             WHERE Branch_ID = $id AND (Order_Status = 'Success' OR Order_Status = 'Completed')";
$statsRes = $conn->query($statsSql);
$stats = $statsRes->fetch_assoc();
$totalRaised = $stats['totalRaised'] ?? 0;
$donorCount = $stats['donorCount'] ?? 0;

// Images
$images = json_decode($branch['Branch_Images'], true);
$hasImages = ($images && !empty($images));
if (!$hasImages) {
    // If no images, we will render a placeholder block instead of a carousel
    $images = []; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($branch['Branch_Name']); ?> - Details</title>
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
        }
        .back-link:hover { background: #F28585; color: white; transform: translateY(-2px); }

        .profile-card { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); overflow: hidden; }
        
        /* HEADER SECTION */
        .profile-header { padding: 30px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: flex-start; }
        .h-main h1 { margin: 0; font-size: 28px; color: #333; font-weight: 800; }
        .h-main .badge-type { background: #e3f2fd; color: #1976d2; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: inline-block; }
        .h-main .est-date { font-size: 13px; color: #888; margin-top: 5px; }
        
        .status-pill { padding: 6px 14px; border-radius: 30px; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .status-open { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-closed { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        /* GALLERY SECTION */
        .gallery-container { height: 320px; position: relative; background: #f8f9fa; border-bottom: 1px solid #eee; }
        .gallery-img { width: 100%; height: 100%; object-fit: cover; display: none; }
        .gallery-img.active { display: block; }
        
        .nav-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.8); color: #333; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 18px; transition: 0.2s; display: flex; align-items: center; justify-content: center; z-index: 10; }
        .nav-btn:hover { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .prev { left: 20px; } .next { right: 20px; }
        
        .no-image-box { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #ccc; }
        .no-image-box i { font-size: 50px; margin-bottom: 15px; }
        .no-image-box span { font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

        /* CONTENT GRID */
        .content-body { padding: 30px; display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
        
        .section-title { font-size: 16px; font-weight: 700; color: #333; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .section-title i { color: #F28585; }
        
        .description-box { font-size: 15px; line-height: 1.7; color: #555; white-space: pre-line; margin-bottom: 30px; text-align: justify; }

        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-item { background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center; border: 1px solid #eee; }
        .stat-val { font-size: 20px; font-weight: 800; color: #333; display: block; margin-bottom: 4px; }
        .stat-lbl { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .info-card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 20px; }
        .info-header { font-size: 12px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px dashed #eee; }
        .info-row { display: flex; gap: 15px; margin-bottom: 18px; align-items: flex-start; }
        .info-row:last-child { margin-bottom: 0; }
        .ir-icon { width: 32px; height: 32px; background: #fff0f0; border-radius: 8px; color: #F28585; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .ir-data h5 { margin: 0 0 3px 0; font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
        .ir-data p { margin: 0; font-size: 14px; font-weight: 600; color: #333; line-height: 1.4; }

        @media (max-width: 768px) {
            .content-body { grid-template-columns: 1fr; }
            .gallery-container { height: 220px; }
        }
    </style>
</head>
<body>

    <div class="detail-wrapper">
        <div class="top-bar">
            <a href="#" onclick="window.close(); return false;" class="back-link">
                <i class="fas fa-arrow-left"></i> Close Page
            </a>
            <div style="font-size:12px; color:#999;">ID: #<?php echo str_pad($branch['Branch_ID'], 4, '0', STR_PAD_LEFT); ?></div>
        </div>

        <div class="profile-card">
            
            <div class="gallery-container">
                <?php if ($hasImages): ?>
                    <?php foreach($images as $idx => $img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" class="gallery-img <?php echo $idx===0?'active':''; ?>">
                    <?php endforeach; ?>
                    <?php if(count($images) > 1): ?>
                        <button class="nav-btn prev" onclick="changeSlide(-1)"><i class="fas fa-chevron-left"></i></button>
                        <button class="nav-btn next" onclick="changeSlide(1)"><i class="fas fa-chevron-right"></i></button>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-image-box">
                        <i class="fas fa-image"></i>
                        <span>No Gallery Images</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-header">
                <div class="h-main">
                    <span class="badge-type"><?php echo htmlspecialchars($branch['Branch_Type']); ?></span>
                    <h1><?php echo htmlspecialchars($branch['Branch_Name']); ?></h1>
                    <div class="est-date"><i class="far fa-calendar-alt"></i> Established since <?php echo date('d M Y', strtotime($branch['Branch_EstablishedDate'])); ?></div>
                </div>
                <div class="status-pill <?php echo ($branch['Branch_OperationalStatus'] == 'Open') ? 'status-open' : 'status-closed'; ?>">
                    <?php echo $branch['Branch_OperationalStatus']; ?>
                </div>
            </div>

            <div class="content-body">
                <div class="left-col">
                    <div class="stat-grid">
                        <div class="stat-item">
                            <span class="stat-val">RM <?php echo number_format($totalRaised, 2); ?></span>
                            <span class="stat-lbl">Total Funds Raised</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-val"><?php echo $donorCount; ?></span>
                            <span class="stat-lbl">Total Donations</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-val"><?php echo $branch['Branch_Capacity']; ?></span>
                            <span class="stat-lbl">Capacity (Pax)</span>
                        </div>
                    </div>

                    <div class="section-title"><i class="fas fa-align-left"></i> About This Branch</div>
                    <div class="description-box">
                        <?php echo nl2br(htmlspecialchars($branch['Branch_Description'])); ?>
                    </div>
                </div>

                <div class="right-col">
                    <div class="section-title"><i class="fas fa-info-circle"></i> Contact Information</div>
                    
                    <div class="info-card">
                        <div class="info-header">Branch Details</div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-phone"></i></div>
                            <div class="ir-data">
                                <h5>Branch Phone</h5>
                                <p><?php echo htmlspecialchars($branch['Branch_ContactNumber']); ?></p>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-envelope"></i></div>
                            <div class="ir-data">
                                <h5>Branch Email</h5>
                                <p><?php echo htmlspecialchars($branch['Branch_Email']); ?></p>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="ir-data">
                                <h5>Location</h5>
                                <p>
                                    <?php echo htmlspecialchars($branch['Branch_Address1']); ?><br>
                                    <?php if($branch['Branch_Address2']) echo htmlspecialchars($branch['Branch_Address2']) . "<br>"; ?>
                                    <?php if($branch['Branch_Address3']) echo htmlspecialchars($branch['Branch_Address3']) . "<br>"; ?>
                                    <?php echo htmlspecialchars($branch['Branch_PostalCode'] . " " . $branch['Branch_City']); ?><br>
                                    <?php echo htmlspecialchars($branch['Branch_State'] . ", " . $branch['Branch_Country']); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-header">Person In Charge (PIC)</div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-user-tie"></i></div>
                            <div class="ir-data">
                                <h5>PIC Name</h5>
                                <p><?php echo htmlspecialchars($branch['Branch_Head']); ?></p>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fas fa-mobile-alt"></i></div>
                            <div class="ir-data">
                                <h5>PIC Contact</h5>
                                <p><?php echo !empty($branch['Branch_Head_Contact']) ? htmlspecialchars($branch['Branch_Head_Contact']) : 'N/A'; ?></p>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="far fa-envelope"></i></div>
                            <div class="ir-data">
                                <h5>PIC Email</h5>
                                <p><?php echo !empty($branch['Branch_Head_Email']) ? htmlspecialchars($branch['Branch_Head_Email']) : 'N/A'; ?></p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <script>
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
    </script>
</body>
</html>