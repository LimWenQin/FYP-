<?php
// admin_redemption_details.php
session_start();

// --- 引入 PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// --- 检查登录 ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// 检查 ID 是否提供
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: redemption_order_management.php");
    exit();
}

$orderId = intval($_GET['id']);

// 获取来源参数 (from_item ID), 决定返回按钮去哪里
$fromItemId = isset($_GET['from_item']) ? intval($_GET['from_item']) : 0;

// 获取订单详情
$sql = "SELECT r.*, d.Donor_Name, d.Donor_Email, rw.Reward_ItemName, rw.Reward_PhotoPath
        FROM redemption_order r 
        JOIN donor d ON r.Donor_ID = d.Donor_ID
        JOIN reward_item rw ON r.Reward_ID = rw.Reward_ID
        WHERE r.Redemption_ID = $orderId";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    header("Location: redemption_order_management.php?error=" . urlencode("Order not found."));
    exit();
}
$order = $result->fetch_assoc();
$currentStatus = trim($order['Redemption_Status']);
if ($currentStatus == 'Processing') $currentStatus = 'Pending';

// --- EMAIL FUNCTION ---
function sendStatusEmail($to, $name, $status, $itemName, $tracking = null, $estDate = null, $cancelReason = null) {
    $subject = "Update on your Redemption Order - Love Bridge";
    $bodyContent = "<div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>";
    $bodyContent .= "<div style='background-color: white; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto;'>";
    $bodyContent .= "<h2 style='color: #F28585;'>Redemption Status Update</h2>";
    $bodyContent .= "<p>Dear <strong>$name</strong>,</p>";

    if ($status == 'Shipped') {
        $bodyContent .= "<p>Great news! Your redemption for <strong>'$itemName'</strong> has been approved and shipped.</p>";
        if ($tracking) {
            $bodyContent .= "<p style='font-size:16px; font-weight:bold; color:#333;'>Tracking Number: $tracking</p>";
        }
        if ($estDate) {
            $formattedDate = date('d M Y', strtotime($estDate));
            $bodyContent .= "<p>Estimated Delivery Date: <strong>$formattedDate</strong></p>";
        }
        $bodyContent .= "<p>You should receive it soon!</p>";
    } elseif ($status == 'Cancelled') {
        $bodyContent .= "<p style='color:#dc3545;'>We regret to inform you that your redemption for <strong>'$itemName'</strong> has been cancelled/rejected.</p>";
        if ($cancelReason) {
            $bodyContent .= "<p><strong>Reason:</strong> " . htmlspecialchars($cancelReason) . "</p>";
        }
        $bodyContent .= "<p>Any points used have been fully refunded to your account.</p>";
    } else {
        $bodyContent .= "<p>The status of your redemption order for <strong>'$itemName'</strong> has been updated to: <strong>$status</strong>.</p>";
    }

    $bodyContent .= "<br><p>Thank you for your support,<br>Love Bridge Team</p>";
    $bodyContent .= "</div></div>";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lovebridge1201@gmail.com'; 
        $mail->Password   = 'odaj iwrz gfrt vven';      
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('lovebridge1201@gmail.com', 'Love Bridge Admin');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyContent;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ACTION 1: SAVE CHANGES (Edit Mode -> View Mode)
    if (isset($_POST['save_info'])) {
        
        // --- 修复电话号码逻辑 ---
        $contactRaw = preg_replace('/[^0-9]/', '', $_POST['contact']);
        if (substr($contactRaw, 0, 2) === '60') {
            $contactRaw = substr($contactRaw, 2);
        } elseif (substr($contactRaw, 0, 1) === '0') {
            $contactRaw = substr($contactRaw, 1);
        }
        $contact = "+60" . $contactRaw;

        $address1 = $conn->real_escape_string(trim($_POST['address1']));
        $address2 = $conn->real_escape_string(trim($_POST['address2']));
        $address3 = $conn->real_escape_string(trim($_POST['address3']));
        $city = $conn->real_escape_string(trim($_POST['city']));
        $postal = $conn->real_escape_string(trim($_POST['postal_code']));
        $state = $conn->real_escape_string(trim($_POST['state']));
        
        // Tracking & Reason only update if they exist in POST (hidden inputs might be empty)
        $tracking = isset($_POST['tracking_number']) ? $conn->real_escape_string($_POST['tracking_number']) : null;
        $cancelReason = isset($_POST['cancel_reason']) ? $conn->real_escape_string($_POST['cancel_reason']) : null;

        $updateSql = "UPDATE redemption_order SET 
                      Redemption_ContactNumber = '$contact',
                      Redemption_Address1 = '$address1',
                      Redemption_Address2 = '$address2',
                      Redemption_Address3 = '$address3',
                      Redemption_City = '$city',
                      Redemption_PostalCode = '$postal',
                      Redemption_State = '$state',
                      Redemption_TrackingNumber = '$tracking',
                      Redemption_CancelReason = '$cancelReason',
                      Redemption_Updated_At = NOW()
                      WHERE Redemption_ID = $orderId";
        
        if ($conn->query($updateSql)) {
            // Keep the 'from_item' param in URL after post
            $redirectUrl = "admin_redemption_details.php?id=$orderId";
            if($fromItemId) $redirectUrl .= "&from_item=$fromItemId";
            $redirectUrl .= "&success=" . urlencode("Details updated successfully.");
            
            header("Location: $redirectUrl");
            exit();
        } else {
            $errorMsg = "Database Error: " . $conn->error;
        }
    }

    // ACTION 2: UPDATE STATUS (Approve/Reject)
    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['new_status']; // 'Shipped' or 'Cancelled'
        $estDays = isset($_POST['est_days']) ? intval($_POST['est_days']) : 3;
        
        // Get values from hidden inputs (populated by JS popup)
        $tracking = isset($_POST['tracking_number']) ? $conn->real_escape_string($_POST['tracking_number']) : '';
        $cancelReason = isset($_POST['cancel_reason']) ? $conn->real_escape_string($_POST['cancel_reason']) : '';

        // Validation
        if ($newStatus == 'Shipped' && empty($tracking)) {
            $errorMsg = "Cannot approve: Tracking Number is missing.";
        } elseif ($newStatus == 'Cancelled' && empty($cancelReason)) {
            $errorMsg = "Cannot reject: Cancellation Reason is missing.";
        } else {
            // Processing
            $extraUpdate = "";
            $estDeliveryDate = null;

            if ($newStatus == 'Shipped') {
                $estDeliveryDate = date('Y-m-d', strtotime("+$estDays days"));
                $extraUpdate = ", Redemption_Shipped_At = NOW(), Redemption_Est_Delivery_Date = '$estDeliveryDate', Redemption_FollowUp_Sent = 0, Redemption_TrackingNumber = '$tracking'";
            }
            
            if ($newStatus == 'Cancelled') {
                // Refund Points Logic
                $checkSql = "SELECT * FROM redemption_order WHERE Redemption_ID = $orderId";
                $checkData = $conn->query($checkSql)->fetch_assoc();
                $refundPoints = $checkData['Redemption_PointsSpent'];
                $refundQty = $checkData['Redemption_Quantity'];
                $donorId = $checkData['Donor_ID'];
                $rewardId = $checkData['Reward_ID'];
                
                $conn->query("UPDATE point SET Points_Total = Points_Total + $refundPoints, Points_Updated_At = NOW() WHERE Donor_ID = $donorId");
                $conn->query("UPDATE reward_item SET Reward_Stock = Reward_Stock + $refundQty WHERE Reward_ID = $rewardId");
                
                $extraUpdate = ", Redemption_CancelReason = '$cancelReason'";
            }

            $finalSql = "UPDATE redemption_order SET 
                         Redemption_Status = '$newStatus', 
                         Redemption_Updated_At = NOW() 
                         $extraUpdate 
                         WHERE Redemption_ID = $orderId";
            
            if ($conn->query($finalSql)) {
                sendStatusEmail($order['Donor_Email'], $order['Donor_Name'], $newStatus, $order['Reward_ItemName'], $tracking, $estDeliveryDate, $cancelReason);
                
                // Determine redirect based on from_item
                if ($fromItemId > 0) {
                    header("Location: view_history.php?id=$fromItemId&tab=redemptions");
                } else {
                    header("Location: redemption_order_management.php?success=" . urlencode("Order #$orderId has been " . strtolower($newStatus) . "."));
                }
                exit();
            } else {
                $errorMsg = "Database Error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Redemption #<?php echo $orderId; ?> - Love Bridge Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Compact Header Styles */
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        
        /* Container */
        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 900px; margin: 0 auto 30px; }
        .form-header { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; display:flex; justify-content:space-between; align-items:center; }
        .form-header h2 { font-size: 20px; color: #333; font-weight: 600; margin: 0; }

        /* Form Elements */
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; } 
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 20px; } 
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-textarea, .form-select { width: 100%; padding: 12px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; font-size: 14px; box-sizing: border-box; }
        
        /* Read Only State (View Mode) */
        .form-input:read-only, .form-textarea:read-only, .form-select[disabled] { background-color: #f8f9fa; color: #666; border-color: #eee; cursor: default; }

        /* Phone Input Styling */
        .phone-format { display: flex; align-items: center; } 
        .phone-prefix { padding: 12px 15px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); font-size: 14px; transition: 0.3s; } 
        .phone-input { border-radius: 0 5px 5px 0 !important; }
        
        /* Form Guide & Error */
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: none; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        .required-star { color: red; margin-left: 3px; font-weight: bold; display: none; }
        
        .input-error { border-color: #dc3545 !important; background-color: #fff5f5 !important; }
        .inline-error { color: #dc3545; font-size: 11px; margin-top: 4px; display: block; font-weight: 500; animation: fadeIn 0.3s; }

        /* Toggle Switch */
        .switch-container { display: flex; align-items: center; gap: 10px; }
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; margin: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: #F28585; }
        input:checked + .slider:before { transform: translateX(24px); }
        .toggle-label { font-size: 14px; font-weight: 600; color: #666; }

        /* Item Preview Section */
        .item-section { background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; display: flex; gap: 15px; align-items: center; margin-bottom: 25px; }
        .item-img { width: 70px; height: 70px; border-radius: 5px; object-fit: cover; border: 1px solid #ddd; cursor: pointer; }
        .item-info h4 { margin: 0 0 5px 0; font-size: 16px; color: #333; }
        .item-meta { font-size: 13px; color: #666; }
        .item-points { color: #dc3545; font-weight: bold; margin-top: 5px; font-size: 13px; }

        /* Logistics AI Panel */
        .ai-panel { background: #e3f2fd; border: 1px solid #bbdefb; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .ai-data { display: flex; gap: 20px; }
        .ai-metric span { font-size: 11px; color: #555; text-transform: uppercase; letter-spacing: 0.5px; }
        .ai-metric strong { display: block; font-size: 15px; color: #0d47a1; margin-top: 2px; }

        /* Bottom Buttons */
        .button-group { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .view-mode-btns { display: flex; gap: 15px; }
        .edit-mode-btns { display: none; justify-content: flex-end; }
        
        .btn-action { flex: 1; padding: 12px; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-approve { background: #28a745; } .btn-approve:hover { background: #218838; }
        .btn-reject { background: #dc3545; } .btn-reject:hover { background: #c82333; }
        .btn-save { background: #F28585; width: 100%; } .btn-save:hover { background: #e06c6c; }

        /* Lightbox */
        .lightbox-modal { display: none; position: fixed; z-index: 2000; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0, 0, 0, 0.9); flex-direction: column; justify-content: center; align-items: center; }
        .lightbox-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 5px; }
        .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer; }

        /* Alert */
        .custom-alert { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; color: white; font-weight: 500; display: none; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .alert-success { background: #28a745; }
        .alert-error { background: #dc3545; }
    </style>
</head>
<body>
    
    <div id="sysAlert" class="custom-alert"></div>

    <div id="imageLightbox" class="lightbox-modal" onclick="this.style.display='none'">
        <span class="close-lightbox">&times;</span>
        <img class="lightbox-content" id="lightboxImage">
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <?php 
                    $backLink = ($fromItemId > 0) ? "view_history.php?id=$fromItemId&tab=redemptions" : "redemption_order_management.php";
                ?>
                <a href="<?php echo $backLink; ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="header-title">
                    <h1>Manage Redemption #<?php echo $orderId; ?></h1>
                    <p style="text-align: center;">Status: 
                        <span style="font-weight:bold; color:<?php echo ($currentStatus=='Pending'?'#e0a800':($currentStatus=='Shipped'?'#28a745':'#dc3545')); ?>">
                            <?php echo $currentStatus; ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="form-container">
                
                <div class="form-header">
                    <h2>Order Details</h2>
                    <?php if ($currentStatus === 'Pending'): ?>
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" id="modeToggle" onchange="toggleEditMode(this.checked)">
                                <span class="slider round"></span>
                            </label>
                            <span class="toggle-label" id="toggleLabel">View Mode</span>
                        </div>
                    <?php else: ?>
                        <div style="font-size:12px; color:#666; font-style:italic;">Order is <?php echo $currentStatus; ?> (Read Only)</div>
                    <?php endif; ?>
                </div>

                <div class="item-section">
                    <?php $img = !empty($order['Reward_PhotoPath']) ? 'uploads/rewards/' . $order['Reward_PhotoPath'] : 'uploads/rewards/default.jpg'; ?>
                    <img src="<?php echo htmlspecialchars($img); ?>" class="item-img" onclick="openLightbox('<?php echo htmlspecialchars($img); ?>')">
                    <div class="item-info">
                        <h4><?php echo htmlspecialchars($order['Reward_ItemName']); ?></h4>
                        <div class="item-meta">Quantity: <strong><?php echo $order['Redemption_Quantity']; ?></strong></div>
                        <div class="item-meta">Donor: <?php echo htmlspecialchars($order['Donor_Name']); ?> (<?php echo htmlspecialchars($order['Donor_Email']); ?>)</div>
                        <div class="item-points">Total Points Used: <?php echo $order['Redemption_PointsSpent']; ?> pts</div>
                    </div>
                </div>

                <form id="detailsForm" method="POST" action="">
                    <input type="hidden" name="est_days" id="hiddenEstDays" value="3">
                    
                    <input type="hidden" name="cancel_reason" id="cancel_reason" value="<?php echo htmlspecialchars($order['Redemption_CancelReason']); ?>">
                    <input type="hidden" name="tracking_number" id="tracking_payload" value="<?php echo htmlspecialchars($order['Redemption_TrackingNumber']); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Contact Number <span class="required-star">*</span></label>
                        <div class="phone-format">
                            <span class="phone-prefix" id="phonePrefix">+60</span>
                            <input type="text" name="contact" id="contact" class="form-input phone-input" 
                                   value="<?php echo htmlspecialchars(str_replace('+60', '', $order['Redemption_ContactNumber'])); ?>" 
                                   readonly placeholder="12-3456789" maxlength="11">
                        </div>
                        <span class="form-guide">Format: 12-3456789 (Prefix 11-19, total 9-11 digits).</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1 <span class="required-star">*</span></label>
                        <input type="text" name="address1" id="address1" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_Address1']); ?>" readonly placeholder="e.g. No. 123, Jalan Example">
                        <span class="form-guide">House unit no., floor, building, street name.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2 <span class="required-star">*</span></label>
                        <input type="text" name="address2" id="address2" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_Address2']); ?>" readonly placeholder="e.g. Taman Sri">
                        <span class="form-guide">Residential area, village, or section.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" id="address3" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_Address3']); ?>" readonly placeholder="Address Line 3 (Optional)">
                        <span class="form-guide">Address Line 3 (Optional).</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City <span class="required-star">*</span></label>
                            <input type="text" name="city" id="city" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_City']); ?>" readonly placeholder="e.g. Kuala Lumpur">
                            <span class="form-guide">City name.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code <span class="required-star">*</span></label>
                            <input type="text" name="postal_code" id="postal_code" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_PostalCode']); ?>" readonly placeholder="e.g. 50000">
                            <span class="form-guide">5-digit postcode.</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">State <span class="required-star">*</span></label>
                        <input type="text" name="state" id="state" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_State']); ?>" readonly onchange="updateLogistics()" placeholder="Select State">
                        <span class="form-guide">Select state.</span>
                    </div>

                    <div class="ai-panel">
                        <div class="ai-data">
                            <div class="ai-metric"><span>Courier</span><strong id="aiCourier">J&T Express</strong></div>
                            <div class="ai-metric"><span>Est. Delivery Time</span><strong id="aiTime">...</strong></div>
                        </div>
                    </div>

                    <?php if (!empty($order['Redemption_TrackingNumber'])): ?>
                    <div class="form-group">
                        <label class="form-label">Tracking Number</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($order['Redemption_TrackingNumber']); ?>" readonly>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($order['Redemption_CancelReason'])): ?>
                    <div class="form-group">
                        <label class="form-label" style="color:#dc3545;">Cancellation Reason</label>
                        <textarea class="form-textarea" rows="2" readonly><?php echo htmlspecialchars($order['Redemption_CancelReason']); ?></textarea>
                    </div>
                    <?php endif; ?>

                    <div class="button-group">
                        <?php if ($currentStatus === 'Pending'): ?>
                            <div class="view-mode-btns" id="viewBtns">
                                <button type="button" class="btn-action btn-reject" onclick="confirmAction('Cancelled')">
                                    <i class="fas fa-times"></i> Reject & Refund
                                </button>
                                <button type="button" class="btn-action btn-approve" onclick="confirmAction('Shipped')">
                                    <i class="fas fa-check"></i> Approve & Ship
                                </button>
                            </div>

                            <div class="edit-mode-btns" id="editBtns">
                                <button type="button" name="save_info" class="btn-action btn-save" onclick="validateAndSave()">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            updateLogistics();
            <?php if(isset($_GET['success'])): ?>
                showAlert("<?php echo htmlspecialchars($_GET['success']); ?>", "success");
            <?php endif; ?>
            <?php if(isset($errorMsg)): ?>
                showAlert("<?php echo htmlspecialchars($errorMsg); ?>", "error");
            <?php endif; ?>
        });

        // --- PHONE INPUT LOGIC ---
        document.getElementById('contact').addEventListener('input', function(e) { 
            let val = this.value.replace(/\D/g, ''); 
            if (val.length > 11) val = val.substring(0, 11); 
            let newVal = ''; 
            if (val.length > 2) { 
                newVal += val.substring(0, 2) + '-' + val.substring(2); 
            } else { 
                newVal = val; 
            } 
            this.value = newVal; 
        });

        // --- POSTCODE LOGIC ---
        document.getElementById('postal_code').addEventListener('input', function() {
            if(document.getElementById('postal_code').readOnly) return; 
            
            const val = this.value.replace(/\D/g, '');
            if (val.length >= 2) {
                const prefix = parseInt(val.substring(0, 2));
                let state = "";
                if (prefix >= 1 && prefix <= 2) state = "Perlis"; else if (prefix >= 5 && prefix <= 9) state = "Kedah"; else if (prefix >= 10 && prefix <= 14) state = "Penang";
                else if (prefix >= 15 && prefix <= 18) state = "Kelantan"; else if (prefix >= 20 && prefix <= 24) state = "Terengganu"; else if (prefix >= 25 && prefix <= 28) state = "Pahang";
                else if (prefix >= 30 && prefix <= 39) state = "Perak"; else if (prefix >= 40 && prefix <= 48) state = "Selangor"; else if (prefix >= 50 && prefix <= 60) state = "Kuala Lumpur";
                else if (prefix === 62) state = "Putrajaya"; else if (prefix >= 63 && prefix <= 68) state = "Selangor"; else if (prefix >= 70 && prefix <= 73) state = "Negeri Sembilan";
                else if (prefix >= 75 && prefix <= 78) state = "Melaka"; else if (prefix >= 80 && prefix <= 86) state = "Johor"; else if (prefix === 87) state = "Labuan";
                else if (prefix >= 88 && prefix <= 91) state = "Sabah"; else if (prefix >= 93 && prefix <= 98) state = "Sarawak";
                
                if (state) {
                    document.getElementById('state').value = state;
                    updateLogistics();
                }
            }
        });

        function toggleEditMode(isEdit) {
            const label = document.getElementById('toggleLabel');
            label.textContent = isEdit ? 'Edit Mode' : 'View Mode';
            
            const ids = ['contact', 'address1', 'address2', 'address3', 'city', 'postal_code', 'state'];
            ids.forEach(id => {
                const el = document.getElementById(id);
                if(el) {
                    el.readOnly = !isEdit;
                    el.style.backgroundColor = isEdit ? 'white' : '#f8f9fa';
                    el.style.borderColor = isEdit ? '#F28585' : '#eee';
                }
            });

            const prefix = document.getElementById('phonePrefix');
            if(prefix) {
                prefix.style.borderColor = isEdit ? '#F28585' : '#eee';
            }

            // Show guides and stars
            const guides = document.querySelectorAll('.form-guide');
            const stars = document.querySelectorAll('.required-star');
            guides.forEach(g => g.style.display = isEdit ? 'block' : 'none');
            stars.forEach(s => s.style.display = isEdit ? 'inline' : 'none');

            // Toggle Buttons
            const viewBtns = document.getElementById('viewBtns');
            const editBtns = document.getElementById('editBtns');
            if (viewBtns && editBtns) {
                viewBtns.style.display = isEdit ? 'none' : 'flex';
                editBtns.style.display = isEdit ? 'flex' : 'none';
            }
            
            clearFormErrors();
        }

        // --- STRICT VALIDATION FUNCTIONS ---
        function validatePhoneDetailed(val) {
            if (!val.includes('-')) return "Missing hyphen symbol ( - ).";
            const parts = val.split('-'); 
            if (parts.length !== 2) return "Invalid format.";
            const front = parts[0]; const back = parts[1];
            if (front.length !== 2) return "Prefix must be 2 digits (e.g., 11-19).";
            if (!/^\d+$/.test(front)) return "Prefix must be numbers.";
            const prefixNum = parseInt(front, 10);
            if (prefixNum < 11 || prefixNum > 19) return "Prefix must be between 11 and 19.";
            if (back.length === 0) return "Enter number after hyphen.";
            if (back.length < 7) return "Number too short.";
            return ""; 
        }

        function showFieldError(inputId, message) {
            const input = document.getElementById(inputId); 
            if (!input) return;
            
            input.classList.add('input-error');
            
            let parent = input.parentNode;
            if (parent.classList.contains('phone-format')) parent = parent.parentNode; 
            
            let errorDiv = parent.querySelector('.inline-error');
            if (errorDiv) errorDiv.remove();

            errorDiv = document.createElement('div'); 
            errorDiv.className = 'inline-error'; 
            errorDiv.textContent = message;
            parent.appendChild(errorDiv);
        }

        function clearFormErrors() {
            const inputs = document.querySelectorAll('.form-input, .form-textarea');
            inputs.forEach(i => i.classList.remove('input-error'));
            document.querySelectorAll('.inline-error').forEach(e => e.remove());
        }

        function validateAndSave() {
            clearFormErrors();
            let hasError = false;
            let firstErrorMsg = "";

            const requiredFields = [
                { id: 'contact', name: 'Contact' },
                { id: 'address1', name: 'Address Line 1' },
                { id: 'address2', name: 'Address Line 2' },
                { id: 'city', name: 'City' },
                { id: 'postal_code', name: 'Postal Code' },
                { id: 'state', name: 'State' }
            ];

            requiredFields.forEach(field => {
                const el = document.getElementById(field.id);
                if (!el.value || el.value.trim() === "") {
                    showFieldError(field.id, field.name + " is required.");
                    hasError = true;
                    if (!firstErrorMsg) firstErrorMsg = field.name + " is required.";
                }
            });

            const contactVal = document.getElementById('contact').value.trim();
            if (contactVal) {
                let phoneMsg = validatePhoneDetailed(contactVal);
                if (phoneMsg) { 
                    showFieldError('contact', phoneMsg); 
                    hasError = true; 
                    if(!firstErrorMsg) firstErrorMsg = phoneMsg; 
                }
            }

            if (hasError) {
                showAlert(firstErrorMsg, 'error');
                return;
            }

            const form = document.getElementById('detailsForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'save_info';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        }

        function confirmAction(status) {
            if (status === 'Cancelled') {
                Swal.fire({
                    title: 'Reject & Refund?',
                    text: "Please provide a reason. Points will be refunded.",
                    icon: 'warning',
                    input: 'textarea',
                    inputLabel: 'Cancellation Reason',
                    inputPlaceholder: 'Enter reason here...',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Yes, Reject',
                    inputValidator: (value) => {
                        if (!value) {
                            return 'You need to write a reason!'
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('cancel_reason').value = result.value;
                        submitStatusUpdate(status);
                    }
                });
            } else if (status === 'Shipped') {
                Swal.fire({
                    title: 'Approve & Ship?',
                    text: 'Please enter the Tracking Number to proceed.',
                    icon: 'info',
                    input: 'text',
                    inputLabel: 'Tracking Number',
                    inputPlaceholder: 'e.g. JNT-12345678',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    confirmButtonText: 'Approve & Ship',
                    inputValidator: (value) => {
                        if (!value) {
                            return 'Tracking Number is required to approve!'
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Set value to hidden payload
                        document.getElementById('tracking_payload').value = result.value;
                        submitStatusUpdate(status);
                    }
                });
            }
        }

        function submitStatusUpdate(status) {
            const form = document.getElementById('detailsForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'update_status';
            input.value = '1';
            form.appendChild(input);

            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'new_status';
            statusInput.value = status;
            form.appendChild(statusInput);

            form.submit();
        }

        function updateLogistics() {
            const state = document.getElementById('state').value;
            let time = "2-3 Days";
            let days = 3;
            
            const eastMalaysia = ['Sabah', 'Sarawak', 'Labuan'];
            let isEast = false;
            eastMalaysia.forEach(s => { if(state.includes(s)) isEast = true; });

            if (isEast) {
                time = "5-6 Days";
                days = 6;
            }

            document.getElementById('aiTime').innerText = time;
            document.getElementById('hiddenEstDays').value = days;
        }

        function openLightbox(src) {
            document.getElementById('lightboxImage').src = src;
            document.getElementById('imageLightbox').style.display = 'flex';
        }

        function showAlert(msg, type) {
            const el = document.getElementById('sysAlert');
            el.innerText = msg;
            el.className = 'custom-alert alert-' + type;
            el.style.display = 'block';
            setTimeout(() => { el.style.display = 'none'; }, 4000);
        }
    </script>
</body>
</html>