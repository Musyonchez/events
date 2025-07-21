<?php
/**
 * USIU Events Management System - User Creation Endpoint
 * 
 * Handles the creation of new user accounts with comprehensive validation,
 * profile image upload support, and user data management. Manages the
 * complete user registration workflow with proper security measures.
 * 
 * Features:
 * - User account creation with full data validation
 * - Profile image upload to AWS S3 with file type validation
 * - Email uniqueness checking and validation
 * - Password hashing and security measures
 * - Data sanitization and security validation
 * - Comprehensive error handling and reporting
 * 
 * Security Features:
 * - Route access control (requires IS_USER_ROUTE)
 * - JWT authentication requirement (admin-only operation)
 * - File upload validation and restrictions
 * - Input sanitization and validation
 * - Password security and hashing
 * - Protected against unauthorized user creation
 * 
 * User Validation:
 * - Email uniqueness and format validation
 * - Password strength requirements
 * - Required field validation (name, email, etc.)
 * - Profile image validation and size restrictions
 * - Role assignment and permission validation
 * 
 * Request Format:
 * POST /api/users/
 * Headers: Authorization: Bearer <jwt_token>
 * Content-Type: multipart/form-data
 * {
 *   "first_name": "John",
 *   "last_name": "Doe",
 *   "email": "john.doe@usiu.ac.ke",
 *   "password": "secure_password",
 *   "role": "student|club_leader|admin",
 *   "profile_image": file_upload (optional)
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "User created successfully",
 *   "data": { "insertedId": "new_user_object_id" }
 * }
 * Error: { "success": false, "message": "Validation error", "errors": [...] }
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

// Core dependencies for user creation functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/upload.php';

// Authenticate user and ensure they have permission to create users (admin-only)
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === Request Data Processing ===

// Get validated and sanitized request data from index.php
$data = $requestData;

// === Profile Image Upload Processing ===

// Handle profile image file upload if present
if (isset($_FILES['profile_image'])) {
    try {
        // Define allowed image file types for security
        $allowedMimeTypes = [
            'image/jpeg',   // JPEG images
            'image/png',    // PNG images
            'image/gif',    // GIF images
            'image/webp',   // WebP images (modern format)
        ];
        
        // Upload profile image to AWS S3 with validation
        $data['profile_image'] = upload_file_to_s3($_FILES['profile_image'], 'user_profiles', $allowedMimeTypes);
        
        // Log successful upload for monitoring
        error_log("User profile image uploaded successfully: " . $data['profile_image']);
        
    } catch (Exception $e) {
        // Handle upload errors with detailed messaging
        send_error('Profile image upload failed: ' . $e->getMessage(), 400, [
            'error_type' => 'file_upload_error',
            'allowed_types' => $allowedMimeTypes,
            'suggestion' => 'Please ensure your profile image is a valid image file (JPEG, PNG, GIF, or WebP) under the size limit'
        ]);
    }
}

// === User Creation Process ===

// Initialize user model with MongoDB users collection
$userModel = new UserModel($db->users);

// Attempt user creation with comprehensive validation
$result = $userModel->createWithValidation($data);

// Handle validation failures
if (!$result['success']) {
    send_error('User creation failed due to validation errors', 400, [
        'validation_errors' => $result['errors'],
        'provided_data' => array_keys($data),
        'suggestion' => 'Please correct the validation errors and try again'
    ]);
}

// Send successful creation response with user details
send_created([
    'insertedId' => (string)$result['id'],
    'user_email' => $data['email'] ?? 'Unknown',
    'user_name' => ($data['first_name'] ?? 'Unknown') . ' ' . ($data['last_name'] ?? 'User'),
    'role' => $data['role'] ?? 'student',
    'profile_image_uploaded' => isset($data['profile_image']),
    'creation_time' => date('Y-m-d H:i:s')
], 'User created successfully');

// Log successful user creation for monitoring
error_log("User created successfully - ID: " . $result['id'] . ", Email: " . ($data['email'] ?? 'Unknown'));

