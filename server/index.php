<?php
/**
 * USIU Events Management System - API Entry Point
 * 
 * This file serves as the main entry point and router for the USIU Events API server.
 * It handles request routing, CORS configuration, and basic API infrastructure setup.
 * 
 * Features:
 * - Simple file-based routing for API endpoints
 * - CORS handling for cross-origin requests
 * - Environment configuration loading
 * - JSON response formatting
 * - OPTIONS preflight request handling
 * 
 * Architecture:
 * - Uses Composer autoloading for dependency management
 * - Loads environment variables via vlucas/phpdotenv
 * - Routes all /api/* requests to corresponding PHP files
 * - Provides fallback response for non-API requests
 * 
 * Security Features:
 * - CORS policy enforcement
 * - Path sanitization and validation
 * - JSON-only response format
 * - File existence validation before inclusion
 * 
 * Request Flow:
 * 1. Load dependencies and environment configuration
 * 2. Apply CORS headers for client communication
 * 3. Handle OPTIONS preflight requests
 * 4. Parse and sanitize request URI
 * 5. Route to appropriate API endpoint file
 * 6. Return 404 for non-existent endpoints
 * 
 * Directory Structure:
 * - /api/auth/    - Authentication endpoints
 * - /api/events/  - Event management endpoints
 * - /api/clubs/   - Club management endpoints
 * - /api/users/   - User management endpoints
 * - /api/comments/- Comment management endpoints
 * 
 * Environment Dependencies:
 * - PHP 8.0+ with MongoDB extension
 * - Composer dependencies (JWT, dotenv, MongoDB driver)
 * - .env file with database and configuration settings
 * - Web server with URL rewriting (Apache/Nginx)
 * 
 * @author USIU Events Development Team
 * @version 1.0.0
 * @since 2024-01-01
 */

// Autoload Composer dependencies
// This includes JWT libraries, MongoDB drivers, and other required packages
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env file
// Contains database credentials, JWT secrets, and configuration settings
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Apply CORS configuration for client-server communication
// Enables frontend JavaScript to make requests to this API
require_once __DIR__ . '/config/cors.php';

// Set JSON content type for all API responses
header('Content-Type: application/json');

// Handle preflight OPTIONS requests for CORS compliance
// Required for browsers to make cross-origin requests with custom headers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No content response
    exit;
}

// Extract request URI and HTTP method
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse and sanitize the request path
// Remove query parameters and normalize path format
$request = parse_url($request, PHP_URL_PATH);
$request = ltrim($request, '/');

// Route API requests to corresponding endpoint files
// Only process requests that start with 'api/' prefix
if (str_starts_with($request, 'api/')) {
    // Construct file path for the requested endpoint
    $path = __DIR__ . '/' . $request;

    // Verify endpoint file exists and include it
    if (file_exists($path)) {
        require $path;
        exit;
    } else {
        // Return 404 for non-existent API endpoints
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
        exit;
    }
}

// Fallback response for non-API requests (health check)
// Confirms the API server is running and accessible
echo json_encode([
    "status" => "success",
    "message" => "USIU Event API is running."
]);
