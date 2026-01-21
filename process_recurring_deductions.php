<?php
// process_recurring_deductions.php
// 这个文件应该由服务器每天自动调用，或者管理员手动访问触发

include 'dataconnection.php';
require_once 'mail_receipt.php'; // 引入发邮件功能

echo "<h1>Starting Recurring Deduction Process...</h1>";

// 1. 查找所有“今天”或“之前”应该扣款，且状态为 Active 的计划
// (查“之前”是为了补扣那些可能因为服务器宕机而错过的款项)
$today = date('Y-m-d');
$sql = "SELECT r.*, d.Donor_Name, d.Donor_Email, d.Donor_Wallet, 
               sc.Case_Title, a.Activity_Name, b.Branch_Name
        FROM recurring_donation r
        JOIN donor d ON r.Donor_ID = d.Donor_ID
        LEFT JOIN special_case sc ON r.Case_ID = sc.Case_ID
        LEFT JOIN activity a ON r.Activity_ID = a.Activity_ID
        LEFT JOIN branch b ON r.Branch_ID = b.Branch_ID
        WHERE r.Recurring_Status = 'Active' 
        AND r.Recurring_Deduction_Date <= ?"; // 注意这里用 <=

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$count_success = 0;
$count_failed = 0;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $plan_id = $row['Recurring_ID'];
        $donor_id = $row['Donor_ID'];
        $amount = $row['Recurring_Amount'];
        $current_wallet = $row['Donor_Wallet'];
        
        // 确定项目名称
        $project_name = "General Fund";
        if($row['Case_Title']) $project_name = "Case: " . $row['Case_Title'];
        elseif($row['Activity_Name']) $project_name = "Activity: " . $row['Activity_Name'];
        elseif($row['Branch_Name']) $project_name = "Branch: " . $row['Branch_Name'];

        echo "<hr>Processing Plan #$plan_id for Donor: {$row['Donor_Name']} (Amount: RM $amount)<br>";

        // 2. 检查余额是否充足
        if ($current_wallet >= $amount) {
            
            // --- 开始事务 (保证扣钱和生成记录同时成功) ---
            $conn->begin_transaction();

            try {
                // A. 扣钱
                $new_balance = $current_wallet - $amount;
                $update_wallet = $conn->query("UPDATE donor SET Donor_Wallet = $new_balance WHERE Donor_ID = $donor_id");

                // B. 生成 Payment 记录
                $txn_ref = "TXN-REC-" . date('YmdHis') . "-" . rand(100,999);
                $ins_pay = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_TXN_Ref, Payment_Amount, Payment_Paid_At, Payment_Bank_Name, Payment_Bank_Masked, Payment_Created_At) VALUES ('System E-Wallet', 'Success', ?, ?, NOW(), 'Recurring Auto', 'Wallet', NOW())");
                $ins_pay->bind_param("sd", $txn_ref, $amount);
                $ins_pay->execute();
                $payment_id = $conn->insert_id;

                // C. 生成 Order 记录
                // (注意：这里你需要根据你的 orders 表结构补充完整字段，比如 Order_Type = 'Recurring')
                $ins_order = $conn->prepare("INSERT INTO orders (Order_Name, Order_ContactNumber, Order_ICNumber, Order_Email, Order_Amount, Order_Currency, Order_PaymentMethod, Order_PaymentStatus, Order_Admin_Status, Order_TXN_Ref, Order_Type, Order_Status, Tax_Receipt_Status, Is_Deleted, Order_Created_At, Order_Updated_At, Donor_ID, Payment_ID, Branch_ID, Activity_ID, Case_ID) 
                SELECT Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email, ?, 'MYR', 'System E-Wallet', 'Success', 'Completed', ?, 'Recurring', 'Completed', 'Not_Requested', 0, NOW(), NOW(), Donor_ID, ?, ?, ?, ? FROM donor WHERE Donor_ID = ?");
                
                // 处理外键 NULL 值
                $b_id = $row['Branch_ID'] ?: NULL;
                $a_id = $row['Activity_ID'] ?: NULL;
                $c_id = $row['Case_ID'] ?: NULL;

                $ins_order->bind_param("dsiiiii", $amount, $txn_ref, $payment_id, $b_id, $a_id, $c_id, $donor_id);
                $ins_order->execute();
                $order_id = $conn->insert_id; // 获取刚生成的订单ID

                // D. 更新下次扣款日期 (+1 个月)
                $next_date = date('Y-m-d', strtotime('+1 month', strtotime($row['Recurring_Deduction_Date'])));
                $update_plan = $conn->query("UPDATE recurring_donation SET Recurring_Deduction_Date = '$next_date', Last_Payment_Date = NOW(), Recurring_Updated_At = NOW() WHERE Recurring_ID = $plan_id");

                // E. 发送邮件 (使用你写好的 mail_receipt.php)
                // 构造 fake row 数据给 sendReceiptEmail 函数
                $email_data = [
                    'Order_Name' => $row['Donor_Name'],
                    'Donor_Name' => $row['Donor_Name'],
                    'Order_ICNumber' => 'N/A (Recurring)', // 只有免税需要IC，普通收据可以简化
                    'Order_Email' => $row['Donor_Email'],
                    'Donor_Email' => $row['Donor_Email'],
                    'Order_TXN_Ref' => $txn_ref,
                    'Order_Created_At' => date('Y-m-d H:i:s'),
                    'Order_PaymentMethod' => 'Auto-Debit (E-Wallet)',
                    'Order_Amount' => $amount,
                    // 地址信息需要再次查询 donor 详情，或者如果你不需要显示详细地址可以省略
                ];
                
                // 补充地址信息 (为了 PDF 好看)
                $donor_q = $conn->query("SELECT * FROM donor WHERE Donor_ID = $donor_id");
                $donor_info = $donor_q->fetch_assoc();
                $email_data = array_merge($email_data, $donor_info);

                // 发送！
                $isSent = sendReceiptEmail($email_data, $project_name, false); // false = 普通收据

                // 提交事务
                $conn->commit();
                echo "<span style='color:green;'>SUCCESS: Payment processed. Next date: $next_date. Email: " . ($isSent ? 'Sent' : 'Failed') . "</span><br>";
                $count_success++;

            } catch (Exception $e) {
                $conn->rollback();
                echo "<span style='color:red;'>ERROR: " . $e->getMessage() . "</span><br>";
            }

        } else {
            // 余额不足
            echo "<span style='color:orange;'>SKIPPED: Insufficient Balance (Wallet: $current_wallet).</span><br>";
            
            // 可选：发送“扣款失败”通知给用户
            $msg = "Recurring payment failed due to insufficient balance. Please top up.";
            $conn->query("INSERT INTO notifications (Donor_ID, Message, Is_Read) VALUES ($donor_id, '$msg', 0)");
            
            $count_failed++;
        }
    }
} else {
    echo "No plans due for deduction today.<br>";
}

echo "<hr><strong>Summary:</strong> Processed: $count_success | Failed/Skipped: $count_failed";
?>