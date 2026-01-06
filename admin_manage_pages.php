<?php
session_start();
include 'dataconnection.php'; 

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 获取管理员信息
$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminProfilePicture = $_SESSION['admin_pic'] ?? '';
$adminPosition = 'Admin';

// 同步 Header 信息
$stmt = $conn->prepare("SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $adminName = $row['Admin_Name'];
    $adminProfilePicture = $row['Admin_ProfilePicture'];
    $adminPosition = $row['Admin_Role'];
}

// 获取页面类型
$pageKey = isset($_GET['type']) ? $_GET['type'] : 'about_us';
$message = "";

// 处理保存
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = $_POST['content'];
    $key = $_POST['page_key'];

    // 假设表 system_pages 存在，且有 Page_Key, Page_Content, Last_Updated
    $stmt = $conn->prepare("UPDATE system_pages SET Page_Content = ?, Last_Updated = NOW() WHERE Page_Key = ?");
    $stmt->bind_param("ss", $content, $key);
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Page content updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Error updating content.</div>";
    }
}

// 获取现有内容
$stmt = $conn->prepare("SELECT * FROM system_pages WHERE Page_Key = ?");
$stmt->bind_param("s", $pageKey);
$stmt->execute();
$result = $stmt->get_result();
$pageData = $result->fetch_assoc();

if (!$pageData) {
    // 简单的错误处理，为了不破坏页面结构
    $pageData = ['Page_Title' => 'Unknown Page', 'Page_Content' => '', 'Last_Updated' => 'Never'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Page - <?php echo htmlspecialchars($pageData['Page_Title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* 页面特定样式 */
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h2 { font-size: 24px; font-weight: 600; color: #333; }
        .last-updated { font-size: 13px; color: var(--gray); background: white; padding: 5px 12px; border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }

        .editor-card { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 1000px; margin: 0 auto; }
        
        .form-group label { display: block; margin-bottom: 10px; font-weight: 600; color: var(--dark); font-size: 16px; }
        
        .content-editor { 
            width: 100%; 
            height: 500px; 
            padding: 20px; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            font-family: 'Courier New', Courier, monospace; /* 让代码或文本编辑更有感觉 */
            font-size: 14px; 
            line-height: 1.6;
            resize: vertical;
            outline: none;
            transition: border-color 0.3s;
        }
        .content-editor:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(242, 133, 133, 0.1); }

        .form-actions { margin-top: 25px; display: flex; justify-content: flex-end; gap: 15px; }
        
        .btn-save { 
            background: linear-gradient(135deg, #28a745, #218838); 
            color: white; 
            padding: 12px 30px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 15px; 
            font-weight: 600; 
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
            transition: transform 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(40, 167, 69, 0.3); }

        .btn-cancel {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #ddd;
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
        }
        .btn-cancel:hover { background: #e2e6ea; }

        .alert { padding: 15px; margin-bottom: 25px; border-radius: 8px; display: flex; align-items: center; gap: 10px; max-width: 1000px; margin-left: auto; margin-right: auto;}
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* 顶部 Tab 模拟 (可选，如果你想让用户知道他在编辑哪个页面) */
        .page-badge {
            background: var(--primary-light);
            color: var(--dark);
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            
            <div class="page-header" style="max-width: 1000px; margin: 0 auto 20px auto;">
                <div>
                    <h2>Edit Page Content <span class="page-badge"><?php echo htmlspecialchars($pageKey); ?></span></h2>
                </div>
                <div class="last-updated">
                    <i class="far fa-clock"></i> Last updated: <strong><?php echo $pageData['Last_Updated']; ?></strong>
                </div>
            </div>

            <?php echo $message; ?>

            <div class="editor-card">
                <form method="POST" action="">
                    <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($pageKey); ?>">
                    
                    <div class="form-group">
                        <label><i class="fas fa-code"></i> HTML / Text Content</label>
                        <p style="font-size: 13px; color: #888; margin-bottom: 10px;">
                            Edit the content below. You can use HTML tags for formatting.
                        </p>
                        <textarea name="content" class="content-editor" required placeholder="Enter page content here..."><?php echo htmlspecialchars($pageData['Page_Content']); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="admin_dashboard.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>