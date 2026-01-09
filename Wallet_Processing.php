<?php
session_start();
include 'dataconnection.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

// 1. 检查登录
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id'];

// 2. 检查 Session 数据
if (empty($_SESSION['donation_data'])) {
    header("Location: Donate_Page.php");
    exit();
}

// 提取数据
$sess_data = $_SESSION['donation_data'];
$amount = $sess_data['amount'];
$donation_type = $sess_data['type'];
// 确保这些 ID 是 null 而不是空字符串，防止 SQL 报错
$branch_id = !empty($sess_data['branch_id']) ? $sess_data['branch_id'] : null;
$case_id = !empty($sess_data['case_id']) ? $sess_data['case_id'] : null;
$activity_id = !empty($sess_data['activity_id']) ? $sess_data['activity_id'] : null;

// 3. 再次检查余额
$stmt = $conn->prepare("SELECT Donor_Wallet FROM donor WHERE Donor_ID = ?");
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$current_balance = $res['Donor_Wallet'];
$stmt->close();

if ($current_balance < $amount) {
    echo "<script>alert('Insufficient wallet balance. Please top up.'); window.location.href='My_Wallet.php';</script>";
    exit();
}

// ==========================================
// 4. 执行扣款和记录
// ==========================================

// A. 扣除余额
$new_balance = $current_balance - $amount;
$upd_stmt = $conn->prepare("UPDATE donor SET Donor_Wallet = ? WHERE Donor_ID = ?");
$upd_stmt->bind_param("di", $new_balance, $current_donor_id);
$upd_stmt->execute();
$upd_stmt->close();

// 获取用户信息
$u_sql = "SELECT Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?";
$u_stmt = $conn->prepare($u_sql);
$u_stmt->bind_param("i", $current_donor_id);
$u_stmt->execute();
$user_data = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

// === [关键修复] 定义所有变量，避免 bind_param 报错 ===
$txn_ref = "TXN-EW-" . date("YmdHis");
$now = date("Y-m-d H:i:s");
$status = "Success";
$payment_method = "E-Wallet";

// B. 插入 Payment 表
$sql_pay = "INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Created_At) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql_pay);
$stmt->bind_param("sssiss", $payment_method, $status, $txn_ref, $amount, $now, $now);
$stmt->execute();
$payment_id = $stmt->insert_id;
$stmt->close();

// C. 插入 Orders 表
// 准备所有变量 (解决 Reference 错误的关键)
$full_name = $user_data['Donor_Name'];
$contact_num = $user_data['Donor_ContactNumber'];
$ic_num = $user_data['Donor_ICNumber'];
$email = $user_data['Donor_Email'];
$order_type = ($donation_type == "monthly") ? "Recurring" : "One-time";
$order_status = "Completed";
$tax_status = ($sess_data['tax_receipt'] == '1') ? 'Requested' : 'Not_Requested';

$sql_ord = "INSERT INTO orders 
    (Order_Name, Order_ContactNumber, Order_ICNumber, Order_Email, 
     Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, 
     Order_TXN_Ref, Order_Type, Order_Status, Tax_Receipt_Status, Order_Created_At, Order_Updated_At, 
     Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID)
    VALUES (?, ?, ?, ?, ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql_ord);

// 这里所有的参数现在都是变量了，不会再报错
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
$stmt->execute();
$stmt->close();

// D. 插入 Recurring (如果是月捐)
if ($donation_type == "monthly") {
    $deduction_date = date("Y-m-d", strtotime("+1 month"));
    $recurring_status = 'Active'; // 定义为变量

    $stmt = $conn->prepare("INSERT INTO recurring_donation (Recurring_Amount, Recurring_Payment_Method, Recurring_Deduction_Date, Recurring_Status, Recurring_Created_At, Recurring_Updated_At, Donor_ID, Branch_ID, Activity_ID, Case_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("dsssssiiii", $amount, $payment_method, $deduction_date, $recurring_status, $now, $now, $current_donor_id, $branch_id, $activity_id, $case_id);
    $stmt->execute();
    $stmt->close();
}

// E. 更新筹款进度
if ($case_id != null) {
    $conn->query("UPDATE special_case SET Raised_Amount = Raised_Amount + $amount WHERE Case_ID = $case_id");
}
if ($activity_id != null) {
    $conn->query("UPDATE activity SET Activity_GetAmount = Activity_GetAmount + $amount WHERE Activity_ID = $activity_id");
}

// F. 跳转到结算页
header("Location: Payment_Settlement_Page.php?txn_ref=$txn_ref");
exit();
?>