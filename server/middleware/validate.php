<?php
/**
 * USIU Events Management System - Request Validation Middleware
 * 
 * Provides HTTP request validation for the USIU Events API including
 * content type validation, JSON parsing, and required parameter checking.
 * Ensures all API requests meet expected format and structure requirements.
 * 
 * Validation Features:
 * - HTTP method-specific validation rules
 * - JSON content type and format validation
 * - Required parameter checking for operations
 * - Standardized error responses for validation failures
 * 
 * Request Requirements:
 * - POST/PATCH: Require application/json content type and valid JSON body
 * - PATCH/DELETE: Require 'id' parameter for resource identification
 * - GET: No specific validation requirements
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

/**
 * Validate HTTP request based on method and requirements
 * 
 * This function performs method-specific request validation to ensure
 * API requests meet the expected format and contain required data.
 * 
 * Validation Rules by Method:
 * - POST/PATCH: Must have JSON content type and valid JSON body
 * - PATCH/DELETE: Must include 'id' parameter in query string
 * - GET: No specific validation (passes through)
 * 
 * Error Responses:
 * - 415 Unsupported Media Type: Wrong content type for JSON endpoints
 * - 400 Bad Request: Invalid JSON or missing required parameters
 * 
 * @param string $method HTTP method (GET, POST, PATCH, DELETE)
 * @return array|null Decoded JSON data for POST/PATCH, null for others
 * 
 * @example
 * // Validate and get request data
 * $requestData = validateRequest($_SERVER['REQUEST_METHOD']);
 * 
 * // Use in API endpoints
 * switch ($_SERVER['REQUEST_METHOD']) {
 *     case 'POST':
 *         $data = validateRequest('POST');
 *         createResource($data);
 *         break;
 * }
 * 
 * @since 1.0.0
 */
function validateRequest($method) {
    // Validate JSON content type and body for data modification requests
    if ($method === 'POST' || $method === 'PATCH') {
        // Check Content-Type header for JSON
        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
            http_response_code(415); // Unsupported Media Type
            echo json_encode(['error' => 'Content-Type must be application/json']);
            exit;
        }

        // Read and decode JSON request body
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Validate JSON parsing success
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid JSON body',
                'json_error' => json_last_error_msg()
            ]);
            exit;
        }

        // Return decoded data for use in endpoint logic
        return $data;
    }

    // Validate required ID parameter for resource-specific operations
    if ($method === 'PATCH' || $method === 'DELETE') {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Resource ID is required']);
            exit;
        }
    }

    // No validation required for GET requests or other methods
    return null;
}
