<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/User.php';

header('Content-Type: application/json');

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

$userModel = new UserModel($db->users);

$result = $userModel->createWithValidation($data);

if (!$result['success']) {
  http_response_code(400);
  echo json_encode(['error' => 'Registration failed', 'details' => $result['errors']]);
  exit;
}

echo json_encode(['success' => true, 'message' => 'User registered successfully', 'userId' => (string)$result['id']]);
