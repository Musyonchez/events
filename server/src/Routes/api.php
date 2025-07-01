<?php
require_once __DIR__ . '/../Controllers/EventController.php';
require_once __DIR__ . '/../Utils/Response.php';

\$controller = new EventController();
Response::json(["route" => "API loaded", "data" => \$controller->getAllEvents()]);
