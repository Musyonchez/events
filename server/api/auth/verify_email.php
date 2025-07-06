<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/cors.php'; // Include CORS settings
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php'; // Include response utility

header('Content-Type: application/json');

if (isset($_GET['token'])) {
  $token = $_GET['token'];
  $userModel = new UserModel($db->users);

  try {
    $verificationResult = $userModel->verifyEmail($token);

    switch ($verificationResult) {
      case 'success':
        send_success('Email has been successfully verified.');
        break;
      case 'invalid_token':
        send_error('Invalid verification link.', 400, ['error_type' => 'invalid_token']);
        break;
      case 'expired_token':
        send_error('Expired verification link.', 400, ['error_type' => 'token_expired']);
        break;
      case 'already_verified':
        send_error('Email already verified.', 400, ['error_type' => 'already_verified']);
        break;
      case 'verification_failed':
      default:
        send_error('Email verification failed. Please try again.', 500, ['error_type' => 'verification_failed']);
        break;
    }
  } catch (Exception $e) {
    send_internal_server_error($e->getMessage());
  }
} else {
  send_error('No verification token provided.', 400);
}
