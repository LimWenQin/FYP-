<?php
// special_case_withdrawal_history.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

if (!isset($_GET['case_id'])) {
    header("Location: special_case_management.php");
    exit();
}

$caseId = intval($_GET['case_id']);

// Get Case Details for Header & Balance Calculation
$caseSql = "SELECT Case_Title, Raised_Amount FROM special_case WHERE Case_ID = $caseId";
$caseRes = $conn->query($caseSql);
if ($caseRes->num_rows == 0) die("Case not found.");
$caseData = $caseRes->fetch_assoc();
$caseTitle = $caseData['Case_Title'];
$totalRaised = floatval($caseData['Raised_Amount']);

// --- 1. 获取筛选和排序参数 ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date'; // 默认按日期
$order = isset($_GET['order']) ? $_GET['order'] : 'desc'; // 默认降序

$filterDate = isset($_GET['f_date']) ? $_GET['f_date'] : '';
$filterMonth = isset($_GET['f_month']) ? $_GET['f_month'] : '';
$filterYear = isset($_GET['f_year']) ? $_GET['f_year'] : '';
$filterStatus = isset($_GET['f_status']) ? $_GET['f_status'] : '';

$minAmount = isset($_GET['min_amount']) && $_GET['min_amount'] !== '' ? floatval($_GET['min_amount']) : '';
$maxAmount = isset($_GET['max_amount']) && $_GET['max_amount'] !== '' ? floatval($_GET['max_amount']) : '';

// --- 2. 构建 SQL 查询 ---
$whereClauses = ["Case_ID = $caseId"];

if ($search) {
    $s = $conn->real_escape_string($search);
    $whereClauses[] = "(Reference_No LIKE '%$s%' OR Purpose LIKE '%$s%')";
}

if ($filterDate) {
    $whereClauses[] = "DATE(Request_Date) = '$filterDate'";
} elseif ($filterMonth && $filterYear) {
    $whereClauses[] = "MONTH(Request_Date) = '$filterMonth' AND YEAR(Request_Date) = '$filterYear'";
} elseif ($filterMonth) {
    $whereClauses[] = "MONTH(Request_Date) = '$filterMonth'";
} elseif ($filterYear) {
    $whereClauses[] = "YEAR(Request_Date) = '$filterYear'";
}

if ($filterStatus) {
    $whereClauses[] = "Status = '" . $conn->real_escape_string($filterStatus) . "'";
}

if ($minAmount !== '') $whereClauses[] = "Amount >= $minAmount";
if ($maxAmount !== '') $whereClauses[] = "Amount <= $maxAmount";

$sortMap = [
    'date' => 'Request_Date',
    'amount' => 'Amount',
    'status' => 'Status'
];
$orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : 'Request_Date';
$orderDir = ($order === 'asc') ? 'ASC' : 'DESC';

$whereSql = implode(' AND ', $whereClauses);

// 分页
$limit = 15;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 主列表查询
$sql = "SELECT * FROM withdrawals WHERE $whereSql ORDER BY $orderBy $orderDir LIMIT $offset, $limit";
$result = $conn->query($sql);

// 分页计数
$countSql = "SELECT COUNT(*) as total FROM withdrawals WHERE $whereSql";
$totalRows = $conn->query($countSql)->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// 计算总提款额 (Total Withdrawn) - 仅计算 Approved 或 Completed 的
// 注意：为了显示“当前筛选下的总额” vs “Case 总提款”，这里我分别计算
// 1. Case 的总提款 (所有时间，Approved/Completed) 用于计算余额
$allWithdrawnSql = "SELECT SUM(Amount) as total FROM withdrawals WHERE Case_ID = $caseId AND (Status = 'Approved' OR Status = 'Completed')";
$allWithdrawn = $conn->query($allWithdrawnSql)->fetch_assoc()['total'] ?: 0;
$availableBalance = $totalRaised - $allWithdrawn;

// 2. 当前筛选列表的总额 (仅用于显示)
$sumSql = "SELECT SUM(Amount) as total_sum FROM withdrawals WHERE $whereSql";
$totalAmountFiltered = $conn->query($sumSql)->fetch_assoc()['total_sum'] ?: 0;

function getQueryLink($newParams = []) {
    $params = $_GET;
    foreach ($newParams as $key => $val) {
        $params[$key] = $val;
    }
    return '?' . http_build_query($params);
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
        /* 复用 styles */
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 22px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #666; font-size: 13px; }

        .balance-cards { display: flex; gap: 20px; margin-bottom: 20px; }
        .bal-card { flex: 1; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; }
        .bal-info h3 { font-size: 12px; color: #777; margin: 0 0 5px 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .bal-info h2 { font-size: 24px; font-weight: 700; margin: 0; }
        .bal-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        
        .filters-container { background: white; padding: 15px 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        .search-group { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 250px; }
        .filter-input, .filter-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; outline: none; font-size: 13px; }
        .btn-filter { background: #f8f9fa; border: 1px solid #ddd; color: #555; padding: 8px 12px; border-radius: 5px; cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .btn-primary { background: var(--primary, #F28585); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; }
        
        .table-card { background: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header-info { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
        
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f3f5; color: #495057; font-weight: 600; padding: 12px 15px; text-align: left; font-size: 13px; border-bottom: 2px solid #eee; }
        td { padding: 12px 15px; border-bottom: 1px solid #f1f1f1; font-size: 13px; color: #333; }
        tr:hover { background-color: #f8f9fa; cursor: pointer; }
        
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-completed { background: #cce5ff; color: #004085; }

        .pagination { display: flex; justify-content: flex-end; padding: 15px 20px; gap: 5px; }
        .page-link { padding: 6px 12px; border: 1px solid #eee; background: white; color: #333; text-decoration: none; border-radius: 4px; font-size: 13px; }
        .page-link.active { background: var(--primary, #F28585); color: white; border-color: var(--primary, #F28585); }
        
        /* Modal for Date/Amount Filters */
        .filter-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.1); z-index: 1000; justify-content: center; align-items: flex-start; padding-top: 100px; }
        .filter-modal-content { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); width: 300px; position: relative; border: 1px solid #eee; animation: fadeIn 0.2s; }
        @keyframes fadeIn { from {opacity:0; transform:translateY(-10px);} to {opacity:1; transform:translateY(0);} }
        .filter-row { margin-bottom: 12px; }
        .filter-label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #555; }
        .close-filter { position: absolute; top: 10px; right: 10px; cursor: pointer; color: #999; }
        .btn-apply-full { width: 100%; background: #28a745; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; margin-top: 10px; font-weight: 600; }
        .amount-row { display: flex; gap: 10px; align-items: center; margin-bottom: 8px; }
        .amount-label { font-size: 12px; width: 60px; color: #666; }
        .sort-icon { color: #ccc; margin-left: 5px; font-size: 11px; } .sort-icon.active { color: #555; }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content">
        <?php include 'admin_header.php'; ?>

        <div class="page-header-compact">
            <a href="special_case_management.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Cases</a>
            <div class="header-title">
                <h1>Withdrawal History</h1>
                <p>Case: <?php echo htmlspecialchars($caseTitle); ?></p>
            </div>
        </div>
        
        <div class="balance-cards">
            <div class="bal-card">
                <div class="bal-info"><h3>Total Raised</h3><h2 style="color:#17a2b8;">RM <?php echo number_format($totalRaised, 2); ?></h2></div>
                <div class="bal-icon" style="background:#e0f7fa; color:#17a2b8;"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
            <div class="bal-card">
                <div class="bal-info"><h3>Total Withdrawn</h3><h2 style="color:#dc3545;">RM <?php echo number_format($allWithdrawn, 2); ?></h2></div>
                <div class="bal-icon" style="background:#f8d7da; color:#dc3545;"><i class="fas fa-minus-circle"></i></div>
            </div>
            <div class="bal-card">
                <div class="bal-info"><h3>Available Balance</h3><h2 style="color:#28a745;">RM <?php echo number_format($availableBalance, 2); ?></h2></div>
                <div class="bal-icon" style="background:#d4edda; color:#28a745;"><i class="fas fa-wallet"></i></div>
            </div>
        </div>

        <form method="GET" class="filters-container">
            <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
            <div class="search-group">
                <input type="text" name="search" class="filter-input" placeholder="Search Reference, Purpose..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;">
                <select name="f_status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="Approved" <?php echo ($filterStatus=='Approved')?'selected':''; ?>>Approved</option>
                    <option value="Pending" <?php echo ($filterStatus=='Pending')?'selected':''; ?>>Pending</option>
                    <option value="Completed" <?php echo ($filterStatus=='Completed')?'selected':''; ?>>Completed</option>
                    <option value="Rejected" <?php echo ($filterStatus=='Rejected')?'selected':''; ?>>Rejected</option>
                </select>
                <button type="submit" class="btn-primary"><i class="fas fa-search"></i></button>
                <?php if($search || $filterDate || $filterMonth || $filterYear || $filterStatus || $minAmount || $maxAmount): ?>
                    <a href="special_case_withdrawal_history.php?case_id=<?php echo $caseId; ?>" class="btn-filter" style="color:#dc3545; border-color:#dc3545;">Clear</a>
                <?php endif; ?>
            </div>
            
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn-filter" onclick="openModal('dateModal')">
                    <i class="far fa-calendar-alt"></i> 
                    <?php echo ($filterDate || $filterMonth || $filterYear) ? 'Date Filtered' : 'Date'; ?>
                </button>
                <button type="button" class="btn-filter" onclick="openModal('amountModal')">
                    <i class="fas fa-dollar-sign"></i> 
                    <?php echo ($minAmount || $maxAmount) ? 'Amt Filtered' : 'Amount'; ?>
                </button>
            </div>
        </form>

        <div class="table-card">
            <div class="table-header-info">
                <span>Showing <?php echo $result->num_rows; ?> of <?php echo $totalRows; ?> records</span>
                <span style="font-weight:600; color:#666;">Filtered Sum: RM <?php echo number_format($totalAmountFiltered, 2); ?></span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>
                            <a href="<?php echo getQueryLink(['sort'=>'date', 'order'=>($sort=='date' && $order=='desc')?'asc':'desc']); ?>" style="color:inherit; text-decoration:none;">
                                Date <i class="fas fa-sort<?php echo ($sort=='date') ? (($order=='desc')?'-down':'-up') : ''; ?> sort-icon <?php echo ($sort=='date')?'active':''; ?>"></i>
                            </a>
                        </th>
                        <th>Reference No</th>
                        <th>Purpose</th>
                        <th>
                            <a href="<?php echo getQueryLink(['sort'=>'status', 'order'=>($sort=='status' && $order=='asc')?'desc':'asc']); ?>" style="color:inherit; text-decoration:none;">
                                Status <i class="fas fa-sort<?php echo ($sort=='status') ? (($order=='asc')?'-up':'-down') : ''; ?> sort-icon <?php echo ($sort=='status')?'active':''; ?>"></i>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getQueryLink(['sort'=>'amount', 'order'=>($sort=='amount' && $order=='desc')?'asc':'desc']); ?>" style="color:inherit; text-decoration:none;">
                                Amount (RM) <i class="fas fa-sort<?php echo ($sort=='amount') ? (($order=='desc')?'-down':'-up') : ''; ?> sort-icon <?php echo ($sort=='amount')?'active':''; ?>"></i>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $statusClass = 'status-pending';
                            if($row['Status'] == 'Approved') $statusClass = 'status-approved';
                            elseif($row['Status'] == 'Rejected') $statusClass = 'status-rejected';
                            elseif($row['Status'] == 'Completed') $statusClass = 'status-completed';
                        ?>
                            <tr onclick="window.open('admin_withdrawal_details.php?id=<?php echo $row['Withdrawal_ID']; ?>', '_blank')">
                                <td><?php echo date('d M Y', strtotime($row['Request_Date'])); ?></td>
                                <td style="font-family:monospace; color:#666;"><?php echo htmlspecialchars($row['Reference_No']); ?></td>
                                <td><?php echo htmlspecialchars($row['Purpose']); ?></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $row['Status']; ?></span></td>
                                <td style="font-weight:bold; color:#dc3545;">- RM <?php echo number_format($row['Amount'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">No withdrawals found matching criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo getQueryLink(['page' => $page - 1]); ?>" class="page-link">&laquo;</a>
                <?php endif; ?>
                
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <?php if($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <a href="<?php echo getQueryLink(['page' => $i]); ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php elseif($i == $page - 3 || $i == $page + 3): ?>
                        <span style="padding: 6px;">...</span>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo getQueryLink(['page' => $page + 1]); ?>" class="page-link">&raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="dateModal" class="filter-modal" onclick="closeModal(event, 'dateModal')">
        <div class="filter-modal-content">
            <span class="close-filter" onclick="document.getElementById('dateModal').style.display='none'">&times;</span>
            <h4 style="margin:0 0 15px 0; font-size:14px;">Filter by Date</h4>
            
            <form method="GET">
                <?php foreach($_GET as $key => $val) if(!in_array($key, ['f_date', 'f_month', 'f_year'])) echo "<input type='hidden' name='$key' value='$val'>"; ?>
                
                <div class="filter-row">
                    <span class="filter-label">Exact Date:</span>
                    <input type="date" name="f_date" class="filter-input" style="width:100%; box-sizing:border-box;" value="<?php echo $filterDate; ?>">
                </div>
                
                <div style="text-align:center; margin:10px 0; font-size:11px; color:#888;">- OR -</div>
                
                <div class="filter-row" style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <span class="filter-label">Month:</span>
                        <select name="f_month" class="filter-select" style="width:100%;">
                            <option value="">All</option>
                            <?php for($m=1; $m<=12; $m++) echo "<option value='$m' ".($filterMonth==$m?'selected':'').">".date('M', mktime(0,0,0,$m,1))."</option>"; ?>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <span class="filter-label">Year:</span>
                        <select name="f_year" class="filter-select" style="width:100%;">
                            <option value="">All</option>
                            <?php for($y=date('Y'); $y>=2023; $y--) echo "<option value='$y' ".($filterYear==$y?'selected':'').">$y</option>"; ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn-apply-full">Apply Filter</button>
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
    </script>
</body>
</html>