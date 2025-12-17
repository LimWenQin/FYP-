<?php
// mail_receipt.php

// 1. 引入库 (注意路径要和你刚才建立的文件夹一致)
require('fpdf/fpdf.php'); 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// 自定义 PDF 类，为了加页眉页脚
class ReceiptPDF extends FPDF {
    function Header() {
        // Logo (如果你有 logo 图片，把 'images/logo.png' 换成你的路径，没有就注释掉)
        // $this->Image('images/logo.png', 10, 6, 30);
        
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(80); // 向右移动
        $this->Cell(30, 10, 'OFFICIAL RECEIPT', 0, 0, 'C');
        $this->Ln(20);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Thank you for your support - Love Bridge Foundation', 0, 0, 'C');
    }
}

function sendReceiptEmail($donationData, $project_name) {
    
    // ==========================================
    // A. 生成 PDF (仿照 invoice.php 的逻辑)
    // ==========================================
    $pdf = new ReceiptPDF('P', 'mm', 'A4');
    $pdf->AddPage();

    // 公司信息
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(190, 10, 'Love Bridge Foundation', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 5, 'Level 12, Menara Love Bridge, Kuala Lumpur', 0, 1, 'C');
    $pdf->Cell(190, 5, 'Phone: +603-1234 5678 | Email: info@lovebridge.org', 0, 1, 'C');
    
    $pdf->Line(10, 45, 200, 45); // 画横线
    $pdf->Ln(10); // 空行

    // 收据详情
    $pdf->SetFont('Arial', '', 12);
    
    // 左侧：收据号
    $pdf->Cell(40, 8, 'Receipt No:', 0);
    $pdf->Cell(60, 8, $donationData['Payment_TXN_Ref'], 0);
    
    // 右侧：日期
    $pdf->Cell(30, 8, 'Date:', 0);
    $pdf->Cell(60, 8, date("d/m/Y", strtotime($donationData['Payment_Paid_At'])), 0, 1);

    // 左侧：捐款人
    $pdf->Cell(40, 8, 'Received From:', 0);
    $pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $donationData['Donor_Name']), 0); // 处理中文乱码风险
    
    // 右侧：支付方式
    $pdf->Cell(30, 8, 'Method:', 0);
    $pdf->Cell(60, 8, $donationData['Payment_Method'], 0, 1);

    $pdf->Ln(10);

    // 表格头
    $pdf->SetFillColor(220, 50, 50); // 红色背景
    $pdf->SetTextColor(255); // 白色字
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(130, 10, 'Description', 1, 0, 'C', true);
    $pdf->Cell(60, 10, 'Amount (RM)', 1, 1, 'C', true);

    // 表格内容
    $pdf->SetTextColor(0); // 黑色字
    $pdf->SetFont('Arial', '', 12);
    
    $desc = "Donation to: " . iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $project_name);
    $amount = number_format($donationData['Order_Amount'], 2);

    $pdf->Ln(10); // 换行
    $pdf->Cell(130, 10, $desc, 1, 0);
    $pdf->Cell(60, 10, $amount, 1, 1, 'R');

    // 总计
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(130, 10, 'Total', 1, 0, 'R');
    $pdf->Cell(60, 10, $amount, 1, 1, 'R');

    // 备注
    $pdf->Ln(20);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'This receipt is computer generated and requires no signature.', 0, 1, 'C');

    // ★ 获取 PDF 内容 (字符串形式)
    $pdfContent = $pdf->Output('S'); 

    // ==========================================
    // B. 发送邮件 (PHPMailer)
    // ==========================================
    $mail = new PHPMailer(true);

    try {
        // 服务器配置
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = '你的邮箱@gmail.com'; // ⚠️ 请修改这里
        $mail->Password   = '你的应用专用密码';    // ⚠️ 请修改这里 (不是登录密码)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // 收发件人
        $mail->setFrom('你的邮箱@gmail.com', 'Love Bridge Receipt');
        $mail->addAddress($donationData['Donor_Email'], $donationData['Donor_Name']);

        // 附件
        $mail->addStringAttachment($pdfContent, 'Receipt_' . $donationData['Payment_TXN_Ref'] . '.pdf');

        // 内容
        $mail->isHTML(true);
        $mail->Subject = 'Official Donation Receipt';
        $mail->Body    = 'Dear Donor,<br><br>Thank you for your generous donation. Please find your official receipt attached.<br><br>Regards,<br>Love Bridge';

        $mail->send();
        return true;
    } catch (Exception $e) {
        // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        return false;
    }
}
?>