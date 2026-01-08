<?php
// admin_sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    .sidebar-menu li a .arrow {
        display: inline-block;
        margin-left: auto;
        transition: transform 0.3s ease;
        float: right; 
        margin-top: 5px;
    }
    .sidebar.collapsed .sidebar-menu li a .arrow { display: none !important; }
    .sidebar-menu li.open > a .arrow { transform: rotate(180deg); }
</style>

<div class="sidebar collapsed" id="sidebar">
    <div class="sidebar-menu">
        <ul>
            <li>
                <a href="admin_dashboard.php" class="<?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
            </li>
            
            <li>
                <a href="admin_donor_page.php" class="<?php echo $current_page == 'admin_donor_page.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> <span>Donor Management</span>
                </a>
            </li>
            <li>
                <a href="staff_management_page.php" class="<?php echo $current_page == 'staff_management_page.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie"></i> <span>Staff Management</span>
                </a>
            </li>
            <li>
                <a href="admin_management_page.php" class="<?php echo $current_page == 'admin_management_page.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield"></i> <span>Admin Management</span>
                </a>
            </li>

            <li>
                <a href="branch_management_page.php" class="<?php echo $current_page == 'branch_management_page.php' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marker-alt"></i> <span>Branch Management</span>
                </a>
            </li>
            
            <?php 
                $is_campaign_active = ($current_page == 'activity_management.php' || $current_page == 'special_case_management.php');
            ?>
            <li class="has-submenu <?php echo $is_campaign_active ? 'open' : ''; ?>">
                <a href="javascript:void(0)" onclick="toggleSubmenu(this)">
                    <i class="fas fa-bullhorn"></i> 
                    <span>Campaign</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <ul class="submenu <?php echo $is_campaign_active ? 'show' : ''; ?>">
                    <li><a href="activity_management.php" class="<?php echo $current_page == 'activity_management.php' ? 'active' : ''; ?>">In-Person Activity</a></li>
                    <li><a href="special_case_management.php" class="<?php echo $current_page == 'special_case_management.php' ? 'active' : ''; ?>">Special Case</a></li>
                </ul>
            </li>

            <li>
                <a href="payment_management.php" class="<?php echo $current_page == 'payment_management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i> <span>Payment Management</span>
                </a>
            </li>

            <li>
                <a href="admin_receipts.php" class="<?php echo $current_page == 'admin_receipts.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice-dollar"></i> <span>Tax Receipt Requests</span>
                </a>
            </li>

            <?php 
                $is_reward_active = ($current_page == 'reward_item_management.php' || $current_page == 'redemption_order_management.php');
            ?>
            <li class="has-submenu <?php echo $is_reward_active ? 'open' : ''; ?>">
                <a href="javascript:void(0)" onclick="toggleSubmenu(this)">
                    <i class="fas fa-gift"></i> 
                    <span>Reward System</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <ul class="submenu <?php echo $is_reward_active ? 'show' : ''; ?>">
                    <li><a href="reward_item_management.php" class="<?php echo $current_page == 'reward_item_management.php' ? 'active' : ''; ?>">Reward Items</a></li>
                    <li><a href="redemption_order_management.php" class="<?php echo $current_page == 'redemption_order_management.php' ? 'active' : ''; ?>">Redemption Orders</a></li>
                </ul>
            </li>

            <?php 
                $is_block_active = ($current_page == 'admin_blocked_donors.php' || $current_page == 'admin_blocked_staff.php' || $current_page == 'admin_blocked_admins.php');
            ?>
            <li class="has-submenu <?php echo $is_block_active ? 'open' : ''; ?>">
                <a href="javascript:void(0)" onclick="toggleSubmenu(this)">
                    <i class="fas fa-ban" style="color: #dc3545;"></i> 
                    <span style="color: #dc3545;">Block List</span>
                    <i class="fas fa-chevron-down arrow" style="color: #dc3545;"></i>
                </a>
                <ul class="submenu <?php echo $is_block_active ? 'show' : ''; ?>">
                    <li><a href="admin_blocked_donors.php" class="<?php echo $current_page == 'admin_blocked_donors.php' ? 'active' : ''; ?>">Blocked Donors</a></li>
                    <li><a href="admin_blocked_staff.php" class="<?php echo $current_page == 'admin_blocked_staff.php' ? 'active' : ''; ?>">Blocked Staff</a></li>
                    <li><a href="admin_blocked_admins.php" class="<?php echo $current_page == 'admin_blocked_admins.php' ? 'active' : ''; ?>">Blocked Admins</a></li>
                </ul>
            </li>

        </ul>
    </div>
</div>

<script>
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