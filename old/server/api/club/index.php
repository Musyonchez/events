<?php

// api/club/index.php

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Basic routing logic
switch ($path) {
    case '/api/club/createClub':
        require __DIR__.'/createClub.php';
        break;

    case '/api/club/getClub':
        require __DIR__.'/getClub.php';
        break;

    case '/api/club/getClubs':
        require __DIR__.'/getClubs.php';
        break;

        // Add more club-related routes here as needed

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Club endpoint not found']);
        break;
}
