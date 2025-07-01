<?php

// api/user/profileImage.php

require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../models/actions/UserActions.php';

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Validate file and user ID
if (! isset($_FILES['image']) || ! isset($_POST['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Image file and user ID are required']);
    exit;
}

$imageFile = $_FILES['image'];
$userId = $_POST['user_id'];

if ($imageFile['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'File upload error']);
    exit;
}

// AWS S3 Configuration
$bucketName = getenv('AWS_BUCKET'); // e.g., your-bucket-name
$region = getenv('AWS_REGION');     // e.g., us-west-1

$s3 = new S3Client([
    'region' => $region,
    'version' => 'latest',
    'credentials' => [
        'key' => getenv('AWS_ACCESS_KEY_ID'),
        'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
    ],
]);

// Generate a unique file name
$ext = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
$key = 'profile-images/'.uniqid().'.'.$ext;

try {
    // Upload to S3
    $result = $s3->putObject([
        'Bucket' => $bucketName,
        'Key' => $key,
        'Body' => file_get_contents($imageFile['tmp_name']),
        'ACL' => 'public-read',
        'ContentType' => $imageFile['type'],
    ]);

    // Get the public URL
    $imageUrl = $result['ObjectURL'];

    // Update user document in MongoDB
    $UserActions = new UserActions;
    $updateResult = $UserActions->updateProfileImage($userId, $imageUrl);

    echo json_encode([
        'message' => 'Image uploaded successfully',
        'imageUrl' => $imageUrl,
        'mongoResult' => $updateResult,
    ]);

} catch (AwsException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'AWS S3 upload failed', 'details' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'details' => $e->getMessage()]);
}
