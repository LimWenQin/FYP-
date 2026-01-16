<?php
session_start();
include 'dataconnection.php';

// ==========================================
// 0. 权限检查
// ==========================================
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}
$current_donor_id = $_SESSION['donor_id']; 

// ==========================================
// 1. 处理用户提交的操作 (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- A. 处理“标记已读” ---
    if (isset($_POST['mark_read_all'])) {
        $stmt = $conn->prepare("UPDATE notifications SET Is_Read = 1 WHERE Donor_ID = ?");
        $stmt->bind_param("i", $current_donor_id);
        $stmt->execute();
        exit; 
    }

    // --- B. 处理捐款计划的操作 ---
    if (isset($_POST['recurring_id'])) {
        $recurring_id = $_POST['recurring_id'];
        
        if (isset($_POST['action_type'])) {
            $action = $_POST['action_type'];
            $new_status = '';
            $msg = "";
            
            if ($action === 'pause') { $new_status = 'Paused'; $msg = "Plan paused successfully."; }
            elseif ($action === 'resume') { $new_status = 'Active'; $msg = "Plan resumed successfully."; }
            elseif ($action === 'cancel') { $new_status = 'Cancelled'; $msg = "Plan cancelled successfully."; }

            if ($new_status) {
                $stmt = $conn->prepare("UPDATE recurring_donation SET Recurring_Status = ?, Recurring_Updated_At = NOW() WHERE Recurring_ID = ? AND Donor_ID = ?");
                $stmt->bind_param("sii", $new_status, $recurring_id, $current_donor_id);
                if($stmt->execute()) {
                    $_SESSION['swal_success'] = $msg;
                }
            }
        }

        if (isset($_POST['update_date']) && isset($_POST['new_date'])) {
            $new_date = $_POST['new_date'];
            if (strtotime($new_date) > time()) {
                $stmt = $conn->prepare("UPDATE recurring_donation SET Recurring_Deduction_Date = ?, Recurring_Updated_At = NOW() WHERE Recurring_ID = ? AND Donor_ID = ?");
                $stmt->bind_param("sii", $new_date, $recurring_id, $current_donor_id);
                if($stmt->execute()) {
                    $_SESSION['swal_success'] = "Deduction date updated successfully.";
                }
            } else {
                $_SESSION['swal_error'] = "Please select a future date.";
            }
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ==========================================
// 2. 读取数据 (分类读取)
// ==========================================
$query = "SELECT r.*, sc.Case_Title, b.Branch_Name, a.Activity_Name
          FROM recurring_donation r
          LEFT JOIN special_case sc ON r.Case_ID = sc.Case_ID
          LEFT JOIN branch b ON r.Branch_ID = b.Branch_ID
          LEFT JOIN activity a ON r.Activity_ID = a.Activity_ID
          WHERE r.Donor_ID = ? 
          ORDER BY r.Recurring_Updated_At DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$plans_result = $stmt->get_result();

$active_plans = [];
$history_plans = [];

while($row = $plans_result->fetch_assoc()) {
    if ($row['Recurring_Status'] == 'Cancelled') {
        $history_plans[] = $row;
    } else {
        $active_plans[] = $row;
    }
}

// 读取通知
$notif_query = "SELECT * FROM notifications WHERE Donor_ID = ? AND Is_Read = 0 ORDER BY Created_At DESC";
$stmt_notif = $conn->prepare($notif_query);
$stmt_notif->bind_param("i", $current_donor_id);
$stmt_notif->execute();
$notif_result = $stmt_notif->get_result();

$popup_messages = [];
while ($row = $notif_result->fetch_assoc()) {
    $popup_messages[] = $row['Message'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recurring Donation Management - Love Bridge</title>
    <style>
        /* --- 核心变量 --- */
        :root {
            --brand-red: #dc2626;     /* 品牌红 */
            --dark-red: #b91c1c;      /* 深红 (Hover用) */
            --light-red: #fef2f2;     /* 浅红背景 */
            --text-dark: #1f2937;     
            --text-gray: #6b7280;     
            --bg-page: #f9fafb;       
            --border-color: #e5e7eb;  
            
            /* 功能色 */
            --green-bg: #ecfdf5; --green-text: #047857;
            --orange-bg: #fffbeb; --orange-text: #b45309;
            --gray-bg: #f3f4f6;   --gray-text: #374151;
        }

        body {
            font-family: 'Segoe UI', 'Inter', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            margin: 0; padding: 0;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 20px;
            min-height: 80vh;
        }

        /* --- 标题 --- */
        .page-header { text-align: center; margin-bottom: 40px; }
        .page-title { font-size: 2.2rem; color: var(--brand-red); font-weight: 800; margin-bottom: 10px; }
        .page-subtitle { color: var(--text-gray); font-size: 1rem; }

        /* --- 提示框 --- */
        .info-alert {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #dbeafe;
            padding: 15px 20px;
            border-radius: 8px;
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 30px;
        }
        .info-alert svg { stroke: #2563eb; }

        /* --- 卡片容器 --- */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        /* --- 表格样式 --- */
        .table-responsive { overflow-x: auto; }
        .styled-table { width: 100%; border-collapse: collapse; }

        /* 红色表头 */
        .styled-table thead tr {
            background-color: var(--brand-red);
            color: white;
        }

        .styled-table th {
            padding: 18px 20px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            text-align: left;
            border-bottom: none;
        }

        .styled-table td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            color: var(--text-dark);
        }

        .styled-table tbody tr:last-child td { border-bottom: none; }
        .styled-table tbody tr:hover { background-color: #fafafa; }

        /* --- 状态标签 --- */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        .status-active { background-color: var(--green-bg); color: var(--green-text); }
        .status-paused { background-color: var(--orange-bg); color: var(--orange-text); }
        .status-cancelled { background-color: var(--gray-bg); color: var(--gray-text); }

        /* --- 表单控件 --- */
        .date-input {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            color: var(--text-dark);
            margin-right: 8px;
            font-size: 0.9rem;
        }

        .action-group { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

        /* --- 按钮样式 --- */
        .btn-sm {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
            display: inline-flex; align-items: center; justify-content: center;
        }

        /* Update: 红色实心 */
        .btn-edit {
            background-color: var(--brand-red);
            color: white;
        }
        .btn-edit:hover { background-color: var(--dark-red); }

        /* Pause: 红色边框 + 红色字 */
        .btn-pause {
            background-color: white;
            color: var(--brand-red);
            border: 1px solid var(--brand-red);
        }
        .btn-pause:hover {
            background-color: var(--light-red); /* 浅红色背景 */
        }

        /* Resume: 绿色实心 (保持不变，以示区分) */
        .btn-resume {
            background-color: white;
            color: var(--green-text);
            border: 1px solid var(--green-text);
        }
        .btn-resume:hover {
            background-color: var(--green-bg);
        }

        /* 【修改】Cancel: 红色实心 (与 Update 一致) */
        .btn-cancel {
            background-color: var(--brand-red);
            color: white;
            border: 1px solid var(--brand-red); /* 确保有框 */
            text-decoration: none;
        }
        .btn-cancel:hover {
            background-color: var(--dark-red);
            border-color: var(--dark-red);
            color: white;
        }

        /* --- 下载区域 --- */
        .download-section {
            text-align: center;
            margin-top: 30px;
            padding: 30px;
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        .btn-download {
            background-color: white;
            color: var(--brand-red);
            border: 1px solid var(--brand-red);
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            transition: 0.2s;
        }
        .btn-download:hover {
            background-color: var(--brand-red);
            color: white;
        }

        /* --- 弹窗样式 --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: white; padding: 30px; border-radius: 12px; width: 400px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .modal-title { font-size: 1.4rem; color: var(--brand-red); font-weight: 700; margin-bottom: 15px; }
        .modal-content { text-align: left; margin-bottom: 20px; line-height: 1.6; }
        .modal-close-btn { background: var(--brand-red); color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        
        /* 响应式 */
        @media (max-width: 768px) {
            .styled-table thead { display: none; }
            .styled-table, tbody, tr, td { display: block; width: 100%; }
            .styled-table tr { margin-bottom: 15px; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: white; }
            .styled-table td { text-align: right; padding-left: 50%; position: relative; border-bottom: 1px solid #f3f4f6; }
            .styled-table td::before { content: attr(data-label); position: absolute; left: 20px; width: 45%; font-weight: 600; text-align: left; color: var(--text-gray); }
            .action-group { justify-content: flex-end; }
        }

        /* --- Tab 容器设计：胶囊滑动风格 --- */
.tabs {
    display: inline-flex;
    background-color: #f3f4f6; /* 浅灰色背景 */
    padding: 5px;
    border-radius: 12px;
    margin-bottom: 30px;
    position: relative;
    border: 1px solid var(--border-color);
}

.tab-btn {
    padding: 10px 24px;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: transparent;
    color: var(--text-gray);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 150px;
    justify-content: center;
    z-index: 1;
}

/* 悬停效果 */
.tab-btn:hover:not(.active) {
    color: var(--brand-red);
    background-color: rgba(220, 38, 38, 0.05);
}

/* 激活状态：红色高亮卡片 */
.tab-btn.active {
    background-color: white;
    color: var(--brand-red);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* 移动端自适应 */
@media (max-width: 480px) {
    .tabs { display: flex; width: 100%; }
    .tab-btn { flex: 1; min-width: auto; padding: 10px 10px; font-size: 0.85rem; }
}
    </style>
</head>

<body>

<?php include 'header_UI.php'; ?>

<div class="main-container">

    <div class="page-header">
        <h1 class="page-title">Recurring Donation Management</h1>
        <p class="page-subtitle">Manage your monthly contributions, modify schedules, or view history.</p>
    </div>

    <div class="info-alert" style="flex-direction: column; align-items: flex-start;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            <span><strong>Note:</strong> You can pause your donation plan temporarily and resume it at any time.</span>
        </div>
        <div style="background: #fffbeb; color: #92400e; padding: 10px; border-radius: 6px; border: 1px solid #fde68a; font-size: 0.9rem; width: 100%;">
            <i class="fas fa-wallet"></i> <strong>E-Wallet Users:</strong> Ensure sufficient wallet balance before the "Next Charge Date" to avoid failed deductions.
        </div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('active', this)">
            <i class="fas fa-sync-alt"></i> Recurring Plans
        </button> 
        <button class="tab-btn" onclick="switchTab('history', this)">
            <i class="fas fa-history"></i> My History
        </button>
    </div>

    <div id="active-tab">
        <div class="content-card">
            <div class="table-responsive">
                <table class="styled-table">

        <div class="content-card">
            <div class="table-responsive">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Plan ID</th>
                            <th>Project / Case</th>
                            <th>Amount (RM)</th>
                            <th>Next Charge Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($active_plans) > 0): ?>
                        <?php foreach($active_plans as $row): ?>
                            <tr>
                                <td data-label="Plan ID">#<?php echo $row['Recurring_ID']; ?></td>
                                <td data-label="Project">
                                    <strong><?php echo htmlspecialchars($row['Case_Title'] ?? $row['Branch_Name'] ?? $row['Activity_Name'] ?? 'General'); ?></strong>
                                </td>
                                <td data-label="Amount"><strong><?php echo number_format($row['Recurring_Amount'], 2); ?></strong></td>
                                <td data-label="Next Charge Date">
                                    <form method="POST" style="display:flex; align-items:center; justify-content: flex-end;">
                                        <input type="hidden" name="recurring_id" value="<?php echo $row['Recurring_ID']; ?>">
                                        <input type="date" name="new_date" value="<?php echo $row['Recurring_Deduction_Date']; ?>" class="date-input" min="<?php echo date('Y-m-d'); ?>">
                                        <button type="submit" name="update_date" class="btn-sm btn-edit">Update</button>
                                    </form>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge <?php echo ($row['Recurring_Status']=='Active') ? 'status-active' : 'status-paused'; ?>">
                                        <?php echo $row['Recurring_Status']; ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <form method="POST" class="action-group" id="form-<?php echo $row['Recurring_ID']; ?>">
                                        <input type="hidden" name="recurring_id" value="<?php echo $row['Recurring_ID']; ?>">
                                        <?php if ($row['Recurring_Status'] == 'Active'): ?>
                                            <button type="submit" name="action_type" value="pause" class="btn-sm btn-pause">Pause</button>
                                        <?php else: ?>
                                            <button type="submit" name="action_type" value="resume" class="btn-sm btn-resume">Resume</button>
                                        <?php endif; ?>
                                        <button type="button" class="btn-sm btn-cancel btn-trigger-cancel" data-id="<?php echo $row['Recurring_ID']; ?>">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding: 40px; color: var(--text-gray);">No active recurring plans found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="history-tab" style="display: none;">
        <div class="content-card">
            <div class="table-responsive">
                <table class="styled-table">
                    <thead>
                        <tr style="background-color: #4b5563;"> <th>Plan ID</th>
                            <th>Project / Case</th>
                            <th>Final Amount</th>
                            <th>Cancelled On</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($history_plans) > 0): ?>
                        <?php foreach($history_plans as $row): ?>
                            <tr style="opacity: 0.8;">
                                <td data-label="Plan ID">#<?php echo $row['Recurring_ID']; ?></td>
                                <td data-label="Project"><?php echo htmlspecialchars($row['Case_Title'] ?? $row['Branch_Name'] ?? $row['Activity_Name'] ?? 'General'); ?></td>
                                <td data-label="Amount">RM <?php echo number_format($row['Recurring_Amount'], 2); ?></td>
                                <td data-label="Cancelled On"><?php echo date('d M Y', strtotime($row['Recurring_Updated_At'])); ?></td>
                                <td data-label="Status"><span class="status-badge status-cancelled">Cancelled</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--text-gray);">No historical records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="download-section">
        <h3 class="download-title">Donation Receipts</h3>
        <p class="download-desc">Access and download your complete history of official tax-deductible receipts.</p>
        <a href="my_receipts.php" class="btn-download">
            <i class="fas fa-file-download"></i> View & Download All Receipts
        </a>
    </div>

</div>

<?php include 'footer.php'; ?>

<div id="notificationModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-title" style="font-size: 1.4rem; color: var(--brand-red); font-weight: 700; margin-bottom: 15px;">🔔 Friendly Reminder</div>
        <div id="modalText" style="text-align: left; margin-bottom: 20px; line-height: 1.6;"></div>
        <button class="btn-edit" onclick="closeModal()" style="padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">OK, Got it</button>
    </div>
</div>

<script>
    function switchTab(tabName, element) {
        // 移除所有按钮的 active 类
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
        // 为当前点击的按钮添加 active 类
        element.classList.add('active');
    
        // 切换内容显示
        document.getElementById('active-tab').style.display = (tabName === 'active' ? 'block' : 'none');
        document.getElementById('history-tab').style.display = (tabName === 'history' ? 'block' : 'none');
    }

    // 处理通知
    var messages = <?php echo json_encode($popup_messages); ?>;
    if (messages.length > 0) {
        document.getElementById("notificationModal").style.display = "flex";
        document.getElementById("modalText").innerHTML = "<ul>" + messages.map(m => `<li>${m}</li>`).join('') + "</ul>";
    }

    function closeModal() {
        document.getElementById("notificationModal").style.display = "none";
        fetch("<?php echo $_SERVER['PHP_SELF']; ?>", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "mark_read_all=1"
        });
    }

    // Cancel 确认框 (SweetAlert2)
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.btn-trigger-cancel').forEach(btn => {
            btn.addEventListener('click', function() {
                const planId = this.getAttribute('data-id');
                const form = document.getElementById('form-' + planId);
                Swal.fire({
                    title: 'Stop this plan?',
                    text: "The plan will move to history. You cannot resume it later.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: 'Yes, cancel it',
                    cancelButtonText: 'No, keep it',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        const input = document.createElement('input');
                        input.type = 'hidden'; input.name = 'action_type'; input.value = 'cancel';
                        form.appendChild(input);
                        form.submit();
                    }
                });
            });
        });

        // 成功/错误提示
        <?php if(isset($_SESSION['swal_success'])): ?>
            Swal.fire({ icon: 'success', title: 'Success!', text: '<?php echo $_SESSION['swal_success']; ?>', confirmButtonColor: '#dc2626' });
            <?php unset($_SESSION['swal_success']); ?>
        <?php endif; ?>
    });
</script>

</body>
</html>