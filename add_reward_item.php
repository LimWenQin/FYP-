<?php
// add_reward_item.php
session_start();

// Check login status
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once 'dataconnection.php'; 

// --- GET CURRENT USER ---
$currentAdminId = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : $_SESSION['staff_id'];

// --- CONFIG ---
$categoryPrefixes = [
    'Household' => 'HO', 'Apparel' => 'AP', 'Handicraft' => 'HA',
    'Electronics' => 'EL', 'Food' => 'FD', 'Voucher' => 'VO', 'Others' => 'OT'
];
$categories = array_keys($categoryPrefixes);
$branchTypes = ['Old Folks Home', 'Orphanage', 'Disabled Care Center'];
define('MAX_STOCK_LIMIT', 500);
define('LOW_STOCK_THRESHOLD', 15);

// --- FETCH BRANCHES ---
$branchesData = [];
$brSql = "SELECT Branch_Name, Branch_Type FROM branch WHERE Is_Deleted = 0 ORDER BY Branch_Name ASC";
$brResult = $conn->query($brSql);
if ($brResult) {
    while ($brRow = $brResult->fetch_assoc()) {
        $branchesData[$brRow['Branch_Type']][] = $brRow['Branch_Name'];
    }
}
$jsonBranches = json_encode($branchesData);

// --- HELPER FUNCTIONS ---
function generateRewardCode($conn, $category, $prefixes) {
    $prefix = isset($prefixes[$category]) ? $prefixes[$category] : 'OT';
    $sql = "SELECT Reward_Code FROM reward_item ORDER BY Reward_ID DESC LIMIT 1";
    $result = $conn->query($sql);
    $newNumber = 1; 
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $parts = explode('-', $row['Reward_Code']);
        if (count($parts) > 1) {
            $newNumber = intval($parts[1]) + 1;
        }
    }
    return $prefix . '-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT); 
}

function logRewardAction($conn, $rewardId, $adminId, $type, $details) {
    $details = $conn->real_escape_string($details);
    $sql = "INSERT INTO reward_logs (Reward_ID, Admin_ID, Action_Type, Action_Details) 
            VALUES ('$rewardId', '$adminId', '$type', '$details')";
    $conn->query($sql);
}

function handleImageUpload($file) {
    if (isset($file) && $file['error'] == 0) {
        $targetDir = "uploads/rewards/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = "reward_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($file['tmp_name'], $targetDir . $fileName)) return $fileName;
    }
    return null;
}

// Variables for UI feedback
$saveSuccess = false;
$errorMsg = "";

// --- HANDLE ADD REWARD ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_reward'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $category = $conn->real_escape_string($_POST['category']); 
    $supplier = $conn->real_escape_string($_POST['supplier']); 
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL; 
    $desc = $conn->real_escape_string($_POST['description']);
    
    // Category Note
    $categoryNote = NULL;
    if ($category === 'Others' && !empty($_POST['other_specify'])) {
        $categoryNote = $conn->real_escape_string($_POST['other_specify']);
    }

    $points = (int)$_POST['points'];
    $stock = (int)$_POST['stock'];
    
    if ($points < 0) $points = 0;
    if ($stock < 0) $stock = 0;

    if ($stock > MAX_STOCK_LIMIT) {
        $errorMsg = "Initial stock ($stock) exceeds maximum limit of " . MAX_STOCK_LIMIT . ".";
    } elseif (empty($_FILES['photo']['name'])) {
        $errorMsg = "Image is required.";
    } else {
        $status = ($stock == 0) ? 'Inactive' : (($stock < LOW_STOCK_THRESHOLD) ? 'Low Stock' : 'Active');
        $imageName = handleImageUpload($_FILES['photo']);
        $expiryVal = $expiryDate ? "'$expiryDate'" : "NULL";
        $noteVal = $categoryNote ? "'$categoryNote'" : "NULL"; 

        $newCode = generateRewardCode($conn, $category, $categoryPrefixes);

        $sql = "INSERT INTO reward_item (Reward_Code, Reward_ItemName, Reward_Category, Reward_Category_Note, Reward_Description, Reward_RequiredPoint, Reward_Supplier, Reward_Stock, Reward_ExpiryDate, Reward_Status, Reward_PhotoPath) 
                VALUES ('$newCode', '$name', '$category', $noteVal, '$desc', '$points', '$supplier', '$stock', $expiryVal, '$status', '$imageName')";
        
        if ($conn->query($sql)) {
            $newId = $conn->insert_id;
            logRewardAction($conn, $newId, $currentAdminId, 'Create', "Created item $newCode ($name). Initial Stock: $stock");
            $saveSuccess = true; 
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
    <title>Add Reward Item - DonationMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    
    <style>
        /* Local Page Styles */
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; cursor: pointer; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #666; font-size: 14px; }
        
        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 800px; margin: 0 auto 30px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; font-size: 14px; }
        .form-control, .form-select { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; transition: 0.3s; font-size: 14px; box-sizing: border-box; }
        .form-control:focus, .form-select:focus { border-color: #F28585; }
        .form-row { display: flex; gap: 20px; } .form-row .form-group { flex: 1; margin-bottom: 0; }
        
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        .required-star { color: red; margin-left: 3px; font-weight: bold; }
        
        /* --- IMAGE UI --- */
        .reward-img-preview { 
            width: 200px; 
            height: 200px; 
            border-radius: 8px; 
            border: 2px dashed #ccc; 
            margin-bottom: 10px; 
            background: #fcfcfc; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            overflow: visible; 
            position: relative;
        }
        .reward-img-preview img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }
        .default-icon { font-size: 48px; color: #ddd; }
        
        /* --- REMOVE IMAGE BUTTON --- */
        .remove-img-btn { 
            position: absolute; 
            top: -10px; 
            right: -10px; 
            background: #dc3545; 
            color: white; 
            border: 2px solid white; 
            border-radius: 50%; 
            width: 26px; 
            height: 26px; 
            display: none; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            font-size: 12px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
            transition: transform 0.2s; 
            z-index: 10; 
        }
        .remove-img-btn:hover { transform: scale(1.1); background: #bd2130; }
        
        .file-upload { text-align: center; }
        .file-upload-label { display: inline-block; padding: 8px 20px; background: white; border: 1px solid #ddd; border-radius: 5px; cursor: pointer; font-size: 13px; color: #555; transition: 0.2s; font-weight: 600; }
        .file-upload-label:hover { background: #f0f0f0; border-color: #ccc; }
        
        .file-upload input[type="file"] { display: none; }
        
        .button-group { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn-clear { padding: 12px 25px; background: white; color: #555; border: 1px solid #ddd; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-clear:hover { background: #f8f9fa; border-color: #aaa; color: #333; transform: translateY(-1px); }
        .btn-submit { padding: 12px 25px; background: linear-gradient(135deg, #F28585 0%, #ff9a9a 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(242, 133, 133, 0.3); }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(242, 133, 133, 0.4); }

        .input-error { border-color: #dc3545 !important; background-color: #fff5f5; }
        .reward-img-preview.input-error { border-color: #dc3545; background-color: #fff5f5; }
        
        .inline-error { color: #dc3545; font-size: 11px; margin-top: 4px; display: block; font-weight: 500;}
        
        /* --- CUSTOM ALERT --- */
        .custom-alert { position: fixed; top: 20px; right: 20px; background: white; border-left: 5px solid; padding: 15px 20px; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; z-index: 9999; transform: translateX(120%); transition: transform 0.3s ease-out; max-width: 350px; }
        .custom-alert.show { transform: translateX(0); }
        .custom-alert.error { border-color: #dc3545; } .custom-alert.error i { color: #dc3545; }
        .custom-alert.success { border-color: #28a745; } .custom-alert.success i { color: #28a745; }
        .alert-content h4 { margin: 0 0 5px; font-size: 14px; color: #333; } .alert-content p { margin: 0; font-size: 13px; color: #666; }

        /* --- SUCCESS MODAL --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center; }
        .success-modal { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .success-icon { width: 70px; height: 70px; background: #e6f4ea; border-radius: 50%; color: #28a745; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
        .modal-btn-group { display: flex; gap: 10px; justify-content: center; margin-top: 25px; }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>
    
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
            <h2 style="margin-bottom: 10px; font-size: 22px;">Success!</h2>
            <p style="color: #666; line-height: 1.5;">New Reward Item has been successfully added to the system.</p>
            <div class="modal-btn-group">
                <button type="button" class="btn-clear" style="border: 1px solid #ddd; text-decoration: none;" onclick="goBackOrClose()">Return to List</button>
                <button type="button" class="btn-submit" onclick="document.getElementById('successModal').style.display='none'; clearForm();">Add Another Item</button>
            </div>
        </div>
    </div>

    <?php include 'admin_sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="javascript:void(0);" class="back-btn" onclick="goBackOrClose()">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <div class="header-title">
                    <h1>Add New Item</h1>
                    <p>Register a new reward item for donor redemption.</p>
                </div>
            </div>

            <div class="form-container">
                <form id="addForm" method="POST" enctype="multipart/form-data" onsubmit="return validateRewardForm()" novalidate>
                    <input type="hidden" name="add_reward" value="1">
                    
                    <div class="form-group" style="text-align: center; display: flex; flex-direction: column; align-items: center;">
                        <label class="form-label" style="margin-bottom: 10px;">Reward Image <span class="required-star">*</span></label>
                        <div class="reward-img-preview" id="add-preview-container">
                            <i class="fas fa-image default-icon"></i>
                            <button type="button" id="removeImgBtn" class="remove-img-btn" onclick="removeImage()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="file-upload">
                            <label for="add_photo" class="file-upload-label">
                                <i class="fas fa-upload"></i> Choose Image
                            </label>
                            <input type="file" id="add_photo" name="photo" accept="image/*" onchange="previewImage(this)">
                            <div id="file-name-display" style="margin-top: 5px; font-size: 12px; color: #666; font-style: italic;">No file chosen</div>
                        </div>
                        
                        <span class="form-guide">Format: JPG, PNG. Max size 2MB. Clear product shot recommended.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Item Name <span class="required-star">*</span></label>
                        <input type="text" name="name" id="add_name" class="form-control" placeholder="e.g. Handmade Soap Bar (Lavender)" required>
                        <span class="form-guide">Unique name for the reward item. Avoid special characters like @ # $.</span>
                        <div id="add_name_error" class="inline-error" style="display:none;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Category <span class="required-star">*</span></label>
                        <select name="category" id="add_category" class="form-select" onchange="handleCategoryChange()">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-guide">Select the most relevant category. System will auto-generate ID (e.g. HO-001).</span>
                    </div>

                    <div class="form-group" id="other_specify_container" style="display:none;">
                        <label class="form-label">Specify Category Detail <span class="required-star">*</span></label>
                        <input type="text" name="other_specify" id="other_specify" class="form-control" placeholder="e.g. Toys, Books, Digital Item">
                        <span class="form-guide">Please specify what kind of item this is.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Points Required <span class="required-star">*</span></label>
                            <input type="number" name="points" id="add_points" class="form-control" placeholder="e.g. 50" min="0" onkeypress="return isNumberKey(event)" oninput="validity.valid||(value='');">
                            <span class="form-guide">How many points a donor needs to redeem one unit.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Quantity (Max 500) <span class="required-star">*</span></label>
                            <input type="number" name="stock" id="add_stock" class="form-control" placeholder="e.g. 100" min="0" max="500" onkeypress="return isNumberKey(event)" oninput="validity.valid||(value='');">
                            <span class="form-guide">Current physical stock available. Cannot be negative.</span>
                        </div>
                    </div>

                    <div class="form-row" style="margin-top:10px;">
                        <div class="form-group">
                            <label class="form-label">Supplier Source <span class="required-star">*</span></label>
                            <select id="add_source_type" class="form-select" onchange="updateSupplierInput()">
                                <option value="">-- Select Source Type --</option>
                                <?php foreach($branchTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                                <option value="Others">Others</option>
                            </select>
                            <span class="form-guide">Where does this item come from? Branch or External.</span>
                        </div>
                        
                        <div class="form-group" id="add_branch_container" style="display:none;">
                            <label class="form-label">Select Branch <span class="required-star">*</span></label>
                            <select id="add_branch_select" class="form-select" onchange="updateSupplierInput()">
                                <option value="">-- Select Branch --</option>
                            </select>
                            <span class="form-guide">Choose the specific branch providing this item.</span>
                        </div>

                        <div class="form-group" id="add_manual_container" style="display:none;">
                            <label class="form-label">Supplier Name <span class="required-star">*</span></label>
                            <input type="text" id="add_manual_supplier" class="form-control" placeholder="e.g. Public Donation / Vendor Name" oninput="updateSupplierInput()">
                            <span class="form-guide">Enter the name of the external supplier or donor.</span>
                        </div>
                        
                        <input type="hidden" name="supplier" id="add_final_supplier">
                    </div>

                    <div class="form-group" id="add_expiry_container" style="display:none;">
                        <label class="form-label">Expiry Date <span class="required-star">*</span></label>
                        <input type="date" name="expiry_date" id="add_expiry" class="form-control">
                        <span class="form-guide">Mandatory for Food or Voucher items.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description <span class="required-star">*</span></label>
                        <textarea name="description" id="add_desc" class="form-control" rows="4" style="resize: vertical;" placeholder="e.g. Dimensions: 10x10cm. Color: Blue. Material: 100% Cotton."></textarea>
                        <span class="form-guide">Provide full details about the item to help donors decide.</span>
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn-clear" onclick="clearForm()"><i class="fas fa-eraser"></i> Clear Form</button>
                        <button type="submit" class="btn-submit"><i class="fas fa-check"></i> Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const branches = <?php echo $jsonBranches; ?>;

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

        <?php if($errorMsg): ?>
            showSystemAlert("<?php echo addslashes($errorMsg); ?>", 'error');
        <?php endif; ?>

        function goBackOrClose() {
            if (window.opener) {
                window.opener.location.reload(); 
                window.close(); 
            } else {
                window.location.href = 'reward_item_management.php';
            }
        }

        function isNumberKey(evt) {
            var charCode = (evt.which) ? evt.which : evt.keyCode;
            if (charCode > 31 && (charCode < 48 || charCode > 57)) return false;
            return true;
        }

        function clearForm() {
            document.getElementById('addForm').reset();
            removeImage(); 
            handleCategoryChange(); 
            document.getElementById('add_branch_select').setAttribute('data-current-type', '');
            updateSupplierInput();
            const inputs = document.querySelectorAll('.form-control, .form-select');
            inputs.forEach(i => i.classList.remove('input-error'));
            document.getElementById('add_name_error').style.display = 'none';
            document.getElementById('add-preview-container').classList.remove('input-error');
            
            window.scrollTo(0, 0); 
        }

        function updateSupplierInput() {
            const sourceType = document.getElementById('add_source_type').value;
            const branchSelect = document.getElementById('add_branch_select');
            const manualInput = document.getElementById('add_manual_supplier');
            const finalInput = document.getElementById('add_final_supplier');
            const branchContainer = document.getElementById('add_branch_container');
            const manualContainer = document.getElementById('add_manual_container');

            branchContainer.style.display = 'none';
            manualContainer.style.display = 'none';

            if (sourceType === 'Others') {
                manualContainer.style.display = 'block';
                finalInput.value = manualInput.value;
            } else if (sourceType) {
                branchContainer.style.display = 'block';
                if (branchSelect.getAttribute('data-current-type') !== sourceType) {
                    branchSelect.innerHTML = '<option value="">-- Select Branch --</option>';
                    if (branches[sourceType]) {
                        branches[sourceType].forEach(bName => {
                            const opt = document.createElement('option');
                            opt.value = bName; opt.innerText = bName;
                            branchSelect.appendChild(opt);
                        });
                    }
                    branchSelect.setAttribute('data-current-type', sourceType);
                }
                finalInput.value = branchSelect.value;
            } else {
                finalInput.value = '';
            }
        }

        function handleCategoryChange() {
            const category = document.getElementById('add_category').value;
            const expiryContainer = document.getElementById('add_expiry_container');
            if (['Food', 'Voucher'].includes(category)) {
                expiryContainer.style.display = 'block';
            } else {
                expiryContainer.style.display = 'none';
                document.getElementById('add_expiry').value = '';
            }
            const otherContainer = document.getElementById('other_specify_container');
            if (category === 'Others') {
                otherContainer.style.display = 'block';
            } else {
                otherContainer.style.display = 'none';
                document.getElementById('other_specify').value = '';
            }
        }
        handleCategoryChange();

        function previewImage(input) {
            const container = document.getElementById('add-preview-container');
            const nameDisplay = document.getElementById('file-name-display');
            const removeBtn = document.getElementById('removeImgBtn');
            
            container.classList.remove('input-error');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { 
                    container.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    container.appendChild(removeBtn); 
                    removeBtn.style.display = 'flex'; 
                }
                reader.readAsDataURL(input.files[0]);
                nameDisplay.innerText = input.files[0].name;
            } else {
                nameDisplay.innerText = 'No file chosen';
            }
        }

        function removeImage() {
            const input = document.getElementById('add_photo');
            input.value = ''; 
            
            const container = document.getElementById('add-preview-container');
            const removeBtn = document.getElementById('removeImgBtn');
            
            container.innerHTML = '<i class="fas fa-image default-icon"></i>';
            container.appendChild(removeBtn);
            removeBtn.style.display = 'none'; 
            
            document.getElementById('file-name-display').innerText = 'No file chosen';
        }

        function validateRewardForm() {
            const inputs = document.querySelectorAll('.form-control, .form-select');
            inputs.forEach(i => i.classList.remove('input-error'));
            
            let hasError = false;
            const getValue = (id) => document.getElementById(id).value.trim();

            if (!getValue('add_name')) { document.getElementById('add_name').classList.add('input-error'); hasError = true; }
            if (!getValue('add_points')) { document.getElementById('add_points').classList.add('input-error'); hasError = true; }
            if (!getValue('add_stock')) { document.getElementById('add_stock').classList.add('input-error'); hasError = true; }
            if (!getValue('add_desc')) { document.getElementById('add_desc').classList.add('input-error'); hasError = true; }
            
            if (getValue('add_category') === 'Others' && !getValue('other_specify')) {
                document.getElementById('other_specify').classList.add('input-error');
                hasError = true;
            }

            const elPhoto = document.getElementById('add_photo');
            const previewContainer = document.getElementById('add-preview-container');
            if (!elPhoto.files || elPhoto.files.length === 0) {
                 previewContainer.classList.add('input-error');
                 hasError = true;
            }

            const sourceType = getValue('add_source_type');
            if (!sourceType) { document.getElementById('add_source_type').classList.add('input-error'); hasError = true; }
            else if (sourceType === 'Others') {
                if (!getValue('add_manual_supplier')) { document.getElementById('add_manual_supplier').classList.add('input-error'); hasError = true; }
            } else {
                if (!getValue('add_branch_select')) { document.getElementById('add_branch_select').classList.add('input-error'); hasError = true; }
            }

            const nameRegex = /^[a-zA-Z0-9\s\-]+$/;
            const nameVal = getValue('add_name');
            if (nameVal && !nameRegex.test(nameVal)) {
                document.getElementById('add_name').classList.add('input-error');
                document.getElementById('add_name_error').innerText = "Invalid characters.";
                document.getElementById('add_name_error').style.display = 'block';
                hasError = true;
            }

            if (hasError) {
                showSystemAlert("Please fix errors.", 'error');
                return false;
            }

            return true;
        }
    </script>
</body>
</html>