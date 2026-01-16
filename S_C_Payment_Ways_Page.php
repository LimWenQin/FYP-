<?php
// S_C_Payment_Ways_Page.php
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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'])) {
    $amount = (float)$_POST['amount'];
    $case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
    $activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
    $type = $_POST['donation_type'];
    $tax_receipt = isset($_POST['tax_receipt']) ? 1 : 0;

    if ($amount <= 0 || ($case_id <= 0 && $activity_id <= 0)) {
        echo "<script>alert('Invalid data. No project selected.'); window.history.back();</script>";
        exit();
    }

    $source_type = ($activity_id > 0) ? 'activity' : 'special_case';

    $_SESSION['donation_data'] = [
        'amount' => $amount,
        'type' => $type,
        'case_id' => $case_id,
        'activity_id' => $activity_id,
        'branch_id' => 0, 
        'tax_receipt' => $tax_receipt,
        'source' => $source_type 
    ];
}

if (empty($_SESSION['donation_data'])) {
    header("Location: Homepage.php");
    exit();
}

$amount = $_SESSION['donation_data']['amount'];
$donation_type = $_SESSION['donation_data']['type'];
$case_id = $_SESSION['donation_data']['case_id'];
$activity_id = $_SESSION['donation_data']['activity_id'];

// 3. 获取钱包余额
$wallet_sql = "SELECT Donor_Wallet FROM donor WHERE Donor_ID = ?";
$w_stmt = $conn->prepare($wallet_sql);
$w_stmt->bind_param("i", $current_donor_id);
$w_stmt->execute();
$current_balance = $w_stmt->get_result()->fetch_assoc()['Donor_Wallet'] ?? 0.00;

// 4. 动态查询项目名称
$project_title = "Unknown Project";
if ($activity_id > 0) {
    $res = $conn->query("SELECT Activity_Name as title FROM activity WHERE Activity_ID = $activity_id");
} else {
    $res = $conn->query("SELECT Case_Title as title FROM special_case WHERE Case_ID = $case_id");
}
if ($r = $res->fetch_assoc()) $project_title = $r['title'];

include 'header_UI.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* 同步 Hero 样式 */
    .hero-wrap {
        height: 280px; position: relative;
        background-image: url('images/hero_3.jpg'); background-size: cover; background-position: center;
        display: flex; align-items: center; justify-content: center; text-align: center;
    }
    .hero-wrap .overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.4); }
    .hero-content { position: relative; z-index: 2; color: #fff; }
    .hero-content h1 { font-family: 'Segoe UI', sans-serif; font-size: 2.8rem; margin-bottom: 5px; }

    /* 同步 Summary 设计 */
    .summary-section-title { font-weight: bold; color: #e16161ff; font-size: 1.2rem; margin-bottom: 10px; }
    .summary-card {
        background: #fff; padding: 20px 40px; border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 6px solid #e16161ff;
        margin-bottom: 30px;
    }
    .summary-item { display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
    .summary-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .summary-label { font-weight: 600; color: #555; }
    .summary-value { font-weight: 700; color: #333; }
    .summary-total { color: #dc2626; font-size: 1.2rem; }

    /* 同步支付方式水平网格排列 */
    .payment-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 50px;
    }
    .payment-option {
        border: 2px solid #eee; border-radius: 12px; padding: 25px 15px;
        text-align: center; cursor: pointer; transition: all 0.3s ease;
        background: #fff; display: flex; flex-direction: column; 
        align-items: center; justify-content: center; min-height: 260px;
    }
    .payment-option:hover { border-color: #e16161ff; box-shadow: 0 8px 25px rgba(225, 97, 97, 0.15); transform: translateY(-5px); }
    .payment-option.disabled { opacity: 0.6; cursor: not-allowed; background: #fafafa; }
    .payment-img { height: 50px; object-fit: contain; margin-bottom: 15px; }
    .payment-title { font-weight: 700; color: #333; font-size: 1rem; margin-bottom: 5px; }
    .payment-desc { font-size: 0.8rem; color: #777; margin-bottom: 15px; line-height: 1.4; flex-grow: 1; }
    .btn-pay { background-color: #ffbb6dff; color: white; border: none; padding: 8px 15px; border-radius: 30px; font-weight: bold; width: 100%; transition: 0.3s; }
    .payment-option:not(.disabled):hover .btn-pay { background-color: #f79c34ff; }

    /* 同步底部横幅设计 */
    .cta-section { 
        padding: 60px 20px;
        background: linear-gradient(rgba(220, 38, 38, 0.9), rgba(185, 28, 28, 0.9)), 
                    url('https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&q=80'); 
        background-size: cover; background-position: center; background-attachment: fixed; 
        text-align: center; color: white; border-radius: 15px; margin-top: 40px;
        box-shadow: 0 10px 30px rgba(220, 38, 38, 0.2);
    }
    .cta-content h2 { font-size: 2.5rem; margin-bottom: 15px; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    .cta-content p { font-size: 1.1rem; max-width: 800px; margin: 0 auto; line-height: 1.6; opacity: 0.95; }

    .btn-orange-back {
        display: inline-flex; align-items: center; gap: 10px;
        background-color: #f79c34; color: white !important;
        padding: 10px 20px; border-radius: 8px; font-weight: 700;
        transition: 0.2s; text-decoration: none !important; margin-bottom: 20px;
    }

    @media (max-width: 991px) {
        .payment-grid { grid-template-columns: 1fr; }
        .cta-content h2 { font-size: 1.8rem; }
    }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Payment Confirmation</h1>
        <p>Your journey of compassion continues here.</p>
    </div>
</div>

<?php 
    $current_step = 2; 
    $flow_type = 'special'; 
    include 'stepper.php'; 
?>

<div class="site-section">
    <div class="container">
        
        <div class="text-left">
            <?php $back_link = ($activity_id > 0) ? "S_C_Payment_Page.php?activity_id=$activity_id" : "S_C_Payment_Page.php?case_id=$case_id"; ?>
            <a href="<?php echo $back_link; ?>" class="btn-orange-back">
                <i class="fas fa-arrow-left"></i> Back to Change Details
            </a>
        </div>

        <h3 class="summary-section-title">Summary</h3>
        <div class="summary-card">
            <div class="summary-item">
                <span class="summary-label">Project / Case</span>
                <span class="summary-value"><?php echo htmlspecialchars($project_title); ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Frequency</span>
                <span class="summary-value text-uppercase"><?php echo htmlspecialchars($donation_type); ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Total Amount</span>
                <span class="summary-value summary-total">RM <?php echo number_format($amount, 2); ?></span>
            </div>
        </div>

        <h3 class="text-center mb-4" style="font-weight: 700; color: #333;">Select Payment Method</h3>
        
        <form id="paymentForm" method="POST" action="">
            <input type="hidden" name="payment_method" id="selected_payment_method" value="">

            <div class="payment-grid">
                <div class="payment-option <?php echo ($current_balance < $amount) ? 'disabled' : ''; ?>" 
                     onclick="<?php echo ($current_balance >= $amount) ? 'handleWalletPay()' : 'insufficientAlert()'; ?>">
                    <img src="images/e-wallet.png" alt="Wallet" class="payment-img">
                    <span class="payment-title">Internal Wallet</span>
                    <p class="payment-desc">Available: <b>RM <?php echo number_format($current_balance, 2); ?></b></p>
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
                    <p class="payment-desc">Scan QR to pay with TNG App.<br><small>Malaysia's No.1 Wallet</small></p>
                    <button type="button" class="btn-pay">Select</button>
                </div>
            </div>
        </form>

        <section class="cta-section">
            <div class="cta-content">
                <h2>Bridge to a Brighter Future</h2>
                <p>Your contribution is more than just a transaction; it's a lifeline of hope. <br>
                    Please select your preferred secure payment method above to finalize your support.</p>
                <div style="font-size: 0.9rem; opacity: 0.8; margin-top: 15px;">
                    <i class="fas fa-shield-alt"></i> Your security is our priority. All transactions are encrypted and secure.
                </div>
            </div>
        </section>

    </div>
</div>

<script>
    const balance = <?php echo $current_balance; ?>;
    const required = <?php echo $amount; ?>;

    function insufficientAlert() {
        Swal.fire({ icon: 'error', title: 'Insufficient Balance', text: 'Please top up your wallet first.', footer: '<a href="Top-Up.php">Go to Top-up</a>' });
    }

    function handleWalletPay() {
        Swal.fire({
            title: 'Confirm Payment',
            text: "RM " + required.toFixed(2) + " will be deducted from your wallet.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Confirm & Pay'
        }).then((result) => {
            if (result.isConfirmed) { submitTo('Wallet_Processing.php'); }
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