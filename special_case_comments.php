<?php
// special_case_comments.php
session_start();

// Check login
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

if (!isset($_GET['case_id'])) {
    // If no case_id, redirect to management page
    header("Location: special_case_management.php");
    exit();
}

$caseId = intval($_GET['case_id']);

// Handle Delete Action
if (isset($_GET['delete_comment_id'])) {
    $commentId = intval($_GET['delete_comment_id']);
    $conn->query("DELETE FROM case_comments WHERE Comment_ID = $commentId");
    header("Location: special_case_comments.php?case_id=$caseId&success=" . urlencode("Comment deleted successfully"));
    exit();
}

// Get Case Details for Header
$caseRes = $conn->query("SELECT Case_Title FROM special_case WHERE Case_ID = $caseId");
$caseTitle = ($caseRes->num_rows > 0) ? $caseRes->fetch_assoc()['Case_Title'] : "Unknown Case";

// Fetch Comments with Donor Info
$sql = "SELECT c.Comment_ID, c.Comment_Text, c.Created_At, d.Donor_Name, d.Donor_Email 
        FROM case_comments c 
        JOIN donor d ON c.Donor_ID = d.Donor_ID 
        WHERE c.Case_ID = $caseId 
        ORDER BY c.Created_At DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Comments - <?php echo htmlspecialchars($caseTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; cursor: pointer; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 22px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #666; font-size: 13px; }

        .comments-container { max-width: 900px; margin: 0 auto; }
        .comment-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 15px; border: 1px solid #eee; display: flex; flex-direction: column; gap: 10px; transition: transform 0.2s; }
        .comment-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        
        .comment-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f5f5f5; padding-bottom: 10px; }
        .donor-info { display: flex; align-items: center; gap: 10px; }
        .donor-avatar { width: 40px; height: 40px; background: #e0f2f1; color: #00695c; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px; }
        .donor-details h4 { margin: 0; font-size: 15px; color: #333; }
        .donor-details span { font-size: 12px; color: #888; }
        .comment-date { font-size: 12px; color: #999; }
        
        .comment-body { font-size: 14px; color: #444; line-height: 1.5; padding: 5px 0; }
        
        .comment-actions { display: flex; justify-content: flex-end; padding-top: 10px; }
        .btn-delete { background: #fff0f0; color: #dc3545; border: 1px solid #f5c6cb; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; transition: 0.2s; }
        .btn-delete:hover { background: #dc3545; color: white; border-color: #dc3545; }

        .empty-state { text-align: center; padding: 50px 20px; color: #999; background: white; border-radius: 10px; border: 1px dashed #ddd; }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content">
            <div class="page-header-compact">
                <a href="special_case_management.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Special Case </a>
                <div class="header-title">
                    <h1>Case Comments</h1>
                    <p><?php echo htmlspecialchars($caseTitle); ?></p>
                </div>
            </div>

            <div class="comments-container">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $initial = strtoupper(substr($row['Donor_Name'], 0, 1));
                    ?>
                    <div class="comment-card">
                        <div class="comment-header">
                            <div class="donor-info">
                                <div class="donor-avatar"><?php echo $initial; ?></div>
                                <div class="donor-details">
                                    <h4><?php echo htmlspecialchars($row['Donor_Name']); ?></h4>
                                    <span><?php echo htmlspecialchars($row['Donor_Email']); ?></span>
                                </div>
                            </div>
                            <span class="comment-date"><i class="far fa-clock"></i> <?php echo date('d M Y, h:i A', strtotime($row['Created_At'])); ?></span>
                        </div>
                        <div class="comment-body">
                            <?php echo nl2br(htmlspecialchars($row['Comment_Text'])); ?>
                        </div>
                        <div class="comment-actions">
                            <a href="javascript:confirmDelete(<?php echo $row['Comment_ID']; ?>, <?php echo $caseId; ?>)" class="btn-delete">
                                <i class="fas fa-trash-alt"></i> Delete Comment
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-comment-dots" style="font-size: 40px; margin-bottom: 10px;"></i>
                        <p>No comments found for this case yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>

    <script>
        function confirmDelete(commentId, caseId) {
            if(confirm("Are you sure you want to delete this comment? This action cannot be undone.")) {
                window.location.href = `special_case_comments.php?delete_comment_id=${commentId}&case_id=${caseId}`;
            }
        }
    </script>
</body>
</html>