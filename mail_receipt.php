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
    if (class_exists('NumberFormatter')) {
        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return "RINGGIT MALAYSIA " . strtoupper($f->format($number)) . " ONLY";
    } else {
        return "RINGGIT MALAYSIA " . number_format($number, 2) . " ONLY";
    }
}

class ReceiptPDF extends FPDF {
    // 标记是否为免税收据
    public $isTaxExempt = false;

    function Header() {
        // 设置页眉字体
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'LOVE BRIDGE FOUNDATION', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Level 12, Menara Love Bridge, Jalan Charity, 50450 Kuala Lumpur', 0, 1, 'C');
        
        // --- 差异点 1: 只有免税收据显示 LHDN 编号 ---
        if ($this->isTaxExempt) {
            $this->Cell(0, 5, 'LHDN Reference No: LHDN.01/35/42/51/179-6.2024', 0, 1, 'C'); 
        } else {
            // 普通收据留白或显示感谢语
            $this->Cell(0, 5, 'Thank you for your kindness and support', 0, 1, 'C');
        }
        $this->Ln(10);
        
        // --- 差异点 2: 动态标题 ---
        $this->SetFont('Arial', 'BU', 14);
        $title = $this->isTaxExempt ? 'OFFICIAL TAX EXEMPTION RECEIPT' : 'OFFICIAL DONATION RECEIPT';
        $this->Cell(0, 10, $title, 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-35);
        $this->SetFont('Arial', 'I', 8);
        
        // --- 差异点 3: 底部法律声明 ---
        if ($this->isTaxExempt) {
            $footerText = "This receipt is issued under Section 44(6) of the Income Tax Act 1967.\nPlease retain this receipt for your tax deduction purposes.";
        } else {
            $footerText = "This is a computer-generated official acknowledgement of your donation.\nNo signature is required for this document.";
        }
        
        $this->MultiCell(0, 4, $footerText, 0, 'C');
        $this->Ln(2);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

/**
 * 发送邮件函数
 * @param array $donationData 数据库订单详情
 * @param string $project_name 项目名称
 * @param bool $isTaxExempt 是否为免税收据
 */
function sendReceiptEmail($donationData, $project_name, $isTaxExempt = false) {
    
    // ==========================================
    // A. 生成 PDF 内容
    // ==========================================
    $pdf = new ReceiptPDF('P', 'mm', 'A4');
    $pdf->isTaxExempt = $isTaxExempt; // 设置模式
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->SetFont('Arial', '', 11);
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();

    // --- 左侧：捐赠者详情 ---
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(100, 7, 'DONOR DETAILS:', 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(100, 6, 'Name: ' . ($donationData['Order_Name'] ?? $donationData['Donor_Name'] ?? 'N/A'), 0, 1);
    
    // 只有免税收据必须显示 IC 号码
    $icLabel = $isTaxExempt ? 'IC / Reg No: ' : 'ID Reference: ';
    $pdf->Cell(100, 6, $icLabel . ($donationData['Order_ICNumber'] ?? 'N/A'), 0, 1);
    
    // 地址处理
    $addr1 = $donationData['Donor_Address1'] ?? '';
    $city  = $donationData['Donor_City'] ?? '';
    $state = $donationData['Donor_State'] ?? '';
    $zip   = $donationData['Donor_PostalCode'] ?? '';
    $fullAddress = trim($addr1);
    if($zip || $city) $fullAddress .= "\n" . trim("$zip $city");
    if($state) $fullAddress .= ",\n" . trim($state);
    if(!$fullAddress) $fullAddress = "N/A";

    $pdf->MultiCell(100, 5, 'Address: ' . $fullAddress, 0, 'L');

    // --- 右侧：收据关键信息 ---
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
    
    // --- 签章区域 (免税收据通常需要更正式的签章) ---
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(120); 
    $pdf->Cell(70, 5, '__________________________', 0, 1, 'C');
    $pdf->Cell(120);
    $pdf->Cell(70, 5, 'Authorized Signature', 0, 1, 'C');
    $pdf->Cell(120);
    $pdf->Cell(70, 5, 'Love Bridge Foundation', 0, 1, 'C');

    $pdfContent = $pdf->Output('S'); 

    // ==========================================
    // B. 配置并发送邮件
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

        // 附件名称根据收据类型变化
        $fileName = $isTaxExempt ? 'Tax_Exemption_Receipt_' : 'Donation_Receipt_';
        $mail->addStringAttachment($pdfContent, $fileName . ($donationData['Order_TXN_Ref'] ?? time()) . '.pdf');

        $mail->isHTML(true);
        if ($isTaxExempt) {
            $mail->Subject = 'Official Tax Exemption Receipt - Love Bridge';
            $mail->Body    = "<h3>Dear Donor,</h3>
                              <p>Thank you for choosing to support our cause. Your tax exemption receipt for <b>{$project_name}</b> has been approved and is attached to this email.</p>
                              <p>Please use this document for your income tax deduction purposes.</p>";
        } else {
            $mail->Subject = 'Official Donation Receipt - Love Bridge';
            $mail->Body    = "<h3>Dear Donor,</h3>
                              <p>Thank you for your generous donation to <b>{$project_name}</b>. Your contribution helps us make a difference.</p>
                              <p>Attached is the official acknowledgement receipt for your record.</p>";
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>