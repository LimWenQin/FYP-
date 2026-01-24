<?php
// send_reminders.php
include 'dataconnection.php';

// 引入 PHPMailer (请确保路径与 mail_receipt.php 一致)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// 1. 查找【当天】需要扣款的 Active 计划 (DATEDIFF = 0)
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
        $amount = number_format($row['Recurring_Amount'], 2);
        $date = $row['Recurring_Deduction_Date'];
        $method = $row['Recurring_Payment_Method'];

        // --- 准备提醒文字 ---
        $wallet_reminder_text = "";
        if ($method == 'System E-Wallet' || $method == 'TNG eWallet') {
            $wallet_reminder_text = " Important: Since you are using $method, please ensure your wallet balance has at least RM $amount to avoid deduction failure.";
        }

        // ==========================================
        // Action 1: 创建 Website 弹窗通知
        // ==========================================
        $msg = "Dear $name, friendly reminder: Your recurring donation of RM $amount is scheduled for $date." . $wallet_reminder_text;
        
        // 修复：正确的 SQL 转义写法
        $escaped_msg = $conn->real_escape_string($msg);
        $check_sql = "SELECT * FROM notifications WHERE Donor_ID = $donor_id AND Message = '$escaped_msg'";
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
        // Action 2: 使用 PHPMailer 发送 Email (更可靠)
        // ==========================================
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'lovebridge1201@gmail.com'; 
            $mail->Password   = 'odaj iwrz gfrt vven'; // 你的 App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('lovebridge1201@gmail.com', 'Love Bridge Admin');
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = 'Upcoming Donation Reminder - Love Bridge';

            $email_warning_html = "";
            if ($method == 'System E-Wallet' || $method == 'TNG eWallet') {
                $email_warning_html = "
                    <div style='background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                        <strong>⚠️ Balance Check Required:</strong><br>
                        You are using <strong>$method</strong>. Please ensure your account has at least <strong>RM $amount</strong> before today's deduction to ensure a successful contribution.
                    </div>";
            }

            $mail->Body = "
                <div style='font-family: Arial; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h3>Hi $name,</h3>
                    <p>This is a reminder that your monthly donation of <strong>RM $amount</strong> is scheduled for today (<strong>$date</strong>).</p>
                    $email_warning_html
                    <p>Thank you for your kindness,<br><strong>Love Bridge Team</strong></p>
                </div>";

            $mail->send();
            echo "<p style='color:green;'>[Email] Reminder sent to $email via PHPMailer</p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>[Email] Failed to send: {$mail->ErrorInfo}</p>";
        }
    }
} else {
    echo "<p>No active donations found for today ($date). Check your DB dates!</p>";
}
?>