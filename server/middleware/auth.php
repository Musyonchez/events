<?php

require_once __DIR__ . '/../utils/jwt.php';

function authenticate() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (empty($authHeader)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Authorization header missing']);
        exit;
    }

    list($jwt) = sscanf($authHeader, 'Bearer %s');

    if (!$jwt) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Bearer token not found in Authorization header']);
        exit;
    }

    $jwtSecret = $_ENV['JWT_SECRET'];
    if (!$jwtSecret) {
        http_response_code(500);
        echo json_encode(['error' => 'JWT secret not configured']);
        exit;
    }

    $decoded = validateJwt($jwt, $jwtSecret);

    if (is_string($decoded)) {
        // It's an error string from validateJwt
        switch ($decoded) {
            case 'expired':
                send_unauthorized('Token expired', ['error_type' => 'token_expired']);
                break;
            case 'invalid_signature':
                send_unauthorized('Invalid token signature', ['error_type' => 'invalid_signature']);
                break;
            case 'not_yet_valid':
                send_unauthorized('Token not yet valid', ['error_type' => 'not_yet_valid']);
                break;
            default:
                send_unauthorized('Invalid token', ['error_type' => 'invalid_token']);
                break;
        }
    }

    // If it's not a string, it's the decoded object
    // Attach user data to the request (e.g., in a global variable or a request object)
    // For simplicity, we'll use a global for now, but a proper request object is better.
    $GLOBALS['user'] = $decoded->data;
    return $decoded->data;
}

function authorize(array $allowedRoles) {
    if (!isset($GLOBALS['user'])) {
        http_response_code(401); // Unauthorized (authentication not performed)
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

    $userRole = $GLOBALS['user']->role;

    if (!in_array($userRole, $allowedRoles)) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'Access forbidden: insufficient role']);
        exit;
    }
}
