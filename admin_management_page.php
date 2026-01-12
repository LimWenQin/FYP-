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

// --- 引入 PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// --- 0. FETCH CURRENT ADMIN INFO ---
$currentAdminId = $_SESSION['admin_id'];
$headerSql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
$headerResult = $conn->query($headerSql);

if ($headerResult && $headerResult->num_rows > 0) {
    $headerRow = $headerResult->fetch_assoc();
    $adminName = $headerRow['Admin_Name'];          
    $adminProfilePicture = $headerRow['Admin_ProfilePicture'];
    $adminPosition = $headerRow['Admin_Role']; // Ensure this variable is set for permission checks
} else {
    $adminName = "Admin";
    $adminProfilePicture = null;
    $adminPosition = "System Admin";
}

// --- HELPER: Generate Strong Random Password ---
function generateStrongRandomPassword($length = 12) {
    $upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $lower = "abcdefghijklmnopqrstuvwxyz";
    $numbers = "0123456789";
    $symbols = "!@#$%^&*()";
    
    $password = "";
    $password .= $upper[rand(0, strlen($upper) - 1)];
    $password .= $lower[rand(0, strlen($lower) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $symbols[rand(0, strlen($symbols) - 1)];
    
    $allChars = $upper . $lower . $numbers . $symbols;
    for ($i = 0; $i < $length - 4; $i++) {
        $password .= $allChars[rand(0, strlen($allChars) - 1)];
    }
    
    return str_shuffle($password);
}

// --- HELPER: Validate IC match DOB (Server Side) ---
function validateIcDobMatch($ic, $dob) {
    $cleanIc = preg_replace('/[^0-9]/', '', $ic);
    if (strlen($cleanIc) < 6) return false;

    $y = substr($cleanIc, 0, 2);
    $m = substr($cleanIc, 2, 2);
    $d = substr($cleanIc, 4, 2);

    $currentYearShort = date('y');
    $century = ($y > $currentYearShort) ? '19' : '20';
    $icDate = $century . $y . '-' . $m . '-' . $d;

    if (!checkdate((int)$m, (int)$d, (int)($century . $y))) {
        return false;
    }

    return $icDate === $dob;
}

// --- 1. EXPORT TO EXCEL LOGIC ---
if (isset($_POST['export_excel'])) {
    $filename = "admin_list_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $exportSql = "SELECT * FROM admin WHERE Is_Deleted = 0 ORDER BY Admin_CreatedAt DESC";
    $exportResult = $conn->query($exportSql);

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST']; 
    $path = dirname($_SERVER['PHP_SELF']); 
    $baseUrl = rtrim($protocol . "://" . $host . $path, '/\\') . '/';

    echo '<table border="1">';
    echo '<tr>
            <th style="width: 80px;">Profile Picture</th> <th>ID</th>
            <th>Name</th><th>Email</th><th>Contact</th><th>IC Number</th>
            <th>Role</th><th>Status</th><th>Address</th><th>Join Date</th><th>Comment</th>
          </tr>';

    if ($exportResult->num_rows > 0) {
        while($row = $exportResult->fetch_assoc()) {
            $fullAddr = $row['Admin_Address1'] . " " . $row['Admin_Address2'] . " " . $row['Admin_Address3'] . " " . $row['Admin_City'] . " " . $row['Admin_State'];
            echo '<tr>';
            echo '<td style="text-align:center; vertical-align:middle; height:80px;">';
            if (!empty($row['Admin_ProfilePicture']) && file_exists($row['Admin_ProfilePicture'])) {
                $fullImageUrl = $baseUrl . $row['Admin_ProfilePicture'];
                echo '<img src="' . $fullImageUrl . '" width="60" height="60" style="object-fit:cover; border-radius:50%;">';
            } else {
                echo 'No Image';
            }
            echo '</td>';
            echo '<td style="vertical-align:middle;">' . $row['Admin_ID'] . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Admin_Name']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Admin_Email']) . '</td>';
            echo '<td style="vertical-align:middle; mso-number-format:\'\@\'">' . htmlspecialchars($row['Admin_ContactNumber']) . '</td>';
            echo '<td style="vertical-align:middle; mso-number-format:\'\@\'">' . htmlspecialchars($row['Admin_ICNUMBER']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Admin_Role']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Admin_Status']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($fullAddr) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Admin_CreatedAt']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Admin_Comment']) . '</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    exit();
}

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
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $contactRaw = trim($_POST['contact']);
    $contact = "+60" . $contactRaw;
    $icNumber = mysqli_real_escape_string($conn, trim($_POST['ic_number']));
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    
    $address1 = mysqli_real_escape_string($conn, trim($_POST['address1']));
    $address2 = mysqli_real_escape_string($conn, trim($_POST['address2']));
    $address3 = mysqli_real_escape_string($conn, trim($_POST['address3']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, trim($_POST['postal_code']));
    $country = mysqli_real_escape_string($conn, "Malaysia");
    
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']); 
    
    $profilePicture = null;
    if (isset($_FILES['profile_picture'])) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) {
            $profilePicture = $uploadedPath;
        }
    }
    
    // Validations
    if (empty($name)) { $errorMessage = "Full Name is required."; }
    elseif (empty($email)) { $errorMessage = "Email is required."; }
    elseif (empty($contactRaw)) { $errorMessage = "Contact Number is required."; }
    elseif (empty($icNumber)) { $errorMessage = "IC Number is required."; }
    elseif (empty($dob)) { $errorMessage = "Date of Birth is required."; } 
    elseif (!preg_match('/^[a-zA-Z\s]+$/', $name)) { $errorMessage = "Name can only contain letters and spaces."; } 
    elseif (strpos($email, '@') === false) { $errorMessage = "Invalid email: Missing '@' symbol."; } 
    elseif (!preg_match('/@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) { $errorMessage = "Invalid email: Missing valid domain extension."; } 
    else {
        $phoneParts = explode('-', $contactRaw);
        if (count($phoneParts) != 2) {
            $errorMessage = "Invalid phone format. Use 12-3456789.";
        } else {
            $backDigits = $phoneParts[1];
            if (strlen($backDigits) < 7 || strlen($backDigits) > 8) {
                $errorMessage = "Invalid phone number: Must be 7-8 digits after hyphen.";
            }
        }
    }

    if (!isset($errorMessage)) {
        if (!validateIcDobMatch($icNumber, $dob)) {
            $errorMessage = "IC Number and Date of Birth do not match.";
        }
    }

    if (!isset($errorMessage)) {
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
                $rawPassword = generateStrongRandomPassword(12);
                $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);
                $isFirstLogin = 1;

                $dbProfilePic = $profilePicture ? "'$profilePicture'" : "NULL";

                $sql = "INSERT INTO admin (
                            Admin_Name, Admin_ContactNumber, Admin_ICNUMBER, Admin_Email, Admin_Password, Admin_IsFirstLogin,
                            Admin_Address1, Admin_Address2, Admin_Address3, Admin_City, Admin_State, Admin_PostalCode, Admin_Country, 
                            Admin_DOB, Admin_Role, Admin_Status, Admin_Comment, Admin_ProfilePicture, Is_Deleted
                        ) VALUES (
                            '$name', '$contact', '$icNumber', '$email', '$hashedPassword', $isFirstLogin,
                            '$address1', '$address2', '$address3', '$city', '$state', '$postalCode', '$country',
                            '$dob', '$role', '$status', '$comment', $dbProfilePic, 0
                        )";
                
                if ($conn->query($sql)) {
                    // Send Email
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'lovebridge1201@gmail.com'; 
                        $mail->Password   = 'odaj iwrz gfrt vven';      
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port       = 465;
                        $mail->setFrom('lovebridge1201@gmail.com', 'Love Bridge Admin System');
                        $mail->addAddress($email, $name);
                        $mail->isHTML(true);
                        $mail->Subject = 'Welcome to Love Bridge - Admin Account Details';
                        $mail->Body    = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                                <h2 style='color: #D97706;'>Welcome to Love Bridge, $name!</h2>
                                <p>Your <strong>$role</strong> account has been successfully created.</p>
                                <div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                    <p style='margin: 5px 0;'><strong>Email:</strong> $email</p>
                                    <p style='margin: 5px 0;'><strong>Temporary Password:</strong> <span style='font-family: monospace; background: #e2e8f0; padding: 3px 6px; border-radius: 3px; color: #d97706; font-weight: bold;'>$rawPassword</span></p>
                                </div>
                                <p><strong>Important:</strong> Please create a new password immediately upon first login.</p>
                            </div>
                        ";
                        $mail->send();
                        $successMessage = "Admin added successfully! Login details sent to email.";
                    } catch (Exception $e) {
                        $successMessage = "Admin added, but email failed. Error: {$mail->ErrorInfo}. Temp Password: $rawPassword";
                    }
                } else {
                    $errorMessage = "Error adding admin: " . $conn->error;
                }
            }
        }
    }
    
    if (!empty($successMessage)) { header("Location: admin_management_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: admin_management_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// --- HANDLE UPDATE ADMIN INFO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_admin'])) {
    $editId = mysqli_real_escape_string($conn, $_POST['admin_id']);
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $contactRaw = trim($_POST['contact']);
    $contact = "+60" . $contactRaw;
    $icNumber = mysqli_real_escape_string($conn, trim($_POST['ic_number']));
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    
    $address1 = mysqli_real_escape_string($conn, trim($_POST['address1']));
    $address2 = mysqli_real_escape_string($conn, trim($_POST['address2']));
    $address3 = mysqli_real_escape_string($conn, trim($_POST['address3']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, trim($_POST['postal_code']));
    $country = mysqli_real_escape_string($conn, "Malaysia");
    
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    
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

    // Validation
    if (empty($name)) { $errorMessage = "Full Name is required."; } 
    elseif (empty($email)) { $errorMessage = "Email is required."; } 
    elseif (empty($contactRaw)) { $errorMessage = "Contact Number is required."; } 
    elseif (empty($icNumber)) { $errorMessage = "IC Number is required."; } 
    elseif (empty($dob)) { $errorMessage = "Date of Birth is required."; }
    elseif (!preg_match('/^[a-zA-Z\s]+$/', $name)) { $errorMessage = "Name can only contain letters and spaces."; } 
    elseif (strpos($email, '@') === false) { $errorMessage = "Invalid email: Missing '@' symbol."; } 
    elseif (!preg_match('/@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) { $errorMessage = "Invalid email domain."; } 
    else {
        $phoneParts = explode('-', $contactRaw);
        if (count($phoneParts) != 2) {
            $errorMessage = "Invalid phone format. Use 12-3456789.";
        } else {
            $backDigits = $phoneParts[1];
            if (strlen($backDigits) < 7 || strlen($backDigits) > 8) {
                $errorMessage = "Invalid phone number: Must be 7-8 digits after hyphen.";
            }
        }
    }

    if (!isset($errorMessage)) {
        if (!validateIcDobMatch($icNumber, $dob)) {
            $errorMessage = "IC Number and Date of Birth do not match.";
        }
    }

    if (!isset($errorMessage)) {
        if (!empty($dob)) {
            $birthDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            if ($age < 18) {
                $errorMessage = "Admin must be at least 18 years old.";
            }
        }

        if (!isset($errorMessage)) {
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
                    Admin_Role = '$role',
                    Admin_Status = '$status',
                    Admin_Comment = '$comment'
                    $picSql
                    WHERE Admin_ID = $editId";
            
            if ($conn->query($sql)) {
                $successMessage = "Admin info updated successfully!";
            } else {
                $errorMessage = "Error updating admin: " . $conn->error;
            }
        }
    }

    if (!empty($successMessage)) { header("Location: admin_management_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: admin_management_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// --- HANDLE BLOCK ADMIN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['block_admin'])) {
    if ($adminPosition === 'Super Admin') {
        $blockId = intval($_POST['block_admin_id']);
        if ($blockId == $_SESSION['admin_id']) {
            $errorMessage = "You cannot block your own account!";
            header("Location: admin_management_page.php?error=" . urlencode($errorMessage));
            exit();
        } else {
            $blockSql = "UPDATE admin SET Is_Deleted = 1 WHERE Admin_ID = $blockId";
            if ($conn->query($blockSql)) {
                $successMessage = "Admin blocked successfully!";
                header("Location: admin_management_page.php?success=" . urlencode($successMessage));
                exit();
            } else {
                $errorMessage = "Error blocking admin: " . $conn->error;
                header("Location: admin_management_page.php?error=" . urlencode($errorMessage));
                exit();
            }
        }
    } else {
        $errorMessage = "Permission Denied. Only Super Admin can block.";
        header("Location: admin_management_page.php?error=" . urlencode($errorMessage));
        exit();
    }
}

// --- PAGINATION & SEARCH & FILTER ---
$results_per_page = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$start_from = ($page - 1) * $results_per_page;

$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";
$filterType = isset($_GET['filter_type']) ? $_GET['filter_type'] : "";
$filterValue = "";

$conditions = [];
$conditions[] = "Is_Deleted = 0";
$orderClause = "ORDER BY Admin_CreatedAt DESC"; 

if (!empty($searchTerm)) {
    $conditions[] = "(Admin_Name LIKE '%$searchTerm%' 
                     OR Admin_Email LIKE '%$searchTerm%' 
                     OR Admin_ID LIKE '%$searchTerm%')";
}

if (!empty($filterType)) {
    if ($filterType == 'role' && !empty($_GET['filter_val_role'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_role']);
        $conditions[] = "Admin_Role = '$filterValue'";
    }
    elseif ($filterType == 'status' && !empty($_GET['filter_val_status'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_status']);
        $conditions[] = "Admin_Status = '$filterValue'";
    }
    // New Filters
    elseif ($filterType == 'name_sort' && isset($_GET['filter_val_name']) && !empty($_GET['filter_val_name'])) {
        $filterValue = $_GET['filter_val_name'];
        if ($filterValue == 'asc') $orderClause = "ORDER BY Admin_Name ASC";
        elseif ($filterValue == 'desc') $orderClause = "ORDER BY Admin_Name DESC";
    }
    elseif ($filterType == 'id_sort' && isset($_GET['filter_val_id']) && !empty($_GET['filter_val_id'])) {
        $filterValue = $_GET['filter_val_id'];
        if ($filterValue == 'asc') $orderClause = "ORDER BY Admin_ID ASC";
        elseif ($filterValue == 'desc') $orderClause = "ORDER BY Admin_ID DESC";
    }
    elseif ($filterType == 'phone' && isset($_GET['filter_val_phone']) && !empty($_GET['filter_val_phone'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_phone']);
        $conditions[] = "Admin_ContactNumber LIKE '%$filterValue%'";
    }
    elseif ($filterType == 'city' && isset($_GET['filter_val_city']) && !empty($_GET['filter_val_city'])) {
        $filterValue = mysqli_real_escape_string($conn, $_GET['filter_val_city']);
        $conditions[] = "Admin_City = '$filterValue'";
    }
}

$whereClause = "";
if (count($conditions) > 0) {
    $whereClause = "WHERE " . implode(' AND ', $conditions);
}

$count_sql = "SELECT COUNT(*) as total FROM admin $whereClause";
$count_result = $conn->query($count_sql);
$total_admins = 0;
if ($count_result && $count_result->num_rows > 0) {
    $row = $count_result->fetch_assoc();
    $total_admins = $row['total'];
}

$total_pages = ceil($total_admins / $results_per_page);
if ($page > $total_pages && $total_pages > 0) { $page = $total_pages; $start_from = ($page - 1) * $results_per_page; }

$sql = "SELECT * FROM admin $whereClause $orderClause LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$admins = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
}

$start_record = ($total_admins > 0) ? $start_from + 1 : 0;
$end_record = min($page * $results_per_page, $total_admins);

$totalAdminsCount = $conn->query("SELECT COUNT(*) as total FROM admin WHERE Is_Deleted = 0")->fetch_assoc()['total'];
$activeAdminsCount = $conn->query("SELECT COUNT(*) as total FROM admin WHERE Admin_Status = 'Active' AND Is_Deleted = 0")->fetch_assoc()['total'];

$malaysiaStates = [ 'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu' ];
$phonePrefixes = ['010', '011', '012', '013', '014', '015', '016', '017', '018', '019'];

// Get distinct cities for filter
$cities = [];
$cityQ = $conn->query("SELECT DISTINCT Admin_City FROM admin WHERE Is_Deleted = 0 AND Admin_City != '' ORDER BY Admin_City ASC");
if($cityQ) while($c = $cityQ->fetch_assoc()) $cities[] = $c['Admin_City'];

// Default placeholder for lightbox
$defaultAvatarPlaceholder = "https://via.placeholder.com/500x500.png?text=No+Profile+Picture";
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
        /* Specific Styles */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .stat-desc { font-size: 12px; display: flex; align-items: center; gap: 5px; font-weight: 500; }
        .stat-desc.text-success { color: #28a745; } .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }

        .admin-management { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; }
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; text-decoration: none; font-size: 14px;}
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        .admin-search { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; flex-wrap: wrap; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; min-width: 200px; }
        .search-input:focus { border-color: var(--primary); }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background-color: white; min-width: 140px; cursor: pointer; }
        .filter-select:focus { border-color: var(--primary); }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* Grid */
        .staff-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .staff-card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.3s, box-shadow 0.3s; position: relative; display: flex; flex-direction: column; border: 1px solid #eee; }
        .staff-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: #F28585; }
        .card-header-actions { position: absolute; top: 15px; right: 15px; z-index: 10; }
        .card-body { padding: 25px 20px 20px; text-align: center; flex: 1; }
        
        .card-avatar { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; background: #ffe5e5; color: #F28585; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 28px; object-fit: cover; border: 3px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.3s; position: relative; cursor: pointer; }
        .card-avatar:hover { transform: scale(1.05); border-color: var(--primary); }
        .card-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .card-name { font-size: 18px; font-weight: 700; color: #333; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-role { font-size: 14px; color: #666; margin-bottom: 12px; display: inline-block; background: #f8f9fa; padding: 4px 12px; border-radius: 20px; font-weight: 500; }
        .card-status { display: inline-block; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 12px; letter-spacing: 0.3px; margin-bottom: 15px; }
        .status-active { background-color: #e6f4ea; color: #1e7e34; }
        .status-inactive { background-color: #fce8e6; color: #c5221f; }
        .card-footer { background: #fcfcfc; border-top: 1px solid #f0f0f0; padding: 15px 20px; font-size: 13px; color: #555; text-align: left; }
        .contact-item { display: flex; align-items: center; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .contact-item i { width: 20px; color: #aaa; text-align: center; margin-right: 8px; }

        .action-menu { position: relative; display: inline-block; }
        .menu-btn { width: 32px; height: 32px; border-radius: 50%; background: white; border: 1px solid #eee; box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: #777; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; }
        .menu-btn:hover { background: #f8f9fa; color: var(--primary); border-color: #ddd; transform: translateY(-1px); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; text-align: left; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 13px; cursor: pointer; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: var(--primary); }
        .text-delete { color: var(--danger) !important; border-top: 1px solid #eee; }

        /* Modals & Forms */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--gray-light); }
        .modal-header h2 { font-size: 18px; font-weight: 600; margin: 0; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray); }
        .modal-body { padding: 20px; }

        .form-row { display: flex; gap: 15px; margin-bottom: 15px; } .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 15px; } .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-input:read-only, .form-textarea:read-only { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; }

        .file-upload { text-align: center; margin-bottom: 20px; }
        .profile-picture-preview { width: 120px; height: 120px; border-radius: 50%; border: 4px solid #f8f9fa; margin: 0 auto 15px; background: #eee; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .profile-picture-preview img { width: 100%; height: 100%; object-fit: cover; }
        .default-avatar-icon { font-size: 48px; color: #ccc; }
        .file-upload-label { display: inline-block; padding: 8px 15px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 5px; cursor: pointer; font-size: 13px; transition: all 0.3s; }
        .file-upload-label:hover { border-color: var(--primary); background: #fff5f5; color: var(--primary); }
        .file-upload input[type="file"] { display: none; }
        .file-info { display: none; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; background: #f1f1f1; padding: 5px 10px; border-radius: 5px; }
        .file-info.active { display: inline-flex; }
        .file-remove { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 14px; padding: 0 5px; }

        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        .required { color: red; margin-left: 3px; font-weight: bold; }

        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); font-weight: bold; }
        .phone-input { border-radius: 0 5px 5px 0 !important; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; animation: slideIn 0.3s;}
        .floating-alert div { line-height: 1.6; }
        .floating-alert i { margin-top: 4px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 15px 0; border-top: 1px solid #eee; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; }

        /* Lightbox - Simple No Arrows */
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 2002; }
        .close-lightbox:hover { color: #bbb; text-decoration: none; }

        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>
    
    <div class="floating-alert floating-alert-success" id="floatingSuccess" style="display: <?php echo isset($_GET['success']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-check-circle"></i>
        <div id="floatingSuccessText"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div>
    </div>

    <div class="floating-alert floating-alert-danger" id="floatingError" style="display: <?php echo isset($_GET['error']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-exclamation-circle"></i>
        <div id="floatingErrorText"><?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?></div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section"><h1>Admin Management</h1><p>Manage all administrators in the system.</p></div>
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-info"><h3>TOTAL ADMINS</h3><h2><?php echo $totalAdminsCount; ?></h2><p class="stat-desc text-success"><i class="fas fa-users"></i> Total registered administrators</p></div><div class="stat-icon"><i class="fas fa-user-shield"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3>ACTIVE ADMINS</h3><h2><?php echo $activeAdminsCount; ?></h2><p class="stat-desc text-success"><i class="fas fa-check-circle"></i> Currently active</p></div><div class="stat-icon"><i class="fas fa-user-check"></i></div></div>
            </div>

            <div class="admin-management">
                <div class="section-header">
                    <h2>Admin List</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="openAddAdminModal()"><i class="fas fa-plus"></i> Add New Admin</button>
                        <form method="POST" action="admin_management_page.php" style="display:inline;">
                            <button type="submit" name="export_excel" class="btn btn-success"><i class="fas fa-download"></i> Export Data</button>
                        </form>
                    </div>
                </div>
                
                <form method="GET" action="admin_management_page.php" class="admin-search">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="role" <?php echo ($filterType == 'role') ? 'selected' : ''; ?>>Role</option>
                            <option value="status" <?php echo ($filterType == 'status') ? 'selected' : ''; ?>>Status</option>
                            <option value="name_sort" <?php echo ($filterType == 'name_sort') ? 'selected' : ''; ?>>Name Sorting</option>
                            <option value="id_sort" <?php echo ($filterType == 'id_sort') ? 'selected' : ''; ?>>Admin ID</option>
                            <option value="phone" <?php echo ($filterType == 'phone') ? 'selected' : ''; ?>>Phone Prefix</option>
                            <option value="city" <?php echo ($filterType == 'city') ? 'selected' : ''; ?>>City</option>
                        </select>
                    </div>

                    <div id="filter_role_container" class="secondary-filter"><select name="filter_val_role" class="filter-select"><option value="">Select Role...</option><option value="Admin" <?php if($filterType == 'role' && $filterValue == 'Admin') echo 'selected'; ?>>Admin</option><option value="Super Admin" <?php if($filterType == 'role' && $filterValue == 'Super Admin') echo 'selected'; ?>>Super Admin</option></select></div>
                    <div id="filter_status_container" class="secondary-filter"><select name="filter_val_status" class="filter-select"><option value="">Select Status...</option><option value="Active" <?php if($filterType == 'status' && $filterValue == 'Active') echo 'selected'; ?>>Active</option><option value="Inactive" <?php if($filterType == 'status' && $filterValue == 'Inactive') echo 'selected'; ?>>Inactive</option></select></div>
                    <div id="filter_name_container" class="secondary-filter"><select name="filter_val_name" class="filter-select"><option value="">Select Order...</option><option value="asc" <?php if($filterValue == 'asc') echo 'selected'; ?>>Name (A-Z)</option><option value="desc" <?php if($filterValue == 'desc') echo 'selected'; ?>>Name (Z-A)</option></select></div>
                    <div id="filter_id_container" class="secondary-filter"><select name="filter_val_id" class="filter-select"><option value="">Select Order...</option><option value="asc" <?php if($filterValue == 'asc') echo 'selected'; ?>>ID (Ascending)</option><option value="desc" <?php if($filterValue == 'desc') echo 'selected'; ?>>ID (Descending)</option></select></div>
                    <div id="filter_phone_container" class="secondary-filter"><select name="filter_val_phone" class="filter-select"><option value="">Select Prefix...</option><?php foreach($phonePrefixes as $pp): ?><option value="<?php echo $pp; ?>" <?php if($filterValue == $pp) echo 'selected'; ?>>+6<?php echo $pp; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_city_container" class="secondary-filter"><select name="filter_val_city" class="filter-select"><option value="">Select City...</option><?php foreach($cities as $c): ?><option value="<?php echo $c; ?>" <?php if($filterValue == $c) echo 'selected'; ?>><?php echo $c; ?></option><?php endforeach; ?></select></div>

                    <input type="text" name="search" class="search-input" placeholder="Search by Name, ID or Email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($searchTerm) || !empty($filterType)): ?>
                        <a href="admin_management_page.php" class="btn btn-danger" style="background-color: #dc3545; padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
                
                <div class="staff-grid">
                    <?php if (count($admins) > 0): foreach($admins as $admin): ?>
                    <div class="staff-card">
                        <div class="card-header-actions">
                            <div class="action-menu">
                                <button class="menu-btn" onclick="toggleMenu(event, <?php echo $admin['Admin_ID']; ?>)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div id="menu-<?php echo $admin['Admin_ID']; ?>" class="dropdown-content">
                                    <div onclick="openViewAdminModal(<?php echo htmlspecialchars(json_encode($admin)); ?>)"><i class="fas fa-eye"></i> View Details</div>
                                    <div onclick='openEditAdminModal(<?php echo json_encode($admin); ?>)'><i class="fas fa-edit"></i> Edit Details</div>
                                    
                                    <?php if ($adminPosition === 'Super Admin'): ?>
                                    <div onclick="openBlockAdminModal(<?php echo $admin['Admin_ID']; ?>, '<?php echo htmlspecialchars($admin['Admin_Name'], ENT_QUOTES); ?>')" class="text-delete"><i class="fas fa-ban"></i> Block Admin</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <?php 
                                $lightboxSrc = !empty($admin['Admin_ProfilePicture']) && file_exists($admin['Admin_ProfilePicture']) ? $admin['Admin_ProfilePicture'] : $defaultAvatarPlaceholder;
                            ?>
                            <div class="card-avatar" onclick="openLightbox('<?php echo $lightboxSrc; ?>')">
                                <?php if (!empty($admin['Admin_ProfilePicture']) && file_exists($admin['Admin_ProfilePicture'])): ?>
                                    <img src="<?php echo htmlspecialchars($admin['Admin_ProfilePicture']); ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo substr($admin['Admin_Name'], 0, 1); ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-name"><?php echo htmlspecialchars($admin['Admin_Name']); ?></div>
                            <div class="card-role"><?php echo htmlspecialchars($admin['Admin_Role']); ?></div>
                            
                            <div>
                                <span class="card-status <?php echo ($admin['Admin_Status'] == 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo htmlspecialchars($admin['Admin_Status'] ?? 'Active'); ?>
                                </span>
                            </div>
                            
                            <div style="font-size: 12px; color: #999; margin-top: 5px;">ID: #<?php echo str_pad($admin['Admin_ID'], 4, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        
                        <div class="card-footer">
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admin['Admin_Email']); ?>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($admin['Admin_ContactNumber']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div style="grid-column: 1 / -1; text-align:center; padding:40px; color:#888; background:#f9f9f9; border-radius:10px;">
                        <i class="fas fa-search" style="font-size:40px; color:#ddd; margin-bottom:10px;"></i>
                        <p>No active admins found matching your criteria.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_admins; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        $queryParams = [];
                        if (!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if (!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if ($filterType == 'role' && !empty($filterValue)) $queryParams['filter_val_role'] = $filterValue;
                            if ($filterType == 'status' && !empty($filterValue)) $queryParams['filter_val_status'] = $filterValue;
                            if ($filterType == 'name_sort' && !empty($filterValue)) $queryParams['filter_val_name'] = $filterValue;
                            if ($filterType == 'id_sort' && !empty($filterValue)) $queryParams['filter_val_id'] = $filterValue;
                            if ($filterType == 'phone' && !empty($filterValue)) $queryParams['filter_val_phone'] = $filterValue;
                            if ($filterType == 'city' && !empty($filterValue)) $queryParams['filter_val_city'] = $filterValue;
                        }
                        $search_query = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                        
                        if ($page > 1) echo '<a href="?page=' . ($page - 1) . $search_query . '" class="pagination-btn">Previous</a>';
                        else echo '<span class="pagination-btn disabled">Previous</span>';
                        
                        $start_window = max(1, $page - 1);
                        $end_window = min($total_pages, $page + 1);
                        if ($page == 1) $end_window = min($total_pages, 3);
                        if ($page == $total_pages) $start_window = max(1, $total_pages - 2);

                        for ($i = $start_window; $i <= $end_window; $i++) {
                            if ($i == $page) echo '<span class="pagination-btn active">' . $i . '</span>';
                            else echo '<a href="?page=' . $i . $search_query . '" class="pagination-btn">' . $i . '</a>';
                        }
                        
                        if ($page < $total_pages) echo '<a href="?page=' . ($page + 1) . $search_query . '" class="pagination-btn">Next</a>';
                        else echo '<span class="pagination-btn disabled">Next</span>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="addAdminModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Administrator</h2><button class="close-btn" onclick="closeAddAdminModal()">&times;</button></div>
            <div class="modal-body">
                <form id="addAdminForm" action="admin_management_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm('add')" novalidate>
                    <input type="hidden" name="add_admin" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Profile Picture</label>
                        <div class="profile-picture-preview" id="add-preview-container"><div class="default-avatar-icon"><i class="fas fa-user"></i></div></div>
                        <div class="file-upload">
                            <label for="add_profile_picture" class="file-upload-label"><i class="fas fa-upload"></i> Choose Profile Picture</label>
                            <input type="file" id="add_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'add-preview-container', 'add-file-info', 'add-file-name')">
                            <div id="add-file-info" class="file-info"><span id="add-file-name" class="file-name"></span><button type="button" class="file-remove" onclick="removeImage('add_profile_picture', 'add-preview-container', 'add-file-info')"><i class="fas fa-times"></i></button></div>
                        </div>
                        <span class="form-guide" style="display:block; text-align:center;">Upload a clear photo (JPG/PNG).</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-input" required placeholder="e.g. John Doe" oninput="validateName(this)">
                        <span class="form-guide">Enter full name as per IC. English letters only.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-input" required onblur="validateEmail('email', 'emailError')" placeholder="e.g. admin@example.com">
                            <span class="form-guide">Valid email address for login.</span>
                            <div id="emailError" class="error-message">Invalid email format.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="add_contact" name="contact" class="form-input phone-input" required maxlength="11" placeholder="12-3456789">
                            </div>
                            <span class="form-guide">Format: 12-3456789 (No need for +60).</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number <span class="required">*</span></label>
                            <input type="text" id="ic_number" name="ic_number" class="form-input" required maxlength="14" placeholder="XXXXXX-XX-XXXX">
                            <span class="form-guide">Format: YYMMDD-PB-#### (e.g. 990101-07-1234).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth <span class="required">*</span></label>
                            <input type="date" id="dob" name="dob" class="form-input" required onchange="validateAge('dob', 'ageError')">
                            <span class="form-guide">Select birth date from calendar.</span>
                            <div id="ageError" class="error-message">Must be at least 18 years old.</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" name="address1" class="form-input" placeholder="e.g. No. 123, Jalan Example">
                        <span class="form-guide">House unit no., floor, building, street name.</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" name="address2" class="form-input" placeholder="e.g. Taman Sri">
                        <span class="form-guide">Residential area, village, or section.</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" class="form-input" placeholder="Address Line 3 (Optional)">
                        <span class="form-guide">Additional address info.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-input" placeholder="e.g. Kuala Lumpur">
                            <span class="form-guide">City or district name.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <select id="state" name="state" class="form-select">
                                <option value="">Select State</option>
                                <?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?>
                            </select>
                            <span class="form-guide">Select state from dropdown.</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input" oninput="autoSelectState('postal_code', 'state')" placeholder="e.g. 50000">
                            <span class="form-guide">5-digit postcode.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-input" value="Malaysia" readonly>
                            <span class="form-guide">Default country is Malaysia.</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Role <span class="required">*</span></label>
                            <select name="role" class="form-select" required><option value="Admin">Admin</option><option value="Super Admin">Super Admin</option></select>
                            <span class="form-guide">Access Level.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <select name="status" class="form-select" required><option value="Active">Active</option><option value="Inactive">Inactive</option></select>
                            <span class="form-guide">Account Status.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description / Comment</label>
                        <textarea name="comment" class="form-textarea" rows="2" placeholder="e.g. Department details..."></textarea>
                        <span class="form-guide">Optional internal notes.</span>
                    </div>
                    
                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Admin</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="editAdminModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Administrator Info</h2><button class="close-btn" onclick="closeEditAdminModal()">&times;</button></div>
            <div class="modal-body">
                <form id="editAdminForm" action="admin_management_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm('edit')" novalidate>
                    <input type="hidden" name="update_admin" value="1">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    
                    <div class="form-group">
                        <label class="form-label">Profile Picture</label>
                        <div class="profile-picture-preview" id="edit-preview-container"><div class="default-avatar-icon"><i class="fas fa-user"></i></div></div>
                        <div class="file-upload">
                            <label for="edit_profile_picture" class="file-upload-label"><i class="fas fa-upload"></i> Change Profile Picture</label>
                            <input type="file" id="edit_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'edit-preview-container', 'edit-file-info', 'edit-file-name')">
                            <div id="edit-file-info" class="file-info"><span id="edit-file-name" class="file-name"></span><button type="button" class="file-remove" onclick="removeImage('edit_profile_picture', 'edit-preview-container', 'edit-file-info')"><i class="fas fa-times"></i></button></div>
                        </div>
                        <span class="form-guide" style="display:block; text-align:center;">Update photo if necessary.</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" id="edit_name" name="name" class="form-input" required placeholder="e.g. John Doe" oninput="validateName(this)">
                        <span class="form-guide">Enter full name as per IC. English letters only.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="edit_email" name="email" class="form-input" required placeholder="e.g. admin@example.com" onblur="validateEmail('edit_email', 'editEmailError')">
                            <span class="form-guide">Valid email address for login.</span>
                            <div id="editEmailError" class="error-message">Invalid email format.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="edit_contact" name="contact" class="form-input phone-input" required maxlength="11" placeholder="12-3456789">
                            </div>
                            <span class="form-guide">Format: 12-3456789 or 11-23456789 (No need for +60).</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number <span class="required">*</span></label>
                            <input type="text" id="edit_ic_number" name="ic_number" class="form-input" required maxlength="14" placeholder="XXXXXX-XX-XXXX">
                            <span class="form-guide">Format: YYMMDD-PB-#### (e.g. 990101-07-1234).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth <span class="required">*</span></label>
                            <input type="date" id="edit_dob" name="dob" class="form-input" required onchange="validateAge('edit_dob', 'editAgeError')">
                            <span class="form-guide">Select birth date from calendar.</span>
                            <div id="editAgeError" class="error-message">Must be at least 18 years old.</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" id="edit_address1" name="address1" class="form-input" placeholder="e.g. No. 123, Jalan Example">
                        <span class="form-guide">House unit no., floor, building, street name.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" id="edit_address2" name="address2" class="form-input" placeholder="e.g. Taman Sri">
                        <span class="form-guide">Residential area, village, or section.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" id="edit_address3" name="address3" class="form-input" placeholder="Address Line 3 (Optional)">
                        <span class="form-guide">Additional address info.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" id="edit_city" name="city" class="form-input" placeholder="e.g. Kuala Lumpur">
                            <span class="form-guide">City or district name.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <select id="edit_state" name="state" class="form-select">
                                <option value="">Select State</option>
                                <?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?>
                            </select>
                            <span class="form-guide">Select state from dropdown.</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" id="edit_postal_code" name="postal_code" class="form-input" oninput="autoSelectState('edit_postal_code', 'edit_state')" placeholder="e.g. 50000">
                            <span class="form-guide">5-digit postcode.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" id="edit_country" name="country" class="form-input" value="Malaysia" readonly>
                            <span class="form-guide">Default country is Malaysia.</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Role <span class="required">*</span></label>
                            <select id="edit_role" name="role" class="form-select" required><option value="Admin">Admin</option><option value="Super Admin">Super Admin</option></select>
                            <span class="form-guide">Access Level.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <select id="edit_status" name="status" class="form-select" required><option value="Active">Active</option><option value="Inactive">Inactive</option></select>
                            <span class="form-guide">Account Status.</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description / Comment</label>
                        <textarea id="edit_comment" name="comment" class="form-textarea" rows="2" placeholder="e.g. Department details..."></textarea>
                        <span class="form-guide">Optional internal notes.</span>
                    </div>

                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Info</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="viewAdminModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Admin Details</h2><button class="close-btn" onclick="closeViewAdminModal()">&times;</button></div>
            <div class="modal-body">
                <div id="view_profile_container" class="profile-picture-preview" style="margin: 0 auto 20px;">
                    <img id="view_profile_picture" src="" alt="Admin Photo" style="display:none; width:100%; height:100%; object-fit:cover;">
                    <div id="view_default_icon" class="default-avatar-icon"><i class="fas fa-user"></i></div>
                </div>
                
                <div class="form-group"><label class="form-label">Full Name</label><input type="text" id="view_fullname" class="form-input" readonly></div>
                <div class="form-row"><div class="form-group"><label class="form-label">Email</label><input type="text" id="view_email" class="form-input" readonly></div><div class="form-group"><label class="form-label">Contact</label><input type="text" id="view_contact" class="form-input" readonly></div></div>
                <div class="form-row"><div class="form-group"><label class="form-label">IC Number</label><input type="text" id="view_ic" class="form-input" readonly></div><div class="form-group"><label class="form-label">DOB</label><input type="text" id="view_dob" class="form-input" readonly></div></div>
                <div class="form-group"><label class="form-label">Address</label><textarea id="view_address" class="form-textarea" readonly rows="3"></textarea></div>
                <div class="form-row"><div class="form-group"><label class="form-label">Role</label><input type="text" id="view_role" class="form-input" readonly></div><div class="form-group"><label class="form-label">Status</label><input type="text" id="view_status" class="form-input" readonly></div></div>
                <div class="form-group"><label class="form-label">Comment</label><textarea id="view_comment" class="form-textarea" readonly rows="2"></textarea></div>
            </div>
        </div>
    </div>

    <div class="modal" id="blockAdminModal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header" style="background-color: #dc3545; color: white;">
                <h2 style="font-size: 16px;"><i class="fas fa-exclamation-triangle"></i> Block Admin</h2>
                <button class="close-btn" onclick="closeModal('blockAdminModal')" style="color: white;">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center; padding: 30px;">
                <form method="POST" action="admin_management_page.php">
                    <input type="hidden" name="block_admin" value="1">
                    <input type="hidden" name="block_admin_id" id="block_admin_id">
                    
                    <i class="fas fa-ban" style="font-size: 50px; color: #dc3545; margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 10px;">Block this admin?</h3>
                    <p style="color: #666; font-size: 14px; margin-bottom: 25px;">
                        Are you sure you want to block <strong id="block_admin_name_display"></strong>?<br>
                        They will not be able to log in.
                    </p>
                    
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button type="button" class="btn" style="background: #eee; color: #333;" onclick="closeModal('blockAdminModal')">Cancel</button>
                        <button type="submit" class="btn btn-danger">Yes, Block</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="imageLightbox" class="lightbox-modal">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <img class="lightbox-content" id="lightboxImage">
    </div>
    
    <script>
        // Filters Logic
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => {
                el.classList.remove('active');
                if(el.querySelector('select')) el.querySelector('select').disabled = true;
            });
            if (type === 'role') {
                const el = document.getElementById('filter_role_container');
                el.classList.add('active');
                if(el.querySelector('select')) el.querySelector('select').disabled = false;
            } else if (type === 'status') {
                const el = document.getElementById('filter_status_container');
                el.classList.add('active');
                if(el.querySelector('select')) el.querySelector('select').disabled = false;
            } else if (type === 'name_sort') {
                const el = document.getElementById('filter_name_container');
                el.classList.add('active');
                if(el.querySelector('select')) el.querySelector('select').disabled = false;
            } else if (type === 'id_sort') {
                const el = document.getElementById('filter_id_container');
                el.classList.add('active');
                if(el.querySelector('select')) el.querySelector('select').disabled = false;
            } else if (type === 'phone') {
                const el = document.getElementById('filter_phone_container');
                el.classList.add('active');
                if(el.querySelector('select')) el.querySelector('select').disabled = false;
            } else if (type === 'city') {
                const el = document.getElementById('filter_city_container');
                el.classList.add('active');
                if(el.querySelector('select')) el.querySelector('select').disabled = false;
            }
        }

        // --- NEW: SYSTEM ALERT FUNCTION ---
        function showSystemError(messageHTML) {
            const errorBox = document.getElementById('floatingError');
            const errorText = document.getElementById('floatingErrorText');
            if(errorBox && errorText) {
                errorText.innerHTML = messageHTML;
                errorBox.style.display = 'flex';
                // Auto hide after 5 seconds
                setTimeout(() => { 
                    errorBox.style.display = 'none'; 
                }, 5000);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            setupPhoneInput('add_contact'); 
            setupPhoneInput('edit_contact');
            setupICInput('ic_number', 'dob'); 
            setupICInput('edit_ic_number', 'edit_dob'); 
            
            // Auto hide alerts that came from PHP
            const s = document.getElementById('floatingSuccess');
            const e = document.getElementById('floatingError');
            if(s && s.style.display === 'flex') setTimeout(() => { s.style.display='none'; }, 5000);
            if(e && e.style.display === 'flex') setTimeout(() => { e.style.display='none'; }, 5000);
            
            window.onclick = function(e) { 
                if (!e.target.matches('.menu-btn') && !e.target.matches('.menu-btn *')) { 
                    document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); 
                } 
                if (e.target.classList.contains('modal')) e.target.style.display = "none"; 
                if (e.target.id == 'imageLightbox') closeLightbox();
            }

            document.addEventListener('keydown', function(event) {
                if (event.key === "Escape" && document.getElementById('imageLightbox').style.display === "flex") { closeLightbox(); }
            });
        });

        // Dropdown Logic
        function toggleMenu(e, id) { 
            e.stopPropagation(); 
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); 
            document.getElementById('menu-' + id).style.display = 'block'; 
        }

        // --- Image Preview Logic ---
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

        // --- Modal Control Functions ---
        function openAddAdminModal() { 
            const dob = document.getElementById('dob');
            dob.readOnly = false;
            dob.style.backgroundColor = "";
            dob.style.color = "";
            dob.style.cursor = "";
            document.getElementById('addAdminModal').style.display = 'flex'; 
        }
        function closeAddAdminModal() { 
            document.getElementById('addAdminModal').style.display = 'none'; 
            document.getElementById('addAdminForm').reset();
            document.getElementById('add-preview-container').innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            document.getElementById('add-file-info').style.display = 'none';
        }
        
        function openEditAdminModal(admin) {
            document.getElementById('editAdminModal').style.display = 'flex';
            document.getElementById('edit_admin_id').value = admin.Admin_ID;
            document.getElementById('edit_name').value = admin.Admin_Name;
            document.getElementById('edit_email').value = admin.Admin_Email;
            
            let cleanContact = admin.Admin_ContactNumber;
            if(cleanContact.startsWith('+60')) cleanContact = cleanContact.substring(3);
            document.getElementById('edit_contact').value = cleanContact;
            
            let icInput = document.getElementById('edit_ic_number');
            icInput.value = admin.Admin_ICNUMBER;
            if (typeof icInput.dispatchEvent === "function") {
                 icInput.dispatchEvent(new Event('input'));
            }

            const dobInput = document.getElementById('edit_dob');
            if(admin.Admin_DOB) dobInput.value = new Date(admin.Admin_DOB).toISOString().split('T')[0];
            
            document.getElementById('edit_address1').value = admin.Admin_Address1;
            document.getElementById('edit_address2').value = admin.Admin_Address2;
            document.getElementById('edit_address3').value = admin.Admin_Address3;
            document.getElementById('edit_city').value = admin.Admin_City;
            document.getElementById('edit_state').value = admin.Admin_State;
            document.getElementById('edit_postal_code').value = admin.Admin_PostalCode;
            document.getElementById('edit_role').value = admin.Admin_Role;
            document.getElementById('edit_status').value = admin.Admin_Status || 'Active';
            document.getElementById('edit_comment').value = admin.Admin_Comment ? admin.Admin_Comment : '';
            
            const container = document.getElementById('edit-preview-container');
            if (admin.Admin_ProfilePicture) {
                container.innerHTML = `<img src="${admin.Admin_ProfilePicture}" alt="Preview">`;
            } else {
                container.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            }
            
            // Re-trigger visual updates for locked fields if needed
            document.getElementById('edit_ic_number').dispatchEvent(new Event('input'));
        }
        function closeEditAdminModal() { document.getElementById('editAdminModal').style.display = 'none'; document.getElementById('editAdminForm').reset(); }

        function openViewAdminModal(admin) {
            const img = document.getElementById('view_profile_picture');
            const icon = document.getElementById('view_default_icon');
            
            if (admin.Admin_ProfilePicture) {
                img.src = admin.Admin_ProfilePicture;
                img.style.display = 'block';
                icon.style.display = 'none';
            } else {
                img.style.display = 'none';
                icon.style.display = 'flex';
            }

            document.getElementById('view_fullname').value = admin.Admin_Name;
            document.getElementById('view_email').value = admin.Admin_Email;
            document.getElementById('view_contact').value = admin.Admin_ContactNumber;
            document.getElementById('view_ic').value = admin.Admin_ICNUMBER;
            document.getElementById('view_dob').value = admin.Admin_DOB;
            
            let address = admin.Admin_Address1;
            if(admin.Admin_Address2) address += ", " + admin.Admin_Address2;
            if(admin.Admin_Address3) address += ", " + admin.Admin_Address3;
            address += "\n" + admin.Admin_PostalCode + " " + admin.Admin_City + ", " + admin.Admin_State;
            
            document.getElementById('view_address').value = address;
            document.getElementById('view_role').value = admin.Admin_Role;
            document.getElementById('view_status').value = admin.Admin_Status;
            document.getElementById('view_comment').value = admin.Admin_Comment;

            document.getElementById('viewAdminModal').style.display = 'flex';
        }
        function closeViewAdminModal() { document.getElementById('viewAdminModal').style.display = 'none'; }

        function openBlockAdminModal(id, name) {
            document.getElementById('block_admin_id').value = id;
            document.getElementById('block_admin_name_display').textContent = name;
            document.getElementById('blockAdminModal').style.display = 'flex';
        }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        // --- NEW: LIGHTBOX FUNCTIONS ---
        function openLightbox(imageSrc) { 
            if (!imageSrc) return; 
            document.getElementById('lightboxImage').src = imageSrc; 
            document.getElementById('imageLightbox').style.display = "flex"; 
        }
        function closeLightbox() { 
            document.getElementById('imageLightbox').style.display = "none"; 
        }

        // --- Helper Functions (Formatting & Validation) ---
        function setupPhoneInput(id) { 
            const el = document.getElementById(id); 
            if(!el) return; 
            el.addEventListener('input', function() { 
                let v = this.value.replace(/\D/g, '');
                if (v.length > 11) v = v.substring(0, 11);
                let newVal = v;
                if (v.length > 2) {
                    newVal = v.substring(0, 2) + '-' + v.substring(2);
                } 
                this.value = newVal;
            }); 
        }
        
        function setupICInput(inputId, dobInputId) {
            const input = document.getElementById(inputId); 
            const dobInput = document.getElementById(dobInputId); 
            if(!input) return;
            input.addEventListener('input', function(e) {
                let val = this.value.replace(/\D/g, ''); 
                if (val.length > 12) val = val.substring(0, 12);
                let newVal = ''; 
                newVal += val.substring(0, 6); 
                if (val.length > 6) newVal += '-' + val.substring(6, 8); 
                if (val.length > 8) newVal += '-' + val.substring(8, 12);
                this.value = newVal;
                
                if (val.length >= 6) {
                    const yy = parseInt(val.substring(0, 2)); 
                    const mm = val.substring(2, 4); 
                    const dd = val.substring(4, 6);
                    const prefix = (yy > (new Date().getFullYear() % 100)) ? '19' : '20';
                    const fullDate = `${prefix}${val.substring(0, 2)}-${mm}-${dd}`;
                    const dateObj = new Date(fullDate);
                    
                    if (!isNaN(dateObj.getTime()) && parseInt(mm) >= 1 && parseInt(mm) <= 12 && parseInt(dd) >= 1 && parseInt(dd) <= 31) { 
                        dobInput.value = fullDate;
                        dobInput.readOnly = true;
                        dobInput.style.backgroundColor = "#e9ecef";
                        dobInput.style.color = "#6c757d";
                        dobInput.style.cursor = "not-allowed";

                        if (inputId === 'ic_number') validateAge('dob', 'ageError');
                        if (inputId === 'edit_ic_number') validateAge('edit_dob', 'editAgeError');
                    } else {
                        dobInput.readOnly = false;
                        dobInput.style.backgroundColor = "";
                        dobInput.style.color = "";
                        dobInput.style.cursor = "";
                    }
                } else {
                    dobInput.readOnly = false;
                    dobInput.style.backgroundColor = "";
                    dobInput.style.color = "";
                    dobInput.style.cursor = "";
                }
            });
        }

        function autoSelectState(postalInputId, stateSelectId) {
            const postal = document.getElementById(postalInputId).value;
            const stateSelect = document.getElementById(stateSelectId);
            if (postal.length >= 2) {
                const prefix = parseInt(postal.substring(0, 2));
                let foundState = "";
                if ((prefix >= 50 && prefix <= 60)) foundState = "Kuala Lumpur";
                else if (prefix >= 62 && prefix <= 62) foundState = "Putrajaya";
                else if (prefix >= 40 && prefix <= 48) foundState = "Selangor";
                else if (prefix >= 63 && prefix <= 68) foundState = "Selangor";
                else if (prefix >= 79 && prefix <= 86) foundState = "Johor";
                else if (prefix >= 75 && prefix <= 78) foundState = "Melaka";
                else if (prefix >= 70 && prefix <= 73) foundState = "Negeri Sembilan";
                else if (prefix >= 30 && prefix <= 39) foundState = "Perak";
                else if (prefix >= 10 && prefix <= 14) foundState = "Penang";
                else if (prefix >= 1 && prefix <= 2) foundState = "Perlis"; 
                else if (prefix >= 5 && prefix <= 9) foundState = "Kedah"; 
                else if (prefix >= 15 && prefix <= 18) foundState = "Kelantan";
                else if (prefix >= 20 && prefix <= 24) foundState = "Terengganu";
                else if (prefix >= 25 && prefix <= 28) foundState = "Pahang";
                else if (prefix >= 88 && prefix <= 91) foundState = "Sabah";
                else if (prefix >= 93 && prefix <= 98) foundState = "Sarawak";
                else if (prefix >= 87 && prefix <= 87) foundState = "Labuan";

                if (foundState !== "") {
                    stateSelect.value = foundState;
                }
            }
        }

        function validateName(input) { input.value = input.value.replace(/[^a-zA-Z\s]/g, ''); }
        
        function validateEmail(id, errorId) { 
            const val = document.getElementById(id).value;
            const errorDiv = document.getElementById(errorId);
            const domainPattern = /@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (val.indexOf('@') === -1) {
                errorDiv.innerText = "Missing '@' symbol";
                errorDiv.style.display = 'block';
                return "Missing '@'";
            }
            if (!domainPattern.test(val)) {
                errorDiv.innerText = "Missing valid domain (e.g. .com)";
                errorDiv.style.display = 'block';
                return "Invalid domain";
            }
            errorDiv.style.display = 'none';
            return ""; 
        }
        
        function validatePhone(id) {
            const val = document.getElementById(id).value;
            if (!val.includes('-')) return "Format must be XX-XXXXXXX (missing dash)";
            const parts = val.split('-');
            if (parts.length !== 2) return "Invalid format";
            const back = parts[1];
            if (back.length < 7 || back.length > 8) return "Must be 7-8 digits after hyphen";
            return ""; 
        }
        
        function validateAge(id, errorId) { 
            const d = document.getElementById(id).value; 
            if(!d) return false; 
            const diff = new Date().getFullYear() - new Date(d).getFullYear(); 
            const valid = diff >= 18; 
            document.getElementById(errorId).style.display = valid ? 'none' : 'block'; 
            return valid; 
        }

        // --- UPDATED: REPLACED ALERT WITH CUSTOM SYSTEM ERROR ---
        function validateForm(type) { 
            let errors = [];
            let name, email, dob, contactId, contactVal, icNum;

            if (type === 'add') {
                name = document.getElementsByName('name')[0].value.trim();
                email = document.getElementById('email').value.trim();
                dob = document.getElementById('dob').value;
                contactId = 'add_contact';
                icNum = document.getElementById('ic_number').value.trim();
            } else {
                name = document.getElementById('edit_name').value.trim();
                email = document.getElementById('edit_email').value.trim();
                dob = document.getElementById('edit_dob').value;
                contactId = 'edit_contact';
                icNum = document.getElementById('edit_ic_number').value.trim();
            }

            // 1. Basic Required Field Checks (Manual because novalidate is on)
            if (!name) errors.push("Full Name is required.");
            if (!email) errors.push("Email is required.");
            if (!icNum) errors.push("IC Number is required.");
            if (!dob) errors.push("Date of Birth is required.");
            
            contactVal = document.getElementById(contactId).value.trim();
            if (!contactVal) errors.push("Contact Number is required.");

            // 2. Specific Format Validations
            if (name && /\d/.test(name)) errors.push("Name cannot contain numbers.");

            if (email) {
                let emailMsg = validateEmail(type === 'add' ? 'email' : 'edit_email', type === 'add' ? 'emailError' : 'editEmailError');
                if(emailMsg) errors.push("Email: " + emailMsg);
            }

            if (dob) {
                if (!validateAge(type === 'add' ? 'dob' : 'edit_dob', type === 'add' ? 'ageError' : 'editAgeError')) {
                    errors.push("Age: Must be 18+.");
                }
            }

            if (contactVal) {
                let phoneMsg = validatePhone(contactId);
                if(phoneMsg) errors.push("Contact: " + phoneMsg);
            }

            if (errors.length > 0) {
                // Modified: Use showSystemError instead of alert
                showSystemError("Please correct errors:<br>" + errors.join("<br>"));
                return false; 
            }
            return true; 
        }
    </script>
</body>
</html>