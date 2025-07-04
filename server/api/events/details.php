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
use MongoDB\BSON\Regex;

header('Content-Type: application/json');

$eventModel = new EventModel($db->events);

// Check if an ID is provided for fetching a single event
if (isset($_GET['id'])) {
    $eventId = $_GET['id'];
    try {
        $event = $eventModel->findById($eventId);
        if ($event) {
            send_success('Event details fetched successfully', 200, $event);
        } else {
            send_not_found('Event');
        }
    } catch (Exception $e) {
        send_error($e->getMessage(), 400);
    }
    exit;
}

// --- Logic for fetching a list of events (with filtering and pagination) ---

// Read pagination parameters
$limit = (int) ($_GET['limit'] ?? 20);
$skip = (int) ($_GET['skip'] ?? 0);

// Whitelist of allowed filter fields
$allowedFilters = [
    'club_id', 'organizer_id', 'location', 'registration_required',
    'category', 'tags', 'status', 'featured'
];

// Fields that should use case-insensitive matching
$caseInsensitiveFields = ['location', 'category', 'status', 'tags'];

$filters = [];
// Build the filter array from the whitelisted GET parameters
foreach ($allowedFilters as $filterKey) {
    if (isset($_GET[$filterKey])) {
        $filterValue = $_GET[$filterKey];

        if (in_array($filterKey, $caseInsensitiveFields, true)) {
            // Use a case-insensitive regex for specific fields
            $filters[$filterKey] = new MongoDB\BSON\Regex($filterValue, 'i');
        } else {
            // Use exact match for other fields
            $filters[$filterKey] = $filterValue;
        }
    }
}

// Fetch the list of events
$events = $eventModel->list($filters, $limit, $skip);

send_success('Events fetched successfully', 200, $events);