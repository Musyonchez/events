<?php
/**
 * USIU Events Management System - Input Sanitization Middleware
 * 
 * Provides security-focused input sanitization to prevent XSS attacks and
 * ensure data integrity across the USIU Events API. Recursively sanitizes
 * arrays and strings while preserving other data types.
 * 
 * Security Features:
 * - XSS prevention through HTML entity encoding
 * - Recursive array sanitization for nested data structures
 * - UTF-8 encoding support for international content
 * - Preservation of non-string data types
 * 
 * Usage:
 * - Apply to all user input before processing
 * - Use in API endpoints receiving form data
 * - Sanitize request bodies and query parameters
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

/**
 * Recursively sanitize input data to prevent XSS attacks
 * 
 * This function performs comprehensive input sanitization by converting
 * HTML special characters to entities, preventing script injection while
 * maintaining data integrity for legitimate content.
 * 
 * Sanitization Process:
 * - Converts HTML special characters to HTML entities
 * - Recursively processes nested arrays
 * - Preserves non-string data types unchanged
 * - Uses UTF-8 encoding for international support
 * 
 * Security Benefits:
 * - Prevents XSS attacks through script injection
 * - Neutralizes malicious HTML content
 * - Maintains data usability after sanitization
 * - Supports complex nested data structures
 * 
 * @param array $data Input data array to sanitize
 * @return array Sanitized data with HTML entities encoded
 * 
 * @example
 * // Sanitize user registration data
 * $userData = sanitizeInput($_POST);
 * 
 * // Sanitize API request body
 * $requestData = json_decode(file_get_contents('php://input'), true);
 * $sanitizedData = sanitizeInput($requestData);
 * 
 * @since 1.0.0
 */
function sanitizeInput(array $data): array
{
    $sanitizedData = [];
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            // Recursively sanitize nested arrays
            $sanitizedData[$key] = sanitizeInput($value);
        } elseif (is_string($value)) {
            // Convert HTML special characters to entities
            // ENT_QUOTES: Convert both single and double quotes
            // UTF-8: Support international characters
            $sanitizedData[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        } else {
            // Preserve non-string values (integers, booleans, etc.)
            $sanitizedData[$key] = $value;
        }
    }
    
    return $sanitizedData;
}
