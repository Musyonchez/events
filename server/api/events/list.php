<?php
/**
 * USIU Events Management System - Event Listing Endpoint
 * 
 * Handles event listing with comprehensive filtering, searching, sorting,
 * and pagination capabilities. Provides public access to published events
 * with advanced query options for enhanced user experience.
 * 
 * Features:
 * - Comprehensive event filtering (search, club, category, status, date)
 * - Advanced search across title and description
 * - Date-based filtering (today, tomorrow, this week, this month, upcoming)
 * - Club and category filtering
 * - Status-based filtering (featured, registration-open)
 * - Multiple sorting options (date, title, featured)
 * - Pagination support with configurable limits
 * - Total count for pagination UI
 * 
 * Filtering Options:
 * - search: Text search in title and description (case-insensitive)
 * - club_id: Filter by specific club
 * - category: Filter by event category
 * - status: Filter by event status or special conditions
 * - date: Filter by date ranges (today, tomorrow, this-week, this-month, upcoming)
 * 
 * Sorting Options:
 * - date-asc/date-desc: Sort by event date
 * - title-asc/title-desc: Sort by event title
 * - featured: Featured events first, then by date
 * 
 * Query Parameters:
 * - page: Page number for pagination (default: 1)
 * - limit: Results per page (default: 12)
 * - search: Search term for title/description
 * - club_id: MongoDB ObjectId of club
 * - category: Event category name
 * - status: Event status or special filter
 * - date: Date filter option
 * - sort: Sorting option
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

// Core dependencies for event listing functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../utils/response.php';

// MongoDB utilities for filtering and querying
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

// Set JSON response header
header('Content-Type: application/json');

// Initialize event model with MongoDB events collection
$eventModel = new EventModel($db->events);

// === Event Listing Logic with Advanced Filtering and Pagination ===

// Pagination configuration
$limit = (int) ($_GET['limit'] ?? 12);  // Default 12 events per page
$page = (int) ($_GET['page'] ?? 1);     // Default to first page
$skip = ($page - 1) * $limit;           // Calculate offset for MongoDB

// Validate pagination parameters
if ($limit > 100) $limit = 100;        // Maximum 100 events per page
if ($page < 1) $page = 1;               // Minimum page 1

// Initialize MongoDB filters array
$filters = [];

// === Advanced Search and Filtering ===

// Full-text search across title and description (case-insensitive)
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    // Use MongoDB regex for flexible text search
    $filters['$or'] = [
        ['title' => new Regex($searchTerm, 'i')],
        ['description' => new Regex($searchTerm, 'i')]
    ];
}

// Club-specific filtering
if (isset($_GET['club_id']) && !empty($_GET['club_id'])) {
    try {
        $filters['club_id'] = new MongoDB\BSON\ObjectId($_GET['club_id']);
    } catch (Exception $e) {
        send_error('Invalid club ID format', 400);
    }
}

// Category-based filtering (case-insensitive)
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $filters['category'] = new Regex(trim($_GET['category']), 'i');
}

// Status and special condition filtering
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $statusFilter = trim($_GET['status']);
    
    switch ($statusFilter) {
        case 'featured':
            // Show only featured events
            $filters['featured'] = true;
            break;
            
        case 'registration-open':
            // Show events with open registration
            $filters['registration_required'] = true;
            $filters['registration_deadline'] = ['$gt' => new UTCDateTime()];
            break;
            
        case 'published':
            // Show only published events
            $filters['status'] = 'published';
            break;
            
        default:
            // Generic status filtering
            $filters['status'] = new Regex($statusFilter, 'i');
            break;
    }
}

// Advanced date-based filtering
if (isset($_GET['date']) && !empty($_GET['date'])) {
    $now = new DateTime();
    $today = new DateTime($now->format('Y-m-d'));
    $dateFilter = trim($_GET['date']);
    
    switch ($dateFilter) {
        case 'today':
            // Events happening today
            $endOfDay = (clone $today)->modify('+1 day');
            $filters['event_date'] = [
                '$gte' => new UTCDateTime($today),
                '$lt' => new UTCDateTime($endOfDay)
            ];
            break;
            
        case 'tomorrow':
            // Events happening tomorrow
            $tomorrow = (clone $today)->modify('+1 day');
            $dayAfter = (clone $tomorrow)->modify('+1 day');
            $filters['event_date'] = [
                '$gte' => new UTCDateTime($tomorrow),
                '$lt' => new UTCDateTime($dayAfter)
            ];
            break;
            
        case 'this-week':
            // Events happening this week (Monday to Sunday)
            $startOfWeek = (clone $today)->modify('this monday');
            $endOfWeek = (clone $startOfWeek)->modify('+1 week');
            $filters['event_date'] = [
                '$gte' => new UTCDateTime($startOfWeek),
                '$lt' => new UTCDateTime($endOfWeek)
            ];
            break;
            
        case 'this-month':
            // Events happening this month
            $startOfMonth = new DateTime('first day of this month midnight');
            $endOfMonth = new DateTime('first day of next month midnight');
            $filters['event_date'] = [
                '$gte' => new UTCDateTime($startOfMonth),
                '$lt' => new UTCDateTime($endOfMonth)
            ];
            break;
            
        case 'upcoming':
            // All future events from now
            $filters['event_date'] = ['$gte' => new UTCDateTime($now)];
            break;
            
        case 'past':
            // All past events
            $filters['event_date'] = ['$lt' => new UTCDateTime($now)];
            break;
    }
}

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
            
        case 'featured':
            // Featured events first, then by date
            $sortOptions['featured'] = -1;
            $sortOptions['event_date'] = 1;
            break;
            
        case 'popular':
            // Most registered events first
            $sortOptions['current_registrations'] = -1;
            $sortOptions['event_date'] = 1;
            break;
            
        case 'recent':
            // Recently created events first
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

// === Execute Query and Return Results ===
try {
    // Get total count of matching events for pagination metadata
    $total = $eventModel->count($filters);

    // Fetch the filtered, sorted, and paginated list of events
    $events = $eventModel->list($filters, $limit, $skip, $sortOptions);

    // Calculate pagination metadata
    $totalPages = ceil($total / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;

    // Send successful response with events and pagination metadata
    send_success('Events fetched successfully', 200, [
        'events' => $events,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage
        ],
        'filters_applied' => [
            'search' => $_GET['search'] ?? null,
            'club_id' => $_GET['club_id'] ?? null,
            'category' => $_GET['category'] ?? null,
            'status' => $_GET['status'] ?? null,
            'date' => $_GET['date'] ?? null,
            'sort' => $_GET['sort'] ?? 'date-asc'
        ]
    ]);

} catch (Exception $e) {
    // Handle database errors with detailed logging
    error_log('Event listing failed: ' . $e->getMessage());
    send_error('Failed to fetch events due to a system error. Please try again.', 500, [
        'error_type' => 'database_error'
    ]);
}
