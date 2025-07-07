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

