<?php

// api/user/login.php

require_once __DIR__.'/../../cors.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method allowed']);
    exit;
}

// Get raw POST input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['email', 'password'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

require_once __DIR__.'/../../models/actions/UserActions.php';

try {
    UserSchema::validate($input);

    $UserActions = new UserActions;
    $user = $UserActions->findByEmail($input['email']);

    if (! $user || ! password_verify($input['password'], $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
        exit;
    }

    echo json_encode([
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'student_id' => $user['student_id'],
            'avatar_url' => $user['avatar_url'],
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'details' => $e->getMessage()]);
}
