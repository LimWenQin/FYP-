<?php
// Payment_Ways_Page.php
session_start();
include 'dataconnection.php';

// 1. 登录检查
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id'];

// ==========================================
// 2. 接收数据并同步 Session
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['branch_id'])) {
        $_SESSION['donation_data']['branch_id'] = $_POST['branch_id'];
    }
    if(isset($_POST['amount'])) {
        $_SESSION['donation_data']['amount'] = (float)$_POST['amount'];
        $_SESSION['donation_data']['type'] = $_POST['donation_type'] ?? 'One-Time';
        $_SESSION['donation_data']['case_id'] = $_POST['case_id'] ?? 0;
        $_SESSION['donation_data']['activity_id'] = $_POST['activity_id'] ?? 0;
    }
}

if (empty($_SESSION['donation_data'])) {
    header("Location: Donate_Page.php");
    exit();
}

$amount = $_SESSION['donation_data']['amount'];
$donation_type = $_SESSION['donation_data']['type'];
$branch_id = $_SESSION['donation_data']['branch_id'] ?? 0;
$case_id = $_SESSION['donation_data']['case_id'] ?? 0;
$activity_id = $_SESSION['donation_data']['activity_id'] ?? 0;

// 获取钱包余额
$wallet_sql = "SELECT Donor_Wallet FROM donor WHERE Donor_ID = ?";
$w_stmt = $conn->prepare($wallet_sql);
$w_stmt->bind_param("i", $current_donor_id);
$w_stmt->execute();
$w_res = $w_stmt->get_result()->fetch_assoc();
$current_balance = $w_res['Donor_Wallet'] ?? 0.00;

// 4. 获取显示名称
$display_name = "General Fund (HQ)"; 
if(!empty($case_id)) {
    $res = $conn->query("SELECT Case_Title FROM special_case WHERE Case_ID = $case_id");
    if($r = $res->fetch_assoc()) $display_name = "Case: " . $r['Case_Title'];
} elseif(!empty($activity_id)) {
    $res = $conn->query("SELECT Activity_Name FROM activity WHERE Activity_ID = $activity_id");
    if($r = $res->fetch_assoc()) $display_name = "Activity: " . $r['Activity_Name'];
} elseif ($branch_id > 0) {
    $stmt = $conn->prepare("SELECT Branch_Name FROM branch WHERE Branch_ID = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) $display_name = $row['Branch_Name']; 
}

include 'header_UI.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* --- 核心变量保持与首页一致 --- */
    :root {
        --primary-red: #dc2626;
        --dark-red: #b91c1c;
        --white: #ffffff;
        --light-gray: #f5f5f5;
    }

    .hero-wrap {
        height: 500px; position: relative;
        background-image: url('images/hero_3.jpg'); background-size: cover; background-position: center;
        display: flex; align-items: center; justify-content: center; text-align: center;
    }
    .hero-wrap .overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.4); }
    .hero-content { position: relative; z-index: 2; color: #fff; }
    .hero-content h1 { font-family: 'Segoe UI', sans-serif; font-size: 3rem; margin-bottom: 5px; }

    /* 保留原 Summary 设计 */
    .summary-section-title { font-weight: bold; color: #e16161ff; font-size: 1.2rem; margin-bottom: 10px; }
    .summary-card {
        background: #fff; padding: 25px 40px; border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 6px solid #e16161ff;
        margin-bottom: 40px;
    }
    .summary-item { display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px dashed #eee; padding-bottom: 12px; }
    .summary-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .summary-label { font-weight: 600; color: #555; }
    .summary-value { font-weight: 700; color: #333; }
    .summary-total { color: #dc2626; font-size: 1.15rem; }

    /* 三列水平排列支付方式 */
    .payment-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 60px;
    }

    .payment-option {
        border: 2px solid #eee; border-radius: 12px; padding: 30px 15px;
        text-align: center; cursor: pointer; transition: all 0.3s ease;
        background: #fff; display: flex; flex-direction: column; 
        align-items: center; justify-content: center; height: 100%; min-height: 300px;
    }
    .payment-option:hover {
        border-color: #e16161ff; box-shadow: 0 8px 25px rgba(225, 97, 97, 0.15); transform: translateY(-5px);
    }
    .payment-option.disabled { opacity: 0.6; cursor: not-allowed; background: #fafafa; }
    
    .payment-img { height: 60px; object-fit: contain; margin-bottom: 20px; }
    .payment-title { font-weight: 700; color: #333; font-size: 1.1rem; display: block; margin-bottom: 8px; }
    .payment-desc { font-size: 0.85rem; color: #777; margin-bottom: 20px; flex-grow: 1; line-height: 1.4; } 
    
    .btn-pay {
        background-color: #ffbb6dff; color: white; border: none; padding: 10px 20px;
        border-radius: 30px; font-weight: bold; transition: 0.3s; width: 100%; margin-top: auto;
    }
    .payment-option:not(.disabled):hover .btn-pay { background-color: #f79c34ff; }

    /* 橙色返回按钮 */
    .btn-orange-back {
        display: inline-flex; align-items: center; gap: 10px;
        background-color: #f79c34; color: white !important;
        padding: 12px 25px; border-radius: 8px;
        font-weight: 700; font-size: 1.1rem;
        transition: 0.2s ease; text-decoration: none !important;
        box-shadow: 0 4px 6px rgba(247, 156, 52, 0.2); margin-bottom: 20px;        
    }
    .btn-orange-back:hover { background-color: #e68a20; transform: translateX(-5px); }

    /* --- ⭐ [参考首页] CTA Section 样式整合 ⭐ --- */
    /* --- CTA Section 样式整合 --- */
.cta-section { 
    padding: 60px 20px; /* 适当缩小上下内边距，因为没有按钮了 */
    background: linear-gradient(rgba(220, 38, 38, 0.9), rgba(185, 28, 28, 0.9)), 
                url('https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&q=80'); 
    background-size: cover; 
    background-position: center; 
    background-attachment: fixed; 
    text-align: center; 
    color: white; 
    border-radius: 15px;
    margin-top: 40px;
    box-shadow: 0 10px 30px rgba(220, 38, 38, 0.2);
}

.cta-content h2 { 
    font-size: 2.5rem; 
    margin-bottom: 15px; 
    font-weight: 800; 
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.cta-content p { 
    font-size: 1.1rem; 
    margin-bottom: 0; /* 移除底部间距，因为没有按钮了 */
    max-width: 800px; 
    margin-left: auto; 
    margin-right: auto; 
    line-height: 1.6;
    opacity: 0.95;
}

@media (max-width: 768px) {
    .cta-content h2 { font-size: 1.8rem; }
    .cta-content p { font-size: 1rem; }
}
文案修
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Payment Selection</h1>
        <p>Complete your act of kindness with your preferred method.</p>
    </div>
</div>

<?php 
    $current_step = 3; 
    $flow_type = 'standard'; 
    include 'stepper.php'; 
?>

<div class="site-section">
    <div class="container">
        
        <div class="text-left">
            <a href="Payment_Page.php" class="btn-orange-back">
                <i class="fas fa-arrow-left"></i> Back to Amount
            </a>
        </div>

        <h3 class="summary-section-title">Summary</h3>
        <div class="summary-card">
            <div class="summary-item">
                <span class="summary-label">Target</span>
                <span class="summary-value"><?php echo htmlspecialchars($display_name); ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Frequency</span>
                <span class="summary-value"><?php echo htmlspecialchars($donation_type); ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Total</span>
                <span class="summary-value summary-total">RM <?php echo number_format($amount, 2); ?></span>
            </div>
        </div>

        <h3 class="text-center mb-4" style="font-weight: 700; color: #333;">Select Payment Method</h3>
        
        <form id="paymentForm" method="POST" action="">
            <input type="hidden" name="payment_method" id="selected_payment_method" value="">

            <div class="payment-grid">
                <div class="payment-option <?php echo ($current_balance < $amount) ? 'disabled' : ''; ?>" 
                     onclick="<?php echo ($current_balance >= $amount) ? 'handleWalletPay()' : ''; ?>">
                    <img src="images/e-wallet.png" alt="Wallet" class="payment-img">
                    <span class="payment-title">E-Wallet Balance</span>
                    <p class="payment-desc">
                        Use your internal balance.<br>
                        Available: <b style="color:<?php echo ($current_balance < $amount)?'#dc2626':'#16a34a';?>">RM <?php echo number_format($current_balance, 2); ?></b>
                    </p>
                    <button type="button" class="btn-pay"><?php echo ($current_balance < $amount)?'Insufficient':'Pay Now';?></button>
                </div>

                <div class="payment-option" onclick="submitTo('Credit_Debit_Page.php')">
                    <img src="images/BankTransfer.jpg" alt="Card" class="payment-img">
                    <span class="payment-title">Credit / Debit Card</span>
                    <p class="payment-desc">Safe & secure checkout via Visa or Mastercard.<br><small>Support local banks</small></p>
                    <button type="button" class="btn-pay">Select</button>
                </div>

                <div class="payment-option" onclick="submitTo('TNG_Page.php')">
                    <img src="images/TNG.png" alt="TNG" class="payment-img">
                    <span class="payment-title">Touch 'n Go eWallet</span>
                    <p class="payment-desc">Scan & Pay using your mobile device.<br><small>Malaysia's favorite wallet</small></p>
                    <button type="button" class="btn-pay">Select</button>
                </div>
            </div>
        </form>

        <section class="cta-section">
            <div class="cta-content">
                <h2>Bridge to a Brighter Future</h2>
                <p>Your contribution is more than just a transaction; it's a lifeline of hope. <br>
                    Please select your preferred secure payment method above to finalize your support.</p>
                <div style="font-size: 0.9rem; opacity: 0.8; margin-top: 10px;">
                <i class="fas fa-shield-alt"></i> Your security is our priority. All transactions are encrypted and secure.
                </div>
            </div>
        </section>

    </div>
</div>

<script>
    const balance = <?php echo $current_balance; ?>;
    const required = <?php echo $amount; ?>;

    function handleWalletPay() {
        Swal.fire({
            title: 'Confirm Payment',
            text: "RM " + required.toFixed(2) + " will be deducted from your e-wallet balance.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Confirm & Pay'
        }).then((result) => {
            if (result.isConfirmed) {
                submitTo('Wallet_Processing.php');
            }
        });
    }

    function submitTo(target) {
        const form = document.getElementById('paymentForm');
        document.getElementById('selected_payment_method').value = target.includes('Wallet') ? "E-Wallet Balance" : (target.includes('Credit') ? "Credit / Debit Card" : "Touch 'n Go");
        form.action = target;
        form.submit();
    }
</script>

<?php include 'footer.php'; ?>