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

// 定义网站基础 URL (用于生成邮件里的确认链接)
// 请根据你的实际环境修改，例如 http://localhost/donation_system/
define('BASE_URL', 'http://localhost/donation_system/');

// ==========================================
// 0. AUTOMATED TASKS (LAZY CRON)
// 每次管理员加载此页面时，自动检查并执行后台任务
// ==========================================
function checkAutomatedTasks($conn) {
    // A. 自动完成 (Auto-Complete): 发货超过 21 天未确认 -> 设为 Completed
    $sqlAutoComp = "UPDATE redemption_order 
                    SET Redemption_Status = 'Completed', Redemption_Updated_At = NOW() 
                    WHERE Redemption_Status = 'Shipped' 
                    AND Redemption_Shipped_At < DATE_SUB(NOW(), INTERVAL 21 DAY)";
    $conn->query($sqlAutoComp);

    // B. 发送跟进邮件 (Follow-Up): 超过预计到达日期 -> 发邮件询问
    // 获取需要发邮件的订单
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
            // 发送跟进邮件
            if (sendFollowUpEmail($row['Donor_Email'], $row['Donor_Name'], $row['Reward_ItemName'], $row['Redemption_ID'])) {
                // 标记为已发送
                $rid = $row['Redemption_ID'];
                $conn->query("UPDATE redemption_order SET Redemption_FollowUp_Sent = 1 WHERE Redemption_ID = $rid");
            }
        }
    }
}
// 执行自动化任务
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

// 1. Search
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

// ==========================================
// EMAIL FUNCTIONS
// ==========================================

// 1. 常规状态更新邮件 (Shipped / Cancelled)
function sendStatusEmail($to, $name, $status, $itemName, $tracking = null, $estDate = null) {
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
        $bodyContent .= "<p>Any points used have been fully refunded to your account.</p>";
    } else {
        $bodyContent .= "<p>The status of your redemption order for <strong>'$itemName'</strong> has been updated to: <strong>$status</strong>.</p>";
    }

    $bodyContent .= "<br><p>Thank you for your support,<br>Love Bridge Team</p>";
    $bodyContent .= "</div></div>";

    return sendEmailViaSMTP($to, $subject, $bodyContent);
}

// 2. 跟进邮件 (Ask if received)
function sendFollowUpEmail($to, $name, $itemName, $orderId) {
    $subject = "Did you receive your item? - Love Bridge";
    
    // 生成确认链接 (假设有一个 donor_confirm.php 处理这个逻辑，或者跳转到 donor portal)
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

// SMTP 发送核心函数
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
        $estDays = isset($_POST['estimated_days']) ? intval($_POST['estimated_days']) : 5; // Get from Hidden Input
        
        $checkSql = "SELECT r.Redemption_Status, r.Redemption_PointsSpent, r.Donor_ID, 
                            d.Donor_Email, d.Donor_Name, rw.Reward_ItemName 
                     FROM redemption_order r 
                     JOIN donor d ON r.Donor_ID = d.Donor_ID
                     JOIN reward_item rw ON r.Reward_ID = rw.Reward_ID
                     WHERE r.Redemption_ID = $redemptionId";
        $checkResult = $conn->query($checkSql);
        $orderData = $checkResult->fetch_assoc();
        $oldStatus = $orderData['Redemption_Status'];

        // Logic for Cancelled (Refund Points)
        if ($newStatus == 'Cancelled' && $oldStatus != 'Cancelled') {
            $refundPoints = $orderData['Redemption_PointsSpent'];
            $donorId = $orderData['Donor_ID'];
            $conn->query("UPDATE point SET Points_Total = Points_Total + $refundPoints, Points_Updated_At = NOW() WHERE Donor_ID = $donorId");
        }

        // Logic for Shipped (Set Date & Est Date)
        $extraUpdate = "";
        $estDeliveryDate = null;
        if ($newStatus == 'Shipped' && $oldStatus != 'Shipped') {
            $estDeliveryDate = date('Y-m-d', strtotime("+$estDays days"));
            $extraUpdate = ", Redemption_Shipped_At = NOW(), Redemption_Est_Delivery_Date = '$estDeliveryDate', Redemption_FollowUp_Sent = 0";
        }

        $sql = "UPDATE redemption_order SET Redemption_Status = '$newStatus', Redemption_Updated_At = NOW() $extraUpdate";
        if ($tracking) {
            $sql .= ", Redemption_TrackingNumber = '$tracking'";
        }
        $sql .= " WHERE Redemption_ID = $redemptionId";
        
        if ($conn->query($sql)) {
            // Send Notification Email
            sendStatusEmail($orderData['Donor_Email'], $orderData['Donor_Name'], $newStatus, $orderData['Reward_ItemName'], $tracking, $estDeliveryDate);

            $msg = "Order #$redemptionId updated to $newStatus.";
            if ($newStatus == 'Cancelled') $msg .= " Points refunded to donor.";
            if ($newStatus == 'Shipped') $msg .= " Donor notified (Est. Arrival: $estDeliveryDate).";
            
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
$hqState = "Kuala Lumpur"; // Default
try {
    $hqSql = "SELECT Headquarters_State FROM headquarters LIMIT 1";
    $hqResult = $conn->query($hqSql);
    if ($hqResult && $hqResult->num_rows > 0) {
        $row = $hqResult->fetch_assoc();
        if (!empty($row['Headquarters_State'])) $hqState = $row['Headquarters_State'];
    }
} catch (Exception $e) {}

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
        .btn-light-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .btn-light-pending:hover { background: #ffeeba; }

        /* Pagination */
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
        
        .item-thumb { width: 50px; height: 50px; border-radius: 5px; object-fit: cover; border: 1px solid #eee; cursor: zoom-in; transition: transform 0.2s; }
        .item-thumb:hover { transform: scale(1.05); border-color: #F28585; }

        .order-meta { font-size: 12px; color: #666; margin-top: 5px; }

        /* Action Menu (Same as Donor Page) */
        .action-cell { display: flex; justify-content: center; align-items: center; }
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { background-color: #f8f9fa; border: 1px solid #e9ecef; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; color: #6c757d; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; outline: none; }
        .menu-btn:hover { background-color: #e2e6ea; color: #F28585; }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 160px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; text-align: left; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 10px; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: #F28585; }

        /* Modals */
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

        .select2-container .select2-selection--single { height: 42px !important; border: 1px solid #ddd !important; border-radius: 5px !important; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background-color: #F28585 !important; color: white !important; }

        .register-hint { margin-top: 5px; padding: 10px; background-color: #f0f8ff; border-left: 3px solid #17a2b8; font-size: 13px; color: #555; border-radius: 4px; }
        .register-hint a { color: #F28585; font-weight: 700; text-decoration: underline; margin-left: 5px; }
        .register-hint a:hover { color: #d65f5f; }
        .phone-format { display: flex; align-items: center; }
        .phone-prefix { padding: 10px 12px; background: #f8f9fa; border: 1px solid #ddd; border-right: none; border-radius: 5px 0 0 5px; color: #666; font-weight: bold; font-size: 14px; }
        .phone-input { border-radius: 0 5px 5px 0 !important; }

        /* Floating Alert */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; max-width: 400px; display: none; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }

        /* Lightbox */
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 2002; }
        .close-lightbox:hover { color: #bbb; }
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
                                            <?php $img = !empty($order['Reward_PhotoPath']) ? 'uploads/rewards/' . $order['Reward_PhotoPath'] : 'uploads/rewards/default.jpg'; ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" class="item-thumb" alt="Item" onclick="openLightbox('<?php echo htmlspecialchars($img); ?>')">
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
                                        <div class="action-cell">
                                            <div class="action-menu">
                                                <button class="menu-btn" onclick="toggleMenu(event, <?php echo $order['Redemption_ID']; ?>)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div id="menu-<?php echo $order['Redemption_ID']; ?>" class="dropdown-content">
                                                    <div onclick='openManageModal(<?php echo json_encode($order); ?>)'>
                                                        <i class="fas fa-tasks"></i> Manage Order
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
                        $queryParams = [];
                        if(!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if(!empty($filterStatus)) $queryParams['status'] = $filterStatus;
                        $queryString = http_build_query($queryParams);
                        $paginationUrl = !empty($queryString) ? '&' . $queryString : '';
                        
                        if ($page > 1) echo '<a href="?page=' . ($page - 1) . $paginationUrl . '" class="pagination-btn">Previous</a>'; 
                        else echo '<span class="pagination-btn disabled">Previous</span>';
                        
                        for ($i = 1; $i <= $total_pages; $i++) { 
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

    <div class="modal" id="addOrderModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add Redemption Order</h2><button onclick="closeModal('addOrderModal')" style="border:none; background:none; font-size:24px; cursor:pointer;">&times;</button></div>
            <div class="modal-body">
                <form method="POST" action="redemption_order_management.php" id="addOrderForm" onsubmit="return validateAddOrder()" novalidate>
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
                                        data-postal="<?php echo htmlspecialchars($d['Donor_PostalCode']); ?>">
                                    <?php echo htmlspecialchars($d['Donor_Name']) . " (" . $d['Donor_ICNumber'] . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="register-hint">
                            <i class="fas fa-info-circle"></i> Donor doesn't have an account? <a href="admin_donor_page.php">Register Donor Here</a>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Select Reward Item <span style="color:red">*</span></label>
                        <select name="reward_id" id="add_reward_id" class="form-select" required>
                            <option value="">-- Choose Reward --</option>
                            <?php foreach($rewardList as $r): ?>
                                <option value="<?php echo $r['Reward_ID']; ?>">
                                    <?php echo htmlspecialchars($r['Reward_ItemName']) . " - " . $r['Reward_RequiredPoint'] . " pts (Stock: " . $r['Reward_Stock'] . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <div class="phone-format">
                            <span class="phone-prefix">+60</span>
                            <input type="text" name="contact" id="add_contact" class="form-input phone-input" required placeholder="12-3456789" maxlength="11">
                        </div>
                    </div>

                    <div style="border-top:1px solid #eee; margin:15px 0; padding-top:10px;">
                        <div class="form-group"><label class="form-label">Address Line 1</label><input type="text" name="address1" id="add_address1" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Address Line 2</label><input type="text" name="address2" id="add_address2" class="form-input"></div>
                        <div class="form-group"><label class="form-label">Address Line 3</label><input type="text" name="address3" id="add_address3" class="form-input"></div>

                        <div style="display:flex; gap:10px;">
                            <div class="form-group" style="flex:1"><label class="form-label">Postal Code</label><input type="text" name="postal_code" id="add_postal_code" class="form-input" required></div>
                            <div class="form-group" style="flex:1"><label class="form-label">City</label><input type="text" name="city" id="add_city" class="form-input" required></div>
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
                        <div class="ai-metric"><span>Estimated Distance</span><strong id="aiDistance">Calculating...</strong></div>
                        <div class="ai-metric"><span>Recommended Courier</span><strong id="aiCourier">Analyzing...</strong></div>
                        <div class="ai-metric"><span>Est. Delivery</span><strong id="aiTime">...</strong></div>
                    </div>
                </div>

                <form method="POST" action="redemption_order_management.php" id="manageForm">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="redemption_id" id="mFormId">
                    <input type="hidden" name="estimated_days" id="hiddenEstDays" value="5">
                    
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
            $('#add_donor_select').select2({ placeholder: "-- Choose Donor --", allowClear: true, dropdownParent: $('#addOrderModal') });
            $('#add_donor_select').on('change', function() {
                var selected = $(this).find(':selected');
                $('input[name="address1"]').val(selected.data('address1'));
                $('input[name="address2"]').val(selected.data('address2'));
                $('input[name="address3"]').val(selected.data('address3'));
                $('input[name="city"]').val(selected.data('city'));
                $('input[name="postal_code"]').val(selected.data('postal'));
                let c = selected.data('contact');
                if(c && c.startsWith('+60')) c = c.substring(3);
                $('#add_contact').val(c);
                if(selected.data('state')) $('select[name="state"]').val(selected.data('state')).change();
            });
            setupPhoneInput('add_contact');
            setupPostcodeState('add_postal_code', 'add_state_select');
        });

        const stateCoords = <?php echo json_encode($stateCoords); ?>;
        const currentHqState = "<?php echo htmlspecialchars($hqState); ?>";
        
        // --- DROPDOWN LOGIC ---
        function toggleMenu(e, id) { 
            e.stopPropagation(); 
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); 
            const menu = document.getElementById('menu-' + id); 
            if(menu) menu.style.display = 'block'; 
        }

        window.onclick = function(e) {
            // Close dropdowns
            if (!e.target.matches('.menu-btn') && !e.target.matches('.menu-btn *')) { 
                document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); 
            }
            // Close Modals
            if(e.target == document.getElementById('addOrderModal')) closeModal('addOrderModal');
            if(e.target == document.getElementById('manageModal')) closeModal('manageModal');
            // Close Lightbox
            if(e.target.id == 'imageLightbox') closeLightbox();
        }

        // --- NEW: SYSTEM ALERT FUNCTION ---
        function showSystemError(message) {
            const errorBox = document.getElementById('floatingError');
            const errorText = document.getElementById('alertErrorText');
            if(errorBox && errorText) {
                errorText.innerText = message;
                errorBox.style.display = 'flex';
                // Auto hide after 5 seconds
                setTimeout(() => {
                    errorBox.style.display = 'none';
                }, 5000);
            }
        }

        // --- AUTO HIDE ALERTS ---
        document.addEventListener('DOMContentLoaded', function() {
            const s = document.getElementById('floatingSuccess');
            const e = document.getElementById('floatingError');
            if(s && s.style.display === 'flex') setTimeout(() => { s.style.display='none'; }, 5000);
            if(e && e.style.display === 'flex') setTimeout(() => { e.style.display='none'; }, 5000);
        });

        // --- VALIDATE FORM (Manual) ---
        function validateAddOrder() {
            let errors = [];
            
            // Get values. Using jQuery for select2 value
            const donor = $('#add_donor_select').val(); 
            const reward = document.getElementById('add_reward_id').value;
            const contact = document.getElementById('add_contact').value.trim();
            const addr1 = document.getElementById('add_address1').value.trim();
            const postal = document.getElementById('add_postal_code').value.trim();
            const city = document.getElementById('add_city').value.trim();
            const state = document.getElementById('add_state_select').value;

            // Checks
            if (!donor) errors.push("Donor is required.");
            if (!reward) errors.push("Reward Item is required.");
            
            if (!contact) errors.push("Contact Number is required.");
            else if (contact.length < 9) errors.push("Invalid Contact Number.");
            
            if (!addr1) errors.push("Address Line 1 is required.");
            if (!postal) errors.push("Postal Code is required.");
            if (!city) errors.push("City is required.");
            if (!state) errors.push("State is required.");

            if (errors.length > 0) {
                showSystemError("Validation Error: " + errors.join(" "));
                return false;
            }
            return true;
        }

        // --- LIGHTBOX ---
        function openLightbox(src) {
            document.getElementById('lightboxImage').src = src;
            document.getElementById('imageLightbox').style.display = 'flex';
        }
        function closeLightbox() {
            document.getElementById('imageLightbox').style.display = 'none';
        }

        function openAddOrderModal() { document.getElementById('addOrderModal').style.display = 'flex'; }
        
        function openManageModal(order) {
            document.getElementById('manageOrderId').innerText = order.Redemption_ID;
            document.getElementById('mFormId').value = order.Redemption_ID;
            document.getElementById('mDonorName').innerText = order.Donor_Name;
            document.getElementById('mContact').innerText = order.Redemption_ContactNumber;
            
            let addr = order.Redemption_Address1;
            if(order.Redemption_Address2) addr += ", " + order.Redemption_Address2;
            addr += ", " + order.Redemption_PostalCode + " " + order.Redemption_City + ", " + order.Redemption_State;
            document.getElementById('mAddress').innerText = addr;
            document.getElementById('mItemName').innerText = order.Reward_ItemName;
            document.getElementById('mPoints').innerText = order.Redemption_PointsSpent;
            document.getElementById('mItemImg').src = order.Reward_PhotoPath ? 'uploads/rewards/' + order.Reward_PhotoPath : 'uploads/rewards/default.jpg';
            document.getElementById('mStatusSelect').value = order.Redemption_Status;
            document.getElementById('mTracking').value = order.Redemption_TrackingNumber || '';
            
            toggleTracking(); 
            calculateAI(order.Redemption_State); 
            document.getElementById('manageModal').style.display = 'flex';
        }

        function setupPhoneInput(inputId) {
            const input = document.getElementById(inputId); if(!input) return;
            input.addEventListener('input', function(e) { 
                let val = this.value.replace(/\D/g, ''); if (val.length > 11) val = val.substring(0, 11); 
                let newVal = val; if (val.length > 2) newVal = val.substring(0, 2) + '-' + val.substring(2); 
                this.value = newVal; 
            });
        }

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
                    if (state) $(stateSelect).val(state).trigger('change');
                }
            });
        }

        function setStatus(status) {
            document.getElementById('mStatusSelect').value = status;
            toggleTracking();

            // Validation for Shipping
            if(status === 'Shipped' && document.getElementById('mTracking').value.trim() === '') {
                Swal.fire('Tracking Required', 'Please enter a tracking number.', 'warning');
                return;
            }

            // SweetAlert2 Confirmation
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
            const grp = document.getElementById('trackingGroup');
            if(val === 'Shipped' || val === 'Completed') grp.style.display = 'block';
            else grp.style.display = 'none';
        }

        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function calculateAI(donorState) {
            let hqLat = 3.1390; let hqLng = 101.6869;
            if (stateCoords[currentHqState]) { hqLat = stateCoords[currentHqState][0]; hqLng = stateCoords[currentHqState][1]; }
            
            let distText = "Unknown"; let courier = "Standard Post"; let time = "3-5 Days"; let daysInt = 5;

            if (stateCoords[donorState]) {
                const lat2 = stateCoords[donorState][0]; const lon2 = stateCoords[donorState][1];
                const R = 6371; 
                const dLat = (lat2 - hqLat) * (Math.PI/180);
                const dLon = (lon2 - hqLng) * (Math.PI/180);
                const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(hqLat*(Math.PI/180)) * Math.cos(lat2*(Math.PI/180)) * Math.sin(dLon/2) * Math.sin(dLon/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                const d = Math.round(R * c);
                
                distText = "~" + d + " km";
                
                if (d < 50) { courier = "Lalamove / Grab"; time = "Same Day (1 Day)"; daysInt = 1; } 
                else if (donorState === 'Sabah' || donorState === 'Sarawak' || donorState === 'Labuan') { courier = "Pos Laju (Air)"; time = "5-7 Days"; daysInt = 7; } 
                else { courier = "J&T Express"; time = "2-3 Days"; daysInt = 3; }
            }
            
            document.getElementById('aiDistance').innerText = distText;
            document.getElementById('aiCourier').innerText = courier;
            document.getElementById('aiTime').innerText = time;
            document.getElementById('hiddenEstDays').value = daysInt; // Send to PHP
        }
    </script>
</body>
</html>