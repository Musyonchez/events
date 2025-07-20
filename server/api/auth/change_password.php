<?php
/**
 * USIU Events Management System - Password Change Endpoint
 * 
 * Handles authenticated password change functionality for logged-in users.
 * Requires current password verification before allowing password updates.
 * Implements secure password change with proper authentication and validation.
 * 
 * Features:
 * - Authenticated password change (requires valid JWT)
 * - Current password verification before change
 * - New password strength validation
 * - Secure password hashing and storage
 * - Detailed error feedback for user experience
 * 
 * Security Features:
 * - Route access control (requires IS_AUTH_ROUTE)
 * - JWT authentication requirement
 * - Current password verification to prevent unauthorized changes
 * - Password strength validation
 * - Secure password hashing with password_hash()
 * - Protection against password change without proper authentication
 * 
 * Password Change Flow:
 * 1. Validate user is authenticated (JWT required)
 * 2. Extract user ID from JWT token
 * 3. Verify current password is correct
 * 4. Validate new password meets strength requirements
 * 5. Hash new password securely
 * 6. Update password in database
 * 7. Send confirmation response
 * 
 * Request Format:
 * POST /api/auth/?action=change_password
 * Headers: Authorization: Bearer <jwt_token>
 * {
 *   "old_password": "currentpassword123",
 *   "new_password": "newsecurepassword456"
 * }
 * 
 * Response Format:
 * Success: { "success": true, "message": "Password changed successfully" }
 * Error: { "success": false, "message": "Current password is incorrect" }
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

// Core dependencies for password change functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// Require authentication - this validates JWT and sets user context
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// Extract user ID from authenticated JWT token context
// The authenticate() middleware sets this in $GLOBALS['user']
$userId = $GLOBALS['user']->userId ?? null;

// Validate that user ID was successfully extracted from JWT
if (!$userId) {
    send_error('Authentication failed: user ID not found in token', 401);
}

// Get validated and sanitized request data from the auth router
$data = $requestData;

// Extract password change fields from request
$oldPassword = $data['old_password'] ?? null;
$newPassword = $data['new_password'] ?? null;

// Validate required fields are provided
if (!$oldPassword || !$newPassword) {
    send_error('Both current password and new password are required', 400);
}

// Validate new password is different from old password
if ($oldPassword === $newPassword) {
    send_error('New password must be different from current password', 400);
}

// Initialize user model with MongoDB users collection
$userModel = new UserModel($db->users);

// Attempt password change with current password verification
$result = $userModel->changePassword($userId, $oldPassword, $newPassword);

// Handle password change failure with specific error feedback
if (!$result['success']) {
    $errors = $result['errors'];
    
    // Provide specific error messages for better user experience
    if (isset($errors['old_password']) && $errors['old_password'] === 'Current password is incorrect') {
        send_error('Current password is incorrect. Please verify your current password and try again.', 400, $errors);
    } elseif (isset($errors['new_password'])) {
        send_error('New password validation failed: ' . $errors['new_password'], 400, $errors);
    } elseif (isset($errors['user']) && $errors['user'] === 'User not found') {
        send_error('User account not found. Please login again.', 404, $errors);
    } else {
        send_error('Password change failed due to an unexpected error. Please try again.', 500, $errors);
    }
}

// Send success response for successful password change
send_success('Password changed successfully. Your new password is now active.', 200, [
    'password_changed' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'security_note' => 'Please logout and login again on other devices for security'
]);
