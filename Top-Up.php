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
// 2. 处理充值逻辑
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_topup'])) {
    
    $amount = $_POST['amount'];
    $method = $_POST['payment_method']; 

    // 后端简单验证
    if ($amount > 0 && !empty($method)) {
        
        // A. 更新 Donor Wallet (加钱)
        $stmt = $conn->prepare("UPDATE donor SET Donor_Wallet = Donor_Wallet + ? WHERE Donor_ID = ?");
        $stmt->bind_param("di", $amount, $current_donor_id);
        $stmt->execute();
        $stmt->close();

        // B. 记录 Payment
        $txn_ref = "TXN-TOPUP-" . date("YmdHis");
        $now = date("Y-m-d H:i:s");
        $status = "Success";
        
        // 银行名称处理
        $bank_name = "Unknown";
        $masked = "Top-up";
        
        if ($method == 'TNG eWallet') {
            $bank_name = 'TNG eWallet';
        } elseif ($method == 'Credit/Debit Card') {
            $bank_name = $_POST['bank_display'] ?? 'Credit Card';
            $card_num = str_replace(' ', '', $_POST['card']); // 去除空格
            $masked = substr($card_num, 0, 4) . " **** **** " . substr($card_num, -4);
        }

        $stmt_pay = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_pay->bind_param("sssissss", $method, $status, $txn_ref, $amount, $now, $bank_name, $masked, $now);
        $stmt_pay->execute();
        $payment_id = $stmt_pay->insert_id;
        $stmt_pay->close();

        // C. 插入 Order 记录
        $dummy_name = "Wallet Top-up"; 
        $stmt_ord = $conn->prepare("INSERT INTO orders (Donor_ID, Payment_ID, Order_Amount, Order_Status, Order_Type, Order_TXN_Ref, Order_Created_At, Order_Name, Order_PaymentMethod) VALUES (?, ?, ?, 'Completed', 'One-time', ?, ?, ?, ?)");
        $stmt_ord->bind_param("iidssss", $current_donor_id, $payment_id, $amount, $txn_ref, $now, $dummy_name, $method);
        $stmt_ord->execute();
        $stmt_ord->close();

        // 设置成功标记
        $topup_successful = true;
        $success_amount = $amount;
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
    
    /* 金额按钮 */
    .amount-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    .btn-amt { padding: 15px; border: 2px solid #eee; background: #fff; cursor: pointer; border-radius: 10px; font-weight: bold; font-size: 1.1rem; color: #333; transition: 0.2s; width: 100%; }
    .btn-amt:hover { border-color: #0057B7; color: #0057B7; }
    .btn-amt.active { background: #0057B7; color: white; border-color: #0057B7; box-shadow: 0 4px 10px rgba(0, 87, 183, 0.3); }

    .input-custom { width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 10px; font-size: 1rem; margin-bottom: 30px; transition: 0.3s; }
    .input-custom:focus { border-color: #0057B7; outline: none; }

    /* 支付方式容器 */
    .payment-methods { display: flex; flex-direction: column; gap: 15px; }
    .method-container { border: 2px solid #eee; border-radius: 12px; overflow: hidden; transition: 0.3s; background: white; }
    .method-header { padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; background: #fff; }
    .method-header:hover { background: #f9f9f9; }
    .method-container.selected { border-color: #28a745; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.1); }
    .method-container.selected .method-header { background: #f0fff4; }

    .m-left { display: flex; align-items: center; gap: 15px; }
    .m-img { width: 50px; height: 50px; object-fit: contain; }
    .m-title { font-weight: 700; color: #333; }
    .m-desc { font-size: 0.85rem; color: #888; }
    .check-circle { width: 24px; height: 24px; border: 2px solid #ccc; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    .check-circle i { display: none; color: white; font-size: 14px; }
    .method-container.selected .check-circle { background: #28a745; border-color: #28a745; }
    .method-container.selected .check-circle i { display: block; }

    /* 下拉内容区 */
    .method-body { display: none; padding: 20px; border-top: 1px solid #eee; background: #fff; animation: slideDown 0.3s ease-out; }
    .method-container.selected .method-body { display: block; }

    /* 表单控件样式 (与 Credit_Debit_Page 保持一致) */
    .form-group { margin-bottom: 15px; position: relative; } /* Added relative for icon */
    .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: #555; margin-bottom: 5px; }
    .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box; height: 50px; }
    .form-control:focus { border-color: #0057B7; outline: none; } /* Focus color */
    .card-icon { position: absolute; right: 15px; top: 38px; font-size: 24px; color: #999; } /* Adjusted top for label */
    .form-row { display: flex; gap: 15px; }

    /* TNG QR Box */
    .qr-box { text-align: center; padding: 10px; }
    .qr-box img { width: 180px; border: 1px solid #ddd; border-radius: 10px; padding: 5px; }
    .qr-text { margin-top: 10px; font-size: 0.9rem; color: #666; }

    .btn-submit { width: 100%; padding: 18px; background: #28a745; color: white; border: none; border-radius: 12px; font-weight: bold; font-size: 1.2rem; cursor: pointer; margin-top: 30px; transition: 0.3s; opacity: 0.6; pointer-events: none; }
    .btn-submit.ready { opacity: 1; pointer-events: all; }
    .btn-submit:hover { background: #218838; transform: translateY(-2px); }

    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 600px) { .amount-grid { grid-template-columns: repeat(2, 1fr); } }
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
                        <div class="m-left">
                            <img src="images/BankTransfer.jpg" class="m-img" alt="Card">
                            <div><div class="m-title">Credit / Debit Card</div><div class="m-desc">Visa, Mastercard</div></div>
                        </div>
                        <div class="check-circle"><i class="fas fa-check"></i></div>
                    </div>
                    
                    <div class="method-body" onclick="event.stopPropagation()">
                        <div class="form-group">
                            <label for="card">Card Number</label>
                            <input type="text" id="card" name="card" class="form-control" maxlength="19" placeholder="0000 0000 0000 0000">
                            <i id="card-brand-icon" class="fas fa-credit-card card-icon"></i>
                        </div>

                        <div class="form-group">
                            <label for="bank_display">Card Type / Bank</label>
                            <input type="text" id="bank_display" name="bank_display" class="form-control" value="Unknown Card" readonly style="background-color: #f9f9f9;">
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1">
                                <label for="exp">Expiration Date</label>
                                <input type="text" id="exp" name="exp" class="form-control" placeholder="MM/YY" maxlength="5">
                            </div>
                            <div class="form-group" style="flex:1">
                                <label for="cvc">CVC / CVV</label>
                                <input type="text" id="cvc" name="cvc" class="form-control" maxlength="3" placeholder="123">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="method-container" id="tng-option" onclick="selectMethod('TNG eWallet', this)">
                    <div class="method-header">
                        <div class="m-left">
                            <img src="images/TNG.png" class="m-img" alt="TNG">
                            <div><div class="m-title">Touch 'n Go eWallet</div><div class="m-desc">Scan QR Code</div></div>
                        </div>
                        <div class="check-circle"><i class="fas fa-check"></i></div>
                    </div>

                    <div class="method-body" onclick="event.stopPropagation()">
                        <div class="qr-box">
                            <img src="images/tng_qr.png" alt="TNG QR" onerror="this.src='https://via.placeholder.com/180?text=QR+Code'">
                            <div class="qr-text">Scan with your TNG eWallet app to pay.</div>
                        </div>
                    </div>
                </div>

            </div>

            <button type="submit" name="confirm_topup" id="btnPay" class="btn-submit">
                Pay Now
            </button>
        </form>
    </div>
</div>

<script>
    let selectedAmount = 0;
    let selectedMethod = "";

    // 1. 金额选择逻辑
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

    // 2. 支付方式选择逻辑
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
            btn.innerText = "Pay Now";
        }
    }

    // 3. 智能输入逻辑 (移植自 Credit_Debit_Page.php)
    document.addEventListener('DOMContentLoaded', function() {
        const cardInput = document.getElementById('card');
        const bankDisplay = document.getElementById('bank_display');
        const cardIcon = document.getElementById('card-brand-icon');
        const expInput = document.getElementById('exp');
        const cvcInput = document.getElementById('cvc');

        // A. 卡号格式化 & 识别
        if(cardInput) {
            cardInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // 只能输入数字
                let formattedValue = '';
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) formattedValue += ' ';
                    formattedValue += value[i];
                }
                e.target.value = formattedValue;
                identifyCardType(value);
            });
        }

        // B. 日期格式化 (MM/YY)
        if(expInput) {
            expInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // 只能输入数字
                if (value.length >= 3) {
                    e.target.value = value.slice(0, 2) + '/' + value.slice(2, 4);
                } else {
                    e.target.value = value;
                }
            });
        }

        // C. CVC 限制
        if(cvcInput) {
            cvcInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, ''); // 只能输入数字
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

            bankDisplay.value = bankName;
            cardIcon.className = `fab ${icons[type]} card-icon`;
            cardIcon.style.color = type === 'unknown' ? '#999' : '#00a651';
        }
    });
    
    // 4. 表单提交最终验证
    document.getElementById('topupForm').addEventListener('submit', function(e){
        // 基础检查
        if(selectedAmount <= 0 || selectedMethod === "") {
            e.preventDefault();
            Swal.fire('Error', 'Please select Amount and Payment Method', 'warning');
            return;
        }
        
        // 如果是信用卡，进行严格检查
        if(selectedMethod === 'Credit/Debit Card') {
            const expValue = document.getElementById('exp').value;
            const cardValue = document.getElementById('card').value.replace(/\s/g, '');
            const cvcValue = document.getElementById('cvc').value;

            // 卡号长度
            if(cardValue.length < 13) {
                e.preventDefault();
                Swal.fire('Invalid Card', 'Please enter a valid card number.', 'error');
                return;
            }

            // 日期长度
            if (expValue.length !== 5) {
                e.preventDefault();
                Swal.fire('Invalid Date', 'Please enter expiry date in MM/YY format.', 'error');
                return;
            }

            // CVC 长度
            if (cvcValue.length < 3) {
                e.preventDefault();
                Swal.fire('Invalid CVC', 'CVC must be at least 3 digits.', 'error');
                return;
            }

            // 日期逻辑检查
            const [mm, yy] = expValue.split('/').map(num => parseInt(num, 10));
            const now = new Date();
            const currentYear = parseInt(now.getFullYear().toString().substr(-2)); 
            const currentMonth = now.getMonth() + 1; 

            let errorMsg = '';
            if (mm < 1 || mm > 12) errorMsg = "Invalid month (01-12).";
            else if (yy < currentYear) errorMsg = "Card has expired.";
            else if (yy === currentYear && mm < currentMonth) errorMsg = "Card has expired.";

            if (errorMsg !== '') {
                e.preventDefault(); 
                Swal.fire({ title: 'Error', text: errorMsg, icon: 'error', confirmButtonColor: '#00a651' });
            }
        }
    });

    // 5. 成功弹窗
    <?php if ($topup_successful): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Top-up Successful!',
                text: 'RM <?php echo number_format($success_amount, 2); ?> has been added to your wallet.',
                icon: 'success',
                confirmButtonColor: '#28a745',
                confirmButtonText: 'View My Wallet'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'E_Wallet.php';
                }
            });
        });
    <?php endif; ?>
</script>

<?php include 'footer.php'; ?>