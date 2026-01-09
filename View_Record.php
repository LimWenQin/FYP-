<?php
session_start();
include 'dataconnection.php';

// 1. 检查登录
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

// 2. 获取参数
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    echo "<script>alert('Invalid request.'); window.location.href='Track_Records.php';</script>";
    exit();
}

$record_id = $_GET['id'];
$record_type = $_GET['type'];
$current_donor_id = $_SESSION['donor_id'];

$details = [];

// 3. 查询详细信息
if ($record_type == 'Cash') {
    // --- 现金捐款 (Cash/Card/E-Wallet) ---
    $sql = "SELECT o.*, 
                   sc.Case_Title, act.Activity_Name, b.Branch_Name 
            FROM orders o
            LEFT JOIN special_case sc ON o.Case_ID = sc.Case_ID
            LEFT JOIN activity act ON o.Activity_ID = act.Activity_ID
            LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
            WHERE o.Order_ID = ? AND o.Donor_ID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $record_id, $current_donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // 1. 确定捐款对象 (Beneficiary)
        $project_name = "General Fund (HQ)";
        if (!empty($row['Case_Title'])) {
            $project_name = "Special Case: " . $row['Case_Title'];
        } elseif (!empty($row['Activity_Name'])) {
            $project_name = "Activity: " . $row['Activity_Name'];
        } elseif (!empty($row['Branch_Name'])) {
            $project_name = "Branch: " . $row['Branch_Name'];
        }

        // 2. 组装显示数据 (顺序决定显示顺序)
        $details = [
            'Beneficiary' => $project_name, // 捐去哪里
            'Amount' => "RM " . number_format($row['Order_Amount'], 2),
            'Transaction Ref' => $row['Order_TXN_Ref'], // 单号
            'Date & Time' => date("d M Y, h:i A", strtotime($row['Order_Created_At'])), // 几时
            'Payment Method' => $row['Order_PaymentMethod'] ?: 'Unknown', // 用什么方式
            'Donation Type' => $row['Order_Type'], // 一次性还是月捐
            'Donor Name' => $row['Order_Name'], // 捐赠者名字
            'Contact Info' => $row['Order_Email'] . ' (' . $row['Order_ContactNumber'] . ')',
            'Tax Receipt' => $row['Tax_Receipt_Status'],
            'Status' => $row['Order_Status']
        ];
    }

} elseif ($record_type == 'Item') {
    // --- 物品捐赠 (Item) ---
    $sql = "SELECT * FROM item_donation WHERE Item_ID = ? AND Donor_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $record_id, $current_donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $details = [
            'Record Type' => 'Item Donation',
            'Ref No' => 'ITEM-' . str_pad($row['Item_ID'], 6, '0', STR_PAD_LEFT),
            'Date Submitted' => date("d M Y, h:i A", strtotime($row['Item_Created_At'] ?? 'now')), 
            'Item Name' => $row['Item_Name'],
            'Quantity' => $row['Item_Quantity'],
            'Description' => $row['Item_Description'] ?: 'N/A',
            'Drop-off Method' => $row['Item_DropOff_Method'],
            'Status' => $row['Item_Status']
        ];
    }
}

// 查无数据处理
if (empty($details)) {
    echo "<script>alert('Record not found.'); window.location.href='Track_Records.php';</script>";
    exit();
}

include 'header_UI.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .view-container { max-width: 700px; margin: 50px auto; padding: 20px; }
    
    .detail-card { 
        background: white; border-radius: 15px; padding: 40px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
        border-top: 5px solid #e16161ff; /* 红色顶条 */
        position: relative;
    }

    .detail-header { 
        border-bottom: 2px dashed #eee; padding-bottom: 20px; margin-bottom: 25px; 
        text-align: center;
    }
    .detail-header h2 { margin: 0 0 10px 0; color: #333; font-weight: 800; font-size: 1.8rem; }
    .detail-subtitle { color: #777; font-size: 0.9rem; }

    /* 状态标签 */
    .status-tag { 
        display: inline-block; padding: 6px 18px; border-radius: 20px; 
        font-weight: bold; font-size: 0.9rem; margin-top: 10px;
    }
    .status-completed, .status-success, .status-approved { background: #dcfce7; color: #166534; }
    .status-pending, .status-requested { background: #fef3c7; color: #92400e; }
    .status-rejected, .status-failed { background: #fee2e2; color: #991b1b; }

    /* 信息行样式 */
    .info-row { display: flex; margin-bottom: 18px; align-items: flex-start; }
    .info-label { width: 160px; font-weight: 600; color: #666; flex-shrink: 0; font-size: 0.95rem; }
    .info-value { flex: 1; color: #222; font-weight: 500; font-size: 1rem; line-height: 1.5; }

    /* 金额特别强调 */
    .amount-highlight { color: #e16161ff; font-weight: 800; font-size: 1.3rem; }

    /* 按钮 */
    .btn-container { margin-top: 30px; text-align: center; }
    .btn-back {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        padding: 12px 35px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db;
        border-radius: 30px; text-decoration: none; font-weight: bold; transition: 0.2s;
    }
    .btn-back:hover { background: #d1d5db; color: #111; transform: translateY(-2px); text-decoration: none; }

    @media (max-width: 600px) {
        .info-row { flex-direction: column; margin-bottom: 20px; }
        .info-label { width: 100%; margin-bottom: 5px; color: #888; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { width: 100%; font-size: 1.1rem; }
    }
</style>

<div class="view-container">
    <div class="detail-card">
        
        <div class="detail-header">
            <h2>Transaction Details</h2>
            <div class="detail-subtitle">Reference: <?php echo htmlspecialchars($details['Transaction Ref'] ?? $details['Ref No']); ?></div>
            
            <?php 
                $status = strtolower($details['Status'] ?? '');
                $status_class = '';
                if (strpos($status, 'success') !== false || strpos($status, 'completed') !== false || strpos($status, 'approved') !== false) $status_class = 'status-success';
                elseif (strpos($status, 'fail') !== false || strpos($status, 'reject') !== false) $status_class = 'status-failed';
                else $status_class = 'status-pending';
            ?>
            <span class="status-tag <?php echo $status_class; ?>">
                <?php echo htmlspecialchars($details['Status'] ?? 'Unknown'); ?>
            </span>
        </div>

        <?php foreach ($details as $label => $value): ?>
            <?php 
                // 排除不需要重复显示的字段
                if ($label == 'Status' || $label == 'Transaction Ref' || $label == 'Ref No') continue; 
            ?>
            
            <div class="info-row">
                <div class="info-label"><?php echo $label; ?></div>
                <div class="info-value">
                    <?php if($label == 'Amount'): ?>
                        <span class="amount-highlight"><?php echo $value; ?></span>
                    <?php elseif($label == 'Tax Receipt'): ?>
                        <?php if($value == 'Generated'): ?>
                            <span style="color:#166534; font-weight:bold;"><i class="fas fa-check-circle"></i> Sent via Email</span>
                        <?php elseif($value == 'Requested'): ?>
                            <span style="color:#d97706; font-weight:bold;"><i class="fas fa-clock"></i> Processing</span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($value); ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php echo htmlspecialchars($value); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="btn-container">
            <a href="Track_Records.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Records
            </a>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>