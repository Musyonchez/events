<?php
/**
 * USIU Events Management System - User Authentication Endpoint
 * 
 * Handles user login authentication with JWT token generation, email verification
 * checking, and refresh token management. Provides secure authentication with
 * comprehensive security features and session management.
 * 
 * Features:
 * - Email and password authentication
 * - JWT access token generation
 * - Refresh token generation and storage
 * - Email verification requirement enforcement
 * - Last login timestamp tracking
 * - Secure password verification
 * 
 * Security Features:
 * - Route access control (requires IS_AUTH_ROUTE)
 * - Password verification using password_verify()
 * - Email verification requirement
 * - Secure JWT token generation
 * - Refresh token for secure token renewal
 * - Protection against unauthorized access
 * 
 * Request Format:
 * POST /api/auth/?action=login
 * {
 *   "email": "john.doe@usiu.ac.ke",
 *   "password": "userpassword123"
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Login successful",
 *   "data": {
 *     "access_token": "eyJ...",
 *     "refresh_token": "abc123...",
 *     "user": { "id": "...", "email": "...", "role": "..." }
 *   }
 * }
 * Error: { "success": false, "message": "Invalid credentials" }
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

// Core dependencies for user authentication functionality
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

// Extract authentication credentials from request data
$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

// Validate required authentication fields
if (!$email || !$password) {
    send_error('Email and password are required', 400);
}

// Initialize user model with MongoDB users collection
$userModel = new UserModel($db->users);

// Attempt to find user by email address
$user = $userModel->findByEmail($email);

// Verify user exists and password is correct
// Use constant-time comparison to prevent timing attacks
if (!$user || !password_verify($password, $user['password'])) {
    send_unauthorized('Invalid credentials');
}

// Enforce email verification requirement for security
if (!$user['is_email_verified']) {
    send_forbidden('Email not verified. Please check your inbox for a verification link.');
}

// Generate JWT access token for authenticated sessions
$jwtSecret = $_ENV['JWT_SECRET'];
if (!$jwtSecret) {
    send_internal_server_error('JWT secret not configured');
}

// Create JWT with user information and role-based access
$accessToken = generateJwt(
    $user['_id']->__toString(), 
    $user['email'], 
    $user['role'], 
    $jwtSecret
);

// Generate secure refresh token for token renewal
$refreshToken = bin2hex(random_bytes(32)); // Cryptographically secure random token
$refreshTokenExpiresAt = time() + (60 * 60 * 24 * 7); // 7-day expiration

// Store refresh token in database for validation
$userModel->saveRefreshToken(
    $user['_id']->__toString(), 
    $refreshToken, 
    $refreshTokenExpiresAt
);

// Track user login activity for security monitoring
$userModel->updateLastLogin($user['_id']->__toString());

// Send successful authentication response with tokens and user data
send_success('Login successful', 200, [
    'access_token' => $accessToken,
    'refresh_token' => $refreshToken,
    'user' => [
        'id' => $user['_id']->__toString(),
        'email' => $user['email'],
        'role' => $user['role'],
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
        'student_id' => $user['student_id'] ?? ''
    ],
    'token_expires_in' => 3600, // 1 hour in seconds
    'refresh_token_expires_in' => 604800 // 7 days in seconds
]);
