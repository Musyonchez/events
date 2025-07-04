<?php
if (!defined('IS_AUTH_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/jwt.php';
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

$refreshToken = $data['refresh_token'] ?? null;

if (!$refreshToken) {
    send_error('Refresh token is required');
}

$userModel = new UserModel($db->users);

// 1. Validate the refresh token and retrieve the user
$user = $userModel->findByRefreshToken($refreshToken);

if (is_string($user)) {
    switch ($user) {
        case 'not_found':
            send_unauthorized('Refresh token not found', ['error_type' => 'refresh_token_not_found']);
            break;
        case 'expired':
            send_unauthorized('Refresh token expired', ['error_type' => 'refresh_token_expired']);
            break;
        default:
            send_unauthorized('Invalid refresh token', ['error_type' => 'invalid_refresh_token']);
            break;
    }
}

// 2. Generate a new access token (JWT)
$jwtSecret = $_ENV['JWT_SECRET'];
if (!$jwtSecret) {
    send_internal_server_error('JWT secret not configured');
}

$newAccessToken = generateJwt($user['_id']->__toString(), $user['email'], $user['role'], $jwtSecret);

// 3. Optionally, issue a new refresh token and invalidate the old one (refresh token rotation)
// This is good practice for security, but adds complexity.
// For now, we'll just issue a new access token.

send_success('Access token refreshed successfully', 200, ['access_token' => $newAccessToken]);
