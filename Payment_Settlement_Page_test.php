<?php
// 1. 引入数据库连接
include 'dataconnection.php';

// 2. 开启 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. 设置时区
date_default_timezone_set("Asia/Kuala_Lumpur");

// 4. 获取交易编号
$txn_ref = $_GET['txn_ref'] ?? '';

if ($txn_ref == '') {
    echo "<script>alert('Invalid transaction reference.'); window.location.href='Homepage.php';</script>";
    exit();
}

// 5. 查询付款详情 (核心逻辑保持不变)
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
    echo "<script>alert('Transaction not found.'); window.location.href='Homepage.php';</script>";
    exit();
}

$row = $result->fetch_assoc();
$stmt->close();

// ---------------------------------------------------------
// ⭐ 积分系统逻辑 (保持原逻辑)
// ---------------------------------------------------------
$calc_amount = $row['Order_Amount'];
$calc_donor_id = $row['Donor_ID'];
$calc_status = $row['Payment_Status']; 

// 每 RM10 = 1 分
$points_to_add = floor($calc_amount / 10);
$is_success = (stripos($calc_status, 'Success') !== false); 

if ($points_to_add > 0 && $is_success && !isset($_SESSION['points_awarded_' . $txn_ref])) {

    // 检查是否已有记录
    $chk_sql = "SELECT Points_ID FROM point WHERE Donor_ID = ?";
    $stmt_chk = $conn->prepare($chk_sql);
    $stmt_chk->bind_param("i", $calc_donor_id);
    $stmt_chk->execute();
    $res_chk = $stmt_chk->get_result();
    $stmt_chk->close();

    if ($res_chk->num_rows > 0) {
        $upd_sql = "UPDATE point SET Points_Total = Points_Total + ?, Points_Earned = Points_Earned + ?, Points_Updated_At = NOW() WHERE Donor_ID = ?";
        $stmt_upd = $conn->prepare($upd_sql);
        $stmt_upd->bind_param("iii", $points_to_add, $points_to_add, $calc_donor_id);
        $stmt_upd->execute();
        $stmt_upd->close();
    } else {
        $ins_sql = "INSERT INTO point (Points_Earned, Points_Total, Points_Updated_At, Donor_ID) VALUES (?, ?, NOW(), ?)";
        $stmt_ins = $conn->prepare($ins_sql);
        $stmt_ins->bind_param("iii", $points_to_add, $points_to_add, $calc_donor_id);
        $stmt_ins->execute();
        $stmt_ins->close();
    }
    // 标记 Session 防止重复加分
    $_SESSION['points_awarded_' . $txn_ref] = true;
}

// ---------------------------------------------------------
// ⭐ 项目名称逻辑 (Branch vs Special Case)
// ---------------------------------------------------------
$project_label = "General Donation"; 
$project_name = "Love Bridge Fund"; 

if (!empty($row['Case_ID'])) {
    $c_sql = "SELECT Case_Title FROM special_case WHERE Case_ID = ?";
    if ($c_stmt = $conn->prepare($c_sql)) {
        $c_stmt->bind_param("i", $row['Case_ID']);
        $c_stmt->execute();
        $c_res = $c_stmt->get_result();
        if ($c_row = $c_res->fetch_assoc()) {
            $project_label = "Special Case";
            $project_name = $c_row['Case_Title'];
        }
        $c_stmt->close();
    }
} else if (!empty($row['Branch_ID'])) {
    $b_sql = "SELECT Branch_Name FROM branch WHERE Branch_ID = ?";
    if ($b_stmt = $conn->prepare($b_sql)) {
        $b_stmt->bind_param("i", $row['Branch_ID']);
        $b_stmt->execute();
        $b_res = $b_stmt->get_result();
        if ($b_row = $b_res->fetch_assoc()) {
            $project_label = "Branch";
            $project_name = $b_row['Branch_Name'];
        }
        $b_stmt->close();
    }
}

// 格式化数据
$paymentDate = date("d M Y, h:i A", strtotime($row['Payment_Paid_At']));
$amountFormatted = "RM " . number_format($row['Order_Amount'], 2);

// 6. 引入新版头部
include 'header_UI_template.php'; 
?>

<style>
    /* --- Hero Banner --- */
    .hero-wrap {
        height: 350px;
        position: relative;
        background-image: url('images/hero_1.jpg');
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .hero-wrap .overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.5);
    }
    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 800px;
    }
    .hero-content h1 {
        font-family: "Mansalva", cursive;
        color: #fff;
        font-size: 3.5rem;
        margin-bottom: 10px;
    }

    /* --- 成功信息区 (左侧) --- */
    .success-box {
        text-align: center;
        padding: 40px 20px;
    }
    .icon-success {
        font-size: 80px;
        color: #00a651;
        margin-bottom: 20px;
    }
    .thank-you-msg {
        font-size: 2rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 15px;
    }
    .points-badge {
        background-color: #FFF5E4;
        color: #F28585;
        border: 2px solid #F28585;
        padding: 10px 25px;
        border-radius: 50px;
        font-weight: bold;
        font-size: 1.2rem;
        display: inline-block;
        margin-top: 20px;
    }

    /* --- 收据卡片 (右侧) --- */
    .receipt-card {
        background: #fff;
        padding: 40px;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        border-top: 5px solid #00a651;
    }
    .receipt-title {
        text-align: center;
        font-weight: bold;
        color: #333;
        margin-bottom: 30px;
        font-family: "Mansalva", cursive;
        font-size: 1.8rem;
    }
    
    .info-group { margin-bottom: 25px; }
    .group-title {
        font-size: 0.9rem;
        text-transform: uppercase;
        color: #999;
        font-weight: bold;
        margin-bottom: 10px;
        border-bottom: 1px solid #eee;
        padding-bottom: 5px;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }
    .label { color: #555; font-weight: 500; }
    .value { color: #000; font-weight: bold; text-align: right; }
    
    .total-row {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 2px dashed #ddd;
        font-size: 1.2rem;
    }
    .total-label { font-weight: bold; color: #00a651; }
    .total-value { font-weight: 900; color: #00a651; }

    /* 按钮 */
    .btn-home {
        background-color: #333;
        color: #fff;
        padding: 12px 30px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: bold;
        transition: 0.3s;
        display: inline-block;
        margin-top: 30px;
    }
    .btn-home:hover { background-color: #00a651; color: #fff; }
    
    .btn-print {
        background-color: #f8f9fa;
        color: #333;
        border: 1px solid #ddd;
        padding: 12px 30px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: bold;
        transition: 0.3s;
        display: inline-block;
        margin-top: 30px;
        margin-right: 10px;
        cursor: pointer;
    }
    .btn-print:hover { background-color: #e2e6ea; }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Donation Successful</h1>
        <p class="text-white">Your generosity helps us build a better world.</p>
    </div>
</div>

<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row align-items-center">
            
            <div class="col-lg-5 mb-5">
                <div class="success-box">
                    <div class="icon-success">
                        <span class="icon-check-circle"></span> ✅
                    </div>
                    <h2 class="thank-you-msg">Thank You!</h2>
                    <p class="text-muted">Your payment has been successfully processed. An official receipt has been sent to your email.</p>

                    <?php if($points_to_add > 0): ?>
                        <div class="points-badge">
                            🎉 +<?php echo $points_to_add; ?> Love Points
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="#" onclick="window.print(); return false;" class="btn-print">Print Receipt</a>
                        <a href="Homepage.php" class="btn-home">Back to Home</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="receipt-card">
                    <h3 class="receipt-title">Order Summary</h3>

                    <div class="info-group">
                        <div class="group-title">Donor Details</div>
                        <div class="info-row">
                            <span class="label">Name</span>
                            <span class="value"><?php echo htmlspecialchars($row['Donor_FName'] . ' ' . $row['Donor_LName']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Email</span>
                            <span class="value"><?php echo htmlspecialchars($row['Donor_Email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Contact</span>
                            <span class="value"><?php echo htmlspecialchars($row['Donor_ContactNumber']); ?></span>
                        </div>
                    </div>

                    <div class="info-group">
                        <div class="group-title">Transaction Details</div>
                        <div class="info-row">
                            <span class="label">Transaction Ref</span>
                            <span class="value"><?php echo htmlspecialchars($row['Payment_TXN_Ref']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Date & Time</span>
                            <span class="value"><?php echo $paymentDate; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Payment Method</span>
                            <span class="value"><?php echo htmlspecialchars($row['Payment_Method']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Status</span>
                            <span class="value text-success"><?php echo htmlspecialchars($row['Payment_Status']); ?></span>
                        </div>
                    </div>

                    <div class="info-group">
                        <div class="group-title">Donation Info</div>
                        <div class="info-row">
                            <span class="label">Type</span>
                            <span class="value text-uppercase"><?php echo htmlspecialchars($row['Order_Type']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Project Type</span>
                            <span class="value"><?php echo htmlspecialchars($project_label); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Project Name</span>
                            <span class="value"><?php echo htmlspecialchars($project_name); ?></span>
                        </div>
                    </div>

                    <div class="total-row">
                        <span class="total-label">Total Amount</span>
                        <span class="total-value"><?php echo $amountFormatted; ?></span>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer_UI_template.php'; ?>