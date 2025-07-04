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

authenticate();


header('Content-Type: application/json');

// Get the user ID from the URL (validated by middleware/validate.php)
$userId = $_GET['id'];

$userModel = new UserModel($db->users);

try {
  if ($userModel->delete($userId)) {
    send_success('User deleted successfully');
  } else {
    send_not_found('User');
  }
} catch (Exception $e) {
  send_internal_server_error($e->getMessage());
}
