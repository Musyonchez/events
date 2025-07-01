<?php

// api/actions/UserActions.php

require_once __DIR__.'/../../db.php';

class ClubModel
{
    private $collection;

    public function __construct()
    {
        $client = require __DIR__.'/../db.php';
        $this->collection = $client->campus_events->clubs;
    }

    public function createClub($data)
    {
        $data['created_at'] = new MongoDB\BSON\UTCDateTime;
        $this->collection->insertOne($data);

        return $data;
    }

    public function getClubById($id)
    {
        return $this->collection->findOne(['id' => $id]);
    }

    public function getAllClubs()
    {
        $cursor = $this->collection->find();

        return iterator_to_array($cursor);
    }
}
