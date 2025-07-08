<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../utils/response.php';

if (!defined('IS_CLUB_ROUTE')) {
    send_error('Invalid request', 400);
}

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_method_not_allowed();
}

// Authenticate user
$user = authenticate();
$userId = $user->userId;

// Get club ID from request data (already parsed and sanitized in index.php)
if (!isset($requestData['club_id']) || empty($requestData['club_id'])) {
    send_error('Club ID is required', 400);
}

$clubId = $requestData['club_id'];

try {
    $clubModel = new ClubModel($db->clubs);
    $club = $clubModel->findById($clubId);

    if (!$club) {
        send_not_found('Club');
    }

    // Check if user is already a member
    $isMember = false;
    foreach (($club['members'] ?? []) as $memberId) {
        if ($memberId->__toString() == $userId) {
            $isMember = true;
            break;
        }
    }

    if ($isMember) {
        send_error('User is already a member of this club', 409);
    }

    // Add user to members array and increment members_count
    $success = $clubModel->addMember($clubId, $userId);

    if ($success) {
        send_success('Successfully joined club', 200);
    } else {
        send_error('Failed to join club', 500);
    }

} catch (Exception $e) {
    send_error('Error joining club: ' . $e->getMessage(), 500);
}

?>