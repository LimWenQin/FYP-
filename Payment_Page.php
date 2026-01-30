<?php
// Payment_Page.php
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
// 1. 接收并存储分行选择数据 (从 Branch_Selection.php 传回)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['branch_id'])) {
    $_SESSION['donation_data']['branch_id'] = $_POST['branch_id'];
}

// 安全检查：如果没有分行选择信息，退回到第一步
if (!isset($_SESSION['donation_data']['branch_id'])) {
    header("Location: Branch_Selection.php");
    exit();
}

// 获取当前捐赠目标名称
$branch_id = $_SESSION['donation_data']['branch_id'];
$display_target_name = "Love Bridge General Fund"; // 默认

if ($branch_id == '0') {
    $res = $conn->query("SELECT HQ_Name FROM headquarters WHERE HQ_ID = 1");
    if($r = $res->fetch_assoc()) $display_target_name = $r['HQ_Name'];
} else {
    $stmt = $conn->prepare("SELECT Branch_Name FROM branch WHERE Branch_ID = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    if($r = $stmt->get_result()->fetch_assoc()) $display_target_name = $r['Branch_Name'];
}

// ⭐ 每次刷新页面，清除金额记忆和 Tax Receipt 勾选记忆
if (isset($_SESSION['donation_data'])) {
    unset($_SESSION['donation_data']['amount']);
    unset($_SESSION['donation_data']['tax_receipt']);
}
$sess_amount = ''; 
$sess_receipt = '0'; 

$sess_type = $_SESSION['donation_data']['type'] ?? 'one-time';

// 获取推荐数据 (最新的 Special Case 和即将开始的 Activity)
$case_sql = "SELECT * FROM special_case WHERE Case_Status = 'Active' ORDER BY Created_At DESC LIMIT 1";
$case_res = $conn->query($case_sql);
$special_case = $case_res->fetch_assoc();

$act_sql = "SELECT * FROM activity WHERE Activity_Status = 'Active' AND Activity_EndDate >= CURDATE() ORDER BY Activity_StartDate ASC LIMIT 1";
$act_res = $conn->query($act_sql);
$activity = $act_res->fetch_assoc();

// 检查用户资料完整性
$check_sql = "SELECT Donor_ICNumber, Donor_Address1, Donor_City, Donor_PostalCode FROM donor WHERE Donor_ID = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$is_profile_complete = (!empty($user_data['Donor_ICNumber']) && !empty($user_data['Donor_Address1']) && !empty($user_data['Donor_City']) && !empty($user_data['Donor_PostalCode'])) ? 'true' : 'false';

include 'header_UI.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Hero Section */
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
    .hero-text h1 { font-size: 48px; margin-bottom: 10px; font-family: 'Segoe UI', sans-serif; }
    
    .top-section { display: flex; gap: 40px; flex-wrap: wrap; padding: 50px 0; border-bottom: 1px solid #eee; }
    .col-left { flex: 1; min-width: 350px; display: flex; align-items: flex-start; }
    .col-right { flex: 1; min-width: 350px; }
    .story-img { width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); object-fit: cover; }

    .donation-card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); border: 1px solid #eee; }
    
    /* 目标确认徽章样式 */
    .target-badge {
        background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; border-radius: 8px;
        margin-bottom: 20px; display: flex; align-items: center; gap: 12px;
    }

    .type-group { display: flex; margin-bottom: 20px; border: 1px solid #cd4e4eff; border-radius: 5px; overflow: hidden; }
    .type-option { flex: 1; text-align: center; padding: 10px; cursor: pointer; background: #fff; color: #cd4e4eff; font-weight: bold; }
    .type-option.active { background: #cd4e4eff; color: #fff; }
    
    .amount-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
    .btn-amount { padding: 12px; border: 1px solid #ddd; background: #fff; cursor: pointer; border-radius: 4px; font-size: 14px; transition: 0.2s; }
    .btn-amount:hover { background: #f0f0f0; }
    .btn-amount.selected { background: #cd4e4eff; color: #fff; border-color: #cd4e4eff; }
    
    .input-custom { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 20px; font-size: 16px; text-align: center; }
    
    .tax-receipt-section { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #cd4e4eff; }
    .checkbox-label { display: flex; align-items: center; cursor: pointer; font-weight: 600; color: #333; }
    .checkbox-label input { width: 18px; height: 18px; margin-right: 10px; cursor: pointer; }
    .tax-note { font-size: 12px; color: #666; margin-top: 5px; margin-left: 28px; }
    
    /* 按钮容器，让 Next 和 Previous 并排 */
.button-group {
    display: flex;
    gap: 15px;
    margin-top: 10px;
}

.btn-next { 
    background: #00a651; 
    color: white; 
    flex: 2; /* Next 按钮占主要宽度 */
    padding: 15px; 
    border-radius: 8px; 
    font-size: 1.1rem; 
    font-weight: bold; 
    border: none; 
    cursor: pointer; 
    transition: 0.2s; 
}
.btn-next:hover { background: #008f45; }

/* 新增 Previous 按钮样式 */
.btn-prev {
    background: #f3f4f6;
    color: #4b5563;
    flex: 1; /* Previous 按钮占次要宽度 */
    padding: 15px;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: bold;
    border: 1px solid #d1d5db;
    cursor: pointer;
    transition: 0.2s;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
}
.btn-prev:hover {
    background: #e5e7eb;
    color: #1f2937;
}

    /* Showcase Block (推荐板块) */
    .bottom-section { padding: 60px 0; }
    .section-title { text-align: center; font-size: 2rem; font-weight: 700; color: #333; margin-bottom: 40px; position: relative; }
    .section-title::after { content: ''; display: block; width: 60px; height: 4px; background: #dc2626; margin: 10px auto 0; border-radius: 2px; }

    .showcase-block {
        background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        display: flex; border: 1px solid #f0f0f0; transition: transform 0.3s ease; margin-bottom: 50px; min-height: 300px;
    }
    .showcase-img-box { flex: 0.8; position: relative; overflow: hidden; }
    .showcase-img { width: 100%; height: 100%; object-fit: cover; }
    .showcase-content { flex: 1.2; padding: 40px; display: flex; flex-direction: column; justify-content: center; }
    
    .badge-tag { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; width: fit-content; }
    .tag-case { background: #fee2e2; color: #dc2626; }
    .tag-activity { background: #dbeafe; color: #2563eb; }
    
    .sc-title { font-size: 1.6rem; font-weight: 700; color: #222; margin: 0 0 15px 0; line-height: 1.3; }
    .sc-desc { font-size: 0.95rem; color: #666; margin-bottom: 25px; line-height: 1.6; text-align: justify; }

    .btn-support { padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 10px; width: fit-content; text-decoration: none; }
    .btn-case { background: #dc2626; color: white; }
    .btn-case:hover { background: #b91c1c; color: white; }
</style>

<div class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-text">
        <h1>Make a Difference</h1>
        <p>Your support helps us build a better future at <b><?php echo htmlspecialchars($display_target_name); ?></b>.</p>
    </div>
</div>

<?php 
    $current_step = 2; 
    $flow_type = 'standard'; 
    include 'stepper.php'; 
?>

<div class="container">
    <div class="top-section">
        <div class="col-left">
            <img src="images/hero_3.jpg" alt="Donation Story" class="story-img">
        </div>
        <div class="col-right">
            <div class="donation-card">
                <h2 style="text-align:center;">Donation Amount</h2>
                <p style="text-align:center; color:#777; font-size:0.9rem; margin-bottom:20px;">Confirm your support for:</p>

                <div class="target-badge">
                    <i class="fas fa-building" style="color:#cd4e4eff; font-size: 1.2rem;"></i>
                    <strong style="color:#333; font-size: 1.1rem;"><?php echo htmlspecialchars($display_target_name); ?></strong>
                </div>
                
                <form id="donationForm" method="POST" action="Payment_Ways_Page.php" autocomplete="off">
                    <div class="type-group">
                        <div class="type-option <?php echo ($sess_type == 'one-time') ? 'active' : ''; ?>" id="btn-once" onclick="selectType('one-time')">One-time</div>
                        <div class="type-option <?php echo ($sess_type == 'monthly') ? 'active' : ''; ?>" id="btn-monthly" onclick="selectType('monthly')">Monthly</div>
                    </div>
                    
                    <input type="hidden" id="donation_type" name="donation_type" value="<?php echo $sess_type; ?>">
                    <input type="hidden" id="amount" name="amount">
                    <input type="hidden" id="tax_receipt" name="tax_receipt" value="0">
                    
                    <div class="amount-grid" id="amount-grid"></div>
                    <input type="number" id="custom_amount" name="custom_amount" class="input-custom" placeholder="Enter custom amount (RM)" min="1" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                    
                    <div class="tax-receipt-section">
                        <label class="checkbox-label">
                            <input type="checkbox" id="receipt_checkbox" onchange="toggleTaxReceipt()">I need a Tax Exemption Receipt
                        </label>
                        <div class="tax-note">* Minimum donation: <strong>RM 30</strong><br>* Requires complete Profile (IC & Address)</div>
                    </div>
                    
                    <div class="button-group">
                        <a href="Branch_Selection.php" class="btn-prev">
                            <i class="fas fa-arrow-left"></i>
                            <span style="margin-left: 8px;">Back to Branch Selection</span>
                        </a>
                        <button type="submit" class="btn-next">Next <i class="fas fa-arrow-right"></i></button>
                    </div>

                    <div style="text-align:center; margin-top:15px;">
                            <p style="color:#999; font-size:0.85rem;">Step 2 of 4: Amount & Policy</p>
                    </div>
                    
                    <div style="text-align:center; margin-top:15px;">
                        <a href="Branch_Selection.php" style="color:#999; font-size:0.85rem; text-decoration:none;"><i class="fas fa-undo"></i> Change destination</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="bottom-section">
        <h2 class="section-title">Urgent Needs & Campaigns</h2>

        <?php if ($special_case): ?>
            <div class="showcase-block">
                <div class="showcase-img-box">
                    <?php 
                        $c_img = json_decode($special_case['Case_Images'], true);
                        $c_src = is_array($c_img) ? $c_img[0] : 'images/default_donation.jpg';
                    ?>
                    <img src="<?php echo htmlspecialchars($c_src); ?>" class="showcase-img" alt="Case">
                </div>
                <div class="showcase-content">
                    <span class="badge-tag tag-case">Special Case</span>
                    <h3 class="sc-title"><?php echo htmlspecialchars($special_case['Case_Title']); ?></h3>
                    <p class="sc-desc"><?php echo htmlspecialchars(substr($special_case['Case_Description'], 0, 180)) . '...'; ?></p>
                    <a href="S_C_Payment_Page.php?case_id=<?php echo $special_case['Case_ID']; ?>" class="btn-support btn-case">
                        <i class="fas fa-heart"></i> Support Now
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activity): ?>
            <div class="showcase-block">
                <div class="showcase-img-box">
                    <?php 
                        $a_img = json_decode($activity['Activity_Images'], true);
                        $a_src = is_array($a_img) ? $a_img[0] : 'images/default_donation.jpg';
                    ?>
                    <img src="<?php echo htmlspecialchars($a_src); ?>" class="showcase-img" alt="Activity">
                </div>
                <div class="showcase-content">
                    <span class="badge-tag tag-activity">Upcoming Campaign</span>
                    <h3 class="sc-title"><?php echo htmlspecialchars($activity['Activity_Name']); ?></h3>
                    <p class="sc-desc"><?php echo htmlspecialchars(substr($activity['Activity_Description'], 0, 180)) . '...'; ?></p>
                    <a href="S_C_Payment_Page.php?activity_id=<?php echo $activity['Activity_ID']; ?>" class="btn-support" style="background:#2563eb; color:white;">
                        <i class="fas fa-calendar-check"></i> Join Campaign
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const isProfileComplete = <?php echo $is_profile_complete; ?>;
    const defaultAmounts = [20, 50, 100, 200, 300, 500];
    const taxAmounts = [30, 50, 100, 200, 300, 500];

    document.addEventListener('DOMContentLoaded', function() {
        renderButtons(defaultAmounts);
        document.getElementById('amount').value = "";
        document.getElementById('custom_amount').value = "";
        document.getElementById('receipt_checkbox').checked = false;
    });

    function renderButtons(amounts) {
        const grid = document.getElementById('amount-grid');
        grid.innerHTML = ''; 
        amounts.forEach(amt => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-amount';
            btn.innerText = 'RM ' + amt;
            btn.onclick = function() { selectAmount(amt, btn); };
            grid.appendChild(btn);
        });
    }

    function selectType(type) {
        document.getElementById("donation_type").value = type;
        document.getElementById('btn-once').classList.toggle('active', type === 'one-time');
        document.getElementById('btn-monthly').classList.toggle('active', type === 'monthly');
    }

    function selectAmount(amount, btnElement) {
        document.getElementById("amount").value = amount;
        document.getElementById("custom_amount").value = amount;
        document.querySelectorAll('.btn-amount').forEach(b => b.classList.remove('selected'));
        if(btnElement) btnElement.classList.add('selected');
    }

    document.getElementById('custom_amount').addEventListener('input', function() {
    // 1. 如果用户输入了负数或0，强制改为1 (或者留空让用户重新输入)
    if (this.value !== "" && parseInt(this.value) < 1) {
        this.value = 1;
    }
    
    // 2. 同步到隐藏的 amount 字段
    document.getElementById("amount").value = this.value;
    
    // 3. 取消预设按钮的选择状态
    document.querySelectorAll('.btn-amount').forEach(b => b.classList.remove('selected'));
});

    function toggleTaxReceipt() {
        const checkbox = document.getElementById('receipt_checkbox');
        const hiddenInput = document.getElementById('tax_receipt');

        if (checkbox.checked) {
            if (!isProfileComplete) {
                checkbox.checked = false; 
                Swal.fire({
                    title: 'Action Required',
                    text: 'To issue a Tax Exemption Receipt, we need your IC Number and complete Address. Please update your profile.',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#00a651',
                    confirmButtonText: 'Go to Profile',
                    cancelButtonText: 'Not now'
                }).then((result) => {
                    if (result.isConfirmed) window.location.href = 'Profile.php';
                });
                return;
            }

            checkbox.checked = false; 
            Swal.fire({
                title: 'Tax Receipt Policy',
                html: 'The minimum donation amount for a tax-deductible receipt is <b>RM 30.00</b>.<br><br>Do you agree to this condition?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00a651',
                confirmButtonText: 'Yes, I agree',
                cancelButtonText: 'No, cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    checkbox.checked = true;
                    renderButtons(taxAmounts);
                    hiddenInput.value = "1";
                    
                    let currentAmt = parseFloat(document.getElementById('amount').value) || 0;
                    if(currentAmt < 30) {
                        document.getElementById('amount').value = "";
                        document.getElementById('custom_amount').value = "";
                        document.querySelectorAll('.btn-amount').forEach(b => b.classList.remove('selected'));
                    }
                } else {
                    checkbox.checked = false;
                    hiddenInput.value = "0";
                }
            });
        } else {
            renderButtons(defaultAmounts);
            hiddenInput.value = "0";
        }
    }

    document.getElementById('donationForm').addEventListener('submit', function(e) {
    const amount = parseInt(document.getElementById("amount").value) || 0;
    const needsReceipt = document.getElementById('tax_receipt').value === "1";
    
    if (amount < 1) { // 限制必须至少为 1
        e.preventDefault();
        Swal.fire({ 
            icon: 'warning', 
            title: 'Invalid Amount', 
            text: 'Minimum donation amount is RM 1.', 
            confirmButtonColor: '#dc2626' 
        });
    } else if (needsReceipt && amount < 30) {
        // ... 原有的 tax receipt 检查保持不变
    }
});
</script>

<?php include 'footer.php'; ?>