<?php
session_start();
include 'dataconnection.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

// 1. 安全检查：确保有登录且有 Session 数据
if (!isset($_SESSION['donor_id']) || empty($_SESSION['donation_data'])) {
    header("Location: Donate_Page.php");
    exit();
}

$current_donor_id = $_SESSION['donor_id'];
$data = $_SESSION['donation_data']; // 获取之前步骤存下的所有数据

$amount = $data['amount'];
$branch_id = $data['branch_id'] ?: null;
$case_id = $data['case_id'] ?: null;
$activity_id = $data['activity_id'] ?: null;
$tax_status = ($data['tax_receipt'] == '1') ? 'Requested' : 'Not_Requested';
$donation_type = $data['type']; // One-time or Monthly

// 2. 获取用户最新余额和个人资料 (用于 Order 表)
$stmt = $conn->prepare("SELECT Donor_Wallet, Donor_Name, Donor_Email, Donor_ContactNumber, Donor_ICNumber FROM donor WHERE Donor_ID = ?");
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. 双重验证余额 (防止黑客绕过前端 JS)
if ($user['Donor_Wallet'] < $amount) {
    echo "<script>alert('Transaction Failed: Insufficient Balance.'); window.location.href='Payment_Ways_Page.php';</script>";
    exit();
}

// ==========================================
// 开始处理交易
// ==========================================

// A. 扣除钱包余额
$new_balance = $user['Donor_Wallet'] - $amount;
$update_wallet = $conn->prepare("UPDATE donor SET Donor_Wallet = ? WHERE Donor_ID = ?");
$update_wallet->bind_param("di", $new_balance, $current_donor_id);
$update_wallet->execute();
$update_wallet->close();

// B. 生成交易详情
$txn_ref = "TXN-EW-" . date("YmdHis"); // EW 代表 E-Wallet 支付
$now = date("Y-m-d H:i:s");
$status = "Success";
$payment_method = "E-Wallet";

// C. 插入 Payment 表
// 这里 Bank Name 写 "LoveBridge Wallet", Masked 写 "WALLET-BAL" 以示区分
$stmt_pay = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, ?, ?, ?, ?, 'LoveBridge Wallet', 'WALLET-BAL', ?)");
$stmt_pay->bind_param("sssisss", $payment_method, $status, $txn_ref, $amount, $now, $now);
$stmt_pay->execute();
$payment_id = $stmt_pay->insert_id;
$stmt_pay->close();

// D. 插入 Orders 表
$order_type = ($donation_type == "monthly") ? "Recurring" : "One-time";

$stmt_ord = $conn->prepare("INSERT INTO orders 
    (Order_Name, Order_ContactNumber, Order_ICNumber, Order_Email, 
     Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, 
     Order_TXN_Ref, Order_Type, Order_Status, Tax_Receipt_Status, 
     Order_Created_At, Order_Updated_At, Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID) 
    VALUES (?, ?, ?, ?, ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt_ord->bind_param("ssssdssssssssiiiii", 
    $user['Donor_Name'], $user['Donor_ContactNumber'], $user['Donor_ICNumber'], $user['Donor_Email'], 
    $amount, $payment_method, $status, $txn_ref, $order_type, "Completed", $tax_status, 
    $now, $now, $current_donor_id, $payment_id, $branch_id, $activity_id, $case_id
);
$stmt_ord->execute();
$stmt_ord->close();

// E. 插入 Recurring 表 (如果是月捐)
if ($donation_type == "monthly") {
    $deduction_date = date("Y-m-d", strtotime("+1 month"));
    $stmt_rec = $conn->prepare("INSERT INTO recurring_donation (Recurring_Amount, Recurring_Payment_Method, Recurring_Deduction_Date, Recurring_Status, Recurring_Created_At, Recurring_Updated_At, Donor_ID, Branch_ID, Activity_ID, Case_ID) VALUES (?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?)");
    $stmt_rec->bind_param("dssssiiii", $amount, $payment_method, $deduction_date, $now, $now, $current_donor_id, $branch_id, $activity_id, $case_id);
    $stmt_rec->execute();
    $stmt_rec->close();
}

// F. 更新项目筹款进度 (Raised Amount)
if ($case_id) {
    $conn->query("UPDATE special_case SET Raised_Amount = Raised_Amount + $amount WHERE Case_ID = $case_id");
}
if ($activity_id) {
    $conn->query("UPDATE activity SET Activity_GetAmount = Activity_GetAmount + $amount WHERE Activity_ID = $activity_id");
}

// 4. 完成后清空 Session 数据 (可选，防止后退重复提交)
// unset($_SESSION['donation_data']);

// 5. 跳转至成功页面
header("Location: Payment_Settlement_Page.php?txn_ref=$txn_ref");
exit();
?>