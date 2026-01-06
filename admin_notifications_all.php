<?php
// admin_notifications_all.php
session_start();

// 1. 检查用户登录
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// ==========================================
// 2. [新增] 获取当前 Admin 的个人资料 (为了 Header 显示)
// ==========================================
// 必须在 include 'admin_header.php' 之前准备好这些变量
$admin_id = $_SESSION['admin_id'];
$admin_sql = "SELECT * FROM admin WHERE Admin_ID = ?";
$stmt = $conn->prepare($admin_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();

if ($admin_row = $admin_result->fetch_assoc()) {
    $adminName = $admin_row['Admin_Name'];
    $adminPosition = $admin_row['Admin_Role']; // 对应数据库里的 Admin_Role
    $adminProfilePicture = $admin_row['Admin_ProfilePicture'];
} else {
    // 如果找不到，使用默认值
    $adminName = "Admin";
    $adminPosition = "Administrator";
    $adminProfilePicture = null;
}
$stmt->close();

// ==========================================
// 3. 处理操作 (Soft Delete / Mark Read)
// ==========================================

// A. 单个删除 (Soft Delete)
if (isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']); 
    $conn->query("UPDATE admin_notifications SET Is_Deleted = 1 WHERE AdminNotification_ID = $del_id");
}

// B. 全部删除 (Soft Delete All)
if (isset($_POST['delete_all'])) {
    $conn->query("UPDATE admin_notifications SET Is_Deleted = 1 WHERE Is_Deleted = 0");
}

// C. 全部已读
if (isset($_POST['mark_all_read'])) {
    $conn->query("UPDATE admin_notifications SET Is_Read = 1 WHERE Is_Read = 0 AND Is_Deleted = 0");
}

// ==========================================
// 4. 获取通知数据
// ==========================================

// 计算未读 (只算未删除的)
$countSql = "SELECT COUNT(*) as unread FROM admin_notifications WHERE Is_Read = 0 AND Is_Deleted = 0";
$countResult = $conn->query($countSql);
$countRow = $countResult->fetch_assoc();
$unreadCount = $countRow['unread']; 

// 获取列表 (关联 Contact_Messages 表以获取发件人名字和Email)
$sql = "SELECT n.*, c.Email as ContactEmail, c.Name as ContactName, c.Title as ContactSubject 
        FROM admin_notifications n 
        LEFT JOIN contact_messages c ON n.Contact_ID = c.Contact_ID 
        WHERE n.Is_Deleted = 0 
        ORDER BY n.Created_At DESC";
$result = $conn->query($sql);
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
        
        /* 顶部工具栏 */
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

        /* 邮件列表容器 */
        .email-list { background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; }

        /* 单个通知行 */
        .email-item { 
            display: flex; align-items: center; padding: 18px 25px; border-bottom: 1px solid #f1f1f1; 
            transition: all 0.2s ease; position: relative;
        }
        .email-item:hover { background-color: #f8f9fa; z-index: 1; box-shadow: 0 0 10px rgba(0,0,0,0.02); }
        .email-item:last-child { border-bottom: none; }
        
        /* 未读状态：左侧红条 + 背景微红 + 文字加粗 */
        .email-item.unread { background-color: #fffdfd; }
        .email-item.unread::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #ff4757;
        }
        .email-item.unread .email-subject { font-weight: 700; color: #000; }
        .email-item.unread .email-time { color: #ff4757; font-weight: 600; }

        /* 图标区域 */
        .email-icon { 
            width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            color: white; font-size: 18px; margin-right: 20px; flex-shrink: 0;
        }
        
        /* 内容区域 */
        .email-content { flex-grow: 1; display: flex; flex-direction: column; justify-content: center; }
        .email-header { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .email-subject { font-size: 15px; color: #333; line-height: 1.4; margin-right: 15px; }
        .email-meta { font-size: 13px; color: #888; display: flex; gap: 15px; }
        
        /* 右侧操作按钮 (悬停显示) */
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
        .action-btn.reply:hover { color: #17a2b8; border-color: #17a2b8; }
        
        /* 颜色类 */
        .bg-green { background: linear-gradient(135deg, #2ecc71, #26af61); box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3); }
        .bg-red { background: linear-gradient(135deg, #e74c3c, #c0392b); box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3); }
        .bg-blue { background: linear-gradient(135deg, #3498db, #2980b9); box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3); }
        .bg-yellow { background: linear-gradient(135deg, #f1c40f, #f39c12); box-shadow: 0 4px 10px rgba(241, 196, 15, 0.3); }
        .bg-purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); box-shadow: 0 4px 10px rgba(155, 89, 182, 0.3); }
        .bg-gray { background: #95a5a6; }

        /* 空状态 */
        .empty-state { text-align: center; padding: 60px 20px; color: #aab; }
        .empty-state i { font-size: 50px; margin-bottom: 20px; opacity: 0.5; }
        
        /* 响应式 */
        @media (max-width: 768px) {
            .email-meta { flex-direction: column; gap: 2px; }
            .email-actions { opacity: 1; } /* 手机版常显 */
        }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?> 

    <div class="main-content" id="mainContent">
        
        <?php 
        // 这里的 admin_header.php 会自动使用上方第2步定义的 $adminName 等变量
        include 'admin_header.php'; 
        ?> 

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

                    <form method="POST" onsubmit="return confirm('Are you sure you want to clear all notifications?');" style="display:inline;">
                        <button type="submit" name="delete_all" class="btn-tool btn-clear">
                            <i class="fas fa-trash"></i> Clear All
                        </button>
                    </form>
                </div>
            </div>

            <div class="email-list">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        // 逻辑：判断图标颜色
                        $type = strtolower($row['Type']);
                        $iconClass = 'fa-bell'; $bgClass = 'bg-gray';
                        
                        if(strpos($type, 'donation') !== false) { 
                            $iconClass = 'fa-hand-holding-usd'; $bgClass = 'bg-green'; 
                        } elseif(strpos($type, 'fail') !== false) { 
                            $iconClass = 'fa-times-circle'; $bgClass = 'bg-red'; 
                        } elseif(strpos($type, 'contact') !== false || strpos($type, 'new_contact') !== false) { 
                            $iconClass = 'fa-envelope-open-text'; $bgClass = 'bg-blue'; 
                        } elseif(strpos($type, 'target') !== false || strpos($type, 'case') !== false) { 
                            $iconClass = 'fa-trophy'; $bgClass = 'bg-yellow'; 
                        } elseif(strpos($type, 'user') !== false) {
                            $iconClass = 'fa-user'; $bgClass = 'bg-purple';
                        }
                    ?>
                    
                    <div class="email-item <?php echo $row['Is_Read'] == 0 ? 'unread' : ''; ?>">
                        
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
                            
                            <?php if (!empty($row['ContactEmail'])): ?>
                                <a href="mailto:<?php echo $row['ContactEmail']; ?>?subject=RE: <?php echo htmlspecialchars($row['ContactSubject']); ?>&body=Dear <?php echo htmlspecialchars($row['ContactName']); ?>,%0D%0A%0D%0ARegarding your message:%0D%0A> <?php echo htmlspecialchars($row['Message']); ?>%0D%0A%0D%0A" 
                                   class="action-btn reply" title="Reply via Email">
                                    <i class="fas fa-reply"></i>
                                </a>
                            <?php endif; ?>

                            <form method="POST" onsubmit="return confirm('Delete this notification?');" style="margin:0;">
                                <input type="hidden" name="delete_id" value="<?php echo $row['AdminNotification_ID']; ?>">
                                <button type="submit" class="action-btn delete" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
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
    
</body>
</html>