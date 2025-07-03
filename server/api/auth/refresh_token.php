<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/jwt.php';

header('Content-Type: application/json');

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

$refreshToken = $data['refresh_token'] ?? null;

if (!$refreshToken) {
    http_response_code(400);
    echo json_encode(['error' => 'Refresh token is required']);
    exit;
}

$userModel = new UserModel($db->users);

// 1. Validate the refresh token and retrieve the user
$user = $userModel->findByRefreshToken($refreshToken);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired refresh token']);
    exit;
}

// 2. Generate a new access token (JWT)
$jwtSecret = $_ENV['JWT_SECRET'];
if (!$jwtSecret) {
    http_response_code(500);
    echo json_encode(['error' => 'JWT secret not configured']);
    exit;
}

$newAccessToken = generateJwt($user['_id']->__toString(), $user['email'], $user['role'], $jwtSecret);

// 3. Optionally, issue a new refresh token and invalidate the old one (refresh token rotation)
// This is good practice for security, but adds complexity.
// For now, we'll just issue a new access token.

http_response_code(200);
echo json_encode([
    'success' => true,
    'access_token' => $newAccessToken,
    // 'refresh_token' => $newRefreshToken // Uncomment if implementing rotation
]);
