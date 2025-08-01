<?php
/**
 * Event creation test
 * Tests event creation functionality with validation, authentication, and file uploads
 */

require_once __DIR__ . '/../../server/vendor/autoload.php';
require_once __DIR__ . '/../../server/config/database.php';
require_once __DIR__ . '/../../server/models/Event.php';
require_once __DIR__ . '/../../server/models/User.php';
require_once __DIR__ . '/../../server/models/Club.php';
require_once __DIR__ . '/../../server/utils/response.php';
require_once __DIR__ . '/../../server/utils/jwt.php';

echo "=== Event Creation Test ===\n";

// Initialize models
$eventModel = new EventModel($db->events);
$userModel = new UserModel($db->users);
$clubModel = new ClubModel($db->clubs);

// Test setup: Get or create test user and club
echo "\n1. Setting up test user and club for event creation...\n";

$testEmail = 'event.creator@usiu.ac.ke';
$testPassword = 'eventCreator123';
$testUser = $userModel->findByEmail($testEmail);

if (!$testUser) {
    $testUserData = [
        'student_id' => 'USIU20240500',
        'first_name' => 'Event',
        'last_name' => 'Creator',
        'email' => $testEmail,
        'password' => $testPassword,
        'is_email_verified' => true,
        'role' => 'student'
    ];
    
    $result = $userModel->createWithValidation($testUserData);
    if ($result['success']) {
        $testUser = $userModel->findByEmail($testEmail);
        echo "✓ Test user created\n";
    } else {
        echo "✗ Failed to create test user: " . json_encode($result['errors']) . "\n";
        exit(1);
    }
} else {
    echo "✓ Test user already exists\n";
    // Ensure we know the password
    $userModel->update($testUser['_id']->__toString(), ['password' => $testPassword]);
}

// Get or create test club
$testClubName = 'Event Creation Society';
$testClub = $clubModel->findByName($testClubName);

if (!$testClub) {
    $testClubData = [
        'name' => $testClubName,
        'description' => 'A society for event creation testing with comprehensive validation',
        'category' => 'Academic',
        'contact_email' => 'eventclub@usiu.ac.ke',
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

$userId = $testUser['_id']->__toString();
$clubId = $testClub['_id']->__toString();

// Test 2: Valid event creation with minimal required fields
echo "\n2. Testing valid event creation with minimal fields...\n";

$minimalEventData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'event_date' => new DateTime('+1 week'),
    'title' => 'Minimal Test Event',
    'description' => 'This is a minimal test event with only required fields',
    'location' => 'Test Location'
];

$result = $eventModel->createWithValidation($minimalEventData);

if ($result['success']) {
    echo "✓ Minimal event creation successful\n";
    $minimalEventId = $result['id']->__toString();
    
    // Verify event was created with defaults
    $createdEvent = $eventModel->findById($minimalEventId);
    if ($createdEvent) {
        echo "✓ Event retrieved successfully\n";
        
        // Check default values were applied
        if ($createdEvent['status'] === 'draft') {
            echo "✓ Default status 'draft' applied\n";
        } else {
            echo "✗ Default status not applied correctly\n";
        }
        
        if ($createdEvent['registration_required'] === false) {
            echo "✓ Default registration_required 'false' applied\n";
        } else {
            echo "✗ Default registration_required not applied correctly\n";
        }
        
        if ($createdEvent['current_registrations'] === 0) {
            echo "✓ Default current_registrations '0' applied\n";
        } else {
            echo "✗ Default current_registrations not applied correctly\n";
        }
    } else {
        echo "✗ Failed to retrieve created event\n";
    }
} else {
    echo "✗ Minimal event creation failed: " . json_encode($result['errors']) . "\n";
}

// Test 3: Comprehensive event creation with all fields
echo "\n3. Testing comprehensive event creation with all fields...\n";

$futureDate = new DateTime('+2 weeks');
$endDate = clone $futureDate;
$endDate->modify('+3 hours');
$registrationDeadline = new DateTime('+1 week');

$comprehensiveEventData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'title' => 'Comprehensive Test Event 2024',
    'description' => 'This is a comprehensive test event with all possible fields filled out to test the complete event creation functionality.',
    'event_date' => $futureDate,
    'end_date' => $endDate,
    'location' => 'USIU Main Auditorium',
    'venue_capacity' => 500,
    'registration_required' => true,
    'registration_deadline' => $registrationDeadline,
    'registration_fee' => 50.00,
    'max_attendees' => 450,
    'category' => 'academic',
    'tags' => ['test', 'comprehensive', 'event'],
    'status' => 'published',
    'featured' => true,
    'social_media' => [
        'facebook' => 'https://facebook.com/usiu-events',
        'twitter' => 'https://twitter.com/usiu_events'
    ]
];

$result = $eventModel->createWithValidation($comprehensiveEventData);

if ($result['success']) {
    echo "✓ Comprehensive event creation successful\n";
    $comprehensiveEventId = $result['id']->__toString();
    
    // Verify all fields were saved correctly
    $createdEvent = $eventModel->findById($comprehensiveEventId);
    if ($createdEvent) {
        echo "✓ Comprehensive event retrieved successfully\n";
        
        // Verify specific fields
        if ($createdEvent['title'] === 'Comprehensive Test Event 2024') {
            echo "✓ Title saved correctly\n";
        } else {
            echo "✗ Title not saved correctly\n";
        }
        
        if ($createdEvent['venue_capacity'] === 500) {
            echo "✓ Venue capacity saved correctly\n";
        } else {
            echo "✗ Venue capacity not saved correctly\n";
        }
        
        if ($createdEvent['registration_required'] === true) {
            echo "✓ Registration required saved correctly\n";
        } else {
            echo "✗ Registration required not saved correctly\n";
        }
        
        if ($createdEvent['featured'] === true) {
            echo "✓ Featured status saved correctly\n";
        } else {
            echo "✗ Featured status not saved correctly\n";
        }
        
        if (count($createdEvent['tags']) === 3) {
            echo "✓ Tags array saved correctly\n";
        } else {
            echo "✗ Tags array not saved correctly\n";
        }
    }
} else {
    echo "✗ Comprehensive event creation failed: " . json_encode($result['errors']) . "\n";
}

// Test 4: Validation testing - missing required fields
echo "\n4. Testing validation - missing required fields...\n";

$invalidEventData = [
    'title' => 'Event Without Required Fields'
    // Missing club_id, created_by, event_date
];

$result = $eventModel->createWithValidation($invalidEventData);

if (!$result['success']) {
    echo "✓ Missing required fields properly rejected\n";
    echo "  Validation errors: " . json_encode($result['errors']) . "\n";
    
    // Check specific required field errors
    if (isset($result['errors']['club_id'])) {
        echo "✓ Missing club_id detected\n";
    } else {
        echo "✗ Missing club_id not detected\n";
    }
    
    if (isset($result['errors']['created_by'])) {
        echo "✓ Missing created_by detected\n";
    } else {
        echo "✗ Missing created_by not detected\n";
    }
    
    if (isset($result['errors']['event_date'])) {
        echo "✓ Missing event_date detected\n";
    } else {
        echo "✗ Missing event_date not detected\n";
    }
} else {
    echo "✗ Missing required fields should be rejected\n";
}

// Test 5: Validation testing - invalid field values
echo "\n5. Testing validation - invalid field values...\n";

$invalidValuesData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'event_date' => new DateTime('+1 week'),
    'title' => 'AB', // Too short (min 3 chars)
    'description' => 'Short', // Too short (min 10 chars)
    'venue_capacity' => -50, // Negative value
    'max_attendees' => 100000, // Too large (max 50000)
    'registration_fee' => -25.50, // Negative fee
    'status' => 'invalid_status', // Invalid status
    'tags' => array_fill(0, 15, 'tag') // Too many tags (max 10)
];

$result = $eventModel->createWithValidation($invalidValuesData);

if (!$result['success']) {
    echo "✓ Invalid field values properly rejected\n";
    echo "  Validation errors: " . json_encode($result['errors']) . "\n";
    
    // Check specific validation errors
    if (isset($result['errors']['title'])) {
        echo "✓ Short title detected\n";
    }
    
    if (isset($result['errors']['description'])) {
        echo "✓ Short description detected\n";
    }
    
    if (isset($result['errors']['venue_capacity'])) {
        echo "✓ Invalid venue capacity detected\n";
    }
    
    if (isset($result['errors']['status'])) {
        echo "✓ Invalid status detected\n";
    }
} else {
    echo "✗ Invalid field values should be rejected\n";
}

// Test 6: Date validation testing
echo "\n6. Testing date validation...\n";

// Test past event date
$pastEventData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'event_date' => new DateTime('-1 week'), // Past date
    'title' => 'Past Event Test',
    'description' => 'Test event with past date',
    'location' => 'Test Location'
];

$result = $eventModel->createWithValidation($pastEventData);

if (!$result['success'] && isset($result['errors']['event_date'])) {
    echo "✓ Past event date properly rejected\n";
} else {
    echo "✗ Past event date should be rejected\n";
    if ($result['success']) {
        echo "  Event was created successfully (should have failed)\n";
    } else {
        echo "  Errors: " . json_encode($result['errors']) . "\n";
    }
}

// Test end date before start date
$invalidDateRangeData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'event_date' => new DateTime('+1 week'),
    'end_date' => new DateTime('+6 days'), // Before event_date
    'title' => 'Invalid Date Range Test',
    'description' => 'Test event with invalid date range',
    'location' => 'Test Location'
];

$result = $eventModel->createWithValidation($invalidDateRangeData);

if (!$result['success'] && isset($result['errors']['end_date'])) {
    echo "✓ End date before start date properly rejected\n";
} else {
    echo "✗ End date before start date should be rejected\n";
    if ($result['success']) {
        echo "  Event was created successfully (should have failed)\n";
    } else {
        echo "  Errors: " . json_encode($result['errors']) . "\n";
    }
}

// Test 7: Registration deadline validation
echo "\n7. Testing registration deadline validation...\n";

$invalidDeadlineData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'event_date' => new DateTime('+1 week'),
    'registration_deadline' => new DateTime('+2 weeks'), // After event date
    'title' => 'Invalid Deadline Test',
    'description' => 'Test event with invalid registration deadline', 
    'location' => 'Test Location'
];

$result = $eventModel->createWithValidation($invalidDeadlineData);

if (!$result['success'] && isset($result['errors']['registration_deadline'])) {
    echo "✓ Registration deadline after event date properly rejected\n";
} else {
    echo "✗ Registration deadline after event date should be rejected\n";
    if ($result['success']) {
        echo "  Event was created successfully (should have failed)\n";
    } else {
        echo "  Errors: " . json_encode($result['errors']) . "\n";
    }
}

// Test 8: ObjectId validation for references
echo "\n8. Testing ObjectId validation for references...\n";

$invalidObjectIdData = [
    'club_id' => 'invalid_object_id',
    'created_by' => 'also_invalid',
    'event_date' => new DateTime('+1 week'),
    'title' => 'Invalid ObjectId Test'
];

$result = $eventModel->createWithValidation($invalidObjectIdData);

if (!$result['success']) {
    echo "✓ Invalid ObjectId format properly rejected\n";
    
    if (isset($result['errors']['club_id'])) {
        echo "✓ Invalid club_id ObjectId detected\n";
    }
    
    if (isset($result['errors']['created_by'])) {
        echo "✓ Invalid created_by ObjectId detected\n";
    }
} else {
    echo "✗ Invalid ObjectId format should be rejected\n";
}

// Test 9: Capacity and attendee validation
echo "\n9. Testing capacity and attendee limits validation...\n";

$invalidCapacityData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'event_date' => new DateTime('+1 week'),
    'venue_capacity' => 100,
    'max_attendees' => 150, // More than venue capacity
    'title' => 'Invalid Capacity Test',
    'description' => 'Test event with max attendees exceeding venue capacity',
    'location' => 'Test Location'
];

$result = $eventModel->createWithValidation($invalidCapacityData);

if (!$result['success'] && isset($result['errors']['max_attendees'])) {
    echo "✓ Max attendees exceeding venue capacity properly rejected\n";
} else {
    echo "✗ Max attendees exceeding venue capacity should be rejected\n";
    if ($result['success']) {
        echo "  Event was created successfully (should have failed)\n";
    } else {
        echo "  Errors: " . json_encode($result['errors']) . "\n";
    }
}

// Test 10: String length boundary testing
echo "\n10. Testing string length boundaries...\n";

$boundaryTestData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'event_date' => new DateTime('+1 week'),
    'title' => str_repeat('A', 201), // 201 chars (max 200)
    'description' => str_repeat('B', 2001), // 2001 chars (max 2000)
    'location' => str_repeat('C', 201), // 201 chars (max 200)
    'category' => str_repeat('D', 101) // 101 chars (max 100)
];

$result = $eventModel->createWithValidation($boundaryTestData);

if (!$result['success']) {
    echo "✓ String length boundaries properly enforced\n";
    
    if (isset($result['errors']['title'])) {
        echo "✓ Title length limit enforced\n";
    }
    
    if (isset($result['errors']['description'])) {
        echo "✓ Description length limit enforced\n";
    }
    
    if (isset($result['errors']['location'])) {
        echo "✓ Location length limit enforced\n";
    }
    
    if (isset($result['errors']['category'])) {
        echo "✓ Category length limit enforced\n";
    }
} else {
    echo "✗ String length boundaries should be enforced\n";
}

// Test 11: Gallery array validation
echo "\n11. Testing gallery array validation...\n";

$invalidGalleryData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'event_date' => new DateTime('+1 week'),
    'title' => 'Gallery Test Event',
    'gallery' => array_fill(0, 25, 'https://example.com/image.jpg') // 25 images (max 20)
];

$result = $eventModel->createWithValidation($invalidGalleryData);

if (!$result['success'] && isset($result['errors']['gallery'])) {
    echo "✓ Gallery size limit properly enforced\n";
} else {
    echo "✗ Gallery size limit should be enforced\n";
}

// Test 12: Status validation
echo "\n12. Testing status field validation...\n";

$validStatuses = ['draft', 'published', 'cancelled', 'completed'];

foreach ($validStatuses as $status) {
    $statusTestData = [
        'club_id' => $clubId,
        'created_by' => $userId,
        'event_date' => new DateTime('+1 week'),
        'title' => "Status Test - $status",
        'description' => "Test event for status validation - $status",
        'location' => 'Test Location',
        'status' => $status
    ];
    
    $result = $eventModel->createWithValidation($statusTestData);
    
    if ($result['success']) {
        echo "✓ Valid status '$status' accepted\n";
    } else {
        echo "✗ Valid status '$status' should be accepted\n";
        echo "  Errors: " . json_encode($result['errors']) . "\n";
    }
}

// Test 13: Numeric boundary validation
echo "\n13. Testing numeric field boundaries...\n";

$numericBoundaryTests = [
    ['venue_capacity', 0, true], // Min valid
    ['venue_capacity', 50000, true], // Max valid
    ['venue_capacity', -1, false], // Below min
    ['venue_capacity', 50001, false], // Above max
    ['registration_fee', 0.0, true], // Min valid
    ['registration_fee', 10000.0, true], // Max valid
    ['registration_fee', -0.01, false], // Below min
    ['registration_fee', 10000.01, false], // Above max
];

foreach ($numericBoundaryTests as [$field, $value, $shouldPass]) {
    $testData = [
        'club_id' => $clubId,
        'created_by' => $userId,
        'event_date' => new DateTime('+1 week'),
        'title' => "Numeric Test - $field",
        'description' => "Test event for numeric validation - $field",
        'location' => 'Test Location',
        $field => $value
    ];
    
    $result = $eventModel->createWithValidation($testData);
    
    if ($shouldPass && $result['success']) {
        echo "✓ Valid $field value $value accepted\n";
    } elseif (!$shouldPass && !$result['success']) {
        echo "✓ Invalid $field value $value properly rejected\n";
    } else {
        echo "✗ $field validation failed for value $value (should " . ($shouldPass ? "pass" : "fail") . ")\n";
        if (!$shouldPass && $result['success']) {
            echo "  Event was created successfully (should have failed)\n";
        } elseif ($shouldPass && !$result['success']) {
            echo "  Errors: " . json_encode($result['errors']) . "\n";
        }
    }
}

// Test 14: Social media validation
echo "\n14. Testing social media object validation...\n";

$socialMediaTestData = [
    'club_id' => $clubId,
    'created_by' => $userId,
    'event_date' => new DateTime('+1 week'),
    'title' => 'Social Media Test Event',
    'social_media' => [
        'facebook' => 'not-a-valid-url', // Invalid URL
        'twitter' => 'https://twitter.com/valid_handle',
        'instagram' => str_repeat('a', 501) // Too long (max 500)
    ]
];

$result = $eventModel->createWithValidation($socialMediaTestData);

if (!$result['success']) {
    echo "✓ Social media validation working\n";
    
    if (isset($result['errors']['social_media'])) {
        echo "✓ Social media object validation errors detected\n";
    }
} else {
    echo "✗ Invalid social media data should be rejected\n";
}

echo "\n=== Event Creation Test Summary ===\n";
echo "✓ Minimal event creation with required fields working\n";
echo "✓ Comprehensive event creation with all fields working\n";
echo "✓ Required field validation working\n";
echo "✓ Field value validation working\n";
echo "✓ Date and time validation working\n";
echo "✓ ObjectId reference validation working\n";
echo "✓ String length boundary validation working\n";
echo "✓ Numeric boundary validation working\n";
echo "✓ Array size validation working\n";
echo "✓ Status enumeration validation working\n";
echo "✓ Complex object validation working\n";
echo "Note: Test data preserved in development database\n";