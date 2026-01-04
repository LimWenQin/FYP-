<?php
session_start();
include 'dataconnection.php';
require_once 'mail_receipt.php'; 

// ==========================================
// 1. 处理 Admin 的操作 (Approve / Reject)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $order_id = $_POST['order_id'];

    if ($action === 'approve') {
        // --- APPROVE 逻辑 ---
        $sql = "SELECT p.*, o.Order_ID, o.Order_Amount, o.Order_Type, o.Order_Status, o.Branch_ID, o.Case_ID, o.Order_Created_At, o.Donor_ID,
                       d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_Address1, d.Donor_Address2, d.Donor_City, d.Donor_State, d.Donor_PostalCode
                FROM orders o 
                JOIN payment p ON o.Payment_ID = p.Payment_ID 
                JOIN donor d ON o.Donor_ID = d.Donor_ID
                WHERE o.Order_ID = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            // 确定项目名称
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

            // 发送邮件
            if (sendReceiptEmail($row, $project_name)) {
                $receipt_no = "REC-" . date("Y") . "-" . str_pad($order_id, 6, "0", STR_PAD_LEFT);
                $file_name = "receipt_" . $order_id . ".pdf";

                $conn->query("UPDATE orders SET Tax_Receipt_Status = 'Generated' WHERE Order_ID = $order_id");
                
                $stmt_ins = $conn->prepare("INSERT INTO receipt (Receipt_Receipt_Number, Receipt_Generated_At, Receipt_Receipt_File, Donor_ID, Order_ID) VALUES (?, NOW(), ?, ?, ?)");
                $stmt_ins->bind_param("ssii", $receipt_no, $file_name, $row['Donor_ID'], $order_id);
                $stmt_ins->execute();

                echo "<script>alert('Receipt Approved & Sent!');</script>";
            } else {
                echo "<script>alert('Error: Email sending failed.');</script>";
            }
        }

    } elseif ($action === 'reject') {
        // --- REJECT 逻辑 ---
        $conn->query("UPDATE orders SET Tax_Receipt_Status = 'Rejected' WHERE Order_ID = $order_id");
        
        // 获取邮箱发通知
        $stmt = $conn->prepare("SELECT d.Donor_Email, d.Donor_Name FROM orders o JOIN donor d ON o.Donor_ID = d.Donor_ID WHERE o.Order_ID = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $userData = $stmt->get_result()->fetch_assoc();
        
        mail($userData['Donor_Email'], "Tax Receipt Request Update", "Dear " . $userData['Donor_Name'] . ", your request has been declined.");
        echo "<script>alert('Request Rejected.');</script>";
    }
}

// ==========================================
// 2. 获取数据 (JOIN Payment 表以获取详细信息)
// ==========================================
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// 注意：这里我们需要 JOIN payment 表，因为 Details 弹窗里需要显示 Payment Method 和 TXN Ref
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
    <title>Admin - Tax Receipt Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; }
        .header-title { color: #dc2626; font-weight: bold; margin-bottom: 20px; }
        .table th { background-color: #333; color: #fff; }
        .btn-action { margin: 2px; }
        .txn-highlight { background-color: #fff3cd; padding: 2px 5px; border-radius: 4px; font-family: monospace; font-weight: bold; }
        
        /* Modal Styles matching Payment Settlement */
        .modal-header { background-color: #dc2626; color: white; }
        .group-title { font-size: 0.85rem; text-transform: uppercase; color: #999; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 15px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.95rem; }
        .label { color: #555; font-weight: 500; }
        .value { color: #000; font-weight: bold; text-align: right; }
    </style>
</head>
<body>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="header-title">Tax Receipt Management</h2>
        <a href="Homepage.php" class="btn btn-outline-secondary">Back to Home</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-center">
                <div class="col-auto"><label class="fw-bold">Verify Transaction:</label></div>
                <div class="col-auto flex-grow-1">
                    <input type="text" name="search" class="form-control" placeholder="Enter TXN Ref..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($search_query)): ?><a href="admin_receipts.php" class="btn btn-secondary">Reset</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Pending Requests</h5>
            <span class="badge bg-danger"><?php echo $result_pending->num_rows; ?> Pending</span>
        </div>
        <div class="card-body">
            <?php if ($result_pending->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Ref / Date</th>
                                <th>Donor Info</th>
                                <th>Amount</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result_pending->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="txn-highlight"><?php echo $row['Order_TXN_Ref']; ?></div>
                                        <small class="text-muted"><?php echo date("d M Y h:i A", strtotime($row['Order_Created_At'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['Donor_Name']); ?></strong><br>
                                        <small class="text-muted">IC: <?php echo $row['Donor_ICNumber']; ?></small>
                                    </td>
                                    <td class="fw-bold text-success">RM <?php echo number_format($row['Order_Amount'], 2); ?></td>
                                    
                                    <td class="text-center">
                                        <button type="button" class="btn btn-info btn-sm btn-action text-white" 
                                                onclick='openModal(<?php echo json_encode($row); ?>)'>
                                            <i class="fas fa-eye"></i> View
                                        </button>

                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Confirm action?');">
                                            <input type="hidden" name="order_id" value="<?php echo $row['Order_ID']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm btn-action">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm btn-action">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">No pending requests found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice"></i> Order Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalContent">
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openModal(data) {
        // 格式化完整地址
        let fullAddress = data.Donor_Address1;
        if(data.Donor_Address2) fullAddress += ", " + data.Donor_Address2;
        fullAddress += ", " + data.Donor_PostalCode + " " + data.Donor_City + ", " + data.Donor_State;

        let html = `
            <div class="text-center mb-3">
                <h3 style="color:#dc2626; font-weight:bold;">RM ${parseFloat(data.Order_Amount).toFixed(2)}</h3>
                <span class="badge bg-success">Status: ${data.Payment_Status || 'Paid'}</span>
            </div>

            <div class="group-title">Transaction Info</div>
            <div class="info-row"><span class="label">TXN Ref:</span> <span class="value">${data.Order_TXN_Ref}</span></div>
            <div class="info-row"><span class="label">Payment Method:</span> <span class="value">${data.Payment_Method || 'Unknown'}</span></div>
            <div class="info-row"><span class="label">Date:</span> <span class="value">${data.Order_Created_At}</span></div>
            
            <div class="group-title">Donor Details (For Receipt)</div>
            <div class="info-row"><span class="label">Name:</span> <span class="value">${data.Donor_Name}</span></div>
            <div class="info-row"><span class="label">IC Number:</span> <span class="value">${data.Donor_ICNumber}</span></div>
            <div class="info-row"><span class="label">Email:</span> <span class="value">${data.Donor_Email}</span></div>
            <div class="info-row"><span class="label">Contact:</span> <span class="value">${data.Donor_ContactNumber}</span></div>
            
            <div class="mt-2">
                <span class="label d-block mb-1">Mailing Address:</span>
                <div class="p-2 bg-light border rounded text-end" style="font-size:0.9rem;">${fullAddress}</div>
            </div>
        `;

        document.getElementById('modalContent').innerHTML = html;
        var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
        myModal.show();
    }
</script>

</body>
</html>