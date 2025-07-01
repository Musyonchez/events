<?php

// api/event/index.php

require_once __DIR__.'/../../cors.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {
    case '/api/event/createEvent':
        require __DIR__.'/createEvent.php';
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Event endpoint not found']);
        break;
}
