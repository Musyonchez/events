<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../middleware/auth.php';

authenticate();


header('Content-Type: application/json');

// Get the user ID from the URL (assuming it's passed as a query parameter)
$userId = $_GET['id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    exit;
}

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

$oldPassword = $data['old_password'] ?? null;
$newPassword = $data['new_password'] ?? null;

if (!$oldPassword || !$newPassword) {
    http_response_code(400);
    echo json_encode(['error' => 'Old password and new password are required']);
    exit;
}

$userModel = new UserModel($db->users);

$result = $userModel->changePassword($userId, $oldPassword, $newPassword);

if (!$result['success']) {
    http_response_code(400);
    echo json_encode(['error' => 'Password change failed', 'details' => $result['errors']]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
