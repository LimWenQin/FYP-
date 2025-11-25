<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include 'dataconnection.php';

// Initialize variables
$search_query = "";
$status_filter = "";
$payment_method_filter = "";

// Handle search and filter
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['search'])) {
        $search_query = $_GET['search'];
    }
    if (isset($_GET['status'])) {
        $status_filter = $_GET['status'];
    }
    if (isset($_GET['payment_method'])) {
        $payment_method_filter = $_GET['payment_method'];
    }
}

// Build query with filters
$sql = "SELECT * FROM payment WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_query)) {
    $sql .= " AND (Payment_TXN_Ref LIKE ? OR Payment_Method LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($status_filter)) {
    $sql .= " AND Payment_Status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($payment_method_filter)) {
    $sql .= " AND Payment_Method = ?";
    $params[] = $payment_method_filter;
    $types .= "s";
}

$sql .= " ORDER BY Payment_Created_At DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get payment statistics
$stats_sql = "SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN Payment_Status = 'completed' THEN Payment_Amount ELSE 0 END) as total_completed_amount,
                SUM(CASE WHEN Payment_Status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN Payment_Status = 'failed' THEN 1 ELSE 0 END) as failed_count
              FROM payment";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $payment_id = $_POST['payment_id'];
    $new_status = $_POST['payment_status'];
    
    $update_sql = "UPDATE payment SET Payment_Status = ? WHERE Payment_ID = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $payment_id);
    
    if ($update_stmt->execute()) {
        $success_message = "Payment status updated successfully!";
        // Refresh the page to show updated status
        header("Location: payment_management.php");
        exit();
    } else {
        $error_message = "Error updating payment status: " . $conn->error;
    }
    $update_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - Donation Management System</title>
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-pink), var(--warm-orange), var(--warm-yellow));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255, 107, 157, 0.25);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-number {
            font-size: 2.5em;
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 16px;
            color: var(--text-light);
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 2px rgba(255, 107, 157, 0.2);
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(255, 107, 157, 0.3);
        }

        .filter-btn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 157, 0.4);
        }

        .reset-btn {
            background: var(--text-light);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .reset-btn:hover {
            background: #6c757d;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payments-table th,
        .payments-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .payments-table th {
            background: linear-gradient(90deg, var(--primary-pink), var(--warm-peach));
            color: white;
            font-weight: 600;
        }

        .payments-table tr:hover {
            background-color: rgba(255, 182, 193, 0.1);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: linear-gradient(135deg, #ffd3b6, #ffaa6d);
            color: #856404;
        }

        .status-completed {
            background: linear-gradient(135deg, #a8e6cf, #6dd5a8);
            color: #155724;
        }

        .status-failed {
            background: linear-gradient(135deg, #ffb3c6, #ff6b9d);
            color: #721c24;
        }

        .action-btn {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(255, 107, 157, 0.3);
        }

        .action-btn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 107, 157, 0.4);
        }

        .no-payments {
            padding: 40px;
            text-align: center;
            color: var(--text-light);
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 500px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--card-shadow);
            position: relative;
        }

        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-pink), var(--warm-peach));
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .modal-title {
            font-size: 24px;
            color: var(--text-dark);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: var(--primary-pink);
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--primary-pink), var(--warm-orange));
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
            width: 100%;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(255, 107, 157, 0.3);
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, var(--warm-orange), var(--primary-pink));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 157, 0.4);
        }

        @media (max-width: 1024px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .payments-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 768px) {
            .stats {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .payments-table th,
            .payments-table td {
                padding: 10px 5px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo" id="sidebarToggle">
            <img src="picture/logo.png" alt="Logo">
            <div class="logo-text">DonationMS</div>
        </div>
        
        <div class="sidebar-menu">
            <a href="admin_dashboard.php" class="menu-item">
                <ion-icon name="grid"></ion-icon>
                <div class="menu-text">Dashboard</div>
            </a>
            <a href="admin_donor_page.php" class="menu-item">
                <ion-icon name="people"></ion-icon>
                <div class="menu-text">Donor Management</div>
            </a>
            <a href="staff_management_page.php" class="menu-item">
                <ion-icon name="person-circle"></ion-icon>
                <div class="menu-text">Staff Management</div>
            </a>
            <a href="admin_management_page.php" class="menu-item">
                <ion-icon name="shield-checkmark"></ion-icon>
                <div class="menu-text">Admin Management</div>
            </a>
            <a href="branch_management_page.php" class="menu-item">
                <ion-icon name="business"></ion-icon>
                <div class="menu-text">Branch Management</div>
            </a>
            <a href="activity_management.php" class="menu-item">
                <ion-icon name="calendar"></ion-icon>
                <div class="menu-text">Activity Management</div>
            </a>
            <a href="payment_management.php" class="menu-item active">
                <ion-icon name="card"></ion-icon>
                <div class="menu-text">Payment Management</div>
            </a>
        </div>
    </aside>
    
    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <!-- Three-line menu toggle -->
                <div class="menu-toggle" id="menuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                
                <!-- Logo that links to homepage -->
                <a href="admin_dashboard.php" class="header-logo">
                    <img src="picture/logo.png" alt="Logo">
                    <div class="header-logo-text">DonationMS</div>
                </a>
            </div>
            
            <div class="header-right">
                <div class="welcome">Welcome back, <?php echo $_SESSION['admin_name']; ?>!</div>
                <a href="admin_logout.php" class="logout">Logout</a>
            </div>
        </div>
        
        <div class="dashboard">
            <h1 class="page-title">Payment Management</h1>
            
            <?php if (isset($error_message)): ?>
            <div class="error-message">
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
            <div class="success-message">
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="card"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_payments']; ?></div>
                    <div class="stat-label">Total Payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="cash"></ion-icon>
                    </div>
                    <div class="stat-number">RM <?php echo number_format($stats['total_completed_amount'], 2); ?></div>
                    <div class="stat-label">Completed Amount</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="time"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $stats['pending_count']; ?></div>
                    <div class="stat-label">Pending Payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <ion-icon name="close-circle"></ion-icon>
                    </div>
                    <div class="stat-number"><?php echo $stats['failed_count']; ?></div>
                    <div class="stat-label">Failed Payments</div>
                </div>
            </div>
            
            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="search">Search by Reference or Method</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Enter reference or payment method">
                    </div>
                    <div class="form-group">
                        <label for="status">Payment Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method">
                            <option value="">All Methods</option>
                            <option value="credit_card" <?php echo $payment_method_filter === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                            <option value="debit_card" <?php echo $payment_method_filter === 'debit_card' ? 'selected' : ''; ?>>Debit Card</option>
                            <option value="online_banking" <?php echo $payment_method_filter === 'online_banking' ? 'selected' : ''; ?>>Online Banking</option>
                            <option value="e-wallet" <?php echo $payment_method_filter === 'e-wallet' ? 'selected' : ''; ?>>E-Wallet</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="filter-btn">Apply Filters</button>
                    </div>
                    <div>
                        <a href="payment_management.php" class="reset-btn" style="text-decoration: none; display: block;">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <?php if ($result->num_rows > 0): ?>
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Transaction Ref</th>
                            <th>Payment Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Paid At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $payment['Payment_ID']; ?></td>
                            <td><?php echo htmlspecialchars($payment['Payment_TXN_Ref']); ?></td>
                            <td><?php echo htmlspecialchars($payment['Payment_Method']); ?></td>
                            <td>RM <?php echo number_format($payment['Payment_Amount'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $payment['Payment_Status']; ?>">
                                    <?php echo ucfirst($payment['Payment_Status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($payment['Payment_Created_At'])); ?></td>
                            <td>
                                <?php if ($payment['Payment_Paid_At'] !== '0000-00-00 00:00:00'): ?>
                                    <?php echo date('M j, Y g:i A', strtotime($payment['Payment_Paid_At'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="action-btn" onclick="openEditModal(<?php echo $payment['Payment_ID']; ?>, '<?php echo $payment['Payment_Status']; ?>')">
                                    Edit Status
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-payments">
                    <h3>No payments found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Payment Status Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Update Payment Status</h2>
                <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_payment_id" name="payment_id">
                <div class="form-group">
                    <label for="payment_status">Payment Status</label>
                    <select id="payment_status" name="payment_status" required>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="submit-btn">Update Status</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');
            
            // Toggle sidebar on menu button click (mobile)
            menuToggle.addEventListener('click', function() {
                if (window.innerWidth < 769) {
                    sidebar.classList.toggle('active');
                }
            });
            
            // Desktop hover functionality
            if (window.innerWidth >= 769) {
                let hoverTimeout;
                
                sidebar.addEventListener('mouseenter', function() {
                    clearTimeout(hoverTimeout);
                    sidebar.classList.add('expanded');
                });
                
                sidebar.addEventListener('mouseleave', function() {
                    hoverTimeout = setTimeout(function() {
                        sidebar.classList.remove('expanded');
                    }, 300);
                });
                
                // Keep sidebar expanded when hovering over menu items
                const menuItems = document.querySelectorAll('.menu-item');
                menuItems.forEach(item => {
                    item.addEventListener('mouseenter', function() {
                        clearTimeout(hoverTimeout);
                    });
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 769 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            });
            
            // Handle menu item clicks
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    menuItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    
                    // On mobile, close sidebar after selecting a menu item
                    if (window.innerWidth < 769) {
                        sidebar.classList.remove('active');
                    }
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 769) {
                    sidebar.classList.remove('active');
                } else {
                    sidebar.classList.remove('expanded');
                }
            });
        });

        // Modal functionality
        function openEditModal(paymentId, currentStatus) {
            document.getElementById('edit_payment_id').value = paymentId;
            document.getElementById('payment_status').value = currentStatus;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>