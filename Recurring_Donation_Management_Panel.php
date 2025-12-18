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
        
        // 1. 状态更改 (暂停/恢复/取消)
        if (isset($_POST['action_type'])) {
            $action = $_POST['action_type'];
            $new_status = '';
            
            if ($action === 'pause') $new_status = 'Paused';
            elseif ($action === 'resume') $new_status = 'Active';
            elseif ($action === 'cancel') $new_status = 'Cancelled';

            if ($new_status) {
                $stmt = $conn->prepare("UPDATE recurring_donation SET Recurring_Status = ?, Recurring_Updated_At = NOW() WHERE Recurring_ID = ? AND Donor_ID = ?");
                $stmt->bind_param("sii", $new_status, $recurring_id, $current_donor_id);
                $stmt->execute();
            }
        }

        // 2. 修改扣款日期
        if (isset($_POST['update_date']) && isset($_POST['new_date'])) {
            $new_date = $_POST['new_date'];
            // 简单验证：必须是未来日期
            if (strtotime($new_date) > time()) {
                $stmt = $conn->prepare("UPDATE recurring_donation SET Recurring_Deduction_Date = ?, Recurring_Updated_At = NOW() WHERE Recurring_ID = ? AND Donor_ID = ?");
                $stmt->bind_param("sii", $new_date, $recurring_id, $current_donor_id);
                $stmt->execute();
            } else {
                echo "<script>alert('Please select a future date.');</script>";
            }
        }
    }
    
    // 刷新页面防止表单重复提交
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ==========================================
// 2. 读取数据 (核心修改部分)
// ==========================================

// 【修改点】使用 LEFT JOIN 查询具体的 Case / Branch / Activity 名字
$query = "SELECT r.*, 
                 sc.Case_Title, 
                 b.Branch_Name, 
                 a.Activity_Name
          FROM recurring_donation r
          LEFT JOIN special_case sc ON r.Case_ID = sc.Case_ID
          LEFT JOIN branch b ON r.Branch_ID = b.Branch_ID
          LEFT JOIN activity a ON r.Activity_ID = a.Activity_ID
          WHERE r.Donor_ID = ? AND r.Recurring_Status != 'Cancelled' 
          ORDER BY r.Recurring_Deduction_Date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$plans_result = $stmt->get_result();

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
            --brand-red: #dc2626;     /* 品牌红 (用于表头) */
            --text-dark: #1f2937;     /* 深色文字 */
            --text-gray: #6b7280;     /* 灰色文字 */
            --bg-page: #f9fafb;       /* 页面背景 */
            --border-color: #e5e7eb;  /* 边框颜色 */
            
            /* 柔和的功能色 (莫兰迪色系) */
            --green-bg: #ecfdf5; --green-text: #047857;
            --orange-bg: #fffbeb; --orange-text: #b45309;
            --red-bg: #fef2f2;    --red-text: #b91c1c;
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
            overflow: hidden; /* 确保表头圆角不被遮挡 */
            border: 1px solid var(--border-color);
        }

        /* --- 表格样式 --- */
        .table-responsive { overflow-x: auto; }
        .styled-table { width: 100%; border-collapse: collapse; }

        /* ★★★ 关键修改：保留红色表头 ★★★ */
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
            border-bottom: none; /* 红色背景不需要底部边框 */
        }

        .styled-table td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            color: var(--text-dark);
        }

        .styled-table tbody tr:last-child td { border-bottom: none; }
        .styled-table tbody tr:hover { background-color: #fafafa; }

        /* --- ★★★ 状态标签 (柔和版) ★★★ --- */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            letter-spacing: 0.5px;
        }

        /* Active: 浅绿底深绿字 */
        .status-active { background-color: var(--green-bg); color: var(--green-text); }
        
        /* Paused: 浅黄底深橘字 */
        .status-paused { background-color: var(--orange-bg); color: var(--orange-text); }
        
        /* Cancelled: 浅灰底深灰字 */
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

        .action-group { display: flex; gap: 8px; flex-wrap: wrap; }

        /* --- ★★★ 按钮样式 (舒服的配色) ★★★ --- */
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

        /* Update: 黑色实心 (稳重) */
        .btn-edit {
            background-color: #1f2937;
            color: white;
        }
        .btn-edit:hover { background-color: black; }

        /* Pause: 白色背景 + 橙色边框 (清爽) */
        .btn-pause {
            background-color: white;
            color: #d97706;
            border-color: #d97706;
        }
        .btn-pause:hover {
            background-color: #fffbeb;
        }

        /* Resume: 白色背景 + 绿色边框 (清爽) */
        .btn-resume {
            background-color: white;
            color: #059669;
            border-color: #059669;
        }
        .btn-resume:hover {
            background-color: #ecfdf5;
        }

        /* Cancel: 纯文字链接 (降低视觉干扰) */
        .btn-cancel {
            background-color: transparent;
            color: #9ca3af;
            text-decoration: underline;
            padding: 6px 8px;
        }
        .btn-cancel:hover {
            color: var(--brand-red);
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
    </style>
</head>

<body>

<?php include 'header_UI.php'; ?>

<div class="main-container">

    <div class="page-header">
        <h1 class="page-title">Recurring Donation Management</h1>
        <p class="page-subtitle">Manage your monthly contributions, modify schedules, or download receipts.</p>
    </div>

    <div class="info-alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        <span><strong>Note:</strong> You can pause your donation plan temporarily and resume it at any time without losing your records.</span>
    </div>

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
                <?php if ($plans_result->num_rows > 0): ?>
                    <?php while($row = $plans_result->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Plan ID">#<?php echo $row['Recurring_ID']; ?></td>
                            
                            <td data-label="Project / Case">
                                <?php 
                                    if (!empty($row['Case_Title'])) {
                                        echo "<strong><i class='fas fa-heart' style='color:#dc2626;'></i> " . htmlspecialchars($row['Case_Title']) . "</strong>";
                                    } elseif (!empty($row['Branch_Name'])) {
                                        echo "<strong><i class='fas fa-building' style='color:#dc2626;'></i> " . htmlspecialchars($row['Branch_Name']) . "</strong>";
                                    } elseif (!empty($row['Activity_Name'])) {
                                        echo "<strong><i class='fas fa-running' style='color:#dc2626;'></i> " . htmlspecialchars($row['Activity_Name']) . "</strong>";
                                    } else {
                                        echo "<span style='color:#999; font-style:italic;'>General Donation</span>";
                                    }
                                ?>
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
                                <form method="POST" class="action-group">
                                    <input type="hidden" name="recurring_id" value="<?php echo $row['Recurring_ID']; ?>">
                                    
                                    <?php if ($row['Recurring_Status'] == 'Active'): ?>
                                        <button type="submit" name="action_type" value="pause" class="btn-sm btn-pause">Pause</button>
                                    <?php else: ?>
                                        <button type="submit" name="action_type" value="resume" class="btn-sm btn-resume">Resume</button>
                                    <?php endif; ?>

                                    <button type="submit" name="action_type" value="cancel" class="btn-sm btn-cancel" onclick="return confirm('Are you sure you want to cancel this plan permanently?');">Cancel</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 40px; color: var(--medium-gray);">No active recurring plans found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="download-section">
        <h3 class="download-title">Monthly Receipts</h3>
        <p class="download-desc">Need a copy of your transaction history for tax purposes? Download your official receipt here.</p>
        <button class="btn-download">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Download PDF Receipt (Last Month)
        </button>
    </div>

</div>

<?php include 'footer.php'; ?>

<div id="notificationModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-title">🔔 Friendly Reminder</div>
        <div class="modal-content" id="modalText">
            </div>
        <button class="modal-close-btn" onclick="closeModal()">OK, Got it</button>
    </div>
</div>

<script>
    // 1. 获取 PHP 传来的未读消息数组
    var messages = <?php echo json_encode($popup_messages); ?>;

    // 2. 如果有消息，显示弹窗
    if (messages.length > 0) {
        var modal = document.getElementById("notificationModal");
        var contentDiv = document.getElementById("modalText");
        
        var htmlContent = "<ul>";
        messages.forEach(function(msg) {
            htmlContent += "<li>" + msg + "</li>";
        });
        htmlContent += "</ul>";
        
        contentDiv.innerHTML = htmlContent;
        modal.style.display = "flex"; 
    }

    // 3. 关闭弹窗并通知后台“已读”
    function closeModal() {
        document.getElementById("notificationModal").style.display = "none";
        
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "<?php echo $_SERVER['PHP_SELF']; ?>", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.send("mark_read_all=1");
    }
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

</body>
</html>