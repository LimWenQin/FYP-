<?php
// admin_audit_details.php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    die("Access Denied. Please login.");
}

include 'dataconnection.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Log ID.");
}

$logId = intval($_GET['id']);

// Fetch Log Details along with User info and Item info
$sql = "SELECT l.*, 
        a.Admin_Name, a.Admin_Role, 
        s.Staff_FullName, s.Staff_Role,
        r.Reward_ItemName, r.Reward_Code, r.Reward_PhotoPath
        FROM reward_logs l 
        LEFT JOIN admin a ON l.Admin_ID = a.Admin_ID 
        LEFT JOIN staff s ON l.Admin_ID = s.Staff_ID
        LEFT JOIN reward_item r ON l.Reward_ID = r.Reward_ID
        WHERE l.Log_ID = $logId";

$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    die("Log entry not found.");
}

$log = $result->fetch_assoc();

// Determine User Name & Role
$userName = !empty($log['Admin_Name']) ? $log['Admin_Name'] : (!empty($log['Staff_FullName']) ? $log['Staff_FullName'] : 'Unknown User');
$userRole = !empty($log['Admin_Role']) ? $log['Admin_Role'] : (!empty($log['Staff_Role']) ? $log['Staff_Role'] : 'System');

// Status Color Logic for the Action Box
$actionClass = 'text-secondary';
$actionText = strtoupper($log['Action_Type']);
$actionColor = '#6c757d'; // Default gray

if(stripos($actionText, 'CREATE') !== false) {
    $actionClass = 'text-primary';
    $actionColor = '#007bff';
}
elseif(stripos($actionText, 'UPDATE') !== false || stripos($actionText, 'STOCK') !== false) {
    $actionClass = 'text-success';
    $actionColor = '#28a745';
}
elseif(stripos($actionText, 'DELETE') !== false) {
    $actionClass = 'text-danger';
    $actionColor = '#dc3545';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Details - #<?php echo $logId; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        body { background: #f4f6f9; padding: 40px; font-family: 'Poppins', sans-serif; }
        
        .details-container { 
            max-width: 1100px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            overflow: hidden; 
        }

        .details-header { background: #F28585; color: white; padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .details-header h2 { margin: 0; font-size: 24px; }
        
        .details-body { padding: 40px; }
        
        .info-group { margin-bottom: 35px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .info-group:last-child { border-bottom: none; margin-bottom: 0; }
        
        .info-group h3 { font-size: 18px; color: #555; margin-bottom: 20px; border-left: 5px solid #F28585; padding-left: 15px; }
        
        .info-list { display: flex; flex-direction: column; gap: 0; }
        .info-item { display: flex; align-items: center; border-bottom: 1px dashed #f0f0f0; padding: 12px 0; }
        
        .label { width: 220px; min-width: 220px; color: #888; font-weight: 500; font-size: 15px; }
        .colon { width: 20px; text-align: center; color: #888; font-weight: 500; margin-right: 15px; }
        .value { font-weight: 600; color: #333; font-size: 16px; flex-grow: 1; }
        
        .action-display { font-size: 28px; color: <?php echo $actionColor; ?>; font-weight: bold; text-align: center; margin: 10px 0 40px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #eee; }
        
        .details-text { background: #fff; padding: 10px; border-radius: 5px; font-family: inherit; white-space: pre-wrap; line-height: 1.6; color: #444; }

        .action-buttons { margin-top: 30px; display: flex; justify-content: space-between; align-items: center; }

        .btn-print { background: #F28585; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s; }
        .btn-print:hover { background: #e07070; }

        .btn-back { background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background 0.3s; text-decoration: none; }
        .btn-back:hover { background: #5a6268; }
        
        .thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd; vertical-align: middle; margin-right: 10px; cursor: pointer; transition: transform 0.2s; }
        .thumb:hover { transform: scale(1.05); }

        /* Lightbox */
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; box-shadow: 0 0 20px rgba(255,255,255,0.1); object-fit: contain; animation: zoomIn 0.3s; }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; z-index: 2002; }
        .close-lightbox:hover { color: #bbb; }
        @keyframes zoomIn { from {transform:scale(0.8); opacity:0;} to {transform:scale(1); opacity:1;} }

        @media print {
            body { padding: 0; background: white; }
            .details-container { box-shadow: none; max-width: 100%; margin: 0; border-radius: 0; }
            .details-header { padding: 15px 20px; -webkit-print-color-adjust: exact; }
            .details-body { padding: 20px; }
            .btn-print, .btn-back { display: none; }
            .action-display { border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div id="imageLightbox" class="lightbox-modal">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <img class="lightbox-content" id="lightboxImage">
    </div>

    <div class="details-container">
        <div class="details-header">
            <h2>Audit Log Details</h2>
            <span>Log ID: #<?php echo $log['Log_ID']; ?></span>
        </div>
        <div class="details-body">
            <div class="action-display">
                <?php echo $actionText; ?>
            </div>

            <div class="info-group">
                <h3>User Information</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="label">User Name</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($userName); ?></span>
                    </div>

                    <div class="info-item">
                        <span class="label">Role</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo htmlspecialchars($userRole); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Timestamp</span>
                        <span class="colon">:</span>
                        <span class="value"><?php echo $log['Log_Created_At']; ?></span>
                    </div>
                </div>
            </div>

            <div class="info-group">
                <h3>Target Item Details</h3>
                <div class="info-list">
                    <?php if($log['Reward_ItemName']): ?>
                        <div class="info-item">
                            <span class="label">Item Name</span>
                            <span class="colon">:</span>
                            <span class="value">
                                <?php if($log['Reward_PhotoPath']): ?>
                                    <img src="uploads/rewards/<?php echo $log['Reward_PhotoPath']; ?>" class="thumb" onclick="openLightbox(this.src)">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($log['Reward_ItemName']); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="label">Item Code</span>
                            <span class="colon">:</span>
                            <span class="value"><?php echo htmlspecialchars($log['Reward_Code']); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="info-item">
                            <span class="label">Status</span>
                            <span class="colon">:</span>
                            <span class="value" style="color:#999; font-style:italic;">Item may have been deleted permanently.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-group">
                <h3>Action Details</h3>
                <div class="info-list">
                    <div class="info-item" style="border-bottom: none;">
                        <span class="label" style="align-self: flex-start; padding-top: 5px;">Description</span>
                        <span class="colon" style="align-self: flex-start; padding-top: 5px;">:</span>
                        <span class="value details-text"><?php echo htmlspecialchars($log['Action_Details']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn-back" onclick="window.close();">
                    <i class="fas fa-arrow-left"></i> Back / Close
                </button>
                
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Log
                </button>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('.btn-back').onclick = function() {
            if (window.history.length > 1 && document.referrer.indexOf(window.location.host) !== -1) {
                window.history.back();
            } else {
                window.close();
            }
        };

        function openLightbox(src) {
            document.getElementById('lightboxImage').src = src;
            document.getElementById('imageLightbox').style.display = 'flex';
        }
        function closeLightbox() {
            document.getElementById('imageLightbox').style.display = 'none';
        }
    </script>
</body>
</html>