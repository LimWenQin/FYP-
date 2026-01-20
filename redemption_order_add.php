<?php
// redemption_order_add.php
session_start();

// --- 1. Check Login ---
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: admin_login.php");
    exit();
}

include 'dataconnection.php';

// --- 2. Fetch Data for Modals ---
// Donors
$donorList = [];
$dq = $conn->query("SELECT d.*, IFNULL(p.Points_Total, 0) as Points_Total 
                    FROM donor d 
                    LEFT JOIN point p ON d.Donor_ID = p.Donor_ID 
                    WHERE d.Is_Deleted = 0 
                    ORDER BY d.Donor_Name ASC");
if($dq) { while($d = $dq->fetch_assoc()) { $donorList[] = $d; } }

// Rewards
$rewardList = [];
$rq = $conn->query("SELECT Reward_ID, Reward_ItemName, Reward_Code, Reward_RequiredPoint, Reward_Stock 
                    FROM reward_item 
                    WHERE Reward_Status = 'Active' AND Reward_Stock > 0 
                    ORDER BY Reward_ItemName ASC");
if($rq) { while($r = $rq->fetch_assoc()) { $rewardList[] = $r; } }

// States
$malaysiaStates = [
    'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 'Negeri Sembilan', 
    'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'
];

$errorMessage = "";
$saveSuccess = false;

// --- 3. Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_order'])) {
    
    // Start Transaction
    $conn->begin_transaction();

    try {
        // Basic Fields
        $donorId = intval($_POST['donor_id']);
        $rewardId = intval($_POST['reward_id']);
        $quantity = intval($_POST['quantity']);
        if ($quantity < 1) $quantity = 1; 
        
        $address1 = $conn->real_escape_string(trim($_POST['address1']));
        $address2 = $conn->real_escape_string(trim($_POST['address2'])); 
        $address3 = $conn->real_escape_string(trim($_POST['address3'])); 
        $city = $conn->real_escape_string(trim($_POST['city']));
        $state = $conn->real_escape_string(trim($_POST['state']));
        $postal = $conn->real_escape_string(trim($_POST['postal_code']));
        
        // --- 修复电话号码逻辑 (PHP端) ---
        $contactRaw = preg_replace('/[^0-9]/', '', $_POST['contact']);
        
        if (substr($contactRaw, 0, 2) === '60') {
            $contactRaw = substr($contactRaw, 2);
        }
        elseif (substr($contactRaw, 0, 1) === '0') {
            $contactRaw = substr($contactRaw, 1);
        }
        
        $contact = "+60" . $contactRaw;

        // --- Logic Checks ---
        // Lock Rows for Concurrency Safety
        $rwQ = $conn->query("SELECT Reward_RequiredPoint, Reward_Stock FROM reward_item WHERE Reward_ID = $rewardId FOR UPDATE");
        if ($rwQ->num_rows == 0) throw new Exception("Selected Reward Item not found.");
        $rwRow = $rwQ->fetch_assoc();
        
        $unitPoints = $rwRow['Reward_RequiredPoint'];
        $currentStock = $rwRow['Reward_Stock'];
        $totalPointsNeeded = $unitPoints * $quantity;

        $ptQ = $conn->query("SELECT Points_Total FROM point WHERE Donor_ID = $donorId FOR UPDATE");
        $ptRow = $ptQ->fetch_assoc();
        $donorHasPoints = $ptRow ? $ptRow['Points_Total'] : 0;
        
        if ($currentStock < $quantity) {
            throw new Exception("Stock insufficient (Available: $currentStock).");
        } 
        if ($donorHasPoints < $totalPointsNeeded) {
            throw new Exception("Insufficient Points (Has: $donorHasPoints, Need: $totalPointsNeeded).");
        }

        // --- Database Updates ---
        
        // 1. Deduct Points
        if ($ptRow) {
            $newPoints = $donorHasPoints - $totalPointsNeeded;
            $updatePt = $conn->query("UPDATE point SET Points_Total = $newPoints, Points_Updated_At = NOW() WHERE Donor_ID = $donorId");
            if (!$updatePt) throw new Exception("Error updating points: " . $conn->error);
        }

        // 2. Deduct Stock
        $updateStock = $conn->query("UPDATE reward_item SET Reward_Stock = Reward_Stock - $quantity WHERE Reward_ID = $rewardId");
        if (!$updateStock) throw new Exception("Error updating stock: " . $conn->error);

        // 3. Create Order
        $sql = "INSERT INTO redemption_order (
            Donor_ID, Reward_ID, Redemption_Quantity, Redemption_PointsSpent, Redemption_Status, 
            Redemption_Address1, Redemption_Address2, Redemption_Address3, 
            Redemption_City, Redemption_State, Redemption_PostalCode, 
            Redemption_ContactNumber, Redemption_Created_At, Redemption_Updated_At
        ) VALUES (
            $donorId, $rewardId, $quantity, $totalPointsNeeded, 'Pending',
            '$address1', '$address2', '$address3', 
            '$city', '$state', '$postal',
            '$contact', NOW(), NOW()
        )";
        
        if (!$conn->query($sql)) {
            throw new Exception("Database Insert Error: " . $conn->error);
        }

        // All Good
        $conn->commit();
        $saveSuccess = true;

    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Redemption Order - Love Bridge</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_common.css">
    <style>
        /* General Styles */
        .page-header-compact { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-top: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 5px; background: #f8f9fa; border: 1px solid #eee; transition: all 0.2s; }
        .back-btn:hover { background: #e9ecef; color: #333; }
        .header-title { flex: 1; text-align: center; padding-right: 120px; }
        .header-title h1 { margin: 0; font-size: 24px; color: #333; font-weight: 700; }
        .header-title p { margin: 5px 0 0; color: #666; font-size: 14px; }
        
        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 900px; margin: 0 auto 30px; }
        .form-header { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; text-align: center; } 
        .form-header h2 { font-size: 18px; color: #333; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }
        
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; } 
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 20px; } 
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark); font-size: 14px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border: 1px solid var(--gray-light); border-radius: 5px; outline: none; transition: 0.3s; font-size: 14px; box-sizing: border-box; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .required { color: red; margin-left: 3px; font-weight: bold; }
        .form-guide { font-size: 12px; color: #6c757d; margin-top: 5px; display: block; font-style: italic; background: #fbfbfb; padding: 4px 8px; border-radius: 4px; border-left: 3px solid #ddd; }
        .section-separator { border-top: 1px dashed #ddd; margin: 30px 0 20px; position: relative; }
        .section-separator span { position: absolute; top: -12px; left: 0; background: #fff; padding-right: 10px; font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; }

        /* Buttons */
        .button-group { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn-clear { padding: 12px 25px; background: white; color: #555; border: 1px solid #ddd; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-clear:hover { background: #f8f9fa; border-color: #aaa; color: #333; transform: translateY(-1px); }
        .btn-save { padding: 12px 25px; background: linear-gradient(135deg, #F28585 0%, #ff9a9a 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(242, 133, 133, 0.4); }

        /* Phone Input */
        .phone-format { display: flex; align-items: center; } 
        .phone-prefix { padding: 12px 15px; background: #f8f9fa; border: 1px solid var(--gray-light); border-right: none; border-radius: 5px 0 0 5px; color: var(--gray); font-size: 14px; } 
        .phone-input { border-radius: 0 5px 5px 0 !important; }

        /* Custom Selection Box */
        .selection-box { display: flex; cursor: pointer; transition: 0.2s; }
        .selection-input { border-radius: 5px 0 0 5px !important; background: white !important; cursor: pointer; border-right: none; }
        .selection-btn { border-radius: 0 5px 5px 0; border: 1px solid var(--gray-light); border-left: none; background: #f8f9fa; padding: 0 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #666; transition: 0.2s; min-width: 40px;}
        .selection-box:hover .selection-btn { background: #e2e6ea; color: #333; }
        
        /* Modal Styles */
        .modal-overlay-custom { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center; padding: 20px; animation: fadeIn 0.2s; }
        .modal-box { background: white; border-radius: 10px; width: 100%; max-width: 800px; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 10px 25px rgba(0,0,0,0.2); animation: slideDown 0.3s ease-out; }
        .modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; border-radius: 10px 10px 0 0; }
        .modal-header h3 { margin: 0; font-size: 16px; font-weight: 600; color: #333; }
        .modal-close { font-size: 24px; color: #999; cursor: pointer; transition: 0.2s; line-height: 1; }
        .modal-close:hover { color: #dc3545; }
        .modal-body { padding: 20px; overflow-y: auto; flex: 1; position: relative; }
        
        .search-bar-container { margin-bottom: 15px; position: relative; }
        .search-bar-container i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .modal-search { width: 100%; padding: 10px 10px 10px 35px; border: 1px solid #ddd; border-radius: 5px; outline: none; box-sizing: border-box; font-size: 14px; }
        .modal-search:focus { border-color: #F28585; }

        /* Table & Sorting */
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        /* Sticky Header Fix */
        .data-table th { text-align: left; padding: 12px; background: #fff; border-bottom: 2px solid #eee; color: #555; font-weight: 600; position: sticky; top: 0; z-index: 10; cursor: pointer; user-select: none; }
        .data-table th:hover { background-color: #f8f9fa; color: #F28585; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f9f9f9; cursor: pointer; transition: 0.1s; color: #333; }
        .data-table tr:hover td { background-color: #fff0f0; color: #000; }
        
        /* Alignments */
        .text-right { text-align: right !important; justify-content: flex-end; }
        .text-center { text-align: center !important; justify-content: center; }
        
        .points-badge { background: #e6f4ea; color: #1e7e34; padding: 3px 8px; border-radius: 10px; font-weight: 600; font-size: 11px; }
        .no-results { text-align: center; padding: 20px; color: #999; font-style: italic; }

        /* Filter Popups */
        .filter-popup { display: none; position: absolute; top: 45px; left: 0; background: white; border: 1px solid #eee; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); width: 220px; padding: 10px; z-index: 20; }
        .filter-popup.active { display: block; }
        .filter-option { display: block; padding: 8px 10px; color: #333; text-decoration: none; font-size: 13px; border-radius: 3px; cursor: pointer; margin-bottom: 2px; }
        .filter-option:hover { background-color: #f8f9fa; color: #F28585; }
        .filter-divider { height: 1px; background: #eee; margin: 5px 0; }
        .filter-label { font-size: 11px; color: #888; font-weight: 600; margin: 5px 0 3px 5px; display: block; }
        .filter-input-group { padding: 5px; }
        .filter-input { width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; box-sizing: border-box; margin-bottom: 5px; }
        .filter-btn { width: 100%; padding: 6px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; }
        .filter-btn:hover { background: #5a6268; }

        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .input-error { border-color: var(--danger) !important; background-color: #fff5f5 !important; }
        .inline-error { color: var(--danger); font-size: 11px; margin-top: 4px; display: block; font-weight: 500; animation: fadeIn 0.3s; }
        .input-error + .selection-btn { border-color: var(--danger) !important; border-left: none; background-color: #fff5f5; }

        /* Alerts */
        .custom-alert { position: fixed; top: 20px; right: 20px; background: white; border-left: 5px solid; padding: 15px 20px; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; z-index: 12000; transform: translateX(120%); transition: transform 0.3s ease-out; max-width: 350px; }
        .custom-alert.show { transform: translateX(0); }
        .custom-alert.error { border-color: #dc3545; } .custom-alert.error i { color: #dc3545; }
        .alert-content h4 { margin: 0 0 5px; font-size: 14px; color: #333; } .alert-content p { margin: 0; font-size: 13px; color: #666; }

        .success-modal { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); animation: popIn 0.3s; }
        .success-icon { width: 70px; height: 70px; background: #e6f4ea; border-radius: 50%; color: #28a745; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        @media (max-width: 768px) { .form-row { flex-direction: column; gap: 0; } }
    </style>
</head>
<body>

    <div id="customAlert" class="custom-alert"><i class="fas" id="alertIcon"></i><div class="alert-content"><h4 id="alertTitle">Title</h4><p id="alertMessage">Message</p></div></div>

    <div id="successModal" class="modal-overlay-custom" style="display: <?php echo $saveSuccess ? 'flex' : 'none'; ?>;">
        <div class="success-modal">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <h2 style="margin-bottom: 10px; font-size: 22px;">Success!</h2>
            <p style="color: #666; line-height: 1.5;">Redemption Order created successfully.</p>
            <div class="modal-btn-group">
                <a href="redemption_order_management.php" class="btn-clear" style="border: 1px solid #ddd; text-decoration:none;">Back to List</a>
                <button type="button" class="btn-save" onclick="window.location.href='redemption_order_add.php'">Continue Add Order</button>
            </div>
        </div>
    </div>

    <div id="donorModal" class="modal-overlay-custom">
        <div class="modal-box">
            <div class="modal-header"><h3>Select Donor</h3><span class="modal-close" onclick="closeModal('donorModal')">&times;</span></div>
            <div class="modal-body">
                <div class="search-bar-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="donorSearch" class="modal-search" placeholder="Search Name, IC or Contact..." onkeyup="renderDonorTable()">
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40%; position:relative;" onclick="toggleFilter('donorNameFilter')">
                                Name / IC <i class="fas fa-sort" style="float:right; color:#ccc;"></i>
                                <div id="donorNameFilter" class="filter-popup">
                                    <span class="filter-option" onclick="sortDonors('name', 'asc')"><i class="fas fa-sort-alpha-down"></i> Name A - Z</span>
                                    <span class="filter-option" onclick="sortDonors('name', 'desc')"><i class="fas fa-sort-alpha-up"></i> Name Z - A</span>
                                    <div class="filter-divider"></div>
                                    <span class="filter-option" onclick="sortDonors('ic', 'asc')"><i class="fas fa-sort-numeric-down"></i> IC Ascending</span>
                                    <span class="filter-option" onclick="sortDonors('ic', 'desc')"><i class="fas fa-sort-numeric-up"></i> IC Descending</span>
                                </div>
                            </th>
                            <th style="width:30%; position:relative;" onclick="toggleFilter('donorContactFilter')">
                                Contact <i class="fas fa-filter" style="float:right; color:#ccc; font-size:11px;"></i>
                                <div id="donorContactFilter" class="filter-popup">
                                    <span class="filter-label">Filter Prefix</span>
                                    <select id="contactPrefixSelect" class="filter-input" onchange="renderDonorTable()">
                                        <option value="">All Prefixes</option>
                                        <?php for($i=11; $i<=19; $i++) echo "<option value='+60$i'>+60$i</option>"; ?>
                                    </select>
                                </div>
                            </th>
                            <th style="width:30%; position:relative;" class="text-right" onclick="toggleFilter('donorPointsFilter')">
                                Points <i class="fas fa-sort" style="margin-left:5px; color:#ccc;"></i>
                                <div id="donorPointsFilter" class="filter-popup" style="right:0; left:auto;">
                                    <span class="filter-option" onclick="sortDonors('points', 'asc')"><i class="fas fa-sort-amount-up"></i> Low to High</span>
                                    <span class="filter-option" onclick="sortDonors('points', 'desc')"><i class="fas fa-sort-amount-down"></i> High to Low</span>
                                    <div class="filter-divider"></div>
                                    <div class="filter-input-group">
                                        <span class="filter-label">Range</span>
                                        <input type="number" id="minPoints" placeholder="Min" class="filter-input">
                                        <input type="number" id="maxPoints" placeholder="Max" class="filter-input">
                                        <button class="filter-btn" onclick="renderDonorTable()">Apply</button>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="donorTableBody">
                        </tbody>
                </table>
                <div id="noDonors" class="no-results" style="display:none;">No donors found.</div>
            </div>
        </div>
    </div>

    <div id="rewardModal" class="modal-overlay-custom">
        <div class="modal-box">
            <div class="modal-header"><h3>Select Reward Item</h3><span class="modal-close" onclick="closeModal('rewardModal')">&times;</span></div>
            <div class="modal-body">
                <div class="search-bar-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="rewardSearch" class="modal-search" placeholder="Search Item Name or Code..." onkeyup="renderRewardTable()">
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:35%; position:relative;" onclick="toggleFilter('rewNameFilter')">
                                Item Name <i class="fas fa-sort" style="float:right; color:#ccc;"></i>
                                <div id="rewNameFilter" class="filter-popup">
                                    <span class="filter-option" onclick="sortRewards('name', 'asc')"><i class="fas fa-sort-alpha-down"></i> Name A - Z</span>
                                    <span class="filter-option" onclick="sortRewards('name', 'desc')"><i class="fas fa-sort-alpha-up"></i> Name Z - A</span>
                                </div>
                            </th>
                            <th style="width:20%; position:relative;" class="text-center" onclick="toggleFilter('rewCodeFilter')">
                                Code <i class="fas fa-sort" style="margin-left:5px; color:#ccc;"></i>
                                <div id="rewCodeFilter" class="filter-popup">
                                    <span class="filter-option" onclick="sortRewards('code', 'asc')">Code A - Z</span>
                                    <span class="filter-option" onclick="sortRewards('code', 'desc')">Code Z - A</span>
                                    <div class="filter-divider"></div>
                                    <div class="filter-input-group">
                                        <span class="filter-label">Code Range</span>
                                        <input type="text" id="startCode" placeholder="Start (e.g. A01)" class="filter-input">
                                        <input type="text" id="endCode" placeholder="End (e.g. A10)" class="filter-input">
                                        <button class="filter-btn" onclick="renderRewardTable()">Apply</button>
                                    </div>
                                </div>
                            </th>
                            <th style="width:25%; position:relative;" class="text-center" onclick="toggleFilter('rewPointsFilter')">
                                Required Points <i class="fas fa-sort" style="margin-left:5px; color:#ccc;"></i>
                                <div id="rewPointsFilter" class="filter-popup">
                                    <span class="filter-option" onclick="sortRewards('points', 'asc')">Low to High</span>
                                    <span class="filter-option" onclick="sortRewards('points', 'desc')">High to Low</span>
                                    <div class="filter-divider"></div>
                                    <div class="filter-input-group">
                                        <span class="filter-label">Range</span>
                                        <input type="number" id="minReq" placeholder="Min" class="filter-input">
                                        <input type="number" id="maxReq" placeholder="Max" class="filter-input">
                                        <button class="filter-btn" onclick="renderRewardTable()">Apply</button>
                                    </div>
                                </div>
                            </th>
                            <th style="width:20%; position:relative;" class="text-center" onclick="toggleFilter('rewStockFilter')">
                                Stock <i class="fas fa-filter" style="margin-left:5px; color:#ccc; font-size:11px;"></i>
                                <div id="rewStockFilter" class="filter-popup" style="right:0; left:auto;">
                                    <div class="filter-input-group">
                                        <span class="filter-label">Approx. Stock</span>
                                        <input type="number" id="minStock" placeholder="Min Qty" class="filter-input">
                                        <button class="filter-btn" onclick="renderRewardTable()">Apply</button>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="rewardTableBody">
                        </tbody>
                </table>
                <div id="noRewards" class="no-results" style="display:none;">No items found.</div>
            </div>
        </div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php include 'admin_header.php'; ?>

        <div class="dashboard-content" style="padding-top: 10px;">
            <div class="page-header-compact">
                <a href="redemption_order_management.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="header-title">
                    <h1>Add Redemption Order</h1>
                    <p>Create a manual redemption order for a donor.</p>
                </div>
            </div>

            <div class="form-container">
                <form id="addOrderForm" method="POST" action="redemption_order_add.php" onsubmit="return validateAddOrder(event)" novalidate>
                    <input type="hidden" name="add_order" value="1">
                    
                    <div class="form-header"><h2>Order Details</h2></div>

                    <div class="form-group">
                        <label class="form-label">Select Donor <span class="required">*</span></label>
                        <input type="hidden" name="donor_id" id="donor_id">
                        <input type="hidden" id="donor_points_avail" value="0">
                        <div class="selection-box" onclick="openModal('donorModal')">
                            <input type="text" id="donor_display" class="form-input selection-input" placeholder="Click to select donor..." readonly>
                            <div class="selection-btn"><i class="fas fa-search"></i></div>
                        </div>
                        <span class="form-guide">Select the donor who is redeeming points.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="flex:2;">
                            <label class="form-label">Select Reward Item <span class="required">*</span></label>
                            <input type="hidden" name="reward_id" id="reward_id">
                            <input type="hidden" id="item_points_req" value="0">
                            <div class="selection-box" onclick="openModal('rewardModal')">
                                <input type="text" id="reward_display" class="form-input selection-input" placeholder="Click to select item..." readonly>
                                <div class="selection-btn"><i class="fas fa-search"></i></div>
                            </div>
                            <span class="form-guide">Choose the item to be redeemed.</span>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Quantity <span class="required">*</span></label>
                            <input type="number" name="quantity" id="add_quantity" class="form-input" value="1" min="1" required onchange="calcPoints()" onkeyup="calcPoints()" placeholder="1">
                            <span class="form-guide">Enter quantity (min 1).</span>
                        </div>
                    </div>
                    
                    <div style="margin-bottom:20px; font-weight: bold; color: #555; text-align:right;" id="pointsSummary">
                        Total Points Required: 0
                    </div>

                    <div class="section-separator"><span>Shipping Information</span></div>

                    <div class="form-group">
                        <label class="form-label">Contact Number <span class="required">*</span></label>
                        <div class="phone-format">
                            <span class="phone-prefix">+60</span>
                            <input type="text" name="contact" id="add_contact" class="form-input phone-input" required placeholder="12-3456789" maxlength="11">
                        </div>
                        <span class="form-guide">Format: 12-3456789 (Prefix 11-19, total 9-11 digits).</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1 <span class="required">*</span></label>
                        <input type="text" name="address1" id="add_address1" class="form-input" required placeholder="e.g. No. 123, Jalan Example">
                        <span class="form-guide">House unit no., floor, building, street name.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2 <span class="required">*</span></label>
                        <input type="text" name="address2" id="add_address2" class="form-input" required placeholder="e.g. Taman Sri">
                        <span class="form-guide">Residential area, village, or section.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" name="address3" id="add_address3" class="form-input" placeholder="Address Line 3 (Optional)">
                        <span class="form-guide">Address Line 3 (Optional).</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postal Code <span class="required">*</span></label>
                            <input type="text" name="postal_code" id="add_postal_code" class="form-input" required placeholder="e.g. 50000">
                            <span class="form-guide">5-digit postcode.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <span class="required">*</span></label>
                            <input type="text" name="city" id="add_city" class="form-input" required placeholder="e.g. Kuala Lumpur">
                            <span class="form-guide">City name.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">State <span class="required">*</span></label>
                        <select name="state" id="add_state_select" class="form-select" required>
                            <option value="">Select State</option>
                            <?php foreach($malaysiaStates as $s) echo "<option value='$s'>$s</option>"; ?>
                        </select>
                        <span class="form-guide">Select state.</span>
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn-clear" onclick="window.location.href='redemption_order_management.php'"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Create Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // --- 1. DATA INITIALIZATION ---
        const donorsData = <?php echo json_encode($donorList); ?>;
        const rewardsData = <?php echo json_encode($rewardList); ?>;
        
        let activeDonors = [...donorsData];
        let activeRewards = [...rewardsData];

        // --- 2. POPUP FILTER LOGIC ---
        function toggleFilter(id) {
            // Close all others first
            document.querySelectorAll('.filter-popup').forEach(el => {
                if(el.id !== id) el.classList.remove('active');
            });
            const popup = document.getElementById(id);
            popup.classList.toggle('active');
            
            // Stop propagation so closing logic works
            event.stopPropagation();
        }

        // Close filters when clicking outside
        window.addEventListener('click', function(e) {
            if (!e.target.closest('th')) {
                document.querySelectorAll('.filter-popup').forEach(el => el.classList.remove('active'));
            }
            if (e.target.classList.contains('modal-overlay-custom')) {
                e.target.style.display = 'none';
            }
        });

        // --- 3. DONOR LOGIC ---
        function renderDonorTable() {
            const tbody = document.getElementById('donorTableBody');
            const search = document.getElementById('donorSearch').value.toLowerCase();
            const prefix = document.getElementById('contactPrefixSelect').value;
            const minP = parseFloat(document.getElementById('minPoints').value) || 0;
            const maxP = parseFloat(document.getElementById('maxPoints').value) || Infinity;

            tbody.innerHTML = '';
            
            let count = 0;
            activeDonors.forEach(d => {
                // Filters
                const matchSearch = d.Donor_Name.toLowerCase().includes(search) || d.Donor_ICNumber.includes(search) || d.Donor_ContactNumber.includes(search);
                const matchPrefix = prefix === '' || d.Donor_ContactNumber.includes(prefix); // Simple includes check for prefix
                const pts = parseFloat(d.Points_Total);
                const matchPoints = pts >= minP && pts <= maxP;

                if (matchSearch && matchPrefix && matchPoints) {
                    const row = `
                        <tr onclick='selectDonor(${JSON.stringify(d)})'>
                            <td>
                                <div style="font-weight:600;">${d.Donor_Name}</div>
                                <div style="font-size:11px; color:#888;">${d.Donor_ICNumber}</div>
                            </td>
                            <td>${d.Donor_ContactNumber}</td>
                            <td class="text-right"><span class="points-badge">${d.Points_Total} pts</span></td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                    count++;
                }
            });
            document.getElementById('noDonors').style.display = count === 0 ? 'block' : 'none';
        }

        function sortDonors(key, order) {
            activeDonors.sort((a, b) => {
                let valA, valB;
                if (key === 'name') { valA = a.Donor_Name.toLowerCase(); valB = b.Donor_Name.toLowerCase(); }
                else if (key === 'ic') { valA = a.Donor_ICNumber; valB = b.Donor_ICNumber; }
                else if (key === 'points') { valA = parseFloat(a.Points_Total); valB = parseFloat(b.Points_Total); }

                if (valA < valB) return order === 'asc' ? -1 : 1;
                if (valA > valB) return order === 'asc' ? 1 : -1;
                return 0;
            });
            renderDonorTable();
        }

        // --- 4. REWARD LOGIC ---
        function renderRewardTable() {
            const tbody = document.getElementById('rewardTableBody');
            const search = document.getElementById('rewardSearch').value.toLowerCase();
            
            const startCode = document.getElementById('startCode').value.toUpperCase();
            const endCode = document.getElementById('endCode').value.toUpperCase();
            
            const minReq = parseFloat(document.getElementById('minReq').value) || 0;
            const maxReq = parseFloat(document.getElementById('maxReq').value) || Infinity;
            const minStock = parseFloat(document.getElementById('minStock').value) || 0;

            tbody.innerHTML = '';
            let count = 0;

            activeRewards.forEach(r => {
                const matchSearch = r.Reward_ItemName.toLowerCase().includes(search) || r.Reward_Code.toLowerCase().includes(search);
                
                // Code Range Check
                const code = r.Reward_Code.toUpperCase();
                let matchCode = true;
                if(startCode && code < startCode) matchCode = false;
                if(endCode && code > endCode) matchCode = false;

                const pts = parseFloat(r.Reward_RequiredPoint);
                const matchPts = pts >= minReq && pts <= maxReq;
                
                const stock = parseFloat(r.Reward_Stock);
                const matchStock = stock >= minStock;

                if (matchSearch && matchCode && matchPts && matchStock) {
                    const row = `
                        <tr onclick='selectReward(${JSON.stringify(r)})'>
                            <td>${r.Reward_ItemName}</td>
                            <td class="text-center"><span style="font-family:monospace; color:#666;">${r.Reward_Code}</span></td>
                            <td class="text-center" style="color:#d63384; font-weight:600;">${r.Reward_RequiredPoint}</td>
                            <td class="text-center">${r.Reward_Stock}</td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                    count++;
                }
            });
            document.getElementById('noRewards').style.display = count === 0 ? 'block' : 'none';
        }

        function sortRewards(key, order) {
            activeRewards.sort((a, b) => {
                let valA, valB;
                if (key === 'name') { valA = a.Reward_ItemName.toLowerCase(); valB = b.Reward_ItemName.toLowerCase(); }
                else if (key === 'code') { valA = a.Reward_Code; valB = b.Reward_Code; }
                else if (key === 'points') { valA = parseFloat(a.Reward_RequiredPoint); valB = parseFloat(b.Reward_RequiredPoint); }

                if (valA < valB) return order === 'asc' ? -1 : 1;
                if (valA > valB) return order === 'asc' ? 1 : -1;
                return 0;
            });
            renderRewardTable();
        }

        // --- 5. MODAL CONTROL ---
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
            if (id === 'donorModal') renderDonorTable();
            if (id === 'rewardModal') renderRewardTable();
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        // --- 6. SELECTION LOGIC ---
        function selectDonor(d) { // d is passed as object
            document.getElementById('donor_id').value = d.Donor_ID;
            document.getElementById('donor_display').value = d.Donor_Name + " (" + d.Points_Total + " pts)";
            document.getElementById('donor_points_avail').value = d.Points_Total;

            // Auto-fill Address
            document.getElementById('add_address1').value = d.Donor_Address1 || '';
            document.getElementById('add_address2').value = d.Donor_Address2 || '';
            document.getElementById('add_address3').value = d.Donor_Address3 || '';
            document.getElementById('add_city').value = d.Donor_City || '';
            document.getElementById('add_postal_code').value = d.Donor_PostalCode || '';
            if(d.Donor_State) document.getElementById('add_state_select').value = d.Donor_State;

            // Phone logic
            let rawContact = d.Donor_ContactNumber || '';
            let digits = rawContact.replace(/\D/g, '');
            if(digits.startsWith('60')) digits = digits.substring(2);
            else if(digits.startsWith('0')) digits = digits.substring(1);
            if(digits.length > 2) digits = digits.substring(0, 2) + '-' + digits.substring(2);
            
            document.getElementById('add_contact').value = digits;

            calcPoints();
            closeModal('donorModal');
            document.getElementById('donor_display').classList.remove('input-error');
        }

        function selectReward(r) { // r is passed as object
            document.getElementById('reward_id').value = r.Reward_ID;
            document.getElementById('reward_display').value = r.Reward_ItemName + " (" + r.Reward_RequiredPoint + " pts)";
            document.getElementById('item_points_req').value = r.Reward_RequiredPoint;
            
            calcPoints();
            closeModal('rewardModal');
            document.getElementById('reward_display').classList.remove('input-error');
        }

        function calcPoints() {
            const qtyInput = document.getElementById('add_quantity');
            let qty = parseInt(qtyInput.value);
            if(isNaN(qty) || qty < 1) { qty = 1; }

            const unitPts = parseInt(document.getElementById('item_points_req').value || 0);
            const donorPts = parseInt(document.getElementById('donor_points_avail').value || 0);
            
            const totalNeeded = unitPts * qty;
            const summary = document.getElementById('pointsSummary');
            
            summary.innerText = "Total Points Required: " + totalNeeded + " (Donor Has: " + donorPts + ")";
            summary.style.color = (donorPts < totalNeeded) ? 'red' : '#28a745';
        }

        // --- 7. UTILS & VALIDATION ---
        document.getElementById('add_postal_code').addEventListener('input', function() {
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
                if (state) document.getElementById('add_state_select').value = state;
            }
        });

        document.getElementById('add_contact').addEventListener('input', function(e) { 
            let val = this.value.replace(/\D/g, ''); 
            if (val.length > 11) val = val.substring(0, 11); 
            let newVal = ''; 
            if (val.length > 2) newVal += val.substring(0, 2) + '-' + val.substring(2); 
            else newVal = val; 
            this.value = newVal; 
        });

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
            if (parent.classList.contains('phone-format') || parent.classList.contains('selection-box')) parent = parent.parentNode; 
            let errorDiv = parent.querySelector('.inline-error');
            if (errorDiv) errorDiv.remove();
            errorDiv = document.createElement('div'); 
            errorDiv.className = 'inline-error'; 
            errorDiv.textContent = message;
            parent.appendChild(errorDiv);
        }

        function clearFormErrors(formId) {
            const form = document.getElementById(formId);
            const inputs = form.querySelectorAll('.form-input, .form-select, .selection-input');
            inputs.forEach(i => i.classList.remove('input-error'));
            form.querySelectorAll('.inline-error').forEach(e => e.remove());
        }

        function validateAddOrder(e) {
            clearFormErrors('addOrderForm');
            let hasError = false;
            let firstErrorMsg = "";

            const requiredFields = [
                { id: 'donor_display', name: 'Donor' }, 
                { id: 'reward_display', name: 'Reward' },
                { id: 'add_quantity', name: 'Quantity' },
                { id: 'add_contact', name: 'Contact' },
                { id: 'add_address1', name: 'Address Line 1' },
                { id: 'add_address2', name: 'Address Line 2' },
                { id: 'add_postal_code', name: 'Postal Code' },
                { id: 'add_city', name: 'City' },
                { id: 'add_state_select', name: 'State' }
            ];

            requiredFields.forEach(field => {
                const el = document.getElementById(field.id);
                let val = el.value;
                if(field.id === 'donor_display') val = document.getElementById('donor_id').value;
                if(field.id === 'reward_display') val = document.getElementById('reward_id').value;

                if (!val || val.trim() === "") {
                    showFieldError(field.id, field.name + " is required.");
                    hasError = true;
                    if (!firstErrorMsg) firstErrorMsg = field.name + " is required.";
                }
            });

            const contactId = 'add_contact';
            const contactVal = document.getElementById(contactId).value.trim();
            if (contactVal) {
                let phoneMsg = validatePhoneDetailed(contactVal);
                if (phoneMsg) { 
                    showFieldError(contactId, phoneMsg); 
                    hasError = true; 
                    if(!firstErrorMsg) firstErrorMsg = phoneMsg; 
                }
            }

            if (!hasError) {
                const unitPts = parseInt(document.getElementById('item_points_req').value || 0);
                const donorPts = parseInt(document.getElementById('donor_points_avail').value || 0);
                const qty = parseInt(document.getElementById('add_quantity').value);
                if (donorPts < (unitPts * qty)) {
                    showSystemAlert('Donor does not have enough points.', 'error');
                    e.preventDefault();
                    return false;
                }
            }

            if (hasError) {
                e.preventDefault();
                showSystemAlert("Please fill in all required fields.", 'error');
                return false;
            }
            return true;
        }

        function showSystemAlert(message, type = 'error') {
            const alertBox = document.getElementById('customAlert');
            const alertIcon = document.getElementById('alertIcon');
            const alertTitle = document.getElementById('alertTitle');
            const alertMsg = document.getElementById('alertMessage');
            alertBox.className = 'custom-alert ' + type;
            if (type === 'error') { alertIcon.className = 'fas fa-exclamation-circle'; alertTitle.innerText = 'Error'; } 
            else { alertIcon.className = 'fas fa-check-circle'; alertTitle.innerText = 'Success'; }
            alertMsg.innerText = message;
            alertBox.classList.add('show');
            setTimeout(() => { alertBox.classList.remove('show'); }, 4000);
        }

        <?php if($errorMessage): ?>
            showSystemAlert("<?php echo addslashes($errorMessage); ?>", 'error');
        <?php endif; ?>
    </script>
</body>
</html>