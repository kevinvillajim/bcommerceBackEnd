<?php

namespace App\Console\Commands;

use App\Models\Seller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateExpiredFeaturedSellers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sellers:update-expired-featured 
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update expired featured seller status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info('🔍 Checking for expired featured sellers...');

        // Find sellers with expired featured status
        $expiredSellers = Seller::where('is_featured', true)
            ->whereNotNull('featured_expires_at')
            ->where('featured_expires_at', '<=', now())
            ->get();

        if ($expiredSellers->isEmpty()) {
            $this->info('✅ No expired featured sellers found.');

            return self::SUCCESS;
        }

        $this->info("Found {$expiredSellers->count()} expired featured seller(s):");

        foreach ($expiredSellers as $seller) {
            $this->line("  - {$seller->store_name} (ID: {$seller->id}) - Expired: {$seller->featured_expires_at}");
        }

        if ($isDryRun) {
            $this->warn('🧪 DRY RUN - No changes made');

            return self::SUCCESS;
        }

        // Update expired sellers
        $updated = 0;
        foreach ($expiredSellers as $seller) {
            try {
                $seller->update(['is_featured' => false]);
                $updated++;

                Log::info('Featured status expired for seller', [
                    'seller_id' => $seller->id,
                    'store_name' => $seller->store_name,
                    'expired_at' => $seller->featured_expires_at,
                    'featured_reason' => $seller->featured_reason,
                ]);

            } catch (\Exception $e) {
                $this->error("Failed to update seller {$seller->id}: {$e->getMessage()}");
                Log::error('Failed to update expired featured seller', [
                    'seller_id' => $seller->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("✅ Updated {$updated} seller(s)");

        return self::SUCCESS;
    }
}
