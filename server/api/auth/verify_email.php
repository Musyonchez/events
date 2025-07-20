<?php
/**
 * USIU Events Management System - Email Verification Endpoint
 * 
 * Handles email verification for user accounts using verification tokens.
 * Processes email verification links sent to users during registration
 * and validates verification tokens to activate user accounts.
 * 
 * Features:
 * - Email verification token validation
 * - Account activation upon successful verification
 * - Token expiration handling
 * - Duplicate verification prevention
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Token-based verification system
 * - Token expiration enforcement
 * - Protection against duplicate verification
 * - Secure token validation process
 * - Error type classification for debugging
 * 
 * Verification Flow:
 * 1. Extract verification token from URL parameter
 * 2. Validate token exists and is properly formatted
 * 3. Look up token in database
 * 4. Check token expiration status
 * 5. Verify account and clear verification token
 * 6. Send appropriate response
 * 
 * Request Format:
 * GET /api/auth/verify_email.php?token=abc123def456...
 * 
 * Response Formats:
 * Success: { "success": true, "message": "Email has been successfully verified." }
 * Error: { "success": false, "message": "Invalid verification link.", "errors": { "error_type": "invalid_token" } }
 * 
 * Error Types:
 * - invalid_token: Token not found or malformed
 * - token_expired: Verification token has expired
 * - already_verified: Email already verified
 * - verification_failed: Database or system error
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Core dependencies for email verification functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if verification token is provided in URL parameters
if (isset($_GET['token'])) {
    // Extract and sanitize verification token
    $token = trim($_GET['token']);
    
    // Validate token format (basic validation)
    if (empty($token) || strlen($token) < 32) {
        send_error('Invalid verification token format.', 400, [
            'error_type' => 'invalid_token_format'
        ]);
    }
    
    // Initialize user model with MongoDB users collection
    $userModel = new UserModel($db->users);

    try {
        // Attempt email verification with the provided token
        $verificationResult = $userModel->verifyEmail($token);

        // Handle verification result with appropriate responses
        switch ($verificationResult) {
            case 'success':
                send_success('Email has been successfully verified. You can now login to your account.', 200, [
                    'verification_status' => 'verified',
                    'next_action' => 'login'
                ]);
                break;
                
            case 'invalid_token':
                send_error('Invalid verification link. Please check your email for the correct link.', 400, [
                    'error_type' => 'invalid_token',
                    'suggestion' => 'Request a new verification email'
                ]);
                break;
                
            case 'expired_token':
                send_error('Verification link has expired. Please request a new verification email.', 400, [
                    'error_type' => 'token_expired',
                    'suggestion' => 'Request a new verification email'
                ]);
                break;
                
            case 'already_verified':
                send_error('Email is already verified. You can proceed to login.', 400, [
                    'error_type' => 'already_verified',
                    'next_action' => 'login'
                ]);
                break;
                
            case 'verification_failed':
            default:
                send_error('Email verification failed due to a system error. Please try again later.', 500, [
                    'error_type' => 'verification_failed',
                    'suggestion' => 'Contact support if the problem persists'
                ]);
                break;
        }
    } catch (Exception $e) {
        // Handle unexpected errors during verification process
        send_internal_server_error('Email verification failed: ' . $e->getMessage());
    }
} else {
    // Handle missing verification token
    send_error('No verification token provided. Please use the link from your verification email.', 400, [
        'error_type' => 'missing_token',
        'suggestion' => 'Check your email for the verification link'
    ]);
}
