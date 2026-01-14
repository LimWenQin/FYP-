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

// 获取 Withdrawal 详细信息，关联 Branch, Special Case, Activity 和 Admin 表
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
$statusClass = 'text-warning'; // Default Pending
$status = ucfirst($row['Status']);
if ($status == 'Approved' || $status == 'Completed') $statusClass = 'text-success';
if ($status == 'Rejected') $statusClass = 'text-danger';

// --- 逻辑处理：提款来源 (Source/Context) ---
$sourceLabel = "Fund Source";
$sourceName = $row['Branch_Name'] . " (General Fund)"; // Default

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
        
        /* 容器宽度 */
        .details-container { 
            max-width: 1100px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            overflow: hidden; 
        }

        /* Header Background - Pink #F28585 */
        .details-header { background: #F28585; color: white; padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .details-header h2 { margin: 0; font-size: 24px; }
        
        .details-body { padding: 40px; }
        
        .info-group { margin-bottom: 35px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .info-group:last-child { border-bottom: none; margin-bottom: 0; }
        
        /* Left Border Color - Pink #F28585 */
        .info-group h3 { font-size: 18px; color: #555; margin-bottom: 20px; border-left: 5px solid #F28585; padding-left: 15px; }
        
        /* 列表布局 */
        .info-list { display: flex; flex-direction: column; gap: 0; }

        .info-item { 
            display: flex; 
            align-items: center; 
            border-bottom: 1px dashed #f0f0f0; 
            padding: 12px 0; 
        }
        
        /* Label & Colon & Value */
        .label { width: 220px; min-width: 220px; color: #888; font-weight: 500; font-size: 15px; }
        .colon { width: 20px; text-align: center; color: #888; font-weight: 500; margin-right: 15px; }
        .value { font-weight: 600; color: #333; font-size: 16px; flex-grow: 1; }
        
        /* Amount Display - Text Color Pink #F28585 */
        .amount-display { font-size: 32px; color: #dc3545; font-weight: bold; text-align: center; margin: 10px 0 40px 0; padding: 20px; background: #fff5f5; border-radius: 8px; border: 1px solid #ffcdd2; }
        
        .text-success { color: #28a745; } .text-danger { color: #dc3545; } .text-warning { color: #856404; }

        .action-buttons { margin-top: 30px; display: flex; justify-content: space-between; align-items: center; }

        /* Print Button */
        .btn-print { 
            background: #F28585; color: white; border: none; padding: 12px 25px; border-radius: 6px; 
            cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s;
        }
        .btn-print:hover { background: #e07070; }

        /* Back Button */
        .btn-back {
            background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 6px; 
            cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s; text-decoration: none;
        }
        .btn-back:hover { background: #5a6268; }

        @media print {
            body { padding: 0; background: white; }
            .details-container { box-shadow: none; max-width: 100%; margin: 0; border-radius: 0; }
            .details-header { padding: 15px 20px; -webkit-print-color-adjust: exact; }
            .details-body { padding: 20px; }
            .btn-print, .btn-back { display: none; }
            .amount-display { border: 1px solid #ddd; }
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
                        <span class="label">Withdrawal From</span>
                        <span class="colon">:</span>
                        <span class="value">
                            <?php echo htmlspecialchars($sourceName); ?> 
                            <span style="font-size: 13px; color: #888; font-weight: normal; margin-left: 5px;">(<?php echo $sourceLabel; ?>)</span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label">Branch Name</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($row['Branch_Name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Status</span>
                        <span class="colon">:</span>
                        <span class="value <?php echo $statusClass; ?>"><?php echo strtoupper($status); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Request Date</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $requestDate; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Processed Date</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $processedDate; ?></span>
                    </div>
                </div>
            </div>

            <div class="info-group">
                <h3>Banking Details</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Bank Name</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($row['Bank_Name'] ?: '-'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Account Number</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($row['Bank_Account'] ?: '-'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Reference / Proof</span>
                        <span class="colon">:</span>
                        <span class="value">
                            <?php if(!empty($row['Reference_Proof'])): ?>
                                <?php echo htmlspecialchars($row['Reference_Proof']); ?>
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
                        <span class="label">Processed By</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($row['Processor_Name'] ?: 'System / Pending'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Withdrawal ID</span>
                        <span class="colon">:</span>
                        <span class="value">#<?php echo $row['Withdrawal_ID']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn-back" onclick="window.close();">
                    <i class="fas fa-arrow-left"></i> Back to Management
                </button>
                
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </div>
    </div>
</body>
</html>