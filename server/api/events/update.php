<?php
if (!defined('IS_EVENT_ROUTE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/upload.php';

authenticate();


header('Content-Type: application/json');

// Get the event ID from the URL or request data
$eventId = $_GET['id'] ?? $requestData['id'] ?? null;

if (!$eventId) {
    send_error('Event ID is required for update', 400);
}

$data = $requestData;

// Remove ID from data to avoid conflicts (ID is passed separately)
unset($data['id']);

// Handle file upload if present
if (isset($_FILES['banner_image'])) {
    try {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        $data['banner_image'] = upload_file_to_s3($_FILES['banner_image'], 'event_banners', $allowedMimeTypes);
    } catch (Exception $e) {
        send_error('File upload failed: ' . $e->getMessage());
    }
}

$eventModel = new EventModel($db->events);

$result = $eventModel->updateWithValidation($eventId, $data);

if (!$result['success']) {
  send_validation_errors($result['errors']);
}

send_success('Event updated successfully', 200, ['modified' => $result['modified']]);
