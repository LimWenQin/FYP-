<?php
// 文件名: cron_auto_deduct.php
// 这是一个后台脚本，不需要漂亮的界面，只需要输出文字日志

include 'dataconnection.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

$today = date("Y-m-d");
echo "<h1>Auto-Deduction Process ($today)</h1><hr>";

// 1. 查询所有【状态活跃】且【到期】的计划
$sql = "SELECT r.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_ICNumber 
        FROM recurring_donation r 
        JOIN donor d ON r.Donor_ID = d.Donor_ID
        WHERE r.Recurring_Status = 'Active' 
        AND r.Recurring_Deduction_Date <= ?"; // 注意：用 <= 是为了防止服务器昨天关机漏掉了

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $plan_id = $row['Recurring_ID'];
        $donor_id = $row['Donor_ID'];
        $amount = $row['Recurring_Amount'];
        
        // ---------------------------------------------------------
        // A. 模拟扣款 (这里通常对接 Stripe/ToyyibPay 的 Recurring API)
        // ---------------------------------------------------------
        // 既然是模拟，我们直接假设成功
        $new_txn_ref = "AUTO-" . date("YmdHis") . "-" . $plan_id;
        $now = date("Y-m-d H:i:s");

        echo "Processing Plan #$plan_id (Donor: {$row['Donor_Name']})... ";

        // ---------------------------------------------------------
        // B. 插入 Payment 表
        // ---------------------------------------------------------
        $pay_sql = "INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) 
                    VALUES (?, 'Success', ?, ?, ?, 'Auto Debit', 'Recurring', ?)";
        $stmt_pay = $conn->prepare($pay_sql);
        $method = $row['Recurring_Payment_Method'];
        $stmt_pay->bind_param("ssdss", $method, $new_txn_ref, $amount, $now, $now);
        
        if ($stmt_pay->execute()) {
            $new_payment_id = $stmt_pay->insert_id;
            $stmt_pay->close();

            // ---------------------------------------------------------
            // C. 插入 Orders 表 (生成第二个月的 Order)
            // ---------------------------------------------------------
            $order_sql = "INSERT INTO orders 
                (Order_Name, Order_ContactNumber, Order_ICNumber, Order_Email, 
                 Order_Amount, Order_Points_Earned, Order_Currency, Order_PaymentMethod, 
                 Order_PaymentStatus, Order_Admin_Status, Order_TXN_Ref, Order_Type, 
                 Order_Status, Is_Deleted, Order_Created_At, Order_Updated_At, 
                 Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID)
                VALUES (?, ?, ?, ?, ?, 0, 'MYR', ?, 'Success', 'Completed', ?, 'Recurring', 'Completed', 0, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_order = $conn->prepare($order_sql);
            
            // 处理外键 NULL (因为有的计划是捐给 Case，有的是 Branch)
            $b_id = $row['Branch_ID']; 
            $a_id = $row['Activity_ID']; 
            $c_id = $row['Case_ID'];

            $stmt_order->bind_param("ssssdssssssiiii", 
                $row['Donor_Name'], $row['Donor_ContactNumber'], $row['Donor_ICNumber'], $row['Donor_Email'],
                $amount, $method, $new_txn_ref, $now, $now, 
                $donor_id, $new_payment_id, $b_id, $a_id, $c_id
            );
            $stmt_order->execute();
            $stmt_order->close();

            // ---------------------------------------------------------
            // D. 更新 Recurring Donation 表 (把日期推后一个月)
            // ---------------------------------------------------------
            $next_date = date('Y-m-d', strtotime('+1 month', strtotime($row['Recurring_Deduction_Date'])));
            
            $update_sql = "UPDATE recurring_donation 
                           SET Recurring_Deduction_Date = ?, Last_Payment_Date = ?, Recurring_Updated_At = ? 
                           WHERE Recurring_ID = ?";
            $stmt_upd = $conn->prepare($update_sql);
            $stmt_upd->bind_param("sssi", $next_date, $now, $now, $plan_id);
            $stmt_upd->execute();
            $stmt_upd->close();

            echo "<span style='color:green; font-weight:bold;'>SUCCESS! Next charge: $next_date</span><br>";

        } else {
            echo "<span style='color:red;'>FAILED to insert payment.</span><br>";
        }
    }
} else {
    echo "No recurring payments due today.<br>";
}

$conn->close();
?>