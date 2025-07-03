<?php
$config = require __DIR__ . '/config.php';

use MongoDB\Client;

try {
    if (empty($config['db']['uri'])) {
        throw new Exception("MongoDB URI is missing.");
    }

    // Create MongoDB client instance using the full URI
    $client = new Client($config['db']['uri']);

    // Select database
    $db = $client->selectDatabase($config['db']['database']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

