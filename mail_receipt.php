<?php
// mail_receipt.php

require('fpdf/fpdf.php'); 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// --- 金额转大写辅助函数 ---
function convertAmountToWords($number) {
    // 检查是否开启了 intl 扩展
    if (class_exists('NumberFormatter')) {
        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return "RINGGIT MALAYSIA " . strtoupper($f->format($number)) . " ONLY";
    } else {
        // Fallback: 如果没有开启 intl 扩展，直接返回数字格式，防止 Fatal Error
        return "RINGGIT MALAYSIA " . number_format($number, 2) . " ONLY";
    }
}

class ReceiptPDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'LOVE BRIDGE FOUNDATION', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Level 12, Menara Love Bridge, Jalan Charity, 50450 Kuala Lumpur', 0, 1, 'C');
        $this->Cell(0, 5, 'LHDN Reference No: LHDN.01/35/42/51/179-6.2024', 0, 1, 'C'); 
        $this->Ln(10);
        
        $this->SetFont('Arial', 'BU', 14);
        $this->Cell(0, 10, 'OFFICIAL TAX EXEMPTION RECEIPT', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-30);
        $this->SetFont('Arial', 'I', 8);
        $this->MultiCell(0, 4, "This receipt is issued under Section 44(6) of the Income Tax Act 1967.\nPlease retain this receipt for your tax deduction purposes.", 0, 'C');
        $this->Ln(2);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

function sendReceiptEmail($donationData, $project_name) {
    
    // ==========================================
    // A. 生成符合 LHDN 标准的 PDF
    // ==========================================
    $pdf = new ReceiptPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->SetFont('Arial', '', 11);
    
    // 记录起始坐标
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();

    // --- 左侧：捐赠者详情 ---
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(100, 7, 'DONOR DETAILS:', 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(100, 6, 'Name: ' . ($donationData['Order_Name'] ?? $donationData['Donor_Name'] ?? 'N/A'), 0, 1);
    $pdf->Cell(100, 6, 'IC / Reg No: ' . ($donationData['Order_ICNumber'] ?? 'N/A'), 0, 1);
    
    // 详细地址处理
    $addr1 = $donationData['Donor_Address1'] ?? '';
    $city  = $donationData['Donor_City'] ?? '';
    $state = $donationData['Donor_State'] ?? '';
    $zip   = $donationData['Donor_PostalCode'] ?? '';
    
    $fullAddress = trim($addr1);
    if($zip || $city) $fullAddress .= "\n" . trim("$zip $city");
    if($state) $fullAddress .= ",\n" . trim($state);
    if(!$fullAddress) $fullAddress = "N/A";

    $pdf->MultiCell(100, 5, 'Address: ' . $fullAddress, 0, 'L');

    // --- 右侧：收据关键信息 (使用绝对定位) ---
    $pdf->SetXY($startX + 110, $startY);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(80, 7, 'RECEIPT INFO:', 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetX($startX + 110);
    $pdf->Cell(80, 6, 'Receipt No: ' . ($donationData['Order_TXN_Ref'] ?? 'N/A'), 0, 1);
    $pdf->SetX($startX + 110);
    
    $dateStr = (!empty($donationData['Order_Created_At'])) ? date("d M Y", strtotime($donationData['Order_Created_At'])) : date("d M Y");
    $pdf->Cell(80, 6, 'Date: ' . $dateStr, 0, 1);
    $pdf->SetX($startX + 110);
    
    $method = $donationData['Order_PaymentMethod'] ?? $donationData['Payment_Method'] ?? 'E-Wallet';
    $pdf->Cell(80, 6, 'Payment: ' . $method, 0, 1);

    $pdf->Ln(20);

    // --- 捐款表格 ---
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(130, 10, 'DESCRIPTION', 1, 0, 'C', true);
    $pdf->Cell(60, 10, 'AMOUNT (RM)', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 11);
    $amount = $donationData['Order_Amount'] ?? 0;
    $pdf->Cell(130, 12, ' Cash Donation for: ' . $project_name, 1, 0, 'L');
    $pdf->Cell(60, 12, number_format($amount, 2), 1, 1, 'R');

    // --- 总计 ---
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(130, 10, 'TOTAL RECEIVED ', 1, 0, 'R');
    $pdf->Cell(60, 10, 'RM ' . number_format($amount, 2), 1, 1, 'R');

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'Amount in words: ' . convertAmountToWords($amount), 0, 1, 'L');

    $pdf->Ln(20);
    
    // --- 签章 ---
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(120); 
    $pdf->Cell(70, 5, '__________________________', 0, 1, 'C');
    $pdf->Cell(120);
    $pdf->Cell(70, 5, 'Authorized Signature', 0, 1, 'C');
    $pdf->Cell(120);
    $pdf->Cell(70, 5, 'Love Bridge Foundation', 0, 1, 'C');

    $pdfContent = $pdf->Output('S'); 

    // ==========================================
    // B. 发送邮件
    // ==========================================
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lovebridge1201@gmail.com'; 
        $mail->Password   = 'odaj iwrz gfrt vven';    
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('lovebridge1201@gmail.com', 'Love Bridge Admin');
        $mail->addAddress($donationData['Order_Email'] ?? $donationData['Donor_Email'], $donationData['Order_Name'] ?? $donationData['Donor_Name']);

        $mail->addStringAttachment($pdfContent, 'Official_Receipt_' . ($donationData['Order_TXN_Ref'] ?? time()) . '.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'Official Tax Exemption Receipt - Love Bridge';
        $mail->Body    = '<p>Dear ' . htmlspecialchars($donationData['Order_Name'] ?? 'Donor') . ',</p>
                          <p>Thank you for your donation. Attached is your official tax-deductible receipt for <b>' . htmlspecialchars($project_name) . '</b>.</p>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>