<?php

// api/actions/EventActions.php

require_once __DIR__.'/../../db.php'; // returns the MongoDB client

class EventsModel
{
    private $collection;

    public function __construct()
    {
        $client = require __DIR__.'/../../db.php';
        $this->collection = $client->campus_events->events;
    }

    public function createEvent($data)
    {
        $now = new MongoDB\BSON\UTCDateTime;

        // Add timestamps
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        // Insert into MongoDB
        $result = $this->collection->insertOne($data);

        return $result->getInsertedId();
    }

    public function getAllEvents($limit = 100, $skip = 0)
    {
        $cursor = $this->collection->find([], [
            'limit' => $limit,
            'skip' => $skip,
            'sort' => ['event_date' => 1, 'event_time' => 1], // Sort by date and time ascending
        ]);

        return iterator_to_array($cursor);
    }

    public function getEventById($id)
    {
        return $this->collection->findOne(['id' => $id]);
    }

    public function getEventsByStatus($status, $limit = 100)
    {
        $cursor = $this->collection->find(['status' => $status], [
            'limit' => $limit,
            'sort' => ['event_date' => 1, 'event_time' => 1],
        ]);

        return iterator_to_array($cursor);
    }

    public function getUpcomingEvents($limit = 50)
    {
        $today = date('Y-m-d');

        $cursor = $this->collection->find([
            'event_date' => ['$gte' => $today],
            'status' => 'upcoming',
        ], [
            'limit' => $limit,
            'sort' => ['event_date' => 1, 'event_time' => 1],
        ]);

        return iterator_to_array($cursor);
    }

    public function getEventsByDateRange($startDate, $endDate)
    {
        $cursor = $this->collection->find([
            'event_date' => [
                '$gte' => $startDate,
                '$lte' => $endDate,
            ],
        ], [
            'sort' => ['event_date' => 1, 'event_time' => 1],
        ]);

        return iterator_to_array($cursor);
    }

    public function getEventsByHost($host, $limit = 50)
    {
        $cursor = $this->collection->find(['host' => $host], [
            'limit' => $limit,
            'sort' => ['event_date' => -1], // Most recent first
        ]);

        return iterator_to_array($cursor);
    }

    public function searchEvents($searchTerm, $limit = 50)
    {
        $cursor = $this->collection->find([
            '$or' => [
                ['event_name' => new MongoDB\BSON\Regex($searchTerm, 'i')],
                ['description' => new MongoDB\BSON\Regex($searchTerm, 'i')],
                ['host' => new MongoDB\BSON\Regex($searchTerm, 'i')],
                ['location' => new MongoDB\BSON\Regex($searchTerm, 'i')],
            ],
        ], [
            'limit' => $limit,
            'sort' => ['event_date' => 1],
        ]);

        return iterator_to_array($cursor);
    }

    public function updateEvent($id, $data)
    {
        $data['updated_at'] = new MongoDB\BSON\UTCDateTime;
        $result = $this->collection->updateOne(
            ['id' => $id],
            ['$set' => $data]
        );

        return $result->getModifiedCount();
    }

    public function updateEventStatus($id, $status)
    {
        $validStatuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];

        if (! in_array($status, $validStatuses)) {
            throw new InvalidArgumentException('Invalid status. Must be one of: '.implode(', ', $validStatuses));
        }

        return $this->updateEvent($id, ['status' => $status]);
    }

    public function deleteEvent($id)
    {
        $result = $this->collection->deleteOne(['id' => $id]);

        return $result->getDeletedCount();
    }

    public function addEventAttendee($eventId, $userId)
    {
        $result = $this->collection->updateOne(
            ['id' => $eventId],
            [
                '$addToSet' => ['attendees' => $userId],
                '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime],
            ]
        );

        return $result->getModifiedCount();
    }

    public function removeEventAttendee($eventId, $userId)
    {
        $result = $this->collection->updateOne(
            ['id' => $eventId],
            [
                '$pull' => ['attendees' => $userId],
                '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime],
            ]
        );

        return $result->getModifiedCount();
    }

    public function getEventAttendees($eventId)
    {
        $event = $this->collection->findOne(['id' => $eventId], [
            'projection' => ['attendees' => 1],
        ]);

        return $event['attendees'] ?? [];
    }

    public function getEventStats()
    {
        $pipeline = [
            [
                '$group' => [
                    '_id' => '$status',
                    'count' => ['$sum' => 1],
                ],
            ],
        ];

        $cursor = $this->collection->aggregate($pipeline);
        $stats = iterator_to_array($cursor);

        // Convert to associative array
        $result = [];
        foreach ($stats as $stat) {
            $result[$stat['_id']] = $stat['count'];
        }

        return $result;
    }

    public function getEventsCountByMonth($year = null)
    {
        if (! $year) {
            $year = date('Y');
        }

        $pipeline = [
            [
                '$match' => [
                    'event_date' => [
                        '$gte' => $year.'-01-01',
                        '$lte' => $year.'-12-31',
                    ],
                ],
            ],
            [
                '$group' => [
                    '_id' => ['$substr' => ['$event_date', 5, 2]], // Extract month
                    'count' => ['$sum' => 1],
                ],
            ],
            [
                '$sort' => ['_id' => 1],
            ],
        ];

        $cursor = $this->collection->aggregate($pipeline);

        return iterator_to_array($cursor);
    }
}
