<?php

// api/actions/UserActions.php

require_once __DIR__.'/../../db.php'; // returns the MongoDB client

class UserActions
{
    private $collection;

    public function __construct()
    {
        $client = require __DIR__.'/../../db.php';
        $this->collection = $client->campus_events->users;
    }

    public function createUser($data)
    {
        $now = new MongoDB\BSON\UTCDateTime;

        // Add timestamps
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        // Insert into MongoDB
        $result = $this->collection->insertOne($data);

        return $result->getInsertedId();
    }

    public function getAllUsers()
    {
        $cursor = $this->collection->find([], [
            'projection' => [
                'password_hash' => 0, // Hide password
            ],
        ]);

        return iterator_to_array($cursor);
    }

    public function getUserById($id)
    {
        return $this->collection->findOne(['id' => $id], [
            'projection' => ['password_hash' => 0],
        ]);
    }

    public function findByEmail($email)
    {
        return $this->collection->findOne(['email' => $email]);
    }

    public function updateProfileImage($userId, $imageUrl)
    {
        return $this->collection->updateOne(
            ['id' => $userId],
            ['$set' => ['profile_image' => $imageUrl]]
        );
    }

    public function updateUser($id, $data)
    {
        $data['updated_at'] = new MongoDB\BSON\UTCDateTime;
        $result = $this->collection->updateOne(
            ['id' => $id],
            ['$set' => $data]
        );

        return $result->getModifiedCount();
    }

    public function deleteUser($id)
    {
        $result = $this->collection->deleteOne(['id' => $id]);

        return $result->getDeletedCount();
    }
}
