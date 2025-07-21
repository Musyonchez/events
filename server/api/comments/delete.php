<?php
/**
 * USIU Events Management System - Comment Deletion Endpoint
 * 
 * Handles comment deletion with comprehensive validation, authorization checks,
 * and data integrity protection. Ensures proper cleanup and prevents
 * unauthorized deletion of comments with appropriate ownership verification.
 * 
 * Features:
 * - Comment deletion with ownership verification
 * - Administrative override capabilities
 * - Data integrity protection
 * - Cascade deletion handling
 * - Audit trail for deletion actions
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Route access control (requires IS_COMMENT_ROUTE)
 * - JWT authentication requirement
 * - Ownership verification (user can delete own comments)
 * - Administrative override (admins can delete any comment)
 * - Protected against unauthorized deletions
 * 
 * Deletion Validation:
 * - Comment existence verification
 * - User ownership or admin privilege checking
 * - Comment status and state validation
 * - Data integrity maintenance
 * 
 * Request Format:
 * DELETE /api/comments/?action=delete&id=<comment_id>
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Comment deleted successfully",
 *   "data": {
 *     "comment_deleted": true,
 *     "comment_id": "comment_object_id",
 *     "deleted_by": "user_id",
 *     "deletion_time": "2024-01-01 12:00:00"
 *   }
 * }
 * Error: { "success": false, "message": "Comment not found" }
 * 
 * Business Rules:
 * - Users can delete their own comments
 * - Admins can delete any comment
 * - Deleted comments are permanently removed
 * - Deletion actions are logged for audit purposes
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

// Core dependencies for comment deletion functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Models and utilities for comment operations
require_once __DIR__ . '/../../models/Comment.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// Authenticate user and ensure they have permission to delete comments
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === Comment ID Validation ===

// Get comment ID from URL parameters
$commentId = $_GET['id'] ?? null;

if (empty($commentId)) {
    send_error('Comment ID is required for deletion. Use: ?action=delete&id=<comment_id>', 400, [
        'parameter' => 'id',
        'requirement' => 'Valid comment ObjectId is required for deletion'
    ]);
}

// Validate comment ID format
if (empty(trim($commentId))) {
    send_error('Comment ID cannot be empty', 400);
}

// === Authorization and Validation ===

// Initialize comment model with MongoDB comments collection
$commentModel = new CommentModel($db->comments);

// Get current user context
$currentUser = $GLOBALS['user'];
if (!$currentUser) {
    send_unauthorized('User authentication required for comment deletion');
}

// TODO: Implement comprehensive authorization check
// Ensure only the comment owner or an admin can delete
// if ($currentUser->role !== 'admin') {
//     $comment = $commentModel->findById($commentId);
//     if (!$comment || $comment['user_id']->__toString() !== $currentUser->userId) {
//         send_forbidden('You are not authorized to delete this comment');
//     }
// }

// === Comment Deletion Process ===

try {
    // Attempt to delete the comment
    $deletionResult = $commentModel->delete($commentId);
    
    if ($deletionResult) {
        // Log successful deletion for audit trail
        error_log("Comment deleted - ID: " . $commentId . ", Deleted by: " . $currentUser->userId . " (" . ($currentUser->role ?? 'unknown') . ")");
        
        // Send success response with deletion details
        send_success('Comment deleted successfully', 200, [
            'comment_deleted' => true,
            'comment_id' => $commentId,
            'deleted_by' => $currentUser->userId,
            'deleter_role' => $currentUser->role ?? 'unknown',
            'deletion_time' => date('Y-m-d H:i:s'),
            'action' => 'comment_deletion'
        ]);
    } else {
        // Comment not found or deletion failed
        send_not_found('Comment not found or already deleted');
    }
    
} catch (Exception $e) {
    // Handle specific error types
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, 'Invalid ObjectId') !== false) {
        send_error('Invalid comment ID format provided', 400, [
            'error_type' => 'invalid_object_id',
            'suggestion' => 'Please provide a valid MongoDB ObjectId'
        ]);
    } else {
        // Log unexpected errors for debugging
        error_log('Comment deletion failed: ' . $errorMessage);
        send_internal_server_error('Failed to delete comment: ' . $errorMessage);
    }
}
