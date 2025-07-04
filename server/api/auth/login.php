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

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (!$email || !$password) {
    send_error('Email and password are required');
}

$userModel = new UserModel($db->users);

$user = $userModel->findByEmail($email);

if (!$user || !password_verify($password, $user['password'])) {
    send_unauthorized('Invalid credentials');
}

// Check if email is verified
if (!$user['is_email_verified']) {
    send_forbidden('Email not verified. Please check your inbox for a verification link.');
}

// Generate JWT
$jwtSecret = $_ENV['JWT_SECRET'];
if (!$jwtSecret) {
    send_internal_server_error('JWT secret not configured');
}

$accessToken = generateJwt($user['_id']->__toString(), $user['email'], $user['role'], $jwtSecret);

// Generate and save Refresh Token
$refreshToken = bin2hex(random_bytes(32)); // Generate a random string for refresh token
$refreshTokenExpiresAt = time() + (60 * 60 * 24 * 7); // Refresh token valid for 7 days
$userModel->saveRefreshToken($user['_id']->__toString(), $refreshToken, $refreshTokenExpiresAt);

// Update last login timestamp
$userModel->updateLastLogin($user['_id']->__toString());

send_success('Login successful', 200, [
    'access_token' => $accessToken,
    'refresh_token' => $refreshToken,
    'user' => [
        'id' => $user['_id']->__toString(),
        'email' => $user['email'],
        'role' => $user['role']
    ]
]);
