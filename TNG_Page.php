<?php
// TNG_Page.php
// 1. 开启 Session
session_start();
include 'dataconnection.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

// 2. 检查登录
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id']; 

// 3. 检查 Session 数据
if (empty($_SESSION['donation_data'])) {
    header("Location: Homepage.php");
    exit();
}

// 从 Session 提取数据
$sess_data = $_SESSION['donation_data'];
$amount = $sess_data['amount'];
$donation_type = $sess_data['type'];

// 处理外键 ID (把 0 或 空值 转为 null)
$branch_id = !empty($sess_data['branch_id']) ? $sess_data['branch_id'] : null;
$case_id = !empty($sess_data['case_id']) ? $sess_data['case_id'] : null;
$activity_id = !empty($sess_data['activity_id']) ? $sess_data['activity_id'] : null;

$source = $sess_data['source'] ?? 'standard'; // 来源标记

// 4. 获取用户资料
$user_sql = "SELECT Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?";
$u_stmt = $conn->prepare($user_sql);
$u_stmt->bind_param("i", $current_donor_id);
$u_stmt->execute();
$user_data = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

if (!$user_data) {
    session_destroy();
    echo "<script>alert('User data not found.'); window.location.href='donor_login.php';</script>";
    exit();
}

// -------------------------
// 5. 显示逻辑：查询项目名字
// -------------------------
$display_project_name = "General Donation"; 
if ($case_id) {
    // 注意：请确保你的数据库字段是 Case_Title 还是 Project_Title，这里沿用你之前的代码
    $c_res = $conn->query("SELECT Case_Title FROM special_case WHERE Case_ID = $case_id");
    if ($row = $c_res->fetch_assoc()) $display_project_name = "Case: " . $row['Case_Title'];
} elseif ($activity_id) {
    $a_res = $conn->query("SELECT Activity_Name FROM activity WHERE Activity_ID = $activity_id");
    if ($row = $a_res->fetch_assoc()) $display_project_name = "Activity: " . $row['Activity_Name'];
} elseif ($branch_id) {
    $b_res = $conn->query("SELECT Branch_Name FROM branch WHERE Branch_ID = $branch_id");
    if ($row = $b_res->fetch_assoc()) $display_project_name = "Branch: " . $row['Branch_Name'];
}

// -------------------------
// 6. 处理付款提交
// -------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_payment'])) {

    // ⭐ 已删除：之前的 "Payment already processed" 检查代码块
    // 现在无论何时点击确认，都会执行下面的数据库插入

    // 生成交易信息
    $payment_method = "TNG eWallet";
    $txn_ref = "TXN-" . date("YmdHis") . "-" . rand(100, 999);
    $now = date("Y-m-d H:i:s");
    $status = "Success"; 
    $bank_name = "TNG eWallet";
    $masked = "QR Payment";

    // 1️⃣ 插入 payment 表
    $stmt = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssissss", $payment_method, $status, $txn_ref, $amount, $now, $bank_name, $masked, $now);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // 2️⃣ 插入 orders 表
    $order_type = ($donation_type == "monthly") ? "Recurring" : "One-time";
    $order_status = "Completed";
    $tax_status = (isset($sess_data['tax_receipt']) && $sess_data['tax_receipt'] == 1) ? 'Requested' : 'Not_Requested';

    $stmt = $conn->prepare("INSERT INTO orders 
        (Order_Name, Order_ContactNumber, Order_ICNumber, Order_Email, 
         Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, Order_TXN_Ref, 
         Order_Type, Order_Status, Tax_Receipt_Status, Order_Created_At, Order_Updated_At, 
         Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID)
        VALUES (?, ?, ?, ?, ?, 'MYR', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssdssssssssiiiii", 
        $user_data['Donor_Name'], 
        $user_data['Donor_ContactNumber'], 
        $user_data['Donor_ICNumber'], 
        $user_data['Donor_Email'], 
        $amount, $payment_method, $status, $txn_ref, $order_type, $order_status, $tax_status, $now, $now, 
        $current_donor_id, $payment_id, $branch_id, $activity_id, $case_id
    );
    
    if (!$stmt->execute()) {
        die("Error inserting order: " . $stmt->error);
    }
    $stmt->close();

    // 3️⃣ 插入 recurring_donation 表
    if ($donation_type == "monthly") {
        $deduction_date = date("Y-m-d", strtotime("+1 month"));
        $rec_status = 'Active';
        
        $stmt = $conn->prepare("INSERT INTO recurring_donation (Recurring_Amount, Recurring_Payment_Method, Recurring_Deduction_Date, Recurring_Status, Recurring_Created_At, Recurring_Updated_At, Donor_ID, Branch_ID, Activity_ID, Case_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("dsssssiiii", $amount, $payment_method, $deduction_date, $rec_status, $now, $now, $current_donor_id, $branch_id, $activity_id, $case_id);
        
        if (!$stmt->execute()) {
             die("Error inserting recurring: " . $stmt->error);
        }
        $stmt->close();
    }

    // 4️⃣ 更新筹款进度 & 捐赠人数 (Special Case)
    if ($case_id != null) {
        // 注意：请确认你的数据库里是 Raised_Amount 还是 Current_Fund，这里沿用你刚才提供的 Raised_Amount
        $conn->query("UPDATE special_case SET Raised_Amount = Raised_Amount + $amount, Donor_Count = Donor_Count + 1 WHERE Case_ID = $case_id");
    }
    
    // 5️⃣ 更新 Activity 筹款进度 (Activity)
    if ($activity_id != null) {
        // 假设 Activity 表有 Activity_GetAmount 字段
        $conn->query("UPDATE activity SET Activity_GetAmount = Activity_GetAmount + $amount WHERE Activity_ID = $activity_id");
    }

    // 跳转到结算页
    // 使用 exit() 确保后续代码不执行
    header("Location: Payment_Settlement_Page.php?txn_ref=$txn_ref");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Touch 'n Go eWallet Payment</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    body { margin: 0; font-family: Arial, sans-serif; background-color: #FFF5E4; color: #4A4A4A; }
    .header { background-color: #0057B7; color: white; display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; }
    .header .title { font-size: 22px; font-weight: bold; }
    .header .call { font-size: 16px; }
    
    .container { display: flex; justify-content: center; align-items: flex-start; padding: 40px; gap: 40px; flex-wrap: wrap; }
    
    .payment-box, .order-summary { background-color: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
    .payment-box { padding: 30px; width: 450px; }
    .payment-box h3 { color: #0057B7; text-align: center; }
    
    .qr-box { text-align: center; margin-top: 20px; }
    .qr-box img { width: 180px; height: 180px; border: 2px solid #ccc; border-radius: 10px; margin-bottom: 10px; }
    .amount { font-size: 20px; font-weight: bold; color: #F28585; text-align: center; margin-top: 15px; }
    
    .payment-steps { font-size: 15px; line-height: 1.6; margin-top: 20px; }
    
    .order-summary { padding: 25px; width: 280px; }
    .order-summary h3 { color: #0057B7; margin-bottom: 15px; border-bottom: 2px solid #A8D5BA; padding-bottom: 5px; }
    .order-summary p { margin: 8px 0; font-size: 15px; }
    .order-summary .total { font-size: 18px; font-weight: bold; text-align: right; color: #F28585; margin-top: 15px; }

    /* Buttons Style */
    .nav-buttons { display: flex; gap: 15px; margin-top: 30px; }
    .btn-nav {
        flex: 1; padding: 12px; border-radius: 8px; font-size: 1rem; font-weight: bold;
        cursor: pointer; border: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s;
        text-decoration: none;
    }
    .btn-prev { background: #e5e7eb; color: #374151; border: 1px solid #d1d5db; }
    .btn-prev:hover { background: #d1d5db; color: #111; text-decoration: none; }
    
    .btn-confirm { background-color: #0057B7; color: white; }
    .btn-confirm:hover { background-color: #004494; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0, 87, 183, 0.3); }

</style>
</head>
<body>

    <div class="header">
        <div class="title">Touch'N Go eWallet</div>
        <div class="call">Call Center: +603-5022 3888</div>
    </div>

    <?php 
        // 动态 Stepper
        if ($source == 'special_case') {
            $flow_type = 'special';
            $current_step = 3; 
        } else {
            $flow_type = 'standard';
            $current_step = 4; 
        }
        
        if (file_exists('stepper.php')) {
            include 'stepper.php';
        }
    ?>

    <div class="container">
        
        <div class="payment-box">
            <h3>Scan to Pay</h3>
            
            <form method="POST" action="">
                
                <div class="qr-box">
                    <img src="images/tng_qr.png" alt="QR Code" onerror="this.src='https://via.placeholder.com/180?text=QR+Code'">
                    <div class="amount">RM <?php echo number_format($amount, 2); ?></div>
                    <p style="color:red; font-size:0.9rem;">QR Code expires in <b>60s</b></p>
                </div>

                <div class="payment-steps">
                    <p><b>How to pay:</b></p>
                    <ol>
                        <li>Open your <strong>TNG eWallet</strong> app.</li>
                        <li>Tap “Scan” and point at the QR code above.</li>
                        <li>After payment is successful on your phone, click <strong>Confirm Payment</strong> below.</li>
                    </ol>
                </div>

                <div class="nav-buttons">
                    <?php
                        // 动态生成返回链接
                        $back_url = "Payment_Ways_Page.php"; // 默认
                        if ($source == 'special_case') {
                            $back_url = "S_C_Payment_Ways_Page.php";
                        }
                    ?>
                    <a href="<?php echo $back_url; ?>" class="btn-nav btn-prev">
                        <i class="fas fa-arrow-left"></i> Previous
                    </a>

                    <button type="submit" name="confirm_payment" class="btn-nav btn-confirm">
                        Confirm Payment
                    </button>
                </div>

            </form>
        </div>

        <div class="order-summary">
            <h3>Order Summary</h3>
            <p><b>Merchant:</b> LOVE BRIDGE</p>
            <p><b>Ref ID:</b> <?php echo "TXN-" . date("YmdHis"); ?></p>
            <p><b>Project:</b><br><?php echo htmlspecialchars($display_project_name); ?></p>
            
            <div style="border-top:1px dashed #ccc; margin:15px 0;"></div>
            
            <p class="total">Total: RM <?php echo number_format($amount, 2); ?></p>
        </div>
    </div>

</body>
</html>