<?php
/**
 * USIU Events Management System - Event Creation Endpoint
 * 
 * Handles event creation with comprehensive validation, file upload support,
 * and authentication. Creates new events with proper data validation,
 * image handling, and creator assignment.
 * 
 * Features:
 * - Event creation with comprehensive validation
 * - Banner image upload to AWS S3
 * - Creator assignment from JWT token
 * - Event schema validation
 * - File upload error handling
 * - Detailed validation error reporting
 * 
 * Security Features:
 * - Route access control (requires IS_EVENT_ROUTE)
 * - JWT authentication requirement
 * - Input validation and sanitization
 * - File upload validation (mime types, size)
 * - Creator ID assignment from authenticated user
 * - Protected against unauthorized event creation
 * 
 * File Upload Support:
 * - Banner image upload to S3
 * - Supported formats: JPEG, PNG, GIF, WebP
 * - Automatic file validation and error handling
 * - S3 URL assignment to event data
 * 
 * Request Format:
 * POST /api/events/?action=create
 * Headers: Authorization: Bearer <jwt_token>
 * Content-Type: multipart/form-data (for file uploads)
 * {
 *   "title": "Event Title",
 *   "description": "Event description...",
 *   "club_id": "club_object_id",
 *   "event_date": "2024-06-15T10:00:00Z",
 *   "location": "Main Auditorium",
 *   "max_attendees": 200,
 *   "registration_required": true,
 *   "banner_image": <file>
 * }
 * 
 * Response Format:
 * Success: { "success": true, "message": "Event created successfully", "data": { "insertedId": "..." } }
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

// Core dependencies for event creation functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/upload.php';

// MongoDB ObjectId for creator assignment
use MongoDB\BSON\ObjectId;

// Require authentication - validates JWT and sets user context
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// Get validated and sanitized request data from the events router
$data = $requestData;

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
        
        // Upload banner image to S3 and get URL
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

// Assign creator ID from authenticated JWT token
// This ensures events are properly attributed to their creators
$data['created_by'] = new ObjectId($GLOBALS['user']->userId);

// Initialize event model with MongoDB events collection
$eventModel = new EventModel($db->events);

// Attempt event creation with comprehensive validation
// This includes schema validation, date validation, and business rule checks
$result = $eventModel->createWithValidation($data);

// Handle event creation failure with detailed error reporting
if (!$result['success']) {
    send_validation_errors($result['errors']);
}

// Send success response with created event ID
send_created([
    'insertedId' => (string)$result['id'],
    'message' => 'Event created successfully',
    'event_status' => 'draft', // New events start as draft
    'next_steps' => [
        'Review event details',
        'Publish when ready',
        'Share with club members'
    ]
]);
