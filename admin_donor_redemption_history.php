<?php
// admin_donor_redemption_history.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

if (!isset($_GET['donor_id'])) {
    header("Location: admin_donor_page.php");
    exit();
}

$donorId = intval($_GET['donor_id']);

// Fetch Donor Name
$donorSql = "SELECT Donor_Name FROM donor WHERE Donor_ID = $donorId";
$donorRes = $conn->query($donorSql);
if ($donorRes->num_rows == 0) die("Donor not found.");
$donorName = $donorRes->fetch_assoc()['Donor_Name'];

// --- 1. Get Parameters ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$results_per_page = 10;

// Date Filters
$filterDate = isset($_GET['f_date']) ? $_GET['f_date'] : '';
$filterMonth = isset($_GET['f_month']) ? $_GET['f_month'] : '';
$filterYear = isset($_GET['f_year']) ? $_GET['f_year'] : '';

// Status Filter
$filterStatus = isset($_GET['f_status']) ? $_GET['f_status'] : '';

// Points Range Filter
$minPoints = isset($_GET['min_points']) && $_GET['min_points'] !== '' ? intval($_GET['min_points']) : '';
$maxPoints = isset($_GET['max_points']) && $_GET['max_points'] !== '' ? intval($_GET['max_points']) : '';

// --- 2. Build Query ---
$conditions = [];
$conditions[] = "r.Donor_ID = $donorId";

// Search
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $conditions[] = "(i.Reward_ItemName LIKE '%$s%' OR r.Redemption_TrackingNumber LIKE '%$s%')";
}

// Date Filters
if (!empty($filterDate)) {
    $d = $conn->real_escape_string($filterDate);
    $conditions[] = "DATE(r.Redemption_Updated_At) = '$d'";
} elseif (!empty($filterMonth) && !empty($filterYear)) {
    $m = intval($filterMonth);
    $y = intval($filterYear);
    $conditions[] = "MONTH(r.Redemption_Updated_At) = $m AND YEAR(r.Redemption_Updated_At) = $y";
} elseif (!empty($filterYear)) {
    $y = intval($filterYear);
    $conditions[] = "YEAR(r.Redemption_Updated_At) = $y";
}

// Status Filter
if (!empty($filterStatus)) {
    $fs = $conn->real_escape_string($filterStatus);
    if ($fs == 'success') {
        $conditions[] = "(r.Redemption_Status = 'Shipped' OR r.Redemption_Status = 'Completed' OR r.Redemption_Status = 'Success')";
    } elseif ($fs == 'pending') {
        $conditions[] = "r.Redemption_Status = 'Pending'";
    } elseif ($fs == 'cancelled') {
        $conditions[] = "r.Redemption_Status = 'Cancelled'";
    }
}

// Points Range
if ($minPoints !== '') {
    $conditions[] = "r.Redemption_PointsSpent >= $minPoints";
}
if ($maxPoints !== '') {
    $conditions[] = "r.Redemption_PointsSpent <= $maxPoints";
}

$whereSql = "WHERE " . implode(" AND ", $conditions);

// Sorting
$sortMap = [
    'date' => 'r.Redemption_Updated_At',
    'points' => 'r.Redemption_PointsSpent',
    'item' => 'i.Reward_ItemName',
    'status' => 'r.Redemption_Status',
    'tracking' => 'r.Redemption_TrackingNumber'
];
$orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : 'r.Redemption_Updated_At';
$orderDir = ($order == 'asc') ? 'ASC' : 'DESC';

// Pagination Calculation
$countSql = "SELECT COUNT(*) as total FROM redemption_order r JOIN reward_item i ON r.Reward_ID = i.Reward_ID $whereSql";
$countRes = $conn->query($countSql);
$total_records = ($countRes && $row = $countRes->fetch_assoc()) ? $row['total'] : 0;
$total_pages = ceil($total_records / $results_per_page);

if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
$start_from = ($page - 1) * $results_per_page;
$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// Main Query
$sql = "SELECT r.*, i.Reward_ItemName, i.Reward_PhotoPath 
        FROM redemption_order r 
        JOIN reward_item i ON r.Reward_ID = i.Reward_ID 
        $whereSql 
        ORDER BY $orderBy $orderDir 
        LIMIT $start_from, $results_per_page";

$result = $conn->query($sql);
$redemptions = [];
while($row = $result->fetch_assoc()) $redemptions[] = $row;

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
    <title>Redemption History - <?php echo htmlspecialchars($donorName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; cursor: pointer; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        .header-title { flex: 1; text-align: center; padding-right: 80px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        
        .history-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 1000px; margin: 0 auto 30px; }
        
        /* Search Bar */
        .search-filter-container { display: flex; gap: 10px; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; align-items: center; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; }
        .search-btn { padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; display:flex; align-items:center; gap:5px; }
        .search-btn:hover { background: #e07070; }
        .clear-btn { padding: 10px 15px; background: #fff; border: 1px solid #ddd; color: #555; border-radius: 5px; cursor: pointer; text-decoration: none; display:flex; align-items:center; gap:5px; }
        .clear-btn:hover { background: #f1f1f1; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 15px; background: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #eee; cursor:pointer; user-select: none; }
        .data-table td { padding: 15px; border-bottom: 1px solid #eee; color: #333; font-size: 14px; vertical-align: middle; }
        .data-table th:hover { background-color: #e9ecef; color: var(--primary); }
        .data-table th i { margin-left: 5px; opacity: 0.5; }

        .sort-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .sort-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; }
        .sort-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .sort-header h3 { margin: 0; font-size: 16px; color: #333; }
        .sort-close { cursor: pointer; font-size: 20px; color: #999; }
        .sort-title { font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; text-decoration: none; color: #555; }
        .sort-btn:hover { background: #f8f9fa; color: var(--primary); }
        
        .filter-row { display: flex; gap: 5px; margin-bottom: 5px; }
        .filter-input { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        .filter-go { padding: 8px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .filter-go:hover { background: #5a6268; }

        .amount-input-group { display: flex; flex-direction: column; gap: 10px; }
        .amount-row { display: flex; align-items: center; gap: 10px; }
        .amount-label { width: 70px; font-size: 13px; color: #666; font-weight: 600; }
        .btn-apply-full { width: 100%; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 5px; }
        .btn-apply-full:hover { background: #e07070; }

        .status-badge { font-weight: 600; font-size: 12px; padding: 4px 8px; border-radius: 4px; }
        .status-success { background: #e6f9ed; color: #28a745; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #ffe6e6; color: #dc3545; }

        /* Pagination */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 10px; }
        .pagination-controls { display: flex; gap: 5px; align-items: center; }
        .pagination-btn { padding: 8px 14px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .pagination-btn.active { background-color: #F28585; color: white; border-color: #F28585; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 768px) { .pagination-container { flex-direction: column; gap: 15px; } }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <?php include 'admin_header.php'; ?>
        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="admin_donor_page.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="header-title">
                    <h1>Redemption History</h1>
                    <p>Donor: <?php echo htmlspecialchars($donorName); ?></p>
                </div>
                <div style="width: 80px;"></div>
            </div>

            <div class="history-container">
                
                <form class="search-filter-container" method="GET">
                    <input type="hidden" name="donor_id" value="<?php echo $donorId; ?>">
                    <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                    <input type="hidden" name="order" value="<?php echo $order; ?>">
                    
                    <?php if(!empty($filterStatus)) echo "<input type='hidden' name='f_status' value='$filterStatus'>"; ?>
                    <?php if(!empty($filterDate)) echo "<input type='hidden' name='f_date' value='$filterDate'>"; ?>
                    <?php if(!empty($filterMonth)) echo "<input type='hidden' name='f_month' value='$filterMonth'>"; ?>
                    <?php if(!empty($filterYear)) echo "<input type='hidden' name='f_year' value='$filterYear'>"; ?>
                    <?php if($minPoints !== '') echo "<input type='hidden' name='min_points' value='$minPoints'>"; ?>
                    <?php if($maxPoints !== '') echo "<input type='hidden' name='max_points' value='$maxPoints'>"; ?>

                    <input type="text" name="search" class="search-input" placeholder="Search Item Name or Tracking No..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                    
                    <?php if(!empty($search) || !empty($filterStatus) || !empty($filterDate) || $minPoints !== '' || $maxPoints !== ''): ?>
                        <a href="?donor_id=<?php echo $donorId; ?>" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th onclick="openModal('dateSortModal')">Date <i class="fas fa-sort"></i></th>
                            <th onclick="openModal('itemSortModal')">Item <i class="fas fa-sort"></i></th>
                            <th onclick="openModal('pointsSortModal')">Points Spent <i class="fas fa-sort"></i></th>
                            <th onclick="openModal('statusSortModal')">Status <i class="fas fa-sort"></i></th>
                            <th onclick="openModal('trackingSortModal')">Tracking No <i class="fas fa-sort"></i></th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($redemptions) > 0): ?>
                            <?php foreach($redemptions as $r): ?>
                                <?php
                                    // [Fix Image Path]
                                    $photo = $r['Reward_PhotoPath'];
                                    $imgSrc = "";
                                    if (!empty($photo)) {
                                        // If stored as full path (containing uploads/) use it directly
                                        if (strpos($photo, 'uploads/') === 0) {
                                            $imgSrc = $photo;
                                        } else {
                                            // Otherwise assume it's in uploads/rewards/ directory
                                            $imgSrc = "uploads/rewards/" . $photo;
                                        }
                                    } else {
                                        $imgSrc = "https://via.placeholder.com/40"; // Fallback
                                    }

                                    // [Fix Status Color]
                                    $st = $r['Redemption_Status'];
                                    $stClass = 'status-pending';
                                    if ($st == 'Shipped' || $st == 'Completed' || $st == 'Success') $stClass = 'status-success';
                                    elseif ($st == 'Cancelled') $stClass = 'status-cancelled';
                                ?>
                                <tr>
                                    <td>
                                        <div><?php echo date('d M Y', strtotime($r['Redemption_Updated_At'])); ?></div>
                                        <div style="font-size:12px; color:#888;"><?php echo date('h:i A', strtotime($r['Redemption_Updated_At'])); ?></div>
                                    </td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" style="width:40px; height:40px; object-fit:cover; border-radius:4px; border:1px solid #eee;">
                                            <span><?php echo htmlspecialchars($r['Reward_ItemName']); ?></span>
                                        </div>
                                    </td>
                                    <td style="color:#666; font-weight:bold;">- <?php echo $r['Redemption_PointsSpent']; ?> pts</td>
                                    <td><span class="status-badge <?php echo $stClass; ?>"><?php echo $st; ?></span></td>
                                    <td style="font-family:monospace; color:#555;"><?php echo $r['Redemption_TrackingNumber'] ? $r['Redemption_TrackingNumber'] : '-'; ?></td>
                                    <td><a href="admin_redemption_details.php?id=<?php echo $r['Redemption_ID']; ?>" target="_blank" style="color:var(--primary); font-weight:600;">View <i class="fas fa-chevron-right"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">No records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
                    <div class="pagination-controls">
                        <?php 
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $queryString = http_build_query($queryParams);
                        $prefix = !empty($queryString) ? "&" : "";

                        if ($page > 1) echo '<a href="?' . $queryString . $prefix . 'page=' . ($page - 1) . '" class="pagination-btn">Previous</a>'; 
                        else echo '<span class="pagination-btn disabled">Previous</span>';

                        $start_window = max(1, $page - 1);
                        $end_window = min($total_pages, $page + 1);
                        if ($page == 1) $end_window = min($total_pages, 3);
                        if ($page == $total_pages) $start_window = max(1, $total_pages - 2);

                        for ($i = $start_window; $i <= $end_window; $i++) {
                            if ($i == $page) echo '<span class="pagination-btn active">' . $i . '</span>';
                            else echo '<a href="?' . $queryString . $prefix . 'page=' . $i . '" class="pagination-btn">' . $i . '</a>';
                        }

                        if ($page < $total_pages) echo '<a href="?' . $queryString . $prefix . 'page=' . ($page + 1) . '" class="pagination-btn">Next</a>'; 
                        else echo '<span class="pagination-btn disabled">Next</span>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="dateSortModal" class="sort-modal" onclick="closeModal(event, 'dateSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Date Options</h3><span class="sort-close" onclick="document.getElementById('dateSortModal').style.display='none'">&times;</span></div>
            
            <span class="sort-title">Sorting</span>
            <a href="<?php echo buildUrl(['sort'=>'date', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-down"></i> Newest to Oldest</a>
            <a href="<?php echo buildUrl(['sort'=>'date', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-up"></i> Oldest to Newest</a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            
            <span class="sort-title">Filter by Specific Date</span>
            <form class="filter-row" method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['f_date', 'f_month', 'f_year', 'page'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                <input type="date" name="f_date" class="filter-input" required>
                <button type="submit" class="filter-go">Go</button>
            </form>

            <span class="sort-title" style="margin-top:10px;">Filter by Month & Year</span>
            <form class="filter-row" method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['f_date', 'f_month', 'f_year', 'page'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                <select name="f_month" class="filter-input">
                    <option value="">Month</option>
                    <?php for($i=1; $i<=12; $i++) echo "<option value='$i'>".date("M", mktime(0,0,0,$i,10))."</option>"; ?>
                </select>
                <select name="f_year" class="filter-input" required>
                    <option value="">Year</option>
                    <?php for($y=date('Y'); $y>=2020; $y--) echo "<option value='$y'>$y</option>"; ?>
                </select>
                <button type="submit" class="filter-go">Go</button>
            </form>
        </div>
    </div>

    <div id="pointsSortModal" class="sort-modal" onclick="closeModal(event, 'pointsSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Points Options</h3><span class="sort-close" onclick="document.getElementById('pointsSortModal').style.display='none'">&times;</span></div>
            
            <span class="sort-title">Sorting</span>
            <a href="<?php echo buildUrl(['sort'=>'points', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Low to High</a>
            <a href="<?php echo buildUrl(['sort'=>'points', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> High to Low</a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            
            <span class="sort-title">Filter by Points Range</span>
            <form class="amount-input-group" method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['min_points', 'max_points', 'page'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                
                <div class="amount-row">
                    <span class="amount-label">Min:</span>
                    <input type="number" name="min_points" class="filter-input" placeholder="0" min="0" value="<?php echo htmlspecialchars($minPoints); ?>">
                </div>
                <div class="amount-row">
                    <span class="amount-label">Max:</span>
                    <input type="number" name="max_points" class="filter-input" placeholder="No Limit" min="0" value="<?php echo htmlspecialchars($maxPoints); ?>">
                </div>
                
                <button type="submit" class="btn-apply-full">Apply Filter</button>
            </form>
        </div>
    </div>

    <div id="itemSortModal" class="sort-modal" onclick="closeModal(event, 'itemSortModal')">
        <div class="sort-modal-content">
            <h3>Item Name</h3>
            <a href="<?php echo buildUrl(['sort'=>'item', 'order'=>'asc']); ?>" class="sort-btn">A - Z</a>
            <a href="<?php echo buildUrl(['sort'=>'item', 'order'=>'desc']); ?>" class="sort-btn">Z - A</a>
        </div>
    </div>

    <div id="trackingSortModal" class="sort-modal" onclick="closeModal(event, 'trackingSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Tracking No Options</h3><span class="sort-close" onclick="document.getElementById('trackingSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'tracking', 'order'=>'asc']); ?>" class="sort-btn">A - Z</a>
            <a href="<?php echo buildUrl(['sort'=>'tracking', 'order'=>'desc']); ?>" class="sort-btn">Z - A</a>
        </div>
    </div>

    <div id="statusSortModal" class="sort-modal" onclick="closeModal(event, 'statusSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Status Options</h3><span class="sort-close" onclick="document.getElementById('statusSortModal').style.display='none'">&times;</span></div>
            <span class="sort-title">Sorting</span>
            <a href="<?php echo buildUrl(['sort'=>'status', 'order'=>'asc']); ?>" class="sort-btn">A - Z</a>
            <a href="<?php echo buildUrl(['sort'=>'status', 'order'=>'desc']); ?>" class="sort-btn">Z - A</a>
            
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by</span>
            <a href="<?php echo buildUrl(['f_status'=>'success']); ?>" class="sort-btn"><span class="status-badge status-success">Shipped / Success</span></a>
            <a href="<?php echo buildUrl(['f_status'=>'pending']); ?>" class="sort-btn"><span class="status-badge status-pending">Pending</span></a>
            <a href="<?php echo buildUrl(['f_status'=>'cancelled']); ?>" class="sort-btn"><span class="status-badge status-cancelled">Cancelled</span></a>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(e, id) { if(e.target.id === id) document.getElementById(id).style.display = 'none'; }
    </script>
</body>
</html>