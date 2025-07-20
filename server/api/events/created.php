<?php
/**
 * USIU Events Management System - User Created Events Endpoint
 * 
 * Retrieves events created by the authenticated user with pagination,
 * sorting, and comprehensive event management information. Provides
 * event creators with a dashboard view of their organized events.
 * 
 * Features:
 * - User's created events listing
 * - Event management dashboard data
 * - Pagination support for event organizers
 * - Flexible sorting options (creation date, event date, title)
 * - Registration statistics and metrics
 * - Event status and lifecycle information
 * 
 * Security Features:
 * - Route access control (requires IS_EVENT_ROUTE)
 * - JWT authentication requirement
 * - Creator-specific data filtering
 * - Protected against unauthorized access
 * - User context validation
 * 
 * Event Management Information:
 * - Complete event details for created events
 * - Registration counts and capacity metrics
 * - Event status and publication state
 * - Creation and modification timestamps
 * - Performance metrics and analytics
 * 
 * Query Parameters:
 * - page: Page number for pagination (default: 1)
 * - limit: Results per page (default: 12)
 * - sort: Sorting option (created-desc, date-asc, date-desc, title-asc, title-desc)
 * - status: Filter by event status (draft, published, cancelled)
 * 
 * Request Format:
 * GET /api/events/?action=created&page=1&limit=10&sort=created-desc
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Created events fetched successfully",
 *   "data": {
 *     "events": [...],
 *     "pagination": { "total": 15, "page": 1, "limit": 10, ... },
 *     "statistics": { "total_events": 15, "published_events": 12, ... }
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

// Core dependencies for created events functionality
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
        case 'created-desc':
            // Most recently created first
            $sortOptions['created_at'] = -1;
            break;
            
        case 'created-asc':
            // Oldest created first
            $sortOptions['created_at'] = 1;
            break;
            
        case 'date-asc':
            // Earliest event dates first
            $sortOptions['event_date'] = 1;
            break;
            
        case 'date-desc':
            // Latest event dates first
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
            
        case 'popular':
            // Most registered events first
            $sortOptions['current_registrations'] = -1;
            $sortOptions['event_date'] = 1;
            break;
            
        case 'status':
            // Sort by status (published, draft, cancelled)
            $sortOptions['status'] = 1;
            $sortOptions['created_at'] = -1;
            break;
            
        default:
            // Default: most recently created first
            $sortOptions['created_at'] = -1;
    }
} else {
    // Default sorting: most recently created first
    $sortOptions['created_at'] = -1;
}

try {
    // Convert user ID to MongoDB ObjectId
    $userId = new ObjectId($GLOBALS['user']->userId);
    
    // === Query Configuration ===
    
    // Find events created by this user
    $filters = [
        'created_by' => $userId
    ];
    
    // Optional: Add status filter for event management
    if (isset($_GET['status'])) {
        $statusFilter = trim($_GET['status']);
        switch ($statusFilter) {
            case 'published':
                $filters['status'] = 'published';
                break;
            case 'draft':
                $filters['status'] = 'draft';
                break;
            case 'cancelled':
                $filters['status'] = 'cancelled';
                break;
            case 'upcoming':
                $filters['event_date'] = ['$gte' => new MongoDB\BSON\UTCDateTime()];
                break;
            case 'past':
                $filters['event_date'] = ['$lt' => new MongoDB\BSON\UTCDateTime()];
                break;
        }
    }

    // === Execute Query ===
    
    // Get total count of created events for pagination metadata
    $total = $eventModel->count($filters);

    // Fetch the filtered, sorted, and paginated list of created events
    $events = $eventModel->list($filters, $limit, $skip, $sortOptions);
    
    // === Enhance Event Data with Management Information ===
    
    $totalRegistrations = 0;
    $publishedEvents = 0;
    $upcomingEvents = 0;
    
    foreach ($events as &$event) {
        $eventDate = $event['event_date']->toDateTime();
        $now = new DateTime();
        
        // Add event timing information
        $event['is_upcoming'] = $eventDate > $now;
        $event['is_past'] = $eventDate < $now;
        $event['is_today'] = $eventDate->format('Y-m-d') === $now->format('Y-m-d');
        $event['days_until_event'] = $eventDate > $now ? $eventDate->diff($now)->days : 0;
        
        // Add registration metrics
        $currentRegistrations = $event['current_registrations'] ?? 0;
        $maxAttendees = $event['max_attendees'] ?? 0;
        
        $event['registration_metrics'] = [
            'current_registrations' => $currentRegistrations,
            'max_attendees' => $maxAttendees,
            'spots_remaining' => $maxAttendees > 0 ? max(0, $maxAttendees - $currentRegistrations) : null,
            'registration_rate' => $maxAttendees > 0 ? round(($currentRegistrations / $maxAttendees) * 100, 1) : null,
            'is_full' => $maxAttendees > 0 && $currentRegistrations >= $maxAttendees
        ];
        
        // Add management actions
        $event['management_actions'] = [
            'can_edit' => true,
            'can_delete' => $currentRegistrations === 0, // Only delete if no registrations
            'can_publish' => $event['status'] === 'draft',
            'can_cancel' => $event['is_upcoming'] && $event['status'] === 'published'
        ];
        
        // Collect statistics
        $totalRegistrations += $currentRegistrations;
        if ($event['status'] === 'published') $publishedEvents++;
        if ($event['is_upcoming']) $upcomingEvents++;
    }

    // Calculate pagination metadata
    $totalPages = ceil($total / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    // Calculate overall statistics
    $statistics = [
        'total_events' => $total,
        'published_events' => $publishedEvents,
        'upcoming_events' => $upcomingEvents,
        'total_registrations' => $totalRegistrations,
        'average_registrations_per_event' => $total > 0 ? round($totalRegistrations / $total, 1) : 0
    ];

    // Send successful response with events, pagination, and statistics
    send_success('Created events fetched successfully', 200, [
        'events' => $events,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage
        ],
        'statistics' => $statistics,
        'user_id' => (string)$userId,
        'filters_applied' => [
            'status' => $_GET['status'] ?? null,
            'sort' => $_GET['sort'] ?? 'created-desc'
        ]
    ]);

} catch (Exception $e) {
    // Handle database errors with detailed logging
    if (strpos($e->getMessage(), 'Invalid ObjectId') !== false) {
        send_error('Invalid user ID format', 400);
    } else {
        error_log('Created events fetch failed: ' . $e->getMessage());
        send_error('Failed to fetch created events due to a system error. Please try again.', 500, [
            'error_type' => 'database_error'
        ]);
    }
}