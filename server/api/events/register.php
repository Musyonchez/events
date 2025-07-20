<?php
/**
 * USIU Events Management System - Event Registration Endpoint
 * 
 * Handles user registration for events with comprehensive validation,
 * capacity checking, deadline enforcement, and confirmation email delivery.
 * Manages the complete event registration workflow with proper security.
 * 
 * Features:
 * - User registration for events with validation
 * - Event capacity and deadline checking
 * - Duplicate registration prevention
 * - Registration confirmation email
 * - Registration status tracking
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Route access control (requires IS_EVENT_ROUTE)
 * - JWT authentication requirement
 * - User verification and validation
 * - Event availability checking
 * - Protected against duplicate registrations
 * 
 * Registration Validation:
 * - Event exists and is published
 * - Registration is required for the event
 * - Registration deadline has not passed
 * - Event capacity is not exceeded
 * - User is not already registered
 * - User account is verified and active
 * 
 * Request Format:
 * POST /api/events/?action=register
 * Headers: Authorization: Bearer <jwt_token>
 * {
 *   "event_id": "event_object_id"
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Successfully registered for the event",
 *   "data": { "registration_confirmed": true }
 * }
 * Error: { "success": false, "message": "Event is full" }
 * 
 * Email Confirmation:
 * - Automatic confirmation email sent upon successful registration
 * - Contains event details and registration information
 * - Includes event date, location, and important details
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// CORS configuration for cross-origin requests
require_once __DIR__ . '/../../config/cors.php';

// Security check to ensure this endpoint is accessed through the events router
if (!defined('IS_EVENT_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Core dependencies for event registration functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/email.php';
require_once __DIR__ . '/../../middleware/auth.php';

// Set JSON response header
header('Content-Type: application/json');

// Authenticate user and get user context
$user = authenticate();

// Initialize models for event and user operations
$eventModel = new EventModel($db->events);
$userModel = new UserModel($db->users);

// Validate authenticated user has required information
if (!isset($user->userId)) {
    send_unauthorized('User ID not found in authentication token');
}

// Validate user has email for confirmation
if (!isset($user->email)) {
    send_error('User email not found in authentication token', 401);
}

// Only accept POST requests for event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse JSON request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate JSON parsing was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error('Invalid JSON data provided', 400);
    }
    
    // Extract event ID from request data
    $eventId = $data['event_id'] ?? '';

    // Validate event ID is provided
    if (empty($eventId)) {
        send_error('Event ID is required for registration', 400);
    }

    // Log registration attempt for debugging and monitoring
    error_log("Event registration attempt - Event ID: " . $eventId . ", User ID: " . (string)$user->userId);
    
    try {
        // Attempt user registration for the event
        $registrationResult = $eventModel->registerUser($eventId, (string)$user->userId);
        
        if ($registrationResult) {
            // Get event details for confirmation email
            $event = $eventModel->findById($eventId);
            
            if ($event) {
                // Format event date for email
                $eventDate = $event['event_date']->toDateTime()->format('F j, Y \\a\\t g:i A');
                
                // Create comprehensive confirmation email
                $emailBody = "
                    <h2>Event Registration Confirmation</h2>
                    <p>You have successfully registered for the following event:</p>
                    <h3>{$event['title']}</h3>
                    <p><strong>Date:</strong> {$eventDate}</p>
                    <p><strong>Location:</strong> {$event['location']}</p>
                    <p><strong>Description:</strong> {$event['description']}</p>
                    <hr>
                    <p>We look forward to seeing you at the event!</p>
                    <p><small>If you need to cancel your registration, please contact the event organizers.</small></p>
                ";
                
                // Send confirmation email
                $emailSent = send_email(
                    $user->email, 
                    'Event Registration Confirmation - ' . $event['title'], 
                    $emailBody
                );
                
                // Log email status
                if (!$emailSent) {
                    error_log("Failed to send registration confirmation email to: " . $user->email);
                }
            }
            
            // Send success response
            send_success('Successfully registered for the event', 200, [
                'registration_confirmed' => true,
                'event_id' => $eventId,
                'user_id' => (string)$user->userId,
                'confirmation_email_sent' => $emailSent ?? false,
                'registration_time' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Registration failed - could be various reasons
            send_error('Failed to register for the event. The event may be full, registration may be closed, or you may already be registered.', 400);
        }
    } catch (Exception $e) {
        // Handle specific registration errors
        $errorMessage = $e->getMessage();
        
        if (strpos($errorMessage, 'Event is full') !== false) {
            send_error('Event registration is full. No more spots available.', 409);
        } elseif (strpos($errorMessage, 'already registered') !== false) {
            send_error('You are already registered for this event.', 409);
        } elseif (strpos($errorMessage, 'Event not found') !== false) {
            send_error('Event not found or no longer available.', 404);
        } else {
            // Log unexpected errors
            error_log('Event registration error: ' . $errorMessage);
            send_error('Registration failed: ' . $errorMessage, 500);
        }
    }
} else {
    // Handle non-POST requests
    send_method_not_allowed('Only POST method is allowed for event registration');
}
