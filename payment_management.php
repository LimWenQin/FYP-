<?php
// payment_management.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- Get Current Admin Info ---
$currentAdminId = $_SESSION['admin_id'];
$adminSql = "SELECT Admin_Name, Admin_Role, Admin_ProfilePicture FROM admin WHERE Admin_ID = $currentAdminId";
$adminResult = $conn->query($adminSql);

if ($adminResult && $adminResult->num_rows > 0) {
    $adminData = $adminResult->fetch_assoc();
    $adminName = $adminData['Admin_Name'];
    $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
}

// ==========================================
// 0. HANDLE EXPORT TO EXCEL (Income Only)
// ==========================================
if (isset($_GET['export']) && $_GET['export'] == 'income') {
    $filename = "report_income_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr><th>Ref ID</th><th>Date & Time</th><th>Donor Name</th><th>Donor Email</th><th>Target</th><th>Amount</th><th>Method</th><th>Status</th></tr>';
    $sql = "SELECT o.Order_TXN_Ref, o.Order_Created_At, d.Donor_Name, d.Donor_Email, o.Order_Amount, o.Order_PaymentMethod, o.Order_PaymentStatus,
            b.Branch_Name, a.Activity_Name, s.Case_Title
            FROM orders o
            JOIN donor d ON o.Donor_ID = d.Donor_ID
            LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
            LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
            LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
            ORDER BY o.Order_Created_At DESC";
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) {
        $target = "General (Other)";
        if($row['Branch_Name']) $target = "Branch: ".$row['Branch_Name'];
        elseif($row['Activity_Name']) $target = "Activity: ".$row['Activity_Name'];
        elseif($row['Case_Title']) $target = "Case: ".$row['Case_Title'];
        
        echo "<tr>
            <td>{$row['Order_TXN_Ref']}</td>
            <td>{$row['Order_Created_At']}</td>
            <td>{$row['Donor_Name']}</td>
            <td>{$row['Donor_Email']}</td>
            <td>{$target}</td>
            <td>{$row['Order_Amount']}</td>
            <td>{$row['Order_PaymentMethod']}</td>
            <td>{$row['Order_PaymentStatus']}</td>
        </tr>";
    }
    echo '</table>';
    exit();
}

// --- Stats Functions ---
$totalRevenue = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success')")->fetch_assoc()['total'] ?? 0;
$recurringRevenue = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success') AND Order_Type = 'Recurring'")->fetch_assoc()['total'] ?? 0;
$totalPoints = floor($totalRevenue / 10);
$pendingCount = $conn->query("SELECT COUNT(*) as count FROM orders WHERE Order_PaymentStatus = 'Pending'")->fetch_assoc()['count'];

// ==========================================
// LIST 1: TRANSACTION HISTORY (LEFT - 收入总账)
// ==========================================
$limit = 6; 
$page_inc = isset($_GET['page_inc']) && is_numeric($_GET['page_inc']) ? (int)$_GET['page_inc'] : 1;
if ($page_inc < 1) $page_inc = 1;
$offset_inc = ($page_inc - 1) * $limit;

$sql_inc_count = "SELECT COUNT(*) as total FROM orders";
$total_inc_recs = $conn->query($sql_inc_count)->fetch_assoc()['total'];
$total_pages_inc = ceil($total_inc_recs / $limit);

$sql_inc = "SELECT o.Order_ID, o.Order_TXN_Ref, o.Order_Type, d.Donor_Name, d.Donor_Email, o.Order_Amount, o.Order_Created_At, o.Order_PaymentMethod, o.Order_PaymentStatus,
            b.Branch_Name, a.Activity_Name, s.Case_Title
            FROM orders o
            JOIN donor d ON o.Donor_ID = d.Donor_ID
            LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
            LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
            LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
            ORDER BY o.Order_Created_At DESC LIMIT $offset_inc, $limit";
$recentTransactions = $conn->query($sql_inc);

$start_inc = ($total_inc_recs > 0) ? $offset_inc + 1 : 0;
$end_inc = min($offset_inc + $limit, $total_inc_recs);

// ==========================================
// LIST 2: E-WALLET TRANSACTIONS (RIGHT - 钱包流水)
// ==========================================
$sql_wallet = "SELECT w.*, d.Donor_Name, d.Donor_ProfilePicture, o.Order_TXN_Ref
               FROM wallet_transaction w
               JOIN donor d ON w.Donor_ID = d.Donor_ID
               LEFT JOIN orders o ON w.Order_ID = o.Order_ID
               ORDER BY w.Created_At DESC LIMIT 6";
$walletTransactions = $conn->query($sql_wallet);

// Chart Data
function getMonthlyRevenueChartData($conn) {
    $data = [];
    for ($i = 5; $i >= 0; $i--) {
        $monthLabel = date('M Y', strtotime("-$i months"));
        $monthStart = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime("-$i months"));
        $sql = "SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success') AND Order_Created_At BETWEEN '$monthStart 00:00:00' AND '$monthEnd 23:59:59'";
        $data['labels'][] = $monthLabel;
        $data['revenue'][] = $conn->query($sql)->fetch_assoc()['total'] ?? 0;
    }
    return $data;
}
$chartData = getMonthlyRevenueChartData($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - Love Bridge</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    
    <style>
        :root {
            --primary: #F28585; 
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --dark: #333;
            --info: #17a2b8;
            --warning: #ffc107;
            --gray-light: #f8f9fa;
        }
        body { background-color: #f4f6f9; }
        .dashboard-content { padding: 25px; }

        /* Floating Alerts */
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: center; gap: 10px; max-width: 400px; animation: slideIn 0.3s; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* NOTE: I have REMOVED the specific .welcome-section CSS here so it inherits 
           the exact same style as your Staff Management page. */

        /* Header Actions (Export Btn) */
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .btn-export { 
            background-color: #28a745; 
            color: white; 
            border: none;
            padding: 8px 15px;
            border-radius: 5px; 
            font-size: 13px;
            font-weight: 500; 
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.3s;
            text-decoration: none;
        }
        .btn-export:hover { background-color: #218838; color: white; }

        /* Stats Cards */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #6c757d; margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; margin: 0; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        
        .text-success { color: var(--success); }
        .text-info { color: var(--info); }
        .text-warning { color: var(--warning); }
        .text-danger { color: var(--danger); }

        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        /* --- Grid Layout (Strict 6:4 Ratio) --- */
        .payment-content-grid {
            display: grid;
            grid-template-columns: 6fr 4fr; /* Left 60%, Right 40% */
            gap: 25px;
            margin-bottom: 30px;
            align-items: stretch; 
        }

        /* --- Content Card --- */
        .content-card {
            background: white; border-radius: 12px; padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); border: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column; 
            height: 100%; 
        }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-header h2 { font-size: 18px; font-weight: 700; color: #333; margin: 0; }

        /* Tables */
        .table-container { flex: 1; overflow-x: auto; min-height: 350px; }
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .custom-table thead th { color: #8898aa; font-weight: 600; text-transform: uppercase; font-size: 11px; padding: 0 15px 10px 15px; text-align: left; }
        .custom-table tbody tr { background: white; transition: transform 0.2s; }
        .custom-table tbody tr:hover { background-color: #fcfcfc; }
        .custom-table td { padding: 12px 15px; vertical-align: middle; color: #525f7f; font-size: 13px; border-top: 1px solid #f5f5f5; border-bottom: 1px solid #f5f5f5; }
        .custom-table td:first-child { border-left: 1px solid #f5f5f5; border-radius: 8px 0 0 8px; }
        .custom-table td:last-child { border-right: 1px solid #f5f5f5; border-radius: 0 8px 8px 0; }

        /* Badges */
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 10px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff8e1; color: #ffca28; }
        .badge-failed { background: #fee2e2; color: #f5365c; }
        
        .badge-credit { background: #e6f4ea; color: #1e7e34; }
        .badge-debit { background: #fce8e6; color: #c5221f; }

        /* Action Button */
        .btn-action {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px;
            background-color: #e3f2fd; color: #1976d2;
            border-radius: 50%; text-decoration: none;
            transition: all 0.2s; border: 1px solid #bbdefb;
        }
        .btn-action:hover { background-color: #1976d2; color: white; transform: translateY(-2px); }

        /* Pagination Controls (Left Column Only) */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: auto; }
        .pagination-info { font-size: 13px; color: #8898aa; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 6px 12px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 13px; }
        .pagination-btn:hover { background-color: #e9ecef; }
        .pagination-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; pointer-events: none; }

        @media (max-width: 1200px) { .payment-content-grid { grid-template-columns: 1fr; } }
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
            
            <div class="welcome-section">
                <h1>Payment Management</h1>
                <p>Monitor revenue, wallet usage, and donation records.</p>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL REVENUE</h3>
                        <h2>RM <?php echo number_format($totalRevenue, 2); ?></h2>
                        <p class="text-success"><i class="fas fa-arrow-up"></i> Lifetime</p>
                    </div>
                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.2); color: #28a745;"><i class="fas fa-wallet"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>RECURRING</h3>
                        <h2>RM <?php echo number_format($recurringRevenue, 2); ?></h2>
                        <p class="text-info">Recurring</p>
                    </div>
                    <div class="stat-icon" style="background: rgba(23, 162, 184, 0.2); color: #17a2b8;"><i class="fas fa-sync"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>POINTS ISSUED</h3>
                        <h2><?php echo number_format($totalPoints); ?></h2>
                        <p class="text-warning">RM 10 = 1 Point</p>
                    </div>
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.2); color: #ffc107;"><i class="fas fa-star"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>PENDING</h3>
                        <h2><?php echo $pendingCount; ?></h2>
                        <p class="text-danger">Needs Action</p>
                    </div>
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.2); color: #dc3545;"><i class="fas fa-exclamation"></i></div>
                </div>
            </div>

            <div class="payment-content-grid">
                
                <div class="content-card">
                    <div class="section-header">
                        <h2>Transaction History</h2>
                        <a href="?export=income" class="btn-export"><i class="fas fa-download"></i> Export Data</a>
                    </div>

                    <div class="table-container">
                        <table class="custom-table">
                            <thead><tr><th>Ref & Time</th><th>Donor</th><th>Target</th><th>Amount</th><th>Method</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if($recentTransactions->num_rows > 0): ?>
                                    <?php while($txn = $recentTransactions->fetch_assoc()): 
                                        $targetName = "General Fund";
                                        if ($txn['Case_Title']) $targetName = "Case: " . $txn['Case_Title'];
                                        elseif ($txn['Activity_Name']) $targetName = "Act: " . $txn['Activity_Name'];
                                        elseif ($txn['Branch_Name']) $targetName = "Branch: " . $txn['Branch_Name'];
                                        
                                        $dateTimeObj = new DateTime($txn['Order_Created_At']);
                                        $dateStr = $dateTimeObj->format('M d, Y');
                                        $timeStr = $dateTimeObj->format('h:i A');
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600; color:#333;"><?php echo $txn['Order_TXN_Ref']; ?></div>
                                            <div style="font-size:11px; color:#888;"><?php echo $dateStr; ?> <span style="color:#aaa;">| <?php echo $timeStr; ?></span></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;"><?php echo htmlspecialchars($txn['Donor_Name']); ?></div>
                                            <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($txn['Donor_Email']); ?></div>
                                        </td>
                                        <td><span style="font-size:11px; display:block; max-width:120px;"><?php echo htmlspecialchars($targetName); ?></span></td>
                                        <td style="font-weight:700; color:#28a745;">RM <?php echo number_format($txn['Order_Amount'], 2); ?></td>
                                        <td><span style="font-size:12px; color:#555;"><?php echo $txn['Order_PaymentMethod']; ?></span></td>
                                        <td>
                                            <a href="admin_payment_details.php?id=<?php echo $txn['Order_ID']; ?>" class="btn-action" target="_blank" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">No transaction records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-container">
                        <div class="pagination-info">Showing <?php echo $start_inc; ?> - <?php echo $end_inc; ?> of <?php echo $total_inc_recs; ?></div>
                        <div class="pagination-controls">
                            <?php if ($page_inc > 1): ?>
                                <a href="?page_inc=<?php echo $page_inc-1; ?>" class="pagination-btn">Previous</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Previous</span>
                            <?php endif; ?>

                            <?php for($i=1; $i<=$total_pages_inc; $i++): ?>
                                <a href="?page_inc=<?php echo $i; ?>" class="pagination-btn <?php echo ($i==$page_inc)?'active':''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page_inc < $total_pages_inc): ?>
                                <a href="?page_inc=<?php echo $page_inc+1; ?>" class="pagination-btn">Next</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="section-header">
                        <h2><i class="fas fa-wallet" style="color: #F28585; margin-right:8px;"></i> E-Wallet Log</h2>
                    </div>
                    
                    <div class="table-container">
                        <table class="custom-table">
                            <thead><tr><th>User / Time</th><th>Type</th><th>Amount</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if($walletTransactions && $walletTransactions->num_rows > 0): ?>
                                    <?php while($wt = $walletTransactions->fetch_assoc()): 
                                         $wtTime = new DateTime($wt['Created_At']);
                                         $wtDateStr = $wtTime->format('M d');
                                         $wtTimeStr = $wtTime->format('h:i A');
                                         $isCredit = ($wt['Transaction_Type'] == 'Credit');
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; margin-bottom:3px;">
                                                <?php if($wt['Donor_ProfilePicture']): ?>
                                                    <img src="<?php echo $wt['Donor_ProfilePicture']; ?>" style="width:20px; height:20px; border-radius:50%; margin-right:6px; object-fit:cover;">
                                                <?php else: ?>
                                                    <div style="width:20px; height:20px; border-radius:50%; background:#eee; margin-right:6px; font-size:10px; display:flex; align-items:center; justify-content:center;"><i class="fas fa-user"></i></div>
                                                <?php endif; ?>
                                                <span style="font-weight:600; font-size:12px;"><?php echo htmlspecialchars($wt['Donor_Name']); ?></span>
                                            </div>
                                            <div style="font-size:10px; color:#888;"><?php echo $wtDateStr . ' | ' . $wtTimeStr; ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $isCredit ? 'badge-credit' : 'badge-debit'; ?>">
                                                <?php echo $isCredit ? 'Credit (Top-up)' : 'Debit (Spent)'; ?>
                                            </span>
                                        </td>
                                        <td style="font-weight:700; color: <?php echo $isCredit ? '#28a745' : '#dc3545'; ?>;">
                                            <?php echo $isCredit ? '+' : '-'; ?> RM <?php echo number_format($wt['Amount'], 2); ?>
                                        </td>
                                        <td>
                                            <?php if($wt['Order_ID']): ?>
                                                <a href="admin_payment_details.php?id=<?php echo $wt['Order_ID']; ?>" class="btn-action" target="_blank" title="View Receipt">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                            <?php else: ?>
                                                <span style="color:#ccc; font-size:10px;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">No wallet activity yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="content-card chart-section">
                <div class="section-header"><h2>Revenue Analytics</h2></div>
                <div style="height: 350px;"><canvas id="revenueChart"></canvas></div>
            </div>

        </div>
    </div>

    <script>
        // Chart Config
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartData['labels']); ?>,
                datasets: [{
                    label: 'Revenue (RM)',
                    data: <?php echo json_encode($chartData['revenue']); ?>,
                    borderColor: '#F28585', backgroundColor: 'rgba(242, 133, 133, 0.1)',
                    fill: true, tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Auto hide floating alert after 5 seconds
        setTimeout(() => {
            const success = document.getElementById('floatingSuccess');
            const error = document.getElementById('floatingError');
            if(success) success.style.display = 'none';
            if(error) error.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>