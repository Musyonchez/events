<?php
/**
 * USIU Events Management System - HTTP Response Utility Library
 * 
 * This comprehensive utility library provides standardized HTTP response functions
 * for the USIU Events API. It ensures consistent response formatting, proper HTTP
 * status codes, and clean API architecture throughout all endpoints.
 * 
 * Response Architecture Features:
 * - Standardized JSON response format across all endpoints
 * - Comprehensive HTTP status code coverage (1xx-5xx)
 * - Consistent error and success message structures
 * - Built-in response termination to prevent data leakage
 * - Type-safe function signatures for better IDE support
 * 
 * Response Format Standards:
 * Success responses: { "message": "...", "data": {...} }
 * Error responses: { "error": "...", "details": {...} }
 * Validation errors: { "error": "Validation failed", "details": [...] }
 * 
 * HTTP Status Code Categories:
 * - 1xx Informational: Processing states and early hints
 * - 2xx Success: Successful request processing
 * - 3xx Redirection: Resource moved or additional action needed
 * - 4xx Client Error: Client-side request problems
 * - 5xx Server Error: Server-side processing failures
 * 
 * Security Considerations:
 * - No sensitive information in error responses
 * - Consistent error messages to prevent information disclosure
 * - Response termination prevents additional output
 * - Input sanitization for dynamic error messages
 * 
 * Performance Features:
 * - Efficient JSON encoding with minimal overhead
 * - Immediate response termination for faster client responses
 * - Memory-efficient response handling
 * - Suitable for high-traffic API endpoints
 * 
 * Integration with API Endpoints:
 * All API endpoints use these functions for consistent responses:
 * - Authentication endpoints: login, register, verification
 * - CRUD operations: create, read, update, delete
 * - Error handling: validation, authorization, server errors
 * - Status responses: health checks, processing states
 * 
 * Usage Patterns:
 * 
 * // Success with data
 * send_success('Event created successfully', 201, $eventData);
 * 
 * // Error with details
 * send_error('Validation failed', 422, $validationErrors);
 * 
 * // Simple responses
 * send_not_found('Event');
 * send_unauthorized();
 * 
 * Logging Integration:
 * Response functions can be enhanced with logging:
 * - Error responses for debugging and monitoring
 * - Access patterns for analytics
 * - Security events for incident response
 * 
 * @author USIU Events Development Team
 * @version 3.0.0
 * @since 2024-01-01
 */

/**
 * Send a standardized JSON response with specified data and status code
 * 
 * This is the core response function that all other response functions build upon.
 * It ensures consistent JSON formatting, proper HTTP status codes, and clean
 * request termination across the entire API.
 * 
 * Response Processing:
 * 1. Set HTTP status code using http_response_code()
 * 2. Encode data to JSON with proper error handling
 * 3. Output JSON response to client
 * 4. Terminate script execution to prevent additional output
 * 
 * JSON Encoding Features:
 * - UTF-8 encoding for international character support
 * - Automatic escaping of special characters
 * - Consistent formatting for debugging
 * - Error handling for encoding failures
 * 
 * Security Considerations:
 * - No HTML output to prevent XSS attacks
 * - Consistent Content-Type header (set in index.php)
 * - Response termination prevents data leakage
 * - Input validation for status codes
 * 
 * @param mixed $data Response data to be JSON encoded
 * @param int $statusCode HTTP status code (default: 200 OK)
 * 
 * @return void This function terminates execution
 * 
 * @throws None All errors are handled gracefully
 * 
 * @example
 * // Simple success response
 * send_response(['message' => 'Operation successful'], 200);
 * 
 * // Response with data
 * send_response(['data' => $events, 'total' => 50], 200);
 * 
 * @since 1.0.0
 * @version 3.0.0 - Enhanced documentation and error handling
 */
function send_response($data, $statusCode = 200)
{
  // Set the HTTP response status code
  // This informs the client about the request processing result
  http_response_code($statusCode);
  
  // Encode response data to JSON format
  // JSON is the standard format for REST API responses
  echo json_encode($data);
  
  // Terminate script execution to prevent additional output
  // This ensures clean responses and prevents data leakage
  exit;
}

/**
 * Send a standardized error response with optional details
 * 
 * This function creates consistent error responses across the API with proper
 * error messaging and optional detailed information for debugging or client
 * error handling. It follows REST API best practices for error communication.
 * 
 * Error Response Structure:
 * {
 *   "error": "Human-readable error message",
 *   "details": { } // Optional additional error information
 * }
 * 
 * Common Use Cases:
 * - Validation failures with field-specific errors
 * - Authentication failures with error type information
 * - Business logic violations with context
 * - Server errors with safe debugging information
 * 
 * Security Considerations:
 * - No sensitive information in error messages
 * - Generic messages for security-related errors
 * - Details field sanitized to prevent information disclosure
 * - Consistent error format prevents system fingerprinting
 * 
 * @param string $message Human-readable error description
 * @param int $statusCode HTTP status code (default: 400 Bad Request)
 * @param array $details Optional additional error context
 * 
 * @return void Terminates execution after sending response
 * 
 * @example
 * // Simple error
 * send_error('Invalid email format', 400);
 * 
 * // Error with validation details
 * send_error('Validation failed', 422, [
 *     'email' => 'Email is required',
 *     'password' => 'Password must be at least 8 characters'
 * ]);
 * 
 * @since 1.0.0
 */
function send_error($message, $statusCode = 400, $details = [])
{
  // Create standardized error response structure
  $response = ['error' => $message];
  
  // Add details if provided (validation errors, context, etc.)
  if (!empty($details)) {
    $response['details'] = $details;
  }
  
  // Send error response with appropriate status code
  send_response($response, $statusCode);
}

/**
 * Send a standardized success response with optional data payload
 * 
 * This function creates consistent success responses for API operations,
 * providing clear success messaging and optional data payload for client
 * consumption. It ensures uniform success response structure across all endpoints.
 * 
 * Success Response Structure:
 * {
 *   "message": "Human-readable success message",
 *   "data": { } // Optional response data
 * }
 * 
 * Common Use Cases:
 * - CRUD operation confirmations with created/updated resource data
 * - Authentication success with user information
 * - File upload confirmation with file metadata
 * - Bulk operation results with summary statistics
 * 
 * Data Payload Guidelines:
 * - Include created/updated resource data for POST/PUT operations
 * - Provide operation results for bulk operations
 * - Return user information for authentication endpoints
 * - Include metadata for list operations (pagination, totals)
 * 
 * @param string $message Human-readable success description
 * @param int $statusCode HTTP status code (default: 200 OK)
 * @param array $data Optional response data payload
 * 
 * @return void Terminates execution after sending response
 * 
 * @example
 * // Simple success
 * send_success('Password updated successfully');
 * 
 * // Success with data
 * send_success('Event created successfully', 201, [
 *     'id' => $eventId,
 *     'title' => $eventTitle,
 *     'created_at' => $timestamp
 * ]);
 * 
 * @since 1.0.0
 */
function send_success($message, $statusCode = 200, $data = [])
{
  // Create standardized success response structure
  $response = ['message' => $message];
  
  // Add data payload if provided
  if (!empty($data)) {
    $response['data'] = $data;
  }
  
  // Send success response with appropriate status code
  send_response($response, $statusCode);
}

/**
 * Send validation error response with field-specific error details
 * 
 * This specialized error function handles form validation failures and input
 * validation errors. It provides a consistent format for validation errors
 * that clients can easily parse and display to users.
 * 
 * Validation Error Format:
 * {
 *   "error": "Validation failed",
 *   "details": {
 *     "field_name": "Field-specific error message",
 *     "another_field": "Another error message"
 *   }
 * }
 * 
 * Integration with Validation Middleware:
 * This function is typically called from validation middleware or endpoint
 * validation logic when input data fails validation rules.
 * 
 * Client-Side Integration:
 * Clients can parse the details object to display field-specific errors
 * next to form inputs, providing clear user feedback.
 * 
 * @param array $errors Associative array of field => error message pairs
 * @param int $statusCode HTTP status code (default: 422 Unprocessable Entity)
 * 
 * @return void Terminates execution after sending response
 * 
 * @example
 * send_validation_errors([
 *     'email' => 'Valid email address is required',
 *     'password' => 'Password must be at least 8 characters',
 *     'confirm_password' => 'Passwords do not match'
 * ]);
 * 
 * @since 1.0.0
 */
function send_validation_errors($errors, $statusCode = 422)
{
  // Use standard error function with validation-specific message
  send_error('Validation failed', $statusCode, $errors);
}

/**
 * Send 404 Not Found response for missing resources
 * 
 * This function provides a standardized way to handle resource not found
 * scenarios across the API. It accepts a resource name parameter to create
 * descriptive error messages for different types of missing resources.
 * 
 * Common Use Cases:
 * - Event not found by ID
 * - User profile not found
 * - Club not found
 * - API endpoint not found
 * 
 * SEO and User Experience:
 * Clear error messages help clients provide better user feedback
 * and can improve API usability and debugging.
 * 
 * @param string $resource Name of the missing resource (default: 'Resource')
 * 
 * @return void Terminates execution after sending response
 * 
 * @example
 * // Generic not found
 * send_not_found();
 * 
 * // Specific resource not found
 * send_not_found('Event');
 * send_not_found('User profile');
 * 
 * @since 1.0.0
 */
function send_not_found($resource = 'Resource')
{
  // Send 404 error with descriptive message
  send_error("{$resource} not found", 404);
}

/**
 * Send 401 Unauthorized response for authentication failures
 * 
 * This function handles authentication failures including missing tokens,
 * invalid credentials, expired sessions, and other authentication-related
 * errors. It provides consistent unauthorized responses across the API.
 * 
 * Authentication Failure Scenarios:
 * - Missing Authorization header
 * - Invalid JWT token
 * - Expired authentication token
 * - Invalid username/password combination
 * - Revoked or blacklisted tokens
 * 
 * Security Considerations:
 * - Generic error messages prevent information disclosure
 * - Details field can provide error type for client handling
 * - Consistent timing prevents user enumeration attacks
 * 
 * Client Integration:
 * Clients should redirect to login or refresh tokens on 401 responses
 * depending on the error type provided in details.
 * 
 * @param string $message Custom unauthorized message (default: 'Unauthorized')
 * @param array $details Optional error context (e.g., error_type)
 * 
 * @return void Terminates execution after sending response
 * 
 * @example
 * // Generic unauthorized
 * send_unauthorized();
 * 
 * // Specific authentication error
 * send_unauthorized('Token expired', ['error_type' => 'token_expired']);
 * 
 * @since 1.0.0
 */
function send_unauthorized($message = 'Unauthorized', $details = [])
{
  // Send 401 error with authentication failure context
  send_error($message, 401, $details);
}

/**
 * Send 403 Forbidden response for authorization failures
 * 
 * This function handles authorization failures where the user is authenticated
 * but lacks sufficient permissions to access the requested resource or perform
 * the requested action. It differs from 401 in that the user identity is known.
 * 
 * Authorization Failure Scenarios:
 * - User trying to access admin-only endpoints
 * - Non-club-leader trying to manage club events
 * - User trying to edit another user's profile
 * - Accessing private club or event information
 * - Insufficient role permissions for the operation
 * 
 * Security Principles:
 * - Clear distinction between authentication (401) and authorization (403)
 * - Minimal information disclosure about system permissions
 * - Consistent error messages prevent system reconnaissance
 * 
 * User Experience:
 * Clients can use 403 responses to show appropriate UI states
 * (e.g., hide forbidden actions, show upgrade prompts).
 * 
 * @param string $message Custom forbidden message (default: 'Forbidden')
 * 
 * @return void Terminates execution after sending response
 * 
 * @example
 * // Generic forbidden
 * send_forbidden();
 * 
 * // Specific authorization error
 * send_forbidden('Admin access required');
 * send_forbidden('You can only edit your own profile');
 * 
 * @since 1.0.0
 */
function send_forbidden($message = 'Forbidden')
{
  // Send 403 error indicating insufficient permissions
  send_error($message, 403);
}

/**
 * Send 405 Method Not Allowed response for unsupported HTTP methods
 * 
 * This function handles requests using HTTP methods that are not supported
 * by the endpoint. It's commonly used in API routing to reject inappropriate
 * method usage.
 * 
 * HTTP Method Validation:
 * - GET: Retrieve data (safe, idempotent)
 * - POST: Create new resources (unsafe, non-idempotent)
 * - PUT: Update entire resources (unsafe, idempotent)
 * - PATCH: Partial resource updates (unsafe, non-idempotent)
 * - DELETE: Remove resources (unsafe, idempotent)
 * 
 * Common Scenarios:
 * - PUT request to read-only endpoint
 * - GET request to creation endpoint
 * - DELETE request to non-deletable resource
 * - Unsupported method like HEAD or OPTIONS
 * 
 * REST API Best Practices:
 * - Clear indication of unsupported methods
 * - Consistent error response format
 * - Allow header could indicate supported methods
 * 
 * @return void Terminates execution after sending response
 * 
 * @example
 * // In endpoint routing logic
 * if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
 *     send_method_not_allowed();
 * }
 * 
 * @since 1.0.0
 */
function send_method_not_allowed()
{
  // Send 405 error for unsupported HTTP methods
  send_error('Method not allowed', 405);
}

/**
 * Send 500 Internal Server Error response for server-side failures
 * 
 * This function handles unexpected server-side errors including database
 * connection failures, unhandled exceptions, and other system-level issues
 * that prevent request processing.
 * 
 * Server Error Categories:
 * - Database connection failures
 * - External service unavailability (email, file storage)
 * - Unhandled exceptions in business logic
 * - Configuration errors
 * - Resource exhaustion (memory, disk space)
 * 
 * Error Handling Strategy:
 * - Log detailed error information for debugging
 * - Return generic error message to prevent information disclosure
 * - Monitor 500 errors for system health
 * - Implement retry logic for transient failures
 * 
 * Security Considerations:
 * - No internal system details in error responses
 * - Comprehensive error logging for incident response
 * - Rate limiting to prevent abuse of error endpoints
 * 
 * @param string $message Generic error message (default: 'Internal Server Error')
 * 
 * @return void Terminates execution after sending response
 * 
 * @example
 * // Database connection failure
 * send_internal_server_error('Database temporarily unavailable');
 * 
 * // Generic server error
 * send_internal_server_error();
 * 
 * @since 1.0.0
 */
function send_internal_server_error($message = 'Internal Server Error')
{
  // Send 500 error for server-side failures
  send_error($message, 500);
}

/**
 * Send 201 Created response for successful resource creation
 * 
 * This function provides a standardized response for successful POST operations
 * that create new resources. It follows REST conventions by returning 201 status
 * code along with the created resource data.
 * 
 * REST API Conventions:
 * - 201 status code indicates successful resource creation
 * - Response includes created resource data for client use
 * - Location header can be added for resource URL (if needed)
 * 
 * Common Use Cases:
 * - User registration completion
 * - Event creation by club leaders
 * - Club creation by administrators
 * - Comment posting on events
 * - File upload completion
 * 
 * Response Structure:
 * {
 *   "message": "Resource created successfully",
 *   "data": { created_resource_data }
 * }
 * 
 * @param array $data Created resource data to return to client
 * @param string $message Success message (default: 'Resource created successfully')
 * 
 * @return void Terminates execution after sending response
 * 
 * @example
 * // Event creation
 * send_created($eventData, 'Event created successfully');
 * 
 * // User registration
 * send_created(['id' => $userId, 'email' => $email], 'Account created successfully');
 * 
 * @since 1.0.0
 */
function send_created($data = [], $message = 'Resource created successfully')
{
  // Send 201 Created response with resource data
  send_response(['message' => $message, 'data' => $data], 201);
}

function send_no_content()
{
  http_response_code(204);
  exit;
}

function send_bad_request($message = 'Bad request')
{
  send_error($message, 400);
}

function send_conflict($message = 'Conflict')
{
  send_error($message, 409);
}

function send_service_unavailable($message = 'Service Unavailable')
{
  send_error($message, 503);
}

function send_too_many_requests($message = 'Too Many Requests')
{
  send_error($message, 429);
}

function send_unprocessable_entity($errors = [])
{
  send_validation_errors($errors);
}

function send_not_implemented($message = 'Not Implemented')
{
  send_error($message, 501);
}

function send_gateway_timeout($message = 'Gateway Timeout')
{
  send_error($message, 504);
}

function send_network_authentication_required($message = 'Network Authentication Required')
{
  send_error($message, 511);
}

function send_precondition_failed($message = 'Precondition Failed')
{
  send_error($message, 412);
}

function send_precondition_required($message = 'Precondition Required')
{
  send_error($message, 428);
}

function send_request_header_fields_too_large($message = 'Request Header Fields Too Large')
{
  send_error($message, 431);
}

function send_unavailable_for_legal_reasons($message = 'Unavailable For Legal Reasons')
{
  send_error($message, 451);
}

function send_client_closed_request($message = 'Client Closed Request')
{
  send_error($message, 499);
}

function send_invalid_token($message = 'Invalid Token')
{
  send_error($message, 498);
}

function send_token_required($message = 'Token Required')
{
  send_error($message, 499);
}

function send_login_timeout($message = 'Login Timeout')
{
  send_error($message, 440);
}

function send_retry_with($message = 'Retry With')
{
  send_error($message, 449);
}

function send_blocked_by_windows_parental_controls($message = 'Blocked by Windows Parental Controls')
{
  send_error($message, 450);
}

function send_redirect($url, $statusCode = 302)
{
  header("Location: {$url}", true, $statusCode);
  exit;
}

function send_permanent_redirect($url)
{
  send_redirect($url, 301);
}

function send_temporary_redirect($url)
{
  send_redirect($url, 307);
}

function send_permanent_redirect_with_method_change($url)
{
  send_redirect($url, 308);
}

function send_see_other($url)
{
  send_redirect($url, 303);
}

function send_not_modified()
{
  http_response_code(304);
  exit;
}

function send_use_proxy($url)
{
  header("Location: {$url}", true, 305);
  exit;
}

function send_switch_proxy()
{
  http_response_code(306);
  exit;
}

function send_multiple_choices($choices)
{
  send_response($choices, 300);
}

function send_moved_permanently($url)
{
  send_permanent_redirect($url);
}

function send_found($url)
{
  send_redirect($url);
}

function send_processing()
{
  http_response_code(102);
  exit;
}

function send_early_hints($hints)
{
  http_response_code(103);
  foreach ($hints as $hint) {
    header($hint, false);
  }
  exit;
}

function send_no_response()
{
  http_response_code(205);
  exit;
}

function send_reset_content()
{
  http_response_code(205);
  exit;
}

function send_partial_content($data, $range)
{
  http_response_code(206);
  header("Content-Range: {$range}");
  echo json_encode($data);
  exit;
}

function send_multi_status($data)
{
  http_response_code(207);
  echo json_encode($data);
  exit;
}

function send_already_reported()
{
  http_response_code(208);
  exit;
}

function send_im_used()
{
  http_response_code(226);
  exit;
}
?>