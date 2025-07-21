<?php
/**
 * USIU Events Management System - User Details Endpoint
 * 
 * Retrieves comprehensive information about a specific user including
 * profile details, activity statistics, and related information.
 * Provides detailed user information for profile pages and administration.
 * 
 * Features:
 * - Single user information retrieval
 * - Privacy-aware data filtering
 * - User activity metrics
 * - Profile completeness indicators
 * - Comprehensive error handling
 * - Data validation and sanitization
 * 
 * Security Features:
 * - Route access control (requires IS_USER_ROUTE)
 * - User ID validation and sanitization
 * - Sensitive data filtering (passwords, tokens)
 * - Protected against invalid ObjectId formats
 * - Privacy protection for sensitive information
 * 
 * User Information:
 * - Basic user details (name, email, role)
 * - Profile information and preferences
 * - Account status and verification state
 * - Activity metrics and statistics
 * - Creation date and last activity
 * 
 * Query Parameters:
 * - id: User ObjectId (required)
 * 
 * Request Format:
 * GET /api/users/?id=<user_object_id>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "User details fetched successfully",
 *   "data": {
 *     "_id": "user_object_id",
 *     "first_name": "John",
 *     "last_name": "Doe",
 *     "email": "john.doe@usiu.ac.ke",
 *     "role": "student",
 *     ...
 *   }
 * }
 * Error: { "success": false, "message": "User not found" }
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Security check to ensure this endpoint is accessed through the users router
if (!defined('IS_USER_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Core dependencies for user details functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Models and utilities for user operations
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

// MongoDB utilities for data operations
use MongoDB\BSON\ObjectId;

// Set JSON response header
header('Content-Type: application/json');

// === User ID Validation ===

// Get the user ID from URL parameters
$userId = $_GET['id'] ?? null;

// Validate that user ID is provided
if (empty($userId)) {
    send_error('User ID is required for retrieving user details. Use: ?id=<user_id>', 400, [
        'parameter' => 'id',
        'requirement' => 'Valid user ObjectId is required'
    ]);
}

// Validate user ID format
if (empty(trim($userId))) {
    send_error('User ID cannot be empty', 400);
}

// === User Information Retrieval ===

// Initialize user model with MongoDB users collection
$userModel = new UserModel($db->users);

try {
    // Retrieve user information by ID
    $user = $userModel->findById($userId);
    
    if (!$user) {
        send_not_found('User not found or no longer available');
    }
    
    // === Privacy Protection and Data Enhancement ===
    
    // Remove sensitive fields for security and privacy
    unset($user['password']);                    // Password hash
    unset($user['refresh_token']);               // JWT refresh token
    unset($user['email_verification_token']);    // Email verification token
    unset($user['password_reset_token']);        // Password reset token
    unset($user['two_factor_secret']);           // 2FA secret (if exists)
    
    // Convert ObjectId to string for JSON response
    if (isset($user['_id'])) {
        $user['id'] = (string)$user['_id'];
    }
    
    // === Enhanced User Information ===
    
    // Add computed fields for better frontend integration
    $user['full_name'] = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
    $user['display_role'] = ucfirst(str_replace('_', ' ', $user['role'] ?? 'student'));
    
    // Add profile completeness indicator
    $requiredFields = ['first_name', 'last_name', 'email', 'student_id'];
    $completedFields = 0;
    foreach ($requiredFields as $field) {
        if (!empty($user[$field])) {
            $completedFields++;
        }
    }
    $user['profile_completeness'] = [
        'percentage' => round(($completedFields / count($requiredFields)) * 100),
        'completed_fields' => $completedFields,
        'total_fields' => count($requiredFields),
        'missing_fields' => array_filter($requiredFields, function($field) use ($user) {
            return empty($user[$field]);
        })
    ];
    
    // Add account status information
    $user['account_info'] = [
        'is_verified' => $user['email_verified'] ?? false,
        'is_active' => ($user['status'] ?? 'active') === 'active',
        'created_date' => $user['created_at'] ?? null,
        'last_login' => $user['last_login'] ?? null
    ];
    
    // Send successful response with comprehensive user details
    send_success('User details fetched successfully', 200, $user);
    
} catch (Exception $e) {
    // Handle specific error types
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, 'Invalid ObjectId') !== false) {
        send_error('Invalid user ID format provided', 400, [
            'error_type' => 'invalid_object_id',
            'suggestion' => 'Please provide a valid MongoDB ObjectId'
        ]);
    } else {
        // Log unexpected errors for debugging
        error_log('User details fetch failed: ' . $errorMessage);
        send_error('Failed to retrieve user details: ' . $errorMessage, 500, [
            'error_type' => 'database_error'
        ]);
    }
}
