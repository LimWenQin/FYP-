<?php
// send_reminders.php
include 'dataconnection.php';

// 设置邮件头部
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= 'From: Love Bridge <no-reply@lovebridge.com>' . "\r\n";

// 1. 查找【3天后】需要扣款的 Active 计划
// 注意：表名和字段名已更新为 donor, recurring_donation
$sql = "SELECT rd.*, d.Donor_Email, d.Donor_Name 
        FROM recurring_donation rd
        JOIN donor d ON rd.Donor_ID = d.Donor_ID
        WHERE rd.Recurring_Status = 'Active' 
        AND DATEDIFF(rd.Recurring_Deduction_Date, CURDATE()) = 3";

$result = $conn->query($sql);

echo "<h2>System Check: Sending Reminders...</h2>";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $donor_id = $row['Donor_ID'];
        $email = $row['Donor_Email'];
        $name = $row['Donor_Name'];
        $amount = $row['Recurring_Amount'];
        $date = $row['Recurring_Deduction_Date'];

        // ==========================================
        // Action 1: 创建 Website 弹窗通知
        // ==========================================
        $msg = "Dear $name, friendly reminder: Your recurring donation of RM $amount is scheduled for $date.";
        
        // 检查防止重复 (避免刷新页面重复插入)
        // 字段: Donor_ID, Message
        $check_sql = "SELECT * FROM notifications WHERE Donor_ID = $donor_id AND Message = '$msg'";
        $check_res = $conn->query($check_sql);
        
        if ($check_res->num_rows == 0) {
            $insert_note = $conn->prepare("INSERT INTO notifications (Donor_ID, Message) VALUES (?, ?)");
            $insert_note->bind_param("is", $donor_id, $msg);
            $insert_note->execute();
            echo "<p style='color:green;'>[Website] Notification created for: $name</p>";
        } else {
            echo "<p style='color:gray;'>[Website] Notification already exists for: $name</p>";
        }

        // ==========================================
        // Action 2: 发送 Email 提醒 (纯文字)
        // ==========================================
        $subject = "Upcoming Donation Reminder - Love Bridge";
        
        $messageBody = "
        <html>
        <body>
            <h3>Hi $name,</h3>
            <p>This is a reminder that your monthly donation of <strong>RM $amount</strong> will be processed on <strong>$date</strong>.</p>
            <p>Please ensure your account has sufficient funds.</p>
            <p>You can manage your donation plan in your user profile.</p>
            <br>
            <p>Thank you,<br>Love Bridge Team</p>
        </body>
        </html>
        ";

        // 发送邮件
        // 注意：本地环境需要 SMTP 配置才能成功发送
        if(mail($email, $subject, $messageBody, $headers)){
            echo "<p style='color:green;'>[Email] Reminder sent to $email</p>";
        } else {
            echo "<p style='color:red;'>[Email] Failed to send to $email (Check SMTP settings)</p>";
        }
    }
} else {
    echo "<p>No donations found due in exactly 3 days.</p>";
}
?>