<?php
if (!defined('IS_CLUB_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/upload.php';

authenticate(); // Ensure user is logged in

header('Content-Type: application/json');

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

// Get leader_id from authenticated user
$data['leader_id'] = $GLOBALS['user']->userId; // Assuming userId is stored in $GLOBALS['user']

// Handle file upload if present
if (isset($_FILES['logo'])) {
    try {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        $data['logo'] = upload_file_to_s3($_FILES['logo'], 'club_logos', $allowedMimeTypes);
    } catch (Exception $e) {
        send_error('File upload failed: ' . $e->getMessage());
    }
}

$clubModel = new ClubModel($db->clubs);

$result = $clubModel->createWithValidation($data);

if (!$result['success']) {
  send_error('Club creation failed', 400, $result['errors']);
}

send_created(['clubId' => (string)$result['id']], 'Club created successfully');
