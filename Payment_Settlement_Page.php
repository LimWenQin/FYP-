<?php
// ==========================================
// 1. 初始化配置与连接
// ==========================================
session_start();
include 'dataconnection.php'; // 确保数据库连接正常
date_default_timezone_set("Asia/Kuala_Lumpur");

// 引入发送邮件的辅助文件
if (file_exists('mail_receipt.php')) {
    require_once 'mail_receipt.php';
}

// ==========================================
// 2. 获取并验证交易编号
// ==========================================
if (!isset($_GET['txn_ref']) || empty($_GET['txn_ref'])) {
    echo "<script>alert('No transaction reference found.'); window.location.href='Homepage.php';</script>";
    exit();
}

$txn_ref = $_GET['txn_ref'];

// ==========================================
// 3. 核心查询 (已修正字段名)
// ==========================================
$sql = "SELECT 
            p.Payment_TXN_Ref, p.Payment_Status, p.Payment_Paid_At, p.Payment_Method,
            o.Order_ID, o.Order_Amount, o.Order_Type, o.Order_Status, 
            o.Branch_ID, o.Case_ID, o.Activity_ID, o.Order_Created_At, o.Order_TXN_Ref,
            o.Order_Name, o.Order_ICNumber, o.Order_Email, o.Order_ContactNumber,
            o.Tax_Receipt_Status,  -- <--- 【修正】这是你数据库里真正的字段名
            d.Donor_ID, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber,
            d.Donor_Address1, d.Donor_Address2, d.Donor_Address3, d.Donor_City, d.Donor_State, d.Donor_PostalCode
        FROM payment p 
        JOIN orders o ON p.Payment_ID = o.Payment_ID 
        JOIN donor d ON o.Donor_ID = d.Donor_ID
        WHERE p.Payment_TXN_Ref = ? OR o.Order_TXN_Ref = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $txn_ref, $txn_ref);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<script>alert('Transaction record not found.'); window.location.href='Homepage.php';</script>";
    exit();
}

$row = $result->fetch_assoc();
$stmt->close();

// ==========================================
// 4. 数据预处理
// ==========================================
$donor_name     = $row['Donor_Name'] ?? "Guest";
$donor_email    = $row['Donor_Email'] ?? "N/A";
$donor_contact  = $row['Donor_ContactNumber'] ?? "N/A";
$order_type     = $row['Order_Type'] ?? 'One-time'; 
$txn_ref_display= $row['Payment_TXN_Ref'] ?? $txn_ref;

$amount_val     = $row['Order_Amount'] ?? 0;
$amount_fmt     = "RM " . number_format($amount_val, 2);

$raw_date       = $row['Payment_Paid_At'] ?? $row['Order_Created_At'];
$payment_date   = date("d M Y, h:i A", strtotime($raw_date));

$payment_method = $row['Payment_Method'] ?? 'Unknown';
$payment_status = $row['Payment_Status'] ?? 'Completed'; 

// 确定项目名称
$project_type = "General Donation";
$project_name = "Love Bridge Fund"; 

if (!empty($row['Case_ID'])) {
    $res = $conn->query("SELECT Case_Title FROM special_case WHERE Case_ID = " . $row['Case_ID']);
    if ($r = $res->fetch_assoc()) {
        $project_type = "Special Case";
        $project_name = $r['Case_Title'];
    }
} elseif (!empty($row['Activity_ID'])) {
    $res = $conn->query("SELECT Activity_Name FROM activity WHERE Activity_ID = " . $row['Activity_ID']);
    if ($r = $res->fetch_assoc()) {
        $project_type = "Activity";
        $project_name = $r['Activity_Name'];
    }
} elseif (!empty($row['Branch_ID'])) {
    $res = $conn->query("SELECT Branch_Name FROM branch WHERE Branch_ID = " . $row['Branch_ID']);
    if ($r = $res->fetch_assoc()) {
        $project_type = "Branch Fund";
        $project_name = $r['Branch_Name'];
    }
}

// 检查支付是否成功
$is_success_status = (stripos($payment_status, 'Success') !== false || stripos($payment_status, 'Completed') !== false);

// ==========================================
// 5. 邮件发送逻辑
// ==========================================
$email_msg = "Processing receipt...";
$sess_key_mail = 'email_sent_' . $txn_ref;

if (!isset($_SESSION[$sess_key_mail]) && $is_success_status) {
    if (function_exists('sendReceiptEmail')) {
        $isSent = sendReceiptEmail($row, $project_name); 
        if ($isSent) {
            $_SESSION[$sess_key_mail] = true;
            $email_msg = "An official receipt (PDF) has been sent to your email.";
        } else {
            $email_msg = "Receipt generated, but email sending failed.";
        }
    } else {
        $email_msg = "Donation processed successfully.";
    }
} else {
    $email_msg = "An official receipt (PDF) has been sent to your email.";
}

// ==========================================
// 6. 积分逻辑 (Love Points)
// ==========================================
// 注意：你的数据库 orders 表有 Trigger 会自动计算积分，
// 但为了确保前台显示的正确性，或者防止 Trigger 没触发，这里保留逻辑作为双重保障是OK的。
// 不过根据你的 SQL Dump，你在 orders 插入或更新时已经有 Trigger 计算积分了。
// 这里我们只做 Session 控制，不重复写入数据库，以免 Trigger 触发两次。

$points_to_add = floor($amount_val / 10); 
// 既然数据库有 Trigger (calculate_points_on_update)，PHP 这里其实不需要再 update point 表了
// 只要 Order 状态是 Success，Trigger 就会加分。
// 为了避免重复加分，这里我们只做显示用途。

// ==========================================
// 7. 【修正】自动处理 Tax Exemption Request
// ==========================================
// 逻辑：如果 Session 里用户要报税，或者数据库里已经是 Requested，就显示成功。
// 如果数据库是 'Not_Requested' 但 Session 说要，我们就 UPDATE 它。

// 1. 检查 Session (用户在填表时是否勾选)
$user_wants_tax = isset($_SESSION['donation_data']['tax_receipt']) && $_SESSION['donation_data']['tax_receipt'] == 1;

// 2. 检查数据库当前状态
$db_tax_status = $row['Tax_Receipt_Status']; // 'Not_Requested', 'Requested', etc.

$sess_key_tax = 'tax_request_processed_' . $txn_ref;

if ($is_success_status && !isset($_SESSION[$sess_key_tax])) {
    
    // 如果用户想要，但数据库里还没记录 (状态是 Not_Requested)
    if ($user_wants_tax && $db_tax_status == 'Not_Requested') {
        
        // 执行 UPDATE。你的数据库 Trigger `after_tax_receipt_request` 会自动检测到这个变更
        // 并自动插入一条 admin_notifications。
        $order_id = $row['Order_ID'];
        $update_tax = $conn->prepare("UPDATE orders SET Tax_Receipt_Status = 'Requested' WHERE Order_ID = ?");
        $update_tax->bind_param("i", $order_id);
        
        if ($update_tax->execute()) {
            $email_msg .= "<br>Tax exemption request submitted to Admin.";
            $db_tax_status = 'Requested'; // 更新当前变量用于显示
        }
        $update_tax->close();
    }
    // 如果数据库里已经是 Requested (可能在创建订单时就写入了)
    elseif ($db_tax_status == 'Requested' || $db_tax_status == 'Generated') {
         // 不做任何事，已经申请了
    }

    $_SESSION[$sess_key_tax] = true;
}

// 如果状态是申请中或已生成，在页面提示用户
if ($db_tax_status == 'Requested') {
    $tax_msg = "Tax Exemption Requested";
} elseif ($db_tax_status == 'Generated') {
    $tax_msg = "Tax Receipt Generated";
} else {
    $tax_msg = "No Tax Receipt Requested";
}

include 'header_UI.php'; 
?>

<style>
    /* 页面专用样式 */
    .hero-wrap { 
        height: 350px; 
        background-image: url('images/hero_1.jpg'); 
        background-size: cover; 
        background-position: center; 
        display: flex; align-items: center; justify-content: center; 
        text-align: center; position: relative; 
    }
    .hero-wrap .overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); }
    .hero-content { position: relative; z-index: 2; max-width: 800px; }
    .hero-content h1 { font-family: 'Segoe UI', sans-serif; color: #fff; font-size: 3.5rem; margin-bottom: 10px; }
    .hero-content p { color: rgba(255,255,255,0.9); font-size: 1.2rem; }

    .success-box { text-align: center; padding: 40px 20px; }
    .icon-success { font-size: 80px; color: #dc2626; margin-bottom: 20px; }
    .thank-you-msg { font-size: 2.2rem; font-weight: 800; color: #333; margin-bottom: 15px; }
    
    .points-badge { 
        background-color: #fff5f5; color: #dc2626; 
        border: 2px solid #dc2626; padding: 10px 25px; 
        border-radius: 50px; font-weight: bold; font-size: 1.1rem; 
        display: inline-block; margin-top: 20px; 
        box-shadow: 0 4px 10px rgba(220, 38, 38, 0.1);
    }

    .receipt-card { 
        background: #fff; padding: 40px; border-radius: 12px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
        border-top: 6px solid #dc2626; 
    }
    .receipt-title { 
        text-align: center; font-weight: 800; color: #333; 
        margin-bottom: 30px; font-size: 1.5rem; letter-spacing: 1px;
    }
    
    .info-group { margin-bottom: 30px; }
    .group-title { 
        font-size: 0.85rem; text-transform: uppercase; color: #999; 
        font-weight: 700; margin-bottom: 15px; 
        border-bottom: 1px solid #f0f0f0; padding-bottom: 8px; 
    }
    .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 1rem; }
    .label { color: #666; font-weight: 500; }
    .value { color: #111; font-weight: 700; text-align: right; max-width: 65%; }
    
    .total-row { 
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 25px; padding-top: 20px; 
        border-top: 2px dashed #e5e5e5; font-size: 1.3rem; 
    }
    .total-label { font-weight: 800; color: #dc2626; }
    .total-value { font-weight: 900; color: #dc2626; font-size: 1.5rem; }

    .btn-home { 
        background-color: #1f2937; color: #fff; padding: 15px 40px; 
        border-radius: 50px; text-decoration: none; font-weight: bold; 
        transition: 0.3s; display: inline-block; margin-top: 30px; 
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    .btn-home:hover { background-color: #dc2626; color: #fff; transform: translateY(-3px); text-decoration: none;}
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Payment Successful</h1>
        <p>Thank you for your generous contribution.</p>
    </div>
</div>

<?php 
    $current_step = 4; 
    $flow_type = ($project_type == 'Special Case') ? 'special' : 'standard';
    if (file_exists('stepper.php')) {
        include 'stepper.php';
    }
?>

<div class="site-section" style="padding: 5em 0; background-color: #f9fafb;">
    <div class="container">
        <div class="row align-items-start">
            
            <div class="col-lg-5 mb-5">
                <div class="success-box">
                    <div class="icon-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 class="thank-you-msg">Thank You!</h2>
                    
                    <p class="text-muted" style="font-size: 1.1rem; line-height: 1.6;">
                        Your donation has been successfully received.<br>
                        <span style="color: #dc2626; font-weight: 600;"><?php echo $email_msg; ?></span>
                    </p>

                    <?php if($points_to_add > 0): ?>
                        <div class="points-badge">
                            <i class="fas fa-gift"></i> &nbsp; You earned +<?php echo $points_to_add; ?> Points!
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-5">
                        <a href="Homepage.php" class="btn-home">Return to Home</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="receipt-card">
                    <h3 class="receipt-title">OFFICIAL RECEIPT</h3>

                    <div class="info-group">
                        <div class="group-title">Donor Information</div>
                        <div class="info-row">
                            <span class="label">Name</span>
                            <span class="value"><?php echo htmlspecialchars($donor_name); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Email</span>
                            <span class="value"><?php echo htmlspecialchars($donor_email); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Contact</span>
                            <span class="value"><?php echo htmlspecialchars($donor_contact); ?></span>
                        </div>
                    </div>

                    <div class="info-group">
                        <div class="group-title">Transaction Details</div>
                        <div class="info-row">
                            <span class="label">Reference No.</span>
                            <span class="value"><?php echo htmlspecialchars($txn_ref_display); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Date & Time</span>
                            <span class="value"><?php echo $payment_date; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Payment Method</span>
                            <span class="value"><?php echo htmlspecialchars($payment_method); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Status</span>
                            <span class="value" style="color:#16a34a;"><?php echo htmlspecialchars($payment_status); ?></span>
                        </div>
                         <div class="info-row">
                            <span class="label">Tax Receipt</span>
                            <span class="value" style="color:#e16161;"><?php echo htmlspecialchars($tax_msg); ?></span>
                        </div>
                    </div>

                    <div class="info-group">
                        <div class="group-title">Donation Destination</div>
                        <div class="info-row">
                            <span class="label">Donation Type</span>
                            <span class="value text-uppercase"><?php echo htmlspecialchars($order_type); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Category</span>
                            <span class="value"><?php echo htmlspecialchars($project_type); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Project Name</span>
                            <span class="value"><?php echo htmlspecialchars($project_name); ?></span>
                        </div>
                    </div>

                    <div class="total-row">
                        <span class="total-label">Total Amount</span>
                        <span class="total-value"><?php echo $amount_fmt; ?></span>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>