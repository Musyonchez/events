<?php

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

return [
  'app_name' => 'USIU Event API',
  'jwt_secret' => $_ENV['JWT_SECRET'] ?? '',
  'jwt_algo' => 'HS256',

    // Email config (if you implement notifications)
    'email' => [
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_user' => 'your_email@example.com',
        'smtp_pass' => 'your_email_password',
        'from_email' => 'no-reply@usiu.ac.ke',
        'from_name' => 'USIU Events',
    ],

    'db' => [
        'type' => 'mongodb',
        'uri' => $_ENV['MONGO_URI'] ?? '',
        'database' => $_ENV['MONGODB_DB'] ?? ''
    ],];

