<?php
// admin_header.php

// 确保 Session 已开启
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 包含数据库连接 (使用 require_once 防止重复包含)
require_once 'dataconnection.php';

// --- 统一获取当前登录用户信息 (Admin 或 Staff) ---
$header_Name = "User";
$header_Role = "";
$header_Pic = null;

if (isset($_SESSION['admin_id'])) {
    // === 如果是 ADMIN ===
    $currentId = $_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = ?");
    $stmt->bind_param("i", $currentId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $header_Name = $row['Admin_Name'];
        $header_Pic = $row['Admin_ProfilePicture'];
        $header_Role = $row['Admin_Role'];
    }
    $stmt->close();
} elseif (isset($_SESSION['staff_id'])) {
    // === 如果是 STAFF ===
    $currentId = $_SESSION['staff_id'];
    $stmt = $conn->prepare("SELECT Staff_FullName, Staff_ProfilePicture, Staff_Role FROM staff WHERE Staff_ID = ?");
    $stmt->bind_param("i", $currentId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $header_Name = $row['Staff_FullName'];
        $header_Pic = $row['Staff_ProfilePicture'];
        $header_Role = $row['Staff_Role'];
    }
    $stmt->close();
}
?>

<div class="top-nav">
    <div class="nav-left">
        <div class="logo">
            <a href="admin_dashboard.php">
                <img src="logo.jpg" alt="Logo">
                <h1>Love Bridge</h1>
            </a>
        </div>
    </div>
    
    <div class="nav-right">
        <div class="settings-dropdown-container" id="settingsDropdown">
            <div class="settings-icon" onclick="toggleSettingsMenu()">
                <i class="fas fa-cog"></i> 
            </div>
            <div class="settings-menu" id="settingsMenu">
                <a href="admin_manage_stories.php"><i class="fas fa-book-open"></i> Manage Stories</a>
                <a href="admin_manage_pages.php?type=about_us"><i class="fas fa-info-circle"></i> About Us</a>
                <a href="admin_manage_pages.php?type=contact_us"><i class="fas fa-address-book"></i> Contact Us</a>
                <a href="admin_manage_pages.php?type=terms_condition"><i class="fas fa-file-contract"></i> Terms & Cond.</a>
                <a href="admin_manage_pages.php?type=privacy_policy"><i class="fas fa-user-shield"></i> Privacy Policy</a>
            </div>
        </div>

        <div class="notification" id="notificationDropdown" onclick="toggleNotificationMenu()">
            <i class="far fa-bell"></i>
            <span class="notification-count" id="notifCount" style="display: none;">0</span>
            
            <div class="notification-dropdown" id="notificationMenu">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <a href="javascript:void(0)" onclick="markAllAsRead(event)">Mark all as read</a>
                </div>
                <div class="notification-list" id="notificationList">
                    <div style="padding: 10px; text-align: center; color: #666;">Loading...</div>
                </div>
                <div class="notification-footer">
                    <a href="admin_notifications_all.php">View All Notifications</a>
                </div>
            </div>
        </div>

        <div class="user-profile" id="userProfileDropdown">
            <div class="user-profile-with-avatar" onclick="toggleUserMenu()">
                <div class="user-avatar">
                    <?php if (!empty($header_Pic) && file_exists($header_Pic)): ?>
                        <img src="<?php echo htmlspecialchars($header_Pic); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; background: #ccc; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight:bold;">
                            <?php echo substr($header_Name, 0, 1); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($header_Name); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($header_Role); ?></div>
                </div>
                <i class="fas fa-chevron-down" style="margin-left: 10px; font-size: 12px;"></i>
            </div>
            <div class="user-profile-dropdown" id="userProfileMenu">
                <?php if(isset($_SESSION['staff_id'])): ?>
                    <a href="staff_profile.php">
                        <i class="fas fa-user"></i> View Profile
                    </a>
                    <a href="staff_profile.php?edit=true">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="staff_profile.php?action=change_password" onclick="smartPasswordModal(event)">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                <?php else: ?>
                    <a href="admin_profile.php">
                        <i class="fas fa-user"></i> View Profile
                    </a>
                    <a href="admin_profile.php?edit=true">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="admin_profile.php?action=change_password" onclick="smartPasswordModal(event)">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                <?php endif; ?>
                
                <div class="divider"></div>
                <a href="admin_logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="globalLogoutModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 99999; justify-content: center; align-items: center;">
    <div class="modal-content" style="background-color: white; border-radius: 12px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden; animation: fadeInModal 0.2s ease-out;">
        <div class="modal-body" style="padding: 30px;">
            <div style="width: 70px; height: 70px; background: #ffe5e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-sign-out-alt" style="font-size: 30px; color: #dc3545;"></i>
            </div>
            <h2 style="font-size: 22px; color: #333; margin-bottom: 10px; font-weight: 700;">Ready to Leave?</h2>
            <p style="color: #666; margin-bottom: 30px; font-size: 15px; line-height: 1.5;">Are you sure you want to logout of the system?</p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button onclick="closeGlobalLogoutModal()" style="padding: 12px 25px; border-radius: 8px; border: 1px solid #ddd; background: white; color: #555; cursor: pointer; font-weight: 600; font-size: 14px; transition: 0.2s;">Cancel</button>
                <a href="admin_logout.php" onclick="proceedLogout()" style="padding: 12px 25px; border-radius: 8px; border: none; background: #dc3545; color: white; text-decoration: none; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.2s;">Logout</a>
            </div>
        </div>
    </div>
</div>

<style>
    .notification-item { padding: 10px; border-bottom: 1px solid #eee; display: flex; align-items: start; gap: 10px; }
    .notification-item.unread { background-color: #f0f7ff; }
    .notif-icon { margin-top: 3px; }
    .notif-content p { margin: 0; font-size: 13px; color: #333; }
    .notif-time { font-size: 11px; color: #888; display: block; margin-top: 4px; }
    
    .notification-count { 
        position: absolute; top: -6px; right: -6px; 
        background: linear-gradient(135deg, #ff4757, #ff6b81); color: white; 
        border-radius: 50%; min-width: 18px; height: 18px; padding: 0 4px; 
        display: flex; align-items: center; justify-content: center;
        font-size: 10px; font-weight: 700;
        border: 2px solid #fff; box-shadow: 0 2px 5px rgba(255, 71, 87, 0.4); z-index: 10;
        animation: pulse-red 2s infinite;
    }
    @keyframes pulse-red {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(255, 71, 87, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 71, 87, 0); }
    }
    .notification-dropdown, .user-profile-dropdown, .settings-menu { display: none; }
    .notification-dropdown.active, .user-profile-dropdown.active, .settings-menu.active { display: block; }
    @keyframes fadeInModal { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const logoutLinks = document.querySelectorAll('a[href*="admin_logout.php"]');
        logoutLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if(this.hasAttribute('onclick') && this.getAttribute('onclick') === 'proceedLogout()') return;
                e.preventDefault(); 
                document.getElementById('globalLogoutModal').style.display = 'flex'; 
                closeAllMenus(null);
            });
        });
    });

    function closeGlobalLogoutModal() { document.getElementById('globalLogoutModal').style.display = 'none'; }
    function proceedLogout() { return true; }

    function smartPasswordModal(e) {
        const isStaff = window.location.pathname.includes('staff_profile.php');
        const targetPage = isStaff ? 'staff_profile.php' : 'admin_profile.php';

        if (window.location.pathname.includes(targetPage)) {
            e.preventDefault();
            if (typeof openChangePasswordModal === 'function') {
                openChangePasswordModal();
                document.getElementById("userProfileMenu").classList.remove("active");
            } else {
                window.location.href = targetPage + '?action=change_password';
            }
        }
    }

    function toggleSettingsMenu() { closeAllMenus('settingsMenu'); document.getElementById("settingsMenu").classList.toggle("active"); }
    function toggleNotificationMenu() { closeAllMenus('notificationMenu'); document.getElementById("notificationMenu").classList.toggle("active"); }
    function toggleUserMenu() { closeAllMenus('userProfileMenu'); document.getElementById("userProfileMenu").classList.toggle("active"); }

    function closeAllMenus(exceptId) {
        const ids = ['settingsMenu', 'notificationMenu', 'userProfileMenu'];
        ids.forEach(id => {
            if(id !== exceptId) {
                const el = document.getElementById(id);
                if(el) el.classList.remove('active');
            }
        });
    }

    window.onclick = function(event) {
        if (!event.target.closest('.settings-dropdown-container') && 
            !event.target.closest('.notification') && 
            !event.target.closest('.user-profile') &&
            !event.target.closest('.modal')) { 
            closeAllMenus(null);
        }
    }

    function fetchNotifications() {
        fetch('fetch_admin_notifications.php')
            .then(response => response.json())
            .then(data => { updateNotificationUI(data); })
            .catch(error => { });
    }

    function updateNotificationUI(data) {
        const countSpan = document.getElementById('notifCount');
        const listContainer = document.getElementById('notificationList');
        if (data && data.count > 0) {
            countSpan.style.display = 'flex'; 
            countSpan.innerText = data.count > 99 ? '99+' : data.count;
        } else {
            countSpan.style.display = 'none';
        }
        if (data && data.notifications.length === 0) {
            listContainer.innerHTML = '<div style="padding:15px; text-align:center; color:#999;">No notifications yet</div>';
        } else if (data) {
            let html = '';
            data.notifications.forEach(notif => {
                const isUnreadClass = notif.is_read == 0 ? 'unread' : '';
                html += `
                    <div class="notification-item ${isUnreadClass}">
                        <div class="notif-icon" style="color: ${notif.color}"><i class="fas ${notif.icon}"></i></div>
                        <div class="notif-content"><p>${notif.message}</p><span class="notif-time">${notif.time}</span></div>
                    </div>`;
            });
            listContainer.innerHTML = html;
        }
    }

    function markAllAsRead(event) {
        if(event) event.stopPropagation();
        fetch('mark_notifications_read.php', { method: 'POST' })
            .then(response => response.text())
            .then(result => { fetchNotifications(); });
    }

    document.addEventListener('DOMContentLoaded', function() {
        if(typeof fetchNotifications === "function") {
             fetchNotifications();
             setInterval(fetchNotifications, 5000);
        }
    });
</script>