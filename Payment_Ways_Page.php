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
        
        // --- 【新增】接收 Tax Receipt 状态 ---
        // 检查 POST 里有没有 tax_receipt，如果有且值为 '1'，存入 Session
        if (isset($_POST['tax_receipt']) && $_POST['tax_receipt'] == '1') {
            $_SESSION['donation_data']['tax_receipt'] = 1;
        } else {
            $_SESSION['donation_data']['tax_receipt'] = 0;
        }
        // -------------------------------------
    }
}

if (empty($_SESSION['donation_data'])) {
    header("Location: Payment_Page.php");
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
$current_balance = $w_stmt->get_result()->fetch_assoc()['Donor_Wallet'] ?? 0.00;

// 获取名称
$display_name = "General Fund (HQ)"; 
if(!empty($case_id)) {
    $r = $conn->query("SELECT Case_Title FROM special_case WHERE Case_ID = $case_id")->fetch_assoc();
    if($r) $display_name = "Case: " . $r['Case_Title'];
} elseif(!empty($activity_id)) {
    $r = $conn->query("SELECT Activity_Name FROM activity WHERE Activity_ID = $activity_id")->fetch_assoc();
    if($r) $display_name = "Activity: " . $r['Activity_Name'];
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
    /* --- 核心变量 --- */
    :root {
        --primary-red: #dc2626;
        --white: #ffffff;
    }

    .hero-wrap {
        height: 400px; position: relative;
        background-image: url('images/hero_3.jpg'); background-size: cover; background-position: center;
        display: flex; align-items: center; justify-content: center; text-align: center;
    }
    .hero-wrap .overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.4); }
    .hero-content { position: relative; z-index: 2; color: #fff; }
    .hero-content h1 { font-family: 'Segoe UI', sans-serif; font-size: 3rem; margin-bottom: 5px; }

    /* --- Back Button --- */
    .btn-orange-back {
        display: inline-flex; align-items: center; gap: 10px;
        background-color: #f79c34; color: white !important;
        padding: 10px 20px; border-radius: 8px;
        font-weight: 600; font-size: 1rem;
        transition: 0.2s ease; text-decoration: none !important;
        box-shadow: 0 4px 6px rgba(247, 156, 52, 0.2); 
        margin-bottom: 20px;        
    }
    .btn-orange-back:hover { background-color: #e68a20; transform: translateX(-3px); }

    /* --- Top Layout: Summary + Video --- */
    .info-video-grid {
        display: grid;
        /* 将左边 Summary 改为 1.5份，右边视频缩减为 1份 */
        grid-template-columns: 1.5fr 1fr; 
        gap: 30px;
        align-items: stretch;
        margin-bottom: 50px;
    }

    /* 1. Summary Card (Style retained) */
    .summary-card-modern {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid #eee;
        display: flex; flex-direction: column; /* 确保内容撑开 */
        height: 100%;
    }

    .sc-header {
        /* 使用更有质感的渐变色 */
        background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
        padding: 30px 20px; /* 增加上下间距 */
        text-align: center; 
        color: white;
    }

    .sc-amount { 
        font-size: 2.8rem; /* 放大金额字体 */
        font-weight: 800; 
        margin-top: 5px; 
        text-shadow: 0 2px 4px rgba(0,0,0,0.2); /* 增加文字阴影 */
    }
    .sc-amount small { font-size: 1rem; margin-right: 5px; opacity: 0.9; }

    .sc-body { padding: 25px; flex-grow: 1; display: flex; flex-direction: column; justify-content: center; }
    .sc-row { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px dashed #eee; padding-bottom: 15px; }
    .sc-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .sc-label { color: #888; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
    .sc-label i { color: #dc2626; width: 16px; }
    .sc-value { font-weight: 700; color: #333; text-align: right; }

    /* 2. Video Section */
    .video-card {
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        position: relative;
        height: 100%; min-height: 300px;
    }
    .video-card video {
        width: 100%; 
        height: 100%; 
        object-fit: cover;
        opacity: 0.6; /* 从 0.8 降低到 0.6，使其更不显眼 */
        transition: opacity 0.3s;
    }
    .video-card:hover video { opacity: 1; }
    
    .video-overlay {
        position: absolute; bottom: 0; left: 0; right: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
        padding: 20px; color: white; pointer-events: none; /* 让点击穿透到视频控件 */
    }
    .video-text h5 { margin: 0; font-weight: 700; font-size: 1.1rem; }
    .video-text p { margin: 5px 0 0; font-size: 0.85rem; opacity: 0.9; }

    /* --- Bottom Layout: Payment Options --- */
    .section-title-center {
        text-align: center; margin-bottom: 30px; 
        font-weight: 800; color: #333; position: relative;
    }
    .section-title-center::after {
        content: ''; display: block; width: 50px; height: 3px; 
        background: #dc2626; margin: 10px auto 0;
    }

    .payment-grid {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
        margin-bottom: 40px;
    }
    .payment-option {
        border: 2px solid #eee; border-radius: 12px; padding: 25px 20px;
        text-align: center; cursor: pointer; transition: all 0.3s ease;
        background: #fff; display: flex; flex-direction: column; 
        align-items: center; min-height: 300px;
    }
    .payment-option:hover {
        border-color: #dc2626; box-shadow: 0 8px 25px rgba(220, 38, 38, 0.1); transform: translateY(-5px);
    }
    .payment-option.disabled { opacity: 0.6; cursor: not-allowed; background: #fafafa; }
    .payment-img { height: 50px; object-fit: contain; margin-bottom: 15px; }
    .payment-title { font-weight: 700; color: #333; font-size: 1.05rem; margin-bottom: 8px; }
    .payment-desc { font-size: 0.85rem; color: #666; margin-bottom: 20px; flex-grow: 1; }
    .btn-pay {
        background-color: #333; color: white; border: none; padding: 8px 20px;
        border-radius: 30px; font-weight: bold; transition: 0.3s; width: 100%; margin-top: auto;
    }
    .payment-option:not(.disabled):hover .btn-pay { background-color: #dc2626; }

    @media (max-width: 768px) {
        .info-video-grid { grid-template-columns: 1fr; } /* 手机上垂直排列 */
        .payment-grid { grid-template-columns: 1fr; }
        .hero-wrap { height: 300px; }
    }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Confirm Donation</h1>
        <p>Make a difference today.</p>
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

        <div class="info-video-grid">
            
            <div class="summary-card-modern">
                <div class="sc-header">
                    <div style="font-size: 0.8rem; text-transform: uppercase; opacity: 0.8; letter-spacing: 1px;">Total Contribution</div>
                    <div class="sc-amount"><small>RM</small><?php echo number_format($amount, 2); ?></div>
                </div>
                <div class="sc-body">
                    <div class="sc-row">
                        <span class="sc-label"><i class="fas fa-heart"></i> Beneficiary</span>
                        <span class="sc-value"><?php echo htmlspecialchars($display_name); ?></span>
                    </div>
                    <div class="sc-row">
                        <span class="sc-label"><i class="fas fa-clock"></i> Type</span>
                        <span class="sc-value"><?php echo htmlspecialchars($donation_type); ?></span>
                    </div>
                    <div class="sc-row">
                        <span class="sc-label"><i class="fas fa-calendar-alt"></i> Date</span>
                        <span class="sc-value"><?php echo date("d M Y"); ?></span>
                    </div>
                    <div style="text-align: center; margin-top: 15px; font-size: 0.75rem; color: #aaa;">
                        ID: <?php echo uniqid('DON-'); ?>
                    </div>
                </div>
            </div>

                <div class="video-card">
                    <video autoplay muted loop playsinline poster="images/cover_image.jpg" style="width:100%; height:100%; object-fit: cover;">
                        <source src="video/thank_you.mp4" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>

                    <div class="video-overlay">
                        <div class="video-text">
                            <h5><i class="fas fa-play-circle"></i> See Your Impact</h5>
                            <p>Thank you for your generosity.</p>
                        </div>
                    </div>
                </div>

        </div>

        <h3 class="section-title-center">Select Payment Method</h3>
        
        <form id="paymentForm" method="POST" action="">
            <input type="hidden" name="payment_method" id="selected_payment_method" value="">

            <div class="payment-grid">
                <div class="payment-option <?php echo ($current_balance < $amount) ? 'disabled' : ''; ?>" 
                     onclick="<?php echo ($current_balance >= $amount) ? 'handleWalletPay()' : ''; ?>">
                    <img src="images/e-wallet.png" alt="Wallet" class="payment-img">
                    <span class="payment-title">E-Wallet Balance</span>
                    <p class="payment-desc">
                        Current Balance: <br>
                        <b style="color:<?php echo ($current_balance < $amount)?'#dc2626':'#16a34a';?>">RM <?php echo number_format($current_balance, 2); ?></b>
                    </p>
                    <button type="button" class="btn-pay"><?php echo ($current_balance < $amount)?'Top-up Required':'Pay Now';?></button>
                </div>

                <div class="payment-option" onclick="submitTo('Credit_Debit_Page.php')">
                    <img src="images/BankTransfer.jpg" alt="Card" class="payment-img">
                    <span class="payment-title">Credit / Debit Card</span>
                    <p class="payment-desc">Secure checkout via Visa or Mastercard.</p>
                    <button type="button" class="btn-pay">Pay with Card</button>
                </div>

                <div class="payment-option" onclick="submitTo('TNG_Page.php')">
                    <img src="images/TNG.png" alt="TNG" class="payment-img">
                    <span class="payment-title">Touch 'n Go</span>
                    <p class="payment-desc">Scan QR Code using your mobile wallet.</p>
                    <button type="button" class="btn-pay">Pay with TNG</button>
                </div>
            </div>
        </form>

        <div style="text-align: center; opacity: 0.7; font-size: 0.9rem; margin-bottom: 40px;">
            <i class="fas fa-lock"></i> All transactions are secure and encrypted.
        </div>

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