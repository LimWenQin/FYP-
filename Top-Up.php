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
$topup_successful = false; // [修改点] 初始化成功标记
$success_amount = 0;

// ==========================================
// 2. 处理充值逻辑
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_topup'])) {
    
    $amount = $_POST['amount'];
    $method = $_POST['payment_method']; 

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
        $bank_name = ($method == 'TNG eWallet') ? 'TNG eWallet' : 'Visa/Master';
        $masked = "Top-up";

        $stmt_pay = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_pay->bind_param("sssissss", $method, $status, $txn_ref, $amount, $now, $bank_name, $masked, $now);
        $stmt_pay->execute();
        $payment_id = $stmt_pay->insert_id;
        $stmt_pay->close();

        // C. 插入 Order
        $dummy_name = "Wallet Top-up"; 
        // C. 插入 Order 记录 (修正：加入了 Order_PaymentMethod)
$dummy_name = "Wallet Top-up"; 
// 在 SQL 中加入了 Order_PaymentMethod
$stmt_ord = $conn->prepare("INSERT INTO orders (Donor_ID, Payment_ID, Order_Amount, Order_Status, Order_Type, Order_TXN_Ref, Order_Created_At, Order_Name, Order_PaymentMethod) VALUES (?, ?, ?, 'Completed', 'One-time', ?, ?, ?, ?)");

// 在 bind_param 中加入了 $method (注意类型字符串变成了 "iidssss")
$stmt_ord->bind_param("iidssss", $current_donor_id, $payment_id, $amount, $txn_ref, $now, $dummy_name, $method);
        $stmt_ord->execute();
        $stmt_ord->close();

        // [修改点] 设置成功标记，而不是直接 alert & exit
        $topup_successful = true;
        $success_amount = $amount;
    }
}

include 'header_UI.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .topup-wrapper { max-width: 700px; margin: 50px auto; padding: 20px; }
    
    .card-box { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 25px rgba(0,0,0,0.08); }
    
    h2 { text-align: center; color: #333; font-weight: 700; margin-bottom: 30px; }
    
    /* 金额选择网格 */
    .section-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; color: #555; border-left: 4px solid #0057B7; padding-left: 10px; }
    
    .amount-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    
    .btn-amt {
        padding: 15px; border: 2px solid #eee; background: #fff; cursor: pointer;
        border-radius: 10px; font-weight: bold; font-size: 1.1rem; color: #333;
        transition: 0.2s; width: 100%;
    }
    .btn-amt:hover { border-color: #0057B7; color: #0057B7; }
    .btn-amt.active { background: #0057B7; color: white; border-color: #0057B7; box-shadow: 0 4px 10px rgba(0, 87, 183, 0.3); }

    .input-custom { 
        width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 10px; 
        font-size: 1rem; margin-bottom: 30px; transition: 0.3s;
    }
    .input-custom:focus { border-color: #0057B7; outline: none; }

    /* 支付方式选择 */
    .payment-methods { display: flex; flex-direction: column; gap: 15px; }
    
    .method-card {
        border: 2px solid #eee; border-radius: 12px; padding: 15px 20px;
        display: flex; align-items: center; justify-content: space-between;
        cursor: pointer; transition: 0.2s;
    }
    .method-card:hover { border-color: #ddd; background: #f9f9f9; }
    .method-card.selected { border-color: #28a745; background: #f0fff4; box-shadow: 0 4px 10px rgba(40, 167, 69, 0.15); }
    
    .m-left { display: flex; align-items: center; gap: 15px; }
    .m-img { width: 50px; height: 50px; object-fit: contain; }
    .m-title { font-weight: 700; color: #333; }
    .m-desc { font-size: 0.85rem; color: #888; }
    
    /* 选中圆圈 */
    .check-circle {
        width: 24px; height: 24px; border: 2px solid #ccc; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
    .check-circle i { display: none; color: white; font-size: 14px; }
    
    .method-card.selected .check-circle { background: #28a745; border-color: #28a745; }
    .method-card.selected .check-circle i { display: block; }

    /* 提交按钮 */
    .btn-submit {
        width: 100%; padding: 18px; background: #28a745; color: white;
        border: none; border-radius: 12px; font-weight: bold; font-size: 1.2rem;
        cursor: pointer; margin-top: 30px; transition: 0.3s;
        opacity: 0.7; pointer-events: none; /* 默认禁用，选好才启用 */
    }
    .btn-submit.ready { opacity: 1; pointer-events: all; }
    .btn-submit:hover { background: #218838; transform: translateY(-2px); }

    @media (max-width: 600px) {
        .amount-grid { grid-template-columns: repeat(2, 1fr); }
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
            <input type="number" id="custom_amount" class="input-custom" placeholder="Or enter custom amount (RM)" oninput="manualAmount(this.value)">

            <div class="section-title">2. Payment Method</div>
            <div class="payment-methods">
                
                <div class="method-card" onclick="selectMethod('Credit/Debit Card', this)">
                    <div class="m-left">
                        <img src="images/BankTransfer.jpg" class="m-img" alt="Card">
                        <div>
                            <div class="m-title">Credit / Debit Card</div>
                            <div class="m-desc">Instant Top-up</div>
                        </div>
                    </div>
                    <div class="check-circle"><i class="fas fa-check"></i></div>
                </div>

                <div class="method-card" onclick="selectMethod('TNG eWallet', this)">
                    <div class="m-left">
                        <img src="images/TNG.png" class="m-img" alt="TNG">
                        <div>
                            <div class="m-title">Touch 'n Go eWallet</div>
                            <div class="m-desc">Scan QR Code</div>
                        </div>
                    </div>
                    <div class="check-circle"><i class="fas fa-check"></i></div>
                </div>

            </div>

            <button type="submit" name="confirm_topup" id="btnPay" class="btn-submit">
                Pay Now
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let selectedAmount = 0;
    let selectedMethod = "";

    function selectAmount(val, btn) {
        selectedAmount = val;
        document.getElementById('amount').value = val;
        document.getElementById('custom_amount').value = ""; // 清空自定义
        
        // 样式
        document.querySelectorAll('.btn-amt').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        checkForm();
    }

    function manualAmount(val) {
        selectedAmount = val;
        document.getElementById('amount').value = val;
        
        // 移除按钮选中样式
        document.querySelectorAll('.btn-amt').forEach(b => b.classList.remove('active'));
        checkForm();
    }

    function selectMethod(method, card) {
        selectedMethod = method;
        document.getElementById('payment_method').value = method;
        
        // 样式
        document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        checkForm();
    }

    function checkForm() {
        const btn = document.getElementById('btnPay');
        if (selectedAmount > 0 && selectedMethod !== "") {
            btn.classList.add('ready');
            btn.innerText = `Pay RM ${parseFloat(selectedAmount).toFixed(2)} with ${selectedMethod === 'TNG eWallet' ? 'TNG' : 'Card'}`;
        } else {
            btn.classList.remove('ready');
            btn.innerText = "Pay Now";
        }
    }
    
    // 表单提交前简单验证
    document.getElementById('topupForm').addEventListener('submit', function(e){
        if(selectedAmount <= 0 || selectedMethod === "") {
            e.preventDefault();
            Swal.fire('Error', 'Please select Amount and Payment Method', 'warning');
        }
    });

    // ==========================================
    // [修改点] 成功弹窗逻辑
    // ==========================================
    <?php if ($topup_successful): ?>
        // 页面加载完毕后执行 SweetAlert
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Top-up Successful!',
                text: 'RM <?php echo number_format($success_amount, 2); ?> has been added to your wallet.',
                icon: 'success',
                confirmButtonColor: '#28a745',
                confirmButtonText: 'View My Wallet'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'My_Wallet.php';
                }
            });
        });
    <?php endif; ?>

</script>

<?php include 'footer.php'; ?>