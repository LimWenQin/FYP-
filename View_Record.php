<?php
session_start();
include 'dataconnection.php';

// 1. Check Login Status
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

// 2. Check Required Parameters
if (!isset($_GET['id'])) {
    echo "<script>alert('Invalid request.'); window.location.href='Track_Records.php';</script>";
    exit();
}

$record_id = $_GET['id'];
$current_donor_id = $_SESSION['donor_id'];
$details = [];
$view_beneficiary_url = ""; // To store the dynamic link

// 3. Query Donation Details
// Added Case_ID, Activity_ID, and Branch_ID to determine the link
$sql = "SELECT o.*, 
               sc.Case_Title, sc.Case_ID as sc_id,
               act.Activity_Name, act.Activity_ID as act_id,
               b.Branch_Name, b.Branch_ID as br_id
        FROM orders o
        LEFT JOIN special_case sc ON o.Case_ID = sc.Case_ID
        LEFT JOIN activity act ON o.Activity_ID = act.Activity_ID
        LEFT JOIN branch b ON o.Branch_ID = b.Branch_ID
        WHERE o.Order_ID = ? AND o.Donor_ID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $record_id, $current_donor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Determine Beneficiary and Generate Detail Link
    $project_name = "General Fund (HQ)";
    
    if (!empty($row['Case_Title'])) {
        $project_name = "Special Case: " . $row['Case_Title'];
        $view_beneficiary_url = "Special_Case_Details.php?id=" . $row['sc_id'];
    } elseif (!empty($row['Activity_Name'])) {
        $project_name = "Activity: " . $row['Activity_Name'];
        $view_beneficiary_url = "Activity_Details.php?id=" . $row['act_id'];
    } elseif (!empty($row['Branch_Name'])) {
        $project_name = "Branch: " . $row['Branch_Name'];
        $view_beneficiary_url = "Branch_Details.php?id=" . $row['br_id'];
    }

    // Format Donation Type
    $db_type = strtolower(trim($row['Order_Type']));
    $display_type = ($db_type === 'recurring') ? 'Monthly Giving' : 'One-Time Donation';

    $details = [
        'Beneficiary' => $project_name,
        'Amount' => "RM " . number_format($row['Order_Amount'], 2),
        'Transaction Ref' => $row['Order_TXN_Ref'],
        'Date & Time' => date("d M Y, h:i A", strtotime($row['Order_Created_At'])),
        'Payment Method' => $row['Order_PaymentMethod'] ?: 'Manual/System Wallet',
        'Donation Type' => $display_type, 
        'Donor Name' => $row['Order_Name'],
        'Contact Info' => $row['Order_Email'] . ' (' . $row['Order_ContactNumber'] . ')',
        'Tax Receipt Status' => $row['Tax_Receipt_Status'],
        'Transaction Status' => $row['Order_Status']
    ];
}

// 4. Handle Record Not Found
if (empty($details)) {
    echo "<script>alert('Record not found.'); window.location.href='Track_Records.php';</script>";
    exit();
}

include 'header_UI.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .view-container { max-width: 700px; margin: 50px auto; padding: 20px; }
    .detail-card { 
        background: white; border-radius: 15px; padding: 40px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
        border-top: 5px solid #e53935; 
        position: relative;
    }
    .detail-header { border-bottom: 2px dashed #eee; padding-bottom: 20px; margin-bottom: 25px; text-align: center; }
    .detail-header h2 { margin: 0 0 10px 0; color: #333; font-weight: 800; font-size: 1.8rem; }
    .status-tag { display: inline-block; padding: 6px 18px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; margin-top: 10px; }
    .status-success { background: #e8f5e9; color: #2e7d32; }
    .status-failed { background: #ffe2e2; color: #991b1b; }
    .status-pending { background: #fff8e1; color: #f57c00; }
    
    .info-row { display: flex; margin-bottom: 18px; align-items: flex-start; }
    .info-label { width: 180px; font-weight: 600; color: #666; flex-shrink: 0; font-size: 0.95rem; }
    .info-value { flex: 1; color: #222; font-weight: 500; font-size: 1rem; line-height: 1.5; }
    
    /* Beneficiary Detail Button */
    .btn-beneficiary {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 8px;
        padding: 6px 14px;
        background: #fff;
        color: #e53935;
        border: 1.5px solid #e53935;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 700;
        text-decoration: none !important;
        transition: 0.3s;
    }
    .btn-beneficiary:hover { background: #e53935; color: #fff; transform: translateY(-2px); }

    .amount-highlight { color: #e53935; font-weight: 800; font-size: 1.3rem; }
    .btn-container { margin-top: 30px; text-align: center; }
    .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 12px 35px; background: #f3f4f6; color: #374151; border-radius: 30px; text-decoration: none; font-weight: bold; transition: 0.2s; border: 1px solid #ddd;}
    .btn-back:hover { background: #ddd; transform: translateY(-2px); text-decoration: none; }

    @media (max-width: 600px) { .info-row { flex-direction: column; } .info-label { width: 100%; margin-bottom: 5px; } }
</style>

<div class="view-container">
    <div class="detail-card">
        <div class="detail-header">
            <h2>Donation Details</h2>
            <div class="detail-subtitle">Reference: <?php echo htmlspecialchars($details['Transaction Ref']); ?></div>
            
            <?php 
                $status = strtolower($details['Transaction Status']);
                $status_class = 'status-pending';
                if (strpos($status, 'success') !== false || strpos($status, 'completed') !== false) $status_class = 'status-success';
                elseif (strpos($status, 'fail') !== false || strpos($status, 'reject') !== false) $status_class = 'status-failed';
            ?>
            <span class="status-tag <?php echo $status_class; ?>">
                <?php echo htmlspecialchars($details['Transaction Status']); ?>
            </span>
        </div>

        <?php foreach ($details as $label => $value): ?>
            <?php if ($label == 'Transaction Status' || $label == 'Transaction Ref') continue; ?>
            
            <div class="info-row">
                <div class="info-label"><?php echo $label; ?></div>
                <div class="info-value">
                    <?php if($label == 'Amount'): ?>
                        <span class="amount-highlight"><?php echo $value; ?></span>
                    
                    <?php elseif($label == 'Beneficiary'): ?>
                        <div style="font-weight: 700;"><?php echo htmlspecialchars($value); ?></div>
                        <?php if (!empty($view_beneficiary_url)): ?>
                            <a href="<?php echo $view_beneficiary_url; ?>" class="btn-beneficiary">
                                <i class="fas fa-external-link-alt"></i> View Info
                            </a>
                        <?php endif; ?>

                    <?php elseif($label == 'Tax Receipt Status'): ?>
                        <?php if($value == 'Generated'): ?>
                            <span style="color:#2e7d32; font-weight:bold;"><i class="fas fa-check-circle"></i> Sent via Email</span>
                        <?php elseif($value == 'Requested'): ?>
                            <span style="color:#f57c00; font-weight:bold;"><i class="fas fa-clock"></i> Processing</span>
                        <?php else: ?>
                            <span style="color:#9e9e9e;"><?php echo htmlspecialchars($value); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php echo htmlspecialchars($value); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="btn-container">
            <a href="Track_Records.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Track Records
            </a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>