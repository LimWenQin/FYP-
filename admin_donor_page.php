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

// --- AJAX HANDLER FOR HISTORY ---
if (isset($_GET['action']) && $_GET['action'] == 'get_donor_history' && isset($_GET['donor_id'])) {
    $histDonorId = intval($_GET['donor_id']);
    $type = $_GET['type'];
    
    if ($type == 'payment') {
        $histSql = "SELECT Order_Created_At, Order_TXN_Ref, Order_Amount, Order_Status 
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

// --- FILE UPLOAD HELPER FUNCTION ---
function handleProfileUpload($file) {
    if (isset($file) && $file['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowedTypes)) {
            $uploadDir = 'uploads/donors/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'donor_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return $uploadPath;
            }
        }
    }
    return null;
}

// Handle Add Donor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_donor'])) {
    $donorName = mysqli_real_escape_string($conn, $_POST['donor_name']);
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
    
    $password = $_POST['password'];
    
    // Handle Profile Picture Upload
    $profilePicture = null;
    if (isset($_FILES['profile_picture'])) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) {
            $profilePicture = $uploadedPath;
        }
    }
    
    // Validation - ONLY Mandatory Fields
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.(com|net|org|edu|gov|my)$/i', $email)) {
        $errorMessage = "Invalid email format.";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $donorName)) {
        $errorMessage = "Name can only contain letters.";
    } elseif (!preg_match('/^\+601[0-9]-[0-9]{7,10}$/', $contact)) { 
        $errorMessage = "Invalid phone format.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>])[A-Za-z\d!@#$%^&*()\-_=+{};:,<.>]{8,15}$/', $password)) {
        $errorMessage = "Password weak.";
    } else {
        // Optional Check: If DOB is provided, validate age
        if (!empty($dob)) {
            $birthDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            if ($age < 18) {
                $errorMessage = "Donor must be 18+.";
            }
        }

        if (!isset($errorMessage)) {
            $checkEmailSql = "SELECT Donor_ID FROM donor WHERE Donor_Email = '$email'";
            $res = $conn->query($checkEmailSql);
            if ($res && $res->num_rows > 0) {
                $errorMessage = "Email exists.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $cols = "Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email, Donor_Password, 
                         Donor_Address1, Donor_Address2, Donor_Address3, Donor_City, Donor_State, Donor_PostalCode, Donor_Country, 
                         Donor_DOB, Donor_Description, Donor_RegisteredAt";
                // Added Donor_RegisteredAt with NOW()
                $vals = "'$donorName', '$contact', '$icNumber', '$email', '$hashedPassword', 
                         '$address1', '$address2', '$address3', '$city', '$state', '$postalCode', '$country', 
                         '$dob', '', NOW()";
                
                if ($profilePicture) {
                    $cols .= ", Donor_ProfilePicture";
                    $vals .= ", '$profilePicture'";
                }

                $sql = "INSERT INTO donor ($cols) VALUES ($vals)";
                
                if ($conn->query($sql)) {
                    $newDonorId = $conn->insert_id;
                    $conn->query("INSERT INTO point (Points_Earned, Points_Total, Points_Updated_At, Donor_ID) VALUES (0, 0, NOW(), $newDonorId)");
                    $successMessage = "Donor added successfully!";
                } else {
                    $errorMessage = "Error: " . $conn->error;
                }
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

    $picSql = "";
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $uploadedPath = handleProfileUpload($_FILES['profile_picture']);
        if ($uploadedPath) {
            $oldPicQ = $conn->query("SELECT Donor_ProfilePicture FROM donor WHERE Donor_ID = $donorId");
            if ($oldRow = $oldPicQ->fetch_assoc()) {
                if (!empty($oldRow['Donor_ProfilePicture']) && file_exists($oldRow['Donor_ProfilePicture'])) {
                    unlink($oldRow['Donor_ProfilePicture']);
                }
            }
            $picSql = ", Donor_ProfilePicture = '$uploadedPath'";
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { 
        $errorMessage = "Invalid email."; 
    } else {
        $sql = "UPDATE donor SET 
                Donor_Name = '$donorName', 
                Donor_ContactNumber = '$contact', 
                Donor_ICNumber = '$icNumber', Donor_Email = '$email',
                Donor_Address1 = '$address1', Donor_Address2 = '$address2', Donor_Address3 = '$address3',
                Donor_City = '$city', Donor_State = '$state', Donor_PostalCode = '$postalCode',
                Donor_Country = '$country', Donor_DOB = '$dob'
                $picSql
                WHERE Donor_ID = $donorId";
        
        if ($conn->query($sql)) { $successMessage = "Donor updated successfully!"; } 
        else { $errorMessage = "Error: " . $conn->error; }
    }
    
    if (!empty($successMessage)) { header("Location: admin_donor_page.php?success=" . urlencode($successMessage)); exit(); }
    elseif (!empty($errorMessage)) { header("Location: admin_donor_page.php?error=" . urlencode($errorMessage)); exit(); }
}

// --- PAGINATION SETTINGS ---
$results_per_page = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

// --- ADVANCED SEARCH & FILTER DATA FETCHING (UPDATED) ---
$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = [];

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
        $filterValue = $pointRange; // Keep to show selected in UI
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

$count_sql = "SELECT COUNT(*) as total FROM donor d $whereClause";
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

$select_fields = "d.*, 
                  COALESCE((SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID), 0) as CurrentPoints,
                  COALESCE((SELECT SUM(o.Order_Amount) FROM orders o WHERE o.Donor_ID = d.Donor_ID), 0) as TotalPayment";

$sql = "SELECT $select_fields FROM donor d 
        $whereClause
        ORDER BY d.Donor_RegisteredAt DESC, d.Donor_ID DESC
        LIMIT $start_from, $results_per_page";

$result = $conn->query($sql);
$donors = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $donors[] = $row;
    }
}

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// Handle Delete
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    $deleteSql = "DELETE FROM donor WHERE Donor_ID = $deleteId";
    if ($conn->query($deleteSql)) {
        header("Location: admin_donor_page.php?success=" . urlencode("Donor deleted successfully!"));
        exit();
    } else {
        $errorMessage = "Error deleting donor: " . $conn->error;
    }
}

// Stats Calculation
function getStats($conn) {
    $currentMonth = date('m');
    $currentYear = date('Y');
    $lastMonthDate = new DateTime('first day of last month');
    $lastMonth = $lastMonthDate->format('m');
    $lastMonthYear = $lastMonthDate->format('Y');

    $sqlTotal = "SELECT COUNT(*) as total FROM donor";
    $resTotal = $conn->query($sqlTotal);
    $totalDonors = ($resTotal) ? $resTotal->fetch_assoc()['total'] : 0;

    $checkCol = $conn->query("SHOW COLUMNS FROM `donor` LIKE 'Donor_RegisteredAt'");
    if($checkCol && $checkCol->num_rows > 0) {
        $sqlNewThisMonth = "SELECT COUNT(*) as total FROM donor WHERE MONTH(Donor_RegisteredAt) = '$currentMonth' AND YEAR(Donor_RegisteredAt) = '$currentYear'";
        $resNew = $conn->query($sqlNewThisMonth);
        $newDonorsThisMonth = ($resNew) ? $resNew->fetch_assoc()['total'] : 0;
        $totalLastMonthEnd = $totalDonors - $newDonorsThisMonth;
        
        $donorPercentChange = 0;
        if ($totalLastMonthEnd > 0) {
            $donorPercentChange = (($totalDonors - $totalLastMonthEnd) / $totalLastMonthEnd) * 100;
        } elseif ($totalDonors > 0) {
            $donorPercentChange = 100;
        }
    } else {
        $donorPercentChange = 0; 
    }
    
    $sqlDonationThis = "SELECT SUM(Order_Amount) as total FROM orders WHERE MONTH(Order_Created_At) = '$currentMonth' AND YEAR(Order_Created_At) = '$currentYear'";
    $resDonationThis = $conn->query($sqlDonationThis);
    $donationThisMonth = ($resDonationThis && $row = $resDonationThis->fetch_assoc()) ? (float)$row['total'] : 0;

    $sqlDonationLast = "SELECT SUM(Order_Amount) as total FROM orders WHERE MONTH(Order_Created_At) = '$lastMonth' AND YEAR(Order_Created_At) = '$lastMonthYear'";
    $resDonationLast = $conn->query($sqlDonationLast);
    $donationLastMonth = ($resDonationLast && $row = $resDonationLast->fetch_assoc()) ? (float)$row['total'] : 0;

    $donationPercentChange = 0;
    if ($donationLastMonth > 0) {
        $donationPercentChange = (($donationThisMonth - $donationLastMonth) / $donationLastMonth) * 100;
    } elseif ($donationThisMonth > 0) {
        $donationPercentChange = 100;
    }

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
    if (empty($donor['Donor_Address1'])) {
        return '-';
    }

    $addressParts = [];
    if (!empty($donor['Donor_Address1'])) $addressParts[] = htmlspecialchars($donor['Donor_Address1']) . ',';
    $line2Parts = [];
    if (!empty($donor['Donor_Address2'])) $line2Parts[] = htmlspecialchars($donor['Donor_Address2']);
    if (!empty($donor['Donor_Address3'])) $line2Parts[] = htmlspecialchars($donor['Donor_Address3']);
    if (!empty($line2Parts)) $addressParts[] = implode(', ', $line2Parts) . ',';
    
    $postal = !empty($donor['Donor_PostalCode']) ? htmlspecialchars($donor['Donor_PostalCode']) : '';
    $city = !empty($donor['Donor_City']) ? htmlspecialchars($donor['Donor_City']) : '';
    $state = !empty($donor['Donor_State']) ? htmlspecialchars($donor['Donor_State']) : '';
    
    if($postal || $city || $state) {
        $addressParts[] = $postal . ' ' . $city . ',' . $state;
    }
    
    return implode("<br>", $addressParts);
}

$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];
$adminPosition = "System Administrator";
$adminProfilePicture = null; 

$conn->close();

// Reference Arrays
$malaysiaStates = [
    'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 
    'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'
];
$years = range(date('Y'), 2020); // Dynamic years

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
        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; display: flex; align-items: center; gap: 5px; }
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .text-muted { color: var(--gray); }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        
        /* Layout & Table */
        .donor-management { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; font-weight: 600; }
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        /* Updated Search & Filter Layout */
        .donor-search { 
            margin-bottom: 20px; 
            display: flex; 
            gap: 10px; 
            align-items: center; 
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; background: white; }
        .search-input:focus { border-color: var(--primary); }
        
        /* Filter Select Style */
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-label { font-size: 13px; font-weight: 600; color: #555; display: none; /* Hidden on small screens or by default if preferred */ }
        @media (min-width: 992px) { .filter-label { display: block; } }

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

        .donor-table { width: 100%; border-collapse: collapse; }
        .donor-table th, .donor-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .donor-table th { font-weight: 600; color: var(--gray); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .donor-info { display: flex; align-items: center; }
        
        /* Avatar Styling */
        .donor-avatar { width: 40px; height: 40px; border-radius: 50%; margin-right: 15px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px; overflow: hidden; }
        .donor-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .donor-details h4 { font-size: 14px; margin-bottom: 4px; color: var(--dark); }
        .donor-details p { font-size: 12px; color: #888; margin: 0; }
        .address-display { font-size: 13px; color: #666; line-height: 1.5; margin: 0; padding: 0; display: block; }
        
        /* Action Menu */
        .action-cell { display: flex; justify-content: center; align-items: center; height: 100%; }
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { background-color: #f8f9fa; border: 1px solid #e9ecef; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; color: #6c757d; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; outline: none; }
        .menu-btn i { font-size: 14px; }
        .menu-btn:hover { background-color: #e2e6ea; color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.1); transform: translateY(-1px); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; animation: fadeIn 0.2s ease; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 10px; }
        .dropdown-content i { width: 16px; text-align: center; color: #888; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: var(--primary); }
        .dropdown-content div:hover i, .dropdown-content a:hover i { color: var(--primary); }
        .text-delete { color: var(--danger) !important; border-top: 1px solid #eee; }
        .text-delete:hover { background-color: #fff5f5 !important; }
        .text-delete i { color: var(--danger) !important; }
        
        /* Modal Styles */
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
        
        /* Upload Styles */
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
        .file-remove:hover { color: #a71d2a; }
        
        /* History Table */
        .history-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        .history-table th, .history-table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .history-table th { background: #f8f9fa; font-weight: 600; color: var(--dark); }
        .empty-state { text-align: center; padding: 30px; color: var(--gray); font-style: italic; }

        /* Utilities */
        .password-input-container { position: relative; display: flex; }
        .password-input-container input { flex: 1; border-radius: 5px 0 0 5px; border-right: none; }
        .password-input-container button.toggle-password { position: static; border: 1px solid var(--gray-light); border-left: none; border-radius: 0; background: white; padding: 0 10px; transform: none; }
        /* Auto Generate Button Style */
        .btn-small { padding: 0 12px; border-radius: 0 5px 5px 0; border: 1px solid var(--gray-light); border-left: none; background: #f8f9fa; cursor: pointer; font-size: 12px; font-weight: 500; color: var(--primary); transition: 0.2s; }
        .btn-small:hover { background: #e9ecef; }
        
        /* Updated Password Wrapper for layout */
        .password-input-group { display: flex; width: 100%; }
        .password-input-container { flex: 1; display: flex; }
        
        .confirm-check { position: absolute; right: 50px; top: 50%; transform: translateY(-50%); color: var(--success); font-size: 14px; display: none; z-index: 2; }
        .error-message { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }
        .form-guide { font-size: 11px; color: #6c757d; margin-top: 3px; display: block; font-style: italic; }
        .password-requirements { margin-top: 8px; font-size: 12px; }
        .requirement-item { display: flex; align-items: center; margin-bottom: 3px; color: #888; }
        .requirement-item.valid { color: var(--success); }
        .requirement-item.invalid { color: var(--gray); } 
        .requirement-item i { width: 15px; text-align: center; margin-right: 5px; }

        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); }
        .phone-input { border-radius: 0 5px 5px 0 !important; }
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; max-width: 400px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        
        /* Pagination Styles */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-info { font-size: 14px; color: #666; font-weight: 400; }
        .pagination-controls { display: flex; gap: 5px; align-items: center; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; display: inline-block; }
        .pagination-btn:hover:not(.disabled):not(.active) { background-color: #e2e6ea; border-color: #dae0e5; color: #212529; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; border-color: #eee; }

        /* Required Field Asterisk */
        .required { color: red; margin-left: 3px; font-weight: bold; }

        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: repeat(2, 1fr); }
            .form-row { flex-direction: column; gap: 0; }
            .pagination-container { flex-direction: column; gap: 15px; }
            .pagination-controls { flex-wrap: wrap; justify-content: center; }
            .donor-search { flex-direction: column; align-items: stretch; } 
            .filter-group { flex-wrap: wrap; }
            .filter-select { width: 100%; margin-bottom: 5px; }
        }
        @media (max-width: 576px) {
            .stats-cards { grid-template-columns: 1fr; }
            .donor-table { display: block; overflow-x: auto; }
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
                <li><a href="admin_donor_page.php" class="active"><i class="fas fa-users"></i> <span>Donor Management</span></a></li>
                <li><a href="staff_management_page.php"><i class="fas fa-user-tie"></i> <span>Staff Management</span></a></li>
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
                <div class="logo">
                    <a href="admin_dashboard.php">
                        <img src="logo.jpg" alt="Logo">
                        <h1>DonationMS</h1>
                    </a>
                </div>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
            </div>
            <div class="nav-right">
                <div class="user-profile" id="userProfileDropdown">
                    <div class="user-profile-with-avatar">
                        <div class="user-avatar"><?php echo substr($adminName, 0, 1); ?></div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($adminName); ?></div>
                            <div class="user-role"><?php echo htmlspecialchars($adminPosition); ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="margin-left: 10px; font-size: 12px;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Donor Management</h1>
                <p>Manage all donors, view details, and track donations.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL DONORS</h3>
                        <h2><?php echo number_format($stats['totalDonors']); ?></h2>
                        <p class="<?php echo $stats['donorTrend'] == 'up' ? 'text-success' : 'text-danger'; ?>">
                            <?php if($stats['donorTrend'] == 'up'): ?>
                                <i class="fas fa-arrow-up"></i> +<?php echo $stats['donorPercentChange']; ?>% from last month
                            <?php else: ?>
                                <i class="fas fa-arrow-down"></i> -<?php echo $stats['donorPercentChange']; ?>% from last month
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL DONATION (THIS MONTH)</h3>
                        <h2>RM <?php echo number_format($stats['donationThisMonth'], 2); ?></h2>
                        <p class="<?php echo $stats['donationTrend'] == 'up' ? 'text-success' : 'text-danger'; ?>">
                            <?php if($stats['donationTrend'] == 'up'): ?>
                                <i class="fas fa-arrow-up"></i> +<?php echo $stats['donationPercentChange']; ?>% from last month
                            <?php else: ?>
                                <i class="fas fa-arrow-down"></i> -<?php echo $stats['donationPercentChange']; ?>% from last month
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                </div>
            </div>

            <div class="donor-management">
                <div class="section-header">
                    <h2>Donor List</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="openAddDonorModal()">
                            <i class="fas fa-plus"></i> Add New Donor
                        </button>
                        <button class="btn btn-success">
                            <i class="fas fa-download"></i> Export Data
                        </button>
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

                    <div id="filter_state_container" class="secondary-filter">
                        <select name="filter_val_state" class="filter-select">
                            <option value="">Select State...</option>
                            <?php foreach($malaysiaStates as $ms): ?>
                                <option value="<?php echo $ms; ?>" <?php echo ($filterValue == $ms && $filterType == 'state') ? 'selected' : ''; ?>><?php echo $ms; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="filter_year_container" class="secondary-filter">
                        <select name="filter_val_year" class="filter-select">
                            <option value="">Select Year...</option>
                            <?php foreach($years as $yr): ?>
                                <option value="<?php echo $yr; ?>" <?php echo ($filterValue == $yr && $filterType == 'year') ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="filter_points_container" class="secondary-filter">
                        <select name="filter_val_points" class="filter-select">
                            <option value="">Select Tier...</option>
                            <option value="low" <?php echo ($filterValue == 'low') ? 'selected' : ''; ?>>Below 500 pts</option>
                            <option value="mid" <?php echo ($filterValue == 'mid') ? 'selected' : ''; ?>>500 - 1000 pts</option>
                            <option value="high" <?php echo ($filterValue == 'high') ? 'selected' : ''; ?>>VIP (> 1000 pts)</option>
                        </select>
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search donors by name, ID or email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    
                    <?php if(!empty($filterType) || !empty($searchTerm)): ?>
                        <a href="admin_donor_page.php" class="btn btn-danger" style="background-color: #dc3545; padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>

                <table class="donor-table">
                    <thead>
                        <tr>
                            <th>DONOR NAME</th>
                            <th>CONTACT INFO</th>
                            <th style="width: 30%;">ADDRESS</th>
                            <th>TOTAL PAYMENT</th>
                            <th>TOTAL POINTS</th>
                            <th style="text-align: center;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($donors) > 0): ?>
                            <?php foreach($donors as $donor): ?>
                            <tr>
                                <td>
                                    <div class="donor-info">
                                        <div class="donor-avatar">
                                            <?php if (!empty($donor['Donor_ProfilePicture'])): ?>
                                                <img src="<?php echo htmlspecialchars($donor['Donor_ProfilePicture']); ?>" alt="Profile">
                                            <?php else: ?>
                                                <?php echo substr($donor['Donor_Name'], 0, 1); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="donor-details">
                                            <h4><?php echo htmlspecialchars($donor['Donor_Name']); ?></h4>
                                            <p>ID: <?php echo htmlspecialchars($donor['Donor_ID']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="donor-details">
                                        <p><?php echo htmlspecialchars($donor['Donor_Email']); ?></p>
                                        <p><?php echo htmlspecialchars($donor['Donor_ContactNumber']); ?></p>
                                    </div>
                                </td>
                                <td>
                                    <div class="address-display"><?php echo formatAddress($donor); ?></div>
                                </td>
                                <td>
                                    RM <?php echo number_format($donor['TotalPayment'], 2); ?>
                                </td>
                                <td>
                                    <?php echo number_format($donor['CurrentPoints']); ?> pts
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <div class="action-menu">
                                            <button class="menu-btn" onclick="toggleMenu(event, <?php echo $donor['Donor_ID']; ?>)">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div id="menu-<?php echo $donor['Donor_ID']; ?>" class="dropdown-content">
                                                <div onclick="openViewDonorModal(<?php echo htmlspecialchars(json_encode($donor)); ?>)">
                                                    <i class="fas fa-eye"></i> View Details
                                                </div>
                                                <div onclick='openEditDonorModal(<?php echo json_encode($donor); ?>)'>
                                                    <i class="fas fa-edit"></i> Edit Details
                                                </div>
                                                <div onclick="openPaymentHistory(<?php echo $donor['Donor_ID']; ?>)">
                                                    <i class="fas fa-history"></i> Payment History
                                                </div>
                                                <div onclick="openRedemptionHistory(<?php echo $donor['Donor_ID']; ?>)">
                                                    <i class="fas fa-gift"></i> Redemption History
                                                </div>
                                                <a href="javascript:confirmDelete(<?php echo $donor['Donor_ID']; ?>)" class="text-delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; padding: 20px;">No donors found matching criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results
                    </div>
                    
                    <div class="pagination-controls">
                        <?php 
                        // Build query parameters for pagination
                        $queryParams = [];
                        if(!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if(!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if($filterType == 'state' && !empty($filterValue)) $queryParams['filter_val_state'] = $filterValue;
                            if($filterType == 'year' && !empty($filterValue)) $queryParams['filter_val_year'] = $filterValue;
                            if($filterType == 'points' && !empty($filterValue)) $queryParams['filter_val_points'] = $filterValue;
                        }
                        
                        $queryString = '';
                        if(!empty($queryParams)) {
                            $queryString = '&' . http_build_query($queryParams);
                        }

                        if ($page > 1): 
                        ?>
                            <a href="?page=<?php echo $page - 1 . $queryString; ?>" class="pagination-btn">Previous</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Previous</span>
                        <?php endif; ?>

                        <?php
                        if ($page == 1) {
                            echo '<span class="pagination-btn active">1</span>';
                        } else {
                            echo '<a href="?page=1' . $queryString . '" class="pagination-btn">1</a>';
                        }
                        ?>

                        <?php
                        $start_mid = $page - 1;
                        $end_mid = $page + 1;
                        if ($start_mid < 2) $start_mid = 2;
                        if ($end_mid > $total_pages - 1) $end_mid = $total_pages - 1;

                        if ($start_mid > 2) {
                            echo '<span class="pagination-btn disabled">...</span>';
                        }

                        for ($i = $start_mid; $i <= $end_mid; $i++) {
                            if ($i == $page) {
                                echo '<span class="pagination-btn active">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . $queryString . '" class="pagination-btn">' . $i . '</a>';
                            }
                        }

                        if ($end_mid < $total_pages - 1) {
                            echo '<span class="pagination-btn disabled">...</span>';
                        }
                        ?>

                        <?php
                        if ($total_pages > 1) {
                            if ($page == $total_pages) {
                                echo '<span class="pagination-btn active">' . $total_pages . '</span>';
                            } else {
                                echo '<a href="?page=' . $total_pages . $queryString . '" class="pagination-btn">' . $total_pages . '</a>';
                            }
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1 . $queryString; ?>" class="pagination-btn">Next</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="addDonorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Donor</h2>
                <button class="close-btn" onclick="closeAddDonorModal()">&times;</button>
            </div>
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
                                <i class="fas fa-upload"></i> Choose File
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
                        <span class="form-guide">Only English letters and spaces are allowed.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-input" required onblur="validateEmail()" placeholder="e.g. user@example.com">
                            <span class="form-guide">Example: user@example.com. Must contain '@' and end with valid domain.</span>
                            <div id="emailError" class="error-message">Invalid email format</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format">
                                <span class="phone-prefix">+601</span>
                                <input type="text" id="contact" name="contact" class="form-input phone-input" required placeholder="X-XXXXXXXX" maxlength="11">
                            </div>
                            <span class="form-guide">(e.g., +6012-3456789 or +6011-12345678)</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">IC Number</label><input type="text" id="ic_number" name="ic_number" class="form-input" placeholder="XXXXXX-XX-XXXX" maxlength="14"><span class="form-guide">(e.g., 900101-01-1234)</span></div>
                        <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" id="dob" name="dob" class="form-input" onchange="validateAge()"><div id="ageError" class="error-message">Must be 18+</div></div>
                    </div>
                    <div class="form-group"><label class="form-label">Address Line 1</label><input type="text" name="address1" class="form-input" placeholder="e.g. No. 123, Jalan Example"><span class="form-guide">House number, street name.</span></div>
                    <div class="form-group"><label class="form-label">Address Line 2</label><input type="text" name="address2" class="form-input" placeholder="e.g. Taman Sri"></div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" class="form-input" placeholder="Address Line 3 (Optional)">
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">City</label><input type="text" name="city" class="form-input" placeholder="e.g. Kuala Lumpur"></div>
                        <div class="form-group"><label class="form-label">State</label><select name="state" class="form-select"><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Postal Code</label><input type="text" name="postal_code" class="form-input" placeholder="e.g. 50000"></div>
                        <div class="form-group"><label class="form-label">Country</label><input type="text" name="country" class="form-input" value="Malaysia" readonly></div>
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
                        <span class="form-guide">Please re-enter the password to confirm.</span>
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
                    <input type="hidden" name="update_donor" value="1">
                    <input type="hidden" id="edit_donor_id" name="donor_id">
                    
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <div class="profile-picture-preview" id="edit-preview-container">
                            <div class="default-avatar-icon"><i class="fas fa-user"></i></div>
                        </div>
                        <div class="file-upload">
                            <label for="edit_profile_picture" class="file-upload-label">
                                <i class="fas fa-upload"></i> Change Picture
                            </label>
                            <input type="file" id="edit_profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this, 'edit-preview-container', 'edit-file-info', 'edit-file-name')">
                            <div id="edit-file-info" class="file-info">
                                <span id="edit-file-name" class="file-name"></span>
                                <button type="button" class="file-remove" id="edit-file-remove-btn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" id="edit_donor_name" name="donor_name" class="form-input" required oninput="validateName(this)">
                        <span class="form-guide">Only English letters and spaces are allowed.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" id="edit_email" name="email" class="form-input" required onblur="validateEditEmail()">
                            <span class="form-guide">Example: user@example.com</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <div class="phone-format"><span class="phone-prefix">+601</span><input type="text" id="edit_contact" name="contact" class="form-input phone-input" required maxlength="11"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">IC Number</label><input type="text" id="edit_ic_number" name="ic_number" class="form-input" maxlength="14"></div>
                        <div class="form-group"><label class="form-label">DOB</label><input type="date" id="edit_dob" name="dob" class="form-input" onchange="validateEditAge()"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Address Line 1</label><input type="text" id="edit_address1" name="address1" class="form-input" placeholder="e.g. No. 123, Jalan Example"></div>
                    <div class="form-group"><label class="form-label">Address Line 2</label><input type="text" id="edit_address2" name="address2" class="form-input"></div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" id="edit_address3" name="address3" class="form-input" placeholder="Address Line 3 (Optional)">
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">City</label><input type="text" id="edit_city" name="city" class="form-input" placeholder="e.g. Kuala Lumpur"></div>
                        <div class="form-group"><label class="form-label">State</label><select id="edit_state" name="state" class="form-select"><option value="">Select State</option><?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?></select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Postal Code</label><input type="text" id="edit_postal_code" name="postal_code" class="form-input" placeholder="e.g. 50000"></div>
                        <div class="form-group"><label class="form-label">Country</label><input type="text" id="edit_country" name="country" class="form-input" value="Malaysia" readonly></div>
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
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" id="view_donor_name" class="form-input" readonly>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Email</label><input type="text" id="view_email" class="form-input" readonly></div>
                    <div class="form-group"><label class="form-label">Contact</label><input type="text" id="view_contact" class="form-input" readonly></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">IC Number</label><input type="text" id="view_ic" class="form-input" readonly></div>
                    <div class="form-group"><label class="form-label">DOB</label><input type="text" id="view_dob" class="form-input" readonly></div>
                </div>
                <div class="form-group"><label class="form-label">Address</label><textarea id="view_address" class="form-input" readonly rows="3"></textarea></div>
            </div>
        </div>
    </div>

    <div class="modal" id="paymentHistoryModal"><div class="modal-content"><div class="modal-header"><h2>Payment History</h2><button class="close-btn" onclick="closeModal('paymentHistoryModal')">&times;</button></div><div class="modal-body"><table class="history-table"><thead><tr><th>Date</th><th>Ref</th><th>Amount</th><th>Status</th></tr></thead><tbody id="paymentHistoryBody"></tbody></table></div></div></div>
    <div class="modal" id="redemptionHistoryModal"><div class="modal-content"><div class="modal-header"><h2>Redemption History</h2><button class="close-btn" onclick="closeModal('redemptionHistoryModal')">&times;</button></div><div class="modal-body"><table class="history-table"><thead><tr><th>Date</th><th>Item</th><th>Points Used</th><th>Status</th></tr></thead><tbody id="redemptionHistoryBody"></tbody></table></div></div></div>

    <script>
        // --- NEW DYNAMIC FILTER SCRIPT ---
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            
            // Hide all secondary filters first
            document.querySelectorAll('.secondary-filter').forEach(el => {
                el.classList.remove('active');
                // Disable inputs to prevent them from cluttering the URL
                el.querySelector('select').disabled = true;
            });

            // Show specific one based on selection
            if (type === 'state') {
                const el = document.getElementById('filter_state_container');
                el.classList.add('active');
                el.querySelector('select').disabled = false;
            } else if (type === 'year') {
                const el = document.getElementById('filter_year_container');
                el.classList.add('active');
                el.querySelector('select').disabled = false;
            } else if (type === 'points') {
                const el = document.getElementById('filter_points_container');
                el.classList.add('active');
                el.querySelector('select').disabled = false;
            }
        }

        // Initialize on load to preserve state after search
        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            // ... (keep existing listeners)
            setupPhoneInput('contact'); setupPhoneInput('edit_contact');
            setupICInput('ic_number'); setupICInput('edit_ic_number');
            const s = document.getElementById('floatingSuccess');
            const e = document.getElementById('floatingError');
            if(s) setTimeout(() => s.style.display='none', 5000);
            if(e) setTimeout(() => e.style.display='none', 5000);
            
            window.onclick = function(event) {
                if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) {
                    document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
                }
                if (event.target.classList.contains('modal')) event.target.style.display = "none";
            }
        });

        // --- EXISTING JAVASCRIPT LOGIC BELOW ---

        // --- Generate Strong Password Function ---
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
            if(toggleBtn) toggleBtn.querySelector('i').className = 'fas fa-eye';
            
            if(confirmId) {
                 const confirmInput = document.getElementById(confirmId);
                 confirmInput.type = "text";
                 const confirmToggle = confirmInput.parentNode.querySelector('.toggle-password');
                 if(confirmToggle) {
                     confirmToggle.querySelector('i').className = 'fas fa-eye';
                 }
            }
            
            if(passId === 'password') validatePasswordRequirements();
            if(confirmId) validatePasswordMatch();
        }

        // --- Image Preview & Remove Logic ---
        function previewImage(input, containerId, infoId, nameId) {
            const container = document.getElementById(containerId);
            const info = document.getElementById(infoId);
            const nameSpan = document.getElementById(nameId);
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    container.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    if(info) {
                        info.style.display = 'inline-flex';
                        nameSpan.textContent = input.files[0].name;
                    }
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

        // --- Input Formatting & Validation ---
        function setupPhoneInput(inputId) {
            const input = document.getElementById(inputId);
            if(!input) return;
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.selectionStart === 2 && this.value.charAt(1) === '-') {
                    e.preventDefault(); 
                    let raw = this.value.replace(/\D/g, '');
                    if (raw.length > 0) {
                        let newRaw = raw.substring(1); 
                        this.value = newRaw.length > 0 ? (newRaw.length > 1 ? newRaw.substring(0, 1) + '-' + newRaw.substring(1) : newRaw.substring(0, 1) + '-') : '';
                        this.setSelectionRange(0, 0);
                    }
                }
            });
            input.addEventListener('input', function(e) {
                let val = this.value.replace(/\D/g, ''); 
                if (val.length > 0) this.value = val.substring(0, 1) + (val.length >= 1 ? '-' : '') + (val.length > 1 ? val.substring(1, 10) : '');
            });
        }
        function setupICInput(inputId) {
            const input = document.getElementById(inputId);
            if(!input) return;
            input.addEventListener('keydown', function(e) { /* Simplified logic */ }); 
            input.addEventListener('input', function(e) {
                let val = this.value.replace(/\D/g, ''); 
                let newVal = '';
                if (val.length > 0) newVal += val.substring(0, 6);
                if (val.length >= 6) newVal += '-';
                if (val.length > 6) newVal += val.substring(6, 8);
                if (val.length >= 8) newVal += '-';
                if (val.length > 8) newVal += val.substring(8, 12);
                this.value = newVal;
            });
        }

        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); document.getElementById('menu-' + id).style.display = 'block'; }
        
        function openViewDonorModal(donor) {
            document.getElementById('view_donor_name').value = donor.Donor_Name;
            document.getElementById('view_email').value = donor.Donor_Email;
            document.getElementById('view_contact').value = donor.Donor_ContactNumber;
            document.getElementById('view_ic').value = donor.Donor_ICNumber;
            document.getElementById('view_dob').value = donor.Donor_DOB;
            let address = donor.Donor_Address1;
            if(donor.Donor_Address2) address += ", " + donor.Donor_Address2;
            if(donor.Donor_Address3) address += ", " + donor.Donor_Address3;
            address += "\n" + donor.Donor_PostalCode + " " + donor.Donor_City + ", " + donor.Donor_State;
            document.getElementById('view_address').value = address;
            document.getElementById('viewDonorModal').style.display = 'flex';
        }

        function openPaymentHistory(donorId) {
            const tbody = document.getElementById('paymentHistoryBody');
            tbody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
            document.getElementById('paymentHistoryModal').style.display = 'flex';
            fetch(`admin_donor_page.php?action=get_donor_history&donor_id=${donorId}&type=payment`).then(res => res.json()).then(data => {
                tbody.innerHTML = '';
                if(data.length === 0) { tbody.innerHTML = '<tr><td colspan="4" class="empty-state">No payment history found.</td></tr>'; } 
                else { data.forEach(row => { tbody.innerHTML += `<tr><td>${row.Order_Created_At}</td><td>${row.Order_TXN_Ref}</td><td>RM ${row.Order_Amount}</td><td>${row.Order_Status}</td></tr>`; }); }
            });
        }

        function openRedemptionHistory(donorId) {
            const tbody = document.getElementById('redemptionHistoryBody');
            tbody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
            document.getElementById('redemptionHistoryModal').style.display = 'flex';
            fetch(`admin_donor_page.php?action=get_donor_history&donor_id=${donorId}&type=redemption`).then(res => res.json()).then(data => {
                tbody.innerHTML = '';
                if(data.length === 0) { tbody.innerHTML = '<tr><td colspan="4" class="empty-state">No redemption history found.</td></tr>'; } 
                else { data.forEach(row => { tbody.innerHTML += `<tr><td>${row.Redemption_Updated_At}</td><td>${row.Reward_ItemName}</td><td>${row.Redemption_PointsSpent} pts</td><td>${row.Redemption_Status}</td></tr>`; }); }
            });
        }

        function openAddDonorModal() { document.getElementById('addDonorModal').style.display = 'flex'; }
        function closeAddDonorModal() { 
            document.getElementById('addDonorModal').style.display = 'none'; 
            document.getElementById('addDonorForm').reset(); 
            document.getElementById('add-preview-container').innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            document.getElementById('add-file-info').style.display = 'none';
            
            document.querySelectorAll('.requirement-item').forEach(el => {
                 el.className = 'requirement-item invalid';
                 el.querySelector('i').className = 'fas fa-times';
            });
        }
        
        function openEditDonorModal(donor) {
            document.getElementById('edit_donor_id').value = donor.Donor_ID;
            document.getElementById('edit_donor_name').value = donor.Donor_Name;
            document.getElementById('edit_email').value = donor.Donor_Email;
            
            let phone = donor.Donor_ContactNumber.replace('+601', '');
            let phoneInput = document.getElementById('edit_contact');
            phoneInput.value = phone;
            phoneInput.dispatchEvent(new Event('input')); 
            
            let icInput = document.getElementById('edit_ic_number');
            icInput.value = donor.Donor_ICNumber;
            icInput.dispatchEvent(new Event('input')); 

            document.getElementById('edit_dob').value = donor.Donor_DOB;
            document.getElementById('edit_address1').value = donor.Donor_Address1;
            document.getElementById('edit_address2').value = donor.Donor_Address2;
            document.getElementById('edit_address3').value = donor.Donor_Address3;
            document.getElementById('edit_city').value = donor.Donor_City;
            document.getElementById('edit_state').value = donor.Donor_State;
            document.getElementById('edit_postal_code').value = donor.Donor_PostalCode;
            document.getElementById('edit_country').value = donor.Donor_Country;
            
            const previewContainer = document.getElementById('edit-preview-container');
            let originalSrc = null;
            if (donor.Donor_ProfilePicture) {
                originalSrc = donor.Donor_ProfilePicture;
                previewContainer.innerHTML = `<img src="${donor.Donor_ProfilePicture}" alt="Preview">`;
            } else {
                previewContainer.innerHTML = '<div class="default-avatar-icon"><i class="fas fa-user"></i></div>';
            }
            
            document.getElementById('edit_profile_picture').value = '';
            document.getElementById('edit-file-info').style.display = 'none';

            document.getElementById('edit-file-remove-btn').onclick = function() {
                removeImage('edit_profile_picture', 'edit-preview-container', 'edit-file-info', originalSrc);
            };

            document.getElementById('editDonorModal').style.display = 'flex';
        }
        function closeEditDonorModal() { document.getElementById('editDonorModal').style.display = 'none'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function confirmDelete(id) { if(confirm("Are you sure?")) window.location.href = "admin_donor_page.php?delete_id=" + id; }
        
        function validateName(input) { input.value = input.value.replace(/[^a-zA-Z\s]/g, ''); }
        function validateEmail() { const v = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|my)$/i.test(document.getElementById('email').value); document.getElementById('emailError').style.display = v ? 'none' : 'block'; return v; }
        function validateEditEmail() { const v = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|my)$/i.test(document.getElementById('edit_email').value); return v; }
        function validateAge() { 
            const d = document.getElementById('dob').value; 
            if(!d) return true; 
            return (new Date().getFullYear() - new Date(d).getFullYear()) >= 18; 
        }
        function validateEditAge() { 
            const d = document.getElementById('edit_dob').value; 
            if(!d) return true; 
            return (new Date().getFullYear() - new Date(d).getFullYear()) >= 18; 
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
                if (valid) { 
                    el.className = 'requirement-item valid'; 
                    icon.className = 'fas fa-check'; 
                } else { 
                    el.className = 'requirement-item invalid'; 
                    icon.className = 'fas fa-times'; 
                    allValid = false; 
                }
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
        
        function togglePasswordVisibility(id) {
            const f = document.getElementById(id);
            const icon = f.nextElementSibling.tagName === 'BUTTON' ? 
                         f.nextElementSibling.querySelector('i') : 
                         f.parentNode.querySelector('button i'); 
            
            if(f.type === 'password') { 
                f.type = 'text'; 
                if(icon) icon.className = 'fas fa-eye-slash'; 
            } else { 
                f.type = 'password'; 
                if(icon) icon.className = 'fas fa-eye'; 
            }
        }
        
        function validateForm() { 
            const validEmail = validateEmail();
            const validPass = validatePasswordRequirements();
            const validMatch = validatePasswordMatch();
            
            return validEmail && validPass && validMatch; 
        }
        
        function validateEditForm() { return validateEditEmail() && validateEditAge(); }
    </script>
</body>
</html>
