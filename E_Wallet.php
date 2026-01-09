<?php
session_start();
include 'dataconnection.php';

// 1. 检查登录
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id'];

// 2. 获取当前钱包余额
$stmt = $conn->prepare("SELECT Donor_Wallet, Donor_Name FROM donor WHERE Donor_ID = ?");
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$balance = $user['Donor_Wallet'] ?? 0.00;
$stmt->close();

// 3. 获取所有交易记录 (进账和出账)
$history_sql = "
    SELECT p.Payment_Created_At, p.Payment_Amount, p.Payment_Method, p.Payment_TXN_Ref, 
           o.Order_Type, o.Case_ID, o.Activity_ID, o.Branch_ID
    FROM payment p
    JOIN orders o ON p.Payment_ID = o.Payment_ID
    WHERE o.Donor_ID = ?
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
    .wallet-container {
        max-width: 800px;
        margin: 50px auto;
        padding: 20px;
    }

    /* 余额卡片 */
    .wallet-card {
        background: linear-gradient(135deg, #0057B7 0%, #003A7A 100%);
        color: white;
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 10px 30px rgba(0, 87, 183, 0.3);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }
    
    .wallet-info h4 { margin: 0; opacity: 0.9; font-weight: 400; font-size: 1.1rem; }
    .wallet-info h1 { margin: 10px 0 0 0; font-size: 3.5rem; font-weight: 700; }
    
    .btn-topup {
        background: rgba(255,255,255,0.2);
        border: 2px solid rgba(255,255,255,0.5);
        color: white;
        padding: 12px 30px;
        border-radius: 50px;
        font-size: 1rem;
        font-weight: bold;
        text-decoration: none;
        transition: all 0.3s;
        display: inline-flex; align-items: center; gap: 10px;
        backdrop-filter: blur(5px);
    }
    .btn-topup:hover { background: white; color: #0057B7; transform: translateY(-2px); text-decoration: none; }

    /* 交易列表 */
    .history-header { font-size: 1.5rem; font-weight: 700; color: #333; margin-bottom: 20px; border-left: 5px solid #dc2626; padding-left: 15px; }
    
    .txn-list { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    
    .txn-item { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #f0f0f0; transition: 0.2s; }
    .txn-item:last-child { border-bottom: none; }
    .txn-item:hover { background-color: #fafafa; }

    .txn-left { display: flex; align-items: center; gap: 20px; }
    
    .txn-icon {
        width: 50px; height: 50px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; flex-shrink: 0;
    }
    
    /* 样式区分：进钱(绿) vs 出钱(红) */
    .icon-in { background-color: #d1fae5; color: #059669; }
    .icon-out { background-color: #fee2e2; color: #dc2626; }

    .txn-details h5 { margin: 0 0 5px 0; font-size: 1rem; font-weight: 600; color: #333; }
    .txn-details p { margin: 0; font-size: 0.85rem; color: #888; }
    
    .txn-amount { font-size: 1.1rem; font-weight: 700; }
    .amt-plus { color: #059669; }
    .amt-minus { color: #dc2626; }

    @media (max-width: 768px) {
        .wallet-card { flex-direction: column; text-align: center; gap: 20px; }
        .wallet-info h1 { font-size: 2.8rem; }
    }
</style>

<div class="wallet-container">
    
    <div class="wallet-card">
        <div class="wallet-info">
            <h4>Total Balance</h4>
            <h1>RM <?php echo number_format($balance, 2); ?></h1>
            <div style="margin-top:10px; font-size:0.9rem; opacity:0.8;">User: <?php echo htmlspecialchars($user['Donor_Name']); ?></div>
        </div>
        
        <a href="Top-Up.php" class="btn-topup">
            <i class="fas fa-plus"></i> Top Up
        </a>
    </div>

    <div class="history-header">Transaction History</div>
    
    <div class="txn-list">
        <?php if ($history->num_rows > 0): ?>
            <?php while($row = $history->fetch_assoc()): ?>
                <?php 
                    // === 核心逻辑修改 ===
                    // 判断是否为 E-Wallet 支付 (支出)
                    $is_expense = ($row['Payment_Method'] === 'E-Wallet');
                    
                    if ($is_expense) {
                        // 🔴 支出 (Donate using E-Wallet) -> 红色 -100
                        $icon_cls = 'icon-out';
                        $icon_nm = 'fa-arrow-up'; // 箭头向上代表钱出去了
                        $amt_cls = 'amt-minus';
                        $amt_sgn = '-';
                        
                        // 显示捐款用途
                        if(!empty($row['Case_ID'])) $title = "Donation to Special Case";
                        elseif(!empty($row['Activity_ID'])) $title = "Donation to Activity";
                        else $title = "Donation to General Fund";
                        
                    } else {
                        // 🟢 收入 (Top-up via Card/TNG) -> 绿色 +100
                        $icon_cls = 'icon-in';
                        $icon_nm = 'fa-arrow-down'; // 箭头向下代表钱进来了
                        $amt_cls = 'amt-plus';
                        $amt_sgn = '+';
                        $title = "Wallet Top-up (" . htmlspecialchars($row['Payment_Method']) . ")";
                    }
                ?>
                
                <div class="txn-item">
                    <div class="txn-left">
                        <div class="txn-icon <?php echo $icon_cls; ?>">
                            <i class="fas <?php echo $icon_nm; ?>"></i>
                        </div>
                        <div class="txn-details">
                            <h5><?php echo $title; ?></h5>
                            <p><?php echo date("d M Y, h:i A", strtotime($row['Payment_Created_At'])); ?> &bull; <?php echo $row['Payment_TXN_Ref']; ?></p>
                        </div>
                    </div>
                    
                    <div class="txn-amount <?php echo $amt_cls; ?>">
                        <?php echo $amt_sgn; ?> RM <?php echo number_format($row['Payment_Amount'], 2); ?>
                    </div>
                </div>

            <?php endwhile; ?>
        <?php else: ?>
            <div style="padding:40px; text-align:center; color:#999;">
                <i class="fas fa-receipt fa-3x" style="margin-bottom:15px; opacity:0.3;"></i><br>
                No transactions yet.
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'footer.php'; ?>