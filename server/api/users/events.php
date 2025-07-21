<?php
/**
 * USIU Events Management System - User Events Endpoint
 * 
 * Retrieves events associated with the authenticated user including
 * registered events, created events, and club events. Provides comprehensive
 * event information tailored to the user's involvement and interests.
 * 
 * Features:
 * - User's registered events listing
 * - User's created events (if organizer/admin)
 * - Club events (if club member/leader)
 * - Pagination support for large event lists
 * - Event type filtering and categorization
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Route access control (requires IS_USER_ROUTE)
 * - JWT authentication requirement
 * - User-specific data filtering
 * - Protected against unauthorized access
 * - User context validation
 * 
 * Event Types:
 * - registered: Events user has registered for
 * - created: Events user has created (future enhancement)
 * - club_events: Events from user's clubs (future enhancement)
 * 
 * Query Parameters:
 * - type: Event type filter (default: 'registered')
 * - limit: Results per page (default: 20)
 * - skip: Number of results to skip for pagination
 * 
 * Request Format:
 * GET /api/users/?action=events&type=registered&limit=10&skip=0
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "User events fetched successfully",
 *   "data": [...]
 * }
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

// Core dependencies for user events functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// Authenticate user and ensure user context is available
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === User Context and Parameter Validation ===

// Initialize event model with MongoDB events collection
$eventModel = new EventModel($db->events);

// Get the authenticated user's ID
$userId = $GLOBALS['user']->userId;

// Validate user context is available
if (!isset($userId)) {
    send_unauthorized('User ID not found in authentication token');
}

// === Query Parameter Processing ===

// Get query parameters for filtering and pagination
$type = $_GET['type'] ?? 'registered';        // Default to registered events
$limit = (int) ($_GET['limit'] ?? 20);         // Default 20 events per page
$skip = (int) ($_GET['skip'] ?? 0);            // Default to first page

// Validate pagination parameters
if ($limit > 100) $limit = 100;               // Maximum 100 events per page
if ($limit < 1) $limit = 20;                  // Minimum 1 event per page
if ($skip < 0) $skip = 0;                     // Minimum skip 0

// Initialize filters array for MongoDB query
$filters = [];

// === Event Type Filtering and Data Retrieval ===

try {
    // Convert user ID to MongoDB ObjectId
    $userObjectId = new MongoDB\BSON\ObjectId($userId);
    
    // Filter events based on type parameter
    switch ($type) {
        case 'registered':
            // Events where the user is in the registered_users array
            $filters['registered_users'] = $userObjectId;
            break;
            
        case 'created':
            // Events created by this user (organizer view)
            $filters['created_by'] = $userObjectId;
            break;
            
        case 'club_events':
            // Future enhancement: Events from user's clubs
            // This would require joining with user's club memberships
            send_error('Club events filtering not yet implemented', 501, [
                'feature_status' => 'planned',
                'available_types' => ['registered', 'created']
            ]);
            break;
            
        default:
            send_error('Invalid event type specified', 400, [
                'allowed_types' => ['registered', 'created'],
                'provided_type' => $type,
                'suggestion' => 'Use one of the allowed event types'
            ]);
            break;
    }
    
    // === Event Data Retrieval ===
    
    // Retrieve filtered events with pagination
    $events = $eventModel->list($filters, $limit, $skip);
    
    // Enhance events with user-specific information
    foreach ($events as &$event) {
        $eventDate = $event['event_date']->toDateTime();
        $now = new DateTime();
        
        // Add event timing information
        $event['is_upcoming'] = $eventDate > $now;
        $event['is_past'] = $eventDate < $now;
        $event['is_today'] = $eventDate->format('Y-m-d') === $now->format('Y-m-d');
        $event['days_until_event'] = $eventDate > $now ? $eventDate->diff($now)->days : 0;
        
        // Add user-specific event information
        if ($type === 'registered') {
            $event['user_registered'] = true;
            $event['can_unregister'] = $event['is_upcoming'];
        } elseif ($type === 'created') {
            $event['user_is_creator'] = true;
            $event['can_edit'] = true;
            $event['can_delete'] = ($event['current_registrations'] ?? 0) === 0;
        }
    }
    
    // Send successful response with user events
    send_success('User events fetched successfully', 200, [
        'events' => $events,
        'event_type' => $type,
        'pagination' => [
            'limit' => $limit,
            'skip' => $skip,
            'count' => count($events)
        ],
        'user_id' => (string)$userObjectId
    ]);
    
} catch (Exception $e) {
    // Handle specific error types
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, 'Invalid ObjectId') !== false) {
        send_error('Invalid user ID format', 400);
    } else {
        // Log unexpected errors for debugging
        error_log('User events fetch failed: ' . $errorMessage);
        send_internal_server_error('Failed to fetch user events: ' . $errorMessage);
    }
}