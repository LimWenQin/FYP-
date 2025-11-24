<?php
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI.php';


$query = "SELECT * FROM Activity ORDER BY Activity_Date DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activities - Donation Platform</title>
    <style>
        :root {
            --primary: #F6B8B8;
            --secondary: #FFF5E4;
            --text: #4A4A4A;
            --light-text: #777777;
            --white: #FFFFFF;
            --shadow: rgba(0, 0, 0, 0.1);
            --success: #4CAF50;
            --ongoing: #FF9800;
            --upcoming: #2196F3;
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
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary);
            padding: 20px 50px;
            box-shadow: 0 2px 6px var(--shadow);
        }
        
        .logo {
            font-weight: bold;
            font-size: 36px;
            color: var(--text);
        }
        
        .header .function-links a {
            margin-left: 15px;
            text-decoration: none;
            font-size: 20px;
            color: var(--text);
            font-weight: bold;
            transition: opacity 0.3s;
        }
        
        .header .function-links a:hover {
            opacity: 0.8;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-name {
            font-weight: bold;
        }
        
        
        .activity-container {
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
        
        .filters {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            background-color: var(--white);
            border: 2px solid var(--primary);
            padding: 8px 20px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .filter-btn.active, .filter-btn:hover {
            background-color: var(--primary);
        }
        
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .activity-card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .activity-card:hover {
            transform: translateY(-5px);
        }
        
        .activity-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .activity-content {
            padding: 20px;
        }
        
        .activity-date {
            color: var(--light-text);
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .activity-title {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text);
        }
        
        .activity-details {
            margin-bottom: 15px;
            color: var(--light-text);
        }
        
        .activity-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .status-completed {
            background-color: rgba(76, 175, 80, 0.2);
            color: var(--success);
        }
        
        .status-ongoing {
            background-color: rgba(255, 152, 0, 0.2);
            color: var(--ongoing);
        }
        
        .status-upcoming {
            background-color: rgba(33, 150, 243, 0.2);
            color: var(--upcoming);
        }
        
        .activity-amount {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 15px;
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
        
        .no-activities {
            text-align: center;
            padding: 40px;
            grid-column: 1 / -1;
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
            
            .activities-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>

    <div class="activity-container">
        <h1 class="page-title">Our Activities</h1>
        <p class="page-description">Discover our latest activities and events. Join us in making a difference through various community engagement programs and fundraising events.</p>
        
        <div class="filters">
            <button class="filter-btn active" data-filter="all">All Activities</button>
            <button class="filter-btn" data-filter="upcoming">Upcoming</button>
            <button class="filter-btn" data-filter="ongoing">Ongoing</button>
            <button class="filter-btn" data-filter="completed">Completed</button>
        </div>
        
        <div class="activities-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while($activity = $result->fetch_assoc()): ?>
                    <div class="activity-card" data-status="<?php echo strtolower($activity['Status']); ?>">
                        <img src="<?php echo !empty($activity['Picture']) ? $activity['Picture'] : 'images/activity-default.jpg'; ?>" alt="Activity Image" class="activity-image">
                        <div class="activity-content">
                            <div class="activity-date"><?php echo date('F j, Y', strtotime($activity['Date'])); ?></div>
                            <h3 class="activity-title"><?php echo htmlspecialchars($activity['Details']); ?></h3>
                            <div class="activity-status status-<?php echo strtolower($activity['Status']); ?>"><?php echo $activity['Status']; ?></div>
                            <div class="activity-amount">Raised: RM <?php echo number_format($activity['Get_Amount'], 2); ?></div>
                            <a href="activity_detail.php?id=<?php echo $activity['ID']; ?>" class="btn btn-full">View Details</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-activities">
                    <h3>No activities found</h3>
                    <p>Check back later for upcoming activities and events.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Filter activities
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const activityCards = document.querySelectorAll('.activity-card');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    
                    activityCards.forEach(card => {
                        if (filter === 'all' || card.getAttribute('data-status') === filter) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>