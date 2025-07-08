<?php
if (!defined('IS_USER_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

authenticate(); // Ensure user is logged in

header('Content-Type: application/json');

try {
    $userId = new ObjectId($GLOBALS['user']->userId);
    $now = new UTCDateTime();
    
    $eventModel = new EventModel($db->events);
    
    // Count registered events
    $registeredEventsCount = $eventModel->count([
        'registered_users' => $userId
    ]);
    
    // Count attended events (past registered events)
    $attendedEventsCount = $eventModel->count([
        'registered_users' => $userId,
        'event_date' => ['$lt' => $now]
    ]);
    
    // Count created events
    $createdEventsCount = $eventModel->count([
        'created_by' => $userId
    ]);
    
    // Count upcoming events (all public upcoming events)
    $upcomingEventsCount = $eventModel->count([
        'event_date' => ['$gte' => $now],
        'status' => 'published'
    ]);
    
    $stats = [
        'registered_events' => $registeredEventsCount,
        'attended_events' => $attendedEventsCount,
        'created_events' => $createdEventsCount,
        'upcoming_events' => $upcomingEventsCount
    ];

    send_success('User statistics fetched successfully', 200, $stats);

} catch (Exception $e) {
    send_error('An error occurred while fetching user statistics: ' . $e->getMessage(), 500);
}