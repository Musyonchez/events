<?php

require_once __DIR__ . '/../schemas/User.php';
require_once __DIR__ . '/../utils/email.php';
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use ValidationException;
use UserSchema;

class UserModel
{
  private Collection $collection;

  public function __construct(Collection $collection)
  {
    $this->collection = $collection;
  }

  // Create a new user document
  public function create(array $data): ObjectId
  {
    try {
      $user = UserSchema::mapAndValidate($data);
    } catch (ValidationException $e) {
      throw new Exception("Validation failed: " . json_encode($e->getErrors()));
    }

    // Check for duplicate email or student_id
    $this->checkDuplicates($user['email'], $user['student_id']);

    $result = $this->collection->insertOne($user);
    if (!$result->isAcknowledged()) {
      throw new Exception("Failed to create user");
    }

    return $result->getInsertedId();
  }

  // Create with validation errors returned instead of thrown
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
        return ['success' => false, 'errors' => ['database' => 'Failed to create user']];
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

  // Find a user by ID
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

  // Find a user by email (useful for authentication)
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

  // Change user password (with validation)
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

  // Search users by name, email, or student ID
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

  // Generate and save email verification token
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

  // Verify email address using token
  public function verifyEmail(string $token): bool
  {
    try {
      $user = $this->collection->findOne([
        'email_verification_token' => $token,
        'email_verification_token_expires_at' => ['$gt' => new UTCDateTime()]
      ]);

      if (!$user) {
        return false; // Invalid or expired token
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
      return $updateResult->getModifiedCount() > 0;
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
      $resetLink = "http://{$_SERVER['HTTP_HOST']}/api/auth/reset_password.php?token={$token}";
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
  public function generateVerificationTokenByEmail(string $email): bool
  {
    try {
      $user = $this->findByEmail($email);
      if (!$user) {
        return false; // Don't reveal if user doesn't exist
      }

      $token = $this->generateVerificationToken((string)$user['_id']);

      if ($token) {
        $verificationLink = "http://localhost:3000/pages/verify-email.html?token={$token}";
        $emailBody = "Please click on the following link to verify your email address: <a href='{$verificationLink}'>{$verificationLink}</a>";
        return send_email($user['email'], 'Verify Your Email Address', $emailBody);
      }
      return false;
    } catch (Exception $e) {
      throw new Exception("Failed to generate and send new verification token: " . $e->getMessage());
    }
  }
}
