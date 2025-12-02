<?php
// 1. 开启 Session (必须放在第一行)
session_start();

// 2. 检查登录
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id'];

// 3. 引入数据库 (这个不输出 HTML，可以放前面)
include 'dataconnection.php';

// 设置时区
date_default_timezone_set("Asia/Kuala_Lumpur");

// 4. 获取用户真实信息 (逻辑处理，不输出 HTML)
$user_sql = "SELECT Donor_FName, Donor_LName, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?";
$u_stmt = $conn->prepare($user_sql);
$u_stmt->bind_param("i", $current_donor_id);
$u_stmt->execute();
$u_result = $u_stmt->get_result();
$user_data = $u_result->fetch_assoc();
$u_stmt->close();

// ---------------------------------------------------------
// 5. ✅ 关键修改：把所有处理逻辑放在 include HTML 之前！
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cvc'])) {

    // 接收数据
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
// 6. ✅ 只有逻辑处理完且没有跳转时，才加载页面 HTML
// ---------------------------------------------------------
include 'header_function.php'; 
include 'header_UI_2.php'; // ⚠️ 这行代码会输出 HTML，必须放在最后！
?>

<!DOCTYPE html>
<html>
<head>
<title>Bank Transfer</title>
<style>

    /* 1. 添加颜色变量定义 */
    :root 
    {
        --gradient-start: #ff6b9d;
        --gradient-middle: #ff8fab;
        --gradient-end: #ffb3c6;
        --white: #ffffff;
    }

    /* ✅ 样式区块（保留你的原始美观风格） */
    body 
    {
        background-color: #FFF5E4;
        font-family: Arial;
        color: #4A4A4A;
        margin: 0;
    }

    .header 
    {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 50px;
        /*应用渐变色背景 --- */
        background: linear-gradient(180deg, var(--gradient-start) 0%, var(--gradient-middle) 50%, var(--gradient-end) 100%);
        box-shadow: 0 4px 15px rgba(255, 107, 157, 0.2); /* 新的阴影 */
        color: #fcfcfcff; /* 为了在渐变色上清晰显示，文字改为白色 */
    }

    .logo 
    {
        display: flex;          /*变成弹性盒子Flexbox，自动变成一行排列*/
        align-items: center;    /* 垂直居中 */
        font-weight: bold;      /*粗体*/
        font-size: 36px;        /*字体大小*/
        gap: 10px;              /* 图片与文字之间的间距 */
    }

    .header .function-links a 
    {
        margin-left: 15px;
        text-decoration: none;
        font-size: 20px;
        color: #fcfcfcff;
        font-weight: bold;
    }

    .search 
    { 
        padding: 6px; font-size: 16px; 
    }

    .container 
    {
        display: flex;
        flex-direction: row;
        padding: 20px;
    }

    .sidebar 
    {
        width: 60px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        margin-right: 20px;
        padding-top: 20px;
    }

    .sidebar button 
    {
        background-color: #A8D5BA;
        border: none;
        border-radius: 10px;
        padding: 8px;
        width: 50px;
        height: 50px;
        cursor: pointer;
        font-weight: bold;
        color: #4A4A4A;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .story-section 
    {
        flex: 2;
        background-color: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    .story-section img 
    {
        width: 100%;
        border-radius: 10px;
        margin-bottom: 15px;
    }

    .story-section h2 
    {
        color: #F28585;
    }

    .donation-box 
    {
        flex: 1;
        background-color: white;
        border-radius: 12px;
        margin-left: 20px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    .donation-box h3 
    {
        text-align: center;
        color: #F28585;
    }

    .bank-form label 
    {
        font-weight: bold;
        margin-top: 10px;
    }

    .bank-form select, .bank-form input 
    {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        margin-top: 5px;
        font-size: 16px;
    }

    .card-info 
    {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-top: 10px;
    }

    .card-info div 
    {
        flex: 1;
    }

    .donate-btn 
    {
        margin-top: 20px;
        width: 100%;
        padding: 12px;
        background-color: #F6B8B8;
        border: none;
        border-radius: 8px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        color: #4A4A4A;
    }

    .donate-btn:hover 
    {
        background-color: #F28585;
    }
</style>
</head>
<body>

<div class="container">
    <!-- 左侧故事区 -->
    <div class="story-section">
        <img src="yourimage.jpg" alt="Donation Story">
        <h2>Donation Story</h2>
        <p>Support our cause with your donation. Your kindness helps improve lives.</p>
    </div>

    <!-- 右侧付款区 -->
    <div class="donation-box">
        <h3>Credit Card / Debit Card</h3>
        <form class="bank-form" method="POST" action="">
            <!-- 从上一页传来的隐藏字段 -->
            <input type="hidden" name="donation_type" value="<?php echo $_POST['donation_type'] ?? 'one-time'; ?>">
            <input type="hidden" name="amount" value="<?php echo $_POST['amount'] ?? 50; ?>">
            <input type="hidden" name="branch_id" value="<?php echo $_POST['branch_id'] ?? 1; ?>">

            <label for="bank">Bank Name</label>
            <select id="bank" name="bank" required>
                <option value="">-- Select Bank --</option>
                <option value="Maybank">Maybank</option>
                <option value="CIMB Bank">CIMB Bank</option>
                <option value="Public Bank">Public Bank</option>
                <option value="Hong Leong Bank">Hong Leong Bank</option>
                <option value="RHB Bank">RHB Bank</option>
            </select>

            <label for="card">Card Number</label>
            <input type="text" id="card" name="card" maxlength="16" placeholder="Enter your card number" pattern="\d{16}" required>

            <div class="card-info">
                <div>
                    <label for="exp">Expiration Date</label>
                    <input type="text" id="exp" name="exp" placeholder="MM/YY" maxlength="5" pattern="(0[1-9]|1[0-2])\/\d{2}" required>
                </div>

                <div>
                    <label for="cvc">CVC</label>
                    <input type="text" id="cvc" name="cvc" maxlength="3" pattern="\d{3}" placeholder="***" required>
                </div>
            </div>

            <button type="submit" class="donate-btn">Confirm Payment</button>
        </form>
    </div>
</div>

</body>
</html>


