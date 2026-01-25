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
    
    $amount = floor((float)$_POST['amount']); // 强制取整，丢弃所有小数位
    $method = $_POST['payment_method']; 

    if ($amount > 0 && !empty($method)) {
        
        // --- 后端安全校验 ---
if ($method == 'Credit/Debit Card') {
    $exp = $_POST['exp'] ?? '';
    $cvc_check = $_POST['cvc'] ?? '';

    if (strlen($exp) !== 5 || strpos($exp, '/') === false) {
        echo "<script>alert('Error: Invalid expiration date format.'); window.history.back();</script>";
        exit();
    }

    $exp_parts = explode('/', $exp);
    $mm = (int)$exp_parts[0];
    $yy = (int)$exp_parts[1]; 

    // 获取当前年份（2位数字）和月份
    $current_y = (int)date('y'); // 比如 2026年返回 26
    $current_m = (int)date('m');

    // 修复点：确保使用了 $ 符号
    if ($mm < 1 || $mm > 12) {
        echo "<script>alert('Error: Invalid month (01-12).'); window.history.back();</script>";
        exit();
    }
    
    // 检查是否过期
    if ($yy < $current_y || ($yy == $current_y && $mm < $current_m)) {
        echo "<script>alert('Error: Card has expired.'); window.history.back();</script>";
        exit();
    }
    
    if (!preg_match('/^\d{3}$/', $cvc_check)) {
        echo "<script>alert('Error: CVC must be 3 digits.'); window.history.back();</script>";
        exit();
    }
}

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE donor SET Donor_Wallet = Donor_Wallet + ? WHERE Donor_ID = ?");
            $stmt->bind_param("di", $amount, $current_donor_id);
            $stmt->execute();
            $stmt->close();

            $txn_ref = "TXN-TOPUP-" . date("YmdHis") . "-" . rand(100, 999);
            $now = date("Y-m-d H:i:s");
            $bank_name = ($method == 'TNG eWallet') ? 'TNG eWallet' : ($_POST['bank_display'] ?? 'Credit Card');
            $masked = "Top-up Account";
            
            if ($method == 'Credit/Debit Card' && isset($_POST['card'])) {
                $card_num = str_replace(' ', '', $_POST['card']);
                $masked = substr($card_num, 0, 4) . " **** **** " . substr($card_num, -4);
            }

            $stmt_pay = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, 'Success', ?, ?, ?, ?, ?, ?)");
            $stmt_pay->bind_param("sssdsss", $method, $txn_ref, $amount, $now, $bank_name, $masked, $now);
            $stmt_pay->execute();
            $payment_id = $stmt_pay->insert_id;
            $stmt_pay->close();

            $order_type_val = "Top-up"; 
            $sql_insert = "INSERT INTO orders (Donor_ID, Payment_ID, Order_Amount, Order_Status, Order_Type, Order_TXN_Ref, Order_Created_At, Order_Name, Order_PaymentMethod, Order_ContactNumber, Order_ICNumber, Order_Email, Order_Updated_At) VALUES (?, ?, ?, 'Completed', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_ord = $conn->prepare($sql_insert);
            $stmt_ord->bind_param("iidsssssssss", $current_donor_id, $payment_id, $amount, $order_type_val, $txn_ref, $now, $d_name, $method, $d_contact, $d_ic, $d_email, $now);
            $stmt_ord->execute();
            $new_order_id = $stmt_ord->insert_id;
            $stmt_ord->close();

            $points_earned = floor($amount / 10);
            if ($points_earned > 0) {
                $pt_sql = "INSERT INTO point (Points_Earned, Points_Total, Points_Updated_At, Donor_ID) VALUES (?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE Points_Earned = ?, Points_Total = Points_Total + ?, Points_Updated_At = NOW()";
                $stmt_pt = $conn->prepare($pt_sql);
                $stmt_pt->bind_param("iiiii", $points_earned, $points_earned, $current_donor_id, $points_earned, $points_earned);
                $stmt_pt->execute();
                $stmt_pt->close();
            }

            $description = "Top-up via " . $method;
            $trans_type = 'Credit';
            $wt_sql = "INSERT INTO wallet_transaction (Donor_ID, Order_ID, Amount, Transaction_Type, Description, Created_At) VALUES (?, ?, ?, ?, ?, NOW())";
            $wt_stmt = $conn->prepare($wt_sql);
            $wt_stmt->bind_param("iidss", $current_donor_id, $new_order_id, $amount, $trans_type, $description);
            $wt_stmt->execute();
            $wt_stmt->close();

            $conn->commit();
            $topup_successful = true;
            $success_amount = $amount;
        } catch (Exception $e) {
            if ($conn && $conn->ping()) { $conn->rollback(); }
            die("Transaction Error: " . $e->getMessage());
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
    /* 新增错误高亮样式 */
    .is-invalid { border: 2px solid #dc3545 !important; background-color: #fff8f8 !important; }
    /* 当容器被选中时，圆圈边框变绿，背景变绿 */
.method-container.selected .check-circle {
    border-color: #28a745 !important;
    background-color: #28a745;
    position: relative;
}

/* 在选中的圆圈中心添加一个白色小点 */
.method-container.selected .check-circle::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 8px;
    background-color: white;
    border-radius: 50%;
}
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
            <input type="number" id="custom_amount" class="input-custom" placeholder="Or enter custom amount (RM)" min="1" oninput="this.value = this.value.replace(/[^0-9]/g, ''); manualAmount(this.value)">

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
                            <input type="text" id="card" name="card" class="form-control card-input" maxlength="19" placeholder="0000 0000 0000 0000">
                            <i id="card-brand-icon" class="fas fa-credit-card card-icon"></i>
                        </div>
                        <div class="form-group">
                            <label>Card Type / Bank</label>
                            <input type="text" id="bank_display" name="bank_display" class="form-control" value="Unknown Card" readonly>
                        </div>
                        <div style="display:flex; gap:15px;">
                            <div class="form-group" style="flex:1">
                                <label>Expiration (MM/YY)</label>
                                <input type="text" id="exp" name="exp" class="form-control card-input" placeholder="MM/YY" maxlength="5">
                            </div>
                            <div class="form-group" style="flex:1">
                                <label>CVC</label>
                                <input type="text" id="cvc" name="cvc" class="form-control card-input" maxlength="3" placeholder="123">
                            </div>
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
    let currentSelectedAmount = 0;
    let currentSelectedMethod = "";

    function selectAmount(val, btn) {
        currentSelectedAmount = val;
        document.getElementById('amount').value = val;
        document.getElementById('custom_amount').value = ""; 
        document.querySelectorAll('.btn-amt').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        checkForm();
    }

    function manualAmount(val) {
    // 如果输入框有内容且小于 1，则强制设为 1
    if (val !== "" && parseInt(val) < 1) {
        val = 1;
        document.getElementById('custom_amount').value = 1;
    }
    
    currentSelectedAmount = val;
    document.getElementById('amount').value = val;
    document.querySelectorAll('.btn-amt').forEach(b => b.classList.remove('active'));
    checkForm();
}

    function selectMethod(method, element) {
        currentSelectedMethod = method;
        document.getElementById('payment_method').value = method;
        document.querySelectorAll('.method-container').forEach(c => c.classList.remove('selected'));
        element.classList.add('selected');
        checkForm();
    }

    function checkForm() {
        const btn = document.getElementById('btnPay');
        let isValid = false;

        if (currentSelectedAmount > 0 && currentSelectedMethod !== "") {
            if (currentSelectedMethod === 'Credit/Debit Card') {
                const card = document.getElementById('card').value.replace(/\s/g, '');
                const exp = document.getElementById('exp').value;
                const cvc = document.getElementById('cvc').value;
                const cvcPattern = /^\d{3}$/;
                if (card.length === 16 && exp.length === 5 && cvcPattern.test(cvc)) {
                    isValid = true;
                }
            } else {
                isValid = true;
            }
        }

        if (isValid) {
            btn.classList.add('ready');
            btn.innerText = `Pay RM ${parseFloat(currentSelectedAmount).toFixed(2)}`;
            btn.disabled = false;
        } else {
            btn.classList.remove('ready');
            btn.innerText = "Pay Now";
            btn.disabled = true;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const topupForm = document.getElementById('topupForm');
        const cardInput = document.getElementById('card');
        const expInput = document.getElementById('exp');
        const cvcInput = document.getElementById('cvc');
        const bankDisplay = document.getElementById('bank_display');
        const cardIcon = document.getElementById('card-brand-icon');

        cardInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            let formattedValue = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) formattedValue += ' ';
                formattedValue += value[i];
            }
            e.target.value = formattedValue;
            identifyCardType(value);
            checkForm();
        });

        expInput.addEventListener('input', function(e) {
            // 输入时自动清除红色边框
            expInput.classList.remove('is-invalid');
            let value = e.target.value.replace(/\D/g, ''); 
            if (value.length >= 3) {
                e.target.value = value.slice(0, 2) + '/' + value.slice(2, 4);
            } else {
                e.target.value = value;
            }
            checkForm();
        });

        cvcInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
            checkForm();
        });

topupForm.addEventListener('submit', function(e) {
            if (document.getElementById('payment_method').value === 'Credit/Debit Card') {
                const expValue = expInput.value; 
                
                // 1. 基础格式检查
                if (expValue.length !== 5) {
                    e.preventDefault();
                    expInput.classList.add('is-invalid');
                    Swal.fire({ title: 'Error', text: 'Please enter a valid expiration date (MM/YY).', icon: 'warning', confirmButtonColor: '#0057B7' });
                    return;
                }

                const [mmStr, yyStr] = expValue.split('/');
                const mm = parseInt(mmStr, 10);
                const yy = parseInt(yyStr, 10);
                
                const now = new Date();
                const fullCurrentYear = now.getFullYear();
                const currentYearShort = parseInt(fullCurrentYear.toString().substr(-2)); 
                const currentMonth = now.getMonth() + 1; 

                // 2. 验证月份是否合法 (01-12)
                if (mm < 1 || mm > 12) {
                    e.preventDefault();
                    expInput.classList.add('is-invalid');
                    Swal.fire({ 
                        title: 'Invalid Month', 
                        text: 'Please enter a month between 01 and 12.', 
                        icon: 'error', 
                        confirmButtonColor: '#0057B7' 
                    }).then(() => {
                        expInput.value = ''; 
                        expInput.focus();
                        checkForm();
                    });
                    return;
                }

                // 3. 验证是否已过期
                if (yy < currentYearShort || (yy === currentYearShort && mm < currentMonth)) {
                    e.preventDefault(); 
                    expInput.classList.add('is-invalid');
                    Swal.fire({ 
                        title: 'Card Expired', 
                        text: 'The expiration date entered has already passed.', 
                        icon: 'error', 
                        confirmButtonColor: '#0057B7' 
                    }).then(() => {
                        expInput.value = ''; 
                        expInput.focus();
                        checkForm();
                    });
                    return;
                }

                // ⭐ 4. [新增核心逻辑] 验证是否超过 5 年
                const maxYearAllowed = currentYearShort + 5;
                if (yy > maxYearAllowed) {
                    e.preventDefault();
                    expInput.classList.add('is-invalid');
                    Swal.fire({ 
                        title: 'Invalid Year', 
                        text: 'Expiration year cannot be more than 5 years from now (' + (fullCurrentYear + 5) + ').', 
                        icon: 'error', 
                        confirmButtonColor: '#0057B7' 
                    }).then(() => {
                        expInput.value = ''; 
                        expInput.focus();
                        checkForm();
                    });
                    return;
                }
            }
        });

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