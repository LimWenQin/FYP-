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

// --- 0. HANDLE EXPORT TO EXCEL ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $filename = "staff_list_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // 计算 Base URL 用于图片显示
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST']; 
    $path = dirname($_SERVER['PHP_SELF']); 
    $baseUrl = rtrim($protocol . "://" . $host . $path, '/\\') . '/';

    // 【修改点 1】导出时过滤掉已删除的员工
    $sql = "SELECT * FROM staff WHERE Is_Deleted = 0 ORDER BY Staff_ID DESC";
    $result = $conn->query($sql);

    echo '<table border="1">';
    echo '<tr>
            <th style="width: 80px; background-color:#f2f2f2;">Profile Picture</th>
            <th style="background-color:#f2f2f2;">ID</th>
            <th style="background-color:#f2f2f2;">Full Name</th>
            <th style="background-color:#f2f2f2;">Email</th>
            <th style="background-color:#f2f2f2;">Contact</th>
            <th style="background-color:#f2f2f2;">IC Number</th>
            <th style="background-color:#f2f2f2;">Role</th>
            <th style="background-color:#f2f2f2;">Status</th>
            <th style="background-color:#f2f2f2;">Join Date</th>
          </tr>';

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo '<tr>';
            
            // --- 图片列 ---
            echo '<td style="text-align:center; vertical-align:middle; height:80px;">';
            if (!empty($row['Staff_ProfilePicture']) && file_exists($row['Staff_ProfilePicture'])) {
                $fullImageUrl = $baseUrl . $row['Staff_ProfilePicture'];
                echo '<img src="' . $fullImageUrl . '" width="60" height="60" style="object-fit:cover; border-radius:50%;">';
            } else {
                echo 'No Image';
            }
            echo '</td>';
            // -------------

            echo '<td style="vertical-align:middle;">' . $row['Staff_ID'] . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Staff_FullName']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Staff_Email']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Staff_ContactNumber']) . '</td>';
            echo '<td style="vertical-align:middle; mso-number-format:\'\@\'">' . htmlspecialchars($row['Staff_ICNumber']) . '</td>'; 
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Staff_Role']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Staff_Status']) . '</td>';
            echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['Staff_JoinDate']) . '</td>';
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
    $fullName = mysqli_real_escape_string($conn, trim($_POST['full_name']));
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
    
    // Role is hardcoded to Staff in logic, though form sends "Staff"
    $role = "Staff"; 
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $password = trim($_POST['password']); 
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $adminId = $_SESSION['admin_id'];

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
    elseif (strlen($contactRaw) < 9 || strlen($contactRaw) > 11) {
         $errorMessage = "Invalid phone number length.";
    }
    elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>])[A-Za-z\d!@#$%^&*()\-_=+{};:,<.>]{8,15}$/', $password)) {
        $errorMessage = "Password does not meet requirements.";
    }
    else {
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
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $dbProfilePic = $profilePicturePath ? "'$profilePicturePath'" : "NULL";

                // Is_Deleted will default to 0 in DB, so no need to change INSERT
                $sql = "INSERT INTO staff (
                    Staff_FullName, Staff_ContactNumber, Staff_ICNumber, 
                    Staff_Email, Staff_Password, Staff_DOB, 
                    Staff_Address1, Staff_Address2, Staff_Address3, 
                    Staff_City, Staff_State, Staff_PostalCode, Staff_Country,
                    Staff_Comment, Staff_Role, Staff_Status, Staff_ProfilePicture, Admin_ID, Staff_JoinDate
                ) VALUES (
                    '$fullName', '$contact', '$icNumber', 
                    '$email', '$hashedPassword', '$dob',
                    '$address1', '$address2', '$address3',
                    '$city', '$state', '$postalCode', '$country',
                    '$comment', '$role', '$status', $dbProfilePic, '$adminId', NOW()
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
    $fullName = mysqli_real_escape_string($conn, trim($_POST['full_name']));
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
    
    // Role hardcoded
    $role = "Staff";
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    $imageUpdateSQL = "";
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) {
             $oldPicQ = $conn->query("SELECT Staff_ProfilePicture FROM staff WHERE Staff_ID = $staffId");
             if ($oldRow = $oldPicQ->fetch_assoc()) {
                 if (!empty($oldRow['Staff_ProfilePicture']) && file_exists($oldRow['Staff_ProfilePicture'])) {
                     unlink($oldRow['Staff_ProfilePicture']);
                 }
             }
             $imageUpdateSQL = ", Staff_ProfilePicture = '$uploadedPath'";
        }
    }

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

// --- 3. Handle Change Password (NEW) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $targetStaffId = mysqli_real_escape_string($conn, $_POST['target_staff_id']);
    $authPassword = $_POST['auth_password']; 
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_new_password'];
    
    $loggedInAdminId = $_SESSION['admin_id'];
    
    // 1. Verify Logged-in Admin's Password (Authorization)
    $authSql = "SELECT Admin_Password FROM admin WHERE Admin_ID = $loggedInAdminId";
    $authResult = $conn->query($authSql);
    $currentUser = $authResult->fetch_assoc();
    
    if (!$currentUser || !password_verify($authPassword, $currentUser['Admin_Password'])) {
        $errorMessage = "Authorization failed: Your current admin password is incorrect.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = "New passwords do not match.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>])[A-Za-z\d!@#$%^&*()\-_=+{};:,<.>]{8,15}$/', $newPassword)) {
        $errorMessage = "New password does not meet security requirements.";
    } else {
        // 2. Update Target Staff's Password
        $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePassSql = "UPDATE staff SET Staff_Password = '$newHashedPassword' WHERE Staff_ID = $targetStaffId";
        
        if ($conn->query($updatePassSql)) {
            $successMessage = "Staff password changed successfully!";
        } else {
            $errorMessage = "Error changing password: " . $conn->error;
        }
    }
    
    if (!empty($successMessage)) { header("Location: staff_management_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: staff_management_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// --- 4. Handle Delete Staff (UPDATED: SOFT DELETE) ---
if (isset($_GET['delete_id'])) {
    $deleteId = mysqli_real_escape_string($conn, $_GET['delete_id']);
    
    // 【修改点 2】 更新 Is_Deleted 状态而不是删除整行
    $deleteSql = "UPDATE staff SET Is_Deleted = 1 WHERE Staff_ID = $deleteId";
    
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

// --- Get Admin Info (Necessary for Header) ---
$adminId = $_SESSION['admin_id'];
$adminName = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';
$adminPosition = "System Administrator";

$adminProfilePicture = null;
$sql = "SELECT Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $adminProfilePicture = $row['Admin_ProfilePicture'];
    $adminPosition = $row['Admin_Role'];
}
$stmt->close();

// --- PAGINATION & SEARCH & FILTER LOGIC ---
$results_per_page = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$search = "";
$filterType = "";
$filterValue = "";
// Removed Role filter logic since role is only Staff
$statusFilter = "";

// 【修改点 3】 默认只显示未删除的员工
$conditions = [];
$conditions[] = "Is_Deleted = 0";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $conditions[] = "(Staff_FullName LIKE '%$search%' OR 
                      Staff_Email LIKE '%$search%' OR 
                      Staff_ICNumber LIKE '%$search%')";
}

if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    if ($filterType == 'status' && isset($_GET['filter_val_status']) && !empty($_GET['filter_val_status'])) {
        $statusFilter = $conn->real_escape_string($_GET['filter_val_status']);
        $conditions[] = "Staff_Status = '$statusFilter'";
        $filterValue = $statusFilter;
    }
}

$whereClause = "";
if (count($conditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

$count_sql = "SELECT COUNT(*) as total FROM staff $whereClause";
$count_result = $conn->query($count_sql);
$total_records = 0;
if ($count_result && $count_result->num_rows > 0) {
    $row = $count_result->fetch_assoc();
    $total_records = $row['total'];
}

$total_pages = ceil($total_records / $results_per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $start_from = ($page - 1) * $results_per_page;
}

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

// 【修改点 4】 统计数据过滤掉已删除员工
function getTotalStaffStats($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM staff WHERE Is_Deleted = 0");
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
}
function getActiveStaffStats($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM staff WHERE Staff_Status = 'Active' AND Is_Deleted = 0");
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
        /* Stats Cards Specific */
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

        /* Management Layout */
        .staff-management { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-header h2 { font-size: 18px; font-weight: 600; color: #333; }
        .header-buttons { display: flex; gap: 10px; }
        .btn { padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; color: white; transition: 0.3s; font-size: 14px; text-decoration: none; }
        .btn-primary { background: #F28585; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }

        /* Search & Filter */
        .staff-search { margin-bottom: 25px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; background-color: white; color: #555; outline: none; cursor: pointer; font-size: 14px; min-width: 140px; }
        .filter-select:focus { border-color: #F28585; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; font-size: 14px; background: white; }
        .search-input:focus { border-color: #F28585; }
        
        /* Grid & Cards */
        .staff-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .staff-card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.3s, box-shadow 0.3s; position: relative; display: flex; flex-direction: column; border: 1px solid #eee; }
        .staff-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: #F28585; }
        .card-header-actions { position: absolute; top: 15px; right: 15px; z-index: 10; }
        .card-body { padding: 25px 20px 20px; text-align: center; flex: 1; }
        .card-avatar { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; background: #ffe5e5; color: #F28585; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 28px; object-fit: cover; border: 3px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .card-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .card-name { font-size: 18px; font-weight: 700; color: #333; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-role { font-size: 14px; color: #666; margin-bottom: 12px; display: inline-block; background: #f8f9fa; padding: 4px 12px; border-radius: 20px; font-weight: 500; }
        .card-status { display: inline-block; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 12px; letter-spacing: 0.3px; margin-bottom: 15px; }
        .status-active { background-color: #e6f4ea; color: #1e7e34; }
        .status-inactive { background-color: #fce8e6; color: #c5221f; }
        .card-footer { background: #fcfcfc; border-top: 1px solid #f0f0f0; padding: 15px 20px; font-size: 13px; color: #555; text-align: left; }
        .contact-item { display: flex; align-items: center; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .contact-item:last-child { margin-bottom: 0; }
        .contact-item i { width: 20px; color: #aaa; text-align: center; margin-right: 8px; }
        
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { width: 32px; height: 32px; border-radius: 50%; background: white; border: 1px solid #eee; box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: #777; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; }
        .menu-btn:hover { background: #f8f9fa; color: var(--primary); border-color: #ddd; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

        /* Modals & Forms (Local styles) */
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
        /* Expanded Form Guide Style */
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
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

        /* Alerts */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; max-width: 400px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }

        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; text-align: left; }
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
            .staff-grid { grid-template-columns: 1fr; }
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

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        
        <?php include 'admin_header.php'; ?>

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
                <div class="section-header">
                    <h2>Staff List</h2>
                    <div class="header-buttons">
                        <button class="btn btn-primary" onclick="openAddStaffModal()"><i class="fas fa-plus"></i> Add New Staff</button>
                        <a href="staff_management_page.php?export=excel" class="btn btn-success"><i class="fas fa-download"></i> Export Data</a>
                    </div>
                </div>

                <form class="staff-search" method="GET" action="staff_management_page.php">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="status" <?php if($filterType == 'status') echo 'selected'; ?>>Status</option>
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
                
                <div class="staff-grid">
                    <?php if (count($staffMembers) > 0): foreach($staffMembers as $staff): ?>
                    <div class="staff-card">
                        <div class="card-header-actions">
                            <div class="action-menu">
                                <button class="menu-btn" onclick="toggleMenu(event, <?php echo $staff['Staff_ID']; ?>)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div id="menu-<?php echo $staff['Staff_ID']; ?>" class="dropdown-content">
                                    <div onclick="openViewStaffModal(<?php echo htmlspecialchars(json_encode($staff)); ?>)"><i class="fas fa-eye"></i> View Details</div>
                                    <div onclick='openEditStaffModal(<?php echo json_encode($staff); ?>)'><i class="fas fa-edit"></i> Edit Details</div>
                                    <div onclick="openChangePasswordModal(<?php echo $staff['Staff_ID']; ?>)"><i class="fas fa-key"></i> Change Password</div>
                                    <a href="javascript:confirmDelete(<?php echo $staff['Staff_ID']; ?>)" class="text-delete"><i class="fas fa-trash"></i> Delete Staff</a>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="card-avatar">
                                <?php if (!empty($staff['Staff_ProfilePicture']) && file_exists($staff['Staff_ProfilePicture'])): ?>
                                    <img src="<?php echo htmlspecialchars($staff['Staff_ProfilePicture']); ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo substr($staff['Staff_FullName'], 0, 1); ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-name"><?php echo htmlspecialchars($staff['Staff_FullName']); ?></div>
                            <div class="card-role"><?php echo htmlspecialchars($staff['Staff_Role']); ?></div>
                            
                            <div>
                                <span class="card-status <?php echo ($staff['Staff_Status'] == 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo htmlspecialchars($staff['Staff_Status']); ?>
                                </span>
                            </div>
                            
                            <div style="font-size: 12px; color: #999; margin-top: 5px;">ID: #<?php echo str_pad($staff['Staff_ID'], 4, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        
                        <div class="card-footer">
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['Staff_Email']); ?>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($staff['Staff_ContactNumber']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div style="grid-column: 1 / -1; text-align:center; padding:40px; color:#888; background:#f9f9f9; border-radius:10px;">
                        <i class="fas fa-search" style="font-size:40px; color:#ddd; margin-bottom:10px;"></i>
                        <p>No active staff members found matching your criteria.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        // Build pagination URL parameters
                        $queryParams = [];
                        if(!empty($search)) $queryParams['search'] = $search;
                        if(!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            // Role parameter removed here too
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
                <div id="view_profile_container" class="profile-picture-preview" style="margin: 0 auto 20px;">
                    <img id="view_profile_picture" src="" alt="Staff Photo" style="display:none; width:100%; height:100%; object-fit:cover;">
                    <div id="view_default_icon" class="default-avatar-icon"><i class="fas fa-user"></i></div>
                </div>
                
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
                        <span class="form-guide">Enter full name as per IC. English letters only.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-input" required onblur="validateEmail('email', 'emailError')" placeholder="e.g. user@example.com">
                            <span class="form-guide">Valid email address (e.g. name@domain.com).</span>
                            <div id="emailError" class="error-message">Invalid email format</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="contact" name="contact" class="form-input phone-input" required placeholder="12-3456789" maxlength="11">
                            </div>
                            <span class="form-guide">Format: 12-3456789 or 11-23456789 (No need for +60).</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number <span class="required">*</span></label>
                            <input type="text" id="ic_number" name="ic_number" class="form-input" placeholder="XXXXXX-XX-XXXX" maxlength="14" required>
                            <span class="form-guide">Format: YYMMDD-PB-#### (e.g. 990101-07-1234).</span>
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
                        <span class="form-guide">House unit no., floor, building, street name.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 2 <span class="required">*</span></label>
                        <input type="text" name="address2" class="form-input" required placeholder="e.g. Taman Sri">
                        <span class="form-guide">Residential area, village, or section.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" class="form-input" placeholder="Address Line 3 (Optional)">
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">City <span class="required">*</span></label><input type="text" name="city" class="form-input" required placeholder="e.g. Kuala Lumpur"></div>
                        <div class="form-group"><label class="form-label">State <span class="required">*</span></label><select id="state" name="state" class="form-select" required><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code <span class="required">*</span></label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input" required oninput="detectStateFromPostcode('postal_code', 'state')" placeholder="e.g. 50000">
                            <span class="form-guide">5-digit postcode.</span>
                        </div>
                        <div class="form-group"><label class="form-label">Country</label><input type="text" name="country" class="form-input" value="Malaysia" readonly></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Role <span class="required">*</span></label>
                            <input type="text" name="role" class="form-input" value="Staff" readonly style="background-color: #f8f9fa; color: #555;">
                        </div>
                        <div class="form-group"><label class="form-label">Status <span class="required">*</span></label><select id="status" name="status" class="form-select" required><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Comments</label>
                        <textarea name="comment" class="form-textarea" rows="2" placeholder="e.g. Staff specific skills or notes"></textarea>
                        <span class="form-guide">Optional notes (e.g., specific skills, emergency contact info, or department details).</span>
                    </div>

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
                        <span class="form-guide">Enter full name as per IC. English letters only.</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="edit_email" name="email" class="form-input" required onblur="validateEmail('edit_email', 'editEmailError')">
                            <span class="form-guide">Valid email address (e.g. name@domain.com).</span>
                            <div id="editEmailError" class="error-message">Invalid email</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="edit_contact" name="contact" class="form-input phone-input" required maxlength="11">
                            </div>
                            <span class="form-guide">Format: 12-3456789 (No need for +60).</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number <span class="required">*</span></label>
                            <input type="text" id="edit_ic_number" name="ic_number" class="form-input" maxlength="14" required>
                            <span class="form-guide">Format: YYMMDD-PB-#### (e.g. 990101-07-1234).</span>
                        </div>
                        <div class="form-group"><label class="form-label">Date of Birth <span class="required">*</span></label><input type="date" id="edit_dob" name="dob" class="form-input" required onchange="validateAge('edit_dob', 'editAgeError')"><div id="editAgeError" class="error-message">Must be at least 18 years old</div></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address Line 1 <span class="required">*</span></label>
                        <input type="text" id="edit_address1" name="address1" class="form-input" required placeholder="Line 1">
                        <span class="form-guide">House unit no., floor, building, street name.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2 <span class="required">*</span></label>
                        <input type="text" id="edit_address2" name="address2" class="form-input" required placeholder="Line 2">
                        <span class="form-guide">Residential area, village, or section.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" id="edit_address3" name="address3" class="form-input" placeholder="Line 3">
                    </div>

                    <div class="form-row">
                        <div class="form-group"><label class="form-label">City <span class="required">*</span></label><input type="text" id="edit_city" name="city" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">State <span class="required">*</span></label><select id="edit_state" name="state" class="form-select" required><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code <span class="required">*</span></label>
                            <input type="text" id="edit_postal_code" name="postal_code" class="form-input" required oninput="detectStateFromPostcode('edit_postal_code', 'edit_state')">
                            <span class="form-guide">5-digit postcode.</span>
                        </div>
                        <div class="form-group"><label class="form-label">Country</label><input type="text" id="edit_country" name="country" class="form-input" value="Malaysia" readonly></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Role <span class="required">*</span></label>
                            <input type="text" name="role" class="form-input" value="Staff" readonly style="background-color: #f8f9fa; color: #555;">
                        </div>
                        <div class="form-group"><label class="form-label">Status <span class="required">*</span></label><select id="edit_status" name="status" class="form-select" required><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Comments</label>
                        <textarea id="edit_comment" name="comment" class="form-textarea" rows="2"></textarea>
                        <span class="form-guide">Optional notes (e.g., specific skills, emergency contact info, or department details).</span>
                    </div>
                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Staff</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="changePasswordModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Change Staff Password</h2><button class="close-btn" onclick="closeChangePasswordModal()">&times;</button></div>
            <div class="modal-body">
                <form id="changePasswordForm" action="staff_management_page.php" method="POST" onsubmit="return validateChangePassword()">
                    <input type="hidden" name="change_password" value="1">
                    <input type="hidden" name="target_staff_id" id="cp_target_id">
                    
                    <div class="form-group">
                        <label class="form-label">Your Current Admin Password (Authorization) <span class="required">*</span></label>
                        <div class="password-input-container">
                            <input type="password" name="auth_password" class="form-input" required placeholder="Enter logged-in admin password">
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility(this.previousElementSibling.id || this.previousElementSibling)"><i class="fas fa-eye"></i></button>
                        </div>
                        <span class="form-guide">To ensure security, please enter your own password to proceed.</span>
                    </div>
                    
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

                    <div class="form-group">
                        <label class="form-label">New Password for Staff <span class="required">*</span></label>
                        <div class="password-input-group">
                            <div class="password-input-container">
                                <input type="password" id="cp_new_password" name="new_password" class="form-input" required oninput="validatePasswordRequirements(this.id, 'cp_req_list', 'cp_')">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('cp_new_password')"><i class="fas fa-eye"></i></button>
                            </div>
                            <button type="button" class="btn-small" onclick="generateStrongPassword('cp_new_password', 'cp_confirm_password')">Auto Generate</button>
                        </div>
                        <div class="password-requirements" id="cp_req_list">
                            <div class="requirement-item invalid" id="cp_lengthReq"><i class="fas fa-times"></i> Must be 8-15 characters long</div>
                            <div class="requirement-item invalid" id="cp_uppercaseReq"><i class="fas fa-times"></i> Must contain at least one Uppercase letter</div>
                            <div class="requirement-item invalid" id="cp_lowercaseReq"><i class="fas fa-times"></i> Must contain at least one Lowercase letter</div>
                            <div class="requirement-item invalid" id="cp_numberReq"><i class="fas fa-times"></i> Must contain at least one Number</div>
                            <div class="requirement-item invalid" id="cp_specialReq"><i class="fas fa-times"></i> Must contain at least one Special character (e.g. !@#)</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password <span class="required">*</span></label>
                        <div class="password-input-container">
                            <input type="password" id="cp_confirm_password" name="confirm_new_password" class="form-input" required oninput="validateMatch(this.id, 'cp_new_password', 'cp_match_error')">
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('cp_confirm_password')"><i class="fas fa-eye"></i></button>
                        </div>
                        <div id="cp_match_error" class="error-message">Passwords do not match</div>
                    </div>

                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => {
                el.classList.remove('active');
                el.querySelector('select').disabled = true;
            });
            // Role check removed
            if (type === 'status') {
                const el = document.getElementById('filter_status_container');
                el.classList.add('active'); el.querySelector('select').disabled = false;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            
            setupPhoneInput('contact');
            setupPhoneInput('edit_contact');
            setupICInput('ic_number', 'dob');
            setupICInput('edit_ic_number', 'edit_dob');

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
                if (event.target == document.getElementById('changePasswordModal')) closeChangePasswordModal();
            }
        });
        
        function setupPhoneInput(inputId) {
            const input = document.getElementById(inputId);
            if(!input) return;
            input.addEventListener('input', function(e) {
                let val = this.value.replace(/\D/g, ''); 
                if (val.length > 11) val = val.substring(0, 11);
                let newVal = '';
                if (val.length > 2) {
                     newVal += val.substring(0, 2) + '-' + val.substring(2);
                } else {
                     newVal = val;
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
                if (val.length > 6) { newVal += '-' + val.substring(6, 8); }
                if (val.length > 8) { newVal += '-' + val.substring(8, 12); }
                this.value = newVal;

                if (val.length >= 6 && dobInput) {
                    const yearPrefix = parseInt(val.substring(0, 2));
                    const month = val.substring(2, 4);
                    const day = val.substring(4, 6);
                    
                    const currentYearShort = new Date().getFullYear() % 100;
                    const fullYear = (yearPrefix > currentYearShort) ? '19' + val.substring(0, 2) : '20' + val.substring(0, 2);
                    
                    if (parseInt(month) >= 1 && parseInt(month) <= 12 && parseInt(day) >= 1 && parseInt(day) <= 31) {
                        dobInput.value = `${fullYear}-${month}-${day}`;
                        if(dobInputId === 'dob') validateAge('dob', 'ageError');
                        else validateAge('edit_dob', 'editAgeError');
                    }
                }
            });
        }

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
            for (let i = 4; i < 12; i++) { password += all[Math.floor(Math.random() * all.length)]; }
            password = password.split('').sort(() => 0.5 - Math.random()).join('');
            
            document.getElementById(passId).value = password;
            // Trigger input event for validation
            document.getElementById(passId).dispatchEvent(new Event('input'));

            if(confirmId) {
                document.getElementById(confirmId).value = password;
                document.getElementById(confirmId).dispatchEvent(new Event('input'));
            }
            
            const passInput = document.getElementById(passId);
            passInput.type = "text";
            // Update icon
            if(passInput.parentNode.querySelector('.toggle-password i')) {
                passInput.parentNode.querySelector('.toggle-password i').className = 'fas fa-eye-slash';
            }
        }

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
            if (originalSrc) { container.innerHTML = `<img src="${originalSrc}" alt="Preview">`; } 
            else { container.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>'; }
        }

        function detectStateFromPostcode(postcodeInputId, stateSelectId) {
            const code = document.getElementById(postcodeInputId).value;
            const stateSelect = document.getElementById(stateSelectId);
            if (code.length >= 2) {
                const prefix = parseInt(code.substring(0, 2));
                let state = "";
                if (prefix >= 1 && prefix <= 2) state = "Perlis";
                else if (prefix >= 5 && prefix <= 9) state = "Kedah";
                else if (prefix >= 10 && prefix <= 14) state = "Penang";
                else if (prefix >= 15 && prefix <= 18) state = "Kelantan";
                else if (prefix >= 20 && prefix <= 24) state = "Terengganu";
                else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                else if (prefix >= 30 && prefix <= 39) state = "Perak";
                else if (prefix >= 40 && prefix <= 48) state = "Selangor";
                else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                else if (prefix === 62) state = "Putrajaya";
                else if (prefix >= 63 && prefix <= 68) state = "Selangor";
                else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                else if (prefix >= 75 && prefix <= 78) state = "Melaka";
                else if (prefix >= 80 && prefix <= 86) state = "Johor";
                else if (prefix === 87) state = "Labuan";
                else if (prefix >= 88 && prefix <= 91) state = "Sabah";
                else if (prefix >= 93 && prefix <= 98) state = "Sarawak";

                if (state !== "") { stateSelect.value = state; }
            }
        }

        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); document.getElementById('menu-' + id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openViewStaffModal(staff) {
            // -- LOGIC FOR VIEW MODAL PHOTO --
            const img = document.getElementById('view_profile_picture');
            const icon = document.getElementById('view_default_icon');
            
            if (staff.Staff_ProfilePicture) {
                img.src = staff.Staff_ProfilePicture;
                img.style.display = 'block';
                icon.style.display = 'none';
            } else {
                img.style.display = 'none';
                icon.style.display = 'flex';
            }
            // ---------------------------------

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
            pInput.value = staff.Staff_ContactNumber.replace('+60', '');
            pInput.dispatchEvent(new Event('input')); 

            const icInput = document.getElementById('edit_ic_number');
            icInput.value = staff.Staff_ICNumber;
            icInput.dispatchEvent(new Event('input')); 

            document.getElementById('edit_dob').value = staff.Staff_DOB;
            document.getElementById('edit_address1').value = staff.Staff_Address1;
            document.getElementById('edit_address2').value = staff.Staff_Address2;
            document.getElementById('edit_address3').value = staff.Staff_Address3;
            document.getElementById('edit_city').value = staff.Staff_City;
            document.getElementById('edit_state').value = staff.Staff_State;
            document.getElementById('edit_postal_code').value = staff.Staff_PostalCode;
            
            // Role is Readonly, so we just set value
            // document.getElementById('edit_role').value = staff.Staff_Role; 
            
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
        
        // --- Change Password Functions ---
        function openChangePasswordModal(id) {
            if (!id) return;
            document.getElementById('changePasswordModal').style.display = 'flex';
            document.getElementById('cp_target_id').value = id;
            document.getElementById('changePasswordForm').reset();
            
            // Reset requirement icons
            document.querySelectorAll('#cp_req_list .requirement-item').forEach(el => {
                el.className = 'requirement-item invalid';
                el.querySelector('i').className = 'fas fa-times';
            });
            document.getElementById('cp_match_error').style.display = 'none';
        }
        function closeChangePasswordModal() { document.getElementById('changePasswordModal').style.display = 'none'; }
        
        function confirmDelete(id) { if(confirm('Are you sure you want to delete this staff member?')) window.location.href = 'staff_management_page.php?delete_id=' + id; }

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

        // Adapted to handle generic IDs or prefixed IDs for different modals
        function validatePasswordRequirements(passId = 'password', listContainerId = null, prefix = '') {
            const pw = document.getElementById(passId).value;
            const reqs = { 
                lengthReq: pw.length >= 8 && pw.length <= 15, 
                uppercaseReq: /[A-Z]/.test(pw), 
                lowercaseReq: /[a-z]/.test(pw), 
                numberReq: /\d/.test(pw), 
                specialReq: /[!@#$%^&*]/.test(pw) 
            };
            let allValid = true;
            
            // If prefix provided (like cp_), check those IDs
            for (const [key, valid] of Object.entries(reqs)) {
                let elId = prefix + key;
                // fallback for default form
                if(prefix === '' && !document.getElementById(key)) continue;

                const el = document.getElementById(elId);
                if(el) {
                    const icon = el.querySelector('i');
                    if (valid) { el.className = 'requirement-item valid'; icon.className = 'fas fa-check'; }
                    else { el.className = 'requirement-item invalid'; icon.className = 'fas fa-times'; allValid = false; }
                }
            }
            
            // Only show main error message for Add Staff form (which uses 'passwordError')
            if(passId === 'password') {
                document.getElementById('passwordError').style.display = (pw && !allValid) ? 'block' : 'none';
                if(document.getElementById('confirm_password').value) validateMatch();
            }
            
            return allValid;
        }
        
        function validateMatch(confirmId = 'confirm_password', passId = 'password', errorId = 'confirmPasswordError') {
            // Handle element passing (sometimes passed as ID string, sometimes defaulting)
            if(typeof confirmId !== 'string') confirmId = confirmId.id; // defensive
            
            const pw = document.getElementById(passId).value;
            const cpw = document.getElementById(confirmId).value;
            const match = pw === cpw && pw !== "";
            
            document.getElementById(errorId).style.display = (match || cpw === "") ? 'none' : 'block';
            
            // Only toggle check icon for Add Staff form
            if(confirmId === 'confirm_password') {
                document.getElementById('password-match-icon').style.display = match ? 'block' : 'none';
            }
            return match;
        }

        function togglePasswordVisibility(idOrElement) {
            let f;
            if (typeof idOrElement === 'string') {
                f = document.getElementById(idOrElement);
            } else {
                // If the element itself is passed (like the button or input)
                f = idOrElement; 
            }
            
            if (!f) return;

            // Find the icon. It's usually inside the button which is a sibling
            let icon;
            if (f.nextElementSibling && f.nextElementSibling.tagName === 'BUTTON') {
                icon = f.nextElementSibling.querySelector('i');
            } else if (f.parentNode.querySelector('button i')) {
                // If f is the input, find the button in the same container
                icon = f.parentNode.querySelector('button i');
            }
            
            if(f.type === 'password') { f.type = 'text'; if(icon) icon.className = 'fas fa-eye-slash'; }
            else { f.type = 'password'; if(icon) icon.className = 'fas fa-eye'; }
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
        
        function validateChangePassword() {
            const vPass = validatePasswordRequirements('cp_new_password', 'cp_req_list', 'cp_');
            const vMatch = validateMatch('cp_confirm_password', 'cp_new_password', 'cp_match_error');
            return vPass && vMatch;
        }
    </script>
</body>
</html>
