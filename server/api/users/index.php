<?php
define('IS_USER_ROUTE', true);

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
        if (isset($_GET['action']) && $_GET['action'] === 'events') {
            require __DIR__ . '/events.php';
        } elseif (isset($_GET['action']) && $_GET['action'] === 'stats') {
            require __DIR__ . '/stats.php';
        } elseif (isset($_GET['action']) && $_GET['action'] === 'list') {
            require __DIR__ . '/list.php';
        } else {
            require __DIR__ . '/details.php';
        }
        break;
    case 'POST':
        require __DIR__ . '/create.php';
        break;
    case 'PATCH':
        require __DIR__ . '/update.php';
        break;
    case 'DELETE':
        require __DIR__ . '/delete.php';
        break;
    default:
        send_method_not_allowed();
        break;
}