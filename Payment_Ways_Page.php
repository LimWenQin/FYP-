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
    // 处理从 Branch Selection 传来的数据
    if(isset($_POST['branch_id'])) {
        $_SESSION['donation_data']['branch_id'] = $_POST['branch_id'];
    }
    // 处理从 Special Case / Activity 详情页直接传来的数据
    if(isset($_POST['amount'])) {
        $_SESSION['donation_data']['amount'] = (float)$_POST['amount'];
        $_SESSION['donation_data']['type'] = $_POST['donation_type'] ?? 'One-Time';
        $_SESSION['donation_data']['case_id'] = $_POST['case_id'] ?? 0;
        $_SESSION['donation_data']['activity_id'] = $_POST['activity_id'] ?? 0;
    }
}

// 安全检查
if (empty($_SESSION['donation_data'])) {
    header("Location: Donate_Page.php");
    exit();
}

// 从 Session 获取最新数据
$amount = $_SESSION['donation_data']['amount'];
$donation_type = $_SESSION['donation_data']['type'];
$branch_id = $_SESSION['donation_data']['branch_id'] ?? 0;
$case_id = $_SESSION['donation_data']['case_id'] ?? 0;
$activity_id = $_SESSION['donation_data']['activity_id'] ?? 0;

// 3. 获取钱包余额
$wallet_sql = "SELECT Donor_Wallet FROM donor WHERE Donor_ID = ?";
$w_stmt = $conn->prepare($wallet_sql);
$w_stmt->bind_param("i", $current_donor_id);
$w_stmt->execute();
$w_res = $w_stmt->get_result()->fetch_assoc();
$current_balance = $w_res['Donor_Wallet'] ?? 0.00;
$w_stmt->close();

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
    $stmt->close();
}

include 'header_UI.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .hero-wrap {
        height: 350px; position: relative;
        background-image: url('images/hero_3.jpg'); background-size: cover; background-position: center;
        display: flex; align-items: center; justify-content: center; text-align: center;
    }
    .hero-wrap .overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); }
    .hero-content { position: relative; z-index: 2; max-width: 800px; }
    .hero-content h1 { font-family: 'Segoe UI', sans-serif; color: #fff; font-size: 3.5rem; margin-bottom: 10px; }
    .hero-content p { font-size: 1.2rem; color: rgba(255, 255, 255, 0.9); }

    .summary-card {
        background: #fff; padding: 30px; border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid #e16161ff;
    }
    .summary-item { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px dashed #eee; padding-bottom: 15px; }
    .summary-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .summary-label { font-weight: 600; color: #555; }
    .summary-value { font-weight: 700; color: #333; font-size: 1.05rem; text-align: right; max-width: 60%; }
    .summary-total { color: #dc2626; font-size: 1.2rem; }

    .payment-option {
        border: 2px solid #eee; border-radius: 10px; padding: 30px 20px;
        text-align: center; cursor: pointer; transition: all 0.3s ease;
        margin-bottom: 20px; background: #fff; display: flex; flex-direction: column; 
        align-items: center; justify-content: center; height: 100%; min-height: 280px;
    }
    .payment-option:hover {
        border-color: #e16161ff; box-shadow: 0 8px 20px rgba(225, 97, 97, 0.15); transform: translateY(-5px);
    }
    .payment-option.disabled { opacity: 0.6; cursor: not-allowed; background: #fafafa; }
    .payment-option.disabled:hover { transform: none; box-shadow: none; border-color: #eee; }
    
    .payment-img { height: 60px; object-fit: contain; margin-bottom: 20px; }
    .payment-title { font-weight: 700; color: #333; font-size: 1.1rem; display: block; margin-bottom: 5px; }
    .payment-desc { font-size: 0.9rem; color: #777; margin-bottom: 20px; flex-grow: 1; } 
    
    .btn-pay {
        background-color: #ffbb6dff; color: white; border: none; padding: 10px 30px;
        border-radius: 30px; font-weight: bold; transition: 0.3s; width: 100%; margin-top: auto;
    }
    .payment-option:not(.disabled):hover .btn-pay { background-color: #f79c34ff; transform: scale(1.05); }

    /* 修改后的 Change Details 按钮样式 */
    .btn-change-details {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background-color: #f8f9fa;
        color: #333;
        border: 1px solid #ddd;
        padding: 10px 20px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: 0.3s;
        text-decoration: none !important;
        margin-top: 20px;
    }
    .btn-change-details:hover {
        background-color: #e2e6ea;
        color: #dc2626;
        border-color: #ccc;
        transform: translateX(-5px);
    }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Secure Payment</h1>
        <p>Choose your preferred payment method.</p>
    </div>
</div>

<?php 
    $current_step = 3; 
    $flow_type = 'standard'; 
    include 'stepper.php'; 
?>

<div class="site-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-5">
                <h3 class="text-cursive mb-3" style="color: #e16161ff;">Summary</h3>
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
                <div class="text-center">
                    <a href="javascript:history.back()" class="btn-change-details">
                        <i class="fas fa-edit"></i> Back to Change Details
                    </a>
                </div>
            </div>

            <div class="col-lg-8">
                <h3 class="text-cursive text-black mb-4 text-center">Select Payment Method</h3>
                <form id="paymentForm" method="POST">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="payment-option <?php echo ($current_balance < $amount) ? 'disabled' : ''; ?>" onclick="handleWalletPay()">
                                <img src="images/e-wallet.png" alt="Wallet" class="payment-img">
                                <span class="payment-title">E-Wallet Balance</span>
                                <p class="payment-desc">
                                    Available: <b style="color:<?php echo ($current_balance < $amount)?'red':'green';?>">RM <?php echo number_format($current_balance, 2); ?></b>
                                </p>
                                <button type="button" class="btn-pay"><?php echo ($current_balance < $amount)?'Insufficient':'Pay Now';?></button>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="payment-option" onclick="submitTo('Credit_Debit_Page.php')">
                                <img src="images/BankTransfer.jpg" alt="Card" class="payment-img">
                                <span class="payment-title">Credit / Debit</span>
                                <p class="payment-desc">Secure via Payment Gateway<br><small>Earn points on Top-up</small></p>
                                <button type="button" class="btn-pay">Select</button>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="payment-option" onclick="submitTo('TNG_Page.php')">
                                <img src="images/TNG.png" alt="TNG" class="payment-img">
                                <span class="payment-title">Touch 'n Go</span>
                                <p class="payment-desc">Scan & Pay via TNG QR<br><small>Earn points on Top-up</small></p>
                                <button type="button" class="btn-pay">Select</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const balance = <?php echo $current_balance; ?>;
    const required = <?php echo $amount; ?>;

    function handleWalletPay() {
        if (balance < required) {
            Swal.fire({
                icon: 'error',
                title: 'Insufficient Balance',
                text: 'Your wallet balance is RM ' + balance.toFixed(2) + '. You need RM ' + required.toFixed(2) + '.',
                footer: '<a href="Top-Up.php">Top up your wallet now?</a>'
            });
            return;
        }

        Swal.fire({
            title: 'Confirm Payment',
            text: "RM " + required.toFixed(2) + " will be deducted from your wallet.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e16161ff',
            confirmButtonText: 'Confirm & Pay'
        }).then((result) => {
            if (result.isConfirmed) {
                submitTo('Wallet_Processing.php');
            }
        });
    }

    function submitTo(target) {
        const form = document.getElementById('paymentForm');
        form.action = target;
        form.submit();
    }
</script>

<?php include 'footer.php'; ?>