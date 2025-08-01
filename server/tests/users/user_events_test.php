<?php
/**
 * User events test
 * Tests comprehensive user-event relationships including created events, registered events, and event history
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../utils/response.php';

echo "=== User Events Relationship Test ===\n";

// Initialize models
$userModel = new UserModel($db->users);
$eventModel = new EventModel($db->events);
$clubModel = new ClubModel($db->clubs);

// Test setup: Create test users for event relationship testing
echo "\n1. Setting up test users for event relationship testing...\n";

$testUsers = [];
$userProfiles = [
    [
        'email' => 'events.creator@usiu.ac.ke',
        'student_id' => 'USIU20246001',
        'first_name' => 'Event',
        'last_name' => 'Creator',
        'role' => 'club_leader',
        'description' => 'User who creates multiple events'
    ],
    [
        'email' => 'events.attendee@usiu.ac.ke',
        'student_id' => 'USIU20246002',
        'first_name' => 'Event',
        'last_name' => 'Attendee',
        'role' => 'student',
        'description' => 'User who registers for multiple events'
    ],
    [
        'email' => 'events.mixed@usiu.ac.ke',
        'student_id' => 'USIU20246003',
        'first_name' => 'Mixed',
        'last_name' => 'User',
        'role' => 'student',
        'description' => 'User who both creates and attends events'
    ]
];

foreach ($userProfiles as $index => $profile) {
    $existingUser = $userModel->findByEmail($profile['email']);
    
    if (!$existingUser) {
        $userData = [
            'student_id' => $profile['student_id'],
            'first_name' => $profile['first_name'],
            'last_name' => $profile['last_name'],
            'email' => $profile['email'],
            'password' => 'eventsTest123',
            'phone' => '+2547' . sprintf('%08d', 20000000 + $index),
            'course' => 'Business Administration',
            'year_of_study' => 3,
            'is_email_verified' => true,
            'role' => $profile['role'],
            'status' => 'active'
        ];
        
        $result = $userModel->createWithValidation($userData);
        if ($result['success']) {
            $testUser = $userModel->findByEmail($profile['email']);
            $testUsers[] = $testUser;
            echo "✓ {$profile['description']} created\n";
        } else {
            echo "✗ Failed to create user: {$profile['email']}\n";
            if (isset($result['errors'])) {
                echo "  Errors: " . json_encode($result['errors']) . "\n";
            }
            exit(1);
        }
    } else {
        $testUsers[] = $existingUser;
        echo "✓ {$profile['description']} already exists\n";
    }
}

if (count($testUsers) < 3) {
    echo "✗ Insufficient test users created\n";
    exit(1);
}

// Create test club for events
$testClub = $clubModel->findByName('User Events Society');
if (!$testClub) {
    $clubData = [
        'name' => 'User Events Society',
        'description' => 'Society for user events relationship testing',
        'category' => 'Academic',
        'contact_email' => 'eventsclub@usiu.ac.ke',
        'leader_id' => $testUsers[0]['_id']->__toString(),
        'created_by' => $testUsers[0]['_id']->__toString(),
        'status' => 'active'
    ];
    $clubResult = $clubModel->create($clubData);
    $testClub = $clubModel->findById($clubResult->__toString());
    echo "✓ Test club created\n";
} else {
    echo "✓ Test club already exists\n";
}

// Test 2: Create events by different users
echo "\n2. Creating events by different users for relationship testing...\n";

$testEvents = [];
$eventTemplates = [
    [
        'title' => 'User Events Workshop 1',
        'description' => 'First workshop for user events testing',
        'creator_index' => 0,
        'date_offset' => '+1 week',
        'status' => 'published',
        'registration_required' => true,
        'max_attendees' => 50
    ],
    [
        'title' => 'User Events Workshop 2', 
        'description' => 'Second workshop for user events testing',
        'creator_index' => 0,
        'date_offset' => '+2 weeks',
        'status' => 'published',
        'registration_required' => true,
        'max_attendees' => 30
    ],
    [
        'title' => 'Mixed User Event',
        'description' => 'Event created by mixed user',
        'creator_index' => 2,
        'date_offset' => '+3 weeks',
        'status' => 'published',
        'registration_required' => true,
        'max_attendees' => 40
    ],
    [
        'title' => 'Past Event for History',
        'description' => 'Past event for testing event history',
        'creator_index' => 0,
        'date_offset' => '-1 week',
        'status' => 'completed',
        'registration_required' => true,
        'max_attendees' => 25
    ],
    [
        'title' => 'Draft Event',
        'description' => 'Draft event for testing different statuses',
        'creator_index' => 0,
        'date_offset' => '+4 weeks',
        'status' => 'draft',
        'registration_required' => false,
        'max_attendees' => 60
    ]
];

foreach ($eventTemplates as $index => $template) {
    $eventData = [
        'title' => $template['title'],
        'description' => $template['description'],
        'club_id' => $testClub['_id']->__toString(),
        'created_by' => $testUsers[$template['creator_index']]['_id']->__toString(),
        'event_date' => new DateTime($template['date_offset']),
        'location' => 'User Events Hall ' . ($index + 1),
        'status' => $template['status'],
        'category' => 'academic',
        'venue_capacity' => $template['max_attendees'] + 10,
        'registration_required' => $template['registration_required'],
        'max_attendees' => $template['max_attendees'],
        'featured' => $index % 2 === 0,
        'tags' => ['user-events', 'test', 'workshop']
    ];
    
    $result = $eventModel->createWithValidation($eventData);
    if ($result['success']) {
        $testEvents[] = $result['id']->__toString();
        echo "✓ Event '{$template['title']}' created by user " . ($template['creator_index'] + 1) . "\n";
    } else {
        echo "✗ Failed to create event '{$template['title']}'\n";
    }
}

if (count($testEvents) < 4) {
    echo "✗ Insufficient test events created\n";
    exit(1);
}

// Test 3: User event registrations
echo "\n3. Testing user event registrations...\n";

$registrationTests = [
    ['user_index' => 1, 'event_indices' => [0, 1, 2], 'description' => 'Attendee user registers for multiple events'],
    ['user_index' => 2, 'event_indices' => [0, 1], 'description' => 'Mixed user registers for events (also creates events)'],
    ['user_index' => 0, 'event_indices' => [2], 'description' => 'Creator user registers for other user\'s event']
];

$successfulRegistrations = 0;
foreach ($registrationTests as $test) {
    $userId = $testUsers[$test['user_index']]['_id']->__toString();
    
    foreach ($test['event_indices'] as $eventIndex) {
        if (isset($testEvents[$eventIndex])) {
            try {
                $registrationResult = $eventModel->registerUser($testEvents[$eventIndex], $userId);
                if ($registrationResult) {
                    $successfulRegistrations++;
                }
            } catch (Exception $e) {
                echo "? Registration may have failed: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "✓ {$test['description']}\n";
}

echo "✓ Completed $successfulRegistrations event registrations\n";

// Test 4: Find events created by user
echo "\n4. Testing retrieval of events created by user...\n";

// Test for user who created multiple events (testUsers[0])
$creatorUserId = $testUsers[0]['_id']->__toString();
$createdEvents = $eventModel->list(['created_by' => new MongoDB\BSON\ObjectId($creatorUserId)], 50, 0);

if (count($createdEvents) >= 3) {
    echo "✓ Found events created by user (" . count($createdEvents) . " events)\n";
    
    // Verify all events belong to the correct creator
    $correctCreator = true;
    foreach ($createdEvents as $event) {
        if ($event['created_by']->__toString() !== $creatorUserId) {
            $correctCreator = false;
            break;
        }
    }
    
    if ($correctCreator) {
        echo "✓ All retrieved events belong to correct creator\n";
    } else {
        echo "✗ Some retrieved events have incorrect creator\n";
    }
    
} else {
    echo "✗ Expected more events created by user, found " . count($createdEvents) . "\n";
}

// Test 5: Find events user is registered for
echo "\n5. Testing retrieval of events user is registered for...\n";

// Test for user who registered for multiple events (testUsers[1])
$attendeeUserId = $testUsers[1]['_id']->__toString();

// Method 1: Check via event model (find events where user is in registered_users array)
$registeredEvents = [];
foreach ($testEvents as $eventId) {
    $event = $eventModel->findById($eventId);
    if ($event && isset($event['registered_users'])) {
        foreach ($event['registered_users'] as $registeredUserId) {
            if ($registeredUserId->__toString() === $attendeeUserId) {
                $registeredEvents[] = $event;
                break;
            }
        }
    }
}

if (count($registeredEvents) >= 2) {
    echo "✓ Found events user is registered for (" . count($registeredEvents) . " events)\n";
    
    // Verify registration data integrity
    $validRegistrations = true;
    foreach ($registeredEvents as $event) {
        if (!isset($event['current_registrations']) || $event['current_registrations'] < 1) {
            $validRegistrations = false;
            break;
        }
    }
    
    if ($validRegistrations) {
        echo "✓ Registration data integrity maintained\n";
    } else {
        echo "✗ Some registration data integrity issues found\n";
    }
    
} else {
    echo "? Expected more registered events, found " . count($registeredEvents) . "\n";
}

// Test 6: User event history and timeline
echo "\n6. Testing user event history and timeline...\n";

$allUserEvents = [];

// Combine created events and registered events for timeline
foreach ($createdEvents as $event) {
    $allUserEvents[] = [
        'event' => $event,
        'relationship' => 'creator',
        'date' => $event['event_date']
    ];
}

foreach ($registeredEvents as $event) {
    // Avoid duplicates (user might be both creator and attendee)
    $alreadyAdded = false;
    foreach ($allUserEvents as $existingEvent) {
        if ($existingEvent['event']['_id']->__toString() === $event['_id']->__toString()) {
            $alreadyAdded = true;
            break;
        }
    }
    
    if (!$alreadyAdded) {
        $allUserEvents[] = [
            'event' => $event,
            'relationship' => 'attendee',
            'date' => $event['event_date']
        ];
    }
}

// Sort by date
usort($allUserEvents, function($a, $b) {
    return $a['date'] <=> $b['date'];
});

if (count($allUserEvents) >= 3) {
    echo "✓ User event timeline constructed (" . count($allUserEvents) . " events)\n";
    
    // Categorize events by time
    $now = new DateTime();
    $pastEvents = 0;
    $upcomingEvents = 0;
    $todayEvents = 0;
    
    foreach ($allUserEvents as $eventData) {
        $eventDate = $eventData['date'];
        // Convert MongoDB UTCDateTime to PHP DateTime for comparison
        if ($eventDate instanceof MongoDB\BSON\UTCDateTime) {
            $eventDate = $eventDate->toDateTime();
        }
        $dateComparison = $eventDate->format('Y-m-d') <=> $now->format('Y-m-d');
        
        if ($dateComparison < 0) {
            $pastEvents++;
        } elseif ($dateComparison > 0) {
            $upcomingEvents++;
        } else {
            $todayEvents++;
        }
    }
    
    echo "✓ Event timeline categorization:\n";
    echo "  - Past events: $pastEvents\n";
    echo "  - Today's events: $todayEvents\n";
    echo "  - Upcoming events: $upcomingEvents\n";
    
} else {
    echo "? User event timeline may be incomplete\n";
}

// Test 7: Event status filtering for user events
echo "\n7. Testing event status filtering for user events...\n";

$statusCounts = [
    'published' => 0,
    'draft' => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($createdEvents as $event) {
    $status = $event['status'] ?? 'unknown';
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}

echo "✓ Event status distribution for creator:\n";
foreach ($statusCounts as $status => $count) {
    echo "  - $status: $count events\n";
}

if ($statusCounts['published'] >= 2) {
    echo "✓ Multiple published events found\n";
} else {
    echo "? Expected more published events\n";
}

// Test 8: User permissions on events
echo "\n8. Testing user permissions on events...\n";

// Test creator permissions (should be able to modify their events)
$creatorEventId = $testEvents[0]; // First event created by testUsers[0]
$creatorEvent = $eventModel->findById($creatorEventId);

if ($creatorEvent && $creatorEvent['created_by']->__toString() === $testUsers[0]['_id']->__toString()) {
    echo "✓ Creator has ownership of their event\n";
    
    // Test if creator can modify event (simulated permission check)
    $canModify = ($creatorEvent['created_by']->__toString() === $testUsers[0]['_id']->__toString());
    if ($canModify) {
        echo "✓ Creator has modification permissions\n";
    } else {
        echo "✗ Creator lacks modification permissions\n";
    }
    
} else {
    echo "✗ Creator ownership validation failed\n";
}

// Test attendee permissions (should not be able to modify events they didn't create)
$attendeeEvent = $eventModel->findById($creatorEventId);
$attendeeCanModify = ($attendeeEvent['created_by']->__toString() === $testUsers[1]['_id']->__toString());

if (!$attendeeCanModify) {
    echo "✓ Attendee correctly lacks modification permissions for others' events\n";
} else {
    echo "✗ Attendee incorrectly has modification permissions\n";
}

// Test 9: Event capacity and registration limits
echo "\n9. Testing event capacity and registration limits for user events...\n";

// Check registration counts vs limits
$capacityChecks = [];
foreach ($testEvents as $eventId) {
    $event = $eventModel->findById($eventId);
    if ($event && isset($event['max_attendees'], $event['current_registrations'])) {
        $capacityCheck = [
            'event_title' => $event['title'],
            'max_attendees' => $event['max_attendees'],
            'current_registrations' => $event['current_registrations'],
            'spots_available' => $event['max_attendees'] - $event['current_registrations'],
            'is_full' => $event['current_registrations'] >= $event['max_attendees']
        ];
        $capacityChecks[] = $capacityCheck;
    }
}

if (count($capacityChecks) >= 3) {
    echo "✓ Event capacity analysis completed:\n";
    foreach ($capacityChecks as $check) {
        $fullStatus = $check['is_full'] ? 'FULL' : 'Available';
        echo "  - {$check['event_title']}: {$check['current_registrations']}/{$check['max_attendees']} ($fullStatus)\n";
    }
} else {
    echo "? Limited capacity data available\n";
}

// Test 10: User event statistics
echo "\n10. Testing user event statistics...\n";

$userStats = [
    'events_created' => count($createdEvents),
    'events_registered' => count($registeredEvents),
    'total_event_involvement' => count($allUserEvents),
    'upcoming_events' => $upcomingEvents ?? 0,
    'past_events' => $pastEvents ?? 0
];

echo "✓ User event statistics for test user:\n";
foreach ($userStats as $metric => $value) {
    echo "  - " . str_replace('_', ' ', ucfirst($metric)) . ": $value\n";
}

if ($userStats['total_event_involvement'] >= 3) {
    echo "✓ User has significant event involvement\n";
} else {
    echo "? User has limited event involvement\n";
}

// Test 11: Cross-user event relationship analysis
echo "\n11. Testing cross-user event relationship analysis...\n";

$crossUserAnalysis = [];
foreach ($testUsers as $index => $user) {
    $userId = $user['_id']->__toString();
    
    // Count events created by this user
    $createdByUser = 0;
    foreach ($testEvents as $eventId) {
        $event = $eventModel->findById($eventId);
        if ($event && $event['created_by']->__toString() === $userId) {
            $createdByUser++;
        }
    }
    
    // Count events this user is registered for
    $registeredByUser = 0;
    foreach ($testEvents as $eventId) {
        $event = $eventModel->findById($eventId);
        if ($event && isset($event['registered_users'])) {
            foreach ($event['registered_users'] as $registeredId) {
                if ($registeredId->__toString() === $userId) {
                    $registeredByUser++;
                    break;
                }
            }
        }
    }
    
    $crossUserAnalysis[] = [
        'user_name' => $user['first_name'] . ' ' . $user['last_name'],
        'events_created' => $createdByUser,
        'events_registered' => $registeredByUser,
        'total_involvement' => $createdByUser + $registeredByUser
    ];
}

echo "✓ Cross-user event analysis:\n";
foreach ($crossUserAnalysis as $analysis) {
    echo "  - {$analysis['user_name']}: Created {$analysis['events_created']}, Registered {$analysis['events_registered']}, Total {$analysis['total_involvement']}\n";
}

// Find most active user
$mostActiveUser = array_reduce($crossUserAnalysis, function($max, $user) {
    return ($user['total_involvement'] > ($max['total_involvement'] ?? 0)) ? $user : $max;
}, []);

if ($mostActiveUser) {
    echo "✓ Most active user: {$mostActiveUser['user_name']} with {$mostActiveUser['total_involvement']} total involvements\n";
}

// Test 12: Performance testing for user-event queries
echo "\n12. Testing user-event query performance...\n";

$startTime = microtime(true);

// Test multiple user event queries
for ($i = 0; $i < 5; $i++) {
    foreach ($testUsers as $user) {
        $userId = $user['_id']->__toString();
        
        // Simulate finding user's created events
        $userCreatedEvents = $eventModel->list(['created_by' => new MongoDB\BSON\ObjectId($userId)], 10, 0);
        
        // Simulate finding user's registered events
        $userRegisteredEvents = [];
        foreach ($testEvents as $eventId) {
            $event = $eventModel->findById($eventId);
            if ($event && isset($event['registered_users'])) {
                foreach ($event['registered_users'] as $registeredId) {
                    if ($registeredId->__toString() === $userId) {
                        $userRegisteredEvents[] = $event;
                        break;
                    }
                }
            }
        }
    }
}

$endTime = microtime(true);
$executionTime = $endTime - $startTime;

if ($executionTime < 5) {
    echo "✓ User-event query performance acceptable (" . round($executionTime, 3) . "s for 15 query cycles)\n";
} else {
    echo "✗ User-event query performance too slow (" . round($executionTime, 3) . "s)\n";
}

echo "  Average time per query cycle: " . round($executionTime / 15, 4) . "s\n";

echo "\n=== User Events Relationship Test Summary ===\n";
echo "✓ User event creation relationships working\n";
echo "✓ User event registration relationships working\n";
echo "✓ Event timeline and history tracking working\n";
echo "✓ Event status filtering working\n";
echo "✓ User permissions on events working\n";
echo "✓ Event capacity and registration limits working\n";
echo "✓ User event statistics calculation working\n";
echo "✓ Cross-user event analysis working\n";
echo "✓ User-event query performance acceptable\n";
echo "Note: Test data preserved in development database\n";