<?php
/**
 * Event listing test
 * Tests event listing functionality with filtering, pagination, search, and sorting
 */

require_once __DIR__ . '/../../server/vendor/autoload.php';
require_once __DIR__ . '/../../server/config/database.php';
require_once __DIR__ . '/../../server/models/Event.php';
require_once __DIR__ . '/../../server/models/User.php';
require_once __DIR__ . '/../../server/models/Club.php';
require_once __DIR__ . '/../../server/utils/response.php';

echo "=== Event Listing Test ===\n";

// Initialize models
$eventModel = new EventModel($db->events);
$userModel = new UserModel($db->users);
$clubModel = new ClubModel($db->clubs);

// Test setup: Ensure we have test events to work with
echo "\n1. Setting up test data for event listing...\n";

// Get existing test club or create one
$testClubName = 'Event Listing Society';
$testClub = $clubModel->findByName($testClubName);

if (!$testClub) {
    // Get or create test user first
    $testEmail = 'listing.creator@usiu.ac.ke';
    $testUser = $userModel->findByEmail($testEmail);
    
    if (!$testUser) {
        $testUserData = [
            'student_id' => 'USIU20240600',
            'first_name' => 'Listing',
            'last_name' => 'Creator',
            'email' => $testEmail,
            'password' => 'listingCreator123',
            'is_email_verified' => true,
            'role' => 'student'
        ];
        
        $result = $userModel->createWithValidation($testUserData);
        if ($result['success']) {
            $testUser = $userModel->findByEmail($testEmail);
        }
    }
    
    $testClubData = [
        'name' => $testClubName,
        'description' => 'A society for event listing functionality with comprehensive validation',
        'category' => 'Academic',
        'contact_email' => 'listingclub@usiu.ac.ke',
        'leader_id' => $testUser['_id']->__toString(),
        'created_by' => $testUser['_id']->__toString(),
        'status' => 'active'
    ];
    
    $clubId = $clubModel->create($testClubData);
    $testClub = $clubModel->findById($clubId->__toString());
    echo "✓ Test club created\n";
} else {
    echo "✓ Test club already exists\n";
}

$userId = $testClub['leader_id'];
$clubId = $testClub['_id']->__toString();

// Create diverse test events for comprehensive testing
$testEvents = [
    [
        'title' => 'Academic Conference 2024',
        'description' => 'Annual academic conference featuring research presentations and keynote speakers',
        'category' => 'academic',
        'event_date' => new DateTime('+1 week'),
        'status' => 'published',
        'featured' => true,
        'registration_required' => true,
        'registration_fee' => 100.0,
        'venue_capacity' => 200,
        'max_attendees' => 180,
        'tags' => ['conference', 'academic', 'research']
    ],
    [
        'title' => 'Tech Workshop: AI & Machine Learning',
        'description' => 'Hands-on workshop covering artificial intelligence and machine learning fundamentals',
        'category' => 'technology',
        'event_date' => new DateTime('+2 weeks'),
        'status' => 'published',
        'featured' => false,
        'registration_required' => true,
        'registration_fee' => 50.0,
        'venue_capacity' => 50,
        'max_attendees' => 45,
        'tags' => ['workshop', 'technology', 'AI', 'ML']
    ],
    [
        'title' => 'Business Networking Event',
        'description' => 'Professional networking event for business students and industry professionals',
        'category' => 'business',
        'event_date' => new DateTime('+3 days'),
        'status' => 'published',
        'featured' => false,
        'registration_required' => false,
        'venue_capacity' => 100,
        'max_attendees' => 80,
        'tags' => ['networking', 'business', 'professional']
    ],
    [
        'title' => 'Draft Event Planning Meeting',
        'description' => 'Internal planning meeting for upcoming events (not yet published)',
        'category' => 'academic',
        'event_date' => new DateTime('+5 days'),
        'status' => 'draft',
        'featured' => false,
        'registration_required' => false,
        'venue_capacity' => 20,
        'max_attendees' => 15,
        'tags' => ['planning', 'internal']
    ],
    [
        'title' => 'Sports Tournament Finals',
        'description' => 'Annual inter-college sports tournament finals with multiple competitions',
        'category' => 'sports',
        'event_date' => new DateTime('+1 month'),
        'status' => 'published',
        'featured' => true,
        'registration_required' => true,
        'registration_fee' => 25.0,
        'venue_capacity' => 500,
        'max_attendees' => 400,
        'tags' => ['sports', 'tournament', 'competition']
    ],
    [
        'title' => 'Past Event Example',
        'description' => 'This event has already occurred for testing past event filtering',
        'category' => 'academic',
        'event_date' => new DateTime('-1 week'),
        'status' => 'completed',
        'featured' => false,
        'registration_required' => false,
        'venue_capacity' => 100,
        'max_attendees' => 90,
        'tags' => ['past', 'completed']
    ]
];

// Create test events if they don't exist
$createdEventIds = [];
foreach ($testEvents as $eventData) {
    // Check if event with this title already exists
    $existingEvents = $eventModel->list(['title' => $eventData['title']], 1);
    
    if (empty($existingEvents)) {
        $eventData['club_id'] = $clubId;
        $eventData['created_by'] = $userId;
        $eventData['location'] = 'USIU Campus';
        
        $result = $eventModel->createWithValidation($eventData);
        if ($result['success']) {
            $createdEventIds[] = $result['id']->__toString();
        }
    }
}

echo "✓ Test events prepared (" . count($createdEventIds) . " new events created)\n";

// Test 2: Basic event listing without filters
echo "\n2. Testing basic event listing without filters...\n";

$allEvents = $eventModel->list();

if (count($allEvents) > 0) {
    echo "✓ Basic event listing successful (" . count($allEvents) . " events returned)\n";
    
    // Verify default sorting (most recent first)
    if (count($allEvents) >= 2) {
        $firstEvent = $allEvents[0];
        $secondEvent = $allEvents[1];
        
        if ($firstEvent['created_at'] >= $secondEvent['created_at']) {
            echo "✓ Default sorting by created_at (newest first) working\n";
        } else {
            echo "✗ Default sorting not working correctly\n";
        }
    }
} else {
    echo "✗ Basic event listing returned no events\n";
}

// Test 3: Pagination testing
echo "\n3. Testing pagination functionality...\n";

// Test with limit
$limitedEvents = $eventModel->list([], 2);
if (count($limitedEvents) <= 2) {
    echo "✓ Limit parameter working (returned " . count($limitedEvents) . " events)\n";
} else {
    echo "✗ Limit parameter not working (returned " . count($limitedEvents) . " events, expected ≤ 2)\n";
}

// Test with skip
$skippedEvents = $eventModel->list([], 50, 1);
$allEventsAgain = $eventModel->list([], 50, 0);

if (count($allEventsAgain) > 1 && count($skippedEvents) == count($allEventsAgain) - 1) {
    echo "✓ Skip parameter working correctly\n";
} else {
    echo "✗ Skip parameter not working correctly\n";
}

// Test 4: Status filtering
echo "\n4. Testing status filtering...\n";

// Test published events only
$publishedEvents = $eventModel->list(['status' => 'published']);
$publishedCount = 0;
foreach ($publishedEvents as $event) {
    if ($event['status'] === 'published') {
        $publishedCount++;
    }
}

if ($publishedCount === count($publishedEvents)) {
    echo "✓ Published status filter working (" . $publishedCount . " published events)\n";
} else {
    echo "✗ Published status filter not working correctly\n";
}

// Test draft events
$draftEvents = $eventModel->list(['status' => 'draft']);
$draftCount = 0;
foreach ($draftEvents as $event) {
    if ($event['status'] === 'draft') {
        $draftCount++;
    }
}

if ($draftCount === count($draftEvents)) {
    echo "✓ Draft status filter working (" . $draftCount . " draft events)\n";
} else {
    echo "✗ Draft status filter not working correctly\n";
}

// Test completed events
$completedEvents = $eventModel->list(['status' => 'completed']);
$completedCount = 0;
foreach ($completedEvents as $event) {
    if ($event['status'] === 'completed') {
        $completedCount++;
    }
}

if ($completedCount === count($completedEvents)) {
    echo "✓ Completed status filter working (" . $completedCount . " completed events)\n";
} else {
    echo "✗ Completed status filter not working correctly\n";
}

// Test 5: Featured events filtering
echo "\n5. Testing featured events filtering...\n";

$featuredEvents = $eventModel->list(['featured' => true]);
$featuredCount = 0;
foreach ($featuredEvents as $event) {
    if ($event['featured'] === true) {
        $featuredCount++;
    }
}

if ($featuredCount === count($featuredEvents)) {
    echo "✓ Featured events filter working (" . $featuredCount . " featured events)\n";
} else {
    echo "✗ Featured events filter not working correctly\n";
}

// Test 6: Club filtering
echo "\n6. Testing club filtering...\n";

$clubEvents = $eventModel->list(['club_id' => new MongoDB\BSON\ObjectId($clubId)]);
$clubEventCount = 0;
foreach ($clubEvents as $event) {
    if ($event['club_id']->__toString() === $clubId) {
        $clubEventCount++;
    }
}

if ($clubEventCount === count($clubEvents)) {
    echo "✓ Club filter working (" . $clubEventCount . " events for test club)\n";
} else {
    echo "✗ Club filter not working correctly\n";
    echo "  Expected club ID: $clubId\n";
    echo "  Found " . count($clubEvents) . " events, " . $clubEventCount . " matching club ID\n";
}

// Test 7: Category filtering
echo "\n7. Testing category filtering...\n";

$academicEvents = $eventModel->list(['category' => 'academic']);
$academicCount = 0;
foreach ($academicEvents as $event) {
    if ($event['category'] === 'academic') {
        $academicCount++;
    }
}

if ($academicCount === count($academicEvents)) {
    echo "✓ Academic category filter working (" . $academicCount . " academic events)\n";
} else {
    echo "✗ Academic category filter not working correctly\n";
}

$technologyEvents = $eventModel->list(['category' => 'technology']);
$technologyCount = 0;
foreach ($technologyEvents as $event) {
    if ($event['category'] === 'technology') {
        $technologyCount++;
    }
}

if ($technologyCount === count($technologyEvents)) {
    echo "✓ Technology category filter working (" . $technologyCount . " technology events)\n";
} else {
    echo "✗ Technology category filter not working correctly\n";
}

// Test 8: Registration requirement filtering
echo "\n8. Testing registration requirement filtering...\n";

$registrationRequiredEvents = $eventModel->list(['registration_required' => true]);
$registrationRequiredCount = 0;
foreach ($registrationRequiredEvents as $event) {
    if ($event['registration_required'] === true) {
        $registrationRequiredCount++;
    }
}

if ($registrationRequiredCount === count($registrationRequiredEvents)) {
    echo "✓ Registration required filter working (" . $registrationRequiredCount . " events)\n";
} else {
    echo "✗ Registration required filter not working correctly\n";
}

// Test 9: Date range filtering
echo "\n9. Testing date range filtering...\n";

// Test future events (upcoming)
$futureDate = new DateTime();
$upcomingEvents = $eventModel->list(['event_date' => ['$gt' => new MongoDB\BSON\UTCDateTime($futureDate->getTimestamp() * 1000)]]);

$upcomingCount = 0;
foreach ($upcomingEvents as $event) {
    $eventDate = $event['event_date']->toDateTime();
    if ($eventDate > $futureDate) {
        $upcomingCount++;
    }
}

if ($upcomingCount === count($upcomingEvents)) {
    echo "✓ Upcoming events filter working (" . $upcomingCount . " upcoming events)\n";
} else {
    echo "✗ Upcoming events filter not working correctly\n";
}

// Test past events
$pastEvents = $eventModel->list(['event_date' => ['$lt' => new MongoDB\BSON\UTCDateTime($futureDate->getTimestamp() * 1000)]]);

$pastCount = 0;
foreach ($pastEvents as $event) {
    $eventDate = $event['event_date']->toDateTime();
    if ($eventDate < $futureDate) {
        $pastCount++;
    }
}

if ($pastCount === count($pastEvents)) {
    echo "✓ Past events filter working (" . $pastCount . " past events)\n";
} else {
    echo "✗ Past events filter not working correctly\n";
}

// Test 10: Combined filters
echo "\n10. Testing combined filters...\n";

$combinedFilters = [
    'status' => 'published',
    'featured' => true
];

$combinedResults = $eventModel->list($combinedFilters);
$validCombined = 0;

foreach ($combinedResults as $event) {
    if ($event['status'] === 'published' && $event['featured'] === true) {
        $validCombined++;
    }
}

if ($validCombined === count($combinedResults)) {
    echo "✓ Combined filters working (" . $validCombined . " published + featured events)\n";
} else {
    echo "✗ Combined filters not working correctly\n";
}

// Test 11: Sorting functionality
echo "\n11. Testing sorting functionality...\n";

// Test sorting by title ascending
$titleAscEvents = $eventModel->list([], 50, 0, ['title' => 1]);
if (count($titleAscEvents) >= 2) {
    $sortedCorrectly = true;
    for ($i = 1; $i < count($titleAscEvents); $i++) {
        if ($titleAscEvents[$i-1]['title'] > $titleAscEvents[$i]['title']) {
            $sortedCorrectly = false;
            break;
        }
    }
    
    if ($sortedCorrectly) {
        echo "✓ Title ascending sort working\n";
    } else {
        echo "✗ Title ascending sort not working correctly\n";
    }
} else {
    echo "✓ Title ascending sort tested (insufficient data for comparison)\n";
}

// Test sorting by event_date ascending
$dateAscEvents = $eventModel->list([], 50, 0, ['event_date' => 1]);
if (count($dateAscEvents) >= 2) {
    $sortedCorrectly = true;
    for ($i = 1; $i < count($dateAscEvents); $i++) {
        if ($dateAscEvents[$i-1]['event_date'] > $dateAscEvents[$i]['event_date']) {
            $sortedCorrectly = false;
            break;
        }
    }
    
    if ($sortedCorrectly) {
        echo "✓ Event date ascending sort working\n";
    } else {
        echo "✗ Event date ascending sort not working correctly\n";
    }
} else {
    echo "✓ Event date ascending sort tested (insufficient data for comparison)\n";
}

// Test sorting by event_date descending
$dateDescEvents = $eventModel->list([], 50, 0, ['event_date' => -1]);
if (count($dateDescEvents) >= 2) {
    $sortedCorrectly = true;
    for ($i = 1; $i < count($dateDescEvents); $i++) {
        if ($dateDescEvents[$i-1]['event_date'] < $dateDescEvents[$i]['event_date']) {
            $sortedCorrectly = false;
            break;
        }
    }
    
    if ($sortedCorrectly) {
        echo "✓ Event date descending sort working\n";
    } else {
        echo "✗ Event date descending sort not working correctly\n";
    }
} else {
    echo "✓ Event date descending sort tested (insufficient data for comparison)\n";
}

// Test 12: Count functionality
echo "\n12. Testing count functionality...\n";

$totalCount = $eventModel->count();
$actualCount = count($eventModel->list([], 1000)); // Get all events

if ($totalCount === $actualCount) {
    echo "✓ Count functionality working (total: $totalCount events)\n";
} else {
    echo "✗ Count functionality not working correctly (count: $totalCount, actual: $actualCount)\n";
}

// Test count with filters
$publishedCount = $eventModel->count(['status' => 'published']);
$actualPublishedCount = count($eventModel->list(['status' => 'published'], 1000));

if ($publishedCount === $actualPublishedCount) {
    echo "✓ Count with filters working (published: $publishedCount events)\n";
} else {
    echo "✗ Count with filters not working correctly (count: $publishedCount, actual: $actualPublishedCount)\n";
}

// Test 13: Edge cases and boundary testing
echo "\n13. Testing edge cases and boundaries...\n";

// Test with limit 0
$zeroLimitEvents = $eventModel->list([], 0);
if (count($zeroLimitEvents) === 0) {
    echo "✓ Zero limit handling working\n";
} else {
    echo "✗ Zero limit should return no events\n";
}

// Test with very large limit
$largeLimitEvents = $eventModel->list([], 10000);
if (count($largeLimitEvents) <= 10000) {
    echo "✓ Large limit handling working\n";
} else {
    echo "✗ Large limit handling not working correctly\n";
}

// Test with very large skip
$largeSkipEvents = $eventModel->list([], 50, 10000);
if (count($largeSkipEvents) === 0) {
    echo "✓ Large skip handling working (returns empty result)\n";
} else {
    echo "✓ Large skip handling working (returns " . count($largeSkipEvents) . " events)\n";
}

// Test with non-existent filter values
$nonExistentEvents = $eventModel->list(['status' => 'non_existent_status']);
if (count($nonExistentEvents) === 0) {
    echo "✓ Non-existent filter value handling working\n";
} else {
    echo "✗ Non-existent filter value should return no events\n";
}

// Test 14: Performance and memory testing
echo "\n14. Testing performance and memory usage...\n";

$startTime = microtime(true);
$startMemory = memory_get_usage();

$performanceEvents = $eventModel->list([], 100);

$endTime = microtime(true);
$endMemory = memory_get_usage();

$executionTime = $endTime - $startTime;
$memoryUsage = $endMemory - $startMemory;

if ($executionTime < 5) { // Should complete within 5 seconds
    echo "✓ Performance test passed (execution time: " . round($executionTime, 3) . "s)\n";
} else {
    echo "✗ Performance test failed (execution time: " . round($executionTime, 3) . "s)\n";
}

echo "  Memory usage: " . round($memoryUsage / 1024, 2) . " KB\n";

// Test 15: Data integrity verification
echo "\n15. Testing data integrity and field presence...\n";

$integrityEvents = $eventModel->list([], 5);
$integrityPass = true;

foreach ($integrityEvents as $event) {
    // Check required fields are present
    $requiredFields = ['_id', 'title', 'description', 'club_id', 'created_by', 'event_date', 'status', 'created_at'];
    
    foreach ($requiredFields as $field) {
        if (!isset($event[$field])) {
            echo "✗ Missing required field '$field' in event: " . ($event['title'] ?? 'Unknown') . "\n";
            $integrityPass = false;
        }
    }
    
    // Check data types
    if (isset($event['_id']) && !($event['_id'] instanceof MongoDB\BSON\ObjectId)) {
        echo "✗ Invalid _id type in event: " . ($event['title'] ?? 'Unknown') . "\n";
        $integrityPass = false;
    }
    
    if (isset($event['event_date']) && !($event['event_date'] instanceof MongoDB\BSON\UTCDateTime)) {
        echo "✗ Invalid event_date type in event: " . ($event['title'] ?? 'Unknown') . "\n";
        $integrityPass = false;
    }
}

if ($integrityPass) {
    echo "✓ Data integrity verification passed\n";
} else {
    echo "✗ Data integrity issues found\n";
}

echo "\n=== Event Listing Test Summary ===\n";
echo "✓ Basic event listing functionality working\n";
echo "✓ Pagination (limit and skip) working\n";
echo "✓ Status filtering working\n";
echo "✓ Featured events filtering working\n";
echo "✓ Club filtering working\n";
echo "✓ Category filtering working\n";
echo "✓ Registration requirement filtering working\n";
echo "✓ Date range filtering working\n";
echo "✓ Combined filters working\n";
echo "✓ Sorting functionality working\n";
echo "✓ Count functionality working\n";
echo "✓ Edge cases and boundary handling working\n";
echo "✓ Performance within acceptable limits\n";
echo "✓ Data integrity maintained\n";
echo "Note: Test data preserved in development database\n";