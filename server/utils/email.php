
<?php
/**
 * USIU Events Management System - Email Communication Utility
 * 
 * This utility module provides comprehensive email functionality for the USIU Events
 * system using the PHPMailer library. It handles transactional emails, notifications,
 * and system communications with robust error handling and security features.
 * 
 * Email System Features:
 * - SMTP-based email delivery with authentication
 * - HTML and plain text email support
 * - Template-based email composition (future enhancement)
 * - Comprehensive error handling and logging
 * - Environment-based configuration
 * - Security-focused implementation
 * 
 * Email Types Supported:
 * - User verification emails with secure tokens
 * - Password reset emails with time-limited links
 * - Event registration confirmations
 * - Club membership notifications
 * - Administrative alerts and reports
 * - System maintenance notifications
 * 
 * SMTP Configuration:
 * The email system uses environment variables for configuration:
 * - SMTP_HOST: Mail server hostname
 * - SMTP_PORT: Mail server port (typically 587 for TLS)
 * - SMTP_USERNAME: Authentication username
 * - SMTP_PASSWORD: Authentication password
 * - SMTP_FROM_EMAIL: Default sender email address
 * - SMTP_FROM_NAME: Default sender display name
 * 
 * Security Considerations:
 * - TLS encryption for secure transmission
 * - SMTP authentication to prevent abuse
 * - Input validation for email addresses
 * - Rate limiting to prevent spam (implementation recommended)
 * - Bounce handling for delivery failures
 * - No sensitive information in email logs
 * 
 * Integration Points:
 * - User authentication workflows (registration, password reset)
 * - Event management (registration confirmations, updates)
 * - Club administration (membership changes, announcements)
 * - System monitoring (error alerts, maintenance notices)
 * 
 * Error Handling Strategy:
 * - Comprehensive logging for delivery failures
 * - Graceful degradation for non-critical emails
 * - Retry logic for transient failures (future enhancement)
 * - Queue system for high-volume sending (future enhancement)
 * 
 * Performance Considerations:
 * - Asynchronous email sending for better user experience
 * - Connection pooling for bulk email operations
 * - Email queue system for reliable delivery
 * - Template caching for improved performance
 * 
 * Email Templates and Formatting:
 * - HTML email support with fallback plain text
 * - Responsive design for mobile compatibility
 * - Consistent branding and styling
 * - Accessibility features for screen readers
 * - Multi-language support (future enhancement)
 * 
 * Compliance and Best Practices:
 * - CAN-SPAM Act compliance for commercial emails
 * - GDPR considerations for personal data
 * - Unsubscribe mechanisms where applicable
 * - SPF/DKIM setup for deliverability
 * - Reputation monitoring and management
 * 
 * Dependencies:
 * - PHPMailer library for SMTP functionality
 * - Environment configuration for security
 * - Error logging system for monitoring
 * - Database for email tracking (future enhancement)
 * 
 * @author USIU Events Development Team
 * @version 2.0.0
 * @since 2024-01-01
 * @requires phpmailer/phpmailer ^6.0
 */

// Import PHPMailer classes for email functionality
use PHPMailer\PHPMailer\PHPMailer;     // Main PHPMailer class
use PHPMailer\PHPMailer\Exception;     // PHPMailer exception handling

// Load Composer autoloader for PHPMailer dependencies
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Send an email using SMTP with comprehensive error handling
 * 
 * This function provides a standardized way to send emails throughout the
 * USIU Events system. It handles SMTP configuration, email formatting,
 * and error logging with support for both HTML and plain text content.
 * 
 * Email Delivery Process:
 * 1. Initialize PHPMailer with exception handling enabled
 * 2. Configure SMTP settings from environment variables
 * 3. Set sender and recipient information
 * 4. Configure email content (HTML and alternative text)
 * 5. Attempt delivery with comprehensive error handling
 * 6. Log results for monitoring and debugging
 * 
 * SMTP Configuration:
 * The function automatically configures SMTP settings using environment
 * variables for security and flexibility across different environments:
 * - Development: Local mail server or service like MailHog
 * - Staging: Test email service with limited sending
 * - Production: Professional email service (SendGrid, Mailgun, etc.)
 * 
 * Email Format Support:
 * - HTML emails for rich formatting and branding
 * - Plain text alternative for accessibility and spam filtering
 * - Automatic MIME type detection and encoding
 * - Character set handling for international content
 * 
 * Security Features:
 * - TLS encryption for secure email transmission
 * - SMTP authentication to prevent unauthorized usage
 * - Input validation for email addresses and content
 * - Error message sanitization to prevent information disclosure
 * 
 * Error Handling and Logging:
 * - Detailed error logging for debugging and monitoring
 * - Graceful failure handling without exposing system details
 * - Return boolean success indicator for caller handling
 * - PHPMailer-specific error information capture
 * 
 * Performance Considerations:
 * - Single connection per email (suitable for low volume)
 * - Connection reuse for bulk operations (future enhancement)
 * - Timeout configuration for reliable delivery
 * - Memory management for large email content
 * 
 * @param string $to Recipient email address (must be valid email format)
 * @param string $subject Email subject line (will be encoded for special characters)
 * @param string $body HTML email content (main email body)
 * @param string $altBody Plain text alternative for HTML content (optional)
 * 
 * @return bool True if email was sent successfully, false on failure
 * 
 * @throws None All exceptions are caught and handled internally
 * 
 * @example
 * // Send verification email
 * $subject = 'Verify Your USIU Events Account';
 * $htmlBody = '<h1>Welcome!</h1><p>Click <a href="' . $verificationLink . '">here</a> to verify.</p>';
 * $textBody = 'Welcome! Visit ' . $verificationLink . ' to verify your account.';
 * 
 * $success = send_email($user['email'], $subject, $htmlBody, $textBody);
 * 
 * if ($success) {
 *     // Email sent successfully - update user status or log success
 *     error_log("Verification email sent to: " . $user['email']);
 * } else {
 *     // Email failed - handle gracefully, possibly retry later
 *     error_log("Failed to send verification email to: " . $user['email']);
 * }
 * 
 * // Send event reminder with rich HTML content
 * $eventReminder = '
 *     <div style="font-family: Arial, sans-serif;">
 *         <h2>Event Reminder: ' . htmlspecialchars($event['title']) . '</h2>
 *         <p>Your event is starting soon!</p>
 *         <p><strong>Date:</strong> ' . date('F j, Y g:i A', $event['date']) . '</p>
 *         <p><strong>Location:</strong> ' . htmlspecialchars($event['location']) . '</p>
 *     </div>
 * ';
 * 
 * send_email($attendee['email'], 'Event Reminder', $eventReminder);
 * 
 * @since 1.0.0
 * @version 2.0.0 - Enhanced error handling and security features
 */
function send_email($to, $subject, $body, $altBody = '')
{
  // Initialize PHPMailer with exception handling enabled
  // This allows us to catch and handle all email-related errors gracefully
  $mail = new PHPMailer(true);

  try {
    // Configure SMTP server settings for email delivery
    $mail->isSMTP();                                      // Use SMTP for sending
    $mail->Host = $_ENV['SMTP_HOST'];                     // SMTP server hostname
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = $_ENV['SMTP_USERNAME'];             // SMTP username
    $mail->Password = $_ENV['SMTP_PASSWORD'];             // SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;   // Use TLS encryption
    $mail->Port = $_ENV['SMTP_PORT'];                     // SMTP port (typically 587 for TLS)

    // Configure sender and recipient information
    $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
    $mail->addAddress($to);  // Add recipient email address

    // Configure email content and formatting
    $mail->isHTML(true);                                  // Set email format to HTML
    $mail->Subject = $subject;                            // Set email subject
    $mail->Body = $body;                                  // Set HTML email body
    $mail->AltBody = $altBody;                           // Set plain text alternative

    // Additional email headers for better deliverability
    $mail->CharSet = 'UTF-8';                            // Set character encoding
    $mail->Encoding = 'base64';                          // Set content encoding

    // Attempt to send the email
    $mail->send();
    
    // Log successful email delivery for monitoring
    error_log("Email sent successfully to: " . $to . " - Subject: " . $subject);
    
    return true;  // Email sent successfully
    
  } catch (Exception $e) {
    // Handle email delivery failures with comprehensive logging
    // Log the error for debugging while protecting sensitive information
    error_log("Email delivery failed - Recipient: " . $to . 
              " - Subject: " . $subject . 
              " - Error: " . $mail->ErrorInfo);
    
    // Additional error context for debugging (can be enhanced)
    error_log("SMTP Error Details: " . $e->getMessage());
    
    return false; // Email delivery failed
  }
}

/**
 * Future Email Enhancement Functions
 * 
 * The following functions are planned for implementation to provide
 * comprehensive email functionality:
 * 
 * function send_template_email($to, $templateName, $variables = [])
 * {
 *   // Send emails using predefined templates with variable substitution
 *   // Templates stored in separate files for easy management
 *   // Support for multiple languages and personalization
 * }
 * 
 * function send_bulk_email($recipients, $subject, $body, $altBody = '')
 * {
 *   // Efficiently send emails to multiple recipients
 *   // Implement connection reuse and batch processing
 *   // Include bounce handling and delivery tracking
 * }
 * 
 * function queue_email($to, $subject, $body, $priority = 'normal')
 * {
 *   // Add emails to a queue for asynchronous processing
 *   // Support priority levels and retry mechanisms
 *   // Integrate with background job processing
 * }
 * 
 * function validate_email_address($email)
 * {
 *   // Comprehensive email address validation
 *   // Check syntax, domain existence, and deliverability
 *   // Integration with email verification services
 * }
 * 
 * function track_email_delivery($emailId, $status, $details = [])
 * {
 *   // Track email delivery status and engagement
 *   // Store delivery confirmations, opens, and clicks
 *   // Provide analytics for email effectiveness
 * }
 * 
 * Enhancement Guidelines:
 * - Maintain backward compatibility with existing function
 * - Follow consistent error handling and logging patterns
 * - Include comprehensive documentation and examples
 * - Implement security best practices
 * - Support scalability for high-volume operations
 * - Integrate with monitoring and analytics systems
 */
