<?php

require_once __DIR__ . '/../schemas/Comment.php';
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use ValidationException;
use CommentSchema;

class CommentModel
{
  private Collection $collection;

  public function __construct(Collection $collection)
  {
    $this->collection = $collection;
  }

  // Create a new comment document
  public function create(array $data): ObjectId
  {
    try {
      $comment = CommentSchema::mapAndValidate($data);
    } catch (ValidationException $e) {
      throw new Exception("Validation failed: " . json_encode($e->getErrors()));
    }

    // Validate parent comment exists if provided
    if (!empty($comment['parent_comment_id'])) {
      $this->validateParentComment($comment['parent_comment_id'], $comment['event_id']);
    }

    $result = $this->collection->insertOne($comment);
    if (!$result->isAcknowledged()) {
      throw new Exception("Failed to create comment");
    }

    return $result->getInsertedId();
  }

  // Create with validation errors returned instead of thrown
  public function createWithValidation(array $data): array
  {
    try {
      $comment = CommentSchema::mapAndValidate($data);
      
      // Validate parent comment exists if provided
      if (!empty($comment['parent_comment_id'])) {
        $parentError = $this->validateParentCommentValidation($comment['parent_comment_id'], $comment['event_id']);
        if (!empty($parentError)) {
          return ['success' => false, 'errors' => $parentError];
        }
      }

      $result = $this->collection->insertOne($comment);
      if (!$result->isAcknowledged()) {
        return ['success' => false, 'errors' => ['database' => 'Failed to create comment']];
      }

      return ['success' => true, 'id' => $result->getInsertedId()];
    } catch (ValidationException $e) {
      return ['success' => false, 'errors' => $e->getErrors()];
    } catch (Exception $e) {
      return ['success' => false, 'errors' => ['database' => $e->getMessage()]];
    }
  }

  // Find a comment by ID
  public function findById(string $id): ?array
  {
    try {
      $comment = $this->collection->findOne(['_id' => new ObjectId($id)]);
      return $comment ? $comment->getArrayCopy() : null;
    } catch (Exception $e) {
      throw new Exception("Invalid comment ID format: {$id}");
    }
  }

  // Find comments by event ID
  public function findByEventId(string $eventId, array $options = []): array
  {
    try {
      $filter = ['event_id' => new ObjectId($eventId)];
      
      // Default options
      $defaultOptions = [
        'limit' => 50,
        'skip' => 0,
        'sort' => ['created_at' => -1], // Most recent first
        'status' => 'approved', // Only approved comments by default
        'include_replies' => true
      ];
      
      $options = array_merge($defaultOptions, $options);
      
      // Add status filter
      if ($options['status'] !== 'all') {
        $filter['status'] = $options['status'];
      }
      
      // Filter for main comments or replies
      if (!$options['include_replies']) {
        $filter['parent_comment_id'] = null;
      }

      $mongoOptions = [
        'limit' => $options['limit'],
        'skip' => $options['skip'],
        'sort' => $options['sort']
      ];

      
      $cursor = $this->collection->find($filter, $mongoOptions);
      $comments = iterator_to_array($cursor);

      return array_map(fn($doc) => $doc->getArrayCopy(), $comments);
    } catch (Exception $e) {
      throw new Exception("Invalid event ID format: {$eventId}");
    }
  }

  // Find replies to a specific comment
  public function findReplies(string $parentCommentId, array $options = []): array
  {
    try {
      $filter = [
        'parent_comment_id' => new ObjectId($parentCommentId),
        'status' => $options['status'] ?? 'approved'
      ];

      $mongoOptions = [
        'limit' => $options['limit'] ?? 20,
        'skip' => $options['skip'] ?? 0,
        'sort' => ['created_at' => 1] // Replies in chronological order
      ];

      $cursor = $this->collection->find($filter, $mongoOptions);
      $replies = iterator_to_array($cursor);

      return array_map(fn($doc) => $doc->getArrayCopy(), $replies);
    } catch (Exception $e) {
      throw new Exception("Invalid parent comment ID format: {$parentCommentId}");
    }
  }

  // Find comments by user ID
  public function findByUserId(string $userId, array $options = []): array
  {
    try {
      $filter = ['user_id' => new ObjectId($userId)];
      
      if (isset($options['status'])) {
        $filter['status'] = $options['status'];
      }

      $mongoOptions = [
        'limit' => $options['limit'] ?? 50,
        'skip' => $options['skip'] ?? 0,
        'sort' => ['created_at' => -1]
      ];

      $cursor = $this->collection->find($filter, $mongoOptions);
      $comments = iterator_to_array($cursor);

      return array_map(fn($doc) => $doc->getArrayCopy(), $comments);
    } catch (Exception $e) {
      throw new Exception("Invalid user ID format: {$userId}");
    }
  }

  // Update a comment by ID
  public function update(string $id, array $data): bool
  {
    try {
      $updateData = CommentSchema::mapForUpdate($data);
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
      throw new Exception("Failed to update comment: " . $e->getMessage());
    }
  }

  // Update with validation errors returned instead of thrown
  public function updateWithValidation(string $id, array $data): array
  {
    try {
      $updateData = CommentSchema::mapForUpdate($data);
      
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

  // Delete comment by ID
  public function delete(string $id): bool
  {
    try {
      // Also delete all replies to this comment
      $this->collection->deleteMany(['parent_comment_id' => new ObjectId($id)]);
      
      // Delete the comment itself
      $deleteResult = $this->collection->deleteOne(['_id' => new ObjectId($id)]);
      return $deleteResult->getDeletedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to delete comment: " . $e->getMessage());
    }
  }

  // List all comments with optional filters, paging etc.
  public function list(array $filters = [], int $limit = 50, int $skip = 0): array
  {
    $options = [
      'limit' => $limit,
      'skip' => $skip,
      'sort' => ['created_at' => -1]
    ];

    $cursor = $this->collection->find($filters, $options);
    $comments = iterator_to_array($cursor);

    return array_map(fn($doc) => $doc->getArrayCopy(), $comments);
  }

  // Get total count of comments (useful for pagination)
  public function count(array $filters = []): int
  {
    return $this->collection->countDocuments($filters);
  }

  // Moderation methods
  public function approve(string $id): bool
  {
    return $this->updateStatus($id, 'approved');
  }

  public function reject(string $id): bool
  {
    return $this->updateStatus($id, 'rejected');
  }

  public function flag(string $id): bool
  {
    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => [
          'flagged' => true,
          'updated_at' => new UTCDateTime()
        ]]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to flag comment: " . $e->getMessage());
    }
  }

  public function unflag(string $id): bool
  {
    try {
      $updateResult = $this->collection->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => [
          'flagged' => false,
          'updated_at' => new UTCDateTime()
        ]]
      );
      return $updateResult->getModifiedCount() > 0;
    } catch (Exception $e) {
      throw new Exception("Failed to unflag comment: " . $e->getMessage());
    }
  }

  // Get comments that need moderation
  public function getPendingComments(int $limit = 50, int $skip = 0): array
  {
    $filter = ['status' => 'pending'];
    $options = [
      'limit' => $limit,
      'skip' => $skip,
      'sort' => ['created_at' => 1] // Oldest first for moderation
    ];

    $cursor = $this->collection->find($filter, $options);
    $comments = iterator_to_array($cursor);

    return array_map(fn($doc) => $doc->getArrayCopy(), $comments);
  }

  // Get flagged comments
  public function getFlaggedComments(int $limit = 50, int $skip = 0): array
  {
    $filter = ['flagged' => true];
    $options = [
      'limit' => $limit,
      'skip' => $skip,
      'sort' => ['updated_at' => -1]
    ];

    $cursor = $this->collection->find($filter, $options);
    $comments = iterator_to_array($cursor);

    return array_map(fn($doc) => $doc->getArrayCopy(), $comments);
  }

  // Get comment statistics
  public function getStats(): array
  {
    $pipeline = [
      [
        '$group' => [
          '_id' => '$status',
          'count' => ['$sum' => 1]
        ]
      ]
    ];

    $cursor = $this->collection->aggregate($pipeline);
    $results = iterator_to_array($cursor);

    $stats = [
      'total' => 0,
      'approved' => 0,
      'pending' => 0,
      'rejected' => 0,
      'flagged' => 0
    ];

    foreach ($results as $result) {
      $status = $result['_id'];
      $count = $result['count'];
      $stats[$status] = $count;
      $stats['total'] += $count;
    }

    // Get flagged count separately
    $stats['flagged'] = $this->collection->countDocuments(['flagged' => true]);

    return $stats;
  }

  // Get threaded comments (main comments with their replies)
  public function getThreadedComments(string $eventId, array $options = []): array
  {
    try {
      // Get main comments
      $mainComments = $this->findByEventId($eventId, array_merge($options, [
        'include_replies' => false
      ]));

      // Get replies for each main comment
      foreach ($mainComments as &$comment) {
        $comment['replies'] = $this->findReplies($comment['_id']->__toString(), [
          'status' => $options['status'] ?? 'approved'
        ]);
      }

      return $mainComments;
    } catch (Exception $e) {
      throw new Exception("Failed to get threaded comments: " . $e->getMessage());
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
      throw new Exception("Failed to update comment status: " . $e->getMessage());
    }
  }

  private function validateParentComment(ObjectId $parentCommentId, ObjectId $eventId): void
  {
    $parentComment = $this->collection->findOne(['_id' => $parentCommentId]);
    
    if (!$parentComment) {
      throw new Exception("Parent comment not found");
    }

    if (!$parentComment['event_id']->equals($eventId)) {
      throw new Exception("Parent comment belongs to a different event");
    }

    if ($parentComment['parent_comment_id'] !== null) {
      throw new Exception("Cannot reply to a reply. Only one level of nesting is allowed");
    }
  }

  private function validateParentCommentValidation(ObjectId $parentCommentId, ObjectId $eventId): array
  {
    $errors = [];
    
    $parentComment = $this->collection->findOne(['_id' => $parentCommentId]);
    
    if (!$parentComment) {
      $errors['parent_comment_id'] = "Parent comment not found";
      return $errors;
    }

    if (!$parentComment['event_id']->equals($eventId)) {
      $errors['parent_comment_id'] = "Parent comment belongs to a different event";
    }

    if ($parentComment['parent_comment_id'] !== null) {
      $errors['parent_comment_id'] = "Cannot reply to a reply. Only one level of nesting is allowed";
    }

    return $errors;
  }
}
