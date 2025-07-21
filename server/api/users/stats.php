<?php
/**
 * USIU Events Management System - User Statistics Endpoint
 * 
 * Provides comprehensive user statistics and analytics for administrative
 * dashboards and reporting purposes. Generates real-time metrics about
 * user activity, registration trends, and system usage patterns.
 * 
 * Features:
 * - Total and active user counts
 * - Monthly registration statistics
 * - Email verification rates
 * - User role distribution analysis
 * - Growth and activity metrics
 * - Real-time data aggregation
 * 
 * Security Features:
 * - Route access control (requires IS_USER_ROUTE)
 * - Administrative data access (recommend auth check)
 * - Privacy-aware statistics (no personal data)
 * - Aggregated data only
 * 
 * Statistics Provided:
 * - Total registered users
 * - Active users (non-suspended)
 * - New users this month
 * - Email verification rate
 * - Role distribution (student, club_leader, admin)
 * - Monthly growth indicators
 * 
 * Request Format:
 * GET /api/users/?action=stats
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "message": "User statistics retrieved successfully",
 *   "data": {
 *     "total_users": 1250,
 *     "active_users": 1180,
 *     "new_users_month": 45,
 *     "verification_rate": 89,
 *     "role_distribution": { ... },
 *     "current_month": "December 2024"
 *   }
 * }
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Core dependencies for user statistics functionality
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

// Security check to ensure this endpoint is accessed through the users router
if (!defined('IS_USER_ROUTE')) {
    send_error('Invalid request. This endpoint must be accessed through the users router.', 400);
}

// Set JSON response header
header('Content-Type: application/json');

// === Date and Time Configuration ===

// Get current date information for time-based statistics
$currentDate = new DateTime();
$currentMonth = $currentDate->format('n');    // 1-12 (numeric month)
$currentYear = $currentDate->format('Y');     // Full year

// Calculate first day of current month for monthly statistics
$monthStart = new DateTime("$currentYear-$currentMonth-01");
$monthStartTimestamp = $monthStart->getTimestamp() * 1000; // Convert to milliseconds for MongoDB

// === User Statistics Aggregation ===

// Total registered users across the platform
$totalUsersResult = $db->users->countDocuments([]);

// Active users count (excluding suspended accounts)
$activeUsersResult = $db->users->countDocuments([
    'status' => ['$ne' => 'suspended']
]);

// New user registrations this month
$newUsersThisMonthResult = $db->users->countDocuments([
    'created_at' => [
        '$gte' => new MongoDB\BSON\UTCDateTime($monthStartTimestamp)
    ]
]);

// Email verification statistics
$verifiedUsersResult = $db->users->countDocuments([
    'is_email_verified' => true
]);

// Calculate email verification rate as percentage
$verificationRate = $totalUsersResult > 0 ? 
    round(($verifiedUsersResult / $totalUsersResult) * 100) : 0;

// === User Role Distribution Analysis ===

// Initialize role distribution tracking
$roleDistribution = [];
$roles = ['student', 'club_leader', 'admin'];

// Count users by role for distribution analysis
foreach ($roles as $role) {
    $count = $db->users->countDocuments(['role' => $role]);
    $roleDistribution[$role] = $count;
}

// Calculate role percentages for better insights
$rolePercentages = [];
foreach ($roleDistribution as $role => $count) {
    $rolePercentages[$role] = $totalUsersResult > 0 ? 
        round(($count / $totalUsersResult) * 100, 1) : 0;
}

// === Enhanced Statistics Compilation ===

// Calculate additional useful metrics
$suspendedUsers = $db->users->countDocuments(['status' => 'suspended']);
$unverifiedUsers = $totalUsersResult - $verifiedUsersResult;
$inactiveUsers = $totalUsersResult - $activeUsersResult;

// Prepare comprehensive response data
$stats = [
    // Basic user counts
    'total_users' => $totalUsersResult,
    'active_users' => $activeUsersResult,
    'inactive_users' => $inactiveUsers,
    'suspended_users' => $suspendedUsers,
    
    // Monthly growth statistics
    'new_users_month' => $newUsersThisMonthResult,
    'current_month' => $currentDate->format('F Y'),
    
    // Email verification metrics
    'verification_stats' => [
        'verified_users' => $verifiedUsersResult,
        'unverified_users' => $unverifiedUsers,
        'verification_rate' => $verificationRate
    ],
    
    // Role distribution with counts and percentages
    'role_distribution' => [
        'counts' => $roleDistribution,
        'percentages' => $rolePercentages
    ],
    
    // Additional insights
    'activity_rate' => $totalUsersResult > 0 ? 
        round(($activeUsersResult / $totalUsersResult) * 100, 1) : 0,
    'suspension_rate' => $totalUsersResult > 0 ? 
        round(($suspendedUsers / $totalUsersResult) * 100, 1) : 0,
    
    // Metadata
    'generated_at' => $currentDate->format('Y-m-d H:i:s'),
    'timezone' => $currentDate->getTimezone()->getName()
];

// Send successful response with comprehensive user statistics
send_success('User statistics retrieved successfully', 200, $stats);