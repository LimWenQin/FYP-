<?php
// Wallet_Processing.php
session_start();
include 'dataconnection.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

// ==========================================
// 1. 安全检查与数据获取
// ==========================================

// 检查登录
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id'];

// 检查 Session 捐款数据
if (empty($_SESSION['donation_data'])) {
    header("Location: Homepage.php"); 
    exit();
}

// 提取数据
$sess_data = $_SESSION['donation_data'];
$amount = $sess_data['amount'];
$donation_type = $sess_data['type']; // 'one-time' or 'monthly'

// ⭐⭐ [关键修复]：确保 ID 为 0 或空时转为 NULL，防止外键错误 ⭐⭐
// 逻辑：如果变量存在且大于0，则使用该值；否则设为 NULL
$branch_id = (!empty($sess_data['branch_id']) && $sess_data['branch_id'] > 0) ? $sess_data['branch_id'] : null;
$case_id = (!empty($sess_data['case_id']) && $sess_data['case_id'] > 0) ? $sess_data['case_id'] : null;
$activity_id = (!empty($sess_data['activity_id']) && $sess_data['activity_id'] > 0) ? $sess_data['activity_id'] : null;

// ==========================================
// 2. 检查余额 (双重验证)
// ==========================================
$stmt = $conn->prepare("SELECT Donor_Wallet FROM donor WHERE Donor_ID = ?");
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$current_balance = $res['Donor_Wallet'] ?? 0.00;
$stmt->close();

if ($current_balance < $amount) {
    // 余额不足，防止非法请求，跳去充值页
    echo "<script>alert('Insufficient wallet balance. Please top up.'); window.location.href='E_Wallet.php';</script>";
    exit();
}

// ==========================================
// 3. 准备所有需要的变量 (关键步骤)
// ==========================================

// 时间与参考号
$txn_ref = "TXN-EW-" . date("YmdHis") . "-" . rand(100, 999); 
$now = date("Y-m-d H:i:s");

// 状态与方式
$status = "Success";
$payment_method = "E-Wallet";
$bank_name = "My E-Wallet";      // 填入数据库，方便查看
$masked_card = "Wallet Balance"; // 填入数据库，方便查看

// 订单详情
$order_type = ($donation_type == "monthly") ? "Recurring" : "One-time";
$order_status = "Completed"; 
$tax_status = (isset($sess_data['tax_receipt']) && $sess_data['tax_receipt'] == '1') ? 'Requested' : 'Not_Requested';

// 获取 Donor 个人资料 (用于填入 Orders 表)
$u_sql = "SELECT Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?";
$u_stmt = $conn->prepare($u_sql);
$u_stmt->bind_param("i", $current_donor_id);
$u_stmt->execute();
$user_data = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

$full_name = $user_data['Donor_Name'];
$contact_num = $user_data['Donor_ContactNumber'];
$ic_num = $user_data['Donor_ICNumber'];
$email = $user_data['Donor_Email'];

// ==========================================
// 4. 执行数据库操作 (事务处理建议开启，这里保持简单逻辑)
// ==========================================

// A. 扣除用户余额
$new_balance = $current_balance - $amount;
$upd_stmt = $conn->prepare("UPDATE donor SET Donor_Wallet = ? WHERE Donor_ID = ?");
$upd_stmt->bind_param("di", $new_balance, $current_donor_id); 
$upd_stmt->execute();
$upd_stmt->close();

// B. 插入 Payment 表
$sql_pay = "INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql_pay);
$stmt->bind_param("sssissss", $payment_method, $status, $txn_ref, $amount, $now, $bank_name, $masked_card, $now);
$stmt->execute();
$payment_id = $stmt->insert_id; // 获取生成的 Payment ID
$stmt->close();

// C. 插入 Orders 表
// 注意：mysqli 会自动处理 null 变量为 SQL NULL
$sql_ord = "INSERT INTO orders 
    (Order_Name, Order_ContactNumber, Order_ICNumber, Order_Email, 
     Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, 
     Order_TXN_Ref, Order_Type, Order_Status, Tax_Receipt_Status, Order_Created_At, Order_Updated_At, 
     Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID)
    VALUES (?, ?, ?, ?, ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql_ord);

$stmt->bind_param("ssssdssssssssiiiii", 
    $full_name, 
    $contact_num, 
    $ic_num, 
    $email, 
    $amount, 
    $payment_method, 
    $status, 
    $txn_ref, 
    $order_type, 
    $order_status, 
    $tax_status, 
    $now, 
    $now, 
    $current_donor_id, 
    $payment_id, 
    $branch_id, 
    $activity_id, 
    $case_id
);

if (!$stmt->execute()) {
    die("Error inserting order: " . $stmt->error);
}
$stmt->close();

// D. 插入 Recurring Donation (如果是月捐)
if ($donation_type == "monthly") {
    $deduction_date = date("Y-m-d", strtotime("+1 month"));
    $recurring_status = 'Active';

    $sql_rec = "INSERT INTO recurring_donation 
        (Recurring_Amount, Recurring_Payment_Method, Recurring_Deduction_Date, Recurring_Status, Recurring_Created_At, Recurring_Updated_At, Donor_ID, Branch_ID, Activity_ID, Case_ID) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
    $stmt = $conn->prepare($sql_rec);
    $stmt->bind_param("dsssssiiii", $amount, $payment_method, $deduction_date, $recurring_status, $now, $now, $current_donor_id, $branch_id, $activity_id, $case_id);
    $stmt->execute();
    $stmt->close();
}

// E. 更新项目筹款进度 (使用处理过的 ID 判断)
if ($case_id != null) {
    $stmt = $conn->prepare("UPDATE special_case SET Raised_Amount = Raised_Amount + ? WHERE Case_ID = ?");
    $stmt->bind_param("di", $amount, $case_id);
    $stmt->execute();
    $stmt->close();
}
if ($activity_id != null) {
    $stmt = $conn->prepare("UPDATE activity SET Activity_GetAmount = Activity_GetAmount + ? WHERE Activity_ID = ?");
    $stmt->bind_param("di", $amount, $activity_id);
    $stmt->execute();
    $stmt->close();
}

// ==========================================
// 5. 完成并跳转
// ==========================================

// 记录 Session 供结算页显示
$_SESSION['last_txn_ref'] = $txn_ref;

// 跳转到结算页
header("Location: Payment_Settlement_Page.php?txn_ref=" . $txn_ref);
exit();
?>