<?php
// ✅ 这个页面只是收集用户选择，还没有连数据库
// ✅ 当用户点击金额或“捐款”按钮时，把选择传到 BranchSelectionPage.php
?>

<!DOCTYPE html>
<html>
<head>
<title>Donation Page</title>
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
    .logo { font-weight: bold; font-size: 36px; }
    .header .function-links a {
        margin-left: 15px;
        text-decoration: none;
        font-size: 20px;
        color: #4A4A4A;
        font-weight: bold;
    }
    .search { padding: 6px; font-size: 16px; }

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
    .story-section img {
        width: 100%;
        border-radius: 10px;
        margin-bottom: 15px;
    }
    .story-section h2 { color: #F28585; }

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

    .donation-box .donation-type {
        text-align: center;
        margin-bottom: 15px;
    }

    .donation-box .donation-type button {
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

    .donation-box .donation-type button.active {
        background-color: #91C8A8;
    }

    .donation-box .donation-type button:hover {
        background-color: #91C8A8;
    }

    .donation-box .amounts {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin: 15px 0;
    }

    .donation-box .amounts button {
        background-color: #A8D5BA;
        border: none;
        border-radius: 10px;
        padding: 10px;
        cursor: pointer;
        font-weight: bold;
        color: #4A4A4A;
        height: 50px;
    }

    .donation-box .amounts button:hover {
        background-color: #91C8A8;
    }

    .donation-box input[type="text"] {
        width: 95%;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #ccc;
        margin-bottom: 15px;
    }

    .donation-box .donate-btn {
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

    .donation-box .donate-btn:hover {
        background-color: #F28585;
    }
</style>

<script>
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
    document.getElementById("donationForm").submit();
}
</script>

</head>
<body>

<header class="header">
    <div class="logo">Brand Name</div>
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
        <img src="yourimage.jpg" alt="Donation Story">
        <h2>Donation Story Title</h2>
        <p>This is background information on the donation drive, such as helping to improve living conditions in senior citizen communities. Donations will be used to purchase daily necessities and medicines.</p>
    </div>

    <div class="donation-box">
        <h3>Types of donations</h3>

        <form id="donationForm" method="POST" action="Branch_Selection.php">
            <div class="donation-type">
                <button type="button" id="onceBtn" onclick="selectType('one-time')">One-time</button>
                <button type="button" id="monthlyBtn" onclick="selectType('monthly')">Monthly</button>
            </div>

            <input type="hidden" id="donation_type" name="donation_type" value="one-time">
            <input type="hidden" id="amount" name="amount">

            <div class="amounts">
                <button type="button" onclick="selectAmount(20)">RM20</button>
                <button type="button" onclick="selectAmount(50)">RM50</button>
                <button type="button" onclick="selectAmount(100)">RM100</button>
                <button type="button" onclick="selectAmount(200)">RM200</button>
                <button type="button" onclick="selectAmount(300)">RM300</button>
                <button type="button" onclick="selectAmount(500)">RM500</button>
            </div>

            <input type="text" id="custom_amount" name="custom_amount" placeholder="Enter custom amount">

            <button type="submit" class="donate-btn" onclick="
                document.getElementById('amount').value = 
                document.getElementById('custom_amount').value || 0;
            ">Donate</button>
        </form>
    </div>
</div>

</body>
</html>
