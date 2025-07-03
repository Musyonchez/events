<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generateJwt(string $userId, string $email, string $role, string $secretKey): string
{
    $issuedAt = time();
    $expirationTime = $issuedAt + (60 * 60); // Token valid for 1 hour

    $payload = [
        'iss' => 'your_domain.com', // Issuer
        'aud' => 'your_app_users', // Audience
        'iat' => $issuedAt, // Issued at: time when the token was generated
        'exp' => $expirationTime, // Expire time
        'data' => [
            'userId' => $userId,
            'email' => $email,
            'role' => $role
        ]
    ];

    return JWT::encode($payload, $secretKey, 'HS256');
}

function validateJwt(string $jwt, string $secretKey): ?object
{
    try {
        $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        // Log the error for debugging, but don't expose details to the client
        // error_log("JWT Validation Error: " . $e->getMessage());
        return null;
    }
}
