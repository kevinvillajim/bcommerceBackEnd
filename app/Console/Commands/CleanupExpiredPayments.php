<?php

namespace App\Console\Commands;

use App\Domain\Repositories\DeunaPaymentRepositoryInterface;
use App\Infrastructure\Services\DeunaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredPayments extends Command
{
    protected $signature = 'payments:cleanup-expired {--dry-run : Show what would be cancelled without actually cancelling}';

    protected $description = 'Cancel expired pending payments that have been active for more than 10 minutes';

    public function handle(): int
    {
        $this->info('🧹 Starting cleanup of expired payments...');

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No payments will actually be cancelled');
        }

        try {
            // Register DeunaServiceProvider if not already registered
            if (! app()->providerIsLoaded(\App\Providers\DeunaServiceProvider::class)) {
                app()->register(\App\Providers\DeunaServiceProvider::class);
            }

            // Resolve dependencies from container
            $paymentRepository = app(DeunaPaymentRepositoryInterface::class);
            $deunaService = app(DeunaService::class);

            // Find payments that are pending and older than 10 minutes (600 seconds)
            $expiredThreshold = Carbon::now()->subMinutes(10);

            $expiredPayments = $paymentRepository->findExpiredPendingPayments($expiredThreshold);

            if ($expiredPayments->isEmpty()) {
                $this->info('✅ No expired payments found');

                return self::SUCCESS;
            }

            $this->info("📋 Found {$expiredPayments->count()} expired payment(s)");

            $cancelledCount = 0;
            $errorCount = 0;

            foreach ($expiredPayments as $payment) {
                $paymentId = $payment->getPaymentId();
                $orderId = $payment->getOrderId();
                $createdAt = $payment->getCreatedAt();

                $this->info("Processing payment: {$paymentId} (Order: {$orderId})");

                if ($isDryRun) {
                    $this->line("  └─ Would cancel: {$paymentId} (Age: {$createdAt->diffForHumans()})");
                    $cancelledCount++;

                    continue;
                }

                try {
                    // Cancel payment via DeUna API
                    $deunaService->cancelPayment(
                        $paymentId,
                        'Automatic cancellation - payment expired after 10 minutes'
                    );

                    // Update payment status in database
                    $paymentRepository->updateStatus($paymentId, 'cancelled');

                    $this->info("  ✅ Cancelled: {$paymentId}");
                    $cancelledCount++;

                } catch (\Exception $e) {
                    $this->error("  ❌ Failed to cancel {$paymentId}: {$e->getMessage()}");

                    Log::error('Failed to cancel expired payment', [
                        'payment_id' => $paymentId,
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);

                    $errorCount++;
                }
            }

            // Summary
            $this->newLine();
            if ($isDryRun) {
                $this->info('🔍 DRY RUN SUMMARY:');
                $this->info("  └─ Would cancel: {$cancelledCount} payment(s)");
            } else {
                $this->info('📊 CLEANUP SUMMARY:');
                $this->info("  ├─ Successfully cancelled: {$cancelledCount} payment(s)");
                if ($errorCount > 0) {
                    $this->error("  └─ Errors: {$errorCount} payment(s)");
                }
            }

            Log::info('Expired payments cleanup completed', [
                'dry_run' => $isDryRun,
                'cancelled_count' => $cancelledCount,
                'error_count' => $errorCount,
                'total_found' => $expiredPayments->count(),
            ]);

            return $errorCount > 0 ? self::FAILURE : self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");

            Log::error('Expired payments cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
