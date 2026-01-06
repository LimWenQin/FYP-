<?php
// mail_receipt.php

require('fpdf/fpdf.php'); 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

class ReceiptPDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(80); 
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
    // A. 生成 PDF
    // ==========================================
    $pdf = new ReceiptPDF('P', 'mm', 'A4');
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(190, 10, 'Love Bridge Foundation', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 5, 'Level 12, Menara Love Bridge, Kuala Lumpur', 0, 1, 'C');
    $pdf->Cell(190, 5, 'Phone: +603-1234 5678 | Email: lovebridge1201@gmail.com', 0, 1, 'C');
    
    $pdf->Line(10, 45, 200, 45);
    $pdf->Ln(10); 

    $pdf->SetFont('Arial', '', 12);
    
    $pdf->Cell(40, 8, 'Receipt No:', 0);
    $pdf->Cell(60, 8, $donationData['Order_TXN_Ref'], 0);
    
    $pdf->Cell(30, 8, 'Date:', 0);
    $pdf->Cell(60, 8, date("d/m/Y", strtotime($donationData['Order_Created_At'])), 0, 1);

    $pdf->Cell(40, 8, 'Received From:', 0);
    $name = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $donationData['Donor_Name']);
    $pdf->Cell(60, 8, $name, 0); 
    
    $pdf->Cell(30, 8, 'Method:', 0);
    $pdf->Cell(60, 8, $donationData['Payment_Method'], 0, 1);

    $pdf->Ln(10);

    $pdf->SetFillColor(242, 133, 133); // Love Bridge Pink/Red
    $pdf->SetTextColor(255);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(130, 10, 'Description', 1, 0, 'C', true);
    $pdf->Cell(60, 10, 'Amount (RM)', 1, 1, 'C', true);

    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial', '', 12);
    
    $desc = "Donation to: " . iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $project_name);
    $amount = number_format($donationData['Order_Amount'], 2);

    $pdf->Ln(10); 
    $pdf->Cell(130, 10, $desc, 1, 0);
    $pdf->Cell(60, 10, $amount, 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(130, 10, 'Total', 1, 0, 'R');
    $pdf->Cell(60, 10, $amount, 1, 1, 'R');

    $pdf->Ln(20);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'This receipt is computer generated and requires no signature.', 0, 1, 'C');

    $pdfContent = $pdf->Output('S'); 

    // ==========================================
    // B. 发送邮件
    // ==========================================
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        // 使用你提供的有效账号
        $mail->Username   = 'lovebridge1201@gmail.com'; 
        $mail->Password   = 'odaj iwrz gfrt vven';    
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 使用 SMTPS (465)
        $mail->Port       = 465;

        $mail->setFrom('lovebridge1201@gmail.com', 'Love Bridge Admin');
        $mail->addAddress($donationData['Donor_Email'], $donationData['Donor_Name']);

        $mail->addStringAttachment($pdfContent, 'Receipt_' . $donationData['Order_TXN_Ref'] . '.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'Official Donation Receipt - Love Bridge';
        $mail->Body    = '
            <div style="font-family: Arial, sans-serif;">
                <h2 style="color: #F28585;">Donation Receipt</h2>
                <p>Dear ' . htmlspecialchars($donationData['Donor_Name']) . ',</p>
                <p>Thank you for your generous donation to <strong>' . htmlspecialchars($project_name) . '</strong>.</p>
                <p>Please find your official tax-deductible receipt attached to this email.</p>
                <br>
                <p>Sincerely,<br>Love Bridge Foundation</p>
            </div>
        ';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
