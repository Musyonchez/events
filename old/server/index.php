<?php

// index.php

require_once __DIR__.'/../../cors.php';

$request = $_SERVER['REQUEST_URI'];

if (preg_match('#^/api/user#', $request)) {
    require __DIR__.'/api/user/index.php';
} elseif (preg_match('#^/api/club#', $request)) {
    require __DIR__.'/api/club/index.php';
} elseif (preg_match('#^/api/event#', $request)) {
    require __DIR__.'/api/event/index.php';
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
