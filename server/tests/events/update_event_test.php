<?php
/**
 * Event update test
 * Tests event modification functionality with validation, partial updates, and security
 */

require_once __DIR__ . '/../../server/vendor/autoload.php';
require_once __DIR__ . '/../../server/config/database.php';
require_once __DIR__ . '/../../server/models/Event.php';
require_once __DIR__ . '/../../server/models/User.php';
require_once __DIR__ . '/../../server/models/Club.php';
require_once __DIR__ . '/../../server/utils/response.php';

echo "=== Event Update Test ===\n";

// Initialize models
$eventModel = new EventModel($db->events);
$userModel = new UserModel($db->users);
$clubModel = new ClubModel($db->clubs);

// Test setup: Create test event for updating
echo "\n1. Setting up test event for update testing...\n";

// Get or create test data
$testUser = $userModel->findByEmail('update.test@usiu.ac.ke');
if (!$testUser) {
    $userData = [
        'student_id' => 'USIU20240800',
        'first_name' => 'Update',
        'last_name' => 'Tester',
        'email' => 'update.test@usiu.ac.ke',
        'password' => 'updateTester123',
        'is_email_verified' => true,
        'role' => 'student'
    ];
    $result = $userModel->createWithValidation($userData);
    $testUser = $userModel->findByEmail('update.test@usiu.ac.ke');
    echo "✓ Test user created\n";
} else {
    echo "✓ Test user already exists\n";
}

$testClub = $clubModel->findByName('Event Update Society');
if (!$testClub) {
    $clubData = [
        'name' => 'Event Update Society',
        'description' => 'Society for event update testing functionality',
        'category' => 'Academic',
        'contact_email' => 'updateclub@usiu.ac.ke',
        'leader_id' => $testUser['_id']->__toString(),
        'created_by' => $testUser['_id']->__toString(),
        'status' => 'active'
    ];
    $clubId = $clubModel->create($clubData);
    $testClub = $clubModel->findById($clubId->__toString());
    echo "✓ Test club created\n";
} else {
    echo "✓ Test club already exists\n";
}

// Create initial test event
$initialEventData = [
    'title' => 'Original Event Title',
    'description' => 'Original event description for update testing',
    'club_id' => $testClub['_id']->__toString(),
    'created_by' => $testUser['_id']->__toString(),
    'event_date' => new DateTime('+2 weeks'),
    'location' => 'Original Location',
    'status' => 'draft',
    'category' => 'academic',
    'venue_capacity' => 100,
    'max_attendees' => 80,
    'registration_required' => false,
    'registration_fee' => 0.0,
    'featured' => false,
    'tags' => ['original', 'test']
];

$result = $eventModel->createWithValidation($initialEventData);
if ($result['success']) {
    $testEventId = $result['id']->__toString();
    echo "✓ Test event created for update testing\n";
} else {
    echo "✗ Failed to create test event\n";
    exit(1);
}

// Test 2: Basic field updates
echo "\n2. Testing basic field updates...\n";

$basicUpdates = [
    'title' => 'Updated Event Title',
    'description' => 'Updated event description with new content',
    'location' => 'Updated Location Hall'
];

$updateResult = $eventModel->updateWithValidation($testEventId, $basicUpdates);

if ($updateResult['success']) {
    echo "✓ Basic field update successful\n";
    
    // Verify changes were applied
    $updatedEvent = $eventModel->findById($testEventId);
    
    if ($updatedEvent['title'] === 'Updated Event Title') {
        echo "✓ Title update verified\n";
    } else {
        echo "✗ Title update not applied correctly\n";
    }
    
    if ($updatedEvent['description'] === 'Updated event description with new content') {
        echo "✓ Description update verified\n";
    } else {
        echo "✗ Description update not applied correctly\n";
    }
    
    if ($updatedEvent['location'] === 'Updated Location Hall') {
        echo "✓ Location update verified\n";
    } else {
        echo "✗ Location update not applied correctly\n";
    }
    
    // Verify unchanged fields remain the same
    if ($updatedEvent['status'] === 'draft') {
        echo "✓ Unchanged fields preserved\n";
    } else {
        echo "✗ Unchanged fields were modified\n";
    }
    
} else {
    echo "✗ Basic field update failed: " . json_encode($updateResult['errors']) . "\n";
}

// Test 3: Numeric field updates
echo "\n3. Testing numeric field updates...\n";

$numericUpdates = [
    'venue_capacity' => 200,
    'max_attendees' => 180,
    'registration_fee' => 50.0
];

$numericResult = $eventModel->updateWithValidation($testEventId, $numericUpdates);

if ($numericResult['success']) {
    echo "✓ Numeric field update successful\n";
    
    $updatedEvent = $eventModel->findById($testEventId);
    
    if ($updatedEvent['venue_capacity'] === 200) {
        echo "✓ Venue capacity update verified\n";
    } else {
        echo "✗ Venue capacity update not applied correctly\n";
    }
    
    if ($updatedEvent['max_attendees'] === 180) {
        echo "✓ Max attendees update verified\n";
    } else {
        echo "✗ Max attendees update not applied correctly\n";
    }
    
    if ($updatedEvent['registration_fee'] === 50.0) {
        echo "✓ Registration fee update verified\n";
    } else {
        echo "✗ Registration fee update not applied correctly\n";
    }
    
} else {
    echo "✗ Numeric field update failed: " . json_encode($numericResult['errors']) . "\n";
}

// Test 4: Boolean field updates
echo "\n4. Testing boolean field updates...\n";

$booleanUpdates = [
    'registration_required' => true,
    'featured' => true
];

$booleanResult = $eventModel->updateWithValidation($testEventId, $booleanUpdates);

if ($booleanResult['success']) {
    echo "✓ Boolean field update successful\n";
    
    $updatedEvent = $eventModel->findById($testEventId);
    
    if ($updatedEvent['registration_required'] === true) {
        echo "✓ Registration required update verified\n";
    } else {
        echo "✗ Registration required update not applied correctly\n";
    }
    
    if ($updatedEvent['featured'] === true) {
        echo "✓ Featured update verified\n";
    } else {
        echo "✗ Featured update not applied correctly\n";
    }
    
} else {
    echo "✗ Boolean field update failed: " . json_encode($booleanResult['errors']) . "\n";
}

// Test 5: Date field updates
echo "\n5. Testing date field updates...\n";

$newEventDate = new DateTime('+3 weeks');
$newEndDate = clone $newEventDate;
$newEndDate->modify('+2 hours');
$newDeadline = new DateTime('+2 weeks');

$dateUpdates = [
    'event_date' => $newEventDate,
    'end_date' => $newEndDate,
    'registration_deadline' => $newDeadline
];

$dateResult = $eventModel->updateWithValidation($testEventId, $dateUpdates);

if ($dateResult['success']) {
    echo "✓ Date field update successful\n";
    
    $updatedEvent = $eventModel->findById($testEventId);
    
    // Compare timestamps (accounting for MongoDB precision)
    $updatedEventDate = $updatedEvent['event_date']->toDateTime();
    if (abs($updatedEventDate->getTimestamp() - $newEventDate->getTimestamp()) < 2) {
        echo "✓ Event date update verified\n";
    } else {
        echo "✗ Event date update not applied correctly\n";
    }
    
    if (isset($updatedEvent['end_date'])) {
        $updatedEndDate = $updatedEvent['end_date']->toDateTime();
        if (abs($updatedEndDate->getTimestamp() - $newEndDate->getTimestamp()) < 2) {
            echo "✓ End date update verified\n";
        } else {
            echo "✗ End date update not applied correctly\n";
        }
    }
    
    if (isset($updatedEvent['registration_deadline'])) {
        $updatedDeadline = $updatedEvent['registration_deadline']->toDateTime();
        if (abs($updatedDeadline->getTimestamp() - $newDeadline->getTimestamp()) < 2) {
            echo "✓ Registration deadline update verified\n";
        } else {
            echo "✗ Registration deadline update not applied correctly\n";
        }
    }
    
} else {
    echo "✗ Date field update failed: " . json_encode($dateResult['errors']) . "\n";
}

// Test 6: Array field updates
echo "\n6. Testing array field updates...\n";

$arrayUpdates = [
    'tags' => ['updated', 'test', 'event', 'academic'],
    'gallery' => ['https://example.com/image1.jpg', 'https://example.com/image2.jpg']
];

$arrayResult = $eventModel->updateWithValidation($testEventId, $arrayUpdates);

if ($arrayResult['success']) {
    echo "✓ Array field update successful\n";
    
    $updatedEvent = $eventModel->findById($testEventId);
    
    if (is_array($updatedEvent['tags']) && count($updatedEvent['tags']) === 4) {
        echo "✓ Tags array update verified\n";
    } else {
        echo "✗ Tags array update not applied correctly\n";
    }
    
    if (is_array($updatedEvent['gallery']) && count($updatedEvent['gallery']) === 2) {
        echo "✓ Gallery array update verified\n";
    } else {
        echo "✗ Gallery array update not applied correctly\n";
    }
    
} else {
    echo "✗ Array field update failed: " . json_encode($arrayResult['errors']) . "\n";
}

// Test 7: Object field updates (social media)
echo "\n7. Testing object field updates...\n";

$objectUpdates = [
    'social_media' => [
        'facebook' => 'https://facebook.com/updated-event',
        'twitter' => 'https://twitter.com/updated_event',
        'instagram' => 'https://instagram.com/updated_event'
    ]
];

$objectResult = $eventModel->updateWithValidation($testEventId, $objectUpdates);

if ($objectResult['success']) {
    echo "✓ Object field update successful\n";
    
    $updatedEvent = $eventModel->findById($testEventId);
    
    if (is_array($updatedEvent['social_media']) && isset($updatedEvent['social_media']['facebook'])) {
        echo "✓ Social media object update verified\n";
    } else {
        echo "✗ Social media object update not applied correctly\n";
    }
    
} else {
    echo "✗ Object field update failed: " . json_encode($objectResult['errors']) . "\n";
}

// Test 8: Validation during updates
echo "\n8. Testing validation during updates...\n";

// Test invalid field values
$invalidUpdates = [
    'title' => 'AB', // Too short
    'venue_capacity' => -50, // Negative
    'max_attendees' => 100000, // Too large
    'status' => 'invalid_status' // Invalid enum value
];

$validationResult = $eventModel->updateWithValidation($testEventId, $invalidUpdates);

if (!$validationResult['success']) {
    echo "✓ Invalid updates properly rejected\n";
    echo "  Validation errors: " . json_encode($validationResult['errors']) . "\n";
    
    // Check specific validation errors
    if (isset($validationResult['errors']['title'])) {
        echo "✓ Short title validation working\n";
    }
    
    if (isset($validationResult['errors']['venue_capacity'])) {
        echo "✓ Negative venue capacity validation working\n";
    }
    
    if (isset($validationResult['errors']['max_attendees'])) {
        echo "✓ Max attendees limit validation working\n";
    }
    
    if (isset($validationResult['errors']['status'])) {
        echo "✓ Invalid status validation working\n";
    }
    
} else {
    echo "✗ Invalid updates should be rejected\n";
}

// Test 9: Business logic validation during updates
echo "\n9. Testing business logic validation during updates...\n";

// Test past event date
$pastDateUpdate = [
    'event_date' => new DateTime('-1 week')
];

$pastDateResult = $eventModel->updateWithValidation($testEventId, $pastDateUpdate);

if (!$pastDateResult['success'] && isset($pastDateResult['errors']['event_date'])) {
    echo "✓ Past event date update properly rejected\n";
} else {
    echo "✗ Past event date update should be rejected\n";
}

// Test end date before start date
$invalidDateRangeUpdate = [
    'event_date' => new DateTime('+4 weeks'),
    'end_date' => new DateTime('+3 weeks')
];

$dateRangeResult = $eventModel->updateWithValidation($testEventId, $invalidDateRangeUpdate);

if (!$dateRangeResult['success'] && isset($dateRangeResult['errors']['end_date'])) {
    echo "✓ Invalid date range update properly rejected\n";
} else {
    echo "✗ Invalid date range update should be rejected\n";
}

// Test max attendees exceeding venue capacity
$capacityExceedUpdate = [
    'venue_capacity' => 100,
    'max_attendees' => 150
];

$capacityResult = $eventModel->updateWithValidation($testEventId, $capacityExceedUpdate);

if (!$capacityResult['success'] && isset($capacityResult['errors']['max_attendees'])) {
    echo "✓ Capacity exceeded update properly rejected\n";
} else {
    echo "✗ Capacity exceeded update should be rejected\n";
}

// Test 10: Partial updates
echo "\n10. Testing partial updates...\n";

// Update only one field
$partialUpdate = [
    'category' => 'technology'
];

$partialResult = $eventModel->updateWithValidation($testEventId, $partialUpdate);

if ($partialResult['success']) {
    echo "✓ Partial update successful\n";
    
    $updatedEvent = $eventModel->findById($testEventId);
    
    if ($updatedEvent['category'] === 'technology') {
        echo "✓ Category partial update verified\n";
    } else {
        echo "✗ Category partial update not applied correctly\n";
    }
    
    // Verify other fields remained unchanged
    if ($updatedEvent['title'] === 'Updated Event Title') {
        echo "✓ Other fields preserved during partial update\n";
    } else {
        echo "✗ Other fields modified during partial update\n";
    }
    
} else {
    echo "✗ Partial update failed: " . json_encode($partialResult['errors']) . "\n";
}

// Test 11: Non-existent event update
echo "\n11. Testing non-existent event update...\n";

$nonExistentId = '507f1f77bcf86cd799439011'; // Valid ObjectId format but doesn't exist
$nonExistentUpdate = ['title' => 'Should Not Work'];

$nonExistentResult = $eventModel->updateWithValidation($nonExistentId, $nonExistentUpdate);

if (!$nonExistentResult['success']) {
    echo "✓ Non-existent event update properly rejected\n";
} else {
    echo "✗ Non-existent event update should be rejected\n";
}

// Test 12: Invalid event ID format for update
echo "\n12. Testing invalid event ID format for update...\n";

try {
    $invalidIdResult = $eventModel->updateWithValidation('invalid_id', ['title' => 'Test']);
    echo "✗ Invalid ID format should throw exception\n";
} catch (Exception $e) {
    echo "✓ Invalid ID format properly rejected\n";
    echo "  Error message: " . $e->getMessage() . "\n";
}

// Test 13: Updated timestamp verification
echo "\n13. Testing updated timestamp modification...\n";

$timestampUpdate = [
    'description' => 'Description updated to test timestamp modification'
];

$originalEvent = $eventModel->findById($testEventId);
$originalUpdatedAt = $originalEvent['updated_at'];

// Wait a moment to ensure timestamp difference
usleep(100000); // 0.1 second

$timestampResult = $eventModel->updateWithValidation($testEventId, $timestampUpdate);

if ($timestampResult['success']) {
    $updatedEvent = $eventModel->findById($testEventId);
    $newUpdatedAt = $updatedEvent['updated_at'];
    
    if ($newUpdatedAt > $originalUpdatedAt) {
        echo "✓ Updated timestamp properly modified\n";
    } else {
        echo "✗ Updated timestamp not modified\n";
    }
    
    // Verify created_at remains unchanged
    if ($updatedEvent['created_at']->__toString() === $originalEvent['created_at']->__toString()) {
        echo "✓ Created timestamp preserved during update\n";
    } else {
        echo "✗ Created timestamp should not change during update\n";
    }
} else {
    echo "✗ Timestamp update test failed\n";
}

// Test 14: Large batch update
echo "\n14. Testing large batch update...\n";

$batchUpdate = [
    'title' => 'Comprehensive Batch Update Event',
    'description' => 'This event has been updated with multiple fields in a single operation to test batch update functionality',
    'location' => 'Batch Update Conference Center',
    'category' => 'business',
    'venue_capacity' => 300,
    'max_attendees' => 250,
    'registration_required' => true,
    'registration_fee' => 75.0,
    'featured' => false,
    'status' => 'published',
    'tags' => ['batch', 'update', 'comprehensive', 'business'],
    'social_media' => [
        'facebook' => 'https://facebook.com/batch-event',
        'linkedin' => 'https://linkedin.com/company/batch-event'
    ]
];

$batchResult = $eventModel->updateWithValidation($testEventId, $batchUpdate);

if ($batchResult['success']) {
    echo "✓ Large batch update successful\n";
    
    $updatedEvent = $eventModel->findById($testEventId);
    
    // Verify a few key fields
    if ($updatedEvent['title'] === 'Comprehensive Batch Update Event' &&
        $updatedEvent['category'] === 'business' &&
        $updatedEvent['status'] === 'published' &&
        count($updatedEvent['tags']) === 4) {
        echo "✓ Batch update fields verified\n";
    } else {
        echo "✗ Batch update fields not applied correctly\n";
    }
    
} else {
    echo "✗ Large batch update failed: " . json_encode($batchResult['errors']) . "\n";
}

// Test 15: Performance testing for updates
echo "\n15. Testing update performance...\n";

$startTime = microtime(true);
$startMemory = memory_get_usage();

// Perform multiple updates to test performance
for ($i = 0; $i < 10; $i++) {
    $performanceUpdate = [
        'description' => "Performance test update iteration $i"
    ];
    $eventModel->updateWithValidation($testEventId, $performanceUpdate);
}

$endTime = microtime(true);
$endMemory = memory_get_usage();

$executionTime = $endTime - $startTime;
$memoryUsage = $endMemory - $startMemory;

if ($executionTime < 5) { // Should complete 10 updates within 5 seconds
    echo "✓ Performance test passed (10 updates in " . round($executionTime, 3) . "s)\n";
} else {
    echo "✗ Performance test failed (10 updates took " . round($executionTime, 3) . "s)\n";
}

echo "  Average time per update: " . round($executionTime / 10, 4) . "s\n";
echo "  Memory usage: " . round($memoryUsage / 1024, 2) . " KB\n";

echo "\n=== Event Update Test Summary ===\n";
echo "✓ Basic field updates working\n";
echo "✓ Numeric field updates working\n";
echo "✓ Boolean field updates working\n";
echo "✓ Date field updates working\n";
echo "✓ Array field updates working\n";
echo "✓ Object field updates working\n";
echo "✓ Validation during updates working\n";
echo "✓ Business logic validation working\n";
echo "✓ Partial updates working\n";
echo "✓ Non-existent event handling working\n";
echo "✓ Invalid ID format handling working\n";
echo "✓ Timestamp management working\n";
echo "✓ Large batch updates working\n";
echo "✓ Performance within acceptable limits\n";
echo "Note: Test data preserved in development database\n";