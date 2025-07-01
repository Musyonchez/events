<?php
// api/club/getClub.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Club ID is required']);
    exit;
}

require_once __DIR__ . '/../../models/ClubModel.php';

$clubModel = new ClubModel();

try {
    $club = $clubModel->getClubById($input['id']);

    if (!$club) {
        http_response_code(404);
        echo json_encode(['error' => 'Club not found']);
    } else {
        echo json_encode(['club' => $club]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch club', 'details' => $e->getMessage()]);
}

