<?php
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI.php';


$query = "SELECT * FROM special_case WHERE Case_Status = 'active' ORDER BY created_at DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Special Cases - Donation Platform</title>
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-name {
            font-weight: bold;
        }
        
       
        
        .case-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 36px;
        }
        
        .page-description {
            text-align: center;
            margin-bottom: 40px;
            font-size: 18px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cases-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .case-card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .case-card:hover {
            transform: translateY(-5px);
        }
        
        .case-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .case-content {
            padding: 20px;
        }
        
        .case-title {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text);
        }
        
        .case-description {
            margin-bottom: 15px;
            color: var(--light-text);
        }
        
        .progress-container {
            margin-bottom: 15px;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .progress-bar {
            height: 10px;
            background-color: var(--progress-bg);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var(--progress-fill);
            border-radius: 5px;
            transition: width 0.5s ease-in-out;
        }
        
        .case-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .case-amount {
            font-weight: bold;
            color: var(--primary);
        }
        
        .case-deadline {
            color: var(--light-text);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--text);
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            text-decoration: none;
            text-align: center;
        }
        
        .btn:hover {
            background-color: #f0a8a8;
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-full {
            display: block;
            width: 100%;
        }
        
        .no-cases {
            text-align: center;
            padding: 40px;
            grid-column: 1 / -1;
        }
        
        .urgent-tag {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: #ff5252;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .case-card {
            position: relative;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            .logo {
                font-size: 28px;
            }
            
            .container {
                padding: 15px;
            }
            
            .cases-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
   

    <div class="case-container">
        <h1 class="page-title">Special Cases</h1>
        <p class="page-description">These are urgent cases that need immediate support. Your donation can make a significant difference in someone's life today.</p>
        
        <div class="cases-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while($case = $result->fetch_assoc()): 
                    $progress = ($case['current_amount'] / $case['target_amount']) * 100;
                    $is_urgent = $case['urgency'] === 'high';
                ?>
                    <div class="case-card">
                        <?php if ($is_urgent): ?>
                            <div class="urgent-tag">URGENT</div>
                        <?php endif; ?>
                        <img src="<?php echo !empty($case['image']) ? $case['image'] : 'images/case-default.jpg'; ?>" alt="Case Image" class="case-image">
                        <div class="case-content">
                            <h3 class="case-title"><?php echo htmlspecialchars($case['title']); ?></h3>
                            <p class="case-description"><?php echo htmlspecialchars($case['description']); ?></p>
                            
                            <div class="progress-container">
                                <div class="progress-info">
                                    <span>Raised: RM <?php echo number_format($case['current_amount'], 2); ?></span>
                                    <span>Goal: RM <?php echo number_format($case['target_amount'], 2); ?></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo min($progress, 100); ?>%"></div>
                                </div>
                                <div class="progress-info">
                                    <span><?php echo number_format($progress, 1); ?>%</span>
                                    <span><?php echo $case['donors_count']; ?> donors</span>
                                </div>
                            </div>
                            
                            <div class="case-details">
                                <div class="case-amount">Target: RM <?php echo number_format($case['target_amount'], 2); ?></div>
                                <div class="case-deadline">Deadline: <?php echo date('M j, Y', strtotime($case['deadline'])); ?></div>
                            </div>
                            
                            <a href="donate.php?case_id=<?php echo $case['id']; ?>" class="btn btn-full">Donate Now</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-cases">
                    <h3>No special cases at the moment</h3>
                    <p>All urgent cases have been resolved. Check back later for new cases.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>