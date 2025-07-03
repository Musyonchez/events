<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/Event.php';

header('Content-Type: application/json');

$data = $requestData;

$eventModel = new EventModel($db->events);

$result = $eventModel->createWithValidation($data);

if (!$result['success']) {
  http_response_code(400);
  echo json_encode(['error' => 'Validation failed', 'details' => $result['errors']]);
  exit;
}

echo json_encode(['success' => true, 'insertedId' => (string)$result['id']]);
