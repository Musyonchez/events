<?php
class User {
  public $email;
  public $password;

  public function __construct($data) {
    $this->email = $data['email'];
    $this->password = $data['password'];
  }
}
