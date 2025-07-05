<?php
// Autoload dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// CORS settings
require_once __DIR__ . '/config/cors.php';

header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No content
    exit;
}

// Get the request URI and method
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove query string and leading slash
$request = parse_url($request, PHP_URL_PATH);
$request = ltrim($request, '/');

// Only route requests under /api/
if (str_starts_with($request, 'api/')) {
    $path = __DIR__ . '/' . $request;

    if (file_exists($path)) {
        require $path;
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
        exit;
    }
}

// Fallback (non-api requests)
echo json_encode([
    "status" => "success",
    "message" => "USIU Event API is running."
]);
