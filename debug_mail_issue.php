<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\ConfigurationService;
use App\Services\MailService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

echo "\n=== DEBUG MAIL CONFIGURATION ISSUE ===\n";

// 1. Check mail configuration from .env
echo "\n1. Mail Configuration from .env:\n";
echo '   MAIL_MAILER: '.env('MAIL_MAILER')."\n";
echo '   MAIL_HOST: '.env('MAIL_HOST')."\n";
echo '   MAIL_PORT: '.env('MAIL_PORT')."\n";
echo '   MAIL_USERNAME: '.env('MAIL_USERNAME')."\n";
echo '   MAIL_ENCRYPTION: '.env('MAIL_ENCRYPTION')."\n";
echo '   MAIL_FROM_ADDRESS: '.env('MAIL_FROM_ADDRESS')."\n";
echo '   MAIL_FROM_NAME: '.env('MAIL_FROM_NAME')."\n";
echo '   QUEUE_CONNECTION: '.env('QUEUE_CONNECTION')."\n";

// 2. Check database configuration
echo "\n2. Mail Configuration from Database:\n";
$configService = app(ConfigurationService::class);

$dbConfigs = [
    'email.smtpHost' => $configService->getConfig('email.smtpHost'),
    'email.smtpPort' => $configService->getConfig('email.smtpPort'),
    'email.smtpUsername' => $configService->getConfig('email.smtpUsername'),
    'email.smtpPassword' => $configService->getConfig('email.smtpPassword') ? '***HIDDEN***' : null,
    'email.smtpEncryption' => $configService->getConfig('email.smtpEncryption'),
    'email.senderEmail' => $configService->getConfig('email.senderEmail'),
    'email.senderName' => $configService->getConfig('email.senderName'),
];

foreach ($dbConfigs as $key => $value) {
    echo "   $key: ".($value ?? 'NOT SET')."\n";
}

// 3. Check Laravel's runtime configuration
echo "\n3. Laravel Runtime Mail Configuration:\n";
echo '   Default mailer: '.Config::get('mail.default')."\n";
echo '   SMTP Host: '.Config::get('mail.mailers.smtp.host')."\n";
echo '   SMTP Port: '.Config::get('mail.mailers.smtp.port')."\n";
echo '   SMTP Username: '.Config::get('mail.mailers.smtp.username')."\n";
echo '   SMTP Encryption: '.Config::get('mail.mailers.smtp.encryption')."\n";
echo '   From Address: '.Config::get('mail.from.address')."\n";
echo '   From Name: '.Config::get('mail.from.name')."\n";

// 4. Check if there are pending jobs in queue
echo "\n4. Queue Status:\n";
$pendingJobs = DB::table('jobs')->count();
$failedJobs = DB::table('failed_jobs')->count();
echo "   Pending jobs: $pendingJobs\n";
echo "   Failed jobs: $failedJobs\n";

if ($failedJobs > 0) {
    echo "   Recent failed jobs:\n";
    $recentFailed = DB::table('failed_jobs')
        ->orderBy('failed_at', 'desc')
        ->limit(3)
        ->get(['payload', 'exception', 'failed_at']);

    foreach ($recentFailed as $job) {
        $payload = json_decode($job->payload);
        echo "     - Failed at: {$job->failed_at}\n";
        echo '       Job: '.($payload->displayName ?? 'Unknown')."\n";
        echo '       Error: '.substr($job->exception, 0, 100)."...\n\n";
    }
}

// 5. Test mail service
echo "\n5. Testing Mail Service:\n";

try {
    $mailService = app(MailService::class);
    echo "   MailService instance created successfully\n";

    // Test connection
    $testResult = $mailService->testConnection();
    echo '   Connection test result: '.$testResult['status']."\n";
    if ($testResult['status'] === 'error') {
        echo '   Error message: '.$testResult['message']."\n";
    }
} catch (\Exception $e) {
    echo '   Error creating MailService: '.$e->getMessage()."\n";
}

// 6. Test sending a simple email
echo "\n6. Testing Direct Mail Send:\n";

try {
    // Find an admin user for testing
    $adminUser = User::where('email', 'admin@admin.com')->first();

    if (! $adminUser) {
        echo "   Admin user not found, creating test user...\n";
        $adminUser = new User;
        $adminUser->name = 'Test Admin';
        $adminUser->email = 'admin@admin.com';
        $adminUser->id = 1;
    }

    // Try to send using MailService
    echo "   Attempting to send test email via MailService...\n";
    $result = $mailService->sendNotificationEmail(
        $adminUser,
        'Test Email from Debug Script',
        'This is a test email to debug mail configuration issues.',
        ['email_type' => 'notification']
    );

    echo '   Send result: '.($result ? 'SUCCESS' : 'FAILED')."\n";

} catch (\Exception $e) {
    echo '   Error sending test email: '.$e->getMessage()."\n";
    echo '   Stack trace: '.$e->getTraceAsString()."\n";
}

// 7. Test Password Reset
echo "\n7. Testing Password Reset Flow:\n";

try {
    // Generate a test token
    $token = \Illuminate\Support\Str::random(60);

    // Try to insert into password_reset_tokens table
    $inserted = DB::table('password_reset_tokens')->insert([
        'email' => $adminUser->email,
        'token' => hash('sha256', $token),
        'created_at' => now(),
    ]);

    echo '   Token inserted into password_reset_tokens: '.($inserted ? 'YES' : 'NO')."\n";

    // Try to send password reset email
    echo "   Attempting to send password reset email...\n";
    $result = $mailService->sendPasswordResetEmail($adminUser, $token);

    echo '   Password reset email result: '.($result ? 'SUCCESS' : 'FAILED')."\n";

    // Clean up test token
    DB::table('password_reset_tokens')->where('email', $adminUser->email)->delete();

} catch (\Exception $e) {
    echo '   Error in password reset test: '.$e->getMessage()."\n";
}

// 8. Check logs for recent errors
echo "\n8. Recent Mail-Related Errors in Logs:\n";

$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentMailErrors = [];

    foreach ($lines as $line) {
        if (stripos($line, 'mail') !== false && stripos($line, 'error') !== false) {
            $recentMailErrors[] = $line;
        }
    }

    if (count($recentMailErrors) > 0) {
        echo '   Found '.count($recentMailErrors)." mail-related errors\n";
        echo "   Last 3 errors:\n";
        $lastThree = array_slice($recentMailErrors, -3);
        foreach ($lastThree as $error) {
            echo '   '.substr($error, 0, 150)."...\n";
        }
    } else {
        echo "   No recent mail-related errors found in logs\n";
    }
}

echo "\n=== END DEBUG ===\n\n";
