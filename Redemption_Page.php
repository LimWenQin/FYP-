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

    // B. 获取用户地址信息，判断是否完整
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
// 2. 处理 POST 请求 (更新地址 或 兑换物品)
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
            echo "<script>alert('Address updated successfully! You can now redeem items.'); window.location.href='Redemption_Page.php';</script>";
        } else {
            echo "<script>alert('Error updating address.');</script>";
        }
        exit();
    }

    // --- 场景 B: 执行兑换 ---
    if (isset($_POST['redeem_item_id'])) {
        $item_id = $_POST['redeem_item_id'];
        
        // 1. 重新查询物品信息 (防作弊)
        $stmt = $conn->prepare("SELECT * FROM reward_item WHERE Reward_ID = ? AND Reward_Status = 'Active'");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item) {
            echo "<script>alert('Item not found or unavailable.');</script>";
        } elseif ($item['Reward_Stock'] <= 0) {
            echo "<script>alert('Out of stock!');</script>";
        } elseif ($donor_points < $item['Reward_RequiredPoint']) {
            echo "<script>alert('Insufficient points!');</script>";
        } elseif (!$donor_address_complete) {
            echo "<script>alert('Please complete your address first.');</script>";
        } else {
            // --- 开始事务处理 (保证数据一致性) ---
            $conn->begin_transaction();

            try {
                // 1. 扣除积分
                $new_points = $donor_points - $item['Reward_RequiredPoint'];
                $update_pt = $conn->prepare("UPDATE point SET Points_Total = ?, Points_Updated_At = NOW() WHERE Donor_ID = ?");
                $update_pt->bind_param("ii", $new_points, $current_donor_id);
                $update_pt->execute();

                // 2. 扣除库存
                $update_stk = $conn->prepare("UPDATE reward_item SET Reward_Stock = Reward_Stock - 1 WHERE Reward_ID = ?");
                $update_stk->bind_param("i", $item_id);
                $update_stk->execute();

                // 3. 创建兑换订单
                // 注意：这里我们将 donor 表当前的地址快照写入 order 表，以防用户未来修改地址影响历史订单
                $insert_ord = $conn->prepare("INSERT INTO redemption_order (Redemption_Address1, Redemption_Address2, Redemption_Address3, Redemption_City, Redemption_State, Redemption_PostalCode, Redemption_Country, Redemption_ContactNumber, Redemption_PointsSpent, Redemption_Status, Redemption_Updated_At, Donor_ID, Reward_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Processing', NOW(), ?, ?)");
                
                $country = 'Malaysia'; // 默认
                $addr3 = ''; // 数据库有这个字段，暂时留空或用 addr2
                $insert_ord->bind_param("ssssssssisi", 
                    $donor_data['Donor_Address1'], 
                    $donor_data['Donor_Address2'], 
                    $addr3,
                    $donor_data['Donor_City'], 
                    $donor_data['Donor_State'], 
                    $donor_data['Donor_PostalCode'], 
                    $country, 
                    $donor_data['Donor_ContactNumber'], 
                    $item['Reward_RequiredPoint'], 
                    $current_donor_id, 
                    $item_id
                );
                $insert_ord->execute();

                // 提交事务
                $conn->commit();
                echo "<script>alert('Redemption Successful! Your points have been deducted.'); window.location.href='Redemption_Page.php';</script>";

            } catch (Exception $e) {
                $conn->rollback();
                echo "<script>alert('Transaction failed: " . $e->getMessage() . "');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rewards Redemption - Love Bridge</title>
    <style>
        /* 复用你 Homepage 的核心样式 */
        :root {
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-gray: #f5f5f5;
            --text-dark: #171717;
            --white: #ffffff;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--light-gray);
            margin: 0; padding: 0;
            color: var(--text-dark);
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* 顶部积分栏 */
        .points-banner {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            padding: 30px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.2);
        }

        .points-info h2 { margin: 0; font-size: 1.5rem; opacity: 0.9; }
        .points-value { font-size: 3rem; font-weight: 800; }
        .points-label { font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; }

        /* 商品网格 */
        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .reward-card {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .reward-card:hover { transform: translateY(-5px); }

        .reward-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #eee;
        }

        .reward-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        
        .reward-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 10px; color: var(--text-dark); }
        .reward-desc { font-size: 0.95rem; color: #666; margin-bottom: 15px; flex-grow: 1; line-height: 1.5; }
        
        .reward-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .reward-cost {
            color: var(--primary-red);
            font-weight: 800;
            font-size: 1.2rem;
        }

        .stock-label { font-size: 0.8rem; color: #888; }

        .btn-redeem {
            background-color: var(--primary-red);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
            width: 100%;
            margin-top: 15px;
        }

        .btn-redeem:hover { background-color: var(--dark-red); }
        .btn-redeem:disabled { background-color: #ccc; cursor: not-allowed; }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center; align-items: center;
        }

        .modal-box {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            position: relative;
        }

        .modal-title { font-size: 1.5rem; color: var(--primary-red); margin-bottom: 20px; font-weight: 700; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; }
        .form-control {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box;
        }

        .close-btn {
            position: absolute; top: 15px; right: 20px; font-size: 1.5rem; cursor: pointer; border: none; background: none;
        }
    </style>
</head>
<body>

<?php include 'header_UI.php'; ?>

<div class="container">
    
    <div class="points-banner">
        <div class="points-info">
            <h2>Your Rewards Balance</h2>
            <div class="points-label">Current Points</div>
        </div>
        <div class="points-value">
            <?php echo number_format($donor_points); ?> PT
        </div>
    </div>

    <h2 style="color: var(--primary-red); margin-bottom: 20px;">Redeemable Items</h2>
    
    <div class="rewards-grid">
        <?php
        $sql = "SELECT * FROM reward_item WHERE Reward_Status = 'Active' ORDER BY Reward_RequiredPoint ASC";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $is_out_of_stock = $row['Reward_Stock'] <= 0;
                $can_afford = $donor_points >= $row['Reward_RequiredPoint'];
                ?>
                <div class="reward-card">
                    <img src="<?php echo htmlspecialchars($row['Reward_PhotoPath']); ?>" alt="Reward" class="reward-img">
                    <div class="reward-body">
                        <div class="reward-title"><?php echo htmlspecialchars($row['Reward_ItemName']); ?></div>
                        <div class="reward-desc"><?php echo htmlspecialchars($row['Reward_Description']); ?></div>
                        
                        <div class="reward-meta">
                            <div class="reward-cost"><?php echo number_format($row['Reward_RequiredPoint']); ?> PTS</div>
                            <div class="stock-label"><?php echo $row['Reward_Stock']; ?> left</div>
                        </div>

                        <button class="btn-redeem" 
                            <?php echo $is_out_of_stock ? 'disabled' : ''; ?>
                            onclick="handleRedeem(
                                <?php echo $row['Reward_ID']; ?>, 
                                <?php echo $row['Reward_RequiredPoint']; ?>,
                                '<?php echo addslashes($row['Reward_ItemName']); ?>'
                            )">
                            <?php 
                            if ($is_out_of_stock) echo "Out of Stock";
                            else echo "Redeem Now"; 
                            ?>
                        </button>
                    </div>
                </div>
                <?php
            }
        } else {
            echo "<p>No rewards available at the moment.</p>";
        }
        ?>
    </div>
</div>

<div id="addressModal" class="modal-overlay">
    <div class="modal-box">
        <button class="close-btn" onclick="closeAddressModal()">&times;</button>
        <div class="modal-title">Shipping Address Required</div>
        <p style="margin-bottom: 20px; color: #666;">To redeem items, we need your delivery address. Please update it below.</p>
        
        <form method="POST">
            <input type="hidden" name="update_address" value="1">
            
            <div class="form-group">
                <label>Address Line 1</label>
                <input type="text" name="addr1" class="form-control" required value="<?php echo isset($donor_data['Donor_Address1']) ? $donor_data['Donor_Address1'] : ''; ?>">
            </div>
            <div class="form-group">
                <label>Address Line 2 (Optional)</label>
                <input type="text" name="addr2" class="form-control" value="<?php echo isset($donor_data['Donor_Address2']) ? $donor_data['Donor_Address2'] : ''; ?>">
            </div>
            <div style="display: flex; gap: 10px;">
                <div class="form-group" style="flex:1">
                    <label>City</label>
                    <input type="text" name="city" class="form-control" required value="<?php echo isset($donor_data['Donor_City']) ? $donor_data['Donor_City'] : ''; ?>">
                </div>
                <div class="form-group" style="flex:1">
                    <label>State</label>
                    <input type="text" name="state" class="form-control" required value="<?php echo isset($donor_data['Donor_State']) ? $donor_data['Donor_State'] : ''; ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Postal Code</label>
                <input type="text" name="zip" class="form-control" required value="<?php echo isset($donor_data['Donor_PostalCode']) ? $donor_data['Donor_PostalCode'] : ''; ?>">
            </div>
            
            <button type="submit" class="btn-redeem">Save & Continue</button>
        </form>
    </div>
</div>

<form id="redeemForm" method="POST" style="display:none;">
    <input type="hidden" name="redeem_item_id" id="hidden_redeem_id">
</form>

<script>
    // 从 PHP 获取当前用户状态
    const isLoggedIn = <?php echo $current_donor_id ? 'true' : 'false'; ?>;
    const isAddressComplete = <?php echo $donor_address_complete ? 'true' : 'false'; ?>;
    const currentPoints = <?php echo $donor_points; ?>;

    function handleRedeem(itemId, requiredPoints, itemName) {
        // 1. 检查是否登录
        if (!isLoggedIn) {
            alert("Please login to redeem rewards.");
            window.location.href = 'donor_login.php';
            return;
        }

        // 2. 检查积分是否足够
        if (currentPoints < requiredPoints) {
            alert("Insufficient points! You need " + (requiredPoints - currentPoints) + " more points.");
            return;
        }

        // 3. 检查地址是否完整
        if (!isAddressComplete) {
            document.getElementById('addressModal').style.display = 'flex';
            return;
        }

        // 4. 最终确认
        if (confirm("Redeem '" + itemName + "' for " + requiredPoints + " points?")) {
            document.getElementById('hidden_redeem_id').value = itemId;
            document.getElementById('redeemForm').submit();
        }
    }

    function closeAddressModal() {
        document.getElementById('addressModal').style.display = 'none';
    }

    // 点击 Modal 外部关闭
    window.onclick = function(event) {
        const modal = document.getElementById('addressModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>

<?php include 'footer.php'; ?>

</body>
</html>