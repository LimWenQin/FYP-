<?php
// Branch_Selection.php
session_start();
include 'dataconnection.php';

// 登录检查
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

// ==========================================
// [NEW] 1. 接收上一页数据并存入 Session
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 只有当是从第一页提交过来的时候，才更新 Session
    // 这样防止“刷新页面”导致数据丢失
    $_SESSION['donation_data'] = [
        'amount' => $_POST['amount'],
        'type' => $_POST['donation_type'],
        'tax_receipt' => $_POST['tax_receipt'],
        // 同时也存下 Step 1 选的 target (如果有)
        'case_id' => $_POST['case_id'] ?? '',
        'activity_id' => $_POST['activity_id'] ?? '',
        // 初始化 branch_id (如果还没选)
        'branch_id' => $_SESSION['donation_data']['branch_id'] ?? ''
    ];
}

// 安全检查：如果没有 Session 数据，踢回第一步
if (empty($_SESSION['donation_data'])) {
    header("Location: Donate_Page.php");
    exit();
}

// 从 Session 获取数据用于显示
$amount = $_SESSION['donation_data']['amount'];
$donation_type = $_SESSION['donation_data']['type'];
$selected_case_id = $_SESSION['donation_data']['case_id'];
$selected_activity_id = $_SESSION['donation_data']['activity_id'];
$pre_selected_branch = $_SESSION['donation_data']['branch_id'];

// 查询所有分行
$sql = "SELECT Branch_ID, Branch_Name, Branch_Type, Branch_Description FROM branch";
$result = $conn->query($sql);
$branches = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $branches[] = $row;
    }
}

include 'header_UI.php'; 
?>

<style>
    /* Hero Banner */
    .hero-wrap {
        height: 350px;
        position: relative;
        background-image: url('images/hero_2.jpg');
        background-size: cover;
        background-position: center;
        display: flex; align-items: center; justify-content: center; text-align: center;
    }
    .hero-wrap .overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); }
    .hero-content { position: relative; z-index: 2; max-width: 800px; }
    .hero-content h1 { font-family: 'Segoe UI', sans-serif; color: #fff; font-size: 3.5rem; margin-bottom: 10px; }
    .hero-content p { font-size: 1.2rem; color: rgba(255, 255, 255, 0.9); }

    /* Selection Card Style (Radio Button Replacement) */
    .branch-option {
        display: block;
        position: relative;
        cursor: pointer;
        margin-bottom: 20px;
    }
    
    /* 隐藏真正的 Radio Input */
    .branch-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }

    /* 卡片外观 */
    .branch-card {
        background: #fff;
        border-radius: 10px;
        padding: 25px;
        border: 2px solid #eee;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }

    /* 选中状态 */
    .branch-option input[type="radio"]:checked + .branch-card {
        border-color: #dc2626;
        background-color: #fff5f5;
        box-shadow: 0 8px 15px rgba(220, 38, 38, 0.1);
        transform: translateY(-2px);
    }
    
    /* 选中时的圆圈标记 */
    .check-circle {
        width: 24px; height: 24px;
        border: 2px solid #ccc;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .check-circle::after {
        content: ''; width: 12px; height: 12px;
        background: #dc2626; border-radius: 50%;
        display: none;
    }
    .branch-option input[type="radio"]:checked + .branch-card .check-circle {
        border-color: #dc2626;
    }
    .branch-option input[type="radio"]:checked + .branch-card .check-circle::after {
        display: block;
    }

    .b-info h4 { margin: 0 0 5px 0; font-weight: 700; color: #333; }
    .b-info p { margin: 0; font-size: 0.9rem; color: #666; }
    .badge-type { background: #eee; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; color: #555; font-weight: 600; }

    /* Navigation Buttons */
    .nav-buttons { display: flex; gap: 20px; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }
    .btn-nav { flex: 1; padding: 15px; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; border: none; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.2s; }
    
    .btn-prev { background: #e5e7eb; color: #374151; border: 1px solid #d1d5db; }
    .btn-prev:hover { background: #d1d5db; color: #111; text-decoration: none; }
    
    .btn-next { background: #dc2626; color: white; }
    .btn-next:hover { background: #b91c1c; color: white; text-decoration: none; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }

    .info-alert { background: #e0f2fe; color: #0369a1; padding: 15px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #0ea5e9; display: flex; align-items: center; gap: 10px; }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Select Beneficiary</h1>
        <p>Choose where your donation goes.</p>
    </div>
</div>

<?php 
    $current_step = 2; 
    $flow_type = 'standard'; 
    include 'stepper.php'; 
?>

<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row justify-content-center">
            
            <div class="col-lg-8">
                
                <?php if(!empty($selected_case_id)): ?>
                    <div class="info-alert">
                        <i class="fas fa-info-circle fa-lg"></i>
                        <div>
                            <strong>Specific Case Selected:</strong> Since you selected a specific case, your donation will be directed to our <strong>General Fund</strong> for processing, but tagged for that case.
                        </div>
                    </div>
                <?php elseif(!empty($selected_activity_id)): ?>
                    <div class="info-alert">
                        <i class="fas fa-info-circle fa-lg"></i>
                        <div>
                            <strong>Activity Selected:</strong> Your donation supports a specific activity. Please select the organizing branch or General Fund.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mb-5 text-center">
                        <h3 class="text-cursive mb-3" style="color: #dc2626;">Choose a Branch</h3>
                        <p class="text-muted">Select a specific branch to support their local efforts, or choose the General Fund.</p>
                    </div>
                <?php endif; ?>

                <form action="Payment_Ways_Page.php" method="POST" id="branchForm">

                    <label class="branch-option">
                        <input type="radio" name="branch_id" value="" 
                            <?php echo ($pre_selected_branch == '') ? 'checked' : ''; ?>>
                        
                        <div class="branch-card">
                            <div class="check-circle"></div>
                            <div class="b-info">
                                <h4>General Fund (HQ)</h4>
                                <p>Funds will be allocated to where they are needed most.</p>
                            </div>
                        </div>
                    </label>

                    <?php if (!empty($branches)): ?>
                        <?php foreach ($branches as $row): ?>
                            <label class="branch-option">
                                <input type="radio" name="branch_id" value="<?php echo $row['Branch_ID']; ?>"
                                    <?php echo ($pre_selected_branch == $row['Branch_ID']) ? 'checked' : ''; ?>>
                                
                                <div class="branch-card">
                                    <div class="check-circle"></div>
                                    <div class="b-info">
                                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:5px;">
                                            <h4><?php echo htmlspecialchars($row['Branch_Name']); ?></h4>
                                            <span class="badge-type"><?php echo htmlspecialchars($row['Branch_Type']); ?></span>
                                        </div>
                                        <p><?php echo nl2br(htmlspecialchars($row['Branch_Description'])); ?></p>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="nav-buttons">
                        <a href="Payment_Page.php" class="btn-nav btn-prev">
                            <i class="fas fa-arrow-left"></i> Previous
                        </a>

                        <button type="submit" class="btn-nav btn-next">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>