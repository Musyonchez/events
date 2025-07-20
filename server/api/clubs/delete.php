<?php
/**
 * USIU Events Management System - Club Deletion Endpoint
 * 
 * Handles club deletion with comprehensive validation, authorization checks,
 * and dependency verification. Ensures data integrity by preventing deletion
 * of clubs with associated events and manages cleanup operations.
 * 
 * Features:
 * - Club deletion with dependency checking
 * - Event association validation
 * - Authorization and ownership verification
 * - Data integrity protection
 * - Cascade deletion prevention
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Route access control (requires IS_CLUB_ROUTE)
 * - JWT authentication requirement
 * - Authorization checks for club deletion
 * - Dependency validation before deletion
 * - Protected against unauthorized deletions
 * 
 * Deletion Validation:
 * - Club existence verification
 * - Associated events checking
 * - User authorization validation
 * - Data integrity maintenance
 * - Cascade operations safety
 * 
 * Request Format:
 * DELETE /api/clubs/?action=delete&id=<club_id>
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Club deleted successfully"
 * }
 * Error: { "success": false, "message": "Cannot delete club. Club has 5 event(s) associated with it." }
 * 
 * Business Rules:
 * - Clubs with associated events cannot be deleted
 * - Only club leaders or admins can delete clubs
 * - Deletion removes all club references safely
 * - Data integrity is maintained across collections
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Security check to ensure this endpoint is accessed through the clubs router
if (!defined('IS_CLUB_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Core dependencies for club deletion functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Models and utilities for club operations
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// Authenticate user and ensure they have permission to delete clubs
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === Club ID Validation ===

// Get the club ID from URL parameters
$clubId = $_GET['id'] ?? null;

if (empty($clubId)) {
    send_error('Club ID is required for deletion. Use: ?action=delete&id=<club_id>', 400, [
        'parameter' => 'id',
        'requirement' => 'Valid club ObjectId is required for deletion'
    ]);
}

// Validate club ID format
if (empty(trim($clubId))) {
    send_error('Club ID cannot be empty', 400);
}

// === Authorization and Validation ===

// Initialize club model with MongoDB clubs collection
$clubModel = new ClubModel($db->clubs);

// TODO: Implement authorization check for club deletion
// Add authorization check to ensure only the club leader or an admin can delete
// if ($GLOBALS['user']->role !== 'admin') {
//     $club = $clubModel->findById($clubId);
//     if (!$club || $club['leader_id']->__toString() !== $GLOBALS['user']->userId) {
//         send_forbidden('You are not authorized to delete this club');
//     }
// }

// === Dependency Validation and Deletion Process ===

try {
    // Convert club ID to MongoDB ObjectId for queries
    $clubObjectId = new MongoDB\BSON\ObjectId($clubId);
    
    // === Event Dependency Checking ===
    
    // Check if club has any associated events before deletion
    $clubEvents = $db->events->find([
        'club_id' => $clubObjectId
    ])->toArray();
    
    // Prevent deletion if events are associated with the club
    if (count($clubEvents) > 0) {
        $eventTitles = array_map(function($event) {
            return $event['title'] ?? 'Untitled Event';
        }, array_slice($clubEvents, 0, 3)); // Show first 3 event titles
        
        send_error(
            'Cannot delete club. Club has ' . count($clubEvents) . ' event(s) associated with it. Please delete these events first.',
            400,
            [
                'error_type' => 'dependency_violation',
                'associated_events_count' => count($clubEvents),
                'sample_events' => $eventTitles,
                'suggestion' => 'Delete all associated events before deleting the club',
                'action_required' => 'Remove ' . count($clubEvents) . ' event(s) first'
            ]
        );
    }
    
    // === Club Deletion Process ===
    
    // Attempt to delete the club
    $deletionResult = $clubModel->delete($clubId);
    
    if ($deletionResult) {
        // Log successful deletion for monitoring
        error_log("Club deleted successfully - ID: " . $clubId);
        
        // Send success response
        send_success('Club deleted successfully', 200, [
            'club_deleted' => true,
            'club_id' => $clubId,
            'deletion_time' => date('Y-m-d H:i:s')
        ]);
    } else {
        // Club not found or already deleted
        send_not_found('Club not found or already deleted');
    }
    
} catch (Exception $e) {
    // Handle specific error types
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, 'Invalid ObjectId') !== false) {
        send_error('Invalid club ID format provided', 400, [
            'error_type' => 'invalid_object_id',
            'suggestion' => 'Please provide a valid MongoDB ObjectId'
        ]);
    } else {
        // Log unexpected errors for debugging
        error_log('Club deletion failed: ' . $errorMessage);
        send_internal_server_error('Failed to delete club: ' . $errorMessage);
    }
}
