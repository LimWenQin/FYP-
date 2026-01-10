<?php
// admin_notifications_all.php
session_start();

// 1. 检查用户登录
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// ==========================================
// 2. 确定角色
// ==========================================
$current_role = 'Guest';
if (isset($_SESSION['admin_id'])) {
    $aid = $_SESSION['admin_id'];
    $q = mysqli_query($conn, "SELECT Admin_Role FROM admin WHERE Admin_ID = $aid");
    if ($r = mysqli_fetch_assoc($q)) $current_role = $r['Admin_Role'];
} elseif (isset($_SESSION['staff_id'])) {
    $current_role = 'Staff';
}

// ==========================================
// 3. 构建过滤条件 (WHERE)
// ==========================================
// 基础条件：未删除
$whereClause = "WHERE n.Is_Deleted = 0";

if ($current_role === 'Super Admin') {
    // Show all
} elseif ($current_role === 'Admin') {
    // Show mostly all
} elseif ($current_role === 'Staff') {
    // Filter out Staff, Payment, Contact, Receipt
    $whereClause .= " AND n.Type NOT IN ('New_Staff', 'Donation', 'receipt_request', 'New_Contact')";
}

// ==========================================
// 4. 处理操作 (Soft Delete / Mark Read)
// ==========================================

// A. 单个删除
if (isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']); 
    // 简单做，不校验所属权，因为是软删
    $conn->query("UPDATE admin_notifications SET Is_Deleted = 1 WHERE AdminNotification_ID = $del_id");
    header("Location: admin_notifications_all.php"); // 防止刷新重提交
    exit;
}

// B. 全部删除 (基于当前过滤条件)
if (isset($_POST['delete_all'])) {
    // 比较复杂，因为不能删别人看不到的。这里简单处理：只删符合当前 whereClause 的
    // 由于 $whereClause 有别名 n.，Update 语法要小心
    // 简化：UPDATE admin_notifications n SET Is_Deleted = 1 WHERE n.Is_Deleted=0 AND [Role Logic]
    // 既然逻辑复用较多，直接全删符合 role 的
    if($current_role === 'Staff') {
         $conn->query("UPDATE admin_notifications SET Is_Deleted = 1 WHERE Is_Deleted = 0 AND Type NOT IN ('New_Staff', 'Donation', 'receipt_request', 'New_Contact')");
    } else {
         $conn->query("UPDATE admin_notifications SET Is_Deleted = 1 WHERE Is_Deleted = 0");
    }
    header("Location: admin_notifications_all.php");
    exit;
}

// C. 全部已读
if (isset($_POST['mark_all_read'])) {
    if($current_role === 'Staff') {
         $conn->query("UPDATE admin_notifications SET Is_Read = 1 WHERE Is_Read = 0 AND Is_Deleted = 0 AND Type NOT IN ('New_Staff', 'Donation', 'receipt_request', 'New_Contact')");
    } else {
         $conn->query("UPDATE admin_notifications SET Is_Read = 1 WHERE Is_Read = 0 AND Is_Deleted = 0");
    }
    header("Location: admin_notifications_all.php");
    exit;
}

// ==========================================
// 5. 获取数据
// ==========================================

// 获取列表 (关联 Contact_Messages 只是为了显示发件人名字，非必须)
$sql = "SELECT n.*, c.Email as ContactEmail, c.Name as ContactName, c.Title as ContactSubject 
        FROM admin_notifications n 
        LEFT JOIN contact_messages c ON n.Contact_ID = c.Contact_ID 
        $whereClause 
        ORDER BY n.Created_At DESC";
$result = $conn->query($sql);

// 计算未读 (基于当前 Filter)
$countSql = "SELECT COUNT(*) as unread FROM admin_notifications n $whereClause AND n.Is_Read = 0";
$countResult = $conn->query($countSql);
$countRow = $countResult->fetch_assoc();
$unreadCount = $countRow['unread']; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox - Love Bridge Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css"> 
    <style>
        /* Outlook/Email 风格样式 */
        body { background-color: #f5f7fa; }
        .notif-container { padding: 30px; max-width: 1100px; margin: 0 auto; }
        
        .toolbar { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 20px; background: white; padding: 15px 25px; 
            border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .toolbar h2 { margin: 0; font-size: 22px; color: #2c3e50; display: flex; align-items: center; gap: 10px; }
        .badge-count { background: #ff4757; color: white; font-size: 12px; padding: 2px 8px; border-radius: 12px; vertical-align: middle; }
        
        .actions-group { display: flex; gap: 10px; }
        .btn-tool { 
            border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; 
            font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: 0.2s;
        }
        .btn-read { background: #e2e6ea; color: #555; }
        .btn-read:hover { background: #dbe0e5; color: #333; }
        .btn-clear { background: #fff0f0; color: #dc3545; }
        .btn-clear:hover { background: #ffe0e0; }

        .email-list { background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; }

        .email-item { 
            display: flex; align-items: center; padding: 18px 25px; border-bottom: 1px solid #f1f1f1; 
            transition: all 0.2s ease; position: relative; cursor: pointer;
        }
        .email-item:hover { background-color: #f8f9fa; z-index: 1; box-shadow: 0 0 10px rgba(0,0,0,0.02); }
        .email-item:last-child { border-bottom: none; }
        
        .email-item.unread { background-color: #fffdfd; }
        .email-item.unread::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #ff4757;
        }
        .email-item.unread .email-subject { font-weight: 700; color: #000; }
        .email-item.unread .email-time { color: #ff4757; font-weight: 600; }

        .email-icon { 
            width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            color: white; font-size: 18px; margin-right: 20px; flex-shrink: 0;
        }
        
        .email-content { flex-grow: 1; display: flex; flex-direction: column; justify-content: center; }
        .email-header { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .email-subject { font-size: 15px; color: #333; line-height: 1.4; margin-right: 15px; }
        .email-meta { font-size: 13px; color: #888; display: flex; gap: 15px; }
        
        .email-actions { 
            display: flex; align-items: center; gap: 10px; opacity: 0; transition: opacity 0.2s; 
            margin-left: 20px; min-width: 100px; justify-content: flex-end;
        }
        .email-item:hover .email-actions { opacity: 1; }

        .action-btn { 
            width: 35px; height: 35px; border-radius: 50%; border: 1px solid #eee; background: white; 
            display: flex; align-items: center; justify-content: center; color: #666; cursor: pointer; transition: 0.2s;
        }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .action-btn.delete:hover { color: #dc3545; border-color: #dc3545; }
        
        /* Colors */
        .bg-green { background: linear-gradient(135deg, #2ecc71, #26af61); }
        .bg-red { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .bg-blue { background: linear-gradient(135deg, #3498db, #2980b9); }
        .bg-yellow { background: linear-gradient(135deg, #f1c40f, #f39c12); }
        .bg-purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .bg-gray { background: #95a5a6; }

        .empty-state { text-align: center; padding: 60px 20px; color: #aab; }
        .empty-state i { font-size: 50px; margin-bottom: 20px; opacity: 0.5; }
        
        /* 自定义 Modal 样式 */
        .confirm-modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;
        }
        .confirm-box {
            background: white; width: 90%; max-width: 400px; border-radius: 10px; 
            padding: 25px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .confirm-box i { font-size: 40px; color: #f1c40f; margin-bottom: 15px; display: block; }
        .confirm-box h3 { margin: 0 0 10px; color: #333; }
        .confirm-box p { color: #666; margin-bottom: 25px; font-size: 14px; }
        .confirm-btns { display: flex; gap: 10px; justify-content: center; }
        .confirm-btns button {
            padding: 10px 25px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; transition: 0.2s;
        }
        .btn-cancel { background: #f1f1f1; color: #555; }
        .btn-confirm { background: #dc3545; color: white; }
        .btn-confirm:hover { background: #c82333; }

        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?> 

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?> 

        <div class="notif-container">
            <div class="toolbar">
                <h2>
                    Inbox 
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge-count"><?php echo $unreadCount; ?> New</span>
                    <?php endif; ?>
                </h2>
                
                <div class="actions-group">
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="mark_all_read" class="btn-tool btn-read" <?php if($unreadCount == 0) echo 'disabled style="opacity:0.5;"'; ?>>
                            <i class="fas fa-check-double"></i> Mark all read
                        </button>
                    </form>

                    <button type="button" onclick="openConfirmModal('deleteAllForm')" class="btn-tool btn-clear">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                    <form id="deleteAllForm" method="POST" style="display:none;">
                        <input type="hidden" name="delete_all" value="1">
                    </form>
                </div>
            </div>

            <div class="email-list">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        // 图标和颜色逻辑
                        $type = strtolower($row['Type']);
                        $iconClass = 'fa-bell'; $bgClass = 'bg-gray';
                        
                        if(strpos($type, 'donation') !== false || strpos($type, 'payment') !== false) { 
                            $iconClass = 'fa-hand-holding-usd'; $bgClass = 'bg-green'; 
                        } elseif(strpos($type, 'fail') !== false) { 
                            $iconClass = 'fa-times-circle'; $bgClass = 'bg-red'; 
                        } elseif(strpos($type, 'contact') !== false) { 
                            $iconClass = 'fa-envelope-open-text'; $bgClass = 'bg-blue'; 
                        } elseif(strpos($type, 'target') !== false || strpos($type, 'case') !== false) { 
                            $iconClass = 'fa-trophy'; $bgClass = 'bg-yellow'; 
                        } elseif(strpos($type, 'staff') !== false) {
                            $iconClass = 'fa-user-tie'; $bgClass = 'bg-purple';
                        } elseif(strpos($type, 'donor') !== false || strpos($type, 'user') !== false) {
                            $iconClass = 'fa-user-plus'; $bgClass = 'bg-purple';
                        }

                        // 跳转链接
                        $targetLink = !empty($row['Link']) ? $row['Link'] : 'javascript:void(0)';
                    ?>
                    
                    <div class="email-item <?php echo $row['Is_Read'] == 0 ? 'unread' : ''; ?>" 
                         onclick="if(!event.target.closest('.email-actions')) window.location.href='<?php echo $targetLink; ?>'">
                        
                        <div class="email-icon <?php echo $bgClass; ?>">
                            <i class="fas <?php echo $iconClass; ?>"></i>
                        </div>

                        <div class="email-content">
                            <div class="email-header">
                                <span class="email-subject"><?php echo htmlspecialchars($row['Message']); ?></span>
                            </div>
                            <div class="email-meta">
                                <span><i class="far fa-clock"></i> <?php echo date('d M Y, h:i A', strtotime($row['Created_At'])); ?></span>
                                <?php if($row['ContactName']): ?>
                                    <span><i class="far fa-user"></i> From: <?php echo htmlspecialchars($row['ContactName']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="email-actions">
                            <button type="button" class="action-btn delete" title="Delete" 
                                    onclick="event.stopPropagation(); openConfirmModal('deleteSingleForm<?php echo $row['AdminNotification_ID']; ?>')">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                            
                            <form id="deleteSingleForm<?php echo $row['AdminNotification_ID']; ?>" method="POST" style="display:none;">
                                <input type="hidden" name="delete_id" value="<?php echo $row['AdminNotification_ID']; ?>">
                            </form>
                        </div>

                    </div>
                    <?php endwhile; ?>
                
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-envelope-open"></i>
                        <h3>You're all caught up!</h3>
                        <p>No new notifications at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="confirm-modal" id="customConfirmModal">
        <div class="confirm-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Are you sure?</h3>
            <p>Do you really want to delete this notification? This process cannot be undone.</p>
            <div class="confirm-btns">
                <button class="btn-cancel" onclick="closeConfirmModal()">Cancel</button>
                <button class="btn-confirm" id="confirmActionBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let formToSubmit = null;

        function openConfirmModal(formId) {
            formToSubmit = document.getElementById(formId);
            const modal = document.getElementById('customConfirmModal');
            
            // 简单的文本替换 logic
            const title = modal.querySelector('h3');
            const desc = modal.querySelector('p');
            
            if (formId === 'deleteAllForm') {
                title.innerText = "Clear All Notifications?";
                desc.innerText = "This will remove all notifications from your inbox.";
            } else {
                title.innerText = "Delete Notification?";
                desc.innerText = "This will remove this notification from your list.";
            }

            modal.style.display = 'flex';
        }

        function closeConfirmModal() {
            document.getElementById('customConfirmModal').style.display = 'none';
            formToSubmit = null;
        }

        document.getElementById('confirmActionBtn').addEventListener('click', function() {
            if (formToSubmit) {
                formToSubmit.submit();
            }
        });

        // 点击背景关闭
        document.getElementById('customConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeConfirmModal();
        });
    </script>

</body>
</html>