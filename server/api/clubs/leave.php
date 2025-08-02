<?php
/**
 * USIU Events Management System - Club Leave Endpoint
 * 
 * Handles user leaving clubs with validation and membership management.
 * Allows users to remove themselves from club membership with proper
 * validation and data consistency maintenance.
 */

// Security check to ensure this endpoint is accessed through the clubs router
if (!defined('IS_CLUB_ROUTE')) {
    send_error('Invalid request. This endpoint must be accessed through the clubs router.', 400);
}

// Core dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../utils/response.php';

use MongoDB\BSON\ObjectId;

// Set JSON response header
header('Content-Type: application/json');

// Parse JSON request data
$requestData = json_decode(file_get_contents('php://input'), true);

// Validate JSON parsing was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('Invalid JSON data provided', 400);
}

// Authenticate user and get user context
$user = authenticate();
$userId = $user->userId;

// Validate required club ID is provided
if (!isset($requestData['club_id']) || empty($requestData['club_id'])) {
    send_error('Club ID is required for leaving club', 400);
}

try {
    // Convert string IDs to MongoDB ObjectIds
    $clubId = new ObjectId($requestData['club_id']);
    $userObjectId = new ObjectId($userId);
    
    // Initialize club model
    $clubModel = new ClubModel($db->clubs);
    
    // Retrieve club details
    $club = $clubModel->findById($clubId);
    if (!$club) {
        send_not_found('Club not found');
    }
    
    // Check if user is currently a member
    $isMember = false;
    $membersList = $club['members'] ?? [];
    
    foreach ($membersList as $memberId) {
        if ($memberId->__toString() === $userId) {
            $isMember = true;
            break;
        }
    }
    
    if (!$isMember) {
        send_error('You are not a member of this club', 400, [
            'error_type' => 'not_member',
            'suggestion' => 'You cannot leave a club you are not a member of'
        ]);
    }
    
    // Remove user from club
    $leaveResult = $clubModel->removeMember($clubId->__toString(), $userId);
    
    if ($leaveResult) {
        // Log successful club leave
        error_log("User left club - User ID: " . $userId . ", Club ID: " . $clubId);
        
        // Send success response
        send_success('Successfully left the club', 200, [
            'leave_confirmed' => true,
            'club_id' => (string)$clubId,
            'club_name' => $club['name'] ?? 'Unknown Club',
            'user_id' => $userId,
            'leave_time' => date('Y-m-d H:i:s')
        ]);
    } else {
        send_error('Failed to leave club. Please try again.', 500, [
            'error_type' => 'update_failed'
        ]);
    }

} catch (Exception $e) {
    // Handle specific error types
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, 'Invalid ObjectId') !== false) {
        send_error('Invalid club ID format', 400);
    } else {
        // Log unexpected errors for debugging
        error_log('Club leave error: ' . $errorMessage);
        send_error('An error occurred while leaving club: ' . $errorMessage, 500);
    }
}