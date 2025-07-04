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
}

// --- Logic for fetching a list of clubs (with filtering and pagination) ---

// Read pagination parameters
$limit = (int) ($_GET['limit'] ?? 50);
$skip = (int) ($_GET['skip'] ?? 0);

// Whitelist of allowed filter fields
$allowedFilters = [
    'category',
    'status',
    'leader_id'
];

// Fields that should use case-insensitive matching
$caseInsensitiveFields = ['category', 'status'];

$filters = [];
// Build the filter array from the whitelisted GET parameters
foreach ($allowedFilters as $filterKey) {
    if (isset($_GET[$filterKey])) {
        $filterValue = $_GET[$filterKey];

        if (in_array($filterKey, $caseInsensitiveFields, true)) {
            // Use a case-insensitive regex for specific fields
            $filters[$filterKey] = new MongoDB\BSON\Regex($filterValue, 'i');
        } else {
            // Use exact match for other fields
            $filters[$filterKey] = $filterValue;
        }
    }
}

// Handle search term
$searchTerm = $_GET['search'] ?? null;
if ($searchTerm) {
    $clubs = $clubModel->search($searchTerm, ['limit' => $limit, 'skip' => $skip, 'status' => $filters['status'] ?? null]);
} else {
    $clubs = $clubModel->list($filters, $limit, $skip);
}

send_success('Clubs fetched successfully', 200, $clubs);