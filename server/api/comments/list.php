<?php
/**
 * USIU Events Management System - Comments Listing Endpoint
 * 
 * Retrieves a paginated list of comments with advanced filtering, searching,
 * and moderation capabilities. Provides comprehensive comment information for
 * administrative purposes and event-specific comment management.
 * 
 * Features:
 * - Paginated comment listings with customizable page size
 * - Status-based filtering (approved, pending, flagged, rejected)
 * - Event-specific comment retrieval
 * - User and event details integration
 * - Administrative comment management
 * - Moderation workflow support
 * 
 * Security Features:
 * - Route access control (requires IS_COMMENT_ROUTE)
 * - JWT authentication requirement
 * - Administrative privilege verification
 * - Role-based access control (admin/club_leader)
 * - Protected against unauthorized access
 * 
 * Query Parameters:
 * - limit: Results per page (default: 50, max: 100)
 * - skip: Number of results to skip for pagination
 * - status: Filter by comment status (approved, pending, flagged, rejected)
 * - event_id: Filter comments for specific event
 * 
 * Request Format:
 * GET /api/comments/?action=list&limit=25&skip=0&status=pending&event_id=<event_id>
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "message": "Comments fetched successfully",
 *   "data": {
 *     "comments": [...],
 *     "total_count": 150,
 *     "page_info": { ... }
 *   }
 * }
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

// Core dependencies for comment listing functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Models and utilities for comment operations
require_once __DIR__ . '/../../models/Comment.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// Authenticate user and ensure they have permission to list comments
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === Authorization Verification ===

// Check if user has administrative privileges for comment management
$currentUser = getCurrentUser();
if (!$currentUser || ($currentUser->role !== 'admin' && $currentUser->role !== 'club_leader')) {
    send_unauthorized('Administrative privileges required for comment management', [
        'required_roles' => ['admin', 'club_leader'],
        'user_role' => $currentUser->role ?? 'unknown',
        'suggestion' => 'Contact an administrator for access to comment management features'
    ]);
}

// === Query Parameter Processing ===

// Initialize comment model with MongoDB comments collection
$commentModel = new CommentModel($db->comments);

// Get and validate query parameters
$limit = (int) ($_GET['limit'] ?? 50);           // Default 50 comments per page
$skip = (int) ($_GET['skip'] ?? 0);              // Default to first page
$status = $_GET['status'] ?? null;               // Filter by comment status
$eventId = $_GET['event_id'] ?? null;            // Filter by specific event

// === Parameter Validation ===

// Validate and sanitize pagination parameters
$limit = max(1, min(100, $limit));              // Between 1 and 100 results per page
$skip = max(0, $skip);                           // Minimum skip 0

// Validate status parameter if provided
$allowedStatuses = ['approved', 'pending', 'flagged', 'rejected'];
if ($status && !in_array($status, $allowedStatuses)) {
    send_error('Invalid status filter', 400, [
        'allowed_statuses' => $allowedStatuses,
        'provided_status' => $status,
        'suggestion' => 'Use one of the allowed status values'
    ]);
}

// === Query Options Configuration ===

// Configure query options for comment retrieval
$options = [
    'limit' => $limit,
    'skip' => $skip,
    'include_user_details' => true,              // Include commenter information
    'include_event_details' => true              // Include event information
];

// Add status filter if specified
if ($status) {
    $options['status'] = $status;
}

// Add event filter if specified
if ($eventId) {
    $options['event_id'] = $eventId;
}

// === Comment Data Retrieval ===

try {
    // Determine query type based on parameters
    if ($eventId) {
        // === Event-Specific Comments ===
        
        // Get comments for a specific event with enhanced details
        $comments = $commentModel->findByEventId($eventId, $options);
        
        // Get total count for pagination metadata
        $totalCount = $commentModel->countByEventId($eventId, ['status' => $status]);
        
    } else {
        // === Administrative Comment Management ===
        
        // Get all comments across the platform (admin functionality)
        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }
        
        // Retrieve comments with joined user and event details
        $comments = $commentModel->listWithDetails($filters, $limit, $skip);
        
        // Get total count for pagination metadata
        $totalCount = $commentModel->countWithFilters($filters);
    }
    
    // === Response Data Enhancement ===
    
    // Calculate pagination metadata
    $currentPage = floor($skip / $limit) + 1;
    $totalPages = ceil($totalCount / $limit);
    
    // Enhance comments with additional metadata
    foreach ($comments as &$comment) {
        // Add comment timing information
        if (isset($comment['created_at'])) {
            $createdDate = $comment['created_at']->toDateTime();
            $comment['time_ago'] = $this->calculateTimeAgo($createdDate);
            $comment['formatted_date'] = $createdDate->format('Y-m-d H:i:s');
        }
        
        // Add moderation status indicators
        $comment['moderation_info'] = [
            'requires_action' => in_array($comment['status'] ?? 'pending', ['pending', 'flagged']),
            'is_approved' => ($comment['status'] ?? 'pending') === 'approved',
            'is_flagged' => ($comment['status'] ?? 'pending') === 'flagged'
        ];
        
        // Convert ObjectIds to strings for JSON response
        if (isset($comment['_id'])) {
            $comment['id'] = (string)$comment['_id'];
        }
    }
    
    // Send successful response with comprehensive comment data
    send_success('Comments fetched successfully', 200, [
        'comments' => $comments,
        'pagination' => [
            'total_count' => $totalCount,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'limit' => $limit,
            'skip' => $skip,
            'has_next_page' => $currentPage < $totalPages,
            'has_prev_page' => $currentPage > 1
        ],
        'filters_applied' => [
            'status' => $status,
            'event_id' => $eventId,
            'is_event_specific' => !empty($eventId)
        ],
        'summary' => [
            'comments_returned' => count($comments),
            'showing_range' => [
                'from' => $skip + 1,
                'to' => min($skip + $limit, $totalCount),
                'total' => $totalCount
            ]
        ]
    ]);
    
} catch (Exception $e) {
    // Handle database errors with detailed logging
    error_log('Comments listing failed: ' . $e->getMessage());
    send_error('Failed to retrieve comments due to a system error. Please try again.', 500, [
        'error_type' => 'database_error',
        'suggestion' => 'Please check your request parameters and try again'
    ]);
}

// Helper function to calculate time ago (could be moved to utilities)
function calculateTimeAgo($datetime) {
    $now = new DateTime();
    $diff = $now->diff($datetime);
    
    if ($diff->days > 0) {
        return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}