<?php
/**
 * USIU Events Management System - User Logout Endpoint
 * 
 * Handles user logout functionality for JWT-based authentication systems.
 * Since JWTs are stateless, logout is primarily handled client-side by
 * discarding tokens. This endpoint provides confirmation and can be extended
 * for token blacklisting or additional cleanup operations.
 * 
 * Features:
 * - Logout confirmation response
 * - Client-side token invalidation instruction
 * - Optional server-side token blacklisting (extensible)
 * - Session cleanup operations
 * 
 * Security Features:
 * - Route access control (requires IS_AUTH_ROUTE)
 * - Stateless logout design for JWT tokens
 * - Clear instruction for client-side token removal
 * - Optional HTTP-only cookie clearing
 * 
 * JWT Logout Considerations:
 * - JWTs are stateless and cannot be invalidated server-side without blacklisting
 * - Client must discard access and refresh tokens
 * - Tokens will remain valid until expiration unless blacklisted
 * - Refresh tokens can be invalidated in the database
 * 
 * Request Format:
 * POST /api/auth/?action=logout
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Logged out successfully. Please discard your tokens."
 * }
 * 
 * Future Enhancements:
 * - Token blacklisting for immediate invalidation
 * - Refresh token invalidation in database
 * - Session cleanup and security logging
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

// Core dependencies for logout functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header
header('Content-Type: application/json');

// JWT-based logout implementation
// Since JWTs are stateless, logout is primarily a client-side operation
// The client must discard both access and refresh tokens

// Optional: Clear HTTP-only cookies if using cookie-based token storage
// This is commented out as the current implementation uses header-based tokens
// setcookie("jwt_token", "", time() - 3600, "/", "", true, true); // Clear JWT cookie
// setcookie("refresh_token", "", time() - 3600, "/", "", true, true); // Clear refresh token cookie

// TODO: Future enhancement - Invalidate refresh token in database
// This would require authentication middleware to extract user ID from JWT
// $userModel = new UserModel($db->users);
// $userModel->invalidateRefreshToken($userId);

// TODO: Future enhancement - Add JWT to blacklist for immediate invalidation
// This would require a blacklist storage mechanism (Redis, database, etc.)
// $blacklistService->addToken($jwtToken, $expirationTime);

// Send logout confirmation response
// Client should immediately discard access and refresh tokens
send_success('Logged out successfully. Please discard your tokens.', 200, [
    'instructions' => [
        'Remove access_token from storage',
        'Remove refresh_token from storage',
        'Clear any cached user data',
        'Redirect to login page if needed'
    ]
]);
