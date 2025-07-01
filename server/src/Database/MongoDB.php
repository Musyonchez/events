<?php
use MongoDB\Client;

class MongoDBConnection {
  private static $instance = null;

  public static function getInstance() {
    if (!self::$instance) {
      $config = include(__DIR__ . '/../../config/mongodb.php');
      self::$instance = (new Client($config['uri']))->selectDatabase($config['db']);
    }
    return self::$instance;
  }
}
