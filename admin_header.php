<div class="top-nav">
    <div class="nav-left">
        <div class="logo">
            <a href="admin_dashboard.php">
                <img src="logo.jpg" alt="Logo">
                <h1>Love Bridge</h1>
            </a>
        </div>
        <div class="search-bar">
            <form action="admin_search_results.php" method="GET" style="display: flex; align-items: center; width: 100%;">
                <button type="submit" style="background: none; border: none; cursor: pointer; color: #666;">
                    <i class="fas fa-search"></i>
                </button>
                <input type="text" name="keyword" placeholder="Search donors, donations, stories..." required 
                       value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>">
            </form>
        </div>
    </div>
    
    <div class="nav-right">
        
        <div class="settings-dropdown-container" id="settingsDropdown">
            <div class="settings-icon" onclick="toggleSettingsMenu()">
                <i class="fas fa-cog"></i> </div>
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
                    <?php if (!empty($adminProfilePicture) && file_exists($adminProfilePicture)): ?>
                        <img src="<?php echo htmlspecialchars($adminProfilePicture); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; background: #ccc; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                            <?php echo isset($adminName) ? substr($adminName, 0, 1) : 'A'; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo isset($adminName) ? htmlspecialchars($adminName) : 'Admin'; ?></div>
                    <div class="user-role"><?php echo isset($adminPosition) ? htmlspecialchars($adminPosition) : 'Role'; ?></div>
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
                <a href="admin_profile.php?action=change_password" onclick="smartPasswordModal(event)">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <div class="divider"></div>
                <a href="admin_logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* 简单的样式补充 */
    .notification-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: start;
        gap: 10px;
    }
    .notification-item.unread {
        background-color: #f0f7ff;
    }
    .notif-icon {
        margin-top: 3px;
    }
    .notif-content p {
        margin: 0;
        font-size: 13px;
        color: #333;
    }
    .notif-time {
        font-size: 11px;
        color: #888;
        display: block;
        margin-top: 4px;
    }
    .notification-count {
        position: absolute;
        top: -5px;
        right: -5px;
        background: red;
        color: white;
        border-radius: 50%;
        padding: 2px 5px;
        font-size: 10px;
    }
    /* 确保下拉菜单默认隐藏 */
    .notification-dropdown, .user-profile-dropdown, .settings-menu {
        display: none;
    }
    .notification-dropdown.active, .user-profile-dropdown.active, .settings-menu.active {
        display: block;
    }
</style>

<script>
    // --- 0. Smart Password Logic ---
    function smartPasswordModal(e) {
        // 检查当前 URL 是否已经是 admin_profile.php
        if (window.location.pathname.includes('admin_profile.php')) {
            // 如果是，阻止跳转，直接调用弹窗函数
            e.preventDefault();
            // 确保函数存在（防止报错）
            if (typeof openChangePasswordModal === 'function') {
                openChangePasswordModal();
                // 关闭下拉菜单
                document.getElementById("userProfileMenu").classList.remove("active");
            } else {
                // 如果函数没加载出来，还是让它跳转刷新
                window.location.href = 'admin_profile.php?action=change_password';
            }
        }
        // 如果不是在 Profile 页面，就让它自然跳转，不用做任何事
    }

    // --- 1. Menu Toggles ---
    function toggleSettingsMenu() {
        closeAllMenus('settingsMenu');
        document.getElementById("settingsMenu").classList.toggle("active");
    }

    function toggleNotificationMenu() {
        closeAllMenus('notificationMenu');
        document.getElementById("notificationMenu").classList.toggle("active");
    }

    function toggleUserMenu() {
        closeAllMenus('userProfileMenu');
        document.getElementById("userProfileMenu").classList.toggle("active");
    }

    function closeAllMenus(exceptId) {
        const ids = ['settingsMenu', 'notificationMenu', 'userProfileMenu'];
        ids.forEach(id => {
            if(id !== exceptId) {
                const el = document.getElementById(id);
                if(el) el.classList.remove('active');
            }
        });
    }

    // Close menus when clicking outside
    window.onclick = function(event) {
        if (!event.target.closest('.settings-dropdown-container') && 
            !event.target.closest('.notification') && 
            !event.target.closest('.user-profile')) {
            closeAllMenus(null);
        }
    }

    // --- 2. Real-time Notification Logic ---

    function fetchNotifications() {
        fetch('fetch_admin_notifications.php')
            .then(response => response.json())
            .then(data => {
                updateNotificationUI(data);
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }

    function updateNotificationUI(data) {
        const countSpan = document.getElementById('notifCount');
        const listContainer = document.getElementById('notificationList');

        // 更新红点数字
        if (data.count > 0) {
            countSpan.style.display = 'block';
            countSpan.innerText = data.count > 99 ? '99+' : data.count;
        } else {
            countSpan.style.display = 'none';
        }

        // 更新列表内容
        if (data.notifications.length === 0) {
            listContainer.innerHTML = '<div style="padding:15px; text-align:center; color:#999;">No notifications yet</div>';
        } else {
            let html = '';
            data.notifications.forEach(notif => {
                const isUnreadClass = notif.is_read == 0 ? 'unread' : '';
                html += `
                    <div class="notification-item ${isUnreadClass}">
                        <div class="notif-icon" style="color: ${notif.color}">
                            <i class="fas ${notif.icon}"></i>
                        </div>
                        <div class="notif-content">
                            <p>${notif.message}</p>
                            <span class="notif-time">${notif.time}</span>
                        </div>
                    </div>
                `;
            });
            listContainer.innerHTML = html;
        }
    }

    function markAllAsRead(event) {
        if(event) event.stopPropagation(); // 防止菜单关闭

        fetch('mark_notifications_read.php', { method: 'POST' })
            .then(response => response.text())
            .then(result => {
                fetchNotifications(); // 重新加载以移除红点
            });
    }

    // 页面加载时立即获取，然后每5秒获取一次
    document.addEventListener('DOMContentLoaded', function() {
        fetchNotifications();
        setInterval(fetchNotifications, 5000); 
    });
</script>