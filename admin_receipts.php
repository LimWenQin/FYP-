<?php
// admin_receipts.php
session_start();

// 1. Check Login
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';
require_once 'mail_receipt.php'; 

// --- Helper: Build URL for Sorting & Filtering ---
function buildUrl($params = []) {
    $current = $_GET;
    $merged = array_merge($current, $params);
    return '?' . http_build_query($merged);
}

// ==========================================
// 2. Get Admin Data
// ==========================================
$adminId = $_SESSION['admin_id'];
$adminName = 'Admin';
$adminPosition = 'Role';
$adminProfilePicture = 'images/default_profile.png'; 

$stmt = $conn->prepare("SELECT Admin_Name, Admin_Role, Admin_ProfilePicture FROM admin WHERE Admin_ID = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $row = $res->fetch_assoc()) {
    $adminName = $row['Admin_Name'];
    $adminPosition = $row['Admin_Role'];
    if(!empty($row['Admin_ProfilePicture'])) {
        $adminProfilePicture = $row['Admin_ProfilePicture'];
    }
}
$stmt->close();

// ==========================================
// 3. Handle Admin Actions (Single & Bulk)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    // --- Function: Process Single Approve ---
    function processApprove($conn, $order_id) {
        $sql = "SELECT p.*, o.Order_ID, o.Order_TXN_Ref, o.Order_Amount, o.Order_Type, o.Order_Status, o.Branch_ID, o.Case_ID, o.Order_Created_At, o.Donor_ID,
                       d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_Address1, d.Donor_Address2, d.Donor_City, d.Donor_State, d.Donor_PostalCode
                FROM orders o 
                JOIN payment p ON o.Payment_ID = p.Payment_ID 
                JOIN donor d ON o.Donor_ID = d.Donor_ID
                WHERE o.Order_ID = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            $project_name = "Love Bridge Fund"; 
            if (!empty($row['Case_ID'])) {
                $c_stmt = $conn->prepare("SELECT Case_Title FROM special_case WHERE Case_ID = ?");
                $c_stmt->bind_param("i", $row['Case_ID']);
                $c_stmt->execute();
                if ($c_row = $c_stmt->get_result()->fetch_assoc()) $project_name = $c_row['Case_Title'];
                $c_stmt->close();
            } else if (!empty($row['Branch_ID'])) {
                $b_stmt = $conn->prepare("SELECT Branch_Name FROM branch WHERE Branch_ID = ?");
                $b_stmt->bind_param("i", $row['Branch_ID']);
                $b_stmt->execute();
                if ($b_row = $b_stmt->get_result()->fetch_assoc()) $project_name = $b_row['Branch_Name'];
                $b_stmt->close();
            }

            if (sendReceiptEmail($row, $project_name)) {
                $receipt_no = "REC-" . date("Y") . "-" . str_pad($order_id, 6, "0", STR_PAD_LEFT);
                $file_name = "receipt_" . $order_id . ".pdf";

                $check_sql = "SELECT Receipt_ID FROM receipt WHERE Order_ID = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $order_id);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows == 0) {
                    $stmt_ins = $conn->prepare("INSERT INTO receipt (Receipt_Receipt_Number, Receipt_Generated_At, Receipt_Receipt_File, Donor_ID, Order_ID) VALUES (?, NOW(), ?, ?, ?)");
                    $stmt_ins->bind_param("ssii", $receipt_no, $file_name, $row['Donor_ID'], $order_id);
                    $stmt_ins->execute();
                    $stmt_ins->close();
                }
                $check_stmt->close();
                $conn->query("UPDATE orders SET Tax_Receipt_Status = 'Generated' WHERE Order_ID = $order_id");
                return true; 
            }
        }
        return false; 
    }

    // --- CASE 1: Single Approve ---
    if ($action === 'approve') {
        if (processApprove($conn, $_POST['order_id'])) {
            $_SESSION['alert'] = ['type' => 'success', 'title' => 'Success!', 'text' => 'Receipt Sent Successfully!'];
        } else {
            $_SESSION['alert'] = ['type' => 'error', 'title' => 'Failed!', 'text' => 'Email sending failed.'];
        }
        header("Location: admin_receipts.php");
        exit();

    // --- CASE 2: Bulk Approve ---
    } elseif ($action === 'bulk_approve') {
        $id_array = explode(',', $_POST['order_ids']);
        $success_count = 0;
        foreach ($id_array as $oid) {
            if (intval($oid) > 0 && processApprove($conn, intval($oid))) {
                $success_count++;
            }
        }
        $_SESSION['alert'] = ['type' => 'success', 'title' => 'Batch Complete', 'text' => "$success_count receipts approved & sent."];
        header("Location: admin_receipts.php");
        exit();

    // --- CASE 3: Single Reject ---
    } elseif ($action === 'reject') {
        $order_id = $_POST['order_id'];
        $conn->query("UPDATE orders SET Tax_Receipt_Status = 'Rejected' WHERE Order_ID = $order_id");
        $_SESSION['alert'] = ['type' => 'success', 'title' => 'Rejected', 'text' => 'Request rejected successfully.'];
        header("Location: admin_receipts.php");
        exit();

    // --- CASE 4: Bulk Reject ---
    } elseif ($action === 'bulk_reject') {
        $ids_str = $conn->real_escape_string($_POST['order_ids']); 
        $id_array = explode(',', $_POST['order_ids']);
        $safe_ids = array_map('intval', $id_array);
        $ids_list = implode(',', $safe_ids);
        
        if (!empty($ids_list)) {
            $conn->query("UPDATE orders SET Tax_Receipt_Status = 'Rejected' WHERE Order_ID IN ($ids_list)");
            $_SESSION['alert'] = ['type' => 'success', 'title' => 'Batch Rejected', 'text' => count($safe_ids) . ' requests rejected.'];
        }
        header("Location: admin_receipts.php");
        exit();
    }
}

// ==========================================
// 4. Fetch Data with Sorting & Filtering
// ==========================================
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Filters
$f_payment = isset($_GET['f_payment']) ? $_GET['f_payment'] : '';
$f_date = isset($_GET['f_date']) ? $_GET['f_date'] : '';
$f_type = isset($_GET['f_type']) ? $_GET['f_type'] : ''; // Filter Type
$min_amount = isset($_GET['min_amount']) ? $_GET['min_amount'] : '';
$max_amount = isset($_GET['max_amount']) ? $_GET['max_amount'] : '';

$sql_pending = "SELECT o.Order_ID, o.Order_Amount, o.Order_Created_At, o.Order_TXN_Ref, o.Order_Type, o.Order_Status, o.Order_PaymentMethod,
                       d.Donor_Name, d.Donor_ICNumber, d.Donor_Email, d.Donor_ContactNumber, 
                       d.Donor_Address1, d.Donor_Address2, d.Donor_City, d.Donor_State, d.Donor_PostalCode,
                       p.Payment_Method, p.Payment_Status, p.Payment_TXN_Ref
                FROM orders o 
                JOIN donor d ON o.Donor_ID = d.Donor_ID 
                LEFT JOIN payment p ON o.Payment_ID = p.Payment_ID
                WHERE o.Tax_Receipt_Status = 'Requested'";

// Search Filter
if (!empty($search_query)) {
    $safe_search = $conn->real_escape_string($search_query);
    $sql_pending .= " AND (o.Order_TXN_Ref LIKE '%$safe_search%' OR d.Donor_Name LIKE '%$safe_search%' OR d.Donor_ICNumber LIKE '%$safe_search%')";
}

// Payment Method Filter
if (!empty($f_payment)) {
    $safe_pm = $conn->real_escape_string($f_payment);
    $sql_pending .= " AND o.Order_PaymentMethod = '$safe_pm'";
}

// Date Filter
if (!empty($f_date)) {
    $safe_date = $conn->real_escape_string($f_date);
    $sql_pending .= " AND DATE(o.Order_Created_At) = '$safe_date'";
}

// Type Filter
if (!empty($f_type)) {
    $safe_type = $conn->real_escape_string($f_type);
    $sql_pending .= " AND o.Order_Type = '$safe_type'";
}

// Amount Range Filter
if ($min_amount !== '') {
    $safe_min = floatval($min_amount);
    $sql_pending .= " AND o.Order_Amount >= $safe_min";
}
if ($max_amount !== '') {
    $safe_max = floatval($max_amount);
    $sql_pending .= " AND o.Order_Amount <= $safe_max";
}

// Sorting Logic
$sortMap = [
    'date' => 'o.Order_Created_At',
    'donor' => 'd.Donor_Name',
    'amount' => 'o.Order_Amount',
    'type' => 'o.Order_Type'
];
$orderByCol = isset($sortMap[$sort]) ? $sortMap[$sort] : 'o.Order_Created_At';
$orderDir = ($order === 'desc') ? 'DESC' : 'ASC';

$sql_pending .= " ORDER BY $orderByCol $orderDir";

$result_pending = $conn->query($sql_pending);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Receipt Requests - Love Bridge</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="admin_common.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #F28585;
            --primary-hover: #D96A6A;
            --secondary-bg: #f8f9fc;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e2e8f0;
            --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.02);
            --success-color: #10b981;
            --danger-color: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--secondary-bg);
            color: var(--text-dark);
        }

        /* --- Page Header Area --- */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .page-title h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge-pending {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* --- Card Container --- */
        .receipt-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 40px;
        }

        /* --- Toolbar (Search & Batch) --- */
        .toolbar-wrapper {
            padding: 20px;
            background: #fff;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
        }

        .search-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            max-width: 500px;
            position: relative;
        }

        /* 修复：只选择搜索图标，不影响右侧的Clear按钮图标 */
        .search-group .search-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            pointer-events: none; /* 让点击穿透到输入框 */
        }

        .search-input {
            flex: 1; /* 自动填满剩余空间，避免挤压按钮 */
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            outline: none;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(242, 133, 133, 0.15);
        }

        /* --- Batch Actions Area --- */
        .batch-actions {
            display: none; /* Default Hidden */
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .batch-actions.active {
            display: flex; /* Shown via JS when items checked */
        }

        /* --- Buttons --- */
        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary { background: var(--primary-color); color: white; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
        
        .btn-outline { background: white; border: 1px solid #d1d5db; color: var(--text-dark); }
        .btn-outline:hover { background: #f3f4f6; border-color: #9ca3af; }

        .btn-danger { background: #fee2e2; color: #b91c1c; }
        .btn-danger:hover { background: #fecaca; }

        .btn-success { background: #d1fae5; color: #047857; }
        .btn-success:hover { background: #a7f3d0; }

        /* --- Table Styling --- */
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer; /* Clickable for sort */
            user-select: none;
        }
        .custom-table th:hover { background-color: #f1f5f9; color: var(--primary-color); }

        .custom-table td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            color: var(--text-dark);
            font-size: 14px;
        }
        .custom-table tbody tr { transition: background-color 0.2s; }
        .custom-table tbody tr:hover { background-color: #fff7f7; }
        .selected-row { background-color: #fff1f1 !important; }

        /* --- Data Cells --- */
        .txn-ref {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #4b5563;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 4px;
        }
        .date-meta { font-size: 12px; color: #94a3b8; display: block; }
        
        .donor-name { font-weight: 600; color: #1e293b; font-size: 15px; }
        .donor-ic { font-size: 12px; color: #64748b; }
        
        .amount-display {
            font-size: 16px;
            font-weight: 700;
            color: var(--success-color);
            background: #ecfdf5;
            padding: 6px 12px;
            border-radius: 8px;
            display: inline-block;
        }

        /* --- Type Highlight (Replaced Status) --- */
        .type-highlight {
            background-color: #e0e7ff; /* Light Indigo */
            color: #4338ca;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }

        /* --- Action Buttons (In Row) --- */
        .action-group {
            display: flex;
            gap: 8px;
            justify-content: center; /* Center buttons in cell */
        }
        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-view { background: #e0f2fe; color: #0284c7; }
        .btn-approve { background: #dcfce7; color: #16a34a; }
        .btn-reject { background: #fee2e2; color: #dc2626; }

        /* --- Checkbox Logic --- */
        /* Note: style="display:none" is added inline in HTML to prevent flash on load */
        .checkbox-wrapper { display: flex; align-items: center; justify-content: center; }
        input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: var(--primary-color);
            cursor: pointer;
        }

        /* --- Sort Modal Styles --- */
        .sort-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .sort-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; }
        .sort-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .sort-header h3 { margin: 0; font-size: 16px; color: #333; }
        .sort-close { cursor: pointer; font-size: 20px; color: #999; }
        
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #555; text-decoration: none; }
        .sort-btn:hover { background: #f8f9fa; border-color: #ddd; color: var(--primary-color); }

        /* Filter Controls */
        .filter-label { font-size: 12px; font-weight: 600; color: #666; margin-top: 10px; display: block; margin-bottom: 4px; }
        .filter-select, .filter-input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; box-sizing: border-box; }
        .btn-apply { width: 100%; padding: 10px; background: var(--primary-color); color: white; border: none; border-radius: 5px; margin-top: 15px; cursor: pointer; font-weight: 600; }
        
        /* --- Detail Modal --- */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; }
        .modal-body-content { background: white; width: 90%; max-width: 600px; border-radius: 16px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .receipt-preview { border: 2px dashed #e2e8f0; border-radius: 12px; padding: 24px; background: #fafafa; }
        .receipt-row { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .divider { height: 1px; background: #e2e8f0; margin: 20px 0; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; color: #cbd5e1; }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            
            <div class="page-header">
                <div class="page-title">
                    <h2>
                        <i class="fas fa-file-invoice-dollar" style="color: var(--primary-color);"></i>
                        Tax Receipt Requests
                        <?php if($result_pending->num_rows > 0): ?>
                            <span class="badge-pending"><?php echo $result_pending->num_rows; ?> Pending</span>
                        <?php endif; ?>
                    </h2>
                </div>
            </div>

            <div class="receipt-card">
                <div class="toolbar-wrapper">
                    <form method="GET" action="" class="search-group">
                        <i class="fas fa-search search-icon"></i> <input type="text" name="search" class="search-input" placeholder="Search by TXN ID, Name, or IC..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <?php foreach($_GET as $key => $val) if($key != 'search') echo "<input type='hidden' name='$key' value='$val'>"; ?>
                        <?php if(!empty($search_query) || !empty($f_payment) || !empty($f_date) || !empty($f_type) || $min_amount != '' || $max_amount != ''): ?>
                            <a href="admin_receipts.php" class="btn btn-outline" style="padding: 10px 15px;"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>

                    <div class="batch-actions" id="batchToolbar">
                        <span style="font-size: 13px; font-weight: 600; color: #64748b;">Selected: <span class="sel-count">0</span></span>
                        
                        <button type="button" class="btn btn-danger" onclick="submitBatch('reject')">
                            <i class="fas fa-times"></i> Reject All
                        </button>
                        <button type="button" class="btn btn-success" onclick="submitBatch('approve')">
                            <i class="fas fa-check-double"></i> Send All
                        </button>
                    </div>

                    <button type="button" class="btn btn-outline" id="selectToggleBtn" onclick="toggleSelectMode()">
                        <i class="far fa-check-square"></i> Select
                    </button>
                </div>

                <form id="bulkForm" method="POST">
                    <input type="hidden" name="action" id="bulkActionInput">
                    <input type="hidden" name="order_ids" id="bulkOrderIdsInput">
                </form>

                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center; display:none;" class="checkbox-cell" id="th-checkbox">
                                <input type="checkbox" id="headerCheckbox" onclick="toggleSelectAll()">
                            </th>
                            
                            <th onclick="openSortModal('txnSortModal')">
                                Transaction Details <?php if($sort=='date') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            
                            <th onclick="openSortModal('donorSortModal')">
                                Donor Information <?php if($sort=='donor') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            
                            <th onclick="openSortModal('amountSortModal')">
                                Amount <?php if($sort=='amount') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>

                            <th onclick="openSortModal('typeSortModal')">
                                Donation Type <?php if($sort=='type') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_pending->num_rows > 0): ?>
                            <?php while($row = $result_pending->fetch_assoc()): ?>
                                <tr id="row-<?php echo $row['Order_ID']; ?>">
                                    <td class="checkbox-wrapper checkbox-cell" style="display:none;">
                                        <input type="checkbox" class="order-checkbox" value="<?php echo $row['Order_ID']; ?>" onclick="updateSelection()">
                                    </td>
                                    
                                    <td>
                                        <span class="txn-ref"><?php echo $row['Order_TXN_Ref']; ?></span>
                                        <span class="date-meta">
                                            <i class="far fa-calendar-alt"></i> <?php echo date("d M Y, h:i A", strtotime($row['Order_Created_At'])); ?>
                                        </span>
                                        <span class="date-meta" style="margin-top:2px;">
                                            <i class="far fa-credit-card"></i> <?php echo $row['Order_PaymentMethod']; ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <div class="donor-name"><?php echo htmlspecialchars($row['Donor_Name']); ?></div>
                                        <div class="donor-ic">IC: <?php echo $row['Donor_ICNumber']; ?></div>
                                        <div class="donor-ic" style="margin-top:2px;"><i class="far fa-envelope"></i> <?php echo htmlspecialchars($row['Donor_Email']); ?></div>
                                    </td>
                                    
                                    <td>
                                        <span class="amount-display">RM <?php echo number_format($row['Order_Amount'], 2); ?></span>
                                    </td>
                                    
                                    <td>
                                        <span class="type-highlight"><?php echo htmlspecialchars($row['Order_Type']); ?></span>
                                    </td>
                                    
                                    <td style="text-align: center;">
                                        <div class="action-group">
                                            <button type="button" class="btn-icon btn-view" title="View Details" onclick='openDetailModal(<?php echo json_encode($row); ?>)'>
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <form method="POST" style="display:inline;" class="approve-form">
                                                <input type="hidden" name="order_id" value="<?php echo $row['Order_ID']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="button" class="btn-icon btn-approve" title="Approve & Send" onclick="confirmApprove(this.form)">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </form>

                                            <form method="POST" style="display:inline;" class="reject-form">
                                                <input type="hidden" name="order_id" value="<?php echo $row['Order_ID']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="button" class="btn-icon btn-reject" title="Reject Request" onclick="confirmReject(this.form)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="far fa-folder-open"></i>
                                        <p>No pending tax receipt requests found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div id="txnSortModal" class="sort-modal" onclick="closeModal(event, 'txnSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter & Sort</h3><span class="sort-close" onclick="document.getElementById('txnSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'date', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-up"></i> Oldest to Newest</a>
            <a href="<?php echo buildUrl(['sort'=>'date', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-down"></i> Newest to Oldest</a>
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
            <form method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['f_payment', 'f_date'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                <label class="filter-label">Payment Method</label>
                <select name="f_payment" class="filter-select">
                    <option value="">All Methods</option>
                    <option value="TNG eWallet" <?php echo ($f_payment == 'TNG eWallet') ? 'selected' : ''; ?>>TNG eWallet</option>
                    <option value="System E-Wallet" <?php echo ($f_payment == 'System E-Wallet') ? 'selected' : ''; ?>>System E-Wallet</option>
                    <option value="Credit Card" <?php echo ($f_payment == 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                </select>
                <label class="filter-label">Filter Date</label>
                <input type="date" name="f_date" class="filter-input" value="<?php echo htmlspecialchars($f_date); ?>">
                <button type="submit" class="btn-apply">Apply Filters</button>
            </form>
        </div>
    </div>

    <div id="donorSortModal" class="sort-modal" onclick="closeModal(event, 'donorSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort Donor Name</h3><span class="sort-close" onclick="document.getElementById('donorSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'donor', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Name A to Z</a>
            <a href="<?php echo buildUrl(['sort'=>'donor', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Name Z to A</a>
        </div>
    </div>

    <div id="amountSortModal" class="sort-modal" onclick="closeModal(event, 'amountSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter Amount</h3><span class="sort-close" onclick="document.getElementById('amountSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'amount', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Small to Large</a>
            <a href="<?php echo buildUrl(['sort'=>'amount', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> Large to Small</a>
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
            <form method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['min_amount', 'max_amount'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                <div style="display: flex; gap: 10px;">
                    <div style="flex:1">
                        <label class="filter-label">Min (RM)</label>
                        <input type="number" name="min_amount" class="filter-input" step="0.01" value="<?php echo htmlspecialchars($min_amount); ?>">
                    </div>
                    <div style="flex:1">
                        <label class="filter-label">Max (RM)</label>
                        <input type="number" name="max_amount" class="filter-input" step="0.01" value="<?php echo htmlspecialchars($max_amount); ?>">
                    </div>
                </div>
                <button type="submit" class="btn-apply">Apply Range</button>
            </form>
        </div>
    </div>

    <div id="typeSortModal" class="sort-modal" onclick="closeModal(event, 'typeSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Filter & Sort Type</h3><span class="sort-close" onclick="document.getElementById('typeSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'type', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Type A to Z</a>
            <a href="<?php echo buildUrl(['sort'=>'type', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Type Z to A</a>
            
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
            
            <form method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['f_type'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                
                <label class="filter-label">Filter Type</label>
                <select name="f_type" class="filter-select">
                    <option value="">All Types</option>
                    <option value="One-time" <?php echo ($f_type == 'One-time') ? 'selected' : ''; ?>>One-time</option>
                    <option value="Monthly" <?php echo ($f_type == 'Monthly') ? 'selected' : ''; ?>>Monthly</option>
                </select>
                
                <button type="submit" class="btn-apply">Apply Filter</button>
            </form>
        </div>
    </div>

    <div class="modal" id="detailModal">
        <div class="modal-body-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0;"><i class="fas fa-receipt"></i> Request Details</h3>
                <button onclick="document.getElementById('detailModal').style.display='none'" style="border:none; background:none; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <div id="modalBodyContent"></div>
            <div style="text-align:right; margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
                <button class="btn btn-outline" onclick="document.getElementById('detailModal').style.display='none'">Close</button>
            </div>
        </div>
    </div>

    <script>
        // --- Sidebar Logic ---
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        if (sidebar && mainContent) {
            sidebar.addEventListener('mouseenter', () => { sidebar.classList.remove('collapsed'); mainContent.classList.add('expanded'); });
            sidebar.addEventListener('mouseleave', () => { sidebar.classList.add('collapsed'); mainContent.classList.remove('expanded'); });
        }

        // ==========================================
        // Modal Logic
        // ==========================================
        function openSortModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeModal(e, id) {
            if(e.target.id === id) document.getElementById(id).style.display = 'none';
        }

        function openDetailModal(data) {
            let fullAddress = data.Donor_Address1;
            if(data.Donor_Address2) fullAddress += ", " + data.Donor_Address2;
            fullAddress += ", " + data.Donor_PostalCode + " " + data.Donor_City + ", " + data.Donor_State;

            const html = `
                <div class="receipt-preview">
                    <div style="text-align:center; margin-bottom:20px;">
                        <span style="font-size:32px; font-weight:700; color:#10b981;">RM ${parseFloat(data.Order_Amount).toFixed(2)}</span>
                        <div style="margin-top:5px; color:#64748b; font-size:12px;">Donation Amount</div>
                    </div>
                    
                    <div class="receipt-row">
                        <span style="color:#64748b; font-size:13px;">Transaction Ref</span>
                        <span style="font-weight:600; font-size:14px;">${data.Order_TXN_Ref}</span>
                    </div>
                    <div class="receipt-row">
                        <span style="color:#64748b; font-size:13px;">Payment Method</span>
                        <span style="font-weight:600; font-size:14px;">${data.Order_PaymentMethod || 'Unknown'}</span>
                    </div>
                    <div class="receipt-row">
                        <span style="color:#64748b; font-size:13px;">Date</span>
                        <span style="font-weight:600; font-size:14px;">${data.Order_Created_At}</span>
                    </div>

                    <div class="divider"></div>

                    <div class="receipt-row">
                        <span style="color:#64748b; font-size:13px;">Donor Name</span>
                        <span style="font-weight:600; font-size:14px;">${data.Donor_Name}</span>
                    </div>
                    <div class="receipt-row">
                        <span style="color:#64748b; font-size:13px;">Email</span>
                        <span style="font-weight:600; font-size:14px;">${data.Donor_Email}</span>
                    </div>
                    
                    <div style="margin-top:15px; background:white; padding:10px; border:1px solid #eee; border-radius:5px;">
                        <span style="color:#64748b; font-size:12px;">Address:</span>
                        <div style="font-size:13px; color:#333;">${fullAddress}</div>
                    </div>
                </div>
            `;
            document.getElementById('modalBodyContent').innerHTML = html;
            document.getElementById('detailModal').style.display = 'flex';
        }

        // ==========================================
        // Select Mode & Batch Logic (FIXED)
        // ==========================================
        let selectionMode = false;
        let allSelected = false;

        // 切换 "Select" 模式
        function toggleSelectMode() {
            selectionMode = !selectionMode;
            const btn = document.getElementById('selectToggleBtn');
            const cells = document.querySelectorAll('.checkbox-cell');
            
            if (selectionMode) {
                // Show Checkboxes (Switch to table-cell)
                cells.forEach(c => c.style.display = 'table-cell');
                btn.innerHTML = '<i class="fas fa-times"></i> Cancel';
                btn.classList.add('btn-danger');
                btn.classList.remove('btn-outline');
            } else {
                // Hide Checkboxes
                cells.forEach(c => c.style.display = 'none');
                
                // Clear all selections
                const allCheckboxes = document.querySelectorAll('.order-checkbox');
                allCheckboxes.forEach(cb => {
                    cb.checked = false;
                    document.getElementById('row-' + cb.value).classList.remove('selected-row');
                });
                // Uncheck header checkbox
                document.getElementById('headerCheckbox').checked = false;
                
                btn.innerHTML = '<i class="far fa-check-square"></i> Select';
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-outline');
                
                // Hide batch toolbar
                updateSelection();
            }
        }

        // 全选功能
        function toggleSelectAll() {
            const masterCheckbox = document.getElementById('headerCheckbox');
            const isChecked = masterCheckbox.checked;
            const checkboxes = document.querySelectorAll('.order-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = isChecked;
            });
            updateSelection();
        }

        // 更新状态 (显示/隐藏按钮)
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
            const count = checkedBoxes.length;

            // Row Highlight Logic
            checkboxes.forEach(cb => {
                const row = document.getElementById('row-' + cb.value);
                if(cb.checked) row.classList.add('selected-row');
                else row.classList.remove('selected-row');
            });

            // Update Counter
            document.querySelectorAll('.sel-count').forEach(el => el.innerText = count);
            const batchToolbar = document.getElementById('batchToolbar');

            // Logic: Only show buttons if Select Mode is ON AND Count > 0
            if (count > 0 && selectionMode) {
                batchToolbar.classList.add('active');
            } else {
                batchToolbar.classList.remove('active');
            }
        }

        // Batch Action Submission
        function submitBatch(type) {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const ids = Array.from(checkboxes).map(cb => cb.value);
            
            if (ids.length === 0) return;

            const actionName = (type === 'approve') ? 'bulk_approve' : 'bulk_reject';
            const title = (type === 'approve') ? 'Approve Batch?' : 'Reject Batch?';
            const color = (type === 'approve') ? '#10b981' : '#ef4444';
            const text = (type === 'approve') 
                ? `Send receipts to ${ids.length} donors?` 
                : `Reject ${ids.length} requests?`;

            Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: color,
                confirmButtonText: 'Yes, Proceed'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('bulkActionInput').value = actionName;
                    document.getElementById('bulkOrderIdsInput').value = ids.join(',');
                    
                    if(type === 'approve') {
                        Swal.fire({title: 'Processing...', text:'Sending emails...', didOpen: () => Swal.showLoading()});
                    }
                    document.getElementById('bulkForm').submit();
                }
            });
        }

        // ==========================================
        // Single Confirmations
        // ==========================================
        function confirmApprove(form) {
            Swal.fire({
                title: 'Send Receipt?', 
                text: "Confirm details and send receipt via Email?", 
                icon: 'question',
                showCancelButton: true, 
                confirmButtonColor: '#10b981', 
                confirmButtonText: 'Yes, Send'
            }).then((res) => { 
                if (res.isConfirmed) { 
                    Swal.fire({title: 'Processing...', text:'Generating PDF...', didOpen: () => Swal.showLoading()});
                    form.submit(); 
                }
            });
        }

        function confirmReject(form) {
            Swal.fire({
                title: 'Reject Request?', 
                text: "Are you sure you want to reject this request?",
                icon: 'warning',
                showCancelButton: true, 
                confirmButtonColor: '#ef4444', 
                confirmButtonText: 'Yes, Reject'
            }).then((res) => { if (res.isConfirmed) form.submit(); });
        }

        // --- Alert from PHP Session ---
        <?php if (isset($_SESSION['alert'])): ?>
            Swal.fire({
                icon: '<?php echo $_SESSION['alert']['type']; ?>',
                title: '<?php echo $_SESSION['alert']['title']; ?>',
                text: '<?php echo $_SESSION['alert']['text']; ?>',
                confirmButtonColor: '#F28585'
            });
            <?php unset($_SESSION['alert']); ?>
        <?php endif; ?>
    </script>
</body>
</html>