<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/Event.php';

header('Content-Type: application/json');

// Get the event ID from the URL
$eventId = $_GET['id'];

$data = $requestData;

$eventModel = new EventModel($db->events);

$result = $eventModel->updateWithValidation($eventId, $data);

if (!$result['success']) {
  http_response_code(400);
  echo json_encode(['error' => 'Validation failed', 'details' => $result['errors']]);
  exit;
}

echo json_encode(['success' => true, 'modified' => $result['modified']]);
