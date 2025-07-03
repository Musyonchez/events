<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';

header('Content-Type: application/json');

// For JWT-based authentication, logout is primarily client-side (discarding the token).
// This endpoint serves as a confirmation and can be extended for token blacklisting if needed.

// Optionally, if using HTTP-only cookies for JWTs, you would clear them here.
// setcookie("jwt_token", "", time() - 3600, "/"); // Example to clear a cookie

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Logged out successfully (client should discard token)']);
