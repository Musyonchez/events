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
    if ($userModel->verifyEmail($token)) {
      send_success('Email has been successfully verified.');
    } else {
      send_error('Invalid or expired verification link.', 400);
    }
  } catch (Exception $e) {
    send_internal_server_error($e->getMessage());
  }
} else {
  send_error('No verification token provided.', 400);
}
