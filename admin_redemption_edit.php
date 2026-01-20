<?php
// admin_redemption_edit.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// Check ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: redemption_order_management.php");
    exit();
}

$orderId = intval($_GET['id']);
$sql = "SELECT r.*, d.Donor_Name, d.Donor_Email, rw.Reward_ItemName 
        FROM redemption_order r 
        JOIN donor d ON r.Donor_ID = d.Donor_ID 
        JOIN reward_item rw ON r.Reward_ID = rw.Reward_ID 
        WHERE r.Redemption_ID = $orderId";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    header("Location: redemption_order_management.php?error=" . urlencode("Order not found."));
    exit();
}
$order = $result->fetch_assoc();
$currentStatus = $order['Redemption_Status'];

// --- HANDLE UPDATE ---
$errorMessage = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_order'])) {
    
    $newStatus = mysqli_real_escape_string($conn, $_POST['status']);
    
    $contactRaw = mysqli_real_escape_string($conn, $_POST['contact']);
    // Ensure format matches DB preference
    $contact = (strpos($contactRaw, '+60') === 0) ? $contactRaw : "+60" . $contactRaw;

    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postal = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $tracking = mysqli_real_escape_string($conn, $_POST['tracking_number']);
    $cancelReason = mysqli_real_escape_string($conn, $_POST['cancel_reason']);

    // Validation (Server Side Backup)
    if ($newStatus == 'Shipped' && empty($tracking)) {
        $errorMessage = "Tracking Number is required for Shipped status.";
    } elseif ($newStatus == 'Cancelled' && empty($cancelReason)) {
        $errorMessage = "Cancellation Reason is required for Cancelled status.";
    } else {
        // --- COMPLEX STATUS CHANGE LOGIC ---
        // Need to handle Point Refund/Deduction if status changes FROM/TO Cancelled
        
        $points = $order['Redemption_PointsSpent'];
        $qty = $order['Redemption_Quantity'];
        $donorId = $order['Donor_ID'];
        $rewardId = $order['Reward_ID'];
        
        // Scenario 1: Was Cancelled, Now Active (Pending/Shipped) -> Re-deduct points/stock
        if ($currentStatus == 'Cancelled' && $newStatus != 'Cancelled') {
            $conn->query("UPDATE point SET Points_Total = Points_Total - $points, Points_Updated_At = NOW() WHERE Donor_ID = $donorId");
            $conn->query("UPDATE reward_item SET Reward_Stock = Reward_Stock - $qty WHERE Reward_ID = $rewardId");
        }
        
        // Scenario 2: Was Active, Now Cancelled -> Refund points/stock
        if ($currentStatus != 'Cancelled' && $newStatus == 'Cancelled') {
            $conn->query("UPDATE point SET Points_Total = Points_Total + $points, Points_Updated_At = NOW() WHERE Donor_ID = $donorId");
            $conn->query("UPDATE reward_item SET Reward_Stock = Reward_Stock + $qty WHERE Reward_ID = $rewardId");
        }

        // Update DB
        $updateSql = "UPDATE redemption_order SET 
                      Redemption_Status = '$newStatus',
                      Redemption_ContactNumber = '$contact',
                      Redemption_Address1 = '$address1',
                      Redemption_Address2 = '$address2',
                      Redemption_Address3 = '$address3',
                      Redemption_City = '$city',
                      Redemption_State = '$state',
                      Redemption_PostalCode = '$postal',
                      Redemption_TrackingNumber = '$tracking',
                      Redemption_CancelReason = '$cancelReason',
                      Redemption_Updated_At = NOW()
                      WHERE Redemption_ID = $orderId";
        
        if ($conn->query($updateSql)) {
            header("Location: redemption_order_management.php?success=" . urlencode("Order #$orderId emergency update successful."));
            exit();
        } else {
            $errorMessage = "Database Error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Edit Order #<?php echo $orderId; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Based on branch_edit.php styles */
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #dc3545; font-size: 14px; font-weight: 500; }
        
        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 900px; margin: 0 auto 30px; }
        .form-header { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; } 
        .form-header h2 { font-size: 18px; color: #333; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }

        .form-row { display: flex; gap: 20px; margin-bottom: 15px; } .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 20px; } .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; font-size: 14px; box-sizing: border-box; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #F28585; }
        .required { color: red; margin-left: 3px; font-weight: bold; }
        
        /* Form Guide Style */
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }

        .button-group { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        
        /* Cancel Button with Hover Effect */
        .btn-cancel { padding: 12px 25px; background: #6c757d; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration:none; }
        .btn-cancel:hover { background: #5a6268; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        
        .btn-save { padding: 12px 25px; background: linear-gradient(135deg, #dc3545 0%, #e06c6c 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4); }

        .alert-box { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

        /* Phone Format from staff_add.php */
        .phone-format { display: flex; align-items: center; } 
        .phone-prefix { padding: 12px 15px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); font-size: 14px; } 
        .phone-input { border-radius: 0 5px 5px 0 !important; }

        /* Inline Error */
        .input-error { border-color: var(--danger) !important; background-color: #fff5f5; }
        .inline-error { color: var(--danger); font-size: 11px; margin-top: 4px; display: block; font-weight: 500; animation: fadeIn 0.3s; }
        
        /* System Alert */
        .custom-alert { position: fixed; top: 20px; right: 20px; background: white; border-left: 5px solid; padding: 15px 20px; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; z-index: 9999; transform: translateX(120%); transition: transform 0.3s ease-out; max-width: 350px; }
        .custom-alert.show { transform: translateX(0); }
        .custom-alert.error { border-color: #dc3545; } .custom-alert.error i { color: #dc3545; }
        .alert-content h4 { margin: 0 0 5px; font-size: 14px; color: #333; } .alert-content p { margin: 0; font-size: 13px; color: #666; }
    </style>
</head>
<body>

    <div id="customAlert" class="custom-alert"><i class="fas" id="alertIcon"></i><div class="alert-content"><h4 id="alertTitle">Title</h4><p id="alertMessage">Message</p></div></div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="redemption_order_management.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="header-title">
                    <h1>Emergency Edit Order #<?php echo $orderId; ?></h1>
                    <p>Modify details for Shipped/Cancelled orders. Use with caution.</p>
                </div>
            </div>

            <div class="form-container">
                <?php if($errorMessage): ?>
                    <div class="alert-box"><i class="fas fa-exclamation-triangle"></i> <?php echo $errorMessage; ?></div>
                <?php endif; ?>

                <form id="editOrderForm" action="" method="POST">
                    <input type="hidden" name="update_order" value="1">

                    <div class="form-header"><h2>Order Status Override</h2></div>
                    
                    <div class="form-group">
                        <label class="form-label">Order Status <span class="required">*</span></label>
                        <select name="status" id="status" class="form-select" onchange="toggleStatusFields()">
                            <option value="Pending" <?php if($order['Redemption_Status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                            <option value="Shipped" <?php if($order['Redemption_Status'] == 'Shipped') echo 'selected'; ?>>Shipped</option>
                            <option value="Cancelled" <?php if($order['Redemption_Status'] == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                            <option value="Completed" <?php if($order['Redemption_Status'] == 'Completed') echo 'selected'; ?>>Completed</option>
                        </select>
                        <small class="form-guide">
                            Warning: Changing from 'Cancelled' to other statuses will re-deduct points/stock. Changing to 'Cancelled' will refund them.
                        </small>
                    </div>

                    <div id="tracking_div" class="form-group">
                        <label class="form-label">Tracking Number <span class="required">*</span></label>
                        <input type="text" name="tracking_number" id="tracking_number" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_TrackingNumber']); ?>" placeholder="e.g. JNT-12345678">
                        <span class="form-guide">Required if status is Shipped.</span>
                    </div>

                    <div id="cancel_div" class="form-group">
                        <label class="form-label">Cancellation Reason <span class="required">*</span></label>
                        <textarea name="cancel_reason" id="cancel_reason" class="form-textarea" rows="2" placeholder="e.g. Out of stock or user request"><?php echo htmlspecialchars($order['Redemption_CancelReason']); ?></textarea>
                        <span class="form-guide">Required if status is Cancelled.</span>
                    </div>

                    <div class="form-header" style="margin-top: 30px;"><h2>Shipping Details</h2></div>

                    <div class="form-group">
                        <label class="form-label">Contact Number <span class="required">*</span></label>
                        <div class="phone-format">
                            <span class="phone-prefix">+60</span>
                            <input type="text" id="edit_contact" name="contact" class="form-input phone-input" value="<?php echo htmlspecialchars(str_replace('+60', '', $order['Redemption_ContactNumber'])); ?>" placeholder="12-3456789" maxlength="11">
                        </div>
                        <span class="form-guide">Format: 12-3456789 (Prefix 11-19, total 9-11 digits).</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1 <span class="required">*</span></label>
                        <input type="text" id="address1" name="address1" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_Address1']); ?>" placeholder="e.g. No. 123, Jalan Example">
                        <span class="form-guide">House unit no., floor, building, street name.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2 <span class="required">*</span></label>
                        <input type="text" id="address2" name="address2" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_Address2']); ?>" placeholder="e.g. Taman Sri">
                        <span class="form-guide">Residential area, village, or section.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_Address3']); ?>" placeholder="Address Line 3 (Optional)">
                        <span class="form-guide">Address Line 3 (Optional).</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City <span class="required">*</span></label>
                            <input type="text" id="city" name="city" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_City']); ?>" placeholder="e.g. Kuala Lumpur">
                            <span class="form-guide">City name.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postcode <span class="required">*</span></label>
                            <input type="text" name="postal_code" id="edit_postal_code" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_PostalCode']); ?>" placeholder="e.g. 50000">
                            <span class="form-guide">5-digit postcode.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">State <span class="required">*</span></label>
                        <select name="state" id="edit_state" class="form-select">
                            <option value="">Select State</option>
                            <?php 
                            $states = ['Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'];
                            foreach($states as $s) {
                                $sel = ($order['Redemption_State'] == $s) ? 'selected' : '';
                                echo "<option value='$s' $sel>$s</option>";
                            }
                            ?>
                        </select>
                        <span class="form-guide">Select state.</span>
                    </div>

                    <div class="button-group">
                        <a href="redemption_order_management.php" class="btn-cancel">Cancel</a>
                        <button type="button" class="btn-save" onclick="validateAndConfirm()">Update Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleStatusFields() {
            const status = document.getElementById('status').value;
            const trackDiv = document.getElementById('tracking_div');
            const cancelDiv = document.getElementById('cancel_div');

            if (status === 'Shipped' || status === 'Completed') {
                trackDiv.style.display = 'block';
            } else {
                trackDiv.style.display = 'none'; 
            }

            if (status === 'Cancelled') {
                cancelDiv.style.display = 'block';
            } else {
                cancelDiv.style.display = 'none';
            }
        }
        
        // Init on load
        toggleStatusFields();

        // --- Phone Logic ---
        document.getElementById('edit_contact').addEventListener('input', function(e) { 
            let val = this.value.replace(/\D/g, ''); 
            if (val.length > 11) val = val.substring(0, 11); 
            let newVal = ''; 
            if (val.length > 2) { 
                newVal += val.substring(0, 2) + '-' + val.substring(2); 
            } else { 
                newVal = val; 
            } 
            this.value = newVal; 
        });

        // Postcode Logic
        document.getElementById('edit_postal_code').addEventListener('input', function() {
            const val = this.value.replace(/\D/g, '');
            if (val.length >= 2) {
                const prefix = parseInt(val.substring(0, 2));
                let state = "";
                if (prefix >= 1 && prefix <= 2) state = "Perlis"; else if (prefix >= 5 && prefix <= 9) state = "Kedah"; else if (prefix >= 10 && prefix <= 14) state = "Penang";
                else if (prefix >= 15 && prefix <= 18) state = "Kelantan"; else if (prefix >= 20 && prefix <= 24) state = "Terengganu"; else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                else if (prefix >= 30 && prefix <= 39) state = "Perak"; else if (prefix >= 40 && prefix <= 48) state = "Selangor"; else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                else if (prefix === 62) state = "Putrajaya"; else if (prefix >= 63 && prefix <= 68) state = "Selangor"; else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                else if (prefix >= 75 && prefix <= 78) state = "Melaka"; else if (prefix >= 80 && prefix <= 86) state = "Johor"; else if (prefix === 87) state = "Labuan";
                else if (prefix >= 88 && prefix <= 91) state = "Sabah"; else if (prefix >= 93 && prefix <= 98) state = "Sarawak";
                if (state) document.getElementById('edit_state').value = state;
            }
        });

        // Validation helpers
        function validatePhoneDetailed(val) {
            if (!val.includes('-')) return "Missing hyphen symbol ( - ). Please use the dash key.";
            const parts = val.split('-'); 
            if (parts.length !== 2) return "Invalid format. Only one hyphen ( - ) allowed.";
            const front = parts[0]; const back = parts[1];
            if (front.length !== 2) return "Prefix must be 2 digits (e.g., 11-19).";
            if (!/^\d+$/.test(front)) return "Prefix must be numbers.";
            const prefixNum = parseInt(front, 10);
            if (prefixNum < 11 || prefixNum > 19) return "Prefix must be between 11 and 19 (e.g., 12-xxxx).";
            if (back.length === 0) return "Please enter numbers after the hyphen ( - ).";
            if (back.length < 7) { 
                let diff = 7 - back.length; 
                return `Number after hyphen ( - ) is too short. Please add at least ${diff} more digit(s).`; 
            }
            if (back.length > 8) return "Number after hyphen ( - ) is too long. Max 8 digits allowed."; 
            return ""; 
        }

        function showFieldError(inputId, message) {
            const input = document.getElementById(inputId); if (!input) return;
            input.classList.add('input-error');
            let parent = input.parentNode;
            if (parent.classList.contains('phone-format')) parent = parent.parentNode; 
            let errorDiv = parent.querySelector('.inline-error');
            if (!errorDiv) { errorDiv = document.createElement('div'); errorDiv.className = 'inline-error'; parent.appendChild(errorDiv); }
            errorDiv.textContent = message;
        }

        function clearFormErrors() {
            const form = document.getElementById('editOrderForm');
            const inputs = form.querySelectorAll('.form-input, .form-select, .form-textarea');
            inputs.forEach(i => i.classList.remove('input-error'));
            form.querySelectorAll('.inline-error').forEach(e => e.remove());
        }

        function showSystemAlert(message, type = 'error') {
            const alertBox = document.getElementById('customAlert');
            const alertIcon = document.getElementById('alertIcon');
            const alertTitle = document.getElementById('alertTitle');
            const alertMsg = document.getElementById('alertMessage');
            alertBox.className = 'custom-alert ' + type;
            if (type === 'error') { alertIcon.className = 'fas fa-exclamation-circle'; alertTitle.innerText = 'Error'; } 
            else { alertIcon.className = 'fas fa-check-circle'; alertTitle.innerText = 'Success'; }
            alertMsg.innerText = message;
            alertBox.classList.add('show');
            setTimeout(() => { alertBox.classList.remove('show'); }, 4000);
        }

        // Main Validation Function
        function validateAndConfirm() {
            clearFormErrors();
            let hasError = false;
            let firstErrorMsg = "";

            // 1. Status Specific Logic
            const status = document.getElementById('status').value;
            if (status === 'Shipped') {
                const track = document.getElementById('tracking_number').value.trim();
                if (!track) {
                    showFieldError('tracking_number', 'Tracking Number is required for Shipped status.');
                    hasError = true; firstErrorMsg = firstErrorMsg || 'Tracking Number is required.';
                }
            }
            if (status === 'Cancelled') {
                const reason = document.getElementById('cancel_reason').value.trim();
                if (!reason) {
                    showFieldError('cancel_reason', 'Cancellation Reason is required.');
                    hasError = true; firstErrorMsg = firstErrorMsg || 'Cancellation Reason is required.';
                }
            }

            // 2. Contact Validation
            const contactId = 'edit_contact';
            const contactVal = document.getElementById(contactId).value.trim();
            if (!contactVal) {
                showFieldError(contactId, "Contact is required.");
                hasError = true; firstErrorMsg = firstErrorMsg || "Contact is required.";
            } else {
                let phoneMsg = validatePhoneDetailed(contactVal);
                if (phoneMsg) {
                    showFieldError(contactId, phoneMsg);
                    hasError = true; firstErrorMsg = firstErrorMsg || phoneMsg;
                }
            }

            // 3. Address Fields Validation (Strictly Required)
            const requiredFields = [
                { id: 'address1', name: 'Address Line 1' },
                { id: 'address2', name: 'Address Line 2' },
                { id: 'city', name: 'City' },
                { id: 'edit_postal_code', name: 'Postcode' },
                { id: 'edit_state', name: 'State' }
            ];

            requiredFields.forEach(field => {
                const el = document.getElementById(field.id);
                if (!el.value.trim()) {
                    showFieldError(field.id, field.name + " is required.");
                    hasError = true; firstErrorMsg = firstErrorMsg || (field.name + " is required.");
                }
            });

            // 4. Decision
            if (hasError) {
                showSystemAlert(firstErrorMsg, "error");
            } else {
                // Show Confirmation Popup
                Swal.fire({
                    title: 'Update Order?',
                    text: "Are you sure you want to update this order's details/status?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#F28585',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Update'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('editOrderForm').submit();
                    }
                });
            }
        }
    </script>
</body>
</html>