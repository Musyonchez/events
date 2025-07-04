<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

$userModel = new UserModel($db->users);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Case 1: Requesting a password reset link (email is provided)
    if (isset($data['email'])) {
        $email = $data['email'] ?? '';

        if (empty($email)) {
            send_error('Email is required');
        }

        // Attempt to generate and send the token. Always send success to prevent user enumeration.
        $userModel->generatePasswordResetToken($email);
        send_success('If a user with that email exists, a password reset link has been sent.');

    // Case 2: Submitting a new password with a token
    } elseif (isset($data['token']) && isset($data['password'])) {
        $token = $data['token'];
        $newPassword = $data['password'];

        if (empty($token) || empty($newPassword)) {
            send_error('Token and new password are required');
        }

        if ($userModel->resetPassword($token, $newPassword)) {
            send_success('Password has been reset successfully.');
        } else {
            send_error('Invalid or expired password reset token.');
        }
    } else {
        send_error('Invalid request payload.');
    }
} else {
    send_method_not_allowed();
}
