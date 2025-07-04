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

$eventId = $_GET['event_id'] ?? null;
$commentId = $_GET['id'] ?? null;

$commentModel = new CommentModel($db->comments);

if ($commentId) {
    // Get a single comment by ID
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
} elseif ($eventId) {
    // Get comments for a specific event
    $limit = (int) ($_GET['limit'] ?? 50);
    $skip = (int) ($_GET['skip'] ?? 0);
    $status = $_GET['status'] ?? 'approved'; // Default to approved comments
    $includeReplies = filter_var($_GET['include_replies'] ?? true, FILTER_VALIDATE_BOOLEAN);

    $options = [
        'limit' => $limit,
        'skip' => $skip,
        'status' => $status, // Always include status in options
        'include_replies' => $includeReplies
    ];

    try {
        $comments = $commentModel->findByEventId($eventId, $options);
        send_success('Comments fetched successfully', 200, $comments);
    } catch (Exception $e) {
        send_error($e->getMessage(), 400);
    }
} else {
    send_error('Event ID or Comment ID is required', 400);
}
