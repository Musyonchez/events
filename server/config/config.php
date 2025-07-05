<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// General Application Settings
define('APP_NAME', 'USIU Events API');
define('DEFAULT_PAGINATION_LIMIT', 20);
define('MAX_PAGINATION_LIMIT', 100);

// Database Configuration
$config['db'] = [
    'uri' => $_ENV['MONGODB_URI'] ?? '',
    'database' => $_ENV['MONGODB_DB'] ?? '',
];

// JWT Configuration
$config['jwt'] = [
    'secret' => $_ENV['JWT_SECRET'] ?? '',
];

// AWS S3 Configuration
$config['aws'] = [
    'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
    'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '',
    'region' => $_ENV['AWS_REGION'] ?? '',
    'bucket' => $_ENV['AWS_BUCKET'] ?? '',
];

// SMTP Configuration
$config['smtp'] = [
    'host' => $_ENV['SMTP_HOST'] ?? '',
    'port' => $_ENV['SMTP_PORT'] ?? '',
    'username' => $_ENV['SMTP_USERNAME'] ?? '',
    'password' => $_ENV['SMTP_PASSWORD'] ?? '',
    'from_email' => $_ENV['SMTP_FROM_EMAIL'] ?? '',
    'from_name' => $_ENV['SMTP_FROM_NAME'] ?? '',
];

// Frontend URL for CORS and redirects
$config['frontend_url'] = $_ENV['FRONTEND_URL'] ?? '';

// Other application-specific configurations can be added here
// For example:
// define('DEBUG_MODE', true);
// define('LOG_LEVEL', 'info');

return $config;