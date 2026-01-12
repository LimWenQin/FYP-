<?php
session_start();
include 'dataconnection.php';

// 1. 检查登录状态
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id'];

// 2. 获取当前用户钱包余额
$stmt = $conn->prepare("SELECT Donor_Wallet, Donor_Name FROM donor WHERE Donor_ID = ?");
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$balance = $user['Donor_Wallet'] ?? 0.00;
$stmt->close();

// 3. 获取特定的交易记录 (兼容性增强版)
$history_sql = "
    SELECT p.Payment_Created_At, p.Payment_Amount, p.Payment_Method, p.Payment_TXN_Ref, 
           o.Order_Type, o.Case_ID, o.Activity_ID
    FROM payment p
    JOIN orders o ON p.Payment_ID = o.Payment_ID
    WHERE o.Donor_ID = ? 
    AND (
        o.Order_Type LIKE '%Top%up%' 
        OR p.Payment_Method = 'E-Wallet'
        OR (o.Case_ID IS NULL AND o.Activity_ID IS NULL AND o.Order_Type != 'Reward' AND o.Order_Type != 'Item')
    )
    ORDER BY p.Payment_Created_At DESC
";
$hist_stmt = $conn->prepare($history_sql);
$hist_stmt->bind_param("i", $current_donor_id);
$hist_stmt->execute();
$history = $hist_stmt->get_result();

include 'header_UI.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .wallet-container { max-width: 800px; margin: 50px auto; padding: 20px; font-family: 'Segoe UI', sans-serif; }
    .wallet-card {
        background: linear-gradient(135deg, #0057B7 0%, #003A7A 100%);
        color: white; border-radius: 20px; padding: 40px;
        box-shadow: 0 10px 30px rgba(0, 87, 183, 0.3);
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 40px;
    }
    .wallet-info h1 { margin: 10px 0 0 0; font-size: 3.5rem; }
    .btn-topup {
        background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.5);
        color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: bold;
    }
    .btn-topup:hover { background: white; color: #0057B7; }
    .txn-list { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .txn-item { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #f0f0f0; }
    .txn-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .icon-in { background-color: #d1fae5; color: #059669; }
    .icon-out { background-color: #fee2e2; color: #dc2626; }
    .amt-plus { color: #059669; font-weight: 700; }
    .amt-minus { color: #dc2626; font-weight: 700; }
</style>

<div class="wallet-container">
    <div class="wallet-card">
        <div class="wallet-info">
            <p style="margin:0; opacity:0.8;">Current Balance</p>
            <h1>RM <?php echo number_format($balance, 2); ?></h1>
        </div>
        <a href="Top-Up.php" class="btn-topup"><i class="fas fa-plus"></i> Top Up</a>
    </div>

    <h3 style="margin-bottom:20px; border-left:5px solid #0057B7; padding-left:15px;">Transaction History</h3>
    
    <div class="txn-list">
        <?php if ($history->num_rows > 0): ?>
            <?php while($row = $history->fetch_assoc()): ?>
                <?php 
                    // 重新定义判断逻辑
                    // 只要不是 E-Wallet 支付，在这个页面里我们都视作“充值进钱包”
                    $is_expense = ($row['Payment_Method'] === 'E-Wallet');
                    
                    if ($is_expense) {
                        $icon_cls = 'icon-out'; $icon_nm = 'fa-arrow-up';
                        $amt_sgn = '-'; $amt_cls = 'amt-minus';
                        if(!empty($row['Case_ID'])) $title = "Donation to Special Case";
                        elseif(!empty($row['Activity_ID'])) $title = "Donation to Activity";
                        else $title = "General Donation";
                    } else {
                        $icon_cls = 'icon-in'; $icon_nm = 'fa-arrow-down';
                        $amt_sgn = '+'; $amt_cls = 'amt-plus';
                        $title = "Wallet Top-up (" . ($row['Payment_Method'] ?: 'Manual') . ")";
                    }
                ?>
                <div class="txn-item">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <div class="txn-icon <?php echo $icon_cls; ?>">
                            <i class="fas <?php echo $icon_nm; ?>"></i>
                        </div>
                        <div>
                            <h5 style="margin:0; font-size:1rem;"><?php echo $title; ?></h5>
                            <p style="margin:0; font-size:0.8rem; color:#888;">
                                <?php echo date("d M Y, h:i A", strtotime($row['Payment_Created_At'])); ?> &bull; <?php echo $row['Payment_TXN_Ref']; ?>
                            </p>
                        </div>
                    </div>
                    <div class="<?php echo $amt_cls; ?>">
                        <?php echo $amt_sgn; ?> RM <?php echo number_format($row['Payment_Amount'], 2); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="padding:40px; text-align:center; color:#999;">No transactions found.</div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>