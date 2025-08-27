<?php

// Simple mail test script for production
// Run this directly on the server: php test_mail_simple.php

echo "\n=== SIMPLE MAIL TEST ===\n";

// Test 1: Check PHP mail configuration
echo "\n1. PHP Mail Configuration:\n";
echo "   sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "   SMTP: " . ini_get('SMTP') . "\n";
echo "   smtp_port: " . ini_get('smtp_port') . "\n";

// Test 2: Try to send a simple mail using PHP's mail() function
echo "\n2. Testing PHP mail() function:\n";
$to = "test@example.com";
$subject = "Test Mail";
$message = "This is a test email.";
$headers = "From: info@comersia.app\r\n";

$result = @mail($to, $subject, $message, $headers);
echo "   mail() function result: " . ($result ? "SUCCESS" : "FAILED") . "\n";

// Test 3: Test SMTP connection directly
echo "\n3. Testing SMTP Connection:\n";
$host = "mail.comersia.app";
$port = 465;
$timeout = 10;

echo "   Connecting to $host:$port...\n";
$fp = @fsockopen("ssl://" . $host, $port, $errno, $errstr, $timeout);

if ($fp) {
    echo "   ✓ Connection successful!\n";
    $response = fgets($fp, 512);
    echo "   Server response: " . $response;
    fclose($fp);
} else {
    echo "   ✗ Connection failed!\n";
    echo "   Error $errno: $errstr\n";
}

// Test 4: Check if Laravel mail config is accessible
echo "\n4. Checking Laravel Configuration:\n";

// Load .env file manually
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $mailConfig = [];
    
    foreach ($lines as $line) {
        if (strpos($line, 'MAIL_') === 0) {
            list($key, $value) = explode('=', $line, 2);
            $mailConfig[$key] = trim($value, '"');
        }
    }
    
    echo "   MAIL_HOST: " . ($mailConfig['MAIL_HOST'] ?? 'NOT SET') . "\n";
    echo "   MAIL_PORT: " . ($mailConfig['MAIL_PORT'] ?? 'NOT SET') . "\n";
    echo "   MAIL_USERNAME: " . ($mailConfig['MAIL_USERNAME'] ?? 'NOT SET') . "\n";
    echo "   MAIL_ENCRYPTION: " . ($mailConfig['MAIL_ENCRYPTION'] ?? 'NOT SET') . "\n";
    echo "   MAIL_FROM_ADDRESS: " . ($mailConfig['MAIL_FROM_ADDRESS'] ?? 'NOT SET') . "\n";
    echo "   QUEUE_CONNECTION: " . ($mailConfig['QUEUE_CONNECTION'] ?? 'NOT SET') . "\n";
} else {
    echo "   .env file not found!\n";
}

// Test 5: Try to send email using SwiftMailer directly (if available)
echo "\n5. Testing Direct SMTP with basic PHP:\n";
if (class_exists('Swift_SmtpTransport')) {
    try {
        $transport = (new Swift_SmtpTransport($host, $port, 'ssl'))
            ->setUsername($mailConfig['MAIL_USERNAME'] ?? '')
            ->setPassword($mailConfig['MAIL_PASSWORD'] ?? '');
        
        $mailer = new Swift_Mailer($transport);
        
        $message = (new Swift_Message('Test Email'))
            ->setFrom([$mailConfig['MAIL_FROM_ADDRESS'] ?? 'test@example.com'])
            ->setTo(['test@example.com'])
            ->setBody('This is a test email');
        
        $result = $mailer->send($message);
        echo "   SwiftMailer result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    } catch (Exception $e) {
        echo "   SwiftMailer error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   SwiftMailer not available\n";
}

echo "\n=== END TEST ===\n";