<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/Event.php';

header('Content-Type: application/json');

// Get the event ID from the URL
$eventId = $_GET['id'];

$eventModel = new EventModel($db->events);

try {
  if ($eventModel->delete($eventId)) {
    echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
  } else {
    http_response_code(404);
    echo json_encode(['error' => 'Event not found or could not be deleted']);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
