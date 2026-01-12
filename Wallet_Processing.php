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
$amount = (float)$sess_data['amount'];
$donation_type = $sess_data['type']; // 'one-time' or 'monthly'

// 确保 ID 为 0 或空时转为 NULL，防止外键错误
$branch_id = (!empty($sess_data['branch_id']) && $sess_data['branch_id'] > 0) ? $sess_data['branch_id'] : null;
$case_id = (!empty($sess_data['case_id']) && $sess_data['case_id'] > 0) ? $sess_data['case_id'] : null;
$activity_id = (!empty($sess_data['activity_id']) && $sess_data['activity_id'] > 0) ? $sess_data['activity_id'] : null;

// ==========================================
// 2. 获取用户资料与余额 (双重验证)
// ==========================================
$stmt = $conn->prepare("SELECT Donor_Wallet, Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?");
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$current_balance = $user_data['Donor_Wallet'] ?? 0.00;

if ($current_balance < $amount) {
    echo "<script>alert('Insufficient wallet balance. Please top up.'); window.location.href='E_Wallet.php';</script>";
    exit();
}

// ==========================================
// 3. 准备所有需要的变量
// ==========================================
$txn_ref = "TXN-EW-" . date("YmdHis") . "-" . rand(100, 999); 
$now = date("Y-m-d H:i:s");
$status = "Success";
$payment_method = "E-Wallet";
$bank_name = "My E-Wallet Balance";
$masked_card = "Wallet-Spending";

$order_type = ($donation_type == "monthly") ? "Recurring" : "One-time";
$order_status = "Completed"; 
$tax_status = (isset($sess_data['tax_receipt']) && $sess_data['tax_receipt'] == '1') ? 'Requested' : 'Not_Requested';

// 准备 Description 文字 (用于流水账单显示)
$description = "Donation by E-Wallet";
if($case_id) {
    $res = $conn->query("SELECT Case_Title FROM special_case WHERE Case_ID = $case_id");
    if($row = $res->fetch_assoc()) $description = "Donate to Case: " . $row['Case_Title'];
} elseif ($activity_id) {
    $res = $conn->query("SELECT Activity_Name FROM activity WHERE Activity_ID = $activity_id");
    if($row = $res->fetch_assoc()) $description = "Donate to Activity: " . $row['Activity_Name'];
}

// ==========================================
// 4. 执行数据库操作 (使用 ACID 事务)
// ==========================================

$conn->begin_transaction(); // 【开启事务】

try {
    // A. 扣除用户余额
    $new_balance = $current_balance - $amount;
    $upd_stmt = $conn->prepare("UPDATE donor SET Donor_Wallet = ? WHERE Donor_ID = ?");
    $upd_stmt->bind_param("di", $new_balance, $current_donor_id); 
    $upd_stmt->execute();

    // B. 插入 Payment 表
    $sql_pay = "INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_pay = $conn->prepare($sql_pay);
    $stmt_pay->bind_param("sssissss", $payment_method, $status, $txn_ref, $amount, $now, $bank_name, $masked_card, $now);
    $stmt_pay->execute();
    $payment_id = $stmt_pay->insert_id;

    // C. 插入 Orders 表
    $sql_ord = "INSERT INTO orders 
        (Order_Name, Order_ContactNumber, Order_ICNumber, Order_Email, 
         Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, 
         Order_TXN_Ref, Order_Type, Order_Status, Tax_Receipt_Status, Order_Created_At, Order_Updated_At, 
         Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID)
        VALUES (?, ?, ?, ?, ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt_ord = $conn->prepare($sql_ord);
    $stmt_ord->bind_param("ssssdssssssssiiiii", 
        $user_data['Donor_Name'], $user_data['Donor_ContactNumber'], $user_data['Donor_ICNumber'], $user_data['Donor_Email'], 
        $amount, $payment_method, $status, $txn_ref, $order_type, $order_status, $tax_status, $now, $now, 
        $current_donor_id, $payment_id, $branch_id, $activity_id, $case_id
    );
    $stmt_ord->execute();
    $new_order_id = $stmt_ord->insert_id;

    // D. 【修正重点】插入 Wallet Transaction 表 (匹配你的数据库字段：Amount, Created_At, Description, Transaction_Type)
    $wt_sql = "INSERT INTO wallet_transaction (Donor_ID, Order_ID, Amount, Transaction_Type, Description, Created_At) 
               VALUES (?, ?, ?, 'Spending', ?, NOW())";
    $stmt_wt = $conn->prepare($wt_sql);
    $stmt_wt->bind_param("iids", $current_donor_id, $new_order_id, $amount, $description);
    $stmt_wt->execute();

    // E. 插入 Recurring Donation (如果是月捐)
    if ($donation_type == "monthly") {
        $deduction_date = date("Y-m-d", strtotime("+1 month"));
        $recurring_status = 'Active';
        $sql_rec = "INSERT INTO recurring_donation 
            (Recurring_Amount, Recurring_Payment_Method, Recurring_Deduction_Date, Recurring_Status, Recurring_Created_At, Recurring_Updated_At, Donor_ID, Branch_ID, Activity_ID, Case_ID) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_rec = $conn->prepare($sql_rec);
        $stmt_rec->bind_param("dsssssiiii", $amount, $payment_method, $deduction_date, $recurring_status, $now, $now, $current_donor_id, $branch_id, $activity_id, $case_id);
        $stmt_rec->execute();
    }

    // F. 更新项目筹款进度
    if ($case_id != null) {
        $stmt_upd = $conn->prepare("UPDATE special_case SET Raised_Amount = Raised_Amount + ? WHERE Case_ID = ?");
        $stmt_upd->bind_param("di", $amount, $case_id);
        $stmt_upd->execute();
    }
    if ($activity_id != null) {
        $stmt_upd = $conn->prepare("UPDATE activity SET Activity_GetAmount = Activity_GetAmount + ? WHERE Activity_ID = ?");
        $stmt_upd->bind_param("di", $amount, $activity_id);
        $stmt_upd->execute();
    }

    // 所有操作成功，提交事务
    $conn->commit();

} catch (Exception $e) {
    // 任何一步出错，回滚所有数据库改动
    $conn->rollback();
    die("Transaction Failed: " . $e->getMessage());
}

// ==========================================
// 5. 完成并跳转
// ==========================================
$_SESSION['last_txn_ref'] = $txn_ref;
header("Location: Payment_Settlement_Page.php?txn_ref=" . $txn_ref);
exit();
?>