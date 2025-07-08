<?php

require_once __DIR__ . '/../utils/exceptions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;


class EventSchema
{
  public static function getFieldDefinitions(): array
  {
    return [
      'title' => ['type' => 'string', 'default' => '', 'min_length' => 3, 'max_length' => 200],
      'description' => ['type' => 'string', 'default' => '', 'min_length' => 10, 'max_length' => 2000],
      'club_id' => ['type' => 'objectid', 'required' => true],
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

    // Add timestamps
    $event['created_at'] = $now;
    $event['updated_at'] = $now;

    return $event;
  }

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

    $updateData['updated_at'] = new UTCDateTime();
    return $updateData;
  }

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
        } else {
          throw new InvalidArgumentException("Invalid date type for field '{$fieldName}', expected string or timestamp");
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

  // Helper method to validate data without mapping (useful for API validation)
  public static function validate(array $data): array
  {
    try {
      self::mapAndValidate($data);
      return [];
    } catch (ValidationException $e) {
      return $e->getErrors();
    }
  }

  // Helper method to validate update data
  public static function validateUpdate(array $data): array
  {
    try {
      self::mapForUpdate($data);
      return [];
    } catch (ValidationException $e) {
      return $e->getErrors();
    }
  }
}
