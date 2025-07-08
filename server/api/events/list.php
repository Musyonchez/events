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
require_once __DIR__ . '/../../utils/response.php';

use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

header('Content-Type: application/json');

$eventModel = new EventModel($db->events);

// --- Logic for fetching a list of events (with filtering, sorting, and pagination) ---

// Pagination
$limit = (int) ($_GET['limit'] ?? 12);
$page = (int) ($_GET['page'] ?? 1);
$skip = ($page - 1) * $limit;

// Filters
$filters = [];

// Search filter (title and description)
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $_GET['search'];
    $filters['$or'] = [
        ['title' => new Regex($searchTerm, 'i')],
        ['description' => new Regex($searchTerm, 'i')]
    ];
}

// Club filter
if (isset($_GET['club_id']) && !empty($_GET['club_id'])) {
    $filters['club_id'] = new MongoDB\BSON\ObjectId($_GET['club_id']);
}

// Category filter
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $filters['category'] = new Regex($_GET['category'], 'i');
}

// Status filter
if (isset($_GET['status']) && !empty($_GET['status'])) {
    if ($_GET['status'] === 'featured') {
        $filters['featured'] = true;
    } elseif ($_GET['status'] === 'registration-open') {
        $filters['registration_required'] = true;
        $filters['registration_deadline'] = ['$gt' => new UTCDateTime()];
    } else {
        $filters['status'] = new Regex($_GET['status'], 'i');
    }
}

// Date filter
if (isset($_GET['date']) && !empty($_GET['date'])) {
    $now = new DateTime();
    $today = new DateTime($now->format('Y-m-d'));
    
    switch ($_GET['date']) {
        case 'today':
            $endOfDay = (clone $today)->modify('+1 day');
            $filters['event_date'] = [
                '$gte' => new UTCDateTime($today),
                '$lt' => new UTCDateTime($endOfDay)
            ];
            break;
        case 'tomorrow':
            $tomorrow = (clone $today)->modify('+1 day');
            $dayAfter = (clone $tomorrow)->modify('+1 day');
            $filters['event_date'] = [
                '$gte' => new UTCDateTime($tomorrow),
                '$lt' => new UTCDateTime($dayAfter)
            ];
            break;
        case 'this-week':
            $startOfWeek = (clone $today)->modify('this monday');
            $endOfWeek = (clone $startOfWeek)->modify('+1 week');
            $filters['event_date'] = [
                '$gte' => new UTCDateTime($startOfWeek),
                '$lt' => new UTCDateTime($endOfWeek)
            ];
            break;
        case 'this-month':
            $startOfMonth = new DateTime('first day of this month midnight');
            $endOfMonth = new DateTime('first day of next month midnight');
            $filters['event_date'] = [
                '$gte' => new UTCDateTime($startOfMonth),
                '$lt' => new UTCDateTime($endOfMonth)
            ];
            break;
        case 'upcoming':
            $filters['event_date'] = ['$gte' => new UTCDateTime($now)];
            break;
    }
}

// Sorting
$sortOptions = [];
if (isset($_GET['sort']) && !empty($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'date-asc':
            $sortOptions['event_date'] = 1;
            break;
        case 'date-desc':
            $sortOptions['event_date'] = -1;
            break;
        case 'title-asc':
            $sortOptions['title'] = 1;
            break;
        case 'title-desc':
            $sortOptions['title'] = -1;
            break;
        case 'featured':
            $sortOptions['featured'] = -1;
            $sortOptions['event_date'] = 1;
            break;
        default:
            $sortOptions['event_date'] = 1; // Default sort
    }
} else {
    $sortOptions['event_date'] = 1; // Default sort
}

try {
    // Fetch total count for pagination
    $total = $eventModel->count($filters);

    // Fetch the list of events
    $events = $eventModel->list($filters, $limit, $skip, $sortOptions);

    send_success('Events fetched successfully', 200, ['events' => $events, 'total' => $total]);

} catch (Exception $e) {
    send_error('An error occurred while fetching events: ' . $e->getMessage(), 500);
}
