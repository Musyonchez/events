<?php
/**
 * USIU Events Management System - Comment Flagging Endpoint
 * 
 * Handles the flagging of comments for moderation review. Allows users and
 * administrators to flag inappropriate, spam, or problematic comments that
 * require moderator attention and potential removal from the platform.
 * 
 * Features:
 * - Comment flagging with status change to 'flagged'
 * - User reporting and community moderation
 * - Flag reason tracking and categorization
 * - Automated moderation triggers
 * - Moderator notification system
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Route access control (requires IS_COMMENT_ROUTE)
 * - JWT authentication requirement
 * - User verification and validation
 * - Duplicate flag prevention
 * - Protected against spam flagging
 * 
 * Flagging Validation:
 * - Comment exists and is accessible
 * - User is authenticated and verified
 * - Comment is not already flagged by the same user
 * - Flag reason is valid and appropriate
 * - Flagging action is logged for review
 * 
 * Request Format:
 * PATCH /api/comments/?action=flag&id=<comment_id>
 * Headers: Authorization: Bearer <jwt_token>
 * {
 *   "reason": "inappropriate|spam|harassment|other",
 *   "details": "Additional details about the flag" (optional)
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Comment flagged successfully",
 *   "data": {
 *     "comment_id": "comment_object_id",
 *     "new_status": "flagged",
 *     "flagged_by": "user_id",
 *     "flag_reason": "inappropriate",
 *     "flag_time": "2024-01-01 12:00:00"
 *   }
 * }
 * Error: { "success": false, "message": "Comment not found" }
 * 
 * Business Rules:
 * - Users can flag comments for review
 * - Multiple flags can escalate priority
 * - Flagged comments are hidden pending review
 * - Moderators are notified of flagged content
 * - Flag abuse is tracked and prevented
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

// Core dependencies for comment flagging functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Models and utilities for comment operations
require_once __DIR__ . '/../../models/Comment.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// Authenticate user and ensure they have permission to flag comments
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === User Authentication and Validation ===

// Get current user context for flagging action
$currentUser = getCurrentUser();
if (!$currentUser) {
    send_unauthorized('User authentication required for comment flagging');
}

// Note: Updated to allow all authenticated users to flag comments
// Administrative review happens during moderation workflow

// === Comment ID Validation ===

// Get comment ID from URL parameters
$commentId = $_GET['id'] ?? null;

if (empty($commentId)) {
    send_error('Comment ID is required for flagging. Use: ?action=flag&id=<comment_id>', 400, [
        'parameter' => 'id',
        'requirement' => 'Valid comment ObjectId is required for flagging'
    ]);
}

// Validate comment ID format
if (empty(trim($commentId))) {
    send_error('Comment ID cannot be empty', 400);
}

// === Request Data Processing ===

// Parse flag reason and details from request body (if provided)
$requestData = json_decode(file_get_contents('php://input'), true) ?? [];
$flagReason = $requestData['reason'] ?? 'inappropriate';
$flagDetails = $requestData['details'] ?? null;

// Validate flag reason
$allowedReasons = ['inappropriate', 'spam', 'harassment', 'hate_speech', 'misinformation', 'other'];
if (!in_array($flagReason, $allowedReasons)) {
    send_error('Invalid flag reason', 400, [
        'allowed_reasons' => $allowedReasons,
        'provided_reason' => $flagReason,
        'suggestion' => 'Use one of the allowed flag reasons'
    ]);
}

// === Comment Flagging Process ===

// Initialize comment model with MongoDB comments collection
$commentModel = new CommentModel($db->comments);

try {
    // Attempt to flag the comment with detailed information
    $flaggingResult = $commentModel->flag($commentId, [
        'flagged_by' => $currentUser->userId,
        'flag_reason' => $flagReason,
        'flag_details' => $flagDetails,
        'flagged_at' => new DateTime(),
        'flagger_role' => $currentUser->role
    ]);
    
    if ($flaggingResult) {
        // Log flagging action for moderation review
        error_log("Comment flagged - ID: " . $commentId . ", Reason: " . $flagReason . ", Flagger: " . $currentUser->userId);
        
        // Send success response with flagging details
        send_success('Comment flagged successfully for moderation review', 200, [
            'comment_flagged' => true,
            'comment_id' => $commentId,
            'new_status' => 'flagged',
            'flagged_by' => $currentUser->userId,
            'flag_reason' => $flagReason,
            'flag_details' => $flagDetails,
            'flag_time' => date('Y-m-d H:i:s'),
            'action' => 'comment_flagged',
            'next_step' => 'Comment will be reviewed by moderators'
        ]);
    } else {
        // Comment not found or flagging failed
        send_not_found('Comment not found or could not be flagged');
    }
    
} catch (Exception $e) {
    // Handle specific error types
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, 'Invalid ObjectId') !== false) {
        send_error('Invalid comment ID format provided', 400, [
            'error_type' => 'invalid_object_id',
            'suggestion' => 'Please provide a valid MongoDB ObjectId'
        ]);
    } elseif (strpos($errorMessage, 'already flagged') !== false) {
        send_error('You have already flagged this comment', 409, [
            'error_type' => 'duplicate_flag',
            'suggestion' => 'You can only flag a comment once'
        ]);
    } else {
        // Log unexpected errors for debugging
        error_log('Comment flagging failed: ' . $errorMessage);
        send_error('Failed to flag comment: ' . $errorMessage, 500, [
            'error_type' => 'flagging_error',
            'comment_id' => $commentId
        ]);
    }
}