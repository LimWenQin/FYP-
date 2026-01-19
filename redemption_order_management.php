<?php
// redemption_order_management.php
session_start();

// --- 引入 PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// --- 检查是否登录 (Admin 或 Staff 均可) ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// 定义网站基础 URL
define('BASE_URL', 'http://localhost/donation_system/');

// ==========================================
// 0. AUTOMATED TASKS (LAZY CRON)
// ==========================================
function checkAutomatedTasks($conn) {
    // A. 自动完成
    $sqlAutoComp = "UPDATE redemption_order 
                    SET Redemption_Status = 'Completed', Redemption_Updated_At = NOW() 
                    WHERE Redemption_Status = 'Shipped' 
                    AND Redemption_Shipped_At < DATE_SUB(NOW(), INTERVAL 21 DAY)";
    $conn->query($sqlAutoComp);

    // B. 发送跟进邮件
    $sqlFollow = "SELECT r.Redemption_ID, d.Donor_Name, d.Donor_Email, rw.Reward_ItemName 
                  FROM redemption_order r
                  JOIN donor d ON r.Donor_ID = d.Donor_ID
                  JOIN reward_item rw ON r.Reward_ID = rw.Reward_ID
                  WHERE r.Redemption_Status = 'Shipped' 
                  AND r.Redemption_FollowUp_Sent = 0
                  AND r.Redemption_Est_Delivery_Date <= CURDATE()";
    
    $resFollow = $conn->query($sqlFollow);
    if ($resFollow && $resFollow->num_rows > 0) {
        while ($row = $resFollow->fetch_assoc()) {
            if (sendFollowUpEmail($row['Donor_Email'], $row['Donor_Name'], $row['Reward_ItemName'], $row['Redemption_ID'])) {
                $rid = $row['Redemption_ID'];
                $conn->query("UPDATE redemption_order SET Redemption_FollowUp_Sent = 1 WHERE Redemption_ID = $rid");
            }
        }
    }
}
checkAutomatedTasks($conn);

// ==========================================
// 1. HANDLE EXPORT TO EXCEL
// ==========================================
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $filename = "redemption_orders_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $sql = "SELECT r.Redemption_ID, r.Redemption_Status, r.Redemption_PointsSpent, r.Redemption_Quantity,
                   r.Redemption_Updated_At, r.Redemption_TrackingNumber, r.Redemption_CancelReason,
                   d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber,
                   rw.Reward_ItemName, rw.Reward_Code
            FROM redemption_order r
            JOIN donor d ON r.Donor_ID = d.Donor_ID
            JOIN reward_item rw ON r.Reward_ID = rw.Reward_ID
            ORDER BY r.Redemption_ID DESC";
    $result = $conn->query($sql);

    echo '<table border="1">';
    echo '<tr>
            <th>Order ID</th><th>Donor Name</th><th>Donor Email</th><th>Contact</th>
            <th>Reward Item</th><th>Item Code</th><th>Quantity</th><th>Points Used</th>
            <th>Status</th><th>Tracking No</th><th>Cancel Reason</th><th>Last Update</th>
          </tr>';

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['Redemption_ID'] . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_Name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_Email']) . '</td>';
            echo '<td style="mso-number-format:\'\@\'">' . htmlspecialchars($row['Donor_ContactNumber']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Reward_ItemName']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Reward_Code']) . '</td>';
            echo '<td>' . $row['Redemption_Quantity'] . '</td>';
            echo '<td>' . $row['Redemption_PointsSpent'] . '</td>';
            echo '<td>' . htmlspecialchars($row['Redemption_Status']) . '</td>';
            echo '<td style="mso-number-format:\'\@\'">' . htmlspecialchars($row['Redemption_TrackingNumber']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Redemption_CancelReason']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Redemption_Updated_At']) . '</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    exit(); 
}

// --- 获取当前管理员/Staff信息 ---
$adminName = "User";
$adminPosition = "Role";
$adminProfilePicture = null;
$currentAdminId = 0;

if (isset($_SESSION['admin_id'])) {
    $currentAdminId = $_SESSION['admin_id'];
    $adminSql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
    $adminResult = $conn->query($adminSql);
    if ($adminResult && $adminResult->num_rows > 0) {
        $adminData = $adminResult->fetch_assoc();
        $adminName = $adminData['Admin_Name'];
        $adminPosition = $adminData['Admin_Role']; 
        $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
    }
} elseif (isset($_SESSION['staff_id'])) {
    $currentAdminId = $_SESSION['staff_id'];
    $staffSql = "SELECT Staff_FullName, Staff_ProfilePicture, Staff_Role FROM staff WHERE Staff_ID = $currentAdminId";
    $staffResult = $conn->query($staffSql);
    if ($staffResult && $staffResult->num_rows > 0) {
        $staffData = $staffResult->fetch_assoc();
        $adminName = $staffData['Staff_FullName'];
        $adminPosition = $staffData['Staff_Role'];
        $adminProfilePicture = $staffData['Staff_ProfilePicture'];
    }
}

// --- SEARCH & FILTER ---
$searchTerm = "";
$whereConditions = ["1=1"]; 

// --- Sorting Logic ---
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Mapping sort keys to DB columns
$sortMap = [
    'id' => 'r.Redemption_ID',
    'item' => 'rw.Reward_ItemName',
    'donor' => 'd.Donor_Name',
    'qty' => 'r.Redemption_Quantity',
    'points' => 'r.Redemption_PointsSpent',
    'status' => 'r.Redemption_Status'
];

$orderByCol = isset($sortMap[$sort]) ? $sortMap[$sort] : '';
$orderDir = ($order === 'desc') ? 'DESC' : 'ASC';

if ($orderByCol) {
    $orderClause = "ORDER BY $orderByCol $orderDir";
} else {
    // Default Sorting: Pending first, then newest ID
    $orderClause = "ORDER BY CASE WHEN r.Redemption_Status = 'Pending' THEN 1 ELSE 2 END, r.Redemption_ID DESC"; 
}


// 1. Keyword Search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereConditions[] = "(r.Redemption_ID LIKE '%$searchTerm%' 
                           OR d.Donor_Name LIKE '%$searchTerm%' 
                           OR rw.Reward_ItemName LIKE '%$searchTerm%')";
}

// 2. Specific Status Button (Main Filter)
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $val = $conn->real_escape_string($_GET['status']);
    if ($val == 'Pending') {
        $whereConditions[] = "r.Redemption_Status = 'Pending'";
    } else {
        $whereConditions[] = "r.Redemption_Status = '$val'";
    }
}

// 3. New Filters from Dropdown
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    
    // Status
    if ($filterType == 'status' && !empty($_GET['filter_val_status'])) {
        $val = $conn->real_escape_string($_GET['filter_val_status']);
        $whereConditions[] = "r.Redemption_Status = '$val'";
    }
    // Date
    elseif ($filterType == 'date' && !empty($_GET['filter_val_date'])) {
        $val = $_GET['filter_val_date'];
        if($val == 'newest') $orderClause = "ORDER BY r.Redemption_Created_At DESC";
        if($val == 'oldest') $orderClause = "ORDER BY r.Redemption_Created_At ASC";
    }
    // Phone Prefix
    elseif ($filterType == 'phone' && !empty($_GET['filter_val_phone'])) {
        $val = $conn->real_escape_string($_GET['filter_val_phone']);
        $whereConditions[] = "r.Redemption_ContactNumber LIKE '%$val%'";
    }
    // City
    elseif ($filterType == 'city' && !empty($_GET['filter_val_city'])) {
        $val = $conn->real_escape_string($_GET['filter_val_city']);
        $whereConditions[] = "r.Redemption_City = '$val'";
    }
}

// 4. HEADER SPECIFIC FILTERS (New Requirements)

// 4.1 Date Filtering (Order Info)
if (!empty($_GET['header_filter_date'])) {
    $date = $conn->real_escape_string($_GET['header_filter_date']);
    // Check against Created_At date
    $whereConditions[] = "DATE(r.Redemption_Created_At) = '$date'";
}
if (!empty($_GET['header_filter_year'])) {
    $y = $conn->real_escape_string($_GET['header_filter_year']);
    $whereConditions[] = "YEAR(r.Redemption_Created_At) = '$y'";
}
if (!empty($_GET['header_filter_month']) && !empty($_GET['header_filter_year'])) {
    $m = $conn->real_escape_string($_GET['header_filter_month']);
    $whereConditions[] = "MONTH(r.Redemption_Created_At) = '$m'";
}

// 4.2 Quantity Range
if (isset($_GET['header_filter_qty_min']) && $_GET['header_filter_qty_min'] !== '') {
    $min = (int)$_GET['header_filter_qty_min'];
    $whereConditions[] = "r.Redemption_Quantity >= $min";
}
if (isset($_GET['header_filter_qty_max']) && $_GET['header_filter_qty_max'] !== '') {
    $max = (int)$_GET['header_filter_qty_max'];
    $whereConditions[] = "r.Redemption_Quantity <= $max";
}

// 4.3 Points Range
if (isset($_GET['header_filter_points_min']) && $_GET['header_filter_points_min'] !== '') {
    $min = (int)$_GET['header_filter_points_min'];
    $whereConditions[] = "r.Redemption_PointsSpent >= $min";
}
if (isset($_GET['header_filter_points_max']) && $_GET['header_filter_points_max'] !== '') {
    $max = (int)$_GET['header_filter_points_max'];
    $whereConditions[] = "r.Redemption_PointsSpent <= $max";
}

// 4.4 Header Status Filter (Direct Selection)
if (isset($_GET['header_filter_status']) && !empty($_GET['header_filter_status'])) {
    $statusVal = $conn->real_escape_string($_GET['header_filter_status']);
    $whereConditions[] = "r.Redemption_Status = '$statusVal'";
}


$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// ==========================================
// HELPER: Build URL for Sorting
// ==========================================
function buildUrl($params = []) {
    $current = $_GET;
    unset($current['page']); // Reset page when sorting
    $merged = array_merge($current, $params);
    return '?' . http_build_query($merged);
}

// Helper to generate hidden inputs for filtering forms (preserves other filters)
function getHiddenInputs($exclude = []) {
    $html = '';
    $params = $_GET;
    // Remove params replaced by form
    foreach ($exclude as $key) { unset($params[$key]); }
    unset($params['page']);
    
    foreach ($params as $key => $value) {
        $html .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'">';
    }
    return $html;
}

// ==========================================
// EMAIL FUNCTIONS
// ==========================================
function sendStatusEmail($to, $name, $status, $itemName, $tracking = null, $estDate = null, $cancelReason = null) {
    $subject = "Update on your Redemption Order - Love Bridge";
    
    $bodyContent = "<div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>";
    $bodyContent .= "<div style='background-color: white; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto;'>";
    $bodyContent .= "<h2 style='color: #F28585;'>Redemption Status Update</h2>";
    $bodyContent .= "<p>Dear <strong>$name</strong>,</p>";

    if ($status == 'Shipped') {
        $bodyContent .= "<p>Great news! Your redemption for <strong>'$itemName'</strong> has been approved and shipped.</p>";
        if ($tracking) {
            $bodyContent .= "<p style='font-size:16px; font-weight:bold; color:#333;'>Tracking Number: $tracking</p>";
        }
        if ($estDate) {
            $formattedDate = date('d M Y', strtotime($estDate));
            $bodyContent .= "<p>Estimated Delivery Date: <strong>$formattedDate</strong></p>";
        }
        $bodyContent .= "<p>You should receive it soon!</p>";
    } elseif ($status == 'Cancelled') {
        $bodyContent .= "<p style='color:#dc3545;'>We regret to inform you that your redemption for <strong>'$itemName'</strong> has been cancelled/rejected.</p>";
        if ($cancelReason) {
            $bodyContent .= "<p><strong>Reason:</strong> " . htmlspecialchars($cancelReason) . "</p>";
        }
        $bodyContent .= "<p>Any points used have been fully refunded to your account.</p>";
    } else {
        $bodyContent .= "<p>The status of your redemption order for <strong>'$itemName'</strong> has been updated to: <strong>$status</strong>.</p>";
    }

    $bodyContent .= "<br><p>Thank you for your support,<br>Love Bridge Team</p>";
    $bodyContent .= "</div></div>";

    return sendEmailViaSMTP($to, $subject, $bodyContent);
}

function sendFollowUpEmail($to, $name, $itemName, $orderId) {
    $subject = "Did you receive your item? - Love Bridge";
    $confirmLink = BASE_URL . "donor_portal.php?action=confirm_receipt&order_id=" . $orderId; 
    
    $bodyContent = "<div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>";
    $bodyContent .= "<div style='background-color: white; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto;'>";
    $bodyContent .= "<h2 style='color: #28a745;'>Has your reward arrived?</h2>";
    $bodyContent .= "<p>Dear <strong>$name</strong>,</p>";
    $bodyContent .= "<p>Our records show that your redeemed item <strong>'$itemName'</strong> should have arrived by now.</p>";
    $bodyContent .= "<p>Could you please confirm if you have received it?</p>";
    $bodyContent .= "<div style='text-align:center; margin: 30px 0;'>";
    $bodyContent .= "<a href='$confirmLink' style='background-color:#F28585; color:white; padding:12px 25px; text-decoration:none; border-radius:5px; font-weight:bold;'>Yes, I Received It</a>";
    $bodyContent .= "</div>";
    $bodyContent .= "<p style='font-size:12px; color:#666;'>If you haven't received it yet, please reply to this email.</p>";
    $bodyContent .= "<br><p>Thank you,<br>Love Bridge Team</p>";
    $bodyContent .= "</div></div>";

    return sendEmailViaSMTP($to, $subject, $bodyContent);
}

function sendEmailViaSMTP($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lovebridge1201@gmail.com'; 
        $mail->Password   = 'odaj iwrz gfrt vven';      
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('lovebridge1201@gmail.com', 'Love Bridge Admin');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 2. HANDLE UPDATE ORDER (Manage)
    if (isset($_POST['action']) && $_POST['action'] == 'update_order') {
        $redemptionId = intval($_POST['redemption_id']);
        $newStatus = $_POST['new_status'];
        $cancelReason = isset($_POST['cancel_reason']) ? $conn->real_escape_string($_POST['cancel_reason']) : null;
        
        // Tracking & Est Days
        $tracking = isset($_POST['tracking_number']) ? $conn->real_escape_string($_POST['tracking_number']) : null;
        $estDays = isset($_POST['estimated_days']) ? intval($_POST['estimated_days']) : 3; 
        
        // Editable Info
        $eContact = $conn->real_escape_string($_POST['contact']);
        $eAddr1 = $conn->real_escape_string($_POST['address1']);
        $eAddr2 = $conn->real_escape_string($_POST['address2']);
        $eAddr3 = $conn->real_escape_string($_POST['address3']);
        $eCity = $conn->real_escape_string($_POST['city']);
        $ePostal = $conn->real_escape_string($_POST['postal_code']);
        $eState = $conn->real_escape_string($_POST['state']);

        // Check Previous Status
        $checkSql = "SELECT r.Redemption_Status, r.Redemption_PointsSpent, r.Redemption_Quantity, r.Donor_ID, r.Reward_ID,
                            d.Donor_Email, d.Donor_Name, rw.Reward_ItemName 
                     FROM redemption_order r 
                     JOIN donor d ON r.Donor_ID = d.Donor_ID
                     JOIN reward_item rw ON r.Reward_ID = rw.Reward_ID
                     WHERE r.Redemption_ID = $redemptionId";
        $checkResult = $conn->query($checkSql);
        $orderData = $checkResult->fetch_assoc();
        $oldStatus = $orderData['Redemption_Status'];

        // Logic for Cancelled (Refund Points & Return Stock)
        if ($newStatus == 'Cancelled' && $oldStatus != 'Cancelled') {
            $refundPoints = $orderData['Redemption_PointsSpent'];
            $refundQty = $orderData['Redemption_Quantity'];
            $donorId = $orderData['Donor_ID'];
            $rewardId = $orderData['Reward_ID'];
            
            // Refund Points
            $conn->query("UPDATE point SET Points_Total = Points_Total + $refundPoints, Points_Updated_At = NOW() WHERE Donor_ID = $donorId");
            // Return Stock
            $conn->query("UPDATE reward_item SET Reward_Stock = Reward_Stock + $refundQty WHERE Reward_ID = $rewardId");
        }

        // Logic for Shipped
        $extraUpdate = "";
        $estDeliveryDate = null;
        if ($newStatus == 'Shipped' && $oldStatus != 'Shipped') {
            $estDeliveryDate = date('Y-m-d', strtotime("+$estDays days"));
            $extraUpdate = ", Redemption_Shipped_At = NOW(), Redemption_Est_Delivery_Date = '$estDeliveryDate', Redemption_FollowUp_Sent = 0";
        }

        // Include Cancel Reason in Update
        $reasonUpdate = "";
        if ($newStatus == 'Cancelled' && !empty($cancelReason)) {
            $reasonUpdate = ", Redemption_CancelReason = '$cancelReason'";
        }

        $sql = "UPDATE redemption_order SET 
                Redemption_Status = '$newStatus', 
                Redemption_TrackingNumber = '$tracking',
                Redemption_ContactNumber = '$eContact',
                Redemption_Address1 = '$eAddr1',
                Redemption_Address2 = '$eAddr2',
                Redemption_Address3 = '$eAddr3',
                Redemption_City = '$eCity',
                Redemption_PostalCode = '$ePostal',
                Redemption_State = '$eState',
                Redemption_Updated_At = NOW() 
                $extraUpdate 
                $reasonUpdate
                WHERE Redemption_ID = $redemptionId";
        
        if ($conn->query($sql)) {
            // Send Notification Email
            sendStatusEmail($orderData['Donor_Email'], $orderData['Donor_Name'], $newStatus, $orderData['Reward_ItemName'], $tracking, $estDeliveryDate, $cancelReason);

            $msg = "Order #$redemptionId updated successfully.";
            header("Location: redemption_order_management.php?success=" . urlencode($msg));
            exit();
        } else {
            header("Location: redemption_order_management.php?error=" . urlencode("Database error: " . $conn->error));
            exit();
        }
    }
}

// --- PAGINATION (Limit page range to 3) ---
$results_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

// Count Total
$count_sql = "SELECT COUNT(*) as total 
              FROM redemption_order r 
              JOIN donor d ON r.Donor_ID = d.Donor_ID 
              JOIN reward_item rw ON r.Reward_ID = rw.Reward_ID 
              $whereClause";
$count_result = $conn->query($count_sql);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $results_per_page);

// Fetch Data
$sql = "SELECT r.*, d.Donor_Name, d.Donor_Email, d.Donor_ProfilePicture, rw.Reward_ItemName, rw.Reward_PhotoPath
        FROM redemption_order r
        JOIN donor d ON r.Donor_ID = d.Donor_ID
        JOIN reward_item rw ON r.Reward_ID = rw.Reward_ID
        $whereClause
        $orderClause
        LIMIT $start_from, $results_per_page";

$result = $conn->query($sql);
$orders = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// --- STATS CALCULATION (Removed Processing) ---
$statsSql = "SELECT 
                COUNT(*) as TotalOrders,
                SUM(CASE WHEN Redemption_Status = 'Pending' THEN 1 ELSE 0 END) as PendingOrders,
                SUM(CASE WHEN Redemption_Status = 'Shipped' THEN 1 ELSE 0 END) as ShippedOrders,
                SUM(CASE WHEN Redemption_Status = 'Completed' THEN 1 ELSE 0 END) as CompletedOrders,
                SUM(Redemption_PointsSpent) as TotalPoints
             FROM redemption_order";
$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

// --- DATA FOR FILTERS ---
$phonePrefixes = ['011', '012', '013', '014', '015', '016', '017', '018', '019'];
$cities = [];
$cityQ = $conn->query("SELECT DISTINCT Redemption_City FROM redemption_order ORDER BY Redemption_City");
while($c = $cityQ->fetch_assoc()) $cities[] = $c['Redemption_City'];

// --- Helper Data for Date Filters ---
$currentYear = date('Y');
$years = range($currentYear, 2020);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redemption Orders - Love Bridge Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* UI Styles */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 28px; font-weight: 600; margin-bottom: 5px; }
        .stat-desc { font-size: 12px; color: #28a745; display: flex; align-items: center; gap: 5px; font-weight: 500;}
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        .stat-card:nth-child(1) .stat-icon { background: rgba(255, 193, 7, 0.2); color: #ffc107; } 
        .stat-card:nth-child(2) .stat-icon { background: rgba(23, 162, 184, 0.2); color: #17a2b8; } 
        .stat-card:nth-child(3) .stat-icon { background: rgba(40, 167, 69, 0.2); color: #28a745; } 
        .stat-card:nth-child(4) .stat-icon { background: rgba(220, 53, 69, 0.2); color: #dc3545; } 

        .order-management { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-header h2 { font-size: 18px; font-weight: 600; color: #333; }
        .header-buttons { display: flex; gap: 10px; }
        .btn { padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; color: white; transition: 0.3s; font-size: 14px; text-decoration: none; }
        .btn-primary { background: #F28585; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-light-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .btn-light-pending:hover { background: #ffeeba; }

        /* Filter Styles */
        .staff-search { margin-bottom: 25px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; flex-wrap: wrap;}
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; background-color: white; outline: none; min-width: 140px; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; background: white; min-width: 200px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: #888; font-size: 13px; border-bottom: 2px solid #f0f0f0; user-select: none; cursor: pointer; }
        th:hover { color: #F28585; }
        td { padding: 15px; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        /* Center Alignment */
        th:nth-child(3), td:nth-child(3) { text-align: center; } /* Donor */
        th:nth-child(4), td:nth-child(4) { text-align: center; } /* Quantity */
        th:nth-child(6), td:nth-child(6) { text-align: center; } /* Status */
        th:nth-child(7), td:nth-child(7) { text-align: center; } /* Action */

        /* Sort Header Styles */
        .sort-header-content { display: flex; align-items: center; gap: 5px; }
        th.center-header .sort-header-content { justify-content: center; }

        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-shipped { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .item-preview { display: flex; align-items: center; gap: 10px; }
        .item-thumb { width: 50px; height: 50px; border-radius: 5px; object-fit: cover; border: 1px solid #eee; cursor: zoom-in; transition: transform 0.2s; }
        .item-thumb:hover { transform: scale(1.05); border-color: #F28585; }
        .order-meta { font-size: 12px; color: #666; margin-top: 5px; }

        /* Action Menu */
        .action-cell { display: flex; justify-content: center; align-items: center; }
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { background-color: #f8f9fa; border: 1px solid #e9ecef; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; color: #6c757d; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; outline: none; }
        .menu-btn:hover { background-color: #e2e6ea; color: #F28585; }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 160px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; text-align: left; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 10px; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: #F28585; }

        /* Pagination */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-info { font-size: 13px; color: #666; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; display: inline-block; margin-left: 5px;}
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; border-color: #eee; }

        /* Modals */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; border-radius: 10px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee; }
        .modal-body { padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .form-input[readonly] { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; }
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; width: 100%; box-sizing: border-box; } 
        .form-row { display: flex; gap: 15px; } 

        .ai-panel { background: #f0f8ff; border: 1px solid #cce5ff; border-radius: 8px; padding: 15px; margin-top: 15px; position: relative; overflow: hidden; }
        .ai-data { display: flex; gap: 20px; }
        .ai-metric { flex: 1; }
        .ai-metric span { font-size: 11px; color: #666; display: block; }
        .ai-metric strong { font-size: 16px; color: #333; }
        
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .btn-approve { flex: 1; background: #28a745; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-reject { flex: 1; background: #dc3545; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        
        .select2-container .select2-selection--single { height: 42px !important; border: 1px solid #ddd !important; border-radius: 5px !important; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
        
        .register-hint { margin-top: 5px; padding: 10px; background-color: #f0f8ff; border-left: 3px solid #17a2b8; font-size: 13px; color: #555; border-radius: 4px; }
        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid #ddd; border-right: none; border-radius: 5px 0 0 5px; color: #666; font-weight: bold; font-size: 14px; }
        .phone-input { border-radius: 0 5px 5px 0 !important; }

        /* Floating Alert */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; display: none; }
        .floating-alert i { margin-top: 3px; }
        .floating-alert-success { background: white; color: #28a745; border-left: 4px solid #28a745; }
        .floating-alert-danger { background: white; color: #dc3545; border-left: 4px solid #dc3545; }

        /* Lightbox */
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 2002; }
        .close-lightbox:hover { color: #bbb; }
        
        .item-preview-row { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; background: #fafafa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }

        /* --- Sort Modal Styles (New) --- */
        .sort-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .sort-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; }
        .sort-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .sort-header h3 { margin: 0; font-size: 16px; color: #333; }
        .sort-close { cursor: pointer; font-size: 20px; color: #999; }
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #555; text-decoration: none; }
        .sort-btn:hover { background: #f8f9fa; border-color: #ddd; color: #F28585; }
        .sort-btn i { width: 20px; text-align: center; margin-right: 8px; }

        /* New Range/Filter Styles */
        .filter-section-title { font-size: 14px; font-weight: 600; color: #555; margin: 15px 0 10px 0; border-top: 1px solid #eee; padding-top: 15px; }
        .range-form { display: flex; flex-direction: column; gap: 10px; }
        .range-inputs { display: flex; gap: 10px; }
        .range-input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        .range-submit { width: 100%; padding: 8px; background: #F28585; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; transition: 0.2s; }
        .range-submit:hover { background: #e06c6c; }
        .go-btn { padding: 8px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .status-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; border-radius: 5px; text-decoration: none; color: #333; text-align: left; font-size: 13px; transition: 0.2s; }
        .status-btn:hover { background: #f8f9fa; border-color: #ddd; }
        .status-btn span { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 10px; }
    </style>
</head>
<body>
    
    <div class="floating-alert floating-alert-success" id="floatingSuccess" style="display: <?php echo isset($_GET['success']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-check-circle"></i>
        <div id="floatingSuccessText"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div>
    </div>

    <div class="floating-alert floating-alert-danger" id="floatingError" style="display: <?php echo isset($_GET['error']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-exclamation-circle"></i>
        <div id="alertErrorText"><?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?></div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Redemption Orders</h1>
                <p>Manage reward redemptions, approve requests, and track shipping.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card"><div class="stat-info"><h3>PENDING REQUESTS</h3><h2><?php echo (int)$stats['PendingOrders']; ?></h2><p class="stat-desc"><i class="fas fa-hourglass-half"></i> Awaiting processing</p></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3>SHIPPED ORDERS</h3><h2><?php echo (int)$stats['ShippedOrders']; ?></h2><p class="stat-desc"><i class="fas fa-truck"></i> On the way</p></div><div class="stat-icon"><i class="fas fa-shipping-fast"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3>COMPLETED ORDERS</h3><h2><?php echo (int)$stats['CompletedOrders']; ?></h2><p class="stat-desc"><i class="fas fa-check-circle"></i> Successfully delivered</p></div><div class="stat-icon"><i class="fas fa-box-open"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3>POINTS REDEEMED</h3><h2><?php echo number_format((int)$stats['TotalPoints']); ?></h2><p class="stat-desc"><i class="fas fa-exchange-alt"></i> Total value exchanged</p></div><div class="stat-icon"><i class="fas fa-star"></i></div></div>
            </div>

            <div class="order-management">
                <div class="section-header">
                    <h2>Order List</h2>
                    <div class="header-buttons">
                        <a href="redemption_order_management.php?status=Pending" class="btn btn-light-pending">
                            <i class="fas fa-filter"></i> Show Pending Only
                        </a>
                        <a href="redemption_order_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Redemption</a>
                        <a href="redemption_order_management.php?export=excel" class="btn btn-success"><i class="fas fa-download"></i> Export Data</a>
                    </div>
                </div>

                <form class="staff-search" method="GET">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="status" <?php if(isset($_GET['filter_type']) && $_GET['filter_type'] == 'status') echo 'selected'; ?>>Status</option>
                            <option value="date" <?php if(isset($_GET['filter_type']) && $_GET['filter_type'] == 'date') echo 'selected'; ?>>Date</option>
                            <option value="phone" <?php if(isset($_GET['filter_type']) && $_GET['filter_type'] == 'phone') echo 'selected'; ?>>Phone Prefix</option>
                            <option value="city" <?php if(isset($_GET['filter_type']) && $_GET['filter_type'] == 'city') echo 'selected'; ?>>City</option>
                        </select>
                    </div>

                    <div id="filter_status" class="secondary-filter"><select name="filter_val_status" class="filter-select"><option value="">Select Status...</option><option value="Pending">Pending</option><option value="Shipped">Shipped</option><option value="Completed">Completed</option><option value="Cancelled">Cancelled</option></select></div>
                    <div id="filter_date" class="secondary-filter"><select name="filter_val_date" class="filter-select"><option value="newest">Newest First</option><option value="oldest">Oldest First</option></select></div>
                    <div id="filter_phone" class="secondary-filter"><select name="filter_val_phone" class="filter-select"><option value="">Select Prefix...</option><?php foreach($phonePrefixes as $p) echo "<option value='$p'>+6$p</option>"; ?></select></div>
                    <div id="filter_city" class="secondary-filter"><select name="filter_val_city" class="filter-select"><option value="">Select City...</option><?php foreach($cities as $c) echo "<option value='$c'>$c</option>"; ?></select></div>

                    <input type="text" name="search" class="search-input" placeholder="Search Order ID, Donor or Item..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($searchTerm) || isset($_GET['filter_type']) || isset($_GET['status']) || isset($_GET['sort']) || isset($_GET['header_filter_date']) || isset($_GET['header_filter_year'])): ?>
                        <a href="redemption_order_management.php" class="btn btn-danger" style="padding:10px 15px;"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
                
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f0f0f0;">
                            <th style="padding: 15px;" onclick="openModal('idSortModal')">
                                <div class="sort-header-content">
                                    ORDER INFO / DATE <?php if($sort=='id') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort" style="color:#ccc; font-size:11px;"></i>'; ?>
                                </div>
                            </th>
                            <th style="padding: 15px;" onclick="openModal('itemSortModal')">
                                <div class="sort-header-content">
                                    ITEM REDEEMED <?php if($sort=='item') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort" style="color:#ccc; font-size:11px;"></i>'; ?>
                                </div>
                            </th>
                            <th style="padding: 15px;" class="center-header" onclick="openModal('donorSortModal')">
                                <div class="sort-header-content">
                                    DONOR <?php if($sort=='donor') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort" style="color:#ccc; font-size:11px;"></i>'; ?>
                                </div>
                            </th> 
                            <th style="padding: 15px;" class="center-header" onclick="openModal('qtySortModal')">
                                <div class="sort-header-content">
                                    QUANTITY <?php if($sort=='qty') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort" style="color:#ccc; font-size:11px;"></i>'; ?>
                                </div>
                            </th>
                            <th style="padding: 15px;" onclick="openModal('pointsSortModal')">
                                <div class="sort-header-content">
                                    POINTS <?php if($sort=='points') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort" style="color:#ccc; font-size:11px;"></i>'; ?>
                                </div>
                            </th>
                            <th style="padding: 15px;" class="center-header" onclick="openModal('statusSortModal')">
                                <div class="sort-header-content">
                                    STATUS <?php if($sort=='status') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort" style="color:#ccc; font-size:11px;"></i>'; ?>
                                </div>
                            </th> 
                            <th style="padding: 15px; text-align:center;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($orders) > 0): ?>
                            <?php foreach($orders as $order): ?>
                                <tr style="border-bottom: 1px solid #f9f9f9;">
                                    <td style="padding: 15px; vertical-align: top;">
                                        <strong>#<?php echo $order['Redemption_ID']; ?></strong>
                                        <div class="order-meta">
                                            <i class="far fa-calendar"></i> 
                                            <?php 
                                                $date = isset($order['Redemption_Created_At']) ? $order['Redemption_Created_At'] : $order['Redemption_Updated_At'];
                                                echo date('d M Y', strtotime($date)); 
                                            ?>
                                        </div>
                                        <?php if(!empty($order['Redemption_TrackingNumber'])): ?>
                                            <div style="font-size: 11px; color: #17a2b8; margin-top: 4px;">
                                                <i class="fas fa-shipping-fast"></i> <?php echo htmlspecialchars($order['Redemption_TrackingNumber']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 15px; vertical-align: top;">
                                        <div class="item-preview">
                                            <?php $img = !empty($order['Reward_PhotoPath']) ? 'uploads/rewards/' . $order['Reward_PhotoPath'] : 'uploads/rewards/default.jpg'; ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" class="item-thumb" alt="Item" onclick="openLightbox('<?php echo htmlspecialchars($img); ?>')">
                                            <div>
                                                <div style="font-weight: 500; font-size: 14px;"><?php echo htmlspecialchars($order['Reward_ItemName']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; vertical-align: top; text-align:center;"> 
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($order['Donor_Name']); ?></div>
                                        <div style="font-size: 12px; color: #888;"><?php echo htmlspecialchars($order['Donor_Email']); ?></div>
                                    </td>
                                    <td style="padding: 15px; vertical-align: top; text-align:center;">
                                        <?php echo $order['Redemption_Quantity']; ?>
                                    </td>
                                    <td style="padding: 15px; vertical-align: top; color: #dc3545; font-weight: bold;">
                                        -<?php echo $order['Redemption_PointsSpent']; ?> pts
                                    </td>
                                    <td style="padding: 15px; vertical-align: top; text-align:center;"> 
                                        <?php 
                                            $s = $order['Redemption_Status'];
                                            $class = 'status-pending'; // Default Yellow
                                            if($s == 'Shipped') $class = 'status-shipped';
                                            if($s == 'Completed') $class = 'status-completed';
                                            if($s == 'Cancelled') $class = 'status-cancelled';
                                        ?>
                                        <span class="status-badge <?php echo $class; ?>"><?php echo $s; ?></span>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <div class="action-cell">
                                            <div class="action-menu">
                                                <button class="menu-btn" onclick="toggleMenu(event, <?php echo $order['Redemption_ID']; ?>)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div id="menu-<?php echo $order['Redemption_ID']; ?>" class="dropdown-content">
                                                    <?php 
                                                    // Fix for JS syntax errors with quotes in data
                                                    $orderJson = htmlspecialchars(json_encode($order), ENT_QUOTES, 'UTF-8'); 
                                                    
                                                    // Only show Process/Manage for 'Pending'
                                                    $statusCheck = trim($order['Redemption_Status']);
                                                    $isPending = (strcasecmp($statusCheck, 'Pending') === 0);
                                                    
                                                    if ($isPending) {
                                                        echo "<div onclick='openManageModal($orderJson)'><i class='fas fa-tasks'></i> Process / Manage Order</div>";
                                                        echo "<a href='admin_redemption_details.php?id=" . $order['Redemption_ID'] . "' target='_blank'><i class='fas fa-info-circle'></i> View Full Details</a>";
                                                    } else {
                                                        // Non-Pending: Only View Details (New Page)
                                                        echo "<a href='admin_redemption_details.php?id=" . $order['Redemption_ID'] . "' target='_blank'><i class='fas fa-eye'></i> View Order Details</a>";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center; padding: 30px; color: #888;">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        $queryParams = $_GET; unset($queryParams['page']);
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

    <div id="idSortModal" class="sort-modal" onclick="closeModal(event, 'idSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Date</h3><span class="sort-close" onclick="document.getElementById('idSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'id', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Oldest First</a>
            <a href="<?php echo buildUrl(['sort'=>'id', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> Newest First</a>
            
            <div class="filter-section-title">Filter by Specific Date</div>
            <form action="redemption_order_management.php" method="GET" class="range-form">
                <?php echo getHiddenInputs(['header_filter_date', 'header_filter_year', 'header_filter_month']); ?>
                <div class="range-inputs">
                    <input type="date" name="header_filter_date" class="range-input" value="<?php echo isset($_GET['header_filter_date']) ? $_GET['header_filter_date'] : ''; ?>">
                    <button type="submit" class="go-btn">Go</button>
                </div>
            </form>

            <div class="filter-section-title">Filter by Month & Year</div>
            <form action="redemption_order_management.php" method="GET" class="range-form">
                <?php echo getHiddenInputs(['header_filter_date', 'header_filter_year', 'header_filter_month']); ?>
                <div class="range-inputs" style="flex-direction:column; gap:5px;">
                    <select name="header_filter_year" class="range-input">
                        <option value="">All Years</option>
                        <?php foreach($years as $yr) echo "<option value='$yr'".(isset($_GET['header_filter_year']) && $_GET['header_filter_year']==$yr ? ' selected' : '').">$yr</option>"; ?>
                    </select>
                    <select name="header_filter_month" class="range-input">
                        <option value="">All Months</option>
                        <?php 
                        for($m=1; $m<=12; $m++) {
                            $mVal = str_pad($m, 2, "0", STR_PAD_LEFT);
                            $mName = date("F", mktime(0, 0, 0, $m, 10));
                            echo "<option value='$mVal'".(isset($_GET['header_filter_month']) && $_GET['header_filter_month']==$mVal ? ' selected' : '').">$mName</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="range-submit">Apply Date Filter</button>
            </form>
        </div>
    </div>

    <div id="itemSortModal" class="sort-modal" onclick="closeModal(event, 'itemSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Item Name</h3><span class="sort-close" onclick="document.getElementById('itemSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'item', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Name (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'item', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Name (Z - A)</a>
        </div>
    </div>

    <div id="donorSortModal" class="sort-modal" onclick="closeModal(event, 'donorSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Donor Name</h3><span class="sort-close" onclick="document.getElementById('donorSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'donor', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Name (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'donor', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Name (Z - A)</a>
        </div>
    </div>

    <div id="qtySortModal" class="sort-modal" onclick="closeModal(event, 'qtySortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Quantity</h3><span class="sort-close" onclick="document.getElementById('qtySortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'qty', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-up"></i> Low to High</a>
            <a href="<?php echo buildUrl(['sort'=>'qty', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-down"></i> High to Low</a>
            
            <div class="filter-section-title">Filter by Range</div>
            <form action="redemption_order_management.php" method="GET" class="range-form">
                <?php echo getHiddenInputs(['header_filter_qty_min', 'header_filter_qty_max']); ?>
                <div class="range-inputs">
                    <input type="number" name="header_filter_qty_min" class="range-input" placeholder="Min" min="1" value="<?php echo isset($_GET['header_filter_qty_min']) ? htmlspecialchars($_GET['header_filter_qty_min']) : ''; ?>">
                    <input type="number" name="header_filter_qty_max" class="range-input" placeholder="Max" min="1" value="<?php echo isset($_GET['header_filter_qty_max']) ? htmlspecialchars($_GET['header_filter_qty_max']) : ''; ?>">
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
            
            <div class="filter-section-title">Filter by Points Range</div>
            <form action="redemption_order_management.php" method="GET" class="range-form">
                <?php echo getHiddenInputs(['header_filter_points_min', 'header_filter_points_max']); ?>
                <div class="range-inputs">
                    <input type="number" name="header_filter_points_min" class="range-input" placeholder="Min" min="0" value="<?php echo isset($_GET['header_filter_points_min']) ? htmlspecialchars($_GET['header_filter_points_min']) : ''; ?>">
                    <input type="number" name="header_filter_points_max" class="range-input" placeholder="Max" min="0" value="<?php echo isset($_GET['header_filter_points_max']) ? htmlspecialchars($_GET['header_filter_points_max']) : ''; ?>">
                </div>
                <button type="submit" class="range-submit">Apply Filter</button>
            </form>
        </div>
    </div>

    <div id="statusSortModal" class="sort-modal" onclick="closeModal(event, 'statusSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Status</h3><span class="sort-close" onclick="document.getElementById('statusSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'status', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Status (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'status', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Status (Z - A)</a>
            
            <div class="filter-section-title">Filter by Status</div>
            <a href="<?php echo buildUrl(['header_filter_status'=>'Pending']); ?>" class="status-btn"><span style="background:#ffc107;"></span> Pending</a>
            <a href="<?php echo buildUrl(['header_filter_status'=>'Shipped']); ?>" class="status-btn"><span style="background:#17a2b8;"></span> Shipped</a>
            <a href="<?php echo buildUrl(['header_filter_status'=>'Cancelled']); ?>" class="status-btn"><span style="background:#dc3545;"></span> Cancelled</a>
            <a href="<?php echo buildUrl(['header_filter_status'=>'Completed']); ?>" class="status-btn"><span style="background:#28a745;"></span> Completed</a>
        </div>
    </div>

    <div class="modal" id="manageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manage Order #<span id="manageOrderId"></span></h2>
                <button onclick="document.getElementById('manageModal').style.display='none'" style="border:none; background:none; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            
            <div class="modal-body">
                <form method="POST" action="redemption_order_management.php" id="manageForm">
                    <input type="hidden" name="action" value="update_order">
                    <input type="hidden" name="redemption_id" id="mFormId">
                    <input type="hidden" name="estimated_days" id="hiddenEstDays" value="3">

                    <div class="item-preview-row">
                        <div style="position:relative;">
                            <img id="mItemImg" src="" style="width:60px; height:60px; object-fit:cover; border-radius:5px; cursor:pointer;" onclick="lightboxFromManage()">
                        </div>
                        <div>
                            <div id="mItemName" style="font-weight:600;"></div>
                            <div id="mQty" style="font-size:13px; color:#555;"></div>
                            <div style="font-size:12px; color:#dc3545;">Total Points Used: <span id="mPoints" style="font-weight:bold;"></span></div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h3 style="font-size:15px; margin:0;">Shipping & Contact Info</h3>
                        <button type="button" class="btn btn-success" id="btnEditInfo" onclick="toggleEditInfo(true)" style="padding:5px 10px; font-size:12px;"><i class="fas fa-edit"></i> Edit Info</button>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Number (+60)</label>
                        <input type="text" name="contact" id="mContact" class="form-input" readonly>
                    </div>
                    <div class="form-group"><label class="form-label">Address 1</label><input type="text" name="address1" id="mAddr1" class="form-input" readonly></div>
                    <div class="form-group"><label class="form-label">Address 2</label><input type="text" name="address2" id="mAddr2" class="form-input" readonly></div>
                    <div class="form-group"><label class="form-label">Address 3</label><input type="text" name="address3" id="mAddr3" class="form-input" readonly></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">City</label><input type="text" name="city" id="mCity" class="form-input" readonly></div>
                        <div class="form-group"><label class="form-label">Postal Code</label><input type="text" name="postal_code" id="mPostal" class="form-input" readonly></div>
                    </div>
                    <div class="form-group"><label class="form-label">State</label><input type="text" name="state" id="mState" class="form-input" readonly></div>

                    <div class="ai-panel">
                        <div class="ai-data">
                            <div class="ai-metric"><span>Courier</span><strong id="aiCourier">J&T Express</strong></div>
                            <div class="ai-metric"><span>Est. Delivery</span><strong id="aiTime">...</strong></div>
                        </div>
                    </div>
                    
                    <h3 style="margin-top:20px; font-size:15px;">Update Status</h3>
                    <div class="form-group">
                        <label class="form-label">Current Status</label>
                        <select name="new_status" id="mStatusSelect" class="form-select" onchange="toggleTracking()">
                            <option value="Pending">Pending</option>
                            <option value="Shipped">Shipped (Approve)</option>
                            <option value="Cancelled">Cancelled (Reject)</option>
                        </select>
                    </div>

                    <div class="form-group" id="trackingGroup" style="display:none;">
                        <label class="form-label">Tracking Number (Required for Shipping)</label>
                        <input type="text" name="tracking_number" id="mTracking" class="form-input" placeholder="e.g. JNT-123456789">
                    </div>

                    <div class="form-group" id="cancelReasonGroup" style="display:none;">
                        <label class="form-label">Cancellation Reason <span style="color:red">*</span></label>
                        <textarea name="cancel_reason" id="mCancelReason" class="form-textarea" rows="3" placeholder="Explain why this order is rejected/cancelled..."></textarea>
                    </div>

                    <div class="action-buttons" id="actionButtonsGroup">
                        <button type="button" class="btn-reject" onclick="setStatus('Cancelled')"><i class="fas fa-times"></i> Reject & Refund</button>
                        <button type="button" class="btn-approve" onclick="setStatus('Shipped')"><i class="fas fa-check"></i> Approve & Ship</button>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btnSave" style="display:none; width:100%; margin-top:10px;">Save Changes</button>
                    
                    <p style="font-size:11px; color:#666; text-align:center; margin-top:10px;">* Email notification will be sent automatically.</p>
                </form>
            </div>
        </div>
    </div>

    <div id="imageLightbox" class="lightbox-modal">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <img class="lightbox-content" id="lightboxImage">
    </div>

    <script>
        // Init Select2
        $(document).ready(function() {
            toggleFilterInputs();
            checkAlerts();
            
            // Close sort popups when clicking elsewhere
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.sort-header').length) {
                    // This is handled by openModal logic now
                }
            });
        });

        // Filter Logic
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            
            if(type) {
                const target = document.getElementById('filter_' + type);
                if(target) {
                    target.classList.add('active');
                    target.querySelector('select').disabled = false;
                }
            }
        }
        
        // Sorting Modal Toggle
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(event, id) {
            if (event.target.id === id) {
                document.getElementById(id).style.display = 'none';
            }
        }

        // Lightbox
        function openLightbox(src) {
            document.getElementById('lightboxImage').src = src;
            document.getElementById('imageLightbox').style.display = 'flex';
        }
        function closeLightbox() {
            document.getElementById('imageLightbox').style.display = 'none';
        }
        function lightboxFromManage() {
            const src = document.getElementById('mItemImg').src;
            openLightbox(src);
        }
        
        function openManageModal(order) {
            document.getElementById('manageOrderId').innerText = order.Redemption_ID;
            document.getElementById('mFormId').value = order.Redemption_ID;
            document.getElementById('mContact').value = order.Redemption_ContactNumber.replace('+60', '');
            
            let addr = order.Redemption_Address1;
            document.getElementById('mAddr1').value = order.Redemption_Address1;
            document.getElementById('mAddr2').value = order.Redemption_Address2;
            document.getElementById('mAddr3').value = order.Redemption_Address3;
            document.getElementById('mCity').value = order.Redemption_City;
            document.getElementById('mPostal').value = order.Redemption_PostalCode;
            document.getElementById('mState').value = order.Redemption_State;

            document.getElementById('mItemName').innerText = order.Reward_ItemName;
            document.getElementById('mQty').innerText = "Qty: " + order.Redemption_Quantity;
            document.getElementById('mPoints').innerText = order.Redemption_PointsSpent;
            document.getElementById('mItemImg').src = order.Reward_PhotoPath ? 'uploads/rewards/' + order.Reward_PhotoPath : 'uploads/rewards/default.jpg';
            
            // --- Update Logistics Info (No AI Distance) ---
            updateLogisticsInfo(order.Redemption_State);

            // --- Status Handling (Processing -> Pending) ---
            let currentStatus = order.Redemption_Status.trim();
            if (currentStatus === 'Processing') {
                currentStatus = 'Pending';
            }
            document.getElementById('mStatusSelect').value = currentStatus;
            
            document.getElementById('mTracking').value = order.Redemption_TrackingNumber || '';
            document.getElementById('mCancelReason').value = order.Redemption_CancelReason || '';
            
            toggleTracking(); 
            toggleEditInfo(false); // Reset edit state

            // LOGIC: Disable management if not Pending
            const statusSelect = document.getElementById('mStatusSelect');
            const actionBtns = document.getElementById('actionButtonsGroup');
            const editBtn = document.getElementById('btnEditInfo');
            const trackingInput = document.getElementById('mTracking');
            const reasonInput = document.getElementById('mCancelReason');

            // Only 'Pending' is editable. (Processing treated as Pending)
            const isPending = (currentStatus === 'Pending');

            if (!isPending) {
                // View Only Mode
                statusSelect.disabled = true;
                actionBtns.style.display = 'none';
                editBtn.style.display = 'none';
                trackingInput.readOnly = true;
                reasonInput.readOnly = true;
                
                // Show cancel reason field if cancelled
                if (currentStatus === 'Cancelled') {
                    document.getElementById('cancelReasonGroup').style.display = 'block';
                } else {
                    document.getElementById('cancelReasonGroup').style.display = 'none';
                }
            } else {
                // Edit Mode (Pending)
                statusSelect.disabled = false;
                actionBtns.style.display = 'flex';
                editBtn.style.display = 'block';
                trackingInput.readOnly = false;
                reasonInput.readOnly = false;
                document.getElementById('cancelReasonGroup').style.display = 'none';
            }

            document.getElementById('manageModal').style.display = 'flex';
        }

        // Edit Info Logic
        function toggleEditInfo(enable) {
            const ids = ['mContact', 'mAddr1', 'mAddr2', 'mAddr3', 'mCity', 'mPostal', 'mState'];
            const btnSave = document.getElementById('btnSave');
            
            ids.forEach(id => {
                const el = document.getElementById(id);
                if(enable) {
                    el.removeAttribute('readonly');
                    el.style.backgroundColor = 'white';
                    el.style.border = '1px solid #F28585';
                } else {
                    el.setAttribute('readonly', true);
                    el.style.backgroundColor = '#e9ecef';
                    el.style.border = '1px solid #ddd';
                }
            });

            if(enable) {
                btnSave.style.display = 'block';
            } else {
                btnSave.style.display = 'none';
            }
        }

        function setStatus(status) {
            document.getElementById('mStatusSelect').value = status;
            toggleTracking(); // To show/hide fields based on selection

            // Validation for Shipping
            if(status === 'Shipped' && document.getElementById('mTracking').value.trim() === '') {
                Swal.fire('Tracking Required', 'Please enter a tracking number.', 'warning');
                return;
            }

            // Validation for Cancellation Reason
            if(status === 'Cancelled' && document.getElementById('mCancelReason').value.trim() === '') {
                Swal.fire('Reason Required', 'Please enter a reason for cancellation.', 'warning');
                return;
            }

            let title = status === 'Cancelled' ? 'Reject & Refund?' : 'Approve & Ship?';
            let text = status === 'Cancelled' ? 'Points will be refunded and donor notified.' : 'Donor will be notified of shipment.';
            let confirmColor = status === 'Cancelled' ? '#dc3545' : '#28a745';

            Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: confirmColor,
                confirmButtonText: 'Yes, Confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('manageForm').submit();
                }
            });
        }

        function toggleTracking() {
            const val = document.getElementById('mStatusSelect').value;
            const trackGrp = document.getElementById('trackingGroup');
            const reasonGrp = document.getElementById('cancelReasonGroup');
            
            // Logic for fields visibility
            if(val === 'Shipped') {
                trackGrp.style.display = 'block';
                reasonGrp.style.display = 'none';
            } else if (val === 'Cancelled') {
                trackGrp.style.display = 'none';
                reasonGrp.style.display = 'block';
            } else {
                trackGrp.style.display = 'none';
                reasonGrp.style.display = 'none';
            }
        }

        function toggleMenu(e, id) { 
            e.stopPropagation(); 
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); 
            const menu = document.getElementById('menu-' + id); 
            if(menu) menu.style.display = 'block'; 
        }

        window.onclick = function(e) {
            if (!e.target.matches('.menu-btn') && !e.target.matches('.menu-btn *')) { 
                document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); 
            }
            if(e.target == document.getElementById('manageModal')) document.getElementById('manageModal').style.display = 'none';
            if(e.target.id == 'imageLightbox') closeLightbox();
        }
        
        // --- Updated Logistics Logic ---
        function updateLogisticsInfo(donorState) {
            let courier = "J&T Express"; 
            let time = "2-3 Days"; 
            let daysInt = 3;

            // Simple check for East Malaysia states
            const eastMalaysia = ['Sabah', 'Sarawak', 'Labuan'];
            if (eastMalaysia.includes(donorState)) {
                time = "5-6 Days";
                daysInt = 6;
            } else {
                time = "2-3 Days";
                daysInt = 3;
            }
            
            document.getElementById('aiCourier').innerText = courier;
            document.getElementById('aiTime').innerText = time;
            document.getElementById('hiddenEstDays').value = daysInt; 
        }

        function showSystemError(message) {
            const errorBox = document.getElementById('floatingError');
            const errorText = document.getElementById('alertErrorText');
            if(errorBox && errorText) {
                errorText.innerHTML = message; 
                errorBox.style.display = 'flex';
                setTimeout(() => { errorBox.style.display = 'none'; }, 5000);
            }
        }

        function checkAlerts() {
            const s = document.getElementById('floatingSuccess');
            const e = document.getElementById('floatingError');
            if(s && s.style.display === 'flex') setTimeout(() => { s.style.display='none'; }, 5000);
            if(e && e.style.display === 'flex') setTimeout(() => { e.style.display='none'; }, 5000);
        }
    </script>
</body>
</html>