<?php

// api/club/createClub.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (
    ! isset($input['name'], $input['description'], $input['logo_url'], $input['contact_info'])
) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required club fields']);
    exit;
}

require_once __DIR__.'/../../models/ClubModel.php';

$clubModel = new ClubModel;

try {
    $club = $clubModel->createClub([
        'id' => bin2hex(random_bytes(16)),
        'name' => $input['name'],
        'description' => $input['description'],
        'logo_url' => $input['logo_url'],
        'contact_info' => $input['contact_info'],
    ]);

    echo json_encode(['message' => 'Club created successfully', 'club' => $club]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create club', 'details' => $e->getMessage()]);
}
