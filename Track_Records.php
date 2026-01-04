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
// 2. 处理 POST 请求 (更新资料 & 申请收据)
// ==========================================

// A. 处理：更新个人资料
if (isset($_POST['update_profile'])) {
    $ic = trim($_POST['ic_number']);
    $address = trim($_POST['address']);
    
    if(!empty($ic) && !empty($address)){
        $stmt = $conn->prepare("UPDATE donor SET Donor_ICNumber = ?, Donor_Address1 = ? WHERE Donor_ID = ?");
        $stmt->bind_param("ssi", $ic, $address, $current_donor_id);
        
        if ($stmt->execute()) {
            echo "<script>alert('Profile updated successfully! You can now request your receipt.'); window.location.href='Track_Records.php';</script>";
        } else {
            echo "<script>alert('Update failed. Please try again.');</script>";
        }
    }
}

// B. 处理：申请报税收据
if (isset($_POST['request_receipt'])) {
    $order_id = $_POST['order_id'];
    
    // 安全验证
    $check_sql = "SELECT d.Donor_ICNumber, d.Donor_Address1, o.Order_Amount 
                  FROM orders o JOIN donor d ON o.Donor_ID = d.Donor_ID 
                  WHERE o.Order_ID = ? AND o.Donor_ID = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("ii", $order_id, $current_donor_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $data = $result_check->fetch_assoc();

    if ($data && $data['Order_Amount'] >= 30 && !empty($data['Donor_ICNumber']) && !empty($data['Donor_Address1'])) {
        $stmt = $conn->prepare("UPDATE orders SET Tax_Receipt_Status = 'Requested' WHERE Order_ID = ?");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute()) {
            echo "<script>alert('Request submitted! Admin will verify shortly.'); window.location.href='Track_Records.php';</script>";
        }
    } else {
        echo "<script>alert('Error: Profile incomplete or amount less than RM30.');</script>";
    }
}

// ==========================================
// 3. 获取数据
// ==========================================

// --- 获取用户资料状态 ---
$user_sql = "SELECT Donor_ICNumber, Donor_Address1 FROM donor WHERE Donor_ID = ?";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("i", $current_donor_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$is_profile_complete = (!empty($user_data['Donor_ICNumber']) && !empty($user_data['Donor_Address1'])) ? 'true' : 'false';

// --- 获取筛选参数 ---
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'All';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'All';

$records = [];

// --- A. 获取现金捐款记录 ---
$is_cash_related = ($filter_type == 'All' || $filter_type == 'TNG eWallet' || $filter_type == 'Credit/Debit Card');

if ($is_cash_related) {
    $sql_orders = "SELECT Order_ID, Order_TXN_Ref, Order_Amount, Order_Status, Order_Created_At, Order_PaymentMethod, Tax_Receipt_Status, 'Cash' as Record_Type 
                   FROM orders 
                   WHERE Donor_ID = ? AND Order_Status != 'Failed'";
    
    if ($filter_type == 'TNG eWallet') {
        $sql_orders .= " AND Order_PaymentMethod LIKE '%TNG%'";
    } elseif ($filter_type == 'Credit/Debit Card') {
        $sql_orders .= " AND (Order_PaymentMethod LIKE '%Credit%' OR Order_PaymentMethod LIKE '%Card%' OR Order_PaymentMethod LIKE '%Debit%')";
    }

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
            'method' => $row['Order_PaymentMethod'],
            'details' => 'RM ' . number_format($row['Order_Amount'], 2),
            'date' => $row['Order_Created_At'],
            'status' => $row['Order_Status'],
            'raw_amount' => $row['Order_Amount'],
            'tax_status' => $row['Tax_Receipt_Status']
        ];
    }
    $stmt->close();
}

// --- B. 获取物品捐赠记录 ---
if ($filter_type == 'All' || $filter_type == 'Item') {
    $sql_items = "SELECT Item_ID, Item_Name, Item_Quantity, Item_Status, Item_Updated_At, Item_DropOff_Method, 'Item' as Record_Type 
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
        $method_str = "Item";
        if (!empty($row['Item_DropOff_Method'])) {
            $method_str .= " (" . $row['Item_DropOff_Method'] . ")";
        }

        $records[] = [
            'id' => $row['Item_ID'],
            'ref' => 'ITEM-' . str_pad($row['Item_ID'], 6, '0', STR_PAD_LEFT),
            'type' => 'Item',
            'method' => $method_str,
            'details' => $row['Item_Name'] . ' (x' . $row['Item_Quantity'] . ')',
            'date' => $row['Item_Updated_At'],
            'status' => $row['Item_Status'],
            'raw_item_name' => $row['Item_Name'],
            'raw_quantity' => $row['Item_Quantity'],
            'tax_status' => 'N/A'
        ];
    }
    $stmt->close();
}

// --- C. 排序 ---
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 保持原有样式不变 */
        :root {
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-red: #fee2e2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --medium-gray: #737373;
            --dark-gray: #262626;
            --text-dark: #171717;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-gray);
            color: var(--text-dark);
            margin: 0; padding: 0;
        }

        .main-container {
            max-width: 1200px; margin: 0 auto; padding: 40px 20px; min-height: 80vh;
        }

        .page-header { text-align: center; margin-bottom: 40px; }
        .page-title {
            font-size: 2.5rem; color: var(--primary-red); font-weight: 800;
            margin-bottom: 15px; position: relative; display: inline-block;
        }
        .page-title::after {
            content: ''; position: absolute; bottom: -10px; left: 50%;
            transform: translateX(-50%); width: 80px; height: 4px;
            background: var(--primary-red); border-radius: 2px;
        }
        .page-subtitle { font-size: 1.1rem; color: var(--medium-gray); }

        /* Filter */
        .filter-card {
            background: var(--white); padding: 25px; border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); margin-bottom: 30px;
            border-left: 5px solid var(--primary-red);
            display: flex; flex-wrap: wrap; gap: 20px; align-items: center; justify-content: space-between;
        }
        .filter-group { display: flex; gap: 15px; align-items: center; }
        .filter-label { font-weight: 700; color: var(--text-dark); }
        .custom-select {
            padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px;
            font-family: inherit; outline: none; min-width: 150px;
        }
        .btn-filter {
            background-color: var(--primary-red); color: white;
            padding: 10px 25px; border: none; border-radius: 5px;
            font-weight: 600; cursor: pointer; transition: all 0.3s ease;
        }
        .btn-filter:hover { background-color: var(--dark-red); }

        /* Table */
        .table-card {
            background: var(--white); border-radius: 10px; overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); overflow-x: auto;
        }
        .styled-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .styled-table thead tr { background-color: var(--primary-red); color: var(--white); }
        .styled-table th, .styled-table td { padding: 18px 15px; border-bottom: 1px solid #eee; text-align: center; vertical-align: middle; }
        .styled-table th { font-weight: 600; font-size: 0.9rem; text-transform: uppercase; }
        .styled-table tbody tr:hover { background-color: var(--light-gray); }

        /* Badges & Buttons */
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; display: inline-block; }
        
        .status-completed, .status-success, .status-generated { background-color: #dcfce7; color: #166534; }
        .status-pending, .status-requested { background-color: #fef3c7; color: #92400e; }
        .status-failed, .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-not_requested { background-color: #f3f4f6; color: #6b7280; }

        .btn-action {
            padding: 6px 12px; border-radius: 5px; font-size: 0.85rem; font-weight: 600;
            cursor: pointer; border: none; transition: 0.2s;
        }
        .btn-view { background: white; border: 1px solid var(--primary-red); color: var(--primary-red); }
        .btn-view:hover { background: var(--primary-red); color: white; }
        .btn-tax { background: var(--primary-red); color: white; border: 1px solid var(--primary-red); }
        .btn-tax:hover { background: var(--dark-red); }
        .btn-processing { background: #fbbf24; color: white; cursor: default; }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .modal-box { background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; border-top: 6px solid var(--primary-red); }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-title { font-size: 1.5rem; font-weight: 800; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }

        @media (max-width: 768px) {
            .styled-table thead { display: none; }
            .styled-table tr { display: block; margin-bottom: 20px; border: 1px solid #eee; }
            .styled-table td { display: flex; justify-content: space-between; text-align: right; padding: 10px; border-bottom: 1px solid #f5f5f5; }
            .styled-table td::before { content: attr(data-label); font-weight: 700; color: #777; }
        }
    </style>
</head>
<body>

<?php include 'header_UI.php'; ?>

<div class="main-container">

    <div class="page-header">
        <h1 class="page-title">Track Records</h1>
        <p class="page-subtitle">View your donation history and check tax receipt status.</p>
    </div>

    <form method="GET" action="" class="filter-card">
        <div class="filter-group">
            <div>
                <span class="filter-label">Filter Method:</span>
                <select name="type" class="custom-select">
                    <option value="All" <?php echo $filter_type=='All'?'selected':''; ?>>All Methods</option>
                    <option value="TNG eWallet" <?php echo $filter_type=='TNG eWallet'?'selected':''; ?>>TNG eWallet</option>
                    <option value="Credit/Debit Card" <?php echo $filter_type=='Credit/Debit Card'?'selected':''; ?>>Credit/Debit Card</option>
                    <option value="Item" <?php echo $filter_type=='Item'?'selected':''; ?>>Item Donation</option>
                </select>
            </div>
            <div>
                <span class="filter-label">Status:</span>
                <select name="status" class="custom-select">
                    <option value="All" <?php echo $filter_status=='All'?'selected':''; ?>>All Status</option>
                    <option value="Completed" <?php echo $filter_status=='Completed'?'selected':''; ?>>Completed</option>
                    <option value="Pending" <?php echo $filter_status=='Pending'?'selected':''; ?>>Pending</option>
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
                    <th>Payment Method</th>
                    <th>Amount / Item</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Tax Receipt</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($records) > 0): ?>
                    <?php foreach ($records as $rec): ?>
                        <tr>
                            <td data-label="Ref ID"><strong><?php echo htmlspecialchars($rec['ref']); ?></strong></td>
                            
                            <td data-label="Method">
                                <?php 
                                    $m = $rec['method'];
                                    $icon = (stripos($m, 'TNG') !== false) ? 'fa-wallet' : ((stripos($m, 'Card') !== false) ? 'fa-credit-card' : 'fa-money-bill');
                                    if(stripos($m, 'Item') !== false) $icon = 'fa-box-open';
                                ?>
                                <i class="fas <?php echo $icon; ?>" style="margin-right:5px; color:#777;"></i>
                                <?php echo htmlspecialchars($m); ?>
                            </td>

                            <td data-label="Details"><?php echo htmlspecialchars($rec['details']); ?></td>
                            <td data-label="Date"><?php echo date('d M Y', strtotime($rec['date'])); ?></td>
                            
                            <td data-label="Status">
                                <?php 
                                    $s_class = (stripos($rec['status'], 'Success') !== false || stripos($rec['status'], 'Completed') !== false) ? 'status-success' : 'status-pending';
                                    if(stripos($rec['status'], 'Failed') !== false) $s_class = 'status-failed';
                                ?>
                                <span class="status-badge <?php echo $s_class; ?>"><?php echo htmlspecialchars($rec['status']); ?></span>
                            </td>

                            <td data-label="Tax Receipt">
                                <?php if ($rec['type'] == 'Item'): ?>
                                    <span style="color:#999; font-size:0.85rem;">N/A (Item)</span>
                                
                                <?php else: ?>
                                    <?php if ($rec['raw_amount'] < 30): ?>
                                        <span class="status-badge status-not_requested">Min RM30</span>
                                    
                                    <?php elseif ($rec['tax_status'] == 'Requested'): ?>
                                        <button class="btn-action btn-processing" disabled><i class="fas fa-clock"></i> Processing</button>
                                    
                                    <?php elseif ($rec['tax_status'] == 'Generated'): ?>
                                        <span class="status-badge status-generated">
                                            <i class="fas fa-envelope"></i> Sent via Email
                                        </span>

                                    <?php elseif ($rec['tax_status'] == 'Rejected'): ?>
                                        <span class="status-badge status-rejected">Rejected</span>

                                    <?php else: ?>
                                        <button onclick="handleRequest(<?php echo $rec['id']; ?>)" class="btn-action btn-tax">Request</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            
                            <td data-label="Action">
                                <button class="btn-action btn-view" onclick='openDetailModal(<?php echo json_encode($rec); ?>)'>View</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding: 40px; color: #999;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="recordModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Details</div>
            <button class="close-btn" onclick="closeDetailModal()">&times;</button>
        </div>
        <div id="modalContent"></div>
    </div>
</div>

<div id="profileModal" class="modal-overlay">
    <div class="modal-box" style="border-top-color: #f59e0b;">
        <div class="modal-header">
            <div class="modal-title">Update Profile</div>
            <button class="close-btn" onclick="closeProfileModal()">&times;</button>
        </div>
        <p style="color:#666; margin-bottom:20px;">LHDN requires your IC and Address for tax receipts.</p>
        <form method="POST" action="Track_Records.php">
            <div class="form-group">
                <label>IC Number</label>
                <input type="text" name="ic_number" required value="<?php echo htmlspecialchars($user_data['Donor_ICNumber'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="3" required><?php echo htmlspecialchars($user_data['Donor_Address1'] ?? ''); ?></textarea>
            </div>
            <button type="submit" name="update_profile" class="btn-filter" style="width:100%">Save & Continue</button>
        </form>
    </div>
</div>

<form id="directRequestForm" method="POST" style="display:none;">
    <input type="hidden" name="order_id" id="hidden_order_id">
    <input type="hidden" name="request_receipt" value="true">
</form>

<?php include 'footer.php'; ?>

<script>
    function openDetailModal(data) {
        var html = `
            <div style="margin-bottom:10px;"><strong>Ref:</strong> ${data.ref}</div>
            <div style="margin-bottom:10px;"><strong>Amount:</strong> ${data.details}</div>
            <div style="margin-bottom:10px;"><strong>Date:</strong> ${data.date}</div>
            <div style="margin-bottom:10px;"><strong>Status:</strong> ${data.status}</div>
        `;
        document.getElementById('modalContent').innerHTML = html;
        document.getElementById('recordModal').style.display = 'flex';
    }
    function closeDetailModal() { document.getElementById('recordModal').style.display = 'none'; }

    const isProfileComplete = <?php echo $is_profile_complete; ?>;
    function handleRequest(orderId) {
        if (isProfileComplete) {
            if(confirm("Request Tax Receipt?")) {
                document.getElementById('hidden_order_id').value = orderId;
                document.getElementById('directRequestForm').submit();
            }
        } else {
            document.getElementById('profileModal').style.display = 'flex';
        }
    }
    function closeProfileModal() { document.getElementById('profileModal').style.display = 'none'; }
    
    window.onclick = function(e) {
        if(e.target == document.getElementById('recordModal')) closeDetailModal();
        if(e.target == document.getElementById('profileModal')) closeProfileModal();
    }
</script>

</body>
</html>