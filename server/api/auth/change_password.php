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
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

authenticate();


header('Content-Type: application/json');

// Get the user ID from the URL (assuming it's passed as a query parameter)
$userId = $_GET['id'] ?? null;

if (!$userId) {
    send_error('User ID is required');
}

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

$oldPassword = $data['old_password'] ?? null;
$newPassword = $data['new_password'] ?? null;

if (!$oldPassword || !$newPassword) {
    send_error('Old password and new password are required');
}

$userModel = new UserModel($db->users);

$result = $userModel->changePassword($userId, $oldPassword, $newPassword);

if (!$result['success']) {
    send_error('Password change failed', 400, $result['errors']);
}

send_success('Password changed successfully');
