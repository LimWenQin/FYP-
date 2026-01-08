<?php
// admin_blocked_staff.php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
include 'dataconnection.php';

// --- Header Data ---
$currentAdminId = $_SESSION['admin_id'];
$adminSql = "SELECT Admin_Name, Admin_ProfilePicture, Admin_Role FROM admin WHERE Admin_ID = $currentAdminId";
$adminResult = $conn->query($adminSql);
if ($adminResult && $adminResult->num_rows > 0) {
    $adminData = $adminResult->fetch_assoc();
    $adminName = $adminData['Admin_Name'];
    $adminPosition = $adminData['Admin_Role']; 
    $adminProfilePicture = $adminData['Admin_ProfilePicture']; 
} else {
    $adminName = "Admin"; $adminPosition = "System Admin"; $adminProfilePicture = null;
}

// --- ACTIONS ---
if (isset($_GET['restore_id'])) {
    $restoreId = intval($_GET['restore_id']);
    $conn->query("UPDATE staff SET Is_Deleted = 0 WHERE Staff_ID = $restoreId");
    header("Location: admin_blocked_staff.php?success=" . urlencode("Staff restored successfully!")); exit();
}
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    $conn->query("DELETE FROM staff WHERE Staff_ID = $deleteId");
    header("Location: admin_blocked_staff.php?success=" . urlencode("Staff permanently deleted!")); exit();
}

// --- SEARCH & FILTER ---
// 逻辑：Filter (Email, ID, Contact) + Search Box
$searchType = isset($_GET['search_type']) ? $_GET['search_type'] : 'name';
$searchValue = isset($_GET['search_value']) ? $conn->real_escape_string($_GET['search_value']) : "";

$whereClause = "WHERE Is_Deleted = 1";

if (!empty($searchValue)) {
    switch ($searchType) {
        case 'email':
            $whereClause .= " AND Staff_Email LIKE '%$searchValue%'";
            break;
        case 'id':
            $whereClause .= " AND Staff_ID LIKE '%$searchValue%'";
            break;
        case 'contact':
            $whereClause .= " AND Staff_ContactNumber LIKE '%$searchValue%'";
            break;
        case 'name':
        default:
            $whereClause .= " AND Staff_FullName LIKE '%$searchValue%'";
            break;
    }
}

$sql = "SELECT * FROM staff $whereClause ORDER BY Staff_ID DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocked Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <link rel="stylesheet" href="admin_blocked.css">
</head>
<body>
    <?php if (isset($_GET['success'])): ?><div class="floating-alert" style="border-left:4px solid #28a745;color:#28a745"><i class="fas fa-check-circle"></i> <?php echo $_GET['success']; ?></div><?php endif; ?>
    
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content">
            <div class="blocked-table-container">
                <div class="table-header">
                    <h2><i class="fas fa-ban"></i> Blocked Staff</h2>
                </div>
                
                <form class="search-filter-container" method="GET">
                    <select name="search_type" class="filter-select">
                        <option value="name" <?php if($searchType == 'name') echo 'selected'; ?>>Filter by Name</option>
                        <option value="email" <?php if($searchType == 'email') echo 'selected'; ?>>Filter by Email</option>
                        <option value="id" <?php if($searchType == 'id') echo 'selected'; ?>>Filter by ID</option>
                        <option value="contact" <?php if($searchType == 'contact') echo 'selected'; ?>>Filter by Contact</option>
                    </select>

                    <input type="text" name="search_value" class="search-input" placeholder="Enter keyword here..." value="<?php echo htmlspecialchars($searchValue); ?>">

                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($searchValue)): ?>
                        <a href="admin_blocked_staff.php" class="btn btn-danger" style="padding: 0 15px;"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Staff Member</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight: bold; color: #666;">#<?php echo $row['Staff_ID']; ?></td>
                            <td>
                                <div class="user-info">
                                    <div class="avatar-circle">
                                        <?php if(!empty($row['Staff_ProfilePicture'])): ?><img src="<?php echo $row['Staff_ProfilePicture']; ?>"><?php else: ?><i class="fas fa-user-tie"></i><?php endif; ?>
                                    </div>
                                    <div class="user-details">
                                        <strong><?php echo htmlspecialchars($row['Staff_FullName']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['Staff_Email']); ?></td>
                            <td><?php echo htmlspecialchars($row['Staff_ContactNumber']); ?></td>
                            <td><?php echo htmlspecialchars($row['Staff_Role']); ?></td>
                            <td class="action-cell">
                                <div class="action-menu">
                                    <button class="menu-btn" onclick="toggleMenu(event, <?php echo $row['Staff_ID']; ?>)"><i class="fas fa-ellipsis-v"></i></button>
                                    <div id="menu-<?php echo $row['Staff_ID']; ?>" class="dropdown-content">
                                        <a href="?restore_id=<?php echo $row['Staff_ID']; ?>" class="text-restore" onclick="return confirm('Restore this staff member?')"><i class="fas fa-undo"></i> Restore</a>
                                        <a href="?delete_id=<?php echo $row['Staff_ID']; ?>" class="text-delete" onclick="return confirm('Delete permanently? This cannot be undone.')"><i class="fas fa-trash"></i> Delete Forever</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6" class="empty-state">No blocked staff found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        function toggleMenu(e, id) { e.stopPropagation(); document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); document.getElementById('menu-' + id).style.display = 'block'; }
        window.onclick = function() { document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none'); }
        setTimeout(() => { const a = document.querySelector('.floating-alert'); if(a) a.style.display='none'; }, 3000);
    </script>
</body>
</html>