<?php
// 1. 开启 Session (必须放在第一行)
session_start();

// 2. 检查登录
if (!isset($_SESSION['donor_id'])) {
    die("Session expired. Please login again via donor_login.php");
}

$current_donor_id = $_SESSION['donor_id']; 

// 引入数据库连接
include 'dataconnection.php';

// 设置时区
date_default_timezone_set("Asia/Kuala_Lumpur");

// 3. 获取当前用户真实资料
$user_sql = "SELECT Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?";
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
?>

<!DOCTYPE html>
<html>
<head>
<title>Touch 'n Go eWallet Payment</title>
<style>
    /* ... 样式保持不变 ... */
    body { margin: 0; font-family: Arial, sans-serif; background-color: #FFF5E4; color: #4A4A4A; }
    .header { background-color: #0057B7; color: white; display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; }
    .header .title { font-size: 22px; font-weight: bold; }
    .header .call { font-size: 16px; }
    .container { display: flex; justify-content: center; align-items: flex-start; padding: 40px; gap: 40px; }
    .payment-box, .order-summary { background-color: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
    .payment-box { padding: 30px; width: 450px; }
    .payment-box h3 { color: #0057B7; text-align: center; }
    .qr-box { text-align: center; margin-top: 20px; }
    .qr-box img { width: 180px; height: 180px; border: 2px solid #ccc; border-radius: 10px; margin-bottom: 10px; }
    .amount { font-size: 20px; font-weight: bold; color: #F28585; text-align: center; margin-top: 15px; }
    .payment-steps { font-size: 15px; line-height: 1.6; }
    .order-summary { padding: 25px; width: 280px; }
    .order-summary h3 { color: #0057B7; margin-bottom: 15px; border-bottom: 2px solid #A8D5BA; padding-bottom: 5px; }
    .order-summary p { margin: 8px 0; font-size: 15px; }
    .order-summary .total { font-size: 18px; font-weight: bold; text-align: right; color: #F28585; margin-top: 15px; }
</style>
</head>
<body>

    <div class="header">
        <div class="title">Touch'N Go eWallet</div>
        <div class="call">Call Center: +603-5022 3888</div>
    </div>

    <?php 
        // 智能判断：根据是否传入 case_id 决定显示 Step 2 (Special) 还是 Step 3 (Standard)
        $is_special_case = (isset($_POST['case_id']) && !empty($_POST['case_id']));

        if ($is_special_case) {
            $flow_type = 'special';
            $current_step = 2; // Step 2 (Payment)
        } else {
            $flow_type = 'standard';
            $current_step = 3; // Step 3 (Payment)
        }
        
        include 'stepper.php'; 
    ?>
    <div class="container">
        <div class="payment-box">
            <h3>Payment Details</h3>
            <form method="POST" action="">
                <input type="hidden" name="donation_type" value="<?php echo htmlspecialchars($_POST['donation_type'] ?? 'one-time'); ?>">
                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($_POST['amount'] ?? 5); ?>">
                <input type="hidden" name="branch_id" value="<?php echo htmlspecialchars($_POST['branch_id'] ?? 0); ?>">
                <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($_POST['case_id'] ?? 0); ?>">
                <input type="hidden" name="activity_id" value="<?php echo htmlspecialchars($_POST['activity_id'] ?? ''); ?>">

                <div class="qr-box">
                    <img src="your_qr_image_here.png" alt="QR Code">
                    <div class="amount">RM <?php echo number_format($_POST['amount'] ?? 5, 2); ?></div>
                    <p>QR Code will expire in <b>60s</b></p>
                </div>

                <div class="payment-steps">
                    <p><b>Pay with your Touch 'n Go eWallet!</b></p>
                    <ol>
                        <li>Open your TNG eWallet app.</li>
                        <li>Tap on “Scan” and scan the QR above.</li>
                        <li>After payment, click confirm below.</li>
                    </ol>
                </div>

                <button type="submit" name="confirm_payment" style="margin-top:20px; width:100%; padding:12px; background-color:#0057B7; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer;">
                    Confirm Payment
                </button>
            </form>
        </div>

        <div class="order-summary">
            <h3>Order Summary</h3>
            <p><b>Payment To:</b> CARE FOR MALAYSIA SOCIETY</p>
            <p><b>Transaction No:</b> <?php echo "TXN-" . date("YmdHis"); ?></p>
            <p><b>Payment Details:</b> Donation</p>
            
            <?php if(!empty($display_case_title)): ?>
                <p><b>Special Project:</b><br><?php echo htmlspecialchars($display_case_title); ?></p>
            <?php endif; ?>

            <p class="total">Total: RM <?php echo number_format($_POST['amount'] ?? 5, 2); ?></p>
        </div>
    </div>

</body>
</html>