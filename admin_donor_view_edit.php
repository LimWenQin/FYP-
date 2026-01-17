<?php
// admin_donor_view_edit.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin_donor_page.php");
    exit();
}

$donorId = intval($_GET['id']);
$mode = isset($_GET['mode']) && $_GET['mode'] == 'edit' ? 'edit' : 'view';

// Fetch Donor Data
$sql = "SELECT * FROM donor WHERE Donor_ID = $donorId";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    header("Location: admin_donor_page.php?error=" . urlencode("Donor not found."));
    exit();
}
$donor = $result->fetch_assoc();

// --- DATA FOR DROPDOWNS ---
$malaysiaStates = ['Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'];

// --- HELPERS ---
function handleProfileUpload($file) {
    if (isset($file) && $file['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowedTypes)) {
            $uploadDir = 'uploads/donors/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'donor_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) return $uploadPath;
        }
    }
    return null;
}

function validateIcDobMatch($ic, $dob) {
    $cleanIc = preg_replace('/[^0-9]/', '', $ic);
    if (strlen($cleanIc) !== 12) return false;
    $pbCode = (int)substr($cleanIc, 6, 2);
    $validPBCodes = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,82];
    if (!in_array($pbCode, $validPBCodes)) { return false; }
    $y = substr($cleanIc, 0, 2); $m = substr($cleanIc, 2, 2); $d = substr($cleanIc, 4, 2);
    $currentYearShort = date('y');
    $century = ($y > $currentYearShort) ? '19' : '20';
    $icDate = $century . $y . '-' . $m . '-' . $d;
    if (!checkdate((int)$m, (int)$d, (int)($century . $y))) return false;
    return $icDate === $dob;
}

$saveSuccess = false;
$errorMessage = null;

// --- HANDLE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_donor'])) {
    $donorName = mysqli_real_escape_string($conn, trim($_POST['donor_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $contactRaw = trim($_POST['contact']); $contact = "+60" . $contactRaw;
    $icNumber = mysqli_real_escape_string($conn, trim($_POST['ic_number']));
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $address1 = mysqli_real_escape_string($conn, trim($_POST['address1']));
    $address2 = mysqli_real_escape_string($conn, trim($_POST['address2']));
    $address3 = mysqli_real_escape_string($conn, trim($_POST['address3']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, trim($_POST['postal_code']));
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $imageUpdateSQL = "";
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) {
             $imageUpdateSQL = ", Donor_ProfilePicture = '$uploadedPath'";
        }
    }

    // UPDATED: Relaxed validation for IC/DOB
    if (!empty($icNumber) && !empty($dob) && !validateIcDobMatch($icNumber, $dob)) {
        $errorMessage = "Invalid IC Number (Format, Length, or State Code mismatch with DOB).";
    } elseif (!empty($icNumber) && empty($dob)) {
         // Validates IC length if entered, even if DOB is empty
         $cleanIc = preg_replace('/[^0-9]/', '', $icNumber);
         if(strlen($cleanIc) !== 12) $errorMessage = "Invalid IC Number length.";
    } else {
        // Check email uniqueness if changed
        if ($email != $donor['Donor_Email']) {
             $check = $conn->query("SELECT Donor_ID FROM donor WHERE Donor_Email = '$email' AND Donor_ID != $donorId");
             if ($check->num_rows > 0) $errorMessage = "Email already exists.";
        }
        
        if (!$errorMessage) {
            $sql = "UPDATE donor SET Donor_Name = '$donorName', Donor_ContactNumber = '$contact', Donor_ICNumber = '$icNumber', Donor_Email = '$email', Donor_DOB = '$dob', Donor_Address1 = '$address1', Donor_Address2 = '$address2', Donor_Address3 = '$address3', Donor_City = '$city', Donor_State = '$state', Donor_PostalCode = '$postalCode', Donor_Country = '$country', Donor_Description = '$description' $imageUpdateSQL WHERE Donor_ID = $donorId";
            
            if ($conn->query($sql)) {
                $result = $conn->query("SELECT * FROM donor WHERE Donor_ID = $donorId");
                $donor = $result->fetch_assoc();
                $saveSuccess = true;
            } else {
                $errorMessage = "Error updating: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Details - Donation Management System</title>
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
        .form-header { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; display:flex; justify-content:space-between; align-items:center; }
        .form-header h2 { font-size: 20px; color: #333; font-weight: 600; }
        
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; } 
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 20px; } 
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; font-size: 14px; box-sizing: border-box; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-input:read-only, .form-textarea:read-only, .form-select:disabled { background-color: #f8f9fa; color: #666; border-color: #eee; cursor: not-allowed; }
        
        .required { color: red; margin-left: 3px; font-weight: bold; }
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        .readonly-field { background-color: #f8f9fa; color: #555; pointer-events: none; }

        .phone-format { display: flex; align-items: center; } 
        .phone-prefix { padding: 12px 15px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); font-size: 14px; } 
        .phone-input { border-radius: 0 5px 5px 0 !important; }
        
        .file-upload { text-align: left; } 
        .profile-picture-preview { width: 120px; height: 120px; border-radius: 50%; border: 4px solid #f8f9fa; margin-bottom: 15px; background: #eee; display: flex; align-items: center; justify-content: center; overflow: visible; position: relative; transition: transform 0.2s, border-color 0.2s; } 
        .profile-picture-preview:hover { transform: scale(1.05); border-color: #F28585; }
        .profile-picture-preview img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; } 
        .default-avatar-icon { font-size: 48px; color: #ccc; } 
        .file-upload-label { display: inline-block; padding: 8px 15px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 5px; cursor: pointer; font-size: 13px; transition: all 0.3s; } 
        .file-upload input[type="file"] { display: none; } 

        /* UPDATED: Remove button style to be clearly visible */
        .remove-img-btn { position: absolute; top: 0; right: 0; background: #dc3545; color: white; border: 2px solid white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); transition: transform 0.2s; z-index: 10; }
        .remove-img-btn:hover { transform: scale(1.1); background: #bd2130; }
        
        /* Toggle Switch */
        .switch-container { display: flex; align-items: center; gap: 10px; }
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; margin: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: #F28585; }
        input:checked + .slider:before { transform: translateX(24px); }
        .toggle-label { font-size: 14px; font-weight: 600; color: #666; }

        .button-group { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn-cancel { padding: 12px 25px; background: #6c757d; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-cancel:hover { background: #5a6268; transform: translateY(-1px); }
        .btn-save { padding: 12px 25px; background: linear-gradient(135deg, #F28585 0%, #ff9a9a 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(242, 133, 133, 0.3); display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(242, 133, 133, 0.4); }

        .edit-controls { display: none; }
        .input-error { border-color: var(--danger) !important; background-color: #fff5f5; }
        .inline-error { color: var(--danger); font-size: 11px; margin-top: 4px; display: block; font-weight: 500; animation: fadeIn 0.3s; }

        .custom-alert { position: fixed; top: 20px; right: 20px; background: white; border-left: 5px solid; padding: 15px 20px; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; z-index: 9999; transform: translateX(120%); transition: transform 0.3s ease-out; max-width: 350px; }
        .custom-alert.show { transform: translateX(0); }
        .custom-alert.error { border-color: #dc3545; } .custom-alert.error i { color: #dc3545; }
        .custom-alert.success { border-color: #28a745; } .custom-alert.success i { color: #28a745; }
        .alert-content h4 { margin: 0 0 5px; font-size: 14px; color: #333; } .alert-content p { margin: 0; font-size: 13px; color: #666; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center; }
        .success-modal { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .success-icon { width: 70px; height: 70px; background: #e6f4ea; border-radius: 50%; color: #28a745; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
        .modal-btn-group { display: flex; gap: 10px; justify-content: center; margin-top: 25px; }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        /* Image Viewer Modal */
        .image-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 10000; justify-content: center; align-items: center; flex-direction: column; }
        .image-modal-content { max-width: 90%; max-height: 80vh; object-fit: contain; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.5); }
        .image-modal-placeholder { width: 300px; height: 300px; background: #eee; color: #ccc; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 120px; font-weight: bold; border: 5px solid white; }
        .image-modal-close { position: absolute; top: 30px; right: 40px; color: white; font-size: 40px; cursor: pointer; transition: 0.3s; }
        .image-modal-close:hover { color: #F28585; transform: scale(1.1); }

        @media (max-width: 768px) { .page-header-compact { flex-direction: column; align-items: flex-start; gap: 10px; } .header-title { padding-right: 0; text-align: left; } .form-row { flex-direction: column; gap: 0; } .button-group { flex-direction: column; } .btn { width: 100%; text-align: center; justify-content: center; } }
    </style>
</head>
<body>
    
    <div id="customAlert" class="custom-alert"><i class="fas" id="alertIcon"></i><div class="alert-content"><h4 id="alertTitle">Title</h4><p id="alertMessage">Message</p></div></div>

    <div id="successModal" class="modal-overlay" style="display: <?php echo $saveSuccess ? 'flex' : 'none'; ?>;">
        <div class="success-modal">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <h2 style="margin-bottom: 10px; font-size: 22px;">Update Successful!</h2>
            <p style="color: #666; line-height: 1.5;">Donor details have been successfully updated.</p>
            <div class="modal-btn-group">
                <a href="admin_donor_page.php" class="btn-cancel" style="border: 1px solid #ddd; background: white; color: #555;">Return to List</a>
                <button type="button" class="btn-save" onclick="document.getElementById('successModal').style.display='none';">Stay Here</button>
            </div>
        </div>
    </div>

    <div id="imageViewerModal" class="image-modal-overlay" onclick="closeImageModal(event)">
        <span class="image-modal-close" onclick="closeImageModal(event, true)">&times;</span>
        <img id="fullImageView" class="image-modal-content" src="" style="display:none;">
        <div id="placeholderView" class="image-modal-placeholder" style="display:none;">
            <i class="fas fa-user"></i>
        </div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="admin_donor_page.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Donor List</a>
                <div class="header-title">
                    <h1>Donor Details</h1>
                    <p>View or modify donor information.</p>
                </div>
            </div>

            <div class="form-container">
                <form id="editDonorForm" action="admin_donor_view_edit.php?id=<?php echo $donorId; ?>" method="POST" enctype="multipart/form-data" onsubmit="return validateForm('edit')" novalidate>
                    <input type="hidden" name="update_donor" value="1">
                    
                    <div class="form-header">
                        <h2>Personal Information</h2>
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" id="modeToggle" onchange="toggleEditMode(this.checked)">
                                <span class="slider round"></span>
                            </label>
                            <span class="toggle-label" id="toggleLabel">View Mode</span>
                        </div>
                    </div>

                    <div class="form-group" style="text-align: center;">
                        <label class="form-label" style="text-align:left;">Profile Picture</label>
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <div class="profile-picture-preview" id="edit-preview-container" onclick="openImageViewer()" style="cursor: pointer;">
                                <?php if (!empty($donor['Donor_ProfilePicture']) && file_exists($donor['Donor_ProfilePicture'])): ?>
                                    <img src="<?php echo $donor['Donor_ProfilePicture']; ?>" alt="Profile">
                                <?php else: ?>
                                    <div class="default-avatar-icon"><i class="fas fa-user"></i></div>
                                <?php endif; ?>
                                <button type="button" id="removeImgBtn" class="remove-img-btn" style="display: none;" onclick="removeImage('edit_profile_picture', 'edit-preview-container', event)"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="file-upload edit-controls">
                                <label for="edit_profile_picture" class="file-upload-label" id="editFileUploadLabel"><i class="fas fa-upload"></i> Change Profile Picture</label>
                                <input type="file" id="edit_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'edit-preview-container')">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required edit-controls">*</span></label>
                        <input type="text" id="donor_name" name="donor_name" class="form-input" value="<?php echo htmlspecialchars($donor['Donor_Name']); ?>" readonly oninput="validateName(this)" placeholder="e.g. John Doe">
                        <span class="form-guide edit-controls">Enter full name as per IC. English letters only.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required edit-controls">*</span></label>
                            <input type="text" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($donor['Donor_Email']); ?>" readonly placeholder="e.g. user@example.com">
                            <span class="form-guide edit-controls">Valid email address (e.g. name@domain.com).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required edit-controls">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="edit_contact" name="contact" class="form-input phone-input" value="<?php echo str_replace('+60', '', $donor['Donor_ContactNumber']); ?>" readonly maxlength="11" placeholder="12-3456789">
                            </div>
                            <span class="form-guide edit-controls">Format: 12-3456789 (Prefix 11-19, total 9-11 digits).</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number</label>
                            <input type="text" id="ic_number" name="ic_number" class="form-input" value="<?php echo htmlspecialchars($donor['Donor_ICNumber']); ?>" maxlength="14" readonly placeholder="e.g. 990101-07-5678">
                            <span class="form-guide edit-controls">Format: YYMMDD-PB-#### (e.g. 990101-07-1234).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="form-input" value="<?php echo $donor['Donor_DOB']; ?>" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" name="address1" class="form-input" value="<?php echo htmlspecialchars($donor['Donor_Address1']); ?>" readonly placeholder="e.g. No. 123, Jalan Example">
                        <span class="form-guide edit-controls">House unit no., floor, building, street name.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" name="address2" class="form-input" value="<?php echo htmlspecialchars($donor['Donor_Address2']); ?>" readonly placeholder="e.g. Taman Sri">
                        <span class="form-guide edit-controls">Residential area, village, or section.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" class="form-input" value="<?php echo htmlspecialchars($donor['Donor_Address3']); ?>" readonly placeholder="Address Line 3 (Optional)">
                        <span class="form-guide edit-controls">Address Line 3 (Optional)</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-input" value="<?php echo htmlspecialchars($donor['Donor_City']); ?>" readonly placeholder="e.g. Kuala Lumpur">
                        </div>
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <select id="state" name="state" class="form-select" disabled>
                                <option value="">Select State</option>
                                <?php foreach($malaysiaStates as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php if($donor['Donor_State'] == $s) echo 'selected'; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input" value="<?php echo htmlspecialchars($donor['Donor_PostalCode']); ?>" readonly oninput="detectStateFromPostcode('postal_code', 'state')" placeholder="e.g. 50000">
                            <span class="form-guide edit-controls">5-digit postcode.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-input readonly-field" value="Malaysia" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Remarks</label>
                        <textarea name="description" class="form-textarea" rows="3" readonly placeholder="e.g. Donor prefers anonymous receipt..."><?php echo htmlspecialchars($donor['Donor_Description']); ?></textarea>
                        <span class="form-guide edit-controls">Optional notes.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Registered At</label>
                            <input type="text" class="form-input readonly-field" value="<?php echo $donor['Donor_RegisteredAt']; ?>" readonly>
                        </div>
                        <div class="form-group">
                             <label class="form-label">Last Login</label>
                            <input type="text" class="form-input readonly-field" value="<?php echo $donor['Donor_LastLogin'] ? $donor['Donor_LastLogin'] : 'Never'; ?>" readonly>
                        </div>
                    </div>

                    <div class="button-group edit-controls">
                        <a href="admin_donor_view_edit.php?id=<?php echo $donorId; ?>" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if($mode === 'edit'): ?>
                const toggle = document.getElementById('modeToggle');
                if(toggle) { toggle.checked = true; toggleEditMode(true); }
            <?php endif; ?>
        });

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

        function toggleEditMode(isEdit) {
            const label = document.getElementById('toggleLabel');
            label.textContent = isEdit ? 'Edit Mode' : 'View Mode';
            document.querySelectorAll('.edit-controls').forEach(el => {
                if(el.tagName === 'SPAN' || el.tagName === 'LABEL') {
                     el.style.display = isEdit ? 'inline-block' : 'none';
                     if(el.classList.contains('form-guide')) el.style.display = isEdit ? 'block' : 'none';
                } else { el.style.display = isEdit ? 'flex' : 'none'; }
            });
            document.querySelectorAll('.form-input, .form-textarea').forEach(el => {
                if (el.name !== 'country' && !el.classList.contains('readonly-field')) el.readOnly = !isEdit; 
            });
            document.querySelectorAll('.form-select').forEach(el => el.disabled = !isEdit);

            // Handle image removal button visibility in Edit mode
            const removeBtn = document.getElementById('removeImgBtn');
            const fileInput = document.getElementById('edit_profile_picture');
            
            // Only show button if we are in Edit mode AND a new file has been selected
            if(isEdit && fileInput.value !== "") {
                removeBtn.style.display = 'flex';
            } else {
                removeBtn.style.display = 'none';
            }

            if (!isEdit) {
                // Reset form to DB state if cancelling (via toggling view mode)
                // Note: Real "Cancel" button reloads page usually, but this handles the toggle
                const container = document.getElementById('edit-preview-container');
                <?php if (!empty($donor['Donor_ProfilePicture']) && file_exists($donor['Donor_ProfilePicture'])): ?>
                    container.innerHTML = `<img src="<?php echo $donor['Donor_ProfilePicture']; ?>" alt="Profile">`;
                <?php else: ?>
                    container.innerHTML = `<div class="default-avatar-icon"><i class="fas fa-user"></i></div>`;
                <?php endif; ?>
                // Ensure button exists for future toggles
                const btn = document.getElementById('removeImgBtn'); 
                if(!container.contains(btn) && btn) container.appendChild(btn); 
                else if(!btn) {
                     // If button was wiped, recreate implies page reload needed or careful DOM manip. 
                     // Simplified: Reloading page on Cancel is safer, but here we just hide/show.
                }
            }
        }

        function validateName(input) { input.value = input.value.replace(/[^a-zA-Z\s]/g, ''); }
        
        function validateEmailDetailed(email) {
            if (!email) return "Email is required.";
            if (!email.includes('@')) return "Missing '@' symbol in email.";
            const parts = email.split('@');
            if (parts[1].length === 0) return "Missing domain name (e.g., gmail.com).";
            if (!parts[1].includes('.')) return "Missing top-level domain (like .com or .org).";
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailPattern.test(email)) return "Invalid email format.";
            return "";
        }

        function validatePhoneDetailed(val) {
            if (!val.includes('-')) return "Missing hyphen symbol ( - ). Please use the dash key.";
            const parts = val.split('-'); 
            if (parts.length !== 2) return "Invalid format. Only one hyphen allowed.";
            const front = parts[0]; const back = parts[1];
            
            // Check strictly 2 digits for prefix
            if (front.length !== 2) return "Prefix must be 2 digits (e.g., 11-19).";
            
            // Check numeric and range 11-19
            if (!/^\d+$/.test(front)) return "Prefix must be numbers.";
            const prefixNum = parseInt(front, 10);
            if (prefixNum < 11 || prefixNum > 19) return "Prefix must be between 11 and 19 (e.g., 12-xxxx).";

            if (back.length === 0) return "Please enter numbers after the hyphen ( - ).";
            
            // Check missing digits
            if (back.length < 7) { 
                let diff = 7 - back.length; 
                return `Number after hyphen ( - ) is too short. Please add at least ${diff} more digit(s).`; 
            }
            if (back.length > 8) return "Number after hyphen ( - ) is too long. Max 8 digits allowed."; 
            
            return ""; 
        }

        function validateICDetailed(ic) {
            const clean = ic.replace(/[^0-9]/g, '');
            if (clean.length !== 12) return "Incomplete IC number.";
            const pbCode = parseInt(clean.substring(6, 8));
            const validStateCodes = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,82];
            if (!validStateCodes.includes(pbCode)) return "Invalid State code.";
            return "";
        }
        function isAgeValid(dobValue) { if (!dobValue) return true; const age = new Date().getFullYear() - new Date(dobValue).getFullYear(); return age >= 18; }
        
        function showFieldError(inputId, message) {
            const input = document.getElementById(inputId); if (!input) return;
            input.classList.add('input-error');
            let parent = input.parentNode;
            if (parent.classList.contains('phone-format')) parent = parent.parentNode; 
            let errorDiv = parent.querySelector('.inline-error');
            if (!errorDiv) { errorDiv = document.createElement('div'); errorDiv.className = 'inline-error'; parent.appendChild(errorDiv); }
            errorDiv.textContent = message;
        }
        function clearFormErrors(formId) {
            const form = document.getElementById(formId);
            const inputs = form.querySelectorAll('.form-input, .form-select');
            inputs.forEach(i => i.classList.remove('input-error'));
            form.querySelectorAll('.inline-error').forEach(e => e.remove());
        }
        function validateForm(type) {
            clearFormErrors('editDonorForm');
            let hasError = false;
            let firstErrorMsg = "";
            let nameId='donor_name', emailId='email', dobId='dob', contactId='edit_contact', icId='ic_number';

            let nameVal = document.getElementById(nameId).value.trim();
            if (!nameVal) { showFieldError(nameId, "This field is required."); hasError = true; if(!firstErrorMsg) firstErrorMsg="Name required."; }

            let emailVal = document.getElementById(emailId).value.trim();
            let emailMsg = validateEmailDetailed(emailVal);
            if (emailMsg) { showFieldError(emailId, emailMsg); hasError = true; if(!firstErrorMsg) firstErrorMsg=emailMsg; }

            let contactVal = document.getElementById(contactId).value.trim();
            if (!contactVal) { showFieldError(contactId, "This field is required."); hasError = true; if(!firstErrorMsg) firstErrorMsg="Contact required."; }
            else { 
                let phoneMsg = validatePhoneDetailed(contactVal); 
                if(phoneMsg) { 
                    showFieldError(contactId, phoneMsg); 
                    hasError = true; 
                    if(!firstErrorMsg) firstErrorMsg=phoneMsg; 
                } 
            }

            // UPDATED: IC Check - Only if entered
            let icVal = document.getElementById(icId).value.trim();
            if (icVal) { 
                let icMsg = validateICDetailed(icVal); 
                if(icMsg) { showFieldError(icId, icMsg); hasError = true; if(!firstErrorMsg) firstErrorMsg=icMsg; } 
            }
            
            // UPDATED: DOB Check - Only if entered
            let dobVal = document.getElementById(dobId).value;
            if (dobVal && !isAgeValid(dobVal)) { showFieldError(dobId, "Donor must be at least 18 years old."); hasError = true; if(!firstErrorMsg) firstErrorMsg="Donor must be > 18."; }

            if (hasError) { showSystemAlert(firstErrorMsg || "Please fix errors.", 'error'); return false; }
            return true; 
        }

        function previewImage(input, containerId) {
            const container = document.getElementById(containerId);
            const removeBtn = document.getElementById('removeImgBtn');
            if (input.files && input.files[0]) { 
                const reader = new FileReader(); 
                reader.onload = function(e) { 
                    // Remove current img if exists
                    const existingImg = container.querySelector('img');
                    const existingIcon = container.querySelector('.default-avatar-icon');
                    if(existingImg) existingImg.remove();
                    if(existingIcon) existingIcon.remove();

                    const newImg = document.createElement('img');
                    newImg.src = e.target.result;
                    newImg.alt = "Preview";
                    container.prepend(newImg); // Add before button

                    // Show remove button
                    removeBtn.style.display = 'flex';
                }; 
                reader.readAsDataURL(input.files[0]); 
            }
        }

        // UPDATED: Stop propagation to prevent image viewer opening when clicking remove
        function removeImage(inputId, containerId, event) {
            if(event) event.stopPropagation();

            document.getElementById(inputId).value = ''; 
            const container = document.getElementById(containerId);
            const removeBtn = document.getElementById('removeImgBtn');
            
            // Restore original image if exists, or default icon
            <?php if (!empty($donor['Donor_ProfilePicture']) && file_exists($donor['Donor_ProfilePicture'])): ?>
                const originalSrc = "<?php echo $donor['Donor_ProfilePicture']; ?>";
                // Keep button logic structure but update content
                const btnClone = removeBtn.cloneNode(true);
                container.innerHTML = `<img src="${originalSrc}" alt="Profile">`;
                container.appendChild(btnClone);
            <?php else: ?>
                const btnClone = removeBtn.cloneNode(true);
                container.innerHTML = `<div class="default-avatar-icon"><i class="fas fa-user"></i></div>`;
                container.appendChild(btnClone);
            <?php endif; ?>
            
            // Hide the button since we are back to original state
            document.getElementById('removeImgBtn').style.display = 'none';
            
            showSystemAlert("Image selection cleared.", 'success');
        }
        
        function detectStateFromPostcode(postcodeInputId, stateSelectId) {
            const code = document.getElementById(postcodeInputId).value; const stateSelect = document.getElementById(stateSelectId);
            if (code.length >= 2) {
                const prefix = parseInt(code.substring(0, 2)); let state = "";
                if (prefix >= 1 && prefix <= 2) state = "Perlis"; else if (prefix >= 5 && prefix <= 9) state = "Kedah"; else if (prefix >= 10 && prefix <= 14) state = "Penang";
                else if (prefix >= 15 && prefix <= 18) state = "Kelantan"; else if (prefix >= 20 && prefix <= 24) state = "Terengganu"; else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                else if (prefix >= 30 && prefix <= 39) state = "Perak"; else if (prefix >= 40 && prefix <= 48) state = "Selangor"; else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                else if (prefix === 62) state = "Putrajaya"; else if (prefix >= 63 && prefix <= 68) state = "Selangor"; else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                else if (prefix >= 75 && prefix <= 78) state = "Melaka"; else if (prefix >= 80 && prefix <= 86) state = "Johor"; else if (prefix === 87) state = "Labuan";
                else if (prefix >= 88 && prefix <= 91) state = "Sabah"; else if (prefix >= 93 && prefix <= 98) state = "Sarawak";
                if (state !== "") { stateSelect.value = state; }
            }
        }

        document.getElementById('edit_contact').addEventListener('input', function(e) { let val = this.value.replace(/\D/g, ''); if (val.length > 11) val = val.substring(0, 11); let newVal = ''; if (val.length > 2) { newVal += val.substring(0, 2) + '-' + val.substring(2); } else { newVal = val; } this.value = newVal; });
        document.getElementById('ic_number').addEventListener('input', function(e) {
            let val = this.value.replace(/\D/g, ''); if (val.length > 12) val = val.substring(0, 12); let newVal = ''; newVal += val.substring(0, 6); if (val.length > 6) { newVal += '-' + val.substring(6, 8); } if (val.length > 8) { newVal += '-' + val.substring(8, 12); } this.value = newVal;
            const dobInput = document.getElementById('dob');
            if (val.length >= 6) {
                const yearPrefix = parseInt(val.substring(0, 2)); const month = val.substring(2, 4); const day = val.substring(4, 6);
                const currentYearShort = new Date().getFullYear() % 100;
                const fullYear = (yearPrefix > currentYearShort) ? '19' + val.substring(0, 2) : '20' + val.substring(0, 2);
                if (parseInt(month) >= 1 && parseInt(month) <= 12 && parseInt(day) >= 1 && parseInt(day) <= 31) {
                    dobInput.value = `${fullYear}-${month}-${day}`; dobInput.readOnly = true; dobInput.style.backgroundColor = "#e9ecef"; dobInput.style.color = "#6c757d"; dobInput.style.cursor = "not-allowed";
                } else { dobInput.readOnly = false; dobInput.style.backgroundColor = ""; dobInput.style.color = ""; dobInput.style.cursor = ""; }
            } else { dobInput.readOnly = false; dobInput.style.backgroundColor = ""; dobInput.style.color = ""; dobInput.style.cursor = ""; }
        });

        // --- Image Viewer Logic ---
        function openImageViewer() {
            // UPDATED: Prevent viewing if user has just selected a new file (focus should be on verifying or removing)
            // Or allow it, but ensure remove button is distinct. 
            // Current logic: simple check if file input has value
            const fileInput = document.getElementById('edit_profile_picture');
            if(fileInput.value !== "") {
                 // User selected a new file. Clicking preview shouldn't necessarily open zoom,
                 // or it's fine to zoom, but X button is the priority.
                 // We will allow zoom to check the new file.
            }

            const container = document.getElementById('edit-preview-container');
            const img = container.querySelector('img'); 
            const modal = document.getElementById('imageViewerModal');
            const fullImage = document.getElementById('fullImageView');
            const placeholder = document.getElementById('placeholderView');

            if (img) {
                fullImage.src = img.src;
                fullImage.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                fullImage.style.display = 'none';
                placeholder.style.display = 'flex';
                // Ensure placeholder has the icon
                placeholder.innerHTML = '<i class="fas fa-user"></i>'; 
            }
            modal.style.display = 'flex';
        }

        function closeImageModal(e, force = false) {
            // Close if clicking the background or the X button
            if (force || e.target.id === 'imageViewerModal') {
                document.getElementById('imageViewerModal').style.display = 'none';
            }
        }
    </script>
</body>
</html>