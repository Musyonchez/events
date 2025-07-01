<?php
class BaseModel {
  protected $collection;

  public function __construct($collection) {
    $this->collection = $collection;
  }
}
