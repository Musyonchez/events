<?php
if (!defined('IS_USER_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

authenticate(); // Ensure user is logged in

header('Content-Type: application/json');

$eventModel = new EventModel($db->events);

// Get the authenticated user's ID
$userId = $GLOBALS['user']->userId;

// Get query parameters for filtering and pagination
$type = $_GET['type'] ?? 'registered'; // Default to registered events
$limit = (int) ($_GET['limit'] ?? 20);
$skip = (int) ($_GET['skip'] ?? 0);

$filters = [];

try {
    switch ($type) {
        case 'registered':
            // Events where the user is in the registered_users array
            $filters['registered_users'] = new MongoDB\BSON\ObjectId($userId);
            break;
        // Add other types if needed, e.g., 'club_events' if user is a club leader
        default:
            send_error('Invalid event type specified. Allowed types: registered.', 400);
            break;
    }

    $events = $eventModel->list($filters, $limit, $skip);
    send_success('User events fetched successfully', 200, $events);

} catch (Exception $e) {
    send_internal_server_error($e->getMessage());
}