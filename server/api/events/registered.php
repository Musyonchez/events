<?php
/**
 * USIU Events Management System - User Registered Events Endpoint
 * 
 * Retrieves events that the authenticated user has registered for with
 * pagination, sorting, and comprehensive event details. Provides users
 * with a personalized view of their event registrations.
 * 
 * Features:
 * - User's registered events listing
 * - Pagination support for large registration lists
 * - Flexible sorting options (date, title)
 * - Event status and timing information
 * - Registration metadata and details
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Route access control (requires IS_EVENT_ROUTE)
 * - JWT authentication requirement
 * - User-specific data filtering
 * - Protected against unauthorized access
 * - User context validation
 * 
 * Event Information:
 * - Complete event details for registered events
 * - Registration status and timing
 * - Event dates and locations
 * - Club information and context
 * - Event status (upcoming, past, cancelled)
 * 
 * Query Parameters:
 * - page: Page number for pagination (default: 1)
 * - limit: Results per page (default: 12)
 * - sort: Sorting option (date-asc, date-desc, title-asc, title-desc)
 * 
 * Request Format:
 * GET /api/events/?action=registered&page=1&limit=10&sort=date-asc
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Registered events fetched successfully",
 *   "data": {
 *     "events": [...],
 *     "pagination": { "total": 25, "page": 1, "limit": 10, ... }
 *   }
 * }
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Security check to ensure this endpoint is accessed through the events router
if (!defined('IS_EVENT_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Core dependencies for registered events functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

// MongoDB utilities for data operations
use MongoDB\BSON\ObjectId;

// Set JSON response header
header('Content-Type: application/json');

// Authenticate user and ensure user context is available
authenticate();

// Validate user context is available
if (!isset($GLOBALS['user']->userId)) {
    send_unauthorized('User ID not found in authentication token');
}

// Initialize event model for database operations
$eventModel = new EventModel($db->events);

// === Pagination Configuration ===
$limit = (int) ($_GET['limit'] ?? 12);  // Default 12 events per page
$page = (int) ($_GET['page'] ?? 1);     // Default to first page
$skip = ($page - 1) * $limit;           // Calculate offset for MongoDB

// Validate pagination parameters
if ($limit > 100) $limit = 100;        // Maximum 100 events per page
if ($page < 1) $page = 1;               // Minimum page 1

// === Flexible Sorting Options ===
$sortOptions = [];
if (isset($_GET['sort']) && !empty($_GET['sort'])) {
    $sortType = trim($_GET['sort']);
    
    switch ($sortType) {
        case 'date-asc':
            // Earliest events first
            $sortOptions['event_date'] = 1;
            break;
            
        case 'date-desc':
            // Latest events first
            $sortOptions['event_date'] = -1;
            break;
            
        case 'title-asc':
            // Alphabetical order A-Z
            $sortOptions['title'] = 1;
            break;
            
        case 'title-desc':
            // Reverse alphabetical order Z-A
            $sortOptions['title'] = -1;
            break;
            
        case 'registration-date':
            // Most recently registered first
            $sortOptions['created_at'] = -1;
            break;
            
        default:
            // Default: upcoming events by date
            $sortOptions['event_date'] = 1;
    }
} else {
    // Default sorting: upcoming events by date
    $sortOptions['event_date'] = 1;
}

try {
    // Convert user ID to MongoDB ObjectId
    $userId = new ObjectId($GLOBALS['user']->userId);
    
    // === Query Configuration ===
    
    // Find events where the user is in the registered_users array
    $filters = [
        'registered_users' => $userId
    ];
    
    // Optional: Add status filter to show only active registrations
    // This could exclude cancelled events or past events based on requirements
    if (isset($_GET['status'])) {
        $statusFilter = trim($_GET['status']);
        switch ($statusFilter) {
            case 'upcoming':
                $filters['event_date'] = ['$gte' => new MongoDB\BSON\UTCDateTime()];
                break;
            case 'past':
                $filters['event_date'] = ['$lt' => new MongoDB\BSON\UTCDateTime()];
                break;
            case 'published':
                $filters['status'] = 'published';
                break;
        }
    }

    // === Execute Query ===
    
    // Get total count of registered events for pagination metadata
    $total = $eventModel->count($filters);

    // Fetch the filtered, sorted, and paginated list of registered events
    $events = $eventModel->list($filters, $limit, $skip, $sortOptions);
    
    // === Enhance Event Data ===
    
    // Add registration-specific metadata to each event
    foreach ($events as &$event) {
        $eventDate = $event['event_date']->toDateTime();
        $now = new DateTime();
        
        // Add event timing information
        $event['is_upcoming'] = $eventDate > $now;
        $event['is_past'] = $eventDate < $now;
        $event['is_today'] = $eventDate->format('Y-m-d') === $now->format('Y-m-d');
        $event['days_until_event'] = $eventDate > $now ? $eventDate->diff($now)->days : 0;
        
        // Add registration metadata
        $event['user_registered'] = true; // User is definitely registered
        $event['can_unregister'] = $event['is_upcoming']; // Can only unregister from future events
    }

    // Calculate pagination metadata
    $totalPages = ceil($total / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;

    // Send successful response with events and pagination metadata
    send_success('Registered events fetched successfully', 200, [
        'events' => $events,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage
        ],
        'user_id' => (string)$userId,
        'filters_applied' => [
            'status' => $_GET['status'] ?? null,
            'sort' => $_GET['sort'] ?? 'date-asc'
        ]
    ]);

} catch (Exception $e) {
    // Handle database errors with detailed logging
    if (strpos($e->getMessage(), 'Invalid ObjectId') !== false) {
        send_error('Invalid user ID format', 400);
    } else {
        error_log('Registered events fetch failed: ' . $e->getMessage());
        send_error('Failed to fetch registered events due to a system error. Please try again.', 500, [
            'error_type' => 'database_error'
        ]);
    }
}