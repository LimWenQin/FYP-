<?php
// 连接数据库
$conn = new mysqli("localhost", "root", "", "donation_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 当表单提交时执行
// 检查这是否是一个 POST 请求，并且是否点击了 "confirm_payment" 按钮
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_payment'])) {

    // 从上一页接收数据
    $donation_type = $_POST['donation_type']; 
    $amount = $_POST['amount'];               
    $branch_id = $_POST['branch_id'];         
    $payment_method = "TNG eWallet";
    $txn_ref = "TXN-" . date("YmdHis");
    $now = date("Y-m-d H:i:s");

    // 1️⃣ 插入到 payment 表
    $stmt = $conn->prepare("INSERT INTO payment 
        (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $status = "Success";
    $bank_name = "TNG eWallet";
    $masked = "QR Payment";
    $stmt->bind_param("sssissss", $payment_method, $status, $txn_ref, $amount, $now, $bank_name, $masked, $now);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // 2️⃣ 插入到 order 表（假设 Donor_ID=1, Activity_ID=1）
    $stmt = $conn->prepare("INSERT INTO `orders` 
        (Order_FName, Order_LName, Order_ContactNumber, Order_ICNumber, Order_Email, 
         Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, Order_TXN_Ref, 
         Order_Type, Order_Status, Order_Created_At, Order_Updated_At, Donor_ID, Payment_ID, Branch_ID, Activity_ID)
        VALUES ('John', 'Tan', '0123456789', '990101-10-1234', 'john.tan@email.com', 
         ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 1)");
    $order_type = ($donation_type == "monthly") ? "Recurring" : "One-time";
    $order_status = "Completed";
    $stmt->bind_param("dssssssiii", $amount, $payment_method, $status, $txn_ref, $order_type, $order_status, $now, $now, $payment_id, $branch_id);
    $stmt->execute();
    $stmt->close();

    // 3️⃣ 如果是每月捐款，插入 recurring_donation 表
    if ($donation_type == "monthly") {
        $deduction_date = date("Y-m-d", strtotime("+1 month"));
        $stmt = $conn->prepare("INSERT INTO recurring_donation 
            (Recurring_Amount, Recurring_Payment_Method, Recurring_Deduction_Date, Recurring_Status, Recurring_Created_At, Recurring_Updated_At, Donor_ID)
            VALUES (?, ?, ?, 'Active', ?, ?, 1)");
        $stmt->bind_param("dssss", $amount, $payment_method, $deduction_date, $now, $now);
        $stmt->execute();
        $stmt->close();
    }

    // 4️⃣ 跳转到成功页面
    header("Location: Payment_Settlement_Page.php?txn_ref=$txn_ref");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Touch 'n Go eWallet Payment</title>
<style>
    body 
    {
        margin: 0;
        font-family: Arial, sans-serif;
        background-color: #FFF5E4;
        color: #4A4A4A;
    }
    .header 
    {
        background-color: #0057B7;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 40px;
    }
    .header .title 
    { 
        font-size: 22px; 
        font-weight: bold; 
    }
    .header .call 
    { 
        font-size: 16px; 
    }
    .container 
    { 
        display: flex; 
        justify-content: center; 
        align-items: flex-start; 
        padding: 40px; gap: 40px; 
    }
    .payment-box, .order-summary 
    {
        background-color: white; 
        border-radius: 10px; 
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .payment-box 
    { 
        padding: 30px; 
        width: 450px; 
    }
    .payment-box h3 
    { 
        color: #0057B7; 
        text-align: center; 
    }
    .qr-box 
    { 
        text-align: center; 
        margin-top: 20px; 
    }
    .qr-box img 
    { 
        width: 180px; 
        height: 180px; 
        border: 2px solid #ccc; 
        border-radius: 10px; 
        margin-bottom: 10px; 
    }
    .amount 
    { 
        font-size: 20px; 
        font-weight: bold; 
        color: #F28585; 
        text-align: center; 
        margin-top: 15px; 
    }
    .payment-steps 
    { 
        font-size: 15px; 
        line-height: 1.6; 
    }
    .order-summary 
    { 
        padding: 25px; 
        width: 280px; 
    }
    .order-summary h3 
    { 
        color: #0057B7; 
        margin-bottom: 15px; 
        border-bottom: 2px solid #A8D5BA; 
        padding-bottom: 5px; 
    }
    .order-summary p 
    { 
        margin: 8px 0; 
        font-size: 15px; 
    }
    .order-summary .total 
    { 
        font-size: 18px; 
        font-weight: bold; 
        text-align: right; 
        color: #F28585; 
        margin-top: 15px; 
    }
    .footer 
    { 
        text-align: center; 
        font-size: 14px; 
        margin-top: 40px; 
        color: #666; 
    }
</style>
</head>
<body>

    <div class="header">
        <div class="title">Touch'N Go eWallet</div>
        <div class="call">Call Center: +603-5022 3888</div>
    </div>

    <div class="container">
        <!-- 左边付款详情 -->
        <div class="payment-box">
            <h3>Payment Details</h3>
            <form method="POST" action="">
                <input type="hidden" name="donation_type" value="<?php echo $_POST['donation_type'] ?? 'one-time'; ?>">
                <input type="hidden" name="amount" value="<?php echo $_POST['amount'] ?? 5; ?>">
                <input type="hidden" name="branch_id" value="<?php echo $_POST['branch_id'] ?? 1; ?>">

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

        <!-- 右边订单摘要 -->
        <div class="order-summary">
            <h3>Order Summary</h3>
            <p><b>Payment To:</b> CARE FOR MALAYSIA SOCIETY</p>
            <p><b>Transaction No:</b> <?php echo "TXN-" . date("YmdHis"); ?></p>
            <p><b>Payment Details:</b> Donation</p>
            <p class="total">Total: RM <?php echo number_format($_POST['amount'] ?? 5, 2); ?></p>
        </div>
    </div>

</body>
</html>


<?php
session_start(); // 1. 开启 Session

// 2. 检查登录状态
if (!isset($_SESSION['donor_id'])) {
    // 如果没登录，重定向回登录页或者给个错误提示
    die("Session expired. Please login again via donor_login.php");
}

$current_donor_id = $_SESSION['donor_id']; // 获取当前用户ID

// 设置时区
date_default_timezone_set("Asia/Kuala_Lumpur");

// 连接数据库
$conn = new mysqli("localhost", "root", "", "donation_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. 获取当前用户的详细资料 (用于填入 orders 表)
$user_sql = "SELECT Donor_FName, Donor_LName, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?";
$u_stmt = $conn->prepare($user_sql);
$u_stmt->bind_param("i", $current_donor_id);
$u_stmt->execute();
$u_result = $u_stmt->get_result();
$user_data = $u_result->fetch_assoc();
$u_stmt->close();

// 当表单提交时执行
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_payment'])) {

    // 从上一页接收数据
    $donation_type = $_POST['donation_type']; 
    $amount = $_POST['amount'];               
    $branch_id = $_POST['branch_id'];         
    $payment_method = "TNG eWallet";
    $txn_ref = "TXN-" . date("YmdHis");
    $now = date("Y-m-d H:i:s");
    $status = "Success";
    $bank_name = "TNG eWallet";
    $masked = "QR Payment";

    // 1️⃣ 插入到 payment 表
    $stmt = $conn->prepare("INSERT INTO payment 
        (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("sssissss", $payment_method, $status, $txn_ref, $amount, $now, $bank_name, $masked, $now);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // 2️⃣ 插入到 orders 表 (关键修改：使用真实用户信息)
    $order_type = ($donation_type == "monthly") ? "Recurring" : "One-time";
    $order_status = "Completed";
    
    // Activity_ID 暂时写死为 1
    $activity_id = 1;

    $stmt = $conn->prepare("INSERT INTO `orders` 
        (Order_FName, Order_LName, Order_ContactNumber, Order_ICNumber, Order_Email, 
         Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, Order_TXN_Ref, 
         Order_Type, Order_Status, Order_Created_At, Order_Updated_At, Donor_ID, Payment_ID, Branch_ID, Activity_ID)
        VALUES (?, ?, ?, ?, ?, ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // 参数类型字符串: "sssssdsssssssiiii" (5个字符串, 1个小数, 7个字符串, 4个整数)
    // 简化起见，除了ID用i，金额用d，其他都用s
    $stmt->bind_param("sssssdsssssssiiii", 
        $user_data['Donor_FName'], 
        $user_data['Donor_LName'], 
        $user_data['Donor_ContactNumber'], 
        $user_data['Donor_ICNumber'], 
        $user_data['Donor_Email'], 
        $amount, 
        $payment_method, 
        $status, 
        $txn_ref, 
        $order_type, 
        $order_status, 
        $now, 
        $now, 
        $current_donor_id, // ✅ 使用当前登录用户的ID
        $payment_id, 
        $branch_id, 
        $activity_id
    );
    $stmt->execute();
    $stmt->close();

    // 3️⃣ 如果是每月捐款，插入 recurring_donation 表
    if ($donation_type == "monthly") {
        $deduction_date = date("Y-m-d", strtotime("+1 month"));
        
        // ⚠️ 修改：使用 $current_donor_id
        $stmt = $conn->prepare("INSERT INTO recurring_donation 
            (Recurring_Amount, Recurring_Payment_Method, Recurring_Deduction_Date, Recurring_Status, Recurring_Created_At, Recurring_Updated_At, Donor_ID)
            VALUES (?, ?, ?, 'Active', ?, ?, ?)");
            
        $stmt->bind_param("dsssi", $amount, $payment_method, $deduction_date, $now, $now, $current_donor_id);
        $stmt->execute();
        $stmt->close();
    }

    // 4️⃣ 跳转到成功页面
    header("Location: Payment_Settlement_Page.php?txn_ref=$txn_ref");
    exit();
}
?>

<!DOCTYPE html>
<html>
<form method="POST" action="">
    <input type="hidden" name="donation_type" value="<?php echo $_POST['donation_type'] ?? 'one-time'; ?>">
    <input type="hidden" name="amount" value="<?php echo $_POST['amount'] ?? 5; ?>">
    <input type="hidden" name="branch_id" value="<?php echo $_POST['branch_id'] ?? 1; ?>">
    </form>
</html>