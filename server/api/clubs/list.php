<?php
require_once __DIR__ . '/../../models/Club.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/response.php';

if (!defined('IS_CLUB_ROUTE')) {
    send_error('Invalid request', 400);
}

$user = authenticate();
$user_id = $user['userId'] ?? null;

// Get query parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'createdAt';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
$min_members = isset($_GET['min_members']) ? (int)$_GET['min_members'] : null;
$max_members = isset($_GET['max_members']) ? (int)$_GET['max_members'] : null;

// Validate and sanitize parameters
$page = max(1, $page);
$limit = max(1, $limit);
$sort_order = strtolower($sort_order) === 'asc' ? 'asc' : 'desc';

// Prepare filters
$filters = [];
if (!empty($search)) {
    $filters['$or'] = [
        ['name' => ['$regex' => $search, '$options' => 'i']],
        ['description' => ['$regex' => $search, '$options' => 'i']],
    ];
}
if (!empty($category)) {
    $filters['category'] = $category;
}
if (!empty($status)) {
    $filters['status'] = $status;
}

// Add member count filters
if ($min_members !== null || $max_members !== null) {
    $member_filter = [];
    if ($min_members !== null) {
        $member_filter['$gte'] = $min_members;
    }
    if ($max_members !== null) {
        $member_filter['$lte'] = $max_members;
    }
    $filters['members_count'] = $member_filter;
}

// Prepare sort options
$sort_options = [$sort_by => ($sort_order === 'asc' ? 1 : -1)];

try {
    $clubModel = new ClubModel($db->clubs);
    $clubs = $clubModel->listClubs($filters, $page, $limit, $sort_options);
    $total_clubs = $clubModel->countClubs($filters);

    // Add is_member flag to each club
    foreach ($clubs as &$club) {
        $club['is_member'] = false;
        if ($user_id && isset($club['members']) && is_array($club['members'])) {
            $current_user_objectId = new MongoDB\BSON\ObjectId($user_id);
            foreach ($club['members'] as $member_objectId) {
                if ($member_objectId == $current_user_objectId) {
                    $club['is_member'] = true;
                    break;
                }
            }
        }
    }
    unset($club); // Break the reference with the last element

    send_response([
        'clubs' => $clubs,
        'total_clubs' => $total_clubs,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total_clubs / $limit)
    ]);
} catch (Exception $e) {
    send_error('Failed to retrieve clubs: ' . $e->getMessage(), 500);
}

?>
    