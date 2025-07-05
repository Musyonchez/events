<?php
if (!defined('IS_AUTH_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

if (!isset($data['email'])) {
    send_error('Email is required.', 400);
}

$email = $data['email'];
$userModel = new UserModel($db->users);

try {
    $result = $userModel->resendVerificationToken($email);

    if ($result['success']) {
        send_success('Verification email sent. Please check your inbox.', 200);
    } else {
        send_error($result['error'], 400);
    }
} catch (Exception $e) {
    send_internal_server_error($e->getMessage());
}
