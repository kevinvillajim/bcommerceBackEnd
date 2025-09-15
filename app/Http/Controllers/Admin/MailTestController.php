<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mail\MailManager;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MailTestController extends Controller
{
    private MailService $mailService;

    private MailManager $mailManager;

    public function __construct(MailService $mailService, MailManager $mailManager)
    {
        $this->mailService = $mailService;
        $this->mailManager = $mailManager;
    }

    /**
     * Test mail configuration and send test email
     */
    public function testMail(Request $request): JsonResponse
    {
        try {
            Log::info('Mail test initiated by admin', [
                'admin_id' => auth()->id(),
                'test_type' => $request->input('type', 'general'),
            ]);

            // Get current mail configuration
            $mailConfig = [
                'driver' => Config::get('mail.default'),
                'host' => Config::get('mail.mailers.smtp.host'),
                'port' => Config::get('mail.mailers.smtp.port'),
                'encryption' => Config::get('mail.mailers.smtp.encryption'),
                'from_address' => Config::get('mail.from.address'),
                'from_name' => Config::get('mail.from.name'),
            ];

            // Test connection
            $connectionTest = $this->mailService->testConnection();

            // Try to send a test email
            $testType = $request->input('type', 'general');
            $userId = $request->input('user_id');

            $testResults = [];

            if ($testType === 'password_reset' || $testType === 'all') {
                // Test password reset email
                $user = $userId ? User::find($userId) : auth()->user();
                if ($user) {
                    $token = Str::random(60);

                    // Save token to database
                    DB::table('password_reset_tokens')->updateOrInsert(
                        ['email' => $user->email],
                        [
                            'email' => $user->email,
                            'token' => hash('sha256', $token),
                            'created_at' => now(),
                        ]
                    );

                    $result = $this->mailManager->sendPasswordResetEmail($user, $token);
                    $testResults['password_reset'] = $result;

                    // Clean up test token
                    DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                }
            }

            if ($testType === 'notification' || $testType === 'all') {
                // Test notification email
                $user = $userId ? User::find($userId) : auth()->user();
                if ($user) {
                    $result = $this->mailService->sendNotificationEmail(
                        $user,
                        'Test Email - Admin Mail System',
                        'This is a test email sent from the admin panel to verify mail configuration.',
                        ['email_type' => 'notification', 'sent_by_admin' => true]
                    );
                    $testResults['notification'] = $result;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Mail test completed',
                'data' => [
                    'configuration' => $mailConfig,
                    'connection_test' => $connectionTest,
                    'email_tests' => $testResults,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Mail test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Mail test failed',
                'error' => $e->getMessage(),
                'debug' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ] : null,
            ], 500);
        }
    }

    /**
     * Get mail system status
     */
    public function getStatus(): JsonResponse
    {
        try {
            // Check queue status
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            // Get recent failed mail jobs
            $recentFailures = [];
            if ($failedJobs > 0) {
                $failures = DB::table('failed_jobs')
                    ->where('payload', 'like', '%Mail%')
                    ->orderBy('failed_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'payload', 'exception', 'failed_at']);

                foreach ($failures as $failure) {
                    $payload = json_decode($failure->payload);
                    $recentFailures[] = [
                        'id' => $failure->id,
                        'job' => $payload->displayName ?? 'Unknown',
                        'failed_at' => $failure->failed_at,
                        'error' => substr($failure->exception, 0, 200),
                    ];
                }
            }

            // Get mail configuration
            $mailConfig = $this->mailService->getMailConfiguration();

            return response()->json([
                'success' => true,
                'data' => [
                    'queue' => [
                        'pending_jobs' => $pendingJobs,
                        'failed_jobs' => $failedJobs,
                        'recent_failures' => $recentFailures,
                    ],
                    'configuration' => $mailConfig,
                    'status' => 'operational',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get mail status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mail status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
