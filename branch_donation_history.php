<?php
// branch_donation_history.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

if (!isset($_GET['branch_id'])) {
    header("Location: branch_management_page.php");
    exit();
}

$branchId = intval($_GET['branch_id']);

// --- 1. 获取分行信息 ---
$branchSql = "SELECT Branch_Name FROM branch WHERE Branch_ID = $branchId";
$branchRes = $conn->query($branchSql);
if ($branchRes->num_rows == 0) die("Branch not found.");
$branchRow = $branchRes->fetch_assoc();
$branchName = $branchRow['Branch_Name'];

// --- 2. 获取筛选和排序参数 ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date'; 
$order = isset($_GET['order']) ? $_GET['order'] : 'desc'; 

// 日期筛选
$filterDate = isset($_GET['f_date']) ? $_GET['f_date'] : '';
$filterMonth = isset($_GET['f_month']) ? $_GET['f_month'] : '';
$filterYear = isset($_GET['f_year']) ? $_GET['f_year'] : '';

// 状态筛选
$filterStatus = isset($_GET['f_status']) ? $_GET['f_status'] : '';

// 金额筛选
$minAmount = isset($_GET['min_amount']) && $_GET['min_amount'] !== '' ? floatval($_GET['min_amount']) : '';
$maxAmount = isset($_GET['max_amount']) && $_GET['max_amount'] !== '' ? floatval($_GET['max_amount']) : '';

// --- 3. 分页参数 (Pagination) ---
$results_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

// --- 4. 统计 Total Raised (仅计算成功) ---
$sqlRaised = "SELECT SUM(Order_Amount) as total, COUNT(Order_ID) as count FROM orders 
              WHERE Branch_ID = $branchId AND (Order_Status = 'Success' OR Order_Status = 'Completed')";
$stats = $conn->query($sqlRaised)->fetch_assoc();
$totalRaised = (float)($stats['total'] ?? 0);
$totalDonors = (int)($stats['count'] ?? 0);

// --- 5. 构建查询条件 ---
$conditions = [];
$conditions[] = "o.Branch_ID = $branchId";

// 状态筛选
if (!empty($filterStatus)) {
    $fs = $conn->real_escape_string($filterStatus);
    if ($fs == 'Success') {
        $conditions[] = "(o.Order_Status = 'Success' OR o.Order_Status = 'Completed')";
    } else {
        $conditions[] = "o.Order_Status = '$fs'";
    }
}

// 搜索
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $conditions[] = "(o.Order_Name LIKE '%$s%' OR o.Order_Email LIKE '%$s%' OR o.Order_TXN_Ref LIKE '%$s%')";
}

// 日期筛选
if (!empty($filterDate)) {
    $d = $conn->real_escape_string($filterDate);
    $conditions[] = "DATE(o.Order_Created_At) = '$d'";
} else {
    if (!empty($filterYear)) {
        $y = $conn->real_escape_string($filterYear);
        $conditions[] = "YEAR(o.Order_Created_At) = '$y'";
    }
    if (!empty($filterMonth)) {
        $m = $conn->real_escape_string($filterMonth);
        $conditions[] = "MONTH(o.Order_Created_At) = '$m'";
    }
}

// 金额筛选
if ($minAmount !== '') {
    $conditions[] = "o.Order_Amount >= $minAmount";
}
if ($maxAmount !== '') {
    $conditions[] = "o.Order_Amount <= $maxAmount";
}

$whereSql = "WHERE " . implode(" AND ", $conditions);

// --- 6. 获取总记录数 (Pagination Logic) ---
$countSql = "SELECT COUNT(*) as total FROM orders o $whereSql";
$countResult = $conn->query($countSql);
$total_records = $countResult->fetch_assoc()['total'];
$total_pages = ceil($total_records / $results_per_page);

// 修正页码 (如果当前页超过总页数)
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $start_from = ($page - 1) * $results_per_page;
}

// 排序映射
$sortMap = [
    'date' => 'o.Order_Created_At',
    'donor' => 'o.Order_Name',
    'amount' => 'o.Order_Amount',
    'status' => 'o.Order_Status'
];
$orderByCol = isset($sortMap[$sort]) ? $sortMap[$sort] : 'o.Order_Created_At';
$orderDir = ($order === 'asc') ? 'ASC' : 'DESC';

// --- 7. 获取数据 (Limit) ---
$sqlList = "SELECT o.*, d.Donor_ProfilePicture 
            FROM orders o
            LEFT JOIN donor d ON o.Donor_ID = d.Donor_ID
            $whereSql 
            ORDER BY $orderByCol $orderDir
            LIMIT $start_from, $results_per_page";

$result = $conn->query($sqlList);
$donations = [];
if ($result) {
    while($row = $result->fetch_assoc()) $donations[] = $row;
}

// 计算 Showing X to Y
$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// 辅助数据
$years = range(date('Y'), 2023); 
$months = [
    '1' => 'January', '2' => 'February', '3' => 'March', '4' => 'April',
    '5' => 'May', '6' => 'June', '7' => 'July', '8' => 'August',
    '9' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];

// URL 构建函数 (保留分页除外参数)
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
    <title>Donation History - <?php echo htmlspecialchars($branchName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        :root { --primary: #F28585; }
        
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #666; font-size: 14px; }

        .history-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 1000px; margin: 0 auto 30px; }

        .balance-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .b-card { padding: 20px; border-radius: 8px; color: white; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .b-card-1 { background: linear-gradient(135deg, #F28585 0%, #ff9a9a 100%); box-shadow: 0 4px 15px rgba(242, 133, 133, 0.3); }
        .b-card-2 { background: linear-gradient(135deg, #6c757d 0%, #868e96 100%); box-shadow: 0 4px 15px rgba(108, 117, 125, 0.2); }
        .b-label { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 5px; }
        .b-val { font-size: 28px; font-weight: 700; }
        .b-icon { position: absolute; right: 20px; bottom: 20px; font-size: 40px; opacity: 0.2; }

        .search-filter-container { display: flex; gap: 10px; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; align-items: center; }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; outline: none; }
        .search-btn { padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; display:flex; align-items:center; gap:5px; }
        .clear-btn { padding: 10px 15px; background: #fff; border: 1px solid #ddd; color: #555; border-radius: 5px; cursor: pointer; text-decoration: none; display:flex; align-items:center; gap:5px; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 15px; background: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #eee; cursor: pointer; user-select: none; }
        .data-table td { padding: 15px; border-bottom: 1px solid #eee; color: #333; font-size: 14px; vertical-align: middle; }
        .data-table tr:hover { background-color: #fcfcfc; }
        .clickable-row { cursor: pointer; transition: background 0.2s; }
        .clickable-row:hover { background-color: #fff5f5 !important; }

        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .st-success { background: #d4edda; color: #155724; }
        .st-completed { background: #d4edda; color: #155724; }
        .st-pending { background: #fff3cd; color: #856404; }
        .st-cancelled { background: #f8d7da; color: #721c24; }
        
        .purpose-text { font-weight: 600; color: #333; }
        .sub-text { font-size: 12px; color: #888; margin-top: 2px; }
        .amount-pos { font-weight: 700; color: #28a745; }

        /* Pagination Styles */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 20px; }
        .pagination-info { font-size: 13px; color: #666; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 6px 12px; border: 1px solid #ddd; background-color: #fff; color: #333; text-decoration: none; border-radius: 4px; font-size: 13px; transition: all 0.2s; }
        .pagination-btn:hover { background-color: #f8f9fa; border-color: #ccc; }
        .pagination-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f9f9f9; }

        /* Modals */
        .sort-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .sort-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; }
        .sort-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .sort-header h3 { margin: 0; font-size: 16px; color: #333; }
        .sort-close { cursor: pointer; font-size: 20px; color: #999; }
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #555; text-decoration: none; }
        .sort-btn:hover { background: #f8f9fa; border-color: #ddd; color: var(--primary); }
        .filter-row { display: flex; gap: 5px; margin-bottom: 5px; }
        .filter-select { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; width: 100%; margin-bottom: 8px; }
        .filter-input { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        .filter-go { padding: 8px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn-apply-full { width: 100%; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 5px; }
        .amount-input-group { display: flex; flex-direction: column; gap: 10px; }
        .amount-row { display: flex; align-items: center; gap: 10px; }
        .amount-label { width: 60px; font-size: 13px; color: #666; font-weight: 600; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="branch_management_page.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="header-title">
                    <h1>Donation History</h1>
                    <p><?php echo htmlspecialchars($branchName); ?></p>
                </div>
                <div style="width: 80px;"></div>
            </div>

            <div class="history-container">
                <div class="balance-cards">
                    <div class="b-card b-card-1">
                        <span class="b-label">Total Raised</span>
                        <span class="b-val">RM <?php echo number_format($totalRaised, 2); ?></span>
                        <i class="fas fa-hand-holding-heart b-icon"></i>
                    </div>
                    <div class="b-card b-card-2">
                        <span class="b-label">Total Donors</span>
                        <span class="b-val"><?php echo number_format($totalDonors); ?></span>
                        <i class="fas fa-users b-icon"></i>
                    </div>
                </div>

                <form class="search-filter-container" method="GET">
                    <input type="hidden" name="branch_id" value="<?php echo $branchId; ?>">
                    <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                    <input type="hidden" name="order" value="<?php echo $order; ?>">
                    <?php if(!empty($filterDate)) echo "<input type='hidden' name='f_date' value='$filterDate'>"; ?>
                    <?php if(!empty($filterYear)) echo "<input type='hidden' name='f_year' value='$filterYear'>"; ?>
                    <?php if(!empty($filterMonth)) echo "<input type='hidden' name='f_month' value='$filterMonth'>"; ?>
                    <?php if(!empty($filterStatus)) echo "<input type='hidden' name='f_status' value='$filterStatus'>"; ?>
                    <?php if($minAmount !== '') echo "<input type='hidden' name='min_amount' value='$minAmount'>"; ?>
                    <?php if($maxAmount !== '') echo "<input type='hidden' name='max_amount' value='$maxAmount'>"; ?>

                    <input type="text" name="search" class="search-input" placeholder="Search Donor Name, Email or Ref No..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($search) || !empty($filterDate) || !empty($filterYear) || !empty($filterMonth) || !empty($filterStatus) || $minAmount !== '' || $maxAmount !== ''): ?>
                        <a href="?branch_id=<?php echo $branchId; ?>" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>

                <?php if(!empty($filterDate) || !empty($filterYear) || !empty($filterMonth) || !empty($filterStatus) || $minAmount !== '' || $maxAmount !== ''): ?>
                    <div style="margin-bottom:15px; font-size:13px; color:#666; background:#fff3cd; padding:8px 12px; border-radius:5px; border:1px solid #ffeeba; display:inline-block;">
                        <i class="fas fa-filter"></i> Active Filters: 
                        <?php if(!empty($filterDate)) echo "Date: <b>$filterDate</b>; "; ?>
                        <?php if(!empty($filterYear)) echo "Year: <b>$filterYear</b>; "; ?>
                        <?php if(!empty($filterMonth)) echo "Month: <b>".$months[$filterMonth]."</b>; "; ?>
                        <?php if(!empty($filterStatus)) echo "Status: <b>$filterStatus</b>; "; ?>
                        <?php if($minAmount !== '' || $maxAmount !== '') echo "Amount: <b>RM " . ($minAmount ?: '0') . " - " . ($maxAmount ?: '∞') . "</b>;"; ?>
                    </div>
                <?php endif; ?>

                <?php if (count($donations) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th onclick="openModal('dateSortModal')">Date <?php if($sort=='date') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th onclick="openModal('donorSortModal')">Donor <?php if($sort=='donor') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th onclick="openModal('statusSortModal')">Status <?php if($sort=='status') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th style="text-align:left;" onclick="openModal('amountSortModal')">Amount <?php if($sort=='amount') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th style="text-align:left;">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($donations as $d): ?>
                                <?php 
                                    $st = $d['Order_Status'];
                                    $badge = 'st-pending';
                                    if ($st == 'Success' || $st == 'Completed') $badge = 'st-success';
                                    elseif ($st == 'Cancelled') $badge = 'st-cancelled';
                                ?>
                                <tr class="clickable-row" onclick="window.open('admin_payment_details.php?id=<?php echo $d['Order_ID']; ?>', '_blank')">
                                    <td>
                                        <div style="font-weight:600;"><?php echo date('d M Y', strtotime($d['Order_Created_At'])); ?></div>
                                        <div class="sub-text"><?php echo date('h:i A', strtotime($d['Order_Created_At'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="purpose-text"><?php echo htmlspecialchars($d['Order_Name']); ?></div>
                                        <div class="sub-text"><i class="far fa-envelope"></i> <?php echo htmlspecialchars($d['Order_Email']); ?></div>
                                    </td>
                                    <td><span class="status-badge <?php echo $badge; ?>"><?php echo $st; ?></span></td>
                                    <td style="text-align:left;"><span class="amount-pos">+ RM <?php echo number_format($d['Order_Amount'], 2); ?></span></td>
                                    <td style="text-align:left;"><span style="color:var(--primary); font-size:12px; font-weight:600;">View <i class="fas fa-external-link-alt"></i></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results
                        </div>
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo buildUrl(['page' => $page - 1]); ?>" class="pagination-btn">Previous</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Previous</span>
                            <?php endif; ?>

                            <?php
                            $start_window = max(1, $page - 1);
                            $end_window = min($total_pages, $page + 1);
                            if ($page == 1) $end_window = min($total_pages, 3);
                            if ($page == $total_pages) $start_window = max(1, $total_pages - 2);

                            for ($i = $start_window; $i <= $end_window; $i++) {
                                $active = ($i == $page) ? 'active' : '';
                                echo "<a href='" . buildUrl(['page' => $i]) . "' class='pagination-btn $active'>$i</a>";
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo buildUrl(['page' => $page + 1]); ?>" class="pagination-btn">Next</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-hand-holding-usd"></i>
                        <p>No donation records found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="dateSortModal" class="sort-modal" onclick="closeModal(event, 'dateSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Date Options</h3><span class="sort-close" onclick="document.getElementById('dateSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'date', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-up"></i> Oldest to Newest</a>
            <a href="<?php echo buildUrl(['sort'=>'date', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-down"></i> Newest to Oldest</a>
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by Specific Date</span>
            <form class="filter-row" method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['f_date', 'f_month', 'f_year', 'page'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                <input type="date" name="f_date" class="filter-input" required value="<?php echo htmlspecialchars($filterDate); ?>">
                <button type="submit" class="filter-go">Go</button>
            </form>
            <span class="sort-title" style="margin-top:10px;">Filter by Month & Year</span>
            <form method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['f_date', 'f_month', 'f_year', 'page'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                <select name="f_year" class="filter-select">
                    <option value="">All Years</option>
                    <?php foreach($years as $y) echo "<option value='$y' ".($filterYear==$y?'selected':'').">$y</option>"; ?>
                </select>
                <select name="f_month" class="filter-select">
                    <option value="">All Months</option>
                    <?php foreach($months as $k => $v) echo "<option value='$k' ".($filterMonth==$k?'selected':'').">$v</option>"; ?>
                </select>
                <button type="submit" class="btn-apply-full">Apply Date Filter</button>
            </form>
        </div>
    </div>
    
    <div id="statusSortModal" class="sort-modal" onclick="closeModal(event, 'statusSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Status Options</h3><span class="sort-close" onclick="document.getElementById('statusSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['f_status'=>'Success', 'page'=>1]); ?>" class="sort-btn"><i class="fas fa-check-circle" style="color:green;"></i> Success / Completed</a>
            <a href="<?php echo buildUrl(['f_status'=>'Pending', 'page'=>1]); ?>" class="sort-btn"><i class="fas fa-clock" style="color:orange;"></i> Pending</a>
            <a href="<?php echo buildUrl(['f_status'=>'Cancelled', 'page'=>1]); ?>" class="sort-btn"><i class="fas fa-times-circle" style="color:red;"></i> Cancelled</a>
            <a href="<?php echo buildUrl(['f_status'=>'', 'page'=>1]); ?>" class="sort-btn" style="background:#eee;"><i class="fas fa-list"></i> Show All</a>
        </div>
    </div>
    
    <div id="donorSortModal" class="sort-modal" onclick="closeModal(event, 'donorSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Donor Sorting</h3><span class="sort-close" onclick="document.getElementById('donorSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'donor', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Name A to Z</a>
            <a href="<?php echo buildUrl(['sort'=>'donor', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Name Z to A</a>
        </div>
    </div>

    <div id="amountSortModal" class="sort-modal" onclick="closeModal(event, 'amountSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Amount Options</h3><span class="sort-close" onclick="document.getElementById('amountSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'amount', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Small to Large</a>
            <a href="<?php echo buildUrl(['sort'=>'amount', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> Large to Small</a>
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by Amount Range</span>
            <form class="amount-input-group" method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['min_amount', 'max_amount', 'page'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                <div class="amount-row"><span class="amount-label">Min:</span><input type="number" name="min_amount" class="filter-input" step="0.01" value="<?php echo htmlspecialchars($minAmount); ?>"></div>
                <div class="amount-row"><span class="amount-label">Max:</span><input type="number" name="max_amount" class="filter-input" step="0.01" value="<?php echo htmlspecialchars($maxAmount); ?>"></div>
                <button type="submit" class="btn-apply-full">Apply Filter</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(e, id) { if(e.target.id === id) document.getElementById(id).style.display = 'none'; }
    </script>
</body>
</html>