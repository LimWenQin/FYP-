<?php
// 1. 开启 Session (必须放在第一行)
session_start();

// 2. 检查登录
//if (!isset($_SESSION['donor_id'])) {
//    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
//    exit();
//}

$current_donor_id = $_SESSION['donor_id']; 

// 引入数据库连接
include 'dataconnection.php';

// 设置时区
date_default_timezone_set("Asia/Kuala_Lumpur");

// 3. 获取当前用户真实资料
$user_sql = "SELECT Donor_FName, Donor_LName, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?";
$u_stmt = $conn->prepare($user_sql);
$u_stmt->bind_param("i", $current_donor_id);
$u_stmt->execute();
$u_result = $u_stmt->get_result();
$user_data = $u_result->fetch_assoc();
$u_stmt->close();

// -------------------------
// 4. 显示逻辑：查询 Special Case 名字
// -------------------------
$display_case_title = ""; 
$incoming_case_id = isset($_POST['case_id']) ? $_POST['case_id'] : 0;

if ($incoming_case_id > 0) {
    $c_sql = "SELECT Case_Title FROM special_case WHERE Case_ID = ?";
    if ($c_stmt = $conn->prepare($c_sql)) {
        $c_stmt->bind_param("i", $incoming_case_id);
        $c_stmt->execute();
        $c_res = $c_stmt->get_result();
        if ($c_row = $c_res->fetch_assoc()) {
            $display_case_title = $c_row['Case_Title'];
        }
        $c_stmt->close();
    }
}

// -------------------------
// 5. 处理付款提交
// -------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_payment'])) {

    // 接收数据
    $donation_type = $_POST['donation_type']; 
    $amount = $_POST['amount'];
    
    // ✅ 处理 3 个关键 ID (0 或空 -> NULL)
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] != '' && $_POST['branch_id'] != '0' ? $_POST['branch_id'] : null;
    $case_id = isset($_POST['case_id']) && $_POST['case_id'] != '' && $_POST['case_id'] != '0' ? $_POST['case_id'] : null;
    $activity_id = isset($_POST['activity_id']) && $_POST['activity_id'] != '' && $_POST['activity_id'] != '0' ? $_POST['activity_id'] : null;

    $payment_method = "TNG eWallet";
    $txn_ref = "TXN-" . date("YmdHis");
    $now = date("Y-m-d H:i:s");
    $status = "Success";
    $bank_name = "TNG eWallet";
    $masked = "QR Payment";

    // 1️⃣ 插入 payment
    $stmt = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssissss", $payment_method, $status, $txn_ref, $amount, $now, $bank_name, $masked, $now);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // 2️⃣ 插入 orders
    $order_type = ($donation_type == "monthly") ? "Recurring" : "One-time";
    $order_status = "Completed";

    $stmt = $conn->prepare("INSERT INTO `orders` 
        (Order_FName, Order_LName, Order_ContactNumber, Order_ICNumber, Order_Email, 
         Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, Order_TXN_Ref, 
         Order_Type, Order_Status, Order_Created_At, Order_Updated_At, 
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

    // 跳转
    header("Location: Payment_Settlement_Page.php?txn_ref=$txn_ref");
    exit();
}

// -------------------------
// 6. 引入新版 Header
// -------------------------
include 'header_UI_template.php'; 
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

    /* --- 左侧：摘要卡片 --- */
    .summary-card {
        background: #f8f9fa;
        padding: 30px;
        border-radius: 8px;
        border-left: 5px solid #0057B7; /* TNG Blue */
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
    .summary-value { font-weight: bold; color: #0057B7; }
    
    .instruction-list { padding-left: 20px; color: #666; margin-top: 20px; }
    .instruction-list li { margin-bottom: 10px; }

    /* --- 右侧：TNG 卡片 --- */
    .tng-card {
        background: #fff;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.1);
        border: 1px solid #e1e1e1;
        text-align: center;
    }
    .tng-header {
        margin-bottom: 20px;
    }
    .tng-header img {
        height: 50px;
        object-fit: contain;
    }
    
    .qr-container {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 10px;
        display: inline-block;
        border: 2px dashed #ccc;
        margin-bottom: 20px;
    }
    .qr-code-img {
        width: 180px;
        height: 180px;
    }
    
    .amount-display {
        font-size: 24px;
        color: #0057B7;
        font-weight: 900;
        margin-bottom: 5px;
    }
    
    .timer {
        font-size: 14px;
        color: #dc3545;
        font-weight: bold;
    }

    .btn-tng {
        background-color: #0057B7; /* TNG Blue */
        color: #fff;
        font-weight: bold;
        font-size: 18px;
        padding: 15px;
        border: none;
        border-radius: 4px;
        width: 100%;
        cursor: pointer;
        transition: 0.3s;
        margin-top: 20px;
    }
    .btn-tng:hover {
        background-color: #004494;
        color: #fff;
    }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>e-Wallet Payment</h1>
        <p>Scan the QR code to complete your donation instantly.</p>
    </div>
</div>

<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row">
            
            <div class="col-lg-5 mb-5">
                <div class="mb-4">
                    <h3 class="text-cursive mb-4" style="color: #0057B7;">Order Summary</h3>
                    <p class="text-muted">Please confirm the details below before scanning.</p>
                </div>

                <div class="summary-card shadow-sm">
                    <div class="summary-item">
                        <span class="summary-label">Pay To</span>
                        <span class="summary-value text-dark">LOVE BRIDGE CHARITY</span>
                    </div>
                    <?php if(!empty($display_case_title)): ?>
                    <div class="summary-item">
                        <span class="summary-label">Project</span>
                        <span class="summary-value text-dark"><?php echo htmlspecialchars($display_case_title); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-item">
                        <span class="summary-label">Transaction ID</span>
                        <span class="summary-value text-muted small"><?php echo "TXN-" . date("YmdHis"); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Amount</span>
                        <span class="summary-value" style="font-size: 1.2rem;">RM <?php echo number_format((float)($_POST['amount'] ?? 5), 2); ?></span>
                    </div>
                </div>

                <div class="mt-5">
                    <h5 class="text-black mb-3">How to pay?</h5>
                    <ol class="instruction-list">
                        <li>Open your <strong>Touch 'n Go eWallet</strong> app.</li>
                        <li>Tap on the <strong>Scan</strong> icon.</li>
                        <li>Scan the QR code displayed on the right.</li>
                        <li>Verify the amount and approve the payment.</li>
                        <li>Click <strong>"Confirm Payment"</strong> once done.</li>
                    </ol>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="tng-card">
                    <div class="tng-header">
                        <h2 style="color: #0057B7; font-weight: 800; font-style: italic;">Touch 'n Go eWallet</h2>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="donation_type" value="<?php echo htmlspecialchars($_POST['donation_type'] ?? 'one-time'); ?>">
                        <input type="hidden" name="amount" value="<?php echo htmlspecialchars($_POST['amount'] ?? 5); ?>">
                        <input type="hidden" name="branch_id" value="<?php echo htmlspecialchars($_POST['branch_id'] ?? 0); ?>">
                        <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($_POST['case_id'] ?? 0); ?>">
                        <input type="hidden" name="activity_id" value="<?php echo htmlspecialchars($_POST['activity_id'] ?? ''); ?>">

                        <div class="qr-container">
                            <img src="images/qr_code.png" alt="TNG QR Code" class="qr-code-img" onerror="this.src='https://via.placeholder.com/180x180?text=QR+Code'">
                        </div>

                        <div class="amount-display">
                            RM <?php echo number_format((float)($_POST['amount'] ?? 5), 2); ?>
                        </div>
                        <p class="timer"><i class="icon-clock-o"></i> QR Code expires in 60s</p>

                        <button type="submit" name="confirm_payment" class="btn-tng">I Have Made Payment</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer_UI_template.php'; ?>