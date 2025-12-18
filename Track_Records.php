<?php
session_start();
include 'dataconnection.php';

// 1. 检查登录状态
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login to view your track records.'); window.location.href='donor_login.php';</script>";
    exit();
}

$current_donor_id = $_SESSION['donor_id'];

// ==========================================
// 2. 数据获取与处理
// ==========================================

// --- 获取筛选参数 ---
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'All';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'All';

$records = [];

// --- A. 获取现金捐款记录 (Orders) ---
if ($filter_type == 'All' || $filter_type == 'Cash') {
    $sql_orders = "SELECT Order_ID, Order_TXN_Ref, Order_Amount, Order_Status, Order_Created_At, 'Cash' as Record_Type 
                   FROM orders 
                   WHERE Donor_ID = ? AND Order_Status != 'Failed'";
    
    // 如果有状态筛选 (这里做简单的映射，你可以根据实际需求调整)
    if ($filter_status != 'All') {
        $sql_orders .= " AND Order_Status LIKE '%$filter_status%'";
    }

    $stmt = $conn->prepare($sql_orders);
    $stmt->bind_param("i", $current_donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = [
            'id' => $row['Order_ID'],
            'ref' => $row['Order_TXN_Ref'],
            'type' => 'Cash',
            'details' => 'RM ' . number_format($row['Order_Amount'], 2),
            'date' => $row['Order_Created_At'],
            'status' => $row['Order_Status'],
            'raw_amount' => $row['Order_Amount'] // 用于详情展示
        ];
    }
    $stmt->close();
}

// --- B. 获取物品捐赠记录 (Item Donation) ---
if ($filter_type == 'All' || $filter_type == 'Item') {
    $sql_items = "SELECT Item_ID, Item_Name, Item_Quantity, Item_Status, Item_Updated_At, 'Item' as Record_Type 
                  FROM item_donation 
                  WHERE Donor_ID = ?";
    
    if ($filter_status != 'All') {
        $sql_items .= " AND Item_Status LIKE '%$filter_status%'";
    }

    $stmt = $conn->prepare($sql_items);
    $stmt->bind_param("i", $current_donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = [
            'id' => $row['Item_ID'],
            'ref' => 'ITEM-' . str_pad($row['Item_ID'], 6, '0', STR_PAD_LEFT),
            'type' => 'Item',
            'details' => $row['Item_Name'] . ' (x' . $row['Item_Quantity'] . ')',
            'date' => $row['Item_Updated_At'],
            'status' => $row['Item_Status'],
            'raw_item_name' => $row['Item_Name'],
            'raw_quantity' => $row['Item_Quantity']
        ];
    }
    $stmt->close();
}

// --- C. 排序：按日期最新的排前面 ---
usort($records, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Records - Love Bridge</title>
    <style>
        /* --- 引入 Homepage 核心变量 --- */
        :root {
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-red: #fee2e2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --medium-gray: #737373;
            --dark-gray: #262626;
            --text-dark: #171717;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --info-blue: #3b82f6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-gray);
            color: var(--text-dark);
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            min-height: 80vh;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 2.5rem;
            color: var(--primary-red);
            font-weight: 800;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary-red);
            border-radius: 2px;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--medium-gray);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Filter Section */
        .filter-card {
            background: var(--white);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            border-left: 5px solid var(--primary-red);
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            justify-content: space-between;
        }

        .filter-group {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .filter-label {
            font-weight: 700;
            color: var(--text-dark);
        }

        .custom-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            color: var(--text-dark);
            outline: none;
            cursor: pointer;
            min-width: 150px;
        }

        .custom-select:focus {
            border-color: var(--primary-red);
        }

        .btn-filter {
            background-color: var(--primary-red);
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            background-color: var(--dark-red);
            transform: translateY(-2px);
        }

        /* Table Design */
        .table-card {
            background: var(--white);
            border-radius: 10px;
            padding: 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
        }

        .styled-table thead tr {
            background-color: var(--primary-red);
            color: var(--white);
            text-align: center;
        }

        .styled-table th, .styled-table td {
            padding: 18px 25px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        .styled-table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .styled-table tbody tr {
            transition: all 0.2s ease;
        }

        .styled-table tbody tr:hover {
            background-color: var(--light-gray);
        }

        /* Type Badges */
        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .type-cash { color: var(--success-green); }
        .type-item { color: var(--info-blue); }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-completed, .status-distributed, .status-success {
            background-color: #d1fae5; color: #065f46;
        }

        .status-pending, .status-received {
            background-color: #fef3c7; color: #92400e;
        }

        .status-cancelled, .status-failed {
            background-color: #fee2e2; color: #991b1b;
        }

        /* Action Button */
        .btn-details {
            background-color: white;
            color: var(--primary-red);
            border: 1px solid var(--primary-red);
            padding: 6px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-details:hover {
            background-color: var(--primary-red);
            color: white;
        }

        /* Modal Design */
        .modal-overlay {
            display: none;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.6); z-index: 1000;
            justify-content: center; align-items: center;
            backdrop-filter: blur(3px);
        }

        .modal-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            border-top: 6px solid var(--primary-red);
            animation: popIn 0.3s ease-out;
        }

        @keyframes popIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .modal-title { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); }
        
        .close-btn {
            background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--medium-gray);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 1.05rem;
        }

        .detail-label { color: var(--medium-gray); font-weight: 500; }
        .detail-value { font-weight: 700; color: var(--text-dark); text-align: right; }

        /* Responsive */
        @media (max-width: 768px) {
            .styled-table thead { display: none; }
            .styled-table, .styled-table tbody, .styled-table tr, .styled-table td {
                display: block; width: 100%;
            }
            .styled-table tr { margin-bottom: 20px; border: 1px solid #eee; border-radius: 8px; overflow: hidden; background: white; }
            .styled-table td {
                text-align: right; padding-left: 50%; position: relative; border-bottom: 1px solid #f5f5f5;
            }
            .styled-table td::before {
                content: attr(data-label);
                position: absolute; left: 20px; width: 45%; font-weight: 700; text-align: left; color: var(--medium-gray);
            }
            .filter-card { flex-direction: column; align-items: stretch; }
            .filter-group { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

<?php include 'header_UI.php'; ?>

<div class="main-container">

    <div class="page-header">
        <h1 class="page-title">Track Records</h1>
        <p class="page-subtitle">View your donation history, track item distribution, and see the impact you've made.</p>
    </div>

    <form method="GET" action="" class="filter-card">
        <div class="filter-group">
            <div>
                <span class="filter-label">Type:</span>
                <select name="type" class="custom-select">
                    <option value="All" <?php echo $filter_type=='All'?'selected':''; ?>>All Types</option>
                    <option value="Cash" <?php echo $filter_type=='Cash'?'selected':''; ?>>Cash Donation</option>
                    <option value="Item" <?php echo $filter_type=='Item'?'selected':''; ?>>Item Donation</option>
                </select>
            </div>
            <div>
                <span class="filter-label">Status:</span>
                <select name="status" class="custom-select">
                    <option value="All" <?php echo $filter_status=='All'?'selected':''; ?>>All Status</option>
                    <option value="Completed" <?php echo $filter_status=='Completed'?'selected':''; ?>>Completed/Distributed</option>
                    <option value="Pending" <?php echo $filter_status=='Pending'?'selected':''; ?>>Pending/Received</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn-filter">Apply Filters</button>
    </form>

    <div class="table-card">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Ref ID</th>
                    <th>Type</th>
                    <th>Details (Amount / Item)</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($records) > 0): ?>
                    <?php foreach ($records as $rec): ?>
                        <tr>
                            <td data-label="Ref ID"><strong><?php echo htmlspecialchars($rec['ref']); ?></strong></td>
                            
                            <td data-label="Type">
                                <span class="type-badge <?php echo ($rec['type']=='Cash') ? 'type-cash' : 'type-item'; ?>">
                                    <?php if($rec['type']=='Cash'): ?>
                                        <span>💰 Cash</span>
                                    <?php else: ?>
                                        <span>📦 Item</span>
                                    <?php endif; ?>
                                </span>
                            </td>

                            <td data-label="Details"><?php echo htmlspecialchars($rec['details']); ?></td>
                            
                            <td data-label="Date"><?php echo date('d M Y, h:i A', strtotime($rec['date'])); ?></td>
                            
                            <td data-label="Status">
                                <?php 
                                    // 简单的状态颜色映射逻辑
                                    $s = strtolower($rec['status']);
                                    $s_class = 'status-pending'; // 默认
                                    if(strpos($s, 'completed') !== false || strpos($s, 'distributed') !== false || strpos($s, 'success') !== false) {
                                        $s_class = 'status-completed';
                                    } elseif(strpos($s, 'failed') !== false || strpos($s, 'cancelled') !== false) {
                                        $s_class = 'status-failed';
                                    }
                                ?>
                                <span class="status-badge <?php echo $s_class; ?>">
                                    <?php echo htmlspecialchars($rec['status']); ?>
                                </span>
                            </td>
                            
                            <td data-label="Action">
                                <button class="btn-details" onclick='openModal(<?php echo json_encode($rec); ?>)'>
                                    View Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 40px; color: var(--medium-gray);">
                            No records found matching your criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<div id="recordModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Record Details</div>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div id="modalContent">
            </div>
        <div style="margin-top: 20px; text-align: right;">
            <button class="btn-filter" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    function openModal(data) {
        var modal = document.getElementById("recordModal");
        var content = document.getElementById("modalContent");
        
        var html = `
            <div class="detail-row">
                <span class="detail-label">Reference ID:</span>
                <span class="detail-value">${data.ref}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Donation Type:</span>
                <span class="detail-value">${data.type}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value">${new Date(data.date).toLocaleDateString()} ${new Date(data.date).toLocaleTimeString()}</span>
            </div>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
        `;

        if (data.type === 'Cash') {
            html += `
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value" style="color: var(--primary-red); font-size: 1.2rem;">RM ${parseFloat(data.raw_amount).toFixed(2)}</span>
                </div>
            `;
        } else {
            html += `
                <div class="detail-row">
                    <span class="detail-label">Item Name:</span>
                    <span class="detail-value">${data.raw_item_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Quantity:</span>
                    <span class="detail-value">${data.raw_quantity}</span>
                </div>
            `;
        }

        html += `
            <div class="detail-row">
                <span class="detail-label">Current Status:</span>
                <span class="status-badge status-${data.status.toLowerCase().includes('success') || data.status.toLowerCase().includes('completed') ? 'completed' : 'pending'}">
                    ${data.status}
                </span>
            </div>
            <div style="margin-top: 15px; background: #f9f9f9; padding: 10px; border-radius: 5px; font-size: 0.9rem; color: #666;">
                <p style="margin:0;">Thank you for your generosity. For any discrepancies, please contact our support team with the Reference ID.</p>
            </div>
        `;

        content.innerHTML = html;
        modal.style.display = "flex";
    }

    function closeModal() {
        document.getElementById("recordModal").style.display = "none";
    }

    // Close modal if clicked outside
    window.onclick = function(event) {
        var modal = document.getElementById("recordModal");
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>

</body>
</html>