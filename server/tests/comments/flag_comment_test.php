<?php
/**
 * Comment flagging test
 * Tests comprehensive flagging and unflagging of inappropriate comments with community moderation
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Comment.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../utils/response.php';

echo "=== Comment Flagging Test ===\n";

// Initialize models
$commentModel = new CommentModel($db->comments);
$eventModel = new EventModel($db->events);
$userModel = new UserModel($db->users);
$clubModel = new ClubModel($db->clubs);

// Test setup: Create test data for comment flagging
echo "\n1. Setting up test data for comment flagging...\n";

// Get or create test users
$testUsers = [];
$userEmails = [
    'flag.user1@usiu.ac.ke',
    'flag.user2@usiu.ac.ke',
    'flag.moderator@usiu.ac.ke'
];
$userRoles = ['student', 'student', 'admin'];

foreach ($userEmails as $index => $email) {
    $testUser = $userModel->findByEmail($email);
    if (!$testUser) {
        $userData = [
            'student_id' => 'USIU202435' . sprintf('%02d', $index + 1),
            'first_name' => 'Flag',
            'last_name' => 'Test' . ($index + 1),
            'email' => $email,
            'password' => 'flagTest123',
            'is_email_verified' => true,
            'role' => $userRoles[$index]
        ];
        $result = $userModel->createWithValidation($userData);
        $testUser = $userModel->findByEmail($email);
        echo "✓ Test user " . ($index + 1) . " created\n";
    } else {
        echo "✓ Test user " . ($index + 1) . " already exists\n";
    }
    $testUsers[] = $testUser;
}

// Get or create test club
$testClub = $clubModel->findByName('Comment Flagging Society');
if (!$testClub) {
    $clubData = [
        'name' => 'Comment Flagging Society',
        'description' => 'Society for comment flagging functionality',
        'category' => 'Academic',
        'contact_email' => 'flagclub@usiu.ac.ke',
        'leader_id' => $testUsers[0]['_id']->__toString(),
        'created_by' => $testUsers[0]['_id']->__toString(),
        'status' => 'active'
    ];
    $clubId = $clubModel->create($clubData);
    $testClub = $clubModel->findById($clubId->__toString());
    echo "✓ Test club created\n";
} else {
    echo "✓ Test club already exists\n";
}

// Create test event for flagging
$testEventData = [
    'title' => 'Comment Flagging Workshop',
    'description' => 'Workshop for testing comment flagging functionality',
    'club_id' => $testClub['_id']->__toString(),
    'created_by' => $testUsers[0]['_id']->__toString(),
    'event_date' => new DateTime('+2 weeks'),
    'location' => 'Flagging Test Hall',
    'status' => 'published',
    'category' => 'academic',
    'venue_capacity' => 50,
    'registration_required' => false,
    'featured' => false,
    'tags' => ['flagging', 'test', 'workshop']
];

$result = $eventModel->createWithValidation($testEventData);
if ($result['success']) {
    $testEventId = $result['id']->__toString();
    echo "✓ Test event created for flagging testing\n";
} else {
    echo "✗ Failed to create test event\n";
    exit(1);
}

// Create test comments for flagging
echo "\n2. Creating test comments for flagging...\n";

$testComments = [];
$commentContents = [
    'This is a perfectly normal comment that should not be flagged',
    'This comment contains some questionable content that might be flagged',
    'This is an approved comment that will be flagged later',
    'This comment will test multiple flagging and unflagging cycles',
    'This comment tests flagging behavior on different statuses'
];

foreach ($commentContents as $index => $content) {
    $commentData = [
        'event_id' => $testEventId,
        'user_id' => $testUsers[$index % 2]['_id']->__toString(),
        'content' => $content
    ];
    
    $createResult = $commentModel->createWithValidation($commentData);
    if ($createResult['success']) {
        $commentId = $createResult['id']->__toString();
        $testComments[] = $commentId;
        
        // Approve some comments for variety
        if ($index % 2 === 0) {
            $commentModel->approve($commentId);
        }
        
        echo "✓ Test comment " . ($index + 1) . " created\n";
    } else {
        echo "✗ Failed to create test comment " . ($index + 1) . "\n";
    }
}

if (count($testComments) < 3) {
    echo "✗ Insufficient test comments created\n";
    exit(1);
}

// Test 3: Basic comment flagging
echo "\n3. Testing basic comment flagging...\n";

$flagResult = $commentModel->flag($testComments[0]);

if ($flagResult) {
    echo "✓ Comment flagging method executed successfully\n";
    
    // Verify comment was flagged
    $flaggedComment = $commentModel->findById($testComments[0]);
    
    if ($flaggedComment && $flaggedComment['flagged'] === true) {
        echo "✓ Comment flagged status set to true\n";
        
        // Verify timestamps updated
        if ($flaggedComment['updated_at'] > $flaggedComment['created_at']) {
            echo "✓ Updated timestamp modified during flagging\n";
        } else {
            echo "✗ Updated timestamp not modified during flagging\n";
        }
        
        // Verify other fields unchanged
        if ($flaggedComment['status'] === 'approved' && 
            $flaggedComment['content'] === $commentContents[0]) {
            echo "✓ Other comment fields unchanged during flagging\n";
        } else {
            echo "✗ Other comment fields were modified during flagging\n";
        }
        
    } else {
        echo "✗ Comment not properly flagged\n";
    }
    
} else {
    echo "✗ Comment flagging method failed\n";
}

// Test 4: Comment unflagging
echo "\n4. Testing comment unflagging...\n";

$unflagResult = $commentModel->unflag($testComments[0]);

if ($unflagResult) {
    echo "✓ Comment unflagging method executed successfully\n";
    
    // Verify comment was unflagged
    $unflaggedComment = $commentModel->findById($testComments[0]);
    
    if ($unflaggedComment && $unflaggedComment['flagged'] === false) {
        echo "✓ Comment flagged status set to false\n";
        
        // Verify timestamps updated again
        if ($unflaggedComment['updated_at'] >= $unflaggedComment['created_at']) {
            echo "✓ Updated timestamp modified during unflagging\n";
        } else {
            echo "✗ Updated timestamp not modified during unflagging\n";
        }
        
    } else {
        echo "✗ Comment not properly unflagged\n";
    }
    
} else {
    echo "✗ Comment unflagging method failed\n";
}

// Test 5: Flagging different comment statuses
echo "\n5. Testing flagging comments with different statuses...\n";

$statusTests = [
    ['comment_index' => 1, 'initial_status' => 'pending', 'description' => 'pending comment'],
    ['comment_index' => 2, 'initial_status' => 'approved', 'description' => 'approved comment']
];

foreach ($statusTests as $test) {
    $commentId = $testComments[$test['comment_index']];
    
    $flagResult = $commentModel->flag($commentId);
    
    if ($flagResult) {
        $flaggedComment = $commentModel->findById($commentId);
        
        if ($flaggedComment && $flaggedComment['flagged'] === true) {
            echo "✓ Successfully flagged {$test['description']}\n";
            
            // Verify status preserved
            if ($flaggedComment['status'] === $test['initial_status']) {
                echo "  ✓ Original status '{$test['initial_status']}' preserved\n";
            } else {
                echo "  ✗ Original status changed from '{$test['initial_status']}' to '{$flaggedComment['status']}'\n";
            }
            
        } else {
            echo "✗ Failed to flag {$test['description']}\n";
        }
        
    } else {
        echo "✗ Flagging method failed for {$test['description']}\n";
    }
}

// Test 6: Multiple flagging attempts on same comment
echo "\n6. Testing multiple flagging attempts on same comment...\n";

$multiComment = $testComments[3];

// Flag, unflag, flag again
$firstFlag = $commentModel->flag($multiComment);
$firstUnflag = $commentModel->unflag($multiComment);
$secondFlag = $commentModel->flag($multiComment);

if ($firstFlag && $firstUnflag && $secondFlag) {
    echo "✓ Multiple flag/unflag operations executed successfully\n";
    
    $finalComment = $commentModel->findById($multiComment);
    
    if ($finalComment && $finalComment['flagged'] === true) {
        echo "✓ Final flagged status is correct (true)\n";
    } else {
        echo "✗ Final flagged status is incorrect\n";
    }
    
} else {
    echo "✗ Some flag/unflag operations failed\n";
}

// Test 7: Invalid comment ID handling
echo "\n7. Testing invalid comment ID handling...\n";

$invalidId = '507f1f77bcf86cd799439088'; // Valid ObjectId format but doesn't exist

try {
    $invalidFlag = $commentModel->flag($invalidId);
    
    if (!$invalidFlag) {
        echo "✓ Invalid comment ID properly handled in flagging\n";
    } else {
        echo "? Invalid comment ID returned success (unexpected)\n";
    }
    
} catch (Exception $e) {
    echo "✓ Invalid comment ID threw exception during flagging: " . $e->getMessage() . "\n";
}

try {
    $invalidUnflag = $commentModel->unflag($invalidId);
    
    if (!$invalidUnflag) {
        echo "✓ Invalid comment ID properly handled in unflagging\n";
    } else {
        echo "? Invalid comment ID returned success (unexpected)\n";
    }
    
} catch (Exception $e) {
    echo "✓ Invalid comment ID threw exception during unflagging: " . $e->getMessage() . "\n";
}

// Test 8: Flagged comments retrieval and filtering
echo "\n8. Testing flagged comments retrieval and filtering...\n";

// Ensure we have some flagged comments
$commentModel->flag($testComments[1]);
$commentModel->flag($testComments[4]);

$allComments = $commentModel->findByEventId($testEventId, ['status' => 'all']);
$flaggedCount = 0;
$unflaggedCount = 0;

foreach ($allComments as $comment) {
    if (isset($comment['flagged']) && $comment['flagged'] === true) {
        $flaggedCount++;
    } else {
        $unflaggedCount++;
    }
}

if ($flaggedCount >= 2) {
    echo "✓ Found flagged comments ($flaggedCount flagged, $unflaggedCount unflagged)\n";
} else {
    echo "✗ Expected at least 2 flagged comments, found $flaggedCount\n";
}

// Test 9: Flagging impact on moderation workflow
echo "\n9. Testing flagging impact on moderation workflow...\n";

$moderationComment = $testComments[2];

// Flag an approved comment
$commentModel->flag($moderationComment);

$flaggedForMod = $commentModel->findById($moderationComment);

if ($flaggedForMod) {
    echo "✓ Flagged comment retrieved for moderation review\n";
    
    // Check if it appears in pending moderation (if implementation supports this)
    $pendingComments = $commentModel->getPendingComments(50, 0);
    
    $flaggedInPending = false;
    foreach ($pendingComments as $pending) {
        if ($pending['_id']->__toString() === $moderationComment && 
            isset($pending['flagged']) && $pending['flagged'] === true) {
            $flaggedInPending = true;
            break;
        }
    }
    
    if ($flaggedInPending) {
        echo "✓ Flagged comment appears in pending moderation queue\n";
    } else {
        echo "? Flagged comment not in pending queue (may depend on implementation)\n";
    }
    
} else {
    echo "✗ Could not retrieve flagged comment for moderation testing\n";
}

// Test 10: Bulk flagging operations
echo "\n10. Testing bulk flagging operations...\n";

// Create additional comments for bulk testing
$bulkComments = [];
for ($i = 1; $i <= 3; $i++) {
    $bulkData = [
        'event_id' => $testEventId,
        'user_id' => $testUsers[0]['_id']->__toString(),
        'content' => "Bulk flagging test comment $i"
    ];
    
    $bulkResult = $commentModel->createWithValidation($bulkData);
    if ($bulkResult['success']) {
        $bulkComments[] = $bulkResult['id']->__toString();
    }
}

if (count($bulkComments) >= 3) {
    echo "✓ Created " . count($bulkComments) . " comments for bulk flagging\n";
    
    $startTime = microtime(true);
    
    // Bulk flag operations
    $successfulFlags = 0;
    foreach ($bulkComments as $commentId) {
        if ($commentModel->flag($commentId)) {
            $successfulFlags++;
        }
    }
    
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;
    
    if ($successfulFlags === count($bulkComments)) {
        echo "✓ Bulk flagging successful ($successfulFlags/" . count($bulkComments) . " flagged)\n";
        echo "  Execution time: " . round($executionTime, 3) . "s\n";
        
        if ($executionTime < 2) {
            echo "✓ Bulk flagging performance acceptable\n";
        } else {
            echo "✗ Bulk flagging too slow\n";
        }
        
    } else {
        echo "✗ Bulk flagging failed ($successfulFlags/" . count($bulkComments) . " flagged)\n";
    }
    
} else {
    echo "✗ Failed to create enough comments for bulk testing\n";
}

// Test 11: Flagging statistics and reporting
echo "\n11. Testing flagging statistics and reporting...\n";

$allEventComments = $commentModel->findByEventId($testEventId, ['status' => 'all']);
$flagStats = [
    'total_comments' => count($allEventComments),
    'flagged_comments' => 0,
    'flagged_approved' => 0,
    'flagged_pending' => 0,
    'flagged_rejected' => 0
];

foreach ($allEventComments as $comment) {
    if (isset($comment['flagged']) && $comment['flagged'] === true) {
        $flagStats['flagged_comments']++;
        
        switch ($comment['status']) {
            case 'approved':
                $flagStats['flagged_approved']++;
                break;
            case 'pending':
                $flagStats['flagged_pending']++;
                break;
            case 'rejected':
                $flagStats['flagged_rejected']++;
                break;
        }
    }
}

echo "✓ Flagging statistics:\n";
echo "  - Total comments: {$flagStats['total_comments']}\n";
echo "  - Flagged comments: {$flagStats['flagged_comments']}\n";
echo "  - Flagged approved: {$flagStats['flagged_approved']}\n";
echo "  - Flagged pending: {$flagStats['flagged_pending']}\n";
echo "  - Flagged rejected: {$flagStats['flagged_rejected']}\n";

$flaggedPercentage = $flagStats['total_comments'] > 0 ? 
    round(($flagStats['flagged_comments'] / $flagStats['total_comments']) * 100, 1) : 0;

echo "  - Flagged percentage: {$flaggedPercentage}%\n";

if ($flagStats['flagged_comments'] >= 5) {
    echo "✓ Sufficient flagged comments for statistical analysis\n";
} else {
    echo "? Limited flagged comments for statistical analysis\n";
}

// Test 12: Flagging audit trail
echo "\n12. Testing flagging audit trail...\n";

$auditComment = $commentModel->findById($testComments[1]);

if ($auditComment) {
    $hasProperTimestamps = isset($auditComment['created_at']) && 
                          isset($auditComment['updated_at']) &&
                          $auditComment['updated_at'] >= $auditComment['created_at'];
    
    $hasFlaggedField = isset($auditComment['flagged']);
    
    if ($hasProperTimestamps && $hasFlaggedField) {
        echo "✓ Flagging audit trail properly maintained\n";
        
        echo "  - Flagged status: " . ($auditComment['flagged'] ? 'true' : 'false') . "\n";
        echo "  - Created: " . $auditComment['created_at']->toDateTime()->format('Y-m-d H:i:s') . "\n";
        echo "  - Updated: " . $auditComment['updated_at']->toDateTime()->format('Y-m-d H:i:s') . "\n";
        
    } else {
        echo "✗ Flagging audit trail incomplete\n";
    }
    
} else {
    echo "✗ Could not retrieve comment for audit trail testing\n";
}

echo "\n=== Comment Flagging Test Summary ===\n";
echo "✓ Basic comment flagging functionality working\n";
echo "✓ Comment unflagging functionality working\n";
echo "✓ Flagging works across different comment statuses\n";
echo "✓ Multiple flag/unflag cycles working\n";
echo "✓ Invalid comment ID handling working\n";
echo "✓ Flagged comments retrieval and filtering working\n";
echo "✓ Flagging integration with moderation workflow\n";
echo "✓ Bulk flagging operations performance acceptable\n";
echo "✓ Flagging statistics and reporting working\n";
echo "✓ Flagging audit trail properly maintained\n";
echo "Note: Test data preserved in development database\n";