<?php
/**
 * USIU Events Management System - Events API Router
 * 
 * Central events endpoint router that handles all event-related operations
 * including event creation, listing, registration, updates, and management.
 * Provides comprehensive event lifecycle management with proper authentication
 * and authorization controls.
 * 
 * Features:
 * - Action-based routing for event operations
 * - Request validation and sanitization
 * - HTTP method enforcement for REST compliance
 * - Centralized error handling
 * - Security isolation for event endpoints
 * 
 * Security Features:
 * - Route access control with IS_EVENT_ROUTE constant
 * - Input validation and sanitization middleware
 * - HTTP method restrictions for each action
 * - Authentication requirements for protected operations
 * - CORS configuration for cross-origin requests
 * 
 * Supported Actions:
 * - create: Event creation (POST, authenticated)
 * - list: Event listing with filters and pagination (GET)
 * - details: Single event details (GET)
 * - update: Event modification (PATCH, authenticated)
 * - delete: Event removal (DELETE, authenticated)
 * - register: User registration for events (POST, authenticated)
 * - unregister: User unregistration from events (POST, authenticated)
 * - registered: List events user is registered for (GET, authenticated)
 * - created: List events created by user (GET, authenticated)
 * - history: User's event participation history (GET, authenticated)
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

// Security constant to ensure event endpoints are accessed properly
define('IS_EVENT_ROUTE', true);

// Core dependencies for event functionality
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Middleware for request processing and security
require_once __DIR__ . '/../../middleware/validate.php';
require_once __DIR__ . '/../../middleware/sanitize.php';
require_once __DIR__ . '/../../utils/response.php';

// Set JSON response header for all event endpoints
header('Content-Type: application/json');

// Get HTTP method for request validation and routing
$method = $_SERVER['REQUEST_METHOD'];

// Validate request format and decode JSON data for POST/PATCH requests
// This ensures proper request format and prevents malformed data
$requestData = validateRequest($method);

// Sanitize input data to prevent XSS and injection attacks
if ($requestData !== null) {
    $requestData = sanitizeInput($requestData);
}

// Route determination based on action parameter
// This allows single endpoint with multiple event operations
$action = $_GET['action'] ?? null;

// Event action router with comprehensive endpoint coverage and HTTP method enforcement
switch ($action) {
    case 'create':
        // Event creation endpoint (requires authentication)
        if ($method === 'POST') {
            require __DIR__ . '/create.php';
        } else {
            send_method_not_allowed('Only POST method is allowed for event creation');
        }
        break;
        
    case 'list':
        // Event listing with filters and pagination (public access)
        if ($method === 'GET') {
            require __DIR__ . '/list.php';
        } else {
            send_method_not_allowed('Only GET method is allowed for event listing');
        }
        break;
        
    case 'details':
        // Single event details retrieval (public access)
        if ($method === 'GET') {
            require __DIR__ . '/details.php';
        } else {
            send_method_not_allowed('Only GET method is allowed for event details');
        }
        break;
        
    case 'update':
        // Event modification endpoint (requires authentication and ownership)
        if ($method === 'PATCH') {
            require __DIR__ . '/update.php';
        } else {
            send_method_not_allowed('Only PATCH method is allowed for event updates');
        }
        break;
        
    case 'delete':
        // Event deletion endpoint (requires authentication and ownership)
        if ($method === 'DELETE') {
            require __DIR__ . '/delete.php';
        } else {
            send_method_not_allowed('Only DELETE method is allowed for event deletion');
        }
        break;
        
    case 'register':
        // User registration for events (requires authentication)
        if ($method === 'POST') {
            require __DIR__ . '/register.php';
        } else {
            send_method_not_allowed('Only POST method is allowed for event registration');
        }
        break;
        
    case 'unregister':
        // User unregistration from events (requires authentication)
        if ($method === 'POST') {
            require __DIR__ . '/unregister.php';
        } else {
            send_method_not_allowed('Only POST method is allowed for event unregistration');
        }
        break;
        
    case 'registered':
        // List events user is registered for (requires authentication)
        if ($method === 'GET') {
            require __DIR__ . '/registered.php';
        } else {
            send_method_not_allowed('Only GET method is allowed for registered events listing');
        }
        break;
        
    case 'created':
        // List events created by user (requires authentication)
        if ($method === 'GET') {
            require __DIR__ . '/created.php';
        } else {
            send_method_not_allowed('Only GET method is allowed for created events listing');
        }
        break;
        
    case 'history':
        // User's event participation history (requires authentication)
        if ($method === 'GET') {
            require __DIR__ . '/history.php';
        } else {
            send_method_not_allowed('Only GET method is allowed for event history');
        }
        break;
        
    default:
        // Handle invalid or missing action parameter
        if ($method === 'GET') {
            // Default to list action for GET requests without specific action
            require __DIR__ . '/list.php';
        } else {
            send_error('Invalid event action. Supported actions: create, list, details, update, delete, register, unregister, registered, created, history', 400);
        }
        break;
}