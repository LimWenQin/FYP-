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

// 2. 获取当前选择的 Case ID
$case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;

// 尝试从 Session 恢复
if ($case_id == 0 && isset($_SESSION['donation_data']['case_id'])) {
    $case_id = $_SESSION['donation_data']['case_id'];
}

if ($case_id == 0) {
    echo "<script>alert('Invalid Case ID.'); window.location.href='Homepage.php';</script>";
    exit();
}

// 3. 查询当前 Case 详情
$case_sql = "SELECT * FROM special_case WHERE Case_ID = ?";
$stmt = $conn->prepare($case_sql);
$stmt->bind_param("i", $case_id);
$stmt->execute();
$special_case = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$special_case) {
    echo "<script>alert('Case not found.'); window.location.href='Homepage.php';</script>";
    exit();
}

// 4. 查询其他 Special Cases (C区)
$other_cases_sql = "SELECT * FROM special_case WHERE Case_ID != ? AND Case_Status = 'Active' ORDER BY Created_At DESC LIMIT 3";
$stmt_other = $conn->prepare($other_cases_sql);
$stmt_other->bind_param("i", $case_id);
$stmt_other->execute();
$other_cases_res = $stmt_other->get_result();

// [Session 恢复] 
$sess_amount = isset($_SESSION['donation_data']['amount']) ? $_SESSION['donation_data']['amount'] : '';
$sess_type = isset($_SESSION['donation_data']['type']) ? $_SESSION['donation_data']['type'] : 'one-time';
$sess_receipt = isset($_SESSION['donation_data']['tax_receipt']) ? $_SESSION['donation_data']['tax_receipt'] : '0';

// 5. 检查用户资料 (Tax)
$check_sql = "SELECT Donor_ICNumber, Donor_Address1, Donor_City, Donor_PostalCode FROM donor WHERE Donor_ID = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$is_profile_complete = (
    !empty($user_data['Donor_ICNumber']) && 
    !empty($user_data['Donor_Address1']) &&
    !empty($user_data['Donor_City']) &&
    !empty($user_data['Donor_PostalCode'])
) ? 'true' : 'false';

include 'header_UI.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Hero */
    .hero {
        height: 350px;
        background-color: #333;
        background-image: url('images/hero_5.jpg');
        background-size: cover;
        background-position: center;
        position: relative;
        display: flex; align-items: center; justify-content: center; text-align: center;
    }
    .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
    .hero-text { position: relative; color: #fff; z-index: 1; }
    .hero-text h1 { font-size: 42px; margin-bottom: 10px; font-family: 'Segoe UI', sans-serif; }
    
    /* ================= 核心布局样式 (Flexbox 强制左右) ================= */
    .page-section { padding: 60px 0; }
    
    .split-layout {
        display: flex;       /* 开启 Flexbox */
        gap: 40px;           /* 左右间距 */
        align-items: stretch;/* 等高 */
        flex-wrap: wrap;     /* 允许换行 (手机端) */
    }

    .layout-left {
        flex: 1;             /* 占 1 份宽 */
        min-width: 350px;    /* 最小宽度，防止太窄 */
    }

    .layout-right {
        flex: 1;             /* 占 1 份宽 */
        min-width: 350px;
    }

    /* 手机端强制上下排列 */
    @media (max-width: 900px) {
        .split-layout { flex-direction: column; }
        .layout-left, .layout-right { width: 100%; }
    }

    /* ================= A 区: Case Details ================= */
    .case-details-box {
        background: #fff; border-radius: 12px; overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08); border: 1px solid #eee;
        height: 100%; 
    }
    .case-img-wrap { position: relative; height: 320px; overflow: hidden; }
    .case-img { width: 100%; height: 100%; object-fit: cover; }
    
    .case-info { padding: 30px; }
    .case-title { font-size: 1.8rem; font-weight: 700; color: #222; margin-bottom: 15px; line-height: 1.3; }
    .case-desc { font-size: 1rem; color: #555; line-height: 1.6; margin-bottom: 25px; text-align: justify; }

    /* Progress Bar */
    .progress-wrap { margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
    .progress-bar-bg { height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden; margin-top: 8px; }
    .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #dc2626, #ef4444); border-radius: 5px; }
    .progress-stats { display: flex; justify-content: space-between; font-weight: 700; font-size: 0.9rem; color: #333; }
    
    /* ================= B 区: Donation Card ================= */
    .donation-card { 
        background: #fff; padding: 35px; border-radius: 12px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid #f0f0f0; 
        height: 100%; 
    }
    .form-header { text-align: center; margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .form-header h2 { font-size: 1.8rem; font-weight: 700; color: #333; margin-bottom: 5px; }

    /* Type Switcher */
    .type-group { display: flex; margin-bottom: 20px; border: 1px solid #dc2626; border-radius: 6px; overflow: hidden; }
    .type-option { flex: 1; text-align: center; padding: 12px; cursor: pointer; background: #fff; color: #dc2626; font-weight: bold; transition: 0.2s; }
    .type-option.active { background: #dc2626; color: #fff; }

    /* Amount Grid */
    .amount-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
    .btn-amount { padding: 15px 5px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; border-radius: 8px; font-size: 15px; font-weight: 600; transition: 0.2s; display: flex; align-items: center; justify-content: center; }
    .btn-amount:hover { border-color: #dc2626; color: #dc2626; }
    .btn-amount.selected { background: #dc2626; color: #fff; border-color: #dc2626; box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3); }

    .input-custom { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 20px; font-size: 16px; text-align: center;}
    
    /* Tax Section */
    .tax-receipt-section { background-color: #fdf2f2; padding: 15px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #fecaca; display: flex; align-items: center; gap: 10px; }
    .checkbox-label { display: flex; align-items: center; cursor: pointer; font-weight: 600; color: #333; font-size: 0.95rem; margin: 0; }
    .checkbox-label input { width: 18px; height: 18px; margin-right: 10px; cursor: pointer; accent-color: #dc2626; }
    
    /* Buttons */
    .nav-buttons { display: flex; gap: 15px; margin-top: 20px; }
    .btn-nav { flex: 1; padding: 15px; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; border: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; transition: 0.2s; }
    .btn-prev { background: #e5e7eb; color: #374151; }
    .btn-next { background: #dc2626; color: white; }
    .btn-next:hover { background: #b91c1c; transform: translateY(-3px); }

    /* ================= C 区: Bottom (Other Cases) ================= */
    .bottom-section { background-color: #f8f9fa; border-top: 1px solid #eee; margin-top: 0; }
    .section-title { text-align: center; font-size: 2rem; font-weight: 700; color: #333; margin-bottom: 40px; }
    
    .other-case-card { 
        background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
        transition: 0.3s; height: 100%; display: flex; flex-direction: column; 
    }
    .other-case-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    
    .other-img-wrap { height: 200px; overflow: hidden; position: relative; }
    .other-img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
    .other-case-card:hover .other-img { transform: scale(1.1); }
    
    .other-content { padding: 20px; flex: 1; display: flex; flex-direction: column; }
    .other-title { font-size: 1.2rem; font-weight: 700; color: #333; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .other-desc { font-size: 0.9rem; color: #666; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex: 1; }
    
    .btn-view-case { 
        display: block; width: 100%; padding: 10px; border: 2px solid #dc2626; border-radius: 30px; 
        color: #dc2626; font-weight: bold; text-align: center; text-decoration: none; transition: 0.2s; 
    }
    .btn-view-case:hover { background: #dc2626; color: white; text-decoration: none; }

</style>

<div class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-text">
        <h1>Support This Cause</h1>
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
        <div class="split-layout">
            
            <div class="layout-left">
                <div class="case-details-box">
                    <div class="case-img-wrap">
                        <img src="<?php echo htmlspecialchars($special_case['Case_Image']); ?>" alt="Case Image" class="case-img">
                    </div>
                    
                    <div class="case-info">
                        <h2 class="case-title"><?php echo htmlspecialchars($special_case['Case_Title']); ?></h2>
                        
                        <?php 
                            $percent = 0;
                            if($special_case['Target_Amount'] > 0) {
                                $percent = ($special_case['Raised_Amount'] / $special_case['Target_Amount']) * 100;
                                if($percent > 100) $percent = 100;
                            }
                        ?>
                        <div class="progress-wrap">
                            <div class="progress-stats">
                                <span>Raised: <span style="color:#dc2626;">RM <?php echo number_format($special_case['Raised_Amount']); ?></span></span>
                                <span>Target: RM <?php echo number_format($special_case['Target_Amount']); ?></span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                            </div>
                            <div class="text-right mt-1" style="font-size:0.85rem; color:#888;">
                                <?php echo number_format($percent, 1); ?>% Funded
                            </div>
                        </div>

                        <p class="case-desc"><?php echo nl2br(htmlspecialchars($special_case['Case_Description'])); ?></p>
                    </div>
                </div>
            </div>

            <div class="layout-right">
                <div class="donation-card">
                    <div class="form-header">
                        <h2>Select Donation Amount</h2>
                        <p>Make a difference today</p>
                    </div>

                    <form id="donationForm" method="POST" action="S_C_Payment_Ways_Page.php">
                        
                        <input type="hidden" id="donation_type" name="donation_type" value="one-time">
                        <input type="hidden" id="amount" name="amount">
                        <input type="hidden" id="tax_receipt" name="tax_receipt" value="0">
                        <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">

                        <div class="type-group">
                            <div class="type-option active" id="btn-once" onclick="selectType('one-time')">One-time</div>
                            <div class="type-option" id="btn-monthly" onclick="selectType('monthly')">Monthly</div>
                        </div>

                        <div class="amount-grid" id="amount-grid"></div>

                        <input type="number" id="custom_amount" name="custom_amount" class="input-custom" placeholder="Enter custom amount (RM)">

                        <div class="tax-receipt-section">
                            <label class="checkbox-label">
                                <input type="checkbox" id="receipt_checkbox" onchange="toggleTaxReceipt()">
                                I need a Tax Exemption Receipt
                            </label>
                            <span class="tax-note" style="font-size:12px; color:#666;">(Min RM 30)</span>
                        </div>

                        <div class="nav-buttons">
                            <a href="Homepage.php" class="btn-nav btn-prev">
                                Cancel
                            </a>
                            <button type="submit" class="btn-nav btn-next">
                                Next Step <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php if ($other_cases_res->num_rows > 0): ?>
<div class="page-section bottom-section">
    <div class="container">
        <h2 class="section-title">Other Urgent Cases</h2>
        
        <div class="row">
            <?php while($row = $other_cases_res->fetch_assoc()): 
                $other_percent = 0;
                if($row['Target_Amount'] > 0) {
                    $other_percent = ($row['Raised_Amount'] / $row['Target_Amount']) * 100;
                    if($other_percent > 100) $other_percent = 100;
                }
            ?>
                <div class="col-md-4 mb-4">
                    <div class="other-case-card">
                        <div class="other-img-wrap">
                            <img src="<?php echo htmlspecialchars($row['Case_Image']); ?>" class="other-img" alt="Case">
                        </div>
                        <div class="other-content">
                            <h3 class="other-title"><?php echo htmlspecialchars($row['Case_Title']); ?></h3>
                            
                            <div class="progress-bar-bg mb-2" style="height:6px;">
                                <div class="progress-bar-fill" style="width: <?php echo $other_percent; ?>%;"></div>
                            </div>
                            <div style="font-size:0.8rem; color:#888; margin-bottom:10px;">
                                RM <?php echo number_format($row['Raised_Amount']); ?> raised
                            </div>

                            <p class="other-desc"><?php echo htmlspecialchars(substr($row['Case_Description'], 0, 100)) . '...'; ?></p>
                            
                            <a href="S_C_Payment_Page.php?case_id=<?php echo $row['Case_ID']; ?>" class="btn-view-case">
                                View Case
                            </a>
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
        renderButtons(defaultAmounts);

        // 恢复数据逻辑
        const savedAmount = "<?php echo $sess_amount; ?>";
        if(savedAmount) {
            document.getElementById('amount').value = savedAmount;
            document.getElementById('custom_amount').value = savedAmount;
            setTimeout(() => {
                document.querySelectorAll('.btn-amount').forEach(btn => {
                    let btnVal = btn.innerText.replace('RM ', '');
                    if(btnVal == savedAmount) btn.classList.add('selected');
                });
            }, 100);
        }

        const savedType = "<?php echo $sess_type; ?>";
        if(savedType) selectType(savedType);

        const savedReceipt = "<?php echo $sess_receipt; ?>";
        if(savedReceipt === '1') {
            document.getElementById('receipt_checkbox').checked = true;
            toggleTaxReceipt();
            if(savedAmount) {
                setTimeout(() => {
                    document.getElementById('amount').value = savedAmount; 
                    document.querySelectorAll('.btn-amount').forEach(btn => {
                        let btnVal = btn.innerText.replace('RM ', '');
                        if(btnVal == savedAmount) btn.classList.add('selected');
                    });
                }, 100);
            }
        }
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
        document.getElementById("amount").value = this.value;
        document.querySelectorAll('.btn-amount').forEach(b => b.classList.remove('selected'));
    });

    function toggleTaxReceipt() {
        const checkbox = document.getElementById('receipt_checkbox');
        const hiddenInput = document.getElementById('tax_receipt');
        
        if (checkbox.checked) {
            renderButtons(taxAmounts);
            hiddenInput.value = "1";
            let currentVal = parseFloat(document.getElementById("amount").value) || 0;
            if (currentVal > 0 && currentVal < 30) {
                selectAmount(30, document.querySelector('.btn-amount')); 
                Swal.fire({ toast: true, position: 'top', icon: 'info', title: 'Minimum RM 30 for Receipt', showConfirmButton: false, timer: 2000 });
            }
        } else {
            renderButtons(defaultAmounts);
            hiddenInput.value = "0";
        }
    }

    document.getElementById('donationForm').addEventListener('submit', function(e) {
        const amount = parseFloat(document.getElementById("amount").value) || 0;
        const needsReceipt = document.getElementById('receipt_checkbox').checked;

        if (amount <= 0) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Oops...', text: 'Please select or enter a donation amount.' });
            return;
        }

        if (needsReceipt) {
            if (amount < 30) {
                e.preventDefault();
                Swal.fire({ icon: 'error', title: 'Minimum Amount', text: 'To receive a Tax Receipt, minimum donation is RM 30.', confirmButtonColor: '#cd4e4eff' });
                return;
            }
            if (!isProfileComplete) {
                e.preventDefault();
                Swal.fire({
                    title: 'Incomplete Profile',
                    text: "Tax Receipts require your IC & Address. Please update your profile.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Update Profile',
                    confirmButtonColor: '#cd4e4eff',
                }).then((result) => {
                    if (result.isConfirmed) window.location.href = 'Track_Records.php';
                });
                return;
            }
        }
    });
</script>

<?php $conn->close(); ?>