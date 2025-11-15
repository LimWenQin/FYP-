<?php
// ✅ 设置时区（马来西亚时间）
date_default_timezone_set("Asia/Kuala_Lumpur");

// ✅ 连接数据库
$conn = new mysqli("localhost", "root", "", "donation_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ 检查表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 接收来自表单与前一页的数据
    $donation_type = $_POST['donation_type']; // one-time 或 monthly
    $amount = $_POST['amount'];               // 金额
    $branch_id = $_POST['branch_id'];         // 分行ID
    $bank_name = $_POST['bank'];              // 银行名称
    $card_number = $_POST['card'];            // 卡号
    $exp = $_POST['exp'];                     // 到期日
    $cvc = $_POST['cvc'];                     // 安全码
    $payment_method = "Bank Transfer";

    // ✅ 系统生成交易参考号与时间
    $txn_ref = "TXN-" . date("YmdHis");
    $now = date("Y-m-d H:i:s");
    $status = "Success";

    // ✅ 屏蔽卡号（如：1234 **** **** 5678）
    $masked_card = substr($card_number, 0, 4) . " **** **** " . substr($card_number, -4);

    // ✅ 1️⃣ 插入到 payment 表
    $stmt = $conn->prepare("INSERT INTO payment 
        (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssissss", $payment_method, $status, $txn_ref, $amount, $now, $bank_name, $masked_card, $now);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // ✅ 2️⃣ 插入到 orders 表
    // 这里先假设捐赠人 (Donor_ID=1) 和活动 (Activity_ID=1)
    $order_type = ($donation_type == "monthly") ? "Recurring" : "One-time";
    $order_status = "Completed";

    $stmt = $conn->prepare("INSERT INTO orders 
        (Order_FName, Order_LName, Order_ContactNumber, Order_ICNumber, Order_Email, 
         Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, 
         Order_TXN_Ref, Order_Type, Order_Status, Order_Created_At, Order_Updated_At, 
         Donor_ID, Payment_ID, Branch_ID, Activity_ID)
        VALUES ('John', 'Tan', '0123456789', '990101-10-1234', 'john.tan@email.com',
         ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 1)");

    $stmt->bind_param("dsssssssiii", 
        $amount, $payment_method, $status, $txn_ref, 
        $order_type, $order_status, $now, $now, 
        $payment_id, $branch_id
    );
    $stmt->execute();
    $stmt->close();

    // ✅ 3️⃣ 如果是每月捐款，插入 recurring_donation 表
    if ($donation_type == "monthly") {
        $deduction_date = date("Y-m-d", strtotime("+1 month"));
        $stmt = $conn->prepare("INSERT INTO recurring_donation 
            (Recurring_Amount, Recurring_Payment_Method, Recurring_Deduction_Date, Recurring_Status, Recurring_Created_At, Recurring_Updated_At, Donor_ID)
            VALUES (?, ?, ?, 'Active', ?, ?, 1)");
        $stmt->bind_param("dssss", $amount, $payment_method, $deduction_date, $now, $now);
        $stmt->execute();
        $stmt->close();
    }

    // ✅ 跳转到付款成功页面
    header("Location: Payment_Settlement_Page.php?txn_ref=$txn_ref");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Bank Transfer</title>
<style>
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
    background-color: #F6B8B8;
    padding: 20px 50px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.logo 
{
    display: flex;
    align-items: center;    /* 垂直居中 */
    font-weight: bold;
    font-size: 36px;
    gap: 10px;              /* 图片与文字之间的间距 */
}

.header .function-links a 
{
    margin-left: 15px;
    text-decoration: none;
    font-size: 20px;
    color: #4A4A4A;
    font-weight: bold;
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

<header class="header">
    <div class="logo">
        LOVE BRIDGE
        <img src="logo" alt="Logo" width="50" height="50">
    </div>
    <div class="function-links">
        <a href="#">Home</a>
        <a href="#">About Us</a>
        <a href="#">Contact Us</a>
    </div>
</header>

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
            <input type="hidden" name="donation_type" value="<?php echo $_GET['donation_type'] ?? 'one-time'; ?>">
            <input type="hidden" name="amount" value="<?php echo $_GET['amount'] ?? 50; ?>">
            <input type="hidden" name="branch_id" value="<?php echo $_GET['branch_id'] ?? 1; ?>">

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
