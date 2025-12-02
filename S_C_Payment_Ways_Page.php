<?php
// 1. 引入必要的设置和头部文件
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI_2.php';

// -------------------------
// 接收 POST 数据
// -------------------------
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$donation_type = isset($_POST['donation_type']) ? $_POST['donation_type'] : "one-time";
$case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;

// -------------------------
// 数据验证
// -------------------------
if ($amount <= 0 || $case_id <= 0) {
    die("<div style='text-align:center; padding:50px;'>
            <h2>Error: Invalid Data.</h2>
            <p>Amount or Case ID is missing.</p>
            <a href='Special_case_Page.php'>Return to Special Cases</a>
         </div>");
}

// -------------------------
// ✅ 新增：用 ID 查询 Special Case 的名字
// -------------------------
$case_title = "Unknown Case"; // 设置一个默认值

if ($case_id > 0) {
    // 准备 SQL 语句：根据 ID 查 Title
    $sql = "SELECT Case_Title FROM special_case WHERE Case_ID = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $case_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // ✅ 成功获取到了名字！
            $case_title = $row['Case_Title']; 
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Payment Ways - Special Case</title>

<style>
    /* 样式保持不变 */
    :root 
    {
        --gradient-start: #ff6b9d;
        --gradient-middle: #ff8fab;
        --gradient-end: #ffb3c6;
        --white: #ffffff;
    }

    body {
        background-color: #FFF5E4;
        margin: 0;
        font-family: Arial, sans-serif;
        color: #4A4A4A;
    }

    .header {
        background: linear-gradient(180deg, var(--gradient-start) 0%, var(--gradient-middle) 50%, var(--gradient-end) 100%) !important;
        box-shadow: 0 4px 15px rgba(255, 107, 157, 0.2);
    }
    
    .header .function-links a, 
    .header .logo {
        color: var(--white) !important;
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
    
    .story-section ul {
        list-style: none;
        padding: 0;
    }
    
    .story-section li {
        margin-bottom: 10px;
        font-size: 16px;
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

<div class="container">

    <div class="sidebar">
        <button><img src="yourimage.jpg" alt="Icon"></button>
        <button><img src="yourimage.jpg" alt="Icon"></button>
        <button><img src="yourimage.jpg" alt="Icon"></button>
        <button><img src="yourimage.jpg" alt="Icon"></button>
    </div>

    <div class="story-section">
        <h2>选择您的付款方式</h2>
        <p>请选择一种您偏好的付款方式来完成捐款。</p>

        <hr style="border: 1px solid #eee; margin: 20px 0;">

        <h3>当前捐款详情：</h3>
        <ul>
            <li><strong>Special Case Name:</strong> <?php echo htmlspecialchars($case_title); ?></li>
            
            <li><strong>Donation Amount:</strong> RM <?php echo htmlspecialchars(number_format($amount, 2)); ?></li>
            <li><strong>Donation Type:</strong> <?php echo htmlspecialchars(ucfirst($donation_type)); ?></li>
        </ul>
    </div>

    <div class="donation-box">
        <h3>Payment Ways</h3>

        <div class="Payment-Ways">

            <img src="bank.jpg" alt="Bank Transfer">
            <img src="tng.jpg" alt="Touch 'n Go">

            <form method="POST" action="Credit_Debit_Page.php">
                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                <input type="hidden" name="donation_type" value="<?php echo htmlspecialchars($donation_type); ?>">
                <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">
                <input type="hidden" name="branch_id" value="0"> 
                
                <button type="submit" class="P_btn">Bank Transfer</button>
            </form>

            <form method="POST" action="TNG_Page.php">
                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                <input type="hidden" name="donation_type" value="<?php echo htmlspecialchars($donation_type); ?>">
                <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">
                <input type="hidden" name="branch_id" value="0">

                <button type="submit" class="P_btn">TNG</button>
            </form>

        </div>
    </div>
</div>

</body>
</html>