<?php
// -------------------------
// 接收 POST 数据
// -------------------------
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$donation_type = isset($_POST['donation_type']) ? $_POST['donation_type'] : "one-time";
$case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;

// -------------------------
// 数据验证（防止错误跳转）
// -------------------------
if ($amount <= 0 || $case_id <= 0) {
    die("错误：捐款金额或特殊个案ID无效。请返回重试。");
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Payment Ways</title>

<style>
body {
    background-color: #FFF5E4;
    margin: 0;
    font-family: Arial;
    color: #4A4A4A;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #F6B8B8;
    padding: 20px 50px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.logo {
    display: flex;
    align-items: center;
    font-weight: bold;
    font-size: 36px;
    gap: 10px;
}

.header .function-links a {
    margin-left: 15px;
    text-decoration: none;
    font-size: 20px;
    color: #4A4A4A;
    font-weight: bold;
}

.container {
    display: flex;
    flex-direction: row;
    padding: 20px;
}

.sidebar {
    width: 60px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    margin-right: 20px;
    padding-top: 20px;
}

.sidebar button {
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

.story-section {
    flex: 2;
    background-color: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.story-section h2 {
    color: #F28585;
}

.donation-box {
    flex: 1;
    background-color: white;
    border-radius: 12px;
    margin-left: 20px;
    padding: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.donation-box h3 {
    text-align: center;
    color: #F28585;
}

.Payment-Ways {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin: 15px 15px;
}

.P_btn {
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

.P_btn:hover {
    background-color: #91C8A8;
}

.Payment-Ways img {
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
        <img src="logo.jpg" width="80" height="80">
        LOVE BRIDGE
    </div>
    <div class="function-links">
        <a href="#">Home</a>
        <a href="#">About Us</a>
        <a href="#">Contact Us</a>
        <a href="#">Activities</a>
    </div>
</header>

<div class="container">

    <div class="sidebar">
        <button><img src="yourimage.jpg"></button>
        <button><img src="yourimage.jpg"></button>
        <button><img src="yourimage.jpg"></button>
        <button><img src="yourimage.jpg"></button>
    </div>

    <div class="story-section">
        <h2>选择您的付款方式</h2>
        <p>请选择一种您偏好的付款方式来完成捐款。</p>

        <h3>当前捐款详情：</h3>
        <ul>
            <li><strong>捐款金额：</strong> RM <?php echo htmlspecialchars($amount); ?></li>
            <li><strong>捐款类型：</strong> <?php echo htmlspecialchars($donation_type); ?></li>
            <li><strong>特殊个案编号：</strong> <?php echo htmlspecialchars($case_id); ?></li>
        </ul>
    </div>

    <div class="donation-box">
        <h3>Payment Ways</h3>

        <div class="Payment-Ways">

            <img src="bank.jpg">
            <img src="tng.jpg">

            <!-- Bank Transfer -->
            <form method="POST" action="Bank_Transfer_Page.php">
                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                <input type="hidden" name="donation_type" value="<?php echo htmlspecialchars($donation_type); ?>">
                <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">
                <button type="submit" class="P_btn">Bank Transfer</button>
            </form>

            <!-- TNG -->
            <form method="POST" action="TNG_Page.php">
                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                <input type="hidden" name="donation_type" value="<?php echo htmlspecialchars($donation_type); ?>">
                <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">
                <button type="submit" class="P_btn">TNG</button>
            </form>

        </div>
    </div>
</div>

</body>
</html>
