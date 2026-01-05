<?php
include 'dataconnection.php';
include 'header_function.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$donor_id = isset($_SESSION['donor_id']) ? $_SESSION['donor_id'] : null;
$is_admin = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : false;

include 'header_UI.php';

// 获取证书ID
$certificate_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$certificate_number = isset($_GET['cert']) ? $_GET['cert'] : '';
$verify_mode = isset($_GET['verify']) ? true : false;

// 获取证书数据
$certificate = null;
if ($certificate_id > 0) {
    $sql = "SELECT c.*, d.Donor_Email, d.Donor_ContactNumber, d.Donor_RegisteredAt,
                   o.Order_Amount, o.Order_Created_At, o.Order_TXN_Ref,
                   sc.Case_Title, a.Activity_Name,
                   hq.HQ_Name, hq.HQ_ContactNumber, hq.HQ_Email, hq.HQ_Address,
                   admin.Admin_Name as Issuer_Name, admin.Admin_Role as Issuer_Role
            FROM certificate c
            LEFT JOIN donor d ON c.Donor_ID = d.Donor_ID
            LEFT JOIN orders o ON c.Order_ID = o.Order_ID
            LEFT JOIN special_case sc ON c.Case_ID = sc.Case_ID
            LEFT JOIN activity a ON c.Activity_ID = a.Activity_ID
            LEFT JOIN headquarters hq ON hq.HQ_ID = 1
            LEFT JOIN admin ON c.Issued_By_Admin_ID = admin.Admin_ID
            WHERE c.Certificate_ID = ?";
    
    if (!$is_admin && !$verify_mode) {
        $sql .= " AND c.Donor_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $certificate_id, $donor_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $certificate_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $certificate = $result->fetch_assoc();
    
} elseif (!empty($certificate_number)) {
    $sql = "SELECT c.*, d.Donor_Email, d.Donor_ContactNumber, d.Donor_RegisteredAt,
                   o.Order_Amount, o.Order_Created_At, o.Order_TXN_Ref,
                   sc.Case_Title, a.Activity_Name,
                   hq.HQ_Name, hq.HQ_ContactNumber, hq.HQ_Email, hq.HQ_Address,
                   admin.Admin_Name as Issuer_Name, admin.Admin_Role as Issuer_Role
            FROM certificate c
            LEFT JOIN donor d ON c.Donor_ID = d.Donor_ID
            LEFT JOIN orders o ON c.Order_ID = o.Order_ID
            LEFT JOIN special_case sc ON c.Case_ID = sc.Case_ID
            LEFT JOIN activity a ON c.Activity_ID = a.Activity_ID
            LEFT JOIN headquarters hq ON hq.HQ_ID = 1
            LEFT JOIN admin ON c.Issued_By_Admin_ID = admin.Admin_ID
            WHERE c.Certificate_Number = ?";
    
    if (!$is_admin && !$verify_mode) {
        $sql .= " AND c.Donor_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $certificate_number, $donor_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $certificate_number);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $certificate = $result->fetch_assoc();
}

// 验证模式下的公开访问
if ($verify_mode && $certificate) {
    $logged_in = true; // 允许公开查看
}

// 获取用户的所有证书
$all_certificates = [];
if ($donor_id && !$verify_mode) {
    $sql = "SELECT c.*, o.Order_Amount, o.Order_Created_At,
                   sc.Case_Title, a.Activity_Name 
            FROM certificate c
            LEFT JOIN orders o ON c.Order_ID = o.Order_ID
            LEFT JOIN special_case sc ON c.Case_ID = sc.Case_ID
            LEFT JOIN activity a ON c.Activity_ID = a.Activity_ID
            WHERE c.Donor_ID = ? 
            ORDER BY c.Issued_Date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $all_certificates[] = $row;
    }
}

// 获取组织统计信息
$org_stats = [];
if ($certificate) {
    $sql = "SELECT 
            COUNT(DISTINCT Donor_ID) as total_donors,
            COUNT(*) as total_certificates,
            SUM(CASE WHEN YEAR(Issued_Date) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as certificates_this_year
            FROM certificate";
    $result = $conn->query($sql);
    $org_stats = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Donation Certificate - Love Bridge Foundation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Merriweather:wght@300;400;700&family=Crimson+Text:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* 组织证书页面样式 */
        :root {
            --primary-red: #c82e03ff;
            --dark-red: #8b1a00ff;
            --gold: #d4af37;
            --silver: #c0c0c0;
            --bronze: #cd7f32;
            --ivory: #fffff0;
            --parchment: #f5f5dc;
            --official-blue: #1e3a8a;
            --official-gold: #b8860b;
            --seal-red: #8b0000;
        }

        body {
            font-family: 'Merriweather', serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .certificate-hero {
            background: linear-gradient(rgba(200, 46, 3, 0.85), rgba(139, 26, 0, 0.85)), 
                        url('https://images.unsplash.com/photo-1554224155-6726b3ff858f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 80px 0;
            margin-bottom: 60px;
        }
        
        .certificate-hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .certificate-hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 900;
        }
        
        .certificate-hero p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.95;
            font-weight: 300;
        }

        .certificate-container {
            max-width: 1200px;
            margin: 0 auto 80px;
            padding: 0 20px;
        }

        /* 组织证书显示区域 */
        .organization-certificate {
            position: relative;
            background: var(--ivory);
            border: 20px solid transparent;
            border-image: url('https://www.transparenttextures.com/patterns/parchment.png') 30 stretch;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            padding: 60px;
            margin-bottom: 40px;
            min-height: 800px;
            overflow: hidden;
        }

        /* 证书边框样式 */
        .certificate-border {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 2px solid var(--gold);
            margin: 15px;
            pointer-events: none;
        }

        .certificate-border:before {
            content: '';
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 1px solid rgba(139, 26, 0, 0.3);
        }

        /* 证书头部 */
        .certificate-header {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
        }

        .organization-seal {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 120px;
            background: var(--seal-red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 900;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            border: 3px solid var(--gold);
            z-index: 1;
        }

        .organization-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: var(--dark-red);
            margin-top: 60px;
            margin-bottom: 10px;
            font-weight: 900;
            letter-spacing: 2px;
        }

        .organization-subtitle {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 300;
        }

        .certificate-main-title {
            font-family: 'Crimson Text', serif;
            font-size: 3.5rem;
            color: #222;
            margin: 40px 0 20px;
            font-weight: 700;
            letter-spacing: 1px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .certificate-tagline {
            font-size: 1.3rem;
            color: #555;
            font-style: italic;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        /* 证书正文 */
        .certificate-body {
            text-align: center;
            margin: 60px 0;
            padding: 0 40px;
        }

        .certificate-proclamation {
            font-size: 1.4rem;
            color: #444;
            margin-bottom: 40px;
            line-height: 1.8;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .donor-honor {
            font-size: 1.8rem;
            color: #222;
            margin: 40px 0;
            font-weight: 600;
        }

        .donor-name-large {
            font-family: 'Playfair Display', serif;
            font-size: 4rem;
            color: var(--dark-red);
            margin: 30px 0;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            line-height: 1.2;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .certificate-statement {
            font-size: 1.4rem;
            line-height: 1.8;
            color: #333;
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.9);
            border-left: 5px solid var(--primary-red);
            border-right: 5px solid var(--primary-red);
            position: relative;
        }

        .certificate-statement:before,
        .certificate-statement:after {
            content: '"';
            font-family: 'Playfair Display', serif;
            font-size: 4rem;
            color: var(--gold);
            position: absolute;
            opacity: 0.3;
        }

        .certificate-statement:before {
            top: -20px;
            left: 20px;
        }

        .certificate-statement:after {
            bottom: -40px;
            right: 20px;
        }

        /* 证书详情表格 */
        .certificate-details-table {
            width: 100%;
            max-width: 900px;
            margin: 50px auto;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border: 2px solid var(--gold);
        }

        .certificate-details-table th {
            background: var(--dark-red);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }

        .certificate-details-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 1rem;
        }

        .certificate-details-table tr:last-child td {
            border-bottom: none;
        }

        .detail-value-highlight {
            color: var(--dark-red);
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* 证书页脚 */
        .certificate-footer {
            margin-top: 80px;
            padding-top: 40px;
            border-top: 3px double #ddd;
            position: relative;
        }

        .signatures-section {
            display: flex;
            justify-content: space-around;
            margin: 40px 0;
            flex-wrap: wrap;
        }

        .official-signature {
            text-align: center;
            min-width: 250px;
            margin: 20px;
        }

        .signature-line {
            height: 2px;
            background: #333;
            width: 200px;
            margin: 10px auto 20px;
            position: relative;
        }

        .signature-line:after {
            content: '';
            position: absolute;
            top: -4px;
            left: 0;
            right: 0;
            height: 10px;
            background: linear-gradient(to right, transparent, #333, transparent);
        }

        .signature-name {
            font-size: 1.3rem;
            font-weight: 700;
            margin-top: 25px;
            color: #222;
        }

        .signature-title {
            font-size: 1rem;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }

        .organization-stamp {
            position: absolute;
            top: 20px;
            right: 40px;
            width: 150px;
            height: 150px;
            border: 5px double var(--gold);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .stamp-text {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--seal-red);
            text-align: center;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .certificate-metadata {
            text-align: center;
            margin-top: 60px;
            font-size: 0.9rem;
            color: #777;
        }

        .certificate-number {
            font-family: monospace;
            font-size: 1.1rem;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .verification-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid var(--primary-red);
        }

        /* 操作按钮 */
        .certificate-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 40px 0;
            flex-wrap: wrap;
        }

        .official-btn {
            background: var(--dark-red);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 6px rgba(139, 26, 0, 0.2);
        }

        .official-btn:hover {
            background: var(--primary-red);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(139, 26, 0, 0.3);
        }

        .official-btn.secondary {
            background: white;
            color: var(--dark-red);
            border: 2px solid var(--dark-red);
        }

        .official-btn.secondary:hover {
            background: #fff5f2;
        }

        /* 验证模式样式 */
        .verification-mode {
            background: #f0f8ff;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 2px solid var(--official-blue);
        }

        .verification-header {
            background: var(--official-blue);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 30px -30px;
            text-align: center;
        }

        .verification-status {
            padding: 15px;
            border-radius: 5px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
        }

        .verification-status.valid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .verification-status.invalid {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* 响应式设计 */
        @media (max-width: 992px) {
            .organization-certificate {
                padding: 30px;
                min-height: auto;
            }
            
            .donor-name-large {
                font-size: 3rem;
            }
            
            .certificate-main-title {
                font-size: 2.8rem;
            }
            
            .signatures-section {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 768px) {
            .certificate-hero h1 {
                font-size: 2.5rem;
            }
            
            .organization-certificate {
                padding: 20px;
                border-width: 10px;
            }
            
            .donor-name-large {
                font-size: 2.5rem;
            }
            
            .certificate-main-title {
                font-size: 2.2rem;
            }
            
            .certificate-body {
                padding: 0 20px;
            }
            
            .organization-stamp {
                position: relative;
                top: 0;
                right: 0;
                margin: 20px auto;
                width: 120px;
                height: 120px;
            }
            
            .certificate-details-table {
                font-size: 0.9rem;
            }
            
            .certificate-details-table th,
            .certificate-details-table td {
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .certificate-hero h1 {
                font-size: 2rem;
            }
            
            .donor-name-large {
                font-size: 2rem;
            }
            
            .certificate-main-title {
                font-size: 1.8rem;
            }
            
            .organization-title {
                font-size: 1.8rem;
            }
            
            .certificate-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .official-btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }

        /* 打印样式 */
        @media print {
            @page {
                size: A4 landscape;
                margin: 0;
            }
            
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .organization-certificate {
                border: none !important;
                box-shadow: none !important;
                padding: 50px !important;
                margin: 0 !important;
                min-height: 29.7cm !important;
                width: 42cm !important;
                page-break-after: always;
            }
            
            .certificate-hero,
            .certificate-actions,
            .verification-mode,
            .verification-info,
            header,
            footer {
                display: none !important;
            }
            
            .official-btn,
            .no-print {
                display: none !important;
            }
            
            .organization-certificate * {
                color: black !important;
            }
        }

        /* 证书列表样式 */
        .certificates-list {
            margin-top: 60px;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            color: var(--dark-red);
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--gold);
        }

        .certificate-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .certificate-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
        }

        .certificate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--primary-red);
        }

        .card-header {
            background: var(--dark-red);
            color: white;
            padding: 20px;
            position: relative;
        }

        .card-header:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gold);
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .card-type {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--gold);
            color: var(--dark-red);
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .card-body {
            padding: 20px;
        }

        .card-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }

        .card-label {
            color: #666;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .card-value {
            color: #333;
            font-weight: 600;
            text-align: right;
        }

        .card-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            text-align: center;
        }

        .view-cert-btn {
            background: var(--dark-red);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }

        .view-cert-btn:hover {
            background: var(--primary-red);
        }
    </style>
</head>
<body>
   
    <!-- 证书页面主要内容 -->
    <main>
        <section class="certificate-hero">
            <div class="certificate-hero-content">
                <h1>Official Donation Certificates</h1>
                <p>Love Bridge Foundation officially recognizes and honors the generosity of our donors through these certificates of appreciation.</p>
            </div>
        </section>

        <div class="certificate-container">
            <?php if ($verify_mode): ?>
                <!-- 验证模式 -->
                <div class="verification-mode">
                    <div class="verification-header">
                        <h2><i class="fas fa-shield-alt"></i> Official Certificate Verification</h2>
                        <p>Love Bridge Foundation - Certificate Authentication System</p>
                    </div>
                    
                    <?php if ($certificate): ?>
                        <div class="verification-status valid">
                            <i class="fas fa-check-circle"></i> VALID CERTIFICATE
                        </div>
                        
                        <div class="organization-certificate">
                            <!-- 证书内容 -->
                        </div>
                        
                        <div class="verification-info">
                            <p><strong>Verified On:</strong> <?php echo date('F j, Y H:i:s'); ?></p>
                            <p><strong>Verification ID:</strong> VER-<?php echo date('Ymd-His'); ?></p>
                            <p><i class="fas fa-info-circle"></i> This certificate has been successfully verified against the Love Bridge Foundation database.</p>
                        </div>
                    <?php else: ?>
                        <div class="verification-status invalid">
                            <i class="fas fa-times-circle"></i> INVALID CERTIFICATE
                        </div>
                        <p>The certificate you are trying to verify does not exist or has been revoked.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($certificate): ?>
                <!-- 组织官方证书显示 -->
                <div class="organization-certificate">
                    <div class="certificate-border"></div>
                    
                    <!-- 组织印章 -->
                    <div class="organization-seal">
                        <div>LB</div>
                    </div>
                    
                    <!-- 证书头部 -->
                    <div class="certificate-header">
                        <h2 class="organization-title">LOVE BRIDGE FOUNDATION</h2>
                        <div class="organization-subtitle">ESTABLISHED 2010 • REGISTERED CHARITY ORGANIZATION</div>
                        <h1 class="certificate-main-title">CERTIFICATE OF APPRECIATION</h1>
                        <div class="certificate-tagline">
                            In Recognition of Exceptional Generosity and Commitment to Humanitarian Causes
                        </div>
                    </div>
                    
                    <!-- 证书正文 -->
                    <div class="certificate-body">
                        <div class="certificate-proclamation">
                            WHEREAS, the Love Bridge Foundation, a duly registered charitable organization 
                            committed to humanitarian service and community development, hereby formally acknowledges:
                        </div>
                        
                        <div class="donor-honor">IS PROUD TO HONOR</div>
                        
                        <div class="donor-name-large">
                            <?php echo htmlspecialchars($certificate['Donor_Name']); ?>
                        </div>
                        
                        <div class="certificate-statement">
                            For their generous contribution and philanthropic support towards the advancement 
                            of charitable initiatives and humanitarian programs undertaken by the Love Bridge Foundation. 
                            This act of benevolence exemplifies the spirit of compassion and solidarity that 
                            strengthens our collective mission to build bridges of hope and opportunity for those in need.
                        </div>
                        
                        <!-- 官方详情表格 -->
                        <table class="certificate-details-table">
                            <thead>
                                <tr>
                                    <th colspan="2">OFFICIAL DONATION RECORD</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($certificate['Donation_Amount']): ?>
                                <tr>
                                    <td><strong>Contribution Amount</strong></td>
                                    <td class="detail-value-highlight">RM <?php echo number_format($certificate['Donation_Amount'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($certificate['Order_TXN_Ref']): ?>
                                <tr>
                                    <td><strong>Transaction Reference</strong></td>
                                    <td><?php echo htmlspecialchars($certificate['Order_TXN_Ref']); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <tr>
                                    <td><strong>Certificate Issue Date</strong></td>
                                    <td><?php echo date('F j, Y', strtotime($certificate['Certificate_Date'])); ?></td>
                                </tr>
                                
                                <tr>
                                    <td><strong>Certificate Validity</strong></td>
                                    <td>
                                        <?php if ($certificate['Valid_Until']): ?>
                                            Valid until <?php echo date('F j, Y', strtotime($certificate['Valid_Until'])); ?>
                                        <?php else: ?>
                                            Permanent Record
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <?php if ($certificate['Case_Title']): ?>
                                <tr>
                                    <td><strong>Designated Purpose</strong></td>
                                    <td><?php echo htmlspecialchars($certificate['Case_Title']); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($certificate['Activity_Name']): ?>
                                <tr>
                                    <td><strong>Associated Program</strong></td>
                                    <td><?php echo htmlspecialchars($certificate['Activity_Name']); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <tr>
                                    <td><strong>Issuing Location</strong></td>
                                    <td><?php echo htmlspecialchars($certificate['Issue_Location']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- 证书页脚 -->
                    <div class="certificate-footer">
                        <div class="signatures-section">
                            <div class="official-signature">
                                <div class="signature-line"></div>
                                <div class="signature-name">
                                    <?php echo $certificate['Issuer_Name'] ? htmlspecialchars($certificate['Issuer_Name']) : 'Director General'; ?>
                                </div>
                                <div class="signature-title">
                                    <?php echo $certificate['Issuer_Role'] ? htmlspecialchars($certificate['Issuer_Role']) : 'Executive Director'; ?><br>
                                    Love Bridge Foundation
                                </div>
                            </div>
                            
                            <div class="official-signature">
                                <div class="signature-line"></div>
                                <div class="signature-name">Head of Philanthropy</div>
                                <div class="signature-title">
                                    Charitable Operations Division<br>
                                    Love Bridge Foundation
                                </div>
                            </div>
                        </div>
                        
                        <!-- 组织印章 -->
                        <div class="organization-stamp">
                            <div class="stamp-text">
                                <div>OFFICIAL</div>
                                <div>SEAL</div>
                                <div>LOVE BRIDGE</div>
                                <div><?php echo date('Y', strtotime($certificate['Certificate_Date'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="certificate-metadata">
                            <div class="certificate-number">
                                <strong>Certificate Number:</strong> <?php echo htmlspecialchars($certificate['Certificate_Number']); ?>
                            </div>
                            <div class="certificate-number">
                                <strong>Reference Number:</strong> <?php echo htmlspecialchars($certificate['Reference_Number']); ?>
                            </div>
                            <div style="margin-top: 15px; font-size: 0.9rem;">
                                This certificate is issued under the authority of Love Bridge Foundation<br>
                                <?php echo htmlspecialchars($certificate['HQ_Address']); ?> | <?php echo htmlspecialchars($certificate['HQ_ContactNumber']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 操作按钮 -->
                <div class="certificate-actions">
                    <button class="official-btn" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Official Copy
                    </button>
                    <button class="official-btn secondary" onclick="downloadCertificate()">
                        <i class="fas fa-download"></i> Download as PDF
                    </button>
                    <?php if (!$verify_mode): ?>
                    <button class="official-btn secondary" onclick="shareCertificate()">
                        <i class="fas fa-share-alt"></i> Share Certificate
                    </button>
                    <a href="certificate.php?cert=<?php echo urlencode($certificate['Certificate_Number']); ?>&verify=1" 
                       class="official-btn secondary" target="_blank">
                        <i class="fas fa-shield-alt"></i> Verify Online
                    </a>
                    <?php endif; ?>
                </div>
                
                <script>
                function downloadCertificate() {
                    alert('Generating official PDF certificate...\nThis feature requires server-side implementation.');
                    // 实际应用中，这里会调用后端生成PDF
                }
                
                function shareCertificate() {
                    const shareData = {
                        title: 'Official Donation Certificate - Love Bridge Foundation',
                        text: 'I received an official donation certificate from Love Bridge Foundation for my charitable contribution!',
                        url: window.location.href
                    };
                    
                    if (navigator.share) {
                        navigator.share(shareData);
                    } else {
                        // 备用方案
                        const textArea = document.createElement('textarea');
                        textArea.value = shareData.url;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        alert('Certificate link copied to clipboard!');
                    }
                }
                </script>
                
            <?php elseif ($donor_id && empty($certificate) && $certificate_id == 0 && $certificate_number == ''): ?>
                <!-- 显示用户的证书列表 -->
                <div class="certificates-list">
                    <h2 class="section-title">Your Official Certificates</h2>
                    
                    <?php if (count($all_certificates) > 0): ?>
                        <div style="margin-bottom: 30px; padding: 20px; background: #fff; border-radius: 8px; border-left: 4px solid var(--primary-red);">
                            <h3 style="color: var(--dark-red); margin-bottom: 10px;">Certificate Statistics</h3>
                            <p>
                                You have <strong><?php echo count($all_certificates); ?> official certificate(s)</strong> 
                                issued by Love Bridge Foundation recognizing your philanthropic contributions.
                            </p>
                        </div>
                        
                        <div class="certificate-grid">
                            <?php foreach ($all_certificates as $cert): ?>
                                <div class="certificate-card">
                                    <div class="card-header">
                                        <div class="card-type"><?php echo htmlspecialchars($cert['Certificate_Type']); ?></div>
                                        <h3><?php echo htmlspecialchars($cert['Certificate_Title']); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-detail">
                                            <span class="card-label">Certificate No:</span>
                                            <span class="card-value"><?php echo htmlspecialchars($cert['Certificate_Number']); ?></span>
                                        </div>
                                        <div class="card-detail">
                                            <span class="card-label">Issued Date:</span>
                                            <span class="card-value"><?php echo date('M j, Y', strtotime($cert['Issued_Date'])); ?></span>
                                        </div>
                                        <?php if ($cert['Donation_Amount']): ?>
                                        <div class="card-detail">
                                            <span class="card-label">Contribution:</span>
                                            <span class="card-value" style="color: var(--dark-red);">RM <?php echo number_format($cert['Donation_Amount'], 2); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="card-detail">
                                            <span class="card-label">Certificate Type:</span>
                                            <span class="card-value"><?php echo htmlspecialchars($cert['Certificate_Type']); ?></span>
                                        </div>
                                        <div class="card-detail">
                                            <span class="card-label">Status:</span>
                                            <span class="card-value">
                                                <?php if ($cert['Certificate_Status'] == 'Active'): ?>
                                                    <span style="color: green;"><i class="fas fa-check-circle"></i> Active</span>
                                                <?php elseif ($cert['Certificate_Status'] == 'Expired'): ?>
                                                    <span style="color: orange;"><i class="fas fa-clock"></i> Expired</span>
                                                <?php else: ?>
                                                    <span style="color: red;"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($cert['Certificate_Status']); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <a href="certificate.php?id=<?php echo $cert['Certificate_ID']; ?>" class="view-cert-btn">
                                            <i class="fas fa-award"></i> View Official Certificate
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- 没有证书 -->
                        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                            <i class="fas fa-certificate" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                            <h3 style="color: #666; margin-bottom: 15px;">No Official Certificates Yet</h3>
                            <p style="color: #888; max-width: 600px; margin: 0 auto 30px; line-height: 1.6;">
                                You haven't received any official certificates from Love Bridge Foundation yet. 
                                Official certificates are automatically issued for qualifying donations and are 
                                part of our formal recognition program for donors.
                            </p>
                            <p style="margin-bottom: 30px;">
                                <a href="Campaign_Page.php" class="official-btn" style="display: inline-block;">
                                    <i class="fas fa-hand-holding-heart"></i> Make a Donation
                                </a>
                            </p>
                            <p style="font-size: 0.9rem; color: #999;">
                                <i class="fas fa-info-circle"></i> Certificates are typically issued within 3-5 business days after donation confirmation.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- 证书未找到或未登录 -->
                <div style="text-align: center; padding: 80px 20px; background: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #ff6b4a; margin-bottom: 20px;"></i>
                    <h3 style="color: #333; margin-bottom: 15px;">Certificate Access</h3>
                    <p style="color: #666; max-width: 600px; margin: 0 auto 30px; line-height: 1.6;">
                        <?php 
                        if (!$logged_in) {
                            echo 'Please log in to access your official donation certificates from Love Bridge Foundation.';
                        } else {
                            echo 'The official certificate you are looking for is not available or you do not have permission to view it.';
                        }
                        ?>
                    </p>
                    <?php if (!$logged_in): ?>
                        <a href="donor_login.php" class="official-btn" style="display: inline-block; margin: 10px;">
                            <i class="fas fa-sign-in-alt"></i> Donor Login
                        </a>
                    <?php else: ?>
                        <a href="certificate.php" class="official-btn" style="display: inline-block; margin: 10px;">
                            <i class="fas fa-certificate"></i> View All Certificates
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // 页面加载时，如果有证书ID，滚动到证书显示区域
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const certId = urlParams.get('id');
            const certNumber = urlParams.get('cert');
            
            if (certId || certNumber) {
                const certDisplay = document.querySelector('.organization-certificate');
                if (certDisplay) {
                    setTimeout(() => {
                        certDisplay.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 500);
                }
            }
            
            // 打印按钮提示
            const printBtn = document.querySelector('.official-btn[onclick*="print"]');
            if (printBtn) {
                printBtn.addEventListener('click', function() {
                    // 添加打印提示
                    const printMsg = document.createElement('div');
                    printMsg.innerHTML = '<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,0.3);z-index:9999;text-align:center;"><p>Preparing official certificate for printing...</p><p><i class="fas fa-spinner fa-spin"></i></p></div>';
                    document.body.appendChild(printMsg);
                    setTimeout(() => printMsg.remove(), 2000);
                });
            }
        });
    </script>
</body>
</html>