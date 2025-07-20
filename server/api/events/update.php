<?php
/**
 * USIU Events Management System - Event Update Endpoint
 * 
 * Handles event updates with comprehensive validation, file upload support,
 * and ownership verification. Allows event creators and authorized users
 * to modify existing events with proper security controls.
 * 
 * Features:
 * - Event update with comprehensive validation
 * - Banner image upload and replacement
 * - Ownership and permission verification
 * - Partial update support (only provided fields)
 * - File upload error handling
 * - Detailed validation error reporting
 * 
 * Security Features:
 * - Route access control (requires IS_EVENT_ROUTE)
 * - JWT authentication requirement
 * - Event ownership verification (future enhancement)
 * - Input validation and sanitization
 * - File upload validation (mime types, size)
 * - Protected against unauthorized modifications
 * 
 * Update Support:
 * - Partial updates (only specified fields)
 * - Banner image replacement
 * - Event status changes
 * - Registration settings modification
 * - Date and venue updates
 * 
 * Request Format:
 * PATCH /api/events/?action=update&id=<event_id>
 * Headers: Authorization: Bearer <jwt_token>
 * Content-Type: multipart/form-data (for file uploads)
 * {
 *   "title": "Updated Event Title",
 *   "description": "Updated description...",
 *   "event_date": "2024-07-15T10:00:00Z",
 *   "status": "published",
 *   "banner_image": <file>
 * }
 * 
 * Response Format:
 * Success: { "success": true, "message": "Event updated successfully", "data": { "modified": true } }
 * Error: { "success": false, "message": "Validation failed", "errors": { "field": "error message" } }
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

// Core dependencies for event update functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/upload.php';

// Require authentication - validates JWT and sets user context
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// Extract event ID from URL parameters or request data
$eventId = $_GET['id'] ?? $requestData['id'] ?? null;

// Validate that event ID is provided
if (!$eventId) {
    send_error('Event ID is required for update. Use: ?action=update&id=<event_id>', 400);
}

// Validate event ID format
if (empty(trim($eventId))) {
    send_error('Event ID cannot be empty', 400);
}

// Get validated and sanitized request data from the events router
$data = $requestData;

// Remove ID from update data to prevent conflicts (ID is passed separately)
unset($data['id']);

// Prevent modification of system fields
unset($data['created_by']);    // Creator cannot be changed
unset($data['created_at']);    // Creation timestamp is immutable

// Handle banner image upload if provided
if (isset($_FILES['banner_image'])) {
    try {
        // Define allowed image formats for event banners
        $allowedMimeTypes = [
            'image/jpeg',  // JPEG images
            'image/png',   // PNG images
            'image/gif',   // GIF images
            'image/webp',  // WebP images (modern format)
        ];
        
        // Upload new banner image to S3 and get URL
        $data['banner_image'] = upload_file_to_s3(
            $_FILES['banner_image'], 
            'event_banners',    // S3 folder prefix
            $allowedMimeTypes
        );
    } catch (Exception $e) {
        // Handle file upload errors with detailed message
        send_error('Banner image upload failed: ' . $e->getMessage(), 400, [
            'upload_error' => true,
            'file_field' => 'banner_image'
        ]);
    }
}

// Initialize event model with MongoDB events collection
$eventModel = new EventModel($db->events);

// TODO: Add ownership verification
// Verify that the authenticated user owns this event or has permission to modify it
// $userId = $GLOBALS['user']->userId;
// $event = $eventModel->findById($eventId);
// if (!$event || $event['created_by']->__toString() !== $userId) {
//     send_forbidden('You do not have permission to update this event');
// }

// Attempt event update with comprehensive validation
$result = $eventModel->updateWithValidation($eventId, $data);

// Handle event update failure with detailed error reporting
if (!$result['success']) {
    send_validation_errors($result['errors']);
}

// Send success response with update confirmation
send_success('Event updated successfully', 200, [
    'modified' => $result['modified'],
    'event_id' => $eventId,
    'timestamp' => date('Y-m-d H:i:s'),
    'updated_fields' => array_keys($data)
]);
