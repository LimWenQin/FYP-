<?php
// admin_redemption_details.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    die("Access Denied. Please login.");
}

include 'dataconnection.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Redemption ID.");
}

$redemptionId = intval($_GET['id']);

// Fetch comprehensive redemption details
$sql = "SELECT r.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber,
        i.Reward_ItemName, i.Reward_Code, i.Reward_Category
        FROM redemption_order r
        JOIN donor d ON r.Donor_ID = d.Donor_ID
        JOIN reward_item i ON r.Reward_ID = i.Reward_ID
        WHERE r.Redemption_ID = $redemptionId";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Redemption Order not found.");
}

$order = $result->fetch_assoc();

// Determine Status Color
$statusClass = 'text-warning';
$statusLower = strtolower($order['Redemption_Status']);
if($statusLower == 'completed' || $statusLower == 'shipped' || $statusLower == 'delivered') $statusClass = 'text-success';
if($statusLower == 'cancelled' || $statusLower == 'rejected') $statusClass = 'text-danger'; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redemption Details - #<?php echo $order['Redemption_ID']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        body { background: #f4f6f9; padding: 40px; font-family: 'Poppins', sans-serif; }
        
        /* 容器宽度 */
        .details-container { 
            max-width: 1100px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            overflow: hidden; 
        }

        /* [修改点1]：背景色改为 var(--primary) 与 Payment 一致 */
        .details-header { 
            background: var(--primary); 
            color: white; 
            padding: 25px 40px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .details-header h2 { margin: 0; font-size: 24px; }
        
        .details-body { padding: 40px; }
        
        .info-group { margin-bottom: 35px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .info-group:last-child { border-bottom: none; }
        
        /* [修改点2]：左侧边框颜色改为 var(--primary) */
        .info-group h3 { 
            font-size: 18px; 
            color: #555; 
            margin-bottom: 20px; 
            border-left: 5px solid var(--primary); 
            padding-left: 15px; 
        }
        
        /* 列表布局 */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 0; 
        }

        .info-item { 
            display: flex; 
            align-items: flex-start; /* 顶部对齐，适应多行地址 */
            border-bottom: 1px dashed #f0f0f0; 
            padding: 12px 0; 
        }
        
        /* 对齐样式 */
        .label { 
            width: 220px;       
            min-width: 220px;   
            color: #888; 
            font-weight: 500; 
            font-size: 15px; 
            padding-top: 2px;
        }
        
        .colon {
            width: 20px;
            text-align: center;
            color: #888;
            font-weight: 500;
            margin-right: 15px;
            padding-top: 2px;
        }
        
        .value { 
            font-weight: 600; 
            color: #333; 
            font-size: 16px; 
            flex-grow: 1; 
            line-height: 1.6; 
        }
        
        .item-display { font-size: 28px; color: #333; font-weight: bold; text-align: center; margin-top: 10px; }
        .points-spent { text-align: center; color: #dc3545; font-weight: bold; font-size: 18px; margin-bottom: 40px; margin-top: 5px;}
        
        .text-success { color: #28a745; } .text-danger { color: #dc3545; } .text-warning { color: #856404; }

        .action-buttons {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Print Button (蓝色) */
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

        /* Back Button */
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
        }
    </style>
</head>
<body>
    <div class="details-container">
        <div class="details-header">
            <h2>Redemption Order Details</h2>
            <span>ID: #<?php echo $order['Redemption_ID']; ?></span>
        </div>
        <div class="details-body">
            <div class="item-display">
                <?php echo htmlspecialchars($order['Reward_ItemName']); ?>
            </div>
            <div class="points-spent">
                - <?php echo $order['Redemption_PointsSpent']; ?> Points
            </div>

            <div class="info-group">
                <h3>Order Status</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Current Status</span>
                        <span class="colon">:</span>
                        <span class="value <?php echo $statusClass; ?>"><?php echo strtoupper($order['Redemption_Status']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Tracking Number</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Redemption_TrackingNumber'] ? $order['Redemption_TrackingNumber'] : 'N/A'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Last Updated</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Redemption_Updated_At']; ?></span>
                    </div>
                </div>
            </div>

            <div class="info-group">
                <h3>Shipping Information</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Receiver Name</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($order['Donor_Name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Contact</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($order['Redemption_ContactNumber']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Address</span>
                        <span class="colon">:</span>
                        <span class="value">
                            <?php 
                            echo htmlspecialchars($order['Redemption_Address1']);
                            if($order['Redemption_Address2']) echo ", " . htmlspecialchars($order['Redemption_Address2']);
                            if($order['Redemption_Address3']) echo ", " . htmlspecialchars($order['Redemption_Address3']);
                            echo "<br>" . htmlspecialchars($order['Redemption_PostalCode']) . " " . htmlspecialchars($order['Redemption_City']);
                            echo ", " . htmlspecialchars($order['Redemption_State']);
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="info-group">
                <h3>Reward Item Details</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">Item Code</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Reward_Code']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Category</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $order['Reward_Category']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn-back" onclick="window.close();">
                    <i class="fas fa-arrow-left"></i> Back / Close
                </button>
                
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Order
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