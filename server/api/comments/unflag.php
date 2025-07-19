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

// Check if user has admin privileges
$currentUser = getCurrentUser();
if (!$currentUser || ($currentUser->role !== 'admin' && $currentUser->role !== 'club_leader')) {
    send_unauthorized('Admin privileges required');
}

$commentId = $_GET['id'] ?? null;

if (!$commentId) {
    send_error('Comment ID is required', 400);
}

$commentModel = new CommentModel($db->comments);

try {
    $result = $commentModel->unflag($commentId);
    
    if ($result) {
        send_success('Comment unflagged successfully');
    } else {
        send_not_found('Comment');
    }
} catch (Exception $e) {
    send_error($e->getMessage(), 400);
}