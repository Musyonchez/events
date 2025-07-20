<?php
/**
 * USIU Events Management System - Club Model
 * 
 * Club data model managing student organizations including creation, membership,
 * leadership, and club-related operations. Handles club lifecycle, membership
 * management, and club-event relationships.
 * 
 * Features:
 * - Club creation and management
 * - Membership tracking and management
 * - Leadership assignment and permissions
 * - Club status and visibility control
 * - Club search and categorization
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 */

require_once __DIR__ . '/../schemas/Club.php';
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use ValidationException;
use ClubSchema;

/**
 * Club Model Class
 * 
 * Manages all club-related database operations including CRUD operations,
 * membership management, and leadership assignment.
 */
class ClubModel
{
  /**
   * MongoDB collection instance for club documents
   */
  private Collection $collection;

  /**
   * Initialize club model with MongoDB collection
   * 
   * @param Collection $collection MongoDB collection for clubs
   */
  public function __construct(Collection $collection)
  {
    $this->collection = $collection;
  }

  /**
   * Create new club with validation and duplicate checking
   * 
   * @param array $data Club data to create
   * @return ObjectId Generated club ID
   * @throws Exception On validation failure or database error
   */
  public function create(array $data): ObjectId
  {
    try {
      $club = ClubSchema::mapAndValidate($data);
    } catch (ValidationException $e) {
      throw new Exception("Validation failed: " . json_encode($e->getErrors()));
    }

    // Check for duplicate club name
    $this->checkDuplicateName($club['name']);

    $result = $this->collection->insertOne($club);
    if (!$result->isAcknowledged()) {
      throw new Exception("Failed to create club");
    }

    return $result->getInsertedId();
  }

  // Create with validation errors returned instead of thrown
  public function createWithValidation(array $data): array
  {
    try {
      $club = ClubSchema::mapAndValidate($data);
      
      // Check for duplicate name
      $duplicateErrors = $this->checkDuplicateNameValidation($club['name']);
      if (!empty($duplicateErrors)) {
        return ['success' => false, 'errors' => $duplicateErrors];
      }

      $result = $this->collection->insertOne($club);
      if (!$result->isAcknowledged()) {
        return ['success' => false, 'errors' => ['database' => 'Failed to create club']];
      }

      return ['success' => true, 'id' => $result->getInsertedId()];
    } catch (ValidationException $e) {
      return ['success' => false, 'errors' => $e->getErrors()];
    } catch (Exception $e) {
      return ['success' => false, 'errors' => ['database' => $e->getMessage()]];
    }
  }

  // Find a club by ID
  public function findById(string $id): ?array
  {
    try {
      $club = $this->collection->findOne(['_id' => new ObjectId($id)]);
      return $club ? $club->getArrayCopy() : null;
    } catch (Exception $e) {
      throw new Exception("Invalid club ID format: {$id}");
    }
  }

  // Find a club by name
  public function findByName(string $name): ?array
  {
    $club = $this->collection->findOne(['name' => $name]);
    return $club ? $club->getArrayCopy() : null;
  }

  // Find clubs by category
  public function findByCategory(string $category, array $options = []): array
  {
    $filter = ['category' => $category];
    
    // Add status filter if provided
    if (isset($options['status'])) {
      $filter['status'] = $options['status'];
    }

    $mongoOptions = [
      'limit' => $options['limit'] ?? 50,
      'skip' => $options['skip'] ?? 0,
      'sort' => $options['sort'] ?? ['name' => 1]
    ];

    $cursor = $this->collection->find($filter, $mongoOptions);
    $clubs = iterator_to_array($cursor);

    return array_map(fn($doc) => $doc->getArrayCopy(), $clubs);
  }

  // Find clubs by leader ID
  public function findByLeader(string $leaderId): array
  {
    try {
      $filter = ['leader_id' => new ObjectId($leaderId)];
      $cursor = $this->collection->find($filter);
      $clubs = iterator_to_array($cursor);

      return array_map(fn($doc) => $doc->getArrayCopy(), $clubs);
    } catch (Exception $e) {
      throw new Exception("Invalid leader ID format: {$leaderId}");
    }
  }

  // Update a club by ID
  public function update(string $id, array $data): bool
  {
    try {
      $updateData = ClubSchema::mapForUpdate($data);
    } catch (ValidationException $e) {
      throw new Exception("Validation failed: " . json_encode($e->getErrors()));
    }

    // Check for duplicate name if name is being updated
    if (isset($updateData['name'])) {
      $this->checkDuplicateName($updateData['name'], $id);
    }

    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => $updateData]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to update club: " . $e->getMessage());
    }
  }

  // Update with validation errors returned instead of thrown
  public function updateWithValidation(string $id, array $data): array
  {
    try {
      $updateData = ClubSchema::mapForUpdate($data);
      
      // Check for duplicate name if name is being updated
      if (isset($updateData['name'])) {
        $duplicateErrors = $this->checkDuplicateNameValidation($updateData['name'], $id);
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

  // Delete club by ID
  public function delete(string $id): bool
  {
    try {
      $deleteResult = $this->collection->deleteOne(['_id' => new ObjectId($id)]);
      return $deleteResult->getDeletedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to delete club: " . $e->getMessage());
    }
  }

  // List all clubs with optional filters, paging and sorting
  public function listClubs(array $filters = [], int $page = 1, int $limit = 10, array $sort_options = []): array
  {
    $skip = ($page - 1) * $limit;

    $query = $filters;

    // Handle min_members and max_members filters
    if (isset($filters['min_members']) || isset($filters['max_members'])) {
        $query['members_count'] = [];
        if (isset($filters['min_members'])) {
            $query['members_count']['$gte'] = (int)$filters['min_members'];
        }
        if (isset($filters['max_members'])) {
            $query['members_count']['$lte'] = (int)$filters['max_members'];
        }
        // Remove original min/max_members from filters to avoid duplication
        unset($query['min_members']);
        unset($query['max_members']);
    }

    $options = [
      'limit' => $limit,
      'skip' => $skip,
      'sort' => !empty($sort_options) ? $sort_options : ['name' => 1] // Default sort by name
    ];

    $cursor = $this->collection->find($query, $options);
    $clubs = iterator_to_array($cursor);

    return array_map(fn($doc) => $doc->getArrayCopy(), $clubs);
  }

  // Get total count of clubs (useful for pagination)
  public function countClubs(array $filters = []): int
  {
    return $this->collection->countDocuments($filters);
  }

  // Update member count
  public function updateMemberCount(string $id, int $count): bool
  {
    try {
      if ($count < 0) {
        throw new Exception("Member count cannot be negative");
      }

      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => [
          'members_count' => $count,
          'updated_at' => new UTCDateTime()
        ]]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to update member count: " . $e->getMessage());
    }
  }

  // Increment member count
  public function incrementMemberCount(string $id, int $increment = 1): bool
  {
    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        [
          '$inc' => ['members_count' => $increment],
          '$set' => ['updated_at' => new UTCDateTime()]
        ]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to increment member count: " . $e->getMessage());
    }
  }

  // Decrement member count
  public function decrementMemberCount(string $id, int $decrement = 1): bool
  {
    try {
      // Ensure member count doesn't go below 0
      $club = $this->findById($id);
      if (!$club) {
        throw new Exception("Club not found");
      }

      $newCount = max(0, $club['members_count'] - $decrement);
      
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => [
          'members_count' => $newCount,
          'updated_at' => new UTCDateTime()
        ]]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to decrement member count: " . $e->getMessage());
    }
  }

  // Transfer leadership
  public function transferLeadership(string $clubId, string $newLeaderId): array
  {
    try {
      $club = $this->findById($clubId);
      if (!$club) {
        return ['success' => false, 'errors' => ['club' => 'Club not found']];
      }

      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($clubId)],
        ['$set' => [
          'leader_id' => new ObjectId($newLeaderId),
          'updated_at' => new UTCDateTime()
        ]]
      );

      return [
        'success' => true,
        'modified' => $updateResult->getModifiedCount() > 0,
        'old_leader_id' => $club['leader_id']
      ];
    } catch (Exception $e) {
      return ['success' => false, 'errors' => ['database' => $e->getMessage()]];
    }
  }

  // Activate club
  public function activate(string $id): bool
  {
    return $this->updateStatus($id, 'active');
  }

  // Deactivate club
  public function deactivate(string $id): bool
  {
    return $this->updateStatus($id, 'inactive');
  }

  // Search clubs by name, description, or category
  public function search(string $searchTerm, array $options = []): array
  {
    $regex = new \MongoDB\BSON\Regex($searchTerm, 'i'); // Case-insensitive search
    
    $filter = [
      '$or' => [
        ['name' => $regex],
        ['description' => $regex],
        ['category' => $regex]
      ]
    ];

    // Add status filter if provided
    if (isset($options['status'])) {
      $filter['status'] = $options['status'];
    }

    $mongoOptions = [
      'limit' => $options['limit'] ?? 20,
      'skip' => $options['skip'] ?? 0,
      'sort' => ['name' => 1]
    ];

    $cursor = $this->collection->find($filter, $mongoOptions);
    $clubs = iterator_to_array($cursor);

    return array_map(fn($doc) => $doc->getArrayCopy(), $clubs);
  }

  // Get club statistics
  public function getStats(): array
  {
    $pipeline = [
      [
        '$group' => [
          '_id' => null,
          'total_clubs' => ['$sum' => 1],
          'active_clubs' => [
            '$sum' => ['$cond' => [['$eq' => ['$status', 'active']], 1, 0]]
          ],
          'inactive_clubs' => [
            '$sum' => ['$cond' => [['$eq' => ['$status', 'inactive']], 1, 0]]
          ],
          'total_members' => ['$sum' => '$members_count'],
          'avg_members_per_club' => ['$avg' => '$members_count']
        ]
      ]
    ];

    $cursor = $this->collection->aggregate($pipeline);
    $results = iterator_to_array($cursor);

    if (empty($results)) {
      return [
        'total_clubs' => 0,
        'active_clubs' => 0,
        'inactive_clubs' => 0,
        'total_members' => 0,
        'avg_members_per_club' => 0
      ];
    }

    $stats = $results[0];
    unset($stats['_id']);
    $stats['avg_members_per_club'] = round($stats['avg_members_per_club'], 2);

    return $stats;
  }

  // Get clubs by category with statistics
  public function getByCategory(): array
  {
    $pipeline = [
      [
        '$group' => [
          '_id' => '$category',
          'count' => ['$sum' => 1],
          'active_count' => [
            '$sum' => ['$cond' => [['$eq' => ['$status', 'active']], 1, 0]]
          ],
          'total_members' => ['$sum' => '$members_count']
        ]
      ],
      [
        '$sort' => ['count' => -1]
      ]
    ];

    $cursor = $this->collection->aggregate($pipeline);
    $results = iterator_to_array($cursor);

    $categoryStats = [];
    foreach ($results as $result) {
      $categoryStats[] = [
        'category' => $result['_id'],
        'club_count' => $result['count'],
        'active_clubs' => $result['active_count'],
        'total_members' => $result['total_members']
      ];
    }

    return $categoryStats;
  }

  // Get popular clubs (by member count)
  public function getPopularClubs(int $limit = 10): array
  {
    $options = [
      'limit' => $limit,
      'sort' => ['members_count' => -1, 'name' => 1]
    ];

    $cursor = $this->collection->find(['status' => 'active'], $options);
    $clubs = iterator_to_array($cursor);

    return array_map(fn($doc) => $doc->getArrayCopy(), $clubs);
  }

  // Get recently created clubs
  public function getRecentClubs(int $limit = 10): array
  {
    $options = [
      'limit' => $limit,
      'sort' => ['created_at' => -1]
    ];

    $cursor = $this->collection->find(['status' => 'active'], $options);
    $clubs = iterator_to_array($cursor);

    return array_map(fn($doc) => $doc->getArrayCopy(), $clubs);
  }

  // Get clubs for public display (sanitized)
  public function getPublicClubs(array $filters = [], int $limit = 50, int $skip = 0): array
  {
    $clubs = $this->list($filters, $limit, $skip);
    
    return array_map(function($club) {
      return ClubSchema::sanitizeForPublic($club);
    }, $clubs);
  }

  // Add a member to the club
  public function addMember(string $clubId, string $userId): bool
  {
    try {
      // First, fix any clubs where members field is incorrectly stored as string
      $this->collection->updateOne(
        ['_id' => new ObjectId($clubId), 'members' => ['$type' => 'string']],
        ['$set' => ['members' => []]]
      );
      
      // Now add the member
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($clubId)],
        [
          '$addToSet' => ['members' => new ObjectId($userId)], // Add to set to avoid duplicates
          '$inc' => ['members_count' => 1],
          '$set' => ['updated_at' => new UTCDateTime()]
        ]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to add member to club: " . $e->getMessage());
    }
  }

  // Remove a member from the club
  public function removeMember(string $clubId, string $userId): bool
  {
    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($clubId)],
        [
          '$pull' => ['members' => new ObjectId($userId)],
          '$inc' => ['members_count' => -1],
          '$set' => ['updated_at' => new UTCDateTime()]
        ]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to remove member from club: " . $e->getMessage());
    }
  }

  // Private helper methods
  private function updateStatus(string $id, string $status): bool
  {
    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => [
          'status' => $status,
          'updated_at' => new UTCDateTime()
        ]]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to update club status: " . $e->getMessage());
    }
  }

  private function checkDuplicateName(string $name, string $excludeId = null): void
  {
    $filter = ['name' => $name];
    
    if ($excludeId) {
      $filter['_id'] = ['$ne' => new ObjectId($excludeId)];
    }

    if ($this->collection->findOne($filter)) {
      throw new Exception("Club with name '{$name}' already exists");
    }
  }

  private function checkDuplicateNameValidation(string $name, string $excludeId = null): array
  {
    $errors = [];
    
    $filter = ['name' => $name];
    
    if ($excludeId) {
      $filter['_id'] = ['$ne' => new ObjectId($excludeId)];
    }

    if ($this->collection->findOne($filter)) {
      $errors['name'] = "Club with name '{$name}' already exists";
    }

    return $errors;
  }
}
