<?php
// 1. 引入数据库连接
include 'dataconnection.php';

// 2. 开启 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. 强制登录检查
if (!isset($_SESSION['donor_id'])) {
   echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
   exit();
}

// 4. 接收 POST 数据
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$donation_type = isset($_POST['donation_type']) ? $_POST['donation_type'] : "one-time";
$case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;

// 5. 数据验证
if ($amount <= 0 || $case_id <= 0) {
    echo "<script>alert('Error: Invalid Data. Amount or Case ID is missing.'); window.history.back();</script>";
    exit();
}

// 6. 用 ID 查询 Special Case 的名字
$case_title = "Unknown Case"; 

if ($case_id > 0) {
    $sql = "SELECT Case_Title FROM special_case WHERE Case_ID = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $case_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $case_title = $row['Case_Title']; 
        }
        $stmt->close();
    }
}

// 7. 引入新版头部
include 'header_UI.php'; 
?>

<style>
    /* --- Hero Banner --- */
    .hero-wrap {
        height: 400px;
        position: relative;
        background-image: url('images/hero_1.jpg');
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .hero-wrap .overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.5);
    }
    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 800px;
    }
    .hero-content h1 {
        font-family: "Mansalva", cursive;
        color: #fff;
        font-size: 4rem;
        margin-bottom: 10px;
    }
    .hero-content p { font-size: 1.2rem; color: rgba(255, 255, 255, 0.9); }

    /* --- 摘要卡片 (左侧) --- */
    .summary-card {
        background: #f8f9fa;
        padding: 30px;
        border-radius: 8px;
        border-left: 5px solid #dc2626;
    }
    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        border-bottom: 1px dashed #ddd;
        padding-bottom: 15px;
    }
    .summary-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .summary-label { font-weight: bold; color: #555; }
    .summary-value { font-weight: bold; color: #dc2626; font-size: 1.1rem; }

    /* --- 付款方式按钮 (右侧) --- */
    .payment-option {
        border: 2px solid #eee;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-bottom: 20px;
        background: #fff;
        display: block; 
        width: 100%;
    }
    .payment-option:hover {
        border-color: #dc2626;
        box-shadow: 0 5px 15px rgba(0, 166, 81, 0.1);
        transform: translateY(-3px);
    }
    .payment-img {
        height: 60px;
        object-fit: contain;
        margin-bottom: 15px;
    }
    .payment-title {
        font-weight: bold;
        color: #333;
        font-size: 18px;
        display: block;
    }
    .payment-desc { font-size: 13px; color: #777; margin-bottom: 15px; }
    
    .btn-pay {
        background-color: #dc2626;
        color: white;
        border: none;
        padding: 10px 30px;
        border-radius: 30px;
        font-weight: bold;
        transition: 0.3s;
    }
    .btn-pay:hover { background-color: #b91c1c; color: white; }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Secure Payment</h1>
        <p>Support a special cause. Your contribution matters.</p>
    </div>
</div>

<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row">
            
            <div class="col-lg-5 mb-5">
                <div class="mb-4">
                    <h3 class="text-cursive mb-4" style="color: #dc2626;">Donation Summary</h3>
                    <p class="text-muted">Please review your special case donation details.</p>
                </div>

                <div class="summary-card shadow-sm">
                    <div class="summary-item">
                        <span class="summary-label">Special Case</span>
                        <span class="summary-value text-dark"><?php echo htmlspecialchars($case_title); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Type</span>
                        <span class="summary-value text-uppercase"><?php echo htmlspecialchars($donation_type); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Amount</span>
                        <span class="summary-value">RM <?php echo number_format($amount, 2); ?></span>
                    </div>
                </div>
                
                <div class="mt-4">
                    <p><small class="text-muted"><i class="icon-lock"></i> 100% of this donation goes to the selected case.</small></p>
                </div>
            </div>

            <div class="col-lg-7">
                <h3 class="text-cursive text-black mb-4 text-center">Select Payment Method</h3>
                
                <div class="row">
                    
                    <div class="col-md-6">
                        <div class="payment-option">
                            <img src="bank.jpg" alt="Bank Transfer" class="payment-img">
                            <span class="payment-title">Credit / Debit Card</span>
                            <p class="payment-desc">Visa, Mastercard, Online Banking</p>
                            
                            <form method="POST" action="Credit_Debit_Page.php">
                                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                                <input type="hidden" name="donation_type" value="<?php echo htmlspecialchars($donation_type); ?>">
                                <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">
                                <input type="hidden" name="branch_id" value="0">
                                
                                <button type="submit" class="btn-pay">Pay Now</button>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="payment-option">
                            <img src="tng.jpg" alt="Touch n Go" class="payment-img">
                            <span class="payment-title">Touch 'n Go eWallet</span>
                            <p class="payment-desc">Scan QR code to pay instantly</p>
                            
                            <form method="POST" action="TNG_Page.php">
                                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                                <input type="hidden" name="donation_type" value="<?php echo htmlspecialchars($donation_type); ?>">
                                <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">
                                <input type="hidden" name="branch_id" value="0">

                                <button type="submit" class="btn-pay">Pay Now</button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>