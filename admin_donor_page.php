<?php
// admin_donor_page.php
session_start();

// --- Check Login ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// --- Get Current User Info ---
$adminName = "User";
$adminPosition = "Role";
$adminProfilePicture = null;
$currentAdminId = 0; 

if (isset($_SESSION['admin_id'])) {
    $currentAdminId = $_SESSION['admin_id'];
    $sql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $adminName = $row['Admin_Name'];
        $adminPosition = $row['Admin_Role']; 
        $adminProfilePicture = $row['Admin_ProfilePicture']; 
    }
} elseif (isset($_SESSION['staff_id'])) {
    $currentStaffId = $_SESSION['staff_id'];
    $currentAdminId = $currentStaffId; 
    
    $sql = "SELECT Staff_FullName, Staff_ProfilePicture, Staff_Role FROM staff WHERE Staff_ID = $currentStaffId";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $adminName = $row['Staff_FullName'];
        $adminPosition = $row['Staff_Role']; 
        $adminProfilePicture = $row['Staff_ProfilePicture'];
    }
}

// --- SEARCH & FILTER PREPARATION ---
$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = [];
$havingConditions = []; 

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Only show active (not deleted)
$whereConditions[] = "d.Is_Deleted = 0";

// 1. Check Search (Keyword)
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereConditions[] = "(d.Donor_Name LIKE '%$searchTerm%' 
                            OR d.Donor_Email LIKE '%$searchTerm%' 
                            OR d.Donor_ID LIKE '%$searchTerm%')";
}

// 2. Check Dynamic Filters (Sidebar/Dropdown)
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    
    if ($filterType == 'name_sort' && isset($_GET['filter_val_name']) && !empty($_GET['filter_val_name'])) {
        $filterValue = $_GET['filter_val_name'];
    }
    elseif ($filterType == 'phone' && isset($_GET['filter_val_phone']) && !empty($_GET['filter_val_phone'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_phone']);
        $whereConditions[] = "d.Donor_ContactNumber LIKE '%$filterValue%'";
    }
    elseif ($filterType == 'state' && isset($_GET['filter_val_state']) && !empty($_GET['filter_val_state'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_state']);
        $whereConditions[] = "d.Donor_State = '$filterValue'";
    } 
    elseif ($filterType == 'year' && isset($_GET['filter_val_year']) && !empty($_GET['filter_val_year'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_year']);
        $whereConditions[] = "YEAR(d.Donor_RegisteredAt) = '$filterValue'";
    }
    elseif ($filterType == 'points' && isset($_GET['filter_val_points']) && !empty($_GET['filter_val_points'])) {
        $pointRange = $_GET['filter_val_points'];
        $filterValue = $pointRange; 
        if ($pointRange == 'low') $whereConditions[] = "(SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID ORDER BY p.Points_Updated_At DESC LIMIT 1) < 50";
        elseif ($pointRange == 'mid') $whereConditions[] = "(SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID ORDER BY p.Points_Updated_At DESC LIMIT 1) BETWEEN 50 AND 200";
        elseif ($pointRange == 'high') $whereConditions[] = "(SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID ORDER BY p.Points_Updated_At DESC LIMIT 1) > 200";
    }
    elseif ($filterType == 'amount' && isset($_GET['filter_val_amount']) && !empty($_GET['filter_val_amount'])) {
        $amountRange = $_GET['filter_val_amount'];
        $filterValue = $amountRange;
        if ($amountRange == 'low') $havingConditions[] = "TotalPayment < 200";
        elseif ($amountRange == 'mid') $havingConditions[] = "TotalPayment BETWEEN 200 AND 500";
        elseif ($amountRange == 'high') $havingConditions[] = "TotalPayment > 500";
    }
}

// 3. HEADER SPECIFIC FILTERS (The requested changes)

// Phone Prefix Filter from Header
if (isset($_GET['header_filter_phone']) && !empty($_GET['header_filter_phone'])) {
    $phonePrefix = $conn->real_escape_string($_GET['header_filter_phone']);
    // Support searching for +601x or just 01x
    $whereConditions[] = "(d.Donor_ContactNumber LIKE '%$phonePrefix%' OR d.Donor_ContactNumber LIKE '+6$phonePrefix%')";
}

// Total Payment Range Filter
if (isset($_GET['header_filter_payment_min']) && $_GET['header_filter_payment_min'] !== '') {
    $payMin = (float)$_GET['header_filter_payment_min'];
    $havingConditions[] = "TotalPayment >= $payMin";
}
if (isset($_GET['header_filter_payment_max']) && $_GET['header_filter_payment_max'] !== '') {
    $payMax = (float)$_GET['header_filter_payment_max'];
    $havingConditions[] = "TotalPayment <= $payMax";
}

// Total Points Range Filter
if (isset($_GET['header_filter_points_min']) && $_GET['header_filter_points_min'] !== '') {
    $ptsMin = (int)$_GET['header_filter_points_min'];
    $havingConditions[] = "CurrentPoints >= $ptsMin";
}
if (isset($_GET['header_filter_points_max']) && $_GET['header_filter_points_max'] !== '') {
    $ptsMax = (int)$_GET['header_filter_points_max'];
    $havingConditions[] = "CurrentPoints <= $ptsMax";
}


$sortMap = [
    'date' => 'd.Donor_RegisteredAt',
    'name' => 'd.Donor_Name',
    'contact' => 'd.Donor_ContactNumber',
    'address' => 'd.Donor_State',
    'payment' => 'TotalPayment',
    'points' => 'CurrentPoints'
];
$orderByCol = isset($sortMap[$sort]) ? $sortMap[$sort] : 'd.Donor_RegisteredAt';
$orderDir = ($order === 'asc') ? 'ASC' : 'DESC';
$orderClause = "ORDER BY $orderByCol $orderDir";

$whereClause = "";
if (count($whereConditions) > 0) $whereClause = "WHERE " . implode(" AND ", $whereConditions);

$havingClause = "";
if (count($havingConditions) > 0) $havingClause = "HAVING " . implode(" AND ", $havingConditions);

// --- EXPORT TO EXCEL ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "donor_complete_data_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $exportSql = "SELECT d.Donor_ID, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_ICNumber, 
                  d.Donor_State, d.Donor_City, d.Donor_RegisteredAt, d.Donor_LastLogin,
                  COALESCE((SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID ORDER BY p.Points_Updated_At DESC LIMIT 1), 0) as CurrentPoints,
                  COALESCE((SELECT SUM(o.Order_Amount) FROM orders o WHERE o.Donor_ID = d.Donor_ID), 0) as TotalPayment
                  FROM donor d $whereClause $havingClause $orderClause";

    $donorRes = $conn->query($exportSql);

    echo '<h3>DONOR PROFILES & STATUS</h3>';
    echo '<table border="1">';
    echo '<tr style="background-color:#eee;"><th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>IC Number</th><th>City</th><th>State</th><th>Points</th><th>Donated (RM)</th><th>Registered Date</th><th>Last Login</th></tr>';
    
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
    exit();
}

// --- PAGINATION ---
$results_per_page = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

// Count Total Records (Updated to handle HAVING clause for Points and Payment)
if (!empty($havingClause)) {
    // We must select the calculated columns in the subquery for HAVING to work
    $count_sql = "SELECT COUNT(*) as total FROM (
                    SELECT d.Donor_ID, 
                    COALESCE((SELECT SUM(o.Order_Amount) FROM orders o WHERE o.Donor_ID = d.Donor_ID), 0) as TotalPayment,
                    COALESCE((SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID ORDER BY p.Points_Updated_At DESC LIMIT 1), 0) as CurrentPoints
                    FROM donor d $whereClause $havingClause
                  ) as sub";
} else {
    $count_sql = "SELECT COUNT(*) as total FROM donor d $whereClause";
}

$count_result = $conn->query($count_sql);
$total_records = ($count_result) ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $results_per_page);
if ($page > $total_pages && $total_pages > 0) { $page = $total_pages; $start_from = ($page - 1) * $results_per_page; }

$select_fields = "d.*, 
                  COALESCE((SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID ORDER BY p.Points_Updated_At DESC LIMIT 1), 0) as CurrentPoints,
                  COALESCE((SELECT SUM(o.Order_Amount) FROM orders o WHERE o.Donor_ID = d.Donor_ID), 0) as TotalPayment";

$sql = "SELECT $select_fields FROM donor d $whereClause $havingClause $orderClause LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$donors = [];
if ($result && $result->num_rows > 0) { while($row = $result->fetch_assoc()) $donors[] = $row; }

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// Stats Calculation
function getStats($conn) {
    $currentMonth = date('m'); $currentYear = date('Y');
    $lastMonthDate = new DateTime('first day of last month');
    $lastMonth = $lastMonthDate->format('m'); $lastMonthYear = $lastMonthDate->format('Y');

    $resTotal = $conn->query("SELECT COUNT(*) as total FROM donor WHERE Is_Deleted = 0");
    $totalDonors = ($resTotal) ? $resTotal->fetch_assoc()['total'] : 0;

    $checkCol = $conn->query("SHOW COLUMNS FROM `donor` LIKE 'Donor_RegisteredAt'");
    if($checkCol && $checkCol->num_rows > 0) {
        $resNew = $conn->query("SELECT COUNT(*) as total FROM donor WHERE Is_Deleted = 0 AND MONTH(Donor_RegisteredAt) = '$currentMonth' AND YEAR(Donor_RegisteredAt) = '$currentYear'");
        $newDonorsThisMonth = ($resNew) ? $resNew->fetch_assoc()['total'] : 0;
        $totalLastMonthEnd = $totalDonors - $newDonorsThisMonth;
        $donorPercentChange = ($totalLastMonthEnd > 0) ? (($totalDonors - $totalLastMonthEnd) / $totalLastMonthEnd) * 100 : ($totalDonors > 0 ? 100 : 0);
    } else $donorPercentChange = 0; 
    
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

// 辅助函数：构建URL
function buildUrl($params = []) {
    $current = $_GET;
    // Reset page to 1 when changing filters
    unset($current['page']);
    $merged = array_merge($current, $params);
    return '?' . http_build_query($merged);
}

// Helper to generate hidden inputs for existing params (to use inside forms)
function getHiddenInputs($exclude = []) {
    $html = '';
    $params = $_GET;
    // Remove params that will be replaced by the form
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    // Also reset page
    unset($params['page']);
    
    foreach ($params as $key => $value) {
        $html .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'">';
    }
    return $html;
}

$malaysiaStates = [ 'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu' ];
$years = range(date('Y'), 2020); 
$phonePrefixes = ['010', '011', '012', '013', '014', '015', '016', '017', '018', '019'];

$exportParams = $_GET; $exportParams['action'] = 'export_excel'; unset($exportParams['page']);
$exportUrl = "?" . http_build_query($exportParams);

// Default placeholder for lightbox
$defaultAvatarPlaceholder = "https://via.placeholder.com/500x500.png?text=No+Profile+Picture";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Management - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* (Existing CSS - Strictly Kept) */
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
        .donor-table th { font-weight: 600; color: var(--gray); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer; user-select: none; }
        .donor-table th:hover { background-color: #f8f9fa; color: var(--primary); }
        .donor-table th i { margin-left: 5px; opacity: 0.5; }

        .donor-info { display: flex; align-items: center; }
        .donor-avatar { width: 40px; height: 40px; border-radius: 50%; margin-right: 15px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px; overflow: hidden; cursor: pointer; }
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
        
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; display: none; }
        .floating-alert div { line-height: 1.6; }
        .floating-alert i { margin-top: 4px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-controls { display: flex; gap: 5px; align-items: center; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; }
        
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 2002; }
        .close-lightbox:hover, .close-lightbox:focus { color: #bbb; text-decoration: none; cursor: pointer; }
        
        @media (max-width: 768px) { .stats-cards { grid-template-columns: repeat(2, 1fr); } .pagination-container { flex-direction: column; gap: 15px; } .donor-search { flex-direction: column; align-items: stretch; } .filter-group { flex-wrap: wrap; } }

        /* Sort Modal Styles */
        .sort-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .sort-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; }
        .sort-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .sort-header h3 { margin: 0; font-size: 16px; color: #333; }
        .sort-close { cursor: pointer; font-size: 20px; color: #999; }
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #555; text-decoration: none; }
        .sort-btn:hover { background: #f8f9fa; border-color: #ddd; color: var(--primary); }
        .sort-btn i { width: 20px; text-align: center; margin-right: 8px; }

        /* New Styles for Range Inputs & Grid */
        .filter-section-title { font-size: 14px; font-weight: 600; color: #555; margin: 15px 0 10px 0; border-top: 1px solid #eee; padding-top: 15px; }
        .prefix-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .prefix-btn { padding: 8px 0; text-align: center; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; color: #333; text-decoration: none; transition: 0.2s; }
        .prefix-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .range-form { display: flex; flex-direction: column; gap: 10px; }
        .range-inputs { display: flex; gap: 10px; }
        .range-input { width: 50%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        .range-submit { width: 100%; padding: 8px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; transition: 0.2s; }
        .range-submit:hover { background: #e06c6c; }
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
                        <a href="admin_donor_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Donor</a>
                        <a href="<?php echo $exportUrl; ?>" class="btn btn-success" target="_blank"><i class="fas fa-download"></i> Export Data</a>
                    </div>
                </div>

                <form method="GET" action="admin_donor_page.php" class="donor-search">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="name_sort" <?php echo ($filterType == 'name_sort') ? 'selected' : ''; ?>>Name Sorting</option>
                            <option value="phone" <?php echo ($filterType == 'phone') ? 'selected' : ''; ?>>Phone Prefix</option>
                            <option value="state" <?php echo ($filterType == 'state') ? 'selected' : ''; ?>>State</option>
                            <option value="year" <?php echo ($filterType == 'year') ? 'selected' : ''; ?>>Registration Year</option>
                            <option value="points" <?php echo ($filterType == 'points') ? 'selected' : ''; ?>>Points Tier</option>
                            <option value="amount" <?php echo ($filterType == 'amount') ? 'selected' : ''; ?>>Total Donation</option>
                        </select>
                    </div>

                    <div id="filter_name_container" class="secondary-filter"><select name="filter_val_name" class="filter-select"><option value="">Select Order...</option><option value="asc" <?php echo ($filterValue == 'asc') ? 'selected' : ''; ?>>Name (A-Z)</option><option value="desc" <?php echo ($filterValue == 'desc') ? 'selected' : ''; ?>>Name (Z-A)</option></select></div>
                    <div id="filter_phone_container" class="secondary-filter"><select name="filter_val_phone" class="filter-select"><option value="">Select Prefix...</option><?php foreach($phonePrefixes as $pp): ?><option value="<?php echo $pp; ?>" <?php echo ($filterValue == $pp) ? 'selected' : ''; ?>>+6<?php echo $pp; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_state_container" class="secondary-filter"><select name="filter_val_state" class="filter-select"><option value="">Select State...</option><?php foreach($malaysiaStates as $ms): ?><option value="<?php echo $ms; ?>" <?php echo ($filterValue == $ms) ? 'selected' : ''; ?>><?php echo $ms; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_year_container" class="secondary-filter"><select name="filter_val_year" class="filter-select"><option value="">Select Year...</option><?php foreach($years as $yr): ?><option value="<?php echo $yr; ?>" <?php echo ($filterValue == $yr) ? 'selected' : ''; ?>><?php echo $yr; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_points_container" class="secondary-filter"><select name="filter_val_points" class="filter-select"><option value="">Select Tier...</option><option value="low" <?php echo ($filterValue == 'low') ? 'selected' : ''; ?>>Below 50 pts</option><option value="mid" <?php echo ($filterValue == 'mid') ? 'selected' : ''; ?>>50 - 200 pts</option><option value="high" <?php echo ($filterValue == 'high') ? 'selected' : ''; ?>>Above 200 pts</option></select></div>
                    <div id="filter_amount_container" class="secondary-filter"><select name="filter_val_amount" class="filter-select"><option value="">Select Amount...</option><option value="low" <?php echo ($filterValue == 'low') ? 'selected' : ''; ?>>Below RM 200</option><option value="mid" <?php echo ($filterValue == 'mid') ? 'selected' : ''; ?>>RM 200 - RM 500</option><option value="high" <?php echo ($filterValue == 'high') ? 'selected' : ''; ?>>Above RM 500</option></select></div>

                    <input type="text" name="search" class="search-input" placeholder="Search donors by name, ID or email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($filterType) || !empty($searchTerm) || !empty($_GET['header_filter_phone']) || !empty($_GET['header_filter_payment_min']) || !empty($_GET['header_filter_points_min'])): ?>
                        <a href="admin_donor_page.php" class="btn btn-danger" style="background-color: #dc3545; padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>

                <table class="donor-table">
                    <thead>
                        <tr>
                            <th onclick="openModal('nameSortModal')">
                                DONOR NAME 
                                <?php if($sort=='name') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th onclick="openModal('contactSortModal')">
                                CONTACT INFO
                                <?php if($sort=='contact') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th style="width: 30%;" onclick="openModal('addressSortModal')">
                                ADDRESS
                                <?php if($sort=='address') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th onclick="openModal('paymentSortModal')">
                                TOTAL PAYMENT
                                <?php if($sort=='payment') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th style="text-align: right;" onclick="openModal('pointsSortModal')">
                                TOTAL POINTS
                                <?php if($sort=='points') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th style="text-align: center;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($donors) > 0): ?>
                            <?php foreach($donors as $donor): ?>
                            <tr>
                                <td>
                                    <div class="donor-info">
                                        <?php 
                                            $lightboxSrc = !empty($donor['Donor_ProfilePicture']) ? htmlspecialchars($donor['Donor_ProfilePicture']) : $defaultAvatarPlaceholder;
                                        ?>
                                        <div class="donor-avatar" onclick="openLightbox('<?php echo $lightboxSrc; ?>')">
                                            <?php if (!empty($donor['Donor_ProfilePicture'])): ?>
                                                <img src="<?php echo htmlspecialchars($donor['Donor_ProfilePicture']); ?>" alt="Profile">
                                            <?php else: echo substr($donor['Donor_Name'], 0, 1); endif; ?>
                                        </div>
                                        <div class="donor-details"><h4><?php echo htmlspecialchars($donor['Donor_Name']); ?></h4><p>ID: <?php echo htmlspecialchars($donor['Donor_ID']); ?></p></div>
                                    </div>
                                </td>
                                <td><div class="donor-details"><p><?php echo htmlspecialchars($donor['Donor_Email']); ?></p><p><?php echo htmlspecialchars($donor['Donor_ContactNumber']); ?></p></div></td>
                                <td><div class="address-display"><?php echo formatAddress($donor); ?></div></td>
                                <td>RM <?php echo number_format($donor['TotalPayment'], 2); ?></td>
                                <td style="text-align: right;"><?php echo number_format($donor['CurrentPoints']); ?> pts</td>
                                <td>
                                    <div class="action-cell">
                                        <div class="action-menu">
                                            <button class="menu-btn" onclick="toggleMenu(event, <?php echo $donor['Donor_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                            <div id="menu-<?php echo $donor['Donor_ID']; ?>" class="dropdown-content">
                                                <a href="admin_donor_view_edit.php?id=<?php echo $donor['Donor_ID']; ?>&mode=view" target="_blank"><i class="fas fa-eye"></i> View Details</a>
                                                <a href="admin_donor_view_edit.php?id=<?php echo $donor['Donor_ID']; ?>&mode=edit" target="_blank"><i class="fas fa-edit"></i> Edit Details</a>
                                                
                                                <a href="admin_donor_payment_history.php?donor_id=<?php echo $donor['Donor_ID']; ?>" target="_blank"><i class="fas fa-history"></i> Payment History</a>
                                                <a href="admin_donor_redemption_history.php?donor_id=<?php echo $donor['Donor_ID']; ?>" target="_blank"><i class="fas fa-gift"></i> Redemption History</a>
                                                <a href="admin_donor_wallet_history.php?donor_id=<?php echo $donor['Donor_ID']; ?>" target="_blank"><i class="fas fa-wallet"></i> Wallet History</a>
                                                
                                                <?php if($adminPosition === 'Super Admin'): ?>
                                                <div onclick="openBlockModal(<?php echo $donor['Donor_ID']; ?>, '<?php echo addslashes($donor['Donor_Name']); ?>')" class="text-delete"><i class="fas fa-ban"></i> Block User</div>
                                                <?php endif; ?>
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
                            if($filterType == 'name_sort' && !empty($filterValue)) $queryParams['filter_val_name'] = $filterValue;
                            if($filterType == 'phone' && !empty($filterValue)) $queryParams['filter_val_phone'] = $filterValue;
                            if($filterType == 'amount' && !empty($filterValue)) $queryParams['filter_val_amount'] = $filterValue;
                        }
                        if($sort != 'date' || $order != 'desc') {
                            $queryParams['sort'] = $sort;
                            $queryParams['order'] = $order;
                        }
                        // Add the new header filters to pagination
                        if(isset($_GET['header_filter_phone'])) $queryParams['header_filter_phone'] = $_GET['header_filter_phone'];
                        if(isset($_GET['header_filter_payment_min'])) $queryParams['header_filter_payment_min'] = $_GET['header_filter_payment_min'];
                        if(isset($_GET['header_filter_payment_max'])) $queryParams['header_filter_payment_max'] = $_GET['header_filter_payment_max'];
                        if(isset($_GET['header_filter_points_min'])) $queryParams['header_filter_points_min'] = $_GET['header_filter_points_min'];
                        if(isset($_GET['header_filter_points_max'])) $queryParams['header_filter_points_max'] = $_GET['header_filter_points_max'];
                        
                        $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';

                        if ($page > 1) echo '<a href="?page=' . ($page - 1) . $queryString . '" class="pagination-btn">Previous</a>'; 
                        else echo '<span class="pagination-btn disabled">Previous</span>';

                        $start_window = max(1, $page - 1);
                        $end_window = min($total_pages, $page + 1);
                        if ($page == 1) $end_window = min($total_pages, 3);
                        if ($page == $total_pages) $start_window = max(1, $total_pages - 2);

                        for ($i = $start_window; $i <= $end_window; $i++) {
                            if ($i == $page) echo '<span class="pagination-btn active">' . $i . '</span>';
                            else echo '<a href="?page=' . $i . $queryString . '" class="pagination-btn">' . $i . '</a>';
                        }

                        if ($page < $total_pages) echo '<a href="?page=' . ($page + 1) . $queryString . '" class="pagination-btn">Next</a>'; 
                        else echo '<span class="pagination-btn disabled">Next</span>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="nameSortModal" class="sort-modal" onclick="closeModal(event, 'nameSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Name</h3><span class="sort-close" onclick="document.getElementById('nameSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'name', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Name (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'name', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Name (Z - A)</a>
        </div>
    </div>

    <div id="contactSortModal" class="sort-modal" onclick="closeModal(event, 'contactSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Contact</h3><span class="sort-close" onclick="document.getElementById('contactSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'contact', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Phone (Ascending)</a>
            <a href="<?php echo buildUrl(['sort'=>'contact', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> Phone (Descending)</a>
            
            <div class="filter-section-title">Filter by Prefix</div>
            <div class="prefix-grid">
                <?php 
                // Define prefixes 010 to 019
                for($p=10; $p<=19; $p++) {
                    $prefix = "0$p";
                    // Build URL keeping existing params
                    $url = buildUrl(['header_filter_phone' => $prefix]);
                    $activeClass = (isset($_GET['header_filter_phone']) && $_GET['header_filter_phone'] == $prefix) ? 'background-color:var(--primary);color:white;' : '';
                    echo "<a href='$url' class='prefix-btn' style='$activeClass'>+6$prefix</a>";
                }
                ?>
            </div>
        </div>
    </div>

    <div id="addressSortModal" class="sort-modal" onclick="closeModal(event, 'addressSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Location</h3><span class="sort-close" onclick="document.getElementById('addressSortModal').style.display='none'">&times;</span></div>
            <span style="display:block; font-size:12px; color:#999; margin-bottom:10px;">Sorting based on State</span>
            <a href="<?php echo buildUrl(['sort'=>'address', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-map-marker-alt"></i> State (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'address', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-map-marker-alt"></i> State (Z - A)</a>
        </div>
    </div>

    <div id="paymentSortModal" class="sort-modal" onclick="closeModal(event, 'paymentSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Total Payment</h3><span class="sort-close" onclick="document.getElementById('paymentSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'payment', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-up"></i> Low to High</a>
            <a href="<?php echo buildUrl(['sort'=>'payment', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-down"></i> High to Low</a>
            
            <div class="filter-section-title">Filter by Amount (RM)</div>
            <form action="admin_donor_page.php" method="GET" class="range-form">
                <?php echo getHiddenInputs(['header_filter_payment_min', 'header_filter_payment_max']); ?>
                <div class="range-inputs">
                    <input type="number" name="header_filter_payment_min" class="range-input" placeholder="Min" min="0" step="0.01" value="<?php echo isset($_GET['header_filter_payment_min']) ? htmlspecialchars($_GET['header_filter_payment_min']) : ''; ?>">
                    <input type="number" name="header_filter_payment_max" class="range-input" placeholder="Max" min="0" step="0.01" value="<?php echo isset($_GET['header_filter_payment_max']) ? htmlspecialchars($_GET['header_filter_payment_max']) : ''; ?>">
                </div>
                <button type="submit" class="range-submit">Apply Filter</button>
            </form>
        </div>
    </div>

    <div id="pointsSortModal" class="sort-modal" onclick="closeModal(event, 'pointsSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Points</h3><span class="sort-close" onclick="document.getElementById('pointsSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'points', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Low to High</a>
            <a href="<?php echo buildUrl(['sort'=>'points', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> High to Low</a>
            
            <div class="filter-section-title">Filter by Points</div>
            <form action="admin_donor_page.php" method="GET" class="range-form">
                <?php echo getHiddenInputs(['header_filter_points_min', 'header_filter_points_max']); ?>
                <div class="range-inputs">
                    <input type="number" name="header_filter_points_min" class="range-input" placeholder="Min" min="0" value="<?php echo isset($_GET['header_filter_points_min']) ? htmlspecialchars($_GET['header_filter_points_min']) : ''; ?>">
                    <input type="number" name="header_filter_points_max" class="range-input" placeholder="Max" min="0" value="<?php echo isset($_GET['header_filter_points_max']) ? htmlspecialchars($_GET['header_filter_points_max']) : ''; ?>">
                </div>
                <button type="submit" class="range-submit">Apply Filter</button>
            </form>
        </div>
    </div>

    <div class="modal" id="blockDonorModal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header" style="background-color: #dc3545; color: white;">
                <h2 style="font-size: 16px;"><i class="fas fa-exclamation-triangle"></i> Block Confirmation</h2>
                <button class="close-btn" onclick="closeModal('blockDonorModal')" style="color: white;">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center; padding: 30px;">
                <form method="POST" action="admin_donor_page.php">
                    <input type="hidden" name="block_donor" value="1"><input type="hidden" name="block_donor_id" id="block_donor_id">
                    <i class="fas fa-ban" style="font-size: 50px; color: #dc3545; margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 10px;">Block this donor?</h3>
                    <p style="color: #666; font-size: 14px; margin-bottom: 25px;">Are you sure you want to block <strong id="block_donor_name_display"></strong>?<br>They will not be able to log in or make donations.</p>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button type="button" class="btn" style="background: #eee; color: #333;" onclick="closeModal('blockDonorModal')">Cancel</button>
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
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            if (type === 'state') { document.getElementById('filter_state_container').classList.add('active'); document.getElementById('filter_state_container').querySelector('select').disabled = false; }
            else if (type === 'year') { document.getElementById('filter_year_container').classList.add('active'); document.getElementById('filter_year_container').querySelector('select').disabled = false; }
            else if (type === 'points') { document.getElementById('filter_points_container').classList.add('active'); document.getElementById('filter_points_container').querySelector('select').disabled = false; }
            else if (type === 'amount') { document.getElementById('filter_amount_container').classList.add('active'); document.getElementById('filter_amount_container').querySelector('select').disabled = false; }
            else if (type === 'name_sort') { document.getElementById('filter_name_container').classList.add('active'); document.getElementById('filter_name_container').querySelector('select').disabled = false; }
            else if (type === 'phone') { document.getElementById('filter_phone_container').classList.add('active'); document.getElementById('filter_phone_container').querySelector('select').disabled = false; }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            const s = document.getElementById('floatingSuccess'); const e = document.getElementById('floatingError');
            if(s) setTimeout(() => s.style.display='none', 5000); if(e) setTimeout(() => e.style.display='none', 5000);
            window.addEventListener('click', function(event) {
                if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
                if (event.target.classList.contains('modal') && !event.target.classList.contains('sort-modal')) event.target.style.display = "none";
                if (event.target.id == 'imageLightbox') closeLightbox();
            });
            document.addEventListener('keydown', function(event) {
                if (event.key === "Escape" && document.getElementById('imageLightbox').style.display === "flex") { closeLightbox(); }
            });
        });

        function openLightbox(imageSrc) { if (!imageSrc) return; document.getElementById('lightboxImage').src = imageSrc; document.getElementById('imageLightbox').style.display = "flex"; }
        function closeLightbox() { document.getElementById('imageLightbox').style.display = "none"; }
        function openBlockModal(id, name) { document.getElementById('block_donor_id').value = id; document.getElementById('block_donor_name_display').textContent = name; document.getElementById('blockDonorModal').style.display = 'flex'; }
        
        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); document.getElementById('menu-' + id).style.display = 'block'; }
        
        function closeModal(e, id) { 
            if(typeof e === 'string') {
                document.getElementById(e).style.display = 'none';
            } else {
                if(e.target.id === id) document.getElementById(id).style.display = 'none';
            }
        }
        
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    </script>
</body>
</html>