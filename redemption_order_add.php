<?php
// redemption_order_add.php
session_start();

// --- Check Login ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- DATA LISTS FOR FORM ---
// 1. Fetch Donors
$donorList = [];
$dq = $conn->query("SELECT d.*, IFNULL(p.Points_Total, 0) as Points_Total 
                    FROM donor d 
                    LEFT JOIN point p ON d.Donor_ID = p.Donor_ID 
                    WHERE d.Is_Deleted = 0 
                    ORDER BY d.Donor_Name ASC");
while($d = $dq->fetch_assoc()) { $donorList[] = $d; }

// 2. Fetch Rewards
$rewardList = [];
$rq = $conn->query("SELECT Reward_ID, Reward_ItemName, Reward_RequiredPoint, Reward_Stock FROM reward_item WHERE Reward_Status = 'Active' AND Reward_Stock > 0 ORDER BY Reward_ItemName ASC");
while($r = $rq->fetch_assoc()) { $rewardList[] = $r; }

// 3. States
$malaysiaStates = [
    'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 
    'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'
];

$errorMessage = "";
$saveSuccess = false;

// --- HANDLE SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_order'])) {
    $donorId = intval($_POST['donor_id']);
    $rewardId = intval($_POST['reward_id']);
    $quantity = intval($_POST['quantity']);
    if ($quantity < 1) $quantity = 1; 
    
    $address1 = $conn->real_escape_string($_POST['address1']);
    $address2 = $conn->real_escape_string($_POST['address2']); 
    $address3 = $conn->real_escape_string($_POST['address3']); 
    
    $city = $conn->real_escape_string($_POST['city']);
    $state = $conn->real_escape_string($_POST['state']);
    $postal = $conn->real_escape_string($_POST['postal_code']);
    
    $contactRaw = $conn->real_escape_string($_POST['contact']);
    // Ensure contact format
    $contact = (strpos($contactRaw, '+60') === 0) ? $contactRaw : "+60" . $contactRaw;

    // Get Reward Info
    $rwQ = $conn->query("SELECT Reward_RequiredPoint, Reward_Stock FROM reward_item WHERE Reward_ID = $rewardId");
    $rwRow = $rwQ->fetch_assoc();
    $unitPoints = $rwRow['Reward_RequiredPoint'];
    $currentStock = $rwRow['Reward_Stock'];

    // Calculate Total Points Needed
    $totalPointsNeeded = $unitPoints * $quantity;

    // Get Donor Points
    $ptQ = $conn->query("SELECT Points_Total, Points_ID FROM point WHERE Donor_ID = $donorId");
    $ptRow = $ptQ->fetch_assoc();
    $donorHasPoints = $ptRow ? $ptRow['Points_Total'] : 0;
    
    if ($currentStock < $quantity) {
        $errorMessage = "Error: Item does not have enough stock (Requested: $quantity, Available: $currentStock).";
    } elseif ($donorHasPoints < $totalPointsNeeded) {
        $errorMessage = "Error: Donor does not have enough points (Has: $donorHasPoints, Need: $totalPointsNeeded).";
    } else {
        // Deduct Points
        $newPoints = $donorHasPoints - $totalPointsNeeded;
        $conn->query("UPDATE point SET Points_Total = $newPoints, Points_Updated_At = NOW() WHERE Donor_ID = $donorId");
        // Deduct Stock
        $conn->query("UPDATE reward_item SET Reward_Stock = Reward_Stock - $quantity WHERE Reward_ID = $rewardId");

        // Insert
        $sql = "INSERT INTO redemption_order (
            Donor_ID, Reward_ID, Redemption_Quantity, Redemption_PointsSpent, Redemption_Status, 
            Redemption_Address1, Redemption_Address2, Redemption_Address3, 
            Redemption_City, Redemption_State, Redemption_PostalCode, 
            Redemption_ContactNumber, Redemption_Created_At, Redemption_Updated_At
        ) VALUES (
            $donorId, $rewardId, $quantity, $totalPointsNeeded, 'Pending',
            '$address1', '$address2', '$address3', 
            '$city', '$state', '$postal',
            '$contact', NOW(), NOW()
        )";
        
        if ($conn->query($sql)) {
            $saveSuccess = true;
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
    <title>Add Redemption Order - Love Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Styles copied from branch_add.php for consistency */
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #666; font-size: 14px; }
        
        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 900px; margin: 0 auto 30px; }
        .form-header { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; text-align: center; } 
        .form-header h2 { font-size: 18px; color: #333; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }
        
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; } 
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 20px; } 
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; font-size: 14px; box-sizing: border-box; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .required { color: red; margin-left: 3px; font-weight: bold; }
        
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        
        .section-separator { border-top: 1px dashed #ddd; margin: 30px 0 20px; position: relative; }
        .section-separator span { position: absolute; top: -12px; left: 0; background: #fff; padding-right: 10px; font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; }

        .button-group { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        
        .btn-clear { padding: 12px 25px; background: white; color: #555; border: 1px solid #ddd; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-clear:hover { background: #f8f9fa; border-color: #aaa; color: #333; transform: translateY(-1px); }
        
        .btn-save { padding: 12px 25px; background: linear-gradient(135deg, #F28585 0%, #ff9a9a 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(242, 133, 133, 0.4); }

        /* Specific Select2 Override */
        .select2-container .select2-selection--single { height: 42px !important; border: 1px solid #ddd !important; border-radius: 5px !important; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }

        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 12px 15px; background: #f8f9fa; border: 1px solid #ddd; border-right: none; border-radius: 5px 0 0 5px; color: #666; font-weight: bold; font-size: 14px; }
        .phone-input { border-radius: 0 5px 5px 0 !important; }
        
        .custom-alert { position: fixed; top: 20px; right: 20px; background: white; border-left: 5px solid; padding: 15px 20px; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; z-index: 9999; transform: translateX(120%); transition: transform 0.3s ease-out; max-width: 350px; }
        .custom-alert.show { transform: translateX(0); }
        .custom-alert.error { border-color: #dc3545; } .custom-alert.error i { color: #dc3545; }
        .alert-content h4 { margin: 0 0 5px; font-size: 14px; color: #333; } .alert-content p { margin: 0; font-size: 13px; color: #666; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center; }
        .success-modal { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .success-icon { width: 70px; height: 70px; background: #e6f4ea; border-radius: 50%; color: #28a745; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
        .modal-btn-group { display: flex; gap: 10px; justify-content: center; margin-top: 25px; }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        @media (max-width: 768px) { 
            .form-row { flex-direction: column; gap: 0; } 
            .page-header-compact { flex-direction: column; align-items: flex-start; gap: 10px; }
            .header-title { padding-right: 0; text-align: left; width: 100%; }
        }
    </style>
</head>
<body>

    <div id="customAlert" class="custom-alert"><i class="fas" id="alertIcon"></i><div class="alert-content"><h4 id="alertTitle">Title</h4><p id="alertMessage">Message</p></div></div>

    <div id="successModal" class="modal-overlay" style="display: <?php echo $saveSuccess ? 'flex' : 'none'; ?>;">
        <div class="success-modal">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <h2 style="margin-bottom: 10px; font-size: 22px;">Success!</h2>
            <p style="color: #666; line-height: 1.5;">Redemption Order created successfully.</p>
            <div class="modal-btn-group">
                <a href="redemption_order_management.php" class="btn-clear" style="border: 1px solid #ddd; text-decoration:none;">Return to List</a>
                <button type="button" class="btn-save" onclick="window.location.href='redemption_order_add.php'">Add Another</button>
            </div>
        </div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="redemption_order_management.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="header-title">
                    <h1>Add Redemption Order</h1>
                    <p>Create a manual redemption order for a donor.</p>
                </div>
            </div>

            <div class="form-container">
                <form id="addOrderForm" method="POST" action="redemption_order_add.php" onsubmit="return validateAddOrder(event)" novalidate>
                    <input type="hidden" name="add_order" value="1">
                    
                    <div class="form-header"><h2>Order Details</h2></div>

                    <div class="form-group">
                        <label class="form-label">Select Donor <span class="required">*</span></label>
                        <select name="donor_id" class="form-select" required id="add_donor_select" style="width: 100%;">
                            <option value="">-- Choose Donor --</option>
                            <?php foreach($donorList as $d): ?>
                                <option value="<?php echo $d['Donor_ID']; ?>" 
                                        data-points="<?php echo $d['Points_Total']; ?>"
                                        data-contact="<?php echo htmlspecialchars($d['Donor_ContactNumber']); ?>"
                                        data-address1="<?php echo htmlspecialchars($d['Donor_Address1']); ?>"
                                        data-address2="<?php echo htmlspecialchars($d['Donor_Address2']); ?>"
                                        data-address3="<?php echo htmlspecialchars($d['Donor_Address3']); ?>"
                                        data-city="<?php echo htmlspecialchars($d['Donor_City']); ?>"
                                        data-state="<?php echo htmlspecialchars($d['Donor_State']); ?>"
                                        data-postal="<?php echo htmlspecialchars($d['Donor_PostalCode']); ?>">
                                    <?php echo htmlspecialchars($d['Donor_Name']) . " (" . $d['Donor_ICNumber'] . ") - " . $d['Points_Total'] . " pts"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-guide">Points and contact info will load automatically.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="flex:2;">
                            <label class="form-label">Select Reward Item <span class="required">*</span></label>
                            <select name="reward_id" id="add_reward_id" class="form-select" required onchange="calcPoints()">
                                <option value="" data-points="0">-- Choose Reward --</option>
                                <?php foreach($rewardList as $r): ?>
                                    <option value="<?php echo $r['Reward_ID']; ?>" data-points="<?php echo $r['Reward_RequiredPoint']; ?>">
                                        <?php echo htmlspecialchars($r['Reward_ItemName']) . " - " . $r['Reward_RequiredPoint'] . " pts (Stock: " . $r['Reward_Stock'] . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Quantity <span class="required">*</span></label>
                            <input type="number" name="quantity" id="add_quantity" class="form-input" value="1" min="1" required onchange="calcPoints()">
                        </div>
                    </div>
                    
                    <div style="margin-bottom:20px; font-weight: bold; color: #555; text-align:right;" id="pointsSummary">
                        Total Points Required: 0
                    </div>

                    <div class="section-separator"><span>Shipping Information</span></div>

                    <div class="form-group">
                        <label class="form-label">Contact Number <span class="required">*</span></label>
                        <div class="phone-format">
                            <span class="phone-prefix">+60</span>
                            <input type="text" name="contact" id="add_contact" class="form-input phone-input" required placeholder="12-3456789" maxlength="11">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1 <span class="required">*</span></label>
                        <input type="text" name="address1" id="add_address1" class="form-input" required placeholder="Unit No, Building Name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2 <span class="required">*</span></label>
                        <input type="text" name="address2" id="add_address2" class="form-input" required placeholder="Street Name, Area">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" id="add_address3" class="form-input" placeholder="Optional">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code <span class="required">*</span></label>
                            <input type="text" name="postal_code" id="add_postal_code" class="form-input" required placeholder="e.g. 81300">
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <span class="required">*</span></label>
                            <input type="text" name="city" id="add_city" class="form-input" required placeholder="e.g. Skudai">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">State <span class="required">*</span></label>
                        <select name="state" id="add_state_select" class="form-select" required>
                            <option value="">Select State</option>
                            <?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?>
                        </select>
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn-clear" onclick="window.location.href='redemption_order_management.php'"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Create Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('#add_donor_select').select2({ placeholder: "-- Choose Donor --", allowClear: true });
            
            // Auto-fill Address logic
            $('#add_donor_select').on('change', function() {
                var selected = $(this).find(':selected');
                
                $('input[name="address1"]').val(selected.data('address1'));
                $('input[name="address2"]').val(selected.data('address2'));
                $('input[name="address3"]').val(selected.data('address3'));
                $('input[name="city"]').val(selected.data('city'));
                $('input[name="postal_code"]').val(selected.data('postal'));
                let c = selected.data('contact') + '';
                if(c && c.startsWith('+60')) c = c.substring(3);
                $('#add_contact').val(c);
                if(selected.data('state')) $('select[name="state"]').val(selected.data('state')).change();
                
                calcPoints(); 
            });

            // Postcode Logic
             document.getElementById('add_postal_code').addEventListener('input', function() {
                const val = this.value.replace(/\D/g, '');
                if (val.length >= 2) {
                    const prefix = parseInt(val.substring(0, 2));
                    let state = "";
                    if (prefix >= 1 && prefix <= 2) state = "Perlis"; else if (prefix >= 5 && prefix <= 9) state = "Kedah"; else if (prefix >= 10 && prefix <= 14) state = "Penang";
                    else if (prefix >= 15 && prefix <= 18) state = "Kelantan"; else if (prefix >= 20 && prefix <= 24) state = "Terengganu"; else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                    else if (prefix >= 30 && prefix <= 36) state = "Perak"; else if (prefix >= 40 && prefix <= 48) state = "Selangor"; else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                    else if (prefix >= 62 && prefix <= 62) state = "Putrajaya"; else if (prefix >= 63 && prefix <= 68) state = "Selangor"; else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                    else if (prefix >= 75 && prefix <= 78) state = "Melaka"; else if (prefix >= 79 && prefix <= 86) state = "Johor"; else if (prefix == 87) state = "Labuan";
                    else if (prefix >= 88 && prefix <= 91) state = "Sabah"; else if (prefix >= 93 && prefix <= 98) state = "Sarawak";
                    if (state) $('#add_state_select').val(state).trigger('change');
                }
            });

            const contactInput = document.getElementById('add_contact');
            contactInput.addEventListener('input', function(e) { 
                let val = this.value.replace(/\D/g, ''); if (val.length > 11) val = val.substring(0, 11); 
                let newVal = val; if (val.length > 2) newVal = val.substring(0, 2) + '-' + val.substring(2); 
                this.value = newVal; 
            });
        });

        function calcPoints() {
            const donorOpt = $('#add_donor_select').find(':selected');
            const rewardOpt = document.getElementById('add_reward_id').selectedOptions[0];
            const qtyInput = document.getElementById('add_quantity');
            
            let qty = parseInt(qtyInput.value);
            if(isNaN(qty) || qty < 1) { qty = 1; qtyInput.value = 1; }

            const unitPts = parseInt(rewardOpt.getAttribute('data-points') || 0);
            const donorPts = parseInt(donorOpt.attr('data-points') || 0);
            
            const totalNeeded = unitPts * qty;
            const summary = document.getElementById('pointsSummary');
            
            summary.innerText = "Total Points Required: " + totalNeeded + " (Donor Has: " + donorPts + ")";
            
            if(donorPts < totalNeeded) {
                summary.style.color = 'red';
            } else {
                summary.style.color = '#28a745';
            }
        }

        function validateAddOrder(e) {
            e.preventDefault();
            const donorOpt = $('#add_donor_select').find(':selected');
            const rewardOpt = document.getElementById('add_reward_id').selectedOptions[0];
            const qty = parseInt(document.getElementById('add_quantity').value);
            
            if (!donorOpt.val()) { Swal.fire('Error', 'Please select a donor', 'error'); return false; }
            if (!rewardOpt.value) { Swal.fire('Error', 'Please select a reward', 'error'); return false; }
            
            const unitPts = parseInt(rewardOpt.getAttribute('data-points') || 0);
            const donorPts = parseInt(donorOpt.attr('data-points') || 0);
            
            if (donorPts < (unitPts * qty)) {
                Swal.fire('Insufficient Points', 'Donor does not have enough points.', 'error');
                return false;
            }

            document.getElementById('addOrderForm').submit();
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

        <?php if($errorMessage): ?>
            showSystemAlert("<?php echo addslashes($errorMessage); ?>", 'error');
        <?php endif; ?>
    </script>
</body>
</html>