<?php
// Entry point for the PHP API
require_once __DIR__ . '/src/Routes/api.php';

echo json_encode(["message" => "Welcome to USIU Campus Events API"]);
