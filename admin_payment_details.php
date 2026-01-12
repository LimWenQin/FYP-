<?php
// admin_payment_details.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    die("Access Denied. Please login.");
}

include 'dataconnection.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Order ID.");
}

$orderId = intval($_GET['id']);

// --- 修改：关联 Branch, Activity, Case 表以获取 Target 信息 ---
$sql = "SELECT o.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, 
        p.Payment_Method as Actual_Method, p.Payment_Status as Pay_Status, p.Payment_Bank_Name,
        b.Branch_Name, a.Activity_Name, s.Case_Title
        FROM orders o
        JOIN donor d ON o.Donor_ID = d.Donor_ID
        LEFT JOIN payment p ON o.Payment_ID = p.Payment_ID
        LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
        LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
        LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
        WHERE o.Order_ID = $orderId";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Order not found.");
}

$order = $result->fetch_assoc();

// Determine Status Color
$statusClass = 'text-warning';
$statusLower = strtolower($order['Order_Status']);
if($statusLower == 'completed' || $statusLower == 'success') $statusClass = 'text-success';
if($statusLower == 'failed' || $statusLower == 'cancelled') $statusClass = 'text-danger';

// --- Determine Donation Target ---
$targetLabel = "General Fund";
$targetName = "-";

if (!empty($order['Branch_Name'])) {
    $targetLabel = "Branch / Shelter";
    $targetName = $order['Branch_Name'];
} elseif (!empty($order['Activity_Name'])) {
    $targetLabel = "Event / Activity";
    $targetName = $order['Activity_Name'];
} elseif (!empty($order['Case_Title'])) {
    $targetLabel = "Special Case";
    $targetName = $order['Case_Title'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - <?php echo $order['Order_TXN_Ref']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        body { background: #f4f6f9; padding: 40px; font-family: 'Poppins', sans-serif; }
        
        /* 容器宽度 - 保持你的原始设计 1100px */
        .details-container { 
            max-width: 1100px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            overflow: hidden; 
        }

        .details-header { background: var(--primary); color: white; padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .details-header h2 { margin: 0; font-size: 24px; }
        
        .details-body { padding: 40px; }
        
        .info-group { margin-bottom: 35px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .info-group:last-child { border-bottom: none; }
        .info-group h3 { font-size: 18px; color: #555; margin-bottom: 20px; border-left: 5px solid var(--primary); padding-left: 15px; }
        
        /* 列表布局 */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 0; 
        }

        .info-item { 
            display: flex; 
            align-items: center; 
            border-bottom: 1px dashed #f0f0f0; 
            padding: 12px 0; 
        }
        
        /* Label & Colon & Value - 保持你的原始宽度设置 */
        .label { 
            width: 220px; 
            min-width: 220px; 
            color: #888; 
            font-weight: 500; 
            font-size: 15px; 
        }
        
        .colon {
            width: 20px;
            text-align: center;
            color: #888;
            font-weight: 500;
            margin-right: 15px; 
        }
        
        .value { 
            font-weight: 600; 
            color: #333; 
            font-size: 16px; 
            flex-grow: 1; 
        }
        
        .amount-display { font-size: 32px; color: var(--primary); font-weight: bold; text-align: center; margin: 10px 0 40px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        
        .text-success { color: #28a745; } .text-danger { color: #dc3545; } .text-warning { color: #856404; }

        .action-buttons {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-print { 
            background: #007bff; 
            color: white; 
            border: none; 
            padding: 12px 25px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        .btn-print:hover { background: #0056b3; }

        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
            text-decoration: none;
        }
        .btn-back:hover { background: #5a6268; }

        @media print {
            body { padding: 0; background: white; }
            .details-container { box-shadow: none; max-width: 100%; margin: 0; border-radius: 0; }
            .details-header { padding: 15px 20px; -webkit-print-color-adjust: exact; }
            .details-body { padding: 20px; }
            .btn-print, .btn-back { display: none; }
            .amount-display { border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="details-container">
        <div class="details-header">
            <h2>Payment Details</h2>
            <span>Order ID: #<?php echo $order['Order_ID']; ?></span>
        </div>
        <div class="details-body">
            <div class="amount-display">
                RM <?php echo number_format($order['Order_Amount'], 2); ?>
            </div>

            <div class="info-group">
                <h3>Transaction Information</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Donation For (Target)</span>
                        <span class="colon">:</span>
                        <span class="value">
                            <?php echo htmlspecialchars($targetName); ?> 
                            <span style="font-size: 13px; color: #888; font-weight: normal; margin-left: 5px;">(<?php echo $targetLabel; ?>)</span>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="label">Reference No</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Order_TXN_Ref']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Transaction Date</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Order_Created_At']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Payment Method</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Order_PaymentMethod']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Payment Status</span>
                        <span class="colon">:</span>
                        <span class="value <?php echo $statusClass; ?>"><?php echo strtoupper($order['Order_Status']); ?></span>
                    </div>
                </div>
            </div>

            <div class="info-group">
                <h3>Donor Details</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Donor Name</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($order['Donor_Name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Email Address</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($order['Donor_Email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Contact Number</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($order['Donor_ContactNumber']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Donor ID</span>
                        <span class="colon">:</span>
                        <span class="value">#<?php echo $order['Donor_ID']; ?></span>
                    </div>
                </div>
            </div>

            <div class="info-group">
                <h3>System & Rewards</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Order Type</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Order_Type']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Points Earned</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Order_Points_Earned']; ?> pts</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Tax Receipt</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Tax_Receipt_Status']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Last Updated At</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Order_Updated_At']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn-back" onclick="window.close();">
                    <i class="fas fa-arrow-left"></i> Back / Close
                </button>
                
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('.btn-back').onclick = function() {
            if (window.history.length > 1 && document.referrer.indexOf(window.location.host) !== -1) {
                window.history.back();
            } else {
                window.close();
            }
        };
    </script>
</body>
</html>