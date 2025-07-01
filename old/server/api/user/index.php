<?php

// api/user/index.php

require_once __DIR__.'/../../cors.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {
    case '/api/user/createUser':
        require __DIR__.'/createUser.php';
        break;

    case '/api/user/login':
        require __DIR__.'/login.php';
        break;

    case '/api/user/getUser':
        require __DIR__.'/getUser.php';
        break;

    case '/api/user/getUsers':
        require __DIR__.'/getUsers.php';
        break;

        // You can add more routes here:
        // case '/api/user/getUser': require __DIR__ . '/getUser.php'; break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'User endpoint not found']);
        break;
}
