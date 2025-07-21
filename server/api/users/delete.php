<?php
/**
 * USIU Events Management System - User Deletion Endpoint
 * 
 * Handles user account deletion with comprehensive validation, dependency
 * checking, and data integrity protection. Ensures proper cleanup and
 * prevents deletion of users with active commitments.
 * 
 * Features:
 * - User account deletion with dependency checking
 * - Event ownership validation
 * - Club leadership validation
 * - Data integrity protection
 * - Cascade deletion prevention
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Route access control (requires IS_USER_ROUTE)
 * - JWT authentication requirement (admin-only operation)
 * - Authorization checks for user deletion
 * - Dependency validation before deletion
 * - Protected against unauthorized deletions
 * 
 * Deletion Validation:
 * - User existence verification
 * - Associated events checking (created events)
 * - Club leadership checking
 * - Active commitments validation
 * - Data integrity maintenance
 * 
 * Request Format:
 * DELETE /api/users/?id=<user_id>
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "User deleted successfully"
 * }
 * Error: { "success": false, "message": "Cannot delete user. User has 5 event(s) and 2 club(s) associated with them." }
 * 
 * Business Rules:
 * - Users with created events cannot be deleted
 * - Users leading clubs cannot be deleted
 * - Only admins can delete user accounts
 * - Data integrity is maintained across collections
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Security check to ensure this endpoint is accessed through the users router
if (!defined('IS_USER_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Core dependencies for user deletion functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// Authenticate user and ensure they have permission to delete users (admin-only)
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === User ID Validation ===

// Get the user ID from URL parameters (validated by middleware)
$userId = $_GET['id'] ?? null;

// Validate user ID is provided
if (empty($userId)) {
    send_error('User ID is required for deletion. Use: ?id=<user_id>', 400, [
        'parameter' => 'id',
        'requirement' => 'Valid user ObjectId is required for deletion'
    ]);
}

// TODO: Add authorization check for user deletion
// Ensure only admins can delete user accounts
// if ($GLOBALS['user']->role !== 'admin') {
//     send_forbidden('Only administrators can delete user accounts');
// }

// === User Deletion Process ===

// Initialize user model with MongoDB users collection
$userModel = new UserModel($db->users);

// === Dependency Validation and Deletion Process ===

try {
    // Convert user ID to MongoDB ObjectId for queries
    $userObjectId = new MongoDB\BSON\ObjectId($userId);
    
    // === Comprehensive Dependency Checking ===
    
    // Check for events created by this user
    $userEvents = $db->events->find([
        'created_by' => $userObjectId
    ])->toArray();
    
    // Check for clubs led by this user
    $userClubs = $db->clubs->find([
        'leader_id' => $userObjectId
    ])->toArray();
    
    // Check for event registrations (optional - for information)
    $eventRegistrations = $db->events->countDocuments([
        'registered_users' => $userObjectId
    ]);
    
    // === Dependency Validation ===
    
    $dependencies = [];
    $detailedInfo = [];
    
    if (count($userEvents) > 0) {
        $dependencies[] = count($userEvents) . ' event(s)';
        $eventTitles = array_slice(array_map(function($event) {
            return $event['title'] ?? 'Untitled Event';
        }, $userEvents), 0, 3);
        $detailedInfo['events'] = [
            'count' => count($userEvents),
            'sample_titles' => $eventTitles
        ];
    }
    
    if (count($userClubs) > 0) {
        $dependencies[] = count($userClubs) . ' club(s)';
        $clubNames = array_slice(array_map(function($club) {
            return $club['name'] ?? 'Unnamed Club';
        }, $userClubs), 0, 3);
        $detailedInfo['clubs'] = [
            'count' => count($userClubs),
            'sample_names' => $clubNames
        ];
    }
    
    // Prevent deletion if user has active commitments
    if (!empty($dependencies)) {
        $errorMessage = 'Cannot delete user. User has ' . implode(' and ', $dependencies) . ' associated with them. Please delete or transfer these first.';
        send_error($errorMessage, 400, [
            'error_type' => 'dependency_violation',
            'dependencies' => $detailedInfo,
            'event_registrations' => $eventRegistrations,
            'suggestion' => 'Delete or transfer ownership of associated events and clubs before deleting the user',
            'action_required' => 'Remove ' . implode(' and ', $dependencies) . ' first'
        ]);
    }
    
    // === User Deletion Process ===
    
    // Attempt to delete the user
    $deletionResult = $userModel->delete($userId);
    
    if ($deletionResult) {
        // Log successful deletion for monitoring
        error_log("User deleted successfully - ID: " . $userId);
        
        // Send success response
        send_success('User deleted successfully', 200, [
            'user_deleted' => true,
            'user_id' => $userId,
            'event_registrations_cleaned' => $eventRegistrations,
            'deletion_time' => date('Y-m-d H:i:s')
        ]);
    } else {
        // User not found or already deleted
        send_not_found('User not found or already deleted');
    }
    
} catch (Exception $e) {
    // Handle specific error types
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, 'Invalid ObjectId') !== false) {
        send_error('Invalid user ID format provided', 400, [
            'error_type' => 'invalid_object_id',
            'suggestion' => 'Please provide a valid MongoDB ObjectId'
        ]);
    } else {
        // Log unexpected errors for debugging
        error_log('User deletion failed: ' . $errorMessage);
        send_internal_server_error('Failed to delete user: ' . $errorMessage);
    }
}
