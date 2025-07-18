<?php
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

if (!defined('IS_USER_ROUTE')) {
    send_error('Invalid request', 400);
}

try {

    // Get query parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $skip = isset($_GET['skip']) ? (int)$_GET['skip'] : 0;
    $role = isset($_GET['role']) ? $_GET['role'] : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $search = isset($_GET['search']) ? $_GET['search'] : null;

    // Build filter
    $filter = [];
    
    if ($role && in_array($role, ['student', 'club_leader', 'admin'])) {
        $filter['role'] = $role;
    }
    
    if ($status && in_array($status, ['active', 'inactive', 'suspended'])) {
        $filter['status'] = $status;
    }
    
    if ($search) {
        $filter['$or'] = [
            ['first_name' => ['$regex' => $search, '$options' => 'i']],
            ['last_name' => ['$regex' => $search, '$options' => 'i']],
            ['email' => ['$regex' => $search, '$options' => 'i']],
            ['student_id' => ['$regex' => $search, '$options' => 'i']]
        ];
    }

    // Get users with pagination
    $options = [
        'limit' => $limit,
        'skip' => $skip,
        'sort' => ['created_at' => -1] // Newest first
    ];
    
    $cursor = $db->users->find($filter, $options);
    $users = [];
    
    foreach ($cursor as $user) {
        // Remove sensitive fields
        unset($user['password']);
        unset($user['refresh_token']);
        unset($user['email_verification_token']);
        unset($user['password_reset_token']);
        
        $users[] = $user;
    }

    // Get total count for pagination
    $totalCount = $db->users->countDocuments($filter);

    // Prepare response data
    $responseData = [
        'users' => $users,
        'total_users' => $totalCount,
        'current_page' => floor($skip / $limit) + 1,
        'total_pages' => ceil($totalCount / $limit),
        'limit' => $limit,
        'skip' => $skip,
        'filters' => [
            'role' => $role,
            'status' => $status,
            'search' => $search
        ]
    ];

    // Send success response
    send_success('Users retrieved successfully', 200, $responseData);

} catch (Exception $e) {
    error_log("Error in users list endpoint: " . $e->getMessage());
    send_error('Internal server error', 500);
}
?>