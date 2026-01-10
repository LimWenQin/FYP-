<?php
// fetch_admin_notifications.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'dataconnection.php'; 

header('Content-Type: application/json');

// --- 1. 确定当前用户角色 ---
$current_role = 'Guest';
if (isset($_SESSION['admin_id'])) {
    // 简单查询一下 admin role，或者直接用 session 里的如果已经存了
    // 为保险起见，这里查一下数据库
    $aid = $_SESSION['admin_id'];
    $r_query = mysqli_query($conn, "SELECT Admin_Role FROM admin WHERE Admin_ID = $aid");
    if($r_row = mysqli_fetch_assoc($r_query)){
        $current_role = $r_row['Admin_Role']; // 'Super Admin' or 'Admin'
    }
} elseif (isset($_SESSION['staff_id'])) {
    $current_role = 'Staff';
}

// --- 2. 构建权限过滤条件 (WHERE Clause) ---
// 默认条件：未读且未删除
$whereClause = "WHERE Is_Read = 0 AND Is_Deleted = 0";

// 根据角色追加过滤
if ($current_role === 'Super Admin') {
    // Super Admin 看所有，不做额外过滤
} 
elseif ($current_role === 'Admin') {
    // Admin 看大部分，除了 Block List 相关的(如果有通知的话)，或者 Admin Management
    // 目前假设 Admin 和 Super Admin 通知基本互通
} 
elseif ($current_role === 'Staff') {
    // Staff 只能看: Donor, Activity, Campaign, Reward
    // Staff 不能看: Staff(New_Staff), Payment(Donation), Receipt(receipt_request), Contact(New_Contact)
    $whereClause .= " AND Type NOT IN ('New_Staff', 'Donation', 'receipt_request', 'New_Contact')";
    // 也可以用白名单逻辑： AND Type IN ('New_Donor', 'Target', 'Update')
} else {
    // Guest 或者未登录，什么都不给看
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit();
}

// --- 3. 获取数量 ---
$countQuery = "SELECT COUNT(*) as unread_count FROM admin_notifications $whereClause";
$countResult = mysqli_query($conn, $countQuery);
$countRow = mysqli_fetch_assoc($countResult);
$unreadCount = $countRow['unread_count'];

// --- 4. 获取列表 (Limit 5) ---
$listQuery = "SELECT * FROM admin_notifications $whereClause ORDER BY Created_At DESC LIMIT 5";
$listResult = mysqli_query($conn, $listQuery);

$notifications = [];
while ($row = mysqli_fetch_assoc($listResult)) {
    // 图标逻辑
    $icon = 'fa-info-circle'; 
    $color = '#6c757d'; 
    
    $type = strtolower($row['Type']);
    $msg = strtolower($row['Message']); // 辅助判断
    
    if (strpos($type, 'donation') !== false) {
        $icon = 'fa-hand-holding-usd';
        $color = '#28a745'; 
    } elseif (strpos($type, 'fail') !== false) {
        $icon = 'fa-times-circle';
        $color = '#dc3545';
    } elseif (strpos($type, 'target') !== false || strpos($type, 'case') !== false) {
        $icon = 'fa-trophy';
        $color = '#ffc107'; 
    } elseif (strpos($type, 'contact') !== false) {
        $icon = 'fa-envelope';
        $color = '#17a2b8'; 
    } elseif (strpos($type, 'staff') !== false) { // New_Staff
        $icon = 'fa-user-tie';
        $color = '#6610f2'; 
    } elseif (strpos($type, 'donor') !== false || strpos($type, 'user') !== false) {
        $icon = 'fa-user-plus';
        $color = '#20c997'; 
    } elseif (strpos($type, 'receipt') !== false) {
        $icon = 'fa-file-invoice';
        $color = '#e83e8c';
    }

    // 默认链接 (以防数据库是旧数据没有 Link)
    $link = $row['Link'];
    if(empty($link)) {
        $link = 'admin_notifications_all.php'; // 默认跳去全部通知页
    }

    $notifications[] = [
        'id' => $row['AdminNotification_ID'],
        'message' => $row['Message'],
        'time' => date('M d, H:i', strtotime($row['Created_At'])),
        'type' => $row['Type'],
        'icon' => $icon,
        'color' => $color,
        'link'  => $link  // 传递链接给前端
    ];
}

echo json_encode([
    'count' => $unreadCount,
    'notifications' => $notifications
]);
?>