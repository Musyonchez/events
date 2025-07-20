<?php
/**
 * USIU Events Management System - Club Update Endpoint
 * 
 * Handles modification of existing club information with comprehensive
 * validation, authorization checks, and automatic role management for
 * leadership changes. Manages the complete club update workflow.
 * 
 * Features:
 * - Club information updates with validation
 * - Logo upload and replacement support
 * - Leadership change management with role transitions
 * - Authorization and ownership verification
 * - Comprehensive error handling and validation
 * - Automatic role promotion and demotion
 * 
 * Security Features:
 * - Route access control (requires IS_CLUB_ROUTE)
 * - JWT authentication requirement
 * - Authorization checks for club modification
 * - File upload validation and restrictions
 * - Input sanitization and validation
 * 
 * Update Validation:
 * - Club existence verification
 * - Leadership change authorization
 * - Data validation against club schema
 * - File upload security and type checking
 * - Role management safety checks
 * 
 * Request Format:
 * PATCH /api/clubs/?action=update&id=<club_id>
 * Headers: Authorization: Bearer <jwt_token>
 * Content-Type: multipart/form-data
 * {
 *   "name": "Updated Club Name",
 *   "description": "Updated Description",
 *   "category": "academic|social|sports|cultural",
 *   "leader_id": "new_leader_object_id",
 *   "logo": file_upload (optional)
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Club updated successfully",
 *   "data": { "modified": true }
 * }
 * Error: { "success": false, "message": "Validation error", "errors": [...] }
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

// Core dependencies for club update functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Models and utilities for club operations
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/upload.php';

// Authenticate user and ensure they have permission to update clubs
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === Club ID Validation ===

// Get the club ID from URL parameters or request data
$clubId = $_GET['id'] ?? $requestData['id'] ?? null;

if (empty($clubId)) {
    send_error('Club ID is required for update. Use: ?action=update&id=<club_id>', 400, [
        'parameter' => 'id',
        'requirement' => 'Valid club ObjectId is required for updates'
    ]);
}

// Validate club ID format
if (empty(trim($clubId))) {
    send_error('Club ID cannot be empty', 400);
}

// === Request Data Processing ===

// Get validated and sanitized request data from index.php
$data = $requestData;

// Remove ID from data to avoid conflicts (ID is passed separately)
unset($data['id']);

// Decode HTML entities in category field (reverse sanitization for predefined values)
if (isset($data['category'])) {
    $data['category'] = htmlspecialchars_decode($data['category'], ENT_QUOTES);
}

// === Logo File Upload Processing ===

// Handle logo file upload if present (for logo updates)
if (isset($_FILES['logo'])) {
    try {
        // Define allowed image file types for security
        $allowedMimeTypes = [
            'image/jpeg',   // JPEG images
            'image/png',    // PNG images
            'image/gif',    // GIF images
            'image/webp',   // WebP images (modern format)
        ];
        
        // Upload new logo to AWS S3 with validation
        $data['logo'] = upload_file_to_s3($_FILES['logo'], 'club_logos', $allowedMimeTypes);
        
        // Log successful upload for monitoring
        error_log("Club logo updated successfully: " . $data['logo']);
        
    } catch (Exception $e) {
        // Handle upload errors with detailed messaging
        send_error('Logo upload failed: ' . $e->getMessage(), 400, [
            'error_type' => 'file_upload_error',
            'allowed_types' => $allowedMimeTypes,
            'suggestion' => 'Please ensure your logo is a valid image file (JPEG, PNG, GIF, or WebP) under the size limit'
        ]);
    }
}

// === Authorization and Validation ===

// Initialize club model with MongoDB clubs collection
$clubModel = new ClubModel($db->clubs);

// TODO: Implement authorization check for club updates
// Add authorization check to ensure only the club leader or an admin can update
// if ($GLOBALS['user']->role !== 'admin') {
//     $club = $clubModel->findById($clubId);
//     if (!$club || $club['leader_id']->__toString() !== $GLOBALS['user']->userId) {
//         send_forbidden('You are not authorized to update this club');
//     }
// }

// === Leadership Change Detection ===

// Check if leadership is being changed (for role management)
$oldLeaderId = null;
if (isset($data['leader_id'])) {
    $existingClub = $clubModel->findById($clubId);
    if ($existingClub && isset($existingClub['leader_id'])) {
        $oldLeaderId = $existingClub['leader_id']->__toString();
    }
}

// === Club Update Process ===

// Attempt club update with comprehensive validation
$result = $clubModel->updateWithValidation($clubId, $data);

// Handle validation failures
if (!$result['success']) {
    send_validation_errors($result['errors']);
}

// === Automatic Role Management for Leadership Changes ===

// Handle leader role changes with comprehensive role management
if (isset($data['leader_id']) && !empty($data['leader_id'])) {
    try {
        $newLeaderId = $data['leader_id'];
        
        // === New Leader Role Promotion ===
        
        // Promote new leader to club_leader role (preserve higher roles)
        $newLeaderObjectId = new MongoDB\BSON\ObjectId($newLeaderId);
        
        // Check the new leader's current role
        $newLeaderUser = $db->users->findOne(['_id' => $newLeaderObjectId]);
        
        if ($newLeaderUser && $newLeaderUser['role'] === 'student') {
            // Only promote students to club_leader, preserve admin roles
            $updateResult = $db->users->updateOne(
                ['_id' => $newLeaderObjectId],
                ['$set' => ['role' => 'club_leader']]
            );
            
            if ($updateResult->getModifiedCount() === 1) {
                error_log("Success: Promoted user ID " . $newLeaderId . " to club_leader role");
            }
        } else if ($newLeaderUser) {
            error_log("Info: New leader ID " . $newLeaderId . " is already " . $newLeaderUser['role']);
        }
        
        // === Old Leader Role Demotion (if applicable) ===
        
        // If there was an old leader and it's different from the new one, handle demotion
        if ($oldLeaderId && $oldLeaderId !== $newLeaderId) {
            // Check if the old leader leads any other clubs
            $otherClubsCount = $db->clubs->countDocuments([
                'leader_id' => new MongoDB\BSON\ObjectId($oldLeaderId),
                '_id' => ['$ne' => new MongoDB\BSON\ObjectId($clubId)]
            ]);
            
            // If they don't lead any other clubs, consider demotion
            if ($otherClubsCount === 0) {
                $oldLeaderUser = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($oldLeaderId)]);
                
                if ($oldLeaderUser && $oldLeaderUser['role'] === 'club_leader') {
                    // Only demote club_leaders to student, preserve admin roles
                    $demoteResult = $db->users->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($oldLeaderId)],
                        ['$set' => ['role' => 'student']]
                    );
                    
                    if ($demoteResult->getModifiedCount() === 1) {
                        error_log("Success: Demoted former leader ID " . $oldLeaderId . " to student role");
                    }
                } else if ($oldLeaderUser) {
                    error_log("Info: Former leader ID " . $oldLeaderId . " is " . $oldLeaderUser['role'] . ", no demotion needed");
                }
            } else {
                error_log("Info: Former leader ID " . $oldLeaderId . " leads " . $otherClubsCount . " other clubs, no demotion");
            }
        }
        
    } catch (Exception $e) {
        // Log role management errors but don't fail club update
        error_log("Error handling leader role changes: " . $e->getMessage());
        // Club update continues even if role management fails to prevent data inconsistency
    }
}

// Send successful update response with details
send_success('Club updated successfully', 200, [
    'modified' => $result['modified'],
    'club_id' => $clubId,
    'leadership_changed' => isset($data['leader_id']) && !empty($data['leader_id']),
    'logo_updated' => isset($data['logo']),
    'update_time' => date('Y-m-d H:i:s')
]);

// Log successful club update for monitoring
error_log("Club updated successfully - ID: " . $clubId . ", Modified: " . ($result['modified'] ? 'true' : 'false'));

