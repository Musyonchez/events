<?php
/**
 * USIU Events Management System - Users API Router
 * 
 * Central routing endpoint for all user-related operations in the USIU Events
 * Management System. Handles HTTP method validation, request processing, and
 * delegates to specific action endpoints for user management functionality.
 * 
 * Supported Operations:
 * - GET: User details, listing, events, stats, and profile information
 * - POST: User creation and registration
 * - PATCH: User profile updates and modifications
 * - DELETE: User account deletion and cleanup
 * 
 * GET Action Routes:
 * - Default: Get user details (details.php)
 * - ?action=events: Get user's events (events.php)
 * - ?action=stats: Get user statistics (stats.php)
 * - ?action=list: List all users (list.php)
 * 
 * Features:
 * - RESTful API design with HTTP method-based routing
 * - Action-based GET request routing
 * - Request validation and data sanitization
 * - Centralized error handling and response formatting
 * - Security through route access control
 * - Comprehensive input processing
 * 
 * Security Features:
 * - Route access control (IS_USER_ROUTE definition)
 * - Request method validation
 * - Input sanitization for all requests
 * - CORS configuration for cross-origin requests
 * - Standardized error responses
 * 
 * Request Processing Flow:
 * 1. Define route access control
 * 2. Load core dependencies and middleware
 * 3. Validate HTTP method and request data
 * 4. Sanitize input data for security
 * 5. Route to appropriate endpoint based on method and action
 * 6. Handle errors with standardized responses
 * 
 * URL Format: /api/users/[?action=<action>&additional_params]
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Define route access control to prevent direct file access
define('IS_USER_ROUTE', true);

// Core system dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Middleware for request processing and security
require_once __DIR__ . '/../../middleware/validate.php';
require_once __DIR__ . '/../../middleware/sanitize.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header for all user API responses
header('Content-Type: application/json');

// Get HTTP method for request routing
$method = $_SERVER['REQUEST_METHOD'];

// Validate the request and get decoded data for POST/PATCH requests
$requestData = validateRequest($method);

// Sanitize input data if it exists to prevent security vulnerabilities
if ($requestData !== null) {
    $requestData = sanitizeInput($requestData);
}

// === HTTP Method-Based Routing with Action Support ===

switch ($method) {
    case 'GET':
        // GET requests support multiple actions through query parameters
        $action = $_GET['action'] ?? null;
        
        switch ($action) {
            case 'events':
                // User's events endpoint - lists events user is involved with
                require __DIR__ . '/events.php';
                break;
                
            case 'stats':
                // User statistics endpoint - provides user activity metrics
                require __DIR__ . '/stats.php';
                break;
                
            case 'list':
                // Users listing endpoint - lists all users with filtering
                require __DIR__ . '/list.php';
                break;
                
            case 'profile':
                // User profile endpoint - detailed profile information
                require __DIR__ . '/profile.php';
                break;
                
            default:
                // Default GET action - user details endpoint
                require __DIR__ . '/details.php';
                break;
        }
        break;
        
    case 'POST':
        // User creation endpoint - handles new user registration
        require __DIR__ . '/create.php';
        break;
        
    case 'PATCH':
        // User update endpoint - handles profile modifications
        require __DIR__ . '/update.php';
        break;
        
    case 'DELETE':
        // User deletion endpoint - handles account removal
        require __DIR__ . '/delete.php';
        break;
        
    default:
        // Handle unsupported HTTP methods
        send_method_not_allowed('Unsupported HTTP method. Supported methods: GET, POST, PATCH, DELETE', [
            'supported_methods' => ['GET', 'POST', 'PATCH', 'DELETE'],
            'get_actions' => ['events', 'stats', 'list', 'profile', 'default (details)'],
            'method_used' => $method
        ]);
        break;
}