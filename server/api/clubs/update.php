<?php
if (!defined('IS_CLUB_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/upload.php';

authenticate(); // Ensure user is logged in

header('Content-Type: application/json');

// Get the club ID from the URL or request data
$clubId = $_GET['id'] ?? $requestData['id'] ?? null;

if (!$clubId) {
    send_error('Club ID is required for update', 400);
}

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

// Remove ID from data to avoid conflicts (ID is passed separately)
unset($data['id']);

// Decode HTML entities in category field (reverse the sanitization for predefined values)
if (isset($data['category'])) {
    $data['category'] = htmlspecialchars_decode($data['category'], ENT_QUOTES);
}

// Handle file upload if present
if (isset($_FILES['logo'])) {
    try {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        $data['logo'] = upload_file_to_s3($_FILES['logo'], 'club_logos', $allowedMimeTypes);
    } catch (Exception $e) {
        send_error('File upload failed: ' . $e->getMessage());
    }
}

$clubModel = new ClubModel($db->clubs);

// Optional: Add authorization check here to ensure only the club leader or an admin can update
// if ($GLOBALS['user']->role !== 'admin') {
//     $club = $clubModel->findById($clubId);
//     if (!$club || $club['leader_id']->__toString() !== $GLOBALS['user']->userId) {
//         send_forbidden('You are not authorized to update this club');
//     }
// }

// Check if leader is being changed
$oldLeaderId = null;
if (isset($data['leader_id'])) {
    $existingClub = $clubModel->findById($clubId);
    if ($existingClub && isset($existingClub['leader_id'])) {
        $oldLeaderId = $existingClub['leader_id']->__toString();
    }
}

$result = $clubModel->updateWithValidation($clubId, $data);

if (!$result['success']) {
  send_validation_errors($result['errors']);
}

// Handle leader role changes
if (isset($data['leader_id']) && !empty($data['leader_id'])) {
    try {
        $newLeaderId = $data['leader_id'];
        
        // Promote new leader to club_leader role (but don't demote admins)
        $newLeaderObjectId = new MongoDB\BSON\ObjectId($newLeaderId);
        
        // Check the new leader's current role
        $newLeaderUser = $db->users->findOne(['_id' => $newLeaderObjectId]);
        
        if ($newLeaderUser && $newLeaderUser['role'] === 'student') {
            // Only promote students to club_leader, don't touch admins or existing club_leaders
            $db->users->updateOne(
                ['_id' => $newLeaderObjectId],
                ['$set' => ['role' => 'club_leader']]
            );
        }
        
        // If there was an old leader and it's different from the new one, demote them
        if ($oldLeaderId && $oldLeaderId !== $newLeaderId) {
            // Check if the old leader leads any other clubs
            $otherClubsCount = $db->clubs->countDocuments([
                'leader_id' => new MongoDB\BSON\ObjectId($oldLeaderId),
                '_id' => ['$ne' => new MongoDB\BSON\ObjectId($clubId)]
            ]);
            
            // If they don't lead any other clubs, demote them to student (but don't demote admins)
            if ($otherClubsCount === 0) {
                $oldLeaderUser = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($oldLeaderId)]);
                
                if ($oldLeaderUser && $oldLeaderUser['role'] === 'club_leader') {
                    // Only demote club_leaders to student, don't touch admins
                    $db->users->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($oldLeaderId)],
                        ['$set' => ['role' => 'student']]
                    );
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error handling leader role changes: " . $e->getMessage());
        // Don't fail the club update if role update fails, just log it
    }
}

send_success('Club updated successfully', 200, ['modified' => $result['modified']]);
