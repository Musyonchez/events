<?php
/**
 * USIU Events Management System - Users Listing Endpoint
 * 
 * Retrieves a paginated list of users with advanced filtering, searching,
 * and sorting capabilities. Provides comprehensive user information for
 * administrative purposes and user discovery with proper privacy controls.
 * 
 * Features:
 * - Paginated user listings with customizable page size
 * - Advanced search functionality (name, email, student ID)
 * - Role-based filtering (student, club_leader, admin)
 * - Status filtering (active, inactive, suspended)
 * - Flexible sorting options (creation date, name)
 * - Privacy protection with sensitive data filtering
 * 
 * Security Features:
 * - Route access control (requires IS_USER_ROUTE)
 * - Sensitive data filtering (passwords, tokens)
 * - Input validation and sanitization
 * - Parameter validation and bounds checking
 * - Protected against SQL injection through MongoDB queries
 * 
 * Query Parameters:
 * - limit: Results per page (default: 50, max: 100)
 * - skip: Number of results to skip for pagination
 * - role: Filter by user role (student, club_leader, admin)
 * - status: Filter by user status (active, inactive, suspended)
 * - search: Search term for name, email, or student ID
 * 
 * Request Format:
 * GET /api/users/?action=list&limit=25&skip=0&role=student&search=john
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "data": {
 *     "users": [...],
 *     "total_users": 150,
 *     "current_page": 1,
 *     "total_pages": 6,
 *     "limit": 25,
 *     "skip": 0,
 *     "filters": { ... }
 *   }
 * }
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Core dependencies for user listing functionality
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

// Security check to ensure this endpoint is accessed through the users router
if (!defined('IS_USER_ROUTE')) {
    send_error('Invalid request. This endpoint must be accessed through the users router.', 400);
}

// === Users Listing Processing ===

try {
    // Set JSON response header
    header('Content-Type: application/json');
    
    // === Query Parameter Extraction ===
    
    // Pagination parameters with defaults and validation
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $skip = isset($_GET['skip']) ? (int)$_GET['skip'] : 0;
    
    // Filtering parameters
    $role = isset($_GET['role']) ? trim($_GET['role']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    
    // === Parameter Validation and Sanitization ===
    
    // Validate and sanitize pagination parameters
    $limit = max(1, min(100, $limit));     // Between 1 and 100 results per page
    $skip = max(0, $skip);                 // Minimum skip 0

    // === Query Filter Construction ===
    
    // Initialize filters array for MongoDB query
    $filter = [];
    
    // Role filter: validate against allowed roles
    if ($role && in_array($role, ['student', 'club_leader', 'admin'])) {
        $filter['role'] = $role;
    }
    
    // Status filter: validate against allowed statuses
    if ($status && in_array($status, ['active', 'inactive', 'suspended'])) {
        $filter['status'] = $status;
    }
    
    // Search filter: matches multiple fields (case-insensitive)
    if ($search && strlen($search) >= 2) {
        $escapedSearch = preg_quote($search, '/');
        $filter['$or'] = [
            ['first_name' => ['$regex' => $escapedSearch, '$options' => 'i']],
            ['last_name' => ['$regex' => $escapedSearch, '$options' => 'i']],
            ['email' => ['$regex' => $escapedSearch, '$options' => 'i']],
            ['student_id' => ['$regex' => $escapedSearch, '$options' => 'i']]
        ];
    }

    // === User Data Retrieval ===
    
    // Configure MongoDB query options
    $options = [
        'limit' => $limit,
        'skip' => $skip,
        'sort' => ['created_at' => -1]  // Newest users first
    ];
    
    // Execute query to retrieve filtered users
    $cursor = $db->users->find($filter, $options);
    $users = [];
    
    // === Privacy Protection and Data Processing ===
    
    foreach ($cursor as $user) {
        // Remove sensitive fields for security and privacy
        unset($user['password']);                    // Password hash
        unset($user['refresh_token']);               // JWT refresh token
        unset($user['email_verification_token']);    // Email verification token
        unset($user['password_reset_token']);        // Password reset token
        unset($user['two_factor_secret']);           // 2FA secret (if exists)
        
        // Convert ObjectId to string for JSON response
        if (isset($user['_id'])) {
            $user['id'] = (string)$user['_id'];
        }
        
        // Add computed fields for better frontend integration
        $user['full_name'] = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
        $user['display_role'] = ucfirst(str_replace('_', ' ', $user['role'] ?? 'student'));
        
        $users[] = $user;
    }

    // === Pagination Metadata Calculation ===
    
    // Get total count for pagination metadata
    $totalCount = $db->users->countDocuments($filter);
    
    // Calculate pagination information
    $currentPage = floor($skip / $limit) + 1;
    $totalPages = ceil($totalCount / $limit);
    
    // === Response Data Preparation ===
    
    $responseData = [
        'users' => $users,
        'pagination' => [
            'total_users' => $totalCount,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'limit' => $limit,
            'skip' => $skip,
            'has_next_page' => $currentPage < $totalPages,
            'has_prev_page' => $currentPage > 1
        ],
        'filters_applied' => [
            'role' => $role,
            'status' => $status,
            'search' => $search,
            'search_active' => !empty($search)
        ],
        'summary' => [
            'users_returned' => count($users),
            'filters_count' => count(array_filter([$role, $status, $search])),
            'showing_range' => [
                'from' => $skip + 1,
                'to' => min($skip + $limit, $totalCount),
                'total' => $totalCount
            ]
        ]
    ];

    // Send successful response with comprehensive user data
    send_success('Users retrieved successfully', 200, $responseData);

} catch (Exception $e) {
    // Handle database errors with detailed logging
    error_log("Error in users list endpoint: " . $e->getMessage());
    send_error('Failed to retrieve users due to a system error. Please try again.', 500, [
        'error_type' => 'database_error',
        'suggestion' => 'Please check your request parameters and try again'
    ]);
}