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

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

// Decode HTML entities in category field (reverse the sanitization for predefined values)
if (isset($data['category'])) {
    $data['category'] = htmlspecialchars_decode($data['category'], ENT_QUOTES);
}

// Validate that leader_id is provided
if (empty($data['leader_id'])) {
    send_error('Club leader must be selected', 400);
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

$result = $clubModel->createWithValidation($data);

if (!$result['success']) {
  send_error('Club creation failed', 400, $result['errors']);
}

// Promote the selected user to club_leader role (but don't demote admins)
try {
    $leaderObjectId = new MongoDB\BSON\ObjectId($data['leader_id']);
    
    // Get current user info to check their role
    $currentUser = $db->users->findOne(['_id' => $leaderObjectId]);
    
    if ($currentUser && $currentUser['role'] === 'student') {
        // Only promote students to club_leader, don't touch admins or existing club_leaders
        $updateResult = $db->users->updateOne(
            ['_id' => $leaderObjectId],
            ['$set' => ['role' => 'club_leader']]
        );
        
        if ($updateResult->getModifiedCount() === 0) {
            error_log("Warning: Could not update user role to club_leader for user ID: " . $data['leader_id']);
        }
    } else if ($currentUser) {
        // User is already admin or club_leader, no role change needed
        error_log("Info: User ID " . $data['leader_id'] . " is already " . $currentUser['role'] . ", no role change needed");
    } else {
        error_log("Warning: Could not find user with ID: " . $data['leader_id']);
    }
} catch (Exception $e) {
    error_log("Error handling user role for club leader: " . $e->getMessage());
    // Don't fail the club creation if role update fails, just log it
}

send_created(['clubId' => (string)$result['id']], 'Club created successfully');
