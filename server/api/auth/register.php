<?php
if (!defined('IS_AUTH_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

$userModel = new UserModel($db->users);

$result = $userModel->createWithValidation($data);

if (!$result['success']) {
  send_error('Registration failed', 400, $result['errors']);
}

send_success('User registered successfully', 200, ['userId' => (string)$result['id']]);
