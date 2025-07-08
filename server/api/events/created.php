<?php
if (!defined('IS_EVENT_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

use MongoDB\BSON\ObjectId;

header('Content-Type: application/json');

authenticate(); // Ensure user is logged in

$eventModel = new EventModel($db->events);

// Pagination
$limit = (int) ($_GET['limit'] ?? 12);
$page = (int) ($_GET['page'] ?? 1);
$skip = ($page - 1) * $limit;

// Sorting
$sortOptions = [];
if (isset($_GET['sort']) && !empty($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'date-asc':
            $sortOptions['event_date'] = 1;
            break;
        case 'date-desc':
            $sortOptions['event_date'] = -1;
            break;
        case 'title-asc':
            $sortOptions['title'] = 1;
            break;
        case 'title-desc':
            $sortOptions['title'] = -1;
            break;
        case 'created-desc':
            $sortOptions['created_at'] = -1;
            break;
        default:
            $sortOptions['created_at'] = -1; // Default sort by creation date
    }
} else {
    $sortOptions['created_at'] = -1; // Default sort by creation date
}

try {
    $userId = new ObjectId($GLOBALS['user']->userId);
    
    // Find events created by this user
    $filters = [
        'created_by' => $userId
    ];

    // Fetch total count for pagination
    $total = $eventModel->count($filters);

    // Fetch the list of events
    $events = $eventModel->list($filters, $limit, $skip, $sortOptions);

    send_success('Created events fetched successfully', 200, ['events' => $events, 'total' => $total]);

} catch (Exception $e) {
    send_error('An error occurred while fetching created events: ' . $e->getMessage(), 500);
}