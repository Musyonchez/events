<?php
/**
 * User registration test
 * Tests user account creation and validation
 */

require_once __DIR__ . '/../../server/vendor/autoload.php';
require_once __DIR__ . '/../../server/config/database.php';
require_once __DIR__ . '/../../server/models/User.php';
require_once __DIR__ . '/../../server/utils/response.php';

echo "=== User Registration Test ===\n";

// Initialize user model
$userModel = new UserModel($db->users);

// Test 1: Valid user registration
echo "\n1. Testing valid user registration...\n";

$validUserData = [
    'student_id' => 'USIU20240100',
    'first_name' => 'Jane',
    'last_name' => 'Smith',
    'email' => 'jane.smith@usiu.ac.ke',
    'password' => 'password123',
    'phone' => '+254712345678',
    'course' => 'Computer Science',
    'year_of_study' => 2
];

// Check if user already exists
$existingUser = $userModel->findByEmail($validUserData['email']);
if ($existingUser) {
    echo "✓ Test user already exists, skipping creation\n";
} else {
    $result = $userModel->createWithValidation($validUserData);
    
    if ($result['success']) {
        echo "✓ Valid user registration test passed\n";
        echo "  User ID: " . $result['id'] . "\n";
    } else {
        echo "✗ Valid user registration test failed\n";
        echo "  Errors: " . json_encode($result['errors']) . "\n";
    }
}

// Test 2: Duplicate email validation
echo "\n2. Testing duplicate email validation...\n";

$duplicateEmailData = [
    'student_id' => 'USIU20240101',
    'first_name' => 'John',
    'last_name' => 'Duplicate',
    'email' => 'jane.smith@usiu.ac.ke', // Same email as above
    'password' => 'password123',
    'phone' => '+254712345679',
    'course' => 'Business',
    'year_of_study' => 1
];

$result = $userModel->createWithValidation($duplicateEmailData);

if (!$result['success'] && isset($result['errors']['email'])) {
    echo "✓ Duplicate email validation test passed\n";
    echo "  Error: " . $result['errors']['email'] . "\n";
} else {
    echo "✗ Duplicate email validation test failed\n";
    echo "  Expected error but got: " . json_encode($result) . "\n";
}

// Test 3: Duplicate student ID validation
echo "\n3. Testing duplicate student ID validation...\n";

$duplicateStudentIdData = [
    'student_id' => 'USIU20240100', // Same student ID as first test
    'first_name' => 'Bob',
    'last_name' => 'Duplicate',
    'email' => 'bob.duplicate@usiu.ac.ke',
    'password' => 'password123',
    'phone' => '+254712345680',
    'course' => 'Engineering',
    'year_of_study' => 3
];

$result = $userModel->createWithValidation($duplicateStudentIdData);

if (!$result['success'] && isset($result['errors']['student_id'])) {
    echo "✓ Duplicate student ID validation test passed\n";
    echo "  Error: " . $result['errors']['student_id'] . "\n";
} else {
    echo "✗ Duplicate student ID validation test failed\n";
    echo "  Expected error but got: " . json_encode($result) . "\n";
}

// Test 4: Invalid email domain
echo "\n4. Testing invalid email domain validation...\n";

$invalidEmailData = [
    'student_id' => 'USIU20240102',
    'first_name' => 'Invalid',
    'last_name' => 'Email',
    'email' => 'invalid@gmail.com', // Wrong domain
    'password' => 'password123',
    'phone' => '+254712345681',
    'course' => 'Mathematics',
    'year_of_study' => 1
];

$result = $userModel->createWithValidation($invalidEmailData);

if (!$result['success'] && isset($result['errors']['email'])) {
    echo "✓ Invalid email domain validation test passed\n";
    echo "  Error: " . $result['errors']['email'] . "\n";
} else {
    echo "✗ Invalid email domain validation test failed\n";
    echo "  Expected error but got: " . json_encode($result) . "\n";
}

// Test 5: Invalid student ID format
echo "\n5. Testing invalid student ID format validation...\n";

$invalidStudentIdData = [
    'student_id' => 'INVALID123', // Wrong format
    'first_name' => 'Invalid',
    'last_name' => 'StudentID',
    'email' => 'invalid.studentid@usiu.ac.ke',
    'password' => 'password123',
    'phone' => '+254712345682',
    'course' => 'Physics',
    'year_of_study' => 2
];

$result = $userModel->createWithValidation($invalidStudentIdData);

if (!$result['success'] && isset($result['errors']['student_id'])) {
    echo "✓ Invalid student ID format validation test passed\n";
    echo "  Error: " . $result['errors']['student_id'] . "\n";
} else {
    echo "✗ Invalid student ID format validation test failed\n";
    echo "  Expected error but got: " . json_encode($result) . "\n";
}

// Test 6: Missing required fields
echo "\n6. Testing missing required fields validation...\n";

$incompleteData = [
    'first_name' => 'Incomplete',
    'email' => 'incomplete@usiu.ac.ke'
    // Missing student_id, last_name, password
];

$result = $userModel->createWithValidation($incompleteData);

if (!$result['success'] && !empty($result['errors'])) {
    echo "✓ Missing required fields validation test passed\n";
    echo "  Errors: " . json_encode($result['errors']) . "\n";
} else {
    echo "✗ Missing required fields validation test failed\n";
    echo "  Expected errors but got: " . json_encode($result) . "\n";
}

// Test 7: Password too short
echo "\n7. Testing password length validation...\n";

$shortPasswordData = [
    'student_id' => 'USIU20240103',
    'first_name' => 'Short',
    'last_name' => 'Password',
    'email' => 'short.password@usiu.ac.ke',
    'password' => '123', // Too short
    'phone' => '+254712345683',
    'course' => 'Chemistry',
    'year_of_study' => 1
];

$result = $userModel->createWithValidation($shortPasswordData);

if (!$result['success'] && isset($result['errors']['password'])) {
    echo "✓ Password length validation test passed\n";
    echo "  Error: " . $result['errors']['password'] . "\n";
} else {
    echo "✗ Password length validation test failed\n";
    echo "  Expected error but got: " . json_encode($result) . "\n";
}

// Test 8: Valid phone number formats
echo "\n8. Testing phone number validation...\n";

$invalidPhoneData = [
    'student_id' => 'USIU20240104',
    'first_name' => 'Invalid',
    'last_name' => 'Phone',
    'email' => 'invalid.phone@usiu.ac.ke',
    'password' => 'password123',
    'phone' => '123456789', // Invalid format
    'course' => 'Biology',
    'year_of_study' => 2
];

$result = $userModel->createWithValidation($invalidPhoneData);

if (!$result['success'] && isset($result['errors']['phone'])) {
    echo "✓ Phone number validation test passed\n";
    echo "  Error: " . $result['errors']['phone'] . "\n";
} else {
    echo "✗ Phone number validation test failed\n";
    echo "  Expected error but got: " . json_encode($result) . "\n";
}

// Test 9: Check that user is created as unverified
echo "\n9. Testing email verification status...\n";

$testUser = $userModel->findByEmail('jane.smith@usiu.ac.ke');
if ($testUser) {
    if ($testUser['is_email_verified'] === false) {
        echo "✓ Email verification status test passed\n";
        echo "  User created as unverified: " . ($testUser['is_email_verified'] ? 'true' : 'false') . "\n";
    } else {
        echo "✗ Email verification status test failed\n";
        echo "  Expected unverified user but got verified\n";
    }
} else {
    echo "✗ Email verification status test failed - user not found\n";
}

// Test 10: Check password is properly hashed
echo "\n10. Testing password hashing...\n";

if ($testUser && isset($testUser['password'])) {
    $isHashed = password_verify('password123', $testUser['password']);
    if ($isHashed) {
        echo "✓ Password hashing test passed\n";
        echo "  Password properly hashed and verifiable\n";
    } else {
        echo "✗ Password hashing test failed\n";
        echo "  Password not properly hashed or not verifiable\n";
    }
} else {
    echo "✗ Password hashing test failed - user or password not found\n";
}

echo "\n=== Registration Test Summary ===\n";
echo "✓ All registration functionality tests completed\n";
echo "Note: Test data preserved in development database\n";