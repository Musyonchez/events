<?php
/**
 * USIU Events Management System - Event Model
 * 
 * Event data model providing comprehensive CRUD operations, registration management,
 * and event lifecycle functionality for the USIU Events system. Handles event creation,
 * updates, user registration, and various filtering and search capabilities.
 * 
 * Features:
 * - Event creation and management
 * - User registration for events
 * - Event status management (draft, published, cancelled)
 * - Registration capacity and deadline handling
 * - Event search and filtering
 * - Registration history tracking
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

require_once __DIR__ . '/../schemas/Event.php';

use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Event Model Class
 * 
 * Manages all event-related database operations including CRUD operations,
 * user registration, and event lifecycle management.
 */
class EventModel
{
  /**
   * MongoDB collection instance for event documents
   */
  private Collection $collection;

  /**
   * Initialize event model with MongoDB collection
   * 
   * @param Collection $collection MongoDB collection for events
   */
  public function __construct(Collection $collection)
  {
    $this->collection = $collection;
  }

  /**
   * Create new event with validation
   * 
   * @param array $data Event data to create
   * @return ObjectId Generated event ID
   * @throws Exception On validation failure or database error
   */
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
  public function list(array $filters = [], int $limit = 50, int $skip = 0, array $sortOptions = ['event_date' => 1]): array
  {
    $options = [
      'limit' => $limit,
      'skip' => $skip,
      'sort' => $sortOptions
    ];

    $cursor = $this->collection->find($filters, $options);
    $events = iterator_to_array($cursor);

    // Convert BSON documents to arrays
    return array_map(fn($doc) => $doc->getArrayCopy(), $events);
  }

  // Count all events with optional filters
  public function count(array $filters = []): int
  {
    return $this->collection->countDocuments($filters);
  }

  // Register a user for an event
  public function registerUser(string $eventId, string $userId): bool
  {
    try {
      $event = $this->findById($eventId);
      if (!$event) {
        throw new Exception("Event not found");
      }

      if ($event['max_attendees'] > 0 && $event['current_registrations'] >= $event['max_attendees']) {
        throw new Exception("Event is full");
      }

      // Check if user is already registered
      if (in_array(new ObjectId($userId), $event['registered_users']->getArrayCopy())) {
        throw new Exception("User is already registered for this event");
      }

      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($eventId)],
        [
          '$addToSet' => ['registered_users' => new ObjectId($userId)],
          '$inc' => ['current_registrations' => 1]
        ]
      );

      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to register user for event: " . $e->getMessage());
    }
  }
}
