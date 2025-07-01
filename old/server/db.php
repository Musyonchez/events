<?php
// api/db.php

require_once __DIR__ . '/./vendor/autoload.php';

use MongoDB\Client;
use Dotenv\Dotenv;

try {
    // Load environment variables from .env in the root directory
    $dotenv = Dotenv::createImmutable(__DIR__ . '/./');
    $dotenv->load();

    // Get MongoDB URI from .env
    $uri = $_ENV['MONGO_URI'];

    // Create MongoDB client
    $client = new Client($uri);

    // Optional: Check if connection is successful
    $client->admin->command(['ping' => 1]);

    // Export $client for other files to use
    return $client;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'MongoDB connection failed: ' . $e->getMessage()]);
    exit;
}
?>


