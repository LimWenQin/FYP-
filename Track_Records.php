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
// 2. 处理 POST 请求
// ==========================================

// A. 处理：更新个人资料
if (isset($_POST['update_profile'])) {
    $ic = trim($_POST['ic_number']);
    $addr1 = trim($_POST['addr1']);
    $addr2 = trim($_POST['addr2']);
    $addr3 = trim($_POST['addr3']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $zip = trim($_POST['zip']);
    $country = trim($_POST['country']);
    
    if(!empty($ic) && !empty($addr1) && !empty($city) && !empty($state) && !empty($zip)){
        $sql = "UPDATE donor SET 
                Donor_ICNumber=?, 
                Donor_Address1=?, Donor_Address2=?, Donor_Address3=?, 
                Donor_City=?, Donor_State=?, Donor_PostalCode=?, Donor_Country=? 
                WHERE Donor_ID=?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi", $ic, $addr1, $addr2, $addr3, $city, $state, $zip, $country, $current_donor_id);
        
        if ($stmt->execute()) {
            echo "<script>alert('Profile updated successfully!'); window.location.href='Track_Records.php';</script>";
        } else {
            echo "<script>alert('Update failed.');</script>";
        }
    }
}

// B. 处理：申请报税收据 (保持原逻辑)
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

// 获取用户资料
$user_sql = "SELECT * FROM donor WHERE Donor_ID = ?";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("i", $current_donor_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();

$is_profile_complete = (
    !empty($user_data['Donor_ICNumber']) && 
    !empty($user_data['Donor_Address1']) && 
    !empty($user_data['Donor_City']) && 
    !empty($user_data['Donor_State']) && 
    !empty($user_data['Donor_PostalCode'])
) ? 'true' : 'false';

// 获取筛选参数
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'All';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'All';

$records = [];

// A. 获取现金捐款记录
$is_cash_related = ($filter_type == 'All' || $filter_type == 'TNG eWallet' || $filter_type == 'Credit/Debit Card' || $filter_type == 'Cash' || $filter_type == 'E-Wallet'); 

if ($is_cash_related) {
    $sql_orders = "SELECT Order_ID, Order_TXN_Ref, Order_Amount, Order_Status, Order_Created_At, Order_PaymentMethod, Tax_Receipt_Status, 'Cash' as Record_Type 
                   FROM orders 
                   WHERE Donor_ID = ? AND Order_Status != 'Failed'";
    
    if ($filter_type != 'All') {
        $sql_orders .= " AND Order_PaymentMethod LIKE '%$filter_type%'";
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

// B. 获取物品捐赠记录
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

// C. 排序
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
        /* 样式保持不变，略去 ... */
        :root { --primary-red: #dc2626; --dark-red: #b91c1c; --light-red: #fee2e2; --white: #ffffff; --light-gray: #f5f5f5; --medium-gray: #737373; --dark-gray: #262626; --text-dark: #171717; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--light-gray); color: var(--text-dark); margin: 0; padding: 0; }
        .main-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; min-height: 80vh; }
        .page-header { text-align: center; margin-bottom: 40px; }
        .page-title { font-size: 2.5rem; color: var(--primary-red); font-weight: 800; margin-bottom: 15px; position: relative; display: inline-block; }
        .page-title::after { content: ''; position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); width: 80px; height: 4px; background: var(--primary-red); border-radius: 2px; }
        .page-subtitle { font-size: 1.1rem; color: var(--medium-gray); }
        .filter-card { background: var(--white); padding: 25px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); margin-bottom: 30px; border-left: 5px solid var(--primary-red); display: flex; flex-wrap: wrap; gap: 20px; align-items: center; justify-content: space-between; }
        .filter-group { display: flex; gap: 15px; align-items: center; }
        .filter-label { font-weight: 700; color: var(--text-dark); }
        .custom-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; outline: none; min-width: 150px; }
        .btn-filter { background-color: var(--primary-red); color: white; padding: 10px 25px; border: none; border-radius: 5px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn-filter:hover { background-color: var(--dark-red); }
        .table-card { background: var(--white); border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); overflow-x: auto; }
        .styled-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .styled-table thead tr { background-color: var(--primary-red); color: var(--white); }
        .styled-table th, .styled-table td { padding: 18px 15px; border-bottom: 1px solid #eee; text-align: center; vertical-align: middle; }
        .styled-table th { font-weight: 600; font-size: 0.9rem; text-transform: uppercase; }
        .styled-table tbody tr:hover { background-color: var(--light-gray); }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .status-completed, .status-success, .status-generated { background-color: #dcfce7; color: #166534; }
        .status-pending, .status-requested { background-color: #fef3c7; color: #92400e; }
        .status-failed, .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-not_requested { background-color: #f3f4f6; color: #9ca3af; } 
        
        /* [修改] 按钮样式 - 去掉 View 按钮的 JS 相关样式，保留外观 */
        .btn-action { padding: 6px 12px; border-radius: 5px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn-view { background: white; border: 1px solid var(--primary-red); color: var(--primary-red); }
        .btn-view:hover { background: var(--primary-red); color: white; text-decoration: none; }
        
        .btn-tax { background: var(--primary-red); color: white; border: 1px solid var(--primary-red); }
        .btn-tax:hover { background: var(--dark-red); }
        .btn-processing { background: #fbbf24; color: white; cursor: default; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .modal-box { background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; border-top: 6px solid var(--primary-red); position: relative; }
        .modal-title { font-size: 1.5rem; font-weight: 800; color: var(--primary-red); margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .close-btn { position: absolute; right: 20px; top: 15px; border: none; background: none; font-size: 1.8rem; cursor: pointer; color: #999; }
        .close-btn:hover { color: #333; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #333; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .form-control:focus { border-color: var(--primary-red); outline: none; }

        @media (max-width: 768px) {
            .styled-table thead { display: none; }
            .styled-table tr { display: block; margin-bottom: 20px; border: 1px solid #eee; background: white; border-radius: 8px; }
            .styled-table td { display: flex; justify-content: space-between; text-align: right; padding: 12px; border-bottom: 1px solid #f5f5f5; }
            .styled-table td::before { content: attr(data-label); font-weight: 700; color: #777; margin-right: auto;}
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
                    <option value="Cash" <?php echo $filter_type=='Cash'?'selected':''; ?>>Cash / Manual</option>
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
                                    $icon = 'fa-money-bill'; // Default
                                    
                                    if (stripos($m, 'TNG') !== false) { $icon = 'fa-wallet'; }
                                    elseif (stripos($m, 'Card') !== false) { $icon = 'fa-credit-card'; }
                                    elseif (stripos($m, 'E-Wallet') !== false) { $icon = 'fa-wallet'; } // E-Wallet 绿色钱包
                                    elseif (stripos($m, 'Item') !== false) { $icon = 'fa-box-open'; }

                                    if (empty($m) && $rec['type'] == 'Cash') {
                                        $m = "Top-up / Unknown";
                                    }
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
                                <a href="View_Record.php?id=<?php echo $rec['id']; ?>&type=<?php echo $rec['type']; ?>" class="btn-action btn-view">
                                    View
                                </a>
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

<div id="profileModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-title">Complete Your Profile</div>
        <button class="close-btn" onclick="closeProfileModal()">&times;</button>
        
        <p style="margin-bottom: 20px; color: #666; font-size: 0.9rem;">
            To issue a valid Tax Exemption Receipt (LHDN requirement), we need your IC Number and Full Address.
        </p>
        
        <form method="POST" action="Track_Records.php">
            <input type="hidden" name="update_profile" value="1">

            <div class="form-group">
                <label>IC Number (MyKad) <span style="color:red">*</span></label>
                <input type="text" name="ic_number" class="form-control" required 
                       placeholder="e.g. 990101-01-1234"
                       value="<?php echo htmlspecialchars($user_data['Donor_ICNumber'] ?? ''); ?>">
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

            <div class="form-group">
                <label>Address Line 1 <span style="color:red">*</span></label>
                <input type="text" name="addr1" class="form-control" required 
                       placeholder="House No, Street Name"
                       value="<?php echo htmlspecialchars($user_data['Donor_Address1'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Address Line 2 (Optional)</label>
                <input type="text" name="addr2" class="form-control" 
                       placeholder="Apartment, Suite, Unit, etc."
                       value="<?php echo htmlspecialchars($user_data['Donor_Address2'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Address Line 3 (Optional)</label>
                <input type="text" name="addr3" class="form-control" 
                       placeholder="Landmark / Additional Info"
                       value="<?php echo htmlspecialchars($user_data['Donor_Address3'] ?? ''); ?>">
            </div>

            <div style="display: flex; gap: 15px;">
                <div class="form-group" style="flex:1">
                    <label>City <span style="color:red">*</span></label>
                    <input type="text" name="city" class="form-control" required 
                           value="<?php echo htmlspecialchars($user_data['Donor_City'] ?? ''); ?>">
                </div>
                <div class="form-group" style="flex:1">
                    <label>State <span style="color:red">*</span></label>
                    <input type="text" name="state" class="form-control" required 
                           value="<?php echo htmlspecialchars($user_data['Donor_State'] ?? ''); ?>">
                </div>
            </div>

            <div style="display: flex; gap: 15px;">
                <div class="form-group" style="flex:1">
                    <label>Postal Code <span style="color:red">*</span></label>
                    <input type="text" name="zip" class="form-control" required 
                           value="<?php echo htmlspecialchars($user_data['Donor_PostalCode'] ?? ''); ?>">
                </div>
                <div class="form-group" style="flex:1">
                    <label>Country <span style="color:red">*</span></label>
                    <input type="text" name="country" class="form-control" required 
                           value="<?php echo htmlspecialchars($user_data['Donor_Country'] ?? 'Malaysia'); ?>">
                </div>
            </div>

            <button type="submit" class="btn-filter" style="width:100%; margin-top: 10px;">Save & Continue</button>
        </form>
    </div>
</div>

<form id="directRequestForm" method="POST" style="display:none;">
    <input type="hidden" name="order_id" id="hidden_order_id">
    <input type="hidden" name="request_receipt" value="true">
</form>

<?php include 'footer.php'; ?>

<script>
    const isProfileComplete = <?php echo $is_profile_complete; ?>;
    
    function handleRequest(orderId) {
        if (isProfileComplete) {
            if(confirm("Request Tax Receipt for this donation?")) {
                document.getElementById('hidden_order_id').value = orderId;
                document.getElementById('directRequestForm').submit();
            }
        } else {
            document.getElementById('profileModal').style.display = 'flex';
        }
    }
    
    function closeProfileModal() { document.getElementById('profileModal').style.display = 'none'; }
    
    window.onclick = function(e) {
        if(e.target == document.getElementById('profileModal')) closeProfileModal();
    }
</script>

</body>
</html>