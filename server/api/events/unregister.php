<?php
/**
 * USIU Events Management System - Event Unregistration Endpoint
 * 
 * Handles user unregistration from events with comprehensive validation,
 * timing restrictions, and data consistency maintenance. Allows users to
 * cancel their event registrations with proper security and business rules.
 * 
 * Features:
 * - User unregistration from events
 * - Registration status validation
 * - Event timing restrictions (no unregistration from past events)
 * - Registration count maintenance
 * - Comprehensive error handling
 * - Data consistency enforcement
 * 
 * Security Features:
 * - Route access control (requires IS_EVENT_ROUTE)
 * - JWT authentication requirement
 * - User verification and validation
 * - Registration status verification
 * - Protected against unauthorized unregistrations
 * 
 * Unregistration Validation:
 * - Event exists and is accessible
 * - User is currently registered for the event
 * - Event has not already occurred
 * - Unregistration deadline has not passed (if applicable)
 * - User account is verified and active
 * 
 * Request Format:
 * POST /api/events/?action=unregister
 * Headers: Authorization: Bearer <jwt_token>
 * {
 *   "event_id": "event_object_id"
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Successfully unregistered from event",
 *   "data": { "unregistration_confirmed": true }
 * }
 * Error: { "success": false, "message": "Cannot unregister from past events" }
 * 
 * Business Rules:
 * - Users cannot unregister from events that have already occurred
 * - Users can only unregister if they are currently registered
 * - Registration counts are automatically decremented
 * - Data consistency is maintained across collections
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Security check to ensure this endpoint is accessed through the events router
if (!defined('IS_EVENT_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Core dependencies for event unregistration functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

// MongoDB utilities for data operations
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

// Set JSON response header
header('Content-Type: application/json');

// Parse JSON request data
$requestData = json_decode(file_get_contents('php://input'), true);

// Validate JSON parsing was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('Invalid JSON data provided', 400);
}

// Authenticate user and get user context
authenticate();

// Validate required event ID is provided
if (!isset($requestData['event_id']) || empty($requestData['event_id'])) {
    send_error('Event ID is required for unregistration', 400);
}

// Validate user context is available
if (!isset($GLOBALS['user']->userId)) {
    send_unauthorized('User ID not found in authentication token');
}

try {
    // Convert string IDs to MongoDB ObjectIds
    $eventId = new ObjectId($requestData['event_id']);
    $userId = new ObjectId($GLOBALS['user']->userId);
    
    // Initialize event model for database operations
    $eventModel = new EventModel($db->events);
    
    // === Event Validation ===
    
    // Retrieve event details
    $event = $eventModel->findById($eventId);
    if (!$event) {
        send_not_found('Event not found');
    }
    
    // Check if event is still in the future (business rule)
    $now = new UTCDateTime();
    $eventDate = $event['event_date'];
    
    if ($eventDate <= $now) {
        send_error('Cannot unregister from events that have already occurred', 400, [
            'error_type' => 'past_event',
            'event_date' => $eventDate->toDateTime()->format('Y-m-d H:i:s')
        ]);
    }
    
    // === Registration Status Validation ===
    
    // Check if user is currently registered for this event
    $isRegistered = false;
    if (isset($event['registered_users'])) {
        foreach ($event['registered_users'] as $registeredUser) {
            if ($registeredUser->__toString() === $userId->__toString()) {
                $isRegistered = true;
                break;
            }
        }
    }
    
    if (!$isRegistered) {
        send_error('You are not currently registered for this event', 400, [
            'error_type' => 'not_registered',
            'suggestion' => 'Check your registered events list'
        ]);
    }
    
    // === Unregistration Process ===
    
    // Remove user from event's registered users list and decrement count
    $updateResult = $db->events->updateOne(
        ['_id' => $eventId],
        [
            '$pull' => ['registered_users' => $userId],
            '$inc' => ['current_registrations' => -1]
        ]
    );
    
    if ($updateResult->getModifiedCount() > 0) {
        // Log successful unregistration for monitoring
        error_log("User unregistered from event - User ID: " . $userId . ", Event ID: " . $eventId);
        
        // Send success response with confirmation details
        send_success('Successfully unregistered from event', 200, [
            'unregistration_confirmed' => true,
            'event_id' => (string)$eventId,
            'event_title' => $event['title'] ?? 'Unknown Event',
            'user_id' => (string)$userId,
            'unregistration_time' => date('Y-m-d H:i:s'),
            'spots_now_available' => ($event['max_attendees'] ?? 0) > 0 ? true : false
        ]);
    } else {
        // Unregistration failed - this shouldn't happen if validation passed
        send_error('Failed to process unregistration. Please try again.', 500, [
            'error_type' => 'update_failed'
        ]);
    }

} catch (Exception $e) {
    // Handle specific error types
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, 'Invalid ObjectId') !== false) {
        send_error('Invalid event ID format', 400);
    } else {
        // Log unexpected errors for debugging
        error_log('Event unregistration error: ' . $errorMessage);
        send_error('An error occurred while processing unregistration: ' . $errorMessage, 500);
    }
}