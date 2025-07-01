<?php

// api/user/getUsers.php

require_once __DIR__.'/../../cors.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Only GET method allowed']);
    exit;
}

require_once __DIR__.'/../../models/actions/UserActions.php';

try {
    $UserActions = new UserActions;
    $users = $UserActions->getAllUsers();

    // Convert MongoDB cursor to array (already done in UserActions)
    echo json_encode(['users' => $users]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
