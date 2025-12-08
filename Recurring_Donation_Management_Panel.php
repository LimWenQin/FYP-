<?php
session_start();
include 'dataconnection.php';

// 【重要】实际项目中，这里应该从 Session 获取登录的 Donor_ID
$current_donor_id = 1; 

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
        
        // 1. 状态更改
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
            if (strtotime($new_date) > time()) {
                $stmt = $conn->prepare("UPDATE recurring_donation SET Recurring_Deduction_Date = ?, Recurring_Updated_At = NOW() WHERE Recurring_ID = ? AND Donor_ID = ?");
                $stmt->bind_param("sii", $new_date, $recurring_id, $current_donor_id);
                $stmt->execute();
            } else {
                echo "<script>alert('Please select a future date.');</script>";
            }
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ==========================================
// 2. 读取数据
// ==========================================
$query = "SELECT * FROM recurring_donation WHERE Donor_ID = ? AND Recurring_Status != 'Cancelled' ORDER BY Recurring_Deduction_Date ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $current_donor_id);
$stmt->execute();
$plans_result = $stmt->get_result();

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
        /* 引入 Homepage 的核心变量 */
        :root {
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-red: #fee2e2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --medium-gray: #737373;
            --dark-gray: #262626;
            --text-dark: #171717;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-gray); /* 统一背景色 */
            color: var(--text-dark);
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
            min-height: 80vh;
        }

        /* 标题部分 */
        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .page-title {
            font-size: 2.5rem;
            color: var(--primary-red);
            font-weight: 800;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary-red);
            border-radius: 2px;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--medium-gray);
            max-width: 600px;
            margin: 0 auto;
        }

        /* 卡片容器风格 (类似 Homepage .vm-card) */
        .content-card {
            background: var(--white);
            border-radius: 10px;
            padding: 40px;
            border-top: 5px solid var(--primary-red);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
        }

        /* 信息提示框 */
        .info-alert {
            background-color: var(--light-red);
            color: var(--dark-red);
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-red);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* 表格样式美化 */
        .table-responsive {
            overflow-x: auto;
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: 1rem;
            background-color: var(--white);
        }

        .styled-table thead tr {
            background-color: var(--primary-red);
            color: var(--white);
            text-align: left;
        }

        .styled-table th, 
        .styled-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }

        .styled-table th {
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .styled-table tbody tr {
            transition: all 0.2s ease;
        }

        .styled-table tbody tr:hover {
            background-color: var(--light-gray);
        }

        .styled-table tbody tr:last-of-type {
            border-bottom: 2px solid var(--primary-red);
        }

        /* 状态标签 */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-paused {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* 表单控件 */
        .date-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            color: var(--text-dark);
            margin-right: 5px;
        }

        .action-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* 按钮风格 (基于 Homepage .btn) */
        .btn-sm {
            padding: 8px 16px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-edit {
            background-color: var(--text-dark);
            color: white;
        }
        .btn-edit:hover { background-color: black; transform: translateY(-2px); }

        .btn-pause {
            background-color: var(--warning-orange);
            color: white;
        }
        .btn-pause:hover { background-color: #d97706; transform: translateY(-2px); }

        .btn-resume {
            background-color: var(--success-green);
            color: white;
        }
        .btn-resume:hover { background-color: #059669; transform: translateY(-2px); }

        .btn-cancel {
            background-color: var(--white);
            color: var(--primary-red);
            border: 1px solid var(--primary-red);
        }
        .btn-cancel:hover { background-color: var(--light-red); }

        .btn-download {
            background-color: var(--primary-red);
            color: white;
            padding: 15px 30px;
            font-size: 1rem;
            border: none;
            border-radius: 5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-download:hover {
            background-color: var(--dark-red);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }

        /* 底部下载区域 */
        .download-section {
            text-align: center;
            margin-top: 20px;
            padding: 40px;
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .download-title {
            font-size: 1.5rem;
            color: var(--text-dark);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .download-desc {
            color: var(--medium-gray);
            margin-bottom: 25px;
        }

        /* Modal Styles (保持你原来的逻辑，只改样式) */
        .modal-overlay {
            display: none;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.6); z-index: 1000;
            justify-content: center; align-items: center;
            backdrop-filter: blur(3px);
        }
        .modal-box {
            background: white; padding: 40px; border-radius: 10px;
            width: 500px; text-align: center; position: relative;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            border-top: 6px solid var(--primary-red);
            animation: popIn 0.3s ease-out;
        }
        .modal-title { 
            font-size: 1.8rem; color: var(--primary-red); font-weight: 800; 
            margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .modal-content { font-size: 1.1rem; margin-bottom: 30px; line-height: 1.6; text-align: left; color: var(--text-dark); }
        .modal-content ul { list-style: none; padding: 0; }
        .modal-content li { 
            background: var(--light-gray); margin-bottom: 10px; padding: 15px; border-radius: 5px; border-left: 3px solid var(--primary-red);
        }
        .modal-close-btn {
            background: var(--primary-red); color: white; padding: 12px 30px;
            border: none; border-radius: 5px; font-size: 1rem; font-weight: 700; cursor: pointer;
            transition: all 0.3s ease;
        }
        .modal-close-btn:hover { background: var(--dark-red); transform: translateY(-2px); }

        @keyframes popIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        /* 响应式调整 */
        @media (max-width: 768px) {
            .styled-table thead { display: none; }
            .styled-table, .styled-table tbody, .styled-table tr, .styled-table td {
                display: block; width: 100%;
            }
            .styled-table tr {
                margin-bottom: 20px; border: 1px solid #eee; border-radius: 8px; overflow: hidden;
            }
            .styled-table td {
                text-align: right; padding-left: 50%; position: relative;
                border-bottom: 1px solid #f5f5f5;
            }
            .styled-table td::before {
                content: attr(data-label);
                position: absolute; left: 15px; width: 45%; font-weight: 700; text-align: left; color: var(--primary-red);
            }
            .date-input { width: 100%; margin-bottom: 10px; }
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
                    <tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--medium-gray);">No active recurring plans found.</td></tr>
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
        
        // 组合消息列表
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

</body>
</html>