<?php
if (!defined('IS_USER_ROUTE')) {
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

// Get the user ID from the URL
$userId = $_GET['id'] ?? null;

// The validate middleware should ideally catch this, but as a fallback:
if (!$userId) {
    send_error('User ID is required');
}

$userModel = new UserModel($db->users);

try {
    $user = $userModel->findById($userId);
    if ($user) {
        send_success('User details fetched successfully', 200, $user);
    } else {
        send_not_found('User');
    }
} catch (Exception $e) {
    send_error($e->getMessage(), 400);
}
