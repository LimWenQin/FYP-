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
// [NEW] 检查是否有 Session 数据 (从第二页返回时恢复数据)
// ==========================================
$sess_amount = isset($_SESSION['donation_data']['amount']) ? $_SESSION['donation_data']['amount'] : '';
$sess_type = isset($_SESSION['donation_data']['type']) ? $_SESSION['donation_data']['type'] : 'one-time';
$sess_receipt = isset($_SESSION['donation_data']['tax_receipt']) ? $_SESSION['donation_data']['tax_receipt'] : '0';
// 尝试恢复选中的 Case 或 Activity ID
$sess_case_id = isset($_SESSION['donation_data']['case_id']) ? $_SESSION['donation_data']['case_id'] : '';
$sess_activity_id = isset($_SESSION['donation_data']['activity_id']) ? $_SESSION['donation_data']['activity_id'] : '';


// ==========================================
// 1. 获取最新的 Special Case (取1个)
// ==========================================
$case_sql = "SELECT * FROM special_case WHERE Case_Status = 'Active' ORDER BY Created_At DESC LIMIT 1";
$case_res = $conn->query($case_sql);
$special_case = $case_res->fetch_assoc();

// ==========================================
// 2. 获取即将开始的 Activity (取1个)
// ==========================================
$act_sql = "SELECT * FROM activity WHERE Activity_Status = 'Active' AND Activity_Date >= CURDATE() ORDER BY Activity_Date ASC LIMIT 1";
$act_res = $conn->query($act_sql);
$activity = $act_res->fetch_assoc();

// ==========================================
// 3. 检查用户资料完整性
// ==========================================
$check_sql = "SELECT Donor_ICNumber, Donor_Address1, Donor_City, Donor_PostalCode FROM donor WHERE Donor_ID = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$res = $stmt->get_result();
$user_data = $res->fetch_assoc();

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
    .hero-text h1 { font-size: 48px; margin-bottom: 10px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    
    /* Main Layout Containers */
    .top-section {
        display: flex; gap: 40px; flex-wrap: wrap; padding: 50px 0;
        border-bottom: 1px solid #eee; /* 分割线 */
    }

    .col-left { flex: 1; min-width: 350px; display: flex; align-items: flex-start; }
    .col-right { flex: 1; min-width: 350px; }
    
    /* 上方左侧大图 */
    .story-img { width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); object-fit: cover; }

    /* Donation Card (Form) */
    .donation-card { 
        background: #fff; padding: 30px; border-radius: 8px; 
        box-shadow: 0 5px 25px rgba(0,0,0,0.1); border: 1px solid #eee; 
    }
    
    /* --- Bottom Full Width Section --- */
    .bottom-section { padding: 60px 0; }
    .section-title { text-align: center; font-size: 2rem; font-weight: 700; color: #333; margin-bottom: 40px; position: relative; }
    .section-title::after {
        content: ''; display: block; width: 60px; height: 4px; background: #dc2626; margin: 10px auto 0; border-radius: 2px;
    }

    .showcase-block {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        display: flex;
        border: 1px solid #f0f0f0;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        margin-bottom: 50px; /* 区块间距 */
        min-height: 350px; /* 增加高度，显得更大气 */
    }
    .showcase-block:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.15); }
    
    /* 图片占 50% */
    .showcase-img-box { flex: 1; position: relative; overflow: hidden; }
    .showcase-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease; }
    .showcase-block:hover .showcase-img { transform: scale(1.05); }
    
    /* 内容占 50% */
    .showcase-content { flex: 1; padding: 40px; display: flex; flex-direction: column; justify-content: center; }
    
    /* 标签和文字 */
    .badge-tag { 
        display: inline-block; padding: 6px 14px; border-radius: 20px; 
        font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; width: fit-content;
    }
    .tag-case { background: #fee2e2; color: #dc2626; }
    .tag-activity { background: #dbeafe; color: #2563eb; }
    
    .sc-title { font-size: 1.8rem; font-weight: 700; color: #222; margin: 0 0 15px 0; line-height: 1.3; }
    .sc-desc { font-size: 1rem; color: #666; margin-bottom: 25px; line-height: 1.6; }
    
    /* 进度条样式 */
    .progress-wrap { margin-bottom: 25px; }
    .progress-bar-bg { height: 10px; background: #eee; border-radius: 5px; overflow: hidden; }
    .progress-bar-fill { height: 100%; background: #dc2626; border-radius: 5px; }
    .progress-text { font-size: 0.9rem; color: #555; margin-top: 8px; display: flex; justify-content: space-between; font-weight: 600; }

    /* 按钮 */
    .btn-support {
        padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: 0.2s;
        display: inline-flex; align-items: center; gap: 10px; width: fit-content; font-size: 1rem;
    }
    .btn-case { background: #dc2626; color: white; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3); }
    .btn-case:hover { background: #b91c1c; transform: translateY(-2px); }
    .btn-act { background: #2563eb; color: white; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3); }
    .btn-act:hover { background: #1d4ed8; transform: translateY(-2px); }

    /* Form Styles (Small details) */
    .type-group { display: flex; margin-bottom: 20px; border: 1px solid #cd4e4eff; border-radius: 5px; overflow: hidden; }
    .type-option { flex: 1; text-align: center; padding: 10px; cursor: pointer; background: #fff; color: #cd4e4eff; font-weight: bold; }
    .type-option.active { background: #cd4e4eff; color: #fff; }

    .amount-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
    .btn-amount { padding: 12px; border: 1px solid #ddd; background: #fff; cursor: pointer; border-radius: 4px; font-size: 14px; transition: 0.2s; }
    .btn-amount:hover { background: #f0f0f0; }
    .btn-amount:active, .btn-amount.selected { background: #cd4e4eff; color: #fff; border-color: #cd4e4eff; }

    .input-custom { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 20px; font-size: 16px; }
    
    .tax-receipt-section { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #cd4e4eff; }
    .checkbox-label { display: flex; align-items: center; cursor: pointer; font-weight: 600; color: #333; }
    .checkbox-label input { width: 18px; height: 18px; margin-right: 10px; cursor: pointer; }
    .tax-note { font-size: 12px; color: #666; margin-top: 5px; margin-left: 28px; }

    /* [NEW] Navigation Buttons */
    .nav-buttons { display: flex; gap: 15px; margin-top: 20px; }
    .btn-nav { flex: 1; padding: 15px; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; border: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px; }
    
    .btn-next { background: #00a651; color: white; transition: 0.2s; }
    .btn-next:hover { background: #008f45; }
    
    .btn-prev { background: #e5e7eb; color: #9ca3af; cursor: not-allowed; } /* Disabled Style */

    /* Responsive */
    @media (max-width: 900px) {
        .top-section { flex-direction: column-reverse; } /* 手机上表格在先 */
        .showcase-block { flex-direction: column !important; min-height: auto; } /* 手机上全变垂直排列 */
        .showcase-img-box { height: 250px; flex: none; }
        .showcase-content { flex: none; }
    }
</style>

<div class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-text">
        <h1>Make a Difference</h1>
        <p>Your support helps us build a better future.</p>
    </div>
</div>

<?php 
    $current_step = 1; 
    $flow_type = 'standard'; 
    include 'stepper.php'; 
?>

<div class="container">
    
    <div class="top-section">
        
        <div class="col-left">
            <img src="images/hero_3.jpg" alt="Donation Story" class="story-img">
        </div>

        <div class="col-right">
            <div class="donation-card" id="donation-card-anchor">
                <h2 style="text-align:center;" id="form-title">General Donation</h2>
                <p style="text-align:center; color:#777; font-size:0.9rem; margin-bottom:20px;" id="form-subtitle">Supporting our main fund</p>

                <form id="donationForm" method="POST" action="Branch_Selection.php">
                    
                    <div class="type-group">
                        <div class="type-option active" id="btn-once" onclick="selectType('one-time')">One-time</div>
                        <div class="type-option" id="btn-monthly" onclick="selectType('monthly')">Monthly</div>
                    </div>

                    <input type="hidden" id="donation_type" name="donation_type" value="one-time">
                    <input type="hidden" id="amount" name="amount">
                    <input type="hidden" id="tax_receipt" name="tax_receipt" value="0">
                    
                    <input type="hidden" id="branch_id" name="branch_id" value=""> 
                    <input type="hidden" id="case_id" name="case_id" value="">
                    <input type="hidden" id="activity_id" name="activity_id" value="">

                    <div class="amount-grid" id="amount-grid"></div>

                    <input type="number" id="custom_amount" name="custom_amount" class="input-custom" placeholder="Enter custom amount (RM)">

                    <div class="tax-receipt-section">
                        <label class="checkbox-label">
                            <input type="checkbox" id="receipt_checkbox" onchange="toggleTaxReceipt()">
                            I need a Tax Exemption Receipt
                        </label>
                        <div class="tax-note">
                            * Minimum donation: <strong>RM 30</strong><br>
                            * Requires complete Profile (IC & Address)
                        </div>
                    </div>

                    <div class="nav-buttons">
                        <button type="button" class="btn-nav btn-prev" disabled>Previous</button>
                        
                        <button type="submit" class="btn-nav btn-next">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                    
                    <div style="text-align:center; margin-top:10px; display:none;" id="reset-link">
                        <a href="#" onclick="resetForm(); return false;" style="color:#999; font-size:0.85rem; text-decoration:none;">
                            <i class="fas fa-undo"></i> Cancel selection & donate to General Fund
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <div class="bottom-section">
        
        <h2 class="section-title">Urgent Needs & Events</h2>

        <?php if ($special_case): 
            $percent = 0;
            if($special_case['Target_Amount'] > 0) {
                $percent = ($special_case['Raised_Amount'] / $special_case['Target_Amount']) * 100;
                if($percent > 100) $percent = 100;
            }
        ?>
        <div class="showcase-block">
            <div class="showcase-img-box">
                <img src="<?php echo htmlspecialchars($special_case['Case_Image']); ?>" alt="Case" class="showcase-img">
            </div>
            <div class="showcase-content">
                <span class="badge-tag tag-case">Featured Case</span>
                <h3 class="sc-title"><?php echo htmlspecialchars($special_case['Case_Title']); ?></h3>
                <p class="sc-desc"><?php echo htmlspecialchars($special_case['Case_Description']); ?></p>
                
                <div class="progress-wrap">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                    </div>
                    <div class="progress-text">
                        <span>Raised: RM <?php echo number_format($special_case['Raised_Amount']); ?></span>
                        <span>Goal: RM <?php echo number_format($special_case['Target_Amount']); ?></span>
                    </div>
                </div>

                <button type="button" class="btn-support btn-case" 
                    onclick="setDonationTarget('case', <?php echo $special_case['Case_ID']; ?>, '<?php echo addslashes($special_case['Case_Title']); ?>')">
                    <i class="fas fa-hand-holding-heart"></i> Donate to this Case
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activity): ?>
        <div class="showcase-block" style="flex-direction: row-reverse;">
            <div class="showcase-img-box">
                <img src="<?php echo !empty($activity['Activity_Picture']) ? htmlspecialchars($activity['Activity_Picture']) : 'images/activity_default.jpg'; ?>" 
                     alt="Activity" class="showcase-img">
            </div>
            <div class="showcase-content">
                <span class="badge-tag tag-activity">Upcoming Activity</span>
                <h3 class="sc-title"><?php echo htmlspecialchars($activity['Activity_Name']); ?></h3>
                <p class="sc-desc"><?php echo htmlspecialchars($activity['Activity_Details']); ?></p>
                
                <div style="margin-bottom:25px; font-size:1rem; color:#555;">
                    <i class="far fa-calendar-alt" style="color:#2563eb; margin-right:8px;"></i> 
                    <strong>Event Date:</strong> <?php echo date("l, d M Y", strtotime($activity['Activity_Date'])); ?>
                </div>

                <button type="button" class="btn-support btn-act" 
                    onclick="setDonationTarget('activity', <?php echo $activity['Activity_ID']; ?>, '<?php echo addslashes($activity['Activity_Name']); ?>')">
                    <i class="fas fa-running"></i> Support Activity
                </button>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<script>
    const isProfileComplete = <?php echo $is_profile_complete; ?>;
    const defaultAmounts = [20, 50, 100, 200, 300, 500];
    const taxAmounts = [30, 50, 100, 200, 300, 500];

    // [NEW] 页面加载时：从 Session 恢复数据
    document.addEventListener('DOMContentLoaded', function() {
        renderButtons(defaultAmounts);

        // 1. 恢复金额
        const savedAmount = "<?php echo $sess_amount; ?>";
        if(savedAmount) {
            document.getElementById('amount').value = savedAmount;
            document.getElementById('custom_amount').value = savedAmount;
            
            // 延迟高亮，因为 renderButtons 可能还没执行完或者被覆盖
            setTimeout(() => {
                document.querySelectorAll('.btn-amount').forEach(btn => {
                    if(btn.innerText.includes(savedAmount)) btn.classList.add('selected');
                });
            }, 100);
        }

        // 2. 恢复类型
        const savedType = "<?php echo $sess_type; ?>";
        if(savedType) selectType(savedType);

        // 3. 恢复免税
        const savedReceipt = "<?php echo $sess_receipt; ?>";
        if(savedReceipt === '1') {
            document.getElementById('receipt_checkbox').checked = true;
            toggleTaxReceipt(); // 重新触发渲染
            
            // 再次确保金额被填入 (因为 toggleTaxReceipt 会重置按钮)
            if(savedAmount) {
                setTimeout(() => {
                    document.getElementById('amount').value = savedAmount;
                    document.getElementById('custom_amount').value = savedAmount;
                    document.querySelectorAll('.btn-amount').forEach(btn => {
                        if(btn.innerText.includes(savedAmount)) btn.classList.add('selected');
                    });
                }, 100);
            }
        }

        // 4. 尝试恢复选中的 Case / Activity (如果它们恰好是当前显示的)
        const savedCaseId = "<?php echo $sess_case_id; ?>";
        const savedActivityId = "<?php echo $sess_activity_id; ?>";
        const currentCaseId = "<?php echo $special_case['Case_ID'] ?? ''; ?>";
        const currentActId = "<?php echo $activity['Activity_ID'] ?? ''; ?>";

        if(savedCaseId && savedCaseId == currentCaseId) {
            setDonationTarget('case', savedCaseId, "<?php echo addslashes($special_case['Case_Title'] ?? ''); ?>");
        } else if(savedActivityId && savedActivityId == currentActId) {
            setDonationTarget('activity', savedActivityId, "<?php echo addslashes($activity['Activity_Name'] ?? ''); ?>");
        }
    });

    // ------------------------------------
    // 核心功能
    // ------------------------------------
    function setDonationTarget(type, id, name) {
        document.getElementById('case_id').value = "";
        document.getElementById('activity_id').value = "";
        
        if (type === 'case') {
            document.getElementById('case_id').value = id;
            document.getElementById('form-title').innerText = "Donate to Case";
            document.getElementById('form-title').style.color = "#dc2626";
        } else if (type === 'activity') {
            document.getElementById('activity_id').value = id;
            document.getElementById('form-title').innerText = "Support Activity";
            document.getElementById('form-title').style.color = "#2563eb";
        }
        
        document.getElementById('form-subtitle').innerText = "Selected: " + name;
        document.getElementById('reset-link').style.display = 'block';

        const formElement = document.getElementById('donation-card-anchor');
        const offset = 100; 
        const bodyRect = document.body.getBoundingClientRect().top;
        const elementRect = formElement.getBoundingClientRect().top;
        const elementPosition = elementRect - bodyRect;
        const offsetPosition = elementPosition - offset;

        window.scrollTo({ top: offsetPosition, behavior: "smooth" });
        
        // 只有当不是页面自动恢复时才弹窗 (判断 title 是否为空)
        if(name) {
             // 简单的 Check: 如果是用户点击触发的
        }
    }

    function resetForm() {
        document.getElementById('case_id').value = "";
        document.getElementById('activity_id').value = "";
        document.getElementById('form-title').innerText = "General Donation";
        document.getElementById('form-title').style.color = "#000";
        document.getElementById('form-subtitle').innerText = "Supporting our main fund";
        document.getElementById('reset-link').style.display = 'none';
    }

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
                Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Minimum RM 30 for Receipt', showConfirmButton: false, timer: 2000 });
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
                    if (result.isConfirmed) window.location.href = 'Profile.php';
                });
                return;
            }
        }
    });
</script>

<?php include 'footer.php'; ?>