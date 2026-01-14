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

    $name    = $data['HQ_Name'] ?? 'Love Bridge International Headquarters';
    $desc    = $data['HQ_Story'] ?? $data['HQ_Description']; 
    $type    = "Headquarters";
    $images  = !empty($data['HQ_Image']) ? [$data['HQ_Image']] : ['images/hero_3.jpg'];
    $address = $data['HQ_Address'] ?? 'N/A';
    $city    = $data['Headquarters_State'] ?? 'Kuala Lumpur';
    $email   = $data['HQ_Email'] ?? 'hq@lovebridge.org.my';
    $phone   = $data['HQ_ContactNumber'] ?? 'N/A';
    $head    = "Management Team"; 
    $capacity = 1000;
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

    $name    = $data['Branch_Name'];
    $desc    = $data['Branch_Description'];
    $type    = $data['Branch_Type'];
    // 分行图片 JSON 解码
    $images  = json_decode($data['Branch_Images'], true) ?: ['images/hero_3.jpg'];
    $address = $data['Branch_Address1'] . ', ' . $data['Branch_Address2'] . ', ' . $data['Branch_Address3'];
    $city    = $data['Branch_City'];
    $email   = $data['Branch_Email'];
    $phone   = $data['Branch_ContactNumber'];
    $head    = $data['Branch_Head'];
    $capacity = $data['Branch_Capacity'];
    $date    = $data['Branch_EstablishedDate'];
    $head_phone = $data['Branch_Head_Contact'];
}

include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($name); ?> - Love Bridge</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #e53935;
            --dark-red: #c62828;
            --soft-bg: #f8fafc;
            --text-main: #1e293b;
            --text-light: #64748b;
        }

        body { background-color: var(--soft-bg); color: var(--text-main); font-family: 'Segoe UI', system-ui, sans-serif; }

        .detail-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }

        /* 返回按钮样式 (胶囊形式) */
        .btn-back-selection {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            background-color: white;
            color: var(--text-main);
            border: 1px solid #e2e8f0;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none !important;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .btn-back-selection:hover {
            background-color: var(--primary-red);
            color: white;
            border-color: var(--primary-red);
            transform: translateX(-5px);
        }

        /* 左侧主卡片 */
        .content-card {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .main-gallery { margin-bottom: 30px; }
        .main-img {
            width: 100%; height: 480px;
            object-fit: cover; border-radius: 15px;
            cursor: zoom-in; transition: 0.3s;
        }
        .thumb-row { display: flex; gap: 12px; margin-top: 15px; overflow-x: auto; padding-bottom: 10px; }
        .thumb {
            width: 90px; height: 65px; object-fit: cover;
            border-radius: 8px; cursor: pointer; opacity: 0.5;
            transition: 0.3s; border: 2px solid transparent;
        }
        .thumb.active, .thumb:hover { opacity: 1; border-color: var(--primary-red); }

        .branch-title { font-size: 32px; font-weight: 800; margin-bottom: 15px; color: #0f172a; }
        
        .badge-row { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .info-badge {
            background: #f1f5f9; color: var(--text-light);
            padding: 6px 16px; border-radius: 30px; font-size: 13px;
            display: flex; align-items: center; gap: 8px; font-weight: 500;
        }
        .badge-highlight { background: #fee2e2; color: var(--primary-red); font-weight: 700; }

        .section-header {
            font-size: 20px; font-weight: 700; margin: 40px 0 20px;
            display: flex; align-items: center; gap: 15px;
        }
        .section-header::after { content: ''; flex: 1; height: 1px; background: #f1f5f9; }

        .desc-content { line-height: 1.8; color: #475569; font-size: 16px; text-align: justify; white-space: pre-line; }

        /* 右侧侧边栏 */
        .sidebar-sticky { position: sticky; top: 100px; }
        
        .contact-card {
            background: white; border-radius: 24px; padding: 32px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05);
            border-top: 5px solid var(--primary-red);
        }

        .info-item { display: flex; gap: 16px; margin-bottom: 24px; }
        .info-icon {
            min-width: 44px; height: 44px; background: #fff1f2;
            color: var(--primary-red); border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 18px;
        }
        .info-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 2px; }
        .info-text { font-weight: 600; font-size: 14px; color: #334155; line-height: 1.4; }

        .manager-tag {
            background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 16px; padding: 20px;
            display: flex; align-items: center; gap: 15px; margin-top: 25px;
        }
        .manager-icon {
            width: 48px; height: 48px; background: var(--primary-red);
            color: white; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 20px;
        }

        @media (max-width: 900px) {
            .detail-container { grid-template-columns: 1fr; }
            .sidebar-sticky { position: static; order: -1; margin-bottom: 30px; }
        }
    </style>
</head>
<body>

<div class="detail-container">
    <div class="left-col">
        <a href="Branch_Selection.php" class="btn-back-selection">
            <i class="fas fa-chevron-left"></i> Back to Selection
        </a>

        <div class="content-card">
            <div class="main-gallery">
                <img src="<?php echo htmlspecialchars($images[0]); ?>" id="displayImg" class="main-img" onclick="openZoom(this.src)">
                <?php if(count($images) > 1): ?>
                <div class="thumb-row">
                    <?php foreach($images as $idx => $img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" 
                             class="thumb <?php echo $idx===0?'active':''; ?>" 
                             onclick="updateImg(this.src, this)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <h1 class="branch-title"><?php echo htmlspecialchars($name); ?></h1>
            
            <div class="badge-row">
                <div class="info-badge badge-highlight"><i class="fas fa-certificate"></i> <?php echo $type; ?></div>
                <div class="info-badge"><i class="fas fa-user-group"></i> Capacity: <?php echo $capacity; ?></div>
                <?php if(!empty($date) && $date != '0000-00-00'): ?>
                    <div class="info-badge"><i class="fas fa-calendar-alt"></i> Est. <?php echo date('Y', strtotime($date)); ?></div>
                <?php endif; ?>
            </div>

            <div class="section-header">About our Center</div>
            <div class="desc-content">
                <?php echo nl2br(htmlspecialchars($desc)); ?>
            </div>

            <div class="section-header">Our Location</div>
            <div style="background: #f8fafc; padding: 25px; border-radius: 16px; color: var(--text-light); border: 1px dashed #e2e8f0;">
                <i class="fas fa-map-pin me-2" style="color: var(--primary-red);"></i> 
                <strong>Full Address:</strong> <?php echo htmlspecialchars($address); ?>, <?php echo htmlspecialchars($city); ?>
            </div>
        </div>
    </div>

    <div class="right-col">
        <div class="sidebar-sticky">
            <div class="contact-card">
                <h4 style="margin-bottom: 28px; font-weight: 800; letter-spacing: -0.5px;">Center Details</h4>
                
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-phone"></i></div>
                    <div>
                        <div class="info-label">Contact Number</div>
                        <div class="info-text"><?php echo htmlspecialchars($phone); ?></div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-envelope-open-text"></i></div>
                    <div>
                        <div class="info-label">Official Email</div>
                        <div class="info-text"><?php echo htmlspecialchars($email); ?></div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-globe"></i></div>
                    <div>
                        <div class="info-label">State</div>
                        <div class="info-text"><?php echo htmlspecialchars($city); ?>, Malaysia</div>
                    </div>
                </div>

                <div class="manager-tag">
                    <div class="manager-icon"><i class="fas fa-user-check"></i></div>
                    <div>
                        <div class="info-label">Manager In Charge</div>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($head); ?></div>
                        <?php if(isset($head_phone)): ?>
                            <div style="font-size: 12px; color: var(--text-light); margin-top: 2px;"><?php echo htmlspecialchars($head_phone); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <p style="text-align: center; font-size: 12px; color: #cbd5e1; margin-top: 25px; font-weight: 500;">
                <i class="fas fa-lock me-1"></i> Data secure & Transparency guaranteed
            </p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    function updateImg(src, el) {
        document.getElementById('displayImg').src = src;
        document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
    }

    function openZoom(src) {
        Swal.fire({
            imageUrl: src,
            imageWidth: '100%',
            showConfirmButton: false,
            showCloseButton: true,
            background: 'rgba(255,255,255,0.95)',
            backdrop: 'rgba(15,23,42,0.9)',
            padding: '10px'
        });
    }
</script>

</body>
</html>