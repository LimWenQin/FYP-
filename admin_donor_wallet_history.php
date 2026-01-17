<?php
// admin_donor_wallet_history.php
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

// --- 1. 获取 Donor 名字 ---
$donorSql = "SELECT * FROM donor WHERE Donor_ID = $donorId";
$donorRes = $conn->query($donorSql);
if ($donorRes->num_rows == 0) die("Donor not found.");
$donorData = $donorRes->fetch_assoc();
$donorName = $donorData['Donor_Name'];

// --- [核心修复]：超级强力的余额计算逻辑 ---
// 1. 先尝试拿 Donor 表里的余额
$balanceFromTable = isset($donorData['Donor_WalletBalance']) ? floatval($donorData['Donor_WalletBalance']) : 0.00;

// 2. 实时从流水账计算 (作为主要依据，因为这才是 History 页面显示的内容)
// 逻辑修复：使用 LIKE 模糊匹配，并且如果 Type 没匹配到，就去 Description 找
$calcSql = "SELECT 
    SUM(
        CASE 
            -- 1. 先看 Transaction_Type 是否包含 Credit/Top-up (不分大小写)
            WHEN Transaction_Type LIKE '%Credit%' OR Transaction_Type LIKE '%Top-up%' OR Transaction_Type LIKE '%Deposit%' OR Transaction_Type LIKE '%Refund%' THEN Amount 
            
            -- 2. 再看 Transaction_Type 是否包含 Debit/Payment
            WHEN Transaction_Type LIKE '%Debit%' OR Transaction_Type LIKE '%Payment%' OR Transaction_Type LIKE '%Donation%' OR Transaction_Type LIKE '%Withdrawal%' THEN -Amount 
            
            -- 3. [关键修复] 如果 Type 是空的或者没匹配到，去 Description 找关键字
            WHEN Description LIKE '%Top-up%' OR Description LIKE '%Deposit%' OR Description LIKE '%Credit%' THEN Amount
            WHEN Description LIKE '%Donate%' OR Description LIKE '%Donation%' OR Description LIKE '%Payment%' OR Description LIKE '%Debit%' THEN -Amount
            
            -- 4. 实在无法识别，算作 0
            ELSE 0 
        END
    ) as Calculated_Balance
    FROM wallet_transaction 
    WHERE Donor_ID = $donorId";

$calcRes = $conn->query($calcSql);
$calcData = $calcRes->fetch_assoc();
$balanceFromHistory = $calcData['Calculated_Balance'] ? floatval($calcData['Calculated_Balance']) : 0.00;

// 逻辑：如果 Table 里是 0 (可能没更新)，就用算出来的 History 余额
$currentBalance = ($balanceFromTable != 0) ? $balanceFromTable : $balanceFromHistory;


// --- 2. 获取筛选和排序参数 ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$results_per_page = 10;

// Filters
$filterDate = isset($_GET['f_date']) ? $_GET['f_date'] : '';
$filterMonth = isset($_GET['f_month']) ? $_GET['f_month'] : '';
$filterYear = isset($_GET['f_year']) ? $_GET['f_year'] : '';
$filterType = isset($_GET['f_type']) ? $_GET['f_type'] : ''; 
$minAmount = isset($_GET['min_amount']) && $_GET['min_amount'] !== '' ? floatval($_GET['min_amount']) : '';
$maxAmount = isset($_GET['max_amount']) && $_GET['max_amount'] !== '' ? floatval($_GET['max_amount']) : '';

// --- 3. 构建 SQL ---
$conditions = [];
$conditions[] = "w.Donor_ID = $donorId";

// Search
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $conditions[] = "(w.Description LIKE '%$s%')";
}

// Date Filter
if (!empty($filterDate)) {
    $d = $conn->real_escape_string($filterDate);
    $conditions[] = "DATE(w.Created_At) = '$d'";
} elseif (!empty($filterMonth) && !empty($filterYear)) {
    $m = intval($filterMonth);
    $y = intval($filterYear);
    $conditions[] = "MONTH(w.Created_At) = $m AND YEAR(w.Created_At) = $y";
} elseif (!empty($filterYear)) {
    $y = intval($filterYear);
    $conditions[] = "YEAR(w.Created_At) = $y";
}

// Type Filter
if (!empty($filterType)) {
    $ft = $conn->real_escape_string($filterType);
    $conditions[] = "w.Transaction_Type LIKE '%$ft%'";
}

// Amount Filter
if ($minAmount !== '') {
    $conditions[] = "w.Amount >= $minAmount";
}
if ($maxAmount !== '') {
    $conditions[] = "w.Amount <= $maxAmount";
}

$whereSql = "WHERE " . implode(" AND ", $conditions);

// Sorting Map
$sortMap = [
    'date' => 'w.Created_At',
    'amount' => 'w.Amount',
    'type' => 'w.Transaction_Type',
    'desc' => 'w.Description'
];
$orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : 'w.Created_At';
$orderDir = ($order == 'asc') ? 'ASC' : 'DESC';

// Pagination
$countSql = "SELECT COUNT(*) as total FROM wallet_transaction w $whereSql";
$countRes = $conn->query($countSql);
$total_records = ($countRes && $row = $countRes->fetch_assoc()) ? $row['total'] : 0;
$total_pages = ceil($total_records / $results_per_page);

if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
$start_from = ($page - 1) * $results_per_page;
$start_record = ($total_records > 0) ? $start_from + 1 : 0;
$end_record = min($start_from + $results_per_page, $total_records);

// Main Query
$sql = "SELECT w.* FROM wallet_transaction w 
        $whereSql 
        ORDER BY $orderBy $orderDir 
        LIMIT $start_from, $results_per_page";

$result = $conn->query($sql);
$transactions = [];
while($row = $result->fetch_assoc()) $transactions[] = $row;

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
    <title>Wallet History - <?php echo htmlspecialchars($donorName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; cursor: pointer; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        .header-title { flex: 1; text-align: center; padding-right: 80px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        
        .history-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 1000px; margin: 0 auto 30px; }
        
        /* Balance Bar (Blue) */
        .summary-banner { background: #e3f2fd; color: #0d47a1; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #bbdefb; }
        .summary-label { font-size: 14px; font-weight: 600; }
        .summary-value { font-size: 20px; font-weight: 700; }

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

        /* Modals & Filters */
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
        .amount-label { width: 60px; font-size: 13px; color: #666; font-weight: 600; }
        .btn-apply-full { width: 100%; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 5px; }
        .btn-apply-full:hover { background: #e07070; }

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
                <a onclick="window.close(); return false;" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="header-title">
                    <h1>Wallet History</h1>
                    <p>Donor: <?php echo htmlspecialchars($donorName); ?></p>
                </div>
                <div style="width: 80px;"></div>
            </div>

            <div class="history-container">
                
                <div class="summary-banner">
                    <span class="summary-label">Current Wallet Balance</span>
                    <span class="summary-value">RM <?php echo number_format($currentBalance, 2); ?></span>
                </div>

                <form class="search-filter-container" method="GET">
                    <input type="hidden" name="donor_id" value="<?php echo $donorId; ?>">
                    <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                    <input type="hidden" name="order" value="<?php echo $order; ?>">
                    
                    <?php if(!empty($filterType)) echo "<input type='hidden' name='f_type' value='$filterType'>"; ?>
                    <?php if(!empty($filterDate)) echo "<input type='hidden' name='f_date' value='$filterDate'>"; ?>
                    <?php if(!empty($filterMonth)) echo "<input type='hidden' name='f_month' value='$filterMonth'>"; ?>
                    <?php if(!empty($filterYear)) echo "<input type='hidden' name='f_year' value='$filterYear'>"; ?>
                    <?php if($minAmount !== '') echo "<input type='hidden' name='min_amount' value='$minAmount'>"; ?>
                    <?php if($maxAmount !== '') echo "<input type='hidden' name='max_amount' value='$maxAmount'>"; ?>

                    <input type="text" name="search" class="search-input" placeholder="Search Description..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                    
                    <?php if(!empty($search) || !empty($filterType) || !empty($filterDate) || $minAmount !== ''): ?>
                        <a href="?donor_id=<?php echo $donorId; ?>" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th onclick="openModal('dateSortModal')">Date <i class="fas fa-sort"></i></th>
                            <th onclick="openModal('typeSortModal')">Type <i class="fas fa-sort"></i></th>
                            <th onclick="openModal('descSortModal')">Description <i class="fas fa-sort"></i></th>
                            <th onclick="openModal('amountSortModal')">Amount <i class="fas fa-sort"></i></th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach($transactions as $t): ?>
                                <?php 
                                    // 智能判断颜色和符号
                                    $type = $t['Transaction_Type'];
                                    
                                    // 统一转小写进行判断
                                    $typeLower = strtolower($type);
                                    $descLower = strtolower($t['Description']);
                                    
                                    // 默认为 Credit (绿色)
                                    $isCredit = false;
                                    
                                    // 关键词匹配逻辑 (显示层)
                                    if (strpos($typeLower, 'credit') !== false || 
                                        strpos($typeLower, 'top-up') !== false || 
                                        strpos($typeLower, 'deposit') !== false || 
                                        strpos($typeLower, 'refund') !== false) {
                                        $isCredit = true;
                                    }
                                    
                                    // 如果描述里有 top-up，也强制认为是 credit
                                    if (strpos($descLower, 'top-up') !== false || strpos($descLower, 'deposit') !== false) {
                                        $isCredit = true;
                                    }
                                    
                                    // 如果描述里有 payment/donation，认为是 debit
                                    if (strpos($descLower, 'payment') !== false || strpos($descLower, 'donation') !== false) {
                                        $isCredit = false;
                                    }

                                    $amountColor = $isCredit ? '#28a745' : '#dc3545';
                                    $sign = $isCredit ? '+' : '-';
                                    $bg = $isCredit ? '#e6f9ed' : '#ffe6e6';
                                    
                                    // 如果 Type 是空的，给个默认值
                                    $displayType = !empty($type) ? $type : ($isCredit ? 'Credit' : 'Debit');
                                ?>
                                <tr>
                                    <td><?php echo date('d M Y h:i A', strtotime($t['Created_At'])); ?></td>
                                    <td><span style="background:<?php echo $bg; ?>; color:<?php echo $amountColor; ?>; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold;"><?php echo htmlspecialchars($displayType); ?></span></td>
                                    <td><?php echo htmlspecialchars($t['Description']); ?></td>
                                    <td style="color:<?php echo $amountColor; ?>; font-weight:bold;"><?php echo $sign; ?> RM <?php echo number_format($t['Amount'], 2); ?></td>
                                    <td><a href="admin_ewallet_details.php?id=<?php echo $t['Wallet_Trans_ID']; ?>" target="_blank" style="color:var(--primary); font-weight:600;">View <i class="fas fa-chevron-right"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">No transactions found.</td></tr>
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

    <div id="amountSortModal" class="sort-modal" onclick="closeModal(event, 'amountSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Amount Options</h3><span class="sort-close" onclick="document.getElementById('amountSortModal').style.display='none'">&times;</span></div>
            <span class="sort-title">Sorting</span>
            <a href="<?php echo buildUrl(['sort'=>'amount', 'order'=>'asc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-down"></i> Small to Large</a>
            <a href="<?php echo buildUrl(['sort'=>'amount', 'order'=>'desc']); ?>" class="sort-btn"><i class="fas fa-sort-numeric-up"></i> Large to Small</a>
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by Amount Range</span>
            <form class="amount-input-group" method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['min_amount', 'max_amount', 'page'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
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

    <div id="typeSortModal" class="sort-modal" onclick="closeModal(event, 'typeSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Type Options</h3><span class="sort-close" onclick="document.getElementById('typeSortModal').style.display='none'">&times;</span></div>
            <span class="sort-title">Sorting</span>
            <a href="<?php echo buildUrl(['sort'=>'type', 'order'=>'asc']); ?>" class="sort-btn">A - Z</a>
            <a href="<?php echo buildUrl(['sort'=>'type', 'order'=>'desc']); ?>" class="sort-btn">Z - A</a>
            <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">
            <span class="sort-title">Filter by</span>
            <a href="<?php echo buildUrl(['f_type'=>'Credit']); ?>" class="sort-btn"><i class="fas fa-plus-circle" style="color:#28a745;"></i> Credit Only</a>
            <a href="<?php echo buildUrl(['f_type'=>'Debit']); ?>" class="sort-btn"><i class="fas fa-minus-circle" style="color:#dc3545;"></i> Debit Only</a>
        </div>
    </div>

    <div id="descSortModal" class="sort-modal" onclick="closeModal(event, 'descSortModal')">
        <div class="sort-modal-content">
            <div class="sort-header"><h3>Description Options</h3><span class="sort-close" onclick="document.getElementById('descSortModal').style.display='none'">&times;</span></div>
            <a href="<?php echo buildUrl(['sort'=>'desc', 'order'=>'asc']); ?>" class="sort-btn">A - Z</a>
            <a href="<?php echo buildUrl(['sort'=>'desc', 'order'=>'desc']); ?>" class="sort-btn">Z - A</a>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(e, id) { if(e.target.id === id) document.getElementById(id).style.display = 'none'; }
    </script>
</body>
</html>