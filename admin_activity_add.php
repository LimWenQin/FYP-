<?php
// admin_activity_add.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- Malaysia Banks List ---
$malaysiaBanks = [
    "Maybank" => "Maybank",
    "CIMB" => "CIMB Bank",
    "Public Bank" => "Public Bank",
    "RHB" => "RHB Bank",
    "Hong Leong" => "Hong Leong Bank",
    "AmBank" => "AmBank",
    "UOB" => "UOB Malaysia",
    "Bank Rakyat" => "Bank Rakyat",
    "OCBC" => "OCBC Bank",
    "HSBC" => "HSBC Bank",
    "Bank Islam" => "Bank Islam",
    "Affin Bank" => "Affin Bank",
    "Alliance Bank" => "Alliance Bank",
    "Standard Chartered" => "Standard Chartered",
    "MBSB" => "MBSB Bank",
    "Citibank" => "Citibank",
    "Bank Muamalat" => "Bank Muamalat",
    "Agrobank" => "Agrobank",
    "BSN" => "Bank Simpanan Nasional"
];

// --- AJAX: GET BRANCH BANK INFO ---
if (isset($_GET['action']) && $_GET['action'] == 'get_branch_bank_info' && isset($_GET['branch_id'])) {
    $branchId = intval($_GET['branch_id']);
    $sql = "SELECT Branch_BankName, Branch_BankAccount FROM branch WHERE Branch_ID = $branchId";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(null);
    }
    exit();
}

// --- HELPER: Image Upload ---
function handleMultiImageUpload($files) {
    $uploadedPaths = [];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $uploadDir = 'uploads/activities/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    if (isset($files['name']) && is_array($files['name'])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] == 0 && in_array($files['type'][$i], $allowedTypes)) {
                $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $fileName = 'act_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
                $uploadPath = $uploadDir . $fileName;
                if (move_uploaded_file($files['tmp_name'][$i], $uploadPath)) {
                    $uploadedPaths[] = $uploadPath;
                }
            }
        }
    }
    return $uploadedPaths;
}

$saveSuccess = false;
$errorMessage = null;

// --- HANDLE SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_activity'])) {
    $activityName = mysqli_real_escape_string($conn, trim($_POST['activity_name']));
    $branchId = mysqli_real_escape_string($conn, $_POST['branch_id']);
    $activityStatus = mysqli_real_escape_string($conn, $_POST['activity_status']);
    $description = mysqli_real_escape_string($conn, trim($_POST['activity_description']));
    $targetAmount = mysqli_real_escape_string($conn, $_POST['target_amount']);
    $getAmount = 0.00; 
    $bankName = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $bankAccount = mysqli_real_escape_string($conn, $_POST['bank_account']);
    $startDate = mysqli_real_escape_string($conn, $_POST['start_date']);
    $endDate = mysqli_real_escape_string($conn, $_POST['end_date']);
    $specificDate = !empty($_POST['specific_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['specific_date']) . "'" : "NULL";
    $venue = !empty($_POST['venue']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['venue'])) . "'" : "NULL";
    $organizer = !empty($_POST['organizer']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['organizer'])) . "'" : "NULL";
    $contactName = !empty($_POST['contact_name']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['contact_name'])) . "'" : "NULL";
    
    $contactRaw = trim($_POST['contact_number']);
    $contactNumber = (strpos($contactRaw, '+60') === 0) ? $contactRaw : "+60" . $contactRaw;
    $contactNumber = !empty($contactNumber) ? "'" . mysqli_real_escape_string($conn, $contactNumber) . "'" : "NULL";
    
    $contactEmail = !empty($_POST['contact_email']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['contact_email'])) . "'" : "NULL";
    $maxParticipants = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : 0;
    
    // Address Fields
    $address1 = mysqli_real_escape_string($conn, trim($_POST['address1']));
    $address2 = mysqli_real_escape_string($conn, trim($_POST['address2']));
    $address3 = mysqli_real_escape_string($conn, trim($_POST['address3']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    $state = mysqli_real_escape_string($conn, $_POST['state']); 
    $postalCode = mysqli_real_escape_string($conn, trim($_POST['postal_code']));
    $country = "Malaysia"; 
    
    $imageJson = "[]";
    if (isset($_FILES['activity_images'])) {
        $uploaded = handleMultiImageUpload($_FILES['activity_images']);
        if(count($uploaded) > 10) $uploaded = array_slice($uploaded, 0, 10);
        if (!empty($uploaded)) $imageJson = mysqli_real_escape_string($conn, json_encode($uploaded));
    }

    if(empty($activityName) || empty($branchId) || empty($targetAmount) || empty($bankName)) {
         $errorMessage = "Required fields are missing.";
    } else {
        $sql = "INSERT INTO activity (
            Activity_Name, Activity_Venue, Activity_Date, Activity_StartDate, Activity_EndDate, 
            Activity_Description, Activity_Organizer, Activity_Contact_Name, Activity_Contact_Number, 
            Activity_Contact_Email, Activity_Max_Participants, Activity_Images, Activity_Status, 
            Activity_GetAmount, Activity_TargetAmount, Activity_Address1, Activity_Address2, 
            Activity_Address3, Activity_City, Activity_State, Activity_PostalCode, Activity_Country, Branch_ID,
            Activity_BankName, Activity_BankAccount
        ) VALUES (
            '$activityName', $venue, $specificDate, '$startDate', '$endDate', 
            '$description', $organizer, $contactName, $contactNumber, 
            $contactEmail, $maxParticipants, '$imageJson', '$activityStatus', 
            '$getAmount', '$targetAmount', '$address1', '$address2', 
            '$address3', '$city', '$state', '$postalCode', '$country', '$branchId',
            '$bankName', '$bankAccount'
        )";
        
        if ($conn->query($sql)) {
            $saveSuccess = true;
        } else {
            $errorMessage = "Database Error: " . $conn->error;
        }
    }
}

// Load Branches
$branches = [];
$branchResult = $conn->query("SELECT Branch_ID, Branch_Name FROM branch WHERE Is_Deleted = 0 ORDER BY Branch_Name");
if ($branchResult) { while($row = $branchResult->fetch_assoc()) $branches[] = $row; }

$malaysiaStates = ['Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Activity - Donation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
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
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; font-size: 14px; box-sizing: border-box; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .required { color: red; margin-left: 3px; font-weight: bold; }
        
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }

        .section-separator { border-top: 1px dashed #ddd; margin: 30px 0 20px; position: relative; }
        .section-separator span { position: absolute; top: -12px; left: 0; background: #fff; padding-right: 10px; font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; }

        .phone-format { display: flex; align-items: center; } 
        .phone-prefix { padding: 12px 15px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: #666; font-size: 14px; } 
        .phone-input { border-radius: 0 5px 5px 0 !important; }

        .readonly-field { background-color: #e9ecef !important; color: #6c757d; cursor: not-allowed; pointer-events: none; }

        /* Upload Styling */
        .upload-container { width: 100%; }
        .upload-box { border: 2px dashed #ccc; background: #fafafa; border-radius: 8px; padding: 30px 20px; text-align: center; cursor: pointer; transition: all 0.3s; position: relative; display: block; margin-bottom: 10px; }
        .upload-box:hover { background: #fff5f5; border-color: #F28585; }
        .upload-box i { font-size: 32px; color: #aaa; margin-bottom: 10px; display: block; }
        .upload-box input[type="file"] { display: none; }
        
        .preview-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
        .preview-item { position: relative; width: 100px; height: 100px; border-radius: 5px; overflow: hidden; border: 1px solid #eee; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .remove-img-btn { position: absolute; top: 4px; right: 4px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.3); transition: 0.2s; }
        .remove-img-btn:hover { background: #c82333; transform: scale(1.1); }

        .button-group { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn-clear { padding: 12px 25px; background: white; color: #555; border: 1px solid #ddd; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-clear:hover { background: #f8f9fa; border-color: #aaa; color: #333; transform: translateY(-1px); }
        .btn-save { padding: 12px 25px; background: linear-gradient(135deg, #F28585 0%, #ff9a9a 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(242, 133, 133, 0.3); display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(242, 133, 133, 0.4); }

        .input-error { border-color: #dc3545 !important; background-color: #fff5f5; }
        .inline-error { color: #dc3545; font-size: 11px; margin-top: 4px; display: block; font-weight: 500; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

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
    </style>
</head>
<body>
    
    <div id="customAlert" class="custom-alert"><i class="fas" id="alertIcon"></i><div class="alert-content"><h4 id="alertTitle">Title</h4><p id="alertMessage">Message</p></div></div>

    <div id="successModal" class="modal-overlay" style="display: <?php echo $saveSuccess ? 'flex' : 'none'; ?>;">
        <div class="success-modal">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <h2 style="margin-bottom: 10px; font-size: 22px;">Success!</h2>
            <p style="color: #666; line-height: 1.5;">Activity has been successfully created.</p>
            <div class="modal-btn-group">
                <a href="activity_management.php" class="btn-clear">Go to List</a>
                <button type="button" class="btn-save" onclick="window.location.href='admin_activity_add.php'">Create Another</button>
            </div>
        </div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="activity_management.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back </a>
                <div class="header-title">
                    <h1>Add New Activity</h1>
                    <p>Create a new fundraising event or activity.</p>
                </div>
            </div>

            <div class="form-container">
                <form id="addActivityForm" action="admin_activity_add.php" method="POST" enctype="multipart/form-data" novalidate onsubmit="return validateForm()">
                    <input type="hidden" name="add_activity" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Activity Images (Max 10) <span class="required">*</span></label>
                        <div class="upload-container">
                            <label class="upload-box" id="uploadBox">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click or Drag images here</p>
                                <input type="file" id="activity_images" name="activity_images[]" multiple accept="image/*" onchange="previewImages(this)">
                            </label>
                            <div class="preview-grid" id="previewContainer"></div>
                        </div>
                        <span class="form-guide">Accepted formats: JPG, PNG. Multiple files allowed. First image will be the cover.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Activity Name <span class="required">*</span></label>
                        <input type="text" id="activity_name" name="activity_name" class="form-input" placeholder="e.g. Charity Run 2024" required>
                        <span class="form-guide">Official name of the event (Numbers are allowed).</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Branch <span class="required">*</span></label>
                            <select id="branch_id" name="branch_id" class="form-select" required onchange="fetchBranchBankInfo()">
                                <option value="">Select Branch</option>
                                <?php foreach($branches as $b) echo "<option value='{$b['Branch_ID']}'>{$b['Branch_Name']}</option>"; ?>
                            </select>
                            <span class="form-guide">Select the organizing branch.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <select id="activity_status" name="activity_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Upcoming">Upcoming</option>
                                <option value="Completed">Completed</option>
                            </select>
                            <span class="form-guide">Current operational status.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description <span class="required">*</span></label>
                        <textarea id="activity_description" name="activity_description" class="form-textarea" rows="4" placeholder="e.g. A fundraising event for..." required></textarea>
                        <span class="form-guide">Detailed explanation of the activity purpose and goals.</span>
                    </div>

                    <div class="section-separator"><span>Bank Information</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Bank Name <span class="required">*</span></label>
                            <select name="bank_name" id="bank_name" class="form-select" required onchange="setupBankValidation()">
                                <option value="">-- Select Bank --</option>
                                <?php foreach($malaysiaBanks as $short => $full) echo "<option value='$short'>$full</option>"; ?>
                            </select>
                            <span class="form-guide">Bank for receiving funds.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number <span class="required">*</span></label>
                            <input type="text" name="bank_account" id="bank_account" class="form-input" placeholder="e.g. 1122334455" required oninput="handleBankInput()">
                            <div id="bank_counter" style="font-size:12px; margin-top:2px; font-weight:bold; color:#dc3545;"></div>
                            <span class="form-guide">Numbers only.</span>
                        </div>
                    </div>

                    <div class="section-separator"><span>Details</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Target Amount (RM) <span class="required">*</span></label>
                            <input type="number" id="target_amount" name="target_amount" class="form-input" step="0.01" min="0" placeholder="e.g. 5000" required onkeypress="return (event.charCode !=8 && 0 == event.charCode || (event.charCode >= 48 && event.charCode <= 57) || event.charCode == 46)">
                            <span class="form-guide">Estimated fundraising goal (Cannot be negative).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Participants</label>
                            <input type="number" id="max_participants" name="max_participants" class="form-input" placeholder="e.g. 100 (0 for unlimited)" min="0" onkeypress="return (event.charCode !=8 &&0 == event.charCode || (event.charCode >= 48 && event.charCode <= 57))">
                            <span class="form-guide">Limit attendees (0 means no limit).</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Date <span class="required">*</span></label>
                            <input type="date" id="start_date" name="start_date" class="form-input" min="<?php echo date('Y-m-d'); ?>" required>
                            <span class="form-guide">Date when activity begins.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date <span class="required">*</span></label>
                            <input type="date" id="end_date" name="end_date" class="form-input" required>
                            <span class="form-guide">Date when activity ends (Must be after Start Date).</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Specific Date (One Day)</label>
                            <input type="date" id="specific_date" name="specific_date" class="form-input" min="<?php echo date('Y-m-d'); ?>">
                            <span class="form-guide">Optional: For single day events.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Venue Name</label>
                            <input type="text" id="venue" name="venue" class="form-input" placeholder="e.g. Dewan Serbaguna" oninput="toggleAddressRequired()">
                            <span class="form-guide">Building or place name.</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Organizer Name <span class="required">*</span></label>
                            <input type="text" id="organizer" name="organizer" class="form-input" placeholder="e.g. Youth Care Team" oninput="validatePersonName(this)">
                            <span class="form-guide">Organizer team (Letters only).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Person <span class="required">*</span></label>
                            <input type="text" id="contact_name" name="contact_name" class="form-input" placeholder="e.g. Ali bin Ahmad" oninput="validatePersonName(this)">
                            <span class="form-guide">PIC name (Letters only).</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="contact_number" name="contact_number" class="form-input phone-input" maxlength="11" placeholder="11-12345678" required>
                            </div>
                            <span class="form-guide">Format: 11-12345678 (Prefix 11-19).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Email <span class="required">*</span></label>
                            <input type="email" id="contact_email" name="contact_email" class="form-input" placeholder="e.g. contact@example.com" required>
                            <span class="form-guide">Valid email address.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address 1 <span id="req_addr1" style="display:none;" class="required">*</span></label>
                        <input type="text" id="address1" name="address1" class="form-input" placeholder="e.g. No. 123, Jalan Harmoni">
                        <span class="form-guide">Unit, Floor, Building.</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Address 2 <span id="req_addr2" style="display:none;" class="required">*</span></label>
                            <input type="text" id="address2" name="address2" class="form-input" placeholder="e.g. Taman Melati">
                            <span class="form-guide">Area/Taman.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address 3</label>
                            <input type="text" name="address3" class="form-input" placeholder="e.g. Near Petronas">
                            <span class="form-guide">Optional details.</span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postcode <span id="req_post" style="display:none;" class="required">*</span></label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input" oninput="detectStateFromPostcode('postal_code', 'state')" placeholder="e.g. 50000">
                            <span class="form-guide">5-digit postcode.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <span id="req_city" style="display:none;" class="required">*</span></label>
                            <input type="text" id="city" name="city" class="form-input" placeholder="e.g. Kuala Lumpur">
                            <span class="form-guide">City name.</span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State <span id="req_state" style="display:none;" class="required">*</span></label>
                            <select id="state" name="state" class="form-select">
                                <option value="">Select State</option>
                                <?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?>
                            </select>
                            <span class="form-guide">State (Auto-detected from Postcode).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country <span class="required">*</span></label>
                            <input type="text" name="country" class="form-input readonly-field" value="Malaysia" readonly required>
                            <span class="form-guide">Fixed to Malaysia.</span>
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn-clear" onclick="clearForm()"><i class="fas fa-eraser"></i> Clear</button>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Create Activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let selectedFiles = [];

        function showSystemAlert(message, type = 'error') {
            const alertBox = document.getElementById('customAlert');
            const alertTitle = document.getElementById('alertTitle');
            const alertMsg = document.getElementById('alertMessage');
            alertBox.className = 'custom-alert ' + type;
            if (type === 'error') { document.getElementById('alertIcon').className = 'fas fa-exclamation-circle'; alertTitle.innerText = 'Error'; } 
            else { document.getElementById('alertIcon').className = 'fas fa-check-circle'; alertTitle.innerText = 'Success'; }
            alertTitle.innerText = type === 'error' ? 'Error' : 'Success';
            alertMsg.innerText = message;
            alertBox.classList.add('show');
            setTimeout(() => { alertBox.classList.remove('show'); }, 4000);
        }

        <?php if($errorMessage): ?>
            showSystemAlert("<?php echo addslashes($errorMessage); ?>", 'error');
        <?php endif; ?>

        // Image Preview with Delete Button
        function previewImages(input) {
            const gallery = document.getElementById('previewContainer');
            const files = Array.from(input.files);
            
            if (selectedFiles.length + files.length > 10) {
                showSystemAlert("Max 10 images allowed.", 'error');
                return;
            }
            
            files.forEach(file => {
                selectedFiles.push(file);
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `<img src="${e.target.result}" alt="Preview"><button type="button" class="remove-img-btn" onclick="removeImage(this)"><i class="fas fa-times"></i></button>`;
                    gallery.appendChild(div);
                }
                reader.readAsDataURL(file);
            });
            updateFileInput();
        }

        function removeImage(btn) {
            const items = Array.from(document.querySelectorAll('.preview-item'));
            const index = items.indexOf(btn.parentElement);
            if (index > -1) {
                selectedFiles.splice(index, 1);
                btn.parentElement.remove();
                updateFileInput();
            }
        }

        function updateFileInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            document.getElementById('activity_images').files = dt.files;
        }

        function clearForm() {
            document.getElementById('addActivityForm').reset();
            document.getElementById('previewContainer').innerHTML = '';
            selectedFiles = [];
            clearFormErrors('addActivityForm');
            const state = document.getElementById('state');
            state.style.backgroundColor = '';
            state.style.pointerEvents = 'auto';
            state.classList.remove('readonly-field');
            toggleAddressRequired(); // Reset address required status
        }

        // Address Requirement Logic
        function toggleAddressRequired() {
            const venueVal = document.getElementById('venue').value.trim();
            const isReq = venueVal.length > 0;
            
            const ids = ['address1', 'address2', 'postal_code', 'city', 'state'];
            const stars = ['req_addr1', 'req_addr2', 'req_post', 'req_city', 'req_state'];
            
            ids.forEach((id, idx) => {
                const el = document.getElementById(id);
                if(isReq) el.setAttribute('required', 'required');
                else el.removeAttribute('required');
                
                const star = document.getElementById(stars[idx]);
                if(star) star.style.display = isReq ? 'inline' : 'none';
            });
        }

        // Bank Validation
        const bankRules = { "Maybank": { digits: 12 }, "CIMB": { digits: 10 }, "Public Bank": { digits: 10 }, "RHB": { digits: 10 }, "Hong Leong": { digits: 11 }, "AmBank": { digits: 13 }, "UOB": { digits: 11 }, "Bank Rakyat": { digits: 12 }, "OCBC": { digits: 10 }, "HSBC": { digits: 12 }, "Bank Islam": { digits: 14 }, "Affin Bank": { digits: 10 }, "Alliance Bank": { digits: 10 }, "Standard Chartered": { digits: 10 }, "MBSB": { digits: 10 }, "Citibank": { digits: 10 }, "Bank Muamalat": { digits: 14 }, "Agrobank": { digits: 15 }, "BSN": { digits: 16 } };

        function setupBankValidation() {
            const bankSelect = document.getElementById('bank_name');
            const accInput = document.getElementById('bank_account');
            if(!bankSelect || !accInput) return;
            const selectedBank = bankSelect.value;
            const rule = bankRules[selectedBank];
            if (selectedBank && rule) {
                accInput.maxLength = rule.digits;
                if (accInput.value.length > rule.digits) accInput.value = accInput.value.slice(0, rule.digits);
                accInput.placeholder = `Enter ${rule.digits} digits for ${selectedBank}`;
                handleBankInput();
            } else {
                accInput.removeAttribute('maxLength');
                accInput.placeholder = "Select a bank first";
                document.getElementById('bank_counter').innerText = "";
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
                const max = rule.digits;
                counter.innerText = `Digits: ${current} / ${max}`;
                counter.style.color = (current === max) ? '#28a745' : '#dc3545';
            } else { counter.innerText = ""; }
        }

        // Date Logic
        const dateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const statusSelect = document.getElementById('activity_status');

        if(dateInput) {
            dateInput.addEventListener('change', function() {
                const inputDateStr = this.value; 
                if(!inputDateStr) return;
                
                // End Date cannot be before Start Date
                endDateInput.min = inputDateStr;
                if(endDateInput.value && endDateInput.value < inputDateStr) {
                    endDateInput.value = ""; 
                }

                // Auto Status Logic
                const d = new Date();
                const todayStr = d.toISOString().split('T')[0];
                if (inputDateStr === todayStr) { statusSelect.value = 'Active'; } 
                else if (inputDateStr > todayStr) { statusSelect.value = 'Upcoming'; }
            });
        }

        // Postcode State
        function detectStateFromPostcode(postcodeInputId, stateSelectId) {
            const code = document.getElementById(postcodeInputId).value.replace(/\D/g, ''); 
            const stateSelect = document.getElementById(stateSelectId);
            if (code.length >= 2) {
                const prefix = parseInt(code.substring(0, 2)); let state = "";
                if (prefix >= 1 && prefix <= 2) state = "Perlis"; else if (prefix >= 5 && prefix <= 9) state = "Kedah"; else if (prefix >= 10 && prefix <= 14) state = "Penang";
                else if (prefix >= 15 && prefix <= 18) state = "Kelantan"; else if (prefix >= 20 && prefix <= 24) state = "Terengganu"; else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                else if (prefix >= 30 && prefix <= 36) state = "Perak"; else if (prefix >= 40 && prefix <= 48) state = "Selangor"; else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                else if (prefix >= 62 && prefix <= 62) state = "Putrajaya"; else if (prefix >= 63 && prefix <= 68) state = "Selangor"; else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                else if (prefix >= 75 && prefix <= 78) state = "Melaka"; else if (prefix >= 80 && prefix <= 86) state = "Johor"; else if (prefix == 87) state = "Labuan";
                else if (prefix >= 88 && prefix <= 91) state = "Sabah"; else if (prefix >= 93 && prefix <= 98) state = "Sarawak";
                
                if (state !== "") { 
                    stateSelect.value = state; 
                    stateSelect.style.backgroundColor = '#e9ecef';
                    stateSelect.style.pointerEvents = 'none'; 
                    stateSelect.classList.add('readonly-field');
                }
            } else {
                stateSelect.style.backgroundColor = '';
                stateSelect.style.pointerEvents = 'auto';
                stateSelect.classList.remove('readonly-field');
            }
        }

        // Phone Input Formatting (Staff Add Logic)
        document.getElementById('contact_number').addEventListener('input', function(e) { 
            let val = this.value.replace(/\D/g, ''); 
            if (val.length > 11) val = val.substring(0, 11); 
            let newVal = ''; 
            if (val.length > 2) { newVal += val.substring(0, 2) + '-' + val.substring(2); } 
            else { newVal = val; } 
            this.value = newVal; 
        });

        function validatePersonName(input) {
            input.value = input.value.replace(/[^a-zA-Z\s]/g, '');
        }

        // --- VALIDATION HELPERS ---
        function showFieldError(inputId, message) {
            const input = document.getElementById(inputId); if (!input) return;
            input.classList.add('input-error');
            let parent = input.parentNode;
            if (parent.classList.contains('phone-format') || parent.id === 'uploadBox') parent = parent.parentNode; 
            let errorDiv = parent.querySelector('.inline-error');
            if (!errorDiv) { errorDiv = document.createElement('div'); errorDiv.className = 'inline-error'; parent.appendChild(errorDiv); }
            errorDiv.textContent = message;
        }

        function clearFormErrors(formId) {
            const form = document.getElementById(formId);
            const inputs = form.querySelectorAll('.form-input, .form-select, .form-textarea, .upload-box');
            inputs.forEach(i => i.classList.remove('input-error'));
            form.querySelectorAll('.inline-error').forEach(e => e.remove());
        }

        function validateEmailDetailed(email) {
            if (!email) return "This field is required.";
            if (!email.includes('@')) return "Missing '@' symbol.";
            const parts = email.split('@');
            if (parts[1].length === 0) return "Missing domain name.";
            if (!parts[1].includes('.')) return "Missing top-level domain.";
            return "";
        }

        function validatePhoneDetailed(val) {
            if (!val.includes('-')) return "Missing hyphen symbol ( - ). Please use the dash key.";
            const parts = val.split('-'); 
            if (parts.length !== 2) return "Invalid format. Only one hyphen ( - ) allowed.";
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

        function validateForm() {
            clearFormErrors('addActivityForm');
            let hasError = false;
            let firstErrorMsg = "";

            // Check Image
            if (selectedFiles.length === 0) {
                const box = document.getElementById('uploadBox');
                box.classList.add('input-error');
                let p = box.parentNode; let ed = document.createElement('div'); ed.className='inline-error'; ed.textContent="At least one image is required."; p.appendChild(ed);
                
                // Show errors for all fields, don't stop here
                hasError = true; if(!firstErrorMsg) firstErrorMsg = "Images required.";
            }

            // Standard IDs check
            const ids = ['activity_name', 'branch_id', 'activity_description', 'target_amount', 'bank_name', 'bank_account', 'start_date', 'end_date', 'organizer', 'contact_name', 'contact_number', 'contact_email'];
            
            // Add conditional Address IDs if Venue is filled
            if (document.getElementById('venue').value.trim() !== "") {
                ids.push('address1', 'address2', 'postal_code', 'city', 'state');
            }

            ids.forEach(id => {
                const el = document.getElementById(id);
                if(el && !el.value.trim()) {
                    showFieldError(id, "This field is required.");
                    hasError = true;
                    if(!firstErrorMsg) firstErrorMsg = "Required fields missing.";
                }
            });

            // Specific validations (run even if hasError is true to show all errors)
            const emailMsg = validateEmailDetailed(document.getElementById('contact_email').value);
            if(emailMsg) { showFieldError('contact_email', emailMsg); hasError = true; if(!firstErrorMsg) firstErrorMsg = emailMsg; }

            const phoneMsg = validatePhoneDetailed(document.getElementById('contact_number').value);
            if(phoneMsg) { showFieldError('contact_number', phoneMsg); hasError = true; if(!firstErrorMsg) firstErrorMsg = phoneMsg; }

            if (hasError) {
                showSystemAlert("Please correct the highlighted errors.", 'error');
                return false; 
            }
            return true;
        }
    </script>
</body>
</html>