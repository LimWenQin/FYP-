<?php
// 1. 引入必要的设置和头部文件 (这就包含了 Session 和 导航栏)
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI_2.php';

// 2. 特殊个案逻辑：获取从 URL 传来的 'case_id'
$case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;

if ($case_id == 0) {
    die("Error: Invalid Special Case ID. Please return to the previous page.");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Special Case Donation</title>
<style>
    /* ✅ 1. 使用与 Payment_Page 一样的颜色变量 */
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

    /* 这里的 .header 样式主要用于覆盖 header_UI_2 中的默认样式，使其变成渐变色 */
    .header 
    {
        display: flex; /* 注意：header_UI_2 可能是 grid，这里覆盖为 flex 可能会影响布局，建议保留背景色修改即可 */
        background: linear-gradient(180deg, var(--gradient-start) 0%, var(--gradient-middle) 50%, var(--gradient-end) 100%) !important;
        box-shadow: 0 4px 15px rgba(255, 107, 157, 0.2);
    }
    
    /* 强制修改 header_UI_2 中的文字颜色为白色以适配渐变背景 */
    .header .function-links a, 
    .header .logo {
        color: var(--white) !important;
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

    .donation-box .donation-type 
    {
        text-align: center;
        margin-bottom: 15px;
    }

    .donation-box .donation-type button 
    {
        background-color: #A8D5BA;
        border: none;
        border-radius: 25px;
        padding: 10px 20px;
        margin: 0 15px;
        font-weight: bold;
        color: #4A4A4A;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .donation-box .donation-type button.active 
    {
        background-color: #91C8A8;
    }

    .donation-box .donation-type button:hover 
    {
        background-color: #91C8A8;
    }

    .donation-box .amounts 
    {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin: 15px 0;
    }

    .donation-box .amounts button 
    {
        background-color: #A8D5BA;
        border: none;
        border-radius: 10px;
        padding: 10px;
        cursor: pointer;
        font-weight: bold;
        color: #4A4A4A;
        height: 50px;
    }

    .donation-box .amounts button:hover 
    {
        background-color: #91C8A8;
    }

    .donation-box input[type="text"] 
    {
        width: 95%;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #ccc;
        margin-bottom: 15px;
    }

    .donation-box .donate-btn 
    {
        width: 100%;
        padding: 12px;
        background-color: #F6B8B8;
        border: none;
        border-radius: 8px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    .donation-box .donate-btn:hover 
    {
        background-color: #F28585;
    }
</style>

<script>
// ✅ 保留原有的 JS 逻辑
function selectType(type) {
    document.getElementById("donation_type").value = type;

    document.getElementById("onceBtn").classList.remove("active");
    document.getElementById("monthlyBtn").classList.remove("active");

    if (type === "one-time") {
        document.getElementById("onceBtn").classList.add("active");
    } else {
        document.getElementById("monthlyBtn").classList.add("active");
    }
}

function selectAmount(amount) {
    document.getElementById("amount").value = amount;
    document.getElementById("custom_amount").value = ""; // 清空自定义金额
}

function beforeSubmit() {
    let selected = document.getElementById("amount").value;
    let custom = document.getElementById("custom_amount").value;

    let finalAmount = selected;

    if (custom !== "") {
        finalAmount = custom;
    }

    if (finalAmount === "" || isNaN(finalAmount) || finalAmount <= 0) {
        alert("Please enter or select a valid donation amount.");
        return false;
    }

    document.getElementById("amount").value = finalAmount;
    return true;
}
</script>

</head>
<body>

<div class="container">

    <div class="sidebar">
        <button><img src="yourimage.jpg" alt="Whatsapp"></button>
        <button><img src="yourimage.jpg" alt="Facebook"></button>
        <button><img src="yourimage.jpg" alt="Instagram"></button>
        <button><img src="yourimage.jpg" alt="General Line"></button>
    </div>

    <div class="story-section">
        <img src="yourimage.jpg" alt="Case Image">
        <h2>Donation Story (Case ID: <?php echo $case_id; ?>)</h2>
        <p>This is a special case donation description text. Your donation will directly support this specific case.</p>
    </div>

    <div class="donation-box">
        <h3>Types of donations</h3>

        <form id="donationForm" method="POST" action="S_C_Payment_Ways_Page.php" onsubmit="return beforeSubmit();">

            <div class="donation-type">
                <button type="button" id="onceBtn" class="active" onclick="selectType('one-time')">One-time</button>
                <button type="button" id="monthlyBtn" onclick="selectType('monthly')">Monthly</button>
            </div>

            <input type="hidden" id="donation_type" name="donation_type" value="one-time">
            <input type="hidden" id="amount" name="amount" value="">
            <input type="hidden" name="case_id" value="<?php echo $case_id; ?>">

            <div class="amounts">
                <button type="button" onclick="selectAmount(20)">RM20</button>
                <button type="button" onclick="selectAmount(50)">RM50</button>
                <button type="button" onclick="selectAmount(100)">RM100</button>
                <button type="button" onclick="selectAmount(200)">RM200</button>
                <button type="button" onclick="selectAmount(300)">RM300</button>
                <button type="button" onclick="selectAmount(500)">RM500</button>
            </div>

            <input type="text" id="custom_amount" placeholder="Enter custom amount">

            <button type="submit" class="donate-btn">Donate</button>

        </form>
    </div>

</div>

</body>
</html>