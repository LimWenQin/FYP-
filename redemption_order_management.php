<?php
// redemption_order_management.php
session_start();

// --- 引入 PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- 0. HANDLE EXPORT TO EXCEL ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $filename = "redemption_orders_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Get Data
    $sql = "SELECT r.Redemption_ID, r.Redemption_Status, r.Redemption_PointsSpent, 
                   r.Redemption_Updated_At, r.Redemption_TrackingNumber,
                   d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber,
                   rw.Reward_ItemName, rw.Reward_Code
            FROM redemption_order r
            JOIN donor d ON r.Donor_ID = d.Donor_ID
            JOIN reward_item rw ON r.Reward_ID = rw.Reward_ID
            ORDER BY r.Redemption_ID DESC";
    $result = $conn->query($sql);

    echo '<table border="1">';
    echo '<tr>
            <th style="background-color:#f2f2f2;">Order ID</th>
            <th style="background-color:#f2f2f2;">Donor Name</th>
            <th style="background-color:#f2f2f2;">Donor Email</th>
            <th style="background-color:#f2f2f2;">Contact</th>
            <th style="background-color:#f2f2f2;">Reward Item</th>
            <th style="background-color:#f2f2f2;">Item Code</th>
            <th style="background-color:#f2f2f2;">Points Used</th>
            <th style="background-color:#f2f2f2;">Status</th>
            <th style="background-color:#f2f2f2;">Tracking No</th>
            <th style="background-color:#f2f2f2;">Last Update</th>
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
            echo '<td>' . $row['Redemption_PointsSpent'] . '</td>';
            echo '<td>' . htmlspecialchars($row['Redemption_Status']) . '</td>';
            echo '<td style="mso-number-format:\'\@\'">' . htmlspecialchars($row['Redemption_TrackingNumber']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Redemption_Updated_At']) . '</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    exit(); 
}

// --- GET CURRENT ADMIN INFO ---
$currentAdminId = $_SESSION['admin_id'];
$adminSql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
$adminResult = $conn->query($adminSql);

if ($adminResult && $adminResult->num_rows > 0) {
    $adminData = $adminResult->fetch_assoc();
    $adminName = $adminData['Admin_Name'];
    $adminPosition = $adminData['Admin_Role'];
    $adminProfilePicture = $adminData['Admin_ProfilePicture'];
} else {
    $adminName = "Admin";
    $adminPosition = "System Administrator";
    $adminProfilePicture = null;
}

// --- SEARCH & FILTER ---
$searchTerm = "";
$filterStatus = "";
$whereConditions = [];

// 1. Search (Order ID, Donor Name, Reward Name)
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereConditions[] = "(r.Redemption_ID LIKE '%$searchTerm%' 
                           OR d.Donor_Name LIKE '%$searchTerm%' 
                           OR rw.Reward_ItemName LIKE '%$searchTerm%')";
}

// 2. Filter by Status
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filterStatus = $conn->real_escape_string($_GET['status']);
    $whereConditions[] = "r.Redemption_Status = '$filterStatus'";
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// --- HELPER: EMAIL FUNCTION (PHPMAILER) ---
function sendStatusEmail($to, $name, $status, $itemName, $tracking = null) {
    $subject = "Update on your Redemption Order - Love Bridge";
    
    // 构建邮件内容
    $bodyContent = "<div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>";
    $bodyContent .= "<div style='background-color: white; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto;'>";
    $bodyContent .= "<h2 style='color: #F28585;'>Redemption Status Update</h2>";
    $bodyContent .= "<p>Dear <strong>$name</strong>,</p>";

    if ($status == 'Shipped') {
        $bodyContent .= "<p>Great news! Your redemption for <strong>'$itemName'</strong> has been approved and shipped.</p>";
        if ($tracking) {
            $bodyContent .= "<p style='font-size:16px; font-weight:bold; color:#333;'>Tracking Number: $tracking</p>";
        }
        $bodyContent .= "<p>You should receive it soon.</p>";
    } elseif ($status == 'Cancelled') {
        $bodyContent .= "<p style='color:#dc3545;'>We regret to inform you that your redemption for <strong>'$itemName'</strong> has been cancelled/rejected.</p>";
        $bodyContent .= "<p>Any points used have been fully refunded to your account.</p>";
        $bodyContent .= "<p>Please contact us if you have any questions.</p>";
    } else {
        $bodyContent .= "<p>The status of your redemption order for <strong>'$itemName'</strong> has been updated to: <strong>$status</strong>.</p>";
    }

    $bodyContent .= "<br><p>Thank you for your support,<br>Love Bridge Team</p>";
    $bodyContent .= "</div></div>";

    // PHPMailer Configuration
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
        $mail->Body    = $bodyContent;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. HANDLE ADD ORDER
    if (isset($_POST['add_order'])) {
        $donorId = intval($_POST['donor_id']);
        $rewardId = intval($_POST['reward_id']);
        
        $address1 = $conn->real_escape_string($_POST['address1']);
        $address2 = $conn->real_escape_string($_POST['address2']); 
        $address3 = $conn->real_escape_string($_POST['address3']); 
        
        $city = $conn->real_escape_string($_POST['city']);
        $state = $conn->real_escape_string($_POST['state']);
        $postal = $conn->real_escape_string($_POST['postal_code']);
        
        $contactRaw = $conn->real_escape_string($_POST['contact']);
        $contact = "+60" . $contactRaw; 

        $rwQ = $conn->query("SELECT Reward_RequiredPoint, Reward_Stock FROM reward_item WHERE Reward_ID = $rewardId");
        $rwRow = $rwQ->fetch_assoc();
        $pointsNeeded = $rwRow['Reward_RequiredPoint'];
        $currentStock = $rwRow['Reward_Stock'];

        $ptQ = $conn->query("SELECT Points_Total, Points_ID FROM point WHERE Donor_ID = $donorId");
        $ptRow = $ptQ->fetch_assoc();
        
        if ($currentStock <= 0) {
            $errorMessage = "Error: Item is out of stock.";
        } elseif (!$ptRow || $ptRow['Points_Total'] < $pointsNeeded) {
            $errorMessage = "Error: Donor does not have enough points (Has: " . ($ptRow ? $ptRow['Points_Total'] : 0) . ", Need: $pointsNeeded).";
        } else {
            $newPoints = $ptRow['Points_Total'] - $pointsNeeded;
            $conn->query("UPDATE point SET Points_Total = $newPoints, Points_Updated_At = NOW() WHERE Donor_ID = $donorId");
            $conn->query("UPDATE reward_item SET Reward_Stock = Reward_Stock - 1 WHERE Reward_ID = $rewardId");

            $sql = "INSERT INTO redemption_order (
                Donor_ID, Reward_ID, Redemption_PointsSpent, Redemption_Status, 
                Redemption_Address1, Redemption_Address2, Redemption_Address3, 
                Redemption_City, Redemption_State, Redemption_PostalCode, 
                Redemption_ContactNumber, Redemption_Updated_At
            ) VALUES (
                $donorId, $rewardId, $pointsNeeded, 'Pending',
                '$address1', '$address2', '$address3', 
                '$city', '$state', '$postal',
                '$contact', NOW()
            )";
            
            if ($conn->query($sql)) {
                $successMessage = "Order added successfully! Points deducted.";
            } else {
                $errorMessage = "Database Error: " . $conn->error;
            }
        }
        
        if (isset($successMessage)) { header("Location: redemption_order_management.php?success=" . urlencode($successMessage)); exit(); }
        if (isset($errorMessage)) { header("Location: redemption_order_management.php?error=" . urlencode($errorMessage)); exit(); }
    }

    // 2. HANDLE UPDATE STATUS
    if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
        $redemptionId = intval($_POST['redemption_id']);
        $newStatus = $_POST['new_status'];
        $tracking = isset($_POST['tracking_number']) ? $conn->real_escape_string($_POST['tracking_number']) : null;
        
        $checkSql = "SELECT r.Redemption_Status, r.Redemption_PointsSpent, r.Donor_ID, 
                            d.Donor_Email, d.Donor_Name, rw.Reward_ItemName 
                     FROM redemption_order r 
                     JOIN donor d ON r.Donor_ID = d.Donor_ID
                     JOIN reward_item rw ON r.Reward_ID = rw.Reward_ID
                     WHERE r.Redemption_ID = $redemptionId";
        $checkResult = $conn->query($checkSql);
        $orderData = $checkResult->fetch_assoc();
        $oldStatus = $orderData['Redemption_Status'];

        if ($newStatus == 'Cancelled' && $oldStatus != 'Cancelled') {
            $refundPoints = $orderData['Redemption_PointsSpent'];
            $donorId = $orderData['Donor_ID'];
            $conn->query("UPDATE point SET Points_Total = Points_Total + $refundPoints, Points_Updated_At = NOW() WHERE Donor_ID = $donorId");
        }

        $sql = "UPDATE redemption_order SET Redemption_Status = '$newStatus', Redemption_Updated_At = NOW()";
        if ($tracking) {
            $sql .= ", Redemption_TrackingNumber = '$tracking'";
        }
        $sql .= " WHERE Redemption_ID = $redemptionId";
        
        if ($conn->query($sql)) {
            sendStatusEmail($orderData['Donor_Email'], $orderData['Donor_Name'], $newStatus, $orderData['Reward_ItemName'], $tracking);

            $msg = "Order #$redemptionId updated to $newStatus.";
            if ($newStatus == 'Cancelled') $msg .= " Points refunded to donor.";
            if ($newStatus == 'Shipped') $msg .= " Email notification sent.";
            
            header("Location: redemption_order_management.php?success=" . urlencode($msg));
            exit();
        } else {
            header("Location: redemption_order_management.php?error=" . urlencode("Database error: " . $conn->error));
            exit();
        }
    }
}

// --- PAGINATION ---
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
        ORDER BY 
            CASE WHEN r.Redemption_Status = 'Pending' THEN 1 ELSE 2 END, 
            r.Redemption_Updated_At DESC
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

// --- STATS CALCULATION ---
$statsSql = "SELECT 
                COUNT(*) as TotalOrders,
                SUM(CASE WHEN Redemption_Status = 'Pending' THEN 1 ELSE 0 END) as PendingOrders,
                SUM(CASE WHEN Redemption_Status = 'Shipped' THEN 1 ELSE 0 END) as ShippedOrders,
                SUM(CASE WHEN Redemption_Status = 'Completed' THEN 1 ELSE 0 END) as CompletedOrders,
                SUM(Redemption_PointsSpent) as TotalPoints
             FROM redemption_order";
$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

// --- FETCH DATA FOR ADD MODAL ---
$donorList = [];
$dq = $conn->query("SELECT Donor_ID, Donor_Name, Donor_ICNumber, Donor_ContactNumber, Donor_Email, Donor_Address1, Donor_Address2, Donor_Address3, Donor_City, Donor_State, Donor_PostalCode FROM donor ORDER BY Donor_Name ASC");
while($d = $dq->fetch_assoc()) { $donorList[] = $d; }

$rewardList = [];
$rq = $conn->query("SELECT Reward_ID, Reward_ItemName, Reward_RequiredPoint, Reward_Stock FROM reward_item WHERE Reward_Status = 'Active' AND Reward_Stock > 0 ORDER BY Reward_ItemName ASC");
while($r = $rq->fetch_assoc()) { $rewardList[] = $r; }

// --- AI LOCATION HELPER ---
$stateCoords = [
    'Johor' => [1.9344, 103.3587],
    'Kedah' => [6.1184, 100.3685],
    'Kelantan' => [5.1500, 101.9742],
    'Kuala Lumpur' => [3.1390, 101.6869],
    'Melaka' => [2.1896, 102.2501],
    'Negeri Sembilan' => [2.7258, 101.9424],
    'Pahang' => [3.8126, 103.3256],
    'Penang' => [5.4141, 100.3288],
    'Perak' => [4.5921, 101.0901],
    'Perlis' => [6.4449, 100.2048],
    'Sabah' => [5.9788, 116.0753],
    'Sarawak' => [1.5533, 110.3592],
    'Selangor' => [3.0738, 101.5183],
    'Terengganu' => [5.3117, 103.1324],
    'Putrajaya' => [2.9264, 101.6964],
    'Labuan' => [5.2831, 115.2308]
];

$malaysiaStates = array_keys($stateCoords);

// --- FETCH HEADQUARTERS STATE (SAFE MODE) ---
// I added a Try-Catch block here to prevent Fatal Errors if the column is missing
$hqState = "Kuala Lumpur"; // Default fallback
try {
    // Check if table column exists first to be extra safe, or just try querying
    $hqSql = "SELECT Headquarters_State FROM headquarters LIMIT 1";
    $hqResult = $conn->query($hqSql);
    
    if ($hqResult && $hqResult->num_rows > 0) {
        $row = $hqResult->fetch_assoc();
        if (!empty($row['Headquarters_State'])) {
            $hqState = $row['Headquarters_State'];
        }
    }
} catch (mysqli_sql_exception $e) {
    // If the column doesn't exist, we catch the error here and do nothing.
    // The system will just use the default "Kuala Lumpur" defined above.
    // You should still run the SQL command to fix the database permanently.
} catch (Exception $e) {
    // Catch generic errors
}

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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* --- 1. STATS CARDS --- */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 28px; font-weight: 600; margin-bottom: 5px; }
        .stat-desc { font-size: 12px; color: #28a745; display: flex; align-items: center; gap: 5px; font-weight: 500;}
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        .stat-card:nth-child(1) .stat-icon { background: rgba(255, 193, 7, 0.2); color: #ffc107; } /* Pending */
        .stat-card:nth-child(2) .stat-icon { background: rgba(23, 162, 184, 0.2); color: #17a2b8; } /* Shipped */
        .stat-card:nth-child(3) .stat-icon { background: rgba(40, 167, 69, 0.2); color: #28a745; } /* Completed - Green */
        .stat-card:nth-child(4) .stat-icon { background: rgba(220, 53, 69, 0.2); color: #dc3545; } /* Total Points - Red */

        /* --- 2. LIST SECTION STYLES --- */
        .order-management { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-header h2 { font-size: 18px; font-weight: 600; color: #333; }
        .header-buttons { display: flex; gap: 10px; }
        .btn { padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; color: white; transition: 0.3s; font-size: 14px; text-decoration: none; }
        .btn-primary { background: #F28585; }
        .btn-success { background: #28a745; }
        
        .btn-light-pending { 
            background: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeeba;
        }
        .btn-light-pending:hover {
            background: #ffeeba;
        }

        /* --- 3. PAGINATION STYLES --- */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-info { font-size: 13px; color: #666; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; display: inline-block; margin-left: 5px;}
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; border-color: #eee; }

        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-shipped { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .item-preview { display: flex; align-items: center; gap: 10px; }
        .item-thumb { width: 50px; height: 50px; border-radius: 5px; object-fit: cover; border: 1px solid #eee; }
        .order-meta { font-size: 12px; color: #666; margin-top: 5px; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; border-radius: 10px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee; }
        .modal-body { padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; }
        .form-input, .form-select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }

        .ai-panel { background: #f0f8ff; border: 1px solid #cce5ff; border-radius: 8px; padding: 15px; margin-top: 15px; position: relative; overflow: hidden; }
        .ai-title { color: #004085; font-weight: bold; font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .ai-data { display: flex; gap: 20px; }
        .ai-metric { flex: 1; }
        .ai-metric span { font-size: 11px; color: #666; display: block; }
        .ai-metric strong { font-size: 16px; color: #333; }
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .btn-approve { flex: 1; background: #28a745; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-reject { flex: 1; background: #dc3545; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .info-box label { font-size: 11px; color: #888; display: block; margin-bottom: 3px; }
        .info-box div { font-size: 14px; font-weight: 500; color: #333; }
        .full-width { grid-column: span 2; }

        .select2-container .select2-selection--single {
            height: 42px !important;
            border: 1px solid #ddd !important;
            border-radius: 5px !important;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
        }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: #F28585 !important;
            color: white !important;
        }

        .register-hint {
            margin-top: 5px;
            padding: 10px;
            background-color: #f0f8ff;
            border-left: 3px solid #17a2b8;
            font-size: 13px;
            color: #555;
            border-radius: 4px;
        }
        .register-hint a {
            color: #F28585; 
            font-weight: 700;
            text-decoration: underline;
            margin-left: 5px;
        }
        .register-hint a:hover { color: #d65f5f; }

        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid #ddd; border-right: none; border-radius: 5px 0 0 5px; color: #666; font-weight: bold; font-size: 14px; }
        .phone-input { border-radius: 0 5px 5px 0 !important; }
    </style>
</head>
<body>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="floating-alert floating-alert-success" id="floatingSuccess" style="position: fixed; top: 20px; right: 20px; padding: 15px 20px; background: white; border-left: 4px solid var(--success); box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px; z-index: 1100; color: var(--success);">
            <i class="fas fa-check-circle"></i>
            <div><?php echo htmlspecialchars($_GET['success']); ?></div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="floating-alert floating-alert-danger" id="floatingError" style="position: fixed; top: 20px; right: 20px; padding: 15px 20px; background: white; border-left: 4px solid var(--danger); box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px; z-index: 1100; color: var(--danger);">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php echo htmlspecialchars($_GET['error']); ?></div>
        </div>
    <?php endif; ?>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Redemption Orders</h1>
                <p>Manage reward redemptions, approve requests, and track shipping.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>PENDING REQUESTS</h3>
                        <h2><?php echo (int)$stats['PendingOrders']; ?></h2>
                        <p class="stat-desc"><i class="fas fa-hourglass-half"></i> Awaiting processing</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>SHIPPED ORDERS</h3>
                        <h2><?php echo (int)$stats['ShippedOrders']; ?></h2>
                        <p class="stat-desc"><i class="fas fa-truck"></i> On the way</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-shipping-fast"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>COMPLETED ORDERS</h3>
                        <h2><?php echo (int)$stats['CompletedOrders']; ?></h2>
                        <p class="stat-desc"><i class="fas fa-check-circle"></i> Successfully delivered</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-box-open"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>POINTS REDEEMED</h3>
                        <h2><?php echo number_format((int)$stats['TotalPoints']); ?></h2>
                        <p class="stat-desc"><i class="fas fa-exchange-alt"></i> Total value exchanged</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                </div>
            </div>

            <div class="order-management">
                <div class="section-header">
                    <h2>Order List</h2>
                    <div class="header-buttons">
                        <a href="redemption_order_management.php?status=Pending" class="btn btn-light-pending">
                            <i class="fas fa-filter"></i> Show Pending Only
                        </a>
                        <button class="btn btn-primary" onclick="openAddOrderModal()"><i class="fas fa-plus"></i> Add Redemption</button>
                        <a href="redemption_order_management.php?export=excel" class="btn btn-success"><i class="fas fa-download"></i> Export Data</a>
                    </div>
                </div>

                <form method="GET" style="display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; background: #f8f9fa; padding: 10px; border-radius: 8px;">
                    <select name="status" onchange="this.form.submit()" class="form-select" style="max-width: 150px;">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo $filterStatus == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Shipped" <?php echo $filterStatus == 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="Completed" <?php echo $filterStatus == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $filterStatus == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <input type="text" name="search" placeholder="Search Order ID, Donor or Item..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="form-input" style="flex: 1;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($searchTerm) || !empty($filterStatus)): ?>
                        <a href="redemption_order_management.php" class="btn btn-danger" style="background:#dc3545; color:white; padding:10px 15px; border-radius:5px;"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>

                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f0f0f0;">
                            <th style="text-align: left; padding: 15px; color: #888; font-size: 13px;">ORDER INFO</th>
                            <th style="text-align: left; padding: 15px; color: #888; font-size: 13px;">ITEM REDEEMED</th>
                            <th style="text-align: left; padding: 15px; color: #888; font-size: 13px;">DONOR</th>
                            <th style="text-align: left; padding: 15px; color: #888; font-size: 13px;">STATUS</th>
                            <th style="text-align: center; padding: 15px; color: #888; font-size: 13px;">ACTION</th>
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
                                            <?php $img = !empty($order['Reward_PhotoPath']) ? $order['Reward_PhotoPath'] : 'uploads/rewards/default.jpg'; ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" class="item-thumb" alt="Item">
                                            <div>
                                                <div style="font-weight: 500; font-size: 14px;"><?php echo htmlspecialchars($order['Reward_ItemName']); ?></div>
                                                <div style="font-size: 12px; color: #dc3545; font-weight: bold;">-<?php echo $order['Redemption_PointsSpent']; ?> pts</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; vertical-align: top;">
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($order['Donor_Name']); ?></div>
                                        <div style="font-size: 12px; color: #888;"><?php echo htmlspecialchars($order['Donor_Email']); ?></div>
                                    </td>
                                    <td style="padding: 15px; vertical-align: top;">
                                        <?php 
                                            $s = $order['Redemption_Status'];
                                            $class = 'status-pending';
                                            if($s == 'Shipped') $class = 'status-shipped';
                                            if($s == 'Completed') $class = 'status-completed';
                                            if($s == 'Cancelled') $class = 'status-cancelled';
                                        ?>
                                        <span class="status-badge <?php echo $class; ?>"><?php echo $s; ?></span>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <button onclick='openManageModal(<?php echo json_encode($order); ?>)' style="background: none; border: 1px solid #ddd; padding: 6px 12px; border-radius: 5px; cursor: pointer; color: #555; transition: 0.2s;">
                                            <i class="fas fa-tasks"></i> Manage
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px; color: #888;">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        // Build pagination URL parameters
                        $queryParams = [];
                        if(!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if(!empty($filterStatus)) $queryParams['status'] = $filterStatus;
                        
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

    <div class="modal" id="addOrderModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add Redemption Order</h2><button onclick="closeModal('addOrderModal')" style="border:none; background:none; font-size:24px; cursor:pointer;">&times;</button></div>
            <div class="modal-body">
                <form method="POST" action="redemption_order_management.php">
                    <input type="hidden" name="add_order" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Select Donor <span style="color:red">*</span></label>
                        <select name="donor_id" class="form-select" required id="add_donor_select" style="width: 100%;">
                            <option value="">-- Choose Donor --</option>
                            <?php foreach($donorList as $d): ?>
                                <option value="<?php echo $d['Donor_ID']; ?>" 
                                        data-contact="<?php echo htmlspecialchars($d['Donor_ContactNumber']); ?>"
                                        data-address1="<?php echo htmlspecialchars($d['Donor_Address1']); ?>"
                                        data-address2="<?php echo htmlspecialchars($d['Donor_Address2']); ?>"
                                        data-address3="<?php echo htmlspecialchars($d['Donor_Address3']); ?>"
                                        data-city="<?php echo htmlspecialchars($d['Donor_City']); ?>"
                                        data-state="<?php echo htmlspecialchars($d['Donor_State']); ?>"
                                        data-postal="<?php echo htmlspecialchars($d['Donor_PostalCode']); ?>"
                                        >
                                    <?php echo htmlspecialchars($d['Donor_Name']) . " (" . $d['Donor_ICNumber'] . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="register-hint">
                            <i class="fas fa-info-circle"></i> 
                            Donor doesn't have an account? 
                            <a href="admin_donor_page.php">Register Donor Here</a>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Select Reward Item <span style="color:red">*</span></label>
                        <select name="reward_id" class="form-select" required>
                            <option value="">-- Choose Reward --</option>
                            <?php foreach($rewardList as $r): ?>
                                <option value="<?php echo $r['Reward_ID']; ?>">
                                    <?php echo htmlspecialchars($r['Reward_ItemName']) . " - " . $r['Reward_RequiredPoint'] . " pts (Stock: " . $r['Reward_Stock'] . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-guide">Select the reward item the donor wishes to redeem.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <div class="phone-format">
                            <span class="phone-prefix">+60</span>
                            <input type="text" name="contact" id="add_contact" class="form-input phone-input" required placeholder="12-3456789" maxlength="11">
                        </div>
                        <span class="form-guide">Format: 12-3456789 or 11-12345678 (Auto-filled).</span>
                    </div>

                    <div style="border-top:1px solid #eee; margin:15px 0; padding-top:10px;">
                        
                        <div class="form-group">
                            <label class="form-label">Address Line 1</label>
                            <input type="text" name="address1" class="form-input" required placeholder="e.g. No. 123, Jalan Example">
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

                        <div style="display:flex; gap:10px;">
                            <div class="form-group" style="flex:1">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="postal_code" id="add_postal_code" class="form-input" required placeholder="e.g. 50000">
                            </div>
                            <div class="form-group" style="flex:1">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">State</label>
                            <select name="state" id="add_state_select" class="form-select" required>
                                <option value="">Select State</option>
                                <?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Create Order</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="manageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manage Order #<span id="manageOrderId"></span></h2>
                <button onclick="closeModal('manageModal')" style="border:none; background:none; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="info-grid">
                    <div class="info-box"><label>Donor Name</label><div id="mDonorName"></div></div>
                    <div class="info-box"><label>Contact</label><div id="mContact"></div></div>
                    <div class="info-box full-width"><label>Address</label><div id="mAddress"></div></div>
                </div>

                <div class="item-preview" style="background:#fafafa; padding:10px; border-radius:8px; border:1px solid #eee;">
                    <img id="mItemImg" src="" style="width:60px; height:60px; object-fit:cover; border-radius:5px;">
                    <div>
                        <div id="mItemName" style="font-weight:600;"></div>
                        <div style="font-size:13px; color:#dc3545;">Points Used: <span id="mPoints"></span></div>
                    </div>
                </div>

                <div class="ai-panel">
                    <div class="ai-title"><i class="fas fa-robot"></i> AI Logistics Intelligence</div>
                    <div class="ai-data">
                        <div class="ai-metric">
                            <span>Estimated Distance</span>
                            <strong id="aiDistance">Calculating...</strong>
                        </div>
                        <div class="ai-metric">
                            <span>Recommended Courier</span>
                            <strong id="aiCourier">Analyzing...</strong>
                        </div>
                        <div class="ai-metric">
                            <span>Est. Delivery Days</span>
                            <strong id="aiTime">...</strong>
                        </div>
                    </div>
                    <div class="ai-map-hint">Distance calculated from Headquarters (<?php echo htmlspecialchars($hqState); ?>) to Donor's State location.</div>
                </div>

                <form method="POST" action="redemption_order_management.php" id="manageForm">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="redemption_id" id="mFormId">
                    
                    <h3 style="margin-top:20px;">Update Status</h3>
                    <div class="form-group">
                        <label class="form-label">Current Status</label>
                        <select name="new_status" id="mStatusSelect" class="form-select" onchange="toggleTracking()">
                            <option value="Pending">Pending</option>
                            <option value="Shipped">Shipped (Approve)</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled (Reject)</option>
                        </select>
                    </div>

                    <div class="form-group" id="trackingGroup" style="display:none;">
                        <label class="form-label">Tracking Number (Required for Shipping)</label>
                        <input type="text" name="tracking_number" id="mTracking" class="form-input" placeholder="e.g. JNT-123456789">
                    </div>

                    <div class="action-buttons">
                        <button type="button" class="btn-reject" onclick="setStatus('Cancelled')"><i class="fas fa-times"></i> Reject & Refund</button>
                        <button type="button" class="btn-approve" onclick="setStatus('Shipped')"><i class="fas fa-check"></i> Approve & Ship</button>
                    </div>
                    <p style="font-size:11px; color:#666; text-align:center; margin-top:10px;">* Email notification will be sent automatically upon Approval or Rejection.</p>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize Select2 and Auto-fill Logic
        $(document).ready(function() {
            $('#add_donor_select').select2({
                placeholder: "-- Choose Donor --",
                allowClear: true,
                dropdownParent: $('#addOrderModal') 
            });

            $('#add_donor_select').on('change', function() {
                var selected = $(this).find(':selected');
                
                var rawContact = selected.data('contact');
                var addr1 = selected.data('address1');
                var addr2 = selected.data('address2');
                var addr3 = selected.data('address3');
                var city = selected.data('city');
                var state = selected.data('state');
                var postal = selected.data('postal');

                $('input[name="address1"]').val(addr1);
                $('input[name="address2"]').val(addr2);
                $('input[name="address3"]').val(addr3);
                $('input[name="city"]').val(city);
                $('input[name="postal_code"]').val(postal);
                
                if (rawContact && rawContact.startsWith('+60')) {
                    rawContact = rawContact.substring(3);
                }
                $('#add_contact').val(rawContact);
                
                let event = new Event('input', { bubbles: true });
                document.getElementById('add_contact').dispatchEvent(event);

                if(state) {
                    $('select[name="state"]').val(state).change();
                }
            });

            setupPhoneInput('add_contact');
            setupPostcodeState('add_postal_code', 'add_state_select');
        });

        // AI DATA: PHP Array to JS Object
        const stateCoords = <?php echo json_encode($stateCoords); ?>;
        const currentHqState = "<?php echo htmlspecialchars($hqState); ?>";
        
        function openAddOrderModal() { document.getElementById('addOrderModal').style.display = 'flex'; }
        
        function openManageModal(order) {
            document.getElementById('manageOrderId').innerText = order.Redemption_ID;
            document.getElementById('mFormId').value = order.Redemption_ID;
            document.getElementById('mDonorName').innerText = order.Donor_Name;
            document.getElementById('mContact').innerText = order.Redemption_ContactNumber;
            
            let addr = order.Redemption_Address1;
            if(order.Redemption_Address2) addr += ", " + order.Redemption_Address2;
            if(order.Redemption_Address3) addr += ", " + order.Redemption_Address3;
            addr += ", " + order.Redemption_PostalCode + " " + order.Redemption_City + ", " + order.Redemption_State;
            
            document.getElementById('mAddress').innerText = addr;
            
            document.getElementById('mItemName').innerText = order.Reward_ItemName;
            document.getElementById('mPoints').innerText = order.Redemption_PointsSpent;
            document.getElementById('mItemImg').src = order.Reward_PhotoPath || 'uploads/rewards/default.jpg';
            
            document.getElementById('mStatusSelect').value = order.Redemption_Status;
            document.getElementById('mTracking').value = order.Redemption_TrackingNumber || '';
            
            toggleTracking(); 
            calculateAI(order.Redemption_State); 

            document.getElementById('manageModal').style.display = 'flex';
        }

        // --- HELPER FUNCTION: Phone Input Formatting ---
        function setupPhoneInput(inputId) {
            const input = document.getElementById(inputId); 
            if(!input) return;
            input.addEventListener('input', function(e) { 
                let val = this.value.replace(/\D/g, ''); 
                if (val.length > 11) val = val.substring(0, 11); 
                let newVal = val; 
                if (val.length > 2) newVal = val.substring(0, 2) + '-' + val.substring(2); 
                this.value = newVal; 
            });
        }

        // --- HELPER FUNCTION: Postcode to State ---
        function setupPostcodeState(postcodeId, stateSelectId) {
            const pcInput = document.getElementById(postcodeId); 
            const stateSelect = document.getElementById(stateSelectId);
            if (!pcInput || !stateSelect) return;
            pcInput.addEventListener('input', function() {
                const val = this.value.replace(/\D/g, '');
                if (val.length >= 2) {
                    const prefix = parseInt(val.substring(0, 2));
                    let state = "";
                    if (prefix >= 1 && prefix <= 2) state = "Perlis"; 
                    else if (prefix >= 5 && prefix <= 9) state = "Kedah"; 
                    else if (prefix >= 10 && prefix <= 14) state = "Penang";
                    else if (prefix >= 15 && prefix <= 18) state = "Kelantan"; 
                    else if (prefix >= 20 && prefix <= 24) state = "Terengganu"; 
                    else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                    else if (prefix >= 30 && prefix <= 36) state = "Perak"; 
                    else if (prefix >= 40 && prefix <= 48) state = "Selangor"; 
                    else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                    else if (prefix >= 62 && prefix <= 62) state = "Putrajaya"; 
                    else if (prefix >= 63 && prefix <= 68) state = "Selangor"; 
                    else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                    else if (prefix >= 75 && prefix <= 78) state = "Melaka"; 
                    else if (prefix >= 79 && prefix <= 86) state = "Johor"; 
                    else if (prefix == 87) state = "Labuan";
                    else if (prefix >= 88 && prefix <= 91) state = "Sabah"; 
                    else if (prefix >= 93 && prefix <= 98) state = "Sarawak";
                    
                    if (state) $(stateSelect).val(state).trigger('change');
                }
            });
        }

        function setStatus(status) {
            document.getElementById('mStatusSelect').value = status;
            toggleTracking();
            if(status === 'Shipped' && document.getElementById('mTracking').value.trim() === '') {
                alert("Please enter a Tracking Number to Approve/Ship.");
                document.getElementById('mTracking').focus();
                return;
            }
            if(confirm("Are you sure you want to set status to " + status + "? An email will be sent.")) {
                document.getElementById('manageForm').submit();
            }
        }

        function toggleTracking() {
            const val = document.getElementById('mStatusSelect').value;
            const grp = document.getElementById('trackingGroup');
            if(val === 'Shipped' || val === 'Completed') grp.style.display = 'block';
            else grp.style.display = 'none';
        }

        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function calculateAI(donorState) {
            let hqLat = 3.1390; 
            let hqLng = 101.6869;

            if (stateCoords[currentHqState]) {
                hqLat = stateCoords[currentHqState][0];
                hqLng = stateCoords[currentHqState][1];
            }
            
            let distText = "Unknown";
            let courier = "Standard Post";
            let time = "3-5 Days";

            if (stateCoords[donorState]) {
                const lat2 = stateCoords[donorState][0];
                const lon2 = stateCoords[donorState][1];
                
                const R = 6371; 
                const dLat = deg2rad(lat2 - hqLat);
                const dLon = deg2rad(lon2 - hqLng);
                const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                          Math.cos(deg2rad(hqLat)) * Math.cos(deg2rad(lat2)) * Math.sin(dLon/2) * Math.sin(dLon/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                const d = Math.round(R * c);
                
                distText = "~" + d + " km";
                
                if (d < 50) {
                    courier = "Lalamove / Grab";
                    time = "Same Day";
                } else if (donorState === 'Sabah' || donorState === 'Sarawak' || donorState === 'Labuan') {
                    courier = "Pos Laju (Air)";
                    time = "5-7 Days";
                } else {
                    courier = "J&T Express";
                    time = "2-3 Days";
                }
            }
            
            document.getElementById('aiDistance').innerText = distText;
            document.getElementById('aiCourier').innerText = courier;
            document.getElementById('aiTime').innerText = time;
        }

        function deg2rad(deg) { return deg * (Math.PI/180); }

        setTimeout(() => {
            const s = document.getElementById('floatingSuccess');
            const e = document.getElementById('floatingError');
            if(s) s.style.display='none';
            if(e) e.style.display='none';
        }, 4000);

        window.onclick = function(e) {
            if(e.target == document.getElementById('addOrderModal')) closeModal('addOrderModal');
            if(e.target == document.getElementById('manageModal')) closeModal('manageModal');
        }
    </script>
</body>
</html>