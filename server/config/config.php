<?php
/**
 * USIU Events Management System - Application Configuration
 * 
 * Central configuration file that loads environment variables and sets up
 * application-wide constants and configuration arrays. This file serves as
 * the single source of truth for all application settings.
 * 
 * Configuration Categories:
 * - Database: MongoDB connection settings
 * - JWT: Authentication token configuration
 * - AWS S3: File storage configuration
 * - SMTP: Email service configuration
 * - Application: General app settings and constants
 * 
 * Environment Variables:
 * All sensitive configuration is loaded from .env file including:
 * - Database credentials and connection strings
 * - JWT secrets for token signing
 * - AWS credentials for file storage
 * - SMTP credentials for email sending
 * - Frontend URL for CORS configuration
 * 
 * Security Features:
 * - Environment variable isolation
 * - Default value fallbacks for missing variables
 * - No hardcoded sensitive values
 * - Structured configuration arrays
 * 
 * Usage:
 * $config = require 'config/config.php';
 * $dbUri = $config['db']['uri'];
 * 
 * @author USIU Events Development Team
 * @version 1.0.0
 * @since 2024-01-01
 */

// Load Composer autoloader for environment variable handling
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables from .env file in project root
// This file contains sensitive configuration that shouldn't be committed to version control
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// General Application Settings
// These constants are used throughout the application for consistent behavior
if (!defined('APP_NAME')) {
    define('APP_NAME', 'USIU Events API');
}
if (!defined('DEFAULT_PAGINATION_LIMIT')) {
    define('DEFAULT_PAGINATION_LIMIT', 20);  // Default number of items per page
}
if (!defined('MAX_PAGINATION_LIMIT')) {
    define('MAX_PAGINATION_LIMIT', 100);     // Maximum items per page to prevent performance issues
}

// Database Configuration
// MongoDB connection settings with fallback to empty strings
$config['db'] = [
    'uri' => $_ENV['MONGODB_URI'] ?? '',           // MongoDB connection string
    'database' => $_ENV['MONGODB_DB'] ?? '',       // Database name for the application
];

// JWT Configuration
// JSON Web Token settings for authentication
$config['jwt'] = [
    'secret' => $_ENV['JWT_SECRET'] ?? '',         // Secret key for signing JWT tokens
];

// AWS S3 Configuration
// Amazon Web Services settings for file storage (logos, banners, etc.)
$config['aws'] = [
    'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',     // AWS access key for API authentication
    'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '', // AWS secret key for API authentication
    'region' => $_ENV['AWS_REGION'] ?? '',         // AWS region for S3 bucket
    'bucket' => $_ENV['AWS_BUCKET'] ?? '',         // S3 bucket name for file storage
];

// SMTP Configuration
// Email server settings for sending notifications and verification emails
$config['smtp'] = [
    'host' => $_ENV['SMTP_HOST'] ?? '',           // SMTP server hostname
    'port' => $_ENV['SMTP_PORT'] ?? '',           // SMTP server port (usually 587 or 465)
    'username' => $_ENV['SMTP_USERNAME'] ?? '',   // SMTP authentication username
    'password' => $_ENV['SMTP_PASSWORD'] ?? '',   // SMTP authentication password
    'from_email' => $_ENV['SMTP_FROM_EMAIL'] ?? '', // Default sender email address
    'from_name' => $_ENV['SMTP_FROM_NAME'] ?? '', // Default sender name
];

// Frontend URL Configuration
// Used for CORS policy and redirect URLs in emails
$config['frontend_url'] = $_ENV['FRONTEND_URL'] ?? '';

// Additional Configuration Options
// Uncomment and modify as needed for specific environments:
// define('DEBUG_MODE', $_ENV['DEBUG_MODE'] ?? false);     // Enable debug logging
// define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'info');      // Set logging verbosity
// define('RATE_LIMIT_REQUESTS', 100);                     // API rate limiting
// define('RATE_LIMIT_WINDOW', 3600);                      // Rate limit time window
// define('MAX_FILE_SIZE', 5 * 1024 * 1024);               // 5MB file upload limit
// define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif']); // Image file types

// Return configuration array for use throughout the application
return $config;