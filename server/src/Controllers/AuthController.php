<?php
class AuthController {
  public function login() {
    // Handle login logic
    return json_encode(["token" => "sample_jwt"]);
  }
}
