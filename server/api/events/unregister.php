<?php
if (!defined('IS_EVENT_ROUTE')) {
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

header('Content-Type: application/json');

// Get the request body
$requestData = json_decode(file_get_contents('php://input'), true);

authenticate(); // Ensure user is logged in

// Validate required data
if (!isset($requestData['event_id']) || empty($requestData['event_id'])) {
    send_error('Event ID is required', 400);
}

try {
    $eventId = new ObjectId($requestData['event_id']);
    $userId = new ObjectId($GLOBALS['user']->userId);
    
    $eventModel = new EventModel($db->events);
    
    // Get the event
    $event = $eventModel->findById($eventId);
    if (!$event) {
        send_not_found('Event');
    }
    
    // Check if registration is still allowed (before event date)
    $now = new UTCDateTime();
    $eventDate = $event['event_date'];
    
    if ($eventDate <= $now) {
        send_error('Cannot unregister from past events', 400);
    }
    
    // Check if user is actually registered
    $isRegistered = false;
    if (isset($event['registered_users'])) {
        foreach ($event['registered_users'] as $registeredUser) {
            if ($registeredUser->equals($userId)) {
                $isRegistered = true;
                break;
            }
        }
    }
    
    if (!$isRegistered) {
        send_error('You are not registered for this event', 400);
    }
    
    // Remove user from registered_users array and decrement current_registrations
    $updateResult = $db->events->updateOne(
        ['_id' => $eventId],
        [
            '$pull' => ['registered_users' => $userId],
            '$inc' => ['current_registrations' => -1]
        ]
    );
    
    if ($updateResult->getModifiedCount() > 0) {
        send_success('Successfully unregistered from event', 200);
    } else {
        send_error('Failed to unregister from event', 500);
    }

} catch (Exception $e) {
    send_error('An error occurred while unregistering: ' . $e->getMessage(), 500);
}