<?php
// -------------------------
// ✅ 1. 数据库连接 (必须连接才能查询名字)
// -------------------------
$servername = "localhost";
$username = "root";
$password = "";
$database = "donation_system";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// -------------------------
// ✅ 2. 获取上一个页面传来的数据
// -------------------------
$amount = isset($_POST['amount']) ? $_POST['amount'] : 0;
$donation_type = isset($_POST['donation_type']) ? $_POST['donation_type'] : "One-time";
$branch_id = isset($_POST['branch_id']) ? $_POST['branch_id'] : 0;

// -------------------------
// ✅ 3. [关键步骤] 使用 ID 去数据库查询分行名字
// -------------------------
$branch_name = "Unknown Branch"; // 设置默认值

if ($branch_id > 0) {
    // 准备 SQL 语句查找名字
    $stmt = $conn->prepare("SELECT Branch_Name FROM branch WHERE Branch_ID = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $branch_name = $row['Branch_Name']; // ✅ 成功获取名字！
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Payment Ways</title>
<style>

    /* 1. 添加颜色变量定义 */
    :root 
    {
        --gradient-start: #ff6b9d;
        --gradient-middle: #ff8fab;
        --gradient-end: #ffb3c6;
        --white: #ffffff;
    }

    body 
    {
        background-color: #FFF5E4;
        margin: 0;
        font-family: Arial;
        color: #4A4A4A;
    }

    .header 
    {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 50px;
        /*应用渐变色背景 --- */
        background: linear-gradient(180deg, var(--gradient-start) 0%, var(--gradient-middle) 50%, var(--gradient-end) 100%);
        box-shadow: 0 4px 15px rgba(255, 107, 157, 0.2); /* 新的阴影 */
        color: #fcfcfcff; /* 为了在渐变色上清晰显示，文字改为白色 */
    }

    .logo 
    {
        display: flex;          /*变成弹性盒子Flexbox，自动变成一行排列*/
        align-items: center;    /* 垂直居中 */
        font-weight: bold;      /*粗体*/
        font-size: 36px;        /*字体大小*/
        gap: 10px;              /* 图片与文字之间的间距 */
    }

    .header .function-links a 
    {
        margin-left: 15px;
        text-decoration: none;
        font-size: 20px;
        color: #fcfcfcff;
        font-weight: bold;
    }

    .search 
    { 
        padding: 6px; font-size: 16px; 
    }

    .container 
    {
        display: flex;
        flex-direction: row;
        padding: 20px;
    }

    .sidebar 
    {
        width: 60px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        margin-right: 20px;
        padding-top: 20px;
    }

    .sidebar button 
    {
        background-color: #A8D5BA;
        border: none;
        border-radius: 10px;
        padding: 8px;
        width: 50px;
        height: 50px;
        cursor: pointer;
        font-weight: bold;
        color: #4A4A4A;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .story-section 
    {
        flex: 2;
        background-color: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    .story-section img 
    {
        width: 100%;
        border-radius: 10px;
        margin-bottom: 15px;
    }

    .story-section h2 
    {
        color: #F28585;
    }

    .donation-box 
    {
        flex: 1;
        background-color: white;
        border-radius: 12px;
        margin-left: 20px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    .donation-box h3 
    {
        text-align: center;
        color: #F28585;
    }

    .donation-box .Payment-Ways 
    {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin: 15px 15px;
    }

    .donation-box .Payment-Ways .P_btn 
    {
        background-color: #A8D5BA;
        border: none;
        border-radius: 10px;
        padding: 10px;
        cursor: pointer;
        font-weight: bold;
        color: #4A4A4A;
        width: 100%;
        height: 50px;
    }

    .donation-box .Payment-Ways .P_btn:hover 
    {
        background-color: #91C8A8;
    }

    .donation-box .Payment-Ways img 
    {
        width: 100%;
        border-radius: 10px;
        margin-bottom: 15px;
        margin-top: 20px;
    }

</style>
</head>
<body>

<header class="header">
    <div class="logo">
        <img src="logo.jpg" alt="Logo" width="80" height="80">
        LOVE BRIDGE
    </div>
    <div class="function-links">
        <input type="text" placeholder="Search..." class="search">
        <a href="#">Home</a>
        <a href="#">About Us</a>
        <a href="#">Contact Us</a>
        <a href="#">History</a>
        <a href="#">Activities</a>
        <a href="#">News & Stories</a>
    </div>
</header>

<div class="container">

    <!-- 左侧社交栏 -->
    <div class="sidebar">
        <button><img src="yourimage.jpg" alt="Whatsapp"></button>
        <button><img src="yourimage.jpg" alt="Facebook"></button>
        <button><img src="yourimage.jpg" alt="Instagram"></button>
        <button><img src="yourimage.jpg" alt="General Line"></button>
    </div>

    <!-- 中间故事区 -->
    <div class="story-section">
        <h2>选择您的付款方式</h2>
        <p>请选择一种您偏好的付款方式来完成捐款。我们支持银行转账和电子钱包。</p>

        <h3>当前捐款详情：</h3>
        <ul>
            <li><strong>捐款金额：</strong> RM <?php echo htmlspecialchars($amount); ?></li>
            <li><strong>捐款类型：</strong> <?php echo htmlspecialchars($donation_type); ?></li>
            <li><strong>分行名称：</strong> <?php echo htmlspecialchars($branch_name); ?></li>
        </ul>
    </div>

    <!-- 右侧付款选项 -->
    <div class="donation-box">
        <h3>Payment Ways</h3>
        <div class="Payment-Ways">

            <img src="bank.jpg" alt="Bank Transfer">
            <img src="tng.jpg" alt="E-Wallet">

            <!-- ✅ Bank Transfer -->
            <form method="POST" action="Credit_Debit_Page.php">
                <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                <input type="hidden" name="donation_type" value="<?php echo $donation_type; ?>">
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                <button type="submit" class="P_btn">Bank Transfer</button>
            </form>

            <!-- ✅ TNG -->
            <form method="POST" action="TNG_Page.php">
                <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                <input type="hidden" name="donation_type" value="<?php echo $donation_type; ?>">
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                <button type="submit" class="P_btn">TNG</button>
            </form>

        </div>
    </div>
</div>

</body>
</html>
