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

authenticate(); // Ensure user is logged in

header('Content-Type: application/json');

// Get the club ID from the URL
$clubId = $_GET['id'] ?? null;

if (!$clubId) {
    send_error('Club ID is required');
}

$clubModel = new ClubModel($db->clubs);

// Optional: Add authorization check here to ensure only the club leader or an admin can delete
// if ($GLOBALS['user']->role !== 'admin') {
//     $club = $clubModel->findById($clubId);
//     if (!$club || $club['leader_id']->__toString() !== $GLOBALS['user']->userId) {
//         send_forbidden('You are not authorized to delete this club');
//     }
// }

try {
    // Check if club has any events before deletion
    $clubObjectId = new MongoDB\BSON\ObjectId($clubId);
    $clubEvents = $db->events->find([
        'club_id' => $clubObjectId
    ])->toArray();
    
    if (count($clubEvents) > 0) {
        send_error('Cannot delete club. Club has ' . count($clubEvents) . ' event(s) associated with it. Please delete these events first.', 400);
    }
    
    if ($clubModel->delete($clubId)) {
        send_success('Club deleted successfully');
    } else {
        send_not_found('Club');
    }
} catch (Exception $e) {
    send_internal_server_error($e->getMessage());
}
