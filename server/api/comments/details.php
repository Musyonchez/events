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
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

$commentId = $_GET['id'] ?? null;

if (!$commentId) {
    send_error('Comment ID is required', 400);
}

$commentModel = new CommentModel($db->comments);

try {
    $comment = $commentModel->findById($commentId);
    if ($comment) {
        send_success('Comment fetched successfully', 200, $comment);
    } else {
        send_not_found('Comment');
    }
} catch (Exception $e) {
    send_error($e->getMessage(), 400);
}