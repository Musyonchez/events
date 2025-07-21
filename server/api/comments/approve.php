<?php
/**
 * USIU Events Management System - Comment Approval Endpoint
 * 
 * Handles the approval of pending comments as part of the comment moderation
 * workflow. Allows administrators and club leaders to approve comments that
 * are pending review, making them publicly visible on events.
 * 
 * Features:
 * - Comment approval with status change to 'approved'
 * - Administrative privilege verification
 * - Comment existence validation
 * - Moderation workflow integration
 * - Audit trail for moderation actions
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Route access control (requires IS_COMMENT_ROUTE)
 * - JWT authentication requirement
 * - Administrative privilege verification (admin/club_leader)
 * - Comment ownership and permission validation
 * - Protected against unauthorized approvals
 * 
 * Approval Validation:
 * - Comment exists and is accessible
 * - User has appropriate moderation privileges
 * - Comment is in a state that can be approved
 * - Approval action is logged for audit purposes
 * 
 * Request Format:
 * PATCH /api/comments/?action=approve&id=<comment_id>
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Comment approved successfully",
 *   "data": {
 *     "comment_id": "comment_object_id",
 *     "new_status": "approved",
 *     "approved_by": "admin_user_id",
 *     "approval_time": "2024-01-01 12:00:00"
 *   }
 * }
 * Error: { "success": false, "message": "Comment not found" }
 * 
 * Business Rules:
 * - Only admins and club leaders can approve comments
 * - Comments can be approved from pending or flagged status
 * - Approval makes comments publicly visible
 * - Approval actions are logged for audit purposes
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

// Core dependencies for comment approval functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Models and utilities for comment operations
require_once __DIR__ . '/../../models/Comment.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// Authenticate user and ensure they have permission to approve comments
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === Authorization Verification ===

// Check if user has administrative privileges for comment moderation
$currentUser = getCurrentUser();
if (!$currentUser || ($currentUser->role !== 'admin' && $currentUser->role !== 'club_leader')) {
    send_unauthorized('Administrative privileges required for comment approval', [
        'required_roles' => ['admin', 'club_leader'],
        'user_role' => $currentUser->role ?? 'unknown',
        'action' => 'comment_approval',
        'suggestion' => 'Contact an administrator for comment moderation access'
    ]);
}

// === Comment ID Validation ===

// Get comment ID from URL parameters
$commentId = $_GET['id'] ?? null;

if (empty($commentId)) {
    send_error('Comment ID is required for approval. Use: ?action=approve&id=<comment_id>', 400, [
        'parameter' => 'id',
        'requirement' => 'Valid comment ObjectId is required for approval'
    ]);
}

// Validate comment ID format
if (empty(trim($commentId))) {
    send_error('Comment ID cannot be empty', 400);
}

// === Comment Approval Process ===

// Initialize comment model with MongoDB comments collection
$commentModel = new CommentModel($db->comments);

try {
    // Attempt to approve the comment
    $approvalResult = $commentModel->approve($commentId, [
        'approved_by' => $currentUser->userId,
        'approved_at' => new DateTime(),
        'moderator_role' => $currentUser->role
    ]);
    
    if ($approvalResult) {
        // Log successful approval for audit trail
        error_log("Comment approved - ID: " . $commentId . ", Moderator: " . $currentUser->userId . " (" . $currentUser->role . ")");
        
        // Send success response with approval details
        send_success('Comment approved successfully', 200, [
            'comment_approved' => true,
            'comment_id' => $commentId,
            'new_status' => 'approved',
            'approved_by' => $currentUser->userId,
            'moderator_role' => $currentUser->role,
            'approval_time' => date('Y-m-d H:i:s'),
            'action' => 'comment_approval'
        ]);
    } else {
        // Comment not found or approval failed
        send_not_found('Comment not found or could not be approved');
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
        error_log('Comment approval failed: ' . $errorMessage);
        send_error('Failed to approve comment: ' . $errorMessage, 500, [
            'error_type' => 'approval_error',
            'comment_id' => $commentId
        ]);
    }
}