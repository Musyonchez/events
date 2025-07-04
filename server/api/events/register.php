<?php
if (!defined('IS_EVENT_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/email.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

// Authenticate user
$user = authenticate();

$eventModel = new EventModel($db->events);
$userModel = new UserModel($db->users);

// Ensure user object is available and has an _id
if (!isset($user->userId)) {
    send_unauthorized('User ID not found in authentication token.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $eventId = $data['event_id'] ?? '';

    if (empty($eventId)) {
        send_error('Event ID is required');
    }

    error_log("Event ID: " . $eventId);
    error_log("User ID: " . (string)$user->userId);
    try {
        if ($eventModel->registerUser($eventId, (string)$user->userId)) {
            $event = $eventModel->findById($eventId);
            $emailBody = "You have successfully registered for the event: <b>{$event['title']}</b>.";
            send_email($user->email, 'Event Registration Confirmation', $emailBody);
            send_success('Successfully registered for the event.');
        } else {
            send_error('Failed to register for the event.');
        }
    } catch (Exception $e) {
        send_error($e->getMessage());
    }
} else {
    send_error('Invalid request method.');
}
