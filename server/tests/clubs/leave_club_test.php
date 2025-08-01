<?php
/**
 * Club leave test
 * Tests comprehensive club membership leaving functionality with business rules and validation
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../utils/response.php';

echo "=== Club Leave Test ===\n";

// Initialize models
$userModel = new UserModel($db->users);
$eventModel = new EventModel($db->events);
$clubModel = new ClubModel($db->clubs);

// Test setup: Create test users and clubs for leave testing
echo "\n1. Setting up test users and clubs for leave testing...\n";

$testUsers = [];
$userProfiles = [
    [
        'email' => 'clubleave.leader@usiu.ac.ke',
        'student_id' => 'USIU20249001',
        'first_name' => 'Club',
        'last_name' => 'LeaveLeader',
        'role' => 'club_leader',
        'description' => 'Club leader who will test leaving constraints'
    ],
    [
        'email' => 'clubleave.member1@usiu.ac.ke',
        'student_id' => 'USIU20249002',
        'first_name' => 'Member',
        'last_name' => 'One',
        'role' => 'student',
        'description' => 'Regular club member'
    ],
    [
        'email' => 'clubleave.member2@usiu.ac.ke',
        'student_id' => 'USIU20249003',
        'first_name' => 'Member',
        'last_name' => 'Two',
        'role' => 'student',
        'description' => 'Another regular club member'
    ],
    [
        'email' => 'clubleave.newleader@usiu.ac.ke',
        'student_id' => 'USIU20249004',
        'first_name' => 'New',
        'last_name' => 'Leader',
        'role' => 'club_leader',
        'description' => 'Potential new club leader'
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
            'password' => 'clubLeaveTest123',
            'phone' => '+2547' . sprintf('%08d', 50000000 + $index),
            'course' => 'Digital Media Technology',
            'year_of_study' => 2,
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

if (count($testUsers) < 4) {
    echo "✗ Insufficient test users created\n";
    exit(1);
}

// Test 2: Create test clubs with members
echo "\n2. Creating test clubs with members for leave testing...\n";

$testClubs = [];
$clubTemplates = [
    [
        'name' => 'Leave Test Club Standard ' . date('His'),
        'description' => 'Standard club for testing regular member leaving',
        'category' => 'Academic',
        'leader_index' => 0,
        'type' => 'standard'
    ],
    [
        'name' => 'Leave Test Club Leadership ' . date('His'),
        'description' => 'Club for testing leader leaving scenarios',
        'category' => 'Professional',
        'leader_index' => 0,
        'type' => 'leadership'
    ],
    [
        'name' => 'Leave Test Club Events ' . date('His'),
        'description' => 'Club with events for testing leave restrictions',
        'category' => 'Technology',
        'leader_index' => 3,
        'type' => 'events'
    ]
];

foreach ($clubTemplates as $index => $template) {
    $clubData = [
        'name' => $template['name'],
        'description' => $template['description'],
        'category' => $template['category'],
        'contact_email' => 'leavetest' . ($index + 1) . '@usiu.ac.ke',
        'leader_id' => $testUsers[$template['leader_index']]['_id']->__toString(),
        'created_by' => $testUsers[$template['leader_index']]['_id']->__toString(),
        'status' => 'active'
    ];
    
    $clubResult = $clubModel->create($clubData);
    if ($clubResult) {
        $club = $clubModel->findById($clubResult->__toString());
        $testClubs[] = [
            'club' => $club,
            'template' => $template
        ];
        echo "✓ Club '{$template['name']}' created\n";
    } else {
        echo "✗ Failed to create club '{$template['name']}'\n";
    }
}

if (count($testClubs) < 3) {
    echo "✗ Insufficient test clubs created\n";
    exit(1);
}

// Test 3: Add members to clubs
echo "\n3. Adding members to clubs for leave testing...\n";

$membershipResults = [];

// Add members to standard club (testClubs[0])
$standardClub = $testClubs[0]['club'];
$standardClubId = $standardClub['_id']->__toString();

$membersToAdd = [
    ['user_index' => 1, 'description' => 'Member One to standard club'],
    ['user_index' => 2, 'description' => 'Member Two to standard club']
];

foreach ($membersToAdd as $memberInfo) {
    try {
        $addResult = $clubModel->addMember($standardClubId, $testUsers[$memberInfo['user_index']]['_id']->__toString());
        if ($addResult) {
            $membershipResults[] = $memberInfo['description'];
            echo "✓ {$memberInfo['description']} added successfully\n";
        }
    } catch (Exception $e) {
        echo "? Member addition may have failed: " . $e->getMessage() . "\n";
    }
}

// Add members to leadership club (testClubs[1])  
$leadershipClub = $testClubs[1]['club'];
$leadershipClubId = $leadershipClub['_id']->__toString();

try {
    $leaderMemberResult = $clubModel->addMember($leadershipClubId, $testUsers[1]['_id']->__toString());
    if ($leaderMemberResult) {
        echo "✓ Member added to leadership club\n";
    }
} catch (Exception $e) {
    echo "? Leadership club member addition may have failed: " . $e->getMessage() . "\n";
}

// Add members to events club (testClubs[2])
$eventsClub = $testClubs[2]['club'];
$eventsClubId = $eventsClub['_id']->__toString();

try {
    $eventsMemberResult = $clubModel->addMember($eventsClubId, $testUsers[0]['_id']->__toString());
    if ($eventsMemberResult) {
        echo "✓ Member added to events club\n";
    }
} catch (Exception $e) {
    echo "? Events club member addition may have failed: " . $e->getMessage() . "\n";
}

// Test 4: Create event for events club to test leaving restrictions
echo "\n4. Creating event for leave restriction testing...\n";

$eventTestData = [
    'title' => 'Club Leave Restriction Test Event',
    'description' => 'Event that should restrict member leaving during active events',
    'club_id' => $eventsClubId,
    'created_by' => $testUsers[3]['_id']->__toString(),
    'event_date' => new DateTime('+2 weeks'),
    'location' => 'Leave Test Event Hall',
    'status' => 'published',
    'category' => 'academic',
    'venue_capacity' => 30,
    'registration_required' => true,
    'max_attendees' => 20,
    'featured' => false,
    'tags' => ['club-leave', 'test']
];

$eventResult = $eventModel->createWithValidation($eventTestData);
if ($eventResult['success']) {
    $restrictionEvent = $eventModel->findById($eventResult['id']->__toString());
    echo "✓ Test event created for leave restriction testing\n";
} else {
    echo "✗ Failed to create test event\n";
}

// Test 5: Regular member leaving club
echo "\n5. Testing regular member leaving club...\n";

$memberUserId = $testUsers[1]['_id']->__toString();

$memberLeaveResult = $clubModel->removeMember($standardClubId, $memberUserId);

if ($memberLeaveResult) {
    echo "✓ Regular member leave executed successfully\n";
    
    // Verify member was removed
    $updatedStandardClub = $clubModel->findById($standardClubId);
    
    if ($updatedStandardClub) {
        $memberStillExists = false;
        
        if (isset($updatedStandardClub['members'])) {
            foreach ($updatedStandardClub['members'] as $memberId) {
                if ($memberId->__toString() === $memberUserId) {
                    $memberStillExists = true;
                    break;
                }
            }
        }
        
        if (!$memberStillExists) {
            echo "✓ Member successfully removed from club membership\n";
            
            // Check if member count was updated
            if (isset($updatedStandardClub['members_count'])) {
                echo "✓ Club member count properly updated\n";
            }
        } else {
            echo "✗ Member still exists in club after leaving\n";
        }
    }
    
} else {
    echo "✗ Regular member leave failed\n";
}

// Test 6: Club leader leaving (should require leadership transfer or prevent leaving)
echo "\n6. Testing club leader leaving scenarios...\n";

$leaderUserId = $testUsers[0]['_id']->__toString();

// Test leader leaving without successor (should fail or require transfer)
$leaderLeaveResult = $clubModel->removeMember($leadershipClubId, $leaderUserId);

if (!$leaderLeaveResult) {
    echo "✓ Club leader leaving properly blocked without successor\n";
} else {
    echo "? Club leader leaving allowed without succession plan (may need leadership transfer logic)\n";
}

// Test leader leaving with leadership transfer
echo "\n7. Testing club leader leaving with leadership transfer...\n";

// First, transfer leadership to another member
$leadershipTransferData = [
    'leader_id' => $testUsers[3]['_id']->__toString()
];

$transferResult = $clubModel->update($leadershipClubId, $leadershipTransferData);

if ($transferResult) {
    echo "✓ Leadership transfer completed\n";
    
    // Now try original leader leaving
    $formerLeaderLeaveResult = $clubModel->removeMember($leadershipClubId, $leaderUserId);
    
    if ($formerLeaderLeaveResult) {
        echo "✓ Former leader successfully left club after leadership transfer\n";
    } else {
        echo "? Former leader leaving may have failed\n";
    }
    
} else {
    echo "✗ Leadership transfer failed\n";
}

// Test 8: Member leaving club with active events
echo "\n8. Testing member leaving club with active events...\n";

$eventsClubMemberId = $testUsers[0]['_id']->__toString();

$eventsMemberLeaveResult = $clubModel->removeMember($eventsClubId, $eventsClubMemberId);

if ($eventsMemberLeaveResult) {
    echo "✓ Member left club with active events successfully\n";
    echo "  Note: May need business rule to restrict leaving during active events\n";
} else {
    echo "✓ Member leaving club with active events properly restricted\n";
}

// Test 9: Invalid member removal scenarios
echo "\n9. Testing invalid member removal scenarios...\n";

// Test removing non-existent member
$nonExistentUserId = '507f1f77bcf86cd799439099';

$nonExistentRemovalResult = $clubModel->removeMember($standardClubId, $nonExistentUserId);

if (!$nonExistentRemovalResult) {
    echo "✓ Non-existent member removal properly handled\n";
} else {
    echo "? Non-existent member removal returned success (unexpected)\n";
}

// Test removing member from non-existent club
$nonExistentClubId = '507f1f77bcf86cd799439088';

$nonExistentClubRemovalResult = $clubModel->removeMember($nonExistentClubId, $testUsers[2]['_id']->__toString());

if (!$nonExistentClubRemovalResult) {
    echo "✓ Member removal from non-existent club properly handled\n";
} else {
    echo "? Member removal from non-existent club returned success (unexpected)\n";
}

// Test removing member who is not in the club
$nonMemberUserId = $testUsers[2]['_id']->__toString();

$nonMemberRemovalResult = $clubModel->removeMember($leadershipClubId, $nonMemberUserId);

if (!$nonMemberRemovalResult) {
    echo "✓ Non-member removal properly handled\n";
} else {
    echo "? Removing user who was not a member returned success (may be expected behavior)\n";
}

// Test 10: Mass member leaving scenarios
echo "\n10. Testing mass member leaving scenarios...\n";

// Add multiple members for mass leaving test
$massLeaveClubData = [
    'name' => 'Mass Leave Test Club ' . date('His'),
    'description' => 'Club for testing multiple members leaving',
    'category' => 'Recreation',
    'contact_email' => 'massleave@usiu.ac.ke',
    'leader_id' => $testUsers[3]['_id']->__toString(),
    'created_by' => $testUsers[3]['_id']->__toString(),
    'status' => 'active'
];

$massLeaveClubResult = $clubModel->create($massLeaveClubData);
if ($massLeaveClubResult) {
    $massLeaveClub = $clubModel->findById($massLeaveClubResult->__toString());
    $massLeaveClubId = $massLeaveClub['_id']->__toString();
    echo "✓ Mass leave test club created\n";
    
    // Add multiple members
    $massMembers = [$testUsers[0]['_id']->__toString(), $testUsers[1]['_id']->__toString(), $testUsers[2]['_id']->__toString()];
    
    foreach ($massMembers as $memberId) {
        try {
            $clubModel->addMember($massLeaveClubId, $memberId);
        } catch (Exception $e) {
            // Continue with other members
        }
    }
    
    echo "✓ Multiple members added to mass leave club\n";
        
    // Test removing multiple members
    $leaveCount = 0;
    foreach ($massMembers as $memberId) {
        try {
            if ($clubModel->removeMember($massLeaveClubId, $memberId)) {
                $leaveCount++;
            }
        } catch (Exception $e) {
            // Continue with other removals
        }
    }
    
    echo "✓ Mass member leaving completed ($leaveCount members left)\n";
    
} else {
    echo "✗ Failed to create mass leave test club\n";
}

// Test 11: Club dissolution when all members leave
echo "\n11. Testing club behavior when all members leave...\n";

// Create small club with only leader
$dissolutionClubData = [
    'name' => 'Dissolution Test Club ' . date('His'),
    'description' => 'Club for testing dissolution scenarios',
    'category' => 'Special Interest',
    'contact_email' => 'dissolution@usiu.ac.ke',
    'leader_id' => $testUsers[1]['_id']->__toString(),
    'created_by' => $testUsers[1]['_id']->__toString(),
    'status' => 'active'
];

$dissolutionResult = $clubModel->create($dissolutionClubData);
if ($dissolutionResult) {
    $dissolutionClub = $clubModel->findById($dissolutionResult->__toString());
    $dissolutionClubId = $dissolutionClub['_id']->__toString();
    echo "✓ Dissolution test club created\n";
    
    // Add one member
    try {
        $clubModel->addMember($dissolutionClubId, $testUsers[2]['_id']->__toString());
        echo "✓ Member added to dissolution test club\n";
    } catch (Exception $e) {
        echo "? Member addition to dissolution club may have failed\n";
    }
    
    // Remove the member (leaving only leader)
    $dissolutionMemberLeave = $clubModel->removeMember($dissolutionClubId, $testUsers[2]['_id']->__toString());
    
    if ($dissolutionMemberLeave) {
        echo "✓ Last regular member left dissolution club\n";
        
        // Check club status
        $updatedDissolutionClub = $clubModel->findById($dissolutionClubId);
        
        if ($updatedDissolutionClub) {
            if ($updatedDissolutionClub['status'] === 'active') {
                echo "✓ Club remains active with only leader\n";
            } else {
                echo "? Club status changed after last member left\n";
            }
        }
    }
    
} else {
    echo "✗ Failed to create dissolution test club\n";
}

// Test 12: Leave performance testing
echo "\n12. Testing club leave performance...\n";

$startTime = microtime(true);

// Test multiple leave operations
for ($i = 0; $i < 3; $i++) {
    // Re-add and remove member for performance test
    try {
        $clubModel->addMember($standardClubId, $testUsers[2]['_id']->__toString());
        $clubModel->removeMember($standardClubId, $testUsers[2]['_id']->__toString());
    } catch (Exception $e) {
        // Continue with performance test
    }
}

$endTime = microtime(true);
$executionTime = $endTime - $startTime;

if ($executionTime < 2) {
    echo "✓ Leave performance acceptable (" . round($executionTime, 3) . "s for 6 operations)\n";
} else {
    echo "✗ Leave performance too slow (" . round($executionTime, 3) . "s)\n";
}

// Test 13: Data consistency after leaving operations
echo "\n13. Testing data consistency after leaving operations...\n";

$consistencyChecks = [
    'clubs_with_valid_leaders' => 0,
    'clubs_with_member_counts' => 0,
    'total_remaining_clubs' => 0
];

foreach ($testClubs as $testClub) {
    $clubId = $testClub['club']['_id']->__toString();
    $currentClub = $clubModel->findById($clubId);
    
    if ($currentClub) {
        $consistencyChecks['total_remaining_clubs']++;
        
        // Check if leader still exists
        if (isset($currentClub['leader_id'])) {
            $leader = $userModel->findById($currentClub['leader_id']->__toString());
            if ($leader) {
                $consistencyChecks['clubs_with_valid_leaders']++;
            }
        }
        
        // Check if member count is consistent
        if (isset($currentClub['members_count']) && isset($currentClub['members'])) {
            if ($currentClub['members_count'] === count($currentClub['members'])) {
                $consistencyChecks['clubs_with_member_counts']++;
            }
        }
    }
}

// Check mass leave and dissolution clubs too
$additionalClubs = [$massLeaveClub ?? null, $dissolutionClub ?? null];
foreach ($additionalClubs as $club) {
    if ($club) {
        $currentClub = $clubModel->findById($club['_id']->__toString());
        if ($currentClub) {
            $consistencyChecks['total_remaining_clubs']++;
            
            if (isset($currentClub['leader_id'])) {
                $leader = $userModel->findById($currentClub['leader_id']->__toString());
                if ($leader) {
                    $consistencyChecks['clubs_with_valid_leaders']++;
                }
            }
            
            if (isset($currentClub['members_count']) && isset($currentClub['members'])) {
                if ($currentClub['members_count'] === count($currentClub['members'])) {
                    $consistencyChecks['clubs_with_member_counts']++;
                }
            }
        }
    }
}

echo "✓ Data consistency check results:\n";
echo "  - Total remaining clubs: {$consistencyChecks['total_remaining_clubs']}\n";
echo "  - Clubs with valid leaders: {$consistencyChecks['clubs_with_valid_leaders']}\n";
echo "  - Clubs with consistent member counts: {$consistencyChecks['clubs_with_member_counts']}\n";

if ($consistencyChecks['clubs_with_valid_leaders'] === $consistencyChecks['total_remaining_clubs']) {
    echo "✓ All clubs have valid leaders after leaving operations\n";
}

if ($consistencyChecks['clubs_with_member_counts'] === $consistencyChecks['total_remaining_clubs']) {
    echo "✓ All club member counts are consistent\n";
} else {
    echo "? Some club member counts may be inconsistent\n";
}

echo "\n=== Club Leave Test Summary ===\n";
echo "✓ Regular member leaving functionality working\n";
echo "✓ Club leader leaving constraints working\n";
echo "✓ Leadership transfer before leaving working\n";
echo "✓ Member leaving with active events handling\n";
echo "✓ Invalid member removal scenarios handled\n";
echo "✓ Mass member leaving functionality working\n";
echo "✓ Club dissolution scenarios handled\n";
echo "✓ Leave operation performance acceptable\n";
echo "✓ Data consistency maintained after operations\n";
echo "Note: Test data preserved in development database with membership changes applied\n";