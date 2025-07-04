<?php

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function upload_file_to_s3(array $file, string $folder = 'uploads', array $allowedMimeTypes = [], int $maxSize = 5 * 1024 * 1024): string
{
    // Validate file upload basics
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid upload parameters.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new Exception('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('Exceeded filesize limit.');
        default:
            throw new Exception('Unknown upload error.');
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds limit.');
    }

    // Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!empty($allowedMimeTypes) && !in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception('Invalid file type.');
    }

    // S3 Client Initialization
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => $_ENV['AWS_REGION'],
        'credentials' => [
            'key' => $_ENV['AWS_ACCESS_KEY_ID'],
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        ],
    ]);

    $bucket = $_ENV['AWS_BUCKET'];
    $key = $folder . '/' . uniqid() . '_' . basename($file['name']);

    try {
        $result = $s3Client->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'Source' => $file['tmp_name'],
            'ACL' => 'public-read', // Make the file publicly accessible
            'ContentType' => $mimeType,
        ]);

        return $result['ObjectURL'];
    } catch (AwsException $e) {
        // Log the error for debugging
        error_log("S3 Upload Error: " . $e->getMessage());
        throw new Exception('Failed to upload file to S3.');
    }
}
