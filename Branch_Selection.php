<?php
// -------------------------
// ✅ 数据库连接
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
// ✅ 获取数据
// -------------------------
$amount = isset($_POST['amount']) ? $_POST['amount'] : 0;
$donation_type = isset($_POST['donation_type']) ? $_POST['donation_type'] : "One-time";

// -------------------------
// ✅ 关键修改：把数据存入数组
// -------------------------
$sql = "SELECT Branch_ID, Branch_Name, Branch_Type, Branch_Description FROM branch";
$result = $conn->query($sql);

$branches = []; // 准备一个空数组
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $branches[] = $row; // 把每一行数据存进去，备用
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Branch Selection</title>
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

    .branch-type 
    {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-top: 20px;
    }

    .branch-card 
    {
        background-color: #A8D5BA;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .branch-card h4 
    {
        margin: 10px 0 5px;
        color: #4A4A4A;
    }

    .branch-card p 
    {
        font-size: 14px;
    }

    .B_btn 
    {
        background-color: #91C8A8;
        border: none;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
        color: #4A4A4A;
        margin-top: 10px;
        width: 100%;
    }

    .B_btn:hover 
    {
        background-color: #7CBF99;
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
    <div class="sidebar">
        <button><img src="yourimage.jpg" alt="Whatsapp"></button>
        <button><img src="yourimage.jpg" alt="Facebook"></button>
        <button><img src="yourimage.jpg" alt="Instagram"></button>
        <button><img src="yourimage.jpg" alt="General Line"></button>
    </div>

    <div class="story-section">
        <h2>选择您希望支持的分行</h2>
        <p>每一间分行都在为不同的群体提供帮助。以下是详细介绍：</p>
        <hr style="border: 1px solid #eee; margin: 20px 0;">

        <?php if (!empty($branches)): ?>
            <?php foreach ($branches as $row): ?>
                <div style="margin-bottom: 30px;">
                    <img src="yourimage.jpg" alt="Branch Story"> 
                    
                    <h3 style="color: #4A4A4A;"><?php echo $row['Branch_Name']; ?></h3>
                    
                    <p style="line-height: 1.6;"><?php echo $row['Branch_Description']; ?></p>
                    
                    <hr style="border: 1px dashed #ccc; margin-top: 20px;">
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>暂无分行资料。</p>
        <?php endif; ?>
    </div>

    <div class="donation-box">
        <h3>Available Branches</h3>
        <div class="branch-type">

        <?php if (!empty($branches)): ?>
            <?php foreach ($branches as $row): ?>
                <div class='branch-card'>
                    <h4><?php echo $row['Branch_Name']; ?></h4>
                    <p>Type: <?php echo $row['Branch_Type']; ?></p>
                    
                    <form method='POST' action='Payment_Ways_Page.php'>
                        <input type='hidden' name='branch_id' value='<?php echo $row['Branch_ID']; ?>'>
                        <input type='hidden' name='amount' value='<?php echo $amount; ?>'>
                        <input type='hidden' name='donation_type' value='<?php echo $donation_type; ?>'>
                        <button type='submit' class='B_btn'>Select</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>

<?php $conn->close(); ?>