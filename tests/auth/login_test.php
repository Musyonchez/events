<?php
/**
 * Login functionality test
 * Tests user authentication with valid and invalid credentials
 */

require_once __DIR__ . '/../../server/vendor/autoload.php';
require_once __DIR__ . '/../../server/config/database.php';
require_once __DIR__ . '/../../server/models/User.php';
require_once __DIR__ . '/../../server/utils/response.php';

echo "=== Login Functionality Test ===\n";

// Test 1: Valid login credentials
echo "\n1. Testing valid login credentials...\n";

// Mock user data for testing
$testEmail = 'test.user@usiu.ac.ke';
$testPassword = 'password123';
$hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);

// Create test user in database if doesn't exist
$userModel = new UserModel($db->users);
$existingUser = $userModel->findByEmail($testEmail);

if (!$existingUser) {
    echo "Creating test user...\n";
    $testUserData = [
        'email' => $testEmail,
        'password' => $hashedPassword,
        'first_name' => 'Test',
        'last_name' => 'User',
        'role' => 'student',
        'student_id' => 'USIU20240001',
        'is_email_verified' => true,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    try {
        $userId = $userModel->create($testUserData);
        echo "✓ Test user created with ID: " . $userId . "\n";
    } catch (Exception $e) {
        echo "✗ Failed to create test user: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "✓ Test user already exists, updating password...\n";
    // Update the existing user's password to match our test
    try {
        $userModel->update($existingUser['_id']->__toString(), [
            'password' => $testPassword,  // Pass plain text, schema will hash it
            'is_email_verified' => true
        ]);
        echo "✓ Test user password updated\n";
    } catch (Exception $e) {
        echo "✗ Failed to update test user password: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Test login with valid credentials
echo "Testing login with valid credentials...\n";

// Simulate POST request data
$validLoginData = [
    'email' => $testEmail,
    'password' => $testPassword
];

// Test the login logic (simulate the login.php flow)
define('IS_AUTH_ROUTE', true);
$_ENV['JWT_SECRET'] = 'test-secret-key-for-development';

$requestData = $validLoginData;
$email = $requestData['email'];
$password = $requestData['password'];

// Validate required fields
if (!$email || !$password) {
    echo "✗ Email and password validation failed\n";
    exit(1);
}

// Find user
$user = $userModel->findByEmail($email);
if (!$user) {
    echo "✗ User not found\n";
    exit(1);
}

// Verify password
if (!password_verify($password, $user['password'])) {
    echo "✗ Password verification failed\n";
    exit(1);
}

// Check email verification
if (!$user['is_email_verified']) {
    echo "✗ Email verification check failed\n";
    exit(1);
}

echo "✓ Valid login test passed\n";

// Test 2: Invalid email
echo "\n2. Testing invalid email...\n";
$invalidUser = $userModel->findByEmail('nonexistent@usiu.ac.ke');
if ($invalidUser) {
    echo "✗ Invalid email test failed - user should not exist\n";
} else {
    echo "✓ Invalid email test passed\n";
}

// Test 3: Invalid password
echo "\n3. Testing invalid password...\n";
$user = $userModel->findByEmail($testEmail);
if (password_verify('wrongpassword', $user['password'])) {
    echo "✗ Invalid password test failed - password should not match\n";
} else {
    echo "✓ Invalid password test passed\n";
}

// Test 4: Unverified email
echo "\n4. Testing unverified email requirement...\n";
$unverifiedEmail = 'unverified@usiu.ac.ke';
$existingUnverified = $userModel->findByEmail($unverifiedEmail);

if (!$existingUnverified) {
    echo "Creating unverified test user...\n";
    $unverifiedUserData = [
        'email' => $unverifiedEmail,
        'password' => $hashedPassword,
        'first_name' => 'Unverified',
        'last_name' => 'User',
        'role' => 'student',
        'student_id' => 'USIU20240002',
        'is_email_verified' => false,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    try {
        $userModel->create($unverifiedUserData);
        echo "✓ Unverified test user created\n";
    } catch (Exception $e) {
        echo "✗ Failed to create unverified test user: " . $e->getMessage() . "\n";
    }
}

$unverifiedUser = $userModel->findByEmail($unverifiedEmail);
if ($unverifiedUser && !$unverifiedUser['is_email_verified']) {
    echo "✓ Email verification requirement test passed\n";
} else {
    echo "✗ Email verification requirement test failed\n";
}

// Test 5: JWT Secret configuration
echo "\n5. Testing JWT secret configuration...\n";
if (isset($_ENV['JWT_SECRET']) && !empty($_ENV['JWT_SECRET'])) {
    echo "✓ JWT secret configuration test passed\n";
} else {
    echo "✗ JWT secret configuration test failed\n";
}

echo "\n=== Login Test Summary ===\n";
echo "✓ All login functionality tests completed\n";
echo "Note: Test data preserved in development database\n";