<?php
// admin_donor_page.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// --- 获取当前管理员信息 ---
$currentAdminId = $_SESSION['admin_id'];
$adminSql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
$adminResult = $conn->query($adminSql);

if ($adminResult && $adminResult->num_rows > 0) {
    $adminData = $adminResult->fetch_assoc();
    $adminName = $adminData['Admin_Name'];
    $adminPosition = $adminData['Admin_Role']; 
    $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
} else {
    $adminName = $_SESSION['admin_name'];
    $adminPosition = "System Administrator";
    $adminProfilePicture = null;
}

// --- SEARCH & FILTER PREPARATION ---
$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = [];

// 【修改点 1】确保只显示没有被 Soft Delete (0) 的用户
// 这样会自动应用到下面的列表显示和 Excel 导出中
$whereConditions[] = "d.Is_Deleted = 0";

// 1. Check Search (Keyword)
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereConditions[] = "(d.Donor_Name LIKE '%$searchTerm%' 
                           OR d.Donor_Email LIKE '%$searchTerm%' 
                           OR d.Donor_ID LIKE '%$searchTerm%')";
}

// 2. Check Dynamic Filters
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    
    // State Filter
    if ($filterType == 'state' && isset($_GET['filter_val_state']) && !empty($_GET['filter_val_state'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_state']);
        $whereConditions[] = "d.Donor_State = '$filterValue'";
    } 
    // Year Filter
    elseif ($filterType == 'year' && isset($_GET['filter_val_year']) && !empty($_GET['filter_val_year'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_year']);
        $whereConditions[] = "YEAR(d.Donor_RegisteredAt) = '$filterValue'";
    }
    // Points Filter
    elseif ($filterType == 'points' && isset($_GET['filter_val_points']) && !empty($_GET['filter_val_points'])) {
        $pointRange = $_GET['filter_val_points'];
        $filterValue = $pointRange; 
        if ($pointRange == 'low') {
            $whereConditions[] = "(SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID) < 500";
        } elseif ($pointRange == 'mid') {
            $whereConditions[] = "(SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID) BETWEEN 500 AND 1000";
        } elseif ($pointRange == 'high') {
            $whereConditions[] = "(SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID) > 1000";
        }
    }
}

// Combine WHERE clause
$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// --- EXPORT TO EXCEL HANDLER (UPDATED: ALL DATA) ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "donor_complete_data_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // 1. Donor List
    echo '<h3>DONOR PROFILES & STATUS</h3>';
    echo '<table border="1">';
    echo '<tr style="background-color:#eee;">
            <th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>IC Number</th>
            <th>City</th><th>State</th><th>Points</th><th>Donated (RM)</th>
            <th>Registered Date</th><th>Last Login</th>
          </tr>';
    
    // $whereClause already includes Is_Deleted = 0
    $donorSql = "SELECT d.Donor_ID, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_ICNumber, 
                  d.Donor_State, d.Donor_City, d.Donor_RegisteredAt, d.Donor_LastLogin,
                  COALESCE((SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID), 0) as CurrentPoints,
                  COALESCE((SELECT SUM(o.Order_Amount) FROM orders o WHERE o.Donor_ID = d.Donor_ID), 0) as TotalPayment
                  FROM donor d $whereClause ORDER BY d.Donor_RegisteredAt DESC";
    $donorRes = $conn->query($donorSql);
    
    if ($donorRes && $donorRes->num_rows > 0) {
        while($row = $donorRes->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['Donor_ID'] . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_Name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_Email']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_ContactNumber']) . '&nbsp;</td>';
            echo '<td>' . htmlspecialchars($row['Donor_ICNumber']) . '&nbsp;</td>';
            echo '<td>' . htmlspecialchars($row['Donor_City']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_State']) . '</td>';
            echo '<td>' . $row['CurrentPoints'] . '</td>';
            echo '<td>' . number_format($row['TotalPayment'], 2) . '</td>';
            echo '<td>' . $row['Donor_RegisteredAt'] . '</td>';
            echo '<td>' . ($row['Donor_LastLogin'] ? $row['Donor_LastLogin'] : 'Never') . '</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    echo '<br><br>';

    // 2. All Payment History
    echo '<h3>DETAILED PAYMENT HISTORY</h3>';
    echo '<table border="1">';
    echo '<tr style="background-color:#eee;">
            <th>Date</th><th>TXN Ref</th><th>Donor Name</th><th>Donor Email</th>
            <th>Amount (RM)</th><th>Payment Method</th><th>Status</th>
          </tr>';
    
    $paySql = "SELECT o.Order_Created_At, o.Order_TXN_Ref, o.Order_Amount, o.Order_PaymentMethod, o.Order_Status,
               d.Donor_Name, d.Donor_Email 
               FROM orders o 
               JOIN donor d ON o.Donor_ID = d.Donor_ID 
               $whereClause 
               ORDER BY o.Order_Created_At DESC";
    $payRes = $conn->query($paySql);

    if ($payRes && $payRes->num_rows > 0) {
        while($row = $payRes->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['Order_Created_At'] . '</td>';
            echo '<td>' . $row['Order_TXN_Ref'] . '&nbsp;</td>';
            echo '<td>' . htmlspecialchars($row['Donor_Name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_Email']) . '</td>';
            echo '<td>' . number_format($row['Order_Amount'], 2) . '</td>';
            echo '<td>' . $row['Order_PaymentMethod'] . '</td>';
            echo '<td>' . $row['Order_Status'] . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7">No payment records found.</td></tr>';
    }
    echo '</table>';
    echo '<br><br>';

    // 3. All Redemption History
    echo '<h3>DETAILED REDEMPTION HISTORY</h3>';
    echo '<table border="1">';
    echo '<tr style="background-color:#eee;">
            <th>Date</th><th>Donor Name</th><th>Reward Item</th>
            <th>Points Spent</th><th>Status</th>
          </tr>';
    
    $redSql = "SELECT r.Redemption_Updated_At, r.Redemption_PointsSpent, r.Redemption_Status, 
               d.Donor_Name, i.Reward_ItemName 
               FROM redemption_order r 
               JOIN donor d ON r.Donor_ID = d.Donor_ID
               JOIN reward_item i ON r.Reward_ID = i.Reward_ID
               $whereClause 
               ORDER BY r.Redemption_Updated_At DESC";
    $redRes = $conn->query($redSql);

    if ($redRes && $redRes->num_rows > 0) {
        while($row = $redRes->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['Redemption_Updated_At'] . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_Name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Reward_ItemName']) . '</td>';
            echo '<td>' . $row['Redemption_PointsSpent'] . '</td>';
            echo '<td>' . $row['Redemption_Status'] . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5">No redemption records found.</td></tr>';
    }
    echo '</table>';

    exit();
}

// --- AJAX HANDLER FOR HISTORY ---
if (isset($_GET['action']) && $_GET['action'] == 'get_donor_history' && isset($_GET['donor_id'])) {
    $histDonorId = intval($_GET['donor_id']);
    $type = $_GET['type'];
    
    if ($type == 'payment') {
        $histSql = "SELECT Order_Created_At, Order_TXN_Ref, Order_Amount, Order_Status, Order_PaymentMethod 
                    FROM orders WHERE Donor_ID = $histDonorId ORDER BY Order_Created_At DESC";
        $histResult = $conn->query($histSql);
        $data = [];
        while($r = $histResult->fetch_assoc()) { $data[] = $r; }
        echo json_encode($data);
    } elseif ($type == 'redemption') {
        $histSql = "SELECT r.Redemption_Updated_At, r.Redemption_PointsSpent, r.Redemption_Status, i.Reward_ItemName 
                    FROM redemption_order r 
                    JOIN reward_item i ON r.Reward_ID = i.Reward_ID 
                    WHERE r.Donor_ID = $histDonorId ORDER BY r.Redemption_Updated_At DESC";
        $histResult = $conn->query($histSql);
        $data = [];
        while($r = $histResult->fetch_assoc()) { $data[] = $r; }
        echo json_encode($data);
    }
    exit(); 
}

// --- FILE UPLOAD HELPER ---
function handleProfileUpload($file) {
    if (isset($file) && $file['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowedTypes)) {
            $uploadDir = 'uploads/donors/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'donor_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) return $uploadPath;
        }
    }
    return null;
}

// Handle Add Donor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_donor'])) {
    $donorName = mysqli_real_escape_string($conn, $_POST['donor_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contactRaw = $_POST['contact']; $contact = "+60" . $contactRaw; 
    $icNumber = mysqli_real_escape_string($conn, $_POST['ic_number']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $description = mysqli_real_escape_string($conn, $_POST['description']); // Added description
    $password = trim($_POST['password']);
    
    $profilePicture = null;
    if (isset($_FILES['profile_picture'])) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) $profilePicture = $uploadedPath;
    }
    
    // Server-side validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.(com|net|org|edu|gov|my)$/i', $email)) {
        $errorMessage = "Invalid email format. Must contain @ and a valid domain.";
    }
    elseif (!preg_match('/^[a-zA-Z\s]+$/', $donorName)) $errorMessage = "Name can only contain letters.";
    elseif (!preg_match('/^\+60[0-9]{1,2}-[0-9]{7,10}$/', $contact)) $errorMessage = "Invalid phone format.";
    else {
        if (!empty($dob)) {
            $age = (new DateTime())->diff(new DateTime($dob))->y;
            if ($age < 18) $errorMessage = "Donor must be 18+.";
        }
        if (!isset($errorMessage)) {
            // Check if email exists AND user is active (not deleted) or if email exists in DB at all?
            // Usually we check if email exists in the whole DB to avoid duplicates even with deleted users, 
            // OR we allow reusing email if previous user was deleted. 
            // For safety, let's keep it simple: unique email across the board.
            $checkEmailSql = "SELECT Donor_ID FROM donor WHERE Donor_Email = '$email'";
            if ($conn->query($checkEmailSql)->num_rows > 0) $errorMessage = "Email exists.";
            else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $cols = "Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email, Donor_Password, 
                         Donor_Address1, Donor_Address2, Donor_Address3, Donor_City, Donor_State, Donor_PostalCode, Donor_Country, 
                         Donor_DOB, Donor_Description, Donor_RegisteredAt, Is_Deleted";
                // Explicitly set Is_Deleted to 0
                $vals = "'$donorName', '$contact', '$icNumber', '$email', '$hashedPassword', 
                         '$address1', '$address2', '$address3', '$city', '$state', '$postalCode', '$country', 
                         '$dob', '$description', NOW(), 0";
                if ($profilePicture) { $cols .= ", Donor_ProfilePicture"; $vals .= ", '$profilePicture'"; }
                $sql = "INSERT INTO donor ($cols) VALUES ($vals)";
                if ($conn->query($sql)) {
                    $newDonorId = $conn->insert_id;
                    $conn->query("INSERT INTO point (Points_Earned, Points_Total, Points_Updated_At, Donor_ID) VALUES (0, 0, NOW(), $newDonorId)");
                    $successMessage = "Donor added successfully!";
                } else $errorMessage = "Error: " . $conn->error;
            }
        }
    }
    if (!empty($successMessage)) { header("Location: admin_donor_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: admin_donor_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// Handle Update Donor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_donor'])) {
    $donorId = mysqli_real_escape_string($conn, $_POST['donor_id']);
    $donorName = mysqli_real_escape_string($conn, $_POST['donor_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contactRaw = $_POST['contact']; $contact = "+60" . $contactRaw;
    $icNumber = mysqli_real_escape_string($conn, $_POST['ic_number']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $address1 = mysqli_real_escape_string($conn, $_POST['address1']);
    $address2 = mysqli_real_escape_string($conn, $_POST['address2']);
    $address3 = mysqli_real_escape_string($conn, $_POST['address3']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $description = mysqli_real_escape_string($conn, $_POST['description']); // Added description

    $picSql = "";
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) {
            $oldPicQ = $conn->query("SELECT Donor_ProfilePicture FROM donor WHERE Donor_ID = $donorId");
            if ($oldRow = $oldPicQ->fetch_assoc()) {
                if (!empty($oldRow['Donor_ProfilePicture']) && file_exists($oldRow['Donor_ProfilePicture'])) unlink($oldRow['Donor_ProfilePicture']);
            }
            $picSql = ", Donor_ProfilePicture = '$uploadedPath'";
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errorMessage = "Invalid email."; 
    else {
        $sql = "UPDATE donor SET Donor_Name = '$donorName', Donor_ContactNumber = '$contact', Donor_ICNumber = '$icNumber', 
                Donor_Email = '$email', Donor_Address1 = '$address1', Donor_Address2 = '$address2', Donor_Address3 = '$address3',
                Donor_City = '$city', Donor_State = '$state', Donor_PostalCode = '$postalCode', Donor_Country = '$country', 
                Donor_DOB = '$dob', Donor_Description = '$description' $picSql WHERE Donor_ID = $donorId";
        if ($conn->query($sql)) $successMessage = "Donor updated successfully!"; else $errorMessage = "Error: " . $conn->error;
    }
    if (!empty($successMessage)) { header("Location: admin_donor_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: admin_donor_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// --- PAGINATION & DELETE (MODIFIED FOR SOFT DELETE) ---
$results_per_page = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$count_sql = "SELECT COUNT(*) as total FROM donor d $whereClause";
$total_records = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_records / $results_per_page);
if ($page > $total_pages && $total_pages > 0) { $page = $total_pages; $start_from = ($page - 1) * $results_per_page; }

$select_fields = "d.*, 
                  COALESCE((SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID), 0) as CurrentPoints,
                  COALESCE((SELECT SUM(o.Order_Amount) FROM orders o WHERE o.Donor_ID = d.Donor_ID), 0) as TotalPayment";
$sql = "SELECT $select_fields FROM donor d $whereClause ORDER BY d.Donor_RegisteredAt DESC, d.Donor_ID DESC LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$donors = [];
if ($result && $result->num_rows > 0) { while($row = $result->fetch_assoc()) $donors[] = $row; }

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// 【修改点 2】 Soft Delete Logic
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    // Update Is_Deleted to 1 instead of DELETE FROM
    if ($conn->query("UPDATE donor SET Is_Deleted = 1 WHERE Donor_ID = $deleteId")) { 
        header("Location: admin_donor_page.php?success=" . urlencode("Donor deleted successfully!")); 
        exit(); 
    }
    else {
        $errorMessage = "Error deleting donor: " . $conn->error;
    }
}

// Stats Calculation (Modified to exclude deleted donors)
function getStats($conn) {
    // ... [Existing Stats Logic] ...
    $currentMonth = date('m'); $currentYear = date('Y');
    $lastMonthDate = new DateTime('first day of last month');
    $lastMonth = $lastMonthDate->format('m'); $lastMonthYear = $lastMonthDate->format('Y');

    // 【修改点 3】 统计时排除已删除用户
    $resTotal = $conn->query("SELECT COUNT(*) as total FROM donor WHERE Is_Deleted = 0");
    $totalDonors = ($resTotal) ? $resTotal->fetch_assoc()['total'] : 0;

    $checkCol = $conn->query("SHOW COLUMNS FROM `donor` LIKE 'Donor_RegisteredAt'");
    if($checkCol && $checkCol->num_rows > 0) {
        $resNew = $conn->query("SELECT COUNT(*) as total FROM donor WHERE Is_Deleted = 0 AND MONTH(Donor_RegisteredAt) = '$currentMonth' AND YEAR(Donor_RegisteredAt) = '$currentYear'");
        $newDonorsThisMonth = ($resNew) ? $resNew->fetch_assoc()['total'] : 0;
        $totalLastMonthEnd = $totalDonors - $newDonorsThisMonth;
        $donorPercentChange = ($totalLastMonthEnd > 0) ? (($totalDonors - $totalLastMonthEnd) / $totalLastMonthEnd) * 100 : ($totalDonors > 0 ? 100 : 0);
    } else $donorPercentChange = 0; 
    
    // For donations, we technically usually count ALL money even from deleted users, 
    // but the query here uses JOIN implicitly or just orders table. 
    // The current query selects from `orders` table directly, which is fine (money is still money).
    $resDonationThis = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE MONTH(Order_Created_At) = '$currentMonth' AND YEAR(Order_Created_At) = '$currentYear'");
    $donationThisMonth = ($resDonationThis && $row = $resDonationThis->fetch_assoc()) ? (float)$row['total'] : 0;

    $resDonationLast = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE MONTH(Order_Created_At) = '$lastMonth' AND YEAR(Order_Created_At) = '$lastMonthYear'");
    $donationLastMonth = ($resDonationLast && $row = $resDonationLast->fetch_assoc()) ? (float)$row['total'] : 0;
    
    $donationPercentChange = ($donationLastMonth > 0) ? (($donationThisMonth - $donationLastMonth) / $donationLastMonth) * 100 : ($donationThisMonth > 0 ? 100 : 0);

    return [
        'totalDonors' => $totalDonors,
        'donorPercentChange' => abs(round($donorPercentChange, 1)),
        'donorTrend' => ($donorPercentChange >= 0) ? 'up' : 'down',
        'donationThisMonth' => $donationThisMonth,
        'donationPercentChange' => abs(round($donationPercentChange, 1)),
        'donationTrend' => ($donationPercentChange >= 0) ? 'up' : 'down'
    ];
}
$stats = getStats($conn);

function formatAddress($donor) {
    if (empty($donor['Donor_Address1'])) return '-';
    $addressParts = [];
    if (!empty($donor['Donor_Address1'])) $addressParts[] = htmlspecialchars($donor['Donor_Address1']) . ',';
    $line2Parts = [];
    if (!empty($donor['Donor_Address2'])) $line2Parts[] = htmlspecialchars($donor['Donor_Address2']);
    if (!empty($donor['Donor_Address3'])) $line2Parts[] = htmlspecialchars($donor['Donor_Address3']);
    if (!empty($line2Parts)) $addressParts[] = implode(', ', $line2Parts) . ',';
    $postal = !empty($donor['Donor_PostalCode']) ? htmlspecialchars($donor['Donor_PostalCode']) : '';
    $city = !empty($donor['Donor_City']) ? htmlspecialchars($donor['Donor_City']) : '';
    $state = !empty($donor['Donor_State']) ? htmlspecialchars($donor['Donor_State']) : '';
    if($postal || $city || $state) $addressParts[] = $postal . ' ' . $city . ',' . $state;
    return implode("<br>", $addressParts);
}

$conn->close();

$malaysiaStates = [ 'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu' ];
$years = range(date('Y'), 2020); 
$exportParams = $_GET; $exportParams['action'] = 'export_excel'; unset($exportParams['page']);
$exportUrl = "?" . http_build_query($exportParams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Existing Styles */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; gap: 5px; }
        .text-success { color: var(--success); } .text-danger { color: var(--danger); }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .donor-management { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; }
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; text-decoration: none; font-size: 13px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .donor-search { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; }
        .search-input:focus { border-color: var(--primary); }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-label { font-size: 13px; font-weight: 600; color: #555; display: none; }
        @media (min-width: 992px) { .filter-label { display: block; } }
        .filter-select { padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background-color: white; min-width: 140px; cursor: pointer; }
        .filter-select:focus { border-color: var(--primary); }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .donor-table { width: 100%; border-collapse: collapse; }
        .donor-table th, .donor-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .donor-table th { font-weight: 600; color: var(--gray); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .donor-info { display: flex; align-items: center; }
        .donor-avatar { width: 40px; height: 40px; border-radius: 50%; margin-right: 15px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px; overflow: hidden; }
        .donor-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .donor-details h4 { font-size: 14px; margin-bottom: 4px; color: var(--dark); }
        .donor-details p { font-size: 12px; color: #888; margin: 0; }
        .address-display { font-size: 13px; color: #666; line-height: 1.5; margin: 0; padding: 0; display: block; }
        .action-cell { display: flex; justify-content: center; align-items: center; height: 100%; }
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { background-color: #f8f9fa; border: 1px solid #e9ecef; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; color: #6c757d; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; outline: none; }
        .menu-btn:hover { background-color: #e2e6ea; color: var(--primary); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 10px; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: var(--primary); }
        .text-delete { color: var(--danger) !important; border-top: 1px solid #eee; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--gray-light); }
        .modal-header h2 { font-size: 18px; font-weight: 600; margin: 0; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray); }
        .modal-body { padding: 20px; background-color: #fdfdfd; }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark); font-size: 14px;}
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-input:read-only, .form-textarea:read-only { background-color: #f8f9fa; color: #555; cursor: default; }

        /* --- UPDATED FORM GUIDE STYLE (MATCHING STAFF PAGE) --- */
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        /* ----------------------------------------------------- */

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

        .password-input-group { display: flex; width: 100%; }
        .password-input-container { flex: 1; display: flex; position: relative; }
        .password-input-container input { flex: 1; border-radius: 5px 0 0 5px; border-right: none; }
        .toggle-password { border: 1px solid var(--gray-light); border-left: none; background: white; padding: 0 10px; cursor: pointer; }
        .btn-small { padding: 0 12px; border-radius: 0 5px 5px 0; border: 1px solid var(--gray-light); border-left: none; background: #f8f9fa; cursor: pointer; font-size: 12px; font-weight: 500; color: var(--primary); }
        .confirm-check { position: absolute; right: 50px; top: 50%; transform: translateY(-50%); color: var(--success); font-size: 14px; display: none; z-index: 2; }
        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }
        
        .password-requirements { margin-top: 8px; font-size: 12px; }
        .requirement-item { display: flex; align-items: center; margin-bottom: 3px; color: #888; }
        .requirement-item.valid { color: var(--success); } .requirement-item.invalid { color: var(--gray); } 
        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); font-weight: bold; }
        .phone-input { border-radius: 0 5px 5px 0 !important; }
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; max-width: 400px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-controls { display: flex; gap: 5px; align-items: center; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; }
        .required { color: red; margin-left: 3px; font-weight: bold; }
        
        /* History Modals Styles */
        .history-list { display: flex; flex-direction: column; gap: 15px; max-height: 500px; overflow-y: auto; padding-right: 5px; }
        .history-card { background: white; border: 1px solid #eee; border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .history-card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.08); border-color: #ddd; transform: translateY(-2px); }
        .h-info-left { display: flex; flex-direction: column; gap: 4px; }
        .h-date { font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; }
        .h-title { font-size: 14px; font-weight: 600; color: #333; }
        .h-subtitle { font-size: 12px; color: #666; font-family: monospace; }
        .h-info-right { text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
        .h-amount { font-size: 16px; font-weight: 700; color: var(--primary); }
        .h-points { font-size: 15px; font-weight: 700; color: #666; }
        .status-badge { font-size: 10px; padding: 3px 8px; border-radius: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-success { background-color: #e6f9ed; color: #28a745; border: 1px solid #c3e6cb; }
        .status-failed { background-color: #ffe6e6; color: #dc3545; border: 1px solid #f5c6cb; }
        .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .empty-state-card { text-align: center; padding: 40px 20px; color: #999; background: #f8f9fa; border-radius: 8px; border: 2px dashed #eee; }
        .empty-state-card i { font-size: 32px; margin-bottom: 10px; color: #ddd; }
        @media (max-width: 768px) { .stats-cards { grid-template-columns: repeat(2, 1fr); } .form-row { flex-direction: column; gap: 0; } .pagination-container { flex-direction: column; gap: 15px; } .donor-search { flex-direction: column; align-items: stretch; } .filter-group { flex-wrap: wrap; } .history-card { flex-direction: column; align-items: flex-start; gap: 10px; } .h-info-right { align-items: flex-start; width: 100%; flex-direction: row; justify-content: space-between; } }
    </style>
</head>
<body>
    <?php if (isset($_GET['success'])): ?>
        <div class="floating-alert floating-alert-success" id="floatingSuccess"><i class="fas fa-check-circle"></i><div><?php echo htmlspecialchars($_GET['success']); ?></div></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="floating-alert floating-alert-danger" id="floatingError"><i class="fas fa-exclamation-circle"></i><div><?php echo htmlspecialchars($_GET['error']); ?></div></div>
    <?php endif; ?>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section"><h1>Donor Management</h1><p>Manage all donors, view details, and track donations.</p></div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info"><h3>TOTAL DONORS</h3><h2><?php echo number_format($stats['totalDonors']); ?></h2><p class="<?php echo $stats['donorTrend'] == 'up' ? 'text-success' : 'text-danger'; ?>"><?php echo ($stats['donorTrend'] == 'up' ? '+' : '-') . $stats['donorPercentChange']; ?>% from last month</p></div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>TOTAL DONATION (THIS MONTH)</h3><h2>RM <?php echo number_format($stats['donationThisMonth'], 2); ?></h2><p class="<?php echo $stats['donationTrend'] == 'up' ? 'text-success' : 'text-danger'; ?>"><?php echo ($stats['donationTrend'] == 'up' ? '+' : '-') . $stats['donationPercentChange']; ?>% from last month</p></div>
                    <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                </div>
            </div>

            <div class="donor-management">
                <div class="section-header">
                    <h2>Donor List</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="openAddDonorModal()"><i class="fas fa-plus"></i> Add New Donor</button>
                        <a href="<?php echo $exportUrl; ?>" class="btn btn-success" target="_blank"><i class="fas fa-download"></i> Export Data</a>
                    </div>
                </div>

                <form method="GET" action="admin_donor_page.php" class="donor-search">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="state" <?php echo ($filterType == 'state') ? 'selected' : ''; ?>>State</option>
                            <option value="year" <?php echo ($filterType == 'year') ? 'selected' : ''; ?>>Registration Year</option>
                            <option value="points" <?php echo ($filterType == 'points') ? 'selected' : ''; ?>>Points Tier</option>
                        </select>
                    </div>

                    <div id="filter_state_container" class="secondary-filter"><select name="filter_val_state" class="filter-select"><option value="">Select State...</option><?php foreach($malaysiaStates as $ms): ?><option value="<?php echo $ms; ?>" <?php echo ($filterValue == $ms && $filterType == 'state') ? 'selected' : ''; ?>><?php echo $ms; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_year_container" class="secondary-filter"><select name="filter_val_year" class="filter-select"><option value="">Select Year...</option><?php foreach($years as $yr): ?><option value="<?php echo $yr; ?>" <?php echo ($filterValue == $yr && $filterType == 'year') ? 'selected' : ''; ?>><?php echo $yr; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_points_container" class="secondary-filter"><select name="filter_val_points" class="filter-select"><option value="">Select Tier...</option><option value="low" <?php echo ($filterValue == 'low') ? 'selected' : ''; ?>>Below 500 pts</option><option value="mid" <?php echo ($filterValue == 'mid') ? 'selected' : ''; ?>>500 - 1000 pts</option><option value="high" <?php echo ($filterValue == 'high') ? 'selected' : ''; ?>>VIP (> 1000 pts)</option></select></div>

                    <input type="text" name="search" class="search-input" placeholder="Search donors by name, ID or email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($filterType) || !empty($searchTerm)): ?><a href="admin_donor_page.php" class="btn btn-danger" style="background-color: #dc3545; padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a><?php endif; ?>
                </form>

                <table class="donor-table">
                    <thead><tr><th>DONOR NAME</th><th>CONTACT INFO</th><th style="width: 30%;">ADDRESS</th><th>TOTAL PAYMENT</th><th>TOTAL POINTS</th><th style="text-align: center;">ACTIONS</th></tr></thead>
                    <tbody>
                        <?php if (count($donors) > 0): ?>
                            <?php foreach($donors as $donor): ?>
                            <tr>
                                <td>
                                    <div class="donor-info">
                                        <div class="donor-avatar">
                                            <?php if (!empty($donor['Donor_ProfilePicture'])): ?><img src="<?php echo htmlspecialchars($donor['Donor_ProfilePicture']); ?>" alt="Profile"><?php else: echo substr($donor['Donor_Name'], 0, 1); endif; ?>
                                        </div>
                                        <div class="donor-details"><h4><?php echo htmlspecialchars($donor['Donor_Name']); ?></h4><p>ID: <?php echo htmlspecialchars($donor['Donor_ID']); ?></p></div>
                                    </div>
                                </td>
                                <td><div class="donor-details"><p><?php echo htmlspecialchars($donor['Donor_Email']); ?></p><p><?php echo htmlspecialchars($donor['Donor_ContactNumber']); ?></p></div></td>
                                <td><div class="address-display"><?php echo formatAddress($donor); ?></div></td>
                                <td>RM <?php echo number_format($donor['TotalPayment'], 2); ?></td>
                                <td><?php echo number_format($donor['CurrentPoints']); ?> pts</td>
                                <td>
                                    <div class="action-cell">
                                        <div class="action-menu">
                                            <button class="menu-btn" onclick="toggleMenu(event, <?php echo $donor['Donor_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                            <div id="menu-<?php echo $donor['Donor_ID']; ?>" class="dropdown-content">
                                                <div onclick="openViewDonorModal(<?php echo htmlspecialchars(json_encode($donor)); ?>)"><i class="fas fa-eye"></i> View Details</div>
                                                <div onclick='openEditDonorModal(<?php echo json_encode($donor); ?>)'><i class="fas fa-edit"></i> Edit Details</div>
                                                <div onclick="openPaymentHistory(<?php echo $donor['Donor_ID']; ?>)"><i class="fas fa-history"></i> Payment History</div>
                                                <div onclick="openRedemptionHistory(<?php echo $donor['Donor_ID']; ?>)"><i class="fas fa-gift"></i> Redemption History</div>
                                                <a href="javascript:confirmDelete(<?php echo $donor['Donor_ID']; ?>)" class="text-delete"><i class="fas fa-trash"></i> Delete</a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; padding: 20px;">No active donors found matching criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        $queryParams = [];
                        if(!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if(!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if($filterType == 'state' && !empty($filterValue)) $queryParams['filter_val_state'] = $filterValue;
                            if($filterType == 'year' && !empty($filterValue)) $queryParams['filter_val_year'] = $filterValue;
                            if($filterType == 'points' && !empty($filterValue)) $queryParams['filter_val_points'] = $filterValue;
                        }
                        $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                        if ($page > 1) echo '<a href="?page=' . ($page - 1) . $queryString . '" class="pagination-btn">Previous</a>'; else echo '<span class="pagination-btn disabled">Previous</span>';
                        if ($page == 1) echo '<span class="pagination-btn active">1</span>'; else echo '<a href="?page=1' . $queryString . '" class="pagination-btn">1</a>';
                        
                        $start_mid = max(2, $page - 1); $end_mid = min($total_pages - 1, $page + 1);
                        if ($start_mid > 2) echo '<span class="pagination-btn disabled">...</span>';
                        for ($i = $start_mid; $i <= $end_mid; $i++) echo ($i == $page) ? '<span class="pagination-btn active">' . $i . '</span>' : '<a href="?page=' . $i . $queryString . '" class="pagination-btn">' . $i . '</a>';
                        if ($end_mid < $total_pages - 1) echo '<span class="pagination-btn disabled">...</span>';
                        
                        if ($total_pages > 1) echo ($page == $total_pages) ? '<span class="pagination-btn active">' . $total_pages . '</span>' : '<a href="?page=' . $total_pages . $queryString . '" class="pagination-btn">' . $total_pages . '</a>';
                        if ($page < $total_pages) echo '<a href="?page=' . ($page + 1) . $queryString . '" class="pagination-btn">Next</a>'; else echo '<span class="pagination-btn disabled">Next</span>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="addDonorModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Donor</h2><button class="close-btn" onclick="closeAddDonorModal()">&times;</button></div>
            <div class="modal-body">
                <form id="addDonorForm" action="admin_donor_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <input type="hidden" name="add_donor" value="1">
                    
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <div class="profile-picture-preview" id="add-preview-container">
                            <div class="default-avatar-icon"><i class="fas fa-user"></i></div>
                        </div>
                        <div class="file-upload">
                            <label for="add_profile_picture" class="file-upload-label">
                                <i class="fas fa-upload"></i> Choose Profile Picture
                            </label>
                            <input type="file" id="add_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'add-preview-container', 'add-file-info', 'add-file-name')">
                            <div id="add-file-info" class="file-info">
                                <span id="add-file-name" class="file-name"></span>
                                <button type="button" class="file-remove" onclick="removeImage('add_profile_picture', 'add-preview-container', 'add-file-info')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="donor_name" class="form-input" required oninput="validateName(this)" placeholder="e.g. John Doe">
                        <span class="form-guide">Enter full name as per IC. English letters only.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-input" required onblur="validateEmail()" placeholder="e.g. user@example.com">
                            <span class="form-guide">Valid email address (e.g. name@domain.com, must contain @ and domain).</span>
                            <div id="emailError" class="error-message">Invalid email format.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="contact" name="contact" class="form-input phone-input" required placeholder="11-12345678" maxlength="11">
                            </div>
                            <span class="form-guide">Format: 12-3456789 or 11-12345678 (No need for +60).</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number</label>
                            <input type="text" id="ic_number" name="ic_number" class="form-input" placeholder="XXXXXX-XX-XXXX" maxlength="14">
                            <span class="form-guide">Format: YYMMDD-PB-#### (e.g. 990101-07-1234).</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="form-input" onchange="validateAge()">
                            <div id="ageError" class="error-message">Must be 18+</div>
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
                    </div>

                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Postal Code</label><input type="text" id="postal_code" name="postal_code" class="form-input" placeholder="e.g. 50000"></div>
                        <div class="form-group"><label class="form-label">City</label><input type="text" name="city" class="form-input" placeholder="e.g. Kuala Lumpur"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">State</label><select id="state_select" name="state" class="form-select"><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div>
                        <div class="form-group"><label class="form-label">Country</label><input type="text" name="country" class="form-input" value="Malaysia" readonly></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Remarks / Description</label>
                        <textarea name="description" class="form-textarea" rows="2"></textarea>
                        <span class="form-guide">Optional: Enter remarks, preferences, or important notes about this donor.</span>
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
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password <span class="required">*</span></label>
                        <div class="password-input-container">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required oninput="validatePasswordMatch()">
                            <i id="password-match-icon" class="fas fa-check-circle confirm-check"></i>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')"><i class="fas fa-eye"></i></button>
                        </div>
                        <div id="confirmPasswordError" class="error-message">Passwords do not match</div>
                    </div>
                    
                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Donor</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="editDonorModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Donor</h2><button class="close-btn" onclick="closeEditDonorModal()">&times;</button></div>
            <div class="modal-body">
                <form id="editDonorForm" action="admin_donor_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateEditForm()">
                    <input type="hidden" name="update_donor" value="1"><input type="hidden" id="edit_donor_id" name="donor_id">
                    
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <div class="profile-picture-preview" id="edit-preview-container">
                            <div class="default-avatar-icon"><i class="fas fa-user"></i></div>
                        </div>
                        <div class="file-upload">
                            <label for="edit_profile_picture" class="file-upload-label">
                                <i class="fas fa-upload"></i> Choose Profile Picture
                            </label>
                            <input type="file" id="edit_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'edit-preview-container', 'edit-file-info', 'edit-file-name')">
                            <div id="edit-file-info" class="file-info">
                                <span id="edit-file-name" class="file-name"></span>
                                <button type="button" class="file-remove" onclick="removeImage('edit_profile_picture', 'edit-preview-container', 'edit-file-info')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" id="edit_donor_name" name="donor_name" class="form-input" required oninput="validateName(this)">
                        <span class="form-guide">Enter full name as per IC. English letters only.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="edit_email" name="email" class="form-input" required onblur="validateEditEmail()">
                            <span class="form-guide">Valid email address (e.g. name@domain.com).</span>
                            <div id="editEmailError" class="error-message">Invalid email format.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" id="edit_contact" name="contact" class="form-input phone-input" required maxlength="11">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IC Number</label>
                            <input type="text" id="edit_ic_number" name="ic_number" class="form-input" maxlength="14">
                            <span class="form-guide">Format: YYMMDD-PB-####.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">DOB</label>
                            <input type="date" id="edit_dob" name="dob" class="form-input" onchange="validateEditAge()">
                        </div>
                    </div>
                    
                    <div class="form-group"><label class="form-label">Address Line 1</label><input type="text" id="edit_address1" name="address1" class="form-input"></div>
                    <div class="form-group"><label class="form-label">Address Line 2</label><input type="text" id="edit_address2" name="address2" class="form-input"></div>
                    <div class="form-group"><label class="form-label">Address Line 3</label><input type="text" id="edit_address3" name="address3" class="form-input"></div>
                    
                    <div class="form-row"><div class="form-group"><label class="form-label">Postal Code</label><input type="text" id="edit_postal_code" name="postal_code" class="form-input"></div><div class="form-group"><label class="form-label">City</label><input type="text" id="edit_city" name="city" class="form-input"></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">State</label><select id="edit_state" name="state" class="form-select"><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div><div class="form-group"><label class="form-label">Country</label><input type="text" id="edit_country" name="country" class="form-input" value="Malaysia" readonly></div></div>
                    
                    <div class="form-group">
                        <label class="form-label">Remarks / Description</label>
                        <textarea id="edit_description" name="description" class="form-textarea" rows="2"></textarea>
                        <span class="form-guide">Optional: Update remarks or notes about this donor.</span>
                    </div>

                    <div class="form-group"><button type="submit" class="btn btn-primary">Update Donor</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="viewDonorModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Donor Details</h2><button class="close-btn" onclick="closeModal('viewDonorModal')">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Full Name</label><input type="text" id="view_donor_name" class="form-input" readonly></div>
                <div class="form-row"><div class="form-group"><label class="form-label">Email</label><input type="text" id="view_email" class="form-input" readonly></div><div class="form-group"><label class="form-label">Contact</label><input type="text" id="view_contact" class="form-input" readonly></div></div>
                <div class="form-row"><div class="form-group"><label class="form-label">IC Number</label><input type="text" id="view_ic" class="form-input" readonly></div><div class="form-group"><label class="form-label">DOB</label><input type="text" id="view_dob" class="form-input" readonly></div></div>
                <div class="form-group"><label class="form-label">Registered At</label><input type="text" id="view_registered" class="form-input" readonly></div>
                <div class="form-group"><label class="form-label">Last Login</label><input type="text" id="view_last_login" class="form-input" readonly></div>
                <div class="form-group"><label class="form-label">Address</label><textarea id="view_address" class="form-input" readonly rows="3"></textarea></div>
                <div class="form-group"><label class="form-label">Remarks</label><textarea id="view_description" class="form-input" readonly rows="2"></textarea></div>
            </div>
        </div>
    </div>

    <div class="modal" id="paymentHistoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Payment History</h2>
                <button class="close-btn" onclick="closeModal('paymentHistoryModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="paymentHistoryList" class="history-list">
                    </div>
            </div>
        </div>
    </div>

    <div class="modal" id="redemptionHistoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Redemption History</h2>
                <button class="close-btn" onclick="closeModal('redemptionHistoryModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="redemptionHistoryList" class="history-list">
                    </div>
            </div>
        </div>
    </div>

    <script>
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            if (type === 'state') { document.getElementById('filter_state_container').classList.add('active'); document.getElementById('filter_state_container').querySelector('select').disabled = false; }
            else if (type === 'year') { document.getElementById('filter_year_container').classList.add('active'); document.getElementById('filter_year_container').querySelector('select').disabled = false; }
            else if (type === 'points') { document.getElementById('filter_points_container').classList.add('active'); document.getElementById('filter_points_container').querySelector('select').disabled = false; }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            setupPhoneInput('contact'); setupPhoneInput('edit_contact');
            setupICInput('ic_number', 'dob'); setupICInput('edit_ic_number', 'edit_dob'); 
            setupPostcodeState('postal_code', 'state_select'); setupPostcodeState('edit_postal_code', 'edit_state');
            const s = document.getElementById('floatingSuccess'); const e = document.getElementById('floatingError');
            if(s) setTimeout(() => s.style.display='none', 5000); if(e) setTimeout(() => e.style.display='none', 5000);
            window.addEventListener('click', function(event) {
                if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
                if (event.target.classList.contains('modal')) event.target.style.display = "none";
            });
        });

        function setupPostcodeState(postcodeId, stateSelectId) {
            const pcInput = document.getElementById(postcodeId); const stateSelect = document.getElementById(stateSelectId);
            if (!pcInput || !stateSelect) return;
            pcInput.addEventListener('input', function() {
                const val = this.value.replace(/\D/g, '');
                if (val.length >= 2) {
                    const prefix = parseInt(val.substring(0, 2));
                    let state = "";
                    if (prefix >= 1 && prefix <= 2) state = "Perlis"; else if (prefix >= 5 && prefix <= 9) state = "Kedah"; else if (prefix >= 10 && prefix <= 14) state = "Penang";
                    else if (prefix >= 15 && prefix <= 18) state = "Kelantan"; else if (prefix >= 20 && prefix <= 24) state = "Terengganu"; else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                    else if (prefix >= 30 && prefix <= 36) state = "Perak"; else if (prefix >= 40 && prefix <= 48) state = "Selangor"; else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                    else if (prefix >= 62 && prefix <= 62) state = "Putrajaya"; else if (prefix >= 63 && prefix <= 68) state = "Selangor"; else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                    else if (prefix >= 75 && prefix <= 78) state = "Melaka"; else if (prefix >= 79 && prefix <= 86) state = "Johor"; else if (prefix == 87) state = "Labuan";
                    else if (prefix >= 88 && prefix <= 91) state = "Sabah"; else if (prefix >= 93 && prefix <= 98) state = "Sarawak";
                    if (state) stateSelect.value = state;
                }
            });
        }

        function setupPhoneInput(inputId) {
            const input = document.getElementById(inputId); if(!input) return;
            input.addEventListener('input', function(e) { let val = this.value.replace(/\D/g, ''); if (val.length > 11) val = val.substring(0, 11); let newVal = val; if (val.length > 2) newVal = val.substring(0, 2) + '-' + val.substring(2); this.value = newVal; });
        }

        function setupICInput(inputId, dobInputId) {
            const input = document.getElementById(inputId); const dobInput = document.getElementById(dobInputId); if(!input) return;
            input.addEventListener('input', function(e) {
                let val = this.value.replace(/\D/g, ''); if (val.length > 12) val = val.substring(0, 12);
                let newVal = ''; newVal += val.substring(0, 6); if (val.length > 6) newVal += '-' + val.substring(6, 8); if (val.length > 8) newVal += '-' + val.substring(8, 12);
                this.value = newVal;
                if (val.length >= 6 && dobInput) {
                    const yy = parseInt(val.substring(0, 2)); const mm = val.substring(2, 4); const dd = val.substring(4, 6);
                    const prefix = (yy > (new Date().getFullYear() % 100)) ? '19' : '20';
                    const fullDate = `${prefix}${val.substring(0, 2)}-${mm}-${dd}`;
                    const dateObj = new Date(fullDate);
                    if (!isNaN(dateObj.getTime())) { dobInput.value = fullDate; if (typeof validateAge === "function") validateAge(); if (inputId.includes('edit') && typeof validateEditAge === "function") validateEditAge(); }
                }
            });
        }

        function generateStrongPassword(passId, confirmId) {
            const upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; const lower = "abcdefghijklmnopqrstuvwxyz"; const numbers = "0123456789"; const specials = "!@#$%^&*"; const all = upper + lower + numbers + specials;
            let password = ""; password += upper[Math.floor(Math.random() * upper.length)]; password += lower[Math.floor(Math.random() * lower.length)]; password += numbers[Math.floor(Math.random() * numbers.length)]; password += specials[Math.floor(Math.random() * specials.length)];
            for (let i = 4; i < 12; i++) { password += all[Math.floor(Math.random() * all.length)]; }
            password = password.split('').sort(() => 0.5 - Math.random()).join('');
            document.getElementById(passId).value = password; if(confirmId) document.getElementById(confirmId).value = password;
            const passInput = document.getElementById(passId); passInput.type = "text"; const toggleBtn = passInput.nextElementSibling; if(toggleBtn) toggleBtn.querySelector('i').className = 'fas fa-eye-slash';
            if(confirmId) { const confirmInput = document.getElementById(confirmId); confirmInput.type = "text"; const confirmToggle = confirmInput.nextElementSibling; if(confirmToggle) confirmToggle.querySelector('i').className = 'fas fa-eye-slash'; }
            if(passId === 'password') validatePasswordRequirements(); if(confirmId) validatePasswordMatch();
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
            document.getElementById(inputId).value = ''; 
            if(infoId) document.getElementById(infoId).style.display = 'none'; 
            const container = document.getElementById(containerId); 
            if (originalSrc) { 
                container.innerHTML = `<img src="${originalSrc}" alt="Preview">`; 
            } else { 
                container.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>'; 
            } 
        }

        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); document.getElementById('menu-' + id).style.display = 'block'; }
        
        function openViewDonorModal(donor) {
            document.getElementById('view_donor_name').value = donor.Donor_Name;
            document.getElementById('view_email').value = donor.Donor_Email;
            document.getElementById('view_contact').value = donor.Donor_ContactNumber;
            document.getElementById('view_ic').value = donor.Donor_ICNumber;
            document.getElementById('view_dob').value = donor.Donor_DOB;
            document.getElementById('view_registered').value = donor.Donor_RegisteredAt;
            document.getElementById('view_last_login').value = donor.Donor_LastLogin ? donor.Donor_LastLogin : 'Never';
            document.getElementById('view_description').value = donor.Donor_Description ? donor.Donor_Description : ''; // Show Description

            let address = donor.Donor_Address1;
            if(donor.Donor_Address2) address += ", " + donor.Donor_Address2;
            if(donor.Donor_Address3) address += ", " + donor.Donor_Address3;
            address += "\n" + donor.Donor_PostalCode + " " + donor.Donor_City + ", " + donor.Donor_State;
            document.getElementById('view_address').value = address;
            document.getElementById('viewDonorModal').style.display = 'flex';
        }

        function openPaymentHistory(donorId) {
            const listContainer = document.getElementById('paymentHistoryList');
            listContainer.innerHTML = '<div class="empty-state-card"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div>';
            document.getElementById('paymentHistoryModal').style.display = 'flex';
            
            fetch(`admin_donor_page.php?action=get_donor_history&donor_id=${donorId}&type=payment`)
            .then(res => res.json())
            .then(data => {
                listContainer.innerHTML = '';
                if(data.length === 0) { 
                    listContainer.innerHTML = '<div class="empty-state-card"><i class="fas fa-file-invoice-dollar"></i><br>No payment history found.</div>'; 
                } else { 
                    data.forEach(row => { 
                        let statusClass = 'status-pending';
                        if(row.Order_Status.toLowerCase() === 'completed' || row.Order_Status.toLowerCase() === 'success') statusClass = 'status-success';
                        else if(row.Order_Status.toLowerCase() === 'failed' || row.Order_Status.toLowerCase() === 'cancelled') statusClass = 'status-failed';
                        
                        const html = `
                        <div class="history-card">
                            <div class="h-info-left">
                                <span class="h-date">${row.Order_Created_At}</span>
                                <span class="h-title">${row.Order_PaymentMethod}</span>
                                <span class="h-subtitle">Ref: ${row.Order_TXN_Ref}</span>
                            </div>
                            <div class="h-info-right">
                                <span class="h-amount">RM ${row.Order_Amount}</span>
                                <span class="status-badge ${statusClass}">${row.Order_Status}</span>
                            </div>
                        </div>`;
                        listContainer.innerHTML += html;
                    }); 
                }
            });
        }

        function openRedemptionHistory(donorId) {
            const listContainer = document.getElementById('redemptionHistoryList');
            listContainer.innerHTML = '<div class="empty-state-card"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div>';
            document.getElementById('redemptionHistoryModal').style.display = 'flex';
            
            fetch(`admin_donor_page.php?action=get_donor_history&donor_id=${donorId}&type=redemption`)
            .then(res => res.json())
            .then(data => {
                listContainer.innerHTML = '';
                if(data.length === 0) { 
                    listContainer.innerHTML = '<div class="empty-state-card"><i class="fas fa-gift"></i><br>No redemption history found.</div>'; 
                } else { 
                    data.forEach(row => { 
                        let statusClass = 'status-pending';
                        if(row.Redemption_Status.toLowerCase() === 'completed' || row.Redemption_Status.toLowerCase() === 'delivered') statusClass = 'status-success';
                        else if(row.Redemption_Status.toLowerCase() === 'rejected') statusClass = 'status-failed';

                        const html = `
                        <div class="history-card">
                            <div class="h-info-left">
                                <span class="h-date">${row.Redemption_Updated_At}</span>
                                <span class="h-title">${row.Reward_ItemName}</span>
                            </div>
                            <div class="h-info-right">
                                <span class="h-points">-${row.Redemption_PointsSpent} pts</span>
                                <span class="status-badge ${statusClass}">${row.Redemption_Status}</span>
                            </div>
                        </div>`;
                        listContainer.innerHTML += html;
                    }); 
                }
            });
        }

        function openAddDonorModal() { document.getElementById('addDonorModal').style.display = 'flex'; }
        function closeAddDonorModal() { 
            document.getElementById('addDonorModal').style.display = 'none'; 
            document.getElementById('addDonorForm').reset(); 
            document.getElementById('add-preview-container').innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>'; 
            document.getElementById('add-file-info').style.display = 'none'; 
            document.querySelectorAll('.requirement-item').forEach(el => { el.className = 'requirement-item invalid'; el.querySelector('i').className = 'fas fa-times'; }); 
        }
        
        function openEditDonorModal(donor) {
            document.getElementById('edit_donor_id').value = donor.Donor_ID;
            document.getElementById('edit_donor_name').value = donor.Donor_Name;
            document.getElementById('edit_email').value = donor.Donor_Email;
            let phone = donor.Donor_ContactNumber.replace(/^\+60/, '');
            document.getElementById('edit_contact').value = phone;
            let icInput = document.getElementById('edit_ic_number'); icInput.value = donor.Donor_ICNumber; icInput.dispatchEvent(new Event('input')); 
            document.getElementById('edit_dob').value = donor.Donor_DOB;
            document.getElementById('edit_address1').value = donor.Donor_Address1;
            document.getElementById('edit_address2').value = donor.Donor_Address2;
            document.getElementById('edit_address3').value = donor.Donor_Address3;
            document.getElementById('edit_city').value = donor.Donor_City;
            document.getElementById('edit_state').value = donor.Donor_State;
            document.getElementById('edit_postal_code').value = donor.Donor_PostalCode;
            document.getElementById('edit_country').value = donor.Donor_Country;
            document.getElementById('edit_description').value = donor.Donor_Description ? donor.Donor_Description : ''; // Populate Description
            
            // Edit Preview
            const container = document.getElementById('edit-preview-container');
            if (donor.Donor_ProfilePicture) { 
                container.innerHTML = `<img src="${donor.Donor_ProfilePicture}" alt="Preview">`; 
            } else { 
                container.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>'; 
            }
            document.getElementById('edit_profile_picture').value = '';
            document.getElementById('edit-file-info').style.display = 'none';
            
            document.getElementById('editDonorModal').style.display = 'flex';
        }

        function closeEditDonorModal() { document.getElementById('editDonorModal').style.display = 'none'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function confirmDelete(id) { if(confirm("Are you sure?")) window.location.href = "admin_donor_page.php?delete_id=" + id; }
        
        function validateName(input) { input.value = input.value.replace(/[^a-zA-Z\s]/g, ''); }
        
        // Updated Email Validation with Visual Feedback
        function validateEmail() { 
            const val = document.getElementById('email').value;
            // Check for @ AND a domain extension (basic check)
            const v = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|my)$/i.test(val); 
            document.getElementById('emailError').style.display = v ? 'none' : 'block'; 
            return v; 
        }
        function validateEditEmail() { 
            const val = document.getElementById('edit_email').value;
            const v = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|my)$/i.test(val); 
            document.getElementById('editEmailError').style.display = v ? 'none' : 'block';
            return v; 
        }
        
        function validateAge() { const d = document.getElementById('dob').value; if(!d) return true; return (new Date().getFullYear() - new Date(d).getFullYear()) >= 18; }
        function validateEditAge() { const d = document.getElementById('edit_dob').value; if(!d) return true; return (new Date().getFullYear() - new Date(d).getFullYear()) >= 18; }
        
        function validatePasswordRequirements() {
            const pw = document.getElementById('password').value;
            const reqs = { lengthReq: pw.length >= 8 && pw.length <= 15, uppercaseReq: /[A-Z]/.test(pw), lowercaseReq: /[a-z]/.test(pw), numberReq: /\d/.test(pw), specialReq: /[!@#$%^&*]/.test(pw) };
            let allValid = true;
            for (const [id, valid] of Object.entries(reqs)) { const el = document.getElementById(id); const icon = el.querySelector('i'); if (valid) { el.className = 'requirement-item valid'; icon.className = 'fas fa-check'; } else { el.className = 'requirement-item invalid'; icon.className = 'fas fa-times'; allValid = false; } }
            if(document.getElementById('confirm_password').value) validatePasswordMatch(); return allValid;
        }
        function validatePasswordMatch() { const m = document.getElementById('password').value === document.getElementById('confirm_password').value; document.getElementById('confirmPasswordError').style.display = m ? 'none' : 'block'; document.getElementById('password-match-icon').style.display = m ? 'block' : 'none'; return m; }
        function togglePasswordVisibility(id) { const f = document.getElementById(id); const toggleBtn = f.nextElementSibling; if(f.type === 'password') { f.type = 'text'; if(toggleBtn) toggleBtn.querySelector('i').className = 'fas fa-eye-slash'; } else { f.type = 'password'; if(toggleBtn) toggleBtn.querySelector('i').className = 'fas fa-eye'; } }
        function validateForm() { return validateEmail() && validatePasswordRequirements() && validatePasswordMatch(); }
        function validateEditForm() { return validateEditEmail() && validateEditAge(); }
    </script>
</body>
</html>
