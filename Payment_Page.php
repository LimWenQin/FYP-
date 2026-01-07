<?php
// 1. PHP 后端逻辑
include 'dataconnection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 登录检查
if (!isset($_SESSION['donor_id'])) {
   echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
   exit();
}

$current_donor_id = $_SESSION['donor_id'];

// ==========================================
// NEW: 检查用户资料是否完整 (用于 JS 验证)
// ==========================================
$check_sql = "SELECT Donor_ICNumber, Donor_Address1, Donor_City, Donor_State, Donor_PostalCode FROM donor WHERE Donor_ID = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$res = $stmt->get_result();
$user_data = $res->fetch_assoc();

// 定义完整性规则 (IC 和 地址必填)
$is_profile_complete = (
    !empty($user_data['Donor_ICNumber']) && 
    !empty($user_data['Donor_Address1']) &&
    !empty($user_data['Donor_City']) &&
    !empty($user_data['Donor_PostalCode'])
) ? 'true' : 'false';

// 2. 引入头部模板
include 'header_UI.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* ... 原有 CSS 保持不变 ... */
    .hero {
        height: 400px;
        background-color: #333;
        background-image: url('images/hero_1.jpg');
        background-size: cover;
        background-position: center;
        position: relative;
        display: flex; align-items: center; justify-content: center; text-align: center;
    }
    .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
    .hero-text { position: relative; color: #fff; z-index: 1; }
    .hero-text h1 { font-size: 48px; margin-bottom: 10px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    
    .content-section { padding: 50px 0; display: flex; gap: 40px; flex-wrap: wrap; }
    .col-left, .col-right { flex: 1; min-width: 300px; }
    .story-img { width: 100%; border-radius: 8px; margin-bottom: 20px; }
    .text-cursive { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #cd4e4eff; }
    
    .donation-card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border: 1px solid #eee; }
    
    .type-group { display: flex; margin-bottom: 20px; border: 1px solid #cd4e4eff; border-radius: 5px; overflow: hidden; }
    .type-option { flex: 1; text-align: center; padding: 10px; cursor: pointer; background: #fff; color: #cd4e4eff; font-weight: bold; }
    .type-option.active { background: #cd4e4eff; color: #fff; }

    .amount-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
    .btn-amount { padding: 12px; border: 1px solid #ddd; background: #fff; cursor: pointer; border-radius: 4px; font-size: 14px; transition: 0.2s; }
    .btn-amount:hover { background: #f0f0f0; }
    .btn-amount:active, .btn-amount.selected { background: #cd4e4eff; color: #fff; border-color: #cd4e4eff; }

    .input-custom { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 20px; font-size: 16px; }
    .btn-submit { width: 100%; padding: 15px; background: #ffbb6dff; color: #fff; font-size: 18px; font-weight: bold; border: none; border-radius: 5px; cursor: pointer; text-transform: uppercase; }
    .btn-submit:hover { background: #f79c34ff; }

    /* NEW: 免税勾选框样式 */
    .tax-receipt-section {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        border-left: 4px solid #cd4e4eff;
    }
    .checkbox-label {
        display: flex;
        align-items: center;
        cursor: pointer;
        font-weight: 600;
        color: #333;
    }
    .checkbox-label input {
        width: 18px;
        height: 18px;
        margin-right: 10px;
        cursor: pointer;
    }
    .tax-note {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
        margin-left: 28px;
    }
</style>

<div class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-text">
        <h1>Make a Difference</h1>
        <p>Your support helps us build a better future for those in need.</p>
    </div>
</div>

<?php 
    $current_step = 1; 
    $flow_type = 'standard'; 
    include 'stepper.php'; 
?>

<div class="container">
    <div class="content-section">
        
        <div class="col-left">
            <img src="images/hero_3.jpg" alt="Donation Story" class="story-img">
            <h2 class="text-cursive">Why Donate?</h2>
            <p>This is background information on the donation drive, such as helping to improve living conditions in senior citizen communities. Donations will be used to purchase daily necessities and medicines.</p>
            <p>Your contribution makes a direct impact on the lives of the elderly and orphans we support. Every penny counts towards building a bridge of love.</p>
        </div>

        <div class="col-right">
            <div class="donation-card">
                <h2 style="text-align:center;">Choose Amount</h2>

                <form id="donationForm" method="POST" action="Branch_Selection.php">
                    
                    <div class="type-group">
                        <div class="type-option active" id="btn-once" onclick="selectType('one-time')">One-time</div>
                        <div class="type-option" id="btn-monthly" onclick="selectType('monthly')">Monthly</div>
                    </div>

                    <input type="hidden" id="donation_type" name="donation_type" value="one-time">
                    <input type="hidden" id="amount" name="amount">
                    <input type="hidden" id="tax_receipt" name="tax_receipt" value="0">

                    <div class="amount-grid" id="amount-grid">
                        </div>

                    <input type="number" id="custom_amount" name="custom_amount" class="input-custom" placeholder="Enter custom amount (RM)">

                    <div class="tax-receipt-section">
                        <label class="checkbox-label">
                            <input type="checkbox" id="receipt_checkbox" onchange="toggleTaxReceipt()">
                            I need a Tax Exemption Receipt
                        </label>
                        <div class="tax-note">
                            * Minimum donation of <strong>RM 30</strong> is required.<br>
                            * Please ensure your profile (IC & Address) is complete.
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">Donate Now</button>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
    // PHP 传给 JS 的变量：资料是否完整
    const isProfileComplete = <?php echo $is_profile_complete; ?>;

    // 默认金额和免税金额配置
    const defaultAmounts = [20, 50, 100, 200, 300, 500];
    const taxAmounts = [30, 50, 100, 200, 300, 500]; // 20 变成 30

    // 初始化页面
    document.addEventListener('DOMContentLoaded', function() {
        renderButtons(defaultAmounts);
    });

    // 渲染金额按钮
    function renderButtons(amounts) {
        const grid = document.getElementById('amount-grid');
        grid.innerHTML = ''; // 清空现有按钮
        
        amounts.forEach(amt => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-amount';
            btn.innerText = 'RM ' + amt;
            btn.onclick = function() { selectAmount(amt, btn); };
            grid.appendChild(btn);
        });
    }

    // 选择类型 (One-time / Monthly)
    function selectType(type) {
        document.getElementById("donation_type").value = type;
        document.getElementById('btn-once').classList.toggle('active', type === 'one-time');
        document.getElementById('btn-monthly').classList.toggle('active', type === 'monthly');
    }

    // 选择金额
    function selectAmount(amount, btnElement) {
        document.getElementById("amount").value = amount;
        document.getElementById("custom_amount").value = amount;
        
        // 高亮选中状态
        document.querySelectorAll('.btn-amount').forEach(b => b.classList.remove('selected'));
        if(btnElement) btnElement.classList.add('selected');
    }

    // 监听自定义输入框
    document.getElementById('custom_amount').addEventListener('input', function() {
        document.getElementById("amount").value = this.value;
        document.querySelectorAll('.btn-amount').forEach(b => b.classList.remove('selected'));
    });

    // NEW: 切换免税选项
    function toggleTaxReceipt() {
        const checkbox = document.getElementById('receipt_checkbox');
        const hiddenInput = document.getElementById('tax_receipt');
        const customInput = document.getElementById('custom_amount');
        
        if (checkbox.checked) {
            // 1. 切换按钮为 RM30 起步
            renderButtons(taxAmounts);
            hiddenInput.value = "1";
            
            // 2. 如果当前选中的金额小于30，自动调整
            let currentVal = parseFloat(document.getElementById("amount").value) || 0;
            if (currentVal > 0 && currentVal < 30) {
                selectAmount(30, document.querySelector('.btn-amount')); // 默认选中第一个(30)
                const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                Toast.fire({ icon: 'info', title: 'Amount adjusted to RM 30 (Minimum for Receipt)' });
            }
        } else {
            // 恢复默认按钮
            renderButtons(defaultAmounts);
            hiddenInput.value = "0";
        }
    }

    // NEW: 表单提交验证
    document.getElementById('donationForm').addEventListener('submit', function(e) {
        const amount = parseFloat(document.getElementById("amount").value) || 0;
        const needsReceipt = document.getElementById('receipt_checkbox').checked;

        // 1. 验证金额
        if (amount <= 0) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Oops...', text: 'Please select or enter a donation amount.' });
            return;
        }

        // 2. 验证免税条件
        if (needsReceipt) {
            // 金额必须 >= 30
            if (amount < 30) {
                e.preventDefault();
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Minimum Amount Required', 
                    text: 'To receive a Tax Exemption Receipt, the minimum donation is RM 30.',
                    confirmButtonColor: '#cd4e4eff'
                });
                return;
            }

            // 资料必须完整 (IC & Address)
            if (!isProfileComplete) {
                e.preventDefault();
                Swal.fire({
                    title: 'Incomplete Profile',
                    text: "To issue a Tax Receipt, LHDN requires your IC Number and full Address. Please update your profile first.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Update Profile',
                    confirmButtonColor: '#cd4e4eff',
                    cancelButtonText: 'Continue without Receipt'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // 跳转去 Track Records (那里有修改资料的弹窗) 
                        // 或者你可以做一个专门的 profile.php
                        window.location.href = 'Track_Records.php'; 
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        // 如果用户选择 "Continue without Receipt"
                        // 取消勾选并提交
                        document.getElementById('receipt_checkbox').checked = false;
                        document.getElementById('tax_receipt').value = "0";
                        document.getElementById('donationForm').submit();
                    }
                });
                return;
            }
        }
    });
</script>

<?php include 'footer.php'; ?>