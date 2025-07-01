<?php

// api/user/getUser.php

require_once __DIR__.'/../../cors.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

require_once __DIR__.'/../../models/actions/UserActions.php';
require_once __DIR__.'/../../models/schemas/UserSchema.php';

try {

    UserSchema::validate($input);

    if (! isset($input['id']) || ! is_string($input['id'])) {
        throw new InvalidArgumentException("Field 'id' is required and must be a string");
    }

    $UserActions = new UserActions;
    $user = $UserActions->getUserById($input['id']);

    if (! $user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    echo json_encode(['user' => $user]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
