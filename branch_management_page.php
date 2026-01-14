<?php
// branch_management_page.php
session_start();

// Check if user is logged in
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

// --- Get Current User Info ---
$adminName = "User";
$adminPosition = "Role";
$adminProfilePicture = null;
$adminId = 0;

if (isset($_SESSION['admin_id'])) {
    $adminId = $_SESSION['admin_id'];
    $sql = "SELECT Admin_ProfilePicture, Admin_Role, Admin_Name FROM admin WHERE Admin_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $adminName = $row['Admin_Name'];
        $adminProfilePicture = $row['Admin_ProfilePicture'];
        $adminPosition = !empty($row['Admin_Role']) ? $row['Admin_Role'] : "Admin";
    }
    $stmt->close();
} elseif (isset($_SESSION['staff_id'])) {
    $currentStaffId = $_SESSION['staff_id'];
    $adminId = $currentStaffId; 
    $sql = "SELECT Staff_FullName, Staff_ProfilePicture, Staff_Role FROM staff WHERE Staff_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $currentStaffId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $adminName = $row['Staff_FullName'];
        $adminProfilePicture = $row['Staff_ProfilePicture'];
        $adminPosition = $row['Staff_Role'];
    }
    $stmt->close();
}

// --- AJAX: Get Branch Payment History ---
if (isset($_GET['action']) && $_GET['action'] == 'get_payment_history' && isset($_GET['branch_id'])) {
    $branchId = intval($_GET['branch_id']);
    $sql = "SELECT o.Order_ID, o.Order_Created_At, o.Order_Amount, o.Order_TXN_Ref, d.Donor_Name 
            FROM orders o 
            JOIN donor d ON o.Donor_ID = d.Donor_ID 
            WHERE o.Branch_ID = $branchId AND (o.Order_Status = 'Success' OR o.Order_Status = 'Completed')
            ORDER BY o.Order_Created_At DESC";
    $result = $conn->query($sql);
    $data = [];
    while($row = $result->fetch_assoc()) {
        $row['Formatted_Amount'] = number_format($row['Order_Amount'], 2);
        $row['Formatted_Date'] = date('d M Y, h:i A', strtotime($row['Order_Created_At']));
        $data[] = $row;
    }
    echo json_encode($data);
    exit();
}

// --- AJAX: Get Branch Withdrawal History (NEW) ---
if (isset($_GET['action']) && $_GET['action'] == 'get_withdrawal_history' && isset($_GET['branch_id'])) {
    $branchId = intval($_GET['branch_id']);
    
    // Join with Activity table to show if it's an activity withdrawal
    $sql = "SELECT w.*, a.Activity_Name 
            FROM withdrawals w 
            LEFT JOIN activity a ON w.Activity_ID = a.Activity_ID 
            WHERE w.Branch_ID = $branchId 
            ORDER BY w.Request_Date DESC";
            
    $result = $conn->query($sql);
    $data = [];
    $totalWithdrawn = 0;
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            if ($row['Status'] == 'Approved' || $row['Status'] == 'Completed') {
                $totalWithdrawn += $row['Amount'];
            }
            $row['Formatted_Amount'] = number_format($row['Amount'], 2);
            $row['Formatted_Date'] = date('d M Y', strtotime($row['Request_Date']));
            $row['Details'] = $row['Activity_Name'] ? "Activity: " . $row['Activity_Name'] : "General Branch Fund";
            $data[] = $row;
        }
    }
    
    echo json_encode(['history' => $data, 'total_withdrawn' => number_format($totalWithdrawn, 2)]);
    exit();
}

// --- SEARCH & FILTER PREPARATION ---
$search = "";
$filterType = "";
$filterValue = "";
$conditions = ["Is_Deleted = 0"];
$orderClause = "ORDER BY Branch_ID DESC"; // Default Sort

// 1. Keyword Search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $conditions[] = "(Branch_Name LIKE '%$search%' OR Branch_City LIKE '%$search%' OR Branch_ID LIKE '%$search%')";
}

// 2. Dynamic Filters
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    
    if ($filterType == 'category' && !empty($_GET['filter_val_category'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_category']);
        $conditions[] = "Branch_Type = '$filterValue'";
    } elseif ($filterType == 'state' && !empty($_GET['filter_val_state'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_state']);
        $conditions[] = "Branch_State = '$filterValue'";
    } elseif ($filterType == 'status' && !empty($_GET['filter_val_status'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_status']);
        $conditions[] = "Branch_OperationalStatus = '$filterValue'";
    } elseif ($filterType == 'name_sort' && !empty($_GET['filter_val_name'])) {
        $filterValue = $_GET['filter_val_name'];
        if ($filterValue == 'asc') $orderClause = "ORDER BY Branch_Name ASC";
        elseif ($filterValue == 'desc') $orderClause = "ORDER BY Branch_Name DESC";
    } elseif ($filterType == 'id_sort' && !empty($_GET['filter_val_id'])) {
        $filterValue = $_GET['filter_val_id'];
        if ($filterValue == 'asc') $orderClause = "ORDER BY Branch_ID ASC";
        elseif ($filterValue == 'desc') $orderClause = "ORDER BY Branch_ID DESC";
    } elseif ($filterType == 'phone' && !empty($_GET['filter_val_phone'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_phone']);
        $conditions[] = "Branch_ContactNumber LIKE '%$filterValue%'";
    } elseif ($filterType == 'city' && !empty($_GET['filter_val_city'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_city']);
        $conditions[] = "Branch_City = '$filterValue'";
    } elseif ($filterType == 'capacity' && !empty($_GET['filter_val_capacity'])) {
        $filterValue = $_GET['filter_val_capacity'];
        if ($filterValue == 'below_100') {
            $conditions[] = "Branch_Capacity < 100";
        } elseif ($filterValue == '100_500') {
            $conditions[] = "Branch_Capacity BETWEEN 100 AND 500";
        } elseif ($filterValue == 'above_500') {
            $conditions[] = "Branch_Capacity > 500";
        }
    }
}

$whereClause = "WHERE " . implode(" AND ", $conditions);

// --- EXPORT TO EXCEL ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "branch_list_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $exportSql = "SELECT b.*, 
                  COALESCE((SELECT SUM(Order_Amount) FROM orders o WHERE o.Branch_ID = b.Branch_ID AND (o.Order_Status = 'Success' OR o.Order_Status = 'Completed')), 0) as TotalRaised 
                  FROM branch b $whereClause $orderClause";
    $res = $conn->query($exportSql);

    echo '<table border="1">';
    echo '<tr><th>ID</th><th>Category</th><th>Name</th><th>Branch Contact</th><th>Branch Email</th><th>Bank Name</th><th>Bank Account</th><th>PIC Name</th><th>PIC Contact</th><th>PIC Email</th><th>Capacity</th><th>Total Raised (RM)</th><th>Address</th><th>Status</th></tr>';
    
    if ($res && $res->num_rows > 0) {
        while($row = $res->fetch_assoc()) {
            $addr = $row['Branch_Address1'] . ", " . $row['Branch_Address2'] . ", " . $row['Branch_Address3'] . ", " . $row['Branch_PostalCode'] . " " . $row['Branch_City'] . ", " . $row['Branch_State'];
            echo "<tr>
                <td>{$row['Branch_ID']}</td>
                <td>{$row['Branch_Type']}</td>
                <td>{$row['Branch_Name']}</td>
                <td>'{$row['Branch_ContactNumber']}</td>
                <td>{$row['Branch_Email']}</td>
                <td>{$row['Branch_BankName']}</td>
                <td>'{$row['Branch_BankAccount']}</td>
                <td>{$row['Branch_Head']}</td>
                <td>'{$row['Branch_Head_Contact']}</td>
                <td>{$row['Branch_Head_Email']}</td>
                <td>{$row['Branch_Capacity']}</td>
                <td>{$row['TotalRaised']}</td>
                <td>{$addr}</td>
                <td>{$row['Branch_OperationalStatus']}</td>
            </tr>";
        }
    }
    echo '</table>';
    exit();
}

// --- STATS LOGIC ---
function getBranchStats($conn) {
    // ... (Stats Logic Remains Same) ...
    $endOfLastMonth = date('Y-m-t 23:59:59', strtotime('last month'));
    $sqlTotalNow = "SELECT COUNT(*) as total FROM branch WHERE Is_Deleted = 0";
    $totalBranchesNow = $conn->query($sqlTotalNow)->fetch_assoc()['total'];
    
    $checkCol = $conn->query("SHOW COLUMNS FROM `branch` LIKE 'Branch_CreatedAt'");
    if($checkCol && $checkCol->num_rows > 0) {
        $sqlTotalLast = "SELECT COUNT(*) as total FROM branch WHERE Is_Deleted = 0 AND Branch_CreatedAt <= '$endOfLastMonth'"; 
        $totalBranchesLast = isset($conn->query($sqlTotalLast)->fetch_assoc()['total']) ? $conn->query($sqlTotalLast)->fetch_assoc()['total'] : 0;
    } else { $totalBranchesLast = 0; }

    $branchPercentChange = ($totalBranchesLast > 0) ? (($totalBranchesNow - $totalBranchesLast) / $totalBranchesLast) * 100 : ($totalBranchesNow > 0 ? 100 : 0);

    $sqlActiveNow = "SELECT COUNT(*) as total FROM branch WHERE Branch_OperationalStatus = 'Open' AND Is_Deleted = 0";
    $activeBranchesNow = $conn->query($sqlActiveNow)->fetch_assoc()['total'];
    $activePercentChange = 0; // Simplified for brevity

    $sqlDonationNow = "SELECT SUM(Order_Amount) as total FROM orders WHERE Branch_ID IS NOT NULL AND (Order_Status = 'Success' OR Order_Status = 'Completed')";
    $totalDonationNow = (float)($conn->query($sqlDonationNow)->fetch_assoc()['total'] ?? 0);
    $donationPercentChange = 0; // Simplified

    return [
        'totalBranches' => $totalBranchesNow,
        'branchPercentChange' => number_format(abs($branchPercentChange), 1),
        'branchTrend' => ($branchPercentChange >= 0) ? 'up' : 'down',
        'activeBranches' => $activeBranchesNow,
        'activePercentChange' => '0.0',
        'activeTrend' => 'up',
        'totalDonationAmount' => $totalDonationNow,
        'donationPercentChange' => '0.0',
        'donationTrend' => 'up'
    ];
}
$stats = getBranchStats($conn);

// --- FILE UPLOAD HELPER ---
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

// --- DATA LISTS ---
$branchTypes = ['Headquarters', 'Branch', 'Old Folks Home', 'Orphanage', 'Disabled Care Center'];
$malaysiaStates = ['Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'];
$phonePrefixes = ['010', '011', '012', '013', '014', '015', '016', '017', '018', '019'];

// Get distinct cities for filter
$cities = [];
$cityQ = $conn->query("SELECT DISTINCT Branch_City FROM branch WHERE Is_Deleted = 0 AND Branch_City != '' ORDER BY Branch_City ASC");
if($cityQ) while($c = $cityQ->fetch_assoc()) $cities[] = $c['Branch_City'];

// --- ADD BRANCH ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_branch'])) {
    $branchName = mysqli_real_escape_string($conn, $_POST['branch_name']);
    $branchType = mysqli_real_escape_string($conn, $_POST['branch_type']);
    $capacity = intval($_POST['capacity']);
    $estDate = mysqli_real_escape_string($conn, $_POST['est_date']);
    $operationalStatus = mysqli_real_escape_string($conn, $_POST['operational_status']);
    
    // Bank Details (Now Required)
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
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $imageJson = "[]";
    if (isset($_FILES['branch_images'])) {
        $uploaded = handleMultiUpload($_FILES['branch_images']);
        if (!empty($uploaded)) $imageJson = json_encode($uploaded);
    }
    
    // Added Validation for Bank Name and Account
    if (empty($branchName) || empty($branchType) || empty($bankName) || empty($bankAccount)) {
        $errorMessage = "Required fields (including Bank Details) are missing.";
    } else {
        $sql = "INSERT INTO branch (Branch_Name, Branch_Type, Branch_Capacity, Branch_EstablishedDate,
                Branch_OperationalStatus, Branch_ContactNumber, Branch_Email,
                Branch_Head, Branch_Head_Contact, Branch_Head_Email,
                Branch_BankName, Branch_BankAccount,
                Branch_Address1, Branch_Address2, Branch_Address3, Branch_City, Branch_State, 
                Branch_PostalCode, Branch_Country, Branch_Description, Branch_Images, Admin_ID) 
                VALUES ('$branchName', '$branchType', $capacity, '$estDate',
                '$operationalStatus', '$contactNumber', '$email',
                '$branchHead', '$headContact', '$headEmail',
                '$bankName', '$bankAccount',
                '$address1', '$address2', '$address3', '$city', '$state', 
                '$postalCode', '$country', '$description', '$imageJson', $adminId)";
        
        if ($conn->query($sql)) $successMessage = "Branch added successfully!";
        else $errorMessage = "Error adding branch: " . $conn->error;
    }
    
    if (!empty($successMessage)) { header("Location: branch_management_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: branch_management_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// --- UPDATE BRANCH ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_branch'])) {
    $branchId = mysqli_real_escape_string($conn, $_POST['branch_id']);
    
    $branchName = mysqli_real_escape_string($conn, $_POST['branch_name']);
    $branchType = mysqli_real_escape_string($conn, $_POST['branch_type']);
    $capacity = intval($_POST['capacity']);
    $estDate = mysqli_real_escape_string($conn, $_POST['est_date']);
    $operationalStatus = mysqli_real_escape_string($conn, $_POST['operational_status']);
    
    // Bank Details
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
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
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
        $errorMessage = "Required fields (including Bank Details) are missing.";
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
        
        if ($conn->query($sql)) $successMessage = "Branch updated successfully!";
        else $errorMessage = "Error updating branch: " . $conn->error;
    }
    
    if (!empty($successMessage)) { header("Location: branch_management_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: branch_management_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// --- DELETE ---
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    $conn->query("UPDATE branch SET Is_Deleted = 1 WHERE Branch_ID = $deleteId");
    header("Location: branch_management_page.php?success=" . urlencode("Branch deleted successfully!"));
    exit();
}

// --- PAGINATION & QUERY ---
$results_per_page = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$count_sql = "SELECT COUNT(*) as total FROM branch $whereClause";
$count_result = $conn->query($count_sql);
$total_records = ($count_result && $row = $count_result->fetch_assoc()) ? $row['total'] : 0;
$total_pages = ceil($total_records / $results_per_page);
if ($page > $total_pages && $total_pages > 0) { $page = $total_pages; $start_from = ($page - 1) * $results_per_page; }

$sql = "SELECT b.*, 
        COALESCE((SELECT SUM(Order_Amount) FROM orders o WHERE o.Branch_ID = b.Branch_ID AND (o.Order_Status = 'Success' OR o.Order_Status = 'Completed')), 0) as TotalDonated 
        FROM branch b $whereClause $orderClause LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$branches = [];
if ($result) { while($row = $result->fetch_assoc()) $branches[] = $row; }

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// --- COLLECT IMAGES FOR LIGHTBOX JS ---
$allBranchImagesMap = [];

$exportParams = $_GET; $exportParams['action'] = 'export_excel'; unset($exportParams['page']);
$exportUrl = "?" . http_build_query($exportParams);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Management - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .stats-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #888; margin-bottom: 5px; text-transform: uppercase; font-weight: 600; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; color: #333; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; gap: 5px; margin: 0; }
        .text-success { color: #28a745 !important; } .text-danger { color: #dc3545 !important; }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(23, 162, 184, 0.2); color: var(--info); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }

        .management-container { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; margin: 0; color: #333; }
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; font-size: 14px; text-decoration: none;}
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        .search-filter-container { margin-bottom: 25px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; background-color: white; min-width: 140px; cursor: pointer; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; background: white; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .branch-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .branch-card { background: white; border-radius: 12px; overflow: visible; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.3s ease; position: relative; display: flex; flex-direction: column; border: 1px solid #f0f0f0; }
        .branch-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        
        .card-image-container { position: relative; height: 200px; background: #f0f0f0; overflow: hidden; border-top-left-radius: 12px; border-top-right-radius: 12px; }
        .card-img { width: 100%; height: 100%; object-fit: cover; display: none; transition: opacity 0.3s; cursor: zoom-in; }
        .card-img.active { display: block; }
        
        .no-image-placeholder { width: 100%; height: 100%; background: #e9ecef; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #adb5bd; }
        .no-image-placeholder i { font-size: 48px; margin-bottom: 10px; }
        .no-image-placeholder span { font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; }

        .carousel-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; padding: 10px; cursor: pointer; z-index: 2; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; }
        .prev-btn { left: 10px; } .next-btn { right: 10px; }
        .img-counter { position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; }

        .status-badge { position: absolute; top: 15px; left: 15px; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 5; pointer-events: none; }
        .status-open { background: #d4edda; color: #155724; } .status-closed { background: #f8d7da; color: #721c24; }
        
        .card-content { padding: 20px; flex: 1; display: flex; flex-direction: column; position: relative; }
        .card-actions { position: absolute; top: 10px; right: 10px; z-index: 50; }
        .menu-btn { background-color: rgba(255, 255, 255, 0.95); border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; color: #555; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.15); transition: all 0.2s; }
        .menu-btn:hover { background-color: white; color: var(--primary); transform: scale(1.1); }
        .action-dropdown { position: absolute; top: 40px; right: 0; background: white; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); width: 190px; display: none; overflow: hidden; margin-bottom: 5px; border: 1px solid #eee; z-index: 100; }
        .action-dropdown.show { display: block; animation: fadeIn 0.2s; }
        .action-item { padding: 10px 15px; display: flex; align-items: center; gap: 8px; color: #333; cursor: pointer; font-size: 13px; transition: 0.1s; text-decoration: none; }
        .action-item:hover { background: #f8f9fa; color: var(--primary); }
        .action-item.delete { color: var(--danger); border-top: 1px solid #f0f0f0; }

        .branch-type { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 1px; margin-bottom: 5px; }
        .branch-title { font-size: 18px; font-weight: 700; margin-bottom: 15px; color: #333; padding-right: 10px; line-height: 1.3; }
        
        .info-grid { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        .info-item { display: flex; align-items: flex-start; gap: 12px; font-size: 13px; color: #555; }
        .info-icon { width: 18px; min-width: 18px; text-align: center; color: var(--primary); margin-top: 2px; }
        .info-text { word-break: break-all; }
        .info-label { font-weight: 600; font-size: 11px; color: #999; text-transform: uppercase; display: block; margin-bottom: 1px; }

        .card-stats { border-top: 1px solid #eee; padding-top: 15px; margin-top: auto; display: flex; justify-content: space-between; align-items: center; }
        .raised-amount { font-size: 16px; font-weight: 700; color: #28a745; }
        .raised-label { font-size: 11px; color: #888; display: block; }

        /* Modal & Form Guides */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; border-radius: 10px; width: 90%; max-width: 650px; max-height: 90vh; overflow-y: auto; padding: 0; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .modal-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fff; position: sticky; top: 0; z-index: 5; }
        .modal-body { padding: 25px; }
        .form-group { margin-bottom: 18px; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        .form-label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #444; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; transition: border 0.2s; outline: none; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(242, 133, 133, 0.1); }
        .required { color: var(--danger); margin-left: 3px; }
        .section-separator { border-top: 1px dashed #ddd; margin: 25px 0; position: relative; }
        .section-separator span { position: absolute; top: -12px; left: 0; background: #fff; padding-right: 10px; font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; }
        
        /* --- UPLOAD STYLES --- */
        .upload-container { width: 100%; }
        .upload-box { border: 2px dashed #ccc; background: #fafafa; border-radius: 8px; padding: 30px 20px; text-align: center; cursor: pointer; transition: all 0.3s; position: relative; }
        .upload-box:hover { background: #fff5f5; border-color: var(--primary); }
        .upload-box i { font-size: 32px; color: #aaa; margin-bottom: 10px; display: block; }
        .upload-box p { margin: 0; font-size: 13px; color: #666; font-weight: 500; }
        .upload-box input[type="file"] { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; z-index: 10; }
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 12px; margin-top: 15px; }
        .preview-item { position: relative; height: 80px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: 1px solid #eee; }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .remove-img-btn { position: absolute; top: 4px; right: 4px; background: #ff4d4d; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2); z-index: 10; }
        
        /* THE FORM GUIDE EXPLANATION STYLE */
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        
        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid #ddd; border-right: none; border-radius: 6px 0 0 6px; color: #666; font-size: 14px; font-weight: bold; }
        .phone-input { border-radius: 0 6px 6px 0 !important; }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; display: inline-block; }
        .pagination-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; border-color: #eee; }
        
        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }
        .history-list { max-height: 400px; overflow-y: auto; }
        .history-item { display: flex; justify-content: space-between; padding: 15px; border-bottom: 1px solid #eee; align-items: center; cursor: pointer; transition: background 0.2s; }
        .history-item:hover { background: #f9f9f9; }
        .h-left h4 { margin: 0 0 5px 0; font-size: 14px; color: #333; }
        .h-left p { margin: 0; font-size: 12px; color: #888; }
        .h-right { font-weight: bold; color: #28a745; font-size: 14px; }
        .h-right-negative { font-weight: bold; color: #dc3545; font-size: 14px; }
        .close-btn { font-size: 24px; cursor: pointer; border:none; background:none; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; animation: slideIn 0.3s; }
        .floating-alert div { line-height: 1.6; }
        .floating-alert i { margin-top: 4px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* LIGHTBOX */
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 2002; }
        .lightbox-nav { cursor: pointer; position: absolute; top: 50%; padding: 16px; margin-top: -50px; color: white; font-weight: bold; font-size: 30px; transition: 0.6s ease; z-index: 2001; background-color: rgba(0,0,0,0.3); }
        .lightbox-prev { left: 0; } .lightbox-next { right: 0; }

        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: 1fr; }
            .form-row { flex-direction: column; gap: 0; }
            .search-filter-container { flex-direction: column; align-items: stretch; }
            .filter-select, .search-input { width: 100%; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="floating-alert floating-alert-success" id="floatingSuccess" style="display: <?php echo isset($_GET['success']) ? 'flex' : 'none'; ?>">
                <i class="fas fa-check-circle"></i>
                <div id="floatingSuccessText"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div>
            </div>

            <div class="floating-alert floating-alert-danger" id="floatingError" style="display: <?php echo isset($_GET['error']) ? 'flex' : 'none'; ?>">
                <i class="fas fa-exclamation-circle"></i>
                <div id="floatingErrorText"><?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?></div>
            </div>

            <div class="welcome-section"><h1>Branch Management</h1><p>Manage shelter branches, view status, and track donations.</p></div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL BRANCHES</h3><h2><?php echo $stats['totalBranches']; ?></h2>
                        <p class="<?php echo ($stats['branchTrend'] == 'down') ? 'text-danger' : 'text-success'; ?>">
                            <i class="fas fa-arrow-<?php echo ($stats['branchTrend'] == 'down') ? 'down' : 'up'; ?>"></i> <?php echo ($stats['branchTrend'] == 'down' ? '-' : '+') . $stats['branchPercentChange']; ?>% from last month
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>ACTIVE BRANCHES</h3><h2><?php echo $stats['activeBranches']; ?></h2>
                        <p class="<?php echo ($stats['activeTrend'] == 'down') ? 'text-danger' : 'text-success'; ?>">
                            <i class="fas fa-arrow-<?php echo ($stats['activeTrend'] == 'down') ? 'down' : 'up'; ?>"></i> <?php echo ($stats['activeTrend'] == 'down' ? '-' : '+') . $stats['activePercentChange']; ?>% from last month
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL DONATIONS</h3><h2>RM <?php echo number_format($stats['totalDonationAmount'], 0); ?></h2>
                        <p class="<?php echo ($stats['donationTrend'] == 'down') ? 'text-danger' : 'text-success'; ?>">
                            <i class="fas fa-arrow-<?php echo ($stats['donationTrend'] == 'down') ? 'down' : 'up'; ?>"></i> <?php echo ($stats['donationTrend'] == 'down' ? '-' : '+') . $stats['donationPercentChange']; ?>% from last month
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                </div>
            </div>

            <div class="management-container">
                <div class="section-header">
                    <h2>Branch List</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add New Branch</button>
                        <a href="<?php echo $exportUrl; ?>" class="btn btn-success" target="_blank"><i class="fas fa-download"></i> Export Data</a>
                    </div>
                </div>

                <form method="GET" class="search-filter-container">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilters()">
                            <option value="">Filter By...</option>
                            <option value="name_sort" <?php echo ($filterType == 'name_sort') ? 'selected' : ''; ?>>Name Sorting</option>
                            <option value="id_sort" <?php echo ($filterType == 'id_sort') ? 'selected' : ''; ?>>Branch ID</option>
                            <option value="phone" <?php echo ($filterType == 'phone') ? 'selected' : ''; ?>>Phone Prefix</option>
                            <option value="city" <?php echo ($filterType == 'city') ? 'selected' : ''; ?>>City</option>
                            <option value="capacity" <?php echo ($filterType == 'capacity') ? 'selected' : ''; ?>>Capacity</option>
                            <option value="category" <?php echo ($filterType == 'category') ? 'selected' : ''; ?>>Category</option>
                            <option value="state" <?php echo ($filterType == 'state') ? 'selected' : ''; ?>>State</option>
                            <option value="status" <?php echo ($filterType == 'status') ? 'selected' : ''; ?>>Status</option>
                        </select>
                    </div>
                    <div id="filter_name_container" class="secondary-filter"><select name="filter_val_name" class="filter-select"><option value="">Select Order...</option><option value="asc" <?php if($filterValue == 'asc') echo 'selected'; ?>>Name (A-Z)</option><option value="desc" <?php if($filterValue == 'desc') echo 'selected'; ?>>Name (Z-A)</option></select></div>
                    <div id="filter_id_container" class="secondary-filter"><select name="filter_val_id" class="filter-select"><option value="">Select Order...</option><option value="asc" <?php if($filterValue == 'asc') echo 'selected'; ?>>ID (Ascending)</option><option value="desc" <?php if($filterValue == 'desc') echo 'selected'; ?>>ID (Descending)</option></select></div>
                    <div id="filter_phone_container" class="secondary-filter"><select name="filter_val_phone" class="filter-select"><option value="">Select Prefix...</option><?php foreach($phonePrefixes as $pp): ?><option value="<?php echo $pp; ?>" <?php if($filterValue == $pp) echo 'selected'; ?>>+6<?php echo $pp; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_city_container" class="secondary-filter"><select name="filter_val_city" class="filter-select"><option value="">Select City...</option><?php foreach($cities as $c): ?><option value="<?php echo $c; ?>" <?php if($filterValue == $c) echo 'selected'; ?>><?php echo $c; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_capacity_container" class="secondary-filter"><select name="filter_val_capacity" class="filter-select"><option value="">Select Range...</option><option value="below_100" <?php if($filterValue == 'below_100') echo 'selected'; ?>>Below 100</option><option value="100_500" <?php if($filterValue == '100_500') echo 'selected'; ?>>100 - 500</option><option value="above_500" <?php if($filterValue == 'above_500') echo 'selected'; ?>>Above 500</option></select></div>
                    <div id="filter_category_container" class="secondary-filter"><select name="filter_val_category" class="filter-select"><option value="">All Categories</option><?php foreach($branchTypes as $t) echo "<option value='$t' ".($filterValue==$t?'selected':'').">$t</option>"; ?></select></div>
                    <div id="filter_state_container" class="secondary-filter"><select name="filter_val_state" class="filter-select"><option value="">All States</option><?php foreach($malaysiaStates as $s) echo "<option value='$s' ".($filterValue==$s?'selected':'').">$s</option>"; ?></select></div>
                    <div id="filter_status_container" class="secondary-filter"><select name="filter_val_status" class="filter-select"><option value="Open" <?php if($filterValue == 'Open') echo 'selected'; ?>>Open</option><option value="Closed" <?php if($filterValue == 'Closed') echo 'selected'; ?>>Closed</option></select></div>

                    <input type="text" name="search" class="search-input" placeholder="Search branch name, city..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($search) || !empty($filterType)): ?><a href="branch_management_page.php" class="btn btn-danger" style="padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a><?php endif; ?>
                </form>

                <div class="branch-grid">
                    <?php if (count($branches) > 0): ?>
                        <?php foreach($branches as $b): ?>
                            <?php 
                                $statusClass = ($b['Branch_OperationalStatus'] == 'Open') ? 'status-open' : 'status-closed';
                                $images = json_decode($b['Branch_Images'], true);
                                $hasImages = ($images && !empty($images));
                                if($hasImages) {
                                    $allBranchImagesMap[$b['Branch_ID']] = $images;
                                }
                            ?>
                            <div class="branch-card" id="card-<?php echo $b['Branch_ID']; ?>">
                                <div class="card-actions">
                                    <div class="action-menu">
                                        <button class="menu-btn" onclick="toggleCardMenu(event, <?php echo $b['Branch_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div id="card-menu-<?php echo $b['Branch_ID']; ?>" class="action-dropdown">
                                            <a href="admin_branch_details.php?id=<?php echo $b['Branch_ID']; ?>" class="action-item" target="_blank"><i class="fas fa-eye"></i> View Details</a>
                                            <a href="activity_management.php?filter_type=branch&filter_val_branch=<?php echo $b['Branch_ID']; ?>" class="action-item"><i class="fas fa-calendar-alt"></i> View Activities</a>
                                            <div class="action-item" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($b)); ?>)"><i class="fas fa-edit"></i> Edit Branch</div>
                                            <div class="action-item" onclick="openPaymentHistory(<?php echo $b['Branch_ID']; ?>)"><i class="fas fa-history"></i> Donation History</div>
                                            <div class="action-item" onclick="openWithdrawalHistory(<?php echo $b['Branch_ID']; ?>)"><i class="fas fa-money-bill-wave"></i> Withdrawal History</div>
                                            <div class="action-item delete" onclick="confirmDelete(<?php echo $b['Branch_ID']; ?>)"><i class="fas fa-trash"></i> Delete</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-image-container">
                                    <?php if($hasImages): ?>
                                        <?php foreach($images as $idx => $img): ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" class="card-img <?php echo $idx===0?'active':''; ?>" onclick="openLightbox(<?php echo $b['Branch_ID']; ?>, <?php echo $idx; ?>)">
                                        <?php endforeach; ?>
                                        <?php if(count($images) > 1): ?>
                                            <button class="carousel-btn prev-btn" onclick="moveCarousel(<?php echo $b['Branch_ID']; ?>, -1)">&#10094;</button>
                                            <button class="carousel-btn next-btn" onclick="moveCarousel(<?php echo $b['Branch_ID']; ?>, 1)">&#10095;</button>
                                            <span class="img-counter">1/<?php echo count($images); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="no-image-placeholder"><i class="fas fa-image"></i><span>No Image Available</span></div>
                                    <?php endif; ?>
                                    <div class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($b['Branch_OperationalStatus'] ?: 'Open'); ?></div>
                                </div>
                                
                                <div class="card-content">
                                    <div class="branch-type"><?php echo htmlspecialchars($b['Branch_Type']); ?></div>
                                    <div class="branch-title"><?php echo htmlspecialchars($b['Branch_Name']); ?></div>
                                    
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <div class="info-icon"><i class="fas fa-envelope"></i></div>
                                            <div class="info-text"><span class="info-label">Email</span><?php echo htmlspecialchars($b['Branch_Email']); ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-icon"><i class="fas fa-phone"></i></div>
                                            <div class="info-text"><span class="info-label">Contact</span><?php echo htmlspecialchars($b['Branch_ContactNumber']); ?></div>
                                        </div>
                                    </div>

                                    <div class="card-stats">
                                        <div><span class="raised-label">Total Donated</span><span class="raised-amount">RM <?php echo number_format($b['TotalDonated'], 2); ?></span></div>
                                        <a href="admin_branch_details.php?id=<?php echo $b['Branch_ID']; ?>" target="_blank" style="color:var(--primary); font-size:12px; font-weight:bold; text-decoration:none;">More Info <i class="fas fa-arrow-right"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align:center; padding:50px; color:#888;">No active branches found.</div>
                    <?php endif; ?>
                </div>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        // ... (Pagination PHP logic same as provided) ...
                        $queryParams = []; 
                        if(!empty($search)) $queryParams['search'] = $search;
                        if(!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if($filterType == 'category' && !empty($filterValue)) $queryParams['filter_val_category'] = $filterValue;
                            elseif($filterType == 'state' && !empty($filterValue)) $queryParams['filter_val_state'] = $filterValue;
                            elseif($filterType == 'status' && !empty($filterValue)) $queryParams['filter_val_status'] = $filterValue;
                            elseif($filterType == 'name_sort' && !empty($filterValue)) $queryParams['filter_val_name'] = $filterValue;
                            elseif($filterType == 'id_sort' && !empty($filterValue)) $queryParams['filter_val_id'] = $filterValue;
                            elseif($filterType == 'phone' && !empty($filterValue)) $queryParams['filter_val_phone'] = $filterValue;
                            elseif($filterType == 'city' && !empty($filterValue)) $queryParams['filter_val_city'] = $filterValue;
                            elseif($filterType == 'capacity' && !empty($filterValue)) $queryParams['filter_val_capacity'] = $filterValue;
                        }
                        $queryString = http_build_query($queryParams);
                        $paginationUrl = !empty($queryString) ? '&' . $queryString : '';

                        if ($page > 1) echo '<a href="?page=' . ($page - 1) . $paginationUrl . '" class="pagination-btn">Previous</a>';
                        else echo '<span class="pagination-btn disabled">Previous</span>';

                        $start_window = max(1, $page - 1);
                        $end_window = min($total_pages, $page + 1);
                        if ($page == 1) $end_window = min($total_pages, 3);
                        if ($page == $total_pages) $start_window = max(1, $total_pages - 2);

                        for ($i = $start_window; $i <= $end_window; $i++) {
                            if ($i == $page) echo '<span class="pagination-btn active">' . $i . '</span>';
                            else echo '<a href="?page=' . $i . $paginationUrl . '" class="pagination-btn">' . $i . '</a>';
                        }

                        if ($page < $total_pages) echo '<a href="?page=' . ($page + 1) . $paginationUrl . '" class="pagination-btn">Next</a>';
                        else echo '<span class="pagination-btn disabled">Next</span>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Branch</h2><button class="close-btn" onclick="closeModal('addModal')">&times;</button></div>
            <div class="modal-body">
                <form action="branch_management_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm('add')" novalidate>
                    <input type="hidden" name="add_branch" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Branch Images <span class="required">*</span></label>
                        <div class="upload-container">
                            <div class="upload-box">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click or Drag images here to upload</p>
                                <input type="file" id="add_branch_images" name="branch_images[]" multiple accept="image/*" onchange="handleFileSelect(event, 'add')" required>
                            </div>
                            <div class="preview-grid" id="add_preview_container"></div>
                        </div>
                        <small class="form-guide">Upload high-quality images of the branch premise (JPG/PNG). First image will be the cover.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Branch Name <span class="required">*</span></label>
                        <input type="text" name="branch_name" id="add_branch_name" class="form-input" required placeholder="e.g. Sunny Shelter KL">
                        <small class="form-guide">The official registered name of this specific branch.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <select name="branch_type" id="add_branch_type" class="form-select" required>
                                <option value="">Select Category...</option>
                                <?php foreach($branchTypes as $t) echo "<option value='$t'>$t</option>"; ?>
                            </select>
                            <small class="form-guide">Select the primary function of this establishment.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <select name="operational_status" id="add_operational_status" class="form-select" required>
                                <option value="Open">Open</option>
                                <option value="Closed">Closed</option>
                            </select>
                            <small class="form-guide">Current operational state (Open for visits/donations).</small>
                        </div>
                    </div>
                    
                    <div class="section-separator"><span>Bank Information</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Bank Name <span class="required">*</span></label>
                            <select name="bank_name" id="add_bank_name" class="form-select" onchange="setupBankValidation('add')" required>
                                <option value="">-- Select Bank --</option>
                                <?php foreach($malaysiaBanks as $short => $full): ?>
                                    <option value="<?php echo $short; ?>"><?php echo $full; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-guide">Select the bank where donations will be received.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number <span class="required">*</span></label>
                            <input type="text" name="bank_account" id="add_bank_account" class="form-input" placeholder="Select a bank first" oninput="handleBankInput('add')" required>
                            <div id="add_bank_counter" style="font-size:12px; margin-top:2px; font-weight:bold; color:#dc3545;"></div>
                            <small class="form-guide">Enter the account number without dashes (e.g. 1122334455).</small>
                            <div id="addBankError" class="error-message">Invalid account number length.</div>
                        </div>
                    </div>

                    <div class="section-separator"><span>Branch Contact Info</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Branch Email <span class="required">*</span></label>
                            <input type="email" name="email" id="add_email" class="form-input" required placeholder="branch@example.com">
                            <small class="form-guide">Official email address for general inquiries.</small>
                            <div id="addEmailError" class="error-message">Invalid email format.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Branch Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" name="contact_number" id="add_contact" class="form-input phone-input" placeholder="11-12345678" required maxlength="11">
                            </div>
                            <small class="form-guide">Official landline or mobile number for the office.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Person In Charge Info</span></div>
                    <div class="form-group">
                        <label class="form-label">PIC Name <span class="required">*</span></label>
                        <input type="text" name="branch_head" id="add_branch_head" class="form-input" required placeholder="e.g. Mr. John Doe">
                        <small class="form-guide">Full name of the branch manager or person in charge.</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">PIC Email <span class="required">*</span></label>
                            <input type="email" name="branch_head_email" id="add_head_email" class="form-input" required placeholder="manager@example.com">
                            <small class="form-guide">Direct email for the Person In Charge.</small>
                            <div id="addHeadEmailError" class="error-message">Invalid email format.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">PIC Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" name="branch_head_contact" id="add_head_contact" class="form-input phone-input" placeholder="12-3456789" required maxlength="11">
                            </div>
                            <small class="form-guide">Direct mobile contact for the Person In Charge.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Details</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Capacity (Pax) <span class="required">*</span></label>
                            <input type="number" name="capacity" id="add_capacity" class="form-input" required placeholder="e.g. 50">
                            <small class="form-guide">Maximum number of occupants this branch can support.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Est. Date <span class="required">*</span></label>
                            <input type="date" name="est_date" id="add_est_date" class="form-input" required>
                            <small class="form-guide">Date when this branch was established.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1 <span class="required">*</span></label>
                        <input type="text" name="address1" id="add_address1" class="form-input" required placeholder="Unit No, Building Name">
                        <small class="form-guide">Primary address line (e.g. No 15, Jalan Bahagia).</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2 <span class="required">*</span></label>
                        <input type="text" name="address2" id="add_address2" class="form-input" required placeholder="Street Name, Area">
                        <small class="form-guide">Secondary address line (e.g. Taman Gembira).</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" id="add_address3" class="form-input" placeholder="Additional landmarks (Optional)">
                        <small class="form-guide">Any additional address details.</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postcode <span class="required">*</span></label>
                            <input type="text" name="postal_code" id="add_postal_code" class="form-input" required placeholder="e.g. 50450">
                            <small class="form-guide">5-digit postal code. State will auto-select based on this.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <span class="required">*</span></label>
                            <input type="text" name="city" id="add_city" class="form-input" required placeholder="e.g. Kuala Lumpur">
                            <small class="form-guide">City or district name.</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State <span class="required">*</span></label>
                            <select name="state" id="add_state" class="form-select" required>
                                <option value="">Select State...</option>
                                <?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?>
                            </select>
                            <small class="form-guide">State where the branch is located.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-input" value="Malaysia" readonly style="background:#f8f9fa;">
                            <small class="form-guide">Default country.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description (Full Details) <span class="required">*</span></label>
                        <textarea name="description" id="add_description" class="form-textarea" required placeholder="Describe the branch's mission, history, and current needs..."></textarea>
                        <small class="form-guide">Detailed description that will appear on the public page.</small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">Save Branch</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Branch</h2><button class="close-btn" onclick="closeModal('editModal')">&times;</button></div>
            <div class="modal-body">
                <form action="branch_management_page.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm('edit')" novalidate>
                    <input type="hidden" name="update_branch" value="1">
                    <input type="hidden" name="branch_id" id="edit_branch_id">
                    <input type="hidden" name="existing_images_json" id="edit_existing_images_input">
                    
                    <div class="form-group">
                        <label class="form-label">Branch Images <span class="required">*</span></label>
                        <div class="upload-container">
                            <div class="upload-box">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Add more images</p>
                                <input type="file" id="edit_branch_images" name="branch_images[]" multiple accept="image/*" onchange="handleFileSelect(event, 'edit')">
                            </div>
                            <div class="preview-grid" id="edit_preview_container"></div>
                        </div>
                        <small class="form-guide">Manage existing images or add new ones (JPG/PNG).</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Branch Name <span class="required">*</span></label>
                        <input type="text" name="branch_name" id="edit_branch_name" class="form-input" required placeholder="e.g. Sunny Shelter KL">
                        <small class="form-guide">The official registered name of this specific branch.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <select name="branch_type" id="edit_branch_type" class="form-select" required><?php foreach($branchTypes as $t) echo "<option value='$t'>$t</option>"; ?></select>
                            <small class="form-guide">Select the primary function of this establishment.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <select name="operational_status" id="edit_operational_status" class="form-select" required><option value="Open">Open</option><option value="Closed">Closed</option></select>
                            <small class="form-guide">Current operational state.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Bank Information</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Bank Name <span class="required">*</span></label>
                            <select name="bank_name" id="edit_bank_name" class="form-select" onchange="setupBankValidation('edit')" required>
                                <option value="">-- Select Bank --</option>
                                <?php foreach($malaysiaBanks as $short => $full): ?>
                                    <option value="<?php echo $short; ?>"><?php echo $full; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-guide">Bank for receiving funds.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number <span class="required">*</span></label>
                            <input type="text" name="bank_account" id="edit_bank_account" class="form-input" placeholder="e.g. 1122334455" oninput="handleBankInput('edit')" required>
                            <div id="edit_bank_counter" style="font-size:12px; margin-top:2px; font-weight:bold; color:#dc3545;"></div>
                            <small class="form-guide">Enter account number (Format depends on selected bank).</small>
                            <div id="editBankError" class="error-message">Invalid account number length.</div>
                        </div>
                    </div>

                    <div class="section-separator"><span>Branch Contact Info</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Branch Email <span class="required">*</span></label>
                            <input type="email" name="email" id="edit_email" class="form-input" required placeholder="branch@example.com">
                            <small class="form-guide">Official email address.</small>
                            <div id="editEmailError" class="error-message">Invalid email format.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Branch Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" name="contact_number" id="edit_contact_number" class="form-input phone-input" required maxlength="11" placeholder="11-12345678">
                            </div>
                            <small class="form-guide">Official office contact number.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Person In Charge Info</span></div>
                    <div class="form-group">
                        <label class="form-label">PIC Name <span class="required">*</span></label>
                        <input type="text" name="branch_head" id="edit_branch_head" class="form-input" required placeholder="e.g. Mr. John Doe">
                        <small class="form-guide">Full name of the Person In Charge.</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">PIC Email <span class="required">*</span></label>
                            <input type="email" name="branch_head_email" id="edit_head_email" class="form-input" required placeholder="manager@example.com">
                            <small class="form-guide">Direct email for the PIC.</small>
                            <div id="editHeadEmailError" class="error-message">Invalid email format.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">PIC Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+60</span>
                                <input type="text" name="branch_head_contact" id="edit_head_contact" class="form-input phone-input" required maxlength="11" placeholder="12-3456789">
                            </div>
                            <small class="form-guide">Direct mobile contact for the PIC.</small>
                        </div>
                    </div>

                    <div class="section-separator"><span>Details</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Capacity <span class="required">*</span></label>
                            <input type="number" name="capacity" id="edit_capacity" class="form-input" required placeholder="e.g. 50">
                            <small class="form-guide">Maximum pax capacity.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Est. Date <span class="required">*</span></label>
                            <input type="date" name="est_date" id="edit_est_date" class="form-input" required>
                            <small class="form-guide">Date established.</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address 1 <span class="required">*</span></label>
                        <input type="text" name="address1" id="edit_address1" class="form-input" required placeholder="Unit No, Building Name">
                        <small class="form-guide">Primary address line.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address 2 <span class="required">*</span></label>
                        <input type="text" name="address2" id="edit_address2" class="form-input" required placeholder="Street Name, Area">
                        <small class="form-guide">Secondary address line.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address 3</label>
                        <input type="text" name="address3" id="edit_address3" class="form-input" placeholder="Additional landmarks">
                        <small class="form-guide">Additional info (optional).</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Postcode <span class="required">*</span></label><input type="text" name="postal_code" id="edit_postal_code" class="form-input" required placeholder="e.g. 50450"><small class="form-guide">5-digit postal code.</small></div>
                        <div class="form-group"><label class="form-label">City <span class="required">*</span></label><input type="text" name="city" id="edit_city" class="form-input" required placeholder="e.g. Kuala Lumpur"><small class="form-guide">City name.</small></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State <span class="required">*</span></label>
                            <select name="state" id="edit_state" class="form-select" required><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select>
                            <small class="form-guide">State.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" id="edit_country" name="country" class="form-input" value="Malaysia" readonly>
                            <small class="form-guide">Country is fixed to Malaysia.</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description <span class="required">*</span></label>
                        <textarea name="description" id="edit_description" class="form-textarea" required placeholder="Description..."></textarea>
                        <small class="form-guide">Full description of the branch.</small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">Update Branch</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="paymentModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Donation History</h2><button class="close-btn" onclick="closeModal('paymentModal')">&times;</button></div>
            <div class="modal-body"><div id="paymentListContainer" class="history-list">Loading...</div></div>
        </div>
    </div>

    <div class="modal" id="withdrawalModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Withdrawal History</h2>
                <button class="close-btn" onclick="closeModal('withdrawalModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:14px; color:#555; font-weight:600;">Total Withdrawn:</span>
                    <span id="totalWithdrawnDisplay" style="font-size:18px; font-weight:bold; color:#dc3545;">RM 0.00</span>
                </div>
                <div id="withdrawalListContainer" class="history-list">Loading...</div>
            </div>
        </div>
    </div>

    <div id="imageLightbox" class="lightbox-modal">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <a class="lightbox-nav lightbox-prev" onclick="changeLightboxImage(-1)">&#10094;</a>
        <img class="lightbox-content" id="lightboxImage">
        <a class="lightbox-nav lightbox-next" onclick="changeLightboxImage(1)">&#10095;</a>
    </div>

    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="background-color: #dc3545; color: white; justify-content:center;">
                <h2 style="color:white; margin:0; font-size:18px;"><i class="fas fa-exclamation-triangle"></i> Delete Branch</h2>
            </div>
            <div class="modal-body">
                <p style="color:#555; margin-bottom:20px;">Are you sure you want to delete this branch?<br>This action cannot be undone.</p>
                <div style="display: flex; justify-content: center; gap: 10px;">
                    <button class="btn" style="background:#eee; color:#333;" onclick="closeModal('deleteModal')">Cancel</button>
                    <a id="confirmDeleteBtn" href="#" class="btn btn-danger">Yes, Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showSystemError(messageHTML) {
            const errorBox = document.getElementById('floatingError');
            const errorText = document.getElementById('floatingErrorText');
            if(errorBox && errorText) {
                errorText.innerHTML = messageHTML;
                errorBox.style.display = 'flex';
                setTimeout(() => { errorBox.style.display = 'none'; }, 5000);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.getElementById('floatingSuccess');
            const errorAlert = document.getElementById('floatingError');
            if (successAlert && successAlert.style.display === 'flex') setTimeout(() => { successAlert.style.display = 'none'; }, 5000);
            if (errorAlert && errorAlert.style.display === 'flex') setTimeout(() => { errorAlert.style.display = 'none'; }, 5000);
            toggleFilters();
        });

        // --- MULTI-UPLOAD LOGIC (Same as before) ---
        let addFiles = []; 
        let editNewFiles = []; 
        let editExistingImages = []; 

        function handleFileSelect(event, mode) {
            const input = event.target;
            const newFiles = Array.from(input.files);
            if (mode === 'add') {
                addFiles = addFiles.concat(newFiles);
                updateFileInput('add_branch_images', addFiles);
                renderPreview('add_preview_container', addFiles, 'add');
            } else if (mode === 'edit') {
                editNewFiles = editNewFiles.concat(newFiles);
                updateFileInput('edit_branch_images', editNewFiles);
                renderEditPreviews();
            }
        }

        function removeFile(index, mode) {
            if (mode === 'add') {
                addFiles.splice(index, 1);
                updateFileInput('add_branch_images', addFiles);
                renderPreview('add_preview_container', addFiles, 'add');
            } else if (mode === 'edit') {
                editNewFiles.splice(index, 1);
                updateFileInput('edit_branch_images', editNewFiles);
                renderEditPreviews();
            }
        }

        function removeExistingImage(index) {
            editExistingImages.splice(index, 1);
            document.getElementById('edit_existing_images_input').value = JSON.stringify(editExistingImages);
            renderEditPreviews();
        }

        function updateFileInput(inputId, fileArray) {
            const dataTransfer = new DataTransfer();
            fileArray.forEach(file => dataTransfer.items.add(file));
            document.getElementById(inputId).files = dataTransfer.files;
        }

        function renderPreview(containerId, fileArray, mode) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            fileArray.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.innerHTML = `<img src="${e.target.result}"><button type="button" class="remove-img-btn" onclick="removeFile(${index}, '${mode}')"><i class="fas fa-times"></i></button>`;
                    container.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        }

        function renderEditPreviews() {
            const container = document.getElementById('edit_preview_container');
            container.innerHTML = '';
            editExistingImages.forEach((src, index) => {
                const item = document.createElement('div');
                item.className = 'preview-item';
                item.innerHTML = `<img src="${src}"><button type="button" class="remove-img-btn" onclick="removeExistingImage(${index})"><i class="fas fa-times"></i></button>`;
                container.appendChild(item);
            });
            editNewFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.innerHTML = `<img src="${e.target.result}"><button type="button" class="remove-img-btn" onclick="removeFile(${index}, 'edit')"><i class="fas fa-times"></i></button>`;
                    container.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        }

        function toggleFilters() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            if(type) {
                const el = document.getElementById('filter_' + type + '_container');
                if(el) { el.classList.add('active'); el.querySelector('select').disabled = false; }
            }
        }

        function toggleCardMenu(event, id) {
            event.stopPropagation();
            document.querySelectorAll('.action-dropdown').forEach(d => { if (d.id !== 'card-menu-' + id) d.classList.remove('show'); });
            const menu = document.getElementById('card-menu-' + id);
            menu.classList.toggle('show');
        }

        window.onclick = function(event) {
            if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('show'));
            if (event.target.classList.contains('modal')) event.target.style.display = 'none';
            if (event.target.id == 'imageLightbox') closeLightbox();
        }

        function openAddModal() { 
            addFiles = []; updateFileInput('add_branch_images', []); document.getElementById('add_preview_container').innerHTML = ''; document.getElementById('addModal').style.display = 'flex'; 
        }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openEditModal(branch) {
            document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('show')); 
            editNewFiles = []; updateFileInput('edit_branch_images', []);
            try { editExistingImages = JSON.parse(branch.Branch_Images || "[]"); } catch(e) { editExistingImages = []; }
            document.getElementById('edit_existing_images_input').value = JSON.stringify(editExistingImages);

            document.getElementById('edit_branch_id').value = branch.Branch_ID;
            document.getElementById('edit_branch_name').value = branch.Branch_Name;
            document.getElementById('edit_branch_type').value = branch.Branch_Type;
            document.getElementById('edit_operational_status').value = branch.Branch_OperationalStatus || 'Open';
            
            // Bank Info Population
            document.getElementById('edit_bank_name').value = branch.Branch_BankName || "";
            document.getElementById('edit_bank_account').value = branch.Branch_BankAccount || "";
            // Trigger validation setup for pre-filled data
            setupBankValidation('edit');

            document.getElementById('edit_branch_head').value = branch.Branch_Head;
            document.getElementById('edit_head_email').value = branch.Branch_Head_Email || '';
            let headPhone = branch.Branch_Head_Contact || ''; if(headPhone.startsWith('+60')) headPhone = headPhone.substring(3);
            document.getElementById('edit_head_contact').value = headPhone;

            document.getElementById('edit_capacity').value = branch.Branch_Capacity;
            document.getElementById('edit_est_date').value = branch.Branch_EstablishedDate;
            document.getElementById('edit_email').value = branch.Branch_Email || '';
            let phone = branch.Branch_ContactNumber; if(phone && phone.startsWith('+60')) phone = phone.substring(3);
            document.getElementById('edit_contact_number').value = phone;

            document.getElementById('edit_address1').value = branch.Branch_Address1;
            document.getElementById('edit_address2').value = branch.Branch_Address2;
            document.getElementById('edit_address3').value = branch.Branch_Address3;
            document.getElementById('edit_city').value = branch.Branch_City;
            document.getElementById('edit_state').value = branch.Branch_State;
            document.getElementById('edit_postal_code').value = branch.Branch_PostalCode;
            document.getElementById('edit_country').value = branch.Branch_Country;
            document.getElementById('edit_description').value = branch.Branch_Description;

            renderEditPreviews();
            document.getElementById('editModal').style.display = 'flex';
        }

        // --- CAROUSEL & LIGHTBOX (Same) ---
        const allBranchImages = <?php echo json_encode($allBranchImagesMap); ?>;
        let currentLightboxBranchId = null; let currentLightboxIndex = 0;

        function moveCarousel(cardId, direction) {
            const card = document.getElementById('card-' + cardId);
            const images = card.querySelectorAll('.card-img');
            const counter = card.querySelector('.img-counter');
            let activeIndex = 0;
            images.forEach((img, index) => { if (img.classList.contains('active')) activeIndex = index; img.classList.remove('active'); });
            let newIndex = activeIndex + direction;
            if (newIndex >= images.length) newIndex = 0; if (newIndex < 0) newIndex = images.length - 1;
            images[newIndex].classList.add('active');
            if(counter) counter.innerText = (newIndex + 1) + '/' + images.length;
        }

        function openLightbox(branchId, index) {
            if (!allBranchImages[branchId] || allBranchImages[branchId].length === 0) return;
            currentLightboxBranchId = branchId; currentLightboxIndex = index;
            updateLightboxImage();
            document.getElementById('imageLightbox').style.display = "flex";
        }
        function closeLightbox() { document.getElementById('imageLightbox').style.display = "none"; }
        function changeLightboxImage(n) {
            if (currentLightboxBranchId === null) return;
            const images = allBranchImages[currentLightboxBranchId];
            currentLightboxIndex += n;
            if (currentLightboxIndex >= images.length) currentLightboxIndex = 0;
            else if (currentLightboxIndex < 0) currentLightboxIndex = images.length - 1;
            updateLightboxImage();
        }
        function updateLightboxImage() {
            const images = allBranchImages[currentLightboxBranchId];
            document.getElementById('lightboxImage').src = images[currentLightboxIndex];
            const prevBtn = document.querySelector('.lightbox-prev'); const nextBtn = document.querySelector('.lightbox-next');
            if (images.length <= 1) { prevBtn.style.display = 'none'; nextBtn.style.display = 'none'; } else { prevBtn.style.display = 'block'; nextBtn.style.display = 'block'; }
        }
        document.addEventListener('keydown', function(event) { if (document.getElementById('imageLightbox').style.display === "flex") { if (event.key === "Escape") closeLightbox(); if (event.key === "ArrowLeft") changeLightboxImage(-1); if (event.key === "ArrowRight") changeLightboxImage(1); } });

        // --- PAYMENT & WITHDRAWAL HISTORY MODALS (Updated) ---
        function openPaymentHistory(branchId) {
            document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('show')); 
            document.getElementById('paymentModal').style.display = 'flex';
            const container = document.getElementById('paymentListContainer');
            container.innerHTML = '<div style="text-align:center; padding:20px;">Loading...</div>';
            fetch(`branch_management_page.php?action=get_payment_history&branch_id=${branchId}`)
                .then(r => r.json()).then(data => {
                    container.innerHTML = '';
                    if (data.length === 0) { container.innerHTML = '<div style="text-align:center; padding:20px; color:#888;">No donation history found.</div>'; return; }
                    data.forEach(txn => {
                        const item = document.createElement('div'); item.className = 'history-item';
                        item.innerHTML = `<div class="h-left"><h4>${txn.Donor_Name}</h4><p>${txn.Formatted_Date} | Ref: ${txn.Order_TXN_Ref}</p></div><div class="h-right">+ RM ${txn.Formatted_Amount}</div>`;
                        container.appendChild(item);
                    });
                });
        }

        function openWithdrawalHistory(branchId) {
            document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('show')); 
            document.getElementById('withdrawalModal').style.display = 'flex';
            const container = document.getElementById('withdrawalListContainer');
            const totalDisplay = document.getElementById('totalWithdrawnDisplay');
            
            container.innerHTML = '<div style="text-align:center; padding:20px;">Loading...</div>';
            totalDisplay.innerText = "RM 0.00";

            fetch(`branch_management_page.php?action=get_withdrawal_history&branch_id=${branchId}`)
                .then(r => r.json()).then(res => {
                    container.innerHTML = '';
                    totalDisplay.innerText = "RM " + res.total_withdrawn;
                    
                    if (res.history.length === 0) { 
                        container.innerHTML = '<div style="text-align:center; padding:20px; color:#888;">No withdrawal records found.</div>'; 
                        return; 
                    }
                    
                    res.history.forEach(w => {
                        const item = document.createElement('div'); 
                        item.className = 'history-item';
                        
                        // --- CLICK EVENT TO OPEN DETAILS IN NEW TAB ---
                        item.style.cursor = 'pointer';
                        item.title = "Click to view full details";
                        item.onclick = function() {
                            window.open('admin_withdrawal_details.php?id=' + w.Withdrawal_ID, '_blank');
                        };

                        const desc = w.Activity_Name ? `<b>Activity:</b> ${w.Activity_Name}` : "General Fund Withdrawal";
                        item.innerHTML = `
                            <div class="h-left">
                                <h4>${desc}</h4>
                                <p>${w.Formatted_Date} | Status: <span style="font-weight:bold; color:${w.Status=='Approved'?'green':'orange'}">${w.Status}</span></p>
                            </div>
                            <div class="h-right-negative">- RM ${w.Formatted_Amount}</div>
                        `;
                        container.appendChild(item);
                    });
                });
        }

        function confirmDelete(id) { 
            const link = document.getElementById('confirmDeleteBtn'); link.href = `branch_management_page.php?delete_id=${id}`; document.getElementById('deleteModal').style.display = 'flex'; 
        }

        function setupPhoneInput(inputId) {
            const input = document.getElementById(inputId); if(!input) return;
            input.addEventListener('input', function(e) { let val = this.value.replace(/\D/g, ''); if (val.length > 11) val = val.substring(0, 11); let newVal = val; if (val.length > 2) newVal = val.substring(0, 2) + '-' + val.substring(2); this.value = newVal; });
        }
        setupPhoneInput('add_contact'); setupPhoneInput('edit_contact_number'); setupPhoneInput('add_head_contact'); setupPhoneInput('edit_head_contact');

        // --- NEW BANK ACCOUNT VALIDATION LOGIC ---
        
        // Define rules for Malaysia Banks (Key matches the $malaysiaBanks array keys)
        const bankRules = {
            "Maybank": { digits: 12 }, // Can be 10-12, but modern usually 12
            "CIMB": { digits: 10 },
            "Public Bank": { digits: 10 },
            "RHB": { digits: 10 },
            "Hong Leong": { digits: 11 },
            "AmBank": { digits: 13 },
            "UOB": { digits: 11 },
            "Bank Rakyat": { digits: 12 },
            "OCBC": { digits: 10 }, // Or 12
            "HSBC": { digits: 12 },
            "Bank Islam": { digits: 14 },
            "Affin Bank": { digits: 10 },
            "Alliance Bank": { digits: 10 },
            "Standard Chartered": { digits: 10 },
            "MBSB": { digits: 10 }, // Variable
            "Citibank": { digits: 10 },
            "Bank Muamalat": { digits: 14 },
            "Agrobank": { digits: 15 }, // Or 12
            "BSN": { digits: 16 }
        };

        function setupBankValidation(mode) {
            const bankSelect = document.getElementById(mode + '_bank_name');
            const accInput = document.getElementById(mode + '_bank_account');
            
            if(!bankSelect || !accInput) return;

            const selectedBank = bankSelect.value;
            const rule = bankRules[selectedBank];

            if (selectedBank && rule) {
                // Set the hard limit so users cannot type more
                accInput.maxLength = rule.digits;
                
                // If they change bank and existing is too long, truncate it
                if (accInput.value.length > rule.digits) {
                    accInput.value = accInput.value.slice(0, rule.digits);
                }

                accInput.placeholder = `Enter ${rule.digits} digits for ${selectedBank}`;
                // Trigger an update to the counter text immediately
                handleBankInput(mode);
            } else {
                accInput.removeAttribute('maxLength'); // No limit if no bank selected yet
                accInput.placeholder = "Select a bank first";
                document.getElementById(mode + '_bank_counter').innerText = "";
            }
        }

        // New function to handle input counting and validation
        function handleBankInput(mode) {
            const input = document.getElementById(mode + '_bank_account');
            const counter = document.getElementById(mode + '_bank_counter');
            const bankSelect = document.getElementById(mode + '_bank_name');
            
            // 1. Enforce Numbers Only
            input.value = input.value.replace(/[^0-9]/g, '');

            // 2. Update Counter
            const rule = bankRules[bankSelect.value];
            if (rule) {
                const current = input.value.length;
                const max = rule.digits;
                counter.innerText = `Digits: ${current} / ${max}`;
                
                // Visual feedback
                if (current === max) {
                    counter.style.color = '#28a745'; // Green when done
                } else {
                    counter.style.color = '#dc3545'; // Red/Orange while typing
                }
            } else {
                counter.innerText = "";
            }
        }

        function checkEmail(val) { if(!val) return true; return /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|my)$/i.test(val); }

        function validateBankAccount(mode) {
            const bankSelect = document.getElementById(mode + '_bank_name');
            const accInput = document.getElementById(mode + '_bank_account');
            const errorMsg = document.getElementById(mode + 'BankError');
            
            // If no bank is selected, account is optional/irrelevant unless user typed in it
            if (!bankSelect.value) {
                return "Bank Name is required.";
            }

            if (!accInput.value.trim()) {
                return "Bank Account Number is required.";
            }

            const rule = bankRules[bankSelect.value];
            if (rule && accInput.value.length !== rule.digits) {
                return `${bankSelect.value} account number must be ${rule.digits} digits.`;
            }

            if(errorMsg) errorMsg.style.display = 'none';
            return ""; // No error
        }

        function validateForm(mode) {
            let errors = [];
            
            // 1. Basic Fields
            const name = document.getElementById(mode + '_branch_name').value.trim();
            const type = document.getElementById(mode + '_branch_type').value;
            const status = document.getElementById(mode + '_operational_status').value;
            const capacity = document.getElementById(mode + '_capacity').value;
            const estDate = document.getElementById(mode + '_est_date').value;
            
            if (!name) errors.push("Branch Name is required.");
            if (!type) errors.push("Branch Category is required.");
            if (!status) errors.push("Operational Status is required.");
            if (!capacity) errors.push("Capacity is required.");
            if (!estDate) errors.push("Established Date is required.");

            // 2. Bank Validation
            const bankErr = validateBankAccount(mode);
            if (bankErr) errors.push(bankErr);

            // 3. Email Validation
            const email = document.getElementById(mode + '_email').value;
            if(!email) errors.push("Branch Email is required.");
            else if(!checkEmail(email)) errors.push("Invalid Branch Email format.");
            
            // 4. Contact Validation
            const contact = document.getElementById(mode === 'add' ? 'add_contact' : 'edit_contact_number').value;
            if(!contact) errors.push("Branch Contact Number is required.");
            else if(contact.length < 8) errors.push("Branch Contact Number is too short.");

            // 5. PIC Info
            const picName = document.getElementById(mode + '_branch_head').value.trim();
            if(!picName) errors.push("PIC Name is required.");
            
            const headEmail = document.getElementById(mode + '_head_email').value;
            if(!headEmail) errors.push("PIC Email is required.");
            else if(!checkEmail(headEmail)) errors.push("Invalid PIC Email format.");

            const headContact = document.getElementById(mode === 'add' ? 'add_head_contact' : 'edit_head_contact').value;
            if(!headContact) errors.push("PIC Contact Number is required.");
            else if(headContact.length < 8) errors.push("PIC Contact Number is too short.");

            // 6. Address Info
            const addr1 = document.getElementById(mode + '_address1').value.trim();
            const addr2 = document.getElementById(mode + '_address2').value.trim();
            const postcode = document.getElementById(mode + '_postal_code').value.trim();
            const city = document.getElementById(mode + '_city').value.trim();
            const state = document.getElementById(mode + '_state').value;
            const desc = document.getElementById(mode + '_description').value.trim();

            if(!addr1) errors.push("Address Line 1 is required.");
            if(!addr2) errors.push("Address Line 2 is required.");
            if(!postcode) errors.push("Postal Code is required.");
            if(!city) errors.push("City is required.");
            if(!state) errors.push("State is required.");
            if(!desc) errors.push("Description is required.");

            // 7. Image Check (Add Mode Only)
            if (mode === 'add') {
                const imgInput = document.getElementById('add_branch_images');
                if (!addFiles || addFiles.length === 0) {
                    errors.push("At least one Branch Image is required.");
                }
            }

            if (errors.length > 0) {
                // Show floating alert system message
                showSystemError("Please correct the following errors:<br>" + errors.join("<br>"));
                return false;
            }

            return true;
        }

        function setupPostcodeState(postcodeId, stateSelectId) {
            const pcInput = document.getElementById(postcodeId); const stateSelect = document.getElementById(stateSelectId); if (!pcInput || !stateSelect) return;
            pcInput.addEventListener('input', function() {
                const val = this.value.replace(/\D/g, '');
                if (val.length >= 2) {
                    const prefix = parseInt(val.substring(0, 2)); let state = "";
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
        setupPostcodeState('add_postal_code', 'add_state'); setupPostcodeState('edit_postal_code', 'edit_state');
    </script>
</body>
</html>