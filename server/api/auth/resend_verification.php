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
        // Generate and send a new verification token
        // The generateVerificationToken method also sends the email
        $success = $userModel->generateVerificationTokenByEmail($email);

        if ($success) {
            send_success('A new verification link has been sent to your email address.');
        } else {
            // This case might happen if the user is not found, but we don't want to reveal that.
            // Or if there was an issue sending the email.
            send_error('Failed to send new verification link. Please try again later.', 500);
        }
    } catch (Exception $e) {
        send_internal_server_error($e->getMessage());
    }
} else {
    send_error('Invalid request method.', 405);
}