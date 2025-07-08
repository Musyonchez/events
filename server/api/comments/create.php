<?php
if (!defined('IS_COMMENT_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/Comment.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

authenticate(); // Ensure user is logged in

header('Content-Type: application/json');

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

// Get user_id from authenticated user
$userId = $GLOBALS['user']->userId;
$data['user_id'] = $userId;

// Fetch full user data to store with comment
$userModel = new UserModel($db->users);
try {
    $user = $userModel->findById($userId);
    if ($user) {
        // Store user data with comment for efficient display
        $data['user'] = [
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'profile_image' => $user['profile_image'] ?? '',
            'email' => $user['email']
        ];
    }
} catch (Exception $e) {
    // If user fetch fails, continue without user data (will fall back to user_id only)
    error_log('Failed to fetch user data for comment: ' . $e->getMessage());
}

$commentModel = new CommentModel($db->comments);

$result = $commentModel->createWithValidation($data);

if (!$result['success']) {
  send_error('Comment creation failed', 400, $result['errors']);
}

send_created(['commentId' => (string)$result['id']], 'Comment created successfully');
