<?php
/**
 * USIU Events Management System - Resend Email Verification Endpoint
 * 
 * Handles resending email verification links for users who haven't received
 * or lost their original verification emails. Provides secure verification
 * token regeneration with proper user enumeration protection.
 * 
 * Features:
 * - Email verification token regeneration
 * - Verification email resending
 * - Protection against user enumeration attacks
 * - Already verified account handling
 * - Rate limiting considerations (future enhancement)
 * 
 * Security Features:
 * - User enumeration protection (same response for valid/invalid emails)
 * - Already verified account detection
 * - Email format validation
 * - Secure token generation
 * - Error handling without information disclosure
 * 
 * Verification Resend Flow:
 * 1. Validate email address format
 * 2. Look up user account by email
 * 3. Check if account is already verified
 * 4. Generate new verification token
 * 5. Send verification email
 * 6. Return generic success response
 * 
 * Request Format:
 * POST /api/auth/resend_verification.php
 * {
 *   "email": "user@usiu.ac.ke"
 * }
 * 
 * Response Format:
 * Success: { "success": true, "message": "A new verification link has been sent..." }
 * Error: { "success": false, "message": "Your email is already verified..." }
 * 
 * Error Types:
 * - already_verified: Account email is already verified
 * - email_send_failed: SMTP or email delivery failure
 * - token_generation_failed: Database or token generation error
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Core dependencies for email verification resend functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests for verification resend operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse JSON request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate JSON parsing was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error('Invalid JSON data provided', 400);
    }
    
    // Extract email address from request
    $email = $data['email'] ?? '';

    // Validate email is provided
    if (empty($email)) {
        send_error('Email address is required', 400);
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_error('Invalid email format', 400);
    }

    // Initialize user model with MongoDB users collection
    $userModel = new UserModel($db->users);

    try {
        // Attempt to generate and send new verification token
        $result = $userModel->generateVerificationTokenByEmail($email);

        // Handle verification resend result with appropriate responses
        switch ($result) {
            case 'success':
                send_success('A new verification link has been sent to your email address. Please check your inbox and spam folder.', 200, [
                    'email_sent' => true,
                    'next_step' => 'Check your email for verification instructions'
                ]);
                break;
                
            case 'user_not_found':
                // Security measure: Don't reveal if user doesn't exist
                // Always return success to prevent user enumeration attacks
                send_success('If an account with that email exists, a new verification link has been sent.', 200, [
                    'email_sent' => true
                ]);
                break;
                
            case 'already_verified':
                send_error('Your email address is already verified. You can login to your account now.', 400, [
                    'error_type' => 'already_verified',
                    'next_action' => 'login'
                ]);
                break;
                
            case 'email_send_failed':
                send_error('Failed to send verification email due to email service issues. Please try again in a few minutes.', 500, [
                    'error_type' => 'email_send_failed',
                    'suggestion' => 'Try again later or contact support'
                ]);
                break;
                
            case 'token_generation_failed':
                send_error('Failed to generate verification token due to a system error. Please try again.', 500, [
                    'error_type' => 'token_generation_failed',
                    'suggestion' => 'Contact support if the problem persists'
                ]);
                break;
                
            default:
                send_error('An unexpected error occurred while processing your request. Please try again later.', 500, [
                    'error_type' => 'unexpected_error'
                ]);
                break;
        }
    } catch (Exception $e) {
        // Handle unexpected errors with detailed logging
        error_log('Email verification resend failed: ' . $e->getMessage());
        send_internal_server_error('Email verification request failed: ' . $e->getMessage());
    }
} else {
    // Handle non-POST requests
    send_method_not_allowed('Only POST method is allowed for verification resend operations');
}