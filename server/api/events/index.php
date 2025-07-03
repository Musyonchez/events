<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/validate.php';
require_once __DIR__ . '/../../middleware/sanitize.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Validate the request and get the decoded data for POST/PATCH requests
$requestData = validateRequest($method);

// Sanitize input data if it exists
if ($requestData !== null) {
    $requestData = sanitizeInput($requestData);
}

if ($method === 'GET') {
  require __DIR__ . '/details.php';
  exit;
}

if ($method === 'POST') {
  require __DIR__ . '/create.php';
  exit;
}

if ($method === 'PATCH') {
  require __DIR__ . '/update.php';
  exit;
}

if ($method === 'DELETE') {
  require __DIR__ . '/delete.php';
  exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
