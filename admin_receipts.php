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
// 2. 获取管理员资料 (用于 Header/Sidebar 显示)
// ==========================================
$adminId = $_SESSION['admin_id'];
$adminName = 'Admin';
$adminPosition = 'Role';
$adminProfilePicture = 'images/default_profile.png'; // 默认图

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
// 3. 处理 Admin 的操作 (Approve / Reject)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $order_id = $_POST['order_id'];

    if ($action === 'approve') {
        // --- APPROVE 逻辑 ---
        
        // 1. 获取详细信息 (!!! 关键修复：加入了 o.Order_TXN_Ref !!!)
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
            // 2. 确定项目名称
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

            // 3. 发送邮件
            if (sendReceiptEmail($row, $project_name)) {
                $receipt_no = "REC-" . date("Y") . "-" . str_pad($order_id, 6, "0", STR_PAD_LEFT);
                $file_name = "receipt_" . $order_id . ".pdf";

                // 4. 更新数据库 (防止重复插入)
                $check_sql = "SELECT Receipt_ID FROM receipt WHERE Order_ID = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $order_id);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows == 0) {
                    // 只有不存在时才插入
                    $stmt_ins = $conn->prepare("INSERT INTO receipt (Receipt_Receipt_Number, Receipt_Generated_At, Receipt_Receipt_File, Donor_ID, Order_ID) VALUES (?, NOW(), ?, ?, ?)");
                    $stmt_ins->bind_param("ssii", $receipt_no, $file_name, $row['Donor_ID'], $order_id);
                    $stmt_ins->execute();
                    $stmt_ins->close();
                }
                $check_stmt->close();

                // 更新 Order 状态
                $conn->query("UPDATE orders SET Tax_Receipt_Status = 'Generated' WHERE Order_ID = $order_id");

                echo "<script>alert('Receipt Approved & Sent Successfully!'); window.location.href='admin_receipts.php';</script>";
            } else {
                echo "<script>alert('Error: Email sending failed. Please check your internet connection or SMTP settings.'); window.location.href='admin_receipts.php';</script>";
            }
        }

    } elseif ($action === 'reject') {
        // --- REJECT 逻辑 ---
        $conn->query("UPDATE orders SET Tax_Receipt_Status = 'Rejected' WHERE Order_ID = $order_id");
        echo "<script>alert('Request Rejected.'); window.location.href='admin_receipts.php';</script>";
    }
}

// ==========================================
// 4. 获取数据列表 (Requested 状态)
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
    <link rel="stylesheet" href="admin_common.css">
    
    <style>
        /* UI Styling */
        .receipt-management { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; color: #333; margin: 0; }
        .badge-danger { background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 10px; }

        .receipt-search { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ccc; border-radius: 5px; outline: none; background: white; font-size: 14px; }
        .search-input:focus { border-color: #F28585; }
        
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; font-size: 13px; color: white; }
        .btn-primary { background: #F28585; }
        .btn-primary:hover { background: #e07474; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-info { background: #17a2b8; }
        
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table th, .custom-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .custom-table th { font-weight: 600; color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; background: #fff; border-bottom: 2px solid #eee; }
        .custom-table tbody tr:hover { background-color: #fcfcfc; }
        
        .txn-highlight { background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-weight: bold; color: #856404; font-size: 12px; }
        .text-muted { color: #888; font-size: 12px; display: block; margin-top: 3px; }
        .amount-text { color: #28a745; font-weight: bold; font-size: 15px; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1100; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 500px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); animation: fadeIn 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #eee; background: #F28585; color: white; border-radius: 10px 10px 0 0; }
        .modal-header h2 { font-size: 16px; font-weight: 600; margin: 0; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: white; opacity: 0.8; }
        .close-btn:hover { opacity: 1; }
        .modal-body { padding: 20px; max-height: 80vh; overflow-y: auto; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid #eee; text-align: right; background: #f9f9f9; border-radius: 0 0 10px 10px; }

        .info-group { margin-bottom: 15px; }
        .info-label { font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .info-value { font-size: 14px; color: #333; font-weight: 500; }
        .info-divider { height: 1px; background: #eee; margin: 15px 0; }
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

                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Ref / Date</th>
                            <th>Donor Info</th>
                            <th>Amount</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_pending->num_rows > 0): ?>
                            <?php while($row = $result_pending->fetch_assoc()): ?>
                                <tr>
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

                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Approve request and send email?');">
                                            <input type="hidden" name="order_id" value="<?php echo $row['Order_ID']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-success">
                                                <i class="fas fa-check"></i> Send
                                            </button>
                                        </form>

                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Reject request?');">
                                            <input type="hidden" name="order_id" value="<?php echo $row['Order_ID']; ?>">
                                            <button type="submit" name="action" value="reject" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px; color: #999;">No pending tax receipt requests found.</td>
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
            <div class="modal-body" id="modalBodyContent">
                </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // --- 1. Sidebar Logic ---
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        if (sidebar && mainContent) {
            sidebar.addEventListener('mouseenter', () => { 
                sidebar.classList.remove('collapsed'); 
                mainContent.classList.add('expanded'); 
            });
            sidebar.addEventListener('mouseleave', () => { 
                sidebar.classList.add('collapsed'); 
                mainContent.classList.remove('expanded'); 
            });
        }

        // --- 2. Modal Logic ---
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

                <div class="info-divider"></div>

                <div class="info-label">Donor Info (For Receipt)</div>
                <div class="info-group">
                    <div class="info-value"><strong>${data.Donor_Name}</strong></div>
                    <div class="info-value">IC: ${data.Donor_ICNumber}</div>
                    <div class="info-value">Email: ${data.Donor_Email}</div>
                    <div class="info-value">Contact: ${data.Donor_ContactNumber}</div>
                </div>
                
                <div class="info-label">Mailing Address</div>
                <div class="address-box">${fullAddress}</div>
            `;

            document.getElementById('modalBodyContent').innerHTML = html;
            document.getElementById('detailModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>

</body>
</html>