<?php
// 1. 开启 Session (必须放在第一行)
session_start();

// 2. 检查登录 (如果没有登录，跳转)
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id']; // 获取当前登录用户的ID

// 引入数据库连接和头部
include 'dataconnection.php';

// 设置时区
date_default_timezone_set("Asia/Kuala_Lumpur");

// 3. 获取当前用户的详细资料
$user_sql = "SELECT Donor_FName, Donor_LName, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?";
$u_stmt = $conn->prepare($user_sql);
$u_stmt->bind_param("i", $current_donor_id);
$u_stmt->execute();
$u_result = $u_stmt->get_result();
$user_data = $u_result->fetch_assoc();
$u_stmt->close();

// ---------------------------------------------------------
// PHP 处理逻辑：当点击 Confirm Payment 时执行
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cvc'])) {

    // 接收基础数据
    $donation_type = $_POST['donation_type']; 
    $amount = $_POST['amount'];
    $bank_name = $_POST['bank'];
    $card_number = $_POST['card'];
    
    // 处理 ID 逻辑 (把 0 转为 NULL)
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] != '' && $_POST['branch_id'] != '0' ? $_POST['branch_id'] : null;
    $case_id = isset($_POST['case_id']) && $_POST['case_id'] != '' && $_POST['case_id'] != '0' ? $_POST['case_id'] : null;
    $activity_id = isset($_POST['activity_id']) && $_POST['activity_id'] != '' && $_POST['activity_id'] != '0' ? $_POST['activity_id'] : null;
    
    $txn_ref = "TXN-" . date("YmdHis");
    $now = date("Y-m-d H:i:s");
    $status = "Success";
    $payment_method = "Bank Transfer";
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
        (Order_FName, Order_LName, Order_ContactNumber, Order_ICNumber, Order_Email, 
         Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, 
         Order_TXN_Ref, Order_Type, Order_Status, Order_Created_At, Order_Updated_At, 
         Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID)
        VALUES (?, ?, ?, ?, ?, ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssssdsssssssiiiii", 
        $user_data['Donor_FName'], $user_data['Donor_LName'], $user_data['Donor_ContactNumber'], $user_data['Donor_ICNumber'], $user_data['Donor_Email'], 
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

    // ✅ 跳转 (这里还没有输出 HTML，所以跳转会成功)
    header("Location: Payment_Settlement_Page.php?txn_ref=$txn_ref");
    exit();
}

// ---------------------------------------------------------
// 4. 引入新版 Header (逻辑处理完才引入)
// ---------------------------------------------------------
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

    /* --- 付款表单样式 --- */
    .payment-card {
        background: #fff;
        padding: 40px;
        border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        border: 1px solid #f0f0f0;
    }

    .form-group label {
        font-weight: bold;
        color: #555;
        margin-bottom: 8px;
        display: block;
    }
    
    .form-control {
        height: 50px;
        border-radius: 4px;
        border: 1px solid #ddd;
        padding: 10px 15px;
        font-size: 16px;
    }
    .form-control:focus {
        border-color: #dc2626;
        box-shadow: none;
    }

    .btn-confirm {
        background-color: #dc2626;
        color: #fff;
        font-weight: bold;
        font-size: 18px;
        padding: 15px;
        border: none;
        border-radius: 4px;
        width: 100%;
        cursor: pointer;
        transition: 0.3s;
    }
    .btn-confirm:hover {
        background-color: #b91c1c;
        color: #fff;
    }

    /* 分隔两个输入框 */
    .card-row {
        display: flex;
        gap: 20px;
    }
    .card-col {
        flex: 1;
    }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Secure Payment</h1>
        <p>Enter your card details to complete the donation securely.</p>
    </div>
</div>

<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row">
            
            <div class="col-md-6 mb-5">
                <img src="yourimage.jpg" alt="Donation Story" class="img-fluid rounded mb-4 shadow-sm">
                <h3 class="text-cursive mb-4" style="color: #dc2626;">Thank You!</h3>
                <p>Your donation of <strong>RM <?php echo htmlspecialchars($_POST['amount'] ?? 0); ?></strong> will make a huge difference.</p>
                <p class="text-muted">We use secure encryption to protect your personal and financial information. Your generosity helps us continue our mission.</p>
                
                <?php if(isset($_POST['case_id']) && $_POST['case_id'] > 0): ?>
                    <div class="alert alert-info mt-3">
                        <strong>Special Project:</strong> You are donating to Case ID #<?php echo htmlspecialchars($_POST['case_id']); ?>
                    </div>
                <?php endif; ?>
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

                        <div class="form-group mb-3">
                            <label for="bank">Bank Name</label>
                            <select id="bank" name="bank" class="form-control" required>
                                <option value="">-- Select Bank --</option>
                                <option value="Maybank">Maybank</option>
                                <option value="CIMB Bank">CIMB Bank</option>
                                <option value="Public Bank">Public Bank</option>
                                <option value="Hong Leong Bank">Hong Leong Bank</option>
                                <option value="RHB Bank">RHB Bank</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="card">Card Number</label>
                            <input type="text" id="card" name="card" class="form-control" maxlength="16" placeholder="1234 5678 9012 3456" pattern="\d{16}" required>
                        </div>

                        <div class="card-row mb-4">
                            <div class="card-col">
                                <label for="exp">Expiration Date</label>
                                <input type="text" id="exp" name="exp" class="form-control" placeholder="MM/YY" maxlength="5" pattern="(0[1-9]|1[0-2])\/\d{2}" required>
                            </div>
                            <div class="card-col">
                                <label for="cvc">CVC / CVV</label>
                                <input type="text" id="cvc" name="cvc" class="form-control" maxlength="3" pattern="\d{3}" placeholder="123" required>
                            </div>
                        </div>

                        <button type="submit" class="btn-confirm">Confirm Payment</button>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted"><i class="icon-lock"></i> Payments are secure and encrypted.</small>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>