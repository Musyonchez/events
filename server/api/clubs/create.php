<?php
/**
 * USIU Events Management System - Club Creation Endpoint
 * 
 * Handles the creation of new clubs with comprehensive validation,
 * file upload support for logos, and automatic role management for
 * club leaders. Manages the complete club registration workflow.
 * 
 * Features:
 * - Club creation with full data validation
 * - Logo upload to AWS S3 with file type validation
 * - Automatic role promotion for club leaders
 * - Data sanitization and security validation
 * - Comprehensive error handling and reporting
 * - Leader role management with safety checks
 * 
 * Security Features:
 * - Route access control (requires IS_CLUB_ROUTE)
 * - JWT authentication requirement
 * - File upload validation and restrictions
 * - Input sanitization and validation
 * - Protected against unauthorized club creation
 * 
 * Club Validation:
 * - Club name uniqueness checking
 * - Category validation against predefined options
 * - Leader ID validation and existence checking
 * - Description and purpose validation
 * - File upload security and size restrictions
 * 
 * Request Format:
 * POST /api/clubs/?action=create
 * Headers: Authorization: Bearer <jwt_token>
 * Content-Type: multipart/form-data
 * {
 *   "name": "Club Name",
 *   "description": "Club Description",
 *   "category": "academic|social|sports|cultural",
 *   "leader_id": "user_object_id",
 *   "logo": file_upload
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Club created successfully",
 *   "data": { "clubId": "new_club_object_id" }
 * }
 * Error: { "success": false, "message": "Validation error", "errors": [...] }
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Security check to ensure this endpoint is accessed through the clubs router
if (!defined('IS_CLUB_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Core dependencies for club creation functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Models and utilities for club operations
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/upload.php';

// Authenticate user and ensure they have permission to create clubs
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// Get validated and sanitized request data from index.php
$data = $requestData;

// Decode HTML entities in category field (reverse sanitization for predefined values)
if (isset($data['category'])) {
    $data['category'] = htmlspecialchars_decode($data['category'], ENT_QUOTES);
}

// === Required Field Validation ===

// Validate that leader_id is provided (required for club creation)
if (empty($data['leader_id'])) {
    send_error('Club leader must be selected. Please provide a valid leader_id.', 400, [
        'field' => 'leader_id',
        'requirement' => 'Club leader ID is required for club creation'
    ]);
}

// === Logo File Upload Processing ===

// Handle logo file upload if present
if (isset($_FILES['logo'])) {
    try {
        // Define allowed image file types for security
        $allowedMimeTypes = [
            'image/jpeg',   // JPEG images
            'image/png',    // PNG images
            'image/gif',    // GIF images
            'image/webp',   // WebP images (modern format)
        ];
        
        // Upload logo to AWS S3 with validation
        $data['logo'] = upload_file_to_s3($_FILES['logo'], 'club_logos', $allowedMimeTypes);
        
        // Log successful upload for monitoring
        error_log("Club logo uploaded successfully: " . $data['logo']);
        
    } catch (Exception $e) {
        // Handle upload errors with detailed messaging
        send_error('Logo upload failed: ' . $e->getMessage(), 400, [
            'error_type' => 'file_upload_error',
            'allowed_types' => $allowedMimeTypes,
            'suggestion' => 'Please ensure your logo is a valid image file (JPEG, PNG, GIF, or WebP) under the size limit'
        ]);
    }
}

// === Club Creation Process ===

// Initialize club model with MongoDB clubs collection
$clubModel = new ClubModel($db->clubs);

// Attempt club creation with comprehensive validation
$result = $clubModel->createWithValidation($data);

// Handle validation failures
if (!$result['success']) {
    send_error('Club creation failed due to validation errors', 400, [
        'validation_errors' => $result['errors'],
        'provided_data' => array_keys($data),
        'suggestion' => 'Please correct the validation errors and try again'
    ]);
}

// === Automatic Role Management for Club Leader ===

// Promote the selected user to club_leader role (with safety checks)
try {
    $leaderObjectId = new MongoDB\BSON\ObjectId($data['leader_id']);
    
    // Get current user information to verify role and existence
    $currentUser = $db->users->findOne(['_id' => $leaderObjectId]); 
    
    if ($currentUser && $currentUser['role'] === 'student') {
        // Only promote students to club_leader, preserve higher roles
        $updateResult = $db->users->updateOne(
            ['_id' => $leaderObjectId],
            ['$set' => ['role' => 'club_leader']]
        );
        
        if ($updateResult->getModifiedCount() === 1) {
            error_log("Success: Promoted user ID " . $data['leader_id'] . " to club_leader role");
        } else {
            error_log("Warning: Could not update user role to club_leader for user ID: " . $data['leader_id']);
        }
    } else if ($currentUser) {
        // User already has admin or club_leader role, no promotion needed
        error_log("Info: User ID " . $data['leader_id'] . " is already " . $currentUser['role'] . ", no role change needed");
    } else {
        // User not found - this is a validation error
        error_log("Warning: Could not find user with ID: " . $data['leader_id'] . " for role promotion");
    }
} catch (Exception $e) {
    // Log role management errors but don't fail club creation
    error_log("Error handling user role for club leader: " . $e->getMessage());
    // Club creation continues even if role update fails to prevent data inconsistency
}

// Send successful creation response with club details
send_created([
    'clubId' => (string)$result['id'],
    'club_name' => $data['name'] ?? 'Unknown',
    'leader_id' => $data['leader_id'],
    'logo_uploaded' => isset($data['logo']),
    'creation_time' => date('Y-m-d H:i:s')
], 'Club created successfully');

// Log successful club creation for monitoring
error_log("Club created successfully - ID: " . $result['id'] . ", Name: " . ($data['name'] ?? 'Unknown'));

