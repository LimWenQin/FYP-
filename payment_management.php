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

// --- FILE UPLOAD HELPER (New Function for Multiple Files) ---
function handleWithdrawalUpload($files) {
    $paths = [];
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $dir = "uploads/withdrawals/";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    // Re-organize $_FILES structure if needed
    if (!is_array($files['name'])) return [];

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] == 0) {
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $name = 'wd_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
                if (move_uploaded_file($files['tmp_name'][$i], $dir . $name)) {
                    $paths[] = $dir . $name;
                }
            }
        }
    }
    return $paths;
}

// ==========================================
// 0. HANDLE WITHDRAWAL FORM SUBMISSION
// ==========================================
if (isset($_POST['submit_withdrawal'])) {
    $errors = []; // 用于收集错误信息

    $w_type = $_POST['withdrawal_type']; // 'branch', 'activity', 'case'
    $w_amount = floatval($_POST['amount']); // 确保是数字
    $w_bank_name = $_POST['bank_name'];
    $w_bank_acc = $_POST['bank_account'];
    
    // 基本验证
    if ($w_amount <= 0) $errors[] = "Amount must be greater than RM 0.00.";

    $branch_id = null;
    $activity_id = null;
    $case_id = null;
    $current_balance = 0;
    
    // Determine IDs and Validate Balance based on selection
    if ($w_type == 'branch') {
        $branch_id = $_POST['target_id_branch'];
        if(!$branch_id) $errors[] = "Please select a Branch.";
        else {
            $in = $conn->query("SELECT SUM(Order_Amount) as t FROM orders WHERE Branch_ID = $branch_id AND Order_PaymentStatus IN ('Success','Completed')")->fetch_assoc()['t'] ?? 0;
            $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Branch_ID = $branch_id AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
            $current_balance = $in - $out;
        }

    } elseif ($w_type == 'activity') {
        $activity_id = $_POST['target_id_activity'];
        if(!$activity_id) $errors[] = "Please select an Activity.";
        else {
            $act_sql = "SELECT Branch_ID, Activity_GetAmount FROM activity WHERE Activity_ID = '$activity_id'";
            $act_res = $conn->query($act_sql);
            if($act_res->num_rows > 0) {
                $act_row = $act_res->fetch_assoc();
                $branch_id = $act_row['Branch_ID'];
                $raised = $act_row['Activity_GetAmount'];
                $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Activity_ID = $activity_id AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
                $current_balance = $raised - $out;
            }
        }

    } elseif ($w_type == 'case') {
        $case_id = $_POST['target_id_case'];
        $branch_id = $_POST['handling_branch_id']; 
        if(!$case_id) $errors[] = "Please select a Special Case.";
        if(!$branch_id) $errors[] = "Please select a Processing Branch.";
        else {
            $case_row = $conn->query("SELECT Raised_Amount FROM special_case WHERE Case_ID = $case_id")->fetch_assoc();
            $raised = $case_row['Raised_Amount'] ?? 0;
            $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Case_ID = $case_id AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
            $current_balance = $raised - $out;
        }
    }

    // 检查余额是否足够
    if ($w_amount > $current_balance) {
        $errors[] = "Insufficient funds! Available: RM " . number_format($current_balance, 2) . ", Request: RM " . number_format($w_amount, 2);
    }

    // 处理文件上传 (Image/PDF) - Modified for Multiple Files
    $proof_json = "[]";
    if (empty($errors)) {
        if (isset($_FILES['proof_file']) && !empty($_FILES['proof_file']['name'][0])) {
            $uploaded_paths = handleWithdrawalUpload($_FILES['proof_file']);
            if (empty($uploaded_paths)) {
                $errors[] = "Failed to upload files or invalid format (Only JPG, PNG, PDF).";
            } else {
                $proof_json = json_encode($uploaded_paths); // Store as JSON string
            }
        } else {
            $errors[] = "At least one Reference Proof file is required.";
        }
    }

    // 如果没有错误，执行插入
    if (empty($errors) && $branch_id && $w_amount > 0) {
        $stmt = $conn->prepare("INSERT INTO withdrawals (Branch_ID, Activity_ID, Case_ID, Amount, Bank_Name, Bank_Account, Reference_Proof, Status, Admin_ID, Request_Date) VALUES (?, ?, ?, ?, ?, ?, ?, 'Completed', ?, NOW())");
        
        $stmt->bind_param("iiidsssi", $branch_id, $activity_id, $case_id, $w_amount, $w_bank_name, $w_bank_acc, $proof_json, $currentAdminId);
        
        if ($stmt->execute()) {
            header("Location: payment_management.php?success=Withdrawal recorded successfully");
            exit();
        } else {
            $error = "Database Error: " . $stmt->error;
            header("Location: payment_management.php?error=" . urlencode($error));
            exit();
        }
        $stmt->close();
    } elseif (!empty($errors)) {
        // 将错误数组转换为字符串，每行一个
        $errorString = implode("<br>", $errors);
        header("Location: payment_management.php?error=" . urlencode($errorString));
        exit();
    } else {
        header("Location: payment_management.php?error=Invalid details provided.");
        exit();
    }
}

// ==========================================
// 1. HANDLE EXPORT: INCOME
// ==========================================
if (isset($_GET['export']) && $_GET['export'] == 'income') {
    $filename = "report_transaction_history_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr>
            <th>Order ID</th><th>Ref No</th><th>Date & Time</th>
            <th>Donor ID</th><th>Donor Name</th><th>Donor Email</th><th>Donor Contact</th><th>Donor IC</th>
            <th>Target Type</th><th>Target Name</th>
            <th>Amount (RM)</th><th>Payment Method</th><th>Payment Status</th><th>Admin Status</th>
          </tr>';
    
    $sql = "SELECT o.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_ICNumber,
            b.Branch_Name, a.Activity_Name, s.Case_Title
            FROM orders o
            JOIN donor d ON o.Donor_ID = d.Donor_ID
            LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
            LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
            LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
            ORDER BY o.Order_Created_At DESC";
            
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) {
        $targetType = "General";
        $targetName = "-";
        if($row['Branch_Name']) { $targetType = "Branch"; $targetName = $row['Branch_Name']; }
        elseif($row['Activity_Name']) { $targetType = "Activity"; $targetName = $row['Activity_Name']; }
        elseif($row['Case_Title']) { $targetType = "Case"; $targetName = $row['Case_Title']; }
        
        echo "<tr>
            <td>{$row['Order_ID']}</td>
            <td>{$row['Order_TXN_Ref']}</td>
            <td>{$row['Order_Created_At']}</td>
            <td>{$row['Donor_ID']}</td>
            <td>{$row['Donor_Name']}</td>
            <td>{$row['Donor_Email']}</td>
            <td>'{$row['Donor_ContactNumber']}</td>
            <td>'{$row['Donor_ICNumber']}</td>
            <td>{$targetType}</td>
            <td>{$targetName}</td>
            <td>{$row['Order_Amount']}</td>
            <td>{$row['Order_PaymentMethod']}</td>
            <td>{$row['Order_PaymentStatus']}</td>
            <td>{$row['Order_Admin_Status']}</td>
        </tr>";
    }
    echo '</table>';
    exit();
}

// ==========================================
// 2. HANDLE EXPORT: E-WALLET
// ==========================================
if (isset($_GET['export']) && $_GET['export'] == 'ewallet') {
    $filename = "report_ewallet_log_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr>
            <th>Txn ID</th><th>Date & Time</th>
            <th>Donor ID</th><th>Donor Name</th><th>Donor Email</th><th>Donor Contact</th>
            <th>Current Wallet Balance (RM)</th>
            <th>Transaction Type</th><th>Amount (RM)</th>
            <th>Description</th><th>Linked Order Ref</th>
          </tr>';
    
    $sql = "SELECT w.*, d.Donor_Name, d.Donor_Email, d.Donor_ContactNumber, d.Donor_Wallet, o.Order_TXN_Ref
            FROM wallet_transaction w
            JOIN donor d ON w.Donor_ID = d.Donor_ID
            LEFT JOIN orders o ON w.Order_ID = o.Order_ID
            ORDER BY w.Created_At DESC";
            
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) {
        $txnID = $row['Wallet_Trans_ID'];
        echo "<tr>
            <td>{$txnID}</td>
            <td>{$row['Created_At']}</td>
            <td>{$row['Donor_ID']}</td>
            <td>{$row['Donor_Name']}</td>
            <td>{$row['Donor_Email']}</td>
            <td>'{$row['Donor_ContactNumber']}</td>
            <td>{$row['Donor_Wallet']}</td>
            <td>{$row['Transaction_Type']}</td>
            <td>{$row['Amount']}</td>
            <td>{$row['Description']}</td>
            <td>{$row['Order_TXN_Ref']}</td>
        </tr>";
    }
    echo '</table>';
    exit();
}

// --- Fetch Data for Withdrawal Dropdowns & Calculate Balance ---
$branchList = [];
$branches = $conn->query("SELECT Branch_ID, Branch_Name, Branch_BankName, Branch_BankAccount FROM branch WHERE Is_Deleted = 0 ORDER BY Branch_Name ASC");
while($b = $branches->fetch_assoc()) {
    $bid = $b['Branch_ID'];
    $in = $conn->query("SELECT SUM(Order_Amount) as t FROM orders WHERE Branch_ID = $bid AND Order_PaymentStatus IN ('Success','Completed')")->fetch_assoc()['t'] ?? 0;
    $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Branch_ID = $bid AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
    $b['balance'] = $in - $out;
    $branchList[] = $b;
}

$activityList = [];
$activities = $conn->query("SELECT a.Activity_ID, a.Activity_Name, a.Activity_GetAmount, a.Branch_ID, b.Branch_BankName, b.Branch_BankAccount 
                            FROM activity a JOIN branch b ON a.Branch_ID = b.Branch_ID 
                            WHERE a.Activity_Status != 'Cancelled' ORDER BY a.Activity_Name ASC");
while($a = $activities->fetch_assoc()) {
    $aid = $a['Activity_ID'];
    $raised = $a['Activity_GetAmount']; 
    $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Activity_ID = $aid AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
    $a['balance'] = $raised - $out;
    $activityList[] = $a;
}

$caseList = [];
$cases = $conn->query("SELECT Case_ID, Case_Title, Case_BankName, Case_BankAccount, Raised_Amount FROM special_case WHERE Case_Status != 'Cancelled' ORDER BY Case_Title ASC");
while($c = $cases->fetch_assoc()) {
    $cid = $c['Case_ID'];
    $raised = $c['Raised_Amount'];
    $out = $conn->query("SELECT SUM(Amount) as t FROM withdrawals WHERE Case_ID = $cid AND Status != 'Rejected'")->fetch_assoc()['t'] ?? 0;
    $c['balance'] = $raised - $out;
    $caseList[] = $c;
}

$jsonBranches = json_encode($branchList);
$jsonActivities = json_encode($activityList);
$jsonCases = json_encode($caseList);

// --- Stats Functions ---
$totalRevenue = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success')")->fetch_assoc()['total'] ?? 0;
$recurringRevenue = $conn->query("SELECT SUM(Order_Amount) as total FROM orders WHERE Order_PaymentStatus IN ('Completed', 'Success') AND Order_Type = 'Recurring'")->fetch_assoc()['total'] ?? 0;
$totalWalletBalance = $conn->query("SELECT SUM(Donor_Wallet) as total FROM donor")->fetch_assoc()['total'] ?? 0;
$pendingCount = $conn->query("SELECT COUNT(*) as count FROM orders WHERE Order_PaymentStatus = 'Pending'")->fetch_assoc()['count'];

// ==========================================
// LIST 1: TRANSACTION HISTORY
// ==========================================
$limit = 5; 
$page_tx = isset($_GET['page_tx']) && is_numeric($_GET['page_tx']) ? (int)$_GET['page_tx'] : 1;
if ($page_tx < 1) $page_tx = 1;
$offset_tx = ($page_tx - 1) * $limit;

// Input Variables
$search_tx = isset($_GET['search_tx']) ? mysqli_real_escape_string($conn, $_GET['search_tx']) : '';
$filter_type_tx = isset($_GET['filter_type_tx']) ? $_GET['filter_type_tx'] : '';
$filter_date_tx = isset($_GET['filter_date_tx']) ? $_GET['filter_date_tx'] : '';
$filter_method_tx = isset($_GET['filter_method_tx']) ? $_GET['filter_method_tx'] : '';

// Build Query with UNION
// 1. Orders Part (Income)
$q1 = "SELECT 
        o.Order_ID as ID, 
        o.Order_TXN_Ref as Ref, 
        d.Donor_Name as Name, 
        d.Donor_Email as Email, 
        o.Order_Amount as Amount, 
        o.Order_Created_At as Date, 
        o.Order_PaymentMethod as Method, 
        'Income' as Type,
        b.Branch_Name, a.Activity_Name, s.Case_Title
       FROM orders o
       JOIN donor d ON o.Donor_ID = d.Donor_ID
       LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
       LEFT JOIN activity a ON o.Activity_ID = a.Activity_ID
       LEFT JOIN special_case s ON o.Case_ID = s.Case_ID
       WHERE o.Order_PaymentStatus IN ('Success', 'Completed')";

// 2. Withdrawals Part (Expense)
$q2 = "SELECT 
        w.Withdrawal_ID as ID, 
        CONCAT('WDR-', w.Withdrawal_ID) as Ref, 
        'System Admin' as Name, 
        w.Bank_Name as Email, 
        w.Amount as Amount, 
        w.Request_Date as Date, 
        'Bank Transfer' as Method, 
        'Withdrawal' as Type,
        b.Branch_Name, a.Activity_Name, s.Case_Title
       FROM withdrawals w
       LEFT JOIN branch b ON w.Branch_ID = b.Branch_ID
       LEFT JOIN activity a ON w.Activity_ID = a.Activity_ID
       LEFT JOIN special_case s ON w.Case_ID = s.Case_ID
       WHERE w.Status = 'Completed'";

$union_sql = "SELECT * FROM ($q1 UNION ALL $q2) AS Combined_Tx WHERE 1=1";

if (!empty($filter_type_tx)) {
    if ($filter_type_tx == 'date' && !empty($filter_date_tx)) {
        $union_sql .= " AND DATE(Date) = '$filter_date_tx'";
    }
    elseif ($filter_type_tx == 'donor_name' && !empty($search_tx)) {
        $union_sql .= " AND Name LIKE '%$search_tx%'";
    }
    elseif ($filter_type_tx == 'email' && !empty($search_tx)) {
        $union_sql .= " AND Email LIKE '%$search_tx%'";
    }
    elseif ($filter_type_tx == 'target' && !empty($search_tx)) {
        $union_sql .= " AND (Branch_Name LIKE '%$search_tx%' OR Activity_Name LIKE '%$search_tx%' OR Case_Title LIKE '%$search_tx%')";
    }
    elseif ($filter_type_tx == 'amount' && !empty($search_tx)) {
        $union_sql .= " AND Amount = '$search_tx'";
    }
    elseif ($filter_type_tx == 'method' && !empty($filter_method_tx)) {
        $union_sql .= " AND Method = '$filter_method_tx'";
    }
} else {
    if (!empty($search_tx)) {
        $union_sql .= " AND (Ref LIKE '%$search_tx%' OR Name LIKE '%$search_tx%' OR Email LIKE '%$search_tx%' OR Method LIKE '%$search_tx%')";
    }
}

$count_res = $conn->query("SELECT COUNT(*) as total FROM ($union_sql) as Cnt");
$total_tx_recs = $count_res->fetch_assoc()['total'];
$total_pages_tx = ceil($total_tx_recs / $limit);

$union_sql .= " ORDER BY Date DESC LIMIT $offset_tx, $limit";
$recentTransactions = $conn->query($union_sql);

$start_tx = ($total_tx_recs > 0) ? $offset_tx + 1 : 0;
$end_tx = min($offset_tx + $limit, $total_tx_recs);


// ==========================================
// LIST 2: E-WALLET LOG
// ==========================================
$page_wl = isset($_GET['page_wl']) && is_numeric($_GET['page_wl']) ? (int)$_GET['page_wl'] : 1;
if ($page_wl < 1) $page_wl = 1;
$offset_wl = ($page_wl - 1) * $limit; 

$search_wl = isset($_GET['search_wl']) ? mysqli_real_escape_string($conn, $_GET['search_wl']) : '';
$filter_type_wl = isset($_GET['filter_type_wl']) ? $_GET['filter_type_wl'] : '';
$filter_date_wl = isset($_GET['filter_date_wl']) ? $_GET['filter_date_wl'] : '';
$filter_trans_type_wl = isset($_GET['filter_trans_type_wl']) ? $_GET['filter_trans_type_wl'] : '';

$where_wl = "WHERE 1=1";

if (!empty($filter_type_wl)) {
    if ($filter_type_wl == 'donor_name' && !empty($search_wl)) {
        $where_wl .= " AND d.Donor_Name LIKE '%$search_wl%'";
    }
    elseif ($filter_type_wl == 'email' && !empty($search_wl)) {
        $where_wl .= " AND d.Donor_Email LIKE '%$search_wl%'";
    }
    elseif ($filter_type_wl == 'date' && !empty($filter_date_wl)) {
        $where_wl .= " AND DATE(w.Created_At) = '$filter_date_wl'";
    }
    elseif ($filter_type_wl == 'type' && !empty($filter_trans_type_wl)) {
        $where_wl .= " AND w.Transaction_Type = '$filter_trans_type_wl'";
    }
    elseif ($filter_type_wl == 'amount' && !empty($search_wl)) {
        $where_wl .= " AND w.Amount = '$search_wl'";
    }
} else {
    if (!empty($search_wl)) {
        $where_wl .= " AND (d.Donor_Name LIKE '%$search_wl%' OR d.Donor_Email LIKE '%$search_wl%' OR o.Order_TXN_Ref LIKE '%$search_wl%')";
    }
}

$sql_wl_count = "SELECT COUNT(*) as total 
                 FROM wallet_transaction w
                 JOIN donor d ON w.Donor_ID = d.Donor_ID
                 LEFT JOIN orders o ON w.Order_ID = o.Order_ID
                 $where_wl";
$total_wl_recs = $conn->query($sql_wl_count)->fetch_assoc()['total'];
$total_pages_wl = ceil($total_wl_recs / $limit);

$sql_wl = "SELECT w.*, d.Donor_Name, d.Donor_Email, d.Donor_ProfilePicture, o.Order_TXN_Ref
           FROM wallet_transaction w
           JOIN donor d ON w.Donor_ID = d.Donor_ID
           LEFT JOIN orders o ON w.Order_ID = o.Order_ID
           $where_wl
           ORDER BY w.Created_At DESC 
           LIMIT $offset_wl, $limit";
$walletTransactions = $conn->query($sql_wl);

$start_wl = ($total_wl_recs > 0) ? $offset_wl + 1 : 0;
$end_wl = min($offset_wl + $limit, $total_wl_recs);

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
        .floating-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1100; display: flex; align-items: flex-start; gap: 10px; max-width: 400px; animation: slideIn 0.3s; }
        .floating-alert i { margin-top: 3px; }
        .floating-alert-success { background: white; color: var(--success); border-left: 4px solid var(--success); }
        .floating-alert-danger { background: white; color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Buttons */
        .btn-export { background-color: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 5px; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 5px; transition: background 0.3s; text-decoration: none; }
        .btn-export:hover { background-color: #218838; color: white; }
        .btn-withdraw { background-color: #fd7e14; color: white; border: none; padding: 8px 15px; border-radius: 5px; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 5px; transition: background 0.3s; text-decoration: none; margin-right: 8px; }
        .btn-withdraw:hover { background-color: #e6700c; color: white; }

        /* Search & Filters */
        .admin-search-container { display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px 15px; border-radius: 8px; border: 1px solid #eee; flex-wrap: wrap; width: 100%; margin-top: 15px; box-sizing: border-box; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; outline: none; background-color: white; min-width: 150px; cursor: pointer; font-size: 13px; }
        .search-input { flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; outline: none; background: white; min-width: 200px; font-size: 13px; }
        .search-input:focus, .filter-select:focus { border-color: var(--primary); }
        .btn-search { background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 13px; display:flex; align-items:center; gap:5px; }
        .btn-clear { background: var(--danger); color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; text-decoration: none; display: flex; align-items: center; font-size: 13px; }
        .secondary-filter { display: none; animation: fadeIn 0.3s; flex: 1; }
        .secondary-filter.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* Stats Cards */
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #6c757d; margin-bottom: 5px; }
        .stat-info h2 { font-size: 24px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; margin: 0; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .text-success { color: var(--success); } .text-info { color: var(--info); } .text-warning { color: var(--warning); } .text-danger { color: var(--danger); }
        .stat-icon { width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        /* Layout */
        .payment-content-grid { display: flex; flex-direction: column; gap: 30px; margin-bottom: 30px; width: 100%; }
        .content-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); border: 1px solid #f0f0f0; display: flex; flex-direction: column; width: 100%; box-sizing: border-box; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0px; }
        .section-header h2 { font-size: 18px; font-weight: 700; color: #333; margin: 0; }
        .header-actions { display: flex; align-items: center; }

        /* Tables */
        .table-container { flex: 1; overflow-x: auto; margin-top: 20px; }
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .custom-table thead th { color: #8898aa; font-weight: 600; text-transform: uppercase; font-size: 13px; padding: 0 15px 10px 15px; text-align: left; white-space: nowrap; }
        .custom-table tbody tr { background: white; transition: transform 0.2s; }
        .custom-table tbody tr:hover { background-color: #fcfcfc; }
        .custom-table td { padding: 15px; vertical-align: top; color: #525f7f; font-size: 14px; border-top: 1px solid #f5f5f5; border-bottom: 1px solid #f5f5f5; }
        .custom-table td:first-child { border-left: 1px solid #f5f5f5; border-radius: 8px 0 0 8px; }
        .custom-table td:last-child { border-right: 1px solid #f5f5f5; border-radius: 0 8px 8px 0; }

        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .badge-credit { background: #e6f4ea; color: #1e7e34; }
        .badge-debit { background: #fce8e6; color: #c5221f; }
        .btn-action { display: inline-flex; align-items: center; justify-content: center; width: 35px; height: 35px; background-color: #e3f2fd; color: #1976d2; border-radius: 50%; text-decoration: none; transition: all 0.2s; border: 1px solid #bbdefb; }
        .btn-action:hover { background-color: #1976d2; color: white; transform: translateY(-2px); }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: auto; }
        .pagination-info { font-size: 13px; color: #8898aa; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-btn { padding: 6px 12px; border: 1px solid #eee; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px; font-size: 13px; }
        .pagination-btn:hover { background-color: #e9ecef; }
        .pagination-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); cursor: default; }
        .pagination-btn.disabled { color: #ccc; cursor: not-allowed; pointer-events: none; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); animation: fadeIn 0.3s; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 0; border: 1px solid #888; width: 50%; max-width: 600px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: slideInTop 0.4s; }
        @keyframes slideInTop { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { padding: 15px 20px; background-color: #f8f9fa; border-bottom: 1px solid #eee; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 18px; color: #333; }
        .close-modal { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-modal:hover { color: #333; }
        .modal-body { padding: 20px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: var(--primary); }
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        
        .modal-footer { padding: 15px 20px; background-color: #f8f9fa; border-top: 1px solid #eee; border-radius: 0 0 10px 10px; text-align: right; }
        .btn-cancel { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px; }
        .btn-submit { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .btn-submit:hover { background: #d66; }

        .balance-display { font-size: 13px; color: var(--success); margin-top: 5px; font-weight: 600; display: none; }
        .balance-error { color: var(--danger); }

        /* Upload & Preview (Design from Branch Management) */
        .upload-container { width: 100%; }
        .upload-box { border: 2px dashed #ccc; padding: 20px; text-align: center; cursor: pointer; border-radius: 8px; position: relative; background: #fafafa; }
        .upload-box:hover { background: #fff5f5; border-color: var(--primary); }
        .upload-box input { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
        .upload-box i { font-size: 32px; color: #aaa; margin-bottom: 10px; display: block; }
        .upload-box p { margin: 0; font-size: 13px; color: #666; font-weight: 500; }
        
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 12px; margin-top: 15px; }
        .preview-item { position: relative; height: 80px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: 1px solid #eee; background:white; display: flex; align-items: center; justify-content: center; }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .preview-item i { font-size: 30px; color: #dc3545; } /* For PDF */
        .remove-img-btn { position: absolute; top: 4px; right: 4px; background: #ff4d4d; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2); z-index: 10; }
        .remove-img-btn:hover { background: #cc0000; transform: scale(1.1); }
    </style>
</head>
<body>

    <div class="floating-alert floating-alert-success" id="floatingSuccess" style="display: <?php echo isset($_GET['success']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-check-circle"></i>
        <div id="floatingSuccessText"><?php echo isset($_GET['success']) ? htmlspecialchars($_GET['success']) : ''; ?></div>
    </div>

    <div class="floating-alert floating-alert-danger" id="floatingError" style="display: <?php echo isset($_GET['error']) ? 'flex' : 'none'; ?>">
        <i class="fas fa-exclamation-circle"></i>
        <div id="floatingErrorText"><?php echo isset($_GET['error']) ? $_GET['error'] : ''; ?></div>
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
                    <div class="stat-info"><h3>TOTAL REVENUE</h3><h2>RM <?php echo number_format($totalRevenue, 2); ?></h2><p class="text-success"><i class="fas fa-arrow-up"></i> Lifetime</p></div>
                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.2); color: #28a745;"><i class="fas fa-wallet"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>RECURRING</h3><h2>RM <?php echo number_format($recurringRevenue, 2); ?></h2><p class="text-info">Recurring</p></div>
                    <div class="stat-icon" style="background: rgba(23, 162, 184, 0.2); color: #17a2b8;"><i class="fas fa-sync"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>SYSTEM WALLET FUNDS</h3><h2>RM <?php echo number_format($totalWalletBalance, 2); ?></h2><p class="text-warning">User Holdings</p></div>
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.2); color: #ffc107;"><i class="fas fa-coins"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>PENDING</h3><h2><?php echo $pendingCount; ?></h2><p class="text-danger">Needs Action</p></div>
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.2); color: #dc3545;"><i class="fas fa-exclamation"></i></div>
                </div>
            </div>

            <div class="payment-content-grid">
                
                <div class="content-card">
                    <div class="section-header">
                        <h2>Transaction History</h2>
                        <div class="header-actions">
                            <button onclick="openWithdrawModal()" class="btn-withdraw"><i class="fas fa-money-bill-wave"></i> Withdraw</button>
                            <a href="?export=income" class="btn-export"><i class="fas fa-download"></i> Export</a>
                        </div>
                    </div>
                    
                    <form method="GET" class="admin-search-container">
                        <?php if($page_wl > 1) echo "<input type='hidden' name='page_wl' value='$page_wl'>"; ?>
                        
                        <div class="filter-group">
                            <i class="fas fa-filter" style="color:#666;"></i>
                            <select name="filter_type_tx" id="filterTypeTx" class="filter-select" onchange="toggleTxFilters()">
                                <option value="">Filter By...</option>
                                <option value="date" <?php echo ($filter_type_tx == 'date') ? 'selected' : ''; ?>>Date</option>
                                <option value="donor_name" <?php echo ($filter_type_tx == 'donor_name') ? 'selected' : ''; ?>>Donor Name</option>
                                <option value="email" <?php echo ($filter_type_tx == 'email') ? 'selected' : ''; ?>>Email</option>
                                <option value="target" <?php echo ($filter_type_tx == 'target') ? 'selected' : ''; ?>>Target (Branch/Act)</option>
                                <option value="amount" <?php echo ($filter_type_tx == 'amount') ? 'selected' : ''; ?>>Amount</option>
                                <option value="method" <?php echo ($filter_type_tx == 'method') ? 'selected' : ''; ?>>Method</option>
                            </select>
                        </div>

                        <div id="filter_date_tx" class="secondary-filter">
                            <input type="date" name="filter_date_tx" class="search-input" value="<?php echo htmlspecialchars($filter_date_tx); ?>">
                        </div>

                        <div id="filter_method_tx" class="secondary-filter">
                            <select name="filter_method_tx" class="search-input">
                                <option value="">Select Method</option>
                                <option value="Card" <?php echo ($filter_method_tx == 'Card') ? 'selected' : ''; ?>>Card</option>
                                <option value="FPX" <?php echo ($filter_method_tx == 'FPX') ? 'selected' : ''; ?>>FPX</option>
                                <option value="E-Wallet" <?php echo ($filter_method_tx == 'E-Wallet') ? 'selected' : ''; ?>>E-Wallet</option>
                            </select>
                        </div>
                        
                        <div id="filter_text_tx" class="secondary-filter active">
                            <input type="text" name="search_tx" class="search-input" placeholder="Search keyword..." value="<?php echo htmlspecialchars($search_tx); ?>">
                        </div>

                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                        
                        <?php if(!empty($search_tx) || !empty($filter_type_tx)): ?>
                            <a href="payment_management.php" class="btn-clear"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>

                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Date / Ref No</th>
                                    <th>Donor / Entity</th>
                                    <th>Target</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($recentTransactions->num_rows > 0): ?>
                                    <?php while($txn = $recentTransactions->fetch_assoc()): 
                                        $targetName = "General Fund";
                                        if ($txn['Case_Title']) $targetName = "Case: " . $txn['Case_Title'];
                                        elseif ($txn['Activity_Name']) $targetName = "Act: " . $txn['Activity_Name'];
                                        elseif ($txn['Branch_Name']) $targetName = "Branch: " . $txn['Branch_Name'];
                                        
                                        $dateTimeObj = new DateTime($txn['Date']);
                                        $dateStr = $dateTimeObj->format('M d, Y');
                                        $timeStr = $dateTimeObj->format('h:i A');

                                        $isWithdrawal = ($txn['Type'] == 'Withdrawal');
                                        $amountColor = $isWithdrawal ? '#dc3545' : '#28a745';
                                        $amountPrefix = $isWithdrawal ? '-' : '';
                                        $detailsLink = $isWithdrawal ? "admin_withdrawal_details.php?id=" . $txn['ID'] : "admin_payment_details.php?id=" . $txn['ID'];
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600; color:#333; font-size:14px;"><?php echo $txn['Ref']; ?></div>
                                            <div style="font-size:12px; color:#888; margin-top:3px;"><?php echo $dateStr . ' ' . $timeStr; ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($txn['Name']); ?></div>
                                            <div style="font-size:12px; color:#888; margin-top:3px;"><?php echo htmlspecialchars($txn['Email']); ?></div>
                                        </td>
                                        <td><span style="font-size:13px; display:block; max-width:150px; white-space:normal; line-height:1.4;"><?php echo htmlspecialchars($targetName); ?></span></td>
                                        <td style="font-weight:700; color:<?php echo $amountColor; ?>; font-size:14px;">
                                            <?php echo $amountPrefix; ?> RM <?php echo number_format($txn['Amount'], 2); ?>
                                        </td>
                                        <td><span style="font-size:13px; color:#555;"><?php echo $txn['Method']; ?></span></td>
                                        <td>
                                            <a href="<?php echo $detailsLink; ?>" class="btn-action" target="_blank" title="View Details">
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
                        <div class="pagination-info">Showing <?php echo $start_tx; ?> - <?php echo $end_tx; ?> of <?php echo $total_tx_recs; ?></div>
                        <div class="pagination-controls">
                            <?php $pgParams = $_GET; ?>
                            <?php if ($page_tx > 1): $pgParams['page_tx'] = $page_tx - 1; ?>
                                <a href="?<?php echo http_build_query($pgParams); ?>" class="pagination-btn">Previous</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Previous</span>
                            <?php endif; ?>

                            <?php 
                            $tx_range_start = max(1, $page_tx - 1);
                            $tx_range_end = min($total_pages_tx, $page_tx + 1);
                            
                            if($total_pages_tx >= 3) {
                                if($page_tx == 1) { $tx_range_end = 3; } 
                                elseif($page_tx == $total_pages_tx) { $tx_range_start = $total_pages_tx - 2; }
                            } else {
                                $tx_range_start = 1; $tx_range_end = $total_pages_tx;
                            }

                            for($i = $tx_range_start; $i <= $tx_range_end; $i++): 
                                $pgParams['page_tx'] = $i; 
                            ?>
                                <a href="?<?php echo http_build_query($pgParams); ?>" class="pagination-btn <?php echo ($i==$page_tx)?'active':''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page_tx < $total_pages_tx): $pgParams['page_tx'] = $page_tx + 1; ?>
                                <a href="?<?php echo http_build_query($pgParams); ?>" class="pagination-btn">Next</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="section-header">
                        <h2><i class="fas fa-wallet" style="color: #F28585; margin-right:8px;"></i> E-Wallet Log</h2>
                        <a href="?export=ewallet" class="btn-export"><i class="fas fa-download"></i> Export</a>
                    </div>
                    
                    <form method="GET" class="admin-search-container">
                        <?php if($page_tx > 1) echo "<input type='hidden' name='page_tx' value='$page_tx'>"; ?>
                        
                        <div class="filter-group">
                            <i class="fas fa-filter" style="color:#666;"></i>
                            <select name="filter_type_wl" id="filterTypeWl" class="filter-select" onchange="toggleWlFilters()">
                                <option value="">Filter By...</option>
                                <option value="donor_name" <?php echo ($filter_type_wl == 'donor_name') ? 'selected' : ''; ?>>Donor Name</option>
                                <option value="email" <?php echo ($filter_type_wl == 'email') ? 'selected' : ''; ?>>Email</option>
                                <option value="date" <?php echo ($filter_type_wl == 'date') ? 'selected' : ''; ?>>Date</option>
                                <option value="type" <?php echo ($filter_type_wl == 'type') ? 'selected' : ''; ?>>Type</option>
                                <option value="amount" <?php echo ($filter_type_wl == 'amount') ? 'selected' : ''; ?>>Amount</option>
                            </select>
                        </div>

                        <div id="filter_date_wl" class="secondary-filter">
                            <input type="date" name="filter_date_wl" class="search-input" value="<?php echo htmlspecialchars($filter_date_wl); ?>">
                        </div>

                        <div id="filter_type_wl_select" class="secondary-filter">
                            <select name="filter_trans_type_wl" class="search-input">
                                <option value="">Select Type</option>
                                <option value="Credit" <?php echo ($filter_trans_type_wl == 'Credit') ? 'selected' : ''; ?>>Credit (+)</option>
                                <option value="Debit" <?php echo ($filter_trans_type_wl == 'Debit') ? 'selected' : ''; ?>>Debit (-)</option>
                            </select>
                        </div>

                        <div id="filter_text_wl" class="secondary-filter active">
                            <input type="text" name="search_wl" class="search-input" placeholder="Search keyword..." value="<?php echo htmlspecialchars($search_wl); ?>">
                        </div>

                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>

                        <?php if(!empty($search_wl) || !empty($filter_type_wl)): ?>
                            <a href="payment_management.php" class="btn-clear"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                    
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Donor</th>
                                    <th>Transaction Details</th> 
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($walletTransactions && $walletTransactions->num_rows > 0): ?>
                                    <?php while($wt = $walletTransactions->fetch_assoc()): 
                                         $wtTime = new DateTime($wt['Created_At']);
                                         $wtDateStr = $wtTime->format('M d, Y');
                                         $wtTimeStr = $wtTime->format('h:i A');
                                         $isCredit = ($wt['Transaction_Type'] == 'Credit');
                                         $txnID = $wt['Wallet_Trans_ID'];
                                         $desc = !empty($wt['Description']) ? $wt['Description'] : '-';
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; margin-bottom:5px;">
                                                <?php if($wt['Donor_ProfilePicture']): ?>
                                                    <img src="<?php echo $wt['Donor_ProfilePicture']; ?>" style="width:35px; height:35px; border-radius:50%; margin-right:10px; object-fit:cover;">
                                                <?php else: ?>
                                                    <div style="width:35px; height:35px; border-radius:50%; background:#eee; margin-right:10px; font-size:14px; display:flex; align-items:center; justify-content:center; color:#888;"><i class="fas fa-user"></i></div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight:600; font-size:14px; color:#333;"><?php echo htmlspecialchars($wt['Donor_Name']); ?></div>
                                                    <div style="font-size:12px; color:#777; margin-top:2px;"><?php echo htmlspecialchars($wt['Donor_Email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td>
                                            <div style="font-weight:500; font-size:13px; color:#333; margin-bottom:3px;">
                                                <?php echo htmlspecialchars($desc); ?>
                                            </div>
                                            <div style="font-size:12px; color:#888;">
                                                <i class="far fa-clock" style="font-size:11px; margin-right:4px;"></i> <?php echo $wtDateStr . ' ' . $wtTimeStr; ?>
                                                <span style="color:#ddd; margin:0 8px;">|</span>
                                                Ref: #<?php echo $txnID; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="badge <?php echo $isCredit ? 'badge-credit' : 'badge-debit'; ?>">
                                                <?php echo $isCredit ? 'Credit' : 'Debit'; ?>
                                            </span>
                                        </td>

                                        <td style="font-weight:700; color: <?php echo $isCredit ? '#28a745' : '#dc3545'; ?>; font-size:14px;">
                                            <?php echo $isCredit ? '+' : '-'; ?> RM <?php echo number_format($wt['Amount'], 2); ?>
                                        </td>

                                        <td>
                                            <a href="admin_ewallet_details.php?id=<?php echo $txnID; ?>" class="btn-action" target="_blank" title="View Wallet Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">No wallet activity found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-container">
                        <div class="pagination-info">Showing <?php echo $start_wl; ?> - <?php echo $end_wl; ?> of <?php echo $total_wl_recs; ?></div>
                        <div class="pagination-controls">
                            <?php $wlParams = $_GET; ?>
                            <?php if ($page_wl > 1): $wlParams['page_wl'] = $page_wl - 1; ?>
                                <a href="?<?php echo http_build_query($wlParams); ?>" class="pagination-btn">Previous</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Previous</span>
                            <?php endif; ?>

                            <?php 
                            $wl_range_start = max(1, $page_wl - 1);
                            $wl_range_end = min($total_pages_wl, $page_wl + 1);
                            
                            if($total_pages_wl >= 3) {
                                if($page_wl == 1) { $wl_range_end = 3; } 
                                elseif($page_wl == $total_pages_wl) { $wl_range_start = $total_pages_wl - 2; }
                            } else {
                                $wl_range_start = 1; $wl_range_end = $total_pages_wl;
                            }

                            for($i = $wl_range_start; $i <= $wl_range_end; $i++): 
                                $wlParams['page_wl'] = $i; 
                            ?>
                                <a href="?<?php echo http_build_query($wlParams); ?>" class="pagination-btn <?php echo ($i==$page_wl)?'active':''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page_wl < $total_pages_wl): $wlParams['page_wl'] = $page_wl + 1; ?>
                                <a href="?<?php echo http_build_query($wlParams); ?>" class="pagination-btn">Next</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card chart-section">
                <div class="section-header"><h2>Revenue Analytics</h2></div>
                <div style="height: 350px;"><canvas id="revenueChart"></canvas></div>
            </div>

        </div>
    </div>

    <div id="withdrawModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Withdrawal Request</h3>
                <span class="close-modal" onclick="closeWithdrawModal()">&times;</span>
            </div>
            <form method="POST" action="payment_management.php" enctype="multipart/form-data" onsubmit="return validateWithdrawal()">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="withdrawal_type">Withdrawal Source Type <span style="color:red">*</span></label>
                        <select name="withdrawal_type" id="withdrawal_type" class="form-control" onchange="toggleWithdrawTarget()" required>
                            <option value="">-- Select Source --</option>
                            <option value="branch">Branch Fund</option>
                            <option value="activity">Activity Fund</option>
                            <option value="case">Special Case Fund</option>
                        </select>
                        <small class="form-guide">Select the funding source (Branch, Activity, or Special Case) for this withdrawal.</small>
                    </div>

                    <div class="form-group" id="group_branch" style="display:none;">
                        <label for="target_id_branch">Select Branch <span style="color:red">*</span></label>
                        <select name="target_id_branch" id="target_id_branch" class="form-control" onchange="updateBankAndBalance('branch')">
                            <option value="">-- Select Branch --</option>
                        </select>
                        <div id="balance_branch" class="balance-display"></div>
                        <small class="form-guide">Choose the specific branch entity from which funds will be deducted.</small>
                    </div>

                    <div class="form-group" id="group_activity" style="display:none;">
                        <label for="target_id_activity">Select Activity <span style="color:red">*</span></label>
                        <select name="target_id_activity" id="target_id_activity" class="form-control" onchange="updateBankAndBalance('activity')">
                            <option value="">-- Select Activity --</option>
                        </select>
                         <div id="balance_activity" class="balance-display"></div>
                         <small class="form-guide">Choose the specific activity fund from which funds will be deducted.</small>
                    </div>

                    <div class="form-group" id="group_case" style="display:none;">
                        <label for="target_id_case">Select Special Case <span style="color:red">*</span></label>
                        <select name="target_id_case" id="target_id_case" class="form-control" onchange="updateBankAndBalance('case')">
                            <option value="">-- Select Case --</option>
                        </select>
                        <div id="balance_case" class="balance-display"></div>
                        <small class="form-guide">Choose the specific special case fund from which funds will be deducted.</small>
                    </div>

                    <div class="form-group" id="group_handling_branch" style="display:none;">
                        <label for="handling_branch_id">Processing Branch <span style="color:red">*</span></label>
                        <select name="handling_branch_id" class="form-control">
                            <option value="">-- Select Processing Branch --</option>
                        </select>
                        <small class="form-guide">Select the branch responsible for processing this special case withdrawal.</small>
                    </div>

                    <div class="form-group">
                        <label>Amount (RM) <span style="color:red">*</span></label>
                        <input type="number" step="1" min="0" name="amount" id="withdraw_amount" class="form-control" placeholder="e.g. 500.00" required>
                        <small class="form-guide">Specify the total amount to withdraw (e.g. 1000). Arrows increase by RM 1.</small>
                    </div>

                    <div class="form-group">
                        <label>Bank Name <span style="color:red">*</span></label>
                        <input type="text" name="bank_name" id="bank_name" class="form-control" placeholder="The bank name associated with the selected source (Auto-filled)." readonly required>
                        <small class="form-guide">The bank associated with the selected source.</small>
                    </div>

                    <div class="form-group">
                        <label>Account Number <span style="color:red">*</span></label>
                        <input type="text" name="bank_account" id="bank_account" class="form-control" placeholder="The account number associated with the selected source (Auto-filled)." readonly required>
                        <small class="form-guide">The bank account number for the transfer.</small>
                    </div>

                    <div class="form-group">
                        <label>Reference / Proof (Images/PDF) <span style="color:red">*</span></label>
                        <div class="upload-container">
                            <div class="upload-box">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click or Drag Proof files (Max 5, PDF/Images)</p>
                                <input type="file" id="proof_file" name="proof_file[]" multiple accept=".jpg,.jpeg,.png,.pdf" onchange="handleFileSelect(event)" required>
                            </div>
                            <div class="preview-grid" id="proof_preview_container"></div>
                        </div>
                        <small class="form-guide">Upload official receipts, bank transfer slips, or invoices as proof of withdrawal.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeWithdrawModal()">Cancel</button>
                    <button type="submit" name="submit_withdrawal" class="btn-submit">Submit Withdrawal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Chart JS (Unchanged)
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

        // Data Injection
        const branchesData = <?php echo $jsonBranches; ?>;
        const activitiesData = <?php echo $jsonActivities; ?>;
        const casesData = <?php echo $jsonCases; ?>;
        let currentMaxBalance = 0;

        // Populate Select Helper
        function populateSelect(id, data, valKey, textKey) {
            const sel = document.getElementById(id);
            sel.innerHTML = '<option value="">-- Select --</option>';
            data.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item[valKey];
                opt.text = item[textKey];
                sel.add(opt);
            });
        }

        populateSelect('target_id_branch', branchesData, 'Branch_ID', 'Branch_Name');
        populateSelect('target_id_activity', activitiesData, 'Activity_ID', 'Activity_Name');
        populateSelect('target_id_case', casesData, 'Case_ID', 'Case_Title');
        
        const handleSel = document.querySelector('[name="handling_branch_id"]');
        handleSel.innerHTML = '<option value="">-- Select Processing Branch --</option>';
        branchesData.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.Branch_ID;
            opt.text = b.Branch_Name;
            handleSel.add(opt);
        });

        // Filter Toggles (Unchanged logic)
        function toggleTxFilters() {
            const type = document.getElementById('filterTypeTx').value;
            document.querySelectorAll('#filter_date_tx, #filter_method_tx, #filter_text_tx').forEach(el => el.classList.remove('active'));
            if (type === 'date') document.getElementById('filter_date_tx').classList.add('active');
            else if (type === 'method') document.getElementById('filter_method_tx').classList.add('active');
            else document.getElementById('filter_text_tx').classList.add('active');
        }
        toggleTxFilters();

        function toggleWlFilters() {
            const type = document.getElementById('filterTypeWl').value;
            document.querySelectorAll('#filter_date_wl, #filter_type_wl_select, #filter_text_wl').forEach(el => el.classList.remove('active'));
            if (type === 'date') document.getElementById('filter_date_wl').classList.add('active');
            else if (type === 'type') document.getElementById('filter_type_wl_select').classList.add('active');
            else document.getElementById('filter_text_wl').classList.add('active');
        }
        toggleWlFilters();

        // --- System Message Function (Requirement 1) ---
        function showSystemError(messageHTML) {
            const errorBox = document.getElementById('floatingError');
            const errorText = document.getElementById('floatingErrorText');
            if(errorBox && errorText) {
                errorText.innerHTML = messageHTML;
                errorBox.style.display = 'flex';
                setTimeout(() => { errorBox.style.display = 'none'; }, 5000);
            }
        }

        // --- FILE UPLOAD LOGIC (Requirement 2 & 3) ---
        let selectedFiles = [];

        function handleFileSelect(event) {
            const input = event.target;
            const newFiles = Array.from(input.files);
            
            // Limit to 5 files total
            if (selectedFiles.length + newFiles.length > 5) {
                showSystemError("You can only upload a maximum of 5 proof files.");
                // Reset input to prevent processing
                input.value = ''; 
                return;
            }

            selectedFiles = selectedFiles.concat(newFiles);
            updateFileInput();
            renderPreview();
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileInput();
            renderPreview();
        }

        function updateFileInput() {
            const input = document.getElementById('proof_file');
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;
        }

        function renderPreview() {
            const container = document.getElementById('proof_preview_container');
            container.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'preview-item';
                
                // Check if PDF or Image
                if (file.type === 'application/pdf') {
                    item.innerHTML = `
                        <i class="fas fa-file-pdf" style="font-size: 24px; color: #dc3545;"></i>
                        <button type="button" class="remove-img-btn" onclick="removeFile(${index})"><i class="fas fa-times"></i></button>
                    `;
                    container.appendChild(item);
                } else {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        item.innerHTML = `<img src="${e.target.result}"><button type="button" class="remove-img-btn" onclick="removeFile(${index})"><i class="fas fa-times"></i></button>`;
                        container.appendChild(item);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // --- Withdrawal Modal Logic ---
        function openWithdrawModal() {
            // Reset upload
            selectedFiles = [];
            document.getElementById('proof_preview_container').innerHTML = '';
            document.getElementById('withdrawModal').style.display = 'block';
        }

        function closeWithdrawModal() {
            document.getElementById('withdrawModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('withdrawModal');
            if (event.target == modal) { modal.style.display = "none"; }
        }

        function toggleWithdrawTarget() {
            var type = document.getElementById('withdrawal_type').value;
            
            document.getElementById('group_branch').style.display = 'none';
            document.getElementById('group_activity').style.display = 'none';
            document.getElementById('group_case').style.display = 'none';
            document.getElementById('group_handling_branch').style.display = 'none';
            document.querySelectorAll('.balance-display').forEach(e => e.style.display = 'none');

            document.getElementById('bank_name').value = '';
            document.getElementById('bank_account').value = '';
            document.getElementById('withdraw_amount').value = '';
            currentMaxBalance = 0;

            document.querySelector('[name="target_id_branch"]').required = false;
            document.querySelector('[name="target_id_activity"]').required = false;
            document.querySelector('[name="target_id_case"]').required = false;
            document.querySelector('[name="handling_branch_id"]').required = false;

            if (type === 'branch') {
                document.getElementById('group_branch').style.display = 'block';
                document.querySelector('[name="target_id_branch"]').required = true;
            } else if (type === 'activity') {
                document.getElementById('group_activity').style.display = 'block';
                document.querySelector('[name="target_id_activity"]').required = true;
            } else if (type === 'case') {
                document.getElementById('group_case').style.display = 'block';
                document.getElementById('group_handling_branch').style.display = 'block';
                document.querySelector('[name="target_id_case"]').required = true;
                document.querySelector('[name="handling_branch_id"]').required = true;
            }
        }

        function updateBankAndBalance(type) {
            let id, data, balId;
            let bankKey, accKey;

            if (type === 'branch') {
                id = document.getElementById('target_id_branch').value;
                data = branchesData.find(b => b.Branch_ID == id);
                bankKey = 'Branch_BankName'; accKey = 'Branch_BankAccount';
                balId = 'balance_branch';
            } else if (type === 'activity') {
                id = document.getElementById('target_id_activity').value;
                data = activitiesData.find(a => a.Activity_ID == id);
                bankKey = 'Branch_BankName'; accKey = 'Branch_BankAccount'; 
                balId = 'balance_activity';
            } else if (type === 'case') {
                id = document.getElementById('target_id_case').value;
                data = casesData.find(c => c.Case_ID == id);
                bankKey = 'Case_BankName'; accKey = 'Case_BankAccount';
                balId = 'balance_case';
            }

            const balEl = document.getElementById(balId);
            if (data) {
                document.getElementById('bank_name').value = data[bankKey] || 'N/A';
                document.getElementById('bank_account').value = data[accKey] || 'N/A';
                
                currentMaxBalance = parseFloat(data.balance);
                balEl.innerHTML = "Available Balance: RM " + currentMaxBalance.toFixed(2);
                balEl.style.display = 'block';
                
                if (currentMaxBalance <= 0) {
                    balEl.className = 'balance-display balance-error';
                    document.getElementById('withdraw_amount').disabled = true;
                    document.getElementById('withdraw_amount').placeholder = "Insufficient Funds";
                } else {
                    balEl.className = 'balance-display';
                    document.getElementById('withdraw_amount').disabled = false;
                    document.getElementById('withdraw_amount').placeholder = "e.g. 500.00";
                    document.getElementById('withdraw_amount').max = currentMaxBalance;
                }
            } else {
                document.getElementById('bank_name').value = '';
                document.getElementById('bank_account').value = '';
                balEl.style.display = 'none';
                currentMaxBalance = 0;
            }
        }

        function validateWithdrawal() {
            const amt = parseFloat(document.getElementById('withdraw_amount').value);
            const errors = [];
            
            if (isNaN(amt) || amt <= 0) {
                errors.push("Amount must be greater than 0.");
            }
            if (amt > currentMaxBalance) {
                errors.push("Amount cannot exceed available funds (RM " + currentMaxBalance.toFixed(2) + ").");
            }
            if (selectedFiles.length === 0) {
                errors.push("Please upload at least one proof file.");
            }

            if (errors.length > 0) {
                // Use System Error instead of Alert (Requirement 1)
                showSystemError(errors.join('<br>'));
                return false;
            }
            return true;
        }

        setTimeout(() => {
            const success = document.getElementById('floatingSuccess');
            const error = document.getElementById('floatingError');
            if(success) success.style.display = 'none';
            if(error) error.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>