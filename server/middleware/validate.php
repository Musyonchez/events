<?php

function validateRequest($method) {
    // For POST and PATCH requests, ensure JSON content type and valid JSON body
    if ($method === 'POST' || $method === 'PATCH') {
        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
            http_response_code(415); // Unsupported Media Type
            echo json_encode(['error' => 'Content-Type must be application/json']);
            exit;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }

        // Store decoded data for later use in the main script
        return $data;
    }

    // For PATCH and DELETE requests, ensure 'id' parameter is present
    if ($method === 'PATCH' || $method === 'DELETE') {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Event ID is required']);
            exit;
        }
    }

    return null; // No data to return for GET/DELETE, or no specific validation needed
}
