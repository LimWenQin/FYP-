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
// 2. 智能识别：是 Campaign (Activity) 还是 Special Case？
// ==========================================
$get_activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
$get_case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;

$activity_id = 0;
$case_id = 0;

if ($get_activity_id > 0) {
    $activity_id = $get_activity_id;
    if (isset($_SESSION['donation_data'])) {
        unset($_SESSION['donation_data']['case_id']);
        $_SESSION['donation_data']['activity_id'] = $activity_id;
    }
} elseif ($get_case_id > 0) {
    $case_id = $get_case_id;
    if (isset($_SESSION['donation_data'])) {
        unset($_SESSION['donation_data']['activity_id']);
        $_SESSION['donation_data']['case_id'] = $case_id;
    }
} else {
    if (!empty($_SESSION['donation_data']['activity_id'])) {
        $activity_id = $_SESSION['donation_data']['activity_id'];
    } elseif (!empty($_SESSION['donation_data']['case_id'])) {
        $case_id = $_SESSION['donation_data']['case_id'];
    }
}

$mode = '';
$main_data = [];
$param_id = 0;

if ($activity_id > 0) {
    $mode = 'activity';
    $param_id = $activity_id;
    $sql = "SELECT Activity_ID as id, Activity_Name as title, Activity_Images as image, Activity_Description as `desc`, Activity_GetAmount as raised, Activity_TargetAmount as target 
            FROM activity WHERE Activity_ID = ?";
    $page_label = "Campaign Donation";
    $other_label = "Other Campaigns";
    
} elseif ($case_id > 0) {
    $mode = 'case';
    $param_id = $case_id;
    $sql = "SELECT Case_ID as id, Case_Title as title, Case_Images as image, Case_Description as `desc`, Raised_Amount as raised, Target_Amount as target 
            FROM special_case WHERE Case_ID = ?";
    $page_label = "Special Case Donation";
    $other_label = "Other Urgent Cases";
} else {
    echo "<script>alert('Invalid Request.'); window.location.href='Homepage.php';</script>";
    exit();
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $param_id);
$stmt->execute();
$res = $stmt->get_result();
$main_data = $res->fetch_assoc();
$stmt->close();

if (!$main_data) {
    echo "<script>alert('Project not found.'); window.location.href='Homepage.php';</script>";
    exit();
}

$display_image = 'images/default_donation.jpg';
if (!empty($main_data['image'])) {
    $img_array = json_decode($main_data['image'], true);
    $display_image = is_array($img_array) ? $img_array[0] : $main_data['image'];
}

// ==========================================
// 3. 查询“其他项目”
// ==========================================
if ($mode == 'activity') {
    $other_sql = "SELECT Activity_ID as id, Activity_Name as title, Activity_Images as image, Activity_Description as `desc`, Activity_GetAmount as raised, Activity_TargetAmount as target 
                  FROM activity WHERE Activity_ID != ? AND Activity_Status = 'Active' 
                  ORDER BY Activity_ID ASC LIMIT 3";
} else {
    $other_sql = "SELECT Case_ID as id, Case_Title as title, Case_Images as image, Case_Description as `desc`, Raised_Amount as raised, Target_Amount as target 
                  FROM special_case WHERE Case_ID != ? AND Case_Status = 'Active' 
                  ORDER BY Created_At DESC LIMIT 3";
}

$stmt_other = $conn->prepare($other_sql);
$stmt_other->bind_param("i", $param_id);
$stmt_other->execute();
$other_res = $stmt_other->get_result();

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
    .hero { height: 350px; background-color: #333; background-image: url('images/hero_5.jpg'); background-size: cover; background-position: center; position: relative; display: flex; align-items: center; justify-content: center; text-align: center; }
    .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
    .hero-text { position: relative; color: #fff; z-index: 1; }
    .hero-text h1 { font-size: 42px; margin-bottom: 10px; font-family: 'Segoe UI', sans-serif; }
    .page-section { padding: 60px 0; }
    .top-layout { display: flex; gap: 40px; align-items: flex-start; flex-wrap: wrap; }
    .layout-left { flex: 1.2; min-width: 350px; }
    .layout-right { flex: 0.8; min-width: 350px; }
    @media (max-width: 768px) { .top-layout { flex-direction: column-reverse; } .layout-left, .layout-right { width: 100%; } }
    .details-box { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.08); border: 1px solid #eee; }
    .img-wrap { position: relative; height: 350px; overflow: hidden; }
    .main-img { width: 100%; height: 100%; object-fit: cover; }
    .info-wrap { padding: 30px; }
    .main-title { font-size: 2rem; font-weight: 700; color: #222; margin-bottom: 20px; line-height: 1.3; }
    .main-desc { font-size: 1.05rem; color: #555; line-height: 1.8; margin-bottom: 25px; text-align: justify; }
    .progress-wrap { margin-bottom: 20px; background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #eee; }
    .progress-bar-bg { height: 12px; background: #e5e7eb; border-radius: 6px; overflow: hidden; margin-top: 10px; }
    .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #dc2626, #ef4444); border-radius: 6px; }
    .progress-stats { display: flex; justify-content: space-between; font-weight: 700; font-size: 1rem; color: #333; }
    .donation-card { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid #f0f0f0; }
    .form-header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
    .form-header h2 { font-size: 1.8rem; font-weight: 700; color: #333; margin-bottom: 5px; }
    
    /* 替换原本切换按钮的样式 */
    .one-time-badge { text-align: center; padding: 12px; background: #fef2f2; color: #dc2626; border: 1px solid #dc2626; border-radius: 6px; font-weight: bold; margin-bottom: 10px; }
    
    .amount-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
    .btn-amount { padding: 15px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; border-radius: 8px; font-size: 16px; font-weight: 600; transition: 0.2s; display: flex; align-items: center; justify-content: center; }
    .btn-amount:hover { border-color: #dc2626; color: #dc2626; }
    .btn-amount.selected { background: #dc2626; color: #fff; border-color: #dc2626; box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3); }
    .input-custom { width: 100%; padding: 15px; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 20px; font-size: 16px; transition: 0.3s; text-align: center;}
    .input-custom:focus { border-color: #dc2626; outline: none; }
    
    .tax-receipt-section { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #dc2626; }
    .checkbox-label { display: flex; align-items: center; cursor: pointer; font-weight: 600; color: #333; }
    .checkbox-label input { width: 18px; height: 18px; margin-right: 10px; cursor: pointer; accent-color: #dc2626; }
    .tax-note { font-size: 12px; color: #666; margin-top: 5px; margin-left: 28px; line-height: 1.5; }

    .nav-buttons { display: flex; gap: 15px; margin-top: 20px; }
    .btn-nav { flex: 1; padding: 15px; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; border: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; transition: 0.2s; }
    .btn-next { background: #00a651; color: white; box-shadow: 0 4px 15px rgba(0, 166, 81, 0.3); width: 100%; }
    .btn-next:hover { background: #008f45; }

    .bottom-section { background-color: #f8f9fa; border-top: 1px solid #eee; }
    .other-card { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s; height: 100%; display: flex; flex-direction: column; }
    .other-img-wrap { height: 220px; overflow: hidden; position: relative; }
    .other-img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
    .other-content { padding: 25px; flex: 1; display: flex; flex-direction: column; }
    .btn-view { display: block; width: 100%; padding: 12px; border: 2px solid #dc2626; border-radius: 30px; color: #dc2626; font-weight: bold; text-align: center; text-decoration: none; }
</style>

<div class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-text">
        <h1><?php echo $page_label; ?></h1>
        <p>Your contribution directly impacts lives.</p>
    </div>
</div>

<?php 
    $current_step = 1; 
    $flow_type = 'special'; 
    include 'stepper.php'; 
?>

<div class="page-section">
    <div class="container">
        <div class="top-layout">
            <div class="layout-left">
                <div class="details-box">
                    <div class="img-wrap">
                        <img src="<?php echo htmlspecialchars($display_image); ?>" alt="Image" class="main-img">
                    </div>
                    <div class="info-wrap">
                        <h2 class="main-title"><?php echo htmlspecialchars($main_data['title']); ?></h2>
                        <?php 
                            $percent = ($main_data['target'] > 0) ? min(($main_data['raised'] / $main_data['target']) * 100, 100) : 0;
                        ?>
                        <div class="progress-wrap">
                            <div class="progress-stats">
                                <span>Raised: <span style="color:#dc2626;">RM <?php echo number_format($main_data['raised']); ?></span></span>
                                <span>Target: RM <?php echo number_format($main_data['target']); ?></span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                            </div>
                            <div class="text-right mt-1" style="font-size:0.9rem; color:#888;">
                                <?php echo number_format($percent, 1); ?>% Funded
                            </div>
                        </div>
                        <p class="main-desc"><?php echo nl2br(htmlspecialchars($main_data['desc'])); ?></p>
                    </div>
                </div>
            </div>

            <div class="layout-right">
                <div class="donation-card">
                    <div class="form-header">
                        <h2>Select Donation Amount</h2>
                        <p>Make a difference today</p>
                    </div>
                    <form id="donationForm" method="POST" action="S_C_Payment_Ways_Page.php" autocomplete="off">
                        <input type="hidden" id="donation_type" name="donation_type" value="one-time">
                        <input type="hidden" id="amount" name="amount">
                        <input type="hidden" id="tax_receipt" name="tax_receipt" value="0">
                        <input type="hidden" name="<?php echo $mode; ?>_id" value="<?php echo $main_data['id']; ?>">

                        <div style="margin-bottom: 25px;">
                            <div class="one-time-badge">
                                <i class="fas fa-hand-holding-heart"></i> One-time Donation
                            </div>
                            <p style="font-size: 0.85rem; color: #666; text-align: center;">
                                * This is an urgent case. Only one-time contributions are accepted.
                            </p>
                        </div>
                        
                        <div class="amount-grid" id="amount-grid"></div>
                        <input type="number" id="custom_amount" name="custom_amount" class="input-custom" placeholder="Enter custom amount (RM)" min="1" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        
                        <div class="tax-receipt-section">
                            <label class="checkbox-label">
                                <input type="checkbox" id="receipt_checkbox" onchange="toggleTaxReceipt()">I need a Tax Exemption Receipt
                            </label>
                        </div>
                        <div class="tax-note" style="margin-top:-15px; margin-bottom:20px; margin-left:5px;">
                            * Minimum donation: <strong>RM 30</strong><br>
                            * Requires complete Profile (IC & Address)
                        </div>

                        <div class="nav-buttons">
                            <button type="submit" class="btn-nav btn-next">Next Step <i class="fas fa-arrow-right"></i></button>
                        </div>

                        <div style="text-align:center; margin-top:15px;" id="reset-link">
                            <a href="#" onclick="resetForm(); return false;" style="color:#999; font-size:0.85rem; text-decoration:none;"><i class="fas fa-undo"></i> Cancel selection</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($other_res->num_rows > 0): ?>
<div class="page-section bottom-section">
    <div class="container">
        <h2 class="section-title"><?php echo $other_label; ?></h2>
        <div class="row">
            <?php while($row = $other_res->fetch_assoc()): 
                $other_percent = ($row['target'] > 0) ? min(($row['raised'] / $row['target']) * 100, 100) : 0;
                $other_img = 'images/default_donation.jpg';
                if(!empty($row['image'])){
                    $other_img_arr = json_decode($row['image'], true);
                    $other_img = is_array($other_img_arr) ? $other_img_arr[0] : $row['image'];
                }
                $link_param = ($mode == 'activity') ? "activity_id=" . $row['id'] : "case_id=" . $row['id'];
            ?>
                <div class="col-md-4 mb-4">
                    <div class="other-card">
                        <div class="other-img-wrap">
                            <img src="<?php echo htmlspecialchars($other_img); ?>" class="other-img" alt="Project">
                        </div>
                        <div class="other-content">
                            <h3 class="other-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                            <div class="progress-bar-bg mb-2" style="height:6px;">
                                <div class="progress-bar-fill" style="width: <?php echo $other_percent; ?>%;"></div>
                            </div>
                            <div style="font-size:0.8rem; color:#888; margin-bottom:10px;">RM <?php echo number_format($row['raised']); ?> raised</div>
                            <p class="other-desc"><?php echo htmlspecialchars(substr($row['desc'], 0, 100)) . '...'; ?></p>
                            <a href="S_C_Payment_Page.php?<?php echo $link_param; ?>" class="btn-view">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>

<script>
    const isProfileComplete = <?php echo $is_profile_complete; ?>;
    const defaultAmounts = [20, 50, 100, 200, 300, 500];
    const taxAmounts = [30, 50, 100, 200, 300, 500];

    document.addEventListener('DOMContentLoaded', function() {
        resetForm(); 
    });

    function renderButtons(amounts) {
        const grid = document.getElementById('amount-grid');
        grid.innerHTML = ''; 
        amounts.forEach(amt => {
            const btn = document.createElement('div');
            btn.className = 'btn-amount';
            btn.innerHTML = 'RM ' + amt;
            btn.onclick = function() { selectAmount(amt, btn); };
            grid.appendChild(btn);
        });
    }

    function selectAmount(amount, btnElement) {
        document.getElementById("amount").value = amount;
        document.getElementById("custom_amount").value = amount;
        document.querySelectorAll('.btn-amount').forEach(b => b.classList.remove('selected'));
        if(btnElement) btnElement.classList.add('selected');
    }

    document.getElementById('custom_amount').addEventListener('input', function() {
    // 如果用户输入的数字小于 1，自动纠正为 1 (仅在非空时判断)
    if (this.value !== "" && parseInt(this.value) < 1) {
        this.value = 1;
    }
    document.getElementById("amount").value = this.value;
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
                    confirmButtonColor: '#dc2626',
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
                confirmButtonColor: '#dc2626',
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

    function resetForm() {
    const form = document.getElementById('donationForm');
    form.reset();
    document.getElementById('amount').value = "";
    document.getElementById('custom_amount').value = ""; // 确保清空显示
    document.getElementById('tax_receipt').value = "0";
    document.getElementById('donation_type').value = "one-time"; 
    document.getElementById('receipt_checkbox').checked = false;
    renderButtons(defaultAmounts);
}

    document.getElementById('donationForm').addEventListener('submit', function(e) {
    // 使用 parseInt 确保是整数
    const amount = parseInt(document.getElementById("amount").value) || 0;
    const needsReceipt = document.getElementById('tax_receipt').value === "1";
    
    if (amount < 1) { // 修改这里：禁止 0 和负数
        e.preventDefault();
        Swal.fire({ 
            icon: 'warning', 
            title: 'Invalid Amount', 
            text: 'Please enter a valid donation amount (minimum RM 1).', 
            confirmButtonColor: '#dc2626' 
        });
    } else if (needsReceipt && amount < 30) {
        e.preventDefault();
        Swal.fire({ 
            icon: 'error', 
            title: 'Invalid Amount', 
            text: 'Tax receipts require a minimum donation of RM 30.', 
            confirmButtonColor: '#dc2626' 
        });
    }
});
</script>