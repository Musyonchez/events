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
      return $event ? $this->convertBSONToArray($event->getArrayCopy()) : null;
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
      // Validate ObjectId format first
      $objectId = new ObjectId($id);
      
      // Check if event exists
      $existingEvent = $this->collection->findOne(['_id' => $objectId]);
      if (!$existingEvent) {
        return ['success' => false, 'errors' => ['event' => 'Event not found']];
      }
      
      $updateData = EventSchema::mapForUpdate($data);
      $updateResult = $this->collection->updateOne(
        ['_id' => $objectId],
        ['$set' => $updateData]
      );

      return [
        'success' => true,
        'modified' => $updateResult->getModifiedCount() > 0
      ];
    } catch (ValidationException $e) {
      return ['success' => false, 'errors' => $e->getErrors()];
    } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
      // Let ObjectId format errors throw as exceptions (don't catch them)
      throw new Exception("Invalid event ID format: {$id}");
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
  public function list(array $filters = [], int $limit = 50, int $skip = 0, array $sortOptions = ['created_at' => -1]): array
  {
    // Handle edge case: limit 0 should return empty array
    if ($limit === 0) {
      return [];
    }
    
    $options = [
      'limit' => $limit,
      'skip' => $skip,
      'sort' => $sortOptions
    ];

    $cursor = $this->collection->find($filters, $options);
    $events = iterator_to_array($cursor);

    // Convert BSON documents to arrays with proper nested array conversion
    return array_map(fn($doc) => $this->convertBSONToArray($doc->getArrayCopy()), $events);
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
      $registeredUsers = $event['registered_users'] ?? [];
      $userObjectId = new ObjectId($userId);
      
      // Convert to string for comparison since registered_users are now PHP arrays with ObjectId objects
      $isAlreadyRegistered = false;
      foreach ($registeredUsers as $registeredUserId) {
        if ($registeredUserId instanceof ObjectId && $registeredUserId->__toString() === $userObjectId->__toString()) {
          $isAlreadyRegistered = true;
          break;
        }
      }
      
      if ($isAlreadyRegistered) {
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

  // Unregister a user from an event
  public function unregisterUserFromEvent(string $eventId, string $userId): array
  {
    try {
      $event = $this->findById($eventId);
      if (!$event) {
        return ['success' => false, 'error' => 'Event not found'];
      }

      // Check if user is registered
      $registeredUsers = $event['registered_users'] ?? [];
      $userObjectId = new ObjectId($userId);
      $isRegistered = false;

      foreach ($registeredUsers as $registeredUserId) {
        if ($registeredUserId instanceof ObjectId && $registeredUserId->__toString() === $userObjectId->__toString()) {
          $isRegistered = true;
          break;
        }
      }

      if (!$isRegistered) {
        return ['success' => false, 'error' => 'User is not registered for this event'];
      }

      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($eventId)],
        [
          '$pull' => ['registered_users' => new ObjectId($userId)],
          '$inc' => ['current_registrations' => -1]
        ]
      );

      return [
        'success' => $updateResult->getModifiedCount() > 0,
        'message' => $updateResult->getModifiedCount() > 0 ? 'User unregistered successfully' : 'Failed to unregister user'
      ];
    } catch (Exception $e) {
      return ['success' => false, 'error' => 'Failed to unregister user: ' . $e->getMessage()];
    }
  }

  // Find event by title
  public function findByTitle(string $title): ?array
  {
    try {
      $result = $this->collection->findOne(['title' => $title]);
      return $result ? $this->convertBSONToArray($result->toArray()) : null;
    } catch (Exception $e) {
      return null;
    }
  }

  /**
   * Convert BSON document to proper PHP arrays, handling nested objects
   * 
   * @param array $data BSON document data
   * @return array Converted data with proper PHP arrays
   */
  private function convertBSONToArray(array $data): array
  {
    $converted = [];
    
    foreach ($data as $key => $value) {
      if ($value instanceof \MongoDB\Model\BSONArray) {
        // Convert BSON array to PHP array
        $converted[$key] = iterator_to_array($value);
      } elseif ($value instanceof \MongoDB\Model\BSONDocument) {
        // Convert BSON document to PHP array recursively
        $converted[$key] = $this->convertBSONToArray($value->getArrayCopy());
      } elseif (is_array($value)) {
        // Recursively convert nested arrays
        $converted[$key] = $this->convertBSONToArray($value);
      } else {
        // Keep primitive values as-is
        $converted[$key] = $value;
      }
    }
    
    return $converted;
  }
}
