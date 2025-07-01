<?php

// api/club/getClubs.php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__.'/../../models/ClubModel.php';

$clubModel = new ClubModel;

try {
    $clubs = $clubModel->getAllClubs();

    echo json_encode(['clubs' => $clubs]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch clubs', 'details' => $e->getMessage()]);
}
