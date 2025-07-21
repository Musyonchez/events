<?php
/**
 * USIU Events Management System - Comments API Router
 * 
 * Central routing endpoint for all comment-related operations in the USIU Events
 * Management System. Handles HTTP method validation, request processing, and
 * delegates to specific action endpoints for comment management functionality.
 * 
 * Supported Actions:
 * - create: Create new comments (POST)
 * - list: List comments with filtering (GET)
 * - details: Get specific comment information (GET)
 * - approve: Approve pending comments (PATCH)
 * - reject: Reject pending comments (PATCH)
 * - flag: Flag comments for moderation (PATCH)
 * - unflag: Remove flags from comments (PATCH)
 * - delete: Remove comments from system (DELETE)
 * 
 * Features:
 * - RESTful API design with action-based routing
 * - HTTP method enforcement for each action
 * - Request validation and data sanitization
 * - Centralized error handling and response formatting
 * - Security through route access control
 * - Comprehensive input processing
 * - Comment moderation workflow support
 * 
 * Security Features:
 * - Route access control (IS_COMMENT_ROUTE definition)
 * - Request method validation per action
 * - Input sanitization for all requests
 * - CORS configuration for cross-origin requests
 * - Standardized error responses
 * - Content moderation and approval workflows
 * 
 * Request Processing Flow:
 * 1. Define route access control
 * 2. Load core dependencies and middleware
 * 3. Validate HTTP method and request data
 * 4. Sanitize input data for security
 * 5. Route to appropriate action endpoint
 * 6. Handle errors with standardized responses
 * 
 * URL Format: /api/comments/?action=<action>&[additional_params]
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Define route access control to prevent direct file access
define('IS_COMMENT_ROUTE', true);

// Core system dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Middleware for request processing and security
require_once __DIR__ . '/../../middleware/validate.php';
require_once __DIR__ . '/../../middleware/sanitize.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header for all comment API responses
header('Content-Type: application/json');

// Get HTTP method for request routing
$method = $_SERVER['REQUEST_METHOD'];

// Validate the request and get decoded data for POST/PATCH requests
$requestData = validateRequest($method);

// Sanitize input data if it exists to prevent security vulnerabilities
if ($requestData !== null) {
    $requestData = sanitizeInput($requestData);
}

// Determine the action based on query parameter
$action = $_GET['action'] ?? null;

// === Action Routing with HTTP Method Enforcement ===

switch ($action) {
    case 'create':
        // Comment creation endpoint - requires POST method
        if ($method === 'POST') {
            require __DIR__ . '/create.php';
        } else {
            send_method_not_allowed('Comment creation requires POST method. Use: POST /api/comments/?action=create');
        }
        break;
        
    case 'list':
        // Comment listing endpoint - requires GET method
        if ($method === 'GET') {
            require __DIR__ . '/list.php';
        } else {
            send_method_not_allowed('Comment listing requires GET method. Use: GET /api/comments/?action=list');
        }
        break;
        
    case 'details':
        // Comment details endpoint - requires GET method
        if ($method === 'GET') {
            require __DIR__ . '/details.php';
        } else {
            send_method_not_allowed('Comment details requires GET method. Use: GET /api/comments/?action=details&id=<comment_id>');
        }
        break;
        
    case 'approve':
        // Comment approval endpoint - requires PATCH method (admin-only)
        if ($method === 'PATCH') {
            require __DIR__ . '/approve.php';
        } else {
            send_method_not_allowed('Comment approval requires PATCH method. Use: PATCH /api/comments/?action=approve&id=<comment_id>');
        }
        break;
        
    case 'reject':
        // Comment rejection endpoint - requires PATCH method (admin-only)
        if ($method === 'PATCH') {
            require __DIR__ . '/reject.php';
        } else {
            send_method_not_allowed('Comment rejection requires PATCH method. Use: PATCH /api/comments/?action=reject&id=<comment_id>');
        }
        break;
        
    case 'flag':
        // Comment flagging endpoint - requires PATCH method
        if ($method === 'PATCH') {
            require __DIR__ . '/flag.php';
        } else {
            send_method_not_allowed('Comment flagging requires PATCH method. Use: PATCH /api/comments/?action=flag&id=<comment_id>');
        }
        break;
        
    case 'unflag':
        // Comment unflagging endpoint - requires PATCH method (admin-only)
        if ($method === 'PATCH') {
            require __DIR__ . '/unflag.php';
        } else {
            send_method_not_allowed('Comment unflagging requires PATCH method. Use: PATCH /api/comments/?action=unflag&id=<comment_id>');
        }
        break;
        
    case 'delete':
        // Comment deletion endpoint - requires DELETE method
        if ($method === 'DELETE') {
            require __DIR__ . '/delete.php';
        } else {
            send_method_not_allowed('Comment deletion requires DELETE method. Use: DELETE /api/comments/?action=delete&id=<comment_id>');
        }
        break;
        
    default:
        // Handle invalid or missing action parameters
        if ($method === 'GET') {
            // For backward compatibility, redirect to get.php for GET requests without action
            require __DIR__ . '/get.php';
        } else {
            // Handle non-GET requests with invalid actions
            send_error('Invalid or missing action parameter', 400, [
                'available_actions' => [
                    'POST' => ['create'],
                    'GET' => ['list', 'details'],
                    'PATCH' => ['approve', 'reject', 'flag', 'unflag'],
                    'DELETE' => ['delete']
                ],
                'method_used' => $method,
                'suggestion' => 'Specify a valid action parameter'
            ]);
        }
        break;
}