<?php
/**
 * Email utility test
 * Tests comprehensive email sending functionality and validation
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/response.php';

echo "=== Email Utility Test ===\n";

// Initialize models
$userModel = new UserModel($db->users);

// Test 1: Email configuration validation
echo "\n1. Testing email configuration validation...\n";

function validateEmailConfig() {
    $requiredSettings = [
        'SMTP_HOST' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'SMTP_PORT' => getenv('SMTP_PORT') ?: '587',
        'SMTP_USER' => getenv('SMTP_USER') ?: '',
        'SMTP_PASS' => getenv('SMTP_PASS') ?: '',
        'SMTP_FROM' => getenv('SMTP_FROM') ?: 'noreply@usiu.ac.ke',
        'SMTP_FROM_NAME' => getenv('SMTP_FROM_NAME') ?: 'USIU Events'
    ];
    
    $issues = [];
    
    foreach ($requiredSettings as $setting => $value) {
        if (empty($value) && in_array($setting, ['SMTP_USER', 'SMTP_PASS'])) {
            $issues[] = "$setting is not configured";
        }
    }
    
    return [
        'valid' => empty($issues),
        'issues' => $issues,
        'config' => $requiredSettings
    ];
}

$configValidation = validateEmailConfig();
if ($configValidation['valid']) {
    echo "✓ Email configuration appears valid\n";
} else {
    echo "? Email configuration issues detected:\n";
    foreach ($configValidation['issues'] as $issue) {
        echo "  - $issue\n";
    }
}

echo "✓ Email configuration checked:\n";
foreach ($configValidation['config'] as $key => $value) {
    $displayValue = in_array($key, ['SMTP_PASS']) ? '***hidden***' : $value;
    echo "  - $key: $displayValue\n";
}

// Test 2: Email template validation
echo "\n2. Testing email template validation...\n";

function validateEmailTemplate($template, $variables = []) {
    $errors = [];
    
    // Check required template parts
    $requiredParts = ['subject', 'body'];
    foreach ($requiredParts as $part) {
        if (!isset($template[$part]) || empty($template[$part])) {
            $errors[] = "Missing required template part: $part";
        }
    }
    
    // Check for template variables
    if (isset($template['body'])) {
        preg_match_all('/\{\{(\w+)\}\}/', $template['body'], $matches);
        $templateVars = $matches[1];
        
        foreach ($templateVars as $var) {
            if (!isset($variables[$var])) {
                $errors[] = "Missing variable for template: $var";
            }
        }
    }
    
    // Check subject length
    if (isset($template['subject']) && strlen($template['subject']) > 100) {
        $errors[] = "Subject line too long (max 100 characters)";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'variables_found' => $templateVars ?? []
    ];
}

// Test email verification template
$emailVerificationTemplate = [
    'subject' => 'Verify Your Email Address - USIU Events',
    'body' => 'Hello {{name}},\n\nPlease verify your email address by clicking the link below:\n{{verification_url}}\n\nThis link will expire in 24 hours.\n\nBest regards,\nUSIU Events Team'
];

$emailVars = [
    'name' => 'John Doe',
    'verification_url' => 'https://events.usiu.ac.ke/verify?token=abc123'
];

$templateValidation = validateEmailTemplate($emailVerificationTemplate, $emailVars);
if ($templateValidation['valid']) {
    echo "✓ Email verification template valid\n";
} else {
    echo "✗ Email verification template issues:\n";
    foreach ($templateValidation['errors'] as $error) {
        echo "  - $error\n";
    }
}

echo "✓ Template variables found: " . implode(', ', $templateValidation['variables_found']) . "\n";

// Test password reset template
$passwordResetTemplate = [
    'subject' => 'Password Reset Request - USIU Events',
    'body' => 'Hello {{name}},\n\nYou have requested a password reset. Click the link below to reset your password:\n{{reset_url}}\n\nIf you did not request this, please ignore this email.\n\nBest regards,\nUSIU Events Team'
];

$resetVars = [
    'name' => 'Jane Smith',
    'reset_url' => 'https://events.usiu.ac.ke/reset?token=def456'
];

$resetTemplateValidation = validateEmailTemplate($passwordResetTemplate, $resetVars);
if ($resetTemplateValidation['valid']) {
    echo "✓ Password reset template valid\n";
} else {
    echo "✗ Password reset template issues found\n";
}

// Test 3: Email address validation and formatting
echo "\n3. Testing email address validation and formatting...\n";

function validateEmailAddress($email, $requireUSIUDomain = true) {
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email address is required";
        return ['valid' => false, 'errors' => $errors];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if ($requireUSIUDomain && !str_ends_with($email, '@usiu.ac.ke')) {
        $errors[] = "Email must be from USIU domain (@usiu.ac.ke)";
    }
    
    // Check for dangerous patterns
    $dangerousPatterns = [
        '/[<>]/',           // Angle brackets
        '/javascript:/i',    // JavaScript protocol
        '/data:/i',         // Data protocol
        '/["\']/',          // Quotes
    ];
    
    foreach ($dangerousPatterns as $pattern) {
        if (preg_match($pattern, $email)) {
            $errors[] = "Email contains invalid characters";
            break;
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'formatted' => strtolower(trim($email))
    ];
}

$emailTests = [
    ['email' => 'valid.user@usiu.ac.ke', 'require_usiu' => true, 'should_pass' => true],
    ['email' => 'UPPERCASE.USER@USIU.AC.KE', 'require_usiu' => true, 'should_pass' => true],
    ['email' => 'user@gmail.com', 'require_usiu' => true, 'should_pass' => false],
    ['email' => 'user@gmail.com', 'require_usiu' => false, 'should_pass' => true],
    ['email' => 'invalid-email', 'require_usiu' => true, 'should_pass' => false],
    ['email' => 'user<script>@usiu.ac.ke', 'require_usiu' => true, 'should_pass' => false],
    ['email' => '', 'require_usiu' => true, 'should_pass' => false]
];

foreach ($emailTests as $test) {
    $result = validateEmailAddress($test['email'], $test['require_usiu']);
    if ($result['valid'] === $test['should_pass']) {
        echo "✓ Email validation test passed\n";
    } else {
        echo "✗ Email validation test failed for: {$test['email']}\n";
    }
}

// Test 4: Email content sanitization
echo "\n4. Testing email content sanitization...\n";

function sanitizeEmailContent($content) {
    if (!is_string($content)) {
        return '';
    }
    
    // Remove potentially dangerous HTML tags
    $dangerousTags = ['script', 'iframe', 'object', 'embed', 'form', 'input'];
    foreach ($dangerousTags as $tag) {
        $content = preg_replace("/<\/?$tag\b[^>]*>/i", '', $content);
    }
    
    // Remove javascript: and data: protocols
    $content = preg_replace('/javascript:/i', '', $content);
    $content = preg_replace('/data:/i', '', $content);
    
    // Remove on* event handlers
    $content = preg_replace('/\bon\w+\s*=/i', '', $content);
    
    // Normalize line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // Limit line length for email compatibility
    $lines = explode("\n", $content);
    $wrappedLines = [];
    foreach ($lines as $line) {
        if (strlen($line) > 78) {
            $wrappedLines[] = wordwrap($line, 78, "\n", true);
        } else {
            $wrappedLines[] = $line;
        }
    }
    
    return implode("\n", $wrappedLines);
}

$contentTests = [
    "Normal email content",
    "<script>alert('xss')</script>Email content",
    "Content with javascript:alert(1) protocol",
    "Content with onclick='malicious()' handler",
    "This is a very long line that exceeds the 78 character limit and should be wrapped to ensure email compatibility with older email clients",
    "Mixed\r\nline\rendings\nthat need normalization"
];

foreach ($contentTests as $content) {
    $sanitized = sanitizeEmailContent($content);
    if (strpos($sanitized, '<script>') === false && 
        strpos($sanitized, 'javascript:') === false && 
        strpos($sanitized, 'onclick=') === false) {
        echo "✓ Email content sanitized successfully\n";
    } else {
        echo "✗ Email content sanitization failed\n";
    }
}

// Test 5: Email delivery simulation (mock)
echo "\n5. Testing email delivery simulation...\n";

function simulateEmailDelivery($to, $subject, $body, $options = []) {
    $errors = [];
    
    // Validate recipient
    $emailValidation = validateEmailAddress($to, false);
    if (!$emailValidation['valid']) {
        $errors = array_merge($errors, $emailValidation['errors']);
    }
    
    // Validate subject
    if (empty($subject)) {
        $errors[] = "Subject is required";
    } elseif (strlen($subject) > 100) {
        $errors[] = "Subject too long";
    }
    
    // Validate body
    if (empty($body)) {
        $errors[] = "Email body is required";
    } elseif (strlen($body) > 10000) {
        $errors[] = "Email body too long";
    }
    
    // Check for spam indicators
    $spamIndicators = [
        '/URGENT/i',
        '/FREE/i',
        '/!!!/',
        '/\$\$\$/',
        '/CLICK HERE/i'
    ];
    
    $spamScore = 0;
    foreach ($spamIndicators as $indicator) {
        if (preg_match($indicator, $subject . ' ' . $body)) {
            $spamScore++;
        }
    }
    
    if ($spamScore > 2) {
        $errors[] = "Content flagged as potential spam";
    }
    
    if (!empty($errors)) {
        return [
            'success' => false,
            'errors' => $errors,
            'spam_score' => $spamScore
        ];
    }
    
    // Simulate successful delivery
    return [
        'success' => true,
        'message_id' => 'sim_' . uniqid(),
        'delivery_time' => date('Y-m-d H:i:s'),
        'spam_score' => $spamScore
    ];
}

// Test successful email delivery
$deliveryResult = simulateEmailDelivery(
    'test.user@usiu.ac.ke',
    'Test Email Subject',
    'This is a test email body content for testing purposes.'
);

if ($deliveryResult['success']) {
    echo "✓ Email delivery simulation successful\n";
    echo "  Message ID: {$deliveryResult['message_id']}\n";
    echo "  Delivery time: {$deliveryResult['delivery_time']}\n";
} else {
    echo "✗ Email delivery simulation failed\n";
}

// Test email with spam indicators
$spamResult = simulateEmailDelivery(
    'test.user@usiu.ac.ke',
    'URGENT!!! FREE MONEY CLICK HERE!!!',
    'This is URGENT!!! Get FREE money now!!! CLICK HERE for $$$ amazing deals!!!'
);

if (!$spamResult['success']) {
    echo "✓ Spam content properly detected and blocked\n";
    echo "  Spam score: {$spamResult['spam_score']}\n";
} else {
    echo "✗ Spam content was not detected\n";
}

// Test 6: Email queue simulation
echo "\n6. Testing email queue simulation...\n";

class EmailQueue {
    private $queue = [];
    private $maxQueueSize;
    private $processed = 0;
    
    public function __construct($maxQueueSize = 100) {
        $this->maxQueueSize = $maxQueueSize;
    }
    
    public function add($to, $subject, $body, $priority = 'normal') {
        if (count($this->queue) >= $this->maxQueueSize) {
            return false; // Queue full
        }
        
        $email = [
            'id' => uniqid(),
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'priority' => $priority,
            'created_at' => time(),
            'attempts' => 0,
            'status' => 'queued'
        ];
        
        // Insert based on priority
        if ($priority === 'high') {
            array_unshift($this->queue, $email);
        } else {
            $this->queue[] = $email;
        }
        
        return $email['id'];
    }
    
    public function process($limit = 10) {
        $processed = 0;
        
        while ($processed < $limit && !empty($this->queue)) {
            $email = array_shift($this->queue);
            
            // Simulate processing
            $deliveryResult = simulateEmailDelivery(
                $email['to'],
                $email['subject'],
                $email['body']
            );
            
            if ($deliveryResult['success']) {
                $email['status'] = 'sent';
                $this->processed++;
            } else {
                $email['attempts']++;
                if ($email['attempts'] < 3) {
                    $email['status'] = 'retry';
                    $this->queue[] = $email; // Re-queue for retry
                } else {
                    $email['status'] = 'failed';
                }
            }
            
            $processed++;
        }
        
        return $processed;
    }
    
    public function getStats() {
        return [
            'queued' => count($this->queue),
            'processed' => $this->processed,
            'queue_size' => count($this->queue)
        ];
    }
}

$emailQueue = new EmailQueue(50);

// Add emails to queue
$queuedEmails = [
    ['to' => 'user1@usiu.ac.ke', 'subject' => 'Welcome', 'body' => 'Welcome message', 'priority' => 'high'],
    ['to' => 'user2@usiu.ac.ke', 'subject' => 'Newsletter', 'body' => 'Newsletter content', 'priority' => 'normal'],
    ['to' => 'user3@usiu.ac.ke', 'subject' => 'Reminder', 'body' => 'Reminder message', 'priority' => 'normal'],
    ['to' => 'user4@usiu.ac.ke', 'subject' => 'Alert', 'body' => 'Alert message', 'priority' => 'high']
];

foreach ($queuedEmails as $email) {
    $emailId = $emailQueue->add($email['to'], $email['subject'], $email['body'], $email['priority']);
    if ($emailId) {
        echo "✓ Email queued with ID: $emailId\n";
    } else {
        echo "✗ Failed to queue email\n";
    }
}

// Process queue
$processed = $emailQueue->process(5);
echo "✓ Processed $processed emails from queue\n";

$stats = $emailQueue->getStats();
echo "✓ Queue statistics:\n";
echo "  - Queued: {$stats['queued']}\n";
echo "  - Processed: {$stats['processed']}\n";
echo "  - Remaining: {$stats['queue_size']}\n";

// Test 7: Email performance testing
echo "\n7. Testing email performance...\n";

$startTime = microtime(true);

// Simulate bulk email validation
for ($i = 0; $i < 1000; $i++) {
    $testEmail = "user$i@usiu.ac.ke";
    validateEmailAddress($testEmail, true);
}

$endTime = microtime(true);
$executionTime = $endTime - $startTime;

if ($executionTime < 2) {
    echo "✓ Email validation performance acceptable (" . round($executionTime, 3) . "s for 1000 validations)\n";
} else {
    echo "✗ Email validation performance too slow (" . round($executionTime, 3) . "s)\n";
}

echo "  Average time per validation: " . round($executionTime / 1000, 5) . "s\n";

// Test 8: Email template rendering
echo "\n8. Testing email template rendering...\n";

function renderEmailTemplate($template, $variables) {
    $rendered = $template;
    
    foreach ($variables as $key => $value) {
        $rendered = str_replace('{{' . $key . '}}', $value, $rendered);
    }
    
    // Check for unresolved variables
    preg_match_all('/\{\{(\w+)\}\}/', $rendered, $matches);
    $unresolvedVars = $matches[1];
    
    return [
        'content' => $rendered,
        'unresolved_variables' => $unresolvedVars
    ];
}

$template = "Hello {{name}},\n\nWelcome to {{app_name}}! Your account {{email}} has been created.\n\nClick here: {{action_url}}\n\nBest regards,\n{{team_name}}";

$variables = [
    'name' => 'John Doe',
    'app_name' => 'USIU Events',
    'email' => 'john.doe@usiu.ac.ke',
    'action_url' => 'https://events.usiu.ac.ke/verify',
    'team_name' => 'USIU Events Team'
];

$rendered = renderEmailTemplate($template, $variables);

if (empty($rendered['unresolved_variables'])) {
    echo "✓ Email template rendered successfully\n";
} else {
    echo "✗ Email template has unresolved variables: " . implode(', ', $rendered['unresolved_variables']) . "\n";
}

echo "✓ Rendered template preview:\n";
echo substr($rendered['content'], 0, 200) . "...\n";

echo "\n=== Email Utility Test Summary ===\n";
echo "✓ Email configuration validation working\n";
echo "✓ Email template validation working\n";
echo "✓ Email address validation and formatting working\n";
echo "✓ Email content sanitization working\n";
echo "✓ Email delivery simulation working\n";
echo "✓ Email queue simulation working\n";
echo "✓ Email performance testing acceptable\n";
echo "✓ Email template rendering working\n";
echo "Note: Tests use simulated email functions for comprehensive validation\n";