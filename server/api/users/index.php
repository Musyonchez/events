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

switch ($method) {
    case 'GET':
        require __DIR__ . '/details.php';
        break;
    case 'POST':
        if (isset($_GET['action']) && $_GET['action'] === 'change_password') {
            require __DIR__ . '/change_password.php';
        } else {
            require __DIR__ . '/create.php';
        }
        break;
    case 'PATCH':
        require __DIR__ . '/update.php';
        break;
    case 'DELETE':
        require __DIR__ . '/delete.php';
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
