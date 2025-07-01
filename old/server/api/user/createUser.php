<?php

// api/user/createUser.php

require_once __DIR__.'/../../cors.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method allowed']);
    exit;
}

// Get raw POST input
$input = json_decode(file_get_contents('php://input'), true);

require_once __DIR__.'/../../models/schemas/UserSchema.php';
require_once __DIR__.'/../../models/actions/UserActions.php';
require_once __DIR__.'/../../models/model/User.php';

try {
    // Validate schema types
    UserSchema::validate($input);

    $UserActions = new UserActions;

    if ($UserActions->findByEmail($input['email'])) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Email already in use']);
        exit;
    }

    $user = User::fromArray($input);
    $insertedId = $UserActions->createUser($user->toArray());

    echo json_encode([
        'message' => 'User created successfully',
        'id' => (string) $insertedId,
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
