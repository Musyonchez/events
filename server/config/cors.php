<?php
/**
 * USIU Events Management System - CORS Configuration
 * 
 * Cross-Origin Resource Sharing (CORS) configuration file that enables
 * secure communication between the frontend client and backend API.
 * Sets appropriate headers to allow browser requests from the frontend domain.
 * 
 * CORS Features:
 * - Configurable origin allowance (development vs production)
 * - HTTP methods authorization for RESTful API
 * - Header specification for authentication and content type
 * - Preflight request handling for complex requests
 * 
 * Security Considerations:
 * - Origin validation based on environment configuration
 * - Limited HTTP methods to prevent unauthorized operations
 * - Specific headers to minimize attack surface
 * - Proper preflight response handling
 * 
 * Development vs Production:
 * - Development: May allow '*' for easier testing
 * - Production: Should specify exact frontend domain
 * - Environment-based configuration through .env
 * 
 * HTTP Methods Allowed:
 * - GET: Retrieve data from API endpoints
 * - POST: Create new resources (events, clubs, etc.)
 * - PUT: Update existing resources completely
 * - PATCH: Update existing resources partially
 * - DELETE: Remove resources from the system
 * - OPTIONS: Handle preflight requests
 * 
 * Headers Allowed:
 * - Content-Type: For JSON request bodies
 * - Authorization: For JWT token authentication
 * 
 * @author USIU Events Development Team
 * @version 1.0.0
 * @since 2024-01-01
 */

// Load application configuration to get frontend URL
require_once __DIR__ . '/config.php';

// Determine allowed origin based on environment configuration
// In development, this might be '*' for easier testing
// In production, this should be the specific frontend domain for security
$allowedOrigin = $config['frontend_url'] ?? '*';

// Set CORS headers for cross-origin communication

// Allow requests from the specified origin (frontend domain)
header("Access-Control-Allow-Origin: $allowedOrigin");

// Specify which HTTP methods are allowed for cross-origin requests
// These cover all RESTful operations needed by the frontend
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");

// Define which headers can be sent in cross-origin requests
// Content-Type: Required for JSON request bodies
// Authorization: Required for JWT token authentication
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS requests
// Browsers send OPTIONS requests before actual requests to verify CORS policy
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Return success status to allow the actual request to proceed
    http_response_code(200);
    exit();
}

