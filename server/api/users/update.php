<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../middleware/auth.php';

authenticate();


header('Content-Type: application/json');

// Get the user ID from the URL (validated by middleware/validate.php)
$userId = $_GET['id'];

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

$userModel = new UserModel($db->users);

$result = $userModel->updateWithValidation($userId, $data);

if (!$result['success']) {
  http_response_code(400);
  echo json_encode(['error' => 'Validation failed', 'details' => $result['errors']]);
  exit;
}

echo json_encode(['success' => true, 'modified' => $result['modified']]);
