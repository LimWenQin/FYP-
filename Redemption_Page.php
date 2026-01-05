<?php
session_start();
include 'dataconnection.php';

// -------------------------
// 1. 初始化与登录检查
// -------------------------
$current_donor_id = isset($_SESSION['donor_id']) ? $_SESSION['donor_id'] : null;
$donor_points = 0;
$donor_address_complete = false;
$donor_data = [];

if ($current_donor_id) {
    // A. 获取用户积分
    $stmt = $conn->prepare("SELECT Points_Total FROM point WHERE Donor_ID = ?");
    $stmt->bind_param("i", $current_donor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $donor_points = $row['Points_Total'];
    }
    $stmt->close();

    // B. 获取用户地址信息
    $stmt = $conn->prepare("SELECT * FROM donor WHERE Donor_ID = ?");
    $stmt->bind_param("i", $current_donor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $donor_data = $res->fetch_assoc();
    
    if (!empty($donor_data['Donor_Address1']) && 
        !empty($donor_data['Donor_City']) && 
        !empty($donor_data['Donor_State']) && 
        !empty($donor_data['Donor_PostalCode'])) {
        $donor_address_complete = true;
    }
    $stmt->close();
}

// -------------------------
// 2. 处理 POST 请求
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_donor_id) {
    
    // --- 场景 A: 更新地址 ---
    if (isset($_POST['update_address'])) {
        $addr1 = $_POST['addr1'];
        $addr2 = $_POST['addr2'];
        $city = $_POST['city'];
        $state = $_POST['state'];
        $zip = $_POST['zip'];

        $sql = "UPDATE donor SET Donor_Address1=?, Donor_Address2=?, Donor_City=?, Donor_State=?, Donor_PostalCode=? WHERE Donor_ID=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $addr1, $addr2, $city, $state, $zip, $current_donor_id);
        if ($stmt->execute()) {
            echo "<script>alert('Address updated successfully!'); window.location.href='Redemption_Page.php';</script>";
        }
        exit();
    }

    // --- 场景 B: 执行兑换 ---
    if (isset($_POST['redeem_item_id'])) {
        $item_id = $_POST['redeem_item_id'];
        
        $stmt = $conn->prepare("SELECT * FROM reward_item WHERE Reward_ID = ? AND Reward_Status = 'Active'");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item || $item['Reward_Stock'] <= 0 || $donor_points < $item['Reward_RequiredPoint'] || !$donor_address_complete) {
            echo "<script>alert('Error: Unable to redeem.');</script>";
        } else {
            $conn->begin_transaction();
            try {
                // 1. 扣分
                $new_points = $donor_points - $item['Reward_RequiredPoint'];
                $update_pt = $conn->prepare("UPDATE point SET Points_Total = ?, Points_Updated_At = NOW() WHERE Donor_ID = ?");
                $update_pt->bind_param("ii", $new_points, $current_donor_id);
                $update_pt->execute();

                // 2. 扣库存
                $conn->query("UPDATE reward_item SET Reward_Stock = Reward_Stock - 1 WHERE Reward_ID = $item_id");

                // 3. 创建订单 (默认状态 Processing)
                $insert_ord = $conn->prepare("INSERT INTO redemption_order (Redemption_Address1, Redemption_Address2, Redemption_Address3, Redemption_City, Redemption_State, Redemption_PostalCode, Redemption_Country, Redemption_ContactNumber, Redemption_PointsSpent, Redemption_Status, Redemption_Updated_At, Donor_ID, Reward_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Processing', NOW(), ?, ?)");
                
                $country = 'Malaysia'; $addr3 = '';
                $insert_ord->bind_param("ssssssssisi", $donor_data['Donor_Address1'], $donor_data['Donor_Address2'], $addr3, $donor_data['Donor_City'], $donor_data['Donor_State'], $donor_data['Donor_PostalCode'], $country, $donor_data['Donor_ContactNumber'], $item['Reward_RequiredPoint'], $current_donor_id, $item_id);
                $insert_ord->execute();

                $conn->commit();
                echo "<script>alert('Redemption Successful!'); window.location.href='Redemption_Page.php';</script>";
            } catch (Exception $e) {
                $conn->rollback();
                echo "<script>alert('Failed.');</script>";
            }
        }
    }

    // --- 场景 C: 确认收货 (User Click Received) ---
    if (isset($_POST['confirm_receive_id'])) {
        $order_id = $_POST['confirm_receive_id'];
        
        // 只有当状态是 'Shipped' 时，用户才能改为 'Completed'
        $stmt = $conn->prepare("UPDATE redemption_order SET Redemption_Status = 'Completed', Redemption_Updated_At = NOW() WHERE Redemption_ID = ? AND Donor_ID = ? AND Redemption_Status = 'Shipped'");
        $stmt->bind_param("ii", $order_id, $current_donor_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo "<script>alert('Thank you! Order marked as Completed.'); window.location.href='Redemption_Page.php';</script>";
        } else {
            echo "<script>alert('Error: Status could not be updated.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rewards Redemption - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-gray: #f5f5f5;
            --text-dark: #171717;
            --white: #ffffff;
            --success-green: #10b981;
            --warning-yellow: #f59e0b;
            --info-blue: #3b82f6;
        }

        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-gray); margin: 0; padding: 0; color: var(--text-dark); }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }

        /* Points Banner */
        .points-banner {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white; padding: 30px; border-radius: 12px;
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; box-shadow: 0 5px 15px rgba(220, 38, 38, 0.2);
        }
        .points-value { font-size: 3rem; font-weight: 800; }

        /* Tabs Navigation */
        .tabs { display: flex; gap: 20px; margin-bottom: 20px; border-bottom: 2px solid #ddd; }
        .tab-btn {
            padding: 10px 20px; cursor: pointer; font-weight: 600; font-size: 1.1rem;
            border: none; background: none; color: #777; position: relative;
        }
        .tab-btn.active { color: var(--primary-red); }
        .tab-btn.active::after {
            content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 3px; background: var(--primary-red);
        }

        /* Rewards Grid */
        .rewards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .reward-card { background: var(--white); border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.3s ease; display: flex; flex-direction: column; }
        .reward-card:hover { transform: translateY(-5px); }
        .reward-img { width: 100%; height: 200px; object-fit: cover; background: #eee; }
        .reward-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .reward-meta { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 15px; border-top: 1px solid #eee; }
        .btn-redeem { background-color: var(--primary-red); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 15px; }
        .btn-redeem:hover { background-color: var(--dark-red); }
        .btn-redeem:disabled { background-color: #ccc; cursor: not-allowed; }

        /* History Table */
        .history-container { display: none; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { text-align: left; padding: 15px; background: #eee; color: #555; }
        .history-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; }
        .st-processing { background: #fef3c7; color: #92400e; }
        .st-shipped { background: #dbeafe; color: #1e40af; }
        .st-completed { background: #dcfce7; color: #166534; }

        .btn-receive {
            background-color: var(--success-green); color: white; border: none;
            padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold;
            display: flex; align-items: center; gap: 5px;
        }
        .btn-receive:hover { background-color: #059669; transform: scale(1.05); }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; position: relative; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; margin-bottom: 15px; }
    </style>
</head>
<body>

<?php include 'header_UI.php'; ?>

<div class="container">
    
    <div class="points-banner">
        <div>
            <h2>Rewards Center</h2>
            <div>Current Balance</div>
        </div>
        <div class="points-value"><?php echo number_format($donor_points); ?> PT</div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('redeem')">Redeem Items</button>
        <button class="tab-btn" onclick="switchTab('history')">My History</button>
    </div>
    
    <div id="redeem-tab" class="rewards-grid">
        <?php
        $sql = "SELECT * FROM reward_item WHERE Reward_Status = 'Active' ORDER BY Reward_RequiredPoint ASC";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $is_out_of_stock = $row['Reward_Stock'] <= 0;
                ?>
                <div class="reward-card">
                    <img src="<?php echo htmlspecialchars($row['Reward_PhotoPath']); ?>" class="reward-img">
                    <div class="reward-body">
                        <div style="font-weight:bold; font-size:1.1rem;"><?php echo htmlspecialchars($row['Reward_ItemName']); ?></div>
                        <div style="color:#666; font-size:0.9rem; margin-top:5px; flex-grow:1;"><?php echo htmlspecialchars($row['Reward_Description']); ?></div>
                        <div class="reward-meta">
                            <div style="color:var(--primary-red); font-weight:800;"><?php echo $row['Reward_RequiredPoint']; ?> PTS</div>
                            <div style="font-size:0.8rem; color:#888;"><?php echo $row['Reward_Stock']; ?> left</div>
                        </div>
                        <button class="btn-redeem" <?php echo $is_out_of_stock ? 'disabled' : ''; ?>
                            onclick="handleRedeem(<?php echo $row['Reward_ID']; ?>, <?php echo $row['Reward_RequiredPoint']; ?>, '<?php echo addslashes($row['Reward_ItemName']); ?>')">
                            <?php echo $is_out_of_stock ? "Out of Stock" : "Redeem Now"; ?>
                        </button>
                    </div>
                </div>
                <?php
            }
        } else { echo "<p>No rewards available.</p>"; }
        ?>
    </div>

    <div id="history-tab" class="history-container">
        <?php if ($current_donor_id): ?>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Date Redeemed</th>
                        <th>Points</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // 联合查询订单和物品表
                    $h_sql = "SELECT ro.*, ri.Reward_ItemName, ri.Reward_PhotoPath 
                              FROM redemption_order ro 
                              JOIN reward_item ri ON ro.Reward_ID = ri.Reward_ID 
                              WHERE ro.Donor_ID = ? 
                              ORDER BY ro.Redemption_Updated_At DESC";
                    $h_stmt = $conn->prepare($h_sql);
                    $h_stmt->bind_param("i", $current_donor_id);
                    $h_stmt->execute();
                    $h_res = $h_stmt->get_result();

                    if ($h_res->num_rows > 0):
                        while ($hist = $h_res->fetch_assoc()):
                            $status = $hist['Redemption_Status']; // Processing, Shipped, Completed
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <img src="<?php echo $hist['Reward_PhotoPath']; ?>" style="width:40px; height:40px; object-fit:cover; border-radius:4px;">
                                        <strong><?php echo htmlspecialchars($hist['Reward_ItemName']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo date("d M Y", strtotime($hist['Redemption_Updated_At'])); ?></td>
                                <td style="font-weight:bold; color:var(--primary-red);">-<?php echo $hist['Redemption_PointsSpent']; ?></td>
                                <td>
                                    <?php 
                                        if($status == 'Processing') echo '<span class="status-badge st-processing">Processing</span>';
                                        elseif($status == 'Shipped') echo '<span class="status-badge st-shipped">Shipped</span>';
                                        elseif($status == 'Completed') echo '<span class="status-badge st-completed">Completed</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($status == 'Shipped'): ?>
                                        <form method="POST" onsubmit="return confirm('Confirm that you have received this item?');">
                                            <input type="hidden" name="confirm_receive_id" value="<?php echo $hist['Redemption_ID']; ?>">
                                            <button type="submit" class="btn-receive">
                                                <i class="fas fa-check-circle"></i> Receive
                                            </button>
                                        </form>
                                    <?php elseif ($status == 'Completed'): ?>
                                        <span style="color:green; font-size:0.9rem;"><i class="fas fa-check"></i> Received</span>
                                    <?php else: ?>
                                        <span style="color:#999; font-size:0.9rem;">Wait for shipment</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; 
                    else: ?>
                        <tr><td colspan="5" style="text-align:center;">No redemption history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align:center; padding:20px;">Please login to view history.</p>
        <?php endif; ?>
    </div>
</div>

<div id="addressModal" class="modal-overlay">
    <div class="modal-box">
        <button onclick="document.getElementById('addressModal').style.display='none'" style="position:absolute; right:20px; top:15px; border:none; background:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        <h3>Shipping Address Required</h3>
        <form method="POST">
            <input type="hidden" name="update_address" value="1">
            <div class="form-group"><label>Address Line 1</label><input type="text" name="addr1" class="form-control" required value="<?php echo $donor_data['Donor_Address1']??''; ?>"></div>
            <div class="form-group"><label>Address Line 2</label><input type="text" name="addr2" class="form-control" value="<?php echo $donor_data['Donor_Address2']??''; ?>"></div>
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1"><label>City</label><input type="text" name="city" class="form-control" required value="<?php echo $donor_data['Donor_City']??''; ?>"></div>
                <div class="form-group" style="flex:1"><label>State</label><input type="text" name="state" class="form-control" required value="<?php echo $donor_data['Donor_State']??''; ?>"></div>
            </div>
            <div class="form-group"><label>Postal Code</label><input type="text" name="zip" class="form-control" required value="<?php echo $donor_data['Donor_PostalCode']??''; ?>"></div>
            <button type="submit" class="btn-redeem">Save & Continue</button>
        </form>
    </div>
</div>

<form id="redeemForm" method="POST" style="display:none;"><input type="hidden" name="redeem_item_id" id="hidden_redeem_id"></form>

<script>
    const isLoggedIn = <?php echo $current_donor_id ? 'true' : 'false'; ?>;
    const isAddressComplete = <?php echo $donor_address_complete ? 'true' : 'false'; ?>;
    const currentPoints = <?php echo $donor_points; ?>;

    // Tab 切换逻辑
    function switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('redeem-tab').style.display = 'none';
        document.getElementById('history-tab').style.display = 'none';

        if (tabName === 'redeem') {
            document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
            document.getElementById('redeem-tab').style.display = 'grid'; // grid for items
        } else {
            document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            document.getElementById('history-tab').style.display = 'block'; // block for table
        }
    }

    function handleRedeem(itemId, reqPoints, name) {
        if (!isLoggedIn) { alert("Please login."); window.location.href='donor_login.php'; return; }
        if (currentPoints < reqPoints) { alert("Insufficient points!"); return; }
        if (!isAddressComplete) { document.getElementById('addressModal').style.display = 'flex'; return; }
        if (confirm("Redeem '" + name + "'?")) {
            document.getElementById('hidden_redeem_id').value = itemId;
            document.getElementById('redeemForm').submit();
        }
    }
</script>

<?php include 'footer.php'; ?>

</body>
</html>