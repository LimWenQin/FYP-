<?php
// admin_manage_team.php
session_start();
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}
include 'dataconnection.php';

$successMsg = "";
$errorMsg = "";

// 1. 处理删除
if (isset($_GET['delete'])) {
    $delId = intval($_GET['delete']);
    $conn->query("DELETE FROM team_members WHERE Team_ID = $delId");
    header("Location: admin_manage_team.php?msg=deleted");
    exit();
}

// 2. 处理添加/更新
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $position = $_POST['position'];
    $desc = $_POST['description'];
    $id = isset($_POST['team_id']) ? intval($_POST['team_id']) : 0;
    
    // 获取现有的图片路径（如果有）
    $existing_image_json = isset($_POST['existing_image']) ? $_POST['existing_image'] : '';

    // 默认图片路径
    $defaultImage = json_encode(['images/default_user.jpg']);
    $finalImagePath = $existing_image_json; // 默认为现有图片

    // 处理新图片上传
    if (!empty($_FILES['photo']['name'])) {
        $targetDir = "images/"; 
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true); 
        
        $fileName = "team_" . time() . "_" . basename($_FILES['photo']['name']);
        $targetFilePath = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFilePath)) {
            $finalImagePath = json_encode([$targetFilePath]); // 如果上传成功，覆盖路径
        }
    }

    // 如果没有新上传，且现有的也被删除了（为空），则使用默认头像
    if (empty($finalImagePath)) {
        $finalImagePath = $defaultImage;
    }

    if ($id > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE team_members SET Name=?, Position=?, Description=?, Images=? WHERE Team_ID=?");
        $stmt->bind_param("ssssi", $name, $position, $desc, $finalImagePath, $id);
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO team_members (Name, Position, Description, Images) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $position, $desc, $finalImagePath);
    }

    if ($stmt->execute()) {
        $successMsg = "Team member saved successfully!";
    } else {
        $errorMsg = "Database Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Founder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Enhanced Page Styling */
        .page-header-area {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border-bottom: 3px solid #d32f2f;
        }
        .page-header-area h2 { margin: 0; color: #333; font-size: 24px; font-weight: 700; }
        .page-header-area p { margin: 5px 0 0; color: #666; font-size: 14px; }

        .btn-add-new {
            background: #d32f2f; color: white; border: none; padding: 12px 25px;
            border-radius: 50px; font-weight: 600; cursor: pointer;
            box-shadow: 0 4px 15px rgba(211, 47, 47, 0.3);
            transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;
        }
        .btn-add-new:hover { background: #b71c1c; transform: translateY(-2px); }

        /* Grid Design */
        .team-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; }
        
        .team-card {
            background: white; border-radius: 16px; overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: 0.3s;
            position: relative; border: 1px solid #eee;
            display: flex; flex-direction: column;
        }
        .team-card:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.1); border-color: #ffcdd2; }
        
        .team-img-wrapper {
            height: 240px; background: #f0f0f0; position: relative; overflow: hidden; cursor: pointer;
        }
        .team-img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .team-card:hover .team-img-wrapper img { transform: scale(1.05); }
        .team-img-wrapper::after {
            content: '\f00e'; /* Search/Zoom icon */
            font-family: "Font Awesome 5 Free"; font-weight: 900;
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            color: white; font-size: 30px; opacity: 0; transition: opacity 0.3s;
            text-shadow: 0 2px 5px rgba(0,0,0,0.5); pointer-events: none;
        }
        .team-img-wrapper:hover::after { opacity: 1; }
        
        .team-info { padding: 25px; flex-grow: 1; }
        .team-info h4 { margin: 0 0 5px; color: #222; font-size: 18px; font-weight: 700; }
        .team-info .role { 
            color: #d32f2f; font-size: 12px; font-weight: 700; 
            margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;
            background: #ffebee; display: inline-block; padding: 4px 10px; border-radius: 4px;
        }
        .team-info p { font-size: 14px; color: #555; line-height: 1.6; margin-bottom: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

        .team-actions {
            padding: 15px 25px; border-top: 1px solid #f0f0f0;
            display: flex; gap: 10px; background: #fafafa;
        }
        .action-btn {
            flex: 1; text-align: center; padding: 10px; border-radius: 8px;
            font-size: 13px; font-weight: 600; text-decoration: none; transition: 0.2s;
        }
        .btn-edit { background: white; color: #333; border: 1px solid #ddd; }
        .btn-edit:hover { background: #333; color: white; border-color: #333; }
        .btn-del { background: #ffebee; color: #d32f2f; border: 1px solid #ffcdd2; }
        .btn-del:hover { background: #d32f2f; color: white; border-color: #d32f2f; }

        /* Modal Design */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); z-index: 2000; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s; }
        .modal.active { display: flex; opacity: 1; }
        
        .modal-content { 
            background: white; padding: 40px; border-radius: 20px; 
            width: 550px; max-width: 90%; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            transform: scale(0.9); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .modal.active .modal-content { transform: scale(1); }
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .modal-header h3 { margin: 0; font-size: 22px; color: #333; }
        .close-modal { cursor: pointer; font-size: 24px; color: #999; transition: 0.2s; }
        .close-modal:hover { color: #333; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; font-size: 14px; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; transition: 0.2s; }
        .form-control:focus { border-color: #d32f2f; outline: none; box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1); }
        textarea.form-control { resize: vertical; min-height: 100px; }

        /* Edit Modal Image Preview Styles */
        .file-upload-wrapper {
            border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 8px; cursor: pointer; background: #fafafa;
        }
        .file-upload-wrapper:hover { border-color: #d32f2f; background: #fff; }
        
        .current-img-container {
            position: relative; display: inline-block; margin-bottom: 15px;
            border: 1px solid #eee; padding: 5px; border-radius: 8px; background: white;
        }
        .current-img-preview {
            width: 100px; height: 100px; object-fit: cover; border-radius: 6px; display: block;
        }
        .remove-img-btn {
            position: absolute; top: -10px; right: -10px;
            background: #d32f2f; color: white; border-radius: 50%;
            width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .remove-img-btn:hover { background: #b71c1c; transform: scale(1.1); }

        .modal-footer { margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px; }
        .btn-modal-cancel { padding: 12px 25px; border: none; background: #eee; color: #555; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-modal-save { padding: 12px 25px; border: none; background: #d32f2f; color: white; border-radius: 8px; cursor: pointer; font-weight: 600; box-shadow: 0 4px 10px rgba(211, 47, 47, 0.2); }

        /* Lightbox (Image Viewer) Styles */
        .image-viewer-modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9); z-index: 3000;
            justify-content: center; align-items: center;
            opacity: 0; transition: opacity 0.3s ease;
        }
        .image-viewer-modal.active { display: flex; opacity: 1; }
        
        .image-viewer-content {
            position: relative; max-width: 90%; max-height: 90%;
            animation: zoomIn 0.3s ease;
        }
        @keyframes zoomIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .image-viewer-content img {
            max-width: 100%; max-height: 90vh; border-radius: 5px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5); border: 2px solid white;
        }
        
        .close-viewer-btn {
            position: absolute; top: -40px; right: -40px;
            color: white; font-size: 35px; cursor: pointer;
            transition: 0.2s; background: transparent; border: none;
        }
        .close-viewer-btn:hover { color: #d32f2f; transform: scale(1.1); }

        /* Responsive adjustments for close button */
        @media (max-width: 768px) {
            .close-viewer-btn { top: -40px; right: 0; }
        }
    </style>
</head>
<body>

<?php include 'admin_sidebar.php'; ?>

<div class="main-content">
    <?php include 'admin_header.php'; ?>
    
    <div class="dashboard-content">
        <div class="page-header-area">
            <div>
                <h2>Manage Founder</h2>
                <p>Add, edit, or remove team members displayed on the "About Us" page.</p>
            </div>
            <button class="btn-add-new" onclick="openModal()">
                <i class="fas fa-plus"></i> Add Founder
            </button>
        </div>

        <?php if($successMsg) echo "<div style='background:#e8f5e9; color:#2e7d32; padding:15px; border-radius:8px; margin-bottom:20px; border-left:5px solid #2e7d32;'><i class='fas fa-check-circle'></i> $successMsg</div>"; ?>

        <div class="team-grid">
            <?php
            $res = $conn->query("SELECT * FROM team_members ORDER BY Created_At DESC");
            if ($res && $res->num_rows > 0):
                while($row = $res->fetch_assoc()):
                    // Parse image JSON
                    $img = 'images/default_user.jpg';
                    if(!empty($row['Images'])) {
                        $decoded = json_decode($row['Images'], true);
                        if(is_array($decoded) && !empty($decoded)) $img = $decoded[0];
                    }
            ?>
            <div class="team-card">
                <div class="team-img-wrapper" onclick="openImageViewer('<?php echo htmlspecialchars($img); ?>')">
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="Team">
                </div>
                <div class="team-info">
                    <h4><?php echo htmlspecialchars($row['Name']); ?></h4>
                    <span class="role"><?php echo htmlspecialchars($row['Position']); ?></span>
                    <p><?php echo htmlspecialchars($row['Description']); ?></p>
                </div>
                <div class="team-actions">
                    <a href="#" class="action-btn btn-edit" onclick='editMember(<?php echo json_encode($row); ?>)'>
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="?delete=<?php echo $row['Team_ID']; ?>" class="action-btn btn-del" onclick="return confirm('Delete this member?')">
                        <i class="fas fa-trash-alt"></i> Delete
                    </a>
                </div>
            </div>
            <?php 
                endwhile; 
            else:
            ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 10px; color: #888;">
                    <i class="fas fa-users-slash" style="font-size: 40px; margin-bottom: 15px; color: #ddd;"></i>
                    <p>No team members found. Click "Add Member" to get started.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal" id="teamModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Team Member</h3>
            <div class="close-modal" onclick="closeModal()">&times;</div>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="team_id" id="team_id">
            <input type="hidden" name="existing_image" id="existing_image">
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" id="name" class="form-control" placeholder="e.g. John Doe" required>
            </div>
            
            <div class="form-group">
                <label>Position / Role</label>
                <input type="text" name="position" id="position" class="form-control" placeholder="e.g. Executive Director" required>
            </div>
            
            <div class="form-group">
                <label>Description (Bio)</label>
                <textarea name="description" id="description" class="form-control" placeholder="Brief introduction..." required></textarea>
            </div>
            
            <div class="form-group">
                <label>Profile Photo</label>
                
                <div id="currentPhotoContainer" class="current-img-container" style="display:none;">
                    <img id="currentPhotoPreview" src="" class="current-img-preview">
                    <div class="remove-img-btn" onclick="removeCurrentPhoto()" title="Remove current photo">
                        <i class="fas fa-times"></i>
                    </div>
                </div>

                <div class="file-upload-wrapper" onclick="document.getElementById('photoInput').click()">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: #d32f2f; margin-bottom: 10px;"></i>
                    <p style="margin:0; font-size:13px; color:#666;">Click to upload new image</p>
                    <input type="file" name="photo" id="photoInput" class="form-control" accept="image/*" style="display:none;" onchange="previewFile()">
                    <div id="filePreview" style="margin-top:10px; font-size:12px; color:green;"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-modal-save">Save Member</button>
            </div>
        </form>
    </div>
</div>

<div class="image-viewer-modal" id="imageViewer">
    <div class="image-viewer-content">
        <button class="close-viewer-btn" onclick="closeImageViewer()">
            <i class="fas fa-times"></i>
        </button>
        <img id="viewerImage" src="" alt="Full View">
    </div>
</div>

<script>
    // --- Add/Edit Modal Functions ---
    function openModal() {
        document.getElementById('teamModal').classList.add('active');
        document.getElementById('modalTitle').innerText = 'Add Team Member';
        
        // Reset form
        document.getElementById('team_id').value = '';
        document.getElementById('name').value = '';
        document.getElementById('position').value = '';
        document.getElementById('description').value = '';
        document.getElementById('photoInput').value = '';
        document.getElementById('filePreview').innerText = '';
        
        // Hide existing image preview
        document.getElementById('existing_image').value = '';
        document.getElementById('currentPhotoContainer').style.display = 'none';
        document.getElementById('currentPhotoPreview').src = '';
    }
    
    function editMember(data) {
        document.getElementById('teamModal').classList.add('active');
        document.getElementById('modalTitle').innerText = 'Edit Team Member';
        
        // Fill form data
        document.getElementById('team_id').value = data.Team_ID;
        document.getElementById('name').value = data.Name;
        document.getElementById('position').value = data.Position;
        document.getElementById('description').value = data.Description;
        
        // Handle Image
        let imgPath = '';
        if(data.Images) {
            try {
                const images = JSON.parse(data.Images);
                if(images && images.length > 0) imgPath = images[0];
            } catch(e) { console.error(e); }
        }

        // Show existing image and hidden input
        if (imgPath) {
            document.getElementById('existing_image').value = data.Images; // keep original JSON string
            document.getElementById('currentPhotoPreview').src = imgPath;
            document.getElementById('currentPhotoContainer').style.display = 'inline-block';
        } else {
            document.getElementById('existing_image').value = '';
            document.getElementById('currentPhotoContainer').style.display = 'none';
        }

        document.getElementById('photoInput').value = '';
        document.getElementById('filePreview').innerText = '';
    }
    
    function removeCurrentPhoto() {
        // 清空隐藏的 existing_image 字段
        document.getElementById('existing_image').value = '';
        // 隐藏预览区域
        document.getElementById('currentPhotoContainer').style.display = 'none';
        // 清空 src 防止缓存显示
        document.getElementById('currentPhotoPreview').src = '';
    }

    function closeModal() {
        document.getElementById('teamModal').classList.remove('active');
    }

    function previewFile() {
        const input = document.getElementById('photoInput');
        if(input.files && input.files[0]) {
            document.getElementById('filePreview').innerText = 'Selected: ' + input.files[0].name;
        }
    }

    // --- Image Viewer (Lightbox) Functions ---
    function openImageViewer(src) {
        document.getElementById('viewerImage').src = src;
        document.getElementById('imageViewer').classList.add('active');
    }

    function closeImageViewer() {
        document.getElementById('imageViewer').classList.remove('active');
        setTimeout(() => {
            document.getElementById('viewerImage').src = '';
        }, 300); // Wait for transition
    }

    // --- Global Click Listeners ---
    window.onclick = function(event) {
        // Close Edit Modal on outside click
        const modal = document.getElementById('teamModal');
        if (event.target == modal) {
            closeModal();
        }

        // Close Image Viewer on outside click (clicking background)
        const viewer = document.getElementById('imageViewer');
        if (event.target == viewer) {
            closeImageViewer();
        }
    }
</script>

</body>
</html>