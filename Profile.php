<?php
include 'dataconnection.php';
include 'header_function.php';


$current_date = date('Y-m-d');
$min_date = date('Y-m-d', strtotime('-100 years')); 


$show_login_modal = false;
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $show_login_modal = true;
}

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;


$donor = [
    "Donor_Name" => "",
    "Donor_ContactNumber" => "",
    "Donor_ICNumber" => "",
    "Donor_Email" => "",
    "Donor_Address1" => "",
    "Donor_Address2" => "",
    "Donor_Address3" => "",
    "Donor_City" => "",
    "Donor_State" => "",
    "Donor_PostalCode" => "",
    "Donor_Country" => "Malaysia",
    "Donor_DOB" => "",
    "Donor_ProfilePicture" => ""
];


if ($logged_in && isset($_SESSION['donor_id'])) {
    $query = "SELECT * FROM donor WHERE Donor_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['donor_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $donor = $result->fetch_assoc();
        if ($donor['Is_Deleted'] == 1) {
             session_destroy();
             echo "<script>alert('This account has been deleted.'); window.location.href='donor_login.php';</script>";
             exit();
        }
    }
    $stmt->close();
}


$delete_success = false; 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    $delete_query = "UPDATE donor SET Is_Deleted = 1 WHERE Donor_ID = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $_SESSION['donor_id']);
    
    if ($stmt->execute()) {
        $delete_success = true; 
    } else {
        echo "<script>alert('Error deleting account.');</script>";
    }
    $stmt->close();
}

$update_success = false;
$update_message = "";
$upload_error = "";

if (!$delete_success && $_SERVER['REQUEST_METHOD'] == 'POST' && $logged_in && isset($_POST['update_profile'])) {
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    // 【修改点 1】PHP 验证：只允许字母和空格 (移除了 /)
    if (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
        echo "<script>alert('Error: Name must contain only letters and spaces.'); window.history.back();</script>";
        exit();
    }

   
    $profile_picture = $donor['Donor_ProfilePicture']; 
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = $_FILES['profile_picture']['name'];
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_ext)) {
            if ($file_size <= 2097152) { 
                $new_file_name = 'profile_' . $_SESSION['donor_id'] . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    if (!empty($donor['Donor_ProfilePicture']) && file_exists($donor['Donor_ProfilePicture'])) {
                        unlink($donor['Donor_ProfilePicture']);
                    }
                    $profile_picture = $destination;
                } else {
                    $upload_error = "Failed to upload profile picture.";
                }
            } else {
                $upload_error = "File size too large (Max 2MB).";
            }
        } else {
            $upload_error = "Invalid file type.";
        }
    }
    
    
    $raw_contact = $_POST['contact'];
    $contact = '0' . $raw_contact; 

    $ic_number = str_replace('-', '', $_POST['icnumber']); 
    $dob = $_POST['dob'];            
    $address1 = $_POST['address1'];
    $address2 = $_POST['address2'];
    $address3 = $_POST['address3'];
    $city = $_POST['city'];
    $state = $_POST['state']; 
    $postalcode = $_POST['postalcode'];
    $country = "Malaysia"; 
    
    
    $update_query = "UPDATE donor SET 
        Donor_Name = ?, 
        Donor_Email = ?, 
        Donor_ContactNumber = ?, 
        Donor_ICNumber = ?,
        Donor_DOB = ?,
        Donor_Address1 = ?,
        Donor_Address2 = ?,
        Donor_Address3 = ?,
        Donor_City = ?,
        Donor_State = ?,
        Donor_PostalCode = ?,
        Donor_Country = ?,
        Donor_ProfilePicture = ?
        WHERE Donor_ID = ?";
    
    $stmt = $conn->prepare($update_query);
    
    $stmt->bind_param("sssssssssssssi", 
        $name, $email, $contact, $ic_number, $dob, 
        $address1, $address2, $address3,
        $city, $state, $postalcode, $country,
        $profile_picture, $_SESSION['donor_id']
    );
    
    if ($stmt->execute()) {
        $update_success = true;
        $update_message = "Profile updated successfully!";
        $_SESSION['donor_name'] = $name;
        
      
        $donor['Donor_Name'] = $name;
        $donor['Donor_Email'] = $email;
        $donor['Donor_ContactNumber'] = $contact;
        $donor['Donor_ICNumber'] = $ic_number; 
        $donor['Donor_DOB'] = $dob;
        $donor['Donor_Address1'] = $address1;
        $donor['Donor_Address2'] = $address2;
        $donor['Donor_Address3'] = $address3;
        $donor['Donor_City'] = $city;
        $donor['Donor_State'] = $state;
        $donor['Donor_PostalCode'] = $postalcode;
        $donor['Donor_Country'] = $country;
        $donor['Donor_ProfilePicture'] = $profile_picture;
    } else {
        $update_message = "Error updating profile: " . $conn->error;
    }
    $stmt->close();
}

function calculateProfileCompletion($donor) {
    
    $total_fields = 10; 
    $completed_fields = 0;
   
    $fields_to_check = ['Donor_Name', 'Donor_Email', 'Donor_ContactNumber', 'Donor_ICNumber', 'Donor_DOB', 'Donor_Address1', 'Donor_City', 'Donor_State', 'Donor_PostalCode', 'Donor_Country', 'Donor_ProfilePicture'];
   
    $fields_to_check = array_slice($fields_to_check, 0, 10);

    foreach ($fields_to_check as $field) {
        if (!empty($donor[$field]) && trim($donor[$field]) !== '') { $completed_fields++; }
    }
    return ($completed_fields / $total_fields) * 100;
}

$completion_percentage = calculateProfileCompletion($donor);
include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Love Bridge</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2563eb;
            --dark-blue: #1e40af;
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-red: #fee2e2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --medium-gray: #737373;
            --dark-gray: #262626;
            --text-dark: #171717;
            --border-color: #e5e5e5;
            --primary-green: #16a34a; 
            --success-bg: #dcfce7;
            --success-text: #166534;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        body { background-color: var(--light-gray); color: var(--text-dark); line-height: 1.6; }
        .page-container { padding: 30px; max-width: 1000px; margin: 0 auto; }
        .page-title { text-align: center; margin-bottom: 30px; font-size: 32px; color: var(--dark-red); font-weight: bold; }
        
        .completion-card {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white; border-radius: 12px; padding: 25px;
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.1); margin-bottom: 30px;
        }
        .completion-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .completion-title { font-size: 20px; font-weight: bold; }
        .percentage { font-size: 28px; font-weight: bold; color: white; }
        .progress-bar-bg { width: 100%; height: 12px; background-color: rgba(255, 255, 255, 0.3); border-radius: 6px; overflow: hidden; margin-bottom: 15px; }
        .progress { height: 100%; background-color: white; border-radius: 6px; width: 0%; transition: width 0.5s ease; }
        
        .profile-container { background-color: var(--white); border-radius: 12px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); padding: 30px; border-top: 4px solid var(--primary-red); }
        .profile-header { display: flex; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid var(--light-red); }
        .profile-avatar-container { margin-right: 20px; text-align: center; }
        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-red), var(--dark-red)); display: flex; align-items: center; justify-content: center; font-size: 48px; color: white; font-weight: bold; overflow: hidden; border: 4px solid var(--light-red); margin-bottom: 10px; object-fit: cover; }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .change-picture-text { color: var(--primary-red); font-size: 14px; font-weight: bold; cursor: pointer; text-decoration: none; }
        .file-input { display: none; }
        .profile-info h2 { font-size: 24px; margin-bottom: 5px; color: var(--dark-gray); }
        .profile-info p { color: var(--medium-gray); }
        
        .form-section { margin-bottom: 25px; }
        .form-section h3 { margin-bottom: 15px; color: var(--dark-red); border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; position: relative; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--dark-gray); }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 15px; border: 2px solid var(--border-color); border-radius: 6px; font-size: 16px; transition: all 0.3s ease; }
        .form-group input:focus { border-color: var(--primary-red); outline: none; }
        .form-group input[readonly] { background-color: #e9e9e9; cursor: not-allowed; color: #555; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        
        .phone-group {
            display: flex;
            align-items: stretch;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .phone-group:focus-within { border-color: var(--primary-red); }
        .phone-prefix {
            background-color: #e5e5e5;
            color: var(--dark-gray);
            padding: 0 15px;
            display: flex;
            align-items: center;
            font-weight: 600;
            border-right: 1px solid #d4d4d4;
            user-select: none;
        }
        .phone-group input {
            border: none;
            border-radius: 0;
            box-shadow: none;
            flex: 1;
        }
        .phone-group input:focus { box-shadow: none; }

        .field-error-msg {
            color: var(--primary-red);
            font-size: 0.85rem;
            margin-top: 5px;
            display: none; 
            font-weight: bold;
        }
        .form-group.error input, .phone-group.error {
            border-color: var(--primary-red);
            background-color: #fff5f5;
        }

        .action-buttons { display: flex; gap: 15px; margin-top: 25px; }
        .submit-btn { background: linear-gradient(135deg, #f79c34ff); color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; flex: 1; transition: transform 0.2s; }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3); }
        .delete-btn { background: linear-gradient(135deg, var(--primary-red), var(--dark-red)); color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; flex: 1; transition: transform 0.2s; }
        .delete-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(220,38,38,0.3); }
        
        .change-pass-link { display: block; text-align: center; margin-top: 20px; color: var(--medium-gray); font-weight: bold; font-size: 16px; text-decoration: underline; transition: all 0.3s ease; }
        .change-pass-link:hover { color: var(--dark-blue); transform: scale(1.02); }

        .tax-note { color: #000000; font-size: 12px; font-weight: bold; margin-top: 5px; display: block; }
        
        .success-message { 
            background-color: var(--success-bg); 
            color: var(--success-text); 
            padding: 15px; 
            border-radius: 6px; 
            margin-bottom: 20px; 
            font-weight: bold;
            border: 1px solid #bbf7d0;
        }

        .modal-overlay {
            display: none; 
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.5); 
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            animation: fadeIn 0.3s ease;
        }

        .modal-icon { font-size: 48px; margin-bottom: 15px; }
        .modal-icon.warning { color: var(--primary-red); }
        .modal-icon.success { color: var(--primary-green); }

        .modal-title { font-size: 22px; font-weight: bold; color: var(--dark-gray); margin-bottom: 10px; }
        .modal-text { color: var(--medium-gray); margin-bottom: 25px; font-size: 16px; }

        .modal-buttons { display: flex; gap: 15px; justify-content: center; }

        .btn-cancel { background-color: #e5e5e5; color: var(--text-dark); border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; flex: 1; transition: background 0.2s; }
        .btn-cancel:hover { background-color: #d4d4d4; }

        .btn-confirm-delete { background: linear-gradient(135deg, var(--primary-red), var(--dark-red)); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; flex: 1; transition: transform 0.2s; }
        .btn-confirm-delete:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(220,38,38,0.3); }

        .btn-ok { background: linear-gradient(135deg, var(--primary-green), #14532d); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; transition: transform 0.2s; }
        .btn-ok:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(22,163,74,0.3); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
            .profile-header { flex-direction: column; text-align: center; }
            .profile-avatar-container { margin-right: 0; margin-bottom: 15px; }
            .action-buttons { flex-direction: column; }
            .submit-btn, .delete-btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <h1 class="page-title">My Profile</h1>
        
        <?php if (!empty($update_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle" style="margin-right:8px;"></i> <?php echo $update_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="completion-card">
            <div class="completion-header">
                <div class="completion-title">Profile Completion</div>
                <div class="percentage" id="progress-text"><?php echo round($completion_percentage); ?>%</div>
            </div>
            <div class="progress-bar-bg">
                <div class="progress" id="progress-bar" style="width: <?php echo $completion_percentage; ?>%"></div>
            </div>
            <div class="completion-message">Complete your details to help us serve you better!</div>
        </div>
        
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar-container">
                    <div class="profile-avatar" id="avatar-display">
                        <?php if (!empty($donor['Donor_ProfilePicture'])): ?>
                            <img src="<?php echo htmlspecialchars($donor['Donor_ProfilePicture']); ?>" id="avatar-image">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <a href="#" class="change-picture-text" onclick="document.getElementById('profile_picture').click(); return false;">Change Picture</a>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($donor['Donor_Name']); ?></h2>
                    <p><?php echo htmlspecialchars($donor['Donor_Email']); ?></p>
                </div>
            </div>
            
            <form method="POST" action="profile.php" enctype="multipart/form-data" id="profileForm" novalidate>
                <input type="hidden" name="update_profile" value="1">
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="file-input" onchange="previewImage(event)">
                
                <div class="form-section">
                    <h3>Personal Information</h3>
                    <div class="form-group" id="nameGroup">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Name']); ?>" required>
                        <div class="field-error-msg" id="nameError"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" id="emailGroup">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Email']); ?>" required>
                            <div class="field-error-msg" id="emailError"></div>
                        </div>
                        <div class="form-group" id="contactGroup">
                            <label for="contact">Contact Number</label>
                            <div class="phone-group">
                                <div class="phone-prefix">+60</div>
                                <input type="tel" id="contact" name="contact" class="track-field" 
                                       placeholder="12-3456789"
                                       value="<?php echo htmlspecialchars(ltrim($donor['Donor_ContactNumber'], '0')); ?>" required>
                            </div>
                            <div class="field-error-msg" id="contactError"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" id="icGroup">
                            <label for="icnumber">IC Number (Optional)</label>
                            <input type="text" id="icnumber" name="icnumber" class="track-field" 
                                   value="<?php echo htmlspecialchars($donor['Donor_ICNumber']); ?>" 
                                   placeholder="Example: 000101-01-1234" 
                                   maxlength="14"
                                   inputmode="numeric">
                            <span class="tax-note">* Required For Tax Exemption</span>
                            <div class="field-error-msg" id="icError"></div>
                        </div>
                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="track-field" 
                                   value="<?php echo $donor['Donor_DOB']; ?>"
                                   max="<?php echo $current_date; ?>" 
                                   min="<?php echo $min_date; ?>">
                                   <span class="tax-note">Must be 18 years old and above</span>
                            <div class="field-error-msg" id="dobError"></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Address Information</h3>
                    <div class="form-group">
                        <label for="address1">Address Line 1</label>
                        <input type="text" id="address1" name="address1" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Address1']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="address2">Address Line 2</label>
                        <input type="text" id="address2" name="address2" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Address2']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="address3">Address Line 3</label>
                        <input type="text" id="address3" name="address3" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_Address3']); ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="postalcode">Postal Code</label>
                            <input type="text" id="postalcode" name="postalcode" class="track-field" 
                                   value="<?php echo htmlspecialchars($donor['Donor_PostalCode']); ?>" 
                                   oninput="handlePostcodeInput(this.value)" placeholder="Enter Postcode">
                        </div>
                        <div class="form-group">
                            <label for="state">State (Auto-filled)</label>
                            <input type="text" id="state" name="state" class="track-field" 
                                   value="<?php echo htmlspecialchars($donor['Donor_State']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" class="track-field" value="<?php echo htmlspecialchars($donor['Donor_City']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" class="track-field" 
                                   value="Malaysia" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="submit-btn" id="updateBtn">Update Profile</button>
                    <button type="button" class="delete-btn" onclick="openDeleteModal()">Delete Account</button>
                </div>
                
                <a href="donor_change_password.php" class="change-pass-link">Change Password</a>

            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-icon warning">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h3 class="modal-title">Delete Account?</h3>
            <p class="modal-text">Are you sure you want to delete your account? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button type="button" class="btn-confirm-delete" onclick="confirmDelete()">Yes, Delete</button>
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="successModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="modal-title">Account Deleted</h3>
            <p class="modal-text">Your account has been deleted successfully.</p>
            <div class="modal-buttons">
                <button type="button" class="btn-ok" onclick="handleSuccessRedirect()">OK</button>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
 
        <?php if ($show_login_modal): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelector('.page-container').style.display = 'none';
                Swal.fire({
                    title: 'Login Required',
                    text: "You need to login to make a donation.",
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#e53935', 
                    cancelButtonColor: '#757575',
                    confirmButtonText: 'Login Now',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'donor_login.php'; 
                    } else {
                        window.location.href = 'Homepage.php'; 
                    }
                });
            });
        <?php endif; ?>

 
        function openDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function confirmDelete() {
            const form = document.getElementById('profileForm');
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_account';
            hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            form.submit();
        }

        <?php if ($delete_success): ?>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById('successModal').style.display = 'flex';
            });
        <?php endif; ?>

        function handleSuccessRedirect() {
            window.location.href = 'donor_logout.php';
        }

        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

    
        const nameInput = document.getElementById('name');
        const nameError = document.getElementById('nameError');
        const nameGroup = document.getElementById('nameGroup');

        const emailInput = document.getElementById('email');
        const emailError = document.getElementById('emailError');
        const emailGroup = document.getElementById('emailGroup');

        const contactInput = document.getElementById('contact');
        const contactError = document.getElementById('contactError');
        const contactGroup = document.querySelector('.phone-group');

      
        nameInput.addEventListener('input', function(e) {
            const val = e.target.value;
            // 【修改点 2】JS 验证：只允许字母和空格 (移除了 /)
            const regex = /^[A-Za-z\s]*$/;
            
            if (!regex.test(val)) {
                nameError.innerText = "Name cannot contain numbers or symbols.";
                nameError.style.display = 'block';
                nameGroup.classList.add('error');
            } else if (val.trim() === '') {
              
                nameError.style.display = 'none';
                nameGroup.classList.remove('error');
            } else {
                nameError.style.display = 'none';
                nameGroup.classList.remove('error');
            }
            updateProgress();
        });

      
        emailInput.addEventListener('input', function(e) {
            updateProgress();
         
            if (emailInput.value.trim() !== '') {
                emailError.style.display = 'none';
                emailGroup.classList.remove('error');
            }
        });

     
        contactInput.addEventListener('input', function(e) {
            let val = e.target.value.replace(/\D/g, ''); 
            
           
            if (val.startsWith('0')) {
                val = val.substring(1);
            }

           
            if (val.length > 2) val = val.substring(0, 2) + '-' + val.substring(2);
            if (val.length > 11) val = val.substring(0, 11);
            
            e.target.value = val;
            updateProgress();
            
          
            if (val.length > 0 && val.charAt(0) !== '1') {
                contactError.innerText = "Phone number must start with 1 (e.g. 12-3456789).";
                contactError.style.display = 'block';
                contactGroup.classList.add('error');
            } else {
                contactError.style.display = 'none';
                contactGroup.classList.remove('error');
            }
        });

        
        const icInput = document.getElementById('icnumber');
        const dobInput = document.getElementById('dob');
        const icError = document.getElementById('icError');
        const icGroup = document.getElementById('icGroup');

        icInput.addEventListener('input', function(e) {
            let cursorStart = this.selectionStart;
            let oldVal = this.value;

            let val = this.value.replace(/\D/g, '');
            let newVal = '';
            
            if (val.length > 12) val = val.substring(0, 12);
            
            if (val.length > 8) {
                newVal = val.substring(0, 6) + '-' + val.substring(6, 8) + '-' + val.substring(8);
            } else if (val.length > 6) {
                newVal = val.substring(0, 6) + '-' + val.substring(6);
            } else {
                newVal = val;
            }
            
            this.value = newVal;

            if (cursorStart < oldVal.length) {
                 if(oldVal.slice(0, cursorStart).replace(/\D/g, '').length === newVal.slice(0, cursorStart).replace(/\D/g, '').length) {
                     let oldDashes = (oldVal.slice(0, cursorStart).match(/-/g) || []).length;
                     let newDashes = (newVal.slice(0, cursorStart).match(/-/g) || []).length;
                     if(newDashes > oldDashes) cursorStart++;
                 }
                 this.setSelectionRange(cursorStart, cursorStart);
            }

            handleICInput(newVal); 
            updateProgress();
        });

        function handleICInput(formattedIC) {
            const ic = formattedIC.replace(/[^0-9]/g, '');
            if (ic.length < 12) {
                dobInput.removeAttribute('readonly');
                icError.style.display = 'none';
                icGroup.classList.remove('error');
                return;
            }

            if (ic.length === 12) {
                const yearShort = ic.substring(0, 2);
                const month = ic.substring(2, 4);
                const day = ic.substring(4, 6);
                const stateCode = ic.substring(6, 8);

                if (month > 0 && month <= 12 && day > 0 && day <= 31) {
                    const currentYearShort = new Date().getFullYear() % 100;
                    let fullYear = '';
                    if (parseInt(yearShort) > currentYearShort) { fullYear = '19' + yearShort; } else { fullYear = '20' + yearShort; }
                    
                    const dateString = `${fullYear}-${month}-${day}`;
                    dobInput.value = dateString;
                    dobInput.setAttribute('readonly', true);
                }

                const validStateCodes = ['01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40','41','42','43','44','45','46','47','48','49','50','51','52','53','54','55','56','57','58','59'];

                if (!validStateCodes.includes(stateCode)) {
                    icError.innerText = "Invalid IC Number: State code '" + stateCode + "' does not exist.";
                    icError.style.display = 'block';
                    icGroup.classList.add('error');
                } else {
                    icError.style.display = 'none';
                    icGroup.classList.remove('error');
                }
            }
        }

      
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            let isValid = true;
            
        
            const nameVal = nameInput.value.trim();
            // 【修改点 3】提交验证：只允许字母和空格 (移除了 /)
            const nameRegex = /^[A-Za-z\s]+$/;
            if (nameVal === '') {
                isValid = false;
                nameError.innerText = "Name is required.";
                nameError.style.display = 'block';
                nameGroup.classList.add('error');
            } else if (!nameRegex.test(nameVal)) {
                isValid = false;
                nameError.innerText = "Name cannot contain numbers or symbols.";
                nameError.style.display = 'block';
                nameGroup.classList.add('error');
            } else {
                nameError.style.display = 'none';
                nameGroup.classList.remove('error');
            }

            const emailVal = emailInput.value.trim();
           
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailVal === '') {
                isValid = false;
                emailError.innerText = "Email is required.";
                emailError.style.display = 'block';
                emailGroup.classList.add('error');
            } else if (!emailRegex.test(emailVal)) {
                isValid = false;
                emailError.innerText = "Please enter a valid email address.";
                emailError.style.display = 'block';
                emailGroup.classList.add('error');
            } else {
                emailError.style.display = 'none';
                emailGroup.classList.remove('error');
            }

            
            const contactVal = contactInput.value.replace(/\D/g, ''); 
            if (contactVal.length === 0) {
                 isValid = false;
                 contactError.innerText = "Phone number is required.";
                 contactError.style.display = 'block';
                 contactGroup.classList.add('error');
            } else if (contactVal.charAt(0) !== '1') {
                isValid = false;
                contactError.innerText = "Phone number must start with 1 (e.g. 12-3456789).";
                contactError.style.display = 'block';
                contactGroup.classList.add('error');
            } else if (contactVal.length < 9 || contactVal.length > 10) {
              
                isValid = false;
                contactError.innerText = "Invalid length. Please check your number.";
                contactError.style.display = 'block';
                contactGroup.classList.add('error');
            } else {
                contactError.style.display = 'none';
                contactGroup.classList.remove('error');
            }

           
            const dobError = document.getElementById('dobError'); 
            const dobVal = dobInput.value;
            
            if (dobVal) {
                const selectedDate = new Date(dobVal);
                const today = new Date();
                
                let age = today.getFullYear() - selectedDate.getFullYear();
                const m = today.getMonth() - selectedDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < selectedDate.getDate())) {
                    age--;
                }

                if (age < 18) {
                    isValid = false;
                    dobError.innerText = "Must be 18 years old and above.";
                    dobError.style.display = 'block';
                    dobInput.style.borderColor = "#dc2626";
                } else {
                    dobError.style.display = 'none';
                    dobInput.style.borderColor = ""; 
                }
            }

           
            if (icInput.value.trim() !== '') {
                const cleanIC = icInput.value.replace(/[^0-9]/g, '');
                if (cleanIC.length !== 12) {
                    isValid = false;
                    icError.innerText = "IC Number must be exactly 12 digits.";
                    icError.style.display = 'block'; icGroup.classList.add('error');
                } else {
                    
                     const month = parseInt(cleanIC.substring(2, 4), 10);
                     const day = parseInt(cleanIC.substring(4, 6), 10);
                     const stateCode = cleanIC.substring(6, 8);
                     const validStateCodes = ['01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16'];
                     
                     if (month < 1 || month > 12 || day < 1 || day > 31) {
                         isValid = false;
                         icError.innerText = "Invalid IC Number: Invalid Date.";
                         icError.style.display = 'block'; icGroup.classList.add('error');
                     } else if (!validStateCodes.includes(stateCode) && parseInt(stateCode) > 59) {
                        
                         isValid = false;
                         icError.innerText = "Invalid IC Number: Invalid State Code.";
                         icError.style.display = 'block'; icGroup.classList.add('error');
                     }
                }
            }

            if (!isValid) {
                e.preventDefault();
                const errorEl = document.querySelector('.field-error-msg[style*="block"]');
                if(errorEl) { errorEl.parentElement.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            }
        });

        
        function handlePostcodeInput(postcode) {
            const stateInput = document.getElementById('state');
            const pc = parseInt(postcode, 10);

            if (postcode.length >= 2 && !isNaN(pc)) {
                let state = "";
                if (pc >= 1000 && pc <= 2999) { state = "Perlis"; }
                else if (pc >= 5000 && pc <= 9999) { state = "Kedah"; }
                else if (pc >= 10000 && pc <= 14999) { state = "Penang"; }
                else if (pc >= 30000 && pc <= 36999) { state = "Perak"; }
                else if (pc >= 39000 && pc <= 39999) { state = "Cameron Highlands (Pahang)"; } 
                else if (pc >= 40000 && pc <= 48999) { state = "Selangor"; }
                else if (pc >= 60000 && pc <= 68999) { state = "Selangor"; } 
                else if (pc >= 50000 && pc <= 60000) { state = "Kuala Lumpur"; }
                else if (pc >= 62000 && pc <= 62999) { state = "Putrajaya"; }
                else if (pc >= 70000 && pc <= 73999) { state = "Negeri Sembilan"; }
                else if (pc >= 75000 && pc <= 78999) { state = "Melaka"; }
                else if (pc >= 80000 && pc <= 86999) { state = "Johor"; }
                else if (pc >= 25000 && pc <= 28999) { state = "Pahang"; }
                else if (pc >= 20000 && pc <= 24999) { state = "Terengganu"; }
                else if (pc >= 15000 && pc <= 19999) { state = "Kelantan"; }
                else if (pc >= 87000 && pc <= 87999) { state = "Labuan"; }
                else if (pc >= 88000 && pc <= 91999) { state = "Sabah"; }
                else if (pc >= 93000 && pc <= 98999) { state = "Sarawak"; }
                
                if (state !== "") { stateInput.value = state; }
            } else if (postcode.length === 0) {
                stateInput.value = "";
            }
            updateProgress();
        }

        
        function previewImage(event) {
            const input = event.target;
            const avatarDisplay = document.getElementById('avatar-display');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarDisplay.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">`;
                    updateProgress(); 
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        
        function updateProgress() {
            
            const fields = ['name', 'email', 'contact', 'icnumber', 'dob', 'address1', 'city', 'state', 'postalcode', 'country'];
            let filledCount = 0;
            const totalFields = fields.length + 1; // +1 for picture
            fields.forEach(id => {
                const el = document.getElementById(id);
                if (el && el.value.trim() !== '') { filledCount++; }
            });
            const avatarDisplay = document.getElementById('avatar-display');
            const fileInput = document.getElementById('profile_picture');
            const hasImgTag = avatarDisplay.querySelector('img') !== null;
            const hasFile = fileInput.files.length > 0;
            if (hasImgTag || hasFile) { filledCount++; }
            const percentage = Math.round((filledCount / totalFields) * 100);
            document.getElementById('progress-bar').style.width = percentage + '%';
            document.getElementById('progress-text').innerText = percentage + '%';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.track-field');
            inputs.forEach(input => {
                input.addEventListener('input', updateProgress);
                input.addEventListener('change', updateProgress);
            });
            const icInput = document.getElementById('icnumber');
            if (icInput.value.trim() !== '') { 
                let val = icInput.value.replace(/\D/g, '');
                if (val.length > 8) {
                    icInput.value = val.substring(0, 6) + '-' + val.substring(6, 8) + '-' + val.substring(8);
                } else if (val.length > 6) {
                    icInput.value = val.substring(0, 6) + '-' + val.substring(6);
                }
                handleICInput(icInput.value); 
            }
            updateProgress();
        });
    </script>
</body>
</html>