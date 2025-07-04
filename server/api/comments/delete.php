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

// Get the comment ID from the URL
$commentId = $_GET['id'] ?? null;

if (!$commentId) {
    send_error('Comment ID is required');
}

$commentModel = new CommentModel($db->comments);

// Optional: Add authorization check here to ensure only the comment owner or an admin can delete
// if ($GLOBALS['user']->role !== 'admin') {
//     $comment = $commentModel->findById($commentId);
//     if (!$comment || $comment['user_id']->__toString() !== $GLOBALS['user']->userId) {
//         send_forbidden('You are not authorized to delete this comment');
//     }
// }

try {
    if ($commentModel->delete($commentId)) {
        send_success('Comment deleted successfully');
    } else {
        send_not_found('Comment');
    }
} catch (Exception $e) {
    send_internal_server_error($e->getMessage());
}
