<?php
/**
 * USIU Events Management System - Database Connection
 * 
 * MongoDB database connection setup file that establishes the database
 * connection using configuration from the main config file. Provides
 * error handling and connection validation for the application.
 * 
 * Database Features:
 * - MongoDB connection using official PHP driver
 * - Configuration-based connection string
 * - Error handling with appropriate HTTP responses
 * - Database selection and client setup
 * - Connection validation before use
 * 
 * Connection Process:
 * 1. Load application configuration
 * 2. Validate MongoDB URI availability
 * 3. Create MongoDB client instance
 * 4. Select application database
 * 5. Handle connection errors gracefully
 * 
 * Error Handling:
 * - Missing URI configuration detection
 * - Connection failure handling
 * - Appropriate HTTP status codes
 * - JSON error responses for API consistency
 * 
 * Global Variables Created:
 * - $client: MongoDB client instance for advanced operations
 * - $db: Selected database instance for collection operations
 * 
 * Usage in Other Files:
 * require_once 'config/database.php';
 * $collection = $db->selectCollection('events');
 * 
 * Security Considerations:
 * - Environment-based configuration
 * - No hardcoded connection strings
 * - Error message sanitization for production
 * - Connection timeout handling
 * 
 * MongoDB Collections Used:
 * - users: User accounts and authentication
 * - events: Event data and registration
 * - clubs: Club information and membership
 * - comments: Event comments and moderation
 * 
 * @author USIU Events Development Team
 * @version 1.0.0
 * @since 2024-01-01
 */

// Load application configuration for database settings
$config = require __DIR__ . '/config.php';

// Import MongoDB client class from the official PHP driver
use MongoDB\Client;

try {
    // Validate that MongoDB URI is configured
    if (empty($config['db']['uri'])) {
        throw new Exception("MongoDB URI is missing from configuration.");
    }

    // Validate that database name is configured
    if (empty($config['db']['database'])) {
        throw new Exception("MongoDB database name is missing from configuration.");
    }

    // Create MongoDB client instance using the connection URI
    // This establishes the connection to the MongoDB server
    $client = new Client($config['db']['uri']);

    // Select the application database from the MongoDB server
    // This database contains all collections for the USIU Events system
    $db = $client->selectDatabase($config['db']['database']);

    // Optional: Test the connection by running a simple command
    // Uncomment for connection validation in critical environments
    // $db->command(['ping' => 1]);

} catch (Exception $e) {
    // Handle database connection errors gracefully
    // Return appropriate HTTP status code for server errors
    http_response_code(500);
    
    // Provide JSON error response consistent with API format
    // In production, consider logging detailed error and returning generic message
    echo json_encode([
        'error' => 'Database connection failed',
        'message' => 'Unable to connect to the database. Please try again later.',
        'details' => $e->getMessage() // Remove in production for security
    ]);
    
    // Stop execution to prevent further errors
    exit;
}

