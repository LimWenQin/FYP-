<?php
// fetch_admin_notifications.php
include 'dataconnection.php'; 

header('Content-Type: application/json');

// 1. 获取未读数量 (只计算未被 Soft Delete 的)
$countQuery = "SELECT COUNT(*) as unread_count FROM admin_notifications WHERE Is_Read = 0 AND Is_Deleted = 0";
$countResult = mysqli_query($conn, $countQuery);
$countRow = mysqli_fetch_assoc($countResult);
$unreadCount = $countRow['unread_count'];

// 2. 获取下拉菜单的通知列表
// 修改逻辑：只显示【未读】且【未删除】的通知，以符合你"mark read后不再出现在预览"的要求
$listQuery = "SELECT * FROM admin_notifications WHERE Is_Read = 0 AND Is_Deleted = 0 ORDER BY Created_At DESC LIMIT 5";
$listResult = mysqli_query($conn, $listQuery);

$notifications = [];
while ($row = mysqli_fetch_assoc($listResult)) {
    // 图标逻辑
    $icon = 'fa-info-circle'; 
    $color = '#6c757d'; // 灰色默认
    
    $type = strtolower($row['Type']);
    
    if (strpos($type, 'donation') !== false) {
        $icon = 'fa-hand-holding-usd';
        $color = '#28a745'; // 绿色
    } elseif (strpos($type, 'fail') !== false) {
        $icon = 'fa-times-circle';
        $color = '#dc3545'; // 红色
    } elseif (strpos($type, 'target') !== false || strpos($type, 'case') !== false) {
        $icon = 'fa-trophy';
        $color = '#ffc107'; // 金色
    } elseif (strpos($type, 'contact') !== false) {
        $icon = 'fa-envelope';
        $color = '#17a2b8'; // 蓝色
    } elseif (strpos($type, 'user') !== false || strpos($type, 'staff') !== false) {
        $icon = 'fa-user-plus';
        $color = '#6610f2'; // 紫色
    }

    $notifications[] = [
        'id' => $row['AdminNotification_ID'],
        'message' => $row['Message'],
        'time' => date('M d, H:i', strtotime($row['Created_At'])),
        'type' => $row['Type'],
        'icon' => $icon,
        'color' => $color
    ];
}

echo json_encode([
    'count' => $unreadCount,
    'notifications' => $notifications
]);
?>