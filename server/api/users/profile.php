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
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

authenticate(); // Ensure user is logged in

header('Content-Type: application/json');

$userModel = new UserModel($db->users);

// The authenticated user's ID is available in $GLOBALS['user']->userId
$userId = $GLOBALS['user']->userId;

try {
    $userProfile = $userModel->findById($userId);
    if ($userProfile) {
        send_success('User profile fetched successfully', 200, $userProfile);
    } else {
        // This case should ideally not happen if authentication is successful
        send_not_found('User profile');
    }
} catch (Exception $e) {
    send_internal_server_error($e->getMessage());
}
