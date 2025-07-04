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

// Get the club ID from the URL (validated by middleware/validate.php)
$clubId = $_GET['id'];

// $requestData is available from index.php after validation and sanitization
$data = $requestData;

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

// Optional: Add authorization check here to ensure only the club leader or an admin can update
// if ($GLOBALS['user']->role !== 'admin') {
//     $club = $clubModel->findById($clubId);
//     if (!$club || $club['leader_id']->__toString() !== $GLOBALS['user']->userId) {
//         send_forbidden('You are not authorized to update this club');
//     }
// }

$result = $clubModel->updateWithValidation($clubId, $data);

if (!$result['success']) {
  send_validation_errors($result['errors']);
}

send_success('Club updated successfully', 200, ['modified' => $result['modified']]);
