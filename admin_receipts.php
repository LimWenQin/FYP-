<?php
// admin_receipts.php
session_start();

// 1. 检查登录
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';
require_once 'mail_receipt.php'; 

// ==========================================
// 2. 获取管理员资料
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
// 3. 处理 Admin 的操作 (Single & Bulk)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    // --- 辅助函数：处理单个批准 ---
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
            $_SESSION['alert'] = ['type' => 'success', 'title' => 'Success!', 'text' => 'Receipt Sent!'];
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
        $_SESSION['alert'] = ['type' => 'info', 'title' => 'Rejected', 'text' => 'Request rejected.'];
        header("Location: admin_receipts.php");
        exit();

    // --- CASE 4: Bulk Reject ---
    } elseif ($action === 'bulk_reject') {
        $ids_str = $conn->real_escape_string($_POST['order_ids']); // Basic sanitization
        // SQL IN clause needs simple comma separated list. explode/implode to be safe with integers
        $id_array = explode(',', $_POST['order_ids']);
        $safe_ids = array_map('intval', $id_array);
        $ids_list = implode(',', $safe_ids);
        
        if (!empty($ids_list)) {
            $conn->query("UPDATE orders SET Tax_Receipt_Status = 'Rejected' WHERE Order_ID IN ($ids_list)");
            $_SESSION['alert'] = ['type' => 'info', 'title' => 'Batch Rejected', 'text' => count($safe_ids) . ' requests rejected.'];
        }
        header("Location: admin_receipts.php");
        exit();
    }
}

// ==========================================
// 4. 获取数据列表
// ==========================================
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql_pending = "SELECT o.Order_ID, o.Order_Amount, o.Order_Created_At, o.Order_TXN_Ref, o.Order_Type, o.Order_Status,
                       d.Donor_Name, d.Donor_ICNumber, d.Donor_Email, d.Donor_ContactNumber, 
                       d.Donor_Address1, d.Donor_Address2, d.Donor_City, d.Donor_State, d.Donor_PostalCode,
                       p.Payment_Method, p.Payment_Status, p.Payment_TXN_Ref
                FROM orders o 
                JOIN donor d ON o.Donor_ID = d.Donor_ID 
                LEFT JOIN payment p ON o.Payment_ID = p.Payment_ID
                WHERE o.Tax_Receipt_Status = 'Requested'";

if (!empty($search_query)) {
    $sql_pending .= " AND (o.Order_TXN_Ref LIKE '%$search_query%' OR d.Donor_Name LIKE '%$search_query%' OR d.Donor_ICNumber LIKE '%$search_query%')";
}

$sql_pending .= " ORDER BY o.Order_Created_At ASC";
$result_pending = $conn->query($sql_pending);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Receipt Requests - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="admin_common.css">
    
    <style>
        .receipt-management { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; color: #333; margin: 0; }
        .badge-danger { background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 10px; }

        /* Search Bar */
        .receipt-search { display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px 8px 0 0; border: 1px solid #eee; border-bottom: none;}
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ccc; border-radius: 5px; outline: none; background: white; font-size: 14px; }
        
        /* Batch Toolbar (Located Below Search) */
        .batch-toolbar { 
            background: #fff; 
            padding: 10px; 
            border: 1px solid #eee; 
            border-top: 1px solid #f0f0f0;
            border-radius: 0 0 8px 8px; 
            margin-bottom: 20px;
            display: flex; 
            align-items: center; 
            gap: 15px;
        }

        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; font-size: 13px; color: white; }
        .btn-primary { background: #F28585; }
        .btn-primary:hover { background: #e07474; }
        .btn-secondary { background: #6c757d; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-info { background: #17a2b8; }
        
        /* Batch Buttons (Outline style) */
        .btn-outline-dark { background: transparent; border: 1px solid #333; color: #333; }
        .btn-outline-dark:hover { background: #333; color: white; }
        
        .btn-batch-approve { background: #28a745; color: white; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .btn-batch-reject { background: #dc3545; color: white; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        
        .batch-active { opacity: 1 !important; pointer-events: auto !important; }

        /* Checkbox Styling */
        input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #F28585; }

        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table th, .custom-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .custom-table th { font-weight: 600; color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; background: #fff; border-bottom: 2px solid #eee; }
        .custom-table tbody tr:hover { background-color: #fcfcfc; }
        .selected-row { background-color: #fff5f5 !important; }

        .txn-highlight { background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-weight: bold; color: #856404; font-size: 12px; }
        .text-muted { color: #888; font-size: 12px; display: block; margin-top: 3px; }
        .amount-text { color: #28a745; font-weight: bold; font-size: 15px; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1100; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 500px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); animation: fadeIn 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #eee; background: #F28585; color: white; border-radius: 10px 10px 0 0; }
        .modal-header h2 { font-size: 16px; font-weight: 600; margin: 0; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: white; opacity: 0.8; }
        .modal-body { padding: 20px; max-height: 80vh; overflow-y: auto; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid #eee; text-align: right; background: #f9f9f9; border-radius: 0 0 10px 10px; }
        .info-group { margin-bottom: 15px; }
        .info-label { font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .info-value { font-size: 14px; color: #333; font-weight: 500; }
        .address-box { background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 13px; border: 1px solid #eee; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            
            <div class="receipt-management">
                <div class="section-header">
                    <h2>Tax Receipt Requests <span class="badge-danger"><?php echo $result_pending->num_rows; ?> Pending</span></h2>
                </div>

                <form method="GET" action="" class="receipt-search">
                    <span style="font-weight:600; color:#555;">Search:</span>
                    <input type="text" name="search" class="search-input" placeholder="Enter TXN Ref, Donor Name, or IC Number..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($search_query)): ?><a href="admin_receipts.php" class="btn btn-secondary">Reset</a><?php endif; ?>
                </form>

                <div class="batch-toolbar">
                    <button type="button" class="btn btn-outline-dark" onclick="toggleSelectAll()">
                        <i class="far fa-check-square"></i> Select All
                    </button>
                    
                    <div style="flex:1;"></div> <button type="button" id="batchRejectBtn" class="btn btn-batch-reject" onclick="submitBatch('reject')">
                        <i class="fas fa-times"></i> Reject Selected (<span class="sel-count">0</span>)
                    </button>

                    <button type="button" id="batchApproveBtn" class="btn btn-batch-approve" onclick="submitBatch('approve')">
                        <i class="fas fa-check-double"></i> Approve Selected (<span class="sel-count">0</span>)
                    </button>
                </div>

                <form id="bulkForm" method="POST">
                    <input type="hidden" name="action" id="bulkActionInput">
                    <input type="hidden" name="order_ids" id="bulkOrderIdsInput">
                </form>

                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th> <th>Ref / Date</th>
                            <th>Donor Info</th>
                            <th>Amount</th>
                            <th style="text-align: center; width: 280px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_pending->num_rows > 0): ?>
                            <?php while($row = $result_pending->fetch_assoc()): ?>
                                <tr id="row-<?php echo $row['Order_ID']; ?>">
                                    <td>
                                        <input type="checkbox" class="order-checkbox" value="<?php echo $row['Order_ID']; ?>" onclick="updateSelection()">
                                    </td>
                                    
                                    <td>
                                        <div class="txn-highlight"><?php echo $row['Order_TXN_Ref']; ?></div>
                                        <span class="text-muted"><?php echo date("d M Y, h:i A", strtotime($row['Order_Created_At'])); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight:600; color:#333;"><?php echo htmlspecialchars($row['Donor_Name']); ?></div>
                                        <span class="text-muted">IC: <?php echo $row['Donor_ICNumber']; ?></span>
                                    </td>
                                    <td>
                                        <span class="amount-text">RM <?php echo number_format($row['Order_Amount'], 2); ?></span>
                                    </td>
                                    
                                    <td style="text-align: center;">
                                        <button type="button" class="btn btn-info" onclick='openModal(<?php echo json_encode($row); ?>)'>
                                            <i class="fas fa-eye"></i> View
                                        </button>

                                        <form method="POST" style="display:inline-block;" class="approve-form">
                                            <input type="hidden" name="order_id" value="<?php echo $row['Order_ID']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="button" class="btn btn-success" onclick="confirmApprove(this.form)">
                                                <i class="fas fa-check"></i> Send
                                            </button>
                                        </form>

                                        <form method="POST" style="display:inline-block;" class="reject-form">
                                            <input type="hidden" name="order_id" value="<?php echo $row['Order_ID']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="button" class="btn btn-danger" onclick="confirmReject(this.form)">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: #999;">No pending tax receipt requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-file-invoice"></i> Request Details</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBodyContent"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
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
        // Batch Action Logic (Select All & Buttons)
        // ==========================================
        let allSelected = false;

        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            allSelected = !allSelected; // Toggle state
            
            checkboxes.forEach(cb => {
                cb.checked = allSelected;
            });
            updateSelection();
        }

        function updateSelection() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
            const count = checkedBoxes.length;

            // Highlight rows
            checkboxes.forEach(cb => {
                const row = document.getElementById('row-' + cb.value);
                if(cb.checked) row.classList.add('selected-row');
                else row.classList.remove('selected-row');
            });

            // Update Buttons UI
            document.querySelectorAll('.sel-count').forEach(el => el.innerText = count);
            
            const approveBtn = document.getElementById('batchApproveBtn');
            const rejectBtn = document.getElementById('batchRejectBtn');

            if (count > 0) {
                approveBtn.classList.add('batch-active');
                rejectBtn.classList.add('batch-active');
            } else {
                approveBtn.classList.remove('batch-active');
                rejectBtn.classList.remove('batch-active');
                allSelected = false; // Reset flag if manually unchecked all
            }
        }

        function submitBatch(type) {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const ids = Array.from(checkboxes).map(cb => cb.value);
            
            if (ids.length === 0) return;

            const actionName = (type === 'approve') ? 'bulk_approve' : 'bulk_reject';
            const title = (type === 'approve') ? 'Approve Selected?' : 'Reject Selected?';
            const color = (type === 'approve') ? '#28a745' : '#dc3545';
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
                        Swal.fire({title: 'Processing...', text:'Sending emails, please wait.', didOpen: () => Swal.showLoading()});
                    }
                    document.getElementById('bulkForm').submit();
                }
            });
        }

        // ==========================================
        // Individual Logic
        // ==========================================
        function openModal(data) {
            let fullAddress = data.Donor_Address1;
            if(data.Donor_Address2) fullAddress += ", " + data.Donor_Address2;
            fullAddress += ", " + data.Donor_PostalCode + " " + data.Donor_City + ", " + data.Donor_State;

            const html = `
                <div style="text-align:center; margin-bottom:20px;">
                    <div style="font-size:24px; font-weight:700; color:#28a745;">RM ${parseFloat(data.Order_Amount).toFixed(2)}</div>
                    <span style="background:#eee; padding:3px 10px; border-radius:15px; font-size:12px; font-weight:bold;">${data.Payment_Status || 'Paid'}</span>
                </div>
                <div class="info-label">Transaction Info</div>
                <div class="info-group">
                    <div class="info-value">Ref: ${data.Order_TXN_Ref}</div>
                    <div class="info-value">Method: ${data.Payment_Method || 'Unknown'}</div>
                    <div class="info-value">Date: ${data.Order_Created_At}</div>
                </div>
                <div class="info-divider" style="height:1px; background:#eee; margin:15px 0;"></div>
                <div class="info-label">Donor Info</div>
                <div class="info-group">
                    <div class="info-value"><strong>${data.Donor_Name}</strong></div>
                    <div class="info-value">IC: ${data.Donor_ICNumber}</div>
                    <div class="info-value">Email: ${data.Donor_Email}</div>
                </div>
                <div class="info-label">Mailing Address</div>
                <div class="address-box">${fullAddress}</div>
            `;
            document.getElementById('modalBodyContent').innerHTML = html;
            document.getElementById('detailModal').style.display = 'flex';
        }

        function closeModal() { document.getElementById('detailModal').style.display = 'none'; }
        window.onclick = function(e) { if(e.target == document.getElementById('detailModal')) closeModal(); }

        function confirmApprove(form) {
            Swal.fire({
                title: 'Send Receipt?', text: "Generate PDF & Email?", icon: 'question',
                showCancelButton: true, confirmButtonColor: '#28a745', confirmButtonText: 'Yes'
            }).then((res) => { if (res.isConfirmed) { Swal.showLoading(); form.submit(); }});
        }

        function confirmReject(form) {
            Swal.fire({
                title: 'Reject Request?', icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Yes, Reject'
            }).then((res) => { if (res.isConfirmed) form.submit(); });
        }

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