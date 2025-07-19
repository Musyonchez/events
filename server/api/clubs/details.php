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
            // Populate leader information if leader_id exists
            if (isset($club['leader_id'])) {
                $leader = $db->users->findOne(['_id' => $club['leader_id']]);
                if ($leader) {
                    $club['leader'] = [
                        'first_name' => $leader['first_name'],
                        'last_name' => $leader['last_name'],
                        'email' => $leader['email'],
                        'profile_image' => $leader['profile_image'] ?? null
                    ];
                }
            }
            
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