<?php
// admin_management_page.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- 获取当前登录用户的角色 ---
$currentAdminId = $_SESSION['admin_id'];
$currentUserRole = 'Admin'; // Default

$roleSql = "SELECT Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
$roleRes = $conn->query($roleSql);
if ($roleRes && $row = $roleRes->fetch_assoc()) {
    $currentUserRole = $row['Admin_Role']; 
}

// --- SEARCH & FILTER PREPARATION ---
$search = "";
$filterType = "";
$filterValue = "";
$conditions = ["Is_Deleted = 0"];
$orderClause = "ORDER BY Admin_CreatedAt DESC, Admin_ID DESC"; 

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $conditions[] = "(Admin_Name LIKE '%$search%' OR Admin_Email LIKE '%$search%' OR Admin_ICNUMBER LIKE '%$search%' OR Admin_ID LIKE '%$search%')";
}

if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    if ($filterType == 'status' && isset($_GET['filter_val_status']) && !empty($_GET['filter_val_status'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_status']);
        $conditions[] = "Admin_Status = '$filterValue'";
    } elseif ($filterType == 'role' && isset($_GET['filter_val_role']) && !empty($_GET['filter_val_role'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_role']);
        $conditions[] = "Admin_Role = '$filterValue'";
    } elseif ($filterType == 'name_sort' && isset($_GET['filter_val_name']) && !empty($_GET['filter_val_name'])) {
        $filterValue = $_GET['filter_val_name'];
        if ($filterValue == 'asc') $orderClause = "ORDER BY Admin_Name ASC";
        elseif ($filterValue == 'desc') $orderClause = "ORDER BY Admin_Name DESC";
    } elseif ($filterType == 'city' && isset($_GET['filter_val_city']) && !empty($_GET['filter_val_city'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_city']);
        $conditions[] = "Admin_City = '$filterValue'";
    }
}

$whereClause = "WHERE " . implode(" AND ", $conditions);

// --- EXPORT TO EXCEL ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $filename = "Admin_List_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    echo "Admin ID\tFull Name\tRole\tEmail\tContact Number\tIC Number\tCity\tStatus\tJoin Date\n";
    
    $exportSql = "SELECT * FROM admin $whereClause $orderClause";
    $exportResult = $conn->query($exportSql);
    
    if ($exportResult->num_rows > 0) {
        while ($row = $exportResult->fetch_assoc()) {
            $name = str_replace("\t", " ", $row['Admin_Name']);
            $email = str_replace("\t", " ", $row['Admin_Email']);
            $city = str_replace("\t", " ", $row['Admin_City']);
            
            echo $row['Admin_ID'] . "\t" . 
                 $name . "\t" . 
                 $row['Admin_Role'] . "\t" . 
                 $email . "\t" . 
                 "'" . $row['Admin_ContactNumber'] . "\t" . 
                 "'" . $row['Admin_ICNUMBER'] . "\t" .      
                 $city . "\t" . 
                 $row['Admin_Status'] . "\t" . 
                 $row['Admin_CreatedAt'] . "\n";
        }
    }
    exit();
}

// --- HANDLE BLOCK ADMIN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['block_admin'])) {
    if ($currentUserRole === 'Super Admin') {
        $blockId = intval($_POST['block_admin_id']);
        if ($blockId == $currentAdminId) {
            header("Location: admin_management_page.php?error=" . urlencode("You cannot block yourself."));
        } else {
            $blockSql = "UPDATE admin SET Is_Deleted = 1 WHERE Admin_ID = $blockId";
            if ($conn->query($blockSql)) header("Location: admin_management_page.php?success=" . urlencode("Admin blocked successfully!"));
            else header("Location: admin_management_page.php?error=" . urlencode("Error: " . $conn->error));
        }
    } else {
        header("Location: admin_management_page.php?error=" . urlencode("Permission Denied. Only Super Admin can block admins."));
    }
    exit();
}

// --- PAGINATION & QUERY ---
$results_per_page = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$count_sql = "SELECT COUNT(*) as total FROM admin $whereClause";
$count_result = $conn->query($count_sql);
$total_records = ($count_result && $row = $count_result->fetch_assoc()) ? $row['total'] : 0;
$total_pages = ceil($total_records / $results_per_page);
if ($page > $total_pages && $total_pages > 0) { $page = $total_pages; $start_from = ($page - 1) * $results_per_page; }

$sql = "SELECT * FROM admin $whereClause $orderClause LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);
$admins = [];
if ($result && $result->num_rows > 0) while($row = $result->fetch_assoc()) $admins[] = $row;

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// --- STATS ---
$totalAdminStats = $conn->query("SELECT COUNT(*) as total FROM admin WHERE Is_Deleted = 0")->fetch_assoc()['total'];
$activeAdminStats = $conn->query("SELECT COUNT(*) as total FROM admin WHERE Admin_Status = 'Active' AND Is_Deleted = 0")->fetch_assoc()['total'];
$superAdminStats = $conn->query("SELECT COUNT(*) as total FROM admin WHERE Admin_Role = 'Super Admin' AND Is_Deleted = 0")->fetch_assoc()['total'];

// --- DATA FOR FILTERS ---
$cities = [];
$cityQ = $conn->query("SELECT DISTINCT Admin_City FROM admin WHERE Is_Deleted = 0 AND Admin_City != '' ORDER BY Admin_City ASC");
if($cityQ) while($c = $cityQ->fetch_assoc()) $cities[] = $c['Admin_City'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Existing Styles */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: var(--gray); margin-bottom: 5px; }
        .stat-info h2 { font-size: 28px; font-weight: 600; margin-bottom: 5px; }
        .stat-desc { font-size: 12px; color: #28a745; display: flex; align-items: center; gap: 5px; font-weight: 500;}
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-card:nth-child(1) .stat-icon { background: rgba(242, 133, 133, 0.2); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(23, 162, 184, 0.2); color: var(--info); }

        .admin-management { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-header h2 { font-size: 18px; font-weight: 600; color: #333; }
        .header-buttons { display: flex; gap: 10px; }
        .btn { padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; color: white; transition: 0.3s; font-size: 14px; text-decoration: none; }
        .btn-primary { background: #F28585; } .btn-success { background: #28a745; } .btn-danger { background: #dc3545; }

        .admin-search { margin-bottom: 25px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; background-color: white; color: #555; outline: none; cursor: pointer; font-size: 14px; min-width: 140px; }
        .filter-select:focus { border-color: #F28585; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; font-size: 14px; background: white; }
        .search-input:focus { border-color: #F28585; }
        
        .admin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .admin-card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.3s, box-shadow 0.3s; position: relative; display: flex; flex-direction: column; border: 1px solid #eee; }
        .admin-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: #F28585; }
        .card-header-actions { position: absolute; top: 15px; right: 15px; z-index: 10; }
        .card-body { padding: 25px 20px 20px; text-align: center; flex: 1; }
        .card-avatar { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; background: #ffe5e5; color: #F28585; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 28px; object-fit: cover; border: 3px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s; }
        .card-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .card-avatar:hover { transform: scale(1.05); border-color: #F28585; }
        .card-name { font-size: 18px; font-weight: 700; color: #333; margin-bottom: 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .card-role { font-size: 14px; color: #666; margin-bottom: 12px; display: inline-block; background: #f8f9fa; padding: 4px 12px; border-radius: 20px; font-weight: 500; }
        .card-status { display: inline-block; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 12px; letter-spacing: 0.3px; margin-bottom: 15px; }
        .status-active { background-color: #e6f4ea; color: #1e7e34; } .status-inactive { background-color: #fce8e6; color: #c5221f; }
        .card-footer { background: #fcfcfc; border-top: 1px solid #f0f0f0; padding: 15px 20px; font-size: 13px; color: #555; text-align: left; }
        .contact-item { display: flex; align-items: center; margin-bottom: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .contact-item i { width: 20px; color: #aaa; text-align: center; margin-right: 8px; }
        
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { width: 32px; height: 32px; border-radius: 50%; background: white; border: 1px solid #eee; box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: #777; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; }
        .menu-btn:hover { background: #f8f9fa; color: var(--primary); border-color: #ddd; transform: translateY(-1px); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; text-align: left; }
        .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .dropdown-content a:hover { background-color: #f8f9fa; color: var(--primary); }
        .text-delete { color: var(--danger) !important; border-top: 1px solid #eee; }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; transition: all 0.3s; display: inline-block; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f8f9fa; border-color: #eee; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; animation: slideIn 0.3s; }
        .floating-alert div { line-height: 1.6; }
        .floating-alert i { margin-top: 4px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Block Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 450px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--gray-light); background-color: #dc3545; color: white; border-radius: 10px 10px 0 0; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: white; }
        .modal-body { padding: 30px; text-align: center; }

        /* Image Viewer Modal */
        .image-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 10000; justify-content: center; align-items: center; flex-direction: column; }
        .image-modal-content { max-width: 90%; max-height: 80vh; object-fit: contain; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.5); }
        .image-modal-placeholder { width: 300px; height: 300px; background: #ffe5e5; color: #F28585; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 120px; font-weight: bold; border: 5px solid white; }
        .image-modal-close { position: absolute; top: 30px; right: 40px; color: white; font-size: 40px; cursor: pointer; transition: 0.3s; }
        .image-modal-close:hover { color: #F28585; transform: scale(1.1); }

        @media (max-width: 768px) { .stats-cards { grid-template-columns: 1fr 1fr; } .admin-search { flex-direction: column; align-items: stretch; } .filter-select, .search-input { width: 100%; margin-bottom: 5px; } .admin-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    
    <div class="floating-alert floating-alert-success" id="floatingSuccess" style="display: <?php echo isset($_GET['success']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-check-circle"></i>
        <div id="floatingSuccessText"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div>
    </div>

    <div class="floating-alert floating-alert-danger" id="floatingError" style="display: <?php echo isset($_GET['error']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-exclamation-circle"></i>
        <div id="floatingErrorText"><?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?></div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="welcome-section"><h1>Admin Management</h1><p>Manage system administrators, roles, and permissions.</p></div>

            <div class="stats-cards">
                <div class="stat-card"><div class="stat-info"><h3>TOTAL ADMINS</h3><h2><?php echo $totalAdminStats; ?></h2><p class="stat-desc"><i class="fas fa-users"></i> All administrators</p></div><div class="stat-icon"><i class="fas fa-user-shield"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3>SUPER ADMINS</h3><h2><?php echo $superAdminStats; ?></h2><p class="stat-desc"><i class="fas fa-crown"></i> High privilege</p></div><div class="stat-icon"><i class="fas fa-crown"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3>ACTIVE ACCOUNTS</h3><h2><?php echo $activeAdminStats; ?></h2><p class="stat-desc"><i class="fas fa-check-circle"></i> Currently active</p></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
            </div>

            <div class="admin-management">
                <div class="section-header">
                    <h2>Admin List</h2>
                    <div class="header-buttons">
                        <?php if ($currentUserRole === 'Super Admin'): ?>
                        <a href="admin_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Admin</a>
                        <?php endif; ?>
                        
                        <a href="admin_management_page.php?export=excel" class="btn btn-success"><i class="fas fa-download"></i> Export Data</a>
                    </div>
                </div>

                <form class="admin-search" method="GET" action="admin_management_page.php">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="name_sort" <?php if($filterType == 'name_sort') echo 'selected'; ?>>Name Sorting</option>
                            <option value="role" <?php if($filterType == 'role') echo 'selected'; ?>>Role</option>
                            <option value="city" <?php if($filterType == 'city') echo 'selected'; ?>>City</option>
                            <option value="status" <?php if($filterType == 'status') echo 'selected'; ?>>Status</option>
                        </select>
                    </div>

                    <div id="filter_name_container" class="secondary-filter"><select name="filter_val_name" class="filter-select"><option value="">Select Order...</option><option value="asc" <?php if($filterValue == 'asc') echo 'selected'; ?>>Name (A-Z)</option><option value="desc" <?php if($filterValue == 'desc') echo 'selected'; ?>>Name (Z-A)</option></select></div>
                    <div id="filter_role_container" class="secondary-filter"><select name="filter_val_role" class="filter-select"><option value="">Select Role...</option><option value="Super Admin" <?php if($filterValue == 'Super Admin') echo 'selected'; ?>>Super Admin</option><option value="Admin" <?php if($filterValue == 'Admin') echo 'selected'; ?>>Admin</option></select></div>
                    <div id="filter_city_container" class="secondary-filter"><select name="filter_val_city" class="filter-select"><option value="">Select City...</option><?php foreach($cities as $c): ?><option value="<?php echo $c; ?>" <?php if($filterValue == $c) echo 'selected'; ?>><?php echo $c; ?></option><?php endforeach; ?></select></div>
                    <div id="filter_status_container" class="secondary-filter"><select name="filter_val_status" class="filter-select"><option value="">Select Status...</option><option value="Active" <?php if($filterValue == 'Active') echo 'selected'; ?>>Active</option><option value="Inactive" <?php if($filterValue == 'Inactive') echo 'selected'; ?>>Inactive</option></select></div>

                    <input type="text" name="search" class="search-input" placeholder="Search admin by name, ID or email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($filterType) || !empty($search)): ?><a href="admin_management_page.php" class="btn btn-danger" style="padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a><?php endif; ?>
                </form>
                
                <div class="admin-grid">
                    <?php if (count($admins) > 0): foreach($admins as $index => $admin): ?>
                    <div class="admin-card">
                        <div class="card-header-actions">
                            <div class="action-menu">
                                <button class="menu-btn" onclick="toggleMenu(event, <?php echo $admin['Admin_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                <div id="menu-<?php echo $admin['Admin_ID']; ?>" class="dropdown-content">
                                    <a href="admin_view_edit.php?id=<?php echo $admin['Admin_ID']; ?>"><i class="fas fa-eye"></i> View Details</a>
                                    
                                    <?php if ($currentUserRole === 'Super Admin' || $currentAdminId == $admin['Admin_ID']): ?>
                                    <a href="admin_view_edit.php?id=<?php echo $admin['Admin_ID']; ?>&mode=edit"><i class="fas fa-edit"></i> Edit Details</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($currentUserRole === 'Super Admin' && $admin['Admin_ID'] != $currentAdminId): ?>
                                    <a href="javascript:void(0)" onclick="openBlockAdminModal(<?php echo $admin['Admin_ID']; ?>, '<?php echo addslashes($admin['Admin_Name']); ?>')" class="text-delete"><i class="fas fa-ban"></i> Block Admin</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php
                                $hasImg = !empty($admin['Admin_ProfilePicture']) && file_exists($admin['Admin_ProfilePicture']);
                                $imgSrc = $hasImg ? htmlspecialchars($admin['Admin_ProfilePicture']) : '';
                                $initial = substr($admin['Admin_Name'], 0, 1);
                            ?>
                            <div class="card-avatar" onclick="openImageModal('<?php echo $imgSrc; ?>', '<?php echo $initial; ?>')">
                                <?php if ($hasImg): ?><img src="<?php echo $imgSrc; ?>" alt="Profile"><?php else: echo $initial; endif; ?>
                            </div>
                            <div class="card-name"><?php echo htmlspecialchars($admin['Admin_Name']); ?></div>
                            <div class="card-role"><?php echo htmlspecialchars($admin['Admin_Role']); ?></div>
                            <div><span class="card-status <?php echo ($admin['Admin_Status'] == 'Active') ? 'status-active' : 'status-inactive'; ?>"><?php echo htmlspecialchars($admin['Admin_Status']); ?></span></div>
                            <div style="font-size: 12px; color: #999; margin-top: 5px;">ID: #<?php echo str_pad($admin['Admin_ID'], 4, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        <div class="card-footer">
                            <div class="contact-item"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admin['Admin_Email']); ?></div>
                            <div class="contact-item"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($admin['Admin_ContactNumber']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div style="grid-column: 1 / -1; text-align:center; padding:40px; color:#888; background:#f9f9f9; border-radius:10px;"><i class="fas fa-search" style="font-size:40px; color:#ddd; margin-bottom:10px;"></i><p>No active administrators found matching your criteria.</p></div>
                    <?php endif; ?>
                </div>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        $queryParams = []; if(!empty($search)) $queryParams['search'] = $search;
                        if(!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if($filterType == 'status' && !empty($filterValue)) $queryParams['filter_val_status'] = $filterValue;
                            elseif($filterType == 'name_sort' && !empty($filterValue)) $queryParams['filter_val_name'] = $filterValue;
                            elseif($filterType == 'role' && !empty($filterValue)) $queryParams['filter_val_role'] = $filterValue;
                            elseif($filterType == 'city' && !empty($filterValue)) $queryParams['filter_val_city'] = $filterValue;
                        }
                        $queryString = http_build_query($queryParams);
                        $paginationUrl = !empty($queryString) ? '&' . $queryString : '';

                        if ($page > 1) echo '<a href="?page=' . ($page - 1) . $paginationUrl . '" class="pagination-btn">Previous</a>';
                        else echo '<span class="pagination-btn disabled">Previous</span>';

                        $start_window = max(1, $page - 1);
                        $end_window = min($total_pages, $page + 1);
                        if ($page == 1) $end_window = min($total_pages, 3);
                        if ($page == $total_pages) $start_window = max(1, $total_pages - 2);

                        for ($i = $start_window; $i <= $end_window; $i++) {
                            if ($i == $page) echo '<span class="pagination-btn active">' . $i . '</span>';
                            else echo '<a href="?page=' . $i . $paginationUrl . '" class="pagination-btn">' . $i . '</a>';
                        }

                        if ($page < $total_pages) echo '<a href="?page=' . ($page + 1) . $paginationUrl . '" class="pagination-btn">Next</a>';
                        else echo '<span class="pagination-btn disabled">Next</span>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="blockStaffModal">
        <div class="modal-content"><div class="modal-header"><h2 style="font-size: 16px;"><i class="fas fa-exclamation-triangle"></i> Block Confirmation</h2><button class="close-btn" onclick="closeModal('blockStaffModal')">&times;</button></div><div class="modal-body"><form method="POST" action="admin_management_page.php"><input type="hidden" name="block_admin" value="1"><input type="hidden" name="block_admin_id" id="block_admin_id"><i class="fas fa-ban" style="font-size: 50px; color: #dc3545; margin-bottom: 20px;"></i><h3 style="margin-bottom: 10px;">Block this admin?</h3><p style="color: #666; font-size: 14px; margin-bottom: 25px;">Are you sure you want to block <strong id="block_admin_name_display"></strong>?<br>They will be moved to the blocked list and cannot access the system.</p><div style="display: flex; gap: 10px; justify-content: center;"><button type="button" class="btn" style="background: #eee; color: #333;" onclick="closeModal('blockStaffModal')">Cancel</button><button type="submit" class="btn btn-danger">Yes, Block</button></div></form></div></div>
    </div>

    <div id="imageViewerModal" class="image-modal-overlay" onclick="closeImageModal(event)">
        <span class="image-modal-close" onclick="closeImageModal(event, true)">&times;</span>
        <img id="fullImageView" class="image-modal-content" src="" style="display:none;">
        <div id="placeholderView" class="image-modal-placeholder" style="display:none;"></div>
    </div>

    <script>
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            if (type === 'status') { document.getElementById('filter_status_container').classList.add('active'); document.getElementById('filter_status_container').querySelector('select').disabled = false; }
            else if (type === 'name_sort') { document.getElementById('filter_name_container').classList.add('active'); document.getElementById('filter_name_container').querySelector('select').disabled = false; }
            else if (type === 'role') { document.getElementById('filter_role_container').classList.add('active'); document.getElementById('filter_role_container').querySelector('select').disabled = false; }
            else if (type === 'city') { document.getElementById('filter_city_container').classList.add('active'); document.getElementById('filter_city_container').querySelector('select').disabled = false; }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            setTimeout(() => { const a = document.getElementById('floatingSuccess'); const b = document.getElementById('floatingError'); if(a) a.style.display='none'; if(b) b.style.display='none'; }, 5000);
            window.onclick = function(event) {
                if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) { document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); }
                if (event.target == document.getElementById('blockStaffModal')) closeModal('blockStaffModal');
            }
        });

        function openBlockAdminModal(id, name) { document.getElementById('block_admin_id').value = id; document.getElementById('block_admin_name_display').textContent = name; document.getElementById('blockStaffModal').style.display = 'flex'; }
        
        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); document.getElementById('menu-' + id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        // --- Image Viewer Logic ---
        function openImageModal(src, initial) {
            const modal = document.getElementById('imageViewerModal');
            const imgEl = document.getElementById('fullImageView');
            const placeholderEl = document.getElementById('placeholderView');

            if (src) {
                imgEl.src = src;
                imgEl.style.display = 'block';
                placeholderEl.style.display = 'none';
            } else {
                imgEl.style.display = 'none';
                placeholderEl.style.display = 'flex';
                placeholderEl.innerText = initial;
            }
            modal.style.display = 'flex';
        }

        function closeImageModal(e, force = false) {
            // Close if clicking the background or the X button
            if (force || e.target.id === 'imageViewerModal') {
                document.getElementById('imageViewerModal').style.display = 'none';
            }
        }
    </script>
</body>
</html>