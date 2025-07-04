<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

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

function validateJwt(string $jwt, string $secretKey): string|object
{
    try {
        $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
        return $decoded; // Success, return the decoded object
    } catch (ExpiredException $e) {
        return 'expired'; // Token is expired
    } catch (SignatureInvalidException $e) {
        return 'invalid_signature'; // Token has been tampered with
    } catch (BeforeValidException $e) {
        return 'not_yet_valid'; // Token is not yet valid
    } catch (Exception $e) {
        // Catch all other JWT exceptions
        error_log("JWT Validation Error: " . $e->getMessage());
        return 'invalid_token'; // Generic invalid token
    }
}