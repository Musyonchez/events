<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
echo json_encode([
    "status" => "success",
    "message" => "USIU Event API: Event Listing Endpoint"
]);

