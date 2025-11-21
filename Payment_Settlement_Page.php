<?php
session_start(); // 1. 开启 Session 防止刷新页面重复加分

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

// ✅ 查询付款详情 (同时也获取 Donor_ID, Order_Amount 和 Payment_Status)
// 我们需要确保只有 "Successful" 的付款才加分
$sql = "SELECT p.*, o.Order_Amount, o.Order_Type, o.Order_Status, o.Branch_ID, o.Order_Created_At, o.Donor_ID, 
               d.Donor_FName, d.Donor_LName, d.Donor_Email, d.Donor_ContactNumber, d.Donor_Address
        FROM payment p 
        JOIN orders o ON p.Payment_ID = o.Payment_ID 
        JOIN donor d ON o.Donor_ID = d.Donor_ID
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

// ==========================================
// ⭐ 积分系统整合 (POINT SYSTEM) - 适配 database: point
// ==========================================

// 1. 获取基础数据
$amount = $row['Order_Amount'];
$donor_id = $row['Donor_ID'];
$payment_status = $row['Payment_Status']; // 确保付款成功

// 2. 计算积分 (每RM10 = 1分)
$points = floor($amount / 10); 

// 3. 执行加分逻辑
// 条件：积分大于0 AND 付款成功 AND Session里没有记录过这次交易
if ($points > 0 && $payment_status == 'Successful' && !isset($_SESSION['points_awarded_' . $txn_ref])) {

    // 检查该 Donor 是否已经在 point 表里有记录
    $check_point_sql = "SELECT Points_ID FROM point WHERE Donor_ID = ?";
    $stmt_check = $conn->prepare($check_point_sql);
    $stmt_check->bind_param("i", $donor_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $stmt_check->close();

    if ($result_check->num_rows > 0) {
        // [情况 A] 用户已有积分记录 -> 更新 (UPDATE)
        // 同时更新 Points_Earned (累积) 和 Points_Total (余额)
        $update_sql = "UPDATE point 
                       SET Points_Earned = Points_Earned + ?, 
                           Points_Total = Points_Total + ?, 
                           Points_Updated_At = NOW() 
                       WHERE Donor_ID = ?";
        $stmt_update = $conn->prepare($update_sql);
        $stmt_update->bind_param("iii", $points, $points, $donor_id);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        // [情况 B] 用户没有积分记录 -> 插入新记录 (INSERT)
        $insert_sql = "INSERT INTO point (Points_Earned, Points_Total, Points_Updated_At, Donor_ID) 
                       VALUES (?, ?, NOW(), ?)";
        $stmt_insert = $conn->prepare($insert_sql);
        $stmt_insert->bind_param("iii", $points, $points, $donor_id);
        $stmt_insert->execute();
        $stmt_insert->close();
    }

    // ⚠️ 注意：您的 database 没有 `points_history` 表。
    // 如果您之后建立该表，可以在这里添加 INSERT 语句。
    // 目前省略，以免代码报错。

    // 4. 标记 Session，防止刷新页面重复加分
    $_SESSION['points_awarded_' . $txn_ref] = true;
}

// ==========================================
// ⭐ 积分系统结束
// ==========================================


// ✅ 查询分行名称
$branchName = "Unknown Branch";
$branch_id = $row['Branch_ID'];
// 只有当 branch_id 有值时才查询
if (!empty($branch_id)) {
    $branch_sql = "SELECT Branch_Name FROM branch WHERE Branch_ID = ?";
    $stmt = $conn->prepare($branch_sql);
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $branch_result = $stmt->get_result();
    if ($branch_result->num_rows > 0) {
        $branchName = $branch_result->fetch_assoc()['Branch_Name'];
    }
    $stmt->close();
}

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
/* CSS 保持不变 */
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

        <div class="success-section">
        <div class="success-icon">✅</div>
        <h1>Payment Successful!</h1>
        <p>Thank you for your donation. Your payment has been received successfully.</p>
        <?php if($points > 0): ?>
            <p style="color: #F28585; font-weight: bold;">You have earned <?php echo $points; ?> Love Points!</p>
        <?php endif; ?>
    </div>

        <div class="order-section">
        <h2>Order Summary</h2>

        <div class="info-group">
            <h3>🧍 Donor Information</h3>
            <div class="info-item"><span>Name:</span><span><?php echo htmlspecialchars($row['Donor_FName'] . ' ' . $row['Donor_LName']); ?></span></div>
            <div class="info-item"><span>Email:</span><span><?php echo htmlspecialchars($row['Donor_Email']); ?></span></div>
            <div class="info-item"><span>Contact Number:</span><span><?php echo htmlspecialchars($row['Donor_ContactNumber']); ?></span></div>
            <div class="info-item"><span>Address:</span><span><?php echo htmlspecialchars($row['Donor_Address']); ?></span></div>
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