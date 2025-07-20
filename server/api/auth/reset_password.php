<?php
/**
 * USIU Events Management System - Password Reset Endpoint
 * 
 * Handles password reset functionality with two-step process: password reset
 * request (token generation) and password reset confirmation (new password setting).
 * Implements secure token-based password reset with email delivery.
 * 
 * Features:
 * - Password reset token generation and email delivery
 * - Token-based password reset verification
 * - Secure new password validation and setting
 * - Protection against user enumeration attacks
 * - Token expiration handling
 * 
 * Security Features:
 * - Two-step password reset process
 * - Cryptographically secure token generation
 * - Token expiration enforcement (1 hour)
 * - User enumeration protection (same response for valid/invalid emails)
 * - Password strength validation
 * - Rate limiting considerations (future enhancement)
 * 
 * Password Reset Flow:
 * Step 1 - Request Reset:
 * 1. User provides email address
 * 2. System generates secure reset token
 * 3. Token stored in database with expiration
 * 4. Reset email sent to user
 * 5. Generic success response (prevents user enumeration)
 * 
 * Step 2 - Confirm Reset:
 * 1. User clicks email link and provides new password
 * 2. System validates reset token
 * 3. Checks token expiration
 * 4. Validates new password strength
 * 5. Updates password and clears reset token
 * 
 * Request Formats:
 * Reset Request: POST { "email": "user@usiu.ac.ke" }
 * Reset Confirm: POST { "token": "abc123...", "password": "newpassword123" }
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Core dependencies for password reset functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header
header('Content-Type: application/json');

// Initialize user model with MongoDB users collection
$userModel = new UserModel($db->users);

// Only accept POST requests for password reset operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse JSON request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate JSON parsing was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error('Invalid JSON data provided', 400);
    }

    // Case 1: Password reset request - Generate and send reset token
    if (isset($data['email'])) {
        $email = $data['email'] ?? '';

        // Validate email is provided
        if (empty($email)) {
            send_error('Email address is required', 400);
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            send_error('Invalid email format', 400);
        }

        try {
            // Generate and send password reset token
            // Always return success response to prevent user enumeration attacks
            $userModel->generatePasswordResetToken($email);
            send_success('If a user account exists with that email address, a password reset link has been sent. Please check your inbox and spam folder.', 200, [
                'email_sent' => true,
                'next_step' => 'Check your email for reset instructions'
            ]);
        } catch (Exception $e) {
            // Log error but still send generic success response
            error_log('Password reset token generation failed: ' . $e->getMessage());
            send_success('If a user account exists with that email address, a password reset link has been sent.');
        }

    // Case 2: Password reset confirmation - Validate token and set new password
    } elseif (isset($data['token']) && isset($data['password'])) {
        $token = $data['token'];
        $newPassword = $data['password'];

        // Validate required fields
        if (empty($token) || empty($newPassword)) {
            send_error('Reset token and new password are required', 400);
        }
        
        // Validate token format
        if (strlen($token) < 32) {
            send_error('Invalid reset token format', 400);
        }
        
        // Validate new password strength
        if (strlen($newPassword) < 8) {
            send_error('New password must be at least 8 characters long', 400);
        }

        try {
            // Attempt to reset password with provided token
            if ($userModel->resetPassword($token, $newPassword)) {
                send_success('Password has been reset successfully. You can now login with your new password.', 200, [
                    'password_reset' => true,
                    'next_action' => 'login'
                ]);
            } else {
                send_error('Invalid or expired password reset token. Please request a new password reset.', 400, [
                    'error_type' => 'invalid_or_expired_token',
                    'suggestion' => 'Request a new password reset'
                ]);
            }
        } catch (Exception $e) {
            send_error('Password reset failed due to a system error. Please try again.', 500, [
                'error_type' => 'system_error'
            ]);
        }
    } else {
        // Handle invalid request payload
        send_error('Invalid request payload. Provide either email (for reset request) or token and password (for reset confirmation).', 400, [
            'expected_fields' => [
                'reset_request' => ['email'],
                'reset_confirm' => ['token', 'password']
            ]
        ]);
    }
} else {
    // Handle non-POST requests
    send_method_not_allowed('Only POST method is allowed for password reset operations');
}
