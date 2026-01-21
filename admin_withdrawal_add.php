<?php
// admin_withdrawal_add.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- Get Current Admin Info ---
$currentAdminId = $_SESSION['admin_id'];
$adminName = "Admin";
$adminResult = $conn->query("SELECT Admin_Name FROM admin WHERE Admin_ID = $currentAdminId");
if ($adminResult && $adminResult->num_rows > 0) {
    $adminName = $adminResult->fetch_assoc()['Admin_Name'];
}

// --- FILE UPLOAD HELPER ---
function handleWithdrawalUpload($files) {
    $paths = [];
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $dir = "uploads/withdrawals/";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    if (!is_array($files['name'])) return [];

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] == 0) {
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $name = 'wd_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
                if (move_uploaded_file($files['tmp_name'][$i], $dir . $name)) {
                    $paths[] = $dir . $name;
                }
            }
        }
    }
    return $paths;
}

// --- HANDLE SUBMISSION ---
$errorMessage = null;
$showSuccessModal = false;

// Check for success flag from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $showSuccessModal = true;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_withdrawal'])) {
    $errors = []; 

    $w_type = $_POST['withdrawal_type'] ?? '';
    $w_amount = floatval($_POST['amount'] ?? 0);
    $w_bank_name = $_POST['bank_name'] ?? '';
    $w_bank_acc = $_POST['bank_account'] ?? '';
    
    if (empty($w_type)) $errors[] = "Withdrawal Source Type is required.";
    if ($w_amount <= 0) $errors[] = "Amount must be greater than RM 0.00.";

    $branch_id = null;
    $activity_id = null;
    $case_id = null;
    $current_balance = 0;
    
    // --- REVISED BALANCE CHECK LOGIC (FIXED) ---
    if ($w_type == 'branch') {
        $branch_id = $_POST['target_id_branch'] ?? null;
        if(!$branch_id) $errors[] = "Please select a Branch.";
        else {
            // Only count orders/withdrawals strictly for the branch (not its activities/cases)
            $in = $conn->query("SELECT SUM(Order_Amount) as t FROM orders WHERE Branch_ID = $branch_id AND Activity_ID IS NULL AND Case_ID IS NULL AND Order_PaymentStatus IN ('Success','Completed')")->fetch_assoc()['t'] ?? 0;
            $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Branch_ID = $branch_id AND Activity_ID IS NULL AND Case_ID IS NULL AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
            $current_balance = $in - $out;
        }

    } elseif ($w_type == 'activity') {
        $activity_id = $_POST['target_id_activity'] ?? null;
        if(!$activity_id) $errors[] = "Please select an Activity.";
        else {
            $act_sql = "SELECT Branch_ID, Activity_GetAmount FROM activity WHERE Activity_ID = '$activity_id'";
            $act_res = $conn->query($act_sql);
            if($act_res->num_rows > 0) {
                $act_row = $act_res->fetch_assoc();
                $branch_id = $act_row['Branch_ID'];
                $raised = $act_row['Activity_GetAmount'];
                $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Activity_ID = $activity_id AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
                $current_balance = $raised - $out;
            }
        }

    } elseif ($w_type == 'case') {
        $case_id = $_POST['target_id_case'] ?? null;
        $handling_branch = $_POST['handling_branch_id'] ?? null; 
        if(!$case_id) $errors[] = "Please select a Special Case.";
        if(!$handling_branch) $errors[] = "Please select a Processing Branch.";
        else {
            $branch_id = $handling_branch; 
            $case_row = $conn->query("SELECT Raised_Amount FROM special_case WHERE Case_ID = $case_id")->fetch_assoc();
            $raised = $case_row['Raised_Amount'] ?? 0;
            $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Case_ID = $case_id AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
            $current_balance = $raised - $out;
        }
    }

    if ($w_amount > $current_balance) {
        $errors[] = "Insufficient funds! Available: RM " . number_format($current_balance, 2);
    }

    $proof_json = "[]";
    if (empty($errors)) {
        if (isset($_FILES['proof_file']) && !empty($_FILES['proof_file']['name'][0])) {
            $uploaded_paths = handleWithdrawalUpload($_FILES['proof_file']);
            if (empty($uploaded_paths)) {
                $errors[] = "Failed to upload files. Allowed: JPG, PNG, PDF.";
            } else {
                $proof_json = json_encode($uploaded_paths);
            }
        } else {
            $errors[] = "At least one Reference Proof file is required.";
        }
    }

    if (empty($errors) && $branch_id && $w_amount > 0) {
        // Auto Approve
        $stmt = $conn->prepare("INSERT INTO withdrawals (Branch_ID, Activity_ID, Case_ID, Amount, Bank_Name, Bank_Account, Reference_Proof, Status, Admin_ID, Request_Date, Processed_Date, Approved_By) VALUES (?, ?, ?, ?, ?, ?, ?, 'Completed', ?, NOW(), NOW(), ?)");
        
        $stmt->bind_param("iiidsssii", $branch_id, $activity_id, $case_id, $w_amount, $w_bank_name, $w_bank_acc, $proof_json, $currentAdminId, $currentAdminId);
        
        if ($stmt->execute()) {
            // Notification
            $newMsg = "New Withdrawal: RM " . number_format($w_amount, 2) . " (Auto-Approved) by " . $adminName;
            $allAdmins = $conn->query("SELECT Admin_ID FROM admin WHERE Is_Deleted = 0");
            while($rw = $allAdmins->fetch_assoc()) {
                $recipientID = $rw['Admin_ID'];
                $nStmt = $conn->prepare("INSERT INTO admin_notifications (Message, Type, Link, Is_Read, Created_At) VALUES (?, 'Payment', 'payment_management.php?tab=withdrawals', 0, NOW())");
                $nStmt->bind_param("s", $newMsg);
                $nStmt->execute();
            }

            // Redirect to self with success flag to show modal
            header("Location: admin_withdrawal_add.php?success=1");
            exit();
        } else {
            $errorMessage = "Database Error: " . $conn->error;
        }
        $stmt->close();
    } elseif (!empty($errors)) {
        $errorMessage = implode("<br>", $errors);
    }
}

// --- PREPARE DATA FOR JS ---
$branchList = [];
$branches = $conn->query("SELECT Branch_ID, Branch_Name, Branch_BankName, Branch_BankAccount FROM branch WHERE Is_Deleted = 0 ORDER BY Branch_Name ASC");
while($b = $branches->fetch_assoc()) {
    $bid = $b['Branch_ID'];
    $in = $conn->query("SELECT SUM(Order_Amount) as t FROM orders WHERE Branch_ID = $bid AND Activity_ID IS NULL AND Case_ID IS NULL AND Order_PaymentStatus IN ('Success','Completed')")->fetch_assoc()['t'] ?? 0;
    $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Branch_ID = $bid AND Activity_ID IS NULL AND Case_ID IS NULL AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
    $b['balance'] = $in - $out;
    $branchList[] = $b;
}

$activityList = [];
$activities = $conn->query("SELECT a.Activity_ID, a.Activity_Name, a.Activity_GetAmount, a.Branch_ID, b.Branch_BankName, b.Branch_BankAccount 
                            FROM activity a JOIN branch b ON a.Branch_ID = b.Branch_ID 
                            WHERE a.Activity_Status != 'Cancelled' ORDER BY a.Activity_Name ASC");
while($a = $activities->fetch_assoc()) {
    $aid = $a['Activity_ID'];
    $raised = $a['Activity_GetAmount']; 
    $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Activity_ID = $aid AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
    $a['balance'] = $raised - $out;
    $activityList[] = $a;
}

$caseList = [];
$cases = $conn->query("SELECT Case_ID, Case_Title, Case_BankName, Case_BankAccount, Raised_Amount FROM special_case WHERE Case_Status != 'Cancelled' ORDER BY Case_Title ASC");
while($c = $cases->fetch_assoc()) {
    $cid = $c['Case_ID'];
    $raised = $c['Raised_Amount'];
    $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Case_ID = $cid AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
    $c['balance'] = $raised - $out;
    $caseList[] = $c;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Withdrawal - Finance Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Compact Header Styles */
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #666; font-size: 14px; }

        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 900px; margin: 0 auto 30px; }
        .form-header { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-header h2 { font-size: 20px; color: #333; font-weight: 600; }
        
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; } 
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        
        .form-group { margin-bottom: 20px; } 
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; transition: 0.3s; font-size: 14px; box-sizing: border-box; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-input:disabled { background-color: #f8f9fa; cursor: not-allowed; color: #6c757d; }
        
        .required { color: red; margin-left: 3px; font-weight: bold; }
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        
        /* Error Styles */
        .input-error { border-color: #dc3545 !important; background-color: #fff5f5; }
        .inline-error { color: #dc3545; font-size: 12px; margin-top: 4px; display: block; font-weight: 500; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .balance-info { font-weight: 600; color: #28a745; margin-top: 5px; font-size: 13px; display: none; }
        .balance-info.error { color: #dc3545; }

        /* Upload Styles */
        .file-upload-area { border: 2px dashed #ddd; padding: 25px; text-align: center; border-radius: 8px; background: #fafafa; cursor: pointer; transition: all 0.3s; }
        .file-upload-area:hover { border-color: var(--primary); background: #fff5f5; }
        .file-upload-area i { font-size: 24px; color: #aaa; margin-bottom: 10px; }
        .file-upload-area p { margin: 0; font-size: 13px; color: #666; }
        
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 12px; margin-top: 15px; }
        .preview-item { position: relative; height: 80px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: 1px solid #eee; background:white; display: flex; align-items: center; justify-content: center; }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .preview-item i { font-size: 30px; color: #dc3545; }
        .remove-img-btn { position: absolute; top: 2px; right: 2px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; }

        .button-group { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn-clear { padding: 12px 25px; background: white; color: #555; border: 1px solid #ddd; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-clear:hover { background: #f8f9fa; border-color: #aaa; color: #333; }
        .btn-save { padding: 12px 25px; background: linear-gradient(135deg, #F28585 0%, #ff9a9a 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(242, 133, 133, 0.3); display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(242, 133, 133, 0.4); }

        /* Custom Alert - Updated for Red Error */
        .custom-alert { position: fixed; top: 20px; right: 20px; background: white; border-left: 5px solid; padding: 15px 20px; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; z-index: 9999; transform: translateX(120%); transition: transform 0.3s ease-out; max-width: 350px; }
        .custom-alert.show { transform: translateX(0); }
        .custom-alert.error { border-color: #dc3545; } 
        .custom-alert.error i { color: #dc3545; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center; }
        .modal-box { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .modal-icon-warning { width: 70px; height: 70px; background: #fff3cd; border-radius: 50%; color: #856404; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
        .modal-icon-success { width: 70px; height: 70px; background: #e6f4ea; border-radius: 50%; color: #28a745; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
        .modal-btn-group { display: flex; gap: 10px; justify-content: center; margin-top: 25px; }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>

    <div id="customAlert" class="custom-alert error"><i class="fas fa-exclamation-circle"></i><div class="alert-content"><h4>Error</h4><p id="alertMessage">Message</p></div></div>

    <div id="confirmModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon-warning"><i class="fas fa-question"></i></div>
            <h2 style="margin-bottom: 10px; font-size: 22px;">Confirm Withdrawal</h2>
            <p style="color: #666; line-height: 1.5;">Are you sure you want to submit this withdrawal request?</p>
            <div class="modal-btn-group">
                <button type="button" class="btn-clear" onclick="closeConfirmModal()">No, Check Again</button>
                <button type="button" class="btn-save" onclick="submitForm()">Yes, Submit</button>
            </div>
        </div>
    </div>

    <div id="successModal" class="modal-overlay" style="display: <?php echo $showSuccessModal ? 'flex' : 'none'; ?>;">
        <div class="modal-box">
            <div class="modal-icon-success"><i class="fas fa-check"></i></div>
            <h2 style="margin-bottom: 10px; font-size: 22px;">Withdrawal Successful!</h2>
            <p style="color: #666; line-height: 1.5;">The fund withdrawal has been processed and approved.</p>
            <div class="modal-btn-group">
                <a href="payment_management.php?tab=withdrawals" class="btn-clear" style="border: 1px solid #ddd;">Back to List</a>
                <button type="button" class="btn-save" onclick="document.getElementById('successModal').style.display='none';">Add Another</button>
            </div>
        </div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="payment_management.php?tab=withdrawals" class="back-btn"><i class="fas fa-arrow-left"></i> Back to History</a>
                <div class="header-title">
                    <h1>Process Withdrawal</h1>
                    <p>Request and record fund withdrawals from donation accounts.</p>
                </div>
            </div>

            <div class="form-container">
                <form id="withdrawForm" action="admin_withdrawal_add.php" method="POST" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="submit_withdrawal" value="1">
                    
                    <div class="form-header">
                        <h2>Source & Amount</h2>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Withdrawal Source Type <span class="required">*</span></label>
                        <select name="withdrawal_type" id="withdrawal_type" class="form-select" onchange="toggleSourceType()">
                            <option value="">-- Select Source --</option>
                            <option value="branch">Branch Fund (General Branch Expenses)</option>
                            <option value="activity">Activity Fund (Specific Event Expenses)</option>
                            <option value="case">Special Case Fund (Beneficiary Payouts)</option>
                        </select>
                        <span class="form-guide">Choose where the money is coming from.</span>
                    </div>

                    <div class="form-group" id="group_branch" style="display:none;">
                        <label class="form-label">Select Branch <span class="required">*</span></label>
                        <select name="target_id_branch" id="target_id_branch" class="form-select" onchange="updateBankAndBalance('branch')">
                            <option value="">-- Select Branch --</option>
                        </select>
                        <div id="balance_branch" class="balance-info"></div>
                        <span class="form-guide">Funds will be deducted from this Branch's general pool.</span>
                    </div>

                    <div class="form-group" id="group_activity" style="display:none;">
                        <label class="form-label">Select Activity <span class="required">*</span></label>
                        <select name="target_id_activity" id="target_id_activity" class="form-select" onchange="updateBankAndBalance('activity')">
                            <option value="">-- Select Activity --</option>
                        </select>
                        <div id="balance_activity" class="balance-info"></div>
                        <span class="form-guide">Funds collected specifically for this activity.</span>
                    </div>

                    <div class="form-group" id="group_case" style="display:none;">
                        <label class="form-label">Select Special Case <span class="required">*</span></label>
                        <select name="target_id_case" id="target_id_case" class="form-select" onchange="updateBankAndBalance('case')">
                            <option value="">-- Select Case --</option>
                        </select>
                        <div id="balance_case" class="balance-info"></div>
                        <span class="form-guide">Donations raised for a specific medical or emergency case.</span>
                    </div>

                    <div class="form-group" id="group_handling_branch" style="display:none;">
                        <label class="form-label">Processing Branch <span class="required">*</span></label>
                        <select name="handling_branch_id" id="handling_branch_id" class="form-select">
                            <option value="">-- Select Processing Branch --</option>
                        </select>
                        <span class="form-guide">Which branch is handling the disbursement for this case?</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Withdrawal Amount (RM) <span class="required">*</span></label>
                            <input type="number" step="0.01" min="0" name="amount" id="withdraw_amount" class="form-input" placeholder="0.00" onkeypress="return (event.charCode != 45)">
                            <span class="form-guide">Must not exceed available balance.</span>
                        </div>
                    </div>

                    <div class="form-header" style="margin-top: 20px;">
                        <h2>Banking Details (Auto-Filled)</h2>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" id="bank_name" class="form-input" readonly style="background-color: #f8f9fa;">
                            <span class="form-guide">Registered bank for the selected entity.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="bank_account" id="bank_account" class="form-input" readonly style="background-color: #f8f9fa;">
                            <span class="form-guide">Verify this matches the physical documents.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Proof of Withdrawal / Receipt <span class="required">*</span></label>
                        <div class="file-upload-area" id="uploadArea" onclick="document.getElementById('proof_file').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload Proof (Images or PDF)</p>
                            <input type="file" id="proof_file" name="proof_file[]" multiple accept=".jpg,.jpeg,.png,.pdf" style="display: none;" onchange="handleFileSelect(event)">
                        </div>
                        <div class="preview-grid" id="proof_preview_container"></div>
                        <span class="form-guide">Upload transfer receipts, invoices, or approval letters (Max 5 files).</span>
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn-clear" onclick="window.location.reload()"><i class="fas fa-eraser"></i> Reset</button>
                        <button type="button" class="btn-save" onclick="confirmSubmission()"><i class="fas fa-check-circle"></i> Confirm Withdrawal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Pass PHP Data to JS
        const branchesData = <?php echo json_encode($branchList); ?>;
        const activitiesData = <?php echo json_encode($activityList); ?>;
        const casesData = <?php echo json_encode($caseList); ?>;
        let currentMaxBalance = 0;

        // Initialize Dynamic Dropdowns
        function populateSelect(id, data, valKey, textKey) {
            const sel = document.getElementById(id);
            sel.innerHTML = '<option value="">-- Select --</option>';
            data.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item[valKey];
                opt.text = item[textKey];
                sel.add(opt);
            });
        }

        populateSelect('target_id_branch', branchesData, 'Branch_ID', 'Branch_Name');
        populateSelect('target_id_activity', activitiesData, 'Activity_ID', 'Activity_Name');
        populateSelect('target_id_case', casesData, 'Case_ID', 'Case_Title');
        
        // Populate Handling Branch
        const handleSel = document.getElementById('handling_branch_id');
        branchesData.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.Branch_ID;
            opt.text = b.Branch_Name;
            handleSel.add(opt);
        });

        function toggleSourceType() {
            var type = document.getElementById('withdrawal_type').value;
            
            // Hide all specialized groups
            document.getElementById('group_branch').style.display = 'none';
            document.getElementById('group_activity').style.display = 'none';
            document.getElementById('group_case').style.display = 'none';
            document.getElementById('group_handling_branch').style.display = 'none';
            
            // Reset Fields
            document.getElementById('bank_name').value = '';
            document.getElementById('bank_account').value = '';
            document.getElementById('withdraw_amount').value = '';
            currentMaxBalance = 0;

            if (type === 'branch') {
                document.getElementById('group_branch').style.display = 'block';
            } else if (type === 'activity') {
                document.getElementById('group_activity').style.display = 'block';
            } else if (type === 'case') {
                document.getElementById('group_case').style.display = 'block';
                document.getElementById('group_handling_branch').style.display = 'block';
            }
        }

        function updateBankAndBalance(type) {
            let id, data, balId;
            let bankKey, accKey;

            if (type === 'branch') {
                id = document.getElementById('target_id_branch').value;
                data = branchesData.find(b => b.Branch_ID == id);
                bankKey = 'Branch_BankName'; accKey = 'Branch_BankAccount';
                balId = 'balance_branch';
            } else if (type === 'activity') {
                id = document.getElementById('target_id_activity').value;
                data = activitiesData.find(a => a.Activity_ID == id);
                bankKey = 'Branch_BankName'; accKey = 'Branch_BankAccount'; 
                balId = 'balance_activity';
            } else if (type === 'case') {
                id = document.getElementById('target_id_case').value;
                data = casesData.find(c => c.Case_ID == id);
                bankKey = 'Case_BankName'; accKey = 'Case_BankAccount';
                balId = 'balance_case';
            }

            // Reset Balance Displays
            document.querySelectorAll('.balance-info').forEach(e => e.style.display = 'none');
            const balEl = document.getElementById(balId);

            if (data) {
                document.getElementById('bank_name').value = data[bankKey] || 'N/A';
                document.getElementById('bank_account').value = data[accKey] || 'N/A';
                
                currentMaxBalance = parseFloat(data.balance);
                balEl.innerHTML = "Available Balance: RM " + currentMaxBalance.toFixed(2);
                balEl.style.display = 'block';
                
                const amtInput = document.getElementById('withdraw_amount');
                if (currentMaxBalance <= 0) {
                    balEl.classList.add('error');
                    amtInput.disabled = true;
                    amtInput.placeholder = "Insufficient Funds";
                } else {
                    balEl.classList.remove('error');
                    amtInput.disabled = false;
                    amtInput.placeholder = "e.g. 500.00";
                    amtInput.max = currentMaxBalance;
                }
            }
        }

        // File Upload Logic
        let selectedFiles = [];
        function handleFileSelect(event) {
            const input = event.target;
            const newFiles = Array.from(input.files);
            if (selectedFiles.length + newFiles.length > 5) {
                showAlert("Max 5 files allowed.");
                input.value = ''; return;
            }
            selectedFiles = selectedFiles.concat(newFiles);
            updateFileInput(); renderPreview();
        }
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileInput(); renderPreview();
        }
        function updateFileInput() {
            const input = document.getElementById('proof_file');
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            input.files = dt.files;
        }
        function renderPreview() {
            const c = document.getElementById('proof_preview_container');
            c.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'preview-item';
                if (file.type === 'application/pdf') {
                    div.innerHTML = `<i class="fas fa-file-pdf"></i><button type="button" class="remove-img-btn" onclick="removeFile(${index})"><i class="fas fa-times"></i></button>`;
                } else {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        div.innerHTML = `<img src="${e.target.result}"><button type="button" class="remove-img-btn" onclick="removeFile(${index})"><i class="fas fa-times"></i></button>`;
                    };
                    reader.readAsDataURL(file);
                }
                c.appendChild(div);
            });
        }

        function showAlert(msg) {
            const a = document.getElementById('customAlert');
            // Force add error class to ensure red color
            a.classList.add('error'); 
            document.getElementById('alertMessage').textContent = msg;
            a.classList.add('show');
            setTimeout(() => a.classList.remove('show'), 4000);
        }

        <?php if($errorMessage): ?>
            showAlert("<?php echo addslashes($errorMessage); ?>");
        <?php endif; ?>

        // Error Handling Function
        function showFieldError(elementId, message) {
            const el = document.getElementById(elementId);
            if(el) {
                el.classList.add('input-error');
                // Check if msg already exists
                let parent = el.parentNode;
                if(!parent.querySelector('.inline-error')) {
                    const msgDiv = document.createElement('div');
                    msgDiv.className = 'inline-error';
                    msgDiv.innerText = message;
                    parent.appendChild(msgDiv);
                } else {
                    // Update existing error message
                    parent.querySelector('.inline-error').innerText = message;
                }
            }
        }

        function clearFieldErrors() {
            document.querySelectorAll('.input-error').forEach(e => e.classList.remove('input-error'));
            document.querySelectorAll('.inline-error').forEach(e => e.remove());
        }

        function confirmSubmission() {
            if(validateForm()) {
                document.getElementById('confirmModal').style.display = 'flex';
            }
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        function submitForm() {
            document.getElementById('confirmModal').style.display = 'none';
            document.getElementById('withdrawForm').submit();
        }

        function validateForm() {
            clearFieldErrors();
            let hasError = false;

            const type = document.getElementById('withdrawal_type').value;
            const amt = parseFloat(document.getElementById('withdraw_amount').value);
            
            if(!type) { 
                showFieldError('withdrawal_type', "Please select a source type."); 
                hasError = true; 
            } else {
                if(type === 'branch' && !document.getElementById('target_id_branch').value) { 
                    showFieldError('target_id_branch', "Please select a Branch."); hasError = true; 
                }
                if(type === 'activity' && !document.getElementById('target_id_activity').value) { 
                    showFieldError('target_id_activity', "Please select an Activity."); hasError = true; 
                }
                if(type === 'case') {
                    if(!document.getElementById('target_id_case').value) { showFieldError('target_id_case', "Please select a Case."); hasError = true; }
                    if(!document.getElementById('handling_branch_id').value) { showFieldError('handling_branch_id', "Please select a Processing Branch."); hasError = true; }
                }
            }

            if(isNaN(amt) || amt <= 0) { 
                showFieldError('withdraw_amount', "Amount must be greater than 0."); hasError = true; 
            } else if(amt > currentMaxBalance) { 
                // Enhanced Error Message showing Max Amount
                showFieldError('withdraw_amount', "Insufficient funds. Max allowed: RM " + currentMaxBalance.toFixed(2)); 
                hasError = true; 
            }
            
            if(selectedFiles.length === 0) { 
                // Upload area doesn't have an input ID visible, apply to area
                const area = document.getElementById('uploadArea');
                area.style.borderColor = "#dc3545";
                area.style.backgroundColor = "#fff5f5";
                
                if(!area.nextElementSibling || !area.nextElementSibling.classList.contains('inline-error')) {
                     // Add error msg after preview grid
                     const msgDiv = document.createElement('div');
                     msgDiv.className = 'inline-error';
                     msgDiv.innerText = "Proof of withdrawal is required.";
                     document.getElementById('proof_preview_container').after(msgDiv);
                }
                hasError = true; 
            } else {
                const area = document.getElementById('uploadArea');
                area.style.borderColor = "";
                area.style.backgroundColor = "";
            }
            
            if(hasError) {
                showAlert("Please fix the errors highlighted in red.");
                return false;
            }

            return true;
        }
    </script>
</body>
</html>