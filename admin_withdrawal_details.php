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
// Join admin 表两次，一次是 Requester (w.Admin_ID), 一次是 Approver (w.Approved_By)
$sql = "SELECT w.*, 
        b.Branch_Name, 
        s.Case_Title, 
        a.Activity_Name, 
        req.Admin_Name as Requester_Name,
        app.Admin_Name as Approver_Name
        FROM withdrawals w
        LEFT JOIN branch b ON w.Branch_ID = b.Branch_ID
        LEFT JOIN special_case s ON w.Case_ID = s.Case_ID
        LEFT JOIN activity a ON w.Activity_ID = a.Activity_ID
        LEFT JOIN admin req ON w.Admin_ID = req.Admin_ID
        LEFT JOIN admin app ON w.Approved_By = app.Admin_ID
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
        
        /* Back 按钮样式，做成按钮的样子但其实是链接 */
        .btn-back { background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s; text-decoration: none; }
        .btn-back:hover { background: #5a6268; }

        /* --- 缩略图列表样式 --- */
        .proof-gallery { display: flex; flex-wrap: wrap; gap: 15px; }
        .proof-item { 
            cursor: pointer; position: relative; display: block; 
            border: 1px solid #ddd; padding: 5px; border-radius: 8px; 
            background: #fff; transition: transform 0.2s, box-shadow 0.2s; 
            width: 120px; height: 120px;
            overflow: hidden;
        }
        .proof-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); border-color: #F28585; }
        
        .proof-img { width: 100%; height: 100%; object-fit: cover; border-radius: 4px; display: block; }
        
        .proof-pdf { 
            width: 100%; height: 100%; display: flex; flex-direction: column; 
            align-items: center; justify-content: center; background: #f8f9fa; 
            color: #dc3545; font-size: 12px; font-weight: 600; text-align: center;
        }
        .proof-pdf i { font-size: 32px; margin-bottom: 8px; }

        /* --- 全屏查看 Modal 样式 --- */
        .modal-overlay {
            display: none; /* 默认隐藏 */
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.85); /* 深色背景 */
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        /* 顶部工具栏 */
        .modal-toolbar {
            position: absolute;
            top: 0; left: 0; right: 0;
            padding: 15px 30px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            z-index: 10002;
        }

        .modal-btn {
            color: white;
            font-size: 16px;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: 0.3s;
            display: flex; align-items: center; gap: 8px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .modal-btn:hover { background: rgba(255,255,255,0.4); }
        .btn-close { background: #dc3545; border-color: #dc3545; }
        .btn-close:hover { background: #c82333; }

        /* 图片容器 */
        .modal-content-img {
            max-width: 90%;
            max-height: 85vh;
            border-radius: 4px;
            box-shadow: 0 0 30px rgba(0,0,0,0.5);
            margin-top: 40px;
        }

        /* PDF 容器 */
        .modal-content-pdf {
            width: 80%;
            height: 85vh;
            background: white;
            border-radius: 4px;
            box-shadow: 0 0 30px rgba(0,0,0,0.5);
            margin-top: 40px;
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
                        <span class="value">
                            <?php echo $requestDate; ?>
                            <br><span style="font-size:12px; color:#888;">(Requested by: <?php echo htmlspecialchars($row['Requester_Name']); ?>)</span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label">Processed Date</span><span class="colon">:</span>
                        <span class="value">
                            <?php echo $processedDate; ?>
                            <?php if($row['Processed_Date']): ?>
                                <br><span style="font-size:12px; color:#888;">(Approved by: <?php echo htmlspecialchars($row['Approver_Name']); ?>)</span>
                            <?php else: ?>
                                <br><span style="font-size:12px; color:#888;">(Pending)</span>
                            <?php endif; ?>
                        </span>
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
                                    // 尝试解析 JSON，如果失败则视为单个路径
                                    $proofFiles = json_decode($rawProof, true);
                                    if (!is_array($proofFiles)) { 
                                        $proofFiles = array($rawProof); 
                                    }

                                    foreach($proofFiles as $filePath):
                                        $filePath = trim($filePath);
                                        // 跳过空路径，防止显示幽灵图标
                                        if (empty($filePath)) continue;

                                        $displayPath = str_replace('\\', '/', $filePath); 
                                        $ext = strtolower(pathinfo($displayPath, PATHINFO_EXTENSION));
                                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                        $type = $isImage ? 'image' : 'pdf';
                                    ?>
                                        <?php if($isImage): ?>
                                            <div class="proof-item" onclick="openModal('<?php echo $displayPath; ?>', 'image')" title="View Image">
                                                <img src="<?php echo $displayPath; ?>" alt="Proof" class="proof-img">
                                            </div>
                                        <?php else: ?>
                                            <div class="proof-item proof-pdf" onclick="openModal('<?php echo $displayPath; ?>', 'pdf')" title="View PDF">
                                                <i class="fas fa-file-pdf"></i>
                                                <span>View PDF</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#aaa;">No proof documents uploaded.</span>
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
                        <span class="value"><?php echo htmlspecialchars($row['Approver_Name'] ?: 'System / Pending'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Withdrawal ID</span><span class="colon">:</span>
                        <span class="value">#<?php echo $row['Withdrawal_ID']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="branch_withdrawal_history.php?branch_id=<?php echo $row['Branch_ID']; ?>" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </div>
    </div>

    <div id="fileModal" class="modal-overlay">
        <div class="modal-toolbar">
            <a id="downloadBtn" href="#" download class="modal-btn" title="Download File">
                <i class="fas fa-download"></i> Download
            </a>
            <span class="modal-btn btn-close" onclick="closeModal()">
                <i class="fas fa-times"></i> Close
            </span>
        </div>
        
        <img class="modal-content-img" id="modalImg" style="display:none;">
        <iframe class="modal-content-pdf" id="modalPdf" style="display:none;"></iframe>
    </div>

    <script>
        // 打开 Modal 函数
        function openModal(src, type) {
            var modal = document.getElementById("fileModal");
            var img = document.getElementById("modalImg");
            var pdf = document.getElementById("modalPdf");
            var dlBtn = document.getElementById("downloadBtn");

            // 设置下载链接
            dlBtn.href = src;

            // 显示 Modal
            modal.style.display = "flex";

            if (type === 'image') {
                img.src = src;
                img.style.display = "block";
                pdf.style.display = "none";
                pdf.src = ""; // 清空 PDF 防止后台播放
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

        // 点击背景关闭
        document.getElementById("fileModal").onclick = function(e) {
            if (e.target === this) {
                closeModal();
            }
        }
    </script>
</body>
</html>