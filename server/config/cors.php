<?php

require_once __DIR__ . '/config.php'; // Load configuration, including frontend_url

// Allow from any origin for development, or specific origin for production
$allowedOrigin = $config['frontend_url'] ?? '*';

header("Access-Control-Allow-Origin: $allowedOrigin");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

