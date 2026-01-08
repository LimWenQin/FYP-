<?php
session_start();
include 'dataconnection.php';

// 1. 检查登录
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

// 2. 获取参数 (ID 和 类型)
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    echo "<script>alert('Invalid request.'); window.location.href='Track_Records.php';</script>";
    exit();
}

$record_id = $_GET['id'];
$record_type = $_GET['type']; // 'Cash' or 'Item'
$current_donor_id = $_SESSION['donor_id'];

$details = [];

// 3. 根据类型查询详细信息
if ($record_type == 'Cash') {
    // 查询订单详情 (关联查询项目名称)
    // 注意：这里加入了 Order_PaymentMethod 的查询
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
        // 确定捐款去向
        $project_name = "General Fund";
        if ($row['Case_Title']) $project_name = "Case: " . $row['Case_Title'];
        elseif ($row['Activity_Name']) $project_name = "Activity: " . $row['Activity_Name'];
        elseif ($row['Branch_Name']) $project_name = "Branch: " . $row['Branch_Name'];

        $details = [
            'Ref No' => $row['Order_TXN_Ref'],
            'Date' => date("d M Y, h:i A", strtotime($row['Order_Created_At'])),
            'Amount' => "RM " . number_format($row['Order_Amount'], 2),
            'Payment Method' => $row['Order_PaymentMethod'] ?: 'Unknown', // 防止空值
            'Status' => $row['Order_Status'],
            'Project / Fund' => $project_name,
            'Tax Receipt' => $row['Tax_Receipt_Status']
        ];
    }

} elseif ($record_type == 'Item') {
    // 查询物品详情
    $sql = "SELECT * FROM item_donation WHERE Item_ID = ? AND Donor_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $record_id, $current_donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $details = [
            'Ref No' => 'ITEM-' . str_pad($row['Item_ID'], 6, '0', STR_PAD_LEFT),
            'Date' => date("d M Y, h:i A", strtotime($row['Item_Updated_At'])), 
            'Item Name' => $row['Item_Name'],
            'Quantity' => $row['Item_Quantity'],
            'Description' => $row['Item_Description'] ?: 'N/A',
            'Drop-off Method' => $row['Item_DropOff_Method'],
            'Status' => $row['Item_Status']
        ];
    }
}

// 如果查不到数据
if (empty($details)) {
    echo "<script>alert('Record not found.'); window.location.href='Track_Records.php';</script>";
    exit();
}

include 'header_UI.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .view-container { max-width: 800px; margin: 50px auto; padding: 20px; }
    .detail-card { 
        background: white; border-radius: 12px; padding: 40px; 
        box-shadow: 0 5px 25px rgba(0,0,0,0.05); border-top: 5px solid #dc2626; 
    }
    .detail-header { 
        display: flex; justify-content: space-between; align-items: center; 
        border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 30px; 
    }
    .detail-header h2 { margin: 0; color: #333; font-weight: 700; }
    .status-tag { 
        padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; 
        background: #eee; color: #555; 
    }
    /* 状态颜色 */
    .status-completed, .status-success, .status-approved { background: #dcfce7; color: #166534; }
    .status-pending, .status-requested { background: #fef3c7; color: #92400e; }
    .status-rejected, .status-failed { background: #fee2e2; color: #991b1b; }

    .info-row { display: flex; margin-bottom: 20px; border-bottom: 1px dashed #f0f0f0; padding-bottom: 15px; }
    .info-row:last-child { border-bottom: none; }
    .info-label { width: 180px; font-weight: 600; color: #777; flex-shrink: 0; }
    .info-value { flex: 1; color: #333; font-weight: 500; }

    .btn-back {
        display: inline-block; padding: 12px 30px; background: #e5e7eb; 
        color: #333; border-radius: 8px; text-decoration: none; font-weight: bold; 
        transition: 0.2s; margin-top: 20px;
    }
    .btn-back:hover { background: #d1d5db; color: #000; text-decoration: none; }
</style>

<div class="view-container">
    <div class="detail-card">
        <div class="detail-header">
            <h2>Record Details</h2>
            <?php 
                $status = strtolower($details['Status'] ?? '');
                $status_class = '';
                if (strpos($status, 'success') !== false || strpos($status, 'completed') !== false || strpos($status, 'approved') !== false) $status_class = 'status-success';
                elseif (strpos($status, 'fail') !== false || strpos($status, 'reject') !== false) $status_class = 'status-failed';
                else $status_class = 'status-pending';
            ?>
            <span class="status-tag <?php echo $status_class; ?>"><?php echo htmlspecialchars($details['Status'] ?? 'Unknown'); ?></span>
        </div>

        <?php foreach ($details as $label => $value): ?>
            <?php if ($label != 'Status'): // Status 已经显示在右上角了 ?>
            <div class="info-row">
                <div class="info-label"><?php echo $label; ?></div>
                <div class="info-value">
                    <?php if($label == 'Amount'): ?>
                        <span style="color:#dc2626; font-weight:bold; font-size:1.1rem;"><?php echo $value; ?></span>
                    <?php else: ?>
                        <?php echo htmlspecialchars($value); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <a href="Track_Records.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php include 'footer.php'; ?>