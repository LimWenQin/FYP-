<?php
include 'dataconnection.php';
include 'header_function.php';
include 'header_UI.php';

$public_activities = [];

if (empty($public_activities)) {
    $public_activities = [
        [
            'Activity_Title' => 'Charity Gala 2024',
            'Activity_Description' => 'Annual fundraising event for orphan education programs',
            'Activity_Type' => 'Event',
            'Activity_Date' => date('Y-m-d H:i:s', strtotime('+2 weeks')),
            'Location' => 'Kuala Lumpur Convention Center',
            'Participants' => 250
        ],
        [
            'Activity_Title' => 'Food Drive for Elderly',
            'Activity_Description' => 'Community initiative to provide essential supplies to elderly in need',
            'Activity_Type' => 'Community Service',
            'Activity_Date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'Location' => 'Multiple locations across KL',
            'Participants' => 120
        ],
        [
            'Activity_Title' => 'Disaster Relief Training',
            'Activity_Description' => 'Training session for volunteers on emergency response protocols',
            'Activity_Type' => 'Training',
            'Activity_Date' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'Location' => 'Red Crescent Headquarters',
            'Participants' => 80
        ],
        [
            'Activity_Title' => 'School Renovation Project',
            'Activity_Description' => 'Volunteer project to renovate facilities at rural school',
            'Activity_Type' => 'Infrastructure',
            'Activity_Date' => date('Y-m-d H:i:s', strtotime('-3 weeks')),
            'Location' => 'Kampung Sungai Lembing',
            'Participants' => 45
        ],
        [
            'Activity_Title' => 'Health Screening Camp',
            'Activity_Description' => 'Free medical check-ups for underprivileged communities',
            'Activity_Type' => 'Healthcare',
            'Activity_Date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'Location' => 'Community Hall, Petaling Jaya',
            'Participants' => 300
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Activities - Donor Platform</title>
    <style>
        :root {
            --primary: #F6B8B8;
            --secondary: #FFF5E4;
            --text: #4A4A4A;
            --light-text: #777777;
            --white: #FFFFFF;
            --shadow: rgba(0, 0, 0, 0.1);
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
        
        .activity-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .activity-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            background-color: var(--secondary);
            border: 1px solid var(--primary);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-btn.active {
            background-color: var(--primary);
            color: var(--text);
            font-weight: bold;
        }
        
        .filter-btn:hover {
            background-color: var(--primary);
        }
        
        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: var(--primary);
        }
        
        .activity-item {
            position: relative;
            margin-bottom: 25px;
            padding: 20px;
            background-color: var(--secondary);
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            transition: transform 0.3s;
        }
        
        .activity-item:hover {
            transform: translateX(5px);
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 25px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--primary);
        }
        
        .activity-type {
            display: inline-block;
            padding: 4px 8px;
            background-color: var(--primary);
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .activity-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
            color: var(--text);
        }
        
        .activity-description {
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .activity-details {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .activity-detail {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--light-text);
            font-size: 14px;
        }
        
        .activity-date {
            color: var(--light-text);
            font-size: 14px;
        }
        
        .join-btn {
            background-color: var(--primary);
            color: var(--text);
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            margin-top: 10px;
            display: inline-block;
        }
        
        .join-btn:hover {
            background-color: #f0a8a8;
        }
        
        .no-activities {
            text-align: center;
            padding: 40px;
            color: var(--light-text);
        }
        
        @media (max-width: 768px) {
            .page-container {
                padding: 15px;
            }
            
            .activity-filters {
                justify-content: center;
            }
            
            .activity-details {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    

    <div class="page-container">
        <h1 class="page-title">Platform Activities & Events</h1>
        
        <div class="activity-container">
            <div class="activity-filters">
                <button class="filter-btn active" data-filter="all">All Activities</button>
                <button class="filter-btn" data-filter="Event">Events</button>
                <button class="filter-btn" data-filter="Community Service">Community Service</button>
                <button class="filter-btn" data-filter="Training">Training</button>
                <button class="filter-btn" data-filter="Healthcare">Healthcare</button>
                <button class="filter-btn" data-filter="Infrastructure">Infrastructure</button>
            </div>
            
            <?php if (!empty($public_activities)): ?>
                <div class="activity-timeline">
                    <?php foreach ($public_activities as $activity): 
                        $date_time = isset($activity['Activity_Date']) ? strtotime($activity['Activity_Date']) : time();
                        $activity_type = isset($activity['Activity_Type']) ? $activity['Activity_Type'] : 'General';
                        $is_upcoming = $date_time > time();
                    ?>
                        <div class="activity-item" data-type="<?php echo htmlspecialchars($activity_type); ?>">
                            <div class="activity-type"><?php echo htmlspecialchars($activity_type); ?></div>
                            <div class="activity-title"><?php echo htmlspecialchars($activity['Activity_Title']); ?></div>
                            <div class="activity-description"><?php echo htmlspecialchars($activity['Activity_Description']); ?></div>
                            
                            <div class="activity-details">
                                <?php if (isset($activity['Location'])): ?>
                                    <div class="activity-detail">
                                        <span>📍</span>
                                        <span><?php echo htmlspecialchars($activity['Location']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($activity['Participants'])): ?>
                                    <div class="activity-detail">
                                        <span>👥</span>
                                        <span><?php echo $activity['Participants']; ?> participants</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="activity-date">
                                <?php 
                                if ($is_upcoming) {
                                    echo 'Upcoming: ' . date('F j, Y \a\t g:i A', $date_time);
                                } else {
                                    echo 'Completed: ' . date('F j, Y', $date_time);
                                }
                                ?>
                            </div>
                            
                            <?php if ($is_upcoming): ?>
                                <button class="join-btn">Join This Activity</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-activities">
                    <h2>No activities scheduled at the moment</h2>
                    <p>Check back later for upcoming platform activities and events.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const activityItems = document.querySelectorAll('.activity-item');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const filterType = this.getAttribute('data-filter');

                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    activityItems.forEach(item => {
                        const itemType = item.getAttribute('data-type');
                        
                        if (filterType === 'all' || itemType === filterType) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
            
            // Add join button functionality
            const joinButtons = document.querySelectorAll('.join-btn');
            joinButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const activityTitle = this.closest('.activity-item').querySelector('.activity-title').textContent;
                    if (confirm(`Are you sure you want to join "${activityTitle}"?`)) {
                        alert('Thank you for your interest! Our team will contact you with more details.');
                        this.textContent = 'Registration Submitted';
                        this.disabled = true;
                    }
                });
            });
        });
    </script>
</body>
</html>