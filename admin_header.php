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
    /* Notification Styles */
    .notification-item { padding: 10px; border-bottom: 1px solid #eee; display: flex; align-items: start; gap: 10px; }
    .notification-item.unread { background-color: #f0f7ff; }
    .notif-icon { margin-top: 3px; }
    .notif-content p { margin: 0; font-size: 13px; color: #333; }
    .notif-time { font-size: 11px; color: #888; display: block; margin-top: 4px; }
    
    /* --- NEW NOTIFICATION BADGE DESIGN --- */
    .notification-count { 
        position: absolute; 
        top: -6px; 
        right: -6px; 
        
        /* Modern Gradient Background */
        background: linear-gradient(135deg, #ff4757, #ff6b81); 
        color: white; 
        
        /* Perfect Circle */
        border-radius: 50%; 
        min-width: 18px; 
        height: 18px; 
        padding: 0 4px; /* Slight padding for wider numbers */
        
        /* Centering Text */
        display: flex; 
        align-items: center; 
        justify-content: center;
        
        /* Typography */
        font-size: 10px; 
        font-weight: 700;
        
        /* Aesthetics */
        border: 2px solid #fff; /* White border to separate from icon */
        box-shadow: 0 2px 5px rgba(255, 71, 87, 0.4); /* Soft shadow */
        z-index: 10;
        
        /* Pulse Animation */
        animation: pulse-red 2s infinite;
    }

    @keyframes pulse-red {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(255, 71, 87, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 71, 87, 0); }
    }
    /* ------------------------------------- */

    .notification-dropdown, .user-profile-dropdown, .settings-menu { display: none; }
    .notification-dropdown.active, .user-profile-dropdown.active, .settings-menu.active { display: block; }
    @keyframes fadeInModal { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>

<script>
    // --- GLOBAL LOGOUT LOGIC ---
    document.addEventListener("DOMContentLoaded", function() {
        // Find all links pointing to admin_logout.php
        const logoutLinks = document.querySelectorAll('a[href*="admin_logout.php"]');
        logoutLinks.forEach(link => {
            // Remove any existing onclick handlers if necessary, or just add event listener
            link.addEventListener('click', function(e) {
                // If the link has 'proceedLogout' attached or is inside the modal, ignore
                if(this.hasAttribute('onclick') && this.getAttribute('onclick') === 'proceedLogout()') return;
                
                e.preventDefault(); // Stop navigation
                document.getElementById('globalLogoutModal').style.display = 'flex'; // Show modal
                
                // Close dropdown if open
                closeAllMenus(null);
            });
        });
    });

    function closeGlobalLogoutModal() {
        document.getElementById('globalLogoutModal').style.display = 'none';
    }
    
    function proceedLogout() {
        // Allow the link to work naturally
        return true;
    }

    // --- 0. Smart Password Logic ---
    function smartPasswordModal(e) {
        if (window.location.pathname.includes('admin_profile.php')) {
            e.preventDefault();
            if (typeof openChangePasswordModal === 'function') {
                openChangePasswordModal();
                document.getElementById("userProfileMenu").classList.remove("active");
            } else {
                window.location.href = 'admin_profile.php?action=change_password';
            }
        }
    }

    // --- 1. Menu Toggles ---
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
            !event.target.closest('.modal')) { // Prevent closing if clicking inside modal
            closeAllMenus(null);
        }
    }

    // --- 2. Real-time Notification Logic ---
    function fetchNotifications() {
        fetch('fetch_admin_notifications.php')
            .then(response => response.json())
            .then(data => { updateNotificationUI(data); })
            .catch(error => console.error('Error fetching notifications:', error));
    }

    function updateNotificationUI(data) {
        const countSpan = document.getElementById('notifCount');
        const listContainer = document.getElementById('notificationList');
        if (data.count > 0) {
            countSpan.style.display = 'flex'; // Ensure flex display for centering
            countSpan.innerText = data.count > 99 ? '99+' : data.count;
        } else {
            countSpan.style.display = 'none';
        }
        if (data.notifications.length === 0) {
            listContainer.innerHTML = '<div style="padding:15px; text-align:center; color:#999;">No notifications yet</div>';
        } else {
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
        // If fetch_admin_notifications.php exists, this runs
        if(typeof fetchNotifications === "function") {
             fetchNotifications();
             setInterval(fetchNotifications, 5000);
        }
    });
</script>