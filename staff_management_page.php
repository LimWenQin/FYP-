<?php
// staff_management_page.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// --- FILE UPLOAD HELPER FUNCTION ---
function handleProfileUpload($file) {
    if (isset($file) && $file['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowedTypes)) {
            $uploadDir = 'uploads/staff_profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'staff_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return $uploadPath;
            }
        }
    }
    return null;
}

// --- 1. Handle Add Staff ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    // Get form data
    $fullName = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    $contactRaw = $_POST['contact'];
    $contact = "+601" . $contactRaw; 

    $icNumber = mysqli_real_escape_string($conn, $_POST['ic_number']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $password = $_POST['password']; 
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    
    $adminId = $_SESSION['admin_id'];

    // Handle File Upload
    $profilePicturePath = null;
    if (isset($_FILES['profile_picture'])) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) {
            $profilePicturePath = $uploadedPath;
        }
    }

    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.(com|net|org|edu|gov|my)$/i', $email)) {
        $errorMessage = "Invalid email format.";
    }
    elseif (!preg_match('/^[a-zA-Z\s]+$/', $fullName)) {
        $errorMessage = "Name can only contain letters and spaces";
    }
    elseif (!preg_match('/^\+601[0-9]-[0-9]{7,10}$/', $contact)) {
        $errorMessage = "Invalid phone number format.";
    }
    elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>])[A-Za-z\d!@#$%^&*()\-_=+{};:,<.>]{8,15}$/', $password)) {
        $errorMessage = "Password does not meet requirements.";
    }
    else {
        // Age Check
        if (!empty($dob)) {
            $birthDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            if ($age < 18) {
                $errorMessage = "Staff must be at least 18 years old";
            }
        }

        if (!isset($errorMessage)) {
            $checkEmailSql = "SELECT Staff_ID FROM staff WHERE Staff_Email = '$email'";
            $emailResult = $conn->query($checkEmailSql);
            
            if ($emailResult && $emailResult->num_rows > 0) {
                $errorMessage = "Email already exists in the system";
            } else {
                $dbProfilePic = $profilePicturePath ? "'$profilePicturePath'" : "NULL";

                $sql = "INSERT INTO staff (
                    Staff_FullName, Staff_ContactNumber, Staff_ICNumber, 
                    Staff_Email, Staff_Password, Staff_DOB, 
                    Staff_Address1, Staff_Address2, Staff_Address3, 
                    Staff_City, Staff_State, Staff_PostalCode, Staff_Country,
                    Staff_Comment, Staff_Role, Staff_Status, Staff_ProfilePicture, Admin_ID
                ) VALUES (
                    '$fullName', '$contact', '$icNumber', 
                    '$email', '$password', '$dob',
                    '$address1', '$address2', '$address3',
                    '$city', '$state', '$postalCode', '$country',
                    '$comment', '$role', '$status', $dbProfilePic, '$adminId'
                )";
                
                if ($conn->query($sql)) {
                    $successMessage = "Staff added successfully!";
                } else {
                    $errorMessage = "Error adding staff: " . $conn->error;
                }
            }
        }
    }
    
    if (!empty($successMessage)) {
        header("Location: staff_management_page.php?success=" . urlencode($successMessage));
        exit();
    } elseif (!empty($errorMessage)) {
        header("Location: staff_management_page.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// --- 2. Handle Update Staff ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_staff'])) {
    $staffId = mysqli_real_escape_string($conn, $_POST['staff_id']);
    $fullName = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    $contactRaw = $_POST['contact'];
    $contact = "+601" . $contactRaw;
    
    $icNumber = mysqli_real_escape_string($conn, $_POST['ic_number']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    // Handle Image Upload Update
    $imageUpdateSQL = "";
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) {
             // Optional: Delete old image
             $oldPicQ = $conn->query("SELECT Staff_ProfilePicture FROM staff WHERE Staff_ID = $staffId");
             if ($oldRow = $oldPicQ->fetch_assoc()) {
                 if (!empty($oldRow['Staff_ProfilePicture']) && file_exists($oldRow['Staff_ProfilePicture'])) {
                     unlink($oldRow['Staff_ProfilePicture']);
                 }
             }
             $imageUpdateSQL = ", Staff_ProfilePicture = '$uploadedPath'";
        }
    }

    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format.";
    } else {
        $sql = "UPDATE staff SET 
                Staff_FullName = '$fullName', 
                Staff_ContactNumber = '$contact', 
                Staff_ICNumber = '$icNumber', 
                Staff_Email = '$email',
                Staff_DOB = '$dob',
                Staff_Address1 = '$address1',
                Staff_Address2 = '$address2',
                Staff_Address3 = '$address3',
                Staff_City = '$city',
                Staff_State = '$state',
                Staff_PostalCode = '$postalCode',
                Staff_Country = '$country',
                Staff_Role = '$role',
                Staff_Status = '$status',
                Staff_Comment = '$comment'
                $imageUpdateSQL
                WHERE Staff_ID = $staffId";
        
        if ($conn->query($sql)) {
            $successMessage = "Staff updated successfully!";
        } else {
            $errorMessage = "Error updating staff: " . $conn->error;
        }
    }
    
    if (!empty($successMessage)) {
        header("Location: staff_management_page.php?success=" . urlencode($successMessage));
        exit();
    } elseif (!empty($errorMessage)) {
        header("Location: staff_management_page.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// --- 3. Handle Delete Staff ---
if (isset($_GET['delete_id'])) {
    $deleteId = mysqli_real_escape_string($conn, $_GET['delete_id']);
    $deleteSql = "DELETE FROM staff WHERE Staff_ID = $deleteId";
    
    if ($conn->query($deleteSql)) {
        $successMessage = "Staff deleted successfully!";
        header("Location: staff_management_page.php?success=" . urlencode($successMessage));
        exit();
    } else {
        $errorMessage = "Error deleting staff: " . $conn->error;
        header("Location: staff_management_page.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// Get Admin Info
$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];
$adminPosition = "System Administrator";

// Get Admin Avatar
$adminProfilePicture = null;
$sql = "SELECT Admin_ProfilePicture FROM admin WHERE Admin_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $adminProfilePicture = $row['Admin_ProfilePicture'];
}
$stmt->close();

// --- PAGINATION & SEARCH & FILTER LOGIC (UPDATED) ---
$results_per_page = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$search = "";
$filterType = "";
$filterValue = "";
$roleFilter = "";
$statusFilter = "";
$conditions = [];

// 1. Search Keyword
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $conditions[] = "(Staff_FullName LIKE '%$search%' OR 
                      Staff_Email LIKE '%$search%' OR 
                      Staff_ICNumber LIKE '%$search%')";
}

// 2. Dynamic Filters
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    
    // Role Filter
    if ($filterType == 'role' && isset($_GET['filter_val_role']) && !empty($_GET['filter_val_role'])) {
        $roleFilter = $conn->real_escape_string($_GET['filter_val_role']);
        $conditions[] = "Staff_Role = '$roleFilter'";
        $filterValue = $roleFilter;
    } 
    // Status Filter
    elseif ($filterType == 'status' && isset($_GET['filter_val_status']) && !empty($_GET['filter_val_status'])) {
        $statusFilter = $conn->real_escape_string($_GET['filter_val_status']);
        $conditions[] = "Staff_Status = '$statusFilter'";
        $filterValue = $statusFilter;
    }
}

// Build WHERE clause
$whereClause = "";
if (count($conditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Total Count
$count_sql = "SELECT COUNT(*) as total FROM staff $whereClause";
$count_result = $conn->query($count_sql);
$total_records = 0;
if ($count_result && $count_result->num_rows > 0) {
    $row = $count_result->fetch_assoc();
    $total_records = $row['total'];
}

// Total Pages
$total_pages = ceil($total_records / $results_per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $start_from = ($page - 1) * $results_per_page;
}

// Data Fetching
$sql = "SELECT * FROM staff $whereClause ORDER BY Staff_JoinDate DESC, Staff_ID DESC LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);

$staffMembers = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $staffMembers[] = $row;
    }
}

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// Stats
function getTotalStaffStats($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM staff");
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
}
function getActiveStaffStats($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM staff WHERE Staff_Status = 'Active'");
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
}

$totalStaffStats = getTotalStaffStats($conn);
$activeStaffStats = getActiveStaffStats($conn);
$inactiveStaffStats = $totalStaffStats - $activeStaffStats;

$conn->close();

$malaysiaStates = [
    'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 
    'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Base Styles */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 28px; font-weight: 600; margin-bottom: 5px; }
        .stat-desc { font-size: 12px; color: #28a745; display: flex; align-items: center; gap: 5px; font-weight: 500;}
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(23, 162, 184, 0.2); color: var(--info); }

        .staff-management { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-header h2 { font-size: 18px; font-weight: 600; color: #333; }
        .header-buttons { display: flex; gap: 10px; }
        .btn { padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; color: white; transition: 0.3s; font-size: 14px; }
        .btn-primary { background: #F28585; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }

        /* --- NEW SEARCH & FILTER STYLES (MATCHING DONOR PAGE) --- */
        .staff-search { 
            margin-bottom: 25px; 
            display: flex; 
            gap: 10px; 
            align-items: center; 
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        
        .filter-group { display: flex; align-items: center; gap: 8px; }
        
        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
            color: #555;
            outline: none;
            cursor: pointer;
            font-size: 14px;
            min-width: 140px;
        }
        .filter-select:focus { border-color: #F28585; }

        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .search-input { 
            flex: 1; 
            padding: 10px 15px; 
            border: 1px solid #ddd; 
            border-radius: 5px;
            outline: none; 
            font-size: 14px; 
            background: white;
        }
        .search-input:focus { border-color: #F28585; }
        /* ----------------------------------------------------- */

        /* Table Styles */
        .staff-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .staff-table th, .staff-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; }
        .staff-table th { font-weight: 600; color: #666; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #eee; }
        .staff-info { display: flex; align-items: center; }
        .staff-avatar { width: 40px; height: 40px; border-radius: 50%; margin-right: 15px; background: #ffe5e5; color: #F28585; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; font-size: 16px; object-fit: cover; }
        .staff-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        /* ROLE & STATUS COLUMN STYLES */
        .role-status-cell { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
        .role-text { font-weight: 500; color: #333; font-size: 14px; }
        .status-badge { padding: 4px 14px; border-radius: 50px; font-size: 12px; font-weight: 600; letter-spacing: 0.3px; }
        .status-active { background-color: #e6f4ea; color: #1e7e34; }
        .status-inactive { background-color: #fce8e6; color: #c5221f; }

        /* Action Button Styles - Circle */
        .action-cell { text-align: center; }
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { width: 36px; height: 36px; border-radius: 50%; background: white; border: 1px solid #eee; box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: #777; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; }
        .menu-btn:hover { background: #f8f9fa; color: var(--primary); border-color: #ddd; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

        /* Modal & Form */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--gray-light); }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray); transition: 0.2s; }
        .close-btn:hover { color: var(--danger); }
        .modal-body { padding: 20px; }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark); font-size: 14px;}
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-input:read-only, .form-textarea:read-only { background-color: #f8f9fa; color: #555; cursor: default; }
        
        .required { color: red; margin-left: 3px; font-weight: bold; }
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 4px; display: block; font-style: italic; }
        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }
        
        .password-requirements { margin-top: 8px; font-size: 12px; }
        .requirement-item { display: flex; align-items: center; margin-bottom: 3px; color: #888; }
        .requirement-item.valid { color: var(--success); }
        .requirement-item.invalid { color: var(--gray); }
        .requirement-item i { width: 15px; text-align: center; margin-right: 5px; }

        .password-input-group { display: flex; width: 100%; }
        .password-input-container { position: relative; flex: 1; display: flex;}
        .password-input-container input { flex: 1; border-radius: 5px 0 0 5px; border-right: none; }
        .password-input-container button.toggle-password { position: static; border: 1px solid var(--gray-light); border-left: none; border-radius: 0; background: white; padding: 0 10px; transform: none; cursor: pointer; color: var(--gray); }
        .btn-small { padding: 0 12px; border-radius: 0 5px 5px 0; border: 1px solid var(--gray-light); border-left: none; background: #f8f9fa; cursor: pointer; font-size: 12px; font-weight: 500; color: var(--primary); transition: 0.2s; }
        .btn-small:hover { background: #e9ecef; }
        .confirm-check { position: absolute; right: 50px; top: 50%; transform: translateY(-50%); color: var(--success); font-size: 14px; display: none; z-index: 5; }

        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); }
        .phone-input { border-radius: 0 5px 5px 0 !important; }
        
        .file-upload { text-align: center; margin-bottom: 20px; }
        .profile-picture-preview { width: 120px; height: 120px; border-radius: 50%; border: 4px solid #f8f9fa; margin: 0 auto 15px; background: #eee; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .profile-picture-preview img { width: 100%; height: 100%; object-fit: cover; }
        .default-avatar-icon { font-size: 48px; color: #ccc; }
        .file-upload-label { display: inline-block; padding: 8px 15px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 5px; cursor: pointer; font-size: 13px; transition: all 0.3s; }
        .file-upload-label:hover { border-color: var(--primary); background: #fff5f5; color: var(--primary); }
        .file-upload input[type="file"] { display: none; }
        .file-info { display: none; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; background: #f1f1f1; padding: 5px 10px; border-radius: 5px; }
        .file-info.active { display: inline-flex; }
        .file-name { font-size: 12px; color: #555; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-remove { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 14px; padding: 0 5px; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; max-width: 400px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }

        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 160px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: var(--primary); }
        .text-delete { color: var(--danger) !important; border-top: 1px solid #eee; }
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; display: inline-block; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; border-color: #eee; }

        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: 1fr 1fr; }
            .form-row { flex-direction: column; gap: 0; }
            .staff-search { flex-direction: column; align-items: stretch; }
            .filter-select, .search-input { width: 100%; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['success'])): ?>
        <div class="floating-alert floating-alert-success" id="floatingSuccess">
            <i class="fas fa-check-circle"></i>
            <div><?php echo htmlspecialchars($_GET['success']); ?></div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="floating-alert floating-alert-danger" id="floatingError">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php echo htmlspecialchars($_GET['error']); ?></div>
        </div>
    <?php endif; ?>

    <div class="sidebar collapsed" id="sidebar">
        <div class="sidebar-menu">
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="admin_donor_page.php"><i class="fas fa-users"></i> <span>Donor Management</span></a></li>
                <li><a href="staff_management_page.php" class="active"><i class="fas fa-user-tie"></i> <span>Staff Management</span></a></li>
                <li><a href="admin_management_page.php"><i class="fas fa-user-shield"></i> <span>Admin Management</span></a></li>
                <li><a href="branch_management_page.php"><i class="fas fa-map-marker-alt"></i> <span>Branch Management</span></a></li>
                <li><a href="activity_management.php"><i class="fas fa-calendar-alt"></i> <span>Activity Management</span></a></li>
                <li><a href="payment_management.php"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
                <li><a href="reward_item_management.php"><i class="fas fa-gift"></i> <span>Reward Items</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="top-nav">
            <div class="nav-left">
                <div class="logo"><a href="admin_dashboard.php"><img src="logo.jpg" alt="Logo"><h1>DonationMS</h1></a></div>
            </div>
            <div class="nav-right">
                <div class="user-profile" id="userProfileDropdown">
                    <div class="user-profile-with-avatar">
                        <div class="user-avatar"><?php if (!empty($adminProfilePicture)): ?><img src="<?php echo htmlspecialchars($adminProfilePicture); ?>" alt="Profile Picture"><?php else: ?><?php echo substr($adminName, 0, 1); ?><?php endif; ?></div>
                        <div class="user-details"><div class="user-name"><?php echo htmlspecialchars($adminName); ?></div><div class="user-role"><?php echo htmlspecialchars($adminPosition); ?></div></div>
                        <i class="fas fa-chevron-down" style="margin-left: 10px; font-size: 12px;"></i>
                    </div>
                    <div class="user-profile-dropdown" id="userProfileMenu">
                        <a href="admin_profile.php"><i class="fas fa-user"></i> View Profile</a>
                        <div class="divider"></div>
                        <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-section"><h1>Staff Management</h1><p>Manage your staff members, roles, and permissions.</p></div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info"><h3>ACTIVE STAFF</h3><h2><?php echo $activeStaffStats; ?></h2><p class="stat-desc"><i class="fas fa-user-check"></i> Currently working</p></div>
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>INACTIVE STAFF</h3><h2><?php echo $inactiveStaffStats; ?></h2><p class="stat-desc"><i class="fas fa-user-slash"></i> Not active</p></div>
                    <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>TOTAL STAFF</h3><h2><?php echo $totalStaffStats; ?></h2><p class="stat-desc"><i class="fas fa-users"></i> All staff members</p></div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
            </div>

            <div class="staff-management">
                <div class="section-header"><h2>Staff List</h2><div class="header-buttons"><button class="btn btn-primary" onclick="openAddStaffModal()"><i class="fas fa-plus"></i> Add New Staff</button><button class="btn btn-success"><i class="fas fa-download"></i> Export Data</button></div></div>

                <form class="staff-search" method="GET" action="staff_management_page.php">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="role" <?php if($filterType == 'role') echo 'selected'; ?>>Role</option>
                            <option value="status" <?php if($filterType == 'status') echo 'selected'; ?>>Status</option>
                        </select>
                    </div>

                    <div id="filter_role_container" class="secondary-filter">
                        <select name="filter_val_role" class="filter-select">
                            <option value="">Select Role...</option>
                            <option value="Manager" <?php if($roleFilter == 'Manager') echo 'selected'; ?>>Manager</option>
                            <option value="Accountant" <?php if($roleFilter == 'Accountant') echo 'selected'; ?>>Accountant</option>
                            <option value="Driver" <?php if($roleFilter == 'Driver') echo 'selected'; ?>>Driver</option>
                            <option value="Coordinator" <?php if($roleFilter == 'Coordinator') echo 'selected'; ?>>Coordinator</option>
                            <option value="Volunteer" <?php if($roleFilter == 'Volunteer') echo 'selected'; ?>>Volunteer</option>
                            <option value="Staff" <?php if($roleFilter == 'Staff') echo 'selected'; ?>>Staff</option>
                        </select>
                    </div>

                    <div id="filter_status_container" class="secondary-filter">
                        <select name="filter_val_status" class="filter-select">
                            <option value="">Select Status...</option>
                            <option value="Active" <?php if($statusFilter == 'Active') echo 'selected'; ?>>Active</option>
                            <option value="Inactive" <?php if($statusFilter == 'Inactive') echo 'selected'; ?>>Inactive</option>
                        </select>
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search staff by name, ID or email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    
                    <?php if(!empty($filterType) || !empty($search)): ?>
                        <a href="staff_management_page.php" class="btn btn-danger" style="padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
                <table class="staff-table">
                    <thead><tr><th>NAME</th><th>CONTACT INFO</th><th>IC / DOB</th><th>ROLE & STATUS</th><th style="text-align: center;">ACTIONS</th></tr></thead>
                    <tbody>
                        <?php if (count($staffMembers) > 0): foreach($staffMembers as $staff): ?>
                        <tr>
                            <td><div class="staff-info"><div class="staff-avatar"><?php if (!empty($staff['Staff_ProfilePicture']) && file_exists($staff['Staff_ProfilePicture'])): ?><img src="<?php echo htmlspecialchars($staff['Staff_ProfilePicture']); ?>" alt="Profile"><?php else: ?><?php echo substr($staff['Staff_Name'], 0, 1); ?><?php endif; ?></div><div><h4 style="margin:0; font-size:14px;"><?php echo htmlspecialchars($staff['Staff_Name']); ?></h4><p style="font-size:12px;color:#888; margin:2px 0 0 0;">ID: #<?php echo str_pad($staff['Staff_ID'], 4, '0', STR_PAD_LEFT); ?></p></div></div></td>
                            <td><div style="font-size:13px; color:#555;"><div style="margin-bottom:3px;"><i class="fas fa-envelope" style="width:15px;color:#999;"></i> <?php echo htmlspecialchars($staff['Staff_Email']); ?></div><div><i class="fas fa-phone" style="width:15px;color:#999;"></i> <?php echo htmlspecialchars($staff['Staff_ContactNumber']); ?></div></div></td>
                            <td><div style="font-size:13px; color:#555;"><div style="margin-bottom:3px;">IC: <?php echo htmlspecialchars($staff['Staff_ICNumber']); ?></div><div>DOB: <?php echo date('Y-m-d', strtotime($staff['Staff_DOB'])); ?></div></div></td>
                            <td>
                                <div class="role-status-cell">
                                    <span class="role-text"><?php echo htmlspecialchars($staff['Staff_Role']); ?></span>
                                    <span class="status-badge <?php echo ($staff['Staff_Status'] == 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo htmlspecialchars($staff['Staff_Status']); ?>
                                    </span>
                                </div>
                            </td>
                            <td><div class="action-cell"><div class="action-menu"><button class="menu-btn" onclick="toggleMenu(event, <?php echo $staff['Staff_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button><div id="menu-<?php echo $staff['Staff_ID']; ?>" class="dropdown-content"><div onclick="openViewStaffModal(<?php echo htmlspecialchars(json_encode($staff)); ?>)"><i class="fas fa-eye"></i> View Details</div><div onclick='openEditStaffModal(<?php echo json_encode($staff); ?>)'><i class="fas fa-edit"></i> Edit Details</div><a href="javascript:confirmDelete(<?php echo $staff['Staff_ID']; ?>)" class="text-delete"><i class="fas fa-trash"></i> Delete Staff</a></div></div></div></td>
                        </tr>
                        <?php endforeach; else: ?><tr><td colspan="5" style="text-align:center; padding:20px; color:#888;">No staff members found matching your search.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        // Build pagination URL parameters
                        $queryParams = [];
                        if(!empty($search)) $queryParams['search'] = $search;
                        if(!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if($filterType == 'role' && !empty($roleFilter)) $queryParams['filter_val_role'] = $roleFilter;
                            if($filterType == 'status' && !empty($statusFilter)) $queryParams['filter_val_status'] = $statusFilter;
                        }
                        
                        $queryString = http_build_query($queryParams);
                        $paginationUrl = !empty($queryString) ? '&' . $queryString : '';
                        
                        if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1 . $paginationUrl; ?>" class="pagination-btn">Previous</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Previous</span>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++) { 
                            if ($i == $page) echo '<span class="pagination-btn active">' . $i . '</span>'; 
                            else echo '<a href="?page=' . $i . $paginationUrl . '" class="pagination-btn">' . $i . '</a>'; 
                        } ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1 . $paginationUrl; ?>" class="pagination-btn">Next</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="viewStaffModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Staff Details</h2><button class="close-btn" onclick="closeModal('viewStaffModal')">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Full Name</label><input type="text" id="view_fullname" class="form-input" readonly></div>
                <div class="form-row"><div class="form-group"><label class="form-label">Email</label><input type="text" id="view_email" class="form-input" readonly></div><div class="form-group"><label class="form-label">Contact</label><input type="text" id="view_contact" class="form-input" readonly></div></div>
                <div class="form-row"><div class="form-group"><label class="form-label">IC Number</label><input type="text" id="view_ic" class="form-input" readonly></div><div class="form-group"><label class="form-label">DOB</label><input type="text" id="view_dob" class="form-input" readonly></div></div>
                <div class="form-group"><label class="form-label">Address</label><textarea id="view_address" class="form-textarea" readonly rows="3"></textarea></div>
                <div class="form-row"><div class="form-group"><label class="form-label">Role</label><input type="text" id="view_role" class="form-input" readonly></div><div class="form-group"><label class="form-label">Status</label><input type="text" id="view_status" class="form-input" readonly></div></div>
            </div>
        </div>
    </div>

    <div class="modal" id="addStaffModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Staff</h2><button class="close-btn" onclick="closeAddStaffModal()">&times;</button></div>
            <div class="modal-body">
                <form id="addStaffForm" action="staff_management_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <input type="hidden" name="add_staff" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Profile Picture</label>
                        <div class="profile-picture-preview" id="add-preview-container"><div class="default-avatar-icon"><i class="fas fa-user"></i></div></div>
                        <div class="file-upload">
                            <label for="add_profile_picture" class="file-upload-label" id="addFileUploadLabel"><i class="fas fa-upload"></i> Choose Profile Picture</label>
                            <input type="file" id="add_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'add-preview-container', 'add-file-info', 'add-file-name')">
                            <div id="add-file-info" class="file-info"><span id="add-file-name" class="file-name"></span><button type="button" class="file-remove" onclick="removeImage('add_profile_picture', 'add-preview-container', 'add-file-info')"><i class="fas fa-times"></i></button></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-input" required oninput="validateName(this)" placeholder="e.g. John Doe">
                        <span class="form-guide">Only English letters and spaces are allowed.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-input" required onblur="validateEmail('email', 'emailError')" placeholder="e.g. user@example.com">
                            <span class="form-guide">Must include '@' and end with .com, .net, .org, etc.</span>
                            <div id="emailError" class="error-message">Invalid email format</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+601</span>
                                <input type="text" id="contact" name="contact" class="form-input phone-input" required placeholder="X-XXXXXXX" maxlength="11">
                            </div>
                            <span class="form-guide">(e.g., +6012-3456789)</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number <span class="required">*</span></label>
                            <input type="text" id="ic_number" name="ic_number" class="form-input" placeholder="XXXXXX-XX-XXXX" maxlength="14" required oninput="formatICNumber('ic_number')">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth <span class="required">*</span></label>
                            <input type="date" id="dob" name="dob" class="form-input" required onchange="validateAge('dob', 'ageError')">
                            <div id="ageError" class="error-message">Must be at least 18 years old</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address Line 1 <span class="required">*</span></label>
                        <input type="text" name="address1" class="form-input" required placeholder="e.g. No. 123, Jalan Example">
                        <span class="form-guide" style="font-style: italic;">House number, street name.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 2 <span class="required">*</span></label>
                        <input type="text" name="address2" class="form-input" required placeholder="e.g. Taman Sri">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" class="form-input" placeholder="Address Line 3 (Optional)">
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">City <span class="required">*</span></label><input type="text" name="city" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">State <span class="required">*</span></label><select name="state" class="form-select" required><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Postal Code <span class="required">*</span></label><input type="text" name="postal_code" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Country</label><input type="text" name="country" class="form-input" value="Malaysia" readonly></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Role <span class="required">*</span></label><select id="role" name="role" class="form-select" required><option value="Manager">Manager</option><option value="Accountant">Accountant</option><option value="Driver">Driver</option><option value="Coordinator">Coordinator</option><option value="Volunteer">Volunteer</option><option value="Staff" selected>Staff</option></select></div>
                        <div class="form-group"><label class="form-label">Status <span class="required">*</span></label><select id="status" name="status" class="form-select" required><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                    </div>

                    <div class="form-group"><label class="form-label">Comments</label><textarea name="comment" class="form-textarea" rows="2"></textarea></div>

                    <div class="form-group">
                        <label class="form-label">Password <span class="required">*</span></label>
                        <div class="password-input-group">
                            <div class="password-input-container">
                                <input type="password" id="password" name="password" class="form-input" required oninput="validatePasswordRequirements()">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')"><i class="fas fa-eye"></i></button>
                            </div>
                            <button type="button" class="btn-small" onclick="generateStrongPassword('password', 'confirm_password')">Auto Generate</button>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement-item invalid" id="lengthReq"><i class="fas fa-times"></i> Must be 8-15 characters long</div>
                            <div class="requirement-item invalid" id="uppercaseReq"><i class="fas fa-times"></i> Must contain at least one Uppercase letter</div>
                            <div class="requirement-item invalid" id="lowercaseReq"><i class="fas fa-times"></i> Must contain at least one Lowercase letter</div>
                            <div class="requirement-item invalid" id="numberReq"><i class="fas fa-times"></i> Must contain at least one Number</div>
                            <div class="requirement-item invalid" id="specialReq"><i class="fas fa-times"></i> Must contain at least one Special character (e.g. !@#)</div>
                        </div>
                        <div id="passwordError" class="error-message">Password does not meet requirements</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password <span class="required">*</span></label>
                        <div class="password-input-container">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required oninput="validateMatch()">
                            <i id="password-match-icon" class="fas fa-check-circle confirm-check"></i>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')"><i class="fas fa-eye"></i></button>
                        </div>
                        <div id="confirmPasswordError" class="error-message">Passwords do not match</div>
                    </div>
                    
                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Staff</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="editStaffModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Staff</h2><button class="close-btn" onclick="closeEditStaffModal()">&times;</button></div>
            <div class="modal-body">
                <form id="editStaffForm" action="staff_management_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateEditForm()">
                    <input type="hidden" name="update_staff" value="1">
                    <input type="hidden" id="edit_staff_id" name="staff_id">
                    
                    <div class="form-group">
                        <label class="form-label">Profile Picture</label>
                        <div class="profile-picture-preview" id="edit-preview-container"><div class="default-avatar-icon"><i class="fas fa-user"></i></div></div>
                        <div class="file-upload">
                            <label for="edit_profile_picture" class="file-upload-label" id="editFileUploadLabel"><i class="fas fa-upload"></i> Change Profile Picture</label>
                            <input type="file" id="edit_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'edit-preview-container', 'edit-file-info', 'edit-file-name')">
                            <div id="edit-file-info" class="file-info"><span id="edit-file-name" class="file-name"></span><button type="button" class="file-remove" id="edit-file-remove-btn"><i class="fas fa-times"></i></button></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" id="edit_fullname" name="full_name" class="form-input" required oninput="validateName(this)">
                        <span class="form-guide">Only English letters and spaces are allowed.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="edit_email" name="email" class="form-input" required onblur="validateEmail('edit_email', 'editEmailError')">
                            <span class="form-guide">Must include '@' and end with .com, .net, etc.</span>
                            <div id="editEmailError" class="error-message">Invalid email</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+601</span>
                                <input type="text" id="edit_contact" name="contact" class="form-input phone-input" required maxlength="11">
                            </div>
                            <span class="form-guide">(e.g., +6012-3456789)</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">IC Number</label><input type="text" id="edit_ic_number" name="ic_number" class="form-input" maxlength="14" oninput="formatICNumber('edit_ic_number')"></div>
                        <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" id="edit_dob" name="dob" class="form-input" onchange="validateAge('edit_dob', 'editAgeError')"><div id="editAgeError" class="error-message">Must be at least 18 years old</div></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" id="edit_address1" name="address1" class="form-input" placeholder="Line 1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" id="edit_address2" name="address2" class="form-input" placeholder="Line 2">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" id="edit_address3" name="address3" class="form-input" placeholder="Line 3">
                    </div>

                    <div class="form-row"><div class="form-group"><label class="form-label">City</label><input type="text" id="edit_city" name="city" class="form-input"></div><div class="form-group"><label class="form-label">State</label><select id="edit_state" name="state" class="form-select"><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Postal Code</label><input type="text" id="edit_postal_code" name="postal_code" class="form-input"></div><div class="form-group"><label class="form-label">Country</label><input type="text" id="edit_country" name="country" class="form-input" value="Malaysia" readonly></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Role</label><select id="edit_role" name="role" class="form-select"><option value="Manager">Manager</option><option value="Accountant">Accountant</option><option value="Driver">Driver</option><option value="Coordinator">Coordinator</option><option value="Volunteer">Volunteer</option><option value="Staff">Staff</option></select></div><div class="form-group"><label class="form-label">Status</label><select id="edit_status" name="status" class="form-select"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div></div>
                    <div class="form-group"><label class="form-label">Comments</label><textarea id="edit_comment" name="comment" class="form-textarea" rows="2"></textarea></div>
                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Staff</button></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // --- NEW DYNAMIC FILTER SCRIPT ---
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            
            // Hide all secondary filters first
            document.querySelectorAll('.secondary-filter').forEach(el => {
                el.classList.remove('active');
                el.querySelector('select').disabled = true; // Disable inputs to clean URL
            });

            // Show specific one based on selection
            if (type === 'role') {
                const el = document.getElementById('filter_role_container');
                el.classList.add('active');
                el.querySelector('select').disabled = false;
            } else if (type === 'status') {
                const el = document.getElementById('filter_status_container');
                el.classList.add('active');
                el.querySelector('select').disabled = false;
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            
            setupPhoneInput('contact');
            setupPhoneInput('edit_contact');
            setTimeout(() => {
                const a = document.getElementById('floatingSuccess');
                const b = document.getElementById('floatingError');
                if(a) a.style.display='none';
                if(b) b.style.display='none';
            }, 5000);
            
            window.onclick = function(event) {
                if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) {
                    document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
                }
                if (event.target == document.getElementById('addStaffModal')) closeAddStaffModal();
                if (event.target == document.getElementById('editStaffModal')) closeEditStaffModal();
                if (event.target == document.getElementById('viewStaffModal')) closeModal('viewStaffModal');
            }
        });
        
        // --- PASSWORD GENERATOR ---
        function generateStrongPassword(passId, confirmId) {
            const upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            const lower = "abcdefghijklmnopqrstuvwxyz";
            const numbers = "0123456789";
            const specials = "!@#$%^&*";
            const all = upper + lower + numbers + specials;
            
            let password = "";
            password += upper[Math.floor(Math.random() * upper.length)];
            password += lower[Math.floor(Math.random() * lower.length)];
            password += numbers[Math.floor(Math.random() * numbers.length)];
            password += specials[Math.floor(Math.random() * specials.length)];
            
            for (let i = 4; i < 12; i++) {
                password += all[Math.floor(Math.random() * all.length)];
            }
            password = password.split('').sort(() => 0.5 - Math.random()).join('');
            
            document.getElementById(passId).value = password;
            if(confirmId) document.getElementById(confirmId).value = password;
            
            const passInput = document.getElementById(passId);
            passInput.type = "text";
            const toggleBtn = passInput.nextElementSibling;
            if(toggleBtn) toggleBtn.querySelector('i').className = 'fas fa-eye-slash';
            
            if(confirmId) {
                 const confirmInput = document.getElementById(confirmId);
                 confirmInput.type = "text";
                 const confirmToggle = confirmInput.parentNode.querySelector('.toggle-password');
                 if(confirmToggle) confirmToggle.querySelector('i').className = 'fas fa-eye-slash';
            }
            
            if(passId === 'password') validatePasswordRequirements();
            if(confirmId) validateMatch();
        }

        // --- IMAGE PREVIEW & REMOVE LOGIC ---
        function previewImage(input, containerId, infoId, nameId) {
            const container = document.getElementById(containerId);
            const info = document.getElementById(infoId);
            const nameSpan = document.getElementById(nameId);
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    container.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    if(info) { info.style.display = 'inline-flex'; nameSpan.textContent = input.files[0].name; }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage(inputId, containerId, infoId, originalSrc = null) {
            const input = document.getElementById(inputId);
            const container = document.getElementById(containerId);
            const info = document.getElementById(infoId);
            
            input.value = '';
            if(info) info.style.display = 'none';
            
            if (originalSrc) {
                container.innerHTML = `<img src="${originalSrc}" alt="Preview">`;
            } else {
                container.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            }
        }

        // --- INPUT FORMATTERS ---
        function setupPhoneInput(inputId) {
            const input = document.getElementById(inputId);
            input.addEventListener('input', function(e) {
                let val = this.value.replace(/\D/g, ''); 
                let newVal = '';
                if (val.length > 0) newVal += val.substring(0, 1);
                if (val.length >= 1) newVal += '-';
                if (val.length > 1) newVal += val.substring(1, 9);
                this.value = newVal;
            });
        }
        function formatICNumber(id) { 
            const el = document.getElementById(id); 
            let v = el.value.replace(/\D/g, ''); 
            let n = ''; 
            if(v.length > 0) n += v.substring(0,6); 
            if(v.length >= 6) n += '-'; 
            if(v.length > 6) n += v.substring(6,8); 
            if(v.length >= 8) n += '-'; 
            if(v.length > 8) n += v.substring(8,12); 
            el.value = n; 
        }

        // --- MENU & MODAL LOGIC ---
        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); document.getElementById('menu-' + id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openViewStaffModal(staff) {
            document.getElementById('view_fullname').value = staff.Staff_FullName;
            document.getElementById('view_email').value = staff.Staff_Email;
            document.getElementById('view_contact').value = staff.Staff_ContactNumber;
            document.getElementById('view_ic').value = staff.Staff_ICNumber;
            document.getElementById('view_dob').value = staff.Staff_DOB;
            let address = staff.Staff_Address1;
            if(staff.Staff_Address2) address += ", " + staff.Staff_Address2;
            if(staff.Staff_Address3) address += ", " + staff.Staff_Address3;
            address += "\n" + staff.Staff_PostalCode + " " + staff.Staff_City + ", " + staff.Staff_State;
            document.getElementById('view_address').value = address;
            document.getElementById('view_role').value = staff.Staff_Role;
            document.getElementById('view_status').value = staff.Staff_Status;
            document.getElementById('viewStaffModal').style.display = 'flex';
        }

        function openAddStaffModal() { document.getElementById('addStaffModal').style.display = 'flex'; }
        function closeAddStaffModal() {
            document.getElementById('addStaffModal').style.display = 'none';
            document.getElementById('addStaffForm').reset();
            document.getElementById('add-preview-container').innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            document.getElementById('add-file-info').style.display = 'none';
            document.querySelectorAll('.requirement-item').forEach(el => { el.className = 'requirement-item invalid'; el.querySelector('i').className = 'fas fa-times'; });
        }

        function openEditStaffModal(staff) {
            document.getElementById('edit_staff_id').value = staff.Staff_ID;
            document.getElementById('edit_fullname').value = staff.Staff_FullName;
            document.getElementById('edit_email').value = staff.Staff_Email;
            
            const pInput = document.getElementById('edit_contact');
            pInput.value = staff.Staff_ContactNumber.replace('+601', '');
            pInput.dispatchEvent(new Event('input'));

            document.getElementById('edit_ic_number').value = staff.Staff_ICNumber;
            document.getElementById('edit_dob').value = staff.Staff_DOB;
            
            document.getElementById('edit_address1').value = staff.Staff_Address1;
            document.getElementById('edit_address2').value = staff.Staff_Address2;
            document.getElementById('edit_address3').value = staff.Staff_Address3;
            
            document.getElementById('edit_city').value = staff.Staff_City;
            document.getElementById('edit_state').value = staff.Staff_State;
            document.getElementById('edit_postal_code').value = staff.Staff_PostalCode;
            document.getElementById('edit_role').value = staff.Staff_Role;
            document.getElementById('edit_status').value = staff.Staff_Status;
            document.getElementById('edit_comment').value = staff.Staff_Comment;

            const previewContainer = document.getElementById('edit-preview-container');
            const fileInfo = document.getElementById('edit-file-info');
            let originalSrc = null;
            if (staff.Staff_ProfilePicture) {
                originalSrc = staff.Staff_ProfilePicture;
                previewContainer.innerHTML = `<img src="${staff.Staff_ProfilePicture}" alt="Preview">`;
            } else {
                previewContainer.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            }
            document.getElementById('edit_profile_picture').value = '';
            fileInfo.style.display = 'none';
            document.getElementById('edit-file-remove-btn').onclick = function() { removeImage('edit_profile_picture', 'edit-preview-container', 'edit-file-info', originalSrc); };
            document.getElementById('editStaffModal').style.display = 'flex';
        }
        function closeEditStaffModal() { document.getElementById('editStaffModal').style.display = 'none'; }
        function confirmDelete(id) { if(confirm('Are you sure you want to delete this staff member?')) window.location.href = 'staff_management_page.php?delete_id=' + id; }

        // --- VALIDATION FUNCTIONS ---
        function validateName(input) { input.value = input.value.replace(/[^a-zA-Z\s]/g, ''); }
        
        function validateEmail(id, errId) {
            const val = document.getElementById(id).value;
            const valid = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|my)$/i.test(val);
            document.getElementById(errId).style.display = valid ? 'none' : 'block';
            return valid;
        }
        
        function validateAge(id, errId) {
            const val = document.getElementById(id).value;
            if(!val) return true;
            const age = new Date().getFullYear() - new Date(val).getFullYear();
            const valid = age >= 18;
            document.getElementById(errId).style.display = valid ? 'none' : 'block';
            return valid;
        }

        function validatePasswordRequirements() {
            const pw = document.getElementById('password').value;
            const reqs = { 
                lengthReq: pw.length >= 8 && pw.length <= 15, 
                uppercaseReq: /[A-Z]/.test(pw), 
                lowercaseReq: /[a-z]/.test(pw), 
                numberReq: /\d/.test(pw), 
                specialReq: /[!@#$%^&*]/.test(pw) 
            };
            let allValid = true;
            for (const [id, valid] of Object.entries(reqs)) {
                const el = document.getElementById(id);
                const icon = el.querySelector('i');
                if (valid) { el.className = 'requirement-item valid'; icon.className = 'fas fa-check'; }
                else { el.className = 'requirement-item invalid'; icon.className = 'fas fa-times'; allValid = false; }
            }
            document.getElementById('passwordError').style.display = (pw && !allValid) ? 'block' : 'none';
            if(document.getElementById('confirm_password').value) validateMatch();
            return allValid;
        }
        
        function validateMatch() {
            const pw = document.getElementById('password').value;
            const cpw = document.getElementById('confirm_password').value;
            const match = pw === cpw && pw !== "";
            document.getElementById('confirmPasswordError').style.display = (match || cpw === "") ? 'none' : 'block';
            document.getElementById('password-match-icon').style.display = match ? 'block' : 'none';
            return match;
        }

        function togglePasswordVisibility(id) {
            const f = document.getElementById(id);
            const icon = f.nextElementSibling.tagName === 'BUTTON' ? f.nextElementSibling.querySelector('i') : f.parentNode.querySelector('button i');
            if(f.type === 'password') { f.type = 'text'; icon.className = 'fas fa-eye-slash'; }
            else { f.type = 'password'; icon.className = 'fas fa-eye'; }
        }

        function validateForm() {
            const vEmail = validateEmail('email', 'emailError');
            const vPass = validatePasswordRequirements();
            const vMatch = validateMatch();
            const vAge = validateAge('dob', 'ageError');
            return vEmail && vPass && vMatch && vAge;
        }

        function validateEditForm() {
            const vEmail = validateEmail('edit_email', 'editEmailError');
            const vAge = validateAge('edit_dob', 'editAgeError');
            return vEmail && vAge;
        }
    </script>
</body>
</html>
