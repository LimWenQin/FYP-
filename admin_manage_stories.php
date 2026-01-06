<?php
session_start();
include 'dataconnection.php';

// 检查登录 (保持不变)
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 获取管理员信息 (保持不变)
$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminProfilePicture = $_SESSION['admin_pic'] ?? '';
$adminPosition = 'Admin';

// 同步 Header 信息 (保持不变)
$stmt = $conn->prepare("SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $adminName = $row['Admin_Name'];
    $adminProfilePicture = $row['Admin_ProfilePicture'];
    $adminPosition = $row['Admin_Role'];
}

$message = "";

// 处理删除 (保持不变)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $imgQ = $conn->query("SELECT Story_Image FROM story WHERE Story_ID = $id");
    if($imgR = $imgQ->fetch_assoc()){
        if(file_exists($imgR['Story_Image']) && !empty($imgR['Story_Image'])) unlink($imgR['Story_Image']);
    }
    
    $conn->query("DELETE FROM story WHERE Story_ID = $id");
    header("Location: admin_manage_stories.php?msg=deleted");
    exit();
}

if(isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
    $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Story deleted successfully.</div>";
}

// 处理添加新 Story (保持不变)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    
    $target_dir = "uploads/stories/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    
    $imagePath = "";
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $fileName;
        if(move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $imagePath = $target_file;
        } else {
             $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Image upload failed. Please check folder permissions.</div>";
        }
    }

    if(empty($message)) {
        // 假设数据库有 Created_At 字段
        $sql = "INSERT INTO story (Story_Title, Story_Description, Story_Image, Created_At) VALUES (?, ?, ?, NOW())";
        // 如果你的数据库没有 Created_At 字段，请使用下面这行:
        // $sql = "INSERT INTO story (Story_Title, Story_Description, Story_Image) VALUES (?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $title, $desc, $imagePath);
        
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Story published successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Database error: " . $conn->error . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stories - Love Bridge Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Page Header */
        .page-header { margin-bottom: 30px; }
        .page-header h2 { font-size: 26px; font-weight: 700; color: var(--dark); margin-bottom: 8px; }
        .page-header p { color: var(--gray); font-size: 15px; }

        /* Layout Grid */
        .content-grid { display: grid; grid-template-columns: 400px 1fr; gap: 30px; align-items: start;}
        @media (max-width: 1100px) { .content-grid { grid-template-columns: 1fr; } }

        /* Modern Card Style */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0,0,0,0.02);
            overflow: hidden;
        }
        .card-header {
            padding: 25px 30px;
            border-bottom: 1px solid #f0f2f5;
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-header h3 { font-size: 18px; font-weight: 700; color: var(--dark); display: flex; align-items: center; gap: 12px; margin: 0;}
        .card-header h3 i { color: var(--primary); background: rgba(242, 133, 133, 0.1); padding: 8px; border-radius: 8px; }
        .card-body { padding: 30px; }

        /* Form Elements Refined */
        .form-group { margin-bottom: 25px; }
        .form-label { display: block; margin-bottom: 10px; font-weight: 600; font-size: 14px; color: var(--dark); }
        .form-control {
            width: 100%; padding: 14px 16px;
            border: 2px solid #eee; border-radius: 10px;
            font-size: 14px; transition: all 0.3s ease;
            background: #fdfdfd; outline: none;
        }
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 5px 15px rgba(242, 133, 133, 0.1); }
        textarea.form-control { resize: vertical; min-height: 130px; line-height: 1.6; }
        
        /* Enhanced File Upload Area */
        .file-upload-container { position: relative; }
        .file-upload-wrapper {
            position: relative;
            border: 2px dashed #e0e0e0; border-radius: 12px;
            padding: 40px 20px; text-align: center;
            cursor: pointer; transition: all 0.3s ease;
            background: #fcfcfc;
        }
        .file-upload-wrapper:hover, .file-upload-wrapper.dragover { border-color: var(--primary); background: #fff5f5; }
        .file-upload-wrapper input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;}
        .upload-icon-box { font-size: 40px; color: var(--primary); margin-bottom: 15px; opacity: 0.8; }
        .upload-text { font-weight: 600; color: var(--dark); margin-bottom: 5px; }
        .upload-hint { font-size: 12px; color: var(--gray); }

        /* Image Preview */
        #preview-container {
            margin-top: 20px; display: none;
            border-radius: 10px; overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: relative;
        }
        #preview-image { width: 100%; height: auto; display: block; object-fit: cover; max-height: 250px;}
        .remove-image-btn {
            position: absolute; top: 10px; right: 10px;
            background: rgba(0,0,0,0.5); color: white;
            border: none; border-radius: 50%; width: 30px; height: 30px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .remove-image-btn:hover { background: var(--danger); }

        /* Submit Button */
        .btn-submit {
            background: var(--primary); color: white;
            border: none; padding: 15px 30px;
            border-radius: 10px; cursor: pointer;
            font-weight: 700; font-size: 16px;
            width: 100%; transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(242, 133, 133, 0.3);
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-submit:hover { background: #d66565; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(242, 133, 133, 0.4); }

        /* Modern Table Styles */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th {
            text-align: left; padding: 18px 20px;
            background-color: #f8f9fa; color: #6c757d;
            font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.8px;
            border-bottom: 1px solid #eee;
        }
        td { padding: 20px; vertical-align: middle; border-bottom: 1px solid #f0f2f5; transition: background 0.2s; }
        tbody tr:hover td { background-color: #fafafa; }
        tbody tr:last-child td { border-bottom: none; }

        /* Table Content Styling */
        .story-img-box {
            width: 80px; height: 60px;
            border-radius: 8px; overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            background: #f0f2f5; display: flex; align-items: center; justify-content: center;
        }
        .story-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .no-img-placeholder { color: #aaa; font-size: 20px; }
        
        .story-title { font-weight: 700; font-size: 15px; color: var(--dark); margin-bottom: 6px; }
        .story-desc { font-size: 13px; color: var(--gray); line-height: 1.5; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .story-date { font-size: 13px; color: #888; font-weight: 500; white-space: nowrap;}

        /* Delete Action Icon Button */
        .btn-delete-icon {
            width: 36px; height: 36px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: rgba(220, 53, 69, 0.1); color: var(--danger);
            text-decoration: none; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .btn-delete-icon:hover { background: var(--danger); color: white; transform: scale(1.15); }
        
        /* Alerts */
        .alert { padding: 16px 20px; margin-bottom: 25px; border-radius: 12px; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .alert-danger { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .alert i { font-size: 18px; }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="page-header">
                <h2>Manage Success Stories</h2>
                <p>Share impactful stories and inspire the community.</p>
            </div>

            <?php echo $message; ?>

            <div class="content-grid">
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus"></i> Publish New Story</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="storyForm">
                            <div class="form-group">
                                <label class="form-label">Story Title</label>
                                <input type="text" name="title" class="form-control" required placeholder="e.g., A New Home for Shelly">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Story Details</label>
                                <textarea name="description" class="form-control" required placeholder="Share the details of the impact story here..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Featured Image</label>
                                <div class="file-upload-container">
                                    <div class="file-upload-wrapper" id="drop-zone">
                                        <div class="upload-icon-box"><i class="fas fa-cloud-upload-alt"></i></div>
                                        <p class="upload-text">Drag & drop or click to upload</p>
                                        <p class="upload-hint">Recommended size: 800x600px (JPG, PNG)</p>
                                        <input type="file" name="image" id="imageInput" accept="image/jpeg, image/png, image/gif">
                                    </div>
                                    <div id="preview-container">
                                        <img id="preview-image" src="#" alt="Image Preview">
                                        <button type="button" class="remove-image-btn" onclick="removeImage()"><i class="fas fa-times"></i></button>
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
                                // 尝试按 Created_At 倒序，如果失败则按 ID 倒序
                                $orderBy = "Story_ID DESC";
                                $checkCol = $conn->query("SHOW COLUMNS FROM story LIKE 'Created_At'");
                                if($checkCol && $checkCol->num_rows > 0) {
                                    $orderBy = "Created_At DESC";
                                }

                                $query = "SELECT * FROM story ORDER BY $orderBy"; 
                                $result = $conn->query($query);
                                $count = 0;
                                
                                if ($result && $result->num_rows > 0):
                                    $count = $result->num_rows;
                                    while ($row = $result->fetch_assoc()):
                                        // 处理日期显示
                                        $dateStr = 'N/A';
                                        if(isset($row['Created_At']) && !empty($row['Created_At'])) {
                                            $dateStr = date('M d, Y', strtotime($row['Created_At']));
                                        }
                                ?>
                                <tr>
                                    <td>
                                        <div class="story-img-box">
                                            <?php if(!empty($row['Story_Image']) && file_exists($row['Story_Image'])): ?>
                                                <img src="<?php echo htmlspecialchars($row['Story_Image']); ?>" alt="Story Image">
                                            <?php else: ?>
                                                <i class="fas fa-image no-img-placeholder"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="story-title"><?php echo htmlspecialchars($row['Story_Title']); ?></div>
                                        <div class="story-desc" title="<?php echo htmlspecialchars($row['Story_Description']); ?>">
                                            <?php echo htmlspecialchars($row['Story_Description']); ?>
                                        </div>
                                    </td>
                                    <td class="story-date">
                                        <i class="far fa-calendar-alt" style="margin-right: 5px; opacity: 0.7;"></i>
                                        <?php echo $dateStr; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="?delete=<?php echo $row['Story_ID']; ?>" class="btn-delete-icon" onclick="return confirm('Are you sure you want to permanently delete this story?')" title="Delete Story">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile; 
                                else:
                                ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--gray);">
                                        <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i><br>
                                        No stories published yet. Start adding some!
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dropZone = document.getElementById('drop-zone');
        const imageInput = document.getElementById('imageInput');
        const previewContainer = document.getElementById('preview-container');
        const previewImage = document.getElementById('preview-image');

        // 更新计数 (一个小细节)
        document.getElementById('story-count').innerText = "<?php echo $count; ?> Stories";

        // Drag and drop styling
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('dragover');
        }
        function unhighlight(e) {
            dropZone.classList.remove('dragover');
        }

        // Handle file selection (via click or drop)
        imageInput.addEventListener('change', function(e) {
            handleFiles(this.files);
        });

        function handleFiles(files) {
            if (files && files[0]) {
                const file = files[0];
                if (!file.type.startsWith('image/')){
                     alert("Please select an image file.");
                     removeImage();
                     return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewContainer.style.display = 'block';
                    dropZone.style.display = 'none'; // Hide the upload box once image is selected
                }
                reader.readAsDataURL(file);
            }
        }

        // Function to remove selected image
        function removeImage() {
            imageInput.value = ''; // Clear the input file
            previewImage.src = '#';
            previewContainer.style.display = 'none';
            dropZone.style.display = 'block'; // Show the upload box again
        }
    </script>
</body>
</html>