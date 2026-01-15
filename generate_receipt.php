<?php
session_start();
include 'dataconnection.php';
require('fpdf/fpdf.php'); // 确保你的路径正确

// ==========================================
// 1. 权限与参数检查
// ==========================================
if (!isset($_SESSION['donor_id'])) {
    die("Unauthorized access. Please login first.");
}

if (!isset($_GET['order_id'])) {
    die("Order ID is missing.");
}

$order_id = (int)$_GET['order_id'];
$current_donor_id = $_SESSION['donor_id'];

// ==========================================
// 2. 获取订单详情 (关联 payment 获取支付方式)
// ==========================================
$query = "SELECT o.*, p.Payment_Method, p.Payment_TXN_Ref 
          FROM orders o 
          JOIN payment p ON o.Payment_ID = p.Payment_ID 
          WHERE o.Order_ID = ? AND o.Donor_ID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $order_id, $current_donor_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Error: Record not found or access denied.");
}

// ==========================================
// 3. 获取捐款项目名称
// ==========================================
$project_name = "General Donation";
if (!empty($data['Case_ID'])) {
    $res = $conn->query("SELECT Case_Title FROM special_case WHERE Case_ID = " . $data['Case_ID']);
    $row = $res->fetch_assoc();
    $project_name = $row['Case_Title'] ?? "Special Case Donation";
} elseif (!empty($data['Activity_ID'])) {
    $res = $conn->query("SELECT Activity_Name FROM activity WHERE Activity_ID = " . $data['Activity_ID']);
    $row = $res->fetch_assoc();
    $project_name = $row['Activity_Name'] ?? "Activity Donation";
} elseif (!empty($data['Branch_ID'])) {
    $res = $conn->query("SELECT Branch_Name FROM branch WHERE Branch_ID = " . $data['Branch_ID']);
    $row = $res->fetch_assoc();
    $project_name = $row['Branch_Name'] ?? "Branch Support";
}

// ==========================================
// 4. 定义 PDF 生成类 (复用你的 mail_receipt.php 逻辑)
// ==========================================
class ReceiptPDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 10, 'OFFICIAL RECEIPT', 0, 1, 'C');
        $this->Ln(15);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Thank you for your support - Love Bridge Foundation', 0, 0, 'C');
    }
}

// ==========================================
// 5. 开始绘制 PDF
// ==========================================
$pdf = new ReceiptPDF('P', 'mm', 'A4');
$pdf->AddPage();

// 机构信息
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(190, 10, 'Love Bridge Foundation', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(190, 5, 'Level 12, Menara Love Bridge, Kuala Lumpur', 0, 1, 'C');
$pdf->Cell(190, 5, 'Phone: +603-1234 5678 | Email: lovebridge1201@gmail.com', 0, 1, 'C');
$pdf->Line(10, 48, 200, 48); // 分割线
$pdf->Ln(15); 

// 收据基本资料
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(40, 8, 'Receipt No:', 0);
$pdf->Cell(60, 8, $data['Order_TXN_Ref'], 0);
$pdf->Cell(30, 8, 'Date:', 0);
$pdf->Cell(60, 8, date("d/m/Y", strtotime($data['Order_Created_At'])), 0, 1);

$pdf->Cell(40, 8, 'Received From:', 0);
$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $data['Order_Name']), 0); 
$pdf->Cell(30, 8, 'Method:', 0);
$pdf->Cell(60, 8, $data['Payment_Method'], 0, 1);
$pdf->Ln(10);

// 表格头部
$pdf->SetFillColor(242, 133, 133); // 浅红色
$pdf->SetTextColor(255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(130, 10, 'Description', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'Amount (RM)', 1, 1, 'C', true);

// 表格内容
$pdf->SetTextColor(0);
$pdf->SetFont('Arial', '', 12);
$desc = "Donation to: " . iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $project_name);
$amount = number_format($data['Order_Amount'], 2);

$pdf->Cell(130, 15, $desc, 1, 0, 'L');
$pdf->Cell(60, 15, $amount, 1, 1, 'R');

// 总计
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(130, 10, 'Total Paid', 1, 0, 'R');
$pdf->Cell(60, 10, 'RM ' . $amount, 1, 1, 'R');

$pdf->Ln(25);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'This receipt is computer generated and requires no signature.', 0, 1, 'C');

// ==========================================
// 6. 输出下载
// ==========================================
$filename = "Receipt_" . $data['Order_TXN_Ref'] . ".pdf";
$pdf->Output('D', $filename); // 'D' 强制浏览器触发下载
exit;