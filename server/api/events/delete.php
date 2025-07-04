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
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

authenticate();


header('Content-Type: application/json');

// Get the event ID from the URL
$eventId = $_GET['id'];

$eventModel = new EventModel($db->events);

try {
  if ($eventModel->delete($eventId)) {
    send_success('Event deleted successfully');
  } else {
    send_not_found('Event');
  }
} catch (Exception $e) {
  send_internal_server_error($e->getMessage());
}
