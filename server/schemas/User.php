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

class UserSchema
{
  public static function getFieldDefinitions(): array
  {
    return [
      'student_id' => ['type' => 'string', 'required' => true, 'min_length' => 8, 'max_length' => 20],
      'first_name' => ['type' => 'string', 'required' => true, 'min_length' => 2, 'max_length' => 50],
      'last_name' => ['type' => 'string', 'required' => true, 'min_length' => 2, 'max_length' => 50],
      'email' => ['type' => 'email', 'required' => true, 'max_length' => 100],
      'password' => ['type' => 'string', 'required' => true, 'min_length' => 8, 'max_length' => 255],
      'phone' => ['type' => 'string', 'default' => '', 'max_length' => 20],
      'course' => ['type' => 'string', 'default' => '', 'max_length' => 100],
      'year_of_study' => ['type' => 'int', 'default' => 1, 'min' => 1, 'max' => 6],
      'profile_image' => ['type' => 'string', 'default' => '', 'max_length' => 500],
      'role' => ['type' => 'string', 'default' => 'student', 'allowed' => ['student', 'admin', 'club_leader']],
      'status' => ['type' => 'string', 'default' => 'active', 'allowed' => ['active', 'inactive', 'suspended']],
      'last_login' => ['type' => 'datetime', 'nullable' => true],
      'refresh_token' => ['type' => 'string', 'nullable' => true, 'max_length' => 255],
      'refresh_token_expires_at' => ['type' => 'datetime', 'nullable' => true],
    ];
  }

  public static function mapAndValidate(array $data): array
  {
    $now = new UTCDateTime();
    $definitions = self::getFieldDefinitions();
    $user = [];
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
          $user[$field] = $castedValue;
        }
      } catch (InvalidArgumentException $e) {
        $errors[$field] = $e->getMessage();
      }
    }

    // Additional validation rules
    if (!empty($user['email'])) {
      $errors = array_merge($errors, self::validateEmail($user['email']));
    }

    if (!empty($user['student_id'])) {
      $errors = array_merge($errors, self::validateStudentId($user['student_id']));
    }

    if (!empty($user['phone'])) {
      $errors = array_merge($errors, self::validatePhone($user['phone']));
    }

    if (!empty($errors)) {
      throw new ValidationException($errors);
    }

    // Hash password if provided
    if (!empty($user['password'])) {
      $user['password'] = password_hash($user['password'], PASSWORD_DEFAULT);
    }

    // Add timestamps
    $user['created_at'] = $now;
    $user['updated_at'] = $now;

    return $user;
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

    // Additional validation for update
    if (!empty($updateData['email'])) {
      $errors = array_merge($errors, self::validateEmail($updateData['email']));
    }

    if (!empty($updateData['student_id'])) {
      $errors = array_merge($errors, self::validateStudentId($updateData['student_id']));
    }

    if (!empty($updateData['phone'])) {
      $errors = array_merge($errors, self::validatePhone($updateData['phone']));
    }

    if (!empty($errors)) {
      throw new ValidationException($errors);
    }

    // Hash password if being updated
    if (!empty($updateData['password'])) {
      $updateData['password'] = password_hash($updateData['password'], PASSWORD_DEFAULT);
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

  private static function validateEmail(string $email): array
  {
    $errors = [];
    
    // Check if email ends with usiu.ac.ke for students
    if (!str_ends_with(strtolower($email), '@usiu.ac.ke')) {
      $errors['email'] = 'Email must be a valid USIU email address ending with @usiu.ac.ke';
    }

    return $errors;
  }

  private static function validateStudentId(string $studentId): array
  {
    $errors = [];
    
    // Basic format validation (adjust regex as needed for your student ID format)
    if (!preg_match('/^[A-Z]{2,4}\d{4,8}$/', $studentId)) {
      $errors['student_id'] = 'Student ID must follow the format: 2-4 letters followed by 4-8 digits (e.g., USIU2023001)';
    }

    return $errors;
  }

  private static function validatePhone(string $phone): array
  {
    $errors = [];
    
    // Remove spaces and common separators for validation
    $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // Basic Kenyan phone number validation
    if (!preg_match('/^\+254[17]\d{8}$|^0[17]\d{8}$/', $cleanPhone)) {
      $errors['phone'] = 'Phone number must be a valid Kenyan number format (e.g., +254712345678 or 0712345678)';
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

  // Helper method to validate login credentials
  public static function validateLogin(array $data): array
  {
    $errors = [];

    if (empty($data['email'])) {
      $errors['email'] = 'Email is required';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'Invalid email format';
    }

    if (empty($data['password'])) {
      $errors['password'] = 'Password is required';
    }

    return $errors;
  }
}
