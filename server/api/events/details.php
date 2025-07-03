<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/Event.php';

header('Content-Type: application/json');

$eventModel = new EventModel($db->events);

// Check if an ID is provided for fetching a single event
if (isset($_GET['id'])) {
    $eventId = $_GET['id'];
    try {
        $event = $eventModel->findById($eventId);
        if ($event) {
            echo json_encode($event);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Event not found']);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
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

echo json_encode($events);