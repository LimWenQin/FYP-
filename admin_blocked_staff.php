<?php
// admin_blocked_staff.php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
include 'dataconnection.php';

// --- Header Data ---
$currentAdminId = $_SESSION['admin_id'];
$adminSql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
$adminResult = $conn->query($adminSql);
if ($adminResult && $adminResult->num_rows > 0) {
    $adminData = $adminResult->fetch_assoc();
    $adminName = $adminData['Admin_Name'];
    $adminPosition = $adminData['Admin_Role']; 
    $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
} else {
    $adminName = "Admin"; $adminPosition = "System Admin"; $adminProfilePicture = null;
}

// ==========================================
// 1. HANDLE ACTIONS (Single & Bulk)
// ==========================================

// --- Single Actions (GET) ---
if (isset($_GET['restore_id'])) {
    $restoreId = intval($_GET['restore_id']);
    $conn->query("UPDATE staff SET Is_Deleted = 0 WHERE Staff_ID = $restoreId");
    header("Location: admin_blocked_staff.php?success=" . urlencode("Staff restored successfully!")); exit();
}
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    $conn->query("DELETE FROM staff WHERE Staff_ID = $deleteId");
    header("Location: admin_blocked_staff.php?success=" . urlencode("Staff permanently deleted!")); exit();
}

// --- Bulk Actions (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $ids_str = $_POST['staff_ids']; // Comma separated string
    
    if (!empty($ids_str)) {
        // Sanitize IDs
        $id_array = explode(',', $ids_str);
        $safe_ids = array_map('intval', $id_array);
        $ids_list = implode(',', $safe_ids);
        
        if ($action === 'bulk_restore') {
            $conn->query("UPDATE staff SET Is_Deleted = 0 WHERE Staff_ID IN ($ids_list)");
            $msg = count($safe_ids) . " staff members restored successfully.";
        } elseif ($action === 'bulk_delete') {
            $conn->query("DELETE FROM staff WHERE Staff_ID IN ($ids_list)");
            $msg = count($safe_ids) . " staff members deleted permanently.";
        }
        
        header("Location: admin_blocked_staff.php?success=" . urlencode($msg));
        exit();
    }
}

// ==========================================
// 2. SEARCH & FILTER LOGIC
// ==========================================
$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = [];

// Default Sort
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// ALWAYS filter by Is_Deleted = 1
$whereConditions[] = "Is_Deleted = 1";

// 2.1 Keyword Search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchValue = $conn->real_escape_string($_GET['search']);
    $searchTerm = $searchValue;
    $whereConditions[] = "(Staff_FullName LIKE '%$searchValue%'
                            OR Staff_Email LIKE '%$searchValue%'
                            OR Staff_ID LIKE '%$searchValue%'
                            OR Staff_ContactNumber LIKE '%$searchValue%'
                            OR Staff_Role LIKE '%$searchValue%')";
}

// 2.2 Dropdown Filters
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    
    if ($filterType == 'name_sort' && isset($_GET['filter_val_name']) && !empty($_GET['filter_val_name'])) {
        $filterValue = $_GET['filter_val_name']; 
    }
    elseif ($filterType == 'phone' && isset($_GET['filter_val_phone']) && !empty($_GET['filter_val_phone'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_phone']);
        $whereConditions[] = "Staff_ContactNumber LIKE '%$filterValue%'";
    }
    elseif ($filterType == 'role' && isset($_GET['filter_val_role']) && !empty($_GET['filter_val_role'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_role']);
        $whereConditions[] = "Staff_Role = '$filterValue'";
    }
}

// 2.3 Sorting Logic
$sortMap = [
    'id' => 'Staff_ID',
    'name' => 'Staff_FullName',
    'email' => 'Staff_Email',
    'contact' => 'Staff_ContactNumber',
    'role' => 'Staff_Role'
];
$orderByCol = isset($sortMap[$sort]) ? $sortMap[$sort] : 'Staff_ID';
$orderDir = ($order === 'asc') ? 'ASC' : 'DESC';

// Handle Name Sort Filter Override
if ($filterType == 'name_sort' && !empty($filterValue)) {
    $orderByCol = 'Staff_FullName';
    $orderDir = ($filterValue == 'asc') ? 'ASC' : 'DESC';
}

$orderClause = "ORDER BY $orderByCol $orderDir";

// Build SQL Clauses
$whereClause = "";
if (count($whereConditions) > 0) $whereClause = "WHERE " . implode(" AND ", $whereConditions);

$sql = "SELECT * FROM staff $whereClause $orderClause";
$result = $conn->query($sql);

// Helper Data for Filters
$phonePrefixes = ['010', '011', '012', '013', '014', '015', '016', '017', '018', '019'];

// URL Builder Helper
function buildUrl($params = []) {
    $current = $_GET;
    $merged = array_merge($current, $params);
    return '?' . http_build_query($merged);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocked Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <link rel="stylesheet" href="admin_blocked.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Filter Bar Styles */
        .search-filter-container { align-items: center; justify-content: space-between; gap: 10px; }
        .filter-group { display: flex; align-items: center; gap: 8px; flex: 1; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; margin-right: 10px; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Batch Action Toolbar */
        .batch-actions {
            display: none; 
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.3s ease-out;
            background: #fff3cd; 
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ffeeba;
            margin-top: 10px;
            width: 100%;
        }
        .batch-actions.active { display: flex; }
        
        /* Select Button */
        .btn-select-toggle { background: white; border: 1px solid #d1d5db; color: #2c3e50; padding: 10px 18px; border-radius: 6px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 8px; height: 45px; }
        .btn-select-toggle:hover { background: #f3f4f6; }
        .btn-select-toggle.active { background: #dc3545; color: white; border-color: #dc3545; }

        /* Table Checkbox & Headers */
        .checkbox-cell { display: none; width: 40px; text-align: center; }
        th { cursor: pointer; user-select: none; }
        th:hover { background-color: #f1f5f9; color: #f28585; }
        
        /* Action Buttons Wrapper (Horizontal Layout) */
        .action-cell { height: 100%; text-align: center; vertical-align: middle; }
        .action-buttons-wrapper {
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: center;
            gap: 8px; /* Gap between buttons */
        }

        /* Inline Action Buttons */
        .btn-icon { 
            width: 34px; height: 34px; 
            border-radius: 6px; 
            display: inline-flex; 
            align-items: center; justify-content: center; 
            border: none; cursor: pointer; transition: all 0.2s; 
            font-size: 14px;
        }
        .btn-restore { background: #d1fae5; color: #047857; }
        .btn-restore:hover { background: #a7f3d0; transform: translateY(-2px); }
        .btn-delete { background: #fee2e2; color: #b91c1c; }
        .btn-delete:hover { background: #fecaca; transform: translateY(-2px); }

        /* Sort Modal Styles */
        .sort-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .sort-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; text-align: left; }
        .sort-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .sort-header h3 { margin: 0; font-size: 16px; color: #333; }
        .sort-close { cursor: pointer; font-size: 20px; color: #999; }
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #555; text-decoration: none; box-sizing: border-box; }
        .sort-btn:hover { background: #f8f9fa; border-color: #ddd; color: #f28585; }

        /* Lightbox Styles */
        .lightbox-modal { display: none; position: fixed; z-index: 3000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        @keyframes zoomIn { from {transform:scale(0)} to {transform:scale(1)} }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 3002; }
        .close-lightbox:hover, .close-lightbox:focus { color: #bbb; text-decoration: none; cursor: pointer; }

        /* Prefix Grid Styles */
        .filter-section-title { font-size: 14px; font-weight: 600; color: #555; margin: 15px 0 10px 0; border-top: 1px solid #eee; padding-top: 15px; }
        .prefix-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .prefix-btn { padding: 8px 0; text-align: center; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; color: #333; text-decoration: none; transition: 0.2s; display: block; }
        .prefix-btn:hover { background: #f28585; color: white; border-color: #f28585; }
    </style>
</head>
<body>
    <?php if (isset($_GET['success'])): ?><div class="floating-alert" style="border-left:4px solid #28a745;color:#28a745"><i class="fas fa-check-circle"></i> <?php echo $_GET['success']; ?></div><?php endif; ?>
    
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content">
            <div class="blocked-table-container">
                <div class="table-header">
                    <h2><i class="fas fa-ban"></i> Blocked Staff</h2>
                </div>
                
                <form class="search-filter-container" method="GET" action="admin_blocked_staff.php" style="display:flex; flex-wrap:wrap; align-items: flex-start;">
                    
                    <div class="filter-group" style="flex-wrap: wrap;">
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="name_sort" <?php echo ($filterType == 'name_sort') ? 'selected' : ''; ?>>Name Sorting</option>
                            <option value="phone" <?php echo ($filterType == 'phone') ? 'selected' : ''; ?>>Phone Prefix</option>
                            <option value="role" <?php echo ($filterType == 'role') ? 'selected' : ''; ?>>Role</option>
                        </select>

                        <div id="filter_name_container" class="secondary-filter">
                            <select name="filter_val_name" class="filter-select">
                                <option value="">Select Order...</option>
                                <option value="asc" <?php echo ($filterValue == 'asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                                <option value="desc" <?php echo ($filterValue == 'desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                            </select>
                        </div>
                        <div id="filter_phone_container" class="secondary-filter">
                            <select name="filter_val_phone" class="filter-select">
                                <option value="">Select Prefix...</option>
                                <?php foreach($phonePrefixes as $pp): ?>
                                    <option value="<?php echo $pp; ?>" <?php echo ($filterValue == $pp) ? 'selected' : ''; ?>>+6<?php echo $pp; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="filter_role_container" class="secondary-filter">
                            <select name="filter_val_role" class="filter-select">
                                <option value="">Select Role...</option>
                                <option value="Staff" <?php echo ($filterValue == 'Staff') ? 'selected' : ''; ?>>Staff</option>
                                </select>
                        </div>

                        <input type="text" name="search" class="search-input" placeholder="Search by name, ID or email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        
                        <?php if(!empty($filterType) || !empty($searchTerm)): ?>
                            <a href="admin_blocked_staff.php" class="btn btn-danger" style="padding: 0 15px;"><i class="fas fa-times"></i></a>
                        <?php endif; ?>

                        <button type="button" class="btn-select-toggle" id="selectToggleBtn" onclick="toggleSelectMode()">
                            <i class="far fa-check-square"></i> Select
                        </button>
                    </div>
                </form>
                
                <div class="batch-actions" id="batchToolbar">
                    <span style="font-size: 13px; font-weight: 600; color: #856404;">Selected: <span class="sel-count">0</span></span>
                    
                    <button type="button" class="btn btn-restore" onclick="submitBatch('restore')">
                        <i class="fas fa-undo"></i> Batch Restore
                    </button>
                    <button type="button" class="btn btn-delete" onclick="submitBatch('delete')">
                        <i class="fas fa-trash"></i> Batch Delete
                    </button>
                </div>

                <form id="bulkForm" method="POST">
                    <input type="hidden" name="bulk_action" id="bulkActionInput">
                    <input type="hidden" name="staff_ids" id="bulkIdsInput">
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-cell" id="th-checkbox">
                                <input type="checkbox" id="headerCheckbox" onclick="toggleSelectAll()">
                            </th>
                            <th onclick="openSortModal('idSortModal')">
                                ID <?php if($sort=='id') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th onclick="openSortModal('nameSortModal')">
                                Staff Member <?php if($sort=='name') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th onclick="openSortModal('emailSortModal')">
                                Email <?php if($sort=='email') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th onclick="openSortModal('contactSortModal')">
                                Contact <?php if($sort=='contact') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th onclick="openSortModal('roleSortModal')">
                                Role <?php if($sort=='role') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?>
                            </th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                        <tr id="row-<?php echo $row['Staff_ID']; ?>">
                            <td class="checkbox-cell">
                                <input type="checkbox" class="row-checkbox" value="<?php echo $row['Staff_ID']; ?>" onclick="updateSelection()">
                            </td>
                            <td style="font-weight: bold; color: #666;">#<?php echo $row['Staff_ID']; ?></td>
                            <td>
                                <div class="user-info">
                                    <div class="avatar-circle">
                                        <?php 
                                            $lightboxSrc = !empty($row['Staff_ProfilePicture']) ? htmlspecialchars($row['Staff_ProfilePicture']) : '';
                                        ?>
                                        <div class="avatar-circle" <?php if($lightboxSrc) echo "onclick=\"openLightbox('$lightboxSrc')\" style='cursor:pointer;'"; ?>>
                                            <?php if(!empty($row['Staff_ProfilePicture'])): ?><img src="<?php echo $row['Staff_ProfilePicture']; ?>"><?php else: ?><i class="fas fa-user-tie"></i><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="user-details">
                                        <strong><?php echo htmlspecialchars($row['Staff_FullName']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['Staff_Email']); ?></td>
                            <td><?php echo htmlspecialchars($row['Staff_ContactNumber']); ?></td>
                            <td><?php echo htmlspecialchars($row['Staff_Role']); ?></td>
                            <td class="action-cell">
                                <div class="action-buttons-wrapper">
                                    <button type="button" class="btn-icon btn-restore" title="Restore Staff" onclick="confirmSingleAction(<?php echo $row['Staff_ID']; ?>, 'restore')">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button type="button" class="btn-icon btn-delete" title="Delete Permanently" onclick="confirmSingleAction(<?php echo $row['Staff_ID']; ?>, 'delete')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7" class="empty-state">No blocked staff found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="idSortModal" class="sort-modal" onclick="closeModal(event, 'idSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by ID</h3><span class="sort-close" onclick="document.getElementById('idSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'id', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Low to High</a>
            <a href="<?php echo buildUrl(['sort'=>'id', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> High to Low</a>
        </div>
    </div>

    <div id="nameSortModal" class="sort-modal" onclick="closeModal(event, 'nameSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Name</h3><span class="sort-close" onclick="document.getElementById('nameSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'name', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Name (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'name', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Name (Z - A)</a>
        </div>
    </div>

    <div id="emailSortModal" class="sort-modal" onclick="closeModal(event, 'emailSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Email</h3><span class="sort-close" onclick="document.getElementById('emailSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'email', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Email (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'email', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Email (Z - A)</a>
        </div>
    </div>

    <div id="contactSortModal" class="sort-modal" onclick="closeModal(event, 'contactSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Contact</h3><span class="sort-close" onclick="document.getElementById('contactSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'contact', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Phone (Ascending)</a>
            <a href="<?php echo buildUrl(['sort'=>'contact', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> Phone (Descending)</a>
            <div class="filter-section-title">Filter by Prefix</div>
            <div class="prefix-grid">
                <?php 
                for($p=10; $p<=19; $p++) {
                    $prefix = "0$p";
                    $url = buildUrl(['filter_type' => 'phone', 'filter_val_phone' => $prefix]);
                    $activeStyle = ($filterType == 'phone' && $filterValue == $prefix) ? 'background-color:#f28585;color:white;border-color:#f28585;' : '';
                    echo "<a href='$url' class='prefix-btn' style='$activeStyle'>+6$prefix</a>";
                }
                ?>
            </div>
        </div>
    </div>

    <div id="roleSortModal" class="sort-modal" onclick="closeModal(event, 'roleSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Sort by Role</h3><span class="sort-close" onclick="document.getElementById('roleSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'role', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Role (A - Z)</a>
            <a href="<?php echo buildUrl(['sort'=>'role', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Role (Z - A)</a>
        </div>
    </div>

    <div id="imageLightbox" class="lightbox-modal">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <img class="lightbox-content" id="lightboxImage">
    </div>

    <script>
        // --- Filter Toggle Logic ---
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            if (type === 'name_sort') { document.getElementById('filter_name_container').classList.add('active'); document.getElementById('filter_name_container').querySelector('select').disabled = false; }
            else if (type === 'phone') { document.getElementById('filter_phone_container').classList.add('active'); document.getElementById('filter_phone_container').querySelector('select').disabled = false; }
            else if (type === 'role') { document.getElementById('filter_role_container').classList.add('active'); document.getElementById('filter_role_container').querySelector('select').disabled = false; }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            const alertBox = document.querySelector('.floating-alert');
            if(alertBox) setTimeout(() => alertBox.style.display='none', 3000);
            
            // Lightbox Close Logic
            window.addEventListener('click', function(event) {
                if (event.target.id == 'imageLightbox') closeLightbox();
            });
            document.addEventListener('keydown', function(event) {
                if (event.key === "Escape" && document.getElementById('imageLightbox').style.display === "flex") { closeLightbox(); }
            });
        });

        // --- Header Modal Logic ---
        function openSortModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(e, id) { if(e.target.id === id) document.getElementById(id).style.display = 'none'; }

        // --- Select & Batch Logic ---
        let selectionMode = false;

        function toggleSelectMode() {
            selectionMode = !selectionMode;
            const btn = document.getElementById('selectToggleBtn');
            const cells = document.querySelectorAll('.checkbox-cell');
            
            if (selectionMode) {
                cells.forEach(c => c.style.display = 'table-cell');
                btn.innerHTML = '<i class="fas fa-times"></i> Cancel';
                btn.classList.add('active');
            } else {
                cells.forEach(c => c.style.display = 'none');
                document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = false; });
                document.getElementById('headerCheckbox').checked = false;
                btn.innerHTML = '<i class="far fa-check-square"></i> Select';
                btn.classList.remove('active');
                updateSelection(); 
            }
        }

        function toggleSelectAll() {
            const master = document.getElementById('headerCheckbox');
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = master.checked);
            updateSelection();
        }

        function updateSelection() {
            const count = document.querySelectorAll('.row-checkbox:checked').length;
            document.querySelectorAll('.sel-count').forEach(el => el.innerText = count);
            const toolbar = document.getElementById('batchToolbar');
            
            if (count > 0 && selectionMode) toolbar.classList.add('active');
            else toolbar.classList.remove('active');
        }

        // --- Lightbox Functions ---
        function openLightbox(src) { 
            if (!src) return; 
            document.getElementById('lightboxImage').src = src; 
            document.getElementById('imageLightbox').style.display = "flex"; 
        }
        function closeLightbox() { 
            document.getElementById('imageLightbox').style.display = "none"; 
        }

        // --- Single Action Confirmation ---
        function confirmSingleAction(id, type) {
            const title = (type === 'restore') ? 'Restore Staff?' : 'Delete Forever?';
            const color = (type === 'restore') ? '#10b981' : '#dc3545';
            const text = (type === 'restore') 
                ? 'Are you sure you want to unblock this staff member?' 
                : 'This action cannot be undone. Are you sure?';
            const urlParam = (type === 'restore') ? 'restore_id' : 'delete_id';

            Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: color,
                confirmButtonText: 'Yes, Proceed'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `admin_blocked_staff.php?${urlParam}=${id}`;
                }
            });
        }

        // --- Batch Action Confirmation ---
        function submitBatch(type) {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            const ids = Array.from(checkboxes).map(cb => cb.value);
            
            if (ids.length === 0) return;

            const actionName = (type === 'restore') ? 'bulk_restore' : 'bulk_delete';
            const title = (type === 'restore') ? 'Restore Staff?' : 'Delete Forever?';
            const color = (type === 'restore') ? '#10b981' : '#dc3545';
            const text = (type === 'restore') 
                ? `Restore ${ids.length} staff members?` 
                : `Permanently delete ${ids.length} staff members? This cannot be undone.`;

            Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: color,
                confirmButtonText: 'Yes, Proceed'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('bulkActionInput').value = actionName;
                    document.getElementById('bulkIdsInput').value = ids.join(',');
                    document.getElementById('bulkForm').submit();
                }
            });
        }
    </script>
</body>
</html>