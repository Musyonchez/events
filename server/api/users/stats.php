<?php
/**
 * USIU Events Management System - User Dashboard Statistics Endpoint
 * 
 * Provides personal user statistics for individual user dashboards.
 * Generates real-time metrics about the authenticated user's activity,
 * event participation, and personal engagement statistics.
 * 
 * Features:
 * - Personal registered events count
 * - Attended events count
 * - Created events count  
 * - Upcoming events count
 * - User-specific analytics
 * - Real-time data aggregation
 * 
 * Security Features:
 * - Route access control (requires IS_USER_ROUTE)
 * - Authentication required (user-specific data)
 * - Personal data access only
 * - User-scoped statistics
 * 
 * Statistics Provided:
 * - registered_events: Events user is registered for
 * - attended_events: Events user has attended (past events)
 * - created_events: Events user has created
 * - upcoming_events: Future events user is registered for
 * 
 * Request Format:
 * GET /api/users/?action=stats
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "message": "User statistics retrieved successfully",
 *   "data": {
 *     "registered_events": 5,
 *     "attended_events": 3,
 *     "created_events": 2,
 *     "upcoming_events": 2
 *   }
 * }
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Core dependencies for user statistics functionality
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

use MongoDB\BSON\ObjectId;

// Security check to ensure this endpoint is accessed through the users router
if (!defined('IS_USER_ROUTE')) {
    send_error('Invalid request. This endpoint must be accessed through the users router.', 400);
}

// Set JSON response header
header('Content-Type: application/json');

// Authenticate user and get user context
$user = authenticate();
$userId = $user->userId;
$userObjectId = new ObjectId($userId);

// === Date and Time Configuration ===

// Get current date information for time-based statistics
$currentDate = new DateTime();
$currentTimestamp = $currentDate->getTimestamp() * 1000; // Convert to milliseconds for MongoDB

// === Personal User Statistics Aggregation ===

try {
    // Count events user is registered for (all events with user in registered_users)
    $registeredEventsCount = $db->events->countDocuments([
        'registered_users' => $userObjectId,
        'status' => ['$ne' => 'cancelled']
    ]);

    // Count events user has attended (past events user was registered for)
    $attendedEventsCount = $db->events->countDocuments([
        'registered_users' => $userObjectId,
        'event_date' => ['$lt' => new MongoDB\BSON\UTCDateTime($currentTimestamp)],
        'status' => ['$ne' => 'cancelled']
    ]);

    // Count events user has created
    $createdEventsCount = $db->events->countDocuments([
        'created_by' => $userObjectId
    ]);

    // Count upcoming events user is registered for
    $upcomingEventsCount = $db->events->countDocuments([
        'registered_users' => $userObjectId,
        'event_date' => ['$gte' => new MongoDB\BSON\UTCDateTime($currentTimestamp)],
        'status' => ['$ne' => 'cancelled']
    ]);

    // Prepare personal statistics response
    $stats = [
        'registered_events' => $registeredEventsCount,
        'attended_events' => $attendedEventsCount,
        'created_events' => $createdEventsCount,
        'upcoming_events' => $upcomingEventsCount
    ];

    // Send successful response with personal user statistics
    send_success('User statistics retrieved successfully', 200, $stats);

} catch (Exception $e) {
    // Log the error for debugging
    error_log('User stats error: ' . $e->getMessage());
    
    // Send error response with default values
    send_error('Failed to load user statistics: ' . $e->getMessage(), 500, [
        'registered_events' => 0,
        'attended_events' => 0,
        'created_events' => 0,
        'upcoming_events' => 0
    ]);
}