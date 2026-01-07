<?php
// admin_blocked_donors.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- 获取当前管理员信息 (用于Header显示) ---
$currentAdminId = $_SESSION['admin_id'];
$adminSql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
$adminResult = $conn->query($adminSql);

if ($adminResult && $adminResult->num_rows > 0) {
    $adminData = $adminResult->fetch_assoc();
    $adminName = $adminData['Admin_Name'];
    $adminPosition = $adminData['Admin_Role']; 
    $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
} else {
    $adminName = $_SESSION['admin_name'];
    $adminPosition = "System Administrator";
    $adminProfilePicture = null;
}

// --- HANDLE RESTORE ---
if (isset($_GET['restore_id'])) {
    $restoreId = intval($_GET['restore_id']);
    // Restore: Set Is_Deleted = 0
    if ($conn->query("UPDATE donor SET Is_Deleted = 0 WHERE Donor_ID = $restoreId")) {
        header("Location: admin_blocked_donors.php?success=" . urlencode("Donor restored successfully!"));
        exit();
    } else {
        $errorMessage = "Error restoring donor: " . $conn->error;
    }
}

// --- HANDLE PERMANENT DELETE ---
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    // Permanent Delete
    if ($conn->query("DELETE FROM donor WHERE Donor_ID = $deleteId")) {
        header("Location: admin_blocked_donors.php?success=" . urlencode("Donor permanently deleted!"));
        exit();
    } else {
        $errorMessage = "Error deleting donor. Make sure database constraints allow deletion. Error: " . $conn->error;
    }
}

// --- SEARCH & FILTER PREPARATION ---
$searchTerm = "";
$filterType = "";
$filterValue = "";
$whereConditions = [];

// 核心条件：只显示被封锁 (Is_Deleted = 1) 的用户
$whereConditions[] = "d.Is_Deleted = 1";

// 1. Check Search (Keyword)
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereConditions[] = "(d.Donor_Name LIKE '%$searchTerm%' 
                           OR d.Donor_Email LIKE '%$searchTerm%' 
                           OR d.Donor_ID LIKE '%$searchTerm%')";
}

// 2. Check Dynamic Filters
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterType = $_GET['filter_type'];
    
    // State Filter
    if ($filterType == 'state' && isset($_GET['filter_val_state']) && !empty($_GET['filter_val_state'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_state']);
        $whereConditions[] = "d.Donor_State = '$filterValue'";
    } 
    // Year Filter
    elseif ($filterType == 'year' && isset($_GET['filter_val_year']) && !empty($_GET['filter_val_year'])) {
        $filterValue = $conn->real_escape_string($_GET['filter_val_year']);
        $whereConditions[] = "YEAR(d.Donor_RegisteredAt) = '$filterValue'";
    }
    // Points Filter
    elseif ($filterType == 'points' && isset($_GET['filter_val_points']) && !empty($_GET['filter_val_points'])) {
        $pointRange = $_GET['filter_val_points'];
        $filterValue = $pointRange; 
        if ($pointRange == 'low') {
            $whereConditions[] = "(SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID) < 500";
        } elseif ($pointRange == 'mid') {
            $whereConditions[] = "(SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID) BETWEEN 500 AND 1000";
        } elseif ($pointRange == 'high') {
            $whereConditions[] = "(SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID) > 1000";
        }
    }
}

// Combine WHERE clause
$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// --- EXPORT TO EXCEL HANDLER ---
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $filename = "blocked_donors_data_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<h3>BLOCKED DONOR LIST</h3>';
    echo '<table border="1">';
    echo '<tr style="background-color:#eee;">
            <th>ID</th><th>Name</th><th>Email</th><th>Contact</th>
            <th>State</th><th>Points</th><th>Registered Date</th>
          </tr>';
    
    $donorSql = "SELECT d.Donor_ID, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, 
                  d.Donor_State, d.Donor_RegisteredAt,
                  COALESCE((SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID), 0) as CurrentPoints
                  FROM donor d $whereClause ORDER BY d.Donor_RegisteredAt DESC";
    $donorRes = $conn->query($donorSql);
    
    if ($donorRes && $donorRes->num_rows > 0) {
        while($row = $donorRes->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['Donor_ID'] . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_Name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_Email']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Donor_ContactNumber']) . '&nbsp;</td>';
            echo '<td>' . htmlspecialchars($row['Donor_State']) . '</td>';
            echo '<td>' . $row['CurrentPoints'] . '</td>';
            echo '<td>' . $row['Donor_RegisteredAt'] . '</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    exit();
}

// --- PAGINATION & FETCH DATA ---
$results_per_page = 10; // 【修改点：设置为10条每页】
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

$count_sql = "SELECT COUNT(*) as total FROM donor d $whereClause";
$total_records = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_records / $results_per_page);
if ($page > $total_pages && $total_pages > 0) { $page = $total_pages; $start_from = ($page - 1) * $results_per_page; }

$select_fields = "d.*, 
                  COALESCE((SELECT Points_Total FROM point p WHERE p.Donor_ID = d.Donor_ID), 0) as CurrentPoints";
$sql = "SELECT $select_fields FROM donor d $whereClause ORDER BY d.Donor_RegisteredAt DESC LIMIT $start_from, $results_per_page";
$result = $conn->query($sql);

$blocked_donors = [];
if ($result && $result->num_rows > 0) { while($row = $result->fetch_assoc()) $blocked_donors[] = $row; }

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// Helper Arrays for Filter
$malaysiaStates = [ 'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu' ];
$years = range(date('Y'), 2020);
$exportParams = $_GET; $exportParams['action'] = 'export_excel'; unset($exportParams['page']);
$exportUrl = "?" . http_build_query($exportParams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocked Donors - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* 复用 Donor Management 的样式以保持一致 */
        .blocked-table-container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .table-header h2 { margin: 0; color: #dc3545; font-size: 18px; }
        
        /* 按钮样式 */
        .btn { padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 5px; text-decoration: none; font-size: 13px; }
        .btn-success { background: var(--success, #28a745); color: white; }
        .btn-danger { background: var(--danger, #dc3545); color: white; }
        .btn-primary { background: var(--primary, #007bff); color: white; }
        
        /* 筛选器样式 */
        .donor-search { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; flex-wrap: wrap;}
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ced4da; border-radius: 5px; outline: none; background: white; min-width: 200px; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ced4da; border-radius: 5px; outline: none; background-color: white; min-width: 140px; cursor: pointer; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* 表格样式 */
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        th { background: #f8f9fa; color: #555; font-size: 13px; text-transform: uppercase; font-weight: 600; }
        
        .donor-info { display: flex; align-items: center; gap: 10px; }
        .avatar-circle { width: 40px; height: 40px; border-radius: 50%; background: #eee; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .empty-state { text-align: center; padding: 40px 20px; color: #888; }
        
        /* --- Action Menu Styles (一致性设计) --- */
        .action-cell { display: flex; justify-content: center; align-items: center; height: 100%; }
        .action-menu { position: relative; display: inline-block; }
        .menu-btn { background-color: #f8f9fa; border: 1px solid #e9ecef; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; color: #6c757d; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; outline: none; }
        .menu-btn:hover { background-color: #e2e6ea; color: var(--primary, #007bff); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 40px; background-color: white; min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); z-index: 100; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        .dropdown-content div, .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 10px; }
        .dropdown-content div:hover, .dropdown-content a:hover { background-color: #f8f9fa; color: var(--primary, #007bff); }
        .text-delete { color: var(--danger, #dc3545) !important; border-top: 1px solid #eee; }
        
        /* 分页样式 */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-info { font-size: 13px; color: #666; }
        .pagination-controls { display: flex; gap: 5px; align-items: center; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; }

        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1100; background: white; }
        
        @media (max-width: 768px) {
            .donor-search { flex-direction: column; align-items: stretch; }
            .pagination-container { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['success'])): ?>
        <div class="floating-alert" style="border-left: 4px solid #28a745; color: #28a745;">
            <i class="fas fa-check-circle"></i> &nbsp; <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) || isset($errorMessage)): ?>
        <div class="floating-alert" style="border-left: 4px solid #dc3545; color: #dc3545;">
            <i class="fas fa-exclamation-circle"></i> &nbsp; <?php echo htmlspecialchars($errorMessage ?? $_GET['error']); ?>
        </div>
    <?php endif; ?>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content">
            <div class="blocked-table-container">
                <div class="table-header">
                    <h2><i class="fas fa-ban"></i> Blocked / Inactive Donors</h2>
                    <a href="<?php echo $exportUrl; ?>" class="btn btn-success" target="_blank"><i class="fas fa-download"></i> Export Data</a>
                </div>
                
                <form method="GET" action="admin_blocked_donors.php" class="donor-search">
                    <div class="filter-group">
                        <i class="fas fa-filter" style="color:#666; margin-right:5px;"></i>
                        <select name="filter_type" id="filterType" class="filter-select" onchange="toggleFilterInputs()">
                            <option value="">Filter By...</option>
                            <option value="state" <?php echo ($filterType == 'state') ? 'selected' : ''; ?>>State</option>
                            <option value="year" <?php echo ($filterType == 'year') ? 'selected' : ''; ?>>Registration Year</option>
                            <option value="points" <?php echo ($filterType == 'points') ? 'selected' : ''; ?>>Points Tier</option>
                        </select>
                    </div>

                    <div id="filter_state_container" class="secondary-filter">
                        <select name="filter_val_state" class="filter-select">
                            <option value="">Select State...</option>
                            <?php foreach($malaysiaStates as $ms): ?>
                                <option value="<?php echo $ms; ?>" <?php echo ($filterValue == $ms && $filterType == 'state') ? 'selected' : ''; ?>><?php echo $ms; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="filter_year_container" class="secondary-filter">
                        <select name="filter_val_year" class="filter-select">
                            <option value="">Select Year...</option>
                            <?php foreach($years as $yr): ?>
                                <option value="<?php echo $yr; ?>" <?php echo ($filterValue == $yr && $filterType == 'year') ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="filter_points_container" class="secondary-filter">
                        <select name="filter_val_points" class="filter-select">
                            <option value="">Select Tier...</option>
                            <option value="low" <?php echo ($filterValue == 'low') ? 'selected' : ''; ?>>Below 500 pts</option>
                            <option value="mid" <?php echo ($filterValue == 'mid') ? 'selected' : ''; ?>>500 - 1000 pts</option>
                            <option value="high" <?php echo ($filterValue == 'high') ? 'selected' : ''; ?>>VIP (> 1000 pts)</option>
                        </select>
                    </div>

                    <input type="text" name="search" class="search-input" placeholder="Search blocked donors..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($filterType) || !empty($searchTerm)): ?>
                        <a href="admin_blocked_donors.php" class="btn btn-danger" style="background-color: #dc3545; padding: 10px 15px;" title="Clear Filters"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th>Donor</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Registered Date</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($blocked_donors) > 0): ?>
                            <?php foreach($blocked_donors as $row): ?>
                                <tr>
                                    <td>
                                        <div class="donor-info">
                                            <div class="avatar-circle">
                                                <?php if(!empty($row['Donor_ProfilePicture'])): ?>
                                                    <img src="<?php echo htmlspecialchars($row['Donor_ProfilePicture']); ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-user" style="color:#ccc;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($row['Donor_Name']); ?></strong><br>
                                                <small style="color:#888;">ID: <?php echo $row['Donor_ID']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['Donor_Email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['Donor_ContactNumber']); ?></td>
                                    <td><?php echo $row['Donor_RegisteredAt']; ?></td>
                                    <td style="text-align:center;">
                                        <div class="action-cell">
                                            <div class="action-menu">
                                                <button class="menu-btn" onclick="toggleMenu(event, <?php echo $row['Donor_ID']; ?>)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div id="menu-<?php echo $row['Donor_ID']; ?>" class="dropdown-content">
                                                    <a href="admin_blocked_donors.php?restore_id=<?php echo $row['Donor_ID']; ?>" 
                                                       onclick="return confirm('Restore this donor? They will be able to login again.')">
                                                       <i class="fas fa-undo"></i> Restore User
                                                    </a>
                                                    
                                                    <a href="admin_blocked_donors.php?delete_id=<?php echo $row['Donor_ID']; ?>" 
                                                       class="text-delete" 
                                                       onclick="return confirm('WARNING: This will PERMANENTLY delete this donor and ALL their history (Orders, Points, etc.) from the database. This cannot be undone. Are you sure?')">
                                                       <i class="fas fa-trash-alt"></i> Delete Forever
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty-state">No blocked donors found matching criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        $queryParams = [];
                        if(!empty($searchTerm)) $queryParams['search'] = $searchTerm;
                        if(!empty($filterType)) {
                            $queryParams['filter_type'] = $filterType;
                            if($filterType == 'state' && !empty($filterValue)) $queryParams['filter_val_state'] = $filterValue;
                            if($filterType == 'year' && !empty($filterValue)) $queryParams['filter_val_year'] = $filterValue;
                            if($filterType == 'points' && !empty($filterValue)) $queryParams['filter_val_points'] = $filterValue;
                        }
                        $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                        
                        if ($page > 1) echo '<a href="?page=' . ($page - 1) . $queryString . '" class="pagination-btn">Previous</a>'; 
                        else echo '<span class="pagination-btn disabled">Previous</span>';
                        
                        if ($page == 1) echo '<span class="pagination-btn active">1</span>'; 
                        else echo '<a href="?page=1' . $queryString . '" class="pagination-btn">1</a>';
                        
                        $start_mid = max(2, $page - 1); 
                        $end_mid = min($total_pages - 1, $page + 1);
                        
                        if ($start_mid > 2) echo '<span class="pagination-btn disabled">...</span>';
                        for ($i = $start_mid; $i <= $end_mid; $i++) {
                            echo ($i == $page) ? '<span class="pagination-btn active">' . $i . '</span>' : '<a href="?page=' . $i . $queryString . '" class="pagination-btn">' . $i . '</a>';
                        }
                        if ($end_mid < $total_pages - 1) echo '<span class="pagination-btn disabled">...</span>';
                        
                        if ($total_pages > 1) {
                            echo ($page == $total_pages) ? '<span class="pagination-btn active">' . $total_pages . '</span>' : '<a href="?page=' . $total_pages . $queryString . '" class="pagination-btn">' . $total_pages . '</a>';
                        }
                        
                        if ($page < $total_pages) echo '<a href="?page=' . ($page + 1) . $queryString . '" class="pagination-btn">Next</a>'; 
                        else echo '<span class="pagination-btn disabled">Next</span>';
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // JS Logic for Filter Toggle
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.querySelectorAll('.secondary-filter').forEach(el => { el.classList.remove('active'); el.querySelector('select').disabled = true; });
            if (type === 'state') { document.getElementById('filter_state_container').classList.add('active'); document.getElementById('filter_state_container').querySelector('select').disabled = false; }
            else if (type === 'year') { document.getElementById('filter_year_container').classList.add('active'); document.getElementById('filter_year_container').querySelector('select').disabled = false; }
            else if (type === 'points') { document.getElementById('filter_points_container').classList.add('active'); document.getElementById('filter_points_container').querySelector('select').disabled = false; }
        }

        // --- Action Menu Toggle Logic (与 Donor Page 保持一致) ---
        function toggleMenu(e, id) { 
            e.stopPropagation(); 
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); 
            document.getElementById('menu-' + id).style.display = 'block'; 
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleFilterInputs();
            const s = document.querySelector('.floating-alert');
            if(s) setTimeout(() => s.style.display='none', 5000);

            // 点击外部关闭菜单
            window.addEventListener('click', function(event) {
                if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn *')) {
                    document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
                }
            });
        });
    </script>
</body>
</html>