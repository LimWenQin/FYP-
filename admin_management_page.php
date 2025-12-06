<?php
// admin_management_page.php
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
            $uploadDir = 'uploads/admins/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'admin_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return $uploadPath;
            }
        }
    }
    return null;
}

// --- HANDLE ADD ADMIN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_admin'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
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
    $password = $_POST['password'];
    
    // Handle Profile Picture
    $profilePicture = null;
    if (isset($_FILES['profile_picture'])) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) {
            $profilePicture = $uploadedPath;
        }
    }
    
    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.(com|net|org|edu|gov|my)$/i', $email)) {
        $errorMessage = "Invalid email format.";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
        $errorMessage = "Name can only contain letters and spaces.";
    } elseif (!preg_match('/^\+601[0-9]-[0-9]{7,10}$/', $contact)) { 
        $errorMessage = "Invalid phone format.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>])[A-Za-z\d!@#$%^&*()\-_=+{};:,<.>]{8,15}$/', $password)) {
        $errorMessage = "Password is weak. Follow requirements.";
    } else {
        // Validate Age (Must be 18+)
        if (!empty($dob)) {
            $birthDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            if ($age < 18) {
                $errorMessage = "Admin must be at least 18 years old.";
            }
        }

        if (!isset($errorMessage)) {
            $checkEmailSql = "SELECT Admin_ID FROM admin WHERE Admin_Email = '$email'";
            $res = $conn->query($checkEmailSql);
            
            if ($res && $res->num_rows > 0) {
                $errorMessage = "Email already exists in the system.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $cols = "Admin_Name, Admin_ContactNumber, Admin_ICNUMBER, Admin_Email, Admin_Password, 
                        Admin_Address1, Admin_Address2, Admin_Address3, Admin_City, Admin_State, Admin_PostalCode, Admin_Country, 
                        Admin_DOB, Admin_Role, Admin_Status, Admin_Comment";
                
                $vals = "'$name', '$contact', '$icNumber', '$email', '$hashedPassword', 
                        '$address1', '$address2', '$address3', '$city', '$state', '$postalCode', '$country',
                        '$dob', '$role', 'Active', 'Added via admin management system'";

                if ($profilePicture) {
                    $cols .= ", Admin_ProfilePicture";
                    $vals .= ", '$profilePicture'";
                }

                $sql = "INSERT INTO admin ($cols) VALUES ($vals)";
                
                if ($conn->query($sql)) {
                    $successMessage = "Admin added successfully!";
                } else {
                    $errorMessage = "Error adding admin: " . $conn->error;
                }
            }
        }
    }
    
    if (!empty($successMessage)) { header("Location: admin_management_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: admin_management_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// --- HANDLE UPDATE ADMIN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_admin'])) {
    $editId = mysqli_real_escape_string($conn, $_POST['admin_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
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
    
    $passwordSql = "";
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>])[A-Za-z\d!@#$%^&*()\-_=+{};:,<.>]{8,15}$/', $password)) {
            $errorMessage = "New password does not meet requirements.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $passwordSql = ", Admin_Password = '$hashedPassword'";
        }
    }

    $picSql = "";
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) {
            $oldPicQ = $conn->query("SELECT Admin_ProfilePicture FROM admin WHERE Admin_ID = $editId");
            if ($oldRow = $oldPicQ->fetch_assoc()) {
                if (!empty($oldRow['Admin_ProfilePicture']) && file_exists($oldRow['Admin_ProfilePicture'])) {
                    unlink($oldRow['Admin_ProfilePicture']);
                }
            }
            $picSql = ", Admin_ProfilePicture = '$uploadedPath'";
        }
    }

    if (empty($errorMessage)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
             $errorMessage = "Invalid email format.";
        } else {
            $sql = "UPDATE admin SET 
                    Admin_Name = '$name', 
                    Admin_ContactNumber = '$contact', 
                    Admin_ICNUMBER = '$icNumber', 
                    Admin_Email = '$email',
                    Admin_Address1 = '$address1', 
                    Admin_Address2 = '$address2', 
                    Admin_Address3 = '$address3',
                    Admin_City = '$city', 
                    Admin_State = '$state', 
                    Admin_PostalCode = '$postalCode',
                    Admin_Country = '$country', 
                    Admin_DOB = '$dob',
                    Admin_Role = '$role'
                    $passwordSql
                    $picSql
                    WHERE Admin_ID = $editId";
            
            if ($conn->query($sql)) {
                $successMessage = "Admin updated successfully!";
            } else {
                $errorMessage = "Error updating admin: " . $conn->error;
            }
        }
    }

    if (!empty($successMessage)) { header("Location: admin_management_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: admin_management_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// --- DELETE ADMIN ---
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    
    if ($deleteId == $_SESSION['admin_id']) {
        $errorMessage = "You cannot delete your own account!";
        header("Location: admin_management_page.php?error=" . urlencode($errorMessage));
        exit();
    } else {
        $deleteSql = "DELETE FROM admin WHERE Admin_ID = $deleteId";
        if ($conn->query($deleteSql)) {
            $successMessage = "Admin deleted successfully!";
            header("Location: admin_management_page.php?success=" . urlencode($successMessage));
            exit();
        } else {
            $errorMessage = "Error deleting admin: " . $conn->error;
            header("Location: admin_management_page.php?error=" . urlencode($errorMessage));
            exit();
        }
    }
}

// --- PAGINATION & SEARCH & FILTER (NEW LOGIC) ---
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$start_from = ($page - 1) * $results_per_page;

// Get Search and Filter inputs (Updated to match Donor Logic)
$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";
$filterType = isset($_GET['filter_type']) ? $_GET['filter_type'] : "";
$filterValue = "";

// Build WHERE Clause dynamically
$conditions = [];

if (!empty($searchTerm)) {
    $conditions[] = "(Admin_Name LIKE '%$searchTerm%' 
                     OR Admin_Email LIKE '%$searchTerm%' 
                     OR Admin_ID LIKE '%$searchTerm%')";
}

// Handle Dynamic Filters
if (!empty($filterType)) {
    if ($filterType == 'role' && !empty($_GET['filter_val_role'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_role']);
        $conditions[] = "Admin_Role = '$filterValue'";
    }
    elseif ($filterType == 'status' && !empty($_GET['filter_val_status'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_status']);
        $conditions[] = "Admin_Status = '$filterValue'";
    }
}

$whereClause = "";
if (count($conditions) > 0) {
    $whereClause = "WHERE " . implode(' AND ', $conditions);
}

// Get Count
$count_sql = "SELECT COUNT(*) as total FROM admin $whereClause";
$count_result = $conn->query($count_sql);
$total_admins = 0;
if ($count_result && $count_result->num_rows > 0) {
    $row = $count_result->fetch_assoc();
    $total_admins = $row['total'];
}

$total_pages = ceil($total_admins / $results_per_page);

// Get Data
$sql = "SELECT * FROM admin $whereClause ORDER BY Admin_Name LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$admins = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
}

$start_record = ($total_admins > 0) ? $start_from + 1 : 0;
$end_record = min($page * $results_per_page, $total_admins);

// --- STATS ---
function getTotalAdmins($conn) {
    $sql = "SELECT COUNT(*) as total FROM admin";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
}

function getActiveAdmins($conn) {
    $sql = "SELECT COUNT(*) as total FROM admin WHERE Admin_Status = 'Active'";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
}

$totalAdminsCount = getTotalAdmins($conn);
$activeAdminsCount = getActiveAdmins($conn);

// --- CURRENT USER INFO ---
$adminId = $_SESSION['admin_id'];
$adminName = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin'; 
$adminProfilePicture = null;

$stmt = $conn->prepare("SELECT Admin_ProfilePicture FROM admin WHERE Admin_ID = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $adminProfilePicture = $row['Admin_ProfilePicture'];
}
$stmt->close();

function formatAddress($admin) {
    $addressParts = [];
    if (!empty($admin['Admin_Address1'])) $addressParts[] = htmlspecialchars($admin['Admin_Address1']) . ',';
    $line2Parts = [];
    if (!empty($admin['Admin_Address2'])) $line2Parts[] = htmlspecialchars($admin['Admin_Address2']);
    if (!empty($admin['Admin_Address3'])) $line2Parts[] = htmlspecialchars($admin['Admin_Address3']);
    if (!empty($line2Parts)) $addressParts[] = implode(', ', $line2Parts) . ',';
    $postal = htmlspecialchars($admin['Admin_PostalCode']);
    $city = htmlspecialchars($admin['Admin_City']);
    $state = htmlspecialchars($admin['Admin_State']);
    $addressParts[] = $postal . ' ' . $city . ',' . $state;
    return implode("<br>", $addressParts);
}

$malaysiaStates = [
    'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 
    'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* 复用样式 - 与 Donor Page 一致 */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .stat-desc { font-size: 12px; display: flex; align-items: center; gap: 5px; font-weight: 500; }
        .stat-desc.text-success { color: #28a745; }
        .stat-desc.text-muted { color: #6c757d; }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }

        .admin-management { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; }
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        /* Updated Search Bar Styling (To Match Donor Page) */
        .admin-search { 
            margin-bottom: 20px; 
            display: flex; 
            gap: 10px; 
            align-items: center; 
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #eee;
            flex-wrap: wrap;
        }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; min-width: 200px; }
        .search-input:focus { border-color: var(--primary); }
        
        /* Filter Styles */
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select {
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            outline: none;
            background-color: white;
            min-width: 140px;
            cursor: pointer;
        }
        .filter-select:focus { border-color: var(--primary); }
        
        /* Secondary Filters (Hidden by Default) */
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th, .admin-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .admin-table th { font-weight: 600; color: var(--gray); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .admin-info { display: flex; align-items: center; }
        .admin-avatar { width: 40px; height: 40px; border-radius: 50%; margin-right: 15px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px; overflow: hidden; }
        .admin-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .admin-details h4 { font-size: 14px; margin-bottom: 4px; color: var(--dark); }
        .admin-details p { font-size: 12px; color: #888; margin: 0; }
        
        .role-status-cell { display: flex; flex-direction: column; align-items: flex-start; gap: 5px; }
        .role-text { font-weight: 600; color: #333; font-size: 13px; }
        .status-badge { padding: 4px 8px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-active { background: rgba(40, 167, 69, 0.1); color: var(--success); }
        .status-inactive { background: rgba(220, 53, 69, 0.1); color: var(--danger); }

        .action-cell { display: flex; justify-content: center; align-items: center; height: 100%; }
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { background-color: #f8f9fa; border: 1px solid #e9ecef; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; color: #6c757d; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
        .menu-btn:hover { background-color: #e2e6ea; color: var(--primary); transform: translateY(-1px); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; border: 1px solid #eee; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 13px; cursor: pointer; transition: background 0.2s; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: var(--primary); }
        .text-delete { color: var(--danger) !important; border-top: 1px solid #eee; }
        .text-delete:hover { background-color: #fff5f5 !important; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--gray-light); }
        .modal-header h2 { font-size: 18px; font-weight: 600; margin: 0; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray); }
        .close-btn:hover { color: var(--danger); }
        .modal-body { padding: 20px; }

        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark); }
        .form-input, .form-select { width: 100%; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; }
        .form-input:read-only { background-color: #f8f9fa; color: #555; cursor: default; }

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

        /* Error Messages & Guides */
        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }
        .form-guide { font-size: 11px; color: #6c757d; margin-top: 3px; display: block; font-style: italic; }
        .required { color: red; margin-left: 3px; font-weight: bold; }

        /* Password Styling */
        .password-input-group { display: flex; width: 100%; }
        .password-input-container { position: relative; flex: 1; display: flex; }
        .password-input-container input { border-radius: 5px 0 0 5px; border-right: none; width: 100%; }
        .password-input-container button.toggle-password { 
            position: static; border: 1px solid var(--gray-light); border-left: none; 
            border-radius: 0; background: white; padding: 0 10px; cursor: pointer; color: #888;
        }
        .btn-small { padding: 0 12px; border-radius: 0 5px 5px 0; border: 1px solid var(--gray-light); border-left: none; background: #f8f9fa; cursor: pointer; font-size: 12px; font-weight: 500; color: var(--primary); transition: 0.2s; }
        .btn-small:hover { background: #e9ecef; }

        .password-requirements { margin-top: 8px; font-size: 12px; }
        .requirement-item { display: flex; align-items: center; margin-bottom: 3px; color: #888; }
        .requirement-item.valid { color: var(--success); }
        .requirement-item.invalid { color: var(--gray); }
        .requirement-item i { width: 15px; text-align: center; margin-right: 5px; }

        /* Phone Input */
        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); }
        .phone-input { border-radius: 0 5px 5px 0 !important; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 15px 0; border-top: 1px solid var(--gray-light); }
        .pagination-info { font-size: 14px; color: var(--gray); }
        .pagination-controls { display: flex; gap: 5px; align-items: center; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; }
        .pagination-btn:hover:not(.disabled):not(.active) { background-color: #e2e6ea; border-color: #dae0e5; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; }
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
                <li><a href="staff_management_page.php"><i class="fas fa-user-tie"></i> <span>Staff Management</span></a></li>
                <li><a href="admin_management_page.php" class="active"><i class="fas fa-user-shield"></i> <span>Admin Management</span></a></li>
                <li><a href="branch_management_page.php"><i class="fas fa-map-marker-alt"></i> <span>Branch Management</span></a></li>
                <li><a href="activity_management.php"><i class="fas fa-calendar-alt"></i> <span>Activity Management</span></a></li>
                <li><a href="payment_management.php"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
                <li><a href="reward_item_management.php"><i class="fas fa-gift"></i> <span>Reward Items</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="top-nav">
            <div class="nav-left"><div class="logo"><a href="admin_dashboard.php"><img src="logo.jpg" alt="Logo"><h1>DonationMS</h1></a></div><div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Search..."></div></div>
            <div class="nav-right"><div class="user-profile" id="userProfileDropdown"><div class="user-profile-with-avatar"><div class="user-avatar"><?php if (!empty($adminProfilePicture)): ?><img src="<?php echo htmlspecialchars($adminProfilePicture); ?>" alt="Profile"><?php else: ?><?php echo substr($adminName, 0, 1); ?><?php endif; ?></div><div class="user-details"><div class="user-name"><?php echo htmlspecialchars($adminName); ?></div><div class="user-role">Administrator</div></div></div></div></div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-section"><h1>Admin Management</h1><p>Manage all administrators in the system.</p></div>
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-info"><h3>TOTAL ADMINS</h3><h2><?php echo $totalAdminsCount; ?></h2><p class="stat-desc text-muted"><i class="fas fa-users"></i> Total registered administrators</p></div><div class="stat-icon"><i class="fas fa-user-shield"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3>ACTIVE ADMINS</h3><h2><?php echo $activeAdminsCount; ?></h2><p class="stat-desc text-success"><i class="fas fa-check-circle"></i> Currently active</p></div><div class="stat-icon"><i class="fas fa-user-check"></i></div></div>
            </div>

            <div class="admin-management">
                <div class="section-header"><h2>Admin List</h2><div class="action-buttons"><button class="btn btn-primary" onclick="openAddAdminModal()"><i class="fas fa-plus"></i> Add New Admin</button><button class="btn btn-success"><i class="fas fa-download"></i> Export Data</button></div></div>
                
                <form method="GET" action="admin_management_page.php" class="admin-search">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="role" <?php echo ($filterType == 'role') ? 'selected' : ''; ?>>Role</option>
                            <option value="status" <?php echo ($filterType == 'status') ? 'selected' : ''; ?>>Status</option>
                        </select>
                    </div>

                    <div id="filter_role_container" class="secondary-filter">
                        <select name="filter_val_role" class="filter-select">
                            <option value="">Select Role...</option>
                            <option value="Admin" <?php if($filterType == 'role' && $filterValue == 'Admin') echo 'selected'; ?>>Admin</option>
                            <option value="Super Admin" <?php if($filterType == 'role' && $filterValue == 'Super Admin') echo 'selected'; ?>>Super Admin</option>
                            <option value="Moderator" <?php if($filterType == 'role' && $filterValue == 'Moderator') echo 'selected'; ?>>Moderator</option>
                        </select>
                    </div>

                    <div id="filter_status_container" class="secondary-filter">
                        <select name="filter_val_status" class="filter-select">
                            <option value="">Select Status...</option>
                            <option value="Active" <?php if($filterType == 'status' && $filterValue == 'Active') echo 'selected'; ?>>Active</option>
                            <option value="Inactive" <?php if($filterType == 'status' && $filterValue == 'Inactive') echo 'selected'; ?>>Inactive</option>
                        </select>
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search by Name, ID or Email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    
                    <?php if (!empty($searchTerm) || !empty($filterType)): ?>
                        <a href="admin_management_page.php" class="btn btn-danger" style="background-color: #dc3545; padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
                <table class="admin-table">
                    <thead><tr><th>ADMIN NAME</th><th>CONTACT INFO</th><th>IC / DOB</th><th style="width: 25%;">ADDRESS</th><th>ROLE & STATUS</th><th style="text-align: center;">ACTIONS</th></tr></thead>
                    <tbody>
                        <?php if (count($admins) > 0): foreach($admins as $admin): ?>
                            <tr>
                                <td><div class="admin-info"><div class="admin-avatar"><?php if (!empty($admin['Admin_ProfilePicture'])): ?><img src="<?php echo htmlspecialchars($admin['Admin_ProfilePicture']); ?>" alt="Profile"><?php else: ?><?php echo substr($admin['Admin_Name'], 0, 1); ?><?php endif; ?></div><div class="admin-details"><h4><?php echo htmlspecialchars($admin['Admin_Name']); ?></h4><p>ID: <?php echo htmlspecialchars($admin['Admin_ID']); ?></p></div></div></td>
                                <td><div class="admin-details"><p><i class="fas fa-envelope" style="width:15px;color:#999;"></i> <?php echo htmlspecialchars($admin['Admin_Email']); ?></p><p><i class="fas fa-phone" style="width:15px;color:#999;"></i> <?php echo htmlspecialchars($admin['Admin_ContactNumber']); ?></p></div></td>
                                <td><div style="font-size:13px; color:#555;"><div style="margin-bottom:3px;">IC: <?php echo htmlspecialchars($admin['Admin_ICNUMBER']); ?></div><div>DOB: <?php echo date('Y-m-d', strtotime($admin['Admin_DOB'])); ?></div></div></td>
                                <td><div class="address-display" style="font-size:12px; color:#666;"><?php echo formatAddress($admin); ?></div></td>
                                <td><div class="role-status-cell"><span class="role-text"><?php echo htmlspecialchars($admin['Admin_Role']); ?></span><span class="status-badge <?php echo ($admin['Admin_Status'] ?? 'Active') === 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $admin['Admin_Status'] ?? 'Active'; ?></span></div></td>
                                <td><div class="action-cell"><div class="action-menu"><button class="menu-btn" onclick="toggleMenu(event, <?php echo $admin['Admin_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button><div id="menu-<?php echo $admin['Admin_ID']; ?>" class="dropdown-content"><div onclick="openViewAdminModal(<?php echo htmlspecialchars(json_encode($admin)); ?>)"><i class="fas fa-eye"></i> View Details</div><div onclick='openEditAdminModal(<?php echo json_encode($admin); ?>)'><i class="fas fa-edit"></i> Edit Details</div><a href="javascript:confirmDelete(<?php echo $admin['Admin_ID']; ?>, '<?php echo htmlspecialchars($admin['Admin_Name']); ?>')" class="text-delete"><i class="fas fa-trash"></i> Delete</a></div></div></div></td>
                            </tr>
                        <?php endforeach; else: ?><tr><td colspan="6" style="text-align: center; padding: 20px;">No admins found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                
                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_admins; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        // Build query params for pagination
                        $queryParams = [];
                        if (!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if (!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            // Add value depending on type
                            if ($filterType == 'role' && !empty($filterValue)) $queryParams['filter_val_role'] = $filterValue;
                            if ($filterType == 'status' && !empty($filterValue)) $queryParams['filter_val_status'] = $filterValue;
                        }
                        $search_query = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                        
                        if ($page > 1): 
                        ?>
                            <a href="?page=<?php echo $page - 1 . $search_query; ?>" class="pagination-btn">Previous</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Previous</span>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): 
                            if ($i == $page): ?>
                                <span class="pagination-btn active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i . $search_query; ?>" class="pagination-btn"><?php echo $i; ?></a>
                            <?php endif; 
                        endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1 . $search_query; ?>" class="pagination-btn">Next</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="addAdminModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Administrator</h2><button class="close-btn" onclick="closeAddAdminModal()">&times;</button></div>
            <div class="modal-body">
                <form id="addAdminForm" action="admin_management_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <input type="hidden" name="add_admin" value="1">
                    <div class="form-group"><label>Profile Picture</label><div class="profile-picture-preview" id="add-preview-container"><div class="default-avatar-icon"><i class="fas fa-user"></i></div></div><div class="file-upload"><label for="add_profile_picture" class="file-upload-label"><i class="fas fa-upload"></i> Choose File</label><input type="file" id="add_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'add-preview-container', 'add-file-info', 'add-file-name')"><div id="add-file-info" class="file-info"><span id="add-file-name" class="file-name"></span><button type="button" class="file-remove" onclick="removeImage('add_profile_picture', 'add-preview-container', 'add-file-info')"><i class="fas fa-times"></i></button></div></div></div>
                    <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="name" class="form-input" required placeholder="Full Name" oninput="validateName(this)"><span class="form-guide">Only English letters and spaces are allowed.</span></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Email <span class="required">*</span></label><input type="email" id="email" name="email" class="form-input" required onblur="validateEmail()" placeholder="e.g. admin@lovebridge.org.my"><span class="form-guide">Must include '@' and end with .com, .net, .org, etc.</span><div id="emailError" class="error-message">Invalid email format</div></div><div class="form-group"><label class="form-label">Contact Number <span class="required">*</span></label><div class="phone-format"><span class="phone-prefix">+601</span><input type="text" id="contact" name="contact" class="form-input phone-input" required maxlength="11" placeholder="X-XXXXXXX"></div><span class="form-guide">(e.g., +6012-3456789)</span></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">IC Number <span class="required">*</span></label><input type="text" id="ic_number" name="ic_number" class="form-input" required maxlength="14" oninput="formatICNumber('ic_number')" placeholder="XXXXXX-XX-XXXX"></div><div class="form-group"><label class="form-label">Date of Birth <span class="required">*</span></label><input type="date" id="dob" name="dob" class="form-input" required onchange="validateAge()"><div id="ageError" class="error-message">Must be 18+</div></div></div>
                    <div class="form-group"><label class="form-label">Address Line 1 <span class="required">*</span></label><input type="text" name="address1" class="form-input" required placeholder="House No, Street"></div><div class="form-group"><label class="form-label">Address Line 2 <span class="required">*</span></label><input type="text" name="address2" class="form-input" required placeholder="Area / Taman"></div><div class="form-group"><label class="form-label">Address Line 3</label><input type="text" name="address3" class="form-input" placeholder="Building (Optional)"></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">City <span class="required">*</span></label><input type="text" name="city" class="form-input" required></div><div class="form-group"><label class="form-label">State <span class="required">*</span></label><select name="state" class="form-select" required><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Postal Code <span class="required">*</span></label><input type="text" name="postal_code" class="form-input" required></div><div class="form-group"><label class="form-label">Country</label><input type="text" name="country" class="form-input" value="Malaysia" readonly></div></div>
                    <div class="form-group"><label class="form-label">Role <span class="required">*</span></label><select name="role" class="form-select" required><option value="Admin">Admin</option><option value="Super Admin">Super Admin</option><option value="Moderator">Moderator</option></select></div>
                    <div class="form-group"><label class="form-label">Password <span class="required">*</span></label><div class="password-input-group"><div class="password-input-container"><input type="password" id="password" name="password" class="form-input" required oninput="validatePasswordRequirements()"><button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')"><i class="fas fa-eye"></i></button></div><button type="button" class="btn-small" onclick="generateStrongPassword('password', 'confirm_password')">Auto Generate</button></div><div class="password-requirements"><div class="requirement-item invalid" id="lengthReq"><i class="fas fa-times"></i> Must be 8-15 characters long</div><div class="requirement-item invalid" id="uppercaseReq"><i class="fas fa-times"></i> Must contain at least one Uppercase letter</div><div class="requirement-item invalid" id="lowercaseReq"><i class="fas fa-times"></i> Must contain at least one Lowercase letter</div><div class="requirement-item invalid" id="numberReq"><i class="fas fa-times"></i> Must contain at least one Number</div><div class="requirement-item invalid" id="specialReq"><i class="fas fa-times"></i> Must contain at least one Special character (e.g. !@#)</div></div></div>
                    <div class="form-group"><label class="form-label">Confirm Password <span class="required">*</span></label><div class="password-input-container"><input type="password" id="confirm_password" name="confirm_password" class="form-input" required oninput="validatePasswordMatch()"><i id="password-match-icon" class="fas fa-check-circle confirm-check" style="display:none; position:absolute; right:40px; top:50%; transform:translateY(-50%); color:green;"></i><button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')"><i class="fas fa-eye"></i></button></div><div id="confirmPasswordError" class="error-message">Passwords do not match</div></div>
                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Admin</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="editAdminModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Administrator</h2><button class="close-btn" onclick="closeEditAdminModal()">&times;</button></div>
            <div class="modal-body">
                <form id="editAdminForm" action="admin_management_page.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_admin" value="1">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="form-group"><label>Profile Picture</label><div class="profile-picture-preview" id="edit-preview-container"><div class="default-avatar-icon"><i class="fas fa-user"></i></div></div><div class="file-upload"><label for="edit_profile_picture" class="file-upload-label"><i class="fas fa-upload"></i> Change File</label><input type="file" id="edit_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'edit-preview-container', 'edit-file-info', 'edit-file-name')"><div id="edit-file-info" class="file-info"><span id="edit-file-name" class="file-name"></span><button type="button" class="file-remove" onclick="removeImage('edit_profile_picture', 'edit-preview-container', 'edit-file-info')"><i class="fas fa-times"></i></button></div></div></div>
                    <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" id="edit_name" name="name" class="form-input" required oninput="validateName(this)"></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Email <span class="required">*</span></label><input type="email" id="edit_email" name="email" class="form-input" required></div><div class="form-group"><label class="form-label">Contact Number <span class="required">*</span></label><div class="phone-format"><span class="phone-prefix">+601</span><input type="text" id="edit_contact" name="contact" class="form-input phone-input" required maxlength="11"></div></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">IC Number <span class="required">*</span></label><input type="text" id="edit_ic_number" name="ic_number" class="form-input" required oninput="formatICNumber('edit_ic_number')"></div><div class="form-group"><label class="form-label">Date of Birth <span class="required">*</span></label><input type="date" id="edit_dob" name="dob" class="form-input" required></div></div>
                    <div class="form-group"><label class="form-label">Address Line 1 <span class="required">*</span></label><input type="text" id="edit_address1" name="address1" class="form-input" required></div><div class="form-group"><label class="form-label">Address Line 2 <span class="required">*</span></label><input type="text" id="edit_address2" name="address2" class="form-input" required></div><div class="form-group"><label class="form-label">Address Line 3</label><input type="text" id="edit_address3" name="address3" class="form-input"></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">City <span class="required">*</span></label><input type="text" id="edit_city" name="city" class="form-input" required></div><div class="form-group"><label class="form-label">State <span class="required">*</span></label><select id="edit_state" name="state" class="form-select" required><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Postal Code <span class="required">*</span></label><input type="text" id="edit_postal_code" name="postal_code" class="form-input" required></div><div class="form-group"><label class="form-label">Country</label><input type="text" name="country" class="form-input" value="Malaysia" readonly></div></div>
                    <div class="form-group"><label class="form-label">Role <span class="required">*</span></label><select id="edit_role" name="role" class="form-select" required><option value="Admin">Admin</option><option value="Super Admin">Super Admin</option><option value="Moderator">Moderator</option></select></div>
                    <div class="form-group"><label class="form-label">New Password (Leave blank to keep current)</label><div class="password-input-group"><div class="password-input-container"><input type="password" id="edit_password" name="password" class="form-input"><button type="button" class="toggle-password" onclick="togglePasswordVisibility('edit_password')"><i class="fas fa-eye"></i></button></div></div><span class="form-guide">If you type here, the password will be changed.</span></div>
                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Admin</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="viewAdminModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Admin Details</h2><button class="close-btn" onclick="closeViewAdminModal()">&times;</button></div>
            <div class="modal-body">
                <div style="text-align:center; margin-bottom:20px;">
                    <div class="profile-picture-preview" id="view-profile-pic" style="margin: 0 auto 10px; border-color: var(--primary);"></div>
                    <h3 id="view_name_display" style="margin:0; font-size:18px;"></h3>
                    <span id="view_role_badge" class="status-badge" style="margin-top:5px; display:inline-block;"></span>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; font-size:14px;">
                    <div><strong>Email:</strong> <span id="view_email"></span></div>
                    <div><strong>Contact:</strong> <span id="view_contact"></span></div>
                    <div><strong>IC Number:</strong> <span id="view_ic"></span></div>
                    <div><strong>DOB:</strong> <span id="view_dob"></span></div>
                    <div style="grid-column: span 2;"><strong>Address:</strong><br><span id="view_address" style="color:#555;"></span></div>
                </div>
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
                // Disable inputs to prevent them from sending empty values to URL if not selected
                if(el.querySelector('select')) el.querySelector('select').disabled = true;
            });

            // Show specific one based on selection
            if (type === 'role') {
                const el = document.getElementById('filter_role_container');
                el.classList.add('active');
                if(el.querySelector('select')) el.querySelector('select').disabled = false;
            } else if (type === 'status') {
                const el = document.getElementById('filter_status_container');
                el.classList.add('active');
                if(el.querySelector('select')) el.querySelector('select').disabled = false;
            }
        }

        // Initialize functionality on load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            
            // Existing setup functions
            setupPhoneInput('contact'); setupPhoneInput('edit_contact');
            const s = document.getElementById('floatingSuccess');
            const e = document.getElementById('floatingError');
            if(s) { s.style.opacity='0'; setTimeout(()=>s.style.display='none',300); }
            if(e) { e.style.opacity='0'; setTimeout(()=>e.style.display='none',300); }
        });

        // --- EXISTING JAVASCRIPT ---
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        sidebar.addEventListener('mouseenter', () => { sidebar.classList.remove('collapsed'); mainContent.classList.add('expanded'); });
        sidebar.addEventListener('mouseleave', () => { sidebar.classList.add('collapsed'); mainContent.classList.remove('expanded'); });

        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); document.getElementById('menu-' + id).style.display = 'block'; }
        window.onclick = function(e) { if (!e.target.matches('.menu-btn') && !e.target.matches('.menu-btn *')) { document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); } if (e.target.classList.contains('modal')) e.target.style.display = "none"; }

        function previewImage(input, containerId, infoId, nameId) {
            const container = document.getElementById(containerId);
            const info = document.getElementById(infoId);
            const nameSpan = document.getElementById(nameId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { container.innerHTML = `<img src="${e.target.result}" alt="Preview">`; if(info) { info.style.display = 'inline-flex'; nameSpan.textContent = input.files[0].name; } }
                reader.readAsDataURL(input.files[0]);
            }
        }
        function removeImage(inputId, containerId, infoId, originalSrc = null) { document.getElementById(inputId).value = ''; if(infoId) document.getElementById(infoId).style.display = 'none'; const container = document.getElementById(containerId); if (originalSrc) { container.innerHTML = `<img src="${originalSrc}" alt="Preview">`; } else { container.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>'; } }

        function openAddAdminModal() { document.getElementById('addAdminModal').style.display = 'flex'; }
        function closeAddAdminModal() { 
            document.getElementById('addAdminModal').style.display = 'none'; 
            document.getElementById('addAdminForm').reset();
            document.getElementById('add-preview-container').innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            document.getElementById('add-file-info').style.display = 'none';
            document.querySelectorAll('.requirement-item').forEach(el => { el.className = 'requirement-item invalid'; el.querySelector('i').className = 'fas fa-times'; });
        }
        
        function openEditAdminModal(admin) {
            document.getElementById('editAdminModal').style.display = 'flex';
            document.getElementById('edit_admin_id').value = admin.Admin_ID;
            document.getElementById('edit_name').value = admin.Admin_Name;
            document.getElementById('edit_email').value = admin.Admin_Email;
            document.getElementById('edit_contact').value = admin.Admin_ContactNumber.replace('+601', '');
            document.getElementById('edit_ic_number').value = admin.Admin_ICNUMBER;
            const dobDate = new Date(admin.Admin_DOB);
            document.getElementById('edit_dob').value = dobDate.toISOString().split('T')[0];
            document.getElementById('edit_address1').value = admin.Admin_Address1;
            document.getElementById('edit_address2').value = admin.Admin_Address2;
            document.getElementById('edit_address3').value = admin.Admin_Address3;
            document.getElementById('edit_city').value = admin.Admin_City;
            document.getElementById('edit_state').value = admin.Admin_State;
            document.getElementById('edit_postal_code').value = admin.Admin_PostalCode;
            document.getElementById('edit_role').value = admin.Admin_Role;
            
            const container = document.getElementById('edit-preview-container');
            if (admin.Admin_ProfilePicture) {
                container.innerHTML = `<img src="${admin.Admin_ProfilePicture}" alt="Preview">`;
            } else {
                container.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            }
        }
        function closeEditAdminModal() { document.getElementById('editAdminModal').style.display = 'none'; document.getElementById('editAdminForm').reset(); }

        function openViewAdminModal(admin) {
            document.getElementById('viewAdminModal').style.display = 'flex';
            document.getElementById('view_name_display').textContent = admin.Admin_Name;
            document.getElementById('view_email').textContent = admin.Admin_Email;
            document.getElementById('view_contact').textContent = admin.Admin_ContactNumber;
            document.getElementById('view_ic').textContent = admin.Admin_ICNUMBER;
            document.getElementById('view_dob').textContent = new Date(admin.Admin_DOB).toDateString();
            
            const roleBadge = document.getElementById('view_role_badge');
            roleBadge.textContent = admin.Admin_Role;
            roleBadge.className = 'status-badge ' + (admin.Admin_Status === 'Active' ? 'status-active' : 'status-inactive');
            
            let addr = admin.Admin_Address1 + ', ';
            if(admin.Admin_Address2) addr += admin.Admin_Address2 + ', ';
            if(admin.Admin_Address3) addr += admin.Admin_Address3 + ', ';
            addr += admin.Admin_PostalCode + ' ' + admin.Admin_City + ', ' + admin.Admin_State;
            document.getElementById('view_address').textContent = addr;

            const container = document.getElementById('view-profile-pic');
            if (admin.Admin_ProfilePicture) {
                container.innerHTML = `<img src="${admin.Admin_ProfilePicture}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">`;
            } else {
                container.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            }
        }
        function closeViewAdminModal() { document.getElementById('viewAdminModal').style.display = 'none'; }

        function confirmDelete(id, name) {
            if (confirm("Are you sure you want to delete admin '" + name + "'? This action cannot be undone.")) {
                window.location.href = "admin_management_page.php?delete_id=" + id;
            }
        }

        // --- Validation & Helper Scripts ---
        function setupPhoneInput(id) { const el = document.getElementById(id); if(!el)return; el.addEventListener('input', function() { let v = this.value.replace(/\D/g, ''); if(v.length > 0) this.value = v.substring(0,1) + (v.length>1?'-':'') + (v.length>1?v.substring(1,9):''); }); }
        
        function formatICNumber(id) { const el = document.getElementById(id); let v = el.value.replace(/\D/g, ''); let n = ''; if(v.length > 0) n += v.substring(0,6); if(v.length >= 6) n += '-'; if(v.length > 6) n += v.substring(6,8); if(v.length >= 8) n += '-'; if(v.length > 8) n += v.substring(8,12); el.value = n; }

        function togglePasswordVisibility(id) { const f = document.getElementById(id); const icon = f.nextElementSibling.querySelector('i'); if(f.type==='password') { f.type='text'; icon.className='fas fa-eye-slash'; } else { f.type='password'; icon.className='fas fa-eye'; } }

        function validateName(input) { input.value = input.value.replace(/[^a-zA-Z\s]/g, ''); }
        function validateEmail() { const v = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|my)$/i.test(document.getElementById('email').value); document.getElementById('emailError').style.display = v ? 'none' : 'block'; return v; }
        function validateAge() { const d = document.getElementById('dob').value; if(!d) return false; const diff = new Date().getFullYear() - new Date(d).getFullYear(); const valid = diff >= 18; document.getElementById('ageError').style.display = valid ? 'none' : 'block'; return valid; }

        function generateStrongPassword(passId, confirmId) {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
            let p = ""; for(let i=0;i<12;i++) p += chars.charAt(Math.floor(Math.random()*chars.length));
            if(!/[A-Z]/.test(p)) p+='A'; if(!/[0-9]/.test(p)) p+='1'; if(!/[!@#$%^&*]/.test(p)) p+='!';
            document.getElementById(passId).value = p; 
            document.getElementById(confirmId).value = p;
            validatePasswordRequirements(); validatePasswordMatch();
            document.getElementById(passId).type='text'; document.getElementById(confirmId).type='text';
        }

        function validatePasswordRequirements() {
            const p = document.getElementById('password').value;
            const reqs = { lengthReq: p.length>=8 && p.length<=15, uppercaseReq: /[A-Z]/.test(p), lowercaseReq: /[a-z]/.test(p), numberReq: /\d/.test(p), specialReq: /[!@#$%^&*]/.test(p) };
            let allValid = true;
            for (const [id, valid] of Object.entries(reqs)) {
                const el = document.getElementById(id);
                const icon = el.querySelector('i');
                if(valid) { el.className='requirement-item valid'; icon.className='fas fa-check'; }
                else { el.className='requirement-item invalid'; icon.className='fas fa-times'; allValid=false; }
            }
            if(document.getElementById('confirm_password').value) validatePasswordMatch();
            return allValid;
        }

        function validatePasswordMatch() {
            const m = document.getElementById('password').value === document.getElementById('confirm_password').value;
            document.getElementById('confirmPasswordError').style.display = m ? 'none' : 'block';
            document.getElementById('password-match-icon').style.display = m ? 'block' : 'none';
            return m;
        }

        function validateForm() { return validateEmail() && validateAge() && validatePasswordRequirements() && validatePasswordMatch(); }
    </script>
</body>
</html>
