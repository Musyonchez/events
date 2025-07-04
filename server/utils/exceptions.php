<?php

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
