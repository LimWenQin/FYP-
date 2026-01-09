<?php
// 1. 开启 Session
session_start();
include 'dataconnection.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

// 2. 检查登录
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id']; 

// 3. 检查 Session 数据 (确保是从正常流程进来的)
if (empty($_SESSION['donation_data'])) {
    // 如果 Session 空了，尝试根据 Referrer 决定跳回哪里，或者直接回首页
    header("Location: Homepage.php");
    exit();
}

// 从 Session 提取数据 (用于显示和填入隐藏字段)
$sess_data = $_SESSION['donation_data'];
$amount = $sess_data['amount'];
$donation_type = $sess_data['type'];
// 使用 null 合并运算符防止警告
$branch_id = $sess_data['branch_id'] ?? null;
$case_id = $sess_data['case_id'] ?? null;
$activity_id = $sess_data['activity_id'] ?? null;
$source = $sess_data['source'] ?? 'standard'; // 来源标记

// 4. 获取用户信息 (用于填入 Order 表)
$user_sql = "SELECT Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?";
$u_stmt = $conn->prepare($user_sql);
$u_stmt->bind_param("i", $current_donor_id);
$u_stmt->execute();
$user_data = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

if (!$user_data) {
    session_destroy();
    echo "<script>alert('User data not found.'); window.location.href='donor_login.php';</script>";
    exit();
}

// ---------------------------------------------------------
// PHP 处理逻辑 (当用户点击 Confirm Payment)
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cvc'])) {

    // 获取表单提交的数据 (Hidden Inputs)
    // 即使表单被篡改，我们也优先使用 Session 里的金额，更安全
    $p_amount = $amount; 
    $p_type = $donation_type;
    $bank_name = $_POST['bank_display']; 
    $card_number = str_replace(' ', '', $_POST['card']); 
    
    // 生成交易信息
    $txn_ref = "TXN-" . date("YmdHis") . "-" . rand(100, 999);
    $now = date("Y-m-d H:i:s");
    $status = "Success";
    $payment_method = "Credit/Debit Card";
    $masked_card = substr($card_number, 0, 4) . " **** **** " . substr($card_number, -4);

    // 1️⃣ 插入 payment 表
    $stmt = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssissss", $payment_method, $status, $txn_ref, $p_amount, $now, $bank_name, $masked_card, $now);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // 2️⃣ 插入 orders 表
    $order_type = ($p_type == "monthly") ? "Recurring" : "One-time";
    $order_status = "Completed";
    
    // 决定报税状态
    $tax_status = (isset($sess_data['tax_receipt']) && $sess_data['tax_receipt'] == 1) ? 'Requested' : 'Not_Requested';

    $full_name = $user_data['Donor_Name']; 

    $stmt = $conn->prepare("INSERT INTO orders 
        (Order_Name, Order_ContactNumber, Order_ICNumber, Order_Email, 
         Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, 
         Order_TXN_Ref, Order_Type, Order_Status, Tax_Receipt_Status, Order_Created_At, Order_Updated_At, 
         Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID)
        VALUES (?, ?, ?, ?, ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssdssssssssiiiii", 
        $full_name, 
        $user_data['Donor_ContactNumber'], 
        $user_data['Donor_ICNumber'], 
        $user_data['Donor_Email'], 
        $p_amount, $payment_method, $status, $txn_ref, $order_type, $order_status, $tax_status, $now, $now, 
        $current_donor_id, $payment_id, $branch_id, $activity_id, $case_id
    );
    $stmt->execute();
    $stmt->close();

    // 3️⃣ 插入 recurring_donation 表 (如果是月捐)
    if ($p_type == "monthly") {
        $deduction_date = date("Y-m-d", strtotime("+1 month"));
        $rec_status = 'Active';
        $stmt = $conn->prepare("INSERT INTO recurring_donation (Recurring_Amount, Recurring_Payment_Method, Recurring_Deduction_Date, Recurring_Status, Recurring_Created_At, Recurring_Updated_At, Donor_ID, Branch_ID, Activity_ID, Case_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("dssssiiii", $p_amount, $payment_method, $deduction_date, $rec_status, $now, $now, $current_donor_id, $branch_id, $activity_id, $case_id);
        $stmt->execute();
        $stmt->close();
    }

    // 4️⃣ 更新筹款进度
    if ($case_id != null) {
        $conn->query("UPDATE special_case SET Raised_Amount = Raised_Amount + $p_amount WHERE Case_ID = $case_id");
    }
    if ($activity_id != null) {
        $conn->query("UPDATE activity SET Activity_GetAmount = Activity_GetAmount + $p_amount WHERE Activity_ID = $activity_id");
    }

    // 5️⃣ 清除 Session (可选，或者留给 Settlement 页面清除)
    // unset($_SESSION['donation_data']); 

    // 跳转
    header("Location: Payment_Settlement_Page.php?txn_ref=$txn_ref");
    exit();
}

include 'header_UI.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
    .hero-content h1 { font-family: 'Segoe UI', sans-serif; color: #fff; font-size: 4rem; margin-bottom: 10px; }
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
    .card-icon { position: absolute; right: 15px; top: 45px; font-size: 24px; color: #999; }
    .input-wrapper { position: relative; }

    /* 按钮组样式 (New) */
    .nav-buttons { display: flex; gap: 20px; margin-top: 30px; }
    .btn-nav {
        flex: 1; padding: 15px; border-radius: 8px; font-size: 1.1rem; font-weight: bold;
        cursor: pointer; border: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.2s;
        text-decoration: none; /* for <a> tag */
    }
    
    /* Previous Button */
    .btn-prev { background: #e5e7eb; color: #374151; border: 1px solid #d1d5db; }
    .btn-prev:hover { background: #d1d5db; color: #111; text-decoration: none; }

    /* Confirm Button */
    .btn-confirm { background: #00a651; color: white; }
    .btn-confirm:hover { background: #008f45; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 166, 81, 0.3); }

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
    // 判断显示哪个 Step
    if ($source == 'special_case') {
        $flow_type = 'special';
        $current_step = 3; // Special 流程的第3步 (Details -> Method -> Payment)
    } else {
        $flow_type = 'standard';
        $current_step = 4; // Standard 流程的第4步
    }
    
    if (file_exists('stepper.php')) {
        include 'stepper.php';
    }
?>

<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row">
            
            <div class="col-md-6 mb-5">
                <img src="images/about_1.jpg" alt="Donation Story" class="img-fluid rounded mb-4 shadow-sm">
                <h3 class="text-cursive mb-4" style="color: #00a651;">Thank You!</h3>
                <p>Your donation of <strong style="font-size:1.2rem; color:#dc2626;">RM <?php echo number_format($amount, 2); ?></strong> makes a difference.</p>
                
                <div class="alert alert-success">
                    <i class="fas fa-shield-alt"></i> Your card information is encrypted and secure.
                </div>
            </div>

            <div class="col-md-6">
                <div class="payment-card">
                    <h3 class="text-cursive text-black text-center mb-4">Card Details</h3>
                    
                    <form class="bank-form" method="POST" action="">
                        
                        <input type="hidden" name="donation_type" value="<?php echo htmlspecialchars($donation_type); ?>">
                        <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">

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

                        <div class="nav-buttons">
                            <?php
                                // 动态生成返回链接
                                $back_url = "Payment_Ways_Page.php"; // 默认
                                if ($source == 'special_case') {
                                    $back_url = "S_C_Payment_Ways_Page.php";
                                }
                            ?>
                            <a href="<?php echo $back_url; ?>" class="btn-nav btn-prev">
                                <i class="fas fa-arrow-left"></i> Previous
                            </a>

                            <button type="submit" class="btn-nav btn-confirm">
                                Confirm Payment
                            </button>
                        </div>
                        
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.bank-form'); 
    const cardInput = document.getElementById('card');
    const bankDisplay = document.getElementById('bank_display');
    const cardIcon = document.getElementById('card-brand-icon');
    const expInput = document.getElementById('exp');
    const cvcInput = document.getElementById('cvc');

    // 1. 卡号输入格式化
    cardInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); 
        let formattedValue = '';
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 4 === 0) formattedValue += ' ';
            formattedValue += value[i];
        }
        e.target.value = formattedValue;
        identifyCardType(value);
    });

    // 2. 有效期格式化
    expInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); 
        if (value.length >= 3) {
            e.target.value = value.slice(0, 2) + '/' + value.slice(2, 4);
        } else {
            e.target.value = value;
        }
    });

    // 3. CVC 限制
    cvcInput.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, ''); 
    });

    // 4. 表单提交验证 (SweetAlert2)
    form.addEventListener('submit', function(e) {
        const expValue = expInput.value; 
        
        if (expValue.length !== 5) {
            e.preventDefault();
            Swal.fire({ title: 'Error', text: 'Please enter a valid expiration date (MM/YY).', icon: 'warning', confirmButtonColor: '#00a651' });
            return;
        }

        const [mm, yy] = expValue.split('/').map(num => parseInt(num, 10));
        const now = new Date();
        const currentYear = parseInt(now.getFullYear().toString().substr(-2)); 
        const currentMonth = now.getMonth() + 1; 

        let errorMsg = '';
        if (mm < 1 || mm > 12) errorMsg = "Invalid month.";
        else if (yy < currentYear) errorMsg = "Card has expired.";
        else if (yy === currentYear && mm < currentMonth) errorMsg = "Card has expired.";

        if (errorMsg !== '') {
            e.preventDefault(); 
            Swal.fire({ title: 'Error', text: errorMsg, icon: 'error', confirmButtonColor: '#00a651' });
        }
    });

    function identifyCardType(number) {
        const patterns = { visa: /^4/, mastercard: /^5[1-5]/, amex: /^3[47]/ };
        const icons = { visa: 'fa-cc-visa', mastercard: 'fa-cc-mastercard', amex: 'fa-cc-amex', unknown: 'fa-credit-card' };
        let type = 'unknown';
        let bankName = 'Unknown Card';

        if (patterns.visa.test(number)) { type = 'visa'; bankName = 'Visa Card'; } 
        else if (patterns.mastercard.test(number)) { type = 'mastercard'; bankName = 'MasterCard'; } 
        else if (patterns.amex.test(number)) { type = 'amex'; bankName = 'American Express'; }

        bankDisplay.value = bankName;
        cardIcon.className = `fab ${icons[type]} card-icon`;
        cardIcon.style.color = type === 'unknown' ? '#999' : '#00a651';
    }
});
</script>

<?php include 'footer.php'; ?>