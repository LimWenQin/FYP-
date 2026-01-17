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
        !empty($donor_data['Donor_PostalCode'])) {
        $donor_address_complete = true;
    }
    $stmt->close();
}

// -------------------------
// 2. 处理 POST 请求
// -------------------------
$alert_script = ""; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_donor_id) {
    
    // 功能一：地址更新处理 (保留)
    if (isset($_POST['update_address'])) {
        $addr1 = $_POST['addr1']; $addr2 = $_POST['addr2']; $city = $_POST['city']; $state = $_POST['state']; $zip = $_POST['zip'];
        $sql = "UPDATE donor SET Donor_Address1=?, Donor_Address2=?, Donor_City=?, Donor_State=?, Donor_PostalCode=? WHERE Donor_ID=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $addr1, $addr2, $city, $state, $zip, $current_donor_id);
        if ($stmt->execute()) {
            $alert_script = "Swal.fire({ title: 'Success!', text: 'Address updated!', icon: 'success', confirmButtonColor: '#dc2626' }).then(() => { window.location.href='Redemption_Page.php'; });";
        }
    }

    // 功能二：兑换请求处理 (保留，已包含 Low Stock 逻辑)
    if (isset($_POST['redeem_item_id'])) {
        $item_id = $_POST['redeem_item_id'];
        $stmt = $conn->prepare("SELECT * FROM reward_item WHERE Reward_ID = ? AND (Reward_Status = 'Active' OR Reward_Status = 'Low Stock')");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item || $item['Reward_Stock'] <= 0 || $donor_points < $item['Reward_RequiredPoint'] || !$donor_address_complete) {
            $alert_script = "Swal.fire('Error', 'Unable to redeem. Please check your points or stock.', 'error');";
        } else {
            $conn->begin_transaction();
            try {
                $new_points = $donor_points - $item['Reward_RequiredPoint'];
                $conn->query("UPDATE point SET Points_Total = $new_points, Points_Updated_At = NOW() WHERE Donor_ID = $current_donor_id");
                $conn->query("UPDATE reward_item SET Reward_Stock = Reward_Stock - 1 WHERE Reward_ID = $item_id");
                
                $insert_ord = $conn->prepare("INSERT INTO redemption_order (Redemption_Address1, Redemption_Address2, Redemption_City, Redemption_State, Redemption_PostalCode, Redemption_Country, Redemption_ContactNumber, Redemption_PointsSpent, Redemption_Status, Redemption_Updated_At, Donor_ID, Reward_ID) VALUES (?, ?, ?, ?, ?, 'Malaysia', ?, ?, 'Processing', NOW(), ?, ?)");
                $insert_ord->bind_param("ssssssiii", $donor_data['Donor_Address1'], $donor_data['Donor_Address2'], $donor_data['Donor_City'], $donor_data['Donor_State'], $donor_data['Donor_PostalCode'], $donor_data['Donor_ContactNumber'], $item['Reward_RequiredPoint'], $current_donor_id, $item_id);
                $insert_ord->execute();
                
                $conn->commit();
                $alert_script = "Swal.fire({ title: 'Redemption Successful!', text: 'Your reward is being processed.', icon: 'success', confirmButtonColor: '#dc2626' }).then(() => { window.location.href='Redemption_Page.php'; });";
            } catch (Exception $e) {
                $conn->rollback();
                $alert_script = "Swal.fire('Failed', 'Transaction failed.', 'error');";
            }
        }
    }

    // ⭐ 新增功能：确认收货处理 (用于更新数据库状态)
    if (isset($_POST['confirm_receive_id'])) {
        $order_id = $_POST['confirm_receive_id'];
        // 只有状态为 Shipped 的订单才能被改为 Completed
        $stmt = $conn->prepare("UPDATE redemption_order SET Redemption_Status = 'Completed', Redemption_Updated_At = NOW() WHERE Redemption_ID = ? AND Donor_ID = ? AND Redemption_Status = 'Shipped'");
        $stmt->bind_param("ii", $order_id, $current_donor_id);
        if ($stmt->execute()) {
            $alert_script = "Swal.fire({ title: 'Order Received!', text: 'Thank you for your confirmation.', icon: 'success' }).then(() => { window.location.href='Redemption_Page.php'; });";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rewards Redemption - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary-red: #dc2626; --dark-red: #b91c1c; --light-gray: #f5f5f5; --text-dark: #171717; --white: #ffffff; --success-green: #10b981; --warning-orange: #f59e0b; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-gray); margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .points-banner { background: linear-gradient(135deg, var(--primary-red), var(--dark-red)); color: white; padding: 30px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .points-value { font-size: 3rem; font-weight: 800; }
        .tabs { display: flex; gap: 20px; margin-bottom: 20px; border-bottom: 2px solid #ddd; }
        .tab-btn { padding: 10px 20px; cursor: pointer; font-weight: 600; font-size: 1.1rem; border: none; background: none; color: #777; position: relative; }
        .tab-btn.active { color: var(--primary-red); }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 3px; background: var(--primary-red); }
        
        .rewards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .reward-card { background: var(--white); border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.3s ease; display: flex; flex-direction: column; position: relative; }
        .reward-card:not(.disabled):hover { transform: translateY(-5px); }
        .reward-card.disabled { opacity: 0.7; filter: grayscale(0.8); cursor: not-allowed; }
        .reward-card.disabled .btn-redeem { background-color: #999 !important; pointer-events: none; }
        
        .stock-badge { position: absolute; top: 10px; left: 10px; padding: 5px 12px; border-radius: 5px; font-size: 0.75rem; font-weight: bold; color: white; z-index: 2; text-transform: uppercase; }
        .badge-out { background: #444; } 
        .badge-inactive { background: #dc2626; } 
        .badge-low { background: var(--warning-orange); box-shadow: 0 2px 4px rgba(0,0,0,0.2); } 

        .reward-img { width: 100%; height: 200px; object-fit: cover; background: #eee; }
        .reward-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .reward-meta { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 15px; border-top: 1px solid #eee; }
        .btn-redeem { background-color: var(--primary-red); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 15px; transition: background 0.2s; }

        .history-container { display: none; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { text-align: left; padding: 15px; background: #eee; color: #555; }
        .history-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; }
        .st-processing { background: #fef3c7; color: #92400e; }
        .st-shipped { background: #dbeafe; color: #1e40af; }
        .st-completed { background: #dcfce7; color: #166534; }
        .st-cancelled { background: #fee2e2; color: #991b1b; }

        /* 新增：确认收货按钮样式 */
        .btn-received { 
            background-color: var(--success-green); 
            color: white; border: none; 
            padding: 8px 15px; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
        }
        .btn-received:hover { background-color: #059669; transform: translateY(-1px); }
    </style>
</head>
<body>

<?php include 'header_UI.php'; ?>

<div class="container">
    <div class="points-banner">
        <div><h2>Rewards Center</h2><div>Current Balance</div></div>
        <div class="points-value"><?php echo number_format($donor_points); ?> PT</div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('redeem')">Redeem Items</button>
        <button class="tab-btn" onclick="switchTab('history')">My History</button>
    </div>
    
    <div id="redeem-tab" class="rewards-grid">
        <?php
        $sql = "SELECT * FROM reward_item ORDER BY FIELD(Reward_Status, 'Active', 'Low Stock', 'Inactive') ASC, Reward_RequiredPoint ASC";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $status = $row['Reward_Status'];
            $stock = (int)$row['Reward_Stock'];
            $is_inactive = ($status === 'Inactive');
            $is_out_of_stock = ($stock <= 0);
            $is_low_stock = ($status === 'Low Stock' || ($stock > 0 && $stock <= 10));
            $can_redeem = (!$is_inactive && !$is_out_of_stock);
            
            $raw_path = $row['Reward_PhotoPath'];
            $clean_path = str_replace('\\', '/', $raw_path);
            $final_src = (strpos($clean_path, 'reward_') === 0) ? "uploads/rewards/" . $clean_path : $clean_path;

            $badge_html = "";
            $btn_text = "Redeem Now";
            $card_class = $can_redeem ? "" : "disabled";

            if ($is_inactive) {
                $badge_html = '<div class="stock-badge badge-inactive">Not Available</div>';
                $btn_text = "Not Available";
            } elseif ($is_out_of_stock) {
                $badge_html = '<div class="stock-badge badge-out">Out of Stock</div>';
                $btn_text = "Out of Stock";
            } elseif ($is_low_stock) {
                $badge_html = '<div class="stock-badge badge-low">Low Stock</div>';
            }
            ?>
            <div class="reward-card <?php echo $card_class; ?>">
                <?php echo $badge_html; ?>
                <img src="<?php echo htmlspecialchars($final_src); ?>" class="reward-img" onerror="this.src='images/hero_3.jpg';">
                <div class="reward-body">
                    <div style="font-weight:bold; font-size:1.1rem;"><?php echo htmlspecialchars($row['Reward_ItemName']); ?></div>
                    <div style="color:#666; font-size:0.9rem; margin-top:5px; flex-grow:1;"><?php echo htmlspecialchars($row['Reward_Description']); ?></div>
                    <div class="reward-meta">
                        <div style="color:var(--primary-red); font-weight:800;"><?php echo $row['Reward_RequiredPoint']; ?> PTS</div>
                        <div style="font-size:0.8rem; <?php echo $is_low_stock ? 'color:var(--warning-orange); font-weight:bold;' : 'color:#888;'; ?>">
                            <?php echo $stock; ?> left
                        </div>
                    </div>
                    <button class="btn-redeem" <?php echo !$can_redeem ? 'disabled' : ''; ?>
                        onclick="handleRedeem(<?php echo $row['Reward_ID']; ?>, <?php echo $row['Reward_RequiredPoint']; ?>, '<?php echo addslashes($row['Reward_ItemName']); ?>')">
                        <?php echo $btn_text; ?>
                    </button>
                </div>
            </div>
        <?php } ?>
    </div>

    <div id="history-tab" class="history-container">
        <?php if ($current_donor_id): ?>
        <table class="history-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Date Redeemed</th>
                    <th>Points spent</th>
                    <th>Status</th> 
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $h_stmt = $conn->prepare("SELECT ro.*, ri.Reward_ItemName, ri.Reward_PhotoPath FROM redemption_order ro JOIN reward_item ri ON ro.Reward_ID = ri.Reward_ID WHERE ro.Donor_ID = ? ORDER BY ro.Redemption_Updated_At DESC");
                $h_stmt->bind_param("i", $current_donor_id);
                $h_stmt->execute();
                $h_res = $h_stmt->get_result();

                if ($h_res->num_rows > 0):
                    while ($hist = $h_res->fetch_assoc()):
                        $status = $hist['Redemption_Status'];
                        $h_path = str_replace('\\', '/', $hist['Reward_PhotoPath']);
                        $h_src = (strpos($h_path, 'reward_') === 0) ? "uploads/rewards/" . $h_path : $h_path;
                ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <img src="<?php echo $h_src; ?>" style="width:50px; height:50px; object-fit:cover; border-radius:5px;" onerror="this.src='images/hero_3.jpg';">
                                <strong><?php echo htmlspecialchars($hist['Reward_ItemName']); ?></strong>
                            </div>
                        </td>
                        <td><?php echo date("d M Y", strtotime($hist['Redemption_Updated_At'])); ?></td>
                        <td style="color:var(--primary-red); font-weight:bold;">-<?php echo $hist['Redemption_PointsSpent']; ?> PT</td>
                        <td>
                            <?php 
                                // 动态显示状态标签
                                $status_lower = strtolower($status);
                                echo "<span class='status-badge st-$status_lower'>$status</span>";
                            ?>
                        </td>
                        <td>
                            <?php if ($status == 'Shipped'): ?>
                                <button type="button" class="btn-received" onclick="confirmReceive(<?php echo $hist['Redemption_ID']; ?>)">
                                    <i class="fas fa-box-open"></i> Confirm Received
                                </button>
                            <?php elseif ($status == 'Completed'): ?>
                                <span style="color:var(--success-green); font-weight:bold;"><i class="fas fa-check-circle"></i> Enjoy!</span>
                            <?php else: ?>
                                <span style="color:#999; font-size:0.85rem;">Processing...</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:50px; color:#999;">No history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<form id="redeemForm" method="POST" style="display:none;"><input type="hidden" name="redeem_item_id" id="hidden_id"></form>

<form id="receiveForm" method="POST" style="display:none;">
    <input type="hidden" name="confirm_receive_id" id="hidden_recv_id">
</form>

<script>
    const isLoggedIn = <?php echo $current_donor_id ? 'true' : 'false'; ?>;
    const isAddressComplete = <?php echo $donor_address_complete ? 'true' : 'false'; ?>;
    const currentPoints = <?php echo $donor_points; ?>;

    // Tab 切换逻辑 (保留)
    function switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('redeem-tab').style.display = (tabName === 'redeem' ? 'grid' : 'none');
        document.getElementById('history-tab').style.display = (tabName === 'history' ? 'block' : 'none');
        event.currentTarget.classList.add('active');
    }

    // 兑换逻辑 (保留)
    function handleRedeem(itemId, reqPoints, name) {
        if (!isLoggedIn) { 
            Swal.fire('Login Required', 'Please login to redeem rewards.', 'warning').then(() => window.location.href='donor_login.php');
            return; 
        }
        if (!isAddressComplete) {
            Swal.fire('Address Required', 'Please complete your address in profile.', 'info').then(() => window.location.href='donor_profile.php');
            return;
        }
        if (currentPoints < reqPoints) {
            Swal.fire('Insufficient Points', 'You do not have enough points.', 'error');
            return;
        }

        Swal.fire({
            title: 'Confirm Redemption',
            text: `Redeem "${name}" for ${reqPoints} points?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Yes, Redeem!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('hidden_id').value = itemId;
                document.getElementById('redeemForm').submit();
            }
        });
    }

    // ⭐ 新增：确认收货逻辑
    function confirmReceive(orderId) {
        Swal.fire({
            title: 'Confirm Received?',
            text: 'Have you received the reward item? This action will mark the order as Completed.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Yes, I received it!'
        }).then(res => {
            if (res.isConfirmed) {
                document.getElementById('hidden_recv_id').value = orderId;
                document.getElementById('receiveForm').submit();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => { <?php if (!empty($alert_script)) echo $alert_script; ?> });
</script>

<?php include 'footer.php'; ?>
</body>
</html>