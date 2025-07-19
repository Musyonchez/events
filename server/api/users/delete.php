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
  // Check if user has any events or clubs before deletion
  $userObjectId = new MongoDB\BSON\ObjectId($userId);
  
  // Check for events created by this user
  $userEvents = $db->events->find([
      'created_by' => $userObjectId
  ])->toArray();
  
  // Check for clubs led by this user
  $userClubs = $db->clubs->find([
      'leader_id' => $userObjectId
  ])->toArray();
  
  $errors = [];
  if (count($userEvents) > 0) {
      $errors[] = count($userEvents) . ' event(s)';
  }
  if (count($userClubs) > 0) {
      $errors[] = count($userClubs) . ' club(s)';
  }
  
  if (!empty($errors)) {
      $errorMessage = 'Cannot delete user. User has ' . implode(' and ', $errors) . ' associated with them. Please delete or transfer these first.';
      send_error($errorMessage, 400);
  }
  
  if ($userModel->delete($userId)) {
    send_success('User deleted successfully');
  } else {
    send_not_found('User');
  }
} catch (Exception $e) {
  send_internal_server_error($e->getMessage());
}
