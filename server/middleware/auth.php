<?php
/**
 * USIU Events Management System - Authentication Middleware
 * 
 * This middleware module provides comprehensive authentication and authorization
 * functionality for the USIU Events API. It handles JWT token validation,
 * user role verification, and request security enforcement across all protected endpoints.
 * 
 * Authentication Architecture Features:
 * - JWT-based stateless authentication using Bearer token format
 * - Multi-layer token validation with detailed error categorization
 * - Role-based access control (RBAC) for fine-grained permissions
 * - Global user context management for request processing
 * - Security-focused error handling with information protection
 * 
 * Security Implementation:
 * - Bearer token extraction from Authorization headers
 * - Cryptographic signature verification using HMAC-SHA256
 * - Token expiration and validity window enforcement
 * - Role-based permission checking for resource access
 * - Comprehensive audit logging for security monitoring
 * 
 * Authentication Flow:
 * 1. Extract Bearer token from Authorization header
 * 2. Validate token format and structure
 * 3. Verify cryptographic signature and claims
 * 4. Check token expiration and validity period
 * 5. Extract user context (ID, email, role)
 * 6. Store authenticated user data for request processing
 * 7. Enforce role-based access control if required
 * 
 * Authorization Levels:
 * - Public: No authentication required (registration, login)
 * - User: Basic authenticated user access (profile, events)
 * - Club Leader: Club management permissions (events, members)
 * - Admin: Full system administration capabilities
 * 
 * Error Handling Strategy:
 * - Specific error types for different authentication failures
 * - Detailed error context for client-side token refresh logic
 * - Security-conscious error messages to prevent information disclosure
 * - Comprehensive logging for security monitoring and incident response
 * 
 * Integration Points:
 * - All protected API endpoints use authenticate() function
 * - Role-specific endpoints use authorize() for permission checking
 * - User context accessed via getCurrentUser() throughout request processing
 * - Logging and monitoring systems track authentication events
 * 
 * Performance Considerations:
 * - Stateless design eliminates database lookups for each request
 * - Fast cryptographic operations for token validation
 * - Minimal memory footprint for user context storage
 * - Efficient role checking using array operations
 * 
 * Token Refresh Strategy:
 * - Client-side token refresh on expiration errors
 * - Graceful handling of token refresh workflows
 * - Seamless user experience with automatic re-authentication
 * - Security audit trails for token lifecycle events
 * 
 * Dependencies:
 * - JWT utility module for token validation logic
 * - Response utility module for standardized error responses
 * - Environment configuration for JWT secret management
 * - HTTP header functions for token extraction
 * 
 * @author USIU Events Development Team
 * @version 3.0.0
 * @since 2024-01-01
 */

// Import JWT validation utilities for token processing
require_once __DIR__ . '/../utils/jwt.php';

/**
 * Authenticate user request using JWT Bearer token
 * 
 * This function performs comprehensive JWT token authentication for API requests.
 * It extracts the Bearer token from Authorization headers, validates the token
 * cryptographically, and establishes user context for the current request.
 * 
 * Authentication Process:
 * 1. Extract Authorization header from HTTP request
 * 2. Parse Bearer token format and extract JWT
 * 3. Validate JWT signature and claims using secret key
 * 4. Handle different token validation error scenarios
 * 5. Store authenticated user context in global scope
 * 6. Return user data for request processing
 * 
 * Token Format Expected:
 * Authorization: Bearer <JWT_TOKEN>
 * 
 * Where JWT_TOKEN is a valid JWT with:
 * - Header: Algorithm and token type
 * - Payload: User claims (id, email, role) and standard claims (iss, aud, iat, exp)
 * - Signature: HMAC-SHA256 signature for integrity verification
 * 
 * Error Scenarios Handled:
 * - Missing Authorization header (401 Unauthorized)
 * - Invalid Bearer token format (401 Unauthorized)
 * - JWT signature verification failure (401 Unauthorized)
 * - Expired tokens (401 Unauthorized with refresh hint)
 * - Tokens not yet valid (401 Unauthorized)
 * - Malformed or corrupted tokens (401 Unauthorized)
 * - Server configuration errors (500 Internal Server Error)
 * 
 * Security Features:
 * - Constant-time token comparison to prevent timing attacks
 * - Detailed error categorization for appropriate client responses
 * - No sensitive information exposed in error messages
 * - Comprehensive audit logging for security monitoring
 * 
 * User Context Management:
 * - Authenticated user data stored in $GLOBALS['user'] for request scope
 * - User context includes: userId, email, role, and token claims
 * - Context accessible throughout request processing via getCurrentUser()
 * - Automatic cleanup at request completion
 * 
 * @return object Decoded JWT payload containing user identification and role information
 *                Structure: { userId: string, email: string, role: string }
 * 
 * @throws None Function handles all errors internally with appropriate HTTP responses
 * 
 * @example
 * // Protect an API endpoint with authentication
 * $user = authenticate();
 * 
 * // Access authenticated user data
 * $userId = $user->userId;
 * $userRole = $user->role;
 * $userEmail = $user->email;
 * 
 * // Process request with authenticated user context
 * processUserRequest($userId, $requestData);
 * 
 * @example
 * // Authentication middleware usage in API endpoints
 * <?php
 * require_once 'middleware/auth.php';
 * 
 * // Authenticate user for protected resource
 * $user = authenticate();
 * 
 * // Continue with authenticated request processing
 * $events = getUserEvents($user->userId);
 * send_success('Events retrieved successfully', 200, $events);
 * 
 * @since 1.0.0
 * @version 3.0.0 - Enhanced error handling and security features
 */
function authenticate() {
    // Extract HTTP headers using getallheaders() function
    // This function returns an associative array of all HTTP headers
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    // Validate presence of Authorization header
    if (empty($authHeader)) {
        // Return 401 Unauthorized for missing authentication
        http_response_code(401);
        echo json_encode([
            'error' => 'Authentication required. Please log in to access this resource.',
            'error_type' => 'missing_authorization_header'
        ]);
        exit;
    }

    // Parse Bearer token format from Authorization header
    // Expected format: "Bearer <JWT_TOKEN>"
    list($jwt) = sscanf($authHeader, 'Bearer %s');

    // Validate Bearer token format and extract JWT
    if (!$jwt) {
        // Return 401 Unauthorized for invalid token format
        http_response_code(401);
        echo json_encode([
            'error' => 'Invalid authentication format. Please log in again.',
            'error_type' => 'invalid_bearer_format'
        ]);
        exit;
    }

    // Retrieve JWT secret from environment configuration
    $jwtSecret = $_ENV['JWT_SECRET'];
    if (!$jwtSecret) {
        // Return 500 Internal Server Error for configuration issues
        error_log("Critical Error: JWT_SECRET not configured in environment");
        http_response_code(500);
        echo json_encode([
            'error' => 'Authentication service temporarily unavailable',
            'error_type' => 'server_configuration_error'
        ]);
        exit;
    }

    // Validate JWT token using cryptographic verification
    $decoded = validateJwt($jwt, $jwtSecret);

    // Handle token validation errors with specific error responses
    if (is_string($decoded)) {
        // validateJwt returns error strings for different failure scenarios
        switch ($decoded) {
            case 'expired':
                // Token has exceeded expiration time - client should refresh
                send_unauthorized('Access token expired', [
                    'error_type' => 'access_token_expired',
                    'action_required' => 'refresh_token'
                ]);
                break;
                
            case 'invalid_signature':
                // Token signature verification failed - potential security issue
                error_log("Security Alert: Invalid JWT signature from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                send_unauthorized('Invalid token signature', [
                    'error_type' => 'invalid_signature',
                    'action_required' => 'login_required'
                ]);
                break;
                
            case 'not_yet_valid':
                // Token used before its valid time - clock skew or replay attack
                error_log("Token used before valid time from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                send_unauthorized('Token not yet valid', [
                    'error_type' => 'not_yet_valid',
                    'action_required' => 'retry_later'
                ]);
                break;
                
            default:
                // Generic token validation failure
                send_unauthorized('Session expired or invalid. Please log in again.', [
                    'error_type' => 'invalid_token',
                    'action_required' => 'login_required'
                ]);
                break;
        }
    }

    // Token validation successful - extract and store user context
    // The decoded object contains user identification and role information
    $GLOBALS['user'] = $decoded->data;
    
    // Log successful authentication for security monitoring
    error_log("User authenticated successfully: " . $decoded->data->email . 
              " (Role: " . $decoded->data->role . ") from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // Return user data for immediate use in endpoint logic
    return $decoded->data;
}

/**
 * Authorize user access based on role-based access control (RBAC)
 * 
 * This function enforces role-based authorization for API endpoints that require
 * specific user roles or permissions. It checks the authenticated user's role
 * against the list of allowed roles for the current resource or operation.
 * 
 * Authorization Process:
 * 1. Verify user authentication has been performed
 * 2. Extract user role from authenticated user context
 * 3. Check if user role is included in allowed roles list
 * 4. Grant or deny access based on role verification
 * 5. Log authorization decisions for audit purposes
 * 
 * Role Hierarchy and Permissions:
 * - 'user': Basic authenticated user (default role)
 *   - Access: Profile management, event viewing, event registration
 *   - Restrictions: Cannot create events, manage clubs, access admin features
 * 
 * - 'club_leader': Club leadership role with management permissions
 *   - Access: All user permissions plus club event creation and management
 *   - Restrictions: Cannot access other clubs' data, limited admin features
 * 
 * - 'admin': Full administrative access to all system features
 *   - Access: All system features including user management, system configuration
 *   - Restrictions: None within the application scope
 * 
 * Common Authorization Patterns:
 * - Public endpoints: No authorization required
 * - User endpoints: authorize(['user', 'club_leader', 'admin'])
 * - Club management: authorize(['club_leader', 'admin'])
 * - Administrative: authorize(['admin'])
 * 
 * Error Handling:
 * - 401 Unauthorized: Authentication not performed before authorization
 * - 403 Forbidden: Authenticated but insufficient permissions
 * - Detailed error context for client understanding
 * - Security audit logging for access attempts
 * 
 * @param array $allowedRoles Array of role strings that can access the resource
 *                           Example: ['admin'], ['club_leader', 'admin'], ['user', 'club_leader', 'admin']
 * 
 * @return void Function either allows request to continue or terminates with error response
 * 
 * @throws None Function handles all authorization failures with appropriate HTTP responses
 * 
 * @example
 * // Admin-only endpoint
 * authenticate();
 * authorize(['admin']);
 * // Only admin users can reach this point
 * 
 * // Club leader and admin endpoint
 * authenticate();
 * authorize(['club_leader', 'admin']);
 * // Club leaders and admins can access this resource
 * 
 * // Multi-role endpoint
 * authenticate();
 * authorize(['user', 'club_leader', 'admin']);
 * // All authenticated users can access this resource
 * 
 * @example
 * // Event creation endpoint with role-based access
 * authenticate();
 * authorize(['club_leader', 'admin']);
 * 
 * $user = getCurrentUser();
 * if ($user->role === 'club_leader') {
 *     // Additional club leader specific validation
 *     validateClubLeaderPermissions($user->userId, $eventData['club_id']);
 * }
 * 
 * createEvent($eventData);
 * 
 * @since 1.0.0
 * @version 3.0.0 - Enhanced role validation and audit logging
 */
function authorize(array $allowedRoles) {
    // Verify that authentication has been performed
    if (!isset($GLOBALS['user'])) {
        // Authentication not performed - this is a programming error
        error_log("Authorization attempted without authentication");
        http_response_code(401);
        echo json_encode([
            'error' => 'Authentication required',
            'error_type' => 'authentication_not_performed'
        ]);
        exit;
    }

    // Extract user role from authenticated user context
    $userRole = $GLOBALS['user']->role;
    $userId = $GLOBALS['user']->userId;

    // Verify user role against allowed roles for this resource
    if (!in_array($userRole, $allowedRoles)) {
        // Log authorization failure for security monitoring
        error_log("Authorization denied: User {$userId} (role: {$userRole}) attempted access requiring roles: " . 
                 implode(', ', $allowedRoles) . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Return 403 Forbidden for insufficient permissions
        http_response_code(403);
        echo json_encode([
            'error' => 'Access forbidden: insufficient permissions',
            'error_type' => 'insufficient_role',
            'required_roles' => $allowedRoles,
            'user_role' => $userRole
        ]);
        exit;
    }

    // Log successful authorization for audit purposes
    error_log("Authorization granted: User {$userId} (role: {$userRole}) accessed resource requiring roles: " . 
             implode(', ', $allowedRoles));
}

/**
 * Get current authenticated user context
 * 
 * This utility function provides access to the authenticated user's context
 * throughout the request processing lifecycle. It returns the user data
 * that was stored during the authentication process.
 * 
 * User Context Structure:
 * The returned object contains the following properties:
 * - userId: MongoDB ObjectId string for user identification
 * - email: User's email address for communication and identification
 * - role: User's role for authorization decisions
 * 
 * Usage Patterns:
 * - Access user ID for database queries and operations
 * - Use email for audit logging and communication
 * - Check role for conditional business logic
 * - Validate user permissions for resource access
 * 
 * Error Handling:
 * - Returns null if authentication has not been performed
 * - Calling code should check for null before using user data
 * - Consider this a programming error if null in protected endpoints
 * 
 * @return object|null Authenticated user data or null if not authenticated
 *                    Object structure: { userId: string, email: string, role: string }
 * 
 * @example
 * // Access current user in endpoint logic
 * authenticate();
 * $user = getCurrentUser();
 * 
 * if ($user) {
 *     $userId = $user->userId;
 *     $userEmail = $user->email;
 *     $userRole = $user->role;
 *     
 *     // Use user context for business logic
 *     $userEvents = getEventsByUserId($userId);
 * }
 * 
 * @example
 * // Conditional logic based on user role
 * $user = getCurrentUser();
 * if ($user && $user->role === 'admin') {
 *     // Admin-specific functionality
 *     $allUsers = getAllUsers();
 * } else {
 *     // Regular user functionality
 *     $userProfile = getUserProfile($user->userId);
 * }
 * 
 * @since 1.0.0
 * @version 2.0.0 - Added comprehensive documentation
 */
function getCurrentUser() {
    // Return authenticated user context or null if not authenticated
    return isset($GLOBALS['user']) ? $GLOBALS['user'] : null;
}

/**
 * Future Authentication Enhancement Functions
 * 
 * The following functions are planned for implementation to provide
 * comprehensive authentication and authorization capabilities:
 * 
 * function authenticateOptional()
 * {
 *   // Optional authentication for endpoints that work with or without auth
 *   // Returns user context if authenticated, null otherwise
 *   // Used for personalized vs anonymous access patterns
 * }
 * 
 * function authorizeResource($resourceType, $resourceId, $requiredPermission)
 * {
 *   // Resource-specific authorization (e.g., club membership, event ownership)
 *   // Check if user has permission to access specific resource instances
 *   // Integrate with database to verify resource ownership/membership
 * }
 * 
 * function rateLimit($identifier, $maxRequests = 100, $windowSeconds = 3600)
 * {
 *   // Rate limiting middleware for API endpoints
 *   // Prevent abuse and protect against denial of service
 *   // Support per-user, per-IP, and per-endpoint rate limiting
 * }
 * 
 * function auditRequest($action, $resourceType = null, $resourceId = null)
 * {
 *   // Comprehensive audit logging for security and compliance
 *   // Track user actions, resource access, and security events
 *   // Integration with centralized logging and monitoring systems
 * }
 * 
 * function validateApiKey($apiKey)
 * {
 *   // API key authentication for service-to-service communication
 *   // Alternative authentication method for external integrations
 *   // Rate limiting and usage tracking for API key usage
 * }
 * 
 * Enhancement Guidelines:
 * - Maintain backward compatibility with existing authentication flow
 * - Follow consistent error handling and response patterns
 * - Implement comprehensive security logging and monitoring
 * - Support scalability for high-traffic scenarios
 * - Integrate with external identity providers (OAuth, SAML)
 * - Provide detailed audit trails for compliance requirements
 */
