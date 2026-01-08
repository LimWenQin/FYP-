<?php
// admin_sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'dataconnection.php';

$current_page = basename($_SERVER['PHP_SELF']);

// --- 1. 获取当前用户的角色 (用于 Sidebar 权限控制) ---
$sidebar_Role = "Guest";

if (isset($_SESSION['admin_id'])) {
    $sid_admin = $_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT Admin_Role FROM admin WHERE Admin_ID = ?");
    $stmt->bind_param("i", $sid_admin);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $sidebar_Role = $row['Admin_Role']; // 'Super Admin' or 'Admin'
    }
    $stmt->close();
} elseif (isset($_SESSION['staff_id'])) {
    $sidebar_Role = "Staff";
}

// --- 2. 定义权限列表 (根据你的要求) ---
// true = 允许访问, false = 禁止访问 (显示锁头)

// 默认全都不允许，下面根据角色开启
$perms = [
    'donor' => false,
    'staff' => false,
    'admin_manage' => false,
    'branch' => false,
    'activity' => false, // In-person & Special Case
    'payment' => false,
    'receipts' => false,
    'reward' => false,   // Reward & Redemption
    'block_list' => false
];

if ($sidebar_Role === 'Super Admin') {
    // Super Admin: 全部允许
    foreach ($perms as $key => $val) { $perms[$key] = true; }
} 
elseif ($sidebar_Role === 'Admin') {
    // Admin: 除了 Admin Management 和 Block List，其他都可以
    $perms['donor'] = true;
    $perms['staff'] = true;
    $perms['branch'] = true;
    $perms['activity'] = true;
    $perms['payment'] = true;
    $perms['receipts'] = true;
    $perms['reward'] = true;
    // admin_manage 和 block_list 保持 false
} 
elseif ($sidebar_Role === 'Staff') {
    // Staff: 只能看 Donor, Branch, Activity, Special Case, Reward, Redemption
    $perms['donor'] = true;
    $perms['branch'] = true;
    $perms['activity'] = true;
    $perms['reward'] = true;
    // staff, admin_manage, payment, receipts, block_list 保持 false
}

// --- 3. 辅助函数：生成菜单链接 ---
function renderMenuItem($isAllowed, $url, $iconClass, $text, $isActive) {
    if ($isAllowed) {
        // 有权限：正常链接
        $activeClass = $isActive ? 'active' : '';
        echo '<li>
                <a href="' . $url . '" class="' . $activeClass . '">
                    <i class="' . $iconClass . '"></i> <span>' . $text . '</span>
                </a>
              </li>';
    } else {
        // 无权限：锁住的链接 (点击不跳转，只弹窗)
        echo '<li>
                <a href="javascript:void(0)" onclick="showAccessDenied()" style="color: #aaa; cursor: not-allowed;">
                    <i class="' . $iconClass . '"></i> 
                    <span>' . $text . '</span>
                    <i class="fas fa-lock" style="float: right; font-size: 12px; margin-top: 4px;"></i>
                </a>
              </li>';
    }
}
?>


<div class="sidebar collapsed" id="sidebar">
    <div class="sidebar-menu">
        <ul>
            <li>
                <a href="admin_dashboard.php" class="<?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
            </li>
            
            <?php renderMenuItem($perms['donor'], 'admin_donor_page.php', 'fas fa-users', 'Donor Management', $current_page == 'admin_donor_page.php'); ?>

            <?php renderMenuItem($perms['staff'], 'staff_management_page.php', 'fas fa-user-tie', 'Staff Management', $current_page == 'staff_management_page.php'); ?>

            <?php renderMenuItem($perms['admin_manage'], 'admin_management_page.php', 'fas fa-user-shield', 'Admin Management', $current_page == 'admin_management_page.php'); ?>

            <?php renderMenuItem($perms['branch'], 'branch_management_page.php', 'fas fa-map-marker-alt', 'Branch Management', $current_page == 'branch_management_page.php'); ?>
            
            <?php 
                $is_campaign_active = ($current_page == 'activity_management.php' || $current_page == 'special_case_management.php');
                $campaign_allowed = $perms['activity']; 
            ?>
            <li class="has-submenu <?php echo $is_campaign_active ? 'open' : ''; ?>">
                <a href="javascript:void(0)" <?php echo $campaign_allowed ? 'onclick="toggleSubmenu(this)"' : 'onclick="showAccessDenied()" style="color:#aaa; cursor:not-allowed;"'; ?>>
                    <i class="fas fa-bullhorn"></i> 
                    <span>Campaign</span>
                    <?php if($campaign_allowed): ?>
                        <i class="fas fa-chevron-down arrow"></i>
                    <?php else: ?>
                        <i class="fas fa-lock" style="float: right; font-size: 12px; margin-top: 4px;"></i>
                    <?php endif; ?>
                </a>
                <?php if($campaign_allowed): ?>
                <ul class="submenu <?php echo $is_campaign_active ? 'show' : ''; ?>">
                    <li><a href="activity_management.php" class="<?php echo $current_page == 'activity_management.php' ? 'active' : ''; ?>">In-Person Activity</a></li>
                    <li><a href="special_case_management.php" class="<?php echo $current_page == 'special_case_management.php' ? 'active' : ''; ?>">Special Case</a></li>
                </ul>
                <?php endif; ?>
            </li>

            <?php renderMenuItem($perms['payment'], 'payment_management.php', 'fas fa-credit-card', 'Payment Management', $current_page == 'payment_management.php'); ?>

            <?php renderMenuItem($perms['receipts'], 'admin_receipts.php', 'fas fa-file-invoice-dollar', 'Tax Receipt Requests', $current_page == 'admin_receipts.php'); ?>

            <?php 
                $is_reward_active = ($current_page == 'reward_item_management.php' || $current_page == 'redemption_order_management.php');
                $reward_allowed = $perms['reward'];
            ?>
            <li class="has-submenu <?php echo $is_reward_active ? 'open' : ''; ?>">
                <a href="javascript:void(0)" <?php echo $reward_allowed ? 'onclick="toggleSubmenu(this)"' : 'onclick="showAccessDenied()" style="color:#aaa; cursor:not-allowed;"'; ?>>
                    <i class="fas fa-gift"></i> 
                    <span>Reward System</span>
                    <?php if($reward_allowed): ?>
                        <i class="fas fa-chevron-down arrow"></i>
                    <?php else: ?>
                        <i class="fas fa-lock" style="float: right; font-size: 12px; margin-top: 4px;"></i>
                    <?php endif; ?>
                </a>
                <?php if($reward_allowed): ?>
                <ul class="submenu <?php echo $is_reward_active ? 'show' : ''; ?>">
                    <li><a href="reward_item_management.php" class="<?php echo $current_page == 'reward_item_management.php' ? 'active' : ''; ?>">Reward Items</a></li>
                    <li><a href="redemption_order_management.php" class="<?php echo $current_page == 'redemption_order_management.php' ? 'active' : ''; ?>">Redemption Orders</a></li>
                </ul>
                <?php endif; ?>
            </li>

            <?php 
                $is_block_active = ($current_page == 'admin_blocked_donors.php' || $current_page == 'admin_blocked_staff.php' || $current_page == 'admin_blocked_admins.php');
                $block_allowed = $perms['block_list'];
            ?>
            <li class="has-submenu <?php echo $is_block_active ? 'open' : ''; ?>">
                <a href="javascript:void(0)" <?php echo $block_allowed ? 'onclick="toggleSubmenu(this)"' : 'onclick="showAccessDenied()" style="color:#aaa; cursor:not-allowed;"'; ?>>
                    <i class="fas fa-ban" <?php if($block_allowed) echo 'style="color: #dc3545;"'; ?>></i> 
                    <span <?php if($block_allowed) echo 'style="color: #dc3545;"'; ?>>Block List</span>
                    <?php if($block_allowed): ?>
                        <i class="fas fa-chevron-down arrow" style="color: #dc3545;"></i>
                    <?php else: ?>
                        <i class="fas fa-lock" style="float: right; font-size: 12px; margin-top: 4px;"></i>
                    <?php endif; ?>
                </a>
                <?php if($block_allowed): ?>
                <ul class="submenu <?php echo $is_block_active ? 'show' : ''; ?>">
                    <li><a href="admin_blocked_donors.php" class="<?php echo $current_page == 'admin_blocked_donors.php' ? 'active' : ''; ?>">Blocked Donors</a></li>
                    <li><a href="admin_blocked_staff.php" class="<?php echo $current_page == 'admin_blocked_staff.php' ? 'active' : ''; ?>">Blocked Staff</a></li>
                    <li><a href="admin_blocked_admins.php" class="<?php echo $current_page == 'admin_blocked_admins.php' ? 'active' : ''; ?>">Blocked Admins</a></li>
                </ul>
                <?php endif; ?>
            </li>

        </ul>
    </div>
</div>

<div id="accessDeniedAlert" style="display: none; position: fixed; top: 20px; right: 20px; background: white; border-left: 4px solid #dc3545; padding: 15px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; border-radius: 5px; animation: slideIn 0.3s ease-out;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-lock" style="color: #dc3545; font-size: 18px;"></i>
        <div>
            <h4 style="margin: 0; font-size: 14px; color: #333;">Access Denied</h4>
            <p style="margin: 2px 0 0; font-size: 12px; color: #666;">You are not authorized to view this page.</p>
        </div>
    </div>
</div>

<script>
// 显示无权限提示
function showAccessDenied() {
    const alertBox = document.getElementById('accessDeniedAlert');
    alertBox.style.display = 'block';
    
    // 3秒后自动消失
    setTimeout(() => {
        alertBox.style.opacity = '0';
        setTimeout(() => { 
            alertBox.style.display = 'none'; 
            alertBox.style.opacity = '1';
        }, 300);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    if (sidebar && mainContent) {
        sidebar.addEventListener('mouseenter', function() {
            sidebar.classList.remove('collapsed');
            mainContent.classList.add('expanded');
        });
        sidebar.addEventListener('mouseleave', function() {
            sidebar.classList.add('collapsed');
            mainContent.classList.remove('expanded');
            document.querySelectorAll('.sidebar-menu .submenu').forEach(menu => menu.classList.remove('show'));
            document.querySelectorAll('.sidebar-menu .has-submenu').forEach(item => item.classList.remove('open'));
        });
    }
});

function toggleSubmenu(element) {
    const sidebar = document.getElementById('sidebar');
    if (sidebar.classList.contains('collapsed')) return; 
    
    const parentLi = element.parentElement;
    const submenu = parentLi.querySelector('.submenu');
    
    parentLi.classList.toggle('open');
    submenu.classList.toggle('show');
}
</script>