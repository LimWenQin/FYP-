<?php
// 1. 开启 Session
session_start();

// 2. 检查登录
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id']; 

// 引入数据库
include 'dataconnection.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

// 3. 获取用户信息
$user_sql = "SELECT Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?";
$u_stmt = $conn->prepare($user_sql);
$u_stmt->bind_param("i", $current_donor_id);
$u_stmt->execute();
$u_result = $u_stmt->get_result();
$user_data = $u_result->fetch_assoc();
$u_stmt->close();

// ---------------------------------------------------------
// PHP 处理逻辑
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cvc'])) {

    $donation_type = $_POST['donation_type']; 
    $amount = $_POST['amount'];
    $bank_name = $_POST['bank_display']; // 获取自动识别的卡类型
    $card_number = str_replace(' ', '', $_POST['card']); // 去除空格
    
    // 处理 ID
    $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
    $case_id = !empty($_POST['case_id']) ? $_POST['case_id'] : null;
    $activity_id = !empty($_POST['activity_id']) ? $_POST['activity_id'] : null;
    
    $txn_ref = "TXN-" . date("YmdHis");
    $now = date("Y-m-d H:i:s");
    $status = "Success";
    $payment_method = "Credit/Debit Card";
    $masked_card = substr($card_number, 0, 4) . " **** **** " . substr($card_number, -4);

    // 1️⃣ 插入 payment
    $stmt = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssissss", $payment_method, $status, $txn_ref, $amount, $now, $bank_name, $masked_card, $now);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // 2️⃣ 插入 orders
    $order_type = ($donation_type == "monthly") ? "Recurring" : "One-time";
    $order_status = "Completed";
    
    $stmt = $conn->prepare("INSERT INTO orders 
        (Order_Name, Order_ContactNumber, Order_ICNumber, Order_Email, 
         Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, 
         Order_TXN_Ref, Order_Type, Order_Status, Order_Created_At, Order_Updated_At, 
         Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID)
        VALUES (?, ?, ?, ?, ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // 合并名字
    $full_name = $user_data['Donor_FName'] . " " . $user_data['Donor_LName'];

    $stmt->bind_param("ssssdsssssssiiiii", 
        $full_name, $user_data['Donor_ContactNumber'], $user_data['Donor_ICNumber'], $user_data['Donor_Email'], 
        $amount, $payment_method, $status, $txn_ref, $order_type, $order_status, $now, $now, 
        $current_donor_id, $payment_id, $branch_id, $activity_id, $case_id
    );
    $stmt->execute();
    $stmt->close();

    // 3️⃣ 插入 recurring
    if ($donation_type == "monthly") {
        $deduction_date = date("Y-m-d", strtotime("+1 month"));
        $stmt = $conn->prepare("INSERT INTO recurring_donation (Recurring_Amount, Recurring_Payment_Method, Recurring_Deduction_Date, Recurring_Status, Recurring_Created_At, Recurring_Updated_At, Donor_ID) VALUES (?, ?, ?, 'Active', ?, ?, ?)");
        $stmt->bind_param("dssssi", $amount, $payment_method, $deduction_date, $now, $now, $current_donor_id);
        $stmt->execute();
        $stmt->close();
    }

    // 跳转
    header("Location: Payment_Settlement_Page.php?txn_ref=$txn_ref");
    exit();
}

// 引入头部
include 'header_UI.php'; 
?>

<style>
    /* Hero Banner */
    .hero-wrap {
        height: 400px;
        position: relative;
        background-image: url('images/hero_4.jpg');
        background-size: cover;
        background-position: center;
        display: flex; align-items: center; justify-content: center; text-align: center;
    }
    .hero-wrap .overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); }
    .hero-content { position: relative; z-index: 2; max-width: 800px; }
    .hero-content h1 { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #fff; font-size: 4rem; margin-bottom: 10px; }
    .hero-content p { font-size: 1.2rem; color: rgba(255, 255, 255, 0.9); }

    /* 表单样式 */
    .payment-card {
        background: #fff; padding: 40px; border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1); border: 1px solid #f0f0f0;
    }
    .form-group label { font-weight: bold; color: #555; margin-bottom: 8px; display: block; }
    .form-control {
        height: 50px; border-radius: 4px; border: 1px solid #ddd;
        padding: 10px 15px; font-size: 16px; width: 100%;
    }
    .form-control:focus { border-color: #00a651; box-shadow: none; outline: none; }
    
    /* 银行图标 */
    .card-icon {
        position: absolute;
        right: 15px;
        top: 45px; /* 调整位置 */
        font-size: 24px;
        color: #999;
    }
    .input-wrapper { position: relative; }

    /* 按钮 */
    .btn-confirm {
        background-color: #00a651; color: #fff; font-weight: bold; font-size: 18px;
        padding: 15px; border: none; border-radius: 4px; width: 100%; cursor: pointer; transition: 0.3s;
    }
    .btn-confirm:hover { background-color: #008f45; }

    .card-row { display: flex; gap: 20px; }
    .card-col { flex: 1; }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Secure Payment</h1>
        <p>Enter your card details securely. We support Auto-Detection.</p>
    </div>
</div>

<?php 
    // 检查是否有 case_id (Special Case)
    // 这里的 case_id 可能是通过 POST 传过来的
    $is_special_case = (isset($_POST['case_id']) && !empty($_POST['case_id']));

    if ($is_special_case) {
        $flow_type = 'special';
        $current_step = 2; // Special Flow Step 2 (Payment)
    } else {
        $flow_type = 'standard';
        $current_step = 3; // Standard Flow Step 3 (Payment)
    }

    include 'stepper.php'; 
?>
<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row">
            
            <div class="col-md-6 mb-5">
                <img src="yourimage.jpg" alt="Donation Story" class="img-fluid rounded mb-4 shadow-sm">
                <h3 class="text-cursive mb-4" style="color: #00a651;">Thank You!</h3>
                <p>Your donation of <strong>RM <?php echo htmlspecialchars($_POST['amount'] ?? 0); ?></strong> makes a difference.</p>
                <div class="alert alert-success">
                    <i class="fas fa-shield-alt"></i> Your card information is encrypted and secure.
                </div>
            </div>

            <div class="col-md-6">
                <div class="payment-card">
                    <h3 class="text-cursive text-black text-center mb-4">Card Details</h3>
                    
                    <form class="bank-form" method="POST" action="">
                        
                        <input type="hidden" name="donation_type" value="<?php echo $_POST['donation_type'] ?? 'one-time'; ?>">
                        <input type="hidden" name="amount" value="<?php echo $_POST['amount'] ?? 50; ?>">
                        <input type="hidden" name="branch_id" value="<?php echo $_POST['branch_id'] ?? 0; ?>">
                        <input type="hidden" name="case_id" value="<?php echo $_POST['case_id'] ?? 0; ?>">
                        <input type="hidden" name="activity_id" value="<?php echo $_POST['activity_id'] ?? ''; ?>">

                        <div class="form-group mb-3 input-wrapper">
                            <label for="card">Card Number</label>
                            <input type="text" id="card" name="card" class="form-control" maxlength="19" placeholder="0000 0000 0000 0000" required>
                            <i id="card-brand-icon" class="fas fa-credit-card card-icon"></i>
                        </div>

                        <div class="form-group mb-3">
                            <label for="bank_display">Card Type / Bank</label>
                            <input type="text" id="bank_display" name="bank_display" class="form-control" value="Unknown Bank" readonly style="background-color: #f9f9f9;">
                        </div>

                        <div class="card-row mb-4">
                            <div class="card-col">
                                <label for="exp">Expiration Date</label>
                                <input type="text" id="exp" name="exp" class="form-control" placeholder="MM/YY" maxlength="5" required>
                            </div>
                            <div class="card-col">
                                <label for="cvc">CVC / CVV</label>
                                <input type="text" id="cvc" name="cvc" class="form-control" maxlength="3" placeholder="123" required>
                            </div>
                        </div>

                        <button type="submit" class="btn-confirm">Confirm Payment</button>
                        
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cardInput = document.getElementById('card');
    const bankDisplay = document.getElementById('bank_display');
    const cardIcon = document.getElementById('card-brand-icon');
    const expInput = document.getElementById('exp');

    // 1. 卡号输入格式化 (每4位加空格) + 识别
    cardInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // 去除非数字
        let formattedValue = '';
        
        // 格式化：每4位加空格
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 4 === 0) {
                formattedValue += ' ';
            }
            formattedValue += value[i];
        }
        e.target.value = formattedValue;

        // 识别卡类型
        identifyCardType(value);
    });

    // 2. 有效期格式化 (自动加斜杠)
    expInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 3) {
            e.target.value = value.slice(0, 2) + '/' + value.slice(2, 4);
        }
    });

    // 3. 识别函数
    function identifyCardType(number) {
        // 正则表达式匹配
        const patterns = {
            visa: /^4/,
            mastercard: /^5[1-5]/,
            amex: /^3[47]/,
            discover: /^6(?:011|5)/,
            jcb: /^(?:2131|1800|35\d{3})/
        };

        // 图标类名映射 (FontAwesome)
        const icons = {
            visa: 'fa-cc-visa',
            mastercard: 'fa-cc-mastercard',
            amex: 'fa-cc-amex',
            discover: 'fa-cc-discover',
            jcb: 'fa-cc-jcb',
            unknown: 'fa-credit-card'
        };

        let type = 'unknown';
        let bankName = 'Unknown Card';

        if (patterns.visa.test(number)) {
            type = 'visa';
            bankName = 'Visa Card'; // 默认 Visa
            // 如果您想根据前6位 BIN 码识别是否是 Maybank/CIMB，需要庞大的数据库
            // 这里简单处理：
            if(number.startsWith('4585')) bankName = 'Visa (Maybank)';
            if(number.startsWith('4378')) bankName = 'Visa (CIMB)';
        } 
        else if (patterns.mastercard.test(number)) {
            type = 'mastercard';
            bankName = 'MasterCard';
            if(number.startsWith('5588')) bankName = 'MasterCard (Public Bank)';
        } 
        else if (patterns.amex.test(number)) {
            type = 'amex';
            bankName = 'American Express';
        }

        // 更新 UI
        bankDisplay.value = bankName;
        
        // 更新图标颜色和类型
        cardIcon.className = `fab ${icons[type]} card-icon`;
        cardIcon.style.color = type === 'unknown' ? '#999' : '#00a651'; // 识别成功变绿
    }
});
</script>

<?php include 'footer.php'; ?>