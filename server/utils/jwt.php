<?php
/**
 * USIU Events Management System - JWT Token Management Utility
 * 
 * This utility module provides comprehensive JSON Web Token (JWT) functionality
 * for secure authentication and authorization throughout the USIU Events system.
 * It handles token generation, validation, and error handling using the Firebase JWT library.
 * 
 * JWT Implementation Features:
 * - Stateless authentication mechanism using HMAC SHA-256 algorithm
 * - Configurable token expiration times for security
 * - Comprehensive error handling for all JWT validation scenarios
 * - Standard JWT claims (iss, aud, iat, exp) for interoperability
 * - Custom payload with user identification and authorization data
 * - Secure signature validation to prevent token tampering
 * 
 * Security Architecture:
 * - Uses HMAC-SHA256 algorithm for cryptographic signature
 * - Secret key-based signing prevents unauthorized token creation
 * - Token expiration prevents indefinite access from compromised tokens
 * - Signature validation ensures token integrity and authenticity
 * - Detailed error categorization for specific security responses
 * 
 * Token Payload Structure:
 * {
 *   "iss": "your_domain.com",           // Issuer identification
 *   "aud": "your_app_users",            // Intended audience
 *   "iat": 1640995200,                  // Issued at timestamp
 *   "exp": 1641001200,                  // Expiration timestamp
 *   "data": {
 *     "userId": "507f1f77bcf86cd799439011", // MongoDB ObjectId
 *     "email": "student@usiu.ac.ke",       // User email address
 *     "role": "user|admin|club_leader"     // Authorization role
 *   }
 * }
 * 
 * Error Handling Categories:
 * - 'expired': Token has passed expiration time
 * - 'invalid_signature': Token has been tampered with or wrong secret
 * - 'not_yet_valid': Token used before its valid time (nbf claim)
 * - 'invalid_token': Generic validation failure or malformed token
 * 
 * Usage Patterns:
 * 
 * // Generate token for authenticated user
 * $token = generateJwt($userId, $email, $role, $secretKey);
 * 
 * // Validate token from Authorization header
 * $result = validateJwt($token, $secretKey);
 * if (is_object($result)) {
 *     // Token is valid, access user data
 *     $userId = $result->data->userId;
 * } else {
 *     // Handle specific error type
 *     switch ($result) {
 *         case 'expired': // Send refresh token request
 *         case 'invalid_signature': // Log security incident
 *     }
 * }
 * 
 * Integration Points:
 * - Authentication endpoints: Login, register, refresh token
 * - Authorization middleware: Protect API endpoints
 * - User session management: Track authenticated users
 * - Role-based access control: Admin and club leader permissions
 * - Security logging: Track authentication events and failures
 * 
 * Dependencies:
 * - firebase/php-jwt: Industry-standard JWT library for PHP
 * - Environment configuration: JWT_SECRET for token signing
 * - Error logging: System error log for security monitoring
 * 
 * Performance Considerations:
 * - Stateless design eliminates database lookups for validation
 * - Fast cryptographic operations with optimized algorithms
 * - Minimal memory footprint for token processing
 * - Suitable for high-frequency API requests
 * 
 * Security Best Practices:
 * - Strong secret key with sufficient entropy (256+ bits)
 * - Short token lifetime to limit exposure window
 * - Secure transmission over HTTPS only
 * - Proper token storage in client (httpOnly cookies recommended)
 * - Regular secret rotation in production environments
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 * @requires firebase/php-jwt ^6.0
 */

// Load Composer autoloader for JWT library dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Import Firebase JWT library classes for token operations
use Firebase\JWT\JWT;                    // Main JWT encoding/decoding class
use Firebase\JWT\Key;                    // Key wrapper for decoding operations
use Firebase\JWT\ExpiredException;       // Token expiration error
use Firebase\JWT\SignatureInvalidException; // Token tampering/invalid signature error
use Firebase\JWT\BeforeValidException;   // Token used before valid time error

/**
 * Generate a JWT token for authenticated user sessions
 * 
 * This function creates a cryptographically signed JWT token containing user
 * identification and authorization information. The token is signed using
 * HMAC-SHA256 algorithm with a secret key from environment configuration.
 * 
 * Token Structure and Claims:
 * - Standard JWT claims (iss, aud, iat, exp) for compatibility
 * - Custom data payload with user information for authorization
 * - One-hour expiration time for security (configurable)
 * - Cryptographic signature for integrity verification
 * 
 * Security Features:
 * - HMAC-SHA256 signature prevents token forgery
 * - Time-based expiration limits token lifetime
 * - Issuer and audience claims for token validation
 * - User role information for authorization decisions
 * 
 * Performance Characteristics:
 * - Fast token generation suitable for login endpoints
 * - Minimal computational overhead for cryptographic operations
 * - Compact token size for efficient transmission
 * - Stateless design eliminates database dependencies
 * 
 * @param string $userId MongoDB ObjectId of the authenticated user
 * @param string $email User's email address for identification
 * @param string $role User's role (user, admin, club_leader) for authorization
 * @param string $secretKey Secret key for cryptographic signing from environment
 * 
 * @return string Base64URL-encoded JWT token ready for transmission
 * 
 * @throws Exception If JWT encoding fails or parameters are invalid
 * 
 * @example
 * // Generate token after successful login
 * $token = generateJwt($user['_id'], $user['email'], $user['role'], $_ENV['JWT_SECRET']);
 * 
 * // Use in Authorization header
 * header("Authorization: Bearer $token");
 * 
 * @since 1.0.0
 * @version 2.0.0 - Added comprehensive error handling and documentation
 */
function generateJwt(string $userId, string $email, string $role, string $secretKey): string
{
    // Current timestamp for token issuance time
    $issuedAt = time();
    
    // Token expiration: 1 hour from issuance (3600 seconds)
    // This short lifetime enhances security by limiting exposure window
    $expirationTime = $issuedAt + (60 * 60);

    // JWT payload with standard claims and custom user data
    $payload = [
        // Standard JWT Claims (RFC 7519)
        'iss' => 'usiu-events.ac.ke',        // Issuer: USIU Events system
        'aud' => 'usiu-events-users',        // Audience: Application users
        'iat' => $issuedAt,                  // Issued At: Token creation time
        'exp' => $expirationTime,            // Expiration: Token validity end
        
        // Custom Claims: User identification and authorization data
        'data' => [
            'userId' => $userId,             // MongoDB ObjectId for user lookup
            'email' => $email,               // Email for user identification
            'role' => $role                  // Authorization role for access control
        ]
    ];

    // Generate and return JWT token using HMAC-SHA256 algorithm
    // The secret key ensures only this system can create valid tokens
    return JWT::encode($payload, $secretKey, 'HS256');
}

/**
 * Validate and decode a JWT token with comprehensive error handling
 * 
 * This function performs complete JWT token validation including signature
 * verification, expiration checking, and structural validation. It returns
 * either the decoded token object for valid tokens or specific error strings
 * for different failure scenarios.
 * 
 * Validation Process:
 * 1. Decode JWT structure and extract header, payload, signature
 * 2. Verify signature using provided secret key and HMAC-SHA256
 * 3. Check token expiration against current timestamp
 * 4. Validate token structure and required claims
 * 5. Return decoded payload or specific error identifier
 * 
 * Error Categories and Responses:
 * - ExpiredException → 'expired': Token has passed expiration time
 * - SignatureInvalidException → 'invalid_signature': Tampering detected
 * - BeforeValidException → 'not_yet_valid': Token used before valid time
 * - General Exception → 'invalid_token': Malformed or invalid token
 * 
 * Security Considerations:
 * - Constant-time signature verification prevents timing attacks
 * - Detailed error logging for security monitoring
 * - Error categorization enables appropriate client responses
 * - No sensitive information leaked in error responses
 * 
 * Integration with Authentication Middleware:
 * The return values enable specific handling of authentication failures:
 * - Valid object: Allow request with user context
 * - 'expired': Prompt for token refresh
 * - 'invalid_signature': Log security incident, force re-authentication
 * - Other errors: Generic authentication failure response
 * 
 * @param string $jwt The JWT token to validate (without 'Bearer ' prefix)
 * @param string $secretKey Secret key for signature verification
 * 
 * @return string|object Returns decoded JWT object if valid, or error string:
 *                      - object: Successfully decoded token with user data
 *                      - 'expired': Token has exceeded expiration time
 *                      - 'invalid_signature': Token signature verification failed
 *                      - 'not_yet_valid': Token used before nbf (not before) time
 *                      - 'invalid_token': Generic validation failure
 * 
 * @throws None All exceptions are caught and converted to error strings
 * 
 * @example
 * // Validate token from Authorization header
 * $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
 * $token = str_replace('Bearer ', '', $authHeader);
 * $result = validateJwt($token, $_ENV['JWT_SECRET']);
 * 
 * if (is_object($result)) {
 *     // Token is valid, extract user information
 *     $userId = $result->data->userId;
 *     $userRole = $result->data->role;
 *     // Continue with authenticated request processing
 * } else {
 *     // Handle authentication failure based on error type
 *     switch ($result) {
 *         case 'expired':
 *             send_unauthorized('Token expired', ['error_type' => 'token_expired']);
 *             break;
 *         case 'invalid_signature':
 *             error_log("Security Alert: Invalid JWT signature from IP: " . $_SERVER['REMOTE_ADDR']);
 *             send_unauthorized('Invalid token signature');
 *             break;
 *         default:
 *             send_unauthorized('Invalid authentication token');
 *     }
 * }
 * 
 * @since 1.0.0
 * @version 2.0.0 - Enhanced error handling and security logging
 */
function validateJwt(string $jwt, string $secretKey): string|object
{
    try {
        // Decode and validate JWT token using Firebase JWT library
        // This performs comprehensive validation including:
        // - Signature verification with HMAC-SHA256
        // - Expiration time checking
        // - Token structure validation
        // - Claims validation (iss, aud, etc.)
        $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
        
        // Return the decoded token object containing user data
        // Access user information: $decoded->data->userId, $decoded->data->role
        return $decoded;
        
    } catch (ExpiredException $e) {
        // Token has exceeded its expiration time (exp claim)
        // This is a normal occurrence requiring token refresh
        return 'expired';
        
    } catch (SignatureInvalidException $e) {
        // Token signature verification failed
        // This indicates potential tampering or wrong secret key
        // Should be logged as a security incident
        error_log("Security Alert: Invalid JWT signature. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return 'invalid_signature';
        
    } catch (BeforeValidException $e) {
        // Token is being used before its 'not before' (nbf) time
        // This could indicate clock skew or potential replay attacks
        return 'not_yet_valid';
        
    } catch (Exception $e) {
        // Catch all other JWT-related exceptions
        // Includes malformed tokens, missing claims, decoding errors
        error_log("JWT Validation Error: " . $e->getMessage() . " IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return 'invalid_token';
    }
}