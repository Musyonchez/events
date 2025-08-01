<?php
/**
 * USIU Events Management System - Event Schema Validation
 * 
 * Comprehensive event data structure and validation schema for the USIU Events
 * system. Provides field definitions, type validation, and business rule
 * enforcement for event management operations.
 * 
 * Features:
 * - Event field definitions and validation rules
 * - Date and time validation with timezone handling
 * - Registration capacity and fee validation
 * - Event status lifecycle management
 * - Image gallery and media validation
 * - Complex object and array field handling
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

require_once __DIR__ . '/../utils/exceptions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Event Schema Class
 * 
 * Manages event data validation, type conversion, and MongoDB document mapping
 * with comprehensive field definitions and business rule validation.
 */
class EventSchema
{
  /**
   * Get event field definitions with validation rules and constraints
   * 
   * @return array Field definitions with type, validation, and constraint rules
   */
  public static function getFieldDefinitions(): array
  {
    return [
      'title' => ['type' => 'string', 'default' => '', 'min_length' => 3, 'max_length' => 200],
      'description' => ['type' => 'string', 'default' => '', 'min_length' => 10, 'max_length' => 2000],
      'club_id' => ['type' => 'objectid', 'required' => true],
      'created_by' => ['type' => 'objectid', 'required' => true],
      'event_date' => ['type' => 'datetime', 'required' => true],
      'end_date' => ['type' => 'datetime', 'nullable' => true],
      'location' => ['type' => 'string', 'default' => '', 'min_length' => 2, 'max_length' => 200],
      'venue_capacity' => ['type' => 'int', 'default' => 0, 'min' => 0, 'max' => 50000],
      'registration_required' => ['type' => 'bool', 'default' => false],
      'registration_deadline' => ['type' => 'datetime', 'nullable' => true],
      'registration_fee' => ['type' => 'float', 'default' => 0, 'min' => 0, 'max' => 10000],
      'max_attendees' => ['type' => 'int', 'default' => 0, 'min' => 0, 'max' => 50000],
      'current_registrations' => ['type' => 'int', 'default' => 0, 'min' => 0],
      'banner_image' => ['type' => 'string', 'default' => '', 'max_length' => 500],
      'gallery' => ['type' => 'string_array', 'default' => [], 'max_items' => 20],
      'category' => ['type' => 'string', 'default' => '', 'max_length' => 100],
      'tags' => ['type' => 'string_array', 'default' => [], 'max_items' => 10],
      'status' => ['type' => 'string', 'default' => 'draft', 'allowed' => ['draft', 'published', 'cancelled', 'completed']],
      'featured' => ['type' => 'bool', 'default' => false],
      'registered_users' => ['type' => 'objectid_array', 'default' => []],
      'social_media' => ['type' => 'object', 'required' => false, 'default' => []],
    ];
  }

  /**
   * Map and validate event data for creation with comprehensive validation
   * 
   * @param array $data Raw event data to validate and map
   * @return array Validated and mapped event data
   * @throws ValidationException On validation failure with detailed error messages
   */
  public static function mapAndValidate(array $data): array
  {
    $now = new UTCDateTime();
    $definitions = self::getFieldDefinitions();
    $event = [];
    $errors = [];

    foreach ($definitions as $field => $config) {
      $value = $data[$field] ?? null;

      // Check required fields
      if (($config['required'] ?? false) && ($value === null || $value === '')) {
        $errors[$field] = "Field '{$field}' is required";
        continue;
      }

      // Use default if value is null/empty and not nullable
      if ($value === null || $value === '') {
        if ($config['nullable'] ?? false) {
          continue; // Skip nullable fields
        }
        $value = $config['default'] ?? null;
      }

      // Type casting and validation
      try {
        $castedValue = self::castValue($value, $config, $field);
        if ($castedValue !== null) {
          $event[$field] = $castedValue;
        }
      } catch (InvalidArgumentException $e) {
        $errors[$field] = $e->getMessage();
      }
    }

    if (!empty($errors)) {
      throw new ValidationException($errors);
    }

    // Business logic validation
    $businessErrors = self::validateBusinessLogic($event);
    if (!empty($businessErrors)) {
      throw new ValidationException($businessErrors);
    }

    // Add timestamps
    $event['created_at'] = $now;
    $event['updated_at'] = $now;

    return $event;
  }

  /**
   * Map and validate event data for updates with selective field validation
   * 
   * @param array $data Event data to update
   * @return array Validated update data
   * @throws ValidationException On validation failure
   */
  public static function mapForUpdate(array $data): array
  {
    $definitions = self::getFieldDefinitions();
    $updateData = [];
    $errors = [];

    foreach ($data as $field => $value) {
      if (!isset($definitions[$field])) {
        continue; // Skip unknown fields
      }

      $config = $definitions[$field];

      // Handle explicit null values for nullable fields
      if ($value === null && ($config['nullable'] ?? false)) {
        $updateData[$field] = null;
        continue;
      }

      // Skip null values for non-nullable fields (don't update)
      if ($value === null) {
        continue;
      }

      // Type casting and validation
      try {
        $castedValue = self::castValue($value, $config, $field);
        if ($castedValue !== null) {
          $updateData[$field] = $castedValue;
        }
      } catch (InvalidArgumentException $e) {
        $errors[$field] = $e->getMessage();
      }
    }

    if (!empty($errors)) {
      throw new ValidationException($errors);
    }

    // Business logic validation for updates (if applicable fields are being updated)
    if (isset($updateData['event_date']) || isset($updateData['end_date']) || isset($updateData['registration_deadline']) || 
        isset($updateData['venue_capacity']) || isset($updateData['max_attendees'])) {
      $businessErrors = self::validateBusinessLogic($updateData);
      if (!empty($businessErrors)) {
        throw new ValidationException($businessErrors);
      }
    }

    $updateData['updated_at'] = new UTCDateTime();
    return $updateData;
  }

  /**
   * Cast and validate field values according to configuration rules
   * 
   * @param mixed $value Value to cast and validate
   * @param array $config Field configuration with type and constraints
   * @param string $fieldName Field name for error reporting
   * @return mixed Casted and validated value
   * @throws InvalidArgumentException On type casting or validation failure
   */
  private static function castValue($value, array $config, string $fieldName)
  {
    if ($value === null) {
      return null;
    }

    switch ($config['type']) {
      case 'objectid':
        try {
          return new ObjectId($value);
        } catch (Exception $e) {
          throw new InvalidArgumentException("Invalid ObjectId format for field '{$fieldName}': {$value}");
        }

      case 'datetime':
        if (is_string($value)) {
          $timestamp = strtotime($value);
          if ($timestamp === false) {
            throw new InvalidArgumentException("Invalid date format for field '{$fieldName}': {$value}");
          }
          return new UTCDateTime($timestamp * 1000);
        } elseif (is_int($value)) {
          return new UTCDateTime($value * 1000);
        } elseif ($value instanceof DateTime) {
          return new UTCDateTime($value->getTimestamp() * 1000);
        } elseif ($value instanceof UTCDateTime) {
          return $value; // Already in correct format
        } else {
          throw new InvalidArgumentException("Invalid date type for field '{$fieldName}', expected string, timestamp, or DateTime object");
        }

      case 'int':
        if (!is_numeric($value)) {
          throw new InvalidArgumentException("Invalid integer for field '{$fieldName}': {$value}");
        }
        $intValue = (int) $value;
        if (isset($config['min']) && $intValue < $config['min']) {
          throw new InvalidArgumentException("Value for field '{$fieldName}' must be at least {$config['min']}");
        }
        if (isset($config['max']) && $intValue > $config['max']) {
          throw new InvalidArgumentException("Value for field '{$fieldName}' must be at most {$config['max']}");
        }
        return $intValue;

      case 'float':
        if (!is_numeric($value)) {
          throw new InvalidArgumentException("Invalid float for field '{$fieldName}': {$value}");
        }
        $floatValue = (float) $value;
        if (isset($config['min']) && $floatValue < $config['min']) {
          throw new InvalidArgumentException("Value for field '{$fieldName}' must be at least {$config['min']}");
        }
        if (isset($config['max']) && $floatValue > $config['max']) {
          throw new InvalidArgumentException("Value for field '{$fieldName}' must be at most {$config['max']}");
        }
        return $floatValue;

      case 'bool':
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;

      case 'objectid_array':
        if (!is_array($value)) {
          throw new InvalidArgumentException("Invalid array for field '{$fieldName}', expected array");
        }
        $validatedArray = [];
        foreach ($value as $index => $item) {
          try {
            $validatedArray[] = new ObjectId($item);
          } catch (Exception $e) {
            throw new InvalidArgumentException("Invalid ObjectId format in '{$fieldName}' at index {$index}");
          }
        }
        return $validatedArray;

      case 'string_array':
        if (!is_array($value)) {
          throw new InvalidArgumentException("Invalid array for field '{$fieldName}', expected array");
        }

        // Validate each item is a string
        $validatedArray = [];
        foreach ($value as $index => $item) {
          if (!is_string($item)) {
            throw new InvalidArgumentException("All items in '{$fieldName}' must be strings, found " . gettype($item) . " at index {$index}");
          }

          // Trim and validate string length
          $trimmedItem = trim($item);
          if (strlen($trimmedItem) === 0) {
            continue; // Skip empty strings
          }

          if (strlen($trimmedItem) > 200) {
            throw new InvalidArgumentException("Items in '{$fieldName}' must be 200 characters or less");
          }

          $validatedArray[] = $trimmedItem;
        }

        // Check max items limit
        if (isset($config['max_items']) && count($validatedArray) > $config['max_items']) {
          throw new InvalidArgumentException("'{$fieldName}' can have at most {$config['max_items']} items");
        }

        return $validatedArray;

      case 'array':
        if (!is_array($value)) {
          throw new InvalidArgumentException("Invalid array for field '{$fieldName}', expected array");
        }
        return $value;

      case 'object':
        if (!is_array($value)) {
          throw new InvalidArgumentException("Invalid object for field '{$fieldName}': expected array");
        }
        return $value;

      case 'string':
      default:
        $stringValue = (string) $value;
        if (isset($config['allowed']) && !in_array($stringValue, $config['allowed'], true)) {
          $allowed = implode(', ', $config['allowed']);
          throw new InvalidArgumentException("Invalid value for field '{$fieldName}'. Allowed values: {$allowed}");
        }
        if (isset($config['min_length']) && strlen($stringValue) < $config['min_length']) {
          throw new InvalidArgumentException("Value for field '{$fieldName}' must be at least {$config['min_length']} characters");
        }
        if (isset($config['max_length']) && strlen($stringValue) > $config['max_length']) {
          throw new InvalidArgumentException("Value for field '{$fieldName}' must be at most {$config['max_length']} characters");
        }
        return $stringValue;
    }
  }

  /**
   * Validate event data without mapping (useful for API validation)
   * 
   * @param array $data Event data to validate
   * @return array Validation errors (empty if valid)
   */
  public static function validate(array $data): array
  {
    try {
      self::mapAndValidate($data);
      return [];
    } catch (ValidationException $e) {
      return $e->getErrors();
    }
  }

  /**
   * Validate update data without mapping
   * 
   * @param array $data Update data to validate
   * @return array Validation errors (empty if valid)
   */
  public static function validateUpdate(array $data): array
  {
    try {
      self::mapForUpdate($data);
      return [];
    } catch (ValidationException $e) {
      return $e->getErrors();
    }
  }

  /**
   * Validate business logic rules for event data
   * 
   * @param array $event Event data to validate
   * @return array Validation errors (empty if valid)
   */
  private static function validateBusinessLogic(array $event): array
  {
    $errors = [];

    // 1. Event date must be in the future
    if (isset($event['event_date'])) {
      $eventDate = $event['event_date'];
      if ($eventDate instanceof UTCDateTime) {
        $eventDateTime = $eventDate->toDateTime();
      } elseif ($eventDate instanceof DateTime) {
        $eventDateTime = $eventDate;
      } else {
        $eventDateTime = new DateTime($eventDate);
      }
      
      if ($eventDateTime <= new DateTime()) {
        $errors['event_date'] = 'Event date must be in the future';
      }
    }

    // 2. End date must be after event date
    if (isset($event['end_date']) && isset($event['event_date'])) {
      $eventDate = $event['event_date'];
      $endDate = $event['end_date'];
      
      // Convert to DateTime objects for comparison
      if ($eventDate instanceof UTCDateTime) {
        $eventDateTime = $eventDate->toDateTime();
      } elseif ($eventDate instanceof DateTime) {
        $eventDateTime = $eventDate;
      } else {
        $eventDateTime = new DateTime($eventDate);
      }
      
      if ($endDate instanceof UTCDateTime) {
        $endDateTime = $endDate->toDateTime();
      } elseif ($endDate instanceof DateTime) {
        $endDateTime = $endDate;
      } else {
        $endDateTime = new DateTime($endDate);
      }
      
      if ($endDateTime <= $eventDateTime) {
        $errors['end_date'] = 'End date must be after event start date';
      }
    }

    // 3. Registration deadline must be before event date
    if (isset($event['registration_deadline']) && isset($event['event_date'])) {
      $eventDate = $event['event_date'];
      $deadline = $event['registration_deadline'];
      
      // Convert to DateTime objects for comparison
      if ($eventDate instanceof UTCDateTime) {
        $eventDateTime = $eventDate->toDateTime();
      } elseif ($eventDate instanceof DateTime) {
        $eventDateTime = $eventDate;
      } else {
        $eventDateTime = new DateTime($eventDate);
      }
      
      if ($deadline instanceof UTCDateTime) {
        $deadlineDateTime = $deadline->toDateTime();
      } elseif ($deadline instanceof DateTime) {
        $deadlineDateTime = $deadline;
      } else {
        $deadlineDateTime = new DateTime($deadline);
      }
      
      if ($deadlineDateTime >= $eventDateTime) {
        $errors['registration_deadline'] = 'Registration deadline must be before event date';
      }
    }

    // 4. Max attendees cannot exceed venue capacity
    if (isset($event['max_attendees']) && isset($event['venue_capacity'])) {
      if ($event['max_attendees'] > $event['venue_capacity']) {
        $errors['max_attendees'] = 'Maximum attendees cannot exceed venue capacity';
      }
    }

    return $errors;
  }
}
