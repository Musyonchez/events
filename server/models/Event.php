<?php

require_once __DIR__ . '/../schemas/Event.php';

use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use ValidationException;
use EventSchema;


class EventModel
{
  private Collection $collection;

  public function __construct(Collection $collection)
  {
    $this->collection = $collection;
  }

  // Create a new event document
  public function create(array $data): ObjectId
  {
    try {
      $event = EventSchema::mapAndValidate($data);
    } catch (ValidationException $e) {
      throw new Exception("Validation failed: " . json_encode($e->getErrors()));
    }

    $result = $this->collection->insertOne($event);

    if (!$result->isAcknowledged()) {
      throw new Exception("Failed to create event");
    }

    return $result->getInsertedId();
  }

  // Create with validation errors returned instead of thrown
  public function createWithValidation(array $data): array
  {
    try {
      $event = EventSchema::mapAndValidate($data);
      $result = $this->collection->insertOne($event);

      if (!$result->isAcknowledged()) {
        return ['success' => false, 'errors' => ['database' => 'Failed to create event']];
      }

      return ['success' => true, 'id' => $result->getInsertedId()];
    } catch (ValidationException $e) {
      return ['success' => false, 'errors' => $e->getErrors()];
    } catch (Exception $e) {
      return ['success' => false, 'errors' => ['database' => $e->getMessage()]];
    }
  }

  // Find an event by ID
  public function findById(string $id): ?array
  {
    try {
      $event = $this->collection->findOne(['_id' => new ObjectId($id)]);
      return $event ? $event->getArrayCopy() : null;
    } catch (Exception $e) {
      throw new Exception("Invalid event ID format: {$id}");
    }
  }

  // Update an event by ID
  public function update(string $id, array $data): bool
  {
    try {
      $updateData = EventSchema::mapForUpdate($data);
    } catch (ValidationException $e) {
      throw new Exception("Validation failed: " . json_encode($e->getErrors()));
    }

    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => $updateData]
      );

      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to update event: " . $e->getMessage());
    }
  }

  // Update with validation errors returned instead of thrown
  public function updateWithValidation(string $id, array $data): array
  {
    try {
      $updateData = EventSchema::mapForUpdate($data);
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => $updateData]
      );

      return [
        'success' => true,
        'modified' => $updateResult->getModifiedCount() > 0
      ];
    } catch (ValidationException $e) {
      return ['success' => false, 'errors' => $e->getErrors()];
    } catch (Exception $e) {
      return ['success' => false, 'errors' => ['database' => $e->getMessage()]];
    }
  }

  // Delete event by ID
  public function delete(string $id): bool
  {
    try {
      $deleteResult = $this->collection->deleteOne(['_id' => new ObjectId($id)]);
      return $deleteResult->getDeletedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to delete event: " . $e->getMessage());
    }
  }

  // List all events with optional filters, paging etc.
  public function list(array $filters = [], int $limit = 50, int $skip = 0): array
  {
    $options = [
      'limit' => $limit,
      'skip' => $skip,
      'sort' => ['event_date' => 1]
    ];

    $cursor = $this->collection->find($filters, $options);
    $events = iterator_to_array((array)$cursor);

    // Convert BSON documents to arrays
    return array_map(fn($doc) => $doc->getArrayCopy(), $events);
  }
}
