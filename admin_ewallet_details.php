<?php
// admin_ewallet_details.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    die("Access Denied. Please login.");
}

include 'dataconnection.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Transaction ID.");
}

$txnId = intval($_GET['id']);

// 获取交易详情
$sql = "SELECT w.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_Wallet, 
        o.Order_TXN_Ref
        FROM wallet_transaction w
        JOIN donor d ON w.Donor_ID = d.Donor_ID
        LEFT JOIN orders o ON w.Order_ID = o.Order_ID
        WHERE w.Wallet_Trans_ID = $txnId";

$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    die("Transaction not found.");
}

$txn = $result->fetch_assoc();

// --- 逻辑处理：类型与颜色 ---

// 1. 获取类型，如果为空显示 (-)
$transType = $txn['Transaction_Type'];
if (empty($transType)) {
    $transType = "-";
    
    // 尝试根据描述智能推断 (仅用于显示颜色)
    if (stripos($txn['Description'], 'Top-up') !== false) {
        $inferredType = 'Credit';
    } elseif (stripos($txn['Description'], 'Donate') !== false) {
        $inferredType = 'Debit';
    } else {
        $inferredType = 'Unknown';
    }
} else {
    $inferredType = $transType;
}

// 2. 决定颜色和符号
$amountClass = 'text-dark'; // 默认黑色
$amountSign = '';

if (strtolower($inferredType) == 'credit') {
    $amountClass = 'text-success'; // 绿色
    $amountSign = '+';
} elseif (strtolower($inferredType) == 'debit') {
    $amountClass = 'text-danger'; // 红色
    $amountSign = '-';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Transaction - #<?php echo $txn['Wallet_Trans_ID']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; padding: 40px; font-family: 'Poppins', sans-serif; }
        
        /* --- 修改重点：调整了 max-width 为 700px --- */
        .details-container { 
            max-width: 700px; /* 之前是 1100px，改小一点会让看起来更紧凑，右边不会空太多 */
            margin: 0 auto; 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            overflow: hidden;
            /* 高度自适应 */
        }

        .details-header { background: #17a2b8; color: white; padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .details-header h2 { margin: 0; font-size: 24px; }
        
        .details-body { padding: 40px; }
        
        .info-group { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .info-group:last-child { border-bottom: none; margin-bottom: 0; } 
        .info-group h3 { font-size: 18px; color: #555; margin-bottom: 15px; border-left: 5px solid #17a2b8; padding-left: 15px; }
        
        .info-list { display: flex; flex-direction: column; gap: 0; }
        .info-item { display: flex; align-items: center; border-bottom: 1px dashed #f0f0f0; padding: 12px 0; }
        
        /* 稍微调整了 label 宽度以适应较窄的容器 */
        .label { width: 180px; min-width: 180px; color: #888; font-weight: 500; font-size: 15px; }
        .colon { width: 20px; text-align: center; color: #888; font-weight: 500; margin-right: 15px; }
        .value { font-weight: 600; color: #333; font-size: 15px; flex-grow: 1; word-break: break-word; }
        
        .amount-display { font-size: 32px; font-weight: bold; text-align: center; margin: 10px 0 30px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        
        .text-success { color: #28a745 !important; } 
        .text-danger { color: #dc3545 !important; } 
        .text-dark { color: #333 !important; }

        .action-buttons { margin-top: 30px; display: flex; justify-content: space-between; align-items: center; }
        
        /* 按钮样式 */
        .btn-print { background: #17a2b8; color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s; }
        .btn-print:hover { background: #138496; }
        
        .btn-back { background: #6c757d; color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s; text-decoration: none; }
        .btn-back:hover { background: #5a6268; }

        @media print {
            body { padding: 0; background: white; }
            .details-container { box-shadow: none; max-width: 100%; margin: 0; border-radius: 0; }
            .btn-print, .btn-back { display: none; }
            .amount-display { border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="details-container">
        <div class="details-header"> 
            <h2>E-Wallet Details</h2>
            <span>ID: #<?php echo $txn['Wallet_Trans_ID']; ?></span>
        </div>
        
        <div class="details-body">
            <div class="amount-display <?php echo $amountClass; ?>">
                <?php echo $amountSign; ?> RM <?php echo number_format($txn['Amount'], 2); ?>
            </div>

            <div class="info-group">
                <h3>Transaction Information</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Date & Time</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $txn['Created_At']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Transaction Type</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo ucfirst($transType); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Purpose / Description</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($txn['Description']); ?></span>
                    </div>
                    <?php if(!empty($txn['Order_TXN_Ref'])): ?>
                    <div class="info-item">
                        <span class="label">Linked Order Ref</span>
                        <span class="colon">:</span>
                        <span class="value">
                            <?php echo $txn['Order_TXN_Ref']; ?>
                            <a href="admin_payment_details.php?id=<?php echo $txn['Order_ID']; ?>" target="_blank" style="font-size: 12px; margin-left: 10px; color: #17a2b8;">(View Order)</a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-group">
                <h3>Donor Details</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Donor Name</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($txn['Donor_Name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Email Address</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($txn['Donor_Email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Contact Number</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($txn['Donor_ContactNumber']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Current Wallet Balance</span>
                        <span class="colon">:</span>
                        <span class="value">RM <?php echo number_format($txn['Donor_Wallet'], 2); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn-back" onclick="window.close();">
                    <i class="fas fa-arrow-left"></i> Close
                </button>
                
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </div>
    </div>
</body>
</html>