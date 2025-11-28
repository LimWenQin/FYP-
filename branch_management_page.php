<?php
// branch_management_page.php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 包含数据库连接
include 'dataconnection.php';

// 获取管理员信息
$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];
$adminEmail = $_SESSION['admin_email'];

// 获取管理员头像
$adminProfilePicture = null;
$sql = "SELECT Admin_ProfilePicture FROM admin WHERE Admin_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $adminProfilePicture = $row['Admin_ProfilePicture'];
}
$stmt->close();

// 获取分支统计数据
function getTotalBranches($conn) {
    $sql = "SELECT COUNT(*) as total FROM branch";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

function getUrgentAttentionBranches($conn) {
    // 由于数据库中没有Branch_Status字段，我们使用示例数据
    // 在实际应用中，您可能需要添加这个字段或使用其他逻辑
    return 2; // 示例数据
}

function getTotalBeneficiaries($conn) {
    // 由于数据库中没有受益人字段，我们使用示例数据
    return 330; // 示例数据
}

function getAllCategories($conn) {
    // 计算所有类别数量
    $sql = "SELECT COUNT(DISTINCT Branch_Type) as total FROM branch";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// 获取分支列表
function getBranches($conn, $search = '', $category = '') {
    $sql = "SELECT * FROM branch WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $sql .= " AND (Branch_Name LIKE ? OR Branch_Address LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ss";
    }
    
    if (!empty($category) && $category !== 'All Categories') {
        $sql .= " AND Branch_Type = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    $sql .= " ORDER BY Branch_Name";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $branches = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $branches[] = $row;
        }
    }
    
    $stmt->close();
    return $branches;
}

// 获取所有分支类型
function getBranchTypes($conn) {
    $sql = "SELECT DISTINCT Branch_Type FROM branch ORDER BY Branch_Type";
    $result = $conn->query($sql);
    
    $types = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $types[] = $row['Branch_Type'];
        }
    }
    
    return $types;
}

// 处理搜索和筛选
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

// 获取数据
$totalBranches = getTotalBranches($conn);
$urgentAttention = getUrgentAttentionBranches($conn);
$totalBeneficiaries = getTotalBeneficiaries($conn);
$allCategories = getAllCategories($conn);
$branches = getBranches($conn, $search, $category);
$branchTypes = getBranchTypes($conn);

// 关闭数据库连接
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Management - Donation Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* Branch Management Specific Styles */
        .branch-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-info h3 {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .stat-info h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 12px;
            color: var(--success);
            display: flex;
            align-items: center;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: rgba(242, 133, 133, 0.2);
            color: var(--primary);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: rgba(255, 193, 7, 0.2);
            color: var(--warning);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: rgba(40, 167, 69, 0.2);
            color: var(--success);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: rgba(23, 162, 184, 0.2);
            color: var(--info);
        }

        /* Search and Filter Section */
        .search-filter {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: var(--gray-light);
            border-radius: 20px;
            padding: 8px 15px;
            flex: 1;
            min-width: 300px;
        }

        .search-bar input {
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
            margin-left: 10px;
        }

        .category-filter {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-filter select {
            padding: 8px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            background: white;
            outline: none;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #e07575;
        }

        /* Branch Cards */
        .branch-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .branch-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }

        .branch-card:hover {
            transform: translateY(-5px);
        }

        .branch-header {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--gray-light);
        }

        .branch-type {
            font-size: 12px;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
            text-transform: uppercase;
        }

        .branch-type.orphanage {
            background: rgba(242, 133, 133, 0.1);
            color: var(--primary);
        }

        .branch-type.animal-shelter {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .branch-type.old-folks-home {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        .branch-type.disabled-care {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .branch-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray);
            font-size: 14px;
            transition: color 0.3s;
        }

        .action-btn:hover {
            color: var(--primary);
        }

        .branch-content {
            padding: 20px;
        }

        .branch-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .branch-details {
            margin-bottom: 15px;
        }

        .branch-detail {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--gray);
        }

        .branch-detail i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
        }

        .branch-status {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-right: 10px;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-urgent {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .branch-capacity {
            margin-bottom: 15px;
        }

        .capacity-label {
            font-size: 14px;
            margin-bottom: 5px;
            color: var(--gray);
        }

        .capacity-bar {
            height: 8px;
            background: var(--gray-light);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .capacity-fill {
            height: 100%;
            border-radius: 4px;
        }

        .capacity-fill.low {
            background: var(--success);
        }

        .capacity-fill.medium {
            background: var(--warning);
        }

        .capacity-fill.high {
            background: var(--danger);
        }

        .capacity-text {
            font-size: 12px;
            color: var(--gray);
            text-align: right;
        }

        .branch-needs {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--gray-light);
        }

        .needs-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .needs-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .need-tag {
            padding: 4px 8px;
            background: var(--light);
            border-radius: 4px;
            font-size: 12px;
            color: var(--gray);
        }

        .branch-audit {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--gray-light);
            font-size: 12px;
            color: var(--gray);
        }

        .audit-actions {
            display: flex;
            gap: 10px;
        }

        /* Add Branch Button */
        .add-branch-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(242, 133, 133, 0.3);
            cursor: pointer;
            transition: all 0.3s;
            z-index: 100;
        }

        .add-branch-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 15px rgba(242, 133, 133, 0.4);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .branch-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .search-filter {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-bar {
                min-width: auto;
            }
            
            .branch-cards {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .branch-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar collapsed" id="sidebar">
        <div class="sidebar-menu">
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="admin_donor_page.php"><i class="fas fa-users"></i> <span>Donor Management</span></a></li>
                <li><a href="staff_management_page.php"><i class="fas fa-user-tie"></i> <span>Staff Management</span></a></li>
                <li><a href="admin_management_page.php"><i class="fas fa-user-shield"></i> <span>Admin Management</span></a></li>
                <li><a href="branch_management_page.php" class="active"><i class="fas fa-map-marker-alt"></i> <span>Branch Management</span></a></li>
                <li><a href="activity_management.php"><i class="fas fa-calendar-alt"></i> <span>Activity Management</span></a></li>
                <li><a href="payment_management.php"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div class="nav-left">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo">
                    <a href="admin_dashboard.php">
                        <img src="logo.jpg" alt="Logo">
                        <h1>DonationMS</h1>
                    </a>
                </div>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
            </div>
            <div class="nav-right">
                <div class="notification" id="notificationDropdown">
                    <i class="far fa-bell"></i>
                    <span class="notification-count">5</span>
                    <div class="notification-dropdown" id="notificationMenu">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <a href="#" onclick="markAllAsRead()">Mark all as read</a>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <!-- Notifications will be loaded here -->
                        </div>
                        <div class="notification-footer">
                            <a href="notifications.php">View All Notifications</a>
                        </div>
                    </div>
                </div>
                <div class="user-profile" id="userProfileDropdown">
                    <div class="user-profile-with-avatar">
                        <div class="user-avatar">
                            <?php if (!empty($adminProfilePicture)): ?>
                                <img src="<?php echo htmlspecialchars($adminProfilePicture); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <?php echo substr($adminName, 0, 1); ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($adminName); ?></div>
                            <div class="user-role">System Administrator</div>
                        </div>
                        <i class="fas fa-chevron-down" style="margin-left: 10px; font-size: 12px;"></i>
                    </div>
                    <div class="user-profile-dropdown" id="userProfileMenu">
                        <a href="admin_profile.php">
                            <i class="fas fa-user"></i> View Profile
                        </a>
                        <a href="admin_profile.php?edit=true">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                        <div class="divider"></div>
                        <a href="admin_logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Branch Management Content -->
        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Branch Management</h1>
                <p>Manage all aid centers, shelters, and care homes.</p>
            </div>

            <!-- Stats Cards -->
            <div class="branch-stats">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL BRANCHES</h3>
                        <h2><?php echo $totalBranches; ?></h2>
                        <p><i class="fas fa-building"></i> All locations</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>URGENT ATTENTION</h3>
                        <h2><?php echo $urgentAttention; ?></h2>
                        <p><i class="fas fa-exclamation-triangle"></i> Need immediate help</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>TOTAL BENEFICIARIES</h3>
                        <h2><?php echo $totalBeneficiaries; ?></h2>
                        <p><i class="fas fa-users"></i> People helped</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>ALL CATEGORIES</h3>
                        <h2><?php echo $allCategories; ?></h2>
                        <p><i class="fas fa-tags"></i> Different types</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-tag"></i>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <form method="GET" action="branch_management_page.php">
                <div class="search-filter">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search branches by name or location..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="category-filter">
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php foreach($branchTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $category === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                    </div>
                </div>
            </form>

            <!-- Branch Cards -->
            <div class="branch-cards">
                <?php if (count($branches) > 0): ?>
                    <?php foreach($branches as $branch): 
                        // 确定分支类型和状态
                        $typeClass = '';
                        $statusClass = '';
                        $statusText = '';
                        $capacityPercent = 0;
                        
                        switch($branch['Branch_Type']) {
                            case 'ORPHANAGE':
                                $typeClass = 'orphanage';
                                $statusClass = 'status-active';
                                $statusText = 'Active';
                                $capacityPercent = 75;
                                break;
                            case 'ANIMAL SHELTER':
                                $typeClass = 'animal-shelter';
                                $statusClass = 'status-active';
                                $statusText = 'Active';
                                $capacityPercent = 84; // 42/50
                                break;
                            case 'OLD FOLKS HOME':
                                $typeClass = 'old-folks-home';
                                $statusClass = 'status-active';
                                $statusText = 'Active';
                                $capacityPercent = 93; // 28/30
                                break;
                            case 'DISABLED CARE':
                                $typeClass = 'disabled-care';
                                $statusClass = 'status-active';
                                $statusText = 'Active';
                                $capacityPercent = 38; // 15/40
                                break;
                            default:
                                $typeClass = 'orphanage';
                                $statusClass = 'status-active';
                                $statusText = 'Active';
                                $capacityPercent = 50;
                        }
                        
                        // 确定容量填充类
                        $capacityFillClass = 'low';
                        if ($capacityPercent > 70) {
                            $capacityFillClass = 'high';
                        } elseif ($capacityPercent > 50) {
                            $capacityFillClass = 'medium';
                        }
                    ?>
                    <div class="branch-card">
                        <div class="branch-header">
                            <span class="branch-type <?php echo $typeClass; ?>"><?php echo htmlspecialchars($branch['Branch_Type']); ?></span>
                            <div class="branch-actions">
                                <button class="action-btn" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="action-btn" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="branch-content">
                            <h3 class="branch-name"><?php echo htmlspecialchars($branch['Branch_Name']); ?></h3>
                            
                            <div class="branch-details">
                                <div class="branch-detail">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($branch['Branch_Address']); ?></span>
                                </div>
                                <div class="branch-detail">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($branch['Branch_ContactNumber']); ?></span>
                                </div>
                            </div>
                            
                            <div class="branch-status">
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                <?php if ($branch['Branch_Type'] == 'ORPHANAGE'): ?>
                                    <span class="status-badge status-urgent">URGENT HELP NEGLIGENCE</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (in_array($branch['Branch_Type'], ['ANIMAL SHELTER', 'OLD FOLKS HOME', 'DISABLED CARE'])): ?>
                            <div class="branch-capacity">
                                <div class="capacity-label">Capacity</div>
                                <div class="capacity-bar">
                                    <div class="capacity-fill <?php echo $capacityFillClass; ?>" style="width: <?php echo $capacityPercent; ?>%"></div>
                                </div>
                                <div class="capacity-text">
                                    <?php 
                                    if ($branch['Branch_Type'] == 'ANIMAL SHELTER') {
                                        echo "42 / 50";
                                    } elseif ($branch['Branch_Type'] == 'OLD FOLKS HOME') {
                                        echo "28 / 30";
                                    } elseif ($branch['Branch_Type'] == 'DISABLED CARE') {
                                        echo "15 / 40";
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="branch-needs">
                                <div class="needs-label">CURRENT NEEDS</div>
                                <div class="needs-list">
                                    <?php 
                                    if ($branch['Branch_Type'] == 'DISABLED CARE') {
                                        echo '<span class="need-tag">Adult Diapers</span>';
                                        echo '<span class="need-tag">Wheelchairs</span>';
                                        echo '<span class="need-tag">Vitamins</span>';
                                    } elseif ($branch['Branch_Type'] == 'ORPHANAGE') {
                                        echo '<span class="need-tag">Renovation Funds</span>';
                                    } else {
                                        echo '<span class="need-tag">Food Supplies</span>';
                                        echo '<span class="need-tag">Medical Aid</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="branch-audit">
                                <span>Audit: <?php 
                                    if ($branch['Branch_Type'] == 'DISABLED CARE') {
                                        echo '2023-09-20';
                                    } elseif ($branch['Branch_Type'] == 'ORPHANAGE') {
                                        echo '2023-01-10';
                                    } else {
                                        echo '2023-10-15';
                                    }
                                ?></span>
                                <div class="audit-actions">
                                    <button class="action-btn" title="Edit"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="action-btn" title="Delete"><i class="fas fa-trash"></i> Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: white; border-radius: 10px;">
                        <i class="fas fa-inbox" style="font-size: 48px; color: var(--gray-light); margin-bottom: 20px;"></i>
                        <h3>No branches found</h3>
                        <p>Try adjusting your search or filter criteria</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Branch Button -->
    <div class="add-branch-btn" title="Add New Branch">
        <i class="fas fa-plus"></i>
    </div>

    <script>
        // Sidebar toggle functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Change icon based on state
            const icon = menuToggle.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-bars';
            } else {
                icon.className = 'fas fa-times';
            }
        });

        // Add active class to sidebar menu items on click
        document.querySelectorAll('.sidebar-menu a').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.sidebar-menu a').forEach(link => {
                    link.classList.remove('active');
                });
                this.classList.add('active');
            });
        });

        // Dropdown functionality
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationMenu = document.getElementById('notificationMenu');
        const userProfileDropdown = document.getElementById('userProfileDropdown');
        const userProfileMenu = document.getElementById('userProfileMenu');

        // Toggle notification dropdown
        notificationDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu.classList.toggle('show');
            userProfileMenu.classList.remove('show');
        });

        // Toggle user profile dropdown
        userProfileDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            userProfileMenu.classList.toggle('show');
            notificationMenu.classList.remove('show');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            notificationMenu.classList.remove('show');
            userProfileMenu.classList.remove('show');
        });

        // Load notifications
        function loadNotifications() {
            const notificationList = document.getElementById('notificationList');
            const notifications = [
                {
                    type: 'success',
                    icon: 'fas fa-donate',
                    title: 'New Donation Received',
                    message: 'John Smith donated RM 500.00',
                    time: '5 minutes ago',
                    unread: true
                },
                {
                    type: 'info',
                    icon: 'fas fa-user-plus',
                    title: 'New Donor Registered',
                    message: 'Sarah Johnson registered as a new donor',
                    time: '1 hour ago',
                    unread: true
                },
                {
                    type: 'warning',
                    icon: 'fas fa-exclamation-triangle',
                    title: 'Low Stock Alert',
                    message: 'Reward items are running low',
                    time: '2 hours ago',
                    unread: false
                },
                {
                    type: 'danger',
                    icon: 'fas fa-times-circle',
                    title: 'Payment Failed',
                    message: 'A recurring donation payment failed',
                    time: '1 day ago',
                    unread: false
                },
                {
                    type: 'info',
                    icon: 'fas fa-calendar-check',
                    title: 'Activity Reminder',
                    message: 'Charity event starts tomorrow',
                    time: '2 days ago',
                    unread: false
                }
            ];

            let html = '';
            notifications.forEach(notification => {
                html += `
                    <div class="notification-item ${notification.unread ? 'unread' : ''}">
                        <div class="notification-content">
                            <div class="notification-icon ${notification.type}">
                                <i class="${notification.icon}"></i>
                            </div>
                            <div class="notification-details">
                                <h4>${notification.title}</h4>
                                <p>${notification.message}</p>
                                <div class="notification-time">${notification.time}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            notificationList.innerHTML = html;
        }

        // Mark all as read function
        function markAllAsRead() {
            const notificationItems = document.querySelectorAll('.notification-item.unread');
            notificationItems.forEach(item => {
                item.classList.remove('unread');
            });
            
            // Update notification count
            const notificationCount = document.querySelector('.notification-count');
            notificationCount.textContent = '0';
            notificationCount.style.display = 'none';
            
            // Close dropdown
            notificationMenu.classList.remove('show');
        }

        // Add branch button functionality
        document.querySelector('.add-branch-btn').addEventListener('click', function() {
            alert('Add New Branch functionality would go here');
            // In a real application, this would open a modal or redirect to a form page
        });

        // Load notifications when page loads
        document.addEventListener('DOMContentLoaded', loadNotifications);
    </script>
</body>
</html>
