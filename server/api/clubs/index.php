<?php
/**
 * USIU Events Management System - Clubs API Router
 * 
 * Central routing endpoint for all club-related operations in the USIU Events
 * Management System. Handles HTTP method validation, request processing, and
 * delegates to specific action endpoints for club management functionality.
 * 
 * Supported Actions:
 * - create: Create new clubs (POST)
 * - list: List all clubs with filtering (GET)
 * - details: Get specific club information (GET)
 * - update: Modify existing club details (PATCH)
 * - delete: Remove clubs from system (DELETE)
 * - join: Join/leave club membership (POST)
 * 
 * Features:
 * - RESTful API design with action-based routing
 * - HTTP method enforcement for each action
 * - Request validation and data sanitization
 * - Centralized error handling and response formatting
 * - Security through route access control
 * - Comprehensive input processing
 * 
 * Security Features:
 * - Route access control (IS_CLUB_ROUTE definition)
 * - Request method validation per action
 * - Input sanitization for all requests
 * - CORS configuration for cross-origin requests
 * - Standardized error responses
 * 
 * Request Processing Flow:
 * 1. Define route access control
 * 2. Load core dependencies and middleware
 * 3. Validate HTTP method and request data
 * 4. Sanitize input data for security
 * 5. Route to appropriate action endpoint
 * 6. Handle errors with standardized responses
 * 
 * URL Format: /api/clubs/?action=<action>&[additional_params]
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Define route access control to prevent direct file access
define('IS_CLUB_ROUTE', true);

// Core system dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Middleware for request processing and security
require_once __DIR__ . '/../../middleware/validate.php';
require_once __DIR__ . '/../../middleware/sanitize.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header for all club API responses
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
        // Club creation endpoint - requires POST method
        if ($method === 'POST') {
            require __DIR__ . '/create.php';
        } else {
            send_method_not_allowed('Club creation requires POST method. Use: POST /api/clubs/?action=create');
        }
        break;
        
    case 'list':
        // Club listing endpoint - requires GET method
        if ($method === 'GET') {
            require __DIR__ . '/list.php';
        } else {
            send_method_not_allowed('Club listing requires GET method. Use: GET /api/clubs/?action=list');
        }
        break;
        
    case 'details':
        // Club details endpoint - requires GET method
        if ($method === 'GET') {
            require __DIR__ . '/details.php';
        } else {
            send_method_not_allowed('Club details requires GET method. Use: GET /api/clubs/?action=details&id=<club_id>');
        }
        break;
        
    case 'update':
        // Club update endpoint - requires PATCH method
        if ($method === 'PATCH') {
            require __DIR__ . '/update.php';
        } else {
            send_method_not_allowed('Club updates require PATCH method. Use: PATCH /api/clubs/?action=update&id=<club_id>');
        }
        break;
        
    case 'delete':
        // Club deletion endpoint - requires DELETE method
        if ($method === 'DELETE') {
            require __DIR__ . '/delete.php';
        } else {
            send_method_not_allowed('Club deletion requires DELETE method. Use: DELETE /api/clubs/?action=delete&id=<club_id>');
        }
        break;
        
    case 'join':
        // Club membership management - requires POST method
        if ($method === 'POST') {
            require __DIR__ . '/join.php';
        } else {
            send_method_not_allowed('Club membership actions require POST method. Use: POST /api/clubs/?action=join');
        }
        break;
        
    case 'leave':
        // Club leave management - requires POST method
        if ($method === 'POST') {
            require __DIR__ . '/leave.php';
        } else {
            send_method_not_allowed('Club leave actions require POST method. Use: POST /api/clubs/?action=leave');
        }
        break;
        
    default:
        // Handle invalid or missing action parameters
        if ($method === 'GET') {
            // Suggest using the list action for GET requests without action
            send_error('Missing action parameter. Use: GET /api/clubs/?action=list to list all clubs', 400, [
                'available_actions' => ['list', 'details'],
                'suggestion' => 'Add ?action=list to see all clubs'
            ]);
        } else {
            // Handle non-GET requests with invalid actions
            send_error('Invalid or missing action parameter', 400, [
                'available_actions' => [
                    'POST' => ['create', 'join'],
                    'GET' => ['list', 'details'],
                    'PATCH' => ['update'],
                    'DELETE' => ['delete']
                ],
                'method_used' => $method
            ]);
        }
        break;
}