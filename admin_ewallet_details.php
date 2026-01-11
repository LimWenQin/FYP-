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

// 获取钱包交易详情 (关联 Donor 和 Order)
// 假设 wallet_transaction 的主键是 id 或 WalletTransaction_ID，这里根据之前的代码逻辑假设为 id (因为 payment_management.php 没明确写主键名，通常是 id)
// 如果你的主键是 WalletTransaction_ID，请把下方的 w.id 改为 w.WalletTransaction_ID
$sql = "SELECT w.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_Wallet, 
        o.Order_TXN_Ref, o.Order_PaymentMethod
        FROM wallet_transaction w
        JOIN donor d ON w.Donor_ID = d.Donor_ID
        LEFT JOIN orders o ON w.Order_ID = o.Order_ID
        WHERE w.id = $txnId"; // <--- 请确认这里的主键字段名

$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    die("Transaction not found.");
}

$txn = $result->fetch_assoc();

// 状态颜色逻辑
$isCredit = (strtolower($txn['Transaction_Type']) == 'credit');
$amountColor = $isCredit ? 'text-success' : 'text-danger';
$amountSign = $isCredit ? '+' : '-';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Wallet Transaction Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; padding: 40px; font-family: 'Poppins', sans-serif; margin: 0; }
        .details-container { max-width: 1100px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }
        .details-header { background: #6c757d; color: white; padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; } /* 灰色背景区分 Wallet */
        .details-header h2 { margin: 0; font-size: 24px; }
        .details-body { padding: 40px; }
        .info-group { margin-bottom: 35px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .info-group:last-child { border-bottom: none; }
        .info-group h3 { font-size: 18px; color: #555; margin-bottom: 20px; border-left: 5px solid #6c757d; padding-left: 15px; }
        .info-list { display: flex; flex-direction: column; gap: 0; }
        .info-item { display: flex; align-items: center; border-bottom: 1px dashed #f0f0f0; padding: 12px 0; }
        .label { width: 220px; min-width: 220px; color: #888; font-weight: 500; font-size: 15px; }
        .colon { width: 20px; text-align: center; color: #888; font-weight: 500; margin-right: 15px; }
        .value { font-weight: 600; color: #333; font-size: 16px; flex-grow: 1; }
        .amount-display { font-size: 32px; font-weight: bold; text-align: center; margin: 10px 0 40px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        .text-success { color: #28a745; } .text-danger { color: #dc3545; }
        .action-buttons { margin-top: 30px; display: flex; justify-content: space-between; align-items: center; }
        .btn-print { background: #007bff; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s; }
        .btn-print:hover { background: #0056b3; }
        .btn-back { background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s; text-decoration: none; }
        .btn-back:hover { background: #5a6268; }
        @media print {
            body { padding: 0; background: white; }
            .details-container { box-shadow: none; max-width: 100%; margin: 0; border-radius: 0; }
            .btn-print, .btn-back { display: none; }
        }
    </style>
</head>
<body>
    <div class="details-container">
        <div class="details-header">
            <h2>E-Wallet Details</h2>
            <span>TX ID: #<?php echo $txn['id']; ?></span>
        </div>
        <div class="details-body">
            <div class="amount-display <?php echo $amountColor; ?>">
                <?php echo $amountSign; ?> RM <?php echo number_format($txn['Amount'], 2); ?>
            </div>

            <div class="info-group">
                <h3>Transaction Info</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Date & Time</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $txn['Created_At']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Type</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo ucfirst($txn['Transaction_Type']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Description</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($txn['Description']); ?></span>
                    </div>
                    <?php if($txn['Order_TXN_Ref']): ?>
                    <div class="info-item">
                        <span class="label">Linked Order Ref</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $txn['Order_TXN_Ref']; ?></span>
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
                        <span class="label">Email</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($txn['Donor_Email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Contact</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($txn['Donor_ContactNumber']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Current Wallet Balance</span>
                        <span class="colon">:</span>
                        <span class="value" style="font-weight:bold;">RM <?php echo number_format($txn['Donor_Wallet'], 2); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn-back" onclick="window.close();"><i class="fas fa-arrow-left"></i> Close</button>
                <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Details</button>
            </div>
        </div>
    </div>
</body>
</html>