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
$alert_script = ""; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_donor_id) {
    
    if (isset($_POST['update_address'])) {
        $addr1 = $_POST['addr1']; $addr2 = $_POST['addr2']; $city = $_POST['city']; $state = $_POST['state']; $zip = $_POST['zip'];
        $sql = "UPDATE donor SET Donor_Address1=?, Donor_Address2=?, Donor_City=?, Donor_State=?, Donor_PostalCode=? WHERE Donor_ID=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $addr1, $addr2, $city, $state, $zip, $current_donor_id);
        if ($stmt->execute()) {
            $alert_script = "Swal.fire({ title: 'Success!', text: 'Address updated!', icon: 'success', confirmButtonColor: '#dc2626' }).then(() => { window.location.href='Redemption_Page.php'; });";
        }
    }

    if (isset($_POST['redeem_item_id'])) {
        $item_id = $_POST['redeem_item_id'];
        $stmt = $conn->prepare("SELECT * FROM reward_item WHERE Reward_ID = ? AND Reward_Status = 'Active'");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item || $item['Reward_Stock'] <= 0 || $donor_points < $item['Reward_RequiredPoint'] || !$donor_address_complete) {
            $alert_script = "Swal.fire('Error', 'Unable to redeem.', 'error');";
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

    if (isset($_POST['confirm_receive_id'])) {
        $order_id = $_POST['confirm_receive_id'];
        $stmt = $conn->prepare("UPDATE redemption_order SET Redemption_Status = 'Completed', Redemption_Updated_At = NOW() WHERE Redemption_ID = ? AND Donor_ID = ? AND Redemption_Status = 'Shipped'");
        $stmt->bind_param("ii", $order_id, $current_donor_id);
        if ($stmt->execute()) {
            $alert_script = "Swal.fire({ title: 'Completed!', text: 'Order marked as received.', icon: 'success' }).then(() => { window.location.href='Redemption_Page.php'; });";
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary-red: #dc2626; --dark-red: #b91c1c; --light-gray: #f5f5f5; --text-dark: #171717; --white: #ffffff; --success-green: #10b981; }
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
        .reward-card.disabled .btn-redeem { background-color: #999 !important; }
        .stock-badge { position: absolute; top: 10px; right: 10px; padding: 5px 10px; border-radius: 5px; font-size: 0.75rem; font-weight: bold; color: white; z-index: 2; }
        .badge-out { background: #444; }
        .badge-inactive { background: #991b1b; }
        .reward-img { width: 100%; height: 200px; object-fit: cover; background: #eee; }
        .reward-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .reward-meta { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 15px; border-top: 1px solid #eee; }
        .btn-redeem { background-color: var(--primary-red); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 15px; }

        /* History Table Styles */
        .history-container { display: none; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { text-align: left; padding: 15px; background: #eee; color: #555; }
        .history-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; }
        .st-processing { background: #fef3c7; color: #92400e; }
        .st-shipped { background: #dbeafe; color: #1e40af; }
        .st-completed { background: #dcfce7; color: #166534; }
        .btn-receive { background-color: var(--success-green); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 5px; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; position: relative; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; margin-bottom: 15px; }
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
        $sql = "SELECT * FROM reward_item ORDER BY FIELD(Reward_Status, 'Active') DESC, Reward_RequiredPoint ASC";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $is_active = ($row['Reward_Status'] == 'Active');
            $is_out_of_stock = ($row['Reward_Stock'] <= 0);
            $can_redeem = ($is_active && !$is_out_of_stock);
            
            // ⭐ 路径处理：将数据库的文件名补全为文件夹路径 ⭐
            $raw_path = $row['Reward_PhotoPath'];
            $clean_path = str_replace('\\', '/', $raw_path);
            $final_src = (strpos($clean_path, 'reward_') === 0) ? "uploads/rewards/" . $clean_path : $clean_path;

            $card_class = $can_redeem ? "" : "disabled";
            $badge_html = "";
            $btn_text = "Redeem Now";

            if (!$is_active) {
                $badge_html = '<div class="stock-badge badge-inactive">NOT AVAILABLE</div>';
                $btn_text = "Not Available";
            } elseif ($is_out_of_stock) {
                $badge_html = '<div class="stock-badge badge-out">OUT OF STOCK</div>';
                $btn_text = "Out of Stock";
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
                        <div style="font-size:0.8rem; color:#888;"><?php echo $row['Reward_Stock']; ?> left</div>
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
                        <th>Points</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
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
                            $status = $hist['Redemption_Status']; 
                            // ⭐ 历史记录图片路径处理
                            $hist_path = str_replace('\\', '/', $hist['Reward_PhotoPath']);
                            $hist_src = (strpos($hist_path, 'reward_') === 0) ? "uploads/rewards/" . $hist_path : $hist_path;
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <img src="<?php echo $hist_src; ?>" style="width:40px; height:40px; object-fit:cover; border-radius:4px;" onerror="this.src='images/hero_3.jpg';">
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
                                        <button type="button" class="btn-receive" onclick="confirmReceive(<?php echo $hist['Redemption_ID']; ?>)">
                                            <i class="fas fa-check-circle"></i> Receive
                                        </button>
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

<form id="redeemForm" method="POST" style="display:none;"><input type="hidden" name="redeem_item_id" id="hidden_id"></form>
<form id="receiveForm" method="POST" style="display:none;"><input type="hidden" name="confirm_receive_id" id="hidden_recv_id"></form>

<script>
    const isLoggedIn = <?php echo $current_donor_id ? 'true' : 'false'; ?>;
    const isAddressComplete = <?php echo $donor_address_complete ? 'true' : 'false'; ?>;
    const currentPoints = <?php echo $donor_points; ?>;

    function switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('redeem-tab').style.display = (tabName === 'redeem' ? 'grid' : 'none');
        document.getElementById('history-tab').style.display = (tabName === 'history' ? 'block' : 'none');
        const btns = document.querySelectorAll('.tab-btn');
        if(tabName === 'redeem') btns[0].classList.add('active'); else btns[1].classList.add('active');
    }

    function handleRedeem(itemId, reqPoints, name) {
        if (!isLoggedIn) { Swal.fire('Warning', 'Please login.', 'warning').then(() => { window.location.href='donor_login.php'; }); return; }
        if (currentPoints < reqPoints) { Swal.fire('Error', 'Insufficient points.', 'error'); return; }
        // ... (此处省略部分冗余逻辑，保持原本弹窗流程即可)
        Swal.fire({ title: 'Confirm', text: `Redeem ${name}?`, showCancelButton: true }).then(res => {
            if(res.isConfirmed) { document.getElementById('hidden_id').value = itemId; document.getElementById('redeemForm').submit(); }
        });
    }

    function confirmReceive(orderId) {
        Swal.fire({ title: 'Received?', text: 'Mark as received?', showCancelButton: true }).then(res => {
            if (res.isConfirmed) { document.getElementById('hidden_recv_id').value = orderId; document.getElementById('receiveForm').submit(); }
        });
    }

    document.addEventListener('DOMContentLoaded', () => { <?php if (!empty($alert_script)) echo $alert_script; ?> });
</script>

<?php include 'footer.php'; ?>
</body>
</html>