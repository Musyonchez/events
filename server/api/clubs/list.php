<?php
/**
 * USIU Events Management System - Clubs Listing Endpoint
 * 
 * Retrieves a paginated list of clubs with advanced filtering, searching,
 * and sorting capabilities. Provides comprehensive club information including
 * leader details and membership statistics for club discovery and browsing.
 * 
 * Features:
 * - Paginated club listings with customizable page size
 * - Advanced search functionality (name and description)
 * - Category-based filtering (academic, social, sports, cultural)
 * - Status filtering (active, inactive)
 * - Member count range filtering
 * - Flexible sorting options (name, date created, member count)
 * - Leader information population
 * 
 * Security Features:
 * - Route access control (requires IS_CLUB_ROUTE)
 * - Input validation and sanitization
 * - Parameter validation and bounds checking
 * - Protected against SQL injection through MongoDB queries
 * 
 * Query Parameters:
 * - page: Page number for pagination (default: 1)
 * - limit: Results per page (default: 10)
 * - search: Search term for name/description
 * - category: Filter by club category
 * - status: Filter by club status
 * - sort_by: Sort field (name, createdAt, members_count)
 * - sort_order: Sort direction (asc, desc)
 * - min_members: Minimum member count filter
 * - max_members: Maximum member count filter
 * 
 * Request Format:
 * GET /api/clubs/?action=list&page=1&limit=12&search=tech&category=academic
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "data": {
 *     "clubs": [...],
 *     "total_clubs": 25,
 *     "page": 1,
 *     "limit": 10,
 *     "total_pages": 3
 *   }
 * }
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Core dependencies for club listing functionality
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../utils/response.php';

// Security check to ensure this endpoint is accessed through the clubs router
if (!defined('IS_CLUB_ROUTE')) {
    send_error('Invalid request. This endpoint must be accessed through the clubs router.', 400);
}

// === Query Parameter Extraction ===

// Pagination parameters with defaults
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

// Search and filtering parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Sorting parameters with defaults
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'createdAt';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';

// Member count range filtering
$min_members = isset($_GET['min_members']) ? (int)$_GET['min_members'] : null;
$max_members = isset($_GET['max_members']) ? (int)$_GET['max_members'] : null;

// === Parameter Validation and Sanitization ===

// Ensure valid pagination values
$page = max(1, $page);                    // Minimum page 1
$limit = max(1, min(100, $limit));        // Between 1 and 100 results per page

// Validate sort order
$sort_order = strtolower($sort_order) === 'asc' ? 'asc' : 'desc';

// Validate sort field against allowed options
$allowed_sort_fields = ['name', 'createdAt', 'members_count', 'category'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'createdAt'; // Default to creation date if invalid
}

// === Query Filter Construction ===

// Initialize filters array for MongoDB query
$filters = [];

// Search filter: matches club name or description (case-insensitive)
if (!empty($search)) {
    $escapedSearch = preg_quote($search, '/');
    $filters['$or'] = [
        ['name' => ['$regex' => $escapedSearch, '$options' => 'i']],
        ['description' => ['$regex' => $escapedSearch, '$options' => 'i']],
    ];
}

// Category filter: exact match for club category
if (!empty($category)) {
    // Validate category against allowed values
    $allowed_categories = ['academic', 'social', 'sports', 'cultural', 'professional'];
    if (in_array($category, $allowed_categories)) {
        $filters['category'] = $category;
    }
}

// Status filter: active, inactive, or pending
if (!empty($status)) {
    $allowed_statuses = ['active', 'inactive', 'pending'];
    if (in_array($status, $allowed_statuses)) {
        $filters['status'] = $status;
    }
}

// Member count range filtering
if ($min_members !== null || $max_members !== null) {
    $member_filter = [];
    
    // Minimum member count filter
    if ($min_members !== null && $min_members >= 0) {
        $member_filter['$gte'] = $min_members;
    }
    
    // Maximum member count filter
    if ($max_members !== null && $max_members >= 0) {
        $member_filter['$lte'] = $max_members;
    }
    
    // Apply member count filter if any limits were set
    if (!empty($member_filter)) {
        $filters['members_count'] = $member_filter;
    }
}

// === Sorting Configuration ===

// Prepare MongoDB sort options (1 for ascending, -1 for descending)
$sort_options = [$sort_by => ($sort_order === 'asc' ? 1 : -1)];

// === Club Data Retrieval and Processing ===

try {
    // Initialize club model with MongoDB clubs collection
    $clubModel = new ClubModel($db->clubs);
    
    // Retrieve filtered, sorted, and paginated clubs
    $clubs = $clubModel->listClubs($filters, $page, $limit, $sort_options);
    
    // Get total count for pagination metadata
    $total_clubs = $clubModel->countClubs($filters);
    
    // === Leader Information Population ===
    
    // Enhance each club with leader details for frontend display
    foreach ($clubs as &$club) {
        if (isset($club['leader_id'])) {
            // Fetch leader information from users collection
            $leader = $db->users->findOne(['_id' => $club['leader_id']]);
            
            if ($leader) {
                // Include essential leader information (no sensitive data)
                $club['leader'] = [
                    'first_name' => $leader['first_name'] ?? 'Unknown',
                    'last_name' => $leader['last_name'] ?? 'Leader',
                    'email' => $leader['email'] ?? null,
                    'profile_image' => $leader['profile_image'] ?? null,
                    'role' => $leader['role'] ?? 'club_leader'
                ];
            } else {
                // Handle cases where leader is not found
                $club['leader'] = [
                    'first_name' => 'Unknown',
                    'last_name' => 'Leader',
                    'email' => null,
                    'profile_image' => null,
                    'role' => 'club_leader'
                ];
            }
        }
    }
    
    // Calculate pagination metadata
    $total_pages = ceil($total_clubs / $limit);
    
    // Send successful response with clubs data and pagination info
    send_response([
        'clubs' => $clubs,
        'pagination' => [
            'total_clubs' => $total_clubs,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages,
            'has_next_page' => $page < $total_pages,
            'has_prev_page' => $page > 1
        ],
        'filters_applied' => [
            'search' => $search ?: null,
            'category' => $category ?: null,
            'status' => $status ?: null,
            'member_range' => ($min_members !== null || $max_members !== null) ? [
                'min' => $min_members,
                'max' => $max_members
            ] : null
        ],
        'sorting' => [
            'sort_by' => $sort_by,
            'sort_order' => $sort_order
        ]
    ]);
    
} catch (Exception $e) {
    // Handle database errors with detailed logging
    error_log('Clubs listing failed: ' . $e->getMessage());
    send_error('Failed to retrieve clubs due to a system error. Please try again.', 500, [
        'error_type' => 'database_error'
    ]);
}

