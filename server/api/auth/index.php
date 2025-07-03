<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../middleware/validate.php';
require_once __DIR__ . '/../../middleware/sanitize.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Validate the request and get the decoded data for POST requests
$requestData = validateRequest($method);

// Sanitize input data if it exists
if ($requestData !== null) {
    $requestData = sanitizeInput($requestData);
}

// Determine the action based on the path or a query parameter
$action = $_GET['action'] ?? null;

switch ($action) {
    case 'register':
        require __DIR__ . '/register.php';
        break;
    case 'login':
        require __DIR__ . '/login.php';
        break;
    case 'logout':
        require __DIR__ . '/logout.php';
        break;
    case 'reset_password':
        require __DIR__ . '/reset_password.php';
        break;
    case 'verify_email':
        require __DIR__ . '/verify_email.php';
        break;
    case 'refresh_token':
        require __DIR__ . '/refresh_token.php';
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid authentication action']);
        break;
}
