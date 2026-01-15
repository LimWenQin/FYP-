<?php
// admin_withdrawal_details.php
session_start();

// 检查登录状态
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    die("Access Denied. Please login.");
}

include 'dataconnection.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Withdrawal ID.");
}

$withdrawalId = intval($_GET['id']);

// 获取 Withdrawal 详细信息
$sql = "SELECT w.*, 
        b.Branch_Name, 
        s.Case_Title, 
        a.Activity_Name, 
        ad.Admin_Name as Processor_Name
        FROM withdrawals w
        LEFT JOIN branch b ON w.Branch_ID = b.Branch_ID
        LEFT JOIN special_case s ON w.Case_ID = s.Case_ID
        LEFT JOIN activity a ON w.Activity_ID = a.Activity_ID
        LEFT JOIN admin ad ON w.Admin_ID = ad.Admin_ID
        WHERE w.Withdrawal_ID = $withdrawalId";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Withdrawal record not found.");
}

$row = $result->fetch_assoc();

// --- 逻辑处理：状态颜色 ---
$statusClass = 'text-warning'; 
$status = ucfirst($row['Status']);
if ($status == 'Approved' || $status == 'Completed') $statusClass = 'text-success';
if ($status == 'Rejected') $statusClass = 'text-danger';

// --- 逻辑处理：提款来源 ---
$sourceLabel = "Fund Source";
$sourceName = $row['Branch_Name'] . " (General Fund)"; 

if (!empty($row['Case_Title'])) {
    $sourceLabel = "Special Case Fund";
    $sourceName = $row['Case_Title'];
} elseif (!empty($row['Activity_Name'])) {
    $sourceLabel = "Activity Fund";
    $sourceName = $row['Activity_Name'];
}

// --- 逻辑处理：日期格式化 ---
$requestDate = date('d M Y, h:i A', strtotime($row['Request_Date']));
$processedDate = !empty($row['Processed_Date']) ? date('d M Y, h:i A', strtotime($row['Processed_Date'])) : '-';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Details - #<?php echo $row['Withdrawal_ID']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        body { background: #f4f6f9; padding: 40px; font-family: 'Poppins', sans-serif; }
        
        .details-container { 
            max-width: 1100px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            overflow: hidden; 
        }

        .details-header { background: #F28585; color: white; padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .details-header h2 { margin: 0; font-size: 24px; }
        
        .details-body { padding: 40px; }
        
        .info-group { margin-bottom: 35px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .info-group:last-child { border-bottom: none; margin-bottom: 0; }
        
        .info-group h3 { font-size: 18px; color: #555; margin-bottom: 20px; border-left: 5px solid #F28585; padding-left: 15px; }
        
        .info-list { display: flex; flex-direction: column; gap: 0; }
        .info-item { display: flex; align-items: flex-start; border-bottom: 1px dashed #f0f0f0; padding: 12px 0; }
        
        .label { width: 220px; min-width: 220px; color: #888; font-weight: 500; font-size: 15px; margin-top: 5px; }
        .colon { width: 20px; text-align: center; color: #888; font-weight: 500; margin-right: 15px; margin-top: 5px; }
        .value { font-weight: 600; color: #333; font-size: 16px; flex-grow: 1; }
        
        .amount-display { font-size: 32px; color: #dc3545; font-weight: bold; text-align: center; margin: 10px 0 40px 0; padding: 20px; background: #fff5f5; border-radius: 8px; border: 1px solid #ffcdd2; }
        
        .text-success { color: #28a745; } .text-danger { color: #dc3545; } .text-warning { color: #856404; }

        .action-buttons { margin-top: 30px; display: flex; justify-content: space-between; align-items: center; }
        .btn-print { background: #F28585; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s; }
        .btn-print:hover { background: #e07070; }
        .btn-back { background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s; text-decoration: none; }
        .btn-back:hover { background: #5a6268; }

        /* --- 缩略图列表样式 --- */
        .proof-gallery { display: flex; flex-wrap: wrap; gap: 10px; }
        .proof-item { 
            cursor: pointer; position: relative; display: block; 
            border: 1px solid #ddd; padding: 4px; border-radius: 5px; 
            background: #fff; transition: transform 0.2s, box-shadow 0.2s; 
        }
        .proof-item:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-color: #F28585; }
        .proof-img { width: 150px; height: 150px; object-fit: cover; border-radius: 3px; display: block; }
        .proof-pdf { 
            width: 150px; height: 100px; display: flex; flex-direction: column; 
            align-items: center; justify-content: center; background: #fdf2f2; 
            color: #dc3545; font-size: 14px; 
        }
        .proof-pdf i { font-size: 30px; margin-bottom: 8px; }

        /* --- 全屏查看 Modal 样式 --- */
        .modal-overlay {
            display: none; /* 默认隐藏 */
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: rgba(0,0,0,0.9); /* 黑色背景 */
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        /* 关闭按钮：右上角 */
        .modal-close-btn {
            position: absolute;
            top: 20px;
            right: 30px; /* 改为右上角 */
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 10001;
            background: rgba(0,0,0,0.5);
            width: 50px;
            height: 50px;
            line-height: 45px;
            text-align: center;
            border-radius: 50%;
        }
        .modal-close-btn:hover { color: #F28585; background: rgba(255,255,255,0.2); }

        /* 图片容器 */
        .modal-content-img {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90vh;
            border: 2px solid white;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }

        /* PDF 容器 */
        .modal-content-pdf {
            width: 80%;
            height: 90vh;
            background: white;
            border: none;
        }

        @media print {
            .details-container { box-shadow: none; max-width: 100%; }
            .btn-print, .btn-back, .modal-overlay { display: none; }
        }
    </style>
</head>
<body>
    <div class="details-container">
        <div class="details-header">
            <h2>Withdrawal Details</h2>
            <span>ID: #<?php echo $row['Withdrawal_ID']; ?></span>
        </div>
        <div class="details-body">
            <div class="amount-display">
                - RM <?php echo number_format($row['Amount'], 2); ?>
            </div>

            <div class="info-group">
                <h3>Transaction Information</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Withdrawal From</span><span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($sourceName); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Branch Name</span><span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($row['Branch_Name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Status</span><span class="colon">:</span>
                        <span class="value <?php echo $statusClass; ?>"><?php echo strtoupper($status); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Request Date</span><span class="colon">:</span>
                        <span class="value"><?php echo $requestDate; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Processed Date</span><span class="colon">:</span>
                        <span class="value"><?php echo $processedDate; ?></span>
                    </div>
                </div>
            </div>

            <div class="info-group">
                <h3>Banking Details</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Bank Name</span><span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($row['Bank_Name'] ?: '-'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Account Number</span><span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($row['Bank_Account'] ?: '-'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Reference / Proof</span>
                        <span class="colon">:</span>
                        <span class="value">
                            <?php if(!empty($row['Reference_Proof'])): ?>
                                <div class="proof-gallery">
                                    <?php 
                                    $rawProof = $row['Reference_Proof'];
                                    $proofFiles = json_decode($rawProof, true);
                                    if (!is_array($proofFiles)) { $proofFiles = array($rawProof); }

                                    foreach($proofFiles as $filePath):
                                        $filePath = trim($filePath);
                                        $displayPath = str_replace('\\', '/', $filePath); 
                                        $ext = strtolower(pathinfo($displayPath, PATHINFO_EXTENSION));
                                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                        $type = $isImage ? 'image' : 'pdf';
                                    ?>
                                        <?php if($isImage): ?>
                                            <div class="proof-item" onclick="openModal('<?php echo $displayPath; ?>', 'image')" title="Click to view">
                                                <img src="<?php echo $displayPath; ?>" alt="Proof" class="proof-img">
                                            </div>
                                        <?php else: ?>
                                            <div class="proof-item proof-pdf" onclick="openModal('<?php echo $displayPath; ?>', 'pdf')" title="Click to view PDF">
                                                <i class="fas fa-file-pdf"></i>
                                                <span>View PDF</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#aaa;">N/A</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="info-group">
                <h3>Internal Processing</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Processed By</span><span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($row['Processor_Name'] ?: 'System / Pending'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Withdrawal ID</span><span class="colon">:</span>
                        <span class="value">#<?php echo $row['Withdrawal_ID']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn-back" onclick="window.close();">
                    <i class="fas fa-arrow-left"></i> Back to Payment Management
                </button>
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </div>
    </div>

    <div id="fileModal" class="modal-overlay">
        <span class="modal-close-btn" onclick="closeModal()">&times;</span>
        
        <img class="modal-content-img" id="modalImg" style="display:none;">
        
        <iframe class="modal-content-pdf" id="modalPdf" style="display:none;"></iframe>
    </div>

    <script>
        // 打开 Modal 函数
        function openModal(src, type) {
            var modal = document.getElementById("fileModal");
            var img = document.getElementById("modalImg");
            var pdf = document.getElementById("modalPdf");

            // 显示 Modal
            modal.style.display = "flex";

            if (type === 'image') {
                img.src = src;
                img.style.display = "block";
                pdf.style.display = "none";
                pdf.src = ""; // 清空 PDF 以停止加载
            } else {
                pdf.src = src;
                pdf.style.display = "block";
                img.style.display = "none";
                img.src = "";
            }
        }

        // 关闭 Modal 函数
        function closeModal() {
            var modal = document.getElementById("fileModal");
            var img = document.getElementById("modalImg");
            var pdf = document.getElementById("modalPdf");

            modal.style.display = "none";
            img.src = ""; // 清理资源
            pdf.src = ""; // 清理资源
        }

        // 点击背景也可以关闭（可选）
        document.getElementById("fileModal").onclick = function(e) {
            if (e.target === this) {
                closeModal();
            }
        }
    </script>
</body>
</html>