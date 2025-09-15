<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Mail\MailManager;
use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DebugMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:debug {--test-send} {--email=admin@admin.com}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug mail configuration and optionally send test email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== MAIL SYSTEM DEBUG ===');

        // 1. Check environment configuration
        $this->info("\n1. Environment Configuration:");
        $this->table(
            ['Setting', 'Value'],
            [
                ['MAIL_MAILER', config('mail.default')],
                ['MAIL_HOST', config('mail.mailers.smtp.host')],
                ['MAIL_PORT', config('mail.mailers.smtp.port')],
                ['MAIL_USERNAME', config('mail.mailers.smtp.username')],
                ['MAIL_ENCRYPTION', config('mail.mailers.smtp.encryption')],
                ['MAIL_FROM_ADDRESS', config('mail.from.address')],
                ['MAIL_FROM_NAME', config('mail.from.name')],
                ['QUEUE_CONNECTION', config('queue.default')],
                ['APP_ENV', config('app.env')],
                ['MAIL_USE_ENV_ONLY', env('MAIL_USE_ENV_ONLY', 'false')],
            ]
        );

        // 2. Test SMTP connection
        $this->info("\n2. Testing SMTP Connection:");
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $encryption = config('mail.mailers.smtp.encryption');

        $prefix = $encryption === 'ssl' ? 'ssl://' : '';
        $fp = @fsockopen($prefix.$host, $port, $errno, $errstr, 10);

        if ($fp) {
            $this->info("✓ Connection to {$host}:{$port} successful");
            fclose($fp);
        } else {
            $this->error("✗ Connection to {$host}:{$port} failed");
            $this->error("  Error {$errno}: {$errstr}");
        }

        // 3. Check database tables
        $this->info("\n3. Database Tables Check:");
        try {
            $tables = [
                'password_reset_tokens' => DB::table('password_reset_tokens')->count(),
                'jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ];

            foreach ($tables as $table => $count) {
                $this->info("  {$table}: {$count} records");
            }
        } catch (\Exception $e) {
            $this->error('  Database error: '.$e->getMessage());
        }

        // 4. Check failed jobs
        $this->info("\n4. Recent Failed Jobs:");
        try {
            $failedJobs = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(5)
                ->get();

            if ($failedJobs->isEmpty()) {
                $this->info('  No failed jobs found');
            } else {
                foreach ($failedJobs as $job) {
                    $payload = json_decode($job->payload);
                    $this->warn('  - '.($payload->displayName ?? 'Unknown').' failed at '.$job->failed_at);
                    $this->line('    Error: '.substr($job->exception, 0, 100).'...');
                }
            }
        } catch (\Exception $e) {
            $this->error('  Could not check failed jobs: '.$e->getMessage());
        }

        // 5. Test mail services
        if ($this->option('test-send')) {
            $email = $this->option('email');
            $this->info("\n5. Testing Mail Send to {$email}:");

            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->warn('  User not found, creating mock user...');
                $user = new User;
                $user->id = 1;
                $user->name = 'Test User';
                $user->email = $email;
            }

            try {
                // Test using MailService
                $mailService = app(MailService::class);
                $this->info('  Testing MailService...');

                $result = $mailService->sendNotificationEmail(
                    $user,
                    'Debug Test Email',
                    'This is a test email sent from the debug command.',
                    ['email_type' => 'notification']
                );

                if ($result) {
                    $this->info('  ✓ MailService: Email sent successfully');
                } else {
                    $this->error('  ✗ MailService: Failed to send email');
                }

                // Test password reset
                $this->info('  Testing Password Reset Email...');
                $token = Str::random(60);

                // Insert token
                DB::table('password_reset_tokens')->updateOrInsert(
                    ['email' => $user->email],
                    [
                        'email' => $user->email,
                        'token' => hash('sha256', $token),
                        'created_at' => now(),
                    ]
                );

                $mailManager = app(MailManager::class);
                $result = $mailManager->sendPasswordResetEmail($user, $token);

                if ($result) {
                    $this->info('  ✓ Password Reset: Email sent successfully');
                } else {
                    $this->error('  ✗ Password Reset: Failed to send email');
                }

                // Clean up
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            } catch (\Exception $e) {
                $this->error('  Error sending test email: '.$e->getMessage());
                $this->error('  '.$e->getTraceAsString());
            }
        } else {
            $this->info("\n5. To send a test email, run: php artisan mail:debug --test-send");
        }

        // 6. Check logs
        $this->info("\n6. Recent Mail Errors in Logs:");
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $mailErrors = [];

            foreach ($lines as $line) {
                if (stripos($line, 'mail') !== false &&
                    (stripos($line, 'error') !== false || stripos($line, 'failed') !== false)) {
                    $mailErrors[] = $line;
                }
            }

            if (empty($mailErrors)) {
                $this->info('  No recent mail errors found');
            } else {
                $recent = array_slice($mailErrors, -3);
                foreach ($recent as $error) {
                    $this->warn('  '.substr(trim($error), 0, 150).'...');
                }
            }
        }

        $this->info("\n=== END DEBUG ===\n");

        return Command::SUCCESS;
    }
}
