<?php
// Branch_Selection.php
session_start();
include 'dataconnection.php';

// 1. 登录检查
if (!isset($_SESSION['donor_id'])) {
    echo "<script>alert('Please login first.'); window.location.href='donor_login.php';</script>";
    exit();
}

// ==========================================
// 2. 初始化流程数据
// ==========================================
if (!isset($_SESSION['donation_data'])) {
    $_SESSION['donation_data'] = [
        'amount' => '',
        'type' => 'one-time',
        'tax_receipt' => '0',
        'case_id' => '',
        'activity_id' => '',
        'branch_id' => ''
    ];
}

// 清理互斥数据
if (isset($_GET['case_id'])) {
    $_SESSION['donation_data']['case_id'] = $_GET['case_id'];
    $_SESSION['donation_data']['activity_id'] = '';
}
if (isset($_GET['activity_id'])) {
    $_SESSION['donation_data']['activity_id'] = $_GET['activity_id'];
    $_SESSION['donation_data']['case_id'] = '';
}

// ==========================================
// 3. 获取数据 (HQ & Branches)
// ==========================================
// HQ
$hq_sql = "SELECT HQ_Name, HQ_Description, HQ_Image, Headquarters_State FROM headquarters WHERE HQ_ID = 1 LIMIT 1";
$hq_result = $conn->query($hq_sql);
$hq_data = $hq_result->fetch_assoc();

$branches_list = [];

// Add HQ to list first
if ($hq_data) {
    $branches_list[] = [
        'Branch_ID' => '0',
        'Branch_Name' => $hq_data['HQ_Name'],
        'Branch_Type' => 'Headquarters',
        'Branch_Description' => $hq_data['HQ_Description'],
        'Branch_City' => $hq_data['Headquarters_State'],
        'decoded_images' => [!empty($hq_data['HQ_Image']) ? $hq_data['HQ_Image'] : 'images/hero_3.jpg']
    ];
}

// Other Branches
$sql = "SELECT Branch_ID, Branch_Name, Branch_Type, Branch_Description, Branch_City, Branch_Images 
        FROM branch 
        WHERE Is_Deleted = 0 AND Branch_OperationalStatus = 'Open'
        ORDER BY Branch_ID ASC";
$result = $conn->query($sql);

while($row = $result->fetch_assoc()) {
    $row['decoded_images'] = !empty($row['Branch_Images']) ? json_decode($row['Branch_Images'], true) : ['images/hero_3.jpg'];
    if (!is_array($row['decoded_images'])) {
        $row['decoded_images'] = [$row['Branch_Images']]; 
    }
    $branches_list[] = $row;
}

include 'header_UI.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* -----------------------------------------------------------
       STYLE COPIED & ADAPTED FROM New&Story.php
    ----------------------------------------------------------- */
    :root {
        --primary-red: #e53935;
        --dark-red: #c62828;
        --light-red: #ff5252;
        --lighter-red: #ffcdd2;
        --white: #ffffff;
        --light-bg: #fef7f7;
        --text: #212121;
        --shadow: rgba(229, 57, 53, 0.1);
    }

    /* 注意：这里不再隐藏 .header-donate-btn 
       因为你需要保留 Header 上的 Donate Button
    */

    body {
        background-color: var(--light-bg);
        color: var(--text);
        line-height: 1.6;
    }

    /* Header Styles */
    .stories-header {
        background: url('images/hero_2.jpg'); 
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        color: white;
        padding: 160px 20px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .stories-header::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(0, 0, 0, 0.6); 
    }

    .page-header-content {
        position: relative;
        z-index: 2;
    }

    .page-title {
        font-size: 56px;
        font-weight: 800;
        margin-bottom: 20px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        color: #fff;
    }

    .page-description {
        font-size: 22px;
        max-width: 800px;
        margin: 0 auto;
        opacity: 0.95;
        line-height: 1.6;
        text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        font-weight: 500;
        color: rgba(255, 255, 255, 0.9);
    }

    /* Main Container */
    .stories-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 60px 40px;
    }

    /* Grid */
    .stories-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 40px;
    }

    /* Card Logic for Selection */
    .branch-label {
        cursor: pointer;
        display: block;
        height: 100%;
        margin: 0;
    }
    
    /* Hide Radio Button */
    .branch-label input[type="radio"] {
        display: none;
    }

    /* Card Styles */
    .story-card {
        background: var(--white);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 30px var(--shadow);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid transparent; 
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    /* Hover Effect */
    .branch-label:hover .story-card {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(229, 57, 53, 0.2);
        border-color: var(--lighter-red);
    }

    .story-image-container {
        position: relative;
        height: 300px;
        overflow: hidden;
    }

    .story-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
    }

    .branch-label:hover .story-image {
        transform: scale(1.05);
    }

    .story-badge {
        position: absolute;
        top: 20px;
        right: 20px;
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        background: var(--primary-red);
        color: white;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }
    
    .badge-hq {
        background: #f59e0b; /* Amber for HQ */
    }

    .story-content {
        padding: 30px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .story-header {
        margin-bottom: 20px;
    }

    .story-meta {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 12px;
        color: #555;
        font-size: 14px;
    }

    .story-meta i {
        color: var(--primary-red);
    }

    .story-title {
        font-size: 28px;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 10px;
        line-height: 1.3;
    }

    .story-description-container {
        margin-bottom: 20px;
        flex: 1;
    }

    .story-description {
        color: #3b3b3b;
        font-size: 16px;
        line-height: 1.8;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .story-footer {
        margin-top: auto;
        padding-top: 20px;
        border-top: 1px solid var(--lighter-red);
        display: flex;
        justify-content: flex-end; /* 让 Read More 靠右 */
        align-items: center;
    }

    .read-more-link {
        color: #888;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .read-more-link:hover {
        color: var(--primary-red);
    }

    /* Responsive */
    @media (max-width: 992px) {
        .stories-grid { grid-template-columns: 1fr; }
        .page-title { font-size: 42px; }
    }
    @media (max-width: 768px) {
        .stories-header { padding: 80px 20px; }
        .page-title { font-size: 32px; }
        .story-image-container { height: 200px; }
    }
</style>

<div class="stories-header">
    <div class="page-header-content">
        <h1 class="page-title">Our Branch</h1>
        <p class="page-description">
            Explore our branches across the country. Select a specific branch to support their local initiatives and help us bridge the gap in your community.
        </p>
    </div>
</div>

<div class="stories-container">
    <form action="Payment_Page.php" method="POST" id="branchForm">
        <div class="stories-grid">
            
            <?php foreach ($branches_list as $branch): 
                $imgSrc = isset($branch['decoded_images'][0]) ? $branch['decoded_images'][0] : 'images/story-default.jpg';
                $isHQ = ($branch['Branch_Type'] === 'Headquarters');
                $badgeClass = $isHQ ? 'story-badge badge-hq' : 'story-badge';
            ?>
            
            <label class="branch-label">
                <input type="radio" name="branch_id" value="<?php echo $branch['Branch_ID']; ?>" onclick="this.form.submit()">

                <div class="story-card">
                    <div class="story-image-container">
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                             alt="<?php echo htmlspecialchars($branch['Branch_Name']); ?>" 
                             class="story-image"
                             onerror="this.src='images/hero_3.jpg';">
                        <span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($branch['Branch_Type']); ?></span>
                    </div>

                    <div class="story-content">
                        <div class="story-header">
                            <div class="story-meta">
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($branch['Branch_City']); ?></span>
                                <?php if($isHQ): ?>
                                    <span><i class="fas fa-star"></i> Central Hub</span>
                                <?php endif; ?>
                            </div>
                            <h3 class="story-title"><?php echo htmlspecialchars($branch['Branch_Name']); ?></h3>
                        </div>

                        <div class="story-description-container">
                            <p class="story-description">
                                <?php echo htmlspecialchars($branch['Branch_Description']); ?>
                            </p>
                        </div>

                        <div class="story-footer">
                            <a href="Branch_Details.php?id=<?php echo $branch['Branch_ID']; ?>" 
                               class="read-more-link"
                               onclick="event.stopPropagation();">
                               Read More <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </label>
            <?php endforeach; ?>

        </div>
    </form>
</div>

<script>
    function animateStoryCards() {
        const storyCards = document.querySelectorAll('.story-card');
        storyCards.forEach((card, index) => {
            const rect = card.getBoundingClientRect();
            if (rect.top < window.innerHeight - 50) {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const storyCards = document.querySelectorAll('.story-card');
        storyCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
        });
        
        setTimeout(animateStoryCards, 100);
        window.addEventListener('scroll', animateStoryCards);
    });
</script>

<?php include 'footer.php'; ?>