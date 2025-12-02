<?php
session_start(); // 1. 开启 Session (防止刷新积分重复)

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

// ✅ 查询付款详情 (同时也获取 Donor_ID, Branch_ID, Case_ID 用于判断)
// 注意：SQL 中加入了 o.Case_ID
$sql = "SELECT p.*, o.Order_Amount, o.Order_Type, o.Order_Status, o.Branch_ID, o.Case_ID, o.Order_Created_At, o.Donor_ID,
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
// ⭐ 积分系统逻辑 (POINT SYSTEM)
// ==========================================
$calc_amount = $row['Order_Amount'];
$calc_donor_id = $row['Donor_ID'];
$calc_status = $row['Payment_Status']; 

// 计算积分 (每 RM10 = 1 分)
$points_to_add = floor($calc_amount / 10);

// 执行加分逻辑
$is_success = (stripos($calc_status, 'Success') !== false); 

if ($points_to_add > 0 && $is_success && !isset($_SESSION['points_awarded_' . $txn_ref])) {

    // 1. 检查该 Donor 在 point 表有没有记录
    $chk_sql = "SELECT Points_ID FROM point WHERE Donor_ID = ?";
    $stmt_chk = $conn->prepare($chk_sql);
    $stmt_chk->bind_param("i", $calc_donor_id);
    $stmt_chk->execute();
    $res_chk = $stmt_chk->get_result();
    $stmt_chk->close();

    if ($res_chk->num_rows > 0) {
        // 更新 (UPDATE)
        $upd_sql = "UPDATE point 
                    SET Points_Total = Points_Total + ?, 
                        Points_Earned = Points_Earned + ?, 
                        Points_Updated_At = NOW() 
                    WHERE Donor_ID = ?";
        $stmt_upd = $conn->prepare($upd_sql);
        $stmt_upd->bind_param("iii", $points_to_add, $points_to_add, $calc_donor_id);
        $stmt_upd->execute();
        $stmt_upd->close();
    } else {
        // 插入 (INSERT)
        $ins_sql = "INSERT INTO point (Points_Earned, Points_Total, Points_Updated_At, Donor_ID) 
                    VALUES (?, ?, NOW(), ?)";
        $stmt_ins = $conn->prepare($ins_sql);
        $stmt_ins->bind_param("iii", $points_to_add, $points_to_add, $calc_donor_id);
        $stmt_ins->execute();
        $stmt_ins->close();
    }

    // 2. 标记 Session
    $_SESSION['points_awarded_' . $txn_ref] = true;
}
// ==========================================
// ⭐ 积分逻辑结束
// ==========================================


// ==========================================
// ✅ 智能显示：是分行捐款 还是 特殊个案捐款？
// ==========================================
$project_label = "Branch"; // 默认显示 Branch
$project_name = "General Donation"; // 默认内容

// 1. 优先检查是否为 Special Case
if (!empty($row['Case_ID'])) {
    $case_id = $row['Case_ID'];
    $c_sql = "SELECT Case_Title FROM special_case WHERE Case_ID = ?";
    if ($c_stmt = $conn->prepare($c_sql)) {
        $c_stmt->bind_param("i", $case_id);
        $c_stmt->execute();
        $c_res = $c_stmt->get_result();
        if ($c_row = $c_res->fetch_assoc()) {
            $project_label = "Special Case";
            $project_name = $c_row['Case_Title'];
        }
        $c_stmt->close();
    }
} 
// 2. 如果不是 Case，再检查 Branch
else if (!empty($row['Branch_ID'])) {
    $branch_id = $row['Branch_ID'];
    $b_sql = "SELECT Branch_Name FROM branch WHERE Branch_ID = ?";
    if ($b_stmt = $conn->prepare($b_sql)) {
        $b_stmt->bind_param("i", $branch_id);
        $b_stmt->execute();
        $b_res = $b_stmt->get_result();
        if ($b_row = $b_res->fetch_assoc()) {
            $project_label = "Branch";
            $project_name = $b_row['Branch_Name'];
        }
        $b_stmt->close();
    }
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
        
        <?php if($points_to_add > 0): ?>
            <p style="color: #F28585; font-size: 18px; font-weight: bold; margin-top: 10px;">
                🎉 You have earned <?php echo $points_to_add; ?> Love Points!
            </p>
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
            
            <div class="info-item">
                <span><?php echo htmlspecialchars($project_label); ?>:</span>
                <span><?php echo htmlspecialchars($project_name); ?></span>
            </div>
        </div>

        <a href="HomePage.php" class="back-btn">Back to Home</a>
    </div>

</body>
</html>