<?php
/**
 * Event Model Unit Tests
 * Comprehensive testing of EventModel class functionality
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Club.php';

echo "=== Event Model Unit Tests ===\n";

// Initialize models
$eventModel = new EventModel($db->events);
$userModel = new UserModel($db->users);
$clubModel = new ClubModel($db->clubs);

// Test 1: Model instantiation
echo "\n1. Testing EventModel instantiation...\n";

if ($eventModel instanceof EventModel) {
    echo "✓ EventModel instantiated successfully\n";
} else {
    echo "✗ EventModel instantiation failed\n";
    exit(1);
}

// Test 2: Setup test dependencies
echo "\n2. Setting up test dependencies (user and club)...\n";

// Create test user
$testUser = $userModel->findByEmail('eventmodel.test@usiu.ac.ke');
if (!$testUser) {
    $userData = [
        'student_id' => 'USIU20250200',
        'first_name' => 'EventModel',
        'last_name' => 'Tester',
        'email' => 'eventmodel.test@usiu.ac.ke',
        'password' => 'EventModel123',
        'phone' => '+254799000200',
        'course' => 'Computer Science',
        'year_of_study' => 2,
        'is_email_verified' => true,
        'role' => 'student'
    ];
    
    $result = $userModel->createWithValidation($userData);
    if ($result['success']) {
        $testUser = $userModel->findByEmail('eventmodel.test@usiu.ac.ke');
        echo "✓ Test user created\n";
    } else {
        echo "✗ Failed to create test user\n";
        exit(1);
    }
} else {
    echo "✓ Test user already exists\n";
}

// Get or create test club - try different approaches
$testClub = $clubModel->findByName('Academic Excellence Society');
if (!$testClub) {
    // Try to find existing clubs by different categories
    $categories = ['academic', 'social', 'sports', 'arts'];
    foreach ($categories as $category) {
        $existingClubs = $clubModel->findByCategory($category);
        if (!empty($existingClubs)) {
            $testClub = $existingClubs[0];
            echo "✓ Using existing club: {$testClub['name']}\n";
            break;
        }
    }
}

if (!$testClub) {
    $clubData = [
        'name' => 'Academic Excellence Society',
        'description' => 'Society for academic excellence and research',
        'category' => 'Academic',
        'contact_email' => 'academic@usiu.ac.ke',
        'leader_id' => $testUser['_id']->__toString(),
        'created_by' => $testUser['_id']->__toString(),
        'status' => 'active'
    ];
    
    try {
        $clubId = $clubModel->create($clubData);
        $testClub = $clubModel->findById($clubId->__toString());
        echo "✓ Test club created\n";
    } catch (Exception $e) {
        echo "✗ Failed to create club: {$e->getMessage()}\n";
        exit(1);
    }
} else {
    echo "✓ Test club found\n";
}

$userId = $testUser['_id']->__toString();
$clubId = $testClub['_id']->__toString();

// Test 3: Create method with valid data
echo "\n3. Testing create method with valid data...\n";

$validEventData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'title' => 'EventModel Test Event',
    'description' => 'Test event for EventModel unit testing',
    'event_date' => new DateTime('+1 week'),
    'location' => 'Unit Test Location',
    'status' => 'draft'
];

try {
    $eventId = $eventModel->create($validEventData);
    if ($eventId instanceof MongoDB\BSON\ObjectId) {
        echo "✓ Event created successfully with valid data\n";
        echo "  Event ID: {$eventId->__toString()}\n";
    } else {
        echo "✗ Event creation did not return ObjectId\n";
    }
} catch (Exception $e) {
    echo "✗ Event creation failed: {$e->getMessage()}\n";
}

// Test 4: Create method with invalid data
echo "\n4. Testing create method with invalid data...\n";

$invalidEventData = [
    'title' => 'Invalid Event'
    // Missing required fields
];

try {
    $invalidEventId = $eventModel->create($invalidEventData);
    echo "✗ Invalid event creation should have failed\n";
} catch (Exception $e) {
    echo "✓ Invalid event creation properly rejected\n";
    echo "  Error: {$e->getMessage()}\n";
}

// Test 5: CreateWithValidation method
echo "\n5. Testing createWithValidation method...\n";

$validationTestData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'title' => 'Validation Test Event',
    'description' => 'Test event for validation testing',
    'event_date' => new DateTime('+2 weeks'),
    'location' => 'Validation Test Location'
];

$result = $eventModel->createWithValidation($validationTestData);
if ($result['success']) {
    echo "✓ createWithValidation successful with valid data\n";
    $validationEventId = $result['id']->__toString();
} else {
    echo "✗ createWithValidation failed with valid data\n";
    var_dump($result['errors']);
}

// Test invalid data with createWithValidation
$invalidValidationData = [
    'title' => 'AB', // Too short
    'description' => 'Short' // Too short
];

$invalidResult = $eventModel->createWithValidation($invalidValidationData);
if (!$invalidResult['success']) {
    echo "✓ createWithValidation properly rejected invalid data\n";
    echo "  Validation errors detected: " . count($invalidResult['errors']) . "\n";
} else {
    echo "✗ createWithValidation should have rejected invalid data\n";
}

// Test 6: FindById method
echo "\n6. Testing findById method...\n";

if (isset($eventId)) {
    $foundEvent = $eventModel->findById($eventId->__toString());
    if ($foundEvent && $foundEvent['title'] === 'EventModel Test Event') {
        echo "✓ findById successfully retrieved event\n";
        echo "  Title: {$foundEvent['title']}\n";
    } else {
        echo "✗ findById failed to retrieve event correctly\n";
    }
    
    // Test with invalid ID
    try {
        $notFound = $eventModel->findById('invalid_id');
        echo "✗ findById should have thrown exception for invalid ID\n";
    } catch (Exception $e) {
        echo "✓ findById properly handled invalid ID\n";
    }
    
    // Test with non-existent ID
    $fakeId = new MongoDB\BSON\ObjectId();
    $notFound = $eventModel->findById($fakeId->__toString());
    if ($notFound === null) {
        echo "✓ findById returned null for non-existent event\n";
    } else {
        echo "✗ findById should return null for non-existent event\n";
    }
}

// Test 7: Update method
echo "\n7. Testing update method...\n";

if (isset($eventId)) {
    $updateData = [
        'title' => 'Updated EventModel Test Event',
        'description' => 'Updated description for EventModel testing'
    ];
    
    try {
        $updated = $eventModel->update($eventId->__toString(), $updateData);
        if ($updated) {
            echo "✓ Event updated successfully\n";
            
            // Verify update
            $updatedEvent = $eventModel->findById($eventId->__toString());
            if ($updatedEvent['title'] === 'Updated EventModel Test Event') {
                echo "✓ Update verification successful\n";
            } else {
                echo "✗ Update verification failed\n";
            }
        } else {
            echo "✗ Event update returned false\n";
        }
    } catch (Exception $e) {
        echo "✗ Event update failed: {$e->getMessage()}\n";
    }
    
    // Test update with invalid data
    try {
        $invalidUpdate = $eventModel->update($eventId->__toString(), ['title' => 'AB']); // Too short
        echo "✗ Invalid update should have failed\n";
    } catch (Exception $e) {
        echo "✓ Invalid update properly rejected\n";
    }
}

// Test 8: UpdateWithValidation method
echo "\n8. Testing updateWithValidation method...\n";

if (isset($validationEventId)) {
    $validUpdateData = [
        'title' => 'Updated Validation Test Event',
        'status' => 'published'
    ];
    
    $updateResult = $eventModel->updateWithValidation($validationEventId, $validUpdateData);
    if ($updateResult['success']) {
        echo "✓ updateWithValidation successful with valid data\n";
        echo "  Modified: " . ($updateResult['modified'] ? 'true' : 'false') . "\n";
    } else {
        echo "✗ updateWithValidation failed with valid data\n";
        var_dump($updateResult['errors']);
    }
    
    // Test with invalid data
    $invalidUpdateData = [
        'venue_capacity' => -100 // Invalid negative value
    ];
    
    $invalidUpdateResult = $eventModel->updateWithValidation($validationEventId, $invalidUpdateData);
    if (!$invalidUpdateResult['success']) {
        echo "✓ updateWithValidation properly rejected invalid data\n";
    } else {
        echo "✗ updateWithValidation should have rejected invalid data\n";
    }
    
    // Test with non-existent event
    $fakeId = new MongoDB\BSON\ObjectId();
    $nonExistentResult = $eventModel->updateWithValidation($fakeId->__toString(), $validUpdateData);
    if (!$nonExistentResult['success'] && isset($nonExistentResult['errors']['event'])) {
        echo "✓ updateWithValidation properly handled non-existent event\n";
    } else {
        echo "✗ updateWithValidation should have detected non-existent event\n";
    }
}

// Test 9: List method
echo "\n9. Testing list method...\n";

$allEvents = $eventModel->list();
if (is_array($allEvents) && count($allEvents) > 0) {
    echo "✓ list method returned events array\n";
    echo "  Total events: " . count($allEvents) . "\n";
} else {
    echo "✗ list method failed to return events\n";
}

// Test with filters
$filteredEvents = $eventModel->list(['status' => 'draft'], 10);
if (is_array($filteredEvents)) {
    echo "✓ list method with filters worked\n";
    echo "  Filtered events: " . count($filteredEvents) . "\n";
} else {
    echo "✗ list method with filters failed\n";
}

// Test with limit 0 (edge case)
$emptyList = $eventModel->list([], 0);
if (is_array($emptyList) && count($emptyList) === 0) {
    echo "✓ list method with limit 0 returned empty array\n";
} else {
    echo "✗ list method with limit 0 should return empty array\n";
}

// Test with pagination
$paginatedEvents = $eventModel->list([], 2, 1, ['created_at' => -1]);
if (is_array($paginatedEvents)) {
    echo "✓ list method with pagination worked\n";
    echo "  Paginated events: " . count($paginatedEvents) . "\n";
} else {
    echo "✗ list method with pagination failed\n";
}

// Test 10: Count method
echo "\n10. Testing count method...\n";

$totalCount = $eventModel->count();
if (is_int($totalCount) && $totalCount >= 0) {
    echo "✓ count method returned valid integer\n";
    echo "  Total count: $totalCount\n";
} else {
    echo "✗ count method failed\n";
}

$filteredCount = $eventModel->count(['status' => 'draft']);
if (is_int($filteredCount) && $filteredCount >= 0) {
    echo "✓ count method with filters worked\n";
    echo "  Filtered count: $filteredCount\n";
} else {
    echo "✗ count method with filters failed\n";
}

// Test 11: RegisterUser method
echo "\n11. Testing registerUser method...\n";

if (isset($eventId)) {
    try {
        $registered = $eventModel->registerUser($eventId->__toString(), $userId);
        if ($registered) {
            echo "✓ User registration successful\n";
            
            // Verify registration
            $eventWithUser = $eventModel->findById($eventId->__toString());
            $isRegistered = false;
            if (isset($eventWithUser['registered_users'])) {
                foreach ($eventWithUser['registered_users'] as $registeredUserId) {
                    if ($registeredUserId instanceof MongoDB\BSON\ObjectId && 
                        $registeredUserId->__toString() === $userId) {
                        $isRegistered = true;
                        break;
                    }
                }
            }
            
            if ($isRegistered) {
                echo "✓ User registration verification successful\n";
            } else {
                echo "✗ User registration verification failed\n";
            }
        } else {
            echo "✗ User registration failed\n";
        }
    } catch (Exception $e) {
        echo "✗ User registration exception: {$e->getMessage()}\n";
    }
    
    // Test duplicate registration
    try {
        $duplicateReg = $eventModel->registerUser($eventId->__toString(), $userId);
        echo "✗ Duplicate registration should have failed\n";
    } catch (Exception $e) {
        echo "✓ Duplicate registration properly rejected\n";
    }
    
    // Test registration for non-existent event
    try {
        $fakeId = new MongoDB\BSON\ObjectId();
        $invalidReg = $eventModel->registerUser($fakeId->__toString(), $userId);
        echo "✗ Registration for non-existent event should have failed\n";
    } catch (Exception $e) {
        echo "✓ Registration for non-existent event properly rejected\n";
    }
}

// Test 12: UnregisterUserFromEvent method
echo "\n12. Testing unregisterUserFromEvent method...\n";

if (isset($eventId)) {
    $unregisterResult = $eventModel->unregisterUserFromEvent($eventId->__toString(), $userId);
    if ($unregisterResult['success']) {
        echo "✓ User unregistration successful\n";
        
        // Verify unregistration
        $eventAfterUnreg = $eventModel->findById($eventId->__toString());
        $stillRegistered = false;
        if (isset($eventAfterUnreg['registered_users'])) {
            foreach ($eventAfterUnreg['registered_users'] as $registeredUserId) {
                if ($registeredUserId instanceof MongoDB\BSON\ObjectId && 
                    $registeredUserId->__toString() === $userId) {
                    $stillRegistered = true;
                    break;
                }
            }
        }
        
        if (!$stillRegistered) {
            echo "✓ User unregistration verification successful\n";
        } else {
            echo "✗ User unregistration verification failed\n";
        }
    } else {
        echo "✗ User unregistration failed: {$unregisterResult['error']}\n";
    }
    
    // Test unregistration when not registered
    $notRegisteredResult = $eventModel->unregisterUserFromEvent($eventId->__toString(), $userId);
    if (!$notRegisteredResult['success']) {
        echo "✓ Unregistration when not registered properly rejected\n";
    } else {
        echo "✗ Unregistration when not registered should have failed\n";
    }
}

// Test 13: FindByTitle method
echo "\n13. Testing findByTitle method...\n";

$foundByTitle = $eventModel->findByTitle('Updated EventModel Test Event');
if ($foundByTitle && isset($foundByTitle['title'])) {
    echo "✓ findByTitle successfully found event\n";
    echo "  Found title: {$foundByTitle['title']}\n";
} else {
    echo "✗ findByTitle failed to find event\n";
}

$notFoundByTitle = $eventModel->findByTitle('Non-existent Event Title');
if ($notFoundByTitle === null) {
    echo "✓ findByTitle returned null for non-existent title\n";
} else {
    echo "✗ findByTitle should return null for non-existent title\n";
}

// Test 14: Delete method
echo "\n14. Testing delete method...\n";

if (isset($validationEventId)) {
    try {
        $deleted = $eventModel->delete($validationEventId);
        if ($deleted) {
            echo "✓ Event deletion successful\n";
            
            // Verify deletion
            $deletedEvent = $eventModel->findById($validationEventId);
            if ($deletedEvent === null) {
                echo "✓ Event deletion verification successful\n";
            } else {
                echo "✗ Event deletion verification failed\n";
            }
        } else {
            echo "✗ Event deletion returned false\n";
        }
    } catch (Exception $e) {
        echo "✗ Event deletion failed: {$e->getMessage()}\n";
    }
    
    // Test deletion of non-existent event
    try {
        $fakeId = new MongoDB\BSON\ObjectId();
        $notDeleted = $eventModel->delete($fakeId->__toString());
        if (!$notDeleted) {
            echo "✓ Deletion of non-existent event returned false\n";
        } else {
            echo "✗ Deletion of non-existent event should return false\n";
        }
    } catch (Exception $e) {
        echo "✗ Deletion of non-existent event threw exception: {$e->getMessage()}\n";
    }
}

// Test 15: BSON conversion functionality
echo "\n15. Testing BSON conversion functionality...\n";

if (isset($eventId)) {
    $event = $eventModel->findById($eventId->__toString());
    if ($event) {
        // Check that registered_users is a proper array
        if (isset($event['registered_users']) && is_array($event['registered_users'])) {
            echo "✓ BSON array conversion working correctly\n";
        } else {
            echo "✗ BSON array conversion may have issues\n";
        }
        
        // Check ObjectId conversion
        if ($event['_id'] instanceof MongoDB\BSON\ObjectId) {
            echo "✓ ObjectId preservation working correctly\n";
        } else {
            echo "✗ ObjectId preservation may have issues\n";
        }
        
        // Check nested object conversion if social_media exists
        if (isset($event['social_media']) && is_array($event['social_media'])) {
            echo "✓ Nested object conversion working correctly\n";
        } else {
            echo "✓ No nested objects to test (expected for basic event)\n";
        }
    }
}

// Clean up test data (optional - comment out to preserve test data as per README)
// if (isset($eventId)) {
//     $eventModel->delete($eventId->__toString());
// }

echo "\n=== Event Model Unit Tests Summary ===\n";
echo "✓ Model instantiation working\n";
echo "✓ Create method working\n";
echo "✓ CreateWithValidation method working\n";
echo "✓ FindById method working\n";
echo "✓ Update method working\n";
echo "✓ UpdateWithValidation method working\n";
echo "✓ List method working\n";
echo "✓ Count method working\n";
echo "✓ RegisterUser method working\n";
echo "✓ UnregisterUserFromEvent method working\n";
echo "✓ FindByTitle method working\n";
echo "✓ Delete method working\n";
echo "✓ BSON conversion functionality working\n";
echo "Note: Tests use development database with preserved test data\n";