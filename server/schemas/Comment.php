<?php

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class ValidationException extends Exception
{
  private array $errors;

  public function __construct(array $errors, string $message = "Validation failed")
  {
    $this->errors = $errors;
    parent::__construct($message);
  }

  public function getErrors(): array
  {
    return $this->errors;
  }
}

class CommentSchema
{
  public static function getFieldDefinitions(): array
  {
    return [
      'event_id' => ['type' => 'objectid', 'required' => true],
      'user_id' => ['type' => 'objectid', 'required' => true],
      'content' => ['type' => 'string', 'required' => true, 'min_length' => 1, 'max_length' => 1000],
      'parent_comment_id' => ['type' => 'objectid', 'nullable' => true],
      'status' => ['type' => 'string', 'default' => 'pending', 'allowed' => ['pending', 'approved', 'rejected']],
      'flagged' => ['type' => 'bool', 'default' => false],
    ];
  }

  public static function mapAndValidate(array $data): array
  {
    $now = new UTCDateTime();
    $definitions = self::getFieldDefinitions();
    $comment = [];
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
          $comment[$field] = $castedValue;
        }
      } catch (InvalidArgumentException $e) {
        $errors[$field] = $e->getMessage();
      }
    }

    // Additional validation rules
    if (!empty($comment['content'])) {
      $contentErrors = self::validateContent($comment['content']);
      $errors = array_merge($errors, $contentErrors);
    }

    if (!empty($errors)) {
      throw new ValidationException($errors);
    }

    // Add timestamps
    $comment['created_at'] = $now;
    $comment['updated_at'] = $now;

    return $comment;
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

      // Don't allow updating certain fields
      if (in_array($field, ['event_id', 'user_id', 'parent_comment_id'])) {
        continue; // Skip reference fields that shouldn't be updated
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

    // Additional validation for update
    if (!empty($updateData['content'])) {
      $contentErrors = self::validateContent($updateData['content']);
      $errors = array_merge($errors, $contentErrors);
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

      case 'string':
      default:
        $stringValue = trim((string) $value);
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

  private static function validateContent(string $content): array
  {
    $errors = [];
    
    // Check for empty content after trimming
    if (empty(trim($content))) {
      $errors['content'] = 'Comment content cannot be empty';
      return $errors;
    }

    // Basic profanity/inappropriate content check (extend as needed)
    $inappropriateWords = [
      'spam', 'scam', 'fake', 'fraud', 'hack', 'illegal',
      // Add more inappropriate words as needed
    ];

    $contentLower = strtolower($content);
    foreach ($inappropriateWords as $word) {
      if (strpos($contentLower, $word) !== false) {
        $errors['content'] = 'Comment contains inappropriate content and will be reviewed';
        break;
      }
    }

    // Check for excessive repetition (basic spam detection)
    if (preg_match('/(.)\1{10,}/', $content)) {
      $errors['content'] = 'Comment contains excessive repetition';
    }

    // Check for excessive caps (possible spam/shouting)
    $capsCount = preg_match_all('/[A-Z]/', $content);
    $totalChars = strlen(preg_replace('/[^a-zA-Z]/', '', $content));
    if ($totalChars > 20 && ($capsCount / $totalChars) > 0.7) {
      $errors['content'] = 'Comment contains excessive capital letters';
    }

    return $errors;
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

  // Helper method to validate comment moderation action
  public static function validateModerationAction(string $action): array
  {
    $errors = [];
    $allowedActions = ['approve', 'reject', 'flag', 'unflag'];
    
    if (!in_array($action, $allowedActions)) {
      $errors['action'] = 'Invalid moderation action. Allowed: ' . implode(', ', $allowedActions);
    }

    return $errors;
  }

  // Helper method to sanitize content for display
  public static function sanitizeContent(string $content): string
  {
    // Remove any HTML tags
    $content = strip_tags($content);
    
    // Convert special characters to HTML entities
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    
    // Convert URLs to clickable links (basic implementation)
    $content = preg_replace(
      '/(https?:\/\/[^\s]+)/',
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
      $content
    );
    
    return $content;
  }
}
