<?php
/**
 * USIU Events Management System - Token Refresh Endpoint
 * 
 * Handles JWT access token renewal using refresh tokens. Provides secure
 * token refresh functionality to maintain user sessions without requiring
 * re-authentication. Implements refresh token validation and new access
 * token generation.
 * 
 * Features:
 * - Refresh token validation and verification
 * - New JWT access token generation
 * - Token expiration handling
 * - Security checks for token authenticity
 * - Error handling for various failure scenarios
 * 
 * Security Features:
 * - Route access control (requires IS_AUTH_ROUTE)
 * - Refresh token expiration checking
 * - Database-stored refresh token validation
 * - Secure JWT generation with user context
 * - Token rotation support (future enhancement)
 * 
 * Token Refresh Flow:
 * 1. Validate refresh token exists in request
 * 2. Look up refresh token in database
 * 3. Check token expiration status
 * 4. Generate new JWT access token
 * 5. Return new access token to client
 * 
 * Request Format:
 * POST /api/auth/?action=refresh_token
 * {
 *   "refresh_token": "abc123def456..."
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Access token refreshed successfully",
 *   "data": { "access_token": "eyJ..." }
 * }
 * Error: { "success": false, "message": "Refresh token expired" }
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Security check to ensure this endpoint is accessed through the auth router
if (!defined('IS_AUTH_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Core dependencies for token refresh functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// User model, JWT utilities, and response utilities
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/jwt.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header
header('Content-Type: application/json');

// Get validated and sanitized request data from the auth router
$data = $requestData;

// Extract refresh token from request data
$refreshToken = $data['refresh_token'] ?? null;

// Validate that refresh token is provided
if (!$refreshToken) {
    send_error('Refresh token is required', 400);
}

// Initialize user model with MongoDB users collection
$userModel = new UserModel($db->users);

// Step 1: Validate refresh token and retrieve associated user
// This checks both token existence and expiration status
$user = $userModel->findByRefreshToken($refreshToken);

// Handle refresh token validation failures
if (is_string($user)) {
    switch ($user) {
        case 'not_found':
            send_unauthorized('Refresh token not found or invalid', [
                'error_type' => 'refresh_token_not_found'
            ]);
            break;
        case 'expired':
            send_unauthorized('Refresh token has expired. Please login again.', [
                'error_type' => 'refresh_token_expired'
            ]);
            break;
        default:
            send_unauthorized('Invalid refresh token', [
                'error_type' => 'invalid_refresh_token'
            ]);
            break;
    }
}

// Step 2: Generate new JWT access token with user information
$jwtSecret = $_ENV['JWT_SECRET'];
if (!$jwtSecret) {
    send_internal_server_error('JWT secret not configured');
}

// Create new JWT access token with fresh expiration time
$newAccessToken = generateJwt(
    $user['_id']->__toString(),
    $user['email'],
    $user['role'],
    $jwtSecret
);

// Step 3: Optional refresh token rotation for enhanced security
// TODO: Implement refresh token rotation to prevent token reuse attacks
// This would involve:
// 1. Generate new refresh token
// 2. Update database with new refresh token
// 3. Invalidate old refresh token
// 4. Return both new access and refresh tokens
// 
// Example implementation:
// $newRefreshToken = bin2hex(random_bytes(32));
// $refreshTokenExpiresAt = time() + (60 * 60 * 24 * 7); // 7 days
// $userModel->saveRefreshToken($user['_id']->__toString(), $newRefreshToken, $refreshTokenExpiresAt);

// Send success response with new access token
send_success('Access token refreshed successfully', 200, [
    'access_token' => $newAccessToken,
    'token_type' => 'Bearer',
    'expires_in' => 3600, // 1 hour in seconds
    'user' => [
        'id' => $user['_id']->__toString(),
        'email' => $user['email'],
        'role' => $user['role']
    ]
]);
