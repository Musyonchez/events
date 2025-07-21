<?php
/**
 * USIU Events Management System - Comment Creation Endpoint
 * 
 * Handles the creation of new comments on events with comprehensive validation,
 * user data embedding, and comment moderation workflow. Manages the complete
 * comment creation process with proper security and data integrity measures.
 * 
 * Features:
 * - Comment creation with full validation
 * - User data embedding for efficient display
 * - Event association and validation
 * - Automatic timestamp generation
 * - Comment moderation workflow support
 * - Comprehensive error handling and reporting
 * 
 * Security Features:
 * - Route access control (requires IS_COMMENT_ROUTE)
 * - JWT authentication requirement
 * - User verification and validation
 * - Input sanitization and validation
 * - Protected against spam and abuse
 * - Content moderation support
 * 
 * Comment Validation:
 * - Event existence verification
 * - User authentication and verification
 * - Content length and format validation
 * - Spam and inappropriate content detection
 * - Rate limiting and abuse prevention
 * 
 * Request Format:
 * POST /api/comments/?action=create
 * Headers: Authorization: Bearer <jwt_token>
 * {
 *   "event_id": "event_object_id",
 *   "content": "Comment text content",
 *   "parent_id": "parent_comment_id" (optional for replies)
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Comment created successfully",
 *   "data": { "commentId": "new_comment_object_id" }
 * }
 * Error: { "success": false, "message": "Validation error", "errors": [...] }
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Security check to ensure this endpoint is accessed through the comments router
if (!defined('IS_COMMENT_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Core dependencies for comment creation functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Models and utilities for comment operations
require_once __DIR__ . '/../../models/Comment.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// Authenticate user and ensure they have permission to create comments
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === Request Data Processing ===

// Get validated and sanitized request data from index.php
$data = $requestData;

// === User Context Integration ===

// Get authenticated user ID from JWT token
$userId = $GLOBALS['user']->userId;

// Validate user ID is available
if (!isset($userId)) {
    send_unauthorized('User ID not found in authentication token');
}

// Associate comment with authenticated user
$data['user_id'] = $userId;

// === User Data Embedding for Performance ===

// Initialize user model to fetch commenter information
$userModel = new UserModel($db->users);

try {
    // Fetch full user data to embed with comment for efficient display
    $user = $userModel->findById($userId);
    
    if ($user) {
        // Embed essential user data with comment to avoid joins during retrieval
        $data['user'] = [
            'id' => (string)$user['_id'],
            'first_name' => $user['first_name'] ?? 'Unknown',
            'last_name' => $user['last_name'] ?? 'User',
            'full_name' => ($user['first_name'] ?? 'Unknown') . ' ' . ($user['last_name'] ?? 'User'),
            'profile_image' => $user['profile_image'] ?? null,
            'email' => $user['email'] ?? null,
            'role' => $user['role'] ?? 'student'
        ];
    } else {
        // Handle case where user is not found (should not happen with valid auth)
        send_error('User account not found. Please re-authenticate.', 401, [
            'error_type' => 'user_not_found',
            'suggestion' => 'Please log out and log in again'
        ]);
    }
} catch (Exception $e) {
    // If user fetch fails, continue without embedded user data
    error_log('Failed to fetch user data for comment embedding: ' . $e->getMessage());
    // Comment creation will continue with user_id only
}

// === Comment Creation Process ===

// Initialize comment model with MongoDB comments collection
$commentModel = new CommentModel($db->comments);

// Attempt comment creation with comprehensive validation
$result = $commentModel->createWithValidation($data);

// Handle validation failures
if (!$result['success']) {
    send_error('Comment creation failed due to validation errors', 400, [
        'validation_errors' => $result['errors'],
        'provided_data' => array_keys($data),
        'suggestion' => 'Please correct the validation errors and try again'
    ]);
}

// Send successful creation response with comment details
send_created([
    'commentId' => (string)$result['id'],
    'event_id' => $data['event_id'] ?? null,
    'user_name' => ($data['user']['full_name'] ?? 'Unknown User'),
    'comment_preview' => substr($data['content'] ?? '', 0, 50) . (strlen($data['content'] ?? '') > 50 ? '...' : ''),
    'creation_time' => date('Y-m-d H:i:s'),
    'requires_moderation' => true // Comments may require approval
], 'Comment created successfully');

// Log successful comment creation for monitoring
error_log("Comment created successfully - ID: " . $result['id'] . ", Event: " . ($data['event_id'] ?? 'Unknown') . ", User: " . $userId);

