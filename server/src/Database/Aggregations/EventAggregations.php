<?php
// Example aggregation pipeline for popular events
return [
  ['$match' => ['is_active' => true]],
  ['$group' => ['_id' => '$category', 'count' => ['$sum' => 1]]],
  ['$sort' => ['count' => -1]]
];
