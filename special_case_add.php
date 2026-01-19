<?php
// special_case_add.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- DATA LISTS ---
$categories = ['Medical','Disability Support','Emergency Relief','Elderly Care','Children Support','Other Cases'];
$malaysiaStates = ['Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'];
$malaysiaBanks = [
    "Maybank" => "Maybank", "CIMB" => "CIMB Bank", "Public Bank" => "Public Bank", "RHB" => "RHB Bank", 
    "Hong Leong" => "Hong Leong Bank", "AmBank" => "AmBank", "UOB" => "UOB Malaysia", "Bank Rakyat" => "Bank Rakyat", 
    "OCBC" => "OCBC Bank", "HSBC" => "HSBC Bank", "Bank Islam" => "Bank Islam", "Affin Bank" => "Affin Bank", 
    "Alliance Bank" => "Alliance Bank", "Standard Chartered" => "Standard Chartered", "MBSB" => "MBSB Bank", 
    "Citibank" => "Citibank", "Bank Muamalat" => "Bank Muamalat", "Agrobank" => "Agrobank", "BSN" => "Bank Simpanan Nasional"
];

// --- FILE UPLOAD HELPER (IMAGES ONLY - FOR CASE IMAGES) ---
function handleMultipleImageUpload($files) {
    $uploadedPaths = [];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $uploadDir = 'uploads/cases/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (!is_array($files['name'])) {
        $files = ['name' => [$files['name']], 'type' => [$files['type']], 'tmp_name' => [$files['tmp_name']], 'error' => [$files['error']]];
    }

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] == 0 && in_array($files['type'][$i], $allowedTypes)) {
            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $name = 'case_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $name)) {
                $uploadedPaths[] = $uploadDir . $name;
            }
        }
    }
    return $uploadedPaths;
}

// --- FILE UPLOAD HELPER (REPORTS: PDF + IMAGES) ---
function handleReportUpload($files) {
    $uploadedPaths = [];
    // Allow Images AND PDF
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $uploadDir = 'uploads/reports/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (!is_array($files['name'])) {
        $files = ['name' => [$files['name']], 'type' => [$files['type']], 'tmp_name' => [$files['tmp_name']], 'error' => [$files['error']]];
    }

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] == 0 && in_array($files['type'][$i], $allowedTypes)) {
            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $name = 'report_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $name)) {
                $uploadedPaths[] = $uploadDir . $name;
            }
        }
    }
    return $uploadedPaths;
}

$errorMessage = "";
$saveSuccess = false;

// --- HANDLE SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_special_case'])) {
    $caseTitle = mysqli_real_escape_string($conn, $_POST['case_title']);
    $caseCategory = mysqli_real_escape_string($conn, $_POST['case_category']);
    $caseDescription = mysqli_real_escape_string($conn, $_POST['case_description']);
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $caseStatus = mysqli_real_escape_string($conn, $_POST['case_status']);
    
    $startDate = "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'";
    $endDate = "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'";
    
    $bankName = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $bankAccount = mysqli_real_escape_string($conn, $_POST['bank_account']);

    $caseLocationName = mysqli_real_escape_string($conn, $_POST['case_location_name']);
    $caseOrganizer = mysqli_real_escape_string($conn, $_POST['case_organizer']);
    $contactName = mysqli_real_escape_string($conn, $_POST['contact_name']);
    
    $rawPhone = $_POST['contact_number'];
    $contactNumber = (strpos($rawPhone, '+60') === 0) ? $rawPhone : "+60" . $rawPhone;
    $contactNumber = mysqli_real_escape_string($conn, $contactNumber);

    $contactEmail = mysqli_real_escape_string($conn, $_POST['contact_email']);
    
    $addr1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $addr2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $addr3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $zip = mysqli_real_escape_string($conn, $_POST['postal_code']);
    
    if (empty($caseTitle) || empty($targetAmount) || empty($bankAccount)) {
        $errorMessage = "Please fill in all required fields.";
    } elseif (!isset($_FILES['case_images']) || empty($_FILES['case_images']['name'][0])) {
         $errorMessage = "At least one main image is required.";
    } else {
        // 1. Handle Case Images
        $caseImagesJson = "[]";
        if (isset($_FILES['case_images'])) {
            $uploaded = handleMultipleImageUpload($_FILES['case_images']);
            if(count($uploaded) > 10) $uploaded = array_slice($uploaded, 0, 10);
            if(!empty($uploaded)) $caseImagesJson = json_encode($uploaded);
        }

        // 2. Handle Medical Reports (New Requirement)
        $medicalReportsJson = "[]";
        if (isset($_FILES['medical_reports']) && !empty($_FILES['medical_reports']['name'][0])) {
            $uploadedReports = handleReportUpload($_FILES['medical_reports']);
            if(count($uploadedReports) > 5) $uploadedReports = array_slice($uploadedReports, 0, 5); // Max 5
            if(!empty($uploadedReports)) $medicalReportsJson = json_encode($uploadedReports);
        }

        $sql = "INSERT INTO special_case (
            Case_Title, Case_Category, Case_Description, Target_Amount, Case_Status, 
            Start_Date, End_Date, Case_LocationName, Case_Organizer, Contact_Name, 
            Contact_Number, Contact_Email, Case_Address1, Case_Address2, Case_Address3, 
            Case_City, Case_State, Case_PostalCode, Case_Images, Case_BankName, 
            Case_BankAccount, Case_Medical_Report, Created_At
        ) VALUES (
            '$caseTitle', '$caseCategory', '$caseDescription', '$targetAmount', '$caseStatus', 
            $startDate, $endDate, '$caseLocationName', '$caseOrganizer', '$contactName', 
            '$contactNumber', '$contactEmail', '$addr1', '$addr2', '$addr3', 
            '$city', '$state', '$zip', '$caseImagesJson', '$bankName', 
            '$bankAccount', '$medicalReportsJson', NOW()
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
    <title>Add Special Case - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Shared Styles from branch_add.php */
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

        .phone-format { display: flex; align-items: center; } 
        .phone-prefix { padding: 12px 15px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); font-size: 14px; font-weight:bold;} 
        .phone-input { border-radius: 0 5px 5px 0 !important; }

        /* Upload Styles */
        .upload-box { border: 2px dashed #ccc; background: #fafafa; border-radius: 8px; padding: 25px; text-align: center; cursor: pointer; position: relative; transition: 0.3s; }
        .upload-box:hover { border-color: var(--primary); background: #fff5f5; }
        .upload-box i { font-size: 30px; color: #aaa; margin-bottom: 10px; display: block; }
        .upload-box input[type="file"] { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; margin-top: 15px; }
        .preview-item { position: relative; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid #eee; display: flex; align-items: center; justify-content: center; background: #fff; }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .preview-file-icon { font-size: 30px; color: #dc3545; } /* PDF Icon Style */
        .remove-img-btn { position: absolute; top: 2px; right: 2px; background: #ff4d4d; color: white; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; }

        .button-group { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        
        .btn-clear { padding: 12px 25px; background: white; color: #555; border: 1px solid #ddd; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-clear:hover { background: #f8f9fa; border-color: #aaa; color: #333; transform: translateY(-1px); }
        
        .btn-save { padding: 12px 25px; background: linear-gradient(135deg, #F28585 0%, #ff9a9a 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(242, 133, 133, 0.4); }

        .input-error { border-color: var(--danger) !important; background-color: #fff5f5; }
        .upload-box.input-error { border: 2px dashed var(--danger) !important; background-color: #fff5f5; }
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
            <h2 style="margin-bottom: 10px; font-size: 22px;">Success!</h2>
            <p style="color: #666; line-height: 1.5;">New Special Case has been successfully added.</p>
            <div class="modal-btn-group">
                <a href="special_case_management.php" class="btn-clear" style="border: 1px solid #ddd; text-decoration:none;">Return to List</a>
                <button type="button" class="btn-save" onclick="window.location.href='special_case_add.php'">Add Another</button>
            </div>
        </div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="special_case_management.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="header-title">
                    <h1>Add New Special Case</h1>
                    <p>Create a fundraising case with details, images, and targets.</p>
                </div>
            </div>

            <div class="form-container">
                <form id="addSpecialCaseForm" action="special_case_add.php" method="POST" enctype="multipart/form-data" onsubmit="return validateSpecialCaseForm()" novalidate>
                    <input type="hidden" name="add_special_case" value="1">
                    
                    <div class="form-header"><h2>Case Details</h2></div>
                    
                    <div class="form-group">
                        <label class="form-label">Case Images (Max 10) <span class="required">*</span></label>
                        <div class="upload-box" id="case_images_box">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click or Drag images here (JPG, PNG)</p>
                            <input type="file" id="case_images" name="case_images[]" multiple accept="image/*" onchange="handleFileSelect(event, 'image')">
                        </div>
                        <div class="preview-grid" id="preview_container"></div>
                        <small class="form-guide">Upload high-quality images to represent the case. Accepted formats: JPG, PNG.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Medical Reports (Max 5, Optional)</label>
                        <div class="upload-box" id="reports_box" style="border-color:#17a2b8; background:#f0fbff;">
                            <i class="fas fa-file-medical-alt" style="color:#17a2b8;"></i>
                            <p style="color:#0056b3;">Click to upload Medical Reports (PDF, JPG, PNG)</p>
                            <input type="file" id="medical_reports" name="medical_reports[]" multiple accept=".pdf, .jpg, .jpeg, .png" onchange="handleFileSelect(event, 'report')">
                        </div>
                        <div class="preview-grid" id="report_preview_container"></div>
                        <small class="form-guide">Upload supporting medical documents or doctor's letters.</small>
                    </div>

                    <div class="form-section-title">Basic Information</div>
                    <div class="form-group">
                        <label class="form-label">Case Title <span class="required">*</span></label>
                        <input type="text" id="case_title" name="case_title" class="form-input" placeholder="e.g. Urgent Flood Relief 2026">
                        <small class="form-guide">A clear, urgent title that describes the need.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category <span class="required">*</span></label>
                        <select name="case_category" id="case_category" class="form-select">
                            <?php foreach($categories as $cat) echo "<option value='$cat'>$cat</option>"; ?>
                        </select>
                        <small class="form-guide">Select the category that best fits this case to help donors find it.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description <span class="required">*</span></label>
                        <textarea id="case_description" name="case_description" class="form-textarea" rows="6" placeholder="Please provide a detailed explanation of the situation, why funds are needed, and how they will be effectively used..."></textarea>
                        <small class="form-guide">Detailed explanation of the situation, why funds are needed, and how they will be used.</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Target Amount (RM) <span class="required">*</span></label>
                            <input type="number" id="target_amount" name="target_amount" class="form-input" step="0.01" min="0" placeholder="Enter the total target amount in Ringgit Malaysia (RM)" onkeypress="return (event.charCode !=45)">
                            <small class="form-guide">Total funds required for this case (cannot be negative).</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <select id="case_status" name="case_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Upcoming">Upcoming</option>
                            </select>
                            <small class="form-guide">Current state of the fundraising campaign.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Logistics & Location</span></div>
                    <div class="form-row">
                         <div class="form-group">
                            <label class="form-label">Start Date <span class="required">*</span></label>
                            <input type="date" id="start_date" name="start_date" class="form-input" min="<?php echo date('Y-m-d'); ?>">
                            <small class="form-guide">Select the date when the campaign or assistance officially starts.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date <span class="required">*</span></label>
                            <input type="date" id="end_date" name="end_date" class="form-input" min="<?php echo date('Y-m-d'); ?>">
                            <small class="form-guide">Select the date when the campaign is expected to end.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Bank Information</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Bank Name <span class="required">*</span></label>
                            <select name="bank_name" id="bank_name" class="form-select" onchange="setupBankValidation()">
                                <option value="">-- Select Bank --</option>
                                <?php foreach($malaysiaBanks as $short => $full): ?>
                                    <option value="<?php echo $short; ?>"><?php echo $full; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-guide">Select the bank receiving donations.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number <span class="required">*</span></label>
                            <input type="text" name="bank_account" id="bank_account" class="form-input" placeholder="Select bank first" oninput="handleBankInput()">
                            <div id="bank_counter" style="font-size:12px; margin-top:2px; font-weight:bold; color:#dc3545;"></div>
                            <small class="form-guide">Enter the account number without dashes or spaces.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Address & Location (Optional)</span></div>
                    <div class="form-group">
                        <label class="form-label">Location Name</label>
                        <input type="text" id="case_location_name" name="case_location_name" class="form-input" placeholder="e.g. Community Hall / Specific Location Name">
                        <small class="form-guide">The specific location name where the event or case is centered.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" id="address1" name="address1" class="form-input" placeholder="Unit, Building, Street">
                        <small class="form-guide">House no, street, building.</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Address Line 2</label>
                            <input type="text" id="address2" name="address2" class="form-input" placeholder="Area, Taman">
                            <small class="form-guide">Area, Taman or Village.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address Line 3</label>
                            <input type="text" id="address3" name="address3" class="form-input" placeholder="Optional">
                            <small class="form-guide">Optional additional address info.</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postcode</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input" placeholder="e.g. 50000">
                            <small class="form-guide">Enter valid postal code.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" id="city" name="city" class="form-input" placeholder="e.g. Kuala Lumpur">
                            <small class="form-guide">City name.</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <select id="state" name="state" class="form-select"><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select>
                            <small class="form-guide">Select state.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-input" value="Malaysia" readonly style="background:#f8f9fa;">
                            <small class="form-guide">Currently limited to Malaysia.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Organizer & Contact</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Organizer Name <span class="required">*</span></label>
                            <input type="text" id="case_organizer" name="case_organizer" class="form-input" oninput="validateName(this)" placeholder="e.g. Hope Foundation Organization">
                            <small class="form-guide">Person or group managing this case (No Numbers).</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Person <span class="required">*</span></label>
                            <input type="text" id="contact_name" name="contact_name" class="form-input" oninput="validateName(this)" placeholder="e.g. Mr. Ali Bin Abu">
                            <small class="form-guide">Primary point of contact (No Numbers).</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Contact Phone <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="contact_number" name="contact_number" class="form-input phone-input" maxlength="11" placeholder="12-3456789">
                            </div>
                            <small class="form-guide">Mobile or office number.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Email <span class="required">*</span></label>
                            <input type="email" id="contact_email" name="contact_email" class="form-input" placeholder="e.g. contact.person@example.com">
                            <small class="form-guide">Official contact email.</small>
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn-clear" onclick="clearFormCustom()"><i class="fas fa-eraser"></i> Clear Form</button>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Case</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showSystemAlert(message, type = 'error') {
            const alertBox = document.getElementById('customAlert');
            document.getElementById('alertIcon').className = type==='error'?'fas fa-exclamation-circle':'fas fa-check-circle';
            document.getElementById('alertTitle').innerText = type==='error'?'Error':'Success';
            document.getElementById('alertMessage').innerText = message;
            alertBox.className = 'custom-alert ' + type + ' show';
            setTimeout(() => { alertBox.classList.remove('show'); }, 4000);
        }

        <?php if($errorMessage): ?>
            showSystemAlert("<?php echo addslashes($errorMessage); ?>", 'error');
        <?php endif; ?>

        // --- UPLOAD LOGIC (DUAL HANDLER) ---
        let selectedImages = [];
        let selectedReports = [];

        function handleFileSelect(event, type) {
            const newFiles = Array.from(event.target.files);
            if (type === 'image') {
                selectedImages = selectedImages.concat(newFiles);
                updateFileInput('case_images', selectedImages);
                renderPreview('preview_container', selectedImages, 'image');
            } else {
                selectedReports = selectedReports.concat(newFiles);
                updateFileInput('medical_reports', selectedReports);
                renderPreview('report_preview_container', selectedReports, 'report');
            }
        }

        function updateFileInput(id, files) {
            const dt = new DataTransfer();
            files.forEach(f => dt.items.add(f));
            document.getElementById(id).files = dt.files;
        }

        function renderPreview(containerId, files, type) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            files.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'preview-item';
                
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        item.innerHTML = `<img src="${e.target.result}"><button type="button" class="remove-img-btn" onclick="removeFile(${index}, '${type}')"><i class="fas fa-times"></i></button>`;
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    // PDF Icon
                    item.innerHTML = `<i class="fas fa-file-pdf preview-file-icon"></i><div style="font-size:10px; position:absolute; bottom:2px; left:2px; right:2px; white-space:nowrap; overflow:hidden;">${file.name}</div><button type="button" class="remove-img-btn" onclick="removeFile(${index}, '${type}')"><i class="fas fa-times"></i></button>`;
                }
                container.appendChild(item);
            });
        }

        function removeFile(index, type) {
            if (type === 'image') {
                selectedImages.splice(index, 1);
                updateFileInput('case_images', selectedImages);
                renderPreview('preview_container', selectedImages, 'image');
            } else {
                selectedReports.splice(index, 1);
                updateFileInput('medical_reports', selectedReports);
                renderPreview('report_preview_container', selectedReports, 'report');
            }
        }

        function clearFormCustom() {
            document.getElementById('addSpecialCaseForm').reset();
            selectedImages = []; selectedReports = [];
            updateFileInput('case_images', []); updateFileInput('medical_reports', []);
            document.getElementById('preview_container').innerHTML = '';
            document.getElementById('report_preview_container').innerHTML = '';
            clearFormErrors();
            document.getElementById('bank_counter').innerText = '';
        }

        // --- VALIDATION HELPERS ---
        function showFieldError(id, msg) {
            const el = document.getElementById(id);
            if(!el) return;
            if(id === 'case_images') document.getElementById('case_images_box').classList.add('input-error');
            else el.classList.add('input-error');
            
            let p = (id === 'case_images' || id === 'medical_reports') ? el.parentNode.parentNode : el.parentNode;
            if(el.parentNode.classList.contains('phone-format')) p = el.parentNode.parentNode;
            
            let err = p.querySelector('.inline-error');
            if(!err) { err = document.createElement('div'); err.className='inline-error'; p.appendChild(err); }
            err.textContent = msg;
        }
        function clearFormErrors() {
            document.querySelectorAll('.input-error').forEach(e => e.classList.remove('input-error'));
            document.querySelectorAll('.inline-error').forEach(e => e.remove());
        }

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
            if(rule){
                accInput.maxLength = rule.digits;
                accInput.placeholder = `Enter ${rule.digits} digits`;
                handleBankInput();
            }
        }
        function handleBankInput() {
            const input = document.getElementById('bank_account');
            const counter = document.getElementById('bank_counter');
            const bankSelect = document.getElementById('bank_name');
            input.value = input.value.replace(/[^0-9]/g, '');
            const rule = bankRules[bankSelect.value];
            if(rule){
                const current = input.value.length;
                counter.innerText = `Digits: ${current} / ${rule.digits}`;
                counter.style.color = (current === rule.digits) ? '#28a745' : '#dc3545';
            }
        }

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
            if (parts.length !== 2) return "Invalid format. Only one hyphen ( - ) allowed.";
            const front = parts[0]; const back = parts[1];
            if (front.length !== 2) return "Prefix must be 2 digits (e.g., 11-19).";
            if (!/^\d+$/.test(front)) return "Prefix must be numbers.";
            const prefixNum = parseInt(front, 10);
            if (prefixNum < 11 || prefixNum > 19) return "Prefix must be between 11 and 19.";
            if (back.length === 0) return "Please enter numbers after the hyphen ( - ).";
            if (back.length < 7) { let diff = 7 - back.length; return `Number after hyphen ( - ) is too short. Please add at least ${diff} more digit(s).`; }
            if (back.length > 8) return "Number after hyphen ( - ) is too long. Max 8 digits allowed."; 
            return ""; 
        }

        function validateName(input) { input.value = input.value.replace(/\d/g, ''); }

        // --- MAIN VALIDATION ---
        function validateSpecialCaseForm() {
            clearFormErrors();
            let hasError = false;
            
            // Note: Location, Address, Postcode, City, State are OPTIONAL
            const reqFields = ['case_title', 'target_amount', 'start_date', 'end_date', 'case_organizer', 'contact_name', 'contact_number', 'contact_email', 'bank_name', 'bank_account'];
            
            reqFields.forEach(id => {
                const el = document.getElementById(id);
                if(!el.value.trim()) { showFieldError(id, "This field is required."); hasError=true; }
            });

            if(selectedImages.length === 0) {
                showFieldError('case_images', "At least one main image is required.");
                hasError=true;
            }

            // Amount Validation
            const amount = document.getElementById('target_amount');
            if (amount.value.trim() && parseFloat(amount.value) < 0) {
                showFieldError('target_amount', "Cannot be negative.");
                hasError = true;
            }

            // Email Validation
            const email = document.getElementById('contact_email');
            if(email.value.trim()) {
                const err = validateEmailDetailed(email.value.trim());
                if (err) { showFieldError('contact_email', err); hasError = true; }
            }

            // Phone Validation
            const phone = document.getElementById('contact_number');
            if(phone.value.trim()) {
                const err = validatePhoneDetailed(phone.value.trim());
                if (err) { showFieldError('contact_number', err); hasError = true; }
            }
            
            // Bank Validation
            const bankName = document.getElementById('bank_name').value;
            const bankAcc = document.getElementById('bank_account').value;
            if (bankName && bankRules[bankName] && bankAcc.length !== bankRules[bankName].digits) {
                showFieldError('bank_account', `Must be ${bankRules[bankName].digits} digits.`); hasError = true;
            }

            // Dates
            const start = document.getElementById('start_date').value;
            const end = document.getElementById('end_date').value;
            if(start && end && end < start) { showFieldError('end_date', "End date cannot be before start date."); hasError=true; }

            if(hasError) { showSystemAlert("Please correct errors.", 'error'); return false; }
            return true;
        }

        // Postcode Logic
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

        document.getElementById('contact_number').addEventListener('input', function() {
            let val = this.value.replace(/\D/g, '');
            if (val.length > 11) val = val.substring(0, 11);
            if (val.length > 2) val = val.substring(0, 2) + '-' + val.substring(2);
            this.value = val;
        });
        
        document.getElementById('start_date').addEventListener('change', function() {
            const today = new Date().setHours(0,0,0,0);
            const sel = new Date(this.value).setHours(0,0,0,0);
            document.getElementById('case_status').value = (sel > today) ? 'Upcoming' : 'Active';
        });

        document.addEventListener('DOMContentLoaded', function() {
            setupBankValidation();
        });
    </script>
</body>
</html>