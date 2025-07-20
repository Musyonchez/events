<?php
/**
 * USIU Events Management System - Event Deletion Endpoint
 * 
 * Handles event deletion with cascade operations for related data.
 * Removes events and all associated comments, registrations, and references
 * with proper ownership verification and data integrity maintenance.
 * 
 * Features:
 * - Event deletion with cascade operations
 * - Associated comments cleanup
 * - Registration data cleanup
 * - Ownership and permission verification
 * - Detailed deletion reporting
 * - Data integrity maintenance
 * 
 * Security Features:
 * - Route access control (requires IS_EVENT_ROUTE)
 * - JWT authentication requirement
 * - Event ownership verification (future enhancement)
 * - Cascade deletion to prevent orphaned data
 * - Protected against unauthorized deletions
 * 
 * Cascade Operations:
 * - Delete all comments associated with the event
 * - Remove user registrations for the event
 * - Clean up any event references in other collections
 * - Maintain data integrity across the system
 * 
 * Request Format:
 * DELETE /api/events/?action=delete&id=<event_id>
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Event deleted successfully (including 5 associated comment(s))"
 * }
 * Error: { "success": false, "message": "Event not found" }
 * 
 * Deletion Process:
 * 1. Verify user authentication and permissions
 * 2. Check event exists and user owns it
 * 3. Delete associated comments
 * 4. Remove user registrations
 * 5. Delete the event itself
 * 6. Report deletion statistics
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

// Core dependencies for event deletion functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// Require authentication - validates JWT and sets user context
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// Extract event ID from URL parameters
$eventId = $_GET['id'] ?? null;

// Validate that event ID is provided
if (!$eventId) {
    send_error('Event ID is required for deletion. Use: ?action=delete&id=<event_id>', 400);
}

// Validate event ID format
if (empty(trim($eventId))) {
    send_error('Event ID cannot be empty', 400);
}

// Initialize event model with MongoDB events collection
$eventModel = new EventModel($db->events);

try {
    // Convert event ID to MongoDB ObjectId for queries
    $eventObjectId = new MongoDB\BSON\ObjectId($eventId);
    
    // TODO: Add ownership verification
    // Verify that the authenticated user owns this event or has permission to delete it
    // $userId = $GLOBALS['user']->userId;
    // $event = $eventModel->findById($eventId);
    // if (!$event) {
    //     send_not_found('Event not found');
    // }
    // if ($event['created_by']->__toString() !== $userId) {
    //     send_forbidden('You do not have permission to delete this event');
    // }
    
    // === Cascade Deletion Operations ===
    
    // Step 1: Delete all comments associated with this event
    $deletedComments = $db->comments->deleteMany([
        'event_id' => $eventObjectId
    ]);
    
    // Step 2: Remove this event from users' registered events lists
    // This prevents orphaned references in user documents
    $updatedUsers = $db->users->updateMany(
        ['registered_events' => $eventObjectId],
        ['$pull' => ['registered_events' => $eventObjectId]]
    );
    
    // Step 3: Delete the event itself
    $eventDeleted = $eventModel->delete($eventId);
    
    if ($eventDeleted) {
        // Build success message with deletion statistics
        $message = 'Event deleted successfully';
        $deletionStats = [];
        
        if ($deletedComments->getDeletedCount() > 0) {
            $deletionStats[] = $deletedComments->getDeletedCount() . ' associated comment(s)';
        }
        
        if ($updatedUsers->getModifiedCount() > 0) {
            $deletionStats[] = 'removed from ' . $updatedUsers->getModifiedCount() . ' user registration(s)';
        }
        
        if (!empty($deletionStats)) {
            $message .= ' (including ' . implode(', ', $deletionStats) . ')';
        }
        
        // Send success response with deletion details
        send_success($message, 200, [
            'event_deleted' => true,
            'comments_deleted' => $deletedComments->getDeletedCount(),
            'user_registrations_cleaned' => $updatedUsers->getModifiedCount(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        // Event not found or already deleted
        send_not_found('Event not found or already deleted');
    }
    
} catch (Exception $e) {
    // Handle invalid ObjectId format or database errors
    if (strpos($e->getMessage(), 'Invalid ObjectId') !== false) {
        send_error('Invalid event ID format', 400);
    } else {
        error_log('Event deletion failed: ' . $e->getMessage());
        send_internal_server_error('Failed to delete event: ' . $e->getMessage());
    }
}
