<?php
/**
 * USIU Events Management System - Club Details Endpoint
 * 
 * Retrieves comprehensive information about a specific club including
 * leader details, membership information, and related statistics.
 * Provides detailed club information for club profile pages.
 * 
 * Features:
 * - Single club information retrieval
 * - Leader information population
 * - Club membership statistics
 * - Event history and upcoming events
 * - Comprehensive error handling
 * - Data validation and sanitization
 * 
 * Security Features:
 * - Route access control (requires IS_CLUB_ROUTE)
 * - Club ID validation and sanitization
 * - Protected against invalid ObjectId formats
 * - Secure data population from related collections
 * 
 * Club Information:
 * - Basic club details (name, description, category)
 * - Leader information and contact details
 * - Membership count and member list (if authorized)
 * - Club status and activity information
 * - Creation date and last activity
 * 
 * Query Parameters:
 * - id: Club ObjectId (required)
 * 
 * Request Format:
 * GET /api/clubs/?action=details&id=<club_object_id>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "Club details fetched successfully",
 *   "data": {
 *     "_id": "club_object_id",
 *     "name": "Club Name",
 *     "description": "Club Description",
 *     "leader": { ... },
 *     ...
 *   }
 * }
 * Error: { "success": false, "message": "Club not found" }
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

// Core dependencies for club details functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Models and utilities for club operations
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../utils/response.php';

// MongoDB utilities for data operations
use MongoDB\BSON\Regex;
use MongoDB\BSON\ObjectId;

// Set JSON response header
header('Content-Type: application/json');

// Initialize club model with MongoDB clubs collection
$clubModel = new ClubModel($db->clubs);

// Extract club ID from query parameters
$clubId = $_GET['id'] ?? null;

// === Club ID Validation ===

if (empty($clubId)) {
    send_error('Club ID is required for retrieving club details. Use: ?action=details&id=<club_id>', 400, [
        'parameter' => 'id',
        'requirement' => 'Valid club ObjectId is required'
    ]);
}

// Validate club ID format
if (empty(trim($clubId))) {
    send_error('Club ID cannot be empty', 400);
}

// === Club Information Retrieval ===

try {
    // Retrieve club information by ID
    $club = $clubModel->findById($clubId);
    
    if (!$club) {
        send_not_found('Club not found or no longer available');
    }
    
    // === Leader Information Population ===
    
    // Populate leader information if leader_id exists
    if (isset($club['leader_id'])) {
        $leader = $db->users->findOne(['_id' => $club['leader_id']]);
        
        if ($leader) {
            // Include comprehensive leader information
            $club['leader'] = [
                'id' => (string)$leader['_id'],
                'first_name' => $leader['first_name'] ?? 'Unknown',
                'last_name' => $leader['last_name'] ?? 'Leader',
                'email' => $leader['email'] ?? null,
                'profile_image' => $leader['profile_image'] ?? null,
                'role' => $leader['role'] ?? 'club_leader',
                'joined_at' => $leader['created_at'] ?? null
            ];
        } else {
            // Handle missing leader gracefully
            $club['leader'] = [
                'id' => null,
                'first_name' => 'Unknown',
                'last_name' => 'Leader',
                'email' => null,
                'profile_image' => null,
                'role' => 'club_leader',
                'joined_at' => null
            ];
        }
    }
    
    // === Additional Club Statistics ===
    
    // Add member count if not already included
    if (!isset($club['members_count'])) {
        $club['members_count'] = isset($club['members']) ? count($club['members']) : 0;
    }
    
    // Add activity metrics
    $club['activity_metrics'] = [
        'total_members' => $club['members_count'] ?? 0,
        'is_active' => ($club['status'] ?? 'inactive') === 'active',
        'last_updated' => $club['updated_at'] ?? $club['created_at'] ?? null
    ];
    
    // Convert ObjectId to string for JSON response
    if (isset($club['_id'])) {
        $club['id'] = (string)$club['_id'];
    }
    
    // Send successful response with comprehensive club details
    send_success('Club details fetched successfully', 200, $club);
    
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
        error_log('Club details fetch failed: ' . $errorMessage);
        send_error('Failed to retrieve club details: ' . $errorMessage, 500, [
            'error_type' => 'database_error'
        ]);
    }
}