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
require_once __DIR__ . '/../../utils/upload.php';

authenticate();


header('Content-Type: application/json');

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

// Handle file upload if present
if (isset($_FILES['profile_image'])) {
    try {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        $data['profile_image'] = upload_file_to_s3($_FILES['profile_image'], 'user_profiles', $allowedMimeTypes);
    } catch (Exception $e) {
        send_error('File upload failed: ' . $e->getMessage());
    }
}

$userModel = new UserModel($db->users);

$result = $userModel->createWithValidation($data);

if (!$result['success']) {
  send_error('User creation failed', 400, $result['errors']);
}

send_created(['insertedId' => (string)$result['id']], 'User created successfully');
