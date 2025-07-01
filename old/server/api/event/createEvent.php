<?php

// api/event/createEvent.php
require_once __DIR__.'/../../cors.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method allowed']);
    exit;
}

// Get raw POST input
$input = json_decode(file_get_contents('php://input'), true);

require_once __DIR__.'/../../models/schemas/EventSchema.php';
require_once __DIR__.'/../../models/actions/EventActions.php';
require_once __DIR__.'/../../models/model/Event.php';

try {
    // Validate schema types
    EventSchema::validate($input);

    $EventActions = new EventsModel;

    // Check if event with same name, date, and time already exists
    $existingEvents = $EventActions->getEventsByDateRange($input['eventDate'], $input['eventDate']);
    foreach ($existingEvents as $existingEvent) {
        if ($existingEvent['event_name'] === $input['eventName'] &&
            $existingEvent['event_time'] === $input['eventTime']) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'Event with same name, date, and time already exists']);
            exit;
        }
    }

    $event = Event::fromArray($input);
    $insertedId = $EventActions->createEvent($event->toArray());

    echo json_encode([
        'message' => 'Event created successfully',
        'id' => (string) $insertedId,
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
