<?php
define('IS_COMMENT_ROUTE', true);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../middleware/validate.php';
require_once __DIR__ . '/../../middleware/sanitize.php';
require_once __DIR__ . '/../../utils/response.php';

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
        require __DIR__ . '/get.php';
        break;
    case 'POST':
        require __DIR__ . '/create.php';
        break;
    case 'DELETE':
        require __DIR__ . '/delete.php';
        break;
    // Add PATCH for update/moderation later if needed
    default:
        send_method_not_allowed();
        break;
}