<?php
// Payment_Ways_Page.php
session_start();
include 'dataconnection.php';

// 登录检查
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

// ==========================================
// 1. 接收上一页数据并存入 Session
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['branch_id'])) {
        $_SESSION['donation_data']['branch_id'] = $_POST['branch_id'];
    }
}

// 安全检查
if (empty($_SESSION['donation_data'])) {
    header("Location: Donate_Page.php");
    exit();
}

// 从 Session 获取数据
$amount = $_SESSION['donation_data']['amount'];
$donation_type = $_SESSION['donation_data']['type'];
$branch_id = $_SESSION['donation_data']['branch_id'];
$case_id = $_SESSION['donation_data']['case_id'];
$activity_id = $_SESSION['donation_data']['activity_id'];

// 获取钱包余额
$wallet_sql = "SELECT Donor_Wallet FROM donor WHERE Donor_ID = ?";
$w_stmt = $conn->prepare($wallet_sql);
$w_stmt->bind_param("i", $_SESSION['donor_id']);
$w_stmt->execute();
$w_res = $w_stmt->get_result()->fetch_assoc();
$current_balance = $w_res['Donor_Wallet'] ?? 0.00;
$w_stmt->close();

// 获取项目名称
$branch_name = "General Fund (HQ)"; 
if(!empty($case_id)) {
    $res = $conn->query("SELECT Case_Title FROM special_case WHERE Case_ID = $case_id");
    if($r = $res->fetch_assoc()) $branch_name = "Case: " . $r['Case_Title'];
} elseif(!empty($activity_id)) {
    $res = $conn->query("SELECT Activity_Name FROM activity WHERE Activity_ID = $activity_id");
    if($r = $res->fetch_assoc()) $branch_name = "Activity: " . $r['Activity_Name'];
} elseif ($branch_id > 0) {
    $stmt = $conn->prepare("SELECT Branch_Name FROM branch WHERE Branch_ID = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $branch_name = $row['Branch_Name']; 
    $stmt->close();
}

include 'header_UI.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* Hero & Summary 样式保持不变 */
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

    /* 统一的 Payment Option 样式 (所有卡片共用) */
    .payment-option {
        border: 2px solid #eee; border-radius: 10px; padding: 30px 20px;
        text-align: center; cursor: pointer; transition: all 0.3s ease;
        margin-bottom: 20px; background: #fff; display: flex; flex-direction: column; 
        align-items: center; justify-content: center; height: 100%; min-height: 280px;
    }
    .payment-option:hover {
        border-color: #e16161ff; box-shadow: 0 8px 20px rgba(225, 97, 97, 0.15); transform: translateY(-5px);
    }
    
    .payment-img { height: 60px; object-fit: contain; margin-bottom: 20px; }
    
    .payment-title { font-weight: 700; color: #333; font-size: 1.1rem; display: block; margin-bottom: 5px; }
    
    .payment-desc { font-size: 0.9rem; color: #777; margin-bottom: 20px; flex-grow: 1; } 
    
    /* 统一的橙色按钮 */
    .btn-pay {
        background-color: #ffbb6dff; color: white; border: none; padding: 10px 30px;
        border-radius: 30px; font-weight: bold; transition: 0.3s; width: 100%; margin-top: auto;
    }
    .payment-option:hover .btn-pay { background-color: #f79c34ff; transform: scale(1.05); }

    .nav-buttons { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }
    .btn-prev {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        padding: 12px 25px; border-radius: 8px; font-size: 1rem; font-weight: bold;
        background: #e5e7eb; color: #374151; border: 1px solid #d1d5db; text-decoration: none; transition: 0.2s;
    }
    .btn-prev:hover { background: #d1d5db; color: #111; text-decoration: none; }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Secure Payment</h1>
        <p>Complete your donation securely.</p>
    </div>
</div>

<?php 
    $current_step = 3; 
    $flow_type = 'standard'; 
    include 'stepper.php'; 
?>

<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row">
            
            <div class="col-lg-4 mb-5">
                <div class="mb-4">
                    <h3 class="text-cursive mb-3" style="color: #e16161ff;">Summary</h3>
                    <p class="text-muted">Review your details.</p>
                </div>

                <div class="summary-card">
                    <div class="summary-item">
                        <span class="summary-label">Project</span>
                        <span class="summary-value"><?php echo htmlspecialchars($branch_name); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Type</span>
                        <span class="summary-value text-uppercase"><?php echo htmlspecialchars($donation_type); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Amount</span>
                        <span class="summary-value summary-total">RM <?php echo number_format((float)$amount, 2); ?></span>
                    </div>
                </div>
                
                <div class="nav-buttons">
                    <a href="Branch_Selection.php" class="btn-prev">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <div class="col-lg-8">
                <h3 class="text-cursive text-black mb-4 text-center">Select Payment Method</h3>
                
                <form id="paymentForm" method="POST" action="">
                    <div class="row justify-content-center">
                        
                        <div class="col-md-4 col-sm-6">
                            <div class="payment-option" onclick="checkWalletBalance()">
                                <img src="images/e-wallet.png" alt="My E-Wallet" class="payment-img" onerror="this.src='https://via.placeholder.com/60?text=Wallet'">
                                
                                <span class="payment-title">My E-Wallet</span>
                                
                                <p class="payment-desc">
                                    Balance: RM <?php echo number_format($current_balance, 2); ?><br>
                                    <span style="font-size:0.8rem">Instant Payment</span>
                                </p>
                                
                                <button type="button" class="btn-pay">Pay Now</button>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="payment-option" onclick="submitForm('card')">
                                <img src="images/BankTransfer.jpg" alt="Card" class="payment-img">
                                <span class="payment-title">Credit / Debit</span>
                                <p class="payment-desc">Visa / Mastercard<br><span style="font-size:0.8rem">Top-up & Pay</span></p>
                                <button type="button" class="btn-pay">Pay Now</button>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="payment-option" onclick="submitForm('tng')">
                                <img src="images/TNG.png" alt="TNG" class="payment-img">
                                <span class="payment-title">Touch 'n Go</span>
                                <p class="payment-desc">eWallet QR<br><span style="font-size:0.8rem">Top-up & Pay</span></p>
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

    function checkWalletBalance() {
        if (currentBalance >= donationAmount) {
            const form = document.getElementById('paymentForm');
            form.action = "Wallet_Processing.php";
            form.submit();
        } else {
            const shortAmount = (donationAmount - currentBalance).toFixed(2);
            Swal.fire({
                title: 'Insufficient Balance',
                html: `
                    Current Balance: <b style="color:#28a745">RM ${currentBalance.toFixed(2)}</b><br>
                    Required: <b style="color:#dc2626">RM ${donationAmount.toFixed(2)}</b><br><br>
                    Please use <b>Credit Card</b> or <b>TNG</b> to pay.
                `,
                icon: 'warning',
                confirmButtonText: 'OK',
                confirmButtonColor: '#0057B7'
            });
        }
    }

    function submitForm(method) {
        const form = document.getElementById('paymentForm');
        if (method === 'card') {
            form.action = "Credit_Debit_Page.php"; 
        } else if (method === 'tng') {
            form.action = "TNG_Page.php";
        }
        form.submit();
    }
</script>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>