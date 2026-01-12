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

// 3. 获取钱包交易记录 (严格匹配你的字段名)
$history_sql = "
    SELECT wt.Wallet_Trans_ID, wt.Transaction_Type, wt.Amount, wt.Created_At,
           o.Order_Type, sc.Case_Title, act.Activity_Name
    FROM wallet_transaction wt
    LEFT JOIN orders o ON wt.Order_ID = o.Order_ID
    LEFT JOIN special_case sc ON o.Case_ID = sc.Case_ID
    LEFT JOIN activity act ON o.Activity_ID = act.Activity_ID
    WHERE wt.Donor_ID = ? 
    ORDER BY wt.Created_At DESC
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
    .wallet-card { background: linear-gradient(135deg, #0057B7 0%, #003A7A 100%); color: white; border-radius: 20px; padding: 40px; box-shadow: 0 10px 30px rgba(0, 87, 183, 0.3); display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
    .wallet-info h1 { margin: 10px 0 0 0; font-size: 3.5rem; }
    .btn-topup { background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.5); color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: bold; }
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
        <?php if ($history && $history->num_rows > 0): ?>
            <?php while($row = $history->fetch_assoc()): ?>
                <?php 
                    $is_topup = ($row['Transaction_Type'] === 'Top-up');
                    if ($is_topup) {
                        $icon_cls = 'icon-in'; $icon_nm = 'fa-arrow-down';
                        $amt_sgn = '+'; $amt_cls = 'amt-plus';
                        $title = "Wallet Top-up";
                        $detail = "Earned Points: " . floor($row['Amount']);
                    } else {
                        $icon_cls = 'icon-out'; $icon_nm = 'fa-arrow-up';
                        $amt_sgn = '-'; $amt_cls = 'amt-minus';
                        if(!empty($row['Case_Title'])) $title = "Donation: " . $row['Case_Title'];
                        elseif(!empty($row['Activity_Name'])) $title = "Donation: " . $row['Activity_Name'];
                        else $title = "General Donation";
                        $detail = "Spent via E-Wallet";
                    }
                ?>
                <div class="txn-item">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <div class="txn-icon <?php echo $icon_cls; ?>">
                            <i class="fas <?php echo $icon_nm; ?>"></i>
                        </div>
                        <div>
                            <h5 style="margin:0; font-size:1rem;"><?php echo htmlspecialchars($title); ?></h5>
                            <p style="margin:0; font-size:0.8rem; color:#888;">
                                <?php echo date("d M Y, h:i A", strtotime($row['Created_At'])); ?> &bull; <?php echo $detail; ?>
                            </p>
                        </div>
                    </div>
                    <div class="<?php echo $amt_cls; ?>">
                        <?php echo $amt_sgn; ?> RM <?php echo number_format($row['Amount'], 2); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="padding:40px; text-align:center; color:#999;">No transactions found in your wallet.</div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>