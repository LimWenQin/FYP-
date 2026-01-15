<?php
session_start();
include 'dataconnection.php';

if (!isset($_SESSION['donor_id'])) {
    header("Location: donor_login.php");
    exit();
}

$current_donor_id = $_SESSION['donor_id'];

// 查询该用户所有已完成的捐款订单
$query = "SELECT o.*, p.Payment_Method 
          FROM orders o 
          JOIN payment p ON o.Payment_ID = p.Payment_ID 
          WHERE o.Donor_ID = ? AND o.Order_Status = 'Completed' 
          ORDER BY o.Order_Created_At DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Receipts - Love Bridge</title>
    <style>
        :root { --brand-red: #dc2626; --hover-orange: #f79c34; --bg-page: #f9fafb; --border-color: #e5e7eb; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg-page); margin: 0; padding: 0; }
        .container { max-width: 1000px; margin: 50px auto; padding: 20px; position: relative; }
        
        /* --- 新增：左上方返回按钮样式 --- */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            background-color: var(--brand-red);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.3s;
            box-shadow: 0 4px 6px rgba(220, 38, 38, 0.2);
            margin-bottom: 25px; /* 与下方标题拉开距离 */
        }
        .back-btn:hover {
            background-color: var(--hover-orange);
            transform: translateX(-5px); /* 微弱的向左位移动画 */
            box-shadow: 0 4px 8px rgba(247, 156, 52, 0.3);
            color: white;
        }

        .header { text-align: center; margin-bottom: 30px; }
        .receipt-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--brand-red); color: white; padding: 12px; text-align: left; }
        td { padding: 15px 12px; border-bottom: 1px solid var(--border-color); }
        .btn-dl { display: inline-flex; align-items: center; gap: 5px; color: var(--brand-red); text-decoration: none; font-weight: 600; border: 1px solid var(--brand-red); padding: 5px 12px; border-radius: 6px; transition: 0.2s; }
        .btn-dl:hover { background: var(--brand-red); color: white; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'header_UI.php'; ?>

    <div class="container">
        
        <a href="Recurring_Donation_Management_Panel.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Management
        </a>

        <div class="header">
            <h1 style="color: var(--brand-red);">My Donation History</h1>
            <p>Download your official receipts for tax deduction purposes.</p>
        </div>

        <div class="receipt-card">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference No</th>
                        <th>Project</th>
                        <th>Amount (RM)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <?php
                                // 确定项目名称
                                $project = "General Donation";
                                if($row['Case_ID']) {
                                    $c_stmt = $conn->prepare("SELECT Case_Title FROM special_case WHERE Case_ID = ?");
                                    $c_stmt->bind_param("i", $row['Case_ID']);
                                    $c_stmt->execute();
                                    $c = $c_stmt->get_result()->fetch_assoc();
                                    $project = $c['Case_Title'] ?? "Special Case";
                                } elseif($row['Activity_ID']) {
                                    $a_stmt = $conn->prepare("SELECT Activity_Name FROM activity WHERE Activity_ID = ?");
                                    $a_stmt->bind_param("i", $row['Activity_ID']);
                                    $a_stmt->execute();
                                    $a = $a_stmt->get_result()->fetch_assoc();
                                    $project = $a['Activity_Name'] ?? "Activity";
                                }
                            ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['Order_Created_At'])); ?></td>
                                <td><small><?php echo htmlspecialchars($row['Order_TXN_Ref']); ?></small></td>
                                <td><?php echo htmlspecialchars($project); ?></td>
                                <td><strong><?php echo number_format($row['Order_Amount'], 2); ?></strong></td>
                                <td>
                                    <a href="generate_receipt.php?order_id=<?php echo $row['Order_ID']; ?>" class="btn-dl">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                                        Download
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 30px;">You haven't made any donations yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>