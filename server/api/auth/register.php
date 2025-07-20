<?php
/**
 * USIU Events Management System - User Registration Endpoint
 * 
 * Handles user account creation with comprehensive validation, email verification,
 * and security features. Creates new user accounts with proper data validation,
 * duplicate checking, and automated email verification workflow.
 * 
 * Features:
 * - User registration with comprehensive validation
 * - Email verification token generation and sending
 * - Duplicate email and student ID prevention
 * - Password hashing and security
 * - USIU email domain validation
 * - Detailed error reporting and validation feedback
 * 
 * Security Features:
 * - Route access control (requires IS_AUTH_ROUTE)
 * - Input validation and sanitization
 * - Password hashing with PHP's password_hash()
 * - Email verification before account activation
 * - Protection against duplicate registrations
 * 
 * Request Format:
 * POST /api/auth/?action=register
 * {
 *   "student_id": "USIU2024001",
 *   "first_name": "John",
 *   "last_name": "Doe",
 *   "email": "john.doe@usiu.ac.ke",
 *   "password": "securepassword123",
 *   "phone": "+254712345678",
 *   "course": "Computer Science",
 *   "year_of_study": 3
 * }
 * 
 * Response Format:
 * Success: { "success": true, "message": "User registered successfully", "data": { "userId": "..." } }
 * Error: { "success": false, "message": "Registration failed", "errors": { "field": "error message" } }
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

// Core dependencies for user registration functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// User model for database operations and response utilities
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header
header('Content-Type: application/json');

// Get validated and sanitized request data from the auth router
// This data has already been processed through validation and sanitization middleware
$data = $requestData;

// Initialize user model with MongoDB users collection
$userModel = new UserModel($db->users);

// Attempt user registration with comprehensive validation
// This includes schema validation, duplicate checking, and email verification setup
$result = $userModel->createWithValidation($data);

// Handle registration failure with detailed error reporting
if (!$result['success']) {
    send_error('Registration failed', 400, $result['errors']);
}

// Send success response with user ID for client reference
// The user will need to verify their email before they can login
send_success('User registered successfully. Please check your email for verification instructions.', 201, [
    'userId' => (string)$result['id'],
    'email_verification_required' => true
]);
