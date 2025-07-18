<?php
define('IS_CLUB_ROUTE', true);

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

// Determine the action based on a query parameter
$action = $_GET['action'] ?? null;

switch ($action) {
    case 'create':
        if ($method === 'POST') {
            require __DIR__ . '/create.php';
        } else {
            send_method_not_allowed();
        }
        break;
    case 'list':
        if ($method === 'GET') {
            require __DIR__ . '/list.php';
        } else {
            send_method_not_allowed();
        }
        break;
    case 'details':
        if ($method === 'GET') {
            require __DIR__ . '/details.php';
        } else {
            send_method_not_allowed();
        }
        break;
    case 'update':
        if ($method === 'PATCH') {
            require __DIR__ . '/update.php';
        } else {
            send_method_not_allowed();
        }
        break;
    case 'delete':
        if ($method === 'DELETE') {
            require __DIR__ . '/delete.php';
        } else {
            send_method_not_allowed();
        }
        break;
    case 'join':
        if ($method === 'POST') {
            require __DIR__ . '/join.php';
        } else {
            send_method_not_allowed();
        }
        break;
    default:
        // For general listing (GET without action), or if no action is specified
        if ($method === 'GET') {
            // This would typically be a list all clubs endpoint
            // For now, we'll just return an error if no specific action is given for GET
            send_error('Invalid club action or missing action parameter.');
        } else {
            send_error('Invalid club action or missing action parameter.');
        }
        break;
}