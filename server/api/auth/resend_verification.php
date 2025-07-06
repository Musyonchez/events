<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/cors.php'; // Include CORS settings
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';

    if (empty($email)) {
        send_error('Email is required.', 400);
    }

    $userModel = new UserModel($db->users);

    try {
        $result = $userModel->generateVerificationTokenByEmail($email);

        switch ($result) {
            case 'success':
                send_success('A new verification link has been sent to your email address.');
                break;
            case 'user_not_found':
                // For security, don't reveal if user doesn't exist
                send_success('If an account with that email exists, a new verification link has been sent.');
                break;
            case 'already_verified':
                send_error('Your email is already verified. You can log in now.', 400, ['error_type' => 'already_verified']);
                break;
            case 'email_send_failed':
                send_error('Failed to send verification email. Please try again later.', 500, ['error_type' => 'email_send_failed']);
                break;
            case 'token_generation_failed':
                send_error('Failed to generate verification token. Please try again later.', 500, ['error_type' => 'token_generation_failed']);
                break;
            default:
                send_error('An unexpected error occurred. Please try again later.', 500);
                break;
        }
    } catch (Exception $e) {
        send_internal_server_error($e->getMessage());
    }
} else {
    send_error('Invalid request method.', 405);
}