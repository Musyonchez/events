<?php
/**
 * USIU Events Management System - Custom Exception Classes
 * 
 * This module defines custom exception classes for structured error handling
 * throughout the USIU Events system. These exceptions provide better error
 * categorization, debugging information, and client-friendly error responses.
 * 
 * Exception Architecture Features:
 * - Type-safe exception handling with specific error categories
 * - Structured error data for validation and business logic failures
 * - Integration with response utility functions for consistent API responses
 * - Enhanced debugging information for development and production monitoring
 * - Separation of system errors from user-facing error messages
 * 
 * Exception Hierarchy:
 * Exception (PHP built-in)
 * ├── ValidationException - Input validation failures
 * ├── AuthenticationException - Authentication-related errors (future)
 * ├── AuthorizationException - Permission-related errors (future)
 * ├── BusinessLogicException - Domain-specific business rule violations (future)
 * └── ExternalServiceException - Third-party service failures (future)
 * 
 * Error Handling Strategy:
 * 1. Catch specific exception types in controllers
 * 2. Extract error details and convert to appropriate HTTP responses
 * 3. Log detailed error information for debugging
 * 4. Return user-friendly error messages to clients
 * 5. Maintain error context for troubleshooting
 * 
 * Integration with Validation System:
 * ValidationException works seamlessly with:
 * - Input validation middleware
 * - Model validation methods
 * - Schema validation classes
 * - Form processing endpoints
 * 
 * Usage Patterns:
 * 
 * // Throwing validation exceptions
 * if (!$isValid) {
 *     throw new ValidationException([
 *         'email' => 'Valid email address is required',
 *         'password' => 'Password must be at least 8 characters'
 *     ], 'User input validation failed');
 * }
 * 
 * // Catching and handling exceptions
 * try {
 *     $user = new User($userData);
 *     $user->validate();
 * } catch (ValidationException $e) {
 *     send_validation_errors($e->getErrors());
 * }
 * 
 * Security Considerations:
 * - No sensitive information exposed in exception messages
 * - Error details sanitized before client transmission
 * - Detailed logging for security monitoring
 * - Consistent error responses prevent information disclosure
 * 
 * Performance Characteristics:
 * - Minimal overhead for exception creation
 * - Efficient error data storage and retrieval
 * - Fast error categorization for response routing
 * - Memory-efficient error context preservation
 * 
 * Development and Debugging:
 * - Rich error context for troubleshooting
 * - Stack trace preservation for debugging
 * - Integration with error logging systems
 * - IDE-friendly type hints and documentation
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

/**
 * Custom exception class for input validation failures
 * 
 * This exception class handles form validation errors, input sanitization failures,
 * and data validation problems throughout the application. It stores detailed
 * field-specific error information that can be easily converted to user-friendly
 * API responses.
 * 
 * Validation Error Structure:
 * The errors array contains field names as keys and error messages as values:
 * [
 *   'email' => 'Valid email address is required',
 *   'password' => 'Password must contain at least 8 characters',
 *   'age' => 'Age must be between 18 and 100'
 * ]
 * 
 * Common Use Cases:
 * - User registration form validation
 * - Event creation input validation
 * - Profile update data validation
 * - API request parameter validation
 * - File upload validation
 * 
 * Integration with Response System:
 * ValidationException integrates seamlessly with the response utility functions:
 * - Automatically converts to 422 Unprocessable Entity responses
 * - Provides structured error data for client-side form handling
 * - Maintains error context for debugging and logging
 * 
 * Error Message Guidelines:
 * - Clear, user-friendly language
 * - Specific field-level guidance
 * - Actionable instructions for correction
 * - Consistent formatting across the application
 * - No technical jargon or system internals
 * 
 * Security Features:
 * - No sensitive data in error messages
 * - Input sanitization for error content
 * - Prevents information disclosure attacks
 * - Safe error serialization for logging
 * 
 * @extends Exception PHP's built-in exception class
 * 
 * @example
 * // Creating and throwing validation exception
 * $errors = [
 *     'title' => 'Event title is required',
 *     'date' => 'Event date must be in the future',
 *     'capacity' => 'Capacity must be a positive number'
 * ];
 * throw new ValidationException($errors, 'Event validation failed');
 * 
 * // Handling validation exception
 * try {
 *     validateEventData($eventData);
 * } catch (ValidationException $e) {
 *     error_log('Validation error: ' . $e->getMessage());
 *     send_validation_errors($e->getErrors());
 * }
 * 
 * @since 1.0.0
 * @version 2.0.0 - Enhanced error handling and documentation
 */
class ValidationException extends Exception
{
  /**
   * Array of field-specific validation errors
   * 
   * This private property stores validation errors in a structured format
   * where array keys represent field names and values contain human-readable
   * error messages for each field.
   * 
   * Error Structure:
   * - Keys: Field names (string) matching form input names
   * - Values: Error messages (string) for user display
   * - Multiple errors per field can be concatenated or stored as arrays
   * 
   * @var array<string, string> Associative array of field errors
   */
  private array $errors;

  /**
   * ValidationException constructor
   * 
   * Creates a new validation exception with field-specific errors and an
   * optional general error message. The constructor stores the detailed
   * error information and calls the parent Exception constructor.
   * 
   * Constructor Parameters:
   * - $errors: Detailed field-level validation errors for client handling
   * - $message: General error message for logging and debugging
   * 
   * Error Processing:
   * 1. Store field-specific errors in private property
   * 2. Set general error message via parent constructor
   * 3. Preserve stack trace for debugging
   * 4. Prepare error data for response generation
   * 
   * Message Guidelines:
   * The general message should be:
   * - Descriptive but not overly technical
   * - Suitable for error logging
   * - Contextual to the validation scenario
   * - Safe for production environments
   * 
   * @param array $errors Associative array of field => error message pairs
   * @param string $message General error message for the exception
   * 
   * @example
   * // User registration validation
   * $validationErrors = [
   *     'email' => 'Email address is already registered',
   *     'password' => 'Password must contain uppercase, lowercase, and numbers',
   *     'student_id' => 'Student ID must be a valid USIU ID number'
   * ];
   * 
   * throw new ValidationException(
   *     $validationErrors,
   *     'User registration validation failed'
   * );
   * 
   * @since 1.0.0
   */
  public function __construct(array $errors, string $message = "Validation failed")
  {
    // Store field-specific validation errors for later retrieval
    $this->errors = $errors;
    
    // Call parent Exception constructor with general error message
    // This sets up the standard exception properties (message, code, file, line, trace)
    parent::__construct($message);
  }

  /**
   * Get detailed validation errors for client response
   * 
   * This method retrieves the field-specific validation errors stored during
   * exception construction. The returned array can be directly used with
   * response utility functions to create structured API error responses.
   * 
   * Return Value Structure:
   * The returned array maintains the original structure with field names
   * as keys and error messages as values, suitable for JSON serialization
   * and client-side error display.
   * 
   * Integration with Response System:
   * This method is typically used in exception handlers to extract error
   * details for API responses:
   * - send_validation_errors() function accepts this array directly
   * - Client applications can parse field-specific errors
   * - Frontend forms can display errors next to relevant inputs
   * 
   * Error Data Security:
   * - Error messages are safe for client transmission
   * - No sensitive system information included
   * - Field names match client-side form structure
   * - Messages provide actionable user guidance
   * 
   * @return array<string, string> Associative array of field validation errors
   * 
   * @example
   * // Exception handling in API endpoint
   * try {
   *     $event = new Event($eventData);
   *     $event->validate();
   *     $event->save();
   * } catch (ValidationException $e) {
   *     // Log the general error message
   *     error_log('Event validation failed: ' . $e->getMessage());
   *     
   *     // Send field-specific errors to client
   *     send_validation_errors($e->getErrors());
   * }
   * 
   * // Client receives structured error response:
   * // {
   * //   "error": "Validation failed",
   * //   "details": {
   * //     "title": "Event title is required",
   * //     "date": "Event date must be in the future"
   * //   }
   * // }
   * 
   * @since 1.0.0
   */
  public function getErrors(): array
  {
    // Return the stored validation errors for response generation
    return $this->errors;
  }
}

/**
 * Future Exception Classes
 * 
 * The following exception classes are planned for implementation to provide
 * comprehensive error handling across the entire USIU Events system:
 * 
 * class AuthenticationException extends Exception
 * {
 *   // Handle login failures, token validation errors, session expiration
 *   private string $errorType; // 'invalid_credentials', 'token_expired', etc.
 *   private ?string $userId;   // User context if available
 * }
 * 
 * class AuthorizationException extends Exception
 * {
 *   // Handle permission errors, role-based access violations
 *   private string $requiredRole;     // Required role for the operation
 *   private string $userRole;         // User's actual role
 *   private string $resource;         // Protected resource identifier
 * }
 * 
 * class BusinessLogicException extends Exception
 * {
 *   // Handle domain-specific business rule violations
 *   private string $businessRule;     // Violated business rule identifier
 *   private array $context;           // Additional context for the violation
 * }
 * 
 * class ExternalServiceException extends Exception
 * {
 *   // Handle third-party service failures (email, file storage, etc.)
 *   private string $serviceName;      // Name of the failing service
 *   private int $httpStatusCode;      // HTTP status from external service
 *   private bool $isRetryable;        // Whether the operation can be retried
 * }
 * 
 * Exception Implementation Guidelines:
 * - Extend PHP's built-in Exception class
 * - Store relevant context data as private properties
 * - Provide getter methods for accessing context data
 * - Include comprehensive PHPDoc documentation
 * - Follow consistent naming and structure patterns
 * - Integrate with existing response utility functions
 * - Support serialization for logging and monitoring
 * - Maintain security by avoiding sensitive data exposure
 */
