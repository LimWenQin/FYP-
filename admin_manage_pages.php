<?php
// admin_manage_pages.php
session_start();

// --- 检查登录 ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// 获取页面类型
// 可用类型: about_us, terms_condition, privacy_policy, contact_messages, contact_settings
$pageKey = isset($_GET['type']) ? $_GET['type'] : 'about_us';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view'; // 'view' or 'edit'

// 防止空白页：如果旧链接传了 contact_us，强制转为 contact_settings
if ($pageKey == 'contact_us') {
    $pageKey = 'contact_settings';
}

// 页面显示标题逻辑
$displayTitle = ucwords(str_replace('_', ' ', $pageKey));

if ($pageKey == 'terms_condition') $displayTitle = "Terms & Conditions";
elseif ($pageKey == 'privacy_policy') $displayTitle = "Privacy Policy";
elseif ($pageKey == 'contact_messages') $displayTitle = "Contact Messages (Inbox)";
elseif ($pageKey == 'contact_settings') $displayTitle = "Contact Us (Settings)";

$successMsg = "";
$errorMsg = "";

// ========================================================
// 逻辑 1: About Us (about_us_info 表) - 保持不变
// ========================================================
if ($pageKey == 'about_us') {
    // 保存处理
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $mode == 'edit') {
        $hero_title = $_POST['hero_title'];
        $hero_desc = $_POST['hero_desc'];
        $story_title = $_POST['story_title'];
        $story_content = $_POST['story_content'];
        $vision_desc = $_POST['vision_desc'];
        $mission_desc = $_POST['mission_desc'];
        
        $vision_points = isset($_POST['vision_points']) ? json_encode(array_filter($_POST['vision_points'])) : '[]';
        $mission_points = isset($_POST['mission_points']) ? json_encode(array_filter($_POST['mission_points'])) : '[]';
        
        $values = [];
        if (isset($_POST['val_title'])) {
            for ($i = 0; $i < count($_POST['val_title']); $i++) {
                if (!empty($_POST['val_title'][$i])) {
                    $values[] = ['title' => $_POST['val_title'][$i], 'desc' => $_POST['val_desc'][$i]];
                }
            }
        }
        $values_json = json_encode($values);

        $focus = [];
        if (isset($_POST['focus_title'])) {
            for ($i = 0; $i < count($_POST['focus_title']); $i++) {
                if (!empty($_POST['focus_title'][$i])) {
                    $focus[] = ['title' => $_POST['focus_title'][$i], 'desc' => $_POST['focus_desc'][$i]];
                }
            }
        }
        $focus_json = json_encode($focus);

        // Update ID=1
        $check = $conn->query("SELECT id FROM about_us_info LIMIT 1");
        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE about_us_info SET hero_title=?, hero_description=?, story_title=?, story_content=?, vision_desc=?, vision_points=?, mission_desc=?, mission_points=?, core_values=?, focus_areas=?, updated_at=NOW() WHERE id=1");
            $stmt->bind_param("ssssssssss", $hero_title, $hero_desc, $story_title, $story_content, $vision_desc, $vision_points, $mission_desc, $mission_points, $values_json, $focus_json);
        } else {
            $stmt = $conn->prepare("INSERT INTO about_us_info (hero_title, hero_description, story_title, story_content, vision_desc, vision_points, mission_desc, mission_points, core_values, focus_areas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $hero_title, $hero_desc, $story_title, $story_content, $vision_desc, $vision_points, $mission_desc, $mission_points, $values_json, $focus_json);
        }

        if ($stmt->execute()) {
            $successMsg = "About Us content updated successfully!";
            $mode = 'view';
        } else {
            $errorMsg = "Error updating: " . $stmt->error;
        }
    }

    // 获取数据
    $res = $conn->query("SELECT * FROM about_us_info LIMIT 1");
    $aboutData = $res->fetch_assoc();
    if (!$aboutData) {
        $aboutData = ['hero_title'=>'', 'hero_description'=>'', 'story_title'=>'', 'story_content'=>'', 'vision_desc'=>'', 'vision_points'=>'[]', 'mission_desc'=>'', 'mission_points'=>'[]', 'core_values'=>'[]', 'focus_areas'=>'[]'];
    }
    
    $vPoints = json_decode($aboutData['vision_points'], true) ?? [];
    $mPoints = json_decode($aboutData['mission_points'], true) ?? [];
    $coreValues = json_decode($aboutData['core_values'], true) ?? [];
    $focusAreas = json_decode($aboutData['focus_areas'], true) ?? [];
}

// ========================================================
// 逻辑 2: Terms & Privacy (纯文本编辑模式)
// ========================================================
elseif ($pageKey == 'terms_condition' || $pageKey == 'privacy_policy') {
    $table = ($pageKey == 'terms_condition') ? 'terms_conditions' : 'privacy_policy';
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $mode == 'edit') {
        $content = $_POST['content'];
        $stmt = $conn->prepare("UPDATE $table SET content=?, created_at=NOW() WHERE is_active=1");
        $stmt->bind_param("s", $content);
        if ($stmt->execute()) {
            if ($stmt->affected_rows === 0) {
                $ins = $conn->prepare("INSERT INTO $table (version, content, effective_date, is_active) VALUES ('1.0', ?, CURDATE(), 1)");
                $ins->bind_param("s", $content);
                $ins->execute();
            }
            $successMsg = "$displayTitle updated successfully!";
            $mode = 'view';
        } else {
            $errorMsg = "Database error: " . $conn->error;
        }
    }
    
    $res = $conn->query("SELECT * FROM $table WHERE is_active=1 ORDER BY created_at DESC LIMIT 1");
    $docData = $res->fetch_assoc();
    $rawContent = $docData ? $docData['content'] : '';
    $cleanContentForEdit = strip_tags($rawContent); // 去除 HTML 用于编辑
    if(empty($cleanContentForEdit) && !empty($rawContent)) $cleanContentForEdit = $rawContent;
}

// ========================================================
// 逻辑 3: Contact Settings (contact_settings 表)
// ========================================================
elseif ($pageKey == 'contact_settings') {
    // 保存处理
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $mode == 'edit') {
        $address = $_POST['address'];
        $phone = $_POST['phone'];
        $whatsapp = $_POST['whatsapp'];
        $email = $_POST['email'];
        $hours = $_POST['hours'];
        $map_src = $_POST['map_src'];

        $check = $conn->query("SELECT Setting_ID FROM contact_settings LIMIT 1");
        
        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE contact_settings SET Address=?, Phone=?, Whatsapp_Link=?, Email=?, Working_Hours=?, Map_Embed_Src=?, Updated_At=NOW() LIMIT 1");
            $stmt->bind_param("ssssss", $address, $phone, $whatsapp, $email, $hours, $map_src);
        } else {
            $stmt = $conn->prepare("INSERT INTO contact_settings (Address, Phone, Whatsapp_Link, Email, Working_Hours, Map_Embed_Src) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $address, $phone, $whatsapp, $email, $hours, $map_src);
        }

        if ($stmt->execute()) {
            $successMsg = "Contact Settings updated successfully!";
            $mode = 'view';
        } else {
            $errorMsg = "Error updating: " . $stmt->error;
        }
    }

    $res = $conn->query("SELECT * FROM contact_settings LIMIT 1");
    $contactData = $res->fetch_assoc();
    if (!$contactData) {
        $contactData = ['Address'=>'', 'Phone'=>'', 'Whatsapp_Link'=>'', 'Email'=>'', 'Working_Hours'=>'', 'Map_Embed_Src'=>''];
    }
}

// ========================================================
// 逻辑 4: Contact Messages (contact_messages 表 - 只读 + 回复 + 附件)
// ========================================================
elseif ($pageKey == 'contact_messages') {
    // 标记为已读
    if(isset($_GET['mark_read'])) {
        $mid = intval($_GET['mark_read']);
        $conn->query("UPDATE contact_messages SET Status='Read' WHERE Contact_ID=$mid");
        // 重定向去掉参数
        echo "<script>window.location.href='admin_manage_pages.php?type=contact_messages';</script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage - <?php echo htmlspecialchars($displayTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .editor-card { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 1100px; margin: 0 auto; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; max-width: 1100px; margin: 0 auto 20px auto; }
        .page-header h2 { font-size: 24px; font-weight: 600; color: #333; }
        
        .form-section-title { font-size: 18px; color: #d32f2f; margin: 25px 0 15px; border-bottom: 2px solid #ffcdd2; padding-bottom: 5px; font-weight: bold; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        textarea.form-control { resize: vertical; min-height: 80px; font-family: inherit; line-height: 1.5; }
        
        .btn-save { background: linear-gradient(135deg, #28a745, #218838); color: white; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 15px; font-weight: 600; box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2); transition: 0.2s; }
        .btn-save:hover { transform: translateY(-2px); }
        .btn-edit-mode { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: 600; box-shadow: 0 4px 10px rgba(0,123,255,0.2); display: inline-flex; align-items: center; gap: 8px; }
        .btn-edit-mode:hover { background: #0056b3; }
        .btn-cancel { background: #f8f9fa; color: #666; border: 1px solid #ddd; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-size: 15px; font-weight: 500; display: inline-block; margin-right: 10px; }

        /* View Mode Styling */
        .view-field { background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #eee; margin-bottom: 20px; }
        .view-label { font-size: 12px; color: #888; text-transform: uppercase; font-weight: 700; margin-bottom: 5px; letter-spacing: 0.5px; }
        .view-value { color: #333; font-size: 15px; line-height: 1.6; white-space: pre-wrap; }
        .view-list-item { background: white; padding: 10px; border: 1px solid #eee; margin-bottom: 5px; border-radius: 4px; display: flex; gap: 10px; }
        .view-list-title { font-weight: bold; color: #d32f2f; min-width: 150px; }

        /* Contact Us Table */
        .msg-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .msg-table th, .msg-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; }
        .msg-table th { background: #f8f9fa; font-weight: 600; color: #555; }
        .badge-new { background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-read { background: #f5f5f5; color: #777; padding: 4px 8px; border-radius: 4px; font-size: 11px; }

        /* New Button Styles for Contact Messages */
        .btn-action-reply { color: #fff; background: #17a2b8; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; margin-right: 5px; }
        .btn-action-reply:hover { background: #138496; }
        .btn-action-read { color: #007bff; border: 1px solid #007bff; padding: 4px 9px; border-radius: 4px; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-action-read:hover { background: #e3f2fd; }
        .btn-attachment-view { color: #555; background: #f0f0f0; border: 1px solid #ddd; padding: 5px 10px; border-radius: 4px; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-attachment-view:hover { background: #e0e0e0; color: #333; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; background: white; color: #28a745; border-left: 4px solid #28a745; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
        
        /* Map Preview */
        .map-preview { width: 100%; height: 250px; background: #eee; display: flex; align-items: center; justify-content: center; color: #888; border-radius: 6px; overflow: hidden; }
        .map-preview iframe { width: 100%; height: 100%; border: 0; }
        .policy-content-view { padding: 20px; background: #fff; border: 1px solid #eee; border-radius: 5px; min-height: 200px; }
    </style>
</head>
<body>

    <?php if(!empty($successMsg)): ?>
    <div class="floating-alert">
        <i class="fas fa-check-circle"></i> <?php echo $successMsg; ?>
    </div>
    <script>setTimeout(() => { document.querySelector('.floating-alert').remove() }, 4000);</script>
    <?php endif; ?>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="page-header">
                <h2>Manage <span style="color:#d32f2f;"><?php echo htmlspecialchars($displayTitle); ?></span></h2>
                
                <?php if ($mode == 'view'): ?>
                    <?php if ($pageKey == 'about_us'): ?>
                        <a href="?type=about_us&mode=edit" class="btn-edit-mode"><i class="fas fa-edit"></i> Edit Content</a>
                    <?php elseif ($pageKey == 'terms_condition'): ?>
                        <a href="?type=terms_condition&mode=edit" class="btn-edit-mode"><i class="fas fa-edit"></i> Edit Terms</a>
                    <?php elseif ($pageKey == 'privacy_policy'): ?>
                        <a href="?type=privacy_policy&mode=edit" class="btn-edit-mode"><i class="fas fa-edit"></i> Edit Policy</a>
                    <?php elseif ($pageKey == 'contact_settings'): ?>
                        <a href="?type=contact_settings&mode=edit" class="btn-edit-mode"><i class="fas fa-edit"></i> Edit Settings</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($pageKey == 'about_us'): ?>
                <div class="editor-card">
                    <?php if ($mode == 'edit'): ?>
                        <form method="POST" action="">
                            <h3 class="form-section-title"><i class="fas fa-image"></i> Hero Section</h3>
                            <div class="form-group">
                                <label>Hero Title</label>
                                <input type="text" name="hero_title" class="form-control" value="<?php echo htmlspecialchars($aboutData['hero_title']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Hero Description</label>
                                <textarea name="hero_desc" class="form-control"><?php echo htmlspecialchars($aboutData['hero_description']); ?></textarea>
                            </div>

                            <h3 class="form-section-title"><i class="fas fa-book"></i> Our Story</h3>
                            <div class="form-group">
                                <label>Story Title</label>
                                <input type="text" name="story_title" class="form-control" value="<?php echo htmlspecialchars($aboutData['story_title']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Story Content</label>
                                <textarea name="story_content" class="form-control" style="height: 120px;"><?php echo htmlspecialchars($aboutData['story_content']); ?></textarea>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                                <div>
                                    <h3 class="form-section-title"><i class="fas fa-eye"></i> Vision</h3>
                                    <textarea name="vision_desc" class="form-control" style="margin-bottom:10px;"><?php echo htmlspecialchars($aboutData['vision_desc']); ?></textarea>
                                    <label>Points:</label>
                                    <?php for($i=0; $i<5; $i++): $val = $vPoints[$i] ?? ''; ?>
                                        <input type="text" name="vision_points[]" class="form-control" style="margin-bottom:5px;" placeholder="Point <?php echo $i+1; ?>" value="<?php echo htmlspecialchars($val); ?>">
                                    <?php endfor; ?>
                                </div>
                                <div>
                                    <h3 class="form-section-title"><i class="fas fa-bullseye"></i> Mission</h3>
                                    <textarea name="mission_desc" class="form-control" style="margin-bottom:10px;"><?php echo htmlspecialchars($aboutData['mission_desc']); ?></textarea>
                                    <label>Points:</label>
                                    <?php for($i=0; $i<6; $i++): $val = $mPoints[$i] ?? ''; ?>
                                        <input type="text" name="mission_points[]" class="form-control" style="margin-bottom:5px;" placeholder="Point <?php echo $i+1; ?>" value="<?php echo htmlspecialchars($val); ?>">
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <h3 class="form-section-title"><i class="fas fa-heart"></i> Core Values</h3>
                            <?php for($i=0; $i<6; $i++): $t = $coreValues[$i]['title'] ?? ''; $d = $coreValues[$i]['desc'] ?? ''; ?>
                                <div style="display:flex; gap:10px; margin-bottom:10px;">
                                    <input type="text" name="val_title[]" class="form-control" style="flex:1;" placeholder="Value Title" value="<?php echo htmlspecialchars($t); ?>">
                                    <input type="text" name="val_desc[]" class="form-control" style="flex:2;" placeholder="Description" value="<?php echo htmlspecialchars($d); ?>">
                                </div>
                            <?php endfor; ?>

                            <h3 class="form-section-title"><i class="fas fa-hands-helping"></i> Focus Areas</h3>
                            <?php for($i=0; $i<6; $i++): $t = $focusAreas[$i]['title'] ?? ''; $d = $focusAreas[$i]['desc'] ?? ''; ?>
                                <div style="display:flex; gap:10px; margin-bottom:10px;">
                                    <input type="text" name="focus_title[]" class="form-control" style="flex:1;" placeholder="Area Title" value="<?php echo htmlspecialchars($t); ?>">
                                    <input type="text" name="focus_desc[]" class="form-control" style="flex:2;" placeholder="Description" value="<?php echo htmlspecialchars($d); ?>">
                                </div>
                            <?php endfor; ?>

                            <div style="margin-top:30px; border-top:1px solid #eee; padding-top:20px; text-align:right;">
                                <a href="?type=about_us&mode=view" class="btn-cancel">Cancel</a>
                                <button type="submit" class="btn-save">Save Changes</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <h3 class="form-section-title">Hero & Story</h3>
                        <div class="view-field">
                            <div class="view-label">Hero Title</div>
                            <div class="view-value"><?php echo htmlspecialchars($aboutData['hero_title']); ?></div>
                        </div>
                        <div class="view-field">
                            <div class="view-label">Hero Description</div>
                            <div class="view-value"><?php echo htmlspecialchars($aboutData['hero_description']); ?></div>
                        </div>
                        <div class="view-field">
                            <div class="view-label"><?php echo htmlspecialchars($aboutData['story_title']); ?></div>
                            <div class="view-value"><?php echo htmlspecialchars($aboutData['story_content']); ?></div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                            <div>
                                <h3 class="form-section-title">Vision</h3>
                                <p class="view-value"><?php echo htmlspecialchars($aboutData['vision_desc']); ?></p>
                                <?php foreach($vPoints as $p): ?>
                                    <div style="padding:5px 0; border-bottom:1px dashed #eee;">• <?php echo htmlspecialchars($p); ?></div>
                                <?php endforeach; ?>
                            </div>
                            <div>
                                <h3 class="form-section-title">Mission</h3>
                                <p class="view-value"><?php echo htmlspecialchars($aboutData['mission_desc']); ?></p>
                                <?php foreach($mPoints as $p): ?>
                                    <div style="padding:5px 0; border-bottom:1px dashed #eee;">• <?php echo htmlspecialchars($p); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <h3 class="form-section-title">Values</h3>
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                            <?php foreach($coreValues as $v): ?>
                                <div class="view-list-item" style="display:block;">
                                    <div class="view-list-title"><?php echo htmlspecialchars($v['title']); ?></div>
                                    <div style="font-size:13px; color:#666;"><?php echo htmlspecialchars($v['desc']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <h3 class="form-section-title">Focus Areas</h3>
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                            <?php foreach($focusAreas as $f): ?>
                                <div class="view-list-item" style="display:block;">
                                    <div class="view-list-title"><?php echo htmlspecialchars($f['title']); ?></div>
                                    <div style="font-size:13px; color:#666;"><?php echo htmlspecialchars($f['desc']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($pageKey == 'terms_condition' || $pageKey == 'privacy_policy'): ?>
                <div class="editor-card">
                    <?php if ($mode == 'edit'): ?>
                        <form method="POST">
                            <div class="form-group">
                                <label><i class="fas fa-file-alt"></i> Edit Content (Plain Text)</label>
                                <textarea name="content" class="form-control" style="height: 500px; font-family: inherit; line-height:1.6; padding:15px;"><?php echo htmlspecialchars($cleanContentForEdit); ?></textarea>
                                <small style="color:#666;">Just type normally. Line breaks will be preserved.</small>
                            </div>
                            <div style="margin-top:20px; text-align:right; border-top:1px solid #eee; padding-top:20px;">
                                <a href="?type=<?php echo $pageKey; ?>&mode=view" class="btn-cancel">Cancel</a>
                                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="view-field">
                            <div class="view-label">Last Updated</div>
                            <div class="view-value"><?php echo isset($docData['effective_date']) ? $docData['effective_date'] : 'N/A'; ?></div>
                        </div>
                        <div class="view-field">
                            <div class="view-label">Content Preview</div>
                            <div class="policy-content-view">
                                <?php echo nl2br(strip_tags($rawContent, '<b><strong><i><u>')); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($pageKey == 'contact_settings'): ?>
                <div class="editor-card">
                    <?php if ($mode == 'edit'): ?>
                        <form method="POST">
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                <div>
                                    <h3 class="form-section-title"><i class="fas fa-info-circle"></i> General Info</h3>
                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($contactData['Email']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($contactData['Phone']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>WhatsApp Link (Full URL)</label>
                                        <input type="text" name="whatsapp" class="form-control" value="<?php echo htmlspecialchars($contactData['Whatsapp_Link']); ?>">
                                    </div>
                                </div>
                                <div>
                                    <h3 class="form-section-title"><i class="fas fa-map-marker-alt"></i> Location & Hours</h3>
                                    <div class="form-group">
                                        <label>Display Address (HTML Allowed, e.g. &lt;br&gt;)</label>
                                        <textarea name="address" class="form-control" style="height:100px;"><?php echo htmlspecialchars($contactData['Address']); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Working Hours (HTML Allowed)</label>
                                        <textarea name="hours" class="form-control" style="height:100px;"><?php echo htmlspecialchars($contactData['Working_Hours']); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <h3 class="form-section-title"><i class="fas fa-map"></i> Google Map Embed</h3>
                            <div class="form-group">
                                <label>Map Embed Source URL (src="..." inside iframe)</label>
                                <input type="text" name="map_src" class="form-control" value="<?php echo htmlspecialchars($contactData['Map_Embed_Src']); ?>">
                                <small style="color:#666;">Copy only the URL inside the src="" attribute from Google Maps Embed code.</small>
                            </div>

                            <div style="margin-top:20px; text-align:right; border-top:1px solid #eee; padding-top:20px;">
                                <a href="?type=contact_settings&mode=view" class="btn-cancel">Cancel</a>
                                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Settings</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
                            <div>
                                <h3 class="form-section-title">Contact Details</h3>
                                <div class="view-field">
                                    <div class="view-label">Email</div>
                                    <div class="view-value"><?php echo htmlspecialchars($contactData['Email']); ?></div>
                                </div>
                                <div class="view-field">
                                    <div class="view-label">Phone</div>
                                    <div class="view-value"><?php echo htmlspecialchars($contactData['Phone']); ?></div>
                                </div>
                                <div class="view-field">
                                    <div class="view-label">WhatsApp</div>
                                    <div class="view-value"><a href="<?php echo htmlspecialchars($contactData['Whatsapp_Link']); ?>" target="_blank"><?php echo htmlspecialchars($contactData['Whatsapp_Link']); ?></a></div>
                                </div>
                            </div>
                            <div>
                                <h3 class="form-section-title">Location & Time</h3>
                                <div class="view-field">
                                    <div class="view-label">Address</div>
                                    <div class="view-value"><?php echo $contactData['Address']; ?></div>
                                </div>
                                <div class="view-field">
                                    <div class="view-label">Working Hours</div>
                                    <div class="view-value"><?php echo $contactData['Working_Hours']; ?></div>
                                </div>
                            </div>
                        </div>

                        <h3 class="form-section-title">Map Preview</h3>
                        <div class="map-preview">
                            <?php if(!empty($contactData['Map_Embed_Src'])): ?>
                                <iframe src="<?php echo htmlspecialchars($contactData['Map_Embed_Src']); ?>" allowfullscreen="" loading="lazy"></iframe>
                            <?php else: ?>
                                <span>No Map URL Provided</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($pageKey == 'contact_messages'): ?>
                <div class="editor-card" style="padding:0; overflow:hidden;">
                    <table class="msg-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Name / Email</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Attachment</th> <th style="min-width: 140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $msgRes = $conn->query("SELECT * FROM contact_messages ORDER BY Created_At DESC");
                            if ($msgRes && $msgRes->num_rows > 0):
                                while($msg = $msgRes->fetch_assoc()):
                            ?>
                            <tr style="<?php echo ($msg['Status'] == 'New') ? 'background:#fffbfb;' : ''; ?>">
                                <td>
                                    <?php if($msg['Status'] == 'New'): ?>
                                        <span class="badge-new">NEW</span>
                                    <?php else: ?>
                                        <span class="badge-read">READ</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:13px; color:#666;"><?php echo date('Y-m-d H:i', strtotime($msg['Created_At'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($msg['Name']); ?></strong><br>
                                    <small style="color:#888;"><?php echo htmlspecialchars($msg['Email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($msg['Title']); ?></td>
                                <td style="max-width:300px;">
                                    <div style="height:50px; overflow:auto; font-size:13px; color:#555;">
                                        <?php echo nl2br(htmlspecialchars($msg['Message'])); ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <?php if(!empty($msg['Attachment'])): ?>
                                        <a href="<?php echo htmlspecialchars($msg['Attachment']); ?>" target="_blank" class="btn-attachment-view">
                                            <i class="fas fa-paperclip"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#ccc;">-</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($msg['Email']); ?>?subject=Re: <?php echo rawurlencode($msg['Title']); ?>" class="btn-action-reply">
                                        <i class="fas fa-reply"></i> Reply
                                    </a>

                                    <?php if($msg['Status'] == 'New'): ?>
                                        <a href="?type=contact_messages&mark_read=<?php echo $msg['Contact_ID']; ?>" class="btn-action-read">
                                            <i class="fas fa-check"></i> Read
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#ccc; font-size:12px;"><i class="fas fa-check-double"></i> Done</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                                <tr><td colspan="7" style="text-align:center; padding:30px; color:#999;">No messages found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>