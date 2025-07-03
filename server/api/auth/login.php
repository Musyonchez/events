<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/jwt.php';

header('Content-Type: application/json');

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

$userModel = new UserModel($db->users);

$user = $userModel->findByEmail($email);

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

// Generate JWT
$jwtSecret = $_ENV['JWT_SECRET'];
if (!$jwtSecret) {
    http_response_code(500);
    echo json_encode(['error' => 'JWT secret not configured']);
    exit;
}

$accessToken = generateJwt($user['_id']->__toString(), $user['email'], $user['role'], $jwtSecret);

// Generate and save Refresh Token
$refreshToken = bin2hex(random_bytes(32)); // Generate a random string for refresh token
$refreshTokenExpiresAt = time() + (60 * 60 * 24 * 7); // Refresh token valid for 7 days
$userModel->saveRefreshToken($user['_id']->__toString(), $refreshToken, $refreshTokenExpiresAt);

// Update last login timestamp
$userModel->updateLastLogin($user['_id']->__toString());

echo json_encode(['success' => true, 'message' => 'Login successful', 'access_token' => $accessToken, 'refresh_token' => $refreshToken, 'user' => [
    'id' => $user['_id']->__toString(),
    'email' => $user['email'],
    'role' => $user['role']
]]);
