<?php
// 1. PHP 后端逻辑
include 'dataconnection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 登录检查 (根据需要取消注释)
if (!isset($_SESSION['donor_id'])) {
   echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
   exit();
}


// 2. 引入头部模板 (这里会自动加载导航栏、CSS 和 <body>)
include 'header_UI.php'; 
?>

<style>
    /* --- Hero Banner (大图背景) --- */
    .hero {
        height: 400px;
        background-color: #333;
        background-image: url('images/hero_1.jpg'); /* 确保有这张图 */
        background-size: cover;
        background-position: center;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .hero-overlay {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
    }
    .hero-text {
        position: relative;
        color: #fff;
        z-index: 1;
    }
    .hero-text h1 { font-size: 48px; color: #fff; margin-bottom: 10px; font-family: "Mansalva", cursive; }
    .hero-text p { font-size: 18px; color: #eee; }

    /* --- 主要内容布局 --- */
    .content-section {
        padding: 50px 0;
        display: flex;
        gap: 40px;
        flex-wrap: wrap;
    }
    .col-left, .col-right {
        flex: 1;
        min-width: 300px;
    }
    
    /* 左侧故事 */
    .story-img {
        width: 100%;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .text-cursive { font-family: "Mansalva", cursive; color: #00a651; }

    /* 右侧表单盒子 */
    .donation-card {
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border: 1px solid #eee;
    }

    /* 捐款类型切换 */
    .type-group {
        display: flex;
        margin-bottom: 20px;
        border: 1px solid #00a651;
        border-radius: 5px;
        overflow: hidden;
    }
    .type-option {
        flex: 1;
        text-align: center;
        padding: 10px;
        cursor: pointer;
        background: #fff;
        color: #00a651;
        font-weight: bold;
    }
    .type-option.active {
        background: #00a651;
        color: #fff;
    }

    /* 金额网格 */
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
    .btn-amount:active { background: #00a651; color: #fff; }

    /* 输入框 */
    .input-custom {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        margin-bottom: 20px;
        font-size: 16px;
    }

    /* 大按钮 */
    .btn-submit {
        width: 100%;
        padding: 15px;
        background: #00a651;
        color: #fff;
        font-size: 18px;
        font-weight: bold;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-transform: uppercase;
    }
    .btn-submit:hover { background: #008f45; }
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
</script>

<div class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-text">
        <h1>Make a Difference</h1>
        <p>Your support helps us build a better future for those in need.</p>
    </div>
</div>

<div class="container">
    <div class="content-section">
        
        <div class="col-left">
            <img src="yourimage.jpg" alt="Donation Story" class="story-img">
            <h2 class="text-cursive">Why Donate?</h2>
            <p>This is background information on the donation drive, such as helping to improve living conditions in senior citizen communities. Donations will be used to purchase daily necessities and medicines.</p>
            <p>Your contribution makes a direct impact on the lives of the elderly and orphans we support. Every penny counts towards building a bridge of love.</p>
        </div>

        <div class="col-right">
            <div class="donation-card">
                <h2 style="text-align:center;">Choose Amount</h2>

                <form id="donationForm" method="POST" action="Branch_Selection.php">
                    
                    <div class="type-group">
                        <div class="type-option active" id="btn-once" onclick="selectType('one-time')">One-time</div>
                        <div class="type-option" id="btn-monthly" onclick="selectType('monthly')">Monthly</div>
                    </div>

                    <input type="hidden" id="donation_type" name="donation_type" value="one-time">
                    <input type="hidden" id="amount" name="amount">

                    <div class="amount-grid">
                        <button type="button" class="btn-amount" onclick="selectAmount(20)">RM 20</button>
                        <button type="button" class="btn-amount" onclick="selectAmount(50)">RM 50</button>
                        <button type="button" class="btn-amount" onclick="selectAmount(100)">RM 100</button>
                        <button type="button" class="btn-amount" onclick="selectAmount(200)">RM 200</button>
                        <button type="button" class="btn-amount" onclick="selectAmount(300)">RM 300</button>
                        <button type="button" class="btn-amount" onclick="selectAmount(500)">RM 500</button>
                    </div>

                    <input type="number" id="custom_amount" name="custom_amount" class="input-custom" placeholder="Enter custom amount (RM)">

                    <button type="submit" class="btn-submit" onclick="
                        document.getElementById('amount').value = 
                        document.getElementById('custom_amount').value || 0;
                    ">Donate Now</button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>