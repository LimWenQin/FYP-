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
// 2. 接收上一页数据并存入 Session
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'])) {
    
    $amount = (float)$_POST['amount'];
    // 接收两个可能的 ID
    $case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
    $activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
    
    $type = $_POST['donation_type'];
    $tax_receipt = isset($_POST['tax_receipt']) ? 1 : 0;

    // 验证逻辑：只要其中一个ID有效即可
    if ($amount <= 0 || ($case_id <= 0 && $activity_id <= 0)) {
        echo "<script>alert('Invalid data. No project selected.'); window.history.back();</script>";
        exit();
    }

    // 确定来源类型
    $source_type = ($activity_id > 0) ? 'activity' : 'special_case';

    // 存入 Session 供后续 Processing 页面使用
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

// Session 检查
if (empty($_SESSION['donation_data'])) {
    header("Location: Homepage.php");
    exit();
}

// 提取数据用于显示
$amount = $_SESSION['donation_data']['amount'];
$donation_type = $_SESSION['donation_data']['type'];
$case_id = $_SESSION['donation_data']['case_id'];
$activity_id = $_SESSION['donation_data']['activity_id'];
$tax_status = $_SESSION['donation_data']['tax_receipt'];

// 3. 获取钱包余额
$wallet_sql = "SELECT Donor_Wallet FROM donor WHERE Donor_ID = ?";
$w_stmt = $conn->prepare($wallet_sql);
$w_stmt->bind_param("i", $current_donor_id);
$w_stmt->execute();
$w_res = $w_stmt->get_result()->fetch_assoc();
$current_balance = $w_res['Donor_Wallet'] ?? 0.00;
$w_stmt->close();

// 4. 动态查询项目名称
$project_title = "Unknown Project";
$display_category = "General";

if ($activity_id > 0) {
    $sql = "SELECT Activity_Name as title FROM activity WHERE Activity_ID = ?";
    $param_id = $activity_id;
    $display_category = "Activity";
} else {
    $sql = "SELECT Case_Title as title FROM special_case WHERE Case_ID = ?";
    $param_id = $case_id;
    $display_category = "Special Case";
}

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $param_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($r = $res->fetch_assoc()) {
        $project_title = $r['title'];
    }
    $stmt->close();
}

include 'header_UI.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .hero-wrap {
        height: 350px; position: relative;
        background-image: url('images/hero_3.jpg'); 
        background-size: cover; background-position: center;
        display: flex; align-items: center; justify-content: center; text-align: center;
    }
    .hero-wrap .overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); }
    .hero-content { position: relative; z-index: 2; max-width: 800px; }
    .hero-content h1 { font-family: 'Segoe UI', sans-serif; color: #fff; font-size: 3.5rem; margin-bottom: 10px; }
    .hero-content p { font-size: 1.2rem; color: rgba(255, 255, 255, 0.9); }

    .summary-card {
        background: #fff; padding: 30px; border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid #dc2626;
    }
    .summary-item { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px dashed #eee; padding-bottom: 15px; }
    .summary-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .summary-label { font-weight: 600; color: #555; }
    .summary-value { font-weight: 700; color: #333; font-size: 1.05rem; text-align: right; max-width: 60%; }
    .summary-total { color: #dc2626; font-size: 1.2rem; }

    .payment-option {
        border: 2px solid #eee; border-radius: 12px; padding: 30px 20px;
        text-align: center; cursor: pointer; transition: all 0.3s ease;
        margin-bottom: 20px; background: #fff; display: flex; flex-direction: column; 
        align-items: center; justify-content: center; height: 100%; min-height: 280px;
    }
    .payment-option:hover {
        border-color: #dc2626; box-shadow: 0 8px 20px rgba(220, 38, 38, 0.15); transform: translateY(-5px);
    }
    .payment-option.disabled { opacity: 0.6; cursor: not-allowed; background: #fafafa; }
    
    .payment-img { height: 65px; object-fit: contain; margin-bottom: 20px; }
    .payment-title { font-weight: 700; color: #333; font-size: 1.15rem; display: block; margin-bottom: 5px; }
    .payment-desc { font-size: 0.9rem; color: #777; margin-bottom: 20px; flex-grow: 1; } 
    
    .btn-pay {
        background-color: #ffbb6dff; color: white; border: none; padding: 12px 35px;
        border-radius: 30px; font-weight: bold; transition: 0.3s; width: 100%; margin-top: auto;
    }
    .payment-option:not(.disabled):hover .btn-pay { background-color: #dc2626; transform: scale(1.05); }

    .btn-orange-back {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        background-color: #f79c34; 
        color: white !important;
        padding: 15px 35px;         
        border-radius: 10px;
        font-weight: 700;           
        font-size: 1.2rem;          
        transition: all 0.3s ease;
        text-decoration: none !important;
        box-shadow: 0 4px 6px rgba(247, 156, 52, 0.2);
        margin-bottom: 25px;        
    }
    .btn-orange-back:hover {
        background-color: #e68a20;
        transform: translateX(-5px);
        box-shadow: 0 6px 12px rgba(247, 156, 52, 0.3);
    }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Secure Payment</h1>
        <p>Your support makes a huge difference for this cause.</p>
    </div>
</div>

<?php 
    $current_step = 2; 
    $flow_type = 'special'; 
    include 'stepper.php'; 
?>

<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row">
            
            <div class="col-lg-4 mb-5">
                <div class="text-left">
                    <?php 
                        $back_link = ($activity_id > 0) ? "S_C_Payment_Page.php?activity_id=$activity_id" : "S_C_Payment_Page.php?case_id=$case_id";
                    ?>
                    <a href="<?php echo $back_link; ?>" class="btn-orange-back">
                        <i class="fas fa-arrow-left"></i> Back to Change Details
                    </a>
                </div>

                <h3 class="text-cursive mb-3" style="color: #dc2626;">Summary</h3>
                <div class="summary-card">
                    <div class="summary-item">
                        <span class="summary-label">Category</span>
                        <span class="summary-value badge badge-info p-2"><?php echo $display_category; ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Target</span>
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
            </div>

            <div class="col-lg-8">
                <h3 class="text-cursive text-black mb-4 text-center">Select Payment Method</h3>
                
                <form id="paymentForm" method="POST" action="">
                    <input type="hidden" name="payment_method" id="selected_payment_method" value="">

                    <div class="row justify-content-center">
                        
                        <div class="col-md-4">
                            <div class="payment-option <?php echo ($current_balance < $amount) ? 'disabled' : ''; ?>" onclick="handleWalletPay()">
                                <img src="images/e-wallet.png" alt="Wallet" class="payment-img">
                                <span class="payment-title">E-Wallet</span>
                                <p class="payment-desc">
                                    Balance: <b style="color:<?php echo ($current_balance < $amount)?'red':'green';?>">RM <?php echo number_format($current_balance, 2); ?></b>
                                </p>
                                <button type="button" class="btn-pay"><?php echo ($current_balance < $amount)?'Insufficient':'Use Balance';?></button>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="payment-option" onclick="submitTo('Credit_Debit_Page.php')">
                                <img src="images/BankTransfer.jpg" alt="Card" class="payment-img">
                                <span class="payment-title">Credit / Debit</span>
                                <p class="payment-desc">Pay via Visa or Mastercard<br><small class="text-success">+ Earn Points</small></p>
                                <button type="button" class="btn-pay">Pay Now</button>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="payment-option" onclick="submitTo('TNG_Page.php')">
                                <img src="images/TNG.png" alt="TNG" class="payment-img">
                                <span class="payment-title">Touch 'n Go</span>
                                <p class="payment-desc">Scan QR to Complete Pay<br><small class="text-success">+ Earn Points</small></p>
                                <button type="button" class="btn-pay">Pay Now</button>
                            </div>
                        </div>

                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
    const currentBalance = <?php echo $current_balance; ?>;
    const donationAmount = <?php echo $amount; ?>;

    function handleWalletPay() {
        if (currentBalance < donationAmount) {
            Swal.fire({
                icon: 'error',
                title: 'Insufficient Balance',
                text: 'You need RM ' + (donationAmount - currentBalance).toFixed(2) + ' more in your wallet.',
                footer: '<a href="Wallet_Page.php">Top up your wallet now?</a>'
            });
            return;
        }

        Swal.fire({
            title: 'Confirm Payment',
            text: "RM " + donationAmount.toFixed(2) + " will be deducted from your e-wallet balance.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Confirm & Donate'
        }).then((result) => {
            if (result.isConfirmed) {
                // ⭐ 调用 submitTo，它会自动识别 Wallet 支付方式
                submitTo('Wallet_Processing.php');
            }
        });
    }

    // ⭐ 修正点 2: 修改后的 submitTo 函数，自动填入支付方式名称 ⭐
    function submitTo(target) {
        const form = document.getElementById('paymentForm');
        const methodInput = document.getElementById('selected_payment_method');

        // 根据目标文件名设定支付方式名称
        let methodName = "";
        if (target.includes('Wallet')) methodName = "E-Wallet Balance";
        else if (target.includes('Credit')) methodName = "Credit / Debit Card";
        else if (target.includes('TNG')) methodName = "Touch 'n Go";

        methodInput.value = methodName; // 写入隐藏格子
        form.action = target;
        form.submit();
    }
</script>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>