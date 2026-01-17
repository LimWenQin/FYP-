<?php
// special_case_donation_history.php
session_start();

// Check login
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

// Get Case Details for Header
$caseRes = $conn->query("SELECT Case_Title FROM special_case WHERE Case_ID = $caseId");
$caseTitle = ($caseRes->num_rows > 0) ? $caseRes->fetch_assoc()['Case_Title'] : "Unknown Case";

// --- 1. 获取筛选和排序参数 ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date'; // 默认按日期
$order = isset($_GET['order']) ? $_GET['order'] : 'desc'; // 默认降序

// 日期特定筛选
$filterDate = isset($_GET['f_date']) ? $_GET['f_date'] : '';
$filterMonth = isset($_GET['f_month']) ? $_GET['f_month'] : '';
$filterYear = isset($_GET['f_year']) ? $_GET['f_year'] : '';

// 支付方式筛选
$filterMethod = isset($_GET['f_method']) ? $_GET['f_method'] : '';

// 金额范围筛选
$minAmount = isset($_GET['min_amount']) && $_GET['min_amount'] !== '' ? floatval($_GET['min_amount']) : '';
$maxAmount = isset($_GET['max_amount']) && $_GET['max_amount'] !== '' ? floatval($_GET['max_amount']) : '';

// --- 2. 构建 SQL 查询 ---
$whereClauses = ["o.Case_ID = $caseId", "(o.Order_Status = 'Completed' OR o.Order_Status = 'Success')"];

if ($search) {
    $s = $conn->real_escape_string($search);
    $whereClauses[] = "(d.Donor_Name LIKE '%$s%' OR o.Order_ID LIKE '%$s%' OR o.Order_TXN_Ref LIKE '%$s%')";
}

// 日期/月份/年份 筛选逻辑
if ($filterDate) {
    $whereClauses[] = "DATE(o.Order_Created_At) = '$filterDate'";
} elseif ($filterMonth && $filterYear) {
    $whereClauses[] = "MONTH(o.Order_Created_At) = '$filterMonth' AND YEAR(o.Order_Created_At) = '$filterYear'";
} elseif ($filterMonth) {
    $whereClauses[] = "MONTH(o.Order_Created_At) = '$filterMonth'";
} elseif ($filterYear) {
    $whereClauses[] = "YEAR(o.Order_Created_At) = '$filterYear'";
}

if ($filterMethod) {
    // 假设 Payment_Method 存储在 payment 表中，需要 Join
    // 如果 orders 表直接有 Payment_Method 字段，可以直接用
    // 这里假设需要 Join payment 表 (根据你的 admin_payment_details 逻辑)
    // 但为了性能，如果 payment 表 join 比较复杂，且你只要 method，可以视情况调整
    // 这里使用简单的 LEFT JOIN payment
    $whereClauses[] = "p.Payment_Method = '" . $conn->real_escape_string($filterMethod) . "'";
}

if ($minAmount !== '') {
    $whereClauses[] = "o.Order_Amount >= $minAmount";
}
if ($maxAmount !== '') {
    $whereClauses[] = "o.Order_Amount <= $maxAmount";
}

// 排序字段映射
$sortMap = [
    'date' => 'o.Order_Created_At',
    'amount' => 'o.Order_Amount',
    'name' => 'd.Donor_Name'
];
$orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : 'o.Order_Created_At';
$orderDir = ($order === 'asc') ? 'ASC' : 'DESC';

$whereSql = implode(' AND ', $whereClauses);

// 分页
$limit = 15;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 主查询
$sql = "SELECT o.Order_ID, o.Order_Created_At, o.Order_Amount, o.Order_TXN_Ref, 
               d.Donor_Name, d.Donor_Email, 
               p.Payment_Method
        FROM orders o 
        JOIN donor d ON o.Donor_ID = d.Donor_ID 
        LEFT JOIN payment p ON o.Payment_ID = p.Payment_ID
        WHERE $whereSql 
        ORDER BY $orderBy $orderDir 
        LIMIT $offset, $limit";

$result = $conn->query($sql);

// 总数查询 (用于分页)
$countSql = "SELECT COUNT(*) as total 
             FROM orders o 
             JOIN donor d ON o.Donor_ID = d.Donor_ID 
             LEFT JOIN payment p ON o.Payment_ID = p.Payment_ID
             WHERE $whereSql";
$countRes = $conn->query($countSql);
$totalRows = $countRes->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// 总捐款统计 (基于当前筛选)
$sumSql = "SELECT SUM(o.Order_Amount) as total_sum
           FROM orders o 
           JOIN donor d ON o.Donor_ID = d.Donor_ID 
           LEFT JOIN payment p ON o.Payment_ID = p.Payment_ID
           WHERE $whereSql";
$sumRes = $conn->query($sumSql);
$totalAmountFiltered = $sumRes->fetch_assoc()['total_sum'] ?: 0;

// URL 参数辅助函数 (保留当前筛选参数)
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
    <title>Donation History - <?php echo htmlspecialchars($caseTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css"> <style>
        /* 复用 branch_donation_history 的样式 */
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 22px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #666; font-size: 13px; }
        
        .filters-container { background: white; padding: 15px 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        .search-group { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 250px; }
        .filter-input, .filter-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; outline: none; font-size: 13px; }
        .filter-input:focus, .filter-select:focus { border-color: var(--primary, #F28585); }
        .btn-filter { background: #f8f9fa; border: 1px solid #ddd; color: #555; padding: 8px 12px; border-radius: 5px; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-filter:hover { background: #e2e6ea; }
        .btn-primary { background: var(--primary, #F28585); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; }
        
        .table-card { background: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header-info { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
        .total-amount { font-size: 16px; font-weight: 700; color: #28a745; }
        
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f3f5; color: #495057; font-weight: 600; padding: 12px 15px; text-align: left; font-size: 13px; border-bottom: 2px solid #eee; }
        td { padding: 12px 15px; border-bottom: 1px solid #f1f1f1; font-size: 13px; color: #333; }
        tr:hover { background-color: #f8f9fa; cursor: pointer; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-method { background: #e2e3e5; color: #383d41; }
        
        .pagination { display: flex; justify-content: flex-end; padding: 15px 20px; gap: 5px; }
        .page-link { padding: 6px 12px; border: 1px solid #eee; background: white; color: #333; text-decoration: none; border-radius: 4px; font-size: 13px; transition: 0.2s; }
        .page-link.active { background: var(--primary, #F28585); color: white; border-color: var(--primary, #F28585); }
        .page-link:hover:not(.active) { background: #f1f1f1; }

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
        .sort-icon { color: #ccc; margin-left: 5px; font-size: 11px; }
        .sort-icon.active { color: #555; }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content">
        <?php include 'admin_header.php'; ?>

        <div class="page-header-compact">
            <a href="special_case_management.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Cases</a>
            <div class="header-title">
                <h1>Donation History</h1>
                <p>Case: <?php echo htmlspecialchars($caseTitle); ?></p>
            </div>
        </div>

        <form method="GET" class="filters-container">
            <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
            <div class="search-group">
                <input type="text" name="search" class="filter-input" placeholder="Search Donor, ID, Ref..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;">
                <button type="submit" class="btn-primary"><i class="fas fa-search"></i></button>
                <?php if($search || $filterDate || $filterMonth || $filterYear || $filterMethod || $minAmount || $maxAmount): ?>
                    <a href="special_case_donation_history.php?case_id=<?php echo $caseId; ?>" class="btn-filter" style="color:#dc3545; border-color:#dc3545;">Clear</a>
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
                <span class="total-amount">Total: RM <?php echo number_format($totalAmountFiltered, 2); ?></span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>
                            <a href="<?php echo getQueryLink(['sort'=>'date', 'order'=>($sort=='date' && $order=='desc')?'asc':'desc']); ?>" style="color:inherit; text-decoration:none;">
                                Date <i class="fas fa-sort<?php echo ($sort=='date') ? (($order=='desc')?'-down':'-up') : ''; ?> sort-icon <?php echo ($sort=='date')?'active':''; ?>"></i>
                            </a>
                        </th>
                        <th>Transaction Ref</th>
                        <th>
                            <a href="<?php echo getQueryLink(['sort'=>'name', 'order'=>($sort=='name' && $order=='asc')?'desc':'asc']); ?>" style="color:inherit; text-decoration:none;">
                                Donor Name <i class="fas fa-sort<?php echo ($sort=='name') ? (($order=='asc')?'-up':'-down') : ''; ?> sort-icon <?php echo ($sort=='name')?'active':''; ?>"></i>
                            </a>
                        </th>
                        <th>Donor Email</th>
                        <th>Method</th>
                        <th>
                            <a href="<?php echo getQueryLink(['sort'=>'amount', 'order'=>($sort=='amount' && $order=='desc')?'asc':'desc']); ?>" style="color:inherit; text-decoration:none;">
                                Amount (RM) <i class="fas fa-sort<?php echo ($sort=='amount') ? (($order=='desc')?'-down':'-up') : ''; ?> sort-icon <?php echo ($sort=='amount')?'active':''; ?>"></i>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr onclick="window.open('admin_payment_details.php?id=<?php echo $row['Order_ID']; ?>', '_blank')">
                                <td><?php echo date('d M Y, h:i A', strtotime($row['Order_Created_At'])); ?></td>
                                <td style="font-family:monospace; color:#666;"><?php echo htmlspecialchars($row['Order_TXN_Ref']); ?></td>
                                <td><?php echo htmlspecialchars($row['Donor_Name']); ?></td>
                                <td><?php echo htmlspecialchars($row['Donor_Email']); ?></td>
                                <td><span class="badge badge-method"><?php echo htmlspecialchars($row['Payment_Method'] ?: 'N/A'); ?></span></td>
                                <td style="font-weight:bold; color:#28a745;">RM <?php echo number_format($row['Order_Amount'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">No donations found matching criteria.</td></tr>
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