<?php
// 1. 引入数据库连接
include 'dataconnection.php';

// 2. 开启 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. 强制登录检查
if (!isset($_SESSION['donor_id'])) {
   echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
   exit();
}

// 4. 获取 Case ID
$case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;

if ($case_id == 0) {
    echo "<script>alert('Invalid Case ID.'); window.history.back();</script>";
    exit();
}

// 5. 查询 Case 详情 (为了显示名字)
$case_title = "Special Case #" . $case_id; // 默认标题
$case_desc = "Your donation will directly support this specific case."; // 默认描述

// 尝试从数据库获取真实标题
$sql = "SELECT Case_Title, Case_Description FROM special_case WHERE Case_ID = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $case_title = $row['Case_Title'];
        // 如果有描述也可以获取，或者只用默认的通用描述
        // $case_desc = $row['Case_Description']; 
    }
    $stmt->close();
}

// 6. 引入新版头部
include 'header_UI.php'; 
?>

<style>
    /* --- Hero Banner --- */
    .hero-wrap {
        height: 400px;
        position: relative;
        background-image: url('images/hero_5.jpg'); /* 确保有这张图 */
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .hero-wrap .overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.5);
    }
    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 800px;
    }
    .hero-content h1 {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #fff;
        font-size: 4rem;
        margin-bottom: 10px;
    }
    .hero-content p { font-size: 1.2rem; color: rgba(255, 255, 255, 0.9); }

    /* --- 捐款卡片 --- */
    .donation-card {
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border: 1px solid #eee;
    }

    /* 类型切换按钮 */
    .type-group {
        display: flex;
        margin-bottom: 20px;
        border: 1px solid #dc2626;
        border-radius: 5px;
        overflow: hidden;
    }
    .type-option {
        flex: 1;
        text-align: center;
        padding: 10px;
        cursor: pointer;
        background: #fff;
        color: #dc2626;
        font-weight: bold;
    }
    .type-option.active {
        background: #dc2626;
        color: #fff;
    }

    /* 金额按钮网格 */
    .amount-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 15px;
    }
    .btn-amount {
        padding: 12px;
        border: 1px solid #ddd;
        background: #fff;
        cursor: pointer;
        border-radius: 4px;
        font-size: 14px;
        transition: 0.2s;
    }
    .btn-amount:hover { background: #f0f0f0; }
    .btn-amount:active { background: #dc2626; color: #fff; }

    /* 输入框 */
    .input-custom {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        margin-bottom: 20px;
        font-size: 16px;
    }

    /* 提交按钮 */
    .btn-submit {
        width: 100%;
        padding: 15px;
        background: #dc2626;
        color: #fff;
        font-size: 18px;
        font-weight: bold;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-transform: uppercase;
        transition: 0.3s;
    }
    .btn-submit:hover { background: #b91c1c; }
</style>

<script>
    function selectType(type) {
        document.getElementById("donation_type").value = type;
        document.getElementById('btn-once').classList.remove('active');
        document.getElementById('btn-monthly').classList.remove('active');
        if(type === 'one-time') {
            document.getElementById('btn-once').classList.add('active');
        } else {
            document.getElementById('btn-monthly').classList.add('active');
        }
    }
    function selectAmount(amount) {
        document.getElementById("amount").value = amount;
        document.getElementById("custom_amount").value = amount;
    }
    
    // 表单验证
    function beforeSubmit() {
        let selected = document.getElementById("amount").value;
        let custom = document.getElementById("custom_amount").value;
        let finalAmount = custom || selected;

        if (!finalAmount || finalAmount <= 0) {
            alert("Please select or enter a valid amount.");
            return false;
        }
        document.getElementById("amount").value = finalAmount;
        return true;
    }
</script>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Special Case Donation</h1>
        <p>Your support provides direct help to specific needs.</p>
    </div>
</div>

<?php 
    $current_step = 1; 
    $flow_type = 'special'; 
    include 'stepper.php'; 
?>
<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row">
            
            <div class="col-md-6 mb-5">
                <img src="yourimage.jpg" alt="Case Image" class="img-fluid rounded mb-4 shadow-sm">
                
                <h3 class="text-cursive mb-4" style="color: #dc2626;">Supporting:</h3>
                <h2 class="text-black mb-4"><?php echo htmlspecialchars($case_title); ?></h2>
                
                <p class="lead"><?php echo htmlspecialchars($case_desc); ?></p>
                <p class="text-muted">This donation is designated for a special case. 100% of your contribution goes directly towards the relief efforts for this specific cause.</p>
            </div>

            <div class="col-md-6">
                <div class="donation-card">
                    <h3 class="text-cursive text-black text-center mb-4">Choose Amount</h3>

                    <form id="donationForm" method="POST" action="S_C_Payment_Ways_Page.php" onsubmit="return beforeSubmit();">
                        
                        <div class="type-group">
                            <div class="type-option active" id="btn-once" onclick="selectType('one-time')">One-time</div>
                            <div class="type-option" id="btn-monthly" onclick="selectType('monthly')">Monthly</div>
                        </div>

                        <input type="hidden" id="donation_type" name="donation_type" value="one-time">
                        <input type="hidden" id="amount" name="amount" value="">
                        <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">

                        <div class="amount-grid">
                            <button type="button" class="btn-amount" onclick="selectAmount(20)">RM 20</button>
                            <button type="button" class="btn-amount" onclick="selectAmount(50)">RM 50</button>
                            <button type="button" class="btn-amount" onclick="selectAmount(100)">RM 100</button>
                            <button type="button" class="btn-amount" onclick="selectAmount(200)">RM 200</button>
                            <button type="button" class="btn-amount" onclick="selectAmount(300)">RM 300</button>
                            <button type="button" class="btn-amount" onclick="selectAmount(500)">RM 500</button>
                        </div>

                        <input type="number" id="custom_amount" class="input-custom" placeholder="Enter custom amount (RM)">

                        <button type="submit" class="btn-submit">Donate Now</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>