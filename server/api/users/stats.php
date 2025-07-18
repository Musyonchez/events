<?php
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

if (!defined('IS_USER_ROUTE')) {
    send_error('Invalid request', 400);
}

// Get current date info
$currentDate = new DateTime();
$currentMonth = $currentDate->format('n'); // 1-12
$currentYear = $currentDate->format('Y');

// Get first day of current month
$monthStart = new DateTime("$currentYear-$currentMonth-01");
$monthStartTimestamp = $monthStart->getTimestamp() * 1000; // Convert to milliseconds for MongoDB

// Get total users count
$totalUsersResult = $db->users->countDocuments([]);

// Get active users count (not suspended)
$activeUsersResult = $db->users->countDocuments([
    'status' => ['$ne' => 'suspended']
]);

// Get new users this month
$newUsersThisMonthResult = $db->users->countDocuments([
    'created_at' => [
        '$gte' => new MongoDB\BSON\UTCDateTime($monthStartTimestamp)
    ]
]);

// Get email verification rate
$verifiedUsersResult = $db->users->countDocuments([
    'is_email_verified' => true
]);

$verificationRate = $totalUsersResult > 0 ? 
    round(($verifiedUsersResult / $totalUsersResult) * 100) : 0;

// Get user role distribution
$roleDistribution = [];
$roles = ['student', 'club_leader', 'admin'];

foreach ($roles as $role) {
    $count = $db->users->countDocuments(['role' => $role]);
    $roleDistribution[$role] = $count;
}

// Prepare response data
$stats = [
    'total_users' => $totalUsersResult,
    'active_users' => $activeUsersResult,
    'new_users_month' => $newUsersThisMonthResult,
    'verification_rate' => $verificationRate,
    'role_distribution' => $roleDistribution,
    'current_month' => $currentDate->format('F Y')
];

// Send success response
send_success('User statistics retrieved successfully', 200, $stats);