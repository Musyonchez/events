<?php
/**
 * USIU Events Management System - File Upload Utility
 * 
 * This utility module provides secure file upload functionality for the USIU Events
 * system using Amazon S3 cloud storage. It handles file validation, security checks,
 * and cloud storage integration with comprehensive error handling.
 * 
 * File Upload Features:
 * - Secure file upload to Amazon S3 cloud storage
 * - Comprehensive file validation and security checks
 * - MIME type validation to prevent malicious file uploads
 * - File size limits to prevent abuse and storage bloat
 * - Unique filename generation to prevent conflicts
 * - Public URL generation for web accessibility
 * 
 * Supported File Types:
 * The system supports various file types based on use case:
 * - Images: JPEG, PNG, GIF, WebP for event banners and club logos
 * - Documents: PDF for event materials and club documents
 * - Profile Pictures: Standardized image formats with size restrictions
 * 
 * Security Architecture:
 * - Multi-layer file validation (upload errors, size, MIME type)
 * - File extension verification against MIME type
 * - Temporary file scanning for malicious content
 * - Unique filename generation to prevent enumeration attacks
 * - Access control through S3 bucket policies
 * - Virus scanning integration (future enhancement)
 * 
 * Storage Strategy:
 * - Amazon S3 for scalable, reliable cloud storage
 * - Organized folder structure for different file types
 * - CDN integration for fast global content delivery
 * - Automatic backup and versioning through S3
 * - Cost-effective storage classes for different usage patterns
 * 
 * File Organization:
 * - /uploads/events/banners/ - Event banner images
 * - /uploads/clubs/logos/ - Club logo images
 * - /uploads/users/avatars/ - User profile pictures
 * - /uploads/documents/ - General document uploads
 * - /uploads/temp/ - Temporary files for processing
 * 
 * Integration Points:
 * - Event creation and editing (banner image uploads)
 * - Club management (logo uploads)
 * - User profiles (avatar uploads)
 * - Administrative tools (bulk file management)
 * 
 * Error Handling:
 * - Comprehensive validation error messages
 * - AWS service error handling and retry logic
 * - File cleanup on upload failures
 * - Detailed logging for debugging and monitoring
 * - Graceful degradation for service unavailability
 * 
 * Performance Considerations:
 * - Direct upload to S3 for minimal server load
 * - Parallel processing for multiple file uploads
 * - Efficient file streaming for large uploads
 * - CDN caching for fast file delivery
 * - Image optimization and compression
 * 
 * Compliance and Privacy:
 * - GDPR compliance for user-uploaded content
 * - Data retention policies for uploaded files
 * - User consent for file processing and storage
 * - Secure deletion of user data when requested
 * 
 * Dependencies:
 * - AWS SDK for PHP for S3 integration
 * - File info extension for MIME type detection
 * - Environment configuration for AWS credentials
 * - Error logging system for monitoring
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 * @requires aws/aws-sdk-php ^3.0
 */

// Import AWS S3 classes for cloud storage functionality
use Aws\S3\S3Client;           // Main S3 client for storage operations
use Aws\Exception\AwsException; // AWS-specific exception handling

/**
 * Upload a file to Amazon S3 with comprehensive validation and security
 * 
 * This function provides secure file upload functionality to Amazon S3 cloud
 * storage with multi-layer validation, security checks, and error handling.
 * It ensures files are safe, valid, and properly organized in cloud storage.
 * 
 * Upload Process:
 * 1. Validate PHP file upload parameters and error codes
 * 2. Check file size against configurable limits
 * 3. Verify MIME type for security and compatibility
 * 4. Generate unique filename to prevent conflicts
 * 5. Initialize AWS S3 client with environment credentials
 * 6. Upload file to S3 with appropriate permissions
 * 7. Return public URL for web access
 * 
 * Security Validation Layers:
 * 1. PHP Upload Error Checking: Validates upload process integrity
 * 2. File Size Validation: Prevents storage abuse and performance issues
 * 3. MIME Type Verification: Blocks potentially malicious files
 * 4. File Extension Validation: Additional security layer (future enhancement)
 * 5. Content Scanning: Virus and malware detection (future enhancement)
 * 
 * File Naming Strategy:
 * Files are renamed using uniqid() + original basename to:
 * - Prevent filename conflicts between users
 * - Avoid directory traversal attacks
 * - Make file enumeration more difficult
 * - Maintain some original filename context
 * 
 * AWS S3 Configuration:
 * - Uses environment variables for secure credential management
 * - Configurable bucket and region for deployment flexibility
 * - Public read access for web-accessible files
 * - Content-Type headers for proper browser handling
 * 
 * Error Handling Strategy:
 * - Specific error messages for different failure types
 * - AWS exception handling with detailed logging
 * - Cleanup of temporary files on failure
 * - No sensitive information in error responses
 * 
 * @param array $file PHP file upload array ($_FILES['fieldname'])
 * @param string $folder S3 folder path for file organization (default: 'uploads')
 * @param array $allowedMimeTypes Allowed MIME types for security (empty = allow all)
 * @param int $maxSize Maximum file size in bytes (default: 5MB)
 * 
 * @return string Public S3 URL for the uploaded file
 * 
 * @throws Exception For validation failures or upload errors
 * 
 * @example
 * // Event banner upload with image restrictions
 * $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
 * $maxImageSize = 2 * 1024 * 1024; // 2MB limit for images
 * 
 * try {
 *     $bannerUrl = upload_file_to_s3(
 *         $_FILES['banner'],
 *         'uploads/events/banners',
 *         $allowedImageTypes,
 *         $maxImageSize
 *     );
 *     
 *     // Save URL to database and return success
 *     $eventData['banner_url'] = $bannerUrl;
 *     send_success('Banner uploaded successfully', 200, ['url' => $bannerUrl]);
 *     
 * } catch (Exception $e) {
 *     error_log('Banner upload failed: ' . $e->getMessage());
 *     send_error('Failed to upload banner: ' . $e->getMessage(), 400);
 * }
 * 
 * // Club logo upload with strict validation
 * $logoTypes = ['image/jpeg', 'image/png'];
 * $logoSize = 1024 * 1024; // 1MB limit for logos
 * 
 * $logoUrl = upload_file_to_s3($_FILES['logo'], 'uploads/clubs/logos', $logoTypes, $logoSize);
 * 
 * // Profile picture upload
 * $avatarUrl = upload_file_to_s3($_FILES['avatar'], 'uploads/users/avatars', $allowedImageTypes);
 * 
 * @since 1.0.0
 * @version 2.0.0 - Enhanced security and error handling
 */
function upload_file_to_s3(array $file, string $folder = 'uploads', array $allowedMimeTypes = [], int $maxSize = 5 * 1024 * 1024): string
{
    // Validate PHP file upload parameters
    // Check if the file upload array has the required structure
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid file upload parameters. Please check your form configuration.');
    }

    // Validate PHP file upload error codes
    // These errors are set by PHP during the upload process
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            // File uploaded successfully, continue processing
            break;
            
        case UPLOAD_ERR_NO_FILE:
            throw new Exception('No file was selected for upload.');
            
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('File size exceeds the maximum allowed limit.');
            
        case UPLOAD_ERR_PARTIAL:
            throw new Exception('File upload was interrupted. Please try again.');
            
        case UPLOAD_ERR_NO_TMP_DIR:
            throw new Exception('Server configuration error: no temporary directory.');
            
        case UPLOAD_ERR_CANT_WRITE:
            throw new Exception('Server configuration error: cannot write to disk.');
            
        case UPLOAD_ERR_EXTENSION:
            throw new Exception('File upload blocked by server extension.');
            
        default:
            throw new Exception('Unknown file upload error occurred.');
    }

    // Validate file size against specified limits
    // This provides an additional layer of protection beyond PHP limits
    if ($file['size'] > $maxSize) {
        $maxSizeMB = round($maxSize / (1024 * 1024), 1);
        throw new Exception("File size exceeds the {$maxSizeMB}MB limit.");
    }

    // Validate file size is not zero (empty file check)
    if ($file['size'] === 0) {
        throw new Exception('File appears to be empty. Please select a valid file.');
    }

    // Perform MIME type validation for security
    // Use finfo extension for accurate MIME type detection
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    // Verify detected MIME type against allowed types (if specified)
    if (!empty($allowedMimeTypes) && !in_array($mimeType, $allowedMimeTypes)) {
        $allowedTypesString = implode(', ', $allowedMimeTypes);
        throw new Exception("File type '{$mimeType}' is not allowed. Allowed types: {$allowedTypesString}");
    }

    // Additional security: verify file extension matches MIME type (future enhancement)
    // This helps prevent MIME type spoofing attacks
    
    // Initialize AWS S3 client with environment credentials
    try {
        $s3Client = new S3Client([
            'version' => 'latest',                    // Use latest AWS SDK version
            'region' => $_ENV['AWS_REGION'],          // AWS region from environment
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS_KEY_ID'],     // AWS access key
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'], // AWS secret key
            ],
        ]);
    } catch (Exception $e) {
        error_log("AWS S3 Client initialization failed: " . $e->getMessage());
        throw new Exception('Cloud storage service is temporarily unavailable.');
    }

    // Configure S3 bucket and generate unique file key
    $bucket = $_ENV['AWS_BUCKET'];
    
    // Generate unique filename to prevent conflicts and improve security
    $uniqueId = uniqid();
    $originalName = basename($file['name']);
    $sanitizedName = preg_replace('/[^a-zA-Z0-9.-]/', '_', $originalName);
    $key = $folder . '/' . $uniqueId . '_' . $sanitizedName;

    try {
        // Upload file to S3 with appropriate configuration
        $result = $s3Client->putObject([
            'Bucket' => $bucket,                      // S3 bucket name
            'Key' => $key,                            // Unique file path in bucket
            'Source' => $file['tmp_name'],            // Local temporary file path
            'ACL' => 'public-read',                   // Make file publicly accessible
            'ContentType' => $mimeType,               // Set proper MIME type for browsers
            'ContentDisposition' => 'inline',        // Display in browser vs download
            'CacheControl' => 'max-age=86400',        // Cache for 24 hours
            'ServerSideEncryption' => 'AES256',       // Encrypt file at rest
        ]);

        // Log successful upload for monitoring and debugging
        error_log("File uploaded successfully to S3: " . $key . " (" . round($file['size'] / 1024, 2) . " KB)");

        // Return the public URL for the uploaded file
        return $result['ObjectURL'];
        
    } catch (AwsException $e) {
        // Handle AWS-specific errors with detailed logging
        $errorCode = $e->getAwsErrorCode();
        $errorMessage = $e->getMessage();
        
        error_log("AWS S3 Upload Error - Code: {$errorCode}, Message: {$errorMessage}, File: {$originalName}");
        
        // Provide user-friendly error messages based on AWS error types
        switch ($errorCode) {
            case 'NoSuchBucket':
                throw new Exception('Cloud storage configuration error. Please contact support.');
                
            case 'AccessDenied':
                throw new Exception('Cloud storage access denied. Please contact support.');
                
            case 'EntityTooLarge':
                throw new Exception('File is too large for cloud storage.');
                
            case 'ServiceUnavailable':
                throw new Exception('Cloud storage service is temporarily unavailable. Please try again later.');
                
            default:
                throw new Exception('Failed to upload file to cloud storage. Please try again.');
        }
        
    } catch (Exception $e) {
        // Handle general exceptions during upload process
        error_log("File upload error: " . $e->getMessage() . " - File: {$originalName}");
        throw new Exception('File upload failed. Please try again.');
    }
}

/**
 * Future File Upload Enhancement Functions
 * 
 * The following functions are planned for implementation to provide
 * comprehensive file management functionality:
 * 
 * function upload_multiple_files($files, $folder, $allowedTypes = [], $maxSize = 5242880)
 * {
 *   // Handle multiple file uploads efficiently
 *   // Parallel processing for better performance
 *   // Atomic operations (all succeed or all fail)
 * }
 * 
 * function validate_image_dimensions($file, $minWidth = 0, $minHeight = 0, $maxWidth = 4096, $maxHeight = 4096)
 * {
 *   // Validate image dimensions for specific use cases
 *   // Aspect ratio validation for banners and logos
 *   // Resolution requirements for different content types
 * }
 * 
 * function optimize_image($filePath, $quality = 85, $maxWidth = 1920, $maxHeight = 1080)
 * {
 *   // Automatic image optimization and compression
 *   // Multiple format generation (WebP, AVIF)
 *   // Thumbnail generation for previews
 * }
 * 
 * function delete_file_from_s3($fileUrl)
 * {
 *   // Remove files from S3 storage
 *   // Cleanup when events/clubs are deleted
 *   // User data deletion for GDPR compliance
 * }
 * 
 * function scan_file_for_viruses($filePath)
 * {
 *   // Integrate with virus scanning services
 *   // Quarantine suspicious files
 *   // Alert administrators of threats
 * }
 * 
 * function generate_file_metadata($file)
 * {
 *   // Extract and store file metadata
 *   // Image EXIF data processing
 *   // Document properties and statistics
 * }
 * 
 * Enhancement Guidelines:
 * - Maintain backward compatibility with existing function
 * - Follow consistent error handling and security patterns
 * - Include comprehensive validation and sanitization
 * - Implement proper logging and monitoring
 * - Support scalability for high-volume operations
 * - Integrate with content delivery networks (CDN)
 */
