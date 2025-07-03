<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../middleware/auth.php';

authenticate();


header('Content-Type: application/json');

// Get the user ID from the URL (validated by middleware/validate.php)
$userId = $_GET['id'];

$userModel = new UserModel($db->users);

try {
  if ($userModel->delete($userId)) {
    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
  } else {
    http_response_code(404);
    echo json_encode(['error' => 'User not found or could not be deleted']);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
