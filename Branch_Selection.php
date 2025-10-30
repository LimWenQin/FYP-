<?php
// -------------------------
// ✅ 数据库连接
// -------------------------
$servername = "localhost";
$username = "root";
$password = "";
$database = "fyp_test";

$conn = new mysqli($servername, $username, $password, $database);

// 检查连接是否成功
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// -------------------------
// ✅ 获取从 Donation Page 传来的数据
// -------------------------
$amount = isset($_GET['amount']) ? $_GET['amount'] : 0;
$donation_type = isset($_GET['donation_type']) ? $_GET['donation_type'] : "One-time";

// -------------------------
// ✅ 从数据库读取分行资料
// -------------------------
$sql = "SELECT Branch_ID, Branch_Name, Branch_Type, Branch_Description FROM branch";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Branch Selection</title>
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
        font-weight: bold;
        font-size: 36px;
    }

    .function-links a {
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

    .story-section img {
        width: 100%;
        border-radius: 10px;
        margin-bottom: 15px;
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

    .branch-type {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-top: 20px;
    }

    .branch-card {
        background-color: #A8D5BA;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .branch-card h4 {
        margin: 10px 0 5px;
        color: #4A4A4A;
    }

    .branch-card p {
        font-size: 14px;
    }

    .B_btn {
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

    .B_btn:hover {
        background-color: #7CBF99;
    }

</style>
</head>
<body>

<header class="header">
    <div class="logo">品牌名</div>
    <div class="function-links">
        <a href="#">Home</a>
        <a href="#">About Us</a>
        <a href="#">Contact</a>
        <a href="#">Activities</a>
    </div>
</header>

<div class="container">
    <!-- 左侧社交按钮 -->
    <div class="sidebar">
        <button><img src="yourimage.jpg" alt="Whatsapp"></button>
        <button><img src="yourimage.jpg" alt="Facebook"></button>
        <button><img src="yourimage.jpg" alt="Instagram"></button>
        <button><img src="yourimage.jpg" alt="General Line"></button>
    </div>

    <!-- 中间故事 -->
    <div class="story-section">
        <h2>选择您希望支持的分行</h2>
        <p>每一个分行都在为不同的群体提供帮助。请选择一个您希望支持的分行来完成捐款。</p>
    </div>

    <!-- 右侧分行显示 -->
    <div class="donation-box">
        <h3>Available Branches</h3>
        <div class="branch-type">

        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "
                <div class='branch-card'>
                    <h4>{$row['Branch_Name']}</h4>
                    <p>Type: {$row['Branch_Type']}</p>
                    <p>{$row['Branch_Description']}</p>
                    <form method='GET' action='Payment_Ways_Page.php'>
                        <input type='hidden' name='branch_id' value='{$row['Branch_ID']}'>
                        <input type='hidden' name='amount' value='{$amount}'>
                        <input type='hidden' name='donation_type' value='{$donation_type}'>
                        <button type='submit' class='B_btn'>Select</button>
                    </form>
                </div>
                ";
            }
        } else {
            echo "<p>No branches found in database.</p>";
        }
        ?>

        </div>
    </div>
</div>

</body>
</html>

<?php $conn->close(); ?>
