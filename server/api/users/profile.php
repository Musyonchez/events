<?php
/**
 * USIU Events Management System - User Profile Endpoint
 * 
 * Retrieves the authenticated user's own profile information with privacy
 * protection and enhanced profile data. Provides comprehensive user profile
 * information for the current user's dashboard and settings.
 * 
 * Features:
 * - Authenticated user's profile retrieval
 * - Privacy-aware data filtering
 * - Enhanced profile information
 * - Activity statistics integration
 * - Profile completeness indicators
 * - Comprehensive error handling
 * 
 * Security Features:
 * - Route access control (requires IS_USER_ROUTE)
 * - JWT authentication requirement
 * - User-specific data access only
 * - Sensitive data filtering (passwords, tokens)
 * - Protected against unauthorized access
 * 
 * Profile Information:
 * - Basic user details (name, email, role)
 * - Profile preferences and settings
 * - Account status and verification state
 * - Activity metrics and statistics
 * - Club memberships and event history
 * - Profile completeness indicators
 * 
 * Request Format:
 * GET /api/users/?action=profile
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Response Format:
 * Success: {
 *   "success": true,
 *   "message": "User profile fetched successfully",
 *   "data": {
 *     "_id": "user_object_id",
 *     "first_name": "John",
 *     "last_name": "Doe",
 *     "email": "john.doe@usiu.ac.ke",
 *     "profile_completeness": { ... },
 *     "activity_summary": { ... },
 *     ...
 *   }
 * }
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

// Core dependencies for user profile functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

// MongoDB utilities for data operations
use MongoDB\BSON\ObjectId;

// Authenticate user and ensure user context is available
authenticate();

// Set JSON response header
header('Content-Type: application/json');

// === User Profile Retrieval ===

// Initialize user model with MongoDB users collection
$userModel = new UserModel($db->users);

// Get the authenticated user's ID from the JWT token
$userId = $GLOBALS['user']->userId;

// Validate user ID is available
if (!isset($userId)) {
    send_unauthorized('User ID not found in authentication token');
}

try {
    // Retrieve the authenticated user's profile
    $userProfile = $userModel->findById($userId);
    
    if (!$userProfile) {
        // This should not happen if authentication is valid
        send_error('User profile not found. Please re-authenticate.', 401, [
            'error_type' => 'profile_not_found',
            'suggestion' => 'Please log out and log in again'
        ]);
    }
    
    // === Privacy Protection and Data Enhancement ===
    
    // Remove sensitive fields for security
    unset($userProfile['password']);
    unset($userProfile['refresh_token']);
    unset($userProfile['email_verification_token']);
    unset($userProfile['password_reset_token']);
    unset($userProfile['two_factor_secret']);
    
    // Convert ObjectId to string for JSON response
    if (isset($userProfile['_id'])) {
        $userProfile['id'] = (string)$userProfile['_id'];
    }
    
    // === Enhanced Profile Information ===
    
    // Add computed fields
    $userProfile['full_name'] = ($userProfile['first_name'] ?? '') . ' ' . ($userProfile['last_name'] ?? '');
    $userProfile['display_role'] = ucfirst(str_replace('_', ' ', $userProfile['role'] ?? 'student'));
    
    // Calculate profile completeness
    $requiredFields = ['first_name', 'last_name', 'email', 'student_id'];
    $completedFields = 0;
    foreach ($requiredFields as $field) {
        if (!empty($userProfile[$field])) {
            $completedFields++;
        }
    }
    
    $userProfile['profile_completeness'] = [
        'percentage' => round(($completedFields / count($requiredFields)) * 100),
        'completed_fields' => $completedFields,
        'total_fields' => count($requiredFields),
        'missing_fields' => array_filter($requiredFields, function($field) use ($userProfile) {
            return empty($userProfile[$field]);
        })
    ];
    
    // Add activity summary
    $userObjectId = new ObjectId($userId);
    $eventsRegistered = $db->events->countDocuments(['registered_users' => $userObjectId]);
    $eventsCreated = $db->events->countDocuments(['created_by' => $userObjectId]);
    $clubsLeading = $db->clubs->countDocuments(['leader_id' => $userObjectId]);
    
    $userProfile['activity_summary'] = [
        'events_registered' => $eventsRegistered,
        'events_created' => $eventsCreated,
        'clubs_leading' => $clubsLeading,
        'total_activities' => $eventsRegistered + $eventsCreated + $clubsLeading
    ];
    
    // Add account information
    $userProfile['account_info'] = [
        'is_verified' => $userProfile['email_verified'] ?? false,
        'is_active' => ($userProfile['status'] ?? 'active') === 'active',
        'member_since' => $userProfile['created_at'] ?? null,
        'last_login' => $userProfile['last_login'] ?? null
    ];
    
    // Send successful response with enhanced profile data
    send_success('User profile fetched successfully', 200, $userProfile);
    
} catch (Exception $e) {
    // Handle unexpected errors
    error_log('User profile fetch failed: ' . $e->getMessage());
    send_internal_server_error('Failed to retrieve user profile: ' . $e->getMessage());
}
