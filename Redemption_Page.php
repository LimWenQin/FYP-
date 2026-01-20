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
    // 获取用户积分
    $stmt = $conn->prepare("SELECT Points_Total FROM point WHERE Donor_ID = ?");
    $stmt->bind_param("i", $current_donor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $donor_points = $row['Points_Total'];
    }
    $stmt->close();

    // 获取用户地址信息
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
// 2. 处理 POST 请求 (保留所有功能)
// -------------------------
$alert_script = ""; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_donor_id) {
    
    // 功能一：地址更新处理 (已保留)
    if (isset($_POST['update_address'])) {
        $addr1 = $_POST['addr1']; $addr2 = $_POST['addr2']; $city = $_POST['city']; $state = $_POST['state']; $zip = $_POST['zip'];
        $sql = "UPDATE donor SET Donor_Address1=?, Donor_Address2=?, Donor_City=?, Donor_State=?, Donor_PostalCode=? WHERE Donor_ID=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $addr1, $addr2, $city, $state, $zip, $current_donor_id);
        if ($stmt->execute()) {
            $alert_script = "Swal.fire({ title: 'Success!', text: 'Address updated!', icon: 'success', confirmButtonColor: '#dc2626' }).then(() => { window.location.href='Redemption_Page.php'; });";
        }
    }

    // 功能二：兑换请求处理 (加入 7 天限制检查)
    if (isset($_POST['redeem_item_id'])) {
        $item_id = $_POST['redeem_item_id'];

        // ⭐ 新增安全校验：检查 7 天内是否已兑换过
        $check_recent = $conn->prepare("SELECT COUNT(*) as total FROM redemption_order WHERE Donor_ID = ? AND Reward_ID = ? AND Redemption_Status != 'Cancelled' AND Redemption_Created_At >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $check_recent->bind_param("ii", $current_donor_id, $item_id);
        $check_recent->execute();
        $count_res = $check_recent->get_result()->fetch_assoc();
        
        if ($count_res['total'] > 0) {
            $alert_script = "Swal.fire('Limit Reached', 'This handicraft item is limited to one redemption every 7 days.', 'warning');";
        } else {
            // 原有兑换事务
            $stmt = $conn->prepare("SELECT * FROM reward_item WHERE Reward_ID = ? AND (Reward_Status = 'Active' OR Reward_Status = 'Low Stock')");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if (!$item || $item['Reward_Stock'] <= 0 || $donor_points < $item['Reward_RequiredPoint'] || !$donor_address_complete) {
                $alert_script = "Swal.fire('Error', 'Unable to redeem. Please check your points or stock.', 'error');";
            } else {
                $conn->begin_transaction();
                try {
                    $new_points = $donor_points - $item['Reward_RequiredPoint'];
                    $conn->query("UPDATE point SET Points_Total = $new_points, Points_Updated_At = NOW() WHERE Donor_ID = $current_donor_id");
                    $conn->query("UPDATE reward_item SET Reward_Stock = Reward_Stock - 1 WHERE Reward_ID = $item_id");
                    
                    $insert_ord = $conn->prepare("INSERT INTO redemption_order (Redemption_Address1, Redemption_Address2, Redemption_City, Redemption_State, Redemption_PostalCode, Redemption_Country, Redemption_ContactNumber, Redemption_PointsSpent, Redemption_Status, Redemption_Updated_At, Donor_ID, Reward_ID, Redemption_Created_At) VALUES (?, ?, ?, ?, ?, 'Malaysia', ?, ?, 'Processing', NOW(), ?, ?, NOW())");
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
    }

    // ⭐ 功能三：确认收货处理 (已完整保留)
    if (isset($_POST['confirm_receive_id'])) {
        $order_id = $_POST['confirm_receive_id'];
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
        .reward-card.disabled { opacity: 0.8; filter: grayscale(0.5); }
        
        .stock-badge { position: absolute; top: 10px; left: 10px; padding: 5px 12px; border-radius: 5px; font-size: 0.75rem; font-weight: bold; color: white; z-index: 2; text-transform: uppercase; }
        .badge-out { background: #444; } 
        .badge-inactive { background: #999; } 
        .badge-low { background: var(--warning-orange); } 
        .badge-cooling { background: #6366f1; } /* 新增冷却期标签颜色 */

        .reward-img { width: 100%; height: 200px; object-fit: cover; background: #eee; }
        .reward-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        
        /* Note 提示框样式 */
        .limit-note { margin: 8px 0; padding: 10px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; font-size: 0.75rem; color: #92400e; line-height: 1.4; }

        .reward-meta { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 15px; border-top: 1px solid #eee; }
        .btn-redeem { background-color: var(--primary-red); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 15px; transition: 0.2s; }
        .btn-redeem:disabled { background-color: #ccc; cursor: not-allowed; }

        .history-container { display: none; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { text-align: left; padding: 15px; background: #eee; color: #555; }
        .history-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; }
        .st-processing { background: #fef3c7; color: #92400e; }
        .st-shipped { background: #dbeafe; color: #1e40af; }
        .st-completed { background: #dcfce7; color: #166534; }
        .st-cancelled { background: #fee2e2; color: #991b1b; }

        .btn-received { background-color: var(--success-green); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; }

        /* 新的蓝色警示框样式 */
.global-notice {
    background-color: #f0f7ff; /* 浅蓝色背景 */
    border: 1px solid #d1e9ff; /* 蓝色细边框 */
    padding: 18px 24px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

/* 圆形感叹号图标样式 */
.global-notice i.fa-info-circle {
    color: #2563eb; /* 蓝色图标 */
    font-size: 1.4rem;
    margin-top: 1px;
}

.notice-content {
    color: #1e40af; /* 深蓝色文字 */
    font-size: 0.95rem;
    line-height: 1.6;
}

.notice-content strong {
    color: #1e3a8a;
    font-weight: 700;
}
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

    <div class="global-notice">
        <i class="fas fa-info-circle"></i>
        <div class="notice-content">
            <strong>Note:</strong> To ensure all donors have a fair opportunity, 
            each item is limited to one redemption per donor every 7 days. 
            Handicraft items are available in limited quantities, thank you for your understanding.
        </div>
    </div>
    
    <div id="redeem-tab" class="rewards-grid">
        <?php
        $sql = "SELECT * FROM reward_item ORDER BY FIELD(Reward_Status, 'Active', 'Low Stock', 'Inactive') ASC, Reward_RequiredPoint ASC";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            
            // ⭐ 7天判定逻辑
            $user_redeemed_recently = false;
            $wait_days_left = 0;
            if ($current_donor_id) {
                $check_recent_stmt = $conn->prepare("SELECT Redemption_Created_At FROM redemption_order WHERE Donor_ID = ? AND Reward_ID = ? AND Redemption_Status != 'Cancelled' AND Redemption_Created_At >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY Redemption_Created_At DESC LIMIT 1");
                $check_recent_stmt->bind_param("ii", $current_donor_id, $row['Reward_ID']);
                $check_recent_stmt->execute();
                $recent_res = $check_recent_stmt->get_result();
                if ($recent_row = $recent_res->fetch_assoc()) {
                    $user_redeemed_recently = true;
                    $available_date = strtotime($recent_row['Redemption_Created_At']) + (7 * 86400);
                    $wait_days_left = ceil(($available_date - time()) / 86400);
                }
                $check_recent_stmt->close();
            }

            $status = $row['Reward_Status'];
            $stock = (int)$row['Reward_Stock'];
            $is_inactive = ($status === 'Inactive');
            $is_out_of_stock = ($stock <= 0);
            
            // 判定最终是否可兑换
            $can_redeem = (!$is_inactive && !$is_out_of_stock && !$user_redeemed_recently);
            
            $final_src = (strpos($row['Reward_PhotoPath'], 'reward_') === 0) ? "uploads/rewards/" . $row['Reward_PhotoPath'] : $row['Reward_PhotoPath'];

            $badge_html = "";
            $btn_text = "Redeem Now";
            $card_class = $can_redeem ? "" : "disabled";

            if ($is_inactive) {
                $badge_html = '<div class="stock-badge badge-inactive">Not Available</div>';
                $btn_text = "N/A";
            } elseif ($is_out_of_stock) {
                $badge_html = '<div class="stock-badge badge-out">Out of Stock</div>';
                $btn_text = "Out of Stock";
            } elseif ($user_redeemed_recently) {
                $badge_html = '<div class="stock-badge badge-cooling">Cooling Period</div>';
                $btn_text = "Wait " . $wait_days_left . " days";
            } elseif ($status === 'Low Stock') {
                $badge_html = '<div class="stock-badge badge-low">Low Stock</div>';
            }
            ?>
            <div class="reward-card <?php echo $card_class; ?>">
                <?php echo $badge_html; ?>
                <img src="<?php echo htmlspecialchars($final_src); ?>" class="reward-img" onerror="this.src='images/hero_3.jpg';">
                <div class="reward-body">
                    <div style="font-weight:bold; font-size:1.1rem;"><?php echo htmlspecialchars($row['Reward_ItemName']); ?></div>
                    
                    <div class="limit-note">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Handicraft items are limited. Each donor can redeem this item once every 7 days.
                    </div>

                    <div style="color:#666; font-size:0.85rem; margin-top:5px; flex-grow:1;"><?php echo htmlspecialchars($row['Reward_Description']); ?></div>
                    
                    <div class="reward-meta">
                        <div style="color:var(--primary-red); font-weight:800;"><?php echo $row['Reward_RequiredPoint']; ?> PTS</div>
                        <div style="font-size:0.8rem; color:#888;"><?php echo $stock; ?> left</div>
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
        <table class="history-table">
            <thead>
                <tr>
                    <th>Item</th><th>Date</th><th>Points</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($current_donor_id):
                $h_stmt = $conn->prepare("SELECT ro.*, ri.Reward_ItemName FROM redemption_order ro JOIN reward_item ri ON ro.Reward_ID = ri.Reward_ID WHERE ro.Donor_ID = ? ORDER BY ro.Redemption_Created_At DESC");
                $h_stmt->bind_param("i", $current_donor_id);
                $h_stmt->execute();
                $h_res = $h_stmt->get_result();
                while ($hist = $h_res->fetch_assoc()):
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($hist['Reward_ItemName']); ?></strong></td>
                        <td><?php echo date("d M Y", strtotime($hist['Redemption_Created_At'])); ?></td>
                        <td style="color:var(--primary-red);">-<?php echo $hist['Redemption_PointsSpent']; ?> PT</td>
                        <td><span class='status-badge st-<?php echo strtolower($hist['Redemption_Status']); ?>'><?php echo $hist['Redemption_Status']; ?></span></td>
                        <td>
                            <?php if ($hist['Redemption_Status'] == 'Shipped'): ?>
                                <button class="btn-received" onclick="confirmReceive(<?php echo $hist['Redemption_ID']; ?>)">
                                    <i class="fas fa-box-open"></i> Confirm Received
                                </button>
                            <?php elseif ($hist['Redemption_Status'] == 'Completed'): ?>
                                <span style="color:var(--success-green); font-weight:bold;"><i class="fas fa-check-circle"></i> Completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="redeemForm" method="POST" style="display:none;"><input type="hidden" name="redeem_item_id" id="hidden_id"></form>
<form id="receiveForm" method="POST" style="display:none;"><input type="hidden" name="confirm_receive_id" id="hidden_recv_id"></form>

<script>
    function switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('redeem-tab').style.display = (tabName === 'redeem' ? 'grid' : 'none');
        document.getElementById('history-tab').style.display = (tabName === 'history' ? 'block' : 'none');
        event.currentTarget.classList.add('active');
    }

    function handleRedeem(itemId, reqPoints, name) {
        if (<?php echo $donor_points; ?> < reqPoints) { Swal.fire('Error', 'Insufficient points.', 'error'); return; }
        if (!<?php echo $donor_address_complete ? 'true' : 'false'; ?>) { Swal.fire('Info', 'Complete address in profile first.', 'info'); return; }

        Swal.fire({
            title: 'Confirm?',
            text: `Redeem "${name}" for ${reqPoints} points?`,
            icon: 'question',
            showCancelButton: true, confirmButtonColor: '#dc2626'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('hidden_id').value = itemId;
                document.getElementById('redeemForm').submit();
            }
        });
    }

    // ⭐ 确认收货 JS 逻辑 (保留)
    function confirmReceive(orderId) {
        Swal.fire({ 
            title: 'Received?', 
            text: 'Have you received the item?',
            icon: 'question', 
            showCancelButton: true,
            confirmButtonColor: '#10b981'
        }).then(res => {
            if (res.isConfirmed) {
                document.getElementById('hidden_recv_id').value = orderId;
                document.getElementById('receiveForm').submit();
            }
        });
    }
    document.addEventListener('DOMContentLoaded', () => { <?php if (!empty($alert_script)) echo $alert_script; ?> });
</script>
</body>
</html>