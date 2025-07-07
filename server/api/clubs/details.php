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
require_once __DIR__ . '/../../utils/response.php';
use MongoDB\BSON\Regex;

header('Content-Type: application/json');

$clubModel = new ClubModel($db->clubs);

$clubId = $_GET['id'] ?? null;

if ($clubId) {
    // Get a single club by ID
    try {
        $club = $clubModel->findById($clubId);
        if ($club) {
            send_success('Club details fetched successfully', 200, $club);
        } else {
            send_not_found('Club');
        }
    } catch (Exception $e) {
        send_error($e->getMessage(), 400);
    }
    exit;
} else {
    send_error('Club ID is required for details', 400);
}