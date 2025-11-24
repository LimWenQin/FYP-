<?php
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI.php';

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;


$stories = [];
$query = "SELECT * FROM story ORDER BY Story_Date DESC LIMIT 10";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stories[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Stories - Donor Platform</title>
    <link rel="stylesheet" href="donor_design.css">
    <style>
  :root {
            --primary: #F6B8B8;
            --secondary: #FFF5E4;
            --text: #4A4A4A;
            --light-text: #777777;
            --white: #FFFFFF;
            --shadow: rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: var(--secondary);
            color: var(--text);
            line-height: 1.6;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #434141;
            padding: 6px 50px;
            box-shadow: 0 2px 4px var(--shadow);
        }
        
        .welcome-text {
            font-size: 16px;
            font-weight: bold;
            color: white;
        }
        
        .auth-buttons {
            display: flex;
            gap: 10px;
        }
        
        .auth-btn {
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            text-decoration: none;
            font-size: 14px;
        }
        
        .login-btn {
            background-color: white;
            color: black;
            border: none;
        }
        
        .login-btn:hover {
            background-color: #938e8eff;
        }
        
        .register-btn {
            background-color: var(--text);
            color: var(--white);
            border: none;
        }
        
        .register-btn:hover {
            background-color: #333333;
        }
        
        .header {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr; 
            align-items: center;
            background-color: var(--primary);
            padding: 20px 50px;
            box-shadow: 0 2px 6px var(--shadow);
            gap: 20px;
        }
        
        .logo {
            font-weight: bold;
            font-size: 36px;
            color: var(--text);
            text-align: left; 
            grid-column: 1;
        }
        
        .header-right { 
            grid-column: 2;
            display: flex;
            align-items: center;
            justify-content: center; 
            gap: 15px;
        }

        .function-links {
            display: flex;
            gap: 15px;
        }
        
        .function-links a {
            text-decoration: none;
            font-size: 18px;
            color: var(--text);
            font-weight: bold;
            transition: opacity 0.3s;
            white-space: nowrap;
        }
        
        .function-links a:hover {
            opacity: 0.8;
        }

        .header-center { 
            grid-column: 3;
            display: flex;
            align-items: center;
            justify-content: flex-end; 
            gap: 15px;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background-color: var(--white);
            border-radius: 4px;
            padding: 4px 8px;
            width: auto; 
            max-width: 250px;
        }
        
        .search-box input {
            border: none;
            background: transparent;
            padding: 6px;
            width: 100%;
            outline: none;
        }
        
        .donate-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 6px 18px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            white-space: nowrap;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .donate-btn:hover {
            background-color: #c0392b;
        }
        .page-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 32px;
            color: var(--text);
        }
        
        .stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .story-card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .story-card:hover {
            transform: translateY(-5px);
        }
        
        .story-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .story-content {
            padding: 20px;
        }
        
        .story-title {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text);
        }
        
        .story-date {
            color: var(--light-text);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .story-excerpt {
            margin-bottom: 15px;
            color: var(--text);
        }
        
        .read-more {
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
        }
        
        .read-more:hover {
            text-decoration: underline;
        }
        
        .no-stories {
            text-align: center;
            padding: 40px;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
        }
        
        @media (max-width: 1024px) {
            .header {
                grid-template-columns: 1fr 1fr; 
                grid-template-rows: auto auto; 
                gap: 15px;
            }
            
            .logo { 
                grid-column: 1;
                grid-row: 1;
                text-align: left;
            }
            
            .header-right { 
                grid-column: 2;
                grid-row: 1;
                justify-content: flex-end; 
            }

            .header-center { 
                grid-column: 1 / span 2;
                grid-row: 2;
                justify-content: center; 
            }
        }
        
        @media (max-width: 768px) {
            .header {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
            }
            
            .header-top, .header {
                padding: 15px 20px;
            }
            
            .logo { 
                grid-row: 1; 
                text-align: center;
            }
            
            .header-right { 
                grid-row: 2; 
                justify-content: center;
            }

            .header-center { 
                grid-row: 3; 
                flex-direction: column;
            }
            
            .page-container {
                padding: 15px;
            }
            
            .stories-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
   
    <div class="page-container">
        <h1 class="page-title">News & Stories</h1>
        
        <?php if (!empty($stories)): ?>
            <div class="stories-grid">
                <?php foreach ($stories as $story): ?>
                    <div class="story-card">
                        <img src="<?php echo htmlspecialchars($story['Image_URL']); ?>" alt="<?php echo htmlspecialchars($story['Title']); ?>" class="story-image">
                        <div class="story-content">
                            <h2 class="story-title"><?php echo htmlspecialchars($story['Title']); ?></h2>
                            <div class="story-date"><?php echo date('F j, Y', strtotime($story['Publish_Date'])); ?></div>
                            <p class="story-excerpt"><?php echo htmlspecialchars(substr($story['Content'], 0, 150)); ?>...</p>
                            <a href="story_detail.php?id=<?php echo $story['Story_ID']; ?>" class="read-more">Read More</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-stories">
                <h2>No stories available</h2>
                <p>Check back later for inspiring stories from our community.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>