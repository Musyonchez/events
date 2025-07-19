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

$commentModel = new CommentModel($db->comments);

// Get query parameters
$limit = (int) ($_GET['limit'] ?? 50);
$skip = (int) ($_GET['skip'] ?? 0);
$status = $_GET['status'] ?? null; // Allow filtering by status
$eventId = $_GET['event_id'] ?? null; // Optional event filtering

$options = [
    'limit' => $limit,
    'skip' => $skip,
    'include_user_details' => true,
    'include_event_details' => true
];

if ($status) {
    $options['status'] = $status;
}

if ($eventId) {
    $options['event_id'] = $eventId;
}

try {
    if ($eventId) {
        // Get comments for a specific event
        $comments = $commentModel->findByEventId($eventId, $options);
    } else {
        // Get all comments (admin functionality) with joined user and event details
        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }
        $comments = $commentModel->listWithDetails($filters, $limit, $skip);
    }
    
    send_success('Comments fetched successfully', 200, ['comments' => $comments]);
} catch (Exception $e) {
    send_error($e->getMessage(), 400);
}