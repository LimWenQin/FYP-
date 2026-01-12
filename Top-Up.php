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
$topup_successful = false;
$success_amount = 0;

// ==========================================
// 2. 提前获取并验证用户资料
// ==========================================
$stmt_info = $conn->prepare("SELECT Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email FROM donor WHERE Donor_ID = ?");
$stmt_info->bind_param("i", $current_donor_id);
$stmt_info->execute();
$res_info = $stmt_info->get_result();
$d_row = $res_info->fetch_assoc();
$stmt_info->close();

if (!$d_row) {
    session_destroy();
    echo "<script>alert('User not found. Please login again.'); window.location.href='donor_login.php';</script>";
    exit();
}

$d_name    = $d_row['Donor_Name'];
$d_contact = $d_row['Donor_ContactNumber'];
$d_ic      = $d_row['Donor_ICNumber'];
$d_email   = $d_row['Donor_Email'];

// ==========================================
// 3. 处理充值逻辑
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'])) {
    
    $amount = (float)$_POST['amount'];
    $method = $_POST['payment_method']; 

    if ($amount > 0 && !empty($method)) {
        
        $conn->begin_transaction();

        try {
            // A. 更新 Donor Wallet (加钱)
            $stmt = $conn->prepare("UPDATE donor SET Donor_Wallet = Donor_Wallet + ? WHERE Donor_ID = ?");
            $stmt->bind_param("di", $amount, $current_donor_id);
            $stmt->execute();
            $stmt->close();

            // B. 记录 Payment
            $txn_ref = "TXN-TOPUP-" . date("YmdHis") . "-" . rand(100, 999);
            $now = date("Y-m-d H:i:s");
            
            $bank_name = ($method == 'TNG eWallet') ? 'TNG eWallet' : ($_POST['bank_display'] ?? 'Credit Card');
            $masked = "Top-up Account";
            
            if ($method == 'Credit/Debit Card' && isset($_POST['card'])) {
                $card_num = str_replace(' ', '', $_POST['card']);
                $masked = substr($card_num, 0, 4) . " **** **** " . substr($card_num, -4);
            }

            $stmt_pay = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, 'Success', ?, ?, ?, ?, ?, ?)");
            $stmt_pay->bind_param("sssisss", $method, $txn_ref, $amount, $now, $bank_name, $masked, $now);
            $stmt_pay->execute();
            $payment_id = $stmt_pay->insert_id;
            $stmt_pay->close();

            // C. 插入 Order 记录 (类型记为 Top-up)
            $order_type_val = "Top-up"; 
            $sql_insert = "INSERT INTO orders (Donor_ID, Payment_ID, Order_Amount, Order_Status, Order_Type, Order_TXN_Ref, Order_Created_At, Order_Name, Order_PaymentMethod, Order_ContactNumber, Order_ICNumber, Order_Email, Order_Updated_At) VALUES (?, ?, ?, 'Completed', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_ord = $conn->prepare($sql_insert);
            $stmt_ord->bind_param("iidsssssssss", $current_donor_id, $payment_id, $amount, $order_type_val, $txn_ref, $now, $d_name, $method, $d_contact, $d_ic, $d_email, $now);
            $stmt_ord->execute();
            $new_order_id = $stmt_ord->insert_id;
            $stmt_ord->close();

            // D. [修正逻辑] 计算并更新积分 (每 RM 10 = 1 PT)
            $points_earned = floor($amount / 10);
            
            // 只有当积分 > 0 时才更新 point 表
            if ($points_earned > 0) {
                $pt_sql = "INSERT INTO point (Points_Earned, Points_Total, Points_Updated_At, Donor_ID) 
                           VALUES ($points_earned, $points_earned, NOW(), $current_donor_id) 
                           ON DUPLICATE KEY UPDATE 
                           Points_Earned = $points_earned, 
                           Points_Total = Points_Total + $points_earned, 
                           Points_Updated_At = NOW()";
                $conn->query($pt_sql);
            }

            // E. [核心修改点] 存入 Wallet Transaction 流水表 (Transaction_Type 修改为数据库允许的 'Credit')
            $description = "Top-up via " . $method;
            $wt_sql = "INSERT INTO wallet_transaction (Donor_ID, Order_ID, Amount, Transaction_Type, Description, Created_At) VALUES (?, ?, ?, 'Credit', ?, NOW())";
            $wt_stmt = $conn->prepare($wt_sql);
            $wt_stmt->bind_param("iids", $current_donor_id, $new_order_id, $amount, $description);
            $wt_stmt->execute();
            $wt_stmt->close();

            $conn->commit();
            
            $topup_successful = true;
            $success_amount = $amount;

        } catch (Exception $e) {
            $conn->rollback();
            echo "Transaction Failed: " . $e->getMessage();
            exit();
        }
    }
}

include 'header_UI.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .topup-wrapper { max-width: 700px; margin: 50px auto; padding: 20px; }
    .card-box { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 25px rgba(0,0,0,0.08); }
    h2 { text-align: center; color: #333; font-weight: 700; margin-bottom: 30px; }
    .section-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; color: #555; border-left: 4px solid #0057B7; padding-left: 10px; }
    .amount-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    .btn-amt { padding: 15px; border: 2px solid #eee; background: #fff; cursor: pointer; border-radius: 10px; font-weight: bold; font-size: 1.1rem; color: #333; transition: 0.2s; width: 100%; }
    .btn-amt:hover { border-color: #0057B7; color: #0057B7; }
    .btn-amt.active { background: #0057B7; color: white; border-color: #0057B7; box-shadow: 0 4px 10px rgba(0, 87, 183, 0.3); }
    .input-custom { width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 10px; font-size: 1rem; margin-bottom: 30px; transition: 0.3s; }
    .payment-methods { display: flex; flex-direction: column; gap: 15px; }
    .method-container { border: 2px solid #eee; border-radius: 12px; overflow: hidden; transition: 0.3s; background: white; }
    .method-header { padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; }
    .method-container.selected { border-color: #28a745; }
    .method-container.selected .method-header { background: #f0fff4; }
    .method-body { display: none; padding: 20px; border-top: 1px solid #eee; background: #fff; }
    .method-container.selected .method-body { display: block; }
    .form-group { margin-bottom: 15px; position: relative; } 
    .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box; }
    .card-icon { position: absolute; right: 15px; top: 38px; font-size: 24px; color: #999; }
    .btn-submit { width: 100%; padding: 18px; background: #28a745; color: white; border: none; border-radius: 12px; font-weight: bold; font-size: 1.2rem; cursor: pointer; margin-top: 30px; transition: 0.3s; opacity: 0.6; pointer-events: none; }
    .btn-submit.ready { opacity: 1; pointer-events: all; }
    .qr-box { text-align: center; }
    .qr-box img { width: 180px; border-radius: 10px; }
</style>

<div class="topup-wrapper">
    <div class="card-box">
        <h2><i class="fas fa-wallet" style="color:#0057B7;"></i> Top Up Wallet</h2>
        
        <form id="topupForm" method="POST" action="">
            <input type="hidden" id="amount" name="amount" value="">
            <input type="hidden" id="payment_method" name="payment_method" value="">

            <div class="section-title">1. Select Amount</div>
            <div class="amount-grid">
                <button type="button" class="btn-amt" onclick="selectAmount(10, this)">RM 10</button>
                <button type="button" class="btn-amt" onclick="selectAmount(20, this)">RM 20</button>
                <button type="button" class="btn-amt" onclick="selectAmount(50, this)">RM 50</button>
                <button type="button" class="btn-amt" onclick="selectAmount(100, this)">RM 100</button>
                <button type="button" class="btn-amt" onclick="selectAmount(200, this)">RM 200</button>
                <button type="button" class="btn-amt" onclick="selectAmount(300, this)">RM 300</button>
            </div>
            <input type="number" id="custom_amount" class="input-custom" placeholder="Or enter custom amount (RM)" oninput="manualAmount(this.value)">

            <div class="section-title">2. Payment Method</div>
            <div class="payment-methods">
                
                <div class="method-container" id="card-option" onclick="selectMethod('Credit/Debit Card', this)">
                    <div class="method-header">
                        <div class="m-left" style="display:flex; align-items:center; gap:15px;">
                            <img src="images/BankTransfer.jpg" style="width:50px;" alt="Card">
                            <div><div style="font-weight:700;">Credit / Debit Card</div><div style="font-size:0.85rem; color:#888;">Visa, Mastercard</div></div>
                        </div>
                        <div class="check-circle" style="width:20px; height:20px; border-radius:50%; border:2px solid #ccc;"></div>
                    </div>
                    <div class="method-body" onclick="event.stopPropagation()">
                        <div class="form-group">
                            <label>Card Number</label>
                            <input type="text" id="card" name="card" class="form-control" maxlength="19" placeholder="0000 0000 0000 0000">
                            <i id="card-brand-icon" class="fas fa-credit-card card-icon"></i>
                        </div>
                        <div class="form-group">
                            <label>Card Type / Bank</label>
                            <input type="text" id="bank_display" name="bank_display" class="form-control" value="Unknown Card" readonly>
                        </div>
                        <div style="display:flex; gap:15px;">
                            <div class="form-group" style="flex:1"><label>Expiration (MM/YY)</label><input type="text" id="exp" name="exp" class="form-control" placeholder="MM/YY" maxlength="5"></div>
                            <div class="form-group" style="flex:1"><label>CVC</label><input type="text" id="cvc" name="cvc" class="form-control" maxlength="3" placeholder="123"></div>
                        </div>
                    </div>
                </div>

                <div class="method-container" id="tng-option" onclick="selectMethod('TNG eWallet', this)">
                    <div class="method-header">
                        <div class="m-left" style="display:flex; align-items:center; gap:15px;">
                            <img src="images/TNG.png" style="width:50px;" alt="TNG">
                            <div><div style="font-weight:700;">Touch 'n Go eWallet</div><div style="font-size:0.85rem; color:#888;">Scan QR Code</div></div>
                        </div>
                        <div class="check-circle" style="width:20px; height:20px; border-radius:50%; border:2px solid #ccc;"></div>
                    </div>
                    <div class="method-body" onclick="event.stopPropagation()">
                        <div class="qr-box">
                            <img src="images/tng_qr.png" alt="TNG QR">
                            <div style="margin-top:10px; font-size:0.9rem; color:#666;">Scan with your TNG app. Points are earned instantly upon payment.</div>
                        </div>
                    </div>
                </div>

            </div>

            <button type="submit" name="confirm_topup" id="btnPay" class="btn-submit">Pay Now</button>
        </form>
    </div>
</div>

<script>
    let selectedAmount = 0;
    let selectedMethod = "";

    function selectAmount(val, btn) {
        selectedAmount = val;
        document.getElementById('amount').value = val;
        document.getElementById('custom_amount').value = ""; 
        document.querySelectorAll('.btn-amt').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        checkForm();
    }

    function manualAmount(val) {
        selectedAmount = val;
        document.getElementById('amount').value = val;
        document.querySelectorAll('.btn-amt').forEach(b => b.classList.remove('active'));
        checkForm();
    }

    function selectMethod(method, element) {
        selectedMethod = method;
        document.getElementById('payment_method').value = method;
        document.querySelectorAll('.method-container').forEach(c => c.classList.remove('selected'));
        element.classList.add('selected');
        checkForm();
    }

    function checkForm() {
        const btn = document.getElementById('btnPay');
        if (selectedAmount > 0 && selectedMethod !== "") {
            btn.classList.add('ready');
            btn.innerText = `Pay RM ${parseFloat(selectedAmount).toFixed(2)}`;
        } else {
            btn.classList.remove('ready');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const cardInput = document.getElementById('card');
        const bankDisplay = document.getElementById('bank_display');
        const cardIcon = document.getElementById('card-brand-icon');
        const expInput = document.getElementById('exp');

        if(cardInput) {
            cardInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                let formattedValue = '';
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) formattedValue += ' ';
                    formattedValue += value[i];
                }
                e.target.value = formattedValue;
                identifyCardType(value);
            });
        }

        if(expInput) {
            expInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 3) e.target.value = value.slice(0, 2) + '/' + value.slice(2, 4);
                else e.target.value = value;
            });
        }

        function identifyCardType(number) {
            const patterns = { visa: /^4/, mastercard: /^5[1-5]/, amex: /^3[47]/ };
            const icons = { visa: 'fa-cc-visa', mastercard: 'fa-cc-mastercard', amex: 'fa-cc-amex', unknown: 'fa-credit-card' };
            let type = 'unknown';
            let bankName = 'Unknown Card';

            if (patterns.visa.test(number)) { type = 'visa'; bankName = 'Visa Card'; } 
            else if (patterns.mastercard.test(number)) { type = 'mastercard'; bankName = 'MasterCard'; } 
            else if (patterns.amex.test(number)) { type = 'amex'; bankName = 'American Express'; }

            if(bankDisplay) bankDisplay.value = bankName;
            if(cardIcon) {
                cardIcon.className = `fab ${icons[type]} card-icon`;
                cardIcon.style.color = type === 'unknown' ? '#999' : '#0057B7';
            }
        }
    });

    <?php if ($topup_successful): ?>
    Swal.fire({
        title: 'Success!',
        html: `RM <?php echo number_format($success_amount, 2); ?> added.<br><b>+<?php echo floor($success_amount / 10); ?> Points earned!</b>`,
        icon: 'success',
        confirmButtonText: 'Great!'
    }).then(() => { window.location.href = 'E_Wallet.php'; });
    <?php endif; ?>
</script>

<?php include 'footer.php'; ?>