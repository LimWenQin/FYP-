<?php
// branch_edit.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

if (!isset($_GET['id'])) {
    header("Location: branch_management_page.php");
    exit();
}

$branchId = intval($_GET['id']);
$sql = "SELECT * FROM branch WHERE Branch_ID = $branchId";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    header("Location: branch_management_page.php?error=" . urlencode("Branch not found."));
    exit();
}
$branch = $result->fetch_assoc();

// --- DATA LISTS ---
$malaysiaBanks = [
    "Maybank" => "Maybank", "CIMB" => "CIMB Bank", "Public Bank" => "Public Bank", "RHB" => "RHB Bank", 
    "Hong Leong" => "Hong Leong Bank", "AmBank" => "AmBank", "UOB" => "UOB Malaysia", "Bank Rakyat" => "Bank Rakyat", 
    "OCBC" => "OCBC Bank", "HSBC" => "HSBC Bank", "Bank Islam" => "Bank Islam", "Affin Bank" => "Affin Bank", 
    "Alliance Bank" => "Alliance Bank", "Standard Chartered" => "Standard Chartered", "MBSB" => "MBSB Bank", 
    "Citibank" => "Citibank", "Bank Muamalat" => "Bank Muamalat", "Agrobank" => "Agrobank", "BSN" => "Bank Simpanan Nasional"
];
$branchTypes = ['Headquarters', 'Branch', 'Old Folks Home', 'Orphanage', 'Disabled Care Center'];
$malaysiaStates = ['Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'];

// --- UPLOAD HELPER ---
function handleMultiUpload($files) {
    $paths = [];
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $dir = 'uploads/branches/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $fileCount = count($files['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] == 0 && in_array($files['type'][$i], $allowed)) {
            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $name = 'br_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $dir . $name)) {
                $paths[] = $dir . $name;
            }
        }
    }
    return $paths;
}

$errorMessage = "";
$saveSuccess = false; // Success flag

// --- HANDLE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_branch'])) {
    
    $branchName = mysqli_real_escape_string($conn, $_POST['branch_name']);
    $branchType = mysqli_real_escape_string($conn, $_POST['branch_type']);
    $capacity = intval($_POST['capacity']);
    $estDate = mysqli_real_escape_string($conn, $_POST['est_date']);
    $operationalStatus = mysqli_real_escape_string($conn, $_POST['operational_status']);
    
    $bankName = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $bankAccount = mysqli_real_escape_string($conn, $_POST['bank_account']);

    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contactRaw = $_POST['contact_number'];
    $contactNumber = (strpos($contactRaw, '+60') === 0) ? $contactRaw : "+60" . $contactRaw;
    $contactNumber = mysqli_real_escape_string($conn, $contactNumber);

    $branchHead = mysqli_real_escape_string($conn, $_POST['branch_head']);
    $headEmail = mysqli_real_escape_string($conn, $_POST['branch_head_email']);
    $headContactRaw = $_POST['branch_head_contact'];
    $headContact = (strpos($headContactRaw, '+60') === 0) ? $headContactRaw : "+60" . $headContactRaw;
    $headContact = mysqli_real_escape_string($conn, $headContact);

    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = "Malaysia";
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    // Image Handling
    $remainingExistingImagesJson = $_POST['existing_images_json'];
    $remainingExistingImages = json_decode($remainingExistingImagesJson, true) ?? [];

    $newImages = [];
    if (isset($_FILES['branch_images'])) {
        $newImages = handleMultiUpload($_FILES['branch_images']);
    }

    $finalImages = array_merge($remainingExistingImages, $newImages);
    if(count($finalImages) > 10) $finalImages = array_slice($finalImages, 0, 10);
    $finalJson = json_encode($finalImages);
    
    if (empty($branchName) || empty($bankName) || empty($bankAccount)) {
        $errorMessage = "Required fields are missing.";
    } else {
        $sql = "UPDATE branch SET 
                Branch_Name = '$branchName', Branch_Type = '$branchType', 
                Branch_Capacity = $capacity, Branch_EstablishedDate = '$estDate', Branch_OperationalStatus = '$operationalStatus',
                Branch_ContactNumber = '$contactNumber', Branch_Email = '$email',
                Branch_Head = '$branchHead', Branch_Head_Contact = '$headContact', Branch_Head_Email = '$headEmail',
                Branch_BankName = '$bankName', Branch_BankAccount = '$bankAccount',
                Branch_Address1 = '$address1', Branch_Address2 = '$address2', Branch_Address3 = '$address3',
                Branch_City = '$city', Branch_State = '$state', Branch_PostalCode = '$postalCode', Branch_Country = '$country',
                Branch_Description = '$description', Branch_Images = '$finalJson'
                WHERE Branch_ID = $branchId";
        
        if ($conn->query($sql)) {
            $saveSuccess = true;
            // Reload Data
            $result = $conn->query("SELECT * FROM branch WHERE Branch_ID = $branchId");
            $branch = $result->fetch_assoc();
        } else {
            $errorMessage = "Error updating branch: " . $conn->error;
        }
    }
}

// Pre-process Data for Display
$contactDisplay = str_replace('+60', '', $branch['Branch_ContactNumber']);
$headContactDisplay = str_replace('+60', '', $branch['Branch_Head_Contact']);
$existingImages = $branch['Branch_Images']; // JSON string
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Branch - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Shared Styles from Add Page */
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        
        /* Updated Header Style */
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #666; font-size: 14px; }
        
        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 900px; margin: 0 auto 30px; }
        .form-header { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; text-align: center; } 
        .form-header h2 { font-size: 18px; color: #333; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }

        .form-row { display: flex; gap: 20px; margin-bottom: 15px; } .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 20px; } .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; font-size: 14px; box-sizing: border-box; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .required { color: red; margin-left: 3px; font-weight: bold; }
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        .section-separator { border-top: 1px dashed #ddd; margin: 30px 0 20px; position: relative; }
        .section-separator span { position: absolute; top: -12px; left: 0; background: #fff; padding-right: 10px; font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; }
        .phone-format { display: flex; align-items: center; } .phone-prefix { padding: 12px 15px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); font-size: 14px; font-weight:bold;} .phone-input { border-radius: 0 5px 5px 0 !important; }

        /* Upload */
        .upload-box { border: 2px dashed #ccc; background: #fafafa; border-radius: 8px; padding: 25px; text-align: center; cursor: pointer; position: relative; transition: 0.3s; }
        .upload-box:hover { border-color: var(--primary); background: #fff5f5; }
        .upload-box i { font-size: 30px; color: #aaa; margin-bottom: 10px; display: block; }
        .upload-box input[type="file"] { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; margin-top: 15px; }
        .preview-item { position: relative; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid #eee; }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .remove-img-btn { position: absolute; top: 2px; right: 2px; background: #ff4d4d; color: white; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; }

        .button-group { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn-cancel { padding: 12px 25px; background: #6c757d; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration:none; }
        .btn-cancel:hover { background: #5a6268; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(90, 98, 104, 0.4); }
        .btn-save { padding: 12px 25px; background: linear-gradient(135deg, #F28585 0%, #ff9a9a 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(242, 133, 133, 0.4); }

        .input-error { border-color: var(--danger) !important; background-color: #fff5f5; }
        .inline-error { color: var(--danger); font-size: 11px; margin-top: 4px; display: block; font-weight: 500; }
        .custom-alert { position: fixed; top: 20px; right: 20px; background: white; border-left: 5px solid; padding: 15px 20px; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; z-index: 9999; transform: translateX(120%); transition: transform 0.3s ease-out; max-width: 350px; }
        .custom-alert.show { transform: translateX(0); }
        .custom-alert.error { border-color: #dc3545; } .custom-alert.error i { color: #dc3545; }
        .alert-content h4 { margin: 0 0 5px; font-size: 14px; color: #333; } .alert-content p { margin: 0; font-size: 13px; color: #666; }

        /* Modal Styles */
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
            <h2 style="margin-bottom: 10px; font-size: 22px;">Updated!</h2>
            <p style="color: #666; line-height: 1.5;">Branch details updated successfully.</p>
            <div class="modal-btn-group">
                <a href="branch_management_page.php" class="btn-clear" style="border: 1px solid #ddd; text-decoration:none;">Return to List</a>
                <button type="button" class="btn-save" onclick="document.getElementById('successModal').style.display='none'">Continue Edit</button>
            </div>
        </div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="branch_management_page.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="header-title">
                    <h1>Edit Branch</h1>
                    <p>Update branch details, status, and contact information.</p>
                </div>
            </div>

            <div class="form-container">
                <form id="editBranchForm" action="branch_edit.php?id=<?php echo $branchId; ?>" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()" novalidate>
                    <input type="hidden" name="update_branch" value="1">
                    <input type="hidden" name="existing_images_json" id="existing_images_json" value='<?php echo htmlspecialchars($existingImages ?: "[]"); ?>'>

                    <div class="form-header"><h2>Branch Information</h2></div>

                    <div class="form-group">
                        <label class="form-label">Branch Images <span class="required">*</span></label>
                        <div class="upload-box">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to add more images</p>
                            <input type="file" id="branch_images" name="branch_images[]" multiple accept="image/*" onchange="handleFileSelect(event)">
                        </div>
                        <div class="preview-grid" id="preview_container"></div>
                        <small class="form-guide">Manage existing images or add new ones (JPG/PNG).</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Branch Name <span class="required">*</span></label>
                        <input type="text" name="branch_name" id="branch_name" class="form-input" required value="<?php echo htmlspecialchars($branch['Branch_Name']); ?>" placeholder="e.g. Sunny Shelter KL">
                        <small class="form-guide">The official registered name of the branch.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <select name="branch_type" id="branch_type" class="form-select" required>
                                <?php foreach($branchTypes as $t): ?>
                                    <option value="<?php echo $t; ?>" <?php if($branch['Branch_Type'] == $t) echo 'selected'; ?>><?php echo $t; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-guide">The primary type of facility for this branch.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <select name="operational_status" id="operational_status" class="form-select" required>
                                <option value="Open" <?php if($branch['Branch_OperationalStatus'] == 'Open') echo 'selected'; ?>>Open</option>
                                <option value="Closed" <?php if($branch['Branch_OperationalStatus'] == 'Closed') echo 'selected'; ?>>Closed</option>
                            </select>
                            <small class="form-guide">Current operational status (Open/Closed).</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Bank Information</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Bank Name <span class="required">*</span></label>
                            <select name="bank_name" id="bank_name" class="form-select" onchange="setupBankValidation()" required>
                                <option value="">-- Select Bank --</option>
                                <?php foreach($malaysiaBanks as $short => $full): ?>
                                    <option value="<?php echo $short; ?>" <?php if($branch['Branch_BankName'] == $short) echo 'selected'; ?>><?php echo $full; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-guide">Select the bank receiving donations for this branch.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number <span class="required">*</span></label>
                            <input type="text" name="bank_account" id="bank_account" class="form-input" value="<?php echo htmlspecialchars($branch['Branch_BankAccount']); ?>" oninput="handleBankInput()" required placeholder="Enter account number">
                            <div id="bank_counter" style="font-size:12px; margin-top:2px; font-weight:bold; color:#dc3545;"></div>
                            <small class="form-guide">Enter the account number without dashes or spaces.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Contact & Location</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Branch Email <span class="required">*</span></label>
                            <input type="email" name="email" id="email" class="form-input" required value="<?php echo htmlspecialchars($branch['Branch_Email']); ?>" placeholder="branch@example.com">
                            <small class="form-guide">Official contact email for general inquiries.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Branch Phone <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" name="contact_number" id="contact_number" class="form-input phone-input" required maxlength="11" value="<?php echo $contactDisplay; ?>" placeholder="11-12345678">
                            </div>
                            <small class="form-guide">Official branch contact number (excluding prefix).</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1 <span class="required">*</span></label>
                        <input type="text" name="address1" id="address1" class="form-input" required value="<?php echo htmlspecialchars($branch['Branch_Address1']); ?>" placeholder="Unit No, Building Name">
                        <small class="form-guide">Primary address line (e.g. House Number, Building).</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2 <span class="required">*</span></label>
                        <input type="text" name="address2" id="address2" class="form-input" required value="<?php echo htmlspecialchars($branch['Branch_Address2']); ?>" placeholder="Street Name, Area">
                        <small class="form-guide">Secondary address line (e.g. Street Name, Taman).</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" id="address3" class="form-input" value="<?php echo htmlspecialchars($branch['Branch_Address3']); ?>" placeholder="Optional">
                        <small class="form-guide">Additional address details (Optional).</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postcode <span class="required">*</span></label>
                            <input type="text" name="postal_code" id="postal_code" class="form-input" required value="<?php echo htmlspecialchars($branch['Branch_PostalCode']); ?>" placeholder="e.g. 50450">
                            <small class="form-guide">Enter the 5-digit postal code.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <span class="required">*</span></label>
                            <input type="text" name="city" id="city" class="form-input" required value="<?php echo htmlspecialchars($branch['Branch_City']); ?>" placeholder="e.g. Kuala Lumpur">
                            <small class="form-guide">City or District.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State <span class="required">*</span></label>
                            <select name="state" id="state" class="form-select" required>
                                <?php foreach($malaysiaStates as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php if($branch['Branch_State'] == $s) echo 'selected'; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-guide">State where the branch is located.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-input" value="Malaysia" readonly style="background:#f8f9fa;">
                            <small class="form-guide">Country is fixed to Malaysia.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Person In Charge (PIC)</span></div>
                    <div class="form-group">
                        <label class="form-label">PIC Name <span class="required">*</span></label>
                        <input type="text" name="branch_head" id="branch_head" class="form-input" required value="<?php echo htmlspecialchars($branch['Branch_Head']); ?>" oninput="validateName(this)" placeholder="e.g. Mr. John Doe">
                        <small class="form-guide">Full name of the Person In Charge.</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">PIC Email <span class="required">*</span></label>
                            <input type="email" name="branch_head_email" id="branch_head_email" class="form-input" required value="<?php echo htmlspecialchars($branch['Branch_Head_Email']); ?>" placeholder="manager@example.com">
                            <small class="form-guide">Direct email address for the PIC.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">PIC Phone <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" name="branch_head_contact" id="branch_head_contact" class="form-input phone-input" required maxlength="11" value="<?php echo $headContactDisplay; ?>" placeholder="12-3456789">
                            </div>
                            <small class="form-guide">Direct mobile number for the PIC.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Details</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Capacity (Pax) <span class="required">*</span></label>
                            <input type="number" name="capacity" id="capacity" class="form-input" required value="<?php echo $branch['Branch_Capacity']; ?>" placeholder="e.g. 50" min="1">
                            <small class="form-guide">Maximum capacity (must be a positive number).</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Established Date <span class="required">*</span></label>
                            <input type="date" name="est_date" id="est_date" class="form-input" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo $branch['Branch_EstablishedDate']; ?>">
                            <small class="form-guide">The date when this branch was established.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description <span class="required">*</span></label>
                        <textarea name="description" id="description" class="form-textarea" required rows="5" placeholder="Describe mission, history..."><?php echo htmlspecialchars($branch['Branch_Description']); ?></textarea>
                        <small class="form-guide">Provide a detailed description of the branch.</small>
                    </div>

                    <div class="button-group">
                        <a href="branch_management_page.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
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

        // --- UPLOAD LOGIC ---
        let existingFiles = [];
        try { existingFiles = JSON.parse(document.getElementById('existing_images_json').value); } catch(e) { existingFiles = []; }
        let newFiles = [];

        function renderAllPreviews() {
            const container = document.getElementById('preview_container');
            container.innerHTML = '';
            
            // Existing
            existingFiles.forEach((src, index) => {
                const item = document.createElement('div');
                item.className = 'preview-item';
                item.innerHTML = `<img src="${src}"><button type="button" class="remove-img-btn" onclick="removeExisting(${index})"><i class="fas fa-times"></i></button>`;
                container.appendChild(item);
            });

            // New
            newFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.innerHTML = `<img src="${e.target.result}"><button type="button" class="remove-img-btn" onclick="removeNew(${index})"><i class="fas fa-times"></i></button>`;
                    container.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        }

        function handleFileSelect(event) {
            newFiles = newFiles.concat(Array.from(event.target.files));
            updateFileInput();
            renderAllPreviews();
        }

        function removeNew(index) {
            newFiles.splice(index, 1);
            updateFileInput();
            renderAllPreviews();
        }

        function removeExisting(index) {
            existingFiles.splice(index, 1);
            document.getElementById('existing_images_json').value = JSON.stringify(existingFiles);
            renderAllPreviews();
        }

        function updateFileInput() {
            const dt = new DataTransfer();
            newFiles.forEach(file => dt.items.add(file));
            document.getElementById('branch_images').files = dt.files;
        }

        // --- VALIDATION & BANK LOGIC (Same as Add Page) ---
        const bankRules = {
            "Maybank": { digits: 12 }, "CIMB": { digits: 10 }, "Public Bank": { digits: 10 }, "RHB": { digits: 10 },
            "Hong Leong": { digits: 11 }, "AmBank": { digits: 13 }, "UOB": { digits: 11 }, "Bank Rakyat": { digits: 12 },
            "OCBC": { digits: 10 }, "HSBC": { digits: 12 }, "Bank Islam": { digits: 14 }, "Affin Bank": { digits: 10 },
            "Alliance Bank": { digits: 10 }, "Standard Chartered": { digits: 10 }, "MBSB": { digits: 10 },
            "Citibank": { digits: 10 }, "Bank Muamalat": { digits: 14 }, "Agrobank": { digits: 15 }, "BSN": { digits: 16 }
        };

        function setupBankValidation() {
            const bankSelect = document.getElementById('bank_name');
            const accInput = document.getElementById('bank_account');
            const rule = bankRules[bankSelect.value];
            if (rule) {
                accInput.maxLength = rule.digits;
                handleBankInput();
            }
        }

        function handleBankInput() {
            const input = document.getElementById('bank_account');
            const counter = document.getElementById('bank_counter');
            const bankSelect = document.getElementById('bank_name');
            input.value = input.value.replace(/[^0-9]/g, '');
            const rule = bankRules[bankSelect.value];
            if (rule) {
                const current = input.value.length;
                counter.innerText = `Digits: ${current} / ${rule.digits}`;
                counter.style.color = (current === rule.digits) ? '#28a745' : '#dc3545';
            }
        }

        function validateEmailDetailed(email) {
            if (!email) return "Required.";
            if (!email.includes('@')) return "Missing '@'.";
            const parts = email.split('@');
            if (parts[1].length === 0 || !parts[1].includes('.')) return "Invalid domain.";
            return "";
        }

        function validatePhoneDetailed(val) {
            if (!val.includes('-')) return "Missing hyphen (-).";
            const parts = val.split('-'); 
            if (parts[0].length < 2 || parts[0].length > 3) return "Invalid prefix.";
            if (parts[1].length < 7) return "Number too short.";
            return ""; 
        }

        function showFieldError(inputId, message) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.classList.add('input-error');
            let parent = input.parentNode;
            if (parent.classList.contains('phone-format') || parent.classList.contains('upload-box')) parent = parent.parentNode;
            let errorDiv = parent.querySelector('.inline-error');
            if (!errorDiv) { errorDiv = document.createElement('div'); errorDiv.className = 'inline-error'; parent.appendChild(errorDiv); }
            errorDiv.textContent = message;
        }

        function clearFormErrors() {
            document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
            document.querySelectorAll('.inline-error').forEach(el => el.remove());
        }

        function validateName(input) { input.value = input.value.replace(/\d/g, ''); }

        function validateForm() {
            clearFormErrors();
            let hasError = false;

            ['branch_name', 'branch_type', 'operational_status', 'address1', 'address2', 'city', 'state', 'postal_code', 'capacity', 'est_date', 'description', 'bank_name', 'bank_account', 'branch_head'].forEach(id => {
                const el = document.getElementById(id);
                if (!el.value.trim()) { showFieldError(id, "Required."); hasError = true; }
            });

            const bankName = document.getElementById('bank_name').value;
            const bankAcc = document.getElementById('bank_account').value;
            if (bankName && bankRules[bankName] && bankAcc.length !== bankRules[bankName].digits) {
                showFieldError('bank_account', `Must be ${bankRules[bankName].digits} digits.`); hasError = true;
            }

            ['email', 'branch_head_email'].forEach(id => {
                const err = validateEmailDetailed(document.getElementById(id).value.trim());
                if (err) { showFieldError(id, err); hasError = true; }
            });

            ['contact_number', 'branch_head_contact'].forEach(id => {
                const err = validatePhoneDetailed(document.getElementById(id).value.trim());
                if (err) { showFieldError(id, err); hasError = true; }
            });

            if (hasError) { showSystemAlert("Please correct errors.", 'error'); return false; }
            return true;
        }

        function setupPhoneInput(id) {
            document.getElementById(id).addEventListener('input', function() {
                let val = this.value.replace(/\D/g, '');
                if (val.length > 11) val = val.substring(0, 11);
                if (val.length > 2) val = val.substring(0, 2) + '-' + val.substring(2);
                this.value = val;
            });
        }
        setupPhoneInput('contact_number');
        setupPhoneInput('branch_head_contact');

        // Postcode to State logic
        document.getElementById('postal_code').addEventListener('input', function() {
            const val = this.value.replace(/\D/g, '');
            if(val.length >= 2) {
                const p = parseInt(val.substring(0,2));
                const s = document.getElementById('state');
                if(p>=1 && p<=2) s.value="Perlis"; else if(p>=5 && p<=9) s.value="Kedah"; else if(p>=10 && p<=14) s.value="Penang";
                else if(p>=15 && p<=18) s.value="Kelantan"; else if(p>=20 && p<=24) s.value="Terengganu"; else if(p>=25 && p<=28) s.value="Pahang";
                else if(p>=30 && p<=39) s.value="Perak"; else if(p>=40 && p<=48) s.value="Selangor"; else if(p>=50 && p<=60) s.value="Kuala Lumpur";
                else if(p==62) s.value="Putrajaya"; else if(p>=63 && p<=68) s.value="Selangor"; else if(p>=70 && p<=73) s.value="Negeri Sembilan";
                else if(p>=75 && p<=78) s.value="Melaka"; else if(p>=80 && p<=86) s.value="Johor"; else if(p==87) s.value="Labuan";
                else if(p>=88 && p<=91) s.value="Sabah"; else if(p>=93 && p<=98) s.value="Sarawak";
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            setupBankValidation();
            renderAllPreviews();
        });
    </script>
</body>
</html>