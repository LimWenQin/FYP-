<?php
// update_stock.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// Check ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: reward_item_management.php");
    exit();
}

$rewardId = intval($_GET['id']);
$currentAdminId = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : $_SESSION['staff_id'];

// Constants
define('MAX_STOCK_LIMIT', 500);
define('LOW_STOCK_THRESHOLD', 15);

// Fetch Current Item Data
$sql = "SELECT Reward_ItemName, Reward_Stock, Reward_Code, Reward_Status FROM reward_item WHERE Reward_ID = $rewardId";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    header("Location: reward_item_management.php?error=Item Not Found");
    exit();
}
$item = $result->fetch_assoc();

// CALCULATE REMAINING CAPACITY
$currentStock = intval($item['Reward_Stock']);
$maxAddable = MAX_STOCK_LIMIT - $currentStock;

// Variables for UI
$saveSuccess = false;
$errorMsg = "";

// --- HANDLE STOCK UPDATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_stock'])) {
    $addQty = intval($_POST['add_qty']);
    $newStock = $currentStock + $addQty;

    // Backend Validation
    if ($addQty <= 0) {
        $errorMsg = "Quantity must be greater than 0.";
    } elseif ($newStock > MAX_STOCK_LIMIT) {
        $errorMsg = "Stock Limit Exceeded (Max " . MAX_STOCK_LIMIT . "). You can only add up to $maxAddable units.";
    } else {
        // Determine new status
        $newStatus = $item['Reward_Status'];
        if ($newStock == 0) {
            $newStatus = "Inactive";
        } elseif ($newStock < LOW_STOCK_THRESHOLD) {
            $newStatus = "Low Stock";
        } else {
            $newStatus = "Active";
        }

        $updateSql = "UPDATE reward_item SET Reward_Stock = $newStock, Reward_Status = '$newStatus' WHERE Reward_ID = $rewardId";
        
        if ($conn->query($updateSql)) {
            // Log Action
            $logDetails = "Stock Update: Added $addQty. Old: $currentStock, New: $newStock";
            $logSql = "INSERT INTO reward_logs (Reward_ID, Admin_ID, Action_Type, Action_Details) 
                       VALUES ('$rewardId', '$currentAdminId', 'Stock Update', '$logDetails')";
            $conn->query($logSql);

            $saveSuccess = true;
            // Refetch updated data
            $item['Reward_Stock'] = $newStock;
            $currentStock = $newStock;
            $maxAddable = MAX_STOCK_LIMIT - $currentStock;
        } else {
            $errorMsg = "Database Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Stock - DonationMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; cursor: pointer; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        
        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 500px; margin: 0 auto 30px; }
        .info-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 25px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #555; }
        .info-row:last-child { margin-bottom: 0; }
        .info-value { font-weight: 700; color: #333; }
        .highlight-stock { color: #F28585; font-size: 18px; }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; font-size: 14px; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; transition: 0.3s; font-size: 16px; box-sizing: border-box; }
        .form-control:focus { border-color: #F28585; }
        
        .btn-submit { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 15px; }
        .btn-submit:hover { background: #218838; }
        
        .error-msg { background: #fff5f5; color: #dc3545; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px; border-left: 3px solid #dc3545; }
        
        /* Custom Alert & Modal Styles */
        .custom-alert { position: fixed; top: 20px; right: 20px; background: white; border-left: 5px solid; padding: 15px 20px; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; z-index: 9999; transform: translateX(120%); transition: transform 0.3s ease-out; max-width: 350px; }
        .custom-alert.show { transform: translateX(0); }
        .custom-alert.error { border-color: #dc3545; } .custom-alert.error i { color: #dc3545; }
        .custom-alert.success { border-color: #28a745; } .custom-alert.success i { color: #28a745; }
        .alert-content h4 { margin: 0 0 5px; font-size: 14px; color: #333; } .alert-content p { margin: 0; font-size: 13px; color: #666; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center; }
        .success-modal { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .success-icon { width: 70px; height: 70px; background: #e6f4ea; border-radius: 50%; color: #28a745; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
        .modal-btn-group { display: flex; gap: 10px; justify-content: center; margin-top: 25px; }
        .btn-modal-back { padding: 10px 20px; background: white; border: 1px solid #ddd; color: #666; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; transition: 0.2s; }
        .btn-modal-back:hover { background: #f8f9fa; }
        .btn-modal-continue { padding: 10px 20px; background: #28a745; border: none; color: white; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.2s; }
        .btn-modal-continue:hover { background: #218838; }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .stock-limit-info { font-size: 12px; color: #F28585; margin-top: 5px; display: block; font-weight: 600; }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    
    <div id="customAlert" class="custom-alert">
        <i class="fas" id="alertIcon"></i>
        <div class="alert-content">
            <h4 id="alertTitle">Title</h4>
            <p id="alertMessage">Message</p>
        </div>
    </div>

    <div id="successModal" class="modal-overlay" style="display: <?php echo $saveSuccess ? 'flex' : 'none'; ?>;">
        <div class="success-modal">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <h2 style="margin-bottom: 10px; font-size: 22px;">Stock Updated!</h2>
            <p style="color: #666; line-height: 1.5;">Successfully added stock to <?php echo htmlspecialchars($item['Reward_ItemName']); ?>.</p>
            <div class="modal-btn-group">
                <button type="button" onclick="goBackOrClose()" class="btn-modal-back">Return to List</button>
                <button type="button" class="btn-modal-continue" onclick="document.getElementById('successModal').style.display='none';">Update More</button>
            </div>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="javascript:void(0);" class="back-btn" onclick="goBackOrClose()"><i class="fas fa-arrow-left"></i> Back</a>
                <div style="flex:1; text-align:center; padding-right:80px;"><h1>Update Stock</h1></div>
            </div>

            <div class="form-container">
                <h3 style="margin-top:0; margin-bottom:20px; color:#333; text-align:center;"><?php echo htmlspecialchars($item['Reward_ItemName']); ?></h3>
                
                <div class="info-card">
                    <div class="info-row">
                        <span>Item Code:</span>
                        <span class="info-value"><?php echo $item['Reward_Code']; ?></span>
                    </div>
                    <div class="info-row">
                        <span>Max Capacity:</span>
                        <span class="info-value"><?php echo MAX_STOCK_LIMIT; ?></span>
                    </div>
                    <div style="margin-top:15px; border-top:1px solid #ddd; padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
                        <span>Current Stock:</span>
                        <span class="info-value highlight-stock"><?php echo $item['Reward_Stock']; ?></span>
                    </div>
                </div>

                <?php if($errorMsg): ?>
                    <div class="error-msg"><i class="fas fa-exclamation-triangle"></i> <?php echo $errorMsg; ?></div>
                <?php endif; ?>

                <form method="POST" onsubmit="return validateStockForm()" novalidate>
                    <input type="hidden" name="update_stock" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Quantity to Add</label>
                        <input type="number" id="add_qty" name="add_qty" class="form-control" placeholder="Enter amount..." min="1" required autofocus onkeypress="return isNumberKey(event)" oninput="checkLimit(this)">
                        <span class="stock-limit-info" id="limit-msg">Max Addable: <?php echo $maxAddable; ?></span>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-plus-circle"></i> Confirm Update
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const maxAddable = <?php echo $maxAddable; ?>;

        function goBackOrClose() {
            // Priority: Close window if opened by another window
            if (window.opener) {
                window.close();
            } else {
                window.location.href = 'reward_item_management.php';
            }
        }

        // Prevent negative sign input
        function isNumberKey(evt) {
            var charCode = (evt.which) ? evt.which : evt.keyCode;
            // Allow numbers only (48-57)
            if (charCode > 31 && (charCode < 48 || charCode > 57))
                return false;
            return true;
        }

        function showSystemAlert(message) {
            const alertBox = document.getElementById('customAlert');
            const alertIcon = document.getElementById('alertIcon');
            const alertTitle = document.getElementById('alertTitle');
            const alertMsg = document.getElementById('alertMessage');
            
            alertBox.className = 'custom-alert error show';
            alertIcon.className = 'fas fa-exclamation-circle';
            alertTitle.innerText = 'Limit Reached';
            alertMsg.innerText = message;
            
            setTimeout(() => { alertBox.classList.remove('show'); }, 4000);
        }

        function checkLimit(input) {
            // Remove any non-digit chars just in case paste
            input.value = input.value.replace(/[^0-9]/g, '');
            
            if (input.value === '') return;

            let val = parseInt(input.value);
            
            // Show system alert if exceeds limit
            if (val > maxAddable) {
                input.value = maxAddable; // Auto correct to max
                showSystemAlert("You cannot exceed the total stock limit of 500.");
            }
        }

        function validateStockForm() {
            const input = document.getElementById('add_qty');
            let val = parseInt(input.value);

            if (isNaN(val) || input.value.trim() === '') {
                showSystemAlert("Please enter a quantity.");
                return false;
            }
            if (val > maxAddable) {
                showSystemAlert("Quantity exceeds limit.");
                return false;
            }
            if (val <= 0) {
                showSystemAlert("Quantity must be greater than 0.");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>