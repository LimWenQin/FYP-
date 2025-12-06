<?php
// 1. 引入数据库连接
include 'dataconnection.php';

// 2. 开启 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. 强制登录检查 (保留您的逻辑)
//if (!isset($_SESSION['donor_id'])) {
//   echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
//   exit();
//}

// 4. 获取上页传来的数据 (金额 & 类型)
$amount = isset($_POST['amount']) ? $_POST['amount'] : 0;
$donation_type = isset($_POST['donation_type']) ? $_POST['donation_type'] : "One-time";

// 5. 查询分行数据 (存入数组)
$sql = "SELECT Branch_ID, Branch_Name, Branch_Type, Branch_Description FROM branch";
$result = $conn->query($sql);
$branches = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $branches[] = $row;
    }
}

// 6. 引入新版头部 (包含 CSS 和 导航栏)
include 'header_UI_template.php'; 
?>

<style>
    /* --- Hero Banner (顶部大图) --- */
    .hero-wrap {
        height: 400px;
        position: relative;
        background-image: url('images/hero_1.jpg'); /* 确保有这张图 */
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
        font-family: "Mansalva", cursive;
        color: #fff;
        font-size: 4rem;
        margin-bottom: 10px;
    }
    .hero-content p {
        font-size: 1.2rem;
        color: rgba(255, 255, 255, 0.9);
    }

    /* --- 分行卡片样式 --- */
    .branch-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #eee;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        margin-bottom: 20px;
        padding: 20px;
        text-align: center;
    }
    .branch-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        border-color: #00a651; /* 悬停时变绿 */
    }
    .branch-card h4 {
        font-weight: bold;
        color: #333;
        margin-bottom: 5px;
    }
    .branch-card .badge {
        font-size: 0.8rem;
        padding: 5px 10px;
        margin-bottom: 15px;
    }
    
    /* 选择按钮 */
    .btn-select {
        background-color: #00a651;
        color: #fff;
        border-radius: 30px;
        padding: 8px 25px;
        border: none;
        font-weight: bold;
        transition: 0.3s;
        width: 100%;
    }
    .btn-select:hover {
        background-color: #008f45;
        color: #fff;
    }
</style>

<div class="hero-wrap">
    <div class="overlay"></div>
    <div class="hero-content">
        <h1>Select a Branch</h1>
        <p>Choose a branch you wish to support. Your donation will make a difference locally.</p>
    </div>
</div>

<div class="site-section" style="padding: 5em 0;">
    <div class="container">
        <div class="row">
            
            <div class="col-lg-7 mb-5">
                <div class="mb-5">
                    <h3 class="text-cursive mb-4" style="color: #00a651;">Our Branches</h3>
                    <p class="text-muted">Each branch provides assistance to different groups. Below are the details of our locations and their specific missions.</p>
                </div>

                <?php if (!empty($branches)): ?>
                    <?php foreach ($branches as $index => $row): ?>
                        <div class="d-flex mb-5 border-bottom pb-5">
                            <div class="mr-4" style="flex: 0 0 150px;">
                                <img src="yourimage.jpg" alt="Branch Image" class="img-fluid rounded shadow-sm">
                            </div>
                            <div>
                                <h3 class="h4 text-black mb-3"><?php echo htmlspecialchars($row['Branch_Name']); ?></h3>
                                <span class="badge badge-info mb-3"><?php echo htmlspecialchars($row['Branch_Type']); ?></span>
                                <p><?php echo nl2br(htmlspecialchars($row['Branch_Description'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No branch information available at the moment.</p>
                <?php endif; ?>
            </div>

            <div class="col-lg-5">
                <div class="p-4 bg-light border rounded" style="position: sticky; top: 100px;">
                    <h3 class="text-cursive text-black mb-4 text-center">Select to Donate</h3>
                    
                    <?php if (!empty($branches)): ?>
                        <div class="row">
                            <?php foreach ($branches as $row): ?>
                                <div class="col-md-12"> <div class="branch-card bg-white">
                                        <h4><?php echo htmlspecialchars($row['Branch_Name']); ?></h4>
                                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($row['Branch_Type']); ?></p>
                                        
                                        <form method="POST" action="Payment_Ways_Page.php">
                                            <input type="hidden" name="branch_id" value="<?php echo $row['Branch_ID']; ?>">
                                            <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                                            <input type="hidden" name="donation_type" value="<?php echo htmlspecialchars($donation_type); ?>">
                                            
                                            <button type="submit" class="btn-select">Select This Branch</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer_UI_template.php'; ?>
<?php $conn->close(); ?>