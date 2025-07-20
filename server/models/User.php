<?php
/**
 * USIU Events Management System - User Model
 * 
 * Comprehensive user data model providing CRUD operations, authentication support,
 * email verification, password management, and user search functionality for the
 * USIU Events system using MongoDB as the data store.
 * 
 * Features:
 * - User registration with email verification
 * - Password hashing and authentication
 * - Profile management and updates
 * - Password reset with secure tokens
 * - JWT refresh token management
 * - User search and filtering
 * - Duplicate email/student ID prevention
 * - Comprehensive validation and error handling
 * 
 * Security Features:
 * - Password hashing with PHP's password_hash()
 * - Secure token generation for email verification and password reset
 * - Protection against duplicate registrations
 * - Password exclusion from query results
 * - Token expiration and validation
 * 
 * @author USIU Events Development Team
 * @version 3.0.0
 * @since 2024-01-01
 */

require_once __DIR__ . '/../schemas/User.php';
require_once __DIR__ . '/../utils/email.php';
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use ValidationException;
use UserSchema;

/**
 * User Model Class
 * 
 * Handles all user-related database operations including authentication,
 * profile management, email verification, and password reset functionality.
 */
class UserModel
{
  /**
   * MongoDB collection instance for user documents
   */
  private Collection $collection;

  /**
   * Initialize user model with MongoDB collection
   * 
   * @param Collection $collection MongoDB collection for users
   */
  public function __construct(Collection $collection)
  {
    $this->collection = $collection;
  }

  /**
   * Create a new user account with validation and duplicate checking
   * 
   * @param array $data User registration data
   * @return ObjectId Generated user ID
   * @throws Exception On validation failure or database error
   */
  public function create(array $data): ObjectId
  {
    try {
      // Validate and map user data using schema
      $user = UserSchema::mapAndValidate($data);
    } catch (ValidationException $e) {
      throw new Exception("Validation failed: " . json_encode($e->getErrors()));
    }

    // Check for duplicate email or student_id
    $this->checkDuplicates($user['email'], $user['student_id']);

    // Insert user document into MongoDB
    $result = $this->collection->insertOne($user);
    if (!$result->isAcknowledged()) {
      throw new Exception("Database error: Unable to save user account. Please try again.");
    }

    return $result->getInsertedId();
  }

  /**
   * Create user with comprehensive validation and email verification
   * Returns validation errors instead of throwing exceptions
   * 
   * @param array $data User registration data
   * @return array Success/failure result with errors or user ID
   */
  public function createWithValidation(array $data): array
  {
    try {
      $user = UserSchema::mapAndValidate($data);
      
      // Check for duplicates
      $duplicateErrors = $this->checkDuplicatesValidation($user['email'], $user['student_id']);
      if (!empty($duplicateErrors)) {
        return ['success' => false, 'errors' => $duplicateErrors];
      }

      $result = $this->collection->insertOne($user);
      if (!$result->isAcknowledged()) {
        return ['success' => false, 'errors' => ['database' => 'Database error: Unable to save user account. Please try again or contact support.']];
      }

      $userId = $result->getInsertedId();
      $token = $this->generateVerificationToken((string)$userId);

      if ($token) {
        // Send verification email
        $verificationLink = "http://localhost:3000/pages/verify-email.html?token={$token}";
        $emailBody = "Please click on the following link to verify your email address: <a href='{$verificationLink}'>{$verificationLink}</a>";
        send_email($user['email'], 'Verify Your Email Address', $emailBody);
      }

      return ['success' => true, 'id' => $userId];
    } catch (ValidationException $e) {
      return ['success' => false, 'errors' => $e->getErrors()];
    } catch (Exception $e) {
      return ['success' => false, 'errors' => ['database' => $e->getMessage()]];
    }
  }

  /**
   * Find user by MongoDB ObjectId (excludes password for security)
   * 
   * @param string $id User ID as string
   * @return array|null User data without password, or null if not found
   * @throws Exception On invalid ID format
   */
  public function findById(string $id): ?array
  {
    try {
      $user = $this->collection->findOne(['_id' => new ObjectId($id)]);
      if ($user) {
        $userData = $user->getArrayCopy();
        // Remove password from response for security
        unset($userData['password']);
        return $userData;
      }
      return null;
    } catch (Exception $e) {
      throw new Exception("Invalid user ID format: {$id}");
    }
  }

  /**
   * Find user by email address (includes password for authentication)
   * 
   * @param string $email User email address
   * @return array|null Complete user data including password, or null
   */
  public function findByEmail(string $email): ?array
  {
    $user = $this->collection->findOne(['email' => $email]);
    return $user ? $user->getArrayCopy() : null;
  }

  // Find a user by student ID
  public function findByStudentId(string $studentId): ?array
  {
    $user = $this->collection->findOne(['student_id' => $studentId]);
    if ($user) {
      $userData = $user->getArrayCopy();
      unset($userData['password']);
      return $userData;
    }
    return null;
  }

  // Update a user by ID
  public function update(string $id, array $data): bool
  {
    try {
      $updateData = UserSchema::mapForUpdate($data);
    } catch (ValidationException $e) {
      throw new Exception("Validation failed: " . json_encode($e->getErrors()));
    }

    // Check for duplicates if email or student_id is being updated
    if (isset($updateData['email']) || isset($updateData['student_id'])) {
      $currentUser = $this->collection->findOne(['_id' => new ObjectId($id)]);
      if (!$currentUser) {
        throw new Exception("User not found");
      }

      $email = $updateData['email'] ?? $currentUser['email'];
      $studentId = $updateData['student_id'] ?? $currentUser['student_id'];
      
      $this->checkDuplicates($email, $studentId, $id);
    }

    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => $updateData]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to update user: " . $e->getMessage());
    }
  }

  // Update with validation errors returned instead of thrown
  public function updateWithValidation(string $id, array $data): array
  {
    try {
      $updateData = UserSchema::mapForUpdate($data);
      
      // Check for duplicates if email or student_id is being updated
      if (isset($updateData['email']) || isset($updateData['student_id'])) {
        $currentUser = $this->collection->findOne(['_id' => new ObjectId($id)]);
        if (!$currentUser) {
          return ['success' => false, 'errors' => ['user' => 'User not found']];
        }

        $email = $updateData['email'] ?? $currentUser['email'];
        $studentId = $updateData['student_id'] ?? $currentUser['student_id'];
        
        $duplicateErrors = $this->checkDuplicatesValidation($email, $studentId, $id);
        if (!empty($duplicateErrors)) {
          return ['success' => false, 'errors' => $duplicateErrors];
        }
      }

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

  // Delete user by ID
  public function delete(string $id): bool
  {
    try {
      $deleteResult = $this->collection->deleteOne(['_id' => new ObjectId($id)]);
      return $deleteResult->getDeletedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to delete user: " . $e->getMessage());
    }
  }

  // List all users with optional filters, paging etc.
  public function list(array $filters = [], int $limit = 50, int $skip = 0): array
  {
    $options = [
      'limit' => $limit,
      'skip' => $skip,
      'sort' => ['created_at' => -1], // Most recent first
      'projection' => ['password' => 0] // Exclude password field
    ];

    $cursor = $this->collection->find($filters, $options);
    $users = iterator_to_array($cursor);

    // Convert BSON documents to arrays
    return array_map(fn($doc) => $doc->getArrayCopy(), $users);
  }

  // Get total count of users (useful for pagination)
  public function count(array $filters = []): int
  {
    return $this->collection->countDocuments($filters);
  }

  // Update last login timestamp
  public function updateLastLogin(string $id): bool
  {
    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => ['last_login' => new UTCDateTime()]]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to update last login: " . $e->getMessage());
    }
  }

  /**
   * Change user password with old password verification
   * 
   * @param string $id User ID
   * @param string $oldPassword Current password for verification
   * @param string $newPassword New password to set
   * @return array Success/failure result with validation errors
   */
  public function changePassword(string $id, string $oldPassword, string $newPassword): array
  {
    try {
      $user = $this->collection->findOne(['_id' => new ObjectId($id)]);
      if (!$user) {
        return ['success' => false, 'errors' => ['user' => 'User not found']];
      }

      // Verify old password
      if (!password_verify($oldPassword, $user['password'])) {
        return ['success' => false, 'errors' => ['old_password' => 'Current password is incorrect']];
      }

      // Validate new password
      if (strlen($newPassword) < 8) {
        return ['success' => false, 'errors' => ['new_password' => 'New password must be at least 8 characters']];
      }

      // Update password
      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => [
          'password' => $hashedPassword,
          'updated_at' => new UTCDateTime()
        ]]
      );

      return [
        'success' => true,
        'modified' => $updateResult->getModifiedCount() > 0
      ];
    } catch (Exception $e) {
      return ['success' => false, 'errors' => ['database' => $e->getMessage()]];
    }
  }

  /**
   * Search users by name, email, student ID, or course (case-insensitive)
   * 
   * @param string $searchTerm Search query
   * @param int $limit Maximum number of results
   * @return array Array of matching users (passwords excluded)
   */
  public function search(string $searchTerm, int $limit = 20): array
  {
    $regex = new \MongoDB\BSON\Regex($searchTerm, 'i'); // Case-insensitive search
    
    $filter = [
      '$or' => [
        ['first_name' => $regex],
        ['last_name' => $regex],
        ['email' => $regex],
        ['student_id' => $regex],
        ['course' => $regex]
      ]
    ];

    $options = [
      'limit' => $limit,
      'projection' => ['password' => 0], // Exclude password
      'sort' => ['first_name' => 1, 'last_name' => 1]
    ];

    $cursor = $this->collection->find($filter, $options);
    $users = iterator_to_array($cursor);

    return array_map(fn($doc) => $doc->getArrayCopy(), $users);
  }

  // Check for duplicate email or student_id (throws exception)
  private function checkDuplicates(string $email, string $studentId, string $excludeId = null): void
  {
    $emailFilter = ['email' => $email];
    $studentIdFilter = ['student_id' => $studentId];

    if ($excludeId) {
      $emailFilter['_id'] = ['$ne' => new ObjectId($excludeId)];
      $studentIdFilter['_id'] = ['$ne' => new ObjectId($excludeId)];
    }

    if ($this->collection->findOne($emailFilter)) {
      throw new Exception("User with email '{$email}' already exists");
    }

    if ($this->collection->findOne($studentIdFilter)) {
      throw new Exception("User with student ID '{$studentId}' already exists");
    }
  }

  // Check for duplicates and return validation errors
  private function checkDuplicatesValidation(string $email, string $studentId, string $excludeId = null): array
  {
    $errors = [];
    
    $emailFilter = ['email' => $email];
    $studentIdFilter = ['student_id' => $studentId];

    if ($excludeId) {
      $emailFilter['_id'] = ['$ne' => new ObjectId($excludeId)];
      $studentIdFilter['_id'] = ['$ne' => new ObjectId($excludeId)];
    }

    if ($this->collection->findOne($emailFilter)) {
      $errors['email'] = "User with email '{$email}' already exists";
    }

    if ($this->collection->findOne($studentIdFilter)) {
      $errors['student_id'] = "User with student ID '{$studentId}' already exists";
    }

    return $errors;
  }

  /**
   * Generate secure email verification token with expiration
   * 
   * @param string $userId User ID to generate token for
   * @return string|null Generated token or null on failure
   * @throws Exception On database error
   */
  public function generateVerificationToken(string $userId): ?string
  {
    try {
      $token = bin2hex(random_bytes(32));
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($userId)],
        ['$set' => [
          'email_verification_token' => $token,
          'email_verification_token_expires_at' => new UTCDateTime((time() + 3600) * 1000), // 1 hour expiry
          'email_verified_at' => null, // Reset verified status
          'is_email_verified' => false
        ]]
      );

      if ($updateResult->getModifiedCount() > 0) {
        return $token;
      }
      return null;
    } catch (Exception $e) {
      throw new Exception("Failed to generate verification token: " . $e->getMessage());
    }
  }

  /**
   * Verify user email using verification token
   * 
   * @param string $token Email verification token
   * @return string Result: 'success', 'invalid_token', 'already_verified', 'expired_token', 'verification_failed'
   * @throws Exception On database error
   */
  public function verifyEmail(string $token): string
  {
    try {
      $user = $this->collection->findOne([
        'email_verification_token' => $token,
      ]);

      if (!$user) {
        return 'invalid_token'; // Token not found
      }

      // Check if already verified
      if (isset($user['is_email_verified']) && $user['is_email_verified'] === true) {
        return 'already_verified';
      }

      // Check if token has expired
      if (isset($user['email_verification_token_expires_at']) && $user['email_verification_token_expires_at']->toDateTime() < new DateTime()) {
        return 'expired_token';
      }

      $updateResult = $this->collection->updateOne(
        ['_id' => $user['_id']],
        ['$set' => [
          'email_verified_at' => new UTCDateTime(),
          'is_email_verified' => true,
          'email_verification_token' => null, // Clear the token
          'email_verification_token_expires_at' => null // Clear the expiry
        ]]
      );

      if ($updateResult->getModifiedCount() > 0) {
        return 'success';
      } else {
        return 'verification_failed'; // Should not happen if user is found and not already verified
      }
    } catch (Exception $e) {
      throw new Exception("Failed to verify email: " . $e->getMessage());
    }
  }

  // Generate and save password reset token
  public function generatePasswordResetToken(string $email): bool
  {
    try {
      $user = $this->findByEmail($email);
      if (!$user) {
        return false; // Don't reveal that the user doesn't exist
      }

      $token = bin2hex(random_bytes(32));
      $expires = new UTCDateTime((time() + 3600) * 1000); // 1 hour expiry

      $this->collection->updateOne(
        ['_id' => $user['_id']],
        ['$set' => [
          'password_reset_token' => $token,
          'password_reset_expires_at' => $expires
        ]]
      );

      // Send password reset email
      $resetLink = "http://localhost:3000/pages/forgot-password.html?token={$token}";
      $emailBody = "Please click on the following link to reset your password: <a href='{$resetLink}'>{$resetLink}</a>";
      return send_email($email, 'Password Reset Request', $emailBody);
    } catch (Exception $e) {
      throw new Exception("Failed to generate password reset token: " . $e->getMessage());
    }
  }

  // Reset password using token
  public function resetPassword(string $token, string $newPassword): bool
  {
    try {
      $user = $this->collection->findOne([
        'password_reset_token' => $token,
        'password_reset_expires_at' => ['$gt' => new UTCDateTime()]
      ]);

      if (!$user) {
        return false; // Invalid or expired token
      }

      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      $this->collection->updateOne(
        ['_id' => $user['_id']],
        ['$set' => [
          'password' => $hashedPassword,
          'password_reset_token' => null,
          'password_reset_expires_at' => null,
          'updated_at' => new UTCDateTime()
        ]]
      );

      return true;
    } catch (Exception $e) {
      throw new Exception("Failed to reset password: " . $e->getMessage());
    }
  }

  // Save refresh token for a user
  public function saveRefreshToken(string $userId, string $refreshToken, int $expiresAt): bool
  {
    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($userId)],
        ['$set' => [
          'refresh_token' => $refreshToken,
          'refresh_token_expires_at' => new UTCDateTime($expiresAt * 1000)
        ]]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to save refresh token: " . $e->getMessage());
    }
  }

  // Find user by refresh token and validate its expiry
  public function findByRefreshToken(string $refreshToken): string|array|null
  {
    $user = $this->collection->findOne([
      'refresh_token' => $refreshToken
    ]);

    if (!$user) {
      return 'not_found'; // Refresh token not found
    }

    // Check if refresh token has expired
    if ($user['refresh_token_expires_at'] && $user['refresh_token_expires_at']->toDateTime() < new DateTime()) {
      return 'expired'; // Refresh token expired
    }

    $userData = $user->getArrayCopy();
    unset($userData['password']); // Always exclude password
    return $userData;
  }

  // Invalidate refresh token (e.g., on logout or when a new one is issued)
  public function invalidateRefreshToken(string $userId): bool
  {
    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($userId)],
        ['$set' => [
          'refresh_token' => null,
          'refresh_token_expires_at' => null
        ]]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to invalidate refresh token: " . $e->getMessage());
    }
  }

  // Generate and send new email verification token by email
  public function generateVerificationTokenByEmail(string $email): string
  {
    try {
      $user = $this->findByEmail($email);
      if (!$user) {
        return 'user_not_found'; // Don't reveal if user doesn't exist
      }

      if (isset($user['is_email_verified']) && $user['is_email_verified'] === true) {
        return 'already_verified';
      }

      $token = $this->generateVerificationToken((string)$user['_id']);

      if ($token) {
        $verificationLink = "http://localhost:3000/pages/verify-email.html?token={$token}";
        $emailBody = "Please click on the following link to verify your email address: <a href='{$verificationLink}'>{$verificationLink}</a>";
        if (send_email($user['email'], 'Verify Your Email Address', $emailBody)) {
          return 'success';
        } else {
          return 'email_send_failed';
        }
      }
      return 'token_generation_failed';
    } catch (Exception $e) {
      throw new Exception("Failed to generate and send new verification token: " . $e->getMessage());
    }
  }
}
