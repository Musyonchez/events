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
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

authenticate(); // Ensure user is logged in

header('Content-Type: application/json');

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

// Get user_id from authenticated user
$data['user_id'] = $GLOBALS['user']->userId; // Assuming userId is stored in $GLOBALS['user']

$commentModel = new CommentModel($db->comments);

$result = $commentModel->createWithValidation($data);

if (!$result['success']) {
  send_error('Comment creation failed', 400, $result['errors']);
}

send_created(['commentId' => (string)$result['id']], 'Comment created successfully');
