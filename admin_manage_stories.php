<?php
// admin_manage_stories.php
session_start();

// --- 检查是否登录 ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- 获取当前用户信息 ---
$adminName = "User";
$adminPosition = "Role";
$adminProfilePicture = null;
$currentUserRole = 'Staff';

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
        $currentUserRole = $row['Admin_Role'];
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

// 消息变量
$successMsg = "";
$errorMsg = "";

// --- 多图上传处理函数 ---
function handleMultiUpload($files) {
    $paths = [];
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $dir = 'uploads/stories/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $fileCount = count($files['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] == 0 && in_array($files['type'][$i], $allowed)) {
            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $name = 'story_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $dir . $name)) {
                $paths[] = $dir . $name;
            }
        }
    }
    return $paths;
}

// --- 处理删除 Story ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // 获取图片路径
    $imgQ = $conn->query("SELECT Story_Image FROM story WHERE Story_ID = $id");
    if($imgR = $imgQ->fetch_assoc()){
        $imgData = $imgR['Story_Image'];
        $paths = json_decode($imgData, true);
        
        // 兼容旧数据（单图）或新数据（数组）
        if (json_last_error() === JSON_ERROR_NONE && is_array($paths)) {
            foreach($paths as $path) { if(file_exists($path) && !empty($path)) unlink($path); }
        } else {
            if(file_exists($imgData) && !empty($imgData)) unlink($imgData);
        }
    }
    
    if($conn->query("DELETE FROM story WHERE Story_ID = $id")) {
        header("Location: admin_manage_stories.php?success=Story deleted successfully");
        exit();
    } else {
        header("Location: admin_manage_stories.php?error=Database error");
        exit();
    }
}

// 获取 URL 消息
if(isset($_GET['success'])) $successMsg = htmlspecialchars($_GET['success']);
if(isset($_GET['error'])) $errorMsg = htmlspecialchars($_GET['error']);

// --- 处理 POST 请求 (添加 & 编辑) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. 添加新 Story
    if (isset($_POST['add_story'])) {
        $title = $_POST['title'];
        $author = $_POST['author']; // [新增] 获取作者
        $desc = $_POST['description'];
        
        $jsonImages = "[]";
        if (isset($_FILES["images"])) {
            $uploadedPaths = handleMultiUpload($_FILES["images"]);
            // 限制最多10张
            if(count($uploadedPaths) > 10) $uploadedPaths = array_slice($uploadedPaths, 0, 10);
            $jsonImages = json_encode($uploadedPaths);
        }

        if(empty($errorMsg)) {
            // 检查是否有 Created_At 列
            $checkCol = $conn->query("SHOW COLUMNS FROM story LIKE 'Created_At'");
            if($checkCol && $checkCol->num_rows > 0) {
                // [修改] SQL 插入 Author
                $sql = "INSERT INTO story (Story_Title, Story_Author, Story_Description, Story_Image, Created_At) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                // [修改] bind_param "ssss"
                $stmt->bind_param("ssss", $title, $author, $desc, $jsonImages);
            } else {
                $sql = "INSERT INTO story (Story_Title, Story_Author, Story_Description, Story_Image) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $title, $author, $desc, $jsonImages);
            }
            
            if ($stmt->execute()) {
                header("Location: admin_manage_stories.php?success=Story published successfully!");
                exit();
            } else {
                $errorMsg = "Database error: " . $conn->error;
            }
        }
    }

    // 2. 编辑 Story
    if (isset($_POST['update_story'])) {
        $storyId = intval($_POST['story_id']);
        $title = $_POST['edit_title'];
        $author = $_POST['edit_author']; // [新增] 获取编辑的作者
        $desc = $_POST['edit_description'];

        // 获取剩余的旧图片
        $remainingExistingImagesJson = $_POST['existing_images_json'];
        $remainingExistingImages = json_decode($remainingExistingImagesJson, true) ?? [];

        // 处理新上传的图片
        $newUploadedPaths = [];
        if (isset($_FILES["edit_images"])) {
            $newUploadedPaths = handleMultiUpload($_FILES["edit_images"]);
        }

        // 合并：旧图 + 新图
        $finalImages = array_merge($remainingExistingImages, $newUploadedPaths);
        if(count($finalImages) > 10) $finalImages = array_slice($finalImages, 0, 10);
        
        $jsonImages = json_encode($finalImages);

        // [修改] SQL 更新 Author
        $updateSql = "UPDATE story SET Story_Title = ?, Story_Author = ?, Story_Description = ?, Story_Image = ? WHERE Story_ID = ?";
        $stmt = $conn->prepare($updateSql);
        // [修改] bind_param "ssssi"
        $stmt->bind_param("ssssi", $title, $author, $desc, $jsonImages, $storyId);

        if ($stmt->execute()) {
            header("Location: admin_manage_stories.php?success=Story updated successfully!");
            exit();
        } else {
            $errorMsg = "Update failed: " . $conn->error;
        }
    }
}

// 准备 Lightbox 数据数组
$galleryData = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stories - Love Bridge Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Page Header */
        .page-header { margin-bottom: 30px; }
        .page-header h2 { font-size: 26px; font-weight: 700; color: var(--dark); margin-bottom: 8px; }
        .page-header p { color: var(--gray); font-size: 15px; }

        /* Layout Grid */
        .content-grid { display: grid; grid-template-columns: 400px 1fr; gap: 30px; align-items: start;}
        @media (max-width: 1100px) { .content-grid { grid-template-columns: 1fr; } }

        /* Card Style */
        .card { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); border: 1px solid rgba(0,0,0,0.02); overflow: hidden; }
        .card-header { padding: 25px 30px; border-bottom: 1px solid #f0f2f5; display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { font-size: 18px; font-weight: 700; color: var(--dark); display: flex; align-items: center; gap: 12px; margin: 0;}
        .card-header h3 i { color: var(--primary); background: rgba(242, 133, 133, 0.1); padding: 8px; border-radius: 8px; }
        .card-body { padding: 30px; }

        /* Form */
        .form-group { margin-bottom: 25px; }
        .form-label { display: block; margin-bottom: 10px; font-weight: 600; font-size: 14px; color: var(--dark); }
        .form-control { width: 100%; padding: 14px 16px; border: 2px solid #eee; border-radius: 10px; font-size: 14px; transition: 0.3s; outline: none; }
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 5px 15px rgba(242, 133, 133, 0.1); }
        textarea.form-control { resize: vertical; min-height: 130px; line-height: 1.6; }
        
        /* Upload Styles */
        .upload-container { width: 100%; }
        .upload-box {
            border: 2px dashed #ccc;
            background: #fafafa;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .upload-box:hover { background: #fff5f5; border-color: var(--primary); }
        .upload-box i { font-size: 32px; color: #aaa; margin-bottom: 10px; display: block; }
        .upload-box p { margin: 0; font-size: 13px; color: #666; font-weight: 500; }
        .upload-box .upload-hint { font-size: 11px; color: #999; margin-top: 5px; }
        
        .upload-box input[type="file"] {
            position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; z-index: 10;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        .preview-item {
            position: relative;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border: 1px solid #eee;
        }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .remove-img-btn {
            position: absolute; top: 4px; right: 4px;
            background: #ff4d4d; color: white;
            border: none; border-radius: 50%;
            width: 20px; height: 20px;
            font-size: 10px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            z-index: 10; transition: 0.2s;
        }
        .remove-img-btn:hover { background: #cc0000; transform: scale(1.1); }

        /* Submit Button */
        .btn-submit {
            background: var(--primary); color: white; border: none; padding: 15px 30px;
            border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 16px;
            width: 100%; transition: all 0.3s; box-shadow: 0 5px 15px rgba(242, 133, 133, 0.3);
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-submit:hover { background: #d66565; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(242, 133, 133, 0.4); }

        /* Table */
        .table-responsive { overflow-x: auto; min-height: 400px;}
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th { text-align: left; padding: 18px 20px; background-color: #f8f9fa; color: #6c757d; font-weight: 700; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #eee; }
        td { padding: 20px; vertical-align: middle; border-bottom: 1px solid #f0f2f5; }
        tbody tr:hover td { background-color: #fafafa; }

        .story-img-box {
            width: 80px; height: 60px; border-radius: 8px; overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); background: #f0f2f5; 
            display: flex; align-items: center; justify-content: center;
            position: relative; cursor: pointer; transition: transform 0.2s;
        }
        .story-img-box:hover { transform: scale(1.05); }
        .story-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .img-count-badge {
            position: absolute; bottom: 2px; right: 2px; background: rgba(0,0,0,0.6); 
            color: white; font-size: 10px; padding: 2px 6px; border-radius: 4px;
        }
        
        /* Story Styles */
        .story-title { font-weight: 700; font-size: 15px; color: var(--dark); margin-bottom: 4px; }
        .story-author { font-size: 12px; color: var(--primary); margin-bottom: 6px; font-weight: 600; display: block; }
        .story-desc { 
            font-size: 13px; color: var(--gray); line-height: 1.5; max-width: 450px; 
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden; text-overflow: ellipsis; white-space: normal;
        }

        .story-date { font-size: 13px; color: #888; font-weight: 500; white-space: nowrap;}
        
        /* Action Menu */
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { width: 32px; height: 32px; border-radius: 50%; background: white; border: 1px solid #eee; box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: #777; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .menu-btn:hover { background: #f8f9fa; color: var(--primary); transform: translateY(-1px); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 160px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; border: 1px solid #eee; overflow: hidden; }
        .dropdown-content div { padding: 12px 16px; cursor: pointer; display: flex; align-items: center; gap: 10px; font-size: 13px; color: #333; transition: 0.1s;}
        .dropdown-content div:hover { background-color: #f8f9fa; color: var(--primary); }
        .text-delete { color: var(--danger) !important; border-top: 1px solid #eee; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; animation: slideInModal 0.3s; }
        @keyframes slideInModal { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #aaa; transition: 0.3s; }
        .close-btn:hover { color: #333; }
        .modal-body { padding: 25px; }

        /* Lightbox */
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; } @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 2002; }
        .lightbox-nav { cursor: pointer; position: absolute; top: 50%; width: auto; padding: 16px; margin-top: -50px; color: white; font-weight: bold; font-size: 30px; transition: 0.6s ease; border-radius: 0 3px 3px 0; user-select: none; z-index: 2001; background-color: rgba(0,0,0,0.3); } .lightbox-nav:hover { background-color: rgba(255,255,255,0.2); } .lightbox-prev { left: 0; } .lightbox-next { right: 0; }
        
        /* Alert */
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
            <div class="page-header">
                <h2>Manage Success Stories</h2>
                <p>Share impactful stories and inspire the community.</p>
            </div>

            <div class="content-grid">
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus"></i> Publish New Story</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="storyForm">
                            <input type="hidden" name="add_story" value="1">
                            <div class="form-group">
                                <label class="form-label">Story Title</label>
                                <input type="text" name="title" class="form-control" required placeholder="e.g., A New Home for Shelly">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Story Author</label>
                                <input type="text" name="author" class="form-control" required placeholder="e.g., John Doe">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Story Details</label>
                                <textarea name="description" class="form-control" required placeholder="Share the details of the impact story here..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Featured Images (Max 10)</label>
                                <div class="upload-container">
                                    <div class="upload-box">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Click or Drag images here to upload</p>
                                        <div class="upload-hint">Supported: JPG, PNG. Max 10 images.</div>
                                        <input type="file" id="add_images" name="images[]" multiple accept="image/*" onchange="handleFileSelect(event, 'add')">
                                    </div>
                                    <div class="preview-grid" id="add_preview_container">
                                        </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Publish Now</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list-alt" style="color: var(--info); background: rgba(13, 202, 240, 0.1);"></i> Published Stories</h3>
                        <div style="font-size: 13px; color: var(--gray);">Total: <span id="story-count">Computing...</span></div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th width="100">Visual</th>
                                    <th>Story Information</th>
                                    <th>Published Date</th>
                                    <th width="80" style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $orderBy = "Story_ID DESC";
                                $checkCol = $conn->query("SHOW COLUMNS FROM story LIKE 'Created_At'");
                                if($checkCol && $checkCol->num_rows > 0) $orderBy = "Created_At DESC";

                                $query = "SELECT * FROM story ORDER BY $orderBy"; 
                                $result = $conn->query($query);
                                $count = 0;
                                
                                if ($result && $result->num_rows > 0):
                                    $count = $result->num_rows;
                                    while ($row = $result->fetch_assoc()):
                                        $dateStr = 'N/A';
                                        if(isset($row['Created_At']) && !empty($row['Created_At'])) {
                                            $dateStr = date('M d, Y, h:i A', strtotime($row['Created_At']));
                                        }

                                        // 图片解码
                                        $displayImg = "";
                                        $imgCount = 0;
                                        $imgData = $row['Story_Image'];
                                        $decoded = json_decode($imgData, true);
                                        
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            if (count($decoded) > 0) {
                                                $displayImg = $decoded[0];
                                                $imgCount = count($decoded);
                                            }
                                        } else {
                                            $displayImg = $imgData; 
                                            if (!empty($imgData)) $imgCount = 1;
                                        }

                                        $galleryData[] = (!empty($displayImg) && file_exists($displayImg)) ? $displayImg : 'https://placehold.co/400?text=No+Image';
                                        $currentIndex = count($galleryData) - 1; 
                                ?>
                                <tr>
                                    <td>
                                        <div class="story-img-box" onclick="openLightbox(<?php echo $currentIndex; ?>)">
                                            <?php if(!empty($displayImg) && file_exists($displayImg)): ?>
                                                <img src="<?php echo htmlspecialchars($displayImg); ?>" alt="Story Image">
                                                <?php if($imgCount > 1): ?>
                                                    <span class="img-count-badge">+<?php echo $imgCount-1; ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <i class="fas fa-image no-img-placeholder"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="story-title"><?php echo htmlspecialchars($row['Story_Title']); ?></div>
                                        <div class="story-author">By: <?php echo htmlspecialchars($row['Story_Author'] ?? 'Unknown'); ?></div>
                                        <div class="story-desc"><?php echo htmlspecialchars($row['Story_Description']); ?></div>
                                    </td>
                                    <td class="story-date">
                                        <i class="far fa-clock" style="margin-right: 5px; opacity: 0.7;"></i><?php echo $dateStr; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="action-menu">
                                            <button class="menu-btn" onclick="toggleMenu(event, <?php echo $row['Story_ID']; ?>)">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div id="menu-<?php echo $row['Story_ID']; ?>" class="dropdown-content">
                                                <div onclick="window.open('admin_story_details.php?id=<?php echo $row['Story_ID']; ?>','_blank')">
                                                    <i class="fas fa-eye"></i> View Details
                                                </div>
                                                <div onclick='openEditStoryModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)'>
                                                    <i class="fas fa-edit"></i> Edit Story
                                                </div>
                                                <div onclick="confirmDelete(<?php echo $row['Story_ID']; ?>)" class="text-delete">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="4" style="text-align: center; padding: 40px; color: var(--gray);">No stories published yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="editStoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Story</h2>
                <button class="close-btn" onclick="closeEditStoryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editStoryForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_story" value="1">
                    <input type="hidden" name="story_id" id="edit_story_id">
                    
                    <input type="hidden" name="existing_images_json" id="edit_existing_images_input">
                    
                    <div class="form-group">
                        <label class="form-label">Story Title</label>
                        <input type="text" id="edit_title" name="edit_title" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Story Author</label>
                        <input type="text" id="edit_author" name="edit_author" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Story Details</label>
                        <textarea id="edit_description" name="edit_description" class="form-control" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Manage Images (Max 10)</label>
                        <div class="upload-container">
                            <div class="upload-box">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Add more images</p>
                                <input type="file" id="edit_images" name="edit_images[]" multiple accept="image/*" onchange="handleFileSelect(event, 'edit')">
                            </div>
                            <div class="preview-grid" id="edit_preview_container"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn-submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="imageLightbox" class="lightbox-modal">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <a class="lightbox-nav lightbox-prev" onclick="changeLightboxImage(-1)">&#10094;</a>
        <img class="lightbox-content" id="lightboxImage">
        <a class="lightbox-nav lightbox-next" onclick="changeLightboxImage(1)">&#10095;</a>
    </div>

    <script>
        document.getElementById('story-count').innerText = "<?php echo $count; ?> Stories";

        // Auto Hide Alerts
        setTimeout(function() {
            const success = document.getElementById('floatingSuccess');
            const error = document.getElementById('floatingError');
            if (success) success.style.display = 'none';
            if (error) error.style.display = 'none';
        }, 5000);

        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete=' + id;
                }
            })
        }

        // --- MULTI-UPLOAD LOGIC ---
        let addFiles = []; 
        let editNewFiles = []; 
        let editExistingImages = []; 

        function handleFileSelect(event, mode) {
            const input = event.target;
            const newFiles = Array.from(input.files);
            
            if (mode === 'add') {
                if (addFiles.length + newFiles.length > 10) {
                    alert("You can only upload a maximum of 10 images.");
                    return;
                }
                addFiles = addFiles.concat(newFiles);
                updateFileInput('add_images', addFiles);
                renderPreview('add_preview_container', addFiles, 'add');
            } else if (mode === 'edit') {
                if (editExistingImages.length + editNewFiles.length + newFiles.length > 10) {
                    alert("Total images (existing + new) cannot exceed 10.");
                    return;
                }
                editNewFiles = editNewFiles.concat(newFiles);
                updateFileInput('edit_images', editNewFiles);
                renderEditPreviews();
            }
        }

        function removeFile(index, mode) {
            if (mode === 'add') {
                addFiles.splice(index, 1);
                updateFileInput('add_images', addFiles);
                renderPreview('add_preview_container', addFiles, 'add');
            } else if (mode === 'edit') {
                editNewFiles.splice(index, 1);
                updateFileInput('edit_images', editNewFiles);
                renderEditPreviews();
            }
        }

        function removeExistingImage(index) {
            editExistingImages.splice(index, 1);
            document.getElementById('edit_existing_images_input').value = JSON.stringify(editExistingImages);
            renderEditPreviews();
        }

        function updateFileInput(inputId, fileArray) {
            const dataTransfer = new DataTransfer();
            fileArray.forEach(file => dataTransfer.items.add(file));
            document.getElementById(inputId).files = dataTransfer.files;
        }

        function renderPreview(containerId, fileArray, mode) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            
            fileArray.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.innerHTML = `
                        <img src="${e.target.result}">
                        <button type="button" class="remove-img-btn" onclick="removeFile(${index}, '${mode}')"><i class="fas fa-times"></i></button>
                    `;
                    container.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        }

        function renderEditPreviews() {
            const container = document.getElementById('edit_preview_container');
            container.innerHTML = '';

            editExistingImages.forEach((src, index) => {
                const item = document.createElement('div');
                item.className = 'preview-item';
                item.innerHTML = `
                    <img src="${src}">
                    <button type="button" class="remove-img-btn" onclick="removeExistingImage(${index})"><i class="fas fa-times"></i></button>
                `;
                container.appendChild(item);
            });

            editNewFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.innerHTML = `
                        <img src="${e.target.result}">
                        <button type="button" class="remove-img-btn" onclick="removeFile(${index}, 'edit')"><i class="fas fa-times"></i></button>
                    `;
                    container.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        }

        // --- UI Logic ---
        function toggleMenu(e, id) {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
            const menu = document.getElementById('menu-' + id);
            if (menu.style.display === 'block') menu.style.display = 'none';
            else menu.style.display = 'block';
        }

        function openEditStoryModal(story) {
            document.getElementById('edit_story_id').value = story.Story_ID;
            document.getElementById('edit_title').value = story.Story_Title;
            
            // 填充 Author, 防止 null 报错
            document.getElementById('edit_author').value = story.Story_Author || '';
            
            document.getElementById('edit_description').value = story.Story_Description;
            
            // 重置新文件
            editNewFiles = [];
            updateFileInput('edit_images', []);

            // 解析旧图片
            try {
                editExistingImages = JSON.parse(story.Story_Image);
                if (!Array.isArray(editExistingImages)) {
                    editExistingImages = story.Story_Image ? [story.Story_Image] : [];
                }
            } catch(e) {
                editExistingImages = story.Story_Image ? [story.Story_Image] : [];
            }

            document.getElementById('edit_existing_images_input').value = JSON.stringify(editExistingImages);
            
            renderEditPreviews();
            document.getElementById('editStoryModal').style.display = 'flex';
        }

        function closeEditStoryModal() {
            document.getElementById('editStoryModal').style.display = 'none';
        }

        // --- Lightbox Logic ---
        const galleryImages = <?php echo json_encode($galleryData); ?>;
        let currentImageIndex = 0;

        function openLightbox(index) {
            if(galleryImages.length === 0) return;
            currentImageIndex = index;
            document.getElementById('imageLightbox').style.display = "flex";
            updateLightboxImage();
        }

        function closeLightbox() {
            document.getElementById('imageLightbox').style.display = "none";
        }

        function changeLightboxImage(n) {
            currentImageIndex += n;
            if (currentImageIndex >= galleryImages.length) currentImageIndex = 0;
            else if (currentImageIndex < 0) currentImageIndex = galleryImages.length - 1;
            updateLightboxImage();
        }

        function updateLightboxImage() {
            document.getElementById('lightboxImage').src = galleryImages[currentImageIndex];
            if(galleryImages.length <= 1) {
                document.querySelector('.lightbox-prev').style.display = 'none';
                document.querySelector('.lightbox-next').style.display = 'none';
            } else {
                document.querySelector('.lightbox-prev').style.display = 'block';
                document.querySelector('.lightbox-next').style.display = 'block';
            }
        }

        window.addEventListener('click', function(event) {
            if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) {
                document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
            }
            if (event.target == document.getElementById('imageLightbox')) closeLightbox();
            if (event.target == document.getElementById('editStoryModal')) closeEditStoryModal();
        });

        document.addEventListener('keydown', function(event) {
            if (document.getElementById('imageLightbox').style.display === "flex") {
                if (event.key === "Escape") closeLightbox();
                if (event.key === "ArrowLeft") changeLightboxImage(-1);
                if (event.key === "ArrowRight") changeLightboxImage(1);
            }
        });
    </script>
</body>
</html>