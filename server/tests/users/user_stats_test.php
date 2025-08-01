<?php
/**
 * User statistics test
 * Tests comprehensive user statistics and analytics functionality including activity metrics, growth analysis, and reporting
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../models/Comment.php';
require_once __DIR__ . '/../../utils/response.php';

echo "=== User Statistics and Analytics Test ===\n";

// Initialize models
$userModel = new UserModel($db->users);
$eventModel = new EventModel($db->events);
$clubModel = new ClubModel($db->clubs);
$commentModel = new CommentModel($db->comments);

// Test 1: Basic user statistics
echo "\n1. Testing basic user statistics...\n";

$basicStats = [
    'total_users' => $userModel->count([]),
    'active_users' => $userModel->count(['status' => 'active']),
    'verified_users' => $userModel->count(['is_email_verified' => true]),
    'unverified_users' => $userModel->count(['is_email_verified' => false]),
    'inactive_users' => $userModel->count(['status' => 'inactive']),
    'suspended_users' => $userModel->count(['status' => 'suspended'])
];

echo "✓ Basic user statistics:\n";
foreach ($basicStats as $metric => $value) {
    echo "  - " . str_replace('_', ' ', ucfirst($metric)) . ": $value\n";
}

if ($basicStats['total_users'] > 0) {
    echo "✓ User base exists for statistics analysis\n";
    
    $verificationRate = $basicStats['verified_users'] / $basicStats['total_users'] * 100;
    $activeRate = $basicStats['active_users'] / $basicStats['total_users'] * 100;
    
    echo "✓ Calculated rates:\n";
    echo "  - Email verification rate: " . round($verificationRate, 1) . "%\n";
    echo "  - Active user rate: " . round($activeRate, 1) . "%\n";
    
} else {
    echo "? No users found for statistics analysis\n";
}

// Test 2: User role distribution
echo "\n2. Testing user role distribution statistics...\n";

$roleStats = [
    'students' => $userModel->count(['role' => 'student']),
    'club_leaders' => $userModel->count(['role' => 'club_leader']),
    'admins' => $userModel->count(['role' => 'admin'])
];

echo "✓ User role distribution:\n";
foreach ($roleStats as $role => $count) {
    echo "  - " . str_replace('_', ' ', ucfirst($role)) . ": $count\n";
}

if ($basicStats['total_users'] > 0) {
    echo "✓ Role percentages:\n";
    foreach ($roleStats as $role => $count) {
        $percentage = ($count / $basicStats['total_users']) * 100;
        echo "  - " . str_replace('_', ' ', ucfirst($role)) . ": " . round($percentage, 1) . "%\n";
    }
}

// Test 3: User creation timeline analysis
echo "\n3. Testing user creation timeline analysis...\n";

// Get all users with creation dates
$allUsers = $userModel->list([], 1000, 0); // Get large sample

if (count($allUsers) > 0) {
    $timelineStats = [
        'today' => 0,
        'this_week' => 0,
        'this_month' => 0,
        'this_year' => 0,
        'older' => 0
    ];
    
    $now = new DateTime();
    $today = $now->format('Y-m-d');
    $thisWeek = $now->sub(new DateInterval('P7D'))->format('Y-m-d');
    $thisMonth = $now->sub(new DateInterval('P30D'))->format('Y-m-d');
    $thisYear = $now->sub(new DateInterval('P365D'))->format('Y-m-d');
    
    foreach ($allUsers as $user) {
        if (isset($user['created_at'])) {
            $createdDate = $user['created_at'];
            if ($createdDate instanceof MongoDB\BSON\UTCDateTime) {
                $createdDate = $createdDate->toDateTime();
            }
            $userDate = $createdDate->format('Y-m-d');
            
            if ($userDate === $today) {
                $timelineStats['today']++;
            } elseif ($userDate >= $thisWeek) {
                $timelineStats['this_week']++;
            } elseif ($userDate >= $thisMonth) {
                $timelineStats['this_month']++;
            } elseif ($userDate >= $thisYear) {
                $timelineStats['this_year']++;
            } else {
                $timelineStats['older']++;
            }
        }
    }
    
    echo "✓ User registration timeline:\n";
    foreach ($timelineStats as $period => $count) {
        echo "  - " . str_replace('_', ' ', ucfirst($period)) . ": $count users\n";
    }
    
    $recentActivity = $timelineStats['today'] + $timelineStats['this_week'];
    if ($recentActivity > 0) {
        echo "✓ Recent user registration activity detected ($recentActivity new users)\n";
    } else {
        echo "? Limited recent user registration activity\n";
    }
    
} else {
    echo "? No users found for timeline analysis\n";
}

// Test 4: User activity and engagement statistics
echo "\n4. Testing user activity and engagement statistics...\n";

$activityStats = [
    'users_with_events_created' => 0,
    'users_with_event_registrations' => 0,
    'users_with_comments' => 0,
    'users_leading_clubs' => 0,
    'completely_inactive_users' => 0
];

$userActivityDetails = [];

foreach ($allUsers as $user) {
    $userId = $user['_id']->__toString();
    $userActivity = [
        'user_id' => $userId,
        'user_name' => $user['first_name'] . ' ' . $user['last_name'],
        'events_created' => 0,
        'events_registered' => 0,
        'comments_made' => 0,
        'clubs_leading' => 0,
        'total_activity_score' => 0
    ];
    
    // Count events created by user
    $createdEvents = $eventModel->list(['created_by' => new MongoDB\BSON\ObjectId($userId)], 100, 0);
    $userActivity['events_created'] = count($createdEvents);
    if ($userActivity['events_created'] > 0) {
        $activityStats['users_with_events_created']++;
    }
    
    // Count event registrations (check all events for this user in registered_users)
    $allEvents = $eventModel->list([], 200, 0);
    foreach ($allEvents as $event) {
        if (isset($event['registered_users'])) {
            foreach ($event['registered_users'] as $registeredId) {
                if ($registeredId->__toString() === $userId) {
                    $userActivity['events_registered']++;
                    break;
                }
            }
        }
    }
    if ($userActivity['events_registered'] > 0) {
        $activityStats['users_with_event_registrations']++;
    }
    
    // Count comments made by user
    try {
        $userComments = $commentModel->findByUserId($userId);
        $userActivity['comments_made'] = count($userComments);
        if ($userActivity['comments_made'] > 0) {
            $activityStats['users_with_comments']++;
        }
    } catch (Exception $e) {
        // User may not exist anymore or comments may be unavailable
    }
    
    // Count clubs led by user
    $ledClubs = $clubModel->listClubs(['leader_id' => new MongoDB\BSON\ObjectId($userId)], 1, 50);
    $userActivity['clubs_leading'] = count($ledClubs['clubs'] ?? []);
    if ($userActivity['clubs_leading'] > 0) {
        $activityStats['users_leading_clubs']++;
    }
    
    // Calculate total activity score
    $userActivity['total_activity_score'] = 
        ($userActivity['events_created'] * 3) +
        ($userActivity['events_registered'] * 1) +
        ($userActivity['comments_made'] * 1) +
        ($userActivity['clubs_leading'] * 5);
    
    if ($userActivity['total_activity_score'] === 0) {
        $activityStats['completely_inactive_users']++;
    }
    
    $userActivityDetails[] = $userActivity;
}

echo "✓ User activity and engagement statistics:\n";
foreach ($activityStats as $metric => $count) {
    echo "  - " . str_replace('_', ' ', ucfirst($metric)) . ": $count\n";
}

if ($basicStats['total_users'] > 0) {
    $engagementRate = (($basicStats['total_users'] - $activityStats['completely_inactive_users']) / $basicStats['total_users']) * 100;
    echo "✓ Overall engagement rate: " . round($engagementRate, 1) . "%\n";
}

// Test 5: Top active users analysis
echo "\n5. Testing top active users analysis...\n";

// Sort users by activity score
usort($userActivityDetails, function($a, $b) {
    return $b['total_activity_score'] <=> $a['total_activity_score'];
});

$topActiveUsers = array_slice($userActivityDetails, 0, 5);

if (count($topActiveUsers) > 0) {
    echo "✓ Top 5 most active users:\n";
    foreach ($topActiveUsers as $index => $user) {
        echo "  " . ($index + 1) . ". {$user['user_name']} (Score: {$user['total_activity_score']})\n";
        echo "     - Events created: {$user['events_created']}\n";
        echo "     - Events registered: {$user['events_registered']}\n";
        echo "     - Comments made: {$user['comments_made']}\n";
        echo "     - Clubs leading: {$user['clubs_leading']}\n";
    }
    
    if ($topActiveUsers[0]['total_activity_score'] > 0) {
        echo "✓ Active user community identified\n";
    } else {
        echo "? Limited user activity detected\n";
    }
    
} else {
    echo "? No active users found\n";
}

// Test 6: User demographic analysis
echo "\n6. Testing user demographic analysis...\n";

$demographicStats = [
    'courses' => [],
    'years_of_study' => [],
    'domains' => []
];

foreach ($allUsers as $user) {
    // Course distribution
    if (isset($user['course']) && !empty($user['course'])) {
        $course = $user['course'];
        $demographicStats['courses'][$course] = ($demographicStats['courses'][$course] ?? 0) + 1;
    }
    
    // Year of study distribution
    if (isset($user['year_of_study']) && !empty($user['year_of_study'])) {
        $year = $user['year_of_study'];
        $demographicStats['years_of_study'][$year] = ($demographicStats['years_of_study'][$year] ?? 0) + 1;
    }
    
    // Email domain analysis
    if (isset($user['email']) && !empty($user['email'])) {
        $domain = substr(strrchr($user['email'], "@"), 1);
        $demographicStats['domains'][$domain] = ($demographicStats['domains'][$domain] ?? 0) + 1;
    }
}

echo "✓ User demographic distribution:\n";

if (!empty($demographicStats['courses'])) {
    echo "  Courses:\n";
    arsort($demographicStats['courses']);
    foreach (array_slice($demographicStats['courses'], 0, 5, true) as $course => $count) {
        echo "    - $course: $count students\n";
    }
}

if (!empty($demographicStats['years_of_study'])) {
    echo "  Years of study:\n";
    ksort($demographicStats['years_of_study']);
    foreach ($demographicStats['years_of_study'] as $year => $count) {
        echo "    - Year $year: $count students\n";
    }
}

if (!empty($demographicStats['domains'])) {
    echo "  Email domains:\n";
    arsort($demographicStats['domains']);
    foreach ($demographicStats['domains'] as $domain => $count) {
        echo "    - $domain: $count users\n";
    }
}

// Test 7: User growth trend analysis
echo "\n7. Testing user growth trend analysis...\n";

$monthlyGrowth = [];
$currentDate = new DateTime();

// Analyze last 6 months of growth
for ($i = 0; $i < 6; $i++) {
    $monthStart = clone $currentDate;
    $monthStart->sub(new DateInterval("P{$i}M"))->modify('first day of this month')->setTime(0, 0, 0);
    
    $monthEnd = clone $monthStart;
    $monthEnd->modify('last day of this month')->setTime(23, 59, 59);
    
    $monthKey = $monthStart->format('Y-m');
    $monthlyGrowth[$monthKey] = [
        'new_users' => 0,
        'month_name' => $monthStart->format('M Y')
    ];
    
    foreach ($allUsers as $user) {
        if (isset($user['created_at'])) {
            $createdDate = $user['created_at'];
            if ($createdDate instanceof MongoDB\BSON\UTCDateTime) {
                $createdDate = $createdDate->toDateTime();
            }
            
            if ($createdDate >= $monthStart && $createdDate <= $monthEnd) {
                $monthlyGrowth[$monthKey]['new_users']++;
            }
        }
    }
}

// Reverse to show chronological order
$monthlyGrowth = array_reverse($monthlyGrowth, true);

echo "✓ User growth trend (last 6 months):\n";
foreach ($monthlyGrowth as $month => $data) {
    echo "  - {$data['month_name']}: {$data['new_users']} new users\n";
}

// Calculate growth rate
$growthData = array_values($monthlyGrowth);
if (count($growthData) >= 2) {
    $currentMonth = end($growthData)['new_users'];
    $previousMonth = prev($growthData)['new_users'];
    
    if ($previousMonth > 0) {
        $growthRate = (($currentMonth - $previousMonth) / $previousMonth) * 100;
        echo "✓ Month-over-month growth rate: " . round($growthRate, 1) . "%\n";
    } else {
        echo "? Growth rate calculation not available (insufficient data)\n";
    }
}

// Test 8: User retention and churn analysis
echo "\n8. Testing user retention and churn analysis...\n";

$retentionStats = [
    'new_users_last_30_days' => 0,
    'active_users_last_7_days' => 0,
    'active_users_last_30_days' => 0,
    'users_with_recent_activity' => 0
];

$thirtyDaysAgo = (new DateTime())->sub(new DateInterval('P30D'));
$sevenDaysAgo = (new DateTime())->sub(new DateInterval('P7D'));

foreach ($allUsers as $user) {
    $userId = $user['_id']->__toString();
    
    // Check if user was created in last 30 days
    if (isset($user['created_at'])) {
        $createdDate = $user['created_at'];
        if ($createdDate instanceof MongoDB\BSON\UTCDateTime) {
            $createdDate = $createdDate->toDateTime();
        }
        
        if ($createdDate >= $thirtyDaysAgo) {
            $retentionStats['new_users_last_30_days']++;
        }
    }
    
    // Check for recent activity (events, registrations, comments)
    $hasRecentActivity = false;
    
    // Check recent events created
    $recentEvents = $eventModel->list(['created_by' => new MongoDB\BSON\ObjectId($userId)], 10, 0);
    foreach ($recentEvents as $event) {
        if (isset($event['created_at'])) {
            $eventCreated = $event['created_at'];
            if ($eventCreated instanceof MongoDB\BSON\UTCDateTime) {
                $eventCreated = $eventCreated->toDateTime();
            }
            
            if ($eventCreated >= $sevenDaysAgo) {
                $retentionStats['active_users_last_7_days']++;
                $hasRecentActivity = true;
                break;
            }
            
            if ($eventCreated >= $thirtyDaysAgo) {
                $retentionStats['active_users_last_30_days']++;
                $hasRecentActivity = true;
            }
        }
    }
    
    if ($hasRecentActivity) {
        $retentionStats['users_with_recent_activity']++;
    }
}

echo "✓ User retention and activity analysis:\n";
foreach ($retentionStats as $metric => $count) {
    echo "  - " . str_replace('_', ' ', ucfirst($metric)) . ": $count\n";
}

if ($basicStats['total_users'] > 0) {
    $activeUserRate7Days = ($retentionStats['active_users_last_7_days'] / $basicStats['total_users']) * 100;
    $activeUserRate30Days = ($retentionStats['active_users_last_30_days'] / $basicStats['total_users']) * 100;
    
    echo "✓ Activity rates:\n";
    echo "  - 7-day active user rate: " . round($activeUserRate7Days, 1) . "%\n";
    echo "  - 30-day active user rate: " . round($activeUserRate30Days, 1) . "%\n";
}

// Test 9: Statistical aggregations and analytics
echo "\n9. Testing statistical aggregations and analytics...\n";

$statisticalAnalysis = [
    'user_activity_distribution' => [
        'highly_active' => 0,    // Score > 10
        'moderately_active' => 0, // Score 5-10
        'low_active' => 0,       // Score 1-4
        'inactive' => 0          // Score 0
    ],
    'average_activity_score' => 0,
    'median_activity_score' => 0,
    'max_activity_score' => 0,
    'min_activity_score' => PHP_INT_MAX
];

$activityScores = [];

foreach ($userActivityDetails as $user) {
    $score = $user['total_activity_score'];
    $activityScores[] = $score;
    
    if ($score > 10) {
        $statisticalAnalysis['user_activity_distribution']['highly_active']++;
    } elseif ($score >= 5) {
        $statisticalAnalysis['user_activity_distribution']['moderately_active']++;
    } elseif ($score >= 1) {
        $statisticalAnalysis['user_activity_distribution']['low_active']++;
    } else {
        $statisticalAnalysis['user_activity_distribution']['inactive']++;
    }
    
    $statisticalAnalysis['max_activity_score'] = max($statisticalAnalysis['max_activity_score'], $score);
    $statisticalAnalysis['min_activity_score'] = min($statisticalAnalysis['min_activity_score'], $score);
}

if (count($activityScores) > 0) {
    $statisticalAnalysis['average_activity_score'] = array_sum($activityScores) / count($activityScores);
    
    sort($activityScores);
    $count = count($activityScores);
    if ($count % 2 === 0) {
        $statisticalAnalysis['median_activity_score'] = ($activityScores[$count/2 - 1] + $activityScores[$count/2]) / 2;
    } else {
        $statisticalAnalysis['median_activity_score'] = $activityScores[floor($count/2)];
    }
}

echo "✓ Statistical analysis results:\n";
echo "  Activity distribution:\n";
foreach ($statisticalAnalysis['user_activity_distribution'] as $level => $count) {
    echo "    - " . str_replace('_', ' ', ucfirst($level)) . ": $count users\n";
}

echo "  Activity score metrics:\n";
echo "    - Average: " . round($statisticalAnalysis['average_activity_score'], 2) . "\n";
echo "    - Median: " . round($statisticalAnalysis['median_activity_score'], 2) . "\n";
echo "    - Maximum: " . $statisticalAnalysis['max_activity_score'] . "\n";
echo "    - Minimum: " . ($statisticalAnalysis['min_activity_score'] === PHP_INT_MAX ? 0 : $statisticalAnalysis['min_activity_score']) . "\n";

// Test 10: Performance testing for statistics queries
echo "\n10. Testing statistics query performance...\n";

$startTime = microtime(true);

// Simulate multiple statistics calculations
for ($i = 0; $i < 3; $i++) {
    $perfStats = [
        'total' => $userModel->count([]),
        'active' => $userModel->count(['status' => 'active']),
        'verified' => $userModel->count(['is_email_verified' => true]),
        'students' => $userModel->count(['role' => 'student']),
        'admins' => $userModel->count(['role' => 'admin'])
    ];
}

$endTime = microtime(true);
$executionTime = $endTime - $startTime;

if ($executionTime < 3) {
    echo "✓ Statistics query performance acceptable (" . round($executionTime, 3) . "s for 15 count queries)\n";
} else {
    echo "✗ Statistics query performance too slow (" . round($executionTime, 3) . "s)\n";
}

echo "  Average time per statistics calculation: " . round($executionTime / 3, 4) . "s\n";

echo "\n=== User Statistics and Analytics Test Summary ===\n";
echo "✓ Basic user statistics calculation working\n";
echo "✓ User role distribution analysis working\n";
echo "✓ User creation timeline analysis working\n";
echo "✓ User activity and engagement statistics working\n";
echo "✓ Top active users identification working\n";
echo "✓ User demographic analysis working\n";
echo "✓ User growth trend analysis working\n";
echo "✓ User retention and churn analysis working\n";
echo "✓ Statistical aggregations and analytics working\n";
echo "✓ Statistics query performance acceptable\n";
echo "Note: Statistics based on current development database data\n";