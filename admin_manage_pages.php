<?php
// admin_manage_pages.php
session_start();

// --- 修改 1: 检查是否登录 (Admin 或 Staff 均可) ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- 修改 2: 获取当前用户信息 (支持 Admin 和 Staff) ---
$adminName = "User";
$adminPosition = "Role";
$adminProfilePicture = null;

if (isset($_SESSION['admin_id'])) {
    $currentId = $_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = ?");
    $stmt->bind_param("i", $currentId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $adminName = $row['Admin_Name'];
        $adminProfilePicture = $row['Admin_ProfilePicture'];
        $adminPosition = $row['Admin_Role'];
    }
    $stmt->close();
} elseif (isset($_SESSION['staff_id'])) {
    $currentId = $_SESSION['staff_id'];
    $stmt = $conn->prepare("SELECT Staff_FullName, Staff_ProfilePicture, Staff_Role FROM staff WHERE Staff_ID = ?");
    $stmt->bind_param("i", $currentId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $adminName = $row['Staff_FullName'];
        $adminProfilePicture = $row['Staff_ProfilePicture'];
        $adminPosition = $row['Staff_Role'];
    }
    $stmt->close();
}

// 获取页面类型
$pageKey = isset($_GET['type']) ? $_GET['type'] : 'about_us';

// --- 逻辑：格式化标题 (例如 terms_conditions -> Terms & Conditions) ---
$displayTitle = ucwords(str_replace('_', ' ', $pageKey));
if (strtolower($pageKey) == 'terms_conditions') {
    $displayTitle = "Terms & Conditions";
} elseif (strtolower($pageKey) == 'about_us') {
    $displayTitle = "About Us";
} elseif (strtolower($pageKey) == 'contact_us') {
    $displayTitle = "Contact Us";
}

$successMsg = "";
$errorMsg = "";

// 处理保存
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = $_POST['content'];
    $key = $_POST['page_key'];

    $stmt = $conn->prepare("UPDATE system_pages SET Page_Content = ?, Last_Updated = NOW() WHERE Page_Key = ?");
    $stmt->bind_param("ss", $content, $key);
    
    if ($stmt->execute()) {
        $successMsg = "Page content updated successfully!";
    } else {
        $errorMsg = "Error updating content: " . $conn->error;
    }
}

// 获取现有内容
$stmt = $conn->prepare("SELECT * FROM system_pages WHERE Page_Key = ?");
$stmt->bind_param("s", $pageKey);
$stmt->execute();
$result = $stmt->get_result();
$pageData = $result->fetch_assoc();

if (!$pageData) {
    // 默认数据，防止报错
    $pageData = ['Page_Title' => $displayTitle, 'Page_Content' => '', 'Last_Updated' => 'Never'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Page - <?php echo htmlspecialchars($displayTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            font-family: 'Courier New', Courier, monospace; 
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
        
        /* 顶部 Tab 模拟 */
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

        /* Floating Alert Styles */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; max-width: 400px; display: none; animation: slideIn 0.3s; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>

    <div class="floating-alert floating-alert-success" id="floatingSuccess" style="display: <?php echo !empty($successMsg) ? 'flex' : 'none'; ?>">
        <i class="fas fa-check-circle"></i>
        <div id="floatingSuccessText"><?php echo $successMsg; ?></div>
    </div>

    <div class="floating-alert floating-alert-danger" id="floatingError" style="display: <?php echo !empty($errorMsg) ? 'flex' : 'none'; ?>">
        <i class="fas fa-exclamation-circle"></i>
        <div id="floatingErrorText"><?php echo $errorMsg; ?></div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            
            <div class="page-header" style="max-width: 1000px; margin: 0 auto 20px auto;">
                <div>
                    <h2>Edit Page Content <span class="page-badge"><?php echo htmlspecialchars($displayTitle); ?></span></h2>
                </div>
                <div class="last-updated">
                    <i class="far fa-clock"></i> Last updated: <strong><?php echo $pageData['Last_Updated']; ?></strong>
                </div>
            </div>

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

    <script>
        // Auto Hide Alerts
        setTimeout(function() {
            const success = document.getElementById('floatingSuccess');
            const error = document.getElementById('floatingError');
            if (success) success.style.display = 'none';
            if (error) error.style.display = 'none';
        }, 5000);
    </script>

</body>
</html>