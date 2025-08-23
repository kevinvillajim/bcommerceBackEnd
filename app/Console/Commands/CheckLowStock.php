<?php

namespace App\Console\Commands;

use App\Events\ProductStockUpdated;
use App\Models\Product;
use App\Models\Seller;
use App\Services\ConfigurationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLowStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-low-stock {--threshold= : Stock threshold for low stock alert (overrides database config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for products with low stock and notify sellers';

    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        parent::__construct();
        $this->configService = $configService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get threshold from option or database configuration
        $threshold = $this->option('threshold');
        if ($threshold !== null) {
            $threshold = (int) $threshold;
        } else {
            $threshold = $this->configService->getConfig('moderation.lowStockThreshold', 10);
        }

        if ($threshold < 1) {
            $threshold = 10; // Fallback value
        }

        $this->info("Checking for products with stock below {$threshold} units...");

        // Get all products with stock below threshold
        $lowStockProducts = Product::where('stock', '<=', $threshold)
            ->where('stock', '>', 0) // Only notify if there's still some stock
            ->where('published', true)
            ->where('status', 'active')
            ->get();

        $count = 0;

        foreach ($lowStockProducts as $product) {
            try {
                // Check if the product belongs to a seller
                $seller = Seller::where('user_id', $product->user_id)->first();
                if (! $seller) {
                    continue;
                }

                // Dispatch event to notify seller
                event(new ProductStockUpdated(
                    $product->id,
                    $product->stock,
                    $product->stock
                ));

                $count++;

                $this->info("Notified seller #{$seller->id} about low stock product #{$product->id} ({$product->stock} units remaining)");
            } catch (\Exception $e) {
                Log::error("Error processing low stock product #{$product->id}: ".$e->getMessage());
                $this->error("Error processing product #{$product->id}: ".$e->getMessage());
            }
        }

        // Now notify about out-of-stock products
        $outOfStockProducts = Product::where('stock', 0)
            ->where('published', true)
            ->where('status', 'active')
            ->get();

        $outOfStockCount = 0;

        foreach ($outOfStockProducts as $product) {
            try {
                // Check if the product belongs to a seller
                $seller = Seller::where('user_id', $product->user_id)->first();
                if (! $seller) {
                    continue;
                }

                // Dispatch event to notify seller
                event(new ProductStockUpdated(
                    $product->id,
                    0,
                    0
                ));

                $outOfStockCount++;

                $this->info("Notified seller #{$seller->id} about out-of-stock product #{$product->id}");
            } catch (\Exception $e) {
                Log::error("Error processing out-of-stock product #{$product->id}: ".$e->getMessage());
                $this->error("Error processing product #{$product->id}: ".$e->getMessage());
            }
        }

        $this->info("Processed {$count} low stock products out of {$lowStockProducts->count()} found.");
        $this->info("Processed {$outOfStockCount} out-of-stock products out of {$outOfStockProducts->count()} found.");
    }
}
