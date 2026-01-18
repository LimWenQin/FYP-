<?php
// special_case_withdrawal_history.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

if (!isset($_GET['case_id'])) {
    // 按之前要求，若无 ID 则关闭窗口
    echo "<script>window.close();</script>";
    exit();
}

$caseId = intval($_GET['case_id']);

// --- 1. 获取 Case 信息 & 筹款总额 ---
$caseSql = "SELECT Case_Title, Raised_Amount FROM special_case WHERE Case_ID = $caseId";
$caseRes = $conn->query($caseSql);
if ($caseRes->num_rows == 0) die("Case not found.");
$caseData = $caseRes->fetch_assoc();
$caseTitle = $caseData['Case_Title'];
$totalRaised = floatval($caseData['Raised_Amount']);

// --- 2. 获取筛选和排序参数 ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date'; 
$order = isset($_GET['order']) ? $_GET['order'] : 'desc'; 

// 日期筛选
$filterDate = isset($_GET['f_date']) ? $_GET['f_date'] : '';
$filterMonth = isset($_GET['f_month']) ? $_GET['f_month'] : '';
$filterYear = isset($_GET['f_year']) ? $_GET['f_year'] : '';

// 状态筛选 (多选)
$filterStatus = isset($_GET['f_status']) ? $_GET['f_status'] : [];
if (is_string($filterStatus)) $filterStatus = $filterStatus === '' ? [] : explode(',', $filterStatus);

// 金额筛选
$minAmount = isset($_GET['min_amount']) && $_GET['min_amount'] !== '' ? floatval($_GET['min_amount']) : '';
$maxAmount = isset($_GET['max_amount']) && $_GET['max_amount'] !== '' ? floatval($_GET['max_amount']) : '';

// --- 3. 分页参数 ---
$results_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $results_per_page;

// --- 4. 统计 & 构建查询条件 ---

// 计算总提款 (仅 Approved/Completed) 用于计算余额
$sqlWithdrawn = "SELECT SUM(Amount) as total FROM withdrawals WHERE Case_ID = $caseId AND (Status = 'Approved' OR Status = 'Completed')";
$totalWithdrawn = (float)($conn->query($sqlWithdrawn)->fetch_assoc()['total'] ?? 0);
$availableBalance = $totalRaised - $totalWithdrawn;

// 构建列表 SQL 条件
$conditions = [];
$conditions[] = "w.Case_ID = $caseId"; // 核心：过滤 Case ID

if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    // 搜索 Admin 名字 或 Withdrawal ID
    $conditions[] = "(ad.Admin_Name LIKE '%$s%' OR w.Withdrawal_ID LIKE '%$s%')";
}

// 日期筛选
if (!empty($filterDate)) {
    $d = $conn->real_escape_string($filterDate);
    $conditions[] = "DATE(w.Request_Date) = '$d'";
} else {
    if (!empty($filterYear)) {
        $y = $conn->real_escape_string($filterYear);
        $conditions[] = "YEAR(w.Request_Date) = '$y'";
    }
    if (!empty($filterMonth)) {
        $m = $conn->real_escape_string($filterMonth);
        $conditions[] = "MONTH(w.Request_Date) = '$m'";
    }
}

// 状态筛选 (多选)
if (!empty($filterStatus)) {
    $validStatuses = array_filter($filterStatus, function($v) { return $v !== '' && $v !== 'All'; });
    if (!empty($validStatuses)) {
        $statusStr = implode("','", array_map(function($s) use ($conn) { return $conn->real_escape_string($s); }, $validStatuses));
        $conditions[] = "w.Status IN ('$statusStr')";
    }
}

// 金额筛选
if ($minAmount !== '') {
    $conditions[] = "w.Amount >= $minAmount";
}
if ($maxAmount !== '') {
    $conditions[] = "w.Amount <= $maxAmount";
}

$whereSql = "WHERE " . implode(" AND ", $conditions);

// --- 5. 分页计算 ---
// JOIN admin 表以获取 Requester Name
$countSql = "SELECT COUNT(*) as total FROM withdrawals w LEFT JOIN admin ad ON w.Admin_ID = ad.Admin_ID $whereSql";
$countResult = $conn->query($countSql);
$total_records = $countResult->fetch_assoc()['total'];
$total_pages = ceil($total_records / $results_per_page);

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $start_from = ($page - 1) * $results_per_page;
}

// 排序
$sortMap = ['date' => 'w.Request_Date', 'requester' => 'ad.Admin_Name', 'status' => 'w.Status', 'amount' => 'w.Amount'];
$orderByCol = isset($sortMap[$sort]) ? $sortMap[$sort] : 'w.Request_Date';
$orderDir = ($order === 'asc') ? 'ASC' : 'DESC';

// --- 6. 数据查询 ---
$sqlList = "SELECT w.*, ad.Admin_Name, ad.Admin_Email 
            FROM withdrawals w 
            LEFT JOIN admin ad ON w.Admin_ID = ad.Admin_ID
            $whereSql 
            ORDER BY $orderByCol $orderDir
            LIMIT $start_from, $results_per_page";

$result = $conn->query($sqlList);
$withdrawals = [];
if ($result) {
    while($row = $result->fetch_assoc()) $withdrawals[] = $row;
}

$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// 辅助数据
$years = range(date('Y'), 2023); 
$months = [1=>'January', 2=>'February', 3=>'March', 4=>'April', 5=>'May', 6=>'June', 7=>'July', 8=>'August', 9=>'September', 10=>'October', 11=>'November', 12=>'December'];
$allStatuses = ['Pending', 'Approved', 'Completed', 'Rejected'];

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
    <title>Withdrawal History - <?php echo htmlspecialchars($caseTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        :root { --primary: #F28585; }
        
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; cursor: pointer; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #666; font-size: 14px; }
        
        .history-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 1000px; margin: 0 auto 30px; }
        
        /* 3-Column Grid for Cards */
        .balance-cards { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .b-card { padding: 20px; border-radius: 8px; color: white; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        
        /* Specific Card Colors */
        .b-card-1 { background: linear-gradient(135deg, #17a2b8 0%, #36b9cc 100%); box-shadow: 0 4px 15px rgba(23, 162, 184, 0.2); } /* Raised - Blue/Teal */
        .b-card-2 { background: linear-gradient(135deg, #F28585 0%, #ff9a9a 100%); box-shadow: 0 4px 15px rgba(242, 133, 133, 0.3); } /* Withdrawn - Red/Pink */
        .b-card-3 { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2); } /* Balance - Green */
        
        .b-label { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 5px; white-space: nowrap; }
        .b-val { font-size: 24px; font-weight: 700; }
        .b-icon { position: absolute; right: 15px; bottom: 15px; font-size: 36px; opacity: 0.2; }

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
        .st-approved { background: #d4edda; color: #155724; }
        .st-completed { background: #d4edda; color: #155724; }
        .st-pending { background: #fff3cd; color: #856404; }
        .st-rejected { background: #f8d7da; color: #721c24; }
        
        .purpose-text { font-weight: 600; color: #333; }
        .sub-text { font-size: 12px; color: #888; margin-top: 2px; }
        .amount-neg { font-weight: 700; color: #dc3545; }
        .empty-state { text-align: center; padding: 50px 20px; color: #999; }
        .empty-state i { font-size: 40px; margin-bottom: 15px; display: block; color: #ddd; }
        
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 20px; }
        .pagination-info { font-size: 13px; color: #666; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 6px 12px; border: 1px solid #ddd; background-color: #fff; color: #333; text-decoration: none; border-radius: 4px; font-size: 13px; transition: all 0.2s; }
        .pagination-btn:hover { background-color: #f8f9fa; border-color: #ccc; }
        .pagination-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; background-color: #f9f9f9; }

        /* Modals (Matching Activity Design) */
        .filter-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2000; justify-content: center; align-items: center; }
        .filter-modal-content { background: white; width: 300px; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); animation: fadeIn 0.2s; position: relative; }
        .close-filter { position: absolute; top: 15px; right: 15px; font-size: 18px; color: #999; cursor: pointer; }
        .sort-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; background: #fff; text-align: left; border-radius: 5px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #555; text-decoration: none; }
        .sort-btn:hover { background: #f8f9fa; border-color: #ddd; color: var(--primary); }
        .filter-row { display: flex; gap: 5px; margin-bottom: 5px; }
        .filter-select { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; width: 100%; margin-bottom: 8px; }
        .filter-input { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        .filter-go { padding: 8px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn-apply-full { width: 100%; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 5px; }
        .checkbox-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
        .checkbox-item { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px; color: #555; }
        .checkbox-item input { width: 16px; height: 16px; accent-color: var(--primary); }
        .amount-row { display: flex; align-items: center; gap: 10px; margin-bottom:10px; }
        .amount-label { width: 70px; font-size: 13px; color: #666; font-weight: 600; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="#" onclick="window.close(); return false;" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Case</a>
                <div class="header-title">
                    <h1>Withdrawal History</h1>
                    <p><?php echo htmlspecialchars($caseTitle); ?></p>
                </div>
                <div style="width: 80px;"></div>
            </div>

            <div class="history-container">
                <div class="balance-cards">
                    <div class="b-card b-card-1">
                        <span class="b-label">Total Raised</span>
                        <span class="b-val">RM <?php echo number_format($totalRaised, 2); ?></span>
                        <i class="fas fa-hand-holding-usd b-icon"></i>
                    </div>
                    <div class="b-card b-card-2">
                        <span class="b-label">Total Withdrawn</span>
                        <span class="b-val">RM <?php echo number_format($totalWithdrawn, 2); ?></span>
                        <i class="fas fa-money-bill-wave b-icon"></i>
                    </div>
                    <div class="b-card b-card-3">
                        <span class="b-label">Available Fund</span>
                        <span class="b-val">RM <?php echo number_format($availableBalance, 2); ?></span>
                        <i class="fas fa-wallet b-icon"></i>
                    </div>
                </div>

                <form class="search-filter-container" method="GET">
                    <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
                    <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                    <input type="hidden" name="order" value="<?php echo $order; ?>">
                    <?php if(!empty($filterDate)) echo "<input type='hidden' name='f_date' value='$filterDate'>"; ?>
                    <?php if(!empty($filterYear)) echo "<input type='hidden' name='f_year' value='$filterYear'>"; ?>
                    <?php if(!empty($filterMonth)) echo "<input type='hidden' name='f_month' value='$filterMonth'>"; ?>
                    <?php foreach($filterStatus as $fs): ?>
                        <input type="hidden" name="f_status[]" value="<?php echo htmlspecialchars($fs); ?>">
                    <?php endforeach; ?>
                    <?php if($minAmount !== '') echo "<input type='hidden' name='min_amount' value='$minAmount'>"; ?>
                    <?php if($maxAmount !== '') echo "<input type='hidden' name='max_amount' value='$maxAmount'>"; ?>

                    <input type="text" name="search" class="search-input" placeholder="Search Requester Name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($search) || !empty($filterDate) || !empty($filterYear) || !empty($filterMonth) || !empty($filterStatus) || $minAmount !== '' || $maxAmount !== ''): ?>
                        <a href="?case_id=<?php echo $caseId; ?>" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>

                <?php if(!empty($filterDate) || !empty($filterYear) || !empty($filterMonth) || !empty($filterStatus) || $minAmount !== '' || $maxAmount !== ''): ?>
                    <div style="margin-bottom:15px; font-size:13px; color:#666; background:#fff3cd; padding:8px 12px; border-radius:5px; border:1px solid #ffeeba; display:inline-block;">
                        <i class="fas fa-filter"></i> Active Filters: 
                        <?php if(!empty($filterDate)) echo "Date: <b>$filterDate</b>; "; ?>
                        <?php if(!empty($filterYear)) echo "Year: <b>$filterYear</b>; "; ?>
                        <?php if(!empty($filterMonth)) echo "Month: <b>".$months[$filterMonth]."</b>; "; ?>
                        <?php if(!empty($filterStatus)) echo "Status: <b>" . implode(", ", $filterStatus) . "</b>; "; ?>
                        <?php if($minAmount !== '' || $maxAmount !== '') echo "Amount: <b>RM " . ($minAmount ?: '0') . " - " . ($maxAmount ?: '∞') . "</b>;"; ?>
                    </div>
                <?php endif; ?>

                <?php if (count($withdrawals) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th onclick="openModal('dateModal')">Date <?php if($sort=='date') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th onclick="openModal('requesterModal')">Requested By <?php if($sort=='requester') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th onclick="openModal('statusModal')">Status <?php if($sort=='status') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th style="text-align:left;" onclick="openModal('amountModal')">Amount <?php if($sort=='amount') echo ($order=='asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'); else echo '<i class="fas fa-sort"></i>'; ?></th>
                                <th style="text-align:left;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($withdrawals as $w): ?>
                                <?php 
                                    $st = ucfirst(strtolower($w['Status']));
                                    $stClass = 'st-pending';
                                    if ($st == 'Approved' || $st == 'Completed') $stClass = 'st-approved';
                                    elseif ($st == 'Rejected') $stClass = 'st-rejected';
                                ?>
                                <tr class="clickable-row" onclick="window.open('admin_withdrawal_details.php?id=<?php echo $w['Withdrawal_ID']; ?>', '_blank')">
                                    <td>
                                        <div style="font-weight:600;"><?php echo date('d M Y', strtotime($w['Request_Date'])); ?></div>
                                        <div class="sub-text"><?php echo date('h:i A', strtotime($w['Request_Date'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="purpose-text"><?php echo htmlspecialchars($w['Admin_Name']); ?></div>
                                        <div class="sub-text"><i class="far fa-envelope"></i> <?php echo htmlspecialchars($w['Admin_Email']); ?></div>
                                    </td>
                                    <td><span class="status-badge <?php echo $stClass; ?>"><?php echo $st; ?></span></td>
                                    <td style="text-align:left;"><span class="amount-neg">- RM <?php echo number_format($w['Amount'], 2); ?></span></td>
                                    <td style="text-align:left;"><span style="color:var(--primary); font-size:12px; font-weight:600;">Details <i class="fas fa-chevron-right"></i></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="pagination-container">
                        <div class="pagination-info">Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?> results</div>
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
                        <i class="fas fa-file-invoice-dollar"></i>
                        <p>No withdrawal records found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="dateModal" class="filter-modal" onclick="closeModal(event, 'dateModal')">
        <div class="filter-modal-content">
            <span class="close-filter" onclick="document.getElementById('dateModal').style.display='none'">&times;</span>
            <h4 style="margin:0 0 15px 0; font-size:14px;">Date Options</h4>
            <a href="<?php echo buildUrl(['sort'=>'date', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-up"></i> Oldest to Newest</a>
            <a href="<?php echo buildUrl(['sort'=>'date', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-amount-down"></i> Newest to Oldest</a>
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <form class="filter-row" method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['f_date', 'f_month', 'f_year', 'page'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                <input type="date" name="f_date" class="filter-input" required value="<?php echo htmlspecialchars($filterDate); ?>">
                <button type="submit" class="filter-go">Go</button>
            </form>
            <form method="GET" style="margin-top:10px;">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['f_date', 'f_month', 'f_year', 'page'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                <select name="f_year" class="filter-select">
                    <option value="">All Years</option>
                    <?php foreach($years as $y) echo "<option value='$y' ".($filterYear==$y?'selected':'').">$y</option>"; ?>
                </select>
                <select name="f_month" class="filter-select">
                    <option value="">All Months</option>
                    <?php foreach($months as $k => $v) echo "<option value='$k' ".($filterMonth==$k?'selected':'').">$v</option>"; ?>
                </select>
                <button type="submit" class="btn-apply-full" style="margin-top:5px;">Apply Filter</button>
            </form>
        </div>
    </div>

    <div id="requesterModal" class="filter-modal" onclick="closeModal(event, 'requesterModal')">
        <div class="filter-modal-content">
            <span class="close-filter" onclick="document.getElementById('requesterModal').style.display='none'">&times;</span>
            <h4 style="margin:0 0 15px 0; font-size:14px;">Sort by Requester</h4>
            <a href="<?php echo buildUrl(['sort'=>'requester', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Name A to Z</a>
            <a href="<?php echo buildUrl(['sort'=>'requester', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Name Z to A</a>
        </div>
    </div>

    <div id="statusModal" class="filter-modal" onclick="closeModal(event, 'statusModal')">
        <div class="filter-modal-content">
            <span class="close-filter" onclick="document.getElementById('statusModal').style.display='none'">&times;</span>
            <h4 style="margin:0 0 15px 0; font-size:14px;">Filter Status</h4>
            <a href="<?php echo buildUrl(['sort'=>'status', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-down"></i> Status A-Z</a>
            <a href="<?php echo buildUrl(['sort'=>'status', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-alpha-up"></i> Status Z-A</a>
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <form method="GET">
                <?php foreach($_GET as $key => $val) if($key !== 'f_status' && $key !== 'page') echo "<input type='hidden' name='$key' value='$val'>"; ?>
                <div class="checkbox-list">
                    <label class="checkbox-item"><input type="checkbox" name="f_status[]" value="All" <?php if(empty($filterStatus) || in_array('All', $filterStatus)) echo 'checked'; ?>> <b>All Statuses</b></label>
                    <?php foreach($allStatuses as $st): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="f_status[]" value="<?php echo $st; ?>" <?php if(in_array($st, $filterStatus) && !in_array('All', $filterStatus)) echo 'checked'; ?>> <?php echo $st; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn-apply-full">Apply Status Filter</button>
            </form>
        </div>
    </div>
    
    <div id="amountModal" class="filter-modal" onclick="closeModal(event, 'amountModal')">
        <div class="filter-modal-content">
            <span class="close-filter" onclick="document.getElementById('amountModal').style.display='none'">&times;</span>
            <h4 style="margin:0 0 15px 0; font-size:14px;">Filter by Amount</h4>
            
            <form method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['min_amount', 'max_amount'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                
                <div class="amount-row">
                    <span class="amount-label">Min (RM):</span>
                    <input type="number" name="min_amount" class="filter-input" placeholder="0.00" step="0.01" min="0" value="<?php echo htmlspecialchars($minAmount); ?>">
                </div>
                <div class="amount-row">
                    <span class="amount-label">Max (RM):</span>
                    <input type="number" name="max_amount" class="filter-input" placeholder="No Limit" step="0.01" min="0" value="<?php echo htmlspecialchars($maxAmount); ?>">
                </div>
                
                <button type="submit" class="btn-apply-full">Apply Filter</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(e, id) { if(e.target.id === id) document.getElementById(id).style.display = 'none'; }
        
        // Auto-uncheck "All" if specific status checked, and vice versa
        const allCheck = document.querySelector('input[value="All"]');
        const otherChecks = document.querySelectorAll('input[name="f_status[]"]:not([value="All"])');
        
        if(allCheck && otherChecks.length > 0) {
            allCheck.addEventListener('change', function() {
                if(this.checked) otherChecks.forEach(c => c.checked = false);
            });
            otherChecks.forEach(c => {
                c.addEventListener('change', function() {
                    if(this.checked) allCheck.checked = false;
                });
            });
        }
    </script>
</body>
</html>