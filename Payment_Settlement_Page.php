<?php
// ✅ 设置时区
date_default_timezone_set("Asia/Kuala_Lumpur");

// ✅ 数据库连接
$conn = new mysqli("localhost", "root", "", "donation_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ 从 URL 获取交易编号
$txn_ref = $_GET['txn_ref'] ?? '';

if ($txn_ref == '') {
    die("<h2 style='text-align:center;color:red;'>Invalid transaction reference.</h2>");
}

// ✅ 查询付款详情
$sql = "SELECT p.*, o.Order_Amount, o.Order_Type, o.Order_Status, o.Branch_ID, o.Order_Created_At 
        FROM payment p 
        JOIN orders o ON p.Payment_ID = o.Payment_ID 
        WHERE p.Payment_TXN_Ref = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $txn_ref);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("<h2 style='text-align:center;color:red;'>Transaction not found.</h2>");
}

$row = $result->fetch_assoc();
$stmt->close();

// ✅ 查询分行名称
$branchName = "Unknown Branch";
$branch_id = $row['Branch_ID'];
$branch_sql = "SELECT Branch_Name FROM branch WHERE Branch_ID = ?";
$stmt = $conn->prepare($branch_sql);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$branch_result = $stmt->get_result();
if ($branch_result->num_rows > 0) {
    $branchName = $branch_result->fetch_assoc()['Branch_Name'];
}
$stmt->close();

$conn->close();

// ✅ 格式化日期与金额
$paymentDate = date("d M Y, h:i A", strtotime($row['Payment_Paid_At']));
$amountFormatted = "RM " . number_format($row['Order_Amount'], 2);
?>
<!DOCTYPE html>
<html>
<head>
<title>Payment Summary</title>
<style>
    body 
    {
        background-color: #FFF5E4;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        color: #4A4A4A;
    }
    .success-section 
    {
        text-align: center;
        margin-top: 80px;
    }
    .success-icon 
    {
        font-size: 80px;
        color: #91C8A8;
    }
    .success-section h1 
    {
        color: #4A4A4A;
        margin-top: 20px;
    }
    .success-section p 
    {
        font-size: 18px;
        color: #666;
    }
    .order-section 
    {
        max-width: 800px;
        margin: 60px auto;
        background-color: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
    }
    .order-section h2 
    {
        color: #F28585;
        border-bottom: 2px solid #F6B8B8;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .info-group 
    {
        margin-bottom: 25px;
    }
    .info-item 
    {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    .info-item span:first-child 
    {
        font-weight: bold;
        color: #4A4A4A;
    }
    .back-btn 
    {
        display: block;
        margin: 40px auto 0;
        background-color: #F6B8B8;
        color: #4A4A4A;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        width: 180px;
    }
    .back-btn:hover 
    {
        background-color: #F28585;
    }
</style>
</head>
<body>

    <!-- 成功提示 -->
    <div class="success-section">
        <div class="success-icon">✅</div>
        <h1>Payment Successful!</h1>
        <p>Thank you for your donation. Your payment has been received successfully.</p>
    </div>

    <!-- 订单详情 -->
    <div class="order-section">
        <h2>Order Summary</h2>

        <div class="info-group">
            <h3>🧍 Donor Information</h3>
            <div class="info-item"><span>Name:</span><span>John Tan</span></div>
            <div class="info-item"><span>Email:</span><span>john.tan@email.com</span></div>
            <div class="info-item"><span>Contact Number:</span><span>012-3456789</span></div>
            <div class="info-item"><span>Address:</span><span>123 Jalan Example, Kuala Lumpur</span></div>
        </div>

        <div class="info-group">
            <h3>💳 Payment Details</h3>
            <div class="info-item"><span>Payment Method:</span><span><?php echo $row['Payment_Method'] . " (" . $row['Payment_Bank_Name'] . ")"; ?></span></div>
            <div class="info-item"><span>Payment Status:</span><span><?php echo $row['Payment_Status']; ?></span></div>
            <div class="info-item"><span>Transaction Reference:</span><span><?php echo $row['Payment_TXN_Ref']; ?></span></div>
            <div class="info-item"><span>Payment Date:</span><span><?php echo $paymentDate; ?></span></div>
        </div>

        <div class="info-group">
            <h3>📦 Order Information</h3>
            <div class="info-item"><span>Order Type:</span><span><?php echo $row['Order_Type']; ?></span></div>
            <div class="info-item"><span>Status:</span><span><?php echo $row['Order_Status']; ?></span></div>
            <div class="info-item"><span>Amount:</span><span><?php echo $amountFormatted; ?></span></div>
            <div class="info-item"><span>Branch:</span><span><?php echo $branchName; ?></span></div>
        </div>

        <a href="HomePage.html" class="back-btn">Back to Home</a>
    </div>

</body>
</html>
