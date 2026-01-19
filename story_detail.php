<?php
// story_detail.php
include 'dataconnection.php'; // Ensure database connection is available
include 'header_function.php'; // Include necessary header functions

// 1. Get Story ID from URL
if (isset($_GET['id'])) {
    $story_id = intval($_GET['id']); // Convert to integer for security

    // 2. Fetch Story Details from Database
    $stmt = $conn->prepare("SELECT * FROM story WHERE Story_ID = ?");
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $story = $result->fetch_assoc();

    // Check if story exists
    if (!$story) {
        echo "<script>alert('Story not found.'); window.location.href='story.php';</script>";
        exit();
    }
} else {
    // Redirect if no ID provided
    header("Location: story.php");
    exit();
}

// 3. Process Data for Display
$title = !empty($story['Story_Title']) ? $story['Story_Title'] : "Untitled Story";
$author = !empty($story['Story_Author']) ? $story['Story_Author'] : "Anonymous";
$category = !empty($story['Story_Category']) ? $story['Story_Category'] : "General";
$date = date('F d, Y', strtotime($story['Story_Date'] ?? $story['Created_At'])); 
$content = $story['Story_Description'];

// Image Handling (Same logic as your main page)
$defaultImage = 'images/story-default.jpg';
$imagePath = $defaultImage;

if (!empty($story['Story_Image'])) {
    $decoded = json_decode($story['Story_Image'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) {
        $imagePath = $decoded[0]; // Use the first image if it's a JSON array
    } else {
        $imagePath = $story['Story_Image']; // Use directly if string
    }
}

// Category Badge Color Logic
if (stripos($category, 'News') !== false) {
    $badgeClass = 'badge-blue';
} else {
    $badgeClass = 'badge-red';
}

include 'header_UI.php'; // Include your UI header
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Love Bridge</title>
    
    <style>
        :root {
            --primary-red: #e53935;
            --dark-red: #c62828;
            --light-red: #ff5252;
            --lighter-red: #ffcdd2;
            --primary-blue: #1976d2; 
            --white: #ffffff;
            --light-bg: #fef7f7;
            --text: #212121;
            --text-muted: #555;
            --shadow: rgba(229, 57, 53, 0.1);
        }

        body {
            background-color: var(--light-bg);
            color: var(--text);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.8; /* Slightly increased line height for readability */
        }

        .detail-container {
            max-width: 900px; /* Reading-friendly width */
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Back Button */
        .back-nav {
            margin-bottom: 20px;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .btn-back:hover {
            color: var(--primary-red);
        }

        /* Main Content Card */
        .story-detail-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08); /* Softer shadow */
            border: 1px solid rgba(0,0,0,0.05);
        }

        /* Hero Image */
        .detail-image-wrapper {
            position: relative;
            width: 100%;
            height: 400px; /* Fixed height for banner feel */
            overflow: hidden;
        }

        .detail-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Badge on Image */
        .detail-badge {
            position: absolute;
            top: 20px;
            left: 20px; /* Left aligned looks better on banners */
            padding: 8px 20px;
            border-radius: 30px;
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 13px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .badge-red { background: var(--primary-red); }
        .badge-blue { background: var(--primary-blue); }

        /* Content Body */
        .detail-body {
            padding: 40px 50px;
        }

        /* Header Info */
        .detail-header {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }

        .detail-title {
            font-size: 36px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .detail-meta {
            display: flex;
            gap: 25px;
            color: var(--text-muted);
            font-size: 15px;
            font-weight: 500;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .meta-item i {
            color: var(--primary-red);
        }

        /* Main Text */
        .detail-description {
            font-size: 18px;
            color: #333;
            white-space: pre-wrap; /* Preserves paragraphs/line breaks from DB */
            text-align: justify;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .detail-image-wrapper { height: 250px; }
            .detail-body { padding: 30px 20px; }
            .detail-title { font-size: 28px; }
            .detail-meta { flex-direction: column; gap: 10px; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="detail-container">
        <div class="back-nav">
            <a href="New&Story.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to News & Stories
            </a>
        </div>

        <article class="story-detail-card">
            <div class="detail-image-wrapper">
                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                     alt="<?php echo htmlspecialchars($title); ?>" 
                     class="detail-image"
                     onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>';">
                
                <span class="detail-badge <?php echo $badgeClass; ?>">
                    <?php echo htmlspecialchars($category); ?>
                </span>
            </div>

            <div class="detail-body">
                <header class="detail-header">
                    <h1 class="detail-title"><?php echo htmlspecialchars($title); ?></h1>
                    
                    <div class="detail-meta">
                        <div class="meta-item">
                            <i class="far fa-calendar-alt"></i>
                            <span><?php echo $date; ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-user-edit"></i>
                            <span>By <?php echo htmlspecialchars($author); ?></span>
                        </div>
                    </div>
                </header>

                <div class="detail-description">
                    <?php echo nl2br(htmlspecialchars($content)); ?>
                </div>
            </div>
        </article>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>