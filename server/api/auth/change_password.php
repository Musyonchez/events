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

// Get the user ID from the authenticated user (JWT token)
$userId = $GLOBALS['user']->userId ?? null;

if (!$userId) {
    send_error('Authentication failed: user ID not found');
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
    // Extract specific error messages for better user feedback
    $errors = $result['errors'];
    
    if (isset($errors['old_password']) && $errors['old_password'] === 'Current password is incorrect') {
        send_error('Current password is incorrect. Please check your password and try again.', 400, $errors);
    } elseif (isset($errors['new_password'])) {
        send_error('New password is invalid: ' . $errors['new_password'], 400, $errors);
    } else {
        send_error('Password change failed. Please try again.', 400, $errors);
    }
}

send_success('Password changed successfully');
