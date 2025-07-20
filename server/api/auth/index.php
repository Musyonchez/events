<?php
/**
 * USIU Events Management System - Authentication API Router
 * 
 * Central authentication endpoint router that handles all authentication-related
 * operations including user registration, login, logout, password management,
 * email verification, and token refresh functionality.
 * 
 * Features:
 * - Action-based routing for authentication operations
 * - Request validation and sanitization
 * - Centralized error handling
 * - Security isolation for authentication endpoints
 * - JWT and refresh token management
 * 
 * Security Features:
 * - Route access control with IS_AUTH_ROUTE constant
 * - Input validation and sanitization middleware
 * - CORS configuration for cross-origin requests
 * - JSON response standardization
 * 
 * Supported Actions:
 * - register: User account creation with email verification
 * - login: User authentication with JWT token generation
 * - logout: Session termination (client-side token removal)
 * - reset_password: Password reset token generation and processing
 * - verify_email: Email verification using verification tokens
 * - refresh_token: JWT access token renewal using refresh tokens
 * - change_password: Authenticated password change
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Security constant to ensure authentication endpoints are accessed properly
define('IS_AUTH_ROUTE', true);

// Core dependencies for authentication functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Middleware for request processing and security
require_once __DIR__ . '/../../middleware/validate.php';
require_once __DIR__ . '/../../middleware/sanitize.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header for all authentication endpoints
header('Content-Type: application/json');

// Get HTTP method for request validation
$method = $_SERVER['REQUEST_METHOD'];

// Validate the request and decode JSON data for POST requests
// This ensures proper request format and prevents malformed data
$requestData = validateRequest($method);

// Sanitize input data to prevent XSS and injection attacks
if ($requestData !== null) {
    $requestData = sanitizeInput($requestData);
}

// Route determination based on action parameter
// This allows single endpoint with multiple authentication operations
$action = $_GET['action'] ?? null;

// Authentication action router with comprehensive endpoint coverage
switch ($action) {
    case 'register':
        // User registration with email verification
        require __DIR__ . '/register.php';
        break;
    case 'login':
        // User authentication with JWT token generation
        require __DIR__ . '/login.php';
        break;
    case 'logout':
        // Session termination and token invalidation
        require __DIR__ . '/logout.php';
        break;
    case 'reset_password':
        // Password reset token generation and processing
        require __DIR__ . '/reset_password.php';
        break;
    case 'verify_email':
        // Email verification using verification tokens
        require __DIR__ . '/verify_email.php';
        break;
    case 'refresh_token':
        // JWT access token renewal using refresh tokens
        require __DIR__ . '/refresh_token.php';
        break;
    case 'change_password':
        // Authenticated password change with old password verification
        require __DIR__ . '/change_password.php';
        break;
    case 'resend_verification':
        // Resend email verification for unverified accounts
        require __DIR__ . '/resend_verification.php';
        break;
    default:
        // Handle invalid or missing action parameter
        send_error('Invalid authentication action. Supported actions: register, login, logout, reset_password, verify_email, refresh_token, change_password, resend_verification', 400);
        break;
}
