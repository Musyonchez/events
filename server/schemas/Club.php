<?php
/**
 * USIU Events Management System - Club Schema Validation
 * 
 * Club data structure and validation schema for the USIU Events system.
 * Provides comprehensive field definitions, validation rules, and business
 * logic enforcement for club management operations.
 * 
 * Features:
 * - Club field definitions and validation rules
 * - Category-based club organization
 * - Membership tracking and validation
 * - Content moderation and profanity filtering
 * - Leadership transfer validation
 * - Public data sanitization
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Validation Exception Class for Club Schema
 * 
 * Custom exception for handling validation errors with detailed error messages
 */
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

/**
 * Club Schema Class
 * 
 * Manages club data validation, type conversion, and MongoDB document mapping
 * with comprehensive field definitions and business rule validation.
 */
class ClubSchema
{
  /**
   * Get club field definitions with validation rules and constraints
   * 
   * @return array Field definitions with type, validation, and constraint rules
   */
  public static function getFieldDefinitions(): array
  {
    return [
      'name' => ['type' => 'string', 'required' => true, 'min_length' => 3, 'max_length' => 100],
      'description' => ['type' => 'string', 'required' => true, 'min_length' => 10, 'max_length' => 1000],
      'category' => ['type' => 'string', 'required' => true, 'allowed' => [
        'Arts & Culture',
        'Academic',
        'Sports',
        'Technology',
        'Business',
        'Community Service',
        'Religious',
        'Professional',
        'Recreation',
        'Special Interest'
      ]],
      'logo' => ['type' => 'string', 'default' => '', 'max_length' => 500],
      'contact_email' => ['type' => 'email', 'required' => true, 'max_length' => 100],
      'leader_id' => ['type' => 'objectid', 'required' => true],
      'members_count' => ['type' => 'int', 'default' => 0, 'min' => 0, 'max' => 10000],
      'status' => ['type' => 'string', 'default' => 'active', 'allowed' => ['active', 'inactive']],
      'members' => ['type' => 'array', 'default' => [], 'items' => ['type' => 'objectid']],
    ];
  }

  /**
   * Map and validate club data for creation with comprehensive validation
   * 
   * @param array $data Raw club data to validate and map
   * @return array Validated and mapped club data
   * @throws ValidationException On validation failure with detailed error messages
   */
  public static function mapAndValidate(array $data): array
  {
    $now = new UTCDateTime();
    $definitions = self::getFieldDefinitions();
    $club = [];
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
          $club[$field] = $castedValue;
        }
      } catch (InvalidArgumentException $e) {
        $errors[$field] = $e->getMessage();
      }
    }

    // Additional validation rules
    $nameErrors = self::validateName($club['name']);
    $errors = array_merge($errors, $nameErrors);

    $emailErrors = self::validateContactEmail($club['contact_email']);
    $errors = array_merge($errors, $emailErrors);

    $descriptionErrors = self::validateDescription($club['description']);
    $errors = array_merge($errors, $descriptionErrors);

    if (!empty($errors)) {
      throw new ValidationException($errors);
    }

    // Add timestamps
    $club['created_at'] = $now;
    $club['updated_at'] = $now;

    return $club;
  }

  /**
   * Map and validate club data for updates with selective field validation
   * 
   * @param array $data Club data to update
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

      // Don't allow updating members_count through this method
      if ($field === 'members_count') {
        continue; // Use dedicated methods for member count updates
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
    if (isset($updateData['name'])) {
      $nameErrors = self::validateName($updateData['name']);
      $errors = array_merge($errors, $nameErrors);
    }

    if (isset($updateData['contact_email'])) {
      $emailErrors = self::validateContactEmail($updateData['contact_email']);
      $errors = array_merge($errors, $emailErrors);
    }

    if (isset($updateData['description'])) {
      $descriptionErrors = self::validateDescription($updateData['description']);
      $errors = array_merge($errors, $descriptionErrors);
    }

    if (!empty($errors)) {
      throw new ValidationException($errors);
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

      case 'email':
        $emailValue = filter_var(trim($value), FILTER_VALIDATE_EMAIL);
        if ($emailValue === false) {
          throw new InvalidArgumentException("Invalid email format for field '{$fieldName}': {$value}");
        }
        if (isset($config['max_length']) && strlen($emailValue) > $config['max_length']) {
          throw new InvalidArgumentException("Email for field '{$fieldName}' must be at most {$config['max_length']} characters");
        }
        return $emailValue;

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

      case 'array':
        if (is_array($value)) {
          $arrayValue = $value;
        } elseif (is_string($value) && ($value === 'Array' || $value === '[]' || empty($value))) {
          // Handle cases where array was incorrectly stored as string
          $arrayValue = [];
        } else {
          throw new InvalidArgumentException("Invalid array for field '{$fieldName}': {$value}");
        }
        
        // Validate array items if specified
        if (isset($config['items'])) {
          $itemConfig = $config['items'];
          foreach ($arrayValue as $index => $item) {
            try {
              $arrayValue[$index] = self::castValue($item, $itemConfig, "{$fieldName}[{$index}]");
            } catch (InvalidArgumentException $e) {
              throw new InvalidArgumentException("Invalid array item at index {$index} for field '{$fieldName}': " . $e->getMessage());
            }
          }
        }
        
        return $arrayValue;

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

  /**
   * Validate club name for appropriateness and policy compliance
   * 
   * @param string $name Club name to validate
   * @return array Validation errors (empty if valid)
   */
  private static function validateName(string $name): array
  {
    $errors = [];
    
    // Check for profanity or inappropriate content
    $inappropriateWords = [
      'hate', 'discrimination', 'illegal', 'scam', 'fake'
      // Add more inappropriate words as needed
    ];

    $nameLower = strtolower($name);
    foreach ($inappropriateWords as $word) {
      if (strpos($nameLower, $word) !== false) {
        $errors['name'] = 'Club name contains inappropriate content';
        break;
      }
    }

    // Check for excessive special characters
    if (preg_match('/[^a-zA-Z0-9\s&\-\']/', $name)) {
      $errors['name'] = 'Club name contains invalid characters. Only letters, numbers, spaces, &, -, and \' are allowed';
    }

    // Check for reserved words
    $reservedWords = ['admin', 'system', 'test', 'usiu', 'university'];
    foreach ($reservedWords as $reserved) {
      if (stripos($name, $reserved) !== false) {
        $errors['name'] = 'Club name cannot contain reserved words';
        break;
      }
    }

    return $errors;
  }

  /**
   * Validate club contact email for USIU domain compliance
   * 
   * @param string $email Contact email to validate
   * @return array Validation errors (empty if valid)
   */
  private static function validateContactEmail(string $email): array
  {
    $errors = [];
    
    // Check if email ends with usiu.ac.ke for official club emails
    if (!str_ends_with(strtolower($email), '@usiu.ac.ke')) {
      $errors['contact_email'] = 'Contact email must be a valid USIU email address ending with @usiu.ac.ke';
    }

    return $errors;
  }

  /**
   * Validate club description for content quality and appropriateness
   * 
   * @param string $description Club description to validate
   * @return array Validation errors (empty if valid)
   */
  private static function validateDescription(string $description): array
  {
    $errors = [];
    
    // Check for empty description after trimming
    if (empty(trim($description))) {
      $errors['description'] = 'Club description cannot be empty';
      return $errors;
    }

    // Check for excessive repetition
    if (preg_match('/(.)\1{20,}/', $description)) {
      $errors['description'] = 'Description contains excessive repetition';
    }

    // Check for excessive caps (unprofessional)
    $capsCount = preg_match_all('/[A-Z]/', $description);
    $totalChars = strlen(preg_replace('/[^a-zA-Z]/', '', $description));
    if ($totalChars > 50 && ($capsCount / $totalChars) > 0.5) {
      $errors['description'] = 'Description contains excessive capital letters';
    }

    // Basic profanity check
    $inappropriateWords = [
      'hate', 'discrimination', 'illegal', 'scam', 'fake', 'fraud'
    ];

    $descriptionLower = strtolower($description);
    foreach ($inappropriateWords as $word) {
      if (strpos($descriptionLower, $word) !== false) {
        $errors['description'] = 'Description contains inappropriate content';
        break;
      }
    }

    return $errors;
  }

  /**
   * Validate club data without mapping (useful for API validation)
   * 
   * @param array $data Club data to validate
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
   * Validate club category against allowed categories
   * 
   * @param string $category Category to validate
   * @return array Validation errors (empty if valid)
   */
  public static function validateCategory(string $category): array
  {
    $errors = [];
    $definitions = self::getFieldDefinitions();
    $allowedCategories = $definitions['category']['allowed'];
    
    if (!in_array($category, $allowedCategories)) {
      $errors['category'] = 'Invalid category. Allowed: ' . implode(', ', $allowedCategories);
    }

    return $errors;
  }

  /**
   * Get all available club categories
   * 
   * @return array List of allowed club categories
   */
  public static function getCategories(): array
  {
    $definitions = self::getFieldDefinitions();
    return $definitions['category']['allowed'];
  }

  /**
   * Validate club leadership transfer data
   * 
   * @param array $data Leadership transfer data
   * @return array Validation errors (empty if valid)
   */
  public static function validateLeadershipTransfer(array $data): array
  {
    $errors = [];

    if (empty($data['new_leader_id'])) {
      $errors['new_leader_id'] = 'New leader ID is required';
    }

    if (empty($data['current_leader_id'])) {
      $errors['current_leader_id'] = 'Current leader ID is required';
    }

    if (!empty($data['new_leader_id']) && !empty($data['current_leader_id'])) {
      if ($data['new_leader_id'] === $data['current_leader_id']) {
        $errors['new_leader_id'] = 'New leader cannot be the same as current leader';
      }
    }

    return $errors;
  }

  /**
   * Sanitize club data for public display by removing sensitive information
   * 
   * @param array $club Complete club data
   * @return array Sanitized club data for public display
   */
  public static function sanitizeForPublic(array $club): array
  {
    // Remove sensitive information for public display
    $publicFields = [
      '_id', 'name', 'description', 'category', 'logo', 
      'members_count', 'status', 'created_at'
    ];

    $sanitized = [];
    foreach ($publicFields as $field) {
      if (isset($club[$field])) {
        $sanitized[$field] = $club[$field];
      }
    }

    return $sanitized;
  }
}
