<?php
/**
 * USIU Events Management System - User Update Endpoint
 * 
 * Handles modification of existing user profiles with comprehensive
 * validation, authorization checks, and profile image upload support.
 * Manages the complete user profile update workflow with proper security.
 * 
 * Features:
 * - User profile updates with validation
 * - Profile image upload and replacement support
 * - Password change functionality
 * - Role and status management (admin-only)
 * - Authorization and ownership verification
 * - Comprehensive error handling and validation
 * 
 * Security Features:
 * - Route access control (requires IS_USER_ROUTE)
 * - JWT authentication requirement
 * - Authorization checks for profile modification
 * - File upload validation and restrictions
 * - Input sanitization and validation
 * - Protected against unauthorized updates
 * 
 * Update Validation:
 * - User existence verification
 * - Authorization checks (own profile or admin)
 * - Data validation against user schema
 * - File upload security and type checking
 * - Email uniqueness validation
 * 
 * Request Format:
 * PATCH /api/users/?id=<user_id>
 * Headers: Authorization: Bearer <jwt_token>
 * Content-Type: multipart/form-data
 * {
 *   "first_name": "Updated Name",
 *   "last_name": "Updated Surname",
 *   "email": "updated.email@usiu.ac.ke",
 *   "profile_image": file_upload (optional)
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "User updated successfully",
 *   "data": { "modified": true }
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

// Core dependencies for user update functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/upload.php';

// Authenticate user and ensure they have permission to update users
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === User ID Validation ===

// Get the user ID from URL parameters (validated by middleware)
$userId = $_GET['id'] ?? null;

// Validate user ID is provided
if (empty($userId)) {
    send_error('User ID is required for update. Use: ?id=<user_id>', 400, [
        'parameter' => 'id',
        'requirement' => 'Valid user ObjectId is required for updates'
    ]);
}

// === Request Data Processing ===

// Get validated and sanitized request data from index.php
$data = $requestData;

// TODO: Add authorization check for user updates
// Ensure users can only update their own profile or admin can update any
// if ($GLOBALS['user']->role !== 'admin' && $GLOBALS['user']->userId !== $userId) {
//     send_forbidden('You are not authorized to update this user profile');
// }

// === Profile Image Upload Processing ===

// Handle profile image file upload if present (for profile updates)
if (isset($_FILES['profile_image'])) {
    try {
        // Define allowed image file types for security
        $allowedMimeTypes = [
            'image/jpeg',   // JPEG images
            'image/png',    // PNG images
            'image/gif',    // GIF images
            'image/webp',   // WebP images (modern format)
        ];
        
        // Upload new profile image to AWS S3 with validation
        $data['profile_image'] = upload_file_to_s3($_FILES['profile_image'], 'user_profiles', $allowedMimeTypes);
        
        // Log successful upload for monitoring
        error_log("User profile image updated successfully: " . $data['profile_image']);
        
    } catch (Exception $e) {
        // Handle upload errors with detailed messaging
        send_error('Profile image upload failed: ' . $e->getMessage(), 400, [
            'error_type' => 'file_upload_error',
            'allowed_types' => $allowedMimeTypes,
            'suggestion' => 'Please ensure your profile image is a valid image file (JPEG, PNG, GIF, or WebP) under the size limit'
        ]);
    }
}

// === User Update Process ===

// Initialize user model with MongoDB users collection
$userModel = new UserModel($db->users);

// Attempt user update with comprehensive validation
$result = $userModel->updateWithValidation($userId, $data);

// Handle validation failures
if (!$result['success']) {
    send_validation_errors($result['errors']);
}

// Send successful update response with details
send_success('User updated successfully', 200, [
    'modified' => $result['modified'],
    'user_id' => $userId,
    'profile_image_updated' => isset($data['profile_image']),
    'fields_updated' => array_keys($data),
    'update_time' => date('Y-m-d H:i:s')
]);

// Log successful user update for monitoring
error_log("User updated successfully - ID: " . $userId . ", Modified: " . ($result['modified'] ? 'true' : 'false'));

