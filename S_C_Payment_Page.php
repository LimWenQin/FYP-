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
// 获取 URL 参数
$get_activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
$get_case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;

// 初始化 ID 变量
$activity_id = 0;
$case_id = 0;

// 逻辑 A: 如果 URL 明确传递了参数，优先使用 URL 参数，并清理 Session 防止干扰
if ($get_activity_id > 0) {
    $activity_id = $get_activity_id;
    // 清除可能存在的旧 Session 数据，确保页面显示的是最新的 URL 请求
    if (isset($_SESSION['donation_data'])) {
        unset($_SESSION['donation_data']['case_id']); // 移除干扰
        $_SESSION['donation_data']['activity_id'] = $activity_id; // 更新 Session
    }
} elseif ($get_case_id > 0) {
    $case_id = $get_case_id;
    // 清除可能存在的旧 Session 数据
    if (isset($_SESSION['donation_data'])) {
        unset($_SESSION['donation_data']['activity_id']); // 移除干扰
        $_SESSION['donation_data']['case_id'] = $case_id; // 更新 Session
    }
} 
// 逻辑 B: 如果 URL 没有参数，尝试从 Session 恢复 (用于“上一步”返回场景)
else {
    if (!empty($_SESSION['donation_data']['activity_id'])) {
        $activity_id = $_SESSION['donation_data']['activity_id'];
    } elseif (!empty($_SESSION['donation_data']['case_id'])) {
        $case_id = $_SESSION['donation_data']['case_id'];
    }
}

// 初始化模式和数据
$mode = '';
$main_data = [];
$param_id = 0;

if ($activity_id > 0) {
    // --- 模式：Activity (Campaign) ---
    $mode = 'activity';
    $param_id = $activity_id;
    $sql = "SELECT Activity_ID as id, Activity_Name as title, Activity_Picture as image, Activity_Details as `desc`, Activity_GetAmount as raised, Activity_TargetAmount as target 
            FROM activity WHERE Activity_ID = ?";
    $page_label = "Campaign Donation";
    $other_label = "Other Campaigns";
    
} elseif ($case_id > 0) {
    // --- 模式：Special Case ---
    $mode = 'case';
    $param_id = $case_id;
    $sql = "SELECT Case_ID as id, Case_Title as title, Case_Image as image, Case_Description as `desc`, Raised_Amount as raised, Target_Amount as target 
            FROM special_case WHERE Case_ID = ?";
    $page_label = "Special Case Donation";
    $other_label = "Other Urgent Cases";
} else {
    // --- 两个 ID 都没有，无效请求 ---
    echo "<script>alert('Invalid Request. No campaign or case specified.'); window.location.href='Homepage.php';</script>";
    exit();
}

// 执行主查询
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $param_id);
$stmt->execute();
$res = $stmt->get_result();
$main_data = $res->fetch_assoc();
$stmt->close();

if (!$main_data) {
    echo "<script>alert('Project not found or inactive.'); window.location.href='Homepage.php';</script>";
    exit();
}

$display_image = !empty($main_data['image']) ? $main_data['image'] : 'images/default_donation.jpg';

// ==========================================
// 3. 查询“其他项目” (底部 C 区)
// ==========================================
if ($mode == 'activity') {
    $other_sql = "SELECT Activity_ID as id, Activity_Name as title, Activity_Picture as image, Activity_Details as `desc`, Activity_GetAmount as raised, Activity_TargetAmount as target 
                  FROM activity WHERE Activity_ID != ? AND Activity_Status = 'Active' 
                  ORDER BY Activity_StartDate ASC LIMIT 3";
} else {
    $other_sql = "SELECT Case_ID as id, Case_Title as title, Case_Image as image, Case_Description as `desc`, Raised_Amount as raised, Target_Amount as target 
                  FROM special_case WHERE Case_ID != ? AND Case_Status = 'Active' 
                  ORDER BY Created_At DESC LIMIT 3";
}

$stmt_other = $conn->prepare($other_sql);
$stmt_other->bind_param("i", $param_id);
$stmt_other->execute();
$other_res = $stmt_other->get_result();

// Session 数据恢复 (金额、类型等)
$sess_amount = $_SESSION['donation_data']['amount'] ?? '';
$sess_type = $_SESSION['donation_data']['type'] ?? 'one-time';
$sess_receipt = $_SESSION['donation_data']['tax_receipt'] ?? '0';

// 检查用户资料
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
    /* Hero Section */
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
    
    /* === 核心布局样式 (修复左右排列问题) === */
    .page-section { padding: 60px 0; }
    
    .top-layout {
        display: flex;
        gap: 40px;
        align-items: flex-start; /* 顶部对齐 */
        flex-wrap: wrap; /* 小屏幕允许折行 */
    }
    
    .layout-left {
        flex: 1; /* 占据剩余空间 */
        min-width: 350px; /* 防止缩得太小 */
    }
    
    .layout-right {
        flex: 1; /* 占据剩余空间 */
        min-width: 350px;
    }

    /* 手机端强制上下排列 */
    @media (max-width: 768px) {
        .top-layout { flex-direction: column-reverse; } /* 表单在上方，详情在下方 */
        .layout-left, .layout-right { width: 100%; }
    }
    
    /* A区: Details Box */
    .details-box {
        background: #fff; border-radius: 12px; overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08); border: 1px solid #eee;
    }
    .img-wrap { position: relative; height: 350px; overflow: hidden; }
    .main-img { width: 100%; height: 100%; object-fit: cover; }
    
    .info-wrap { padding: 30px; }
    .main-title { font-size: 2rem; font-weight: 700; color: #222; margin-bottom: 20px; line-height: 1.3; }
    .main-desc { font-size: 1.05rem; color: #555; line-height: 1.8; margin-bottom: 25px; text-align: justify; }

    /* Progress Bar */
    .progress-wrap { margin-bottom: 20px; background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #eee; }
    .progress-bar-bg { height: 12px; background: #e5e7eb; border-radius: 6px; overflow: hidden; margin-top: 10px; }
    .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #dc2626, #ef4444); border-radius: 6px; }
    .progress-stats { display: flex; justify-content: space-between; font-weight: 700; font-size: 1rem; color: #333; }
    
    /* B区: Donation Card */
    .donation-card { 
        background: #fff; padding: 40px; border-radius: 12px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid #f0f0f0; 
    }
    .form-header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
    .form-header h2 { font-size: 1.8rem; font-weight: 700; color: #333; margin-bottom: 5px; }

    /* Form Components */
    .type-group { display: flex; margin-bottom: 25px; border: 1px solid #dc2626; border-radius: 6px; overflow: hidden; }
    .type-option { flex: 1; text-align: center; padding: 12px; cursor: pointer; background: #fff; color: #dc2626; font-weight: bold; transition: 0.2s; }
    .type-option.active { background: #dc2626; color: #fff; }

    .amount-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
    .btn-amount { padding: 15px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; border-radius: 8px; font-size: 16px; font-weight: 600; transition: 0.2s; display: flex; align-items: center; justify-content: center; }
    .btn-amount:hover { border-color: #dc2626; color: #dc2626; }
    .btn-amount.selected { background: #dc2626; color: #fff; border-color: #dc2626; box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3); }

    .input-custom { width: 100%; padding: 15px; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 20px; font-size: 16px; transition: 0.3s; text-align: center;}
    .input-custom:focus { border-color: #dc2626; outline: none; }

    .tax-receipt-section { background-color: #fdf2f2; padding: 15px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #fecaca; display: flex; align-items: center; gap: 10px; }
    .checkbox-label { display: flex; align-items: center; cursor: pointer; font-weight: 600; color: #333; font-size: 1rem; margin: 0; }
    .checkbox-label input { width: 20px; height: 20px; margin-right: 10px; cursor: pointer; accent-color: #dc2626; }
    .tax-note { font-size: 13px; color: #666; margin-top:0; }

    .nav-buttons { display: flex; gap: 15px; margin-top: 20px; }
    .btn-nav { flex: 1; padding: 15px; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; border: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; transition: 0.2s; }
    .btn-prev { background: #e5e7eb; color: #374151; }
    .btn-next { background: #dc2626; color: white; box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4); }
    .btn-next:hover { background: #b91c1c; transform: translateY(-3px); }

    /* C区: Bottom Section */
    .bottom-section { background-color: #f8f9fa; border-top: 1px solid #eee; margin-top: 0; }
    .section-title { text-align: center; font-size: 2rem; font-weight: 700; color: #333; margin-bottom: 40px; }
    
    .other-card { 
        background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
        transition: 0.3s; height: 100%; display: flex; flex-direction: column; 
    }
    .other-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    .other-img-wrap { height: 220px; overflow: hidden; position: relative; }
    .other-img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
    .other-card:hover .other-img { transform: scale(1.1); }
    
    .other-content { padding: 25px; flex: 1; display: flex; flex-direction: column; }
    .other-title { font-size: 1.3rem; font-weight: 700; color: #333; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .other-desc { font-size: 0.95rem; color: #666; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex: 1; }
    
    .btn-view { 
        display: block; width: 100%; padding: 12px; border: 2px solid #dc2626; border-radius: 30px; 
        color: #dc2626; font-weight: bold; text-align: center; text-decoration: none; transition: 0.2s; 
    }
    .btn-view:hover { background: #dc2626; color: white; text-decoration: none; }
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
                            $percent = 0;
                            if($main_data['target'] > 0) {
                                $percent = ($main_data['raised'] / $main_data['target']) * 100;
                                if($percent > 100) $percent = 100;
                            }
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

                    <form id="donationForm" method="POST" action="S_C_Payment_Ways_Page.php">
                        
                        <input type="hidden" id="donation_type" name="donation_type" value="one-time">
                        <input type="hidden" id="amount" name="amount">
                        <input type="hidden" id="tax_receipt" name="tax_receipt" value="0">
                        
                        <?php if ($mode == 'activity'): ?>
                            <input type="hidden" name="activity_id" value="<?php echo $main_data['id']; ?>">
                            <input type="hidden" name="case_id" value="0">
                        <?php else: ?>
                            <input type="hidden" name="case_id" value="<?php echo $main_data['id']; ?>">
                            <input type="hidden" name="activity_id" value="0">
                        <?php endif; ?>

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
                            <span class="tax-note">(Min RM 30)</span>
                        </div>

                        <div class="nav-buttons">
                            <a href="Homepage.php" class="btn-nav btn-prev">Cancel</a>
                            <button type="submit" class="btn-nav btn-next">Next Step <i class="fas fa-arrow-right"></i></button>
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
                $other_percent = 0;
                if($row['target'] > 0) {
                    $other_percent = ($row['raised'] / $row['target']) * 100;
                    if($other_percent > 100) $other_percent = 100;
                }
                $img = !empty($row['image']) ? $row['image'] : 'images/default_donation.jpg';
                $link_param = ($mode == 'activity') ? "activity_id=" . $row['id'] : "case_id=" . $row['id'];
            ?>
                <div class="col-md-4 mb-4">
                    <div class="other-card">
                        <div class="other-img-wrap">
                            <img src="<?php echo htmlspecialchars($img); ?>" class="other-img" alt="Project">
                        </div>
                        <div class="other-content">
                            <h3 class="other-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                            
                            <div class="progress-bar-bg mb-2" style="height:6px;">
                                <div class="progress-bar-fill" style="width: <?php echo $other_percent; ?>%;"></div>
                            </div>
                            <div style="font-size:0.8rem; color:#888; margin-bottom:10px;">
                                RM <?php echo number_format($row['raised']); ?> raised
                            </div>

                            <p class="other-desc"><?php echo htmlspecialchars(substr($row['desc'], 0, 100)) . '...'; ?></p>
                            
                            <a href="S_C_Payment_Page.php?<?php echo $link_param; ?>" class="btn-view">
                                View Details
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

        // 恢复数据
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
            const btn = document.createElement('div'); // 使用 div 模拟按钮
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