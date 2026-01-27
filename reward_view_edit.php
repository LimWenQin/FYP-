<?php
// reward_view_edit.php
session_start();

// Check login status
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once 'dataconnection.php'; 

// Check ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: reward_item_management.php");
    exit();
}

$rewardId = intval($_GET['id']);
$mode = isset($_GET['mode']) && $_GET['mode'] == 'edit' ? 'edit' : 'view';
$currentAdminId = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : $_SESSION['staff_id'];

// Fetch Data
$sql = "SELECT * FROM reward_item WHERE Reward_ID = $rewardId";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    header("Location: reward_item_management.php?error=Item Not Found");
    exit();
}
$item = $result->fetch_assoc();

// Config
$categories = ['Household', 'Apparel', 'Handicraft', 'Electronics', 'Food', 'Voucher', 'Others'];
$branchTypes = ['Old Folks Home', 'Orphanage', 'Disabled Care Center'];
define('MAX_STOCK_LIMIT', 500);
define('LOW_STOCK_THRESHOLD', 15);

// Fetch Branches
$branchesData = [];
$brSql = "SELECT Branch_Name, Branch_Type FROM branch WHERE Is_Deleted = 0 ORDER BY Branch_Name ASC";
$brResult = $conn->query($brSql);
if ($brResult) {
    while ($brRow = $brResult->fetch_assoc()) {
        $branchesData[$brRow['Branch_Type']][] = $brRow['Branch_Name'];
    }
}
$jsonBranches = json_encode($branchesData);

// --- FUNCTIONS ---
function logRewardAction($conn, $rewardId, $adminId, $type, $details) {
    $details = $conn->real_escape_string($details);
    $conn->query("INSERT INTO reward_logs (Reward_ID, Admin_ID, Action_Type, Action_Details) VALUES ('$rewardId', '$adminId', '$type', '$details')");
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

// --- HANDLE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_reward'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $category = $conn->real_escape_string($_POST['category']); 
    $supplier = $conn->real_escape_string($_POST['supplier']); 
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL; 
    $desc = $conn->real_escape_string($_POST['description']);
    
    // CHANGED: Category Note handling
    $categoryNote = NULL;
    if ($category === 'Others' && !empty($_POST['other_specify'])) {
        $categoryNote = $conn->real_escape_string($_POST['other_specify']);
    }

    $points = (int)$_POST['points'];
    $stock = (int)$_POST['stock'];
    $status = $_POST['status']; 

    // Prevent negatives
    if ($points < 0) $points = 0;
    if ($stock < 0) $stock = 0;

    if ($stock > MAX_STOCK_LIMIT) {
        $errorMsg = "Stock ($stock) exceeds maximum limit of " . MAX_STOCK_LIMIT . ".";
    } else {
        // Auto status update logic if not set to Inactive manually
        if ($status != 'Inactive') {
            $status = ($stock < LOW_STOCK_THRESHOLD) ? 'Low Stock' : 'Active';
        }

        $imageSql = "";
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $uploaded = handleImageUpload($_FILES['photo']);
            if ($uploaded) {
                $imageSql = ", Reward_PhotoPath = '$uploaded'";
            }
        }

        $expiryVal = $expiryDate ? "'$expiryDate'" : "NULL";
        $noteVal = $categoryNote ? "'$categoryNote'" : "NULL";

        $sql = "UPDATE reward_item SET 
                Reward_ItemName='$name', 
                Reward_Category='$category',
                Reward_Category_Note=$noteVal, 
                Reward_Description='$desc', 
                Reward_RequiredPoint='$points', 
                Reward_Supplier='$supplier', 
                Reward_Stock='$stock', 
                Reward_ExpiryDate=$expiryVal,
                Reward_Status='$status' 
                $imageSql 
                WHERE Reward_ID=$rewardId";
        
        if ($conn->query($sql)) {
            logRewardAction($conn, $rewardId, $currentAdminId, 'Update', "Updated details for $name. Stock set to $stock");
            $saveSuccess = true; 
            
            // Refetch data to show updated info immediately
            $item = $conn->query("SELECT * FROM reward_item WHERE Reward_ID = $rewardId")->fetch_assoc();
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
    <title>Reward Details - DonationMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    
    <style>
        /* Local Page Styles */
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        
        /* Back Button Style */
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; cursor: pointer; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        
        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 900px; margin: 0 auto 30px; }
        .form-header { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; display:flex; justify-content:space-between; align-items:center; }
        .form-header h2 { font-size: 20px; color: #333; font-weight: 600; }
        
        .form-group { margin-bottom: 20px; } 
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; font-size: 14px; }
        .form-control, .form-select { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; transition: 0.3s; font-size: 14px; box-sizing: border-box; }
        .form-control:read-only, .form-select:disabled { background-color: #f8f9fa; color: #666; cursor: not-allowed; }
        .form-control:focus, .form-select:focus { border-color: #F28585; }
        .form-row { display: flex; gap: 20px; } .form-row .form-group { flex: 1; margin-bottom: 0; }
        
        /* Guide Text */
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        
        /* Switch */
        .switch-container { display: flex; align-items: center; gap: 10px; }
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #F28585; }
        input:checked + .slider:before { transform: translateX(24px); }
        .toggle-label { font-size: 14px; font-weight: 600; color: #666; }

        /* FIXED IMAGE UI */
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
            cursor: pointer; /* Clickable for view */
        }
        .reward-img-preview img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }
        .default-icon { font-size: 48px; color: #ddd; }

        /* Remove Button */
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

        .btn-save { padding: 12px 25px; background: #F28585; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { background: #d66565; }
        .btn-cancel { padding: 12px 25px; background: #6c757d; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-right: 10px; text-decoration: none; }
        .button-group { display: flex; justify-content: flex-end; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        
        .edit-controls { display: none; }
        .required-star { color: red; display: inline-block; }
        .input-error { border-color: #dc3545 !important; background-color: #fff5f5; }

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

        /* --- IMAGE VIEWER MODAL (View Mode) --- */
        .image-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 10001; justify-content: center; align-items: center; flex-direction: column; }
        .image-modal-content { max-width: 90%; max-height: 80vh; object-fit: contain; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.5); }
        .image-modal-close { position: absolute; top: 30px; right: 40px; color: white; font-size: 40px; cursor: pointer; transition: 0.3s; }
        .image-modal-close:hover { color: #F28585; transform: scale(1.1); }
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
            <h2 style="margin-bottom: 10px; font-size: 22px;">Update Successful!</h2>
            <p style="color: #666; line-height: 1.5;">Reward Item details have been successfully updated.</p>
            <div class="modal-btn-group">
                <button type="button" class="btn-cancel" style="border: 1px solid #ddd;" onclick="goBackOrClose()">Return to List</button>
                <a href="add_reward_item.php" class="btn-save" style="text-decoration: none;">Add Another Item</a>
            </div>
        </div>
    </div>

    <div id="imageViewerModal" class="image-modal-overlay" onclick="closeImageModal(event)">
        <span class="image-modal-close" onclick="closeImageModal(event, true)">&times;</span>
        <img id="fullImageView" class="image-modal-content" src="" style="display:none;">
        <div id="placeholderView" style="color:white; font-size:20px; display:none;">No Image Available</div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="javascript:void(0);" class="back-btn" onclick="goBackOrClose()">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <div class="header-title"><h1>Reward Item Details</h1></div>
            </div>

            <div class="form-container">
                <?php if($errorMsg): ?>
                    <div style="background:#ffe6e6; color:#dc3545; padding:10px; border-radius:5px; margin-bottom:20px; text-align:center;">
                        <?php echo $errorMsg; ?>
                    </div>
                <?php endif; ?>

                <form id="editForm" method="POST" enctype="multipart/form-data" onsubmit="return validateRewardForm()">
                    <input type="hidden" name="update_reward" value="1">
                    
                    <div class="form-header">
                        <h2>Item Information (ID: <?php echo $item['Reward_Code']; ?>)</h2>
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" id="modeToggle" onchange="toggleEditMode(this.checked)">
                                <span class="slider round"></span>
                            </label>
                            <span class="toggle-label" id="toggleLabel">View Mode</span>
                        </div>
                    </div>

                    <div class="form-group" style="text-align: center; display: flex; flex-direction: column; align-items: center;">
                        <label class="form-label" style="margin-bottom:10px;">Reward Image</label>
                        <div class="reward-img-preview" id="edit-preview-container" onclick="handleImageClick()">
                            <?php if (!empty($item['Reward_PhotoPath']) && file_exists('uploads/rewards/'.$item['Reward_PhotoPath'])): ?>
                                <img src="uploads/rewards/<?php echo $item['Reward_PhotoPath']; ?>" alt="Reward" id="currentImage">
                            <?php else: ?>
                                <i class="fas fa-image default-icon"></i>
                            <?php endif; ?>
                            
                            <button type="button" id="removeImgBtn" class="remove-img-btn" onclick="removeImage(event)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="file-upload edit-controls">
                            <label for="edit_photo" class="file-upload-label"><i class="fas fa-upload"></i> Change Image</label>
                            <input type="file" id="edit_photo" name="photo" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <span class="form-guide edit-controls">Format: JPG, PNG. Max size 2MB.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Item Name <span class="required-star edit-controls">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($item['Reward_ItemName']); ?>" readonly placeholder="e.g. Handmade Soap">
                        <span class="form-guide edit-controls">Unique name for the reward item.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category <span class="required-star edit-controls">*</span></label>
                            <select name="category" id="category" class="form-select" onchange="handleCategoryChange()" disabled>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo ($item['Reward_Category'] == $cat) ? 'selected' : ''; ?>>
                                        <?php echo $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-guide edit-controls">Category determines the Item ID prefix.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required-star edit-controls">*</span></label>
                            <select name="status" id="status" class="form-select" disabled>
                                <option value="Active" <?php echo ($item['Reward_Status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Low Stock" <?php echo ($item['Reward_Status'] == 'Low Stock') ? 'selected' : ''; ?>>Low Stock</option>
                                <option value="Inactive" <?php echo ($item['Reward_Status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <span class="form-guide edit-controls">Controls visibility to donors.</span>
                        </div>
                    </div>

                    <div class="form-group" id="other_specify_container" style="display:none;">
                        <label class="form-label">Specify Category Detail <span class="required-star edit-controls">*</span></label>
                        <input type="text" name="other_specify" id="other_specify" class="form-control" placeholder="e.g. Toys, Books" readonly value="<?php echo htmlspecialchars($item['Reward_Category_Note']); ?>">
                        <span class="form-guide edit-controls">Please specify what kind of item this is.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Points Required <span class="required-star edit-controls">*</span></label>
                            <input type="number" name="points" id="points" class="form-control" value="<?php echo $item['Reward_RequiredPoint']; ?>" min="0" readonly onkeypress="return isNumberKey(event)" oninput="validity.valid||(value='');" placeholder="0">
                            <span class="form-guide edit-controls">Points needed to redeem one unit.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Quantity <span class="required-star edit-controls">*</span></label>
                            <input type="number" name="stock" id="stock" class="form-control" value="<?php echo $item['Reward_Stock']; ?>" min="0" max="500" readonly onkeypress="return isNumberKey(event)" oninput="validity.valid||(value='');" placeholder="0">
                            <span class="form-guide edit-controls">Physical stock count. Max 500.</span>
                        </div>
                    </div>

                    <div class="form-row" style="margin-top:15px;">
                        <div class="form-group">
                            <label class="form-label">Supplier Source <span class="required-star edit-controls">*</span></label>
                            <select id="source_type" class="form-select" onchange="updateSupplierInput()" disabled>
                                <option value="">-- Select Source Type --</option>
                                <?php foreach($branchTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                                <option value="Others">Others</option>
                            </select>
                            <span class="form-guide edit-controls">Where does this item come from?</span>
                        </div>
                        
                        <div class="form-group" id="branch_container" style="display:none;">
                            <label class="form-label">Select Branch <span class="required-star edit-controls">*</span></label>
                            <select id="branch_select" class="form-select" onchange="updateSupplierInput()" disabled>
                                <option value="">-- Select Branch --</option>
                            </select>
                            <span class="form-guide edit-controls">Choose the specific branch.</span>
                        </div>

                        <div class="form-group" id="manual_container" style="display:none;">
                            <label class="form-label">Supplier Name <span class="required-star edit-controls">*</span></label>
                            <input type="text" id="manual_supplier" class="form-control" oninput="updateSupplierInput()" readonly placeholder="e.g. Public Donation">
                            <span class="form-guide edit-controls">Enter external supplier name.</span>
                        </div>
                        
                        <input type="hidden" name="supplier" id="final_supplier" value="<?php echo htmlspecialchars($item['Reward_Supplier']); ?>">
                    </div>

                    <div class="form-group" id="expiry_container" style="display:none;">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiry" class="form-control" value="<?php echo $item['Reward_ExpiryDate']; ?>" readonly>
                        <span class="form-guide edit-controls">Required for Food/Vouchers.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description <span class="required-star edit-controls">*</span></label>
                        <textarea name="description" id="description" class="form-control" rows="4" style="resize: vertical;" readonly placeholder="Item details..."><?php echo htmlspecialchars($item['Reward_Description']); ?></textarea>
                        <span class="form-guide edit-controls">Detailed description for donors.</span>
                    </div>

                    <div class="button-group edit-controls">
                        <a href="reward_view_edit.php?id=<?php echo $rewardId; ?>&mode=view" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const branches = <?php echo $jsonBranches; ?>;
        const currentSupplier = "<?php echo addslashes($item['Reward_Supplier']); ?>";
        let isEditMode = false;

        document.addEventListener("DOMContentLoaded", function() {
            initSupplierField();
            handleCategoryChange(); 

            <?php if($mode === 'edit'): ?>
                const toggle = document.getElementById('modeToggle');
                if(toggle) {
                    toggle.checked = true;
                    toggleEditMode(true);
                }
            <?php endif; ?>
        });

        function goBackOrClose() {
            if (window.opener) {
                window.opener.location.reload(); 
                window.close(); 
            } else {
                window.location.href = 'reward_item_management.php';
            }
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

        function handleImageClick() {
            openImageViewer();
        }

        function openImageViewer() {
            const container = document.getElementById('edit-preview-container');
            const img = container.querySelector('img'); 
            const modal = document.getElementById('imageViewerModal');
            const fullImage = document.getElementById('fullImageView');
            const placeholder = document.getElementById('placeholderView');

            if (img) {
                fullImage.src = img.src;
                fullImage.style.display = 'block';
                placeholder.style.display = 'none';
                modal.style.display = 'flex';
            } else {
            }
        }

        function closeImageModal(e, force = false) {
            if (force || e.target.id === 'imageViewerModal') {
                document.getElementById('imageViewerModal').style.display = 'none';
            }
        }

        function isNumberKey(evt) {
            var charCode = (evt.which) ? evt.which : evt.keyCode;
            if (charCode > 31 && (charCode < 48 || charCode > 57)) return false;
            return true;
        }

        function initSupplierField() {
            let foundType = '';
            for (const [type, branchList] of Object.entries(branches)) {
                if (branchList.includes(currentSupplier)) {
                    foundType = type; break;
                }
            }
            
            const sourceType = document.getElementById('source_type');
            const manualIn = document.getElementById('manual_supplier');
            const branchSel = document.getElementById('branch_select');

            if (foundType) {
                sourceType.value = foundType;
                updateSupplierInput(); 
                branchSel.value = currentSupplier;
            } else if (currentSupplier) {
                sourceType.value = 'Others';
                manualIn.value = currentSupplier;
                updateSupplierInput();
            } else {
                sourceType.value = '';
                updateSupplierInput();
            }
        }

        function updateSupplierInput() {
            const sourceType = document.getElementById('source_type').value;
            const branchSelect = document.getElementById('branch_select');
            const manualInput = document.getElementById('manual_supplier');
            const finalInput = document.getElementById('final_supplier');
            
            const branchContainer = document.getElementById('branch_container');
            const manualContainer = document.getElementById('manual_container');

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
            const category = document.getElementById('category').value;
            const expiryContainer = document.getElementById('expiry_container');
            if (['Food', 'Voucher'].includes(category)) {
                expiryContainer.style.display = 'block';
            } else {
                expiryContainer.style.display = 'none';
            }
            const otherContainer = document.getElementById('other_specify_container');
            if (category === 'Others') {
                otherContainer.style.display = 'block';
            } else {
                otherContainer.style.display = 'none';
            }
        }

        function toggleEditMode(isEdit) {
            isEditMode = isEdit;
            document.getElementById('toggleLabel').textContent = isEdit ? 'Edit Mode' : 'View Mode';
            
            document.querySelectorAll('.edit-controls').forEach(el => {
                if(el.tagName === 'SPAN' || el.tagName === 'LABEL') {
                     if(el.classList.contains('form-guide')) {
                         el.style.display = isEdit ? 'block' : 'none';
                     } else {
                         el.style.display = isEdit ? 'inline-block' : 'none';
                     }
                } else {
                     el.style.display = isEdit ? 'flex' : 'none';
                }
            });
            
            document.querySelectorAll('.form-control').forEach(el => el.readOnly = !isEdit);
            document.querySelectorAll('.form-select').forEach(el => el.disabled = !isEdit);
            
            document.getElementById('edit-preview-container').style.cursor = 'pointer'; 
            
            const removeBtn = document.getElementById('removeImgBtn');
            if (!isEdit) {
                removeBtn.style.display = 'none';
            }
        }

        function previewImage(input) {
            const container = document.getElementById('edit-preview-container');
            const removeBtn = document.getElementById('removeImgBtn');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { 
                    container.innerHTML = `<img src="${e.target.result}" alt="Preview">`; 
                    container.appendChild(removeBtn);
                    if(isEditMode) removeBtn.style.display = 'flex';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage(e) {
            if(e) e.stopPropagation(); 
            
            const input = document.getElementById('edit_photo');
            input.value = ''; 
            
            const container = document.getElementById('edit-preview-container');
            const removeBtn = document.getElementById('removeImgBtn');
            
            <?php if (!empty($item['Reward_PhotoPath']) && file_exists('uploads/rewards/'.$item['Reward_PhotoPath'])): ?>
                container.innerHTML = `<img src="uploads/rewards/<?php echo $item['Reward_PhotoPath']; ?>" alt="Reward">`;
            <?php else: ?>
                container.innerHTML = `<i class="fas fa-image default-icon"></i>`;
            <?php endif; ?>
            
            container.appendChild(removeBtn);
            removeBtn.style.display = 'none';
        }

        function validateRewardForm() {
            const inputs = document.querySelectorAll('.form-control, .form-select');
            inputs.forEach(i => i.classList.remove('input-error'));
            
            let hasError = false;
            const getValue = (id) => document.getElementById(id).value.trim();

            if (!getValue('name')) { document.getElementById('name').classList.add('input-error'); hasError = true; }
            if (!getValue('points')) { document.getElementById('points').classList.add('input-error'); hasError = true; }
            if (!getValue('stock')) { document.getElementById('stock').classList.add('input-error'); hasError = true; }
            if (!getValue('description')) { document.getElementById('description').classList.add('input-error'); hasError = true; }
            
            if (document.getElementById('category').value === 'Others' && !getValue('other_specify')) {
                document.getElementById('other_specify').classList.add('input-error');
                hasError = true;
            }

            const nameRegex = /^[a-zA-Z0-9\s\-]+$/;
            if (getValue('name') && !nameRegex.test(getValue('name'))) {
                document.getElementById('name').classList.add('input-error');
                showSystemAlert("Name contains invalid characters.", 'error');
                return false;
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