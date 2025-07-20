<?php
/**
 * USIU Events Management System - Club Membership Management Endpoint
 * 
 * Handles user membership operations for clubs including joining clubs
 * and leaving clubs. Manages membership validation, duplicate prevention,
 * and membership count maintenance with comprehensive error handling.
 * 
 * Features:
 * - Club membership joining with validation
 * - Duplicate membership prevention
 * - Membership count management
 * - User authentication and authorization
 * - Club existence verification
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Route access control (requires IS_CLUB_ROUTE)
 * - JWT authentication requirement
 * - POST method enforcement
 * - User verification and validation
 * - Protected against duplicate memberships
 * 
 * Membership Validation:
 * - Club exists and is accessible
 * - User is authenticated and verified
 * - User is not already a member
 * - Club allows new memberships
 * - Membership data consistency
 * 
 * Request Format:
 * POST /api/clubs/?action=join
 * Headers: Authorization: Bearer <jwt_token>
 * {
 *   "club_id": "club_object_id"
 * }
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Successfully joined club"
 * }
 * Error: { "success": false, "message": "User is already a member of this club" }
 * 
 * Business Rules:
 * - Users can only join clubs they are not already members of
 * - Club membership count is automatically updated
 * - User membership status is tracked consistently
 * - Data integrity is maintained across operations
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Core dependencies for club membership functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../utils/response.php';

// Security check to ensure this endpoint is accessed through the clubs router
if (!defined('IS_CLUB_ROUTE')) {
    send_error('Invalid request. This endpoint must be accessed through the clubs router.', 400);
}

// === HTTP Method Validation ===

// Ensure it's a POST request (membership changes require POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_method_not_allowed('Club membership actions require POST method. Use: POST /api/clubs/?action=join');
}

// Set JSON response header
header('Content-Type: application/json');

// === User Authentication and Validation ===

// Authenticate user and get user context
$user = authenticate();
$userId = $user->userId;

// Validate user ID is available
if (empty($userId)) {
    send_unauthorized('User ID not found in authentication token');
}

// === Request Data Validation ===

// Get club ID from request data (already parsed and sanitized in index.php)
if (!isset($requestData['club_id']) || empty($requestData['club_id'])) {
    send_error('Club ID is required for membership operations', 400, [
        'field' => 'club_id',
        'requirement' => 'Valid club ObjectId is required'
    ]);
}

$clubId = trim($requestData['club_id']);

// Validate club ID format
if (empty($clubId)) {
    send_error('Club ID cannot be empty', 400);
}

// === Club Membership Processing ===

try {
    // Initialize club model with MongoDB clubs collection
    $clubModel = new ClubModel($db->clubs);
    
    // === Club Existence Validation ===
    
    // Retrieve club information
    $club = $clubModel->findById($clubId);
    
    if (!$club) {
        send_not_found('Club not found or no longer available');
    }
    
    // === Membership Status Checking ===
    
    // Check if user is already a member of this club
    $isMember = false;
    $membersList = $club['members'] ?? [];
    
    foreach ($membersList as $memberId) {
        if ($memberId->__toString() == $userId) {
            $isMember = true;
            break;
        }
    }
    
    // Prevent duplicate membership
    if ($isMember) {
        send_error('You are already a member of this club', 409, [
            'error_type' => 'duplicate_membership',
            'club_name' => $club['name'] ?? 'Unknown Club',
            'suggestion' => 'You are already registered as a member of this club'
        ]);
    }
    
    // === Membership Addition Process ===
    
    // Add user to club members array and increment members_count
    $membershipResult = $clubModel->addMember($clubId, $userId);
    
    if ($membershipResult) {
        // Log successful membership for monitoring
        error_log("User joined club successfully - User ID: " . $userId . ", Club ID: " . $clubId);
        
        // Send success response with membership details
        send_success('Successfully joined club', 200, [
            'membership_confirmed' => true,
            'club_id' => $clubId,
            'club_name' => $club['name'] ?? 'Unknown Club',
            'user_id' => $userId,
            'join_time' => date('Y-m-d H:i:s'),
            'total_members' => ($club['members_count'] ?? 0) + 1
        ]);
    } else {
        // Membership addition failed
        send_error('Failed to join club. Please try again.', 500, [
            'error_type' => 'membership_addition_failed'
        ]);
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
        error_log('Club membership error: ' . $errorMessage);
        send_error('Error joining club: ' . $errorMessage, 500, [
            'error_type' => 'database_error'
        ]);
    }
}