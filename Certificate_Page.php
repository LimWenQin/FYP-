<?php
// 连接数据库
include 'dataconnection.php';

// 获取证书编号 (通过 URL 参数，例如: certificate_page.php?code=VOL-2026-888)
$cert_code = isset($_GET['code']) ? $_GET['code'] : '';

$certificate = null;

if (!empty($cert_code)) {
    // 关联 Activity 表以获取活动名称
    $sql = "SELECT gc.*, a.Activity_Name, a.Activity_Venue 
            FROM general_certificates gc
            LEFT JOIN activity a ON gc.Activity_ID = a.Activity_ID
            WHERE gc.Cert_Code = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cert_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $certificate = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Certificate - Love Bridge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=Great+Vibes&family=Merriweather:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-red: #8b1a00ff;
            --gold: #d4af37;
            --ivory: #fffff0;
        }

        body {
            background-color: #f0f0f0;
            margin: 0;
            padding: 20px;
            font-family: 'Merriweather', serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* 证书主体 (A4 横向比例) */
        .cert-container {
            width: 1000px; /* A4 width roughly */
            height: 700px; /* A4 height roughly */
            background-color: #fff;
            background-image: url('https://www.transparenttextures.com/patterns/parchment.png');
            position: relative;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            box-sizing: border-box;
            text-align: center;
            border: 15px solid #fff;
            outline: 4px solid var(--gold);
        }

        /* 内部边框装饰 */
        .inner-border {
            width: 100%;
            height: 100%;
            border: 2px solid var(--primary-red);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        /* 角落花纹 */
        .corner {
            position: absolute;
            width: 40px;
            height: 40px;
            border-style: double;
            border-color: var(--gold);
        }
        .top-left { top: 10px; left: 10px; border-width: 4px 0 0 4px; }
        .top-right { top: 10px; right: 10px; border-width: 4px 4px 0 0; }
        .bottom-left { bottom: 10px; left: 10px; border-width: 0 0 4px 4px; }
        .bottom-right { bottom: 10px; right: 10px; border-width: 0 4px 4px 0; }

        /* 头部 */
        .header-section {
            margin-bottom: 20px;
        }
        
        .org-name {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            letter-spacing: 3px;
            color: #555;
            text-transform: uppercase;
            font-weight: 700;
        }

        .cert-title {
            font-family: 'Playfair Display', serif;
            font-size: 56px;
            color: var(--primary-red);
            margin: 10px 0;
            font-weight: 900;
            text-transform: uppercase;
            line-height: 1;
        }

        .subtitle {
            font-size: 18px;
            font-style: italic;
            color: #666;
            margin-bottom: 20px;
        }

        /* 接收者名字 */
        .recipient-name {
            font-family: 'Great Vibes', cursive; /* 手写风格 */
            font-size: 80px;
            color: #222;
            margin: 10px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
            display: inline-block;
            min-width: 500px;
        }

        /* 正文内容 */
        .cert-body {
            font-size: 18px;
            line-height: 1.6;
            color: #444;
            max-width: 750px;
            margin: 0 auto 40px auto;
        }

        .highlight {
            font-weight: bold;
            color: var(--primary-red);
        }

        /* 底部签名区 */
        .footer-section {
            display: flex;
            justify-content: space-around;
            width: 80%;
            margin-top: 40px;
        }

        .signature-block {
            text-align: center;
        }

        .sign-img {
            height: 60px; 
            margin-bottom: -10px;
            /* 这里可以放签名图片 */
            font-family: 'Great Vibes', cursive;
            font-size: 30px;
            color: #000;
        }

        .sign-line {
            width: 250px;
            height: 1px;
            background-color: #333;
            margin: 5px auto;
        }

        .sign-name {
            font-weight: bold;
            font-size: 16px;
            margin-top: 5px;
        }

        .sign-role {
            font-size: 14px;
            color: #777;
            font-style: italic;
        }

        /* 印章 */
        .seal {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 100px;
            border: 3px solid var(--primary-red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-red);
            font-weight: bold;
            opacity: 0.2;
            font-family: 'Playfair Display', serif;
            font-size: 14px;
            text-align: center;
        }

        .cert-code {
            position: absolute;
            bottom: 15px;
            right: 20px;
            font-size: 10px;
            color: #aaa;
            font-family: monospace;
        }

        /* 打印按钮 - 打印时会隐藏 */
        .action-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            background: var(--primary-red);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-family: sans-serif;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .btn:hover {
            opacity: 0.9;
        }

        /* 错误提示样式 */
        .error-container {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* 打印模式 CSS */
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
                display: block;
            }
            .cert-container {
                box-shadow: none;
                margin: 0;
                width: 100%;
                height: 100vh;
                border: none;
                page-break-after: always;
            }
            .action-bar {
                display: none !important;
            }
            @page {
                size: A4 landscape;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <?php if ($certificate): ?>
        
        <div class="action-bar">
            <button class="btn" onclick="window.print()"><i class="fas fa-print"></i> Print Certificate</button>
        </div>

        <div class="cert-container">
            <div class="inner-border">
                <div class="corner top-left"></div>
                <div class="corner top-right"></div>
                <div class="corner bottom-left"></div>
                <div class="corner bottom-right"></div>

                <div class="header-section">
                    <div class="org-name"><i class="fas fa-heart" style="color:var(--primary-red)"></i> Love Bridge Foundation</div>
                    <div class="cert-title"><?php echo htmlspecialchars($certificate['Cert_Type']); ?></div>
                    <div class="subtitle">is proudly presented to</div>
                </div>

                <div class="recipient-name">
                    <?php echo htmlspecialchars($certificate['Recipient_Name']); ?>
                </div>

                <div class="cert-body">
                    <?php echo htmlspecialchars($certificate['Description']); ?>
                    <br><br>
                    <?php if ($certificate['Activity_Name']): ?>
                    In recognition of your participation in the event:<br>
                    <span class="highlight"><?php echo htmlspecialchars($certificate['Activity_Name']); ?></span>
                    <?php endif; ?>
                    <br>
                    <span style="font-size: 14px; color: #666;">
                        Given on this day, <?php echo date("F j, Y", strtotime($certificate['Issue_Date'])); ?>
                    </span>
                </div>

                <div class="seal">
                    OFFICIAL<br>SEAL
                </div>

                <div class="footer-section">
                    <div class="signature-block">
                        <div class="sign-img"><?php echo htmlspecialchars($certificate['Issuer_Name']); ?></div> 
                        <div class="sign-line"></div>
                        <div class="sign-name"><?php echo htmlspecialchars($certificate['Issuer_Name']); ?></div>
                        <div class="sign-role"><?php echo htmlspecialchars($certificate['Issuer_Role']); ?></div>
                    </div>
                </div>

                <div class="cert-code">ID: <?php echo htmlspecialchars($certificate['Cert_Code']); ?></div>
            </div>
        </div>

    <?php else: ?>
        <div class="error-container">
            <i class="fas fa-search" style="font-size: 50px; color: #ccc; margin-bottom: 20px;"></i>
            <h2>Certificate Not Found</h2>
            <p>We could not find a certificate with the provided code.</p>
            <p>Please check the link or contact Love Bridge administration.</p>
        </div>
    <?php endif; ?>

</body>
</html>