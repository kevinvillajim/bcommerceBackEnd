<?php

namespace App\Console\Commands;

use App\Events\ShippingDelayed;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Shipping;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CheckShippingDelays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-shipping-delays {--days=2 : Number of days considered as delay}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for shipping updates that have been delayed and notify sellers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysThreshold = (int) $this->option('days');
        if ($daysThreshold < 1) {
            $daysThreshold = 2; // Default value
        }

        $this->info("Checking for shipping delays of {$daysThreshold} days or more...");

        $cutoffDate = Carbon::now()->subDays($daysThreshold);

        // Get all non-delivered shipments that haven't been updated
        $delayedShipments = Shipping::where(function ($query) {
            $query->where('status', '!=', 'delivered')
                ->where('status', '!=', 'returned')
                ->where('status', '!=', 'cancelled');
        })
            ->where(function ($query) use ($cutoffDate) {
                $query->where('last_updated', '<', $cutoffDate)
                    ->orWhere('updated_at', '<', $cutoffDate);
            })
            ->get();

        $count = 0;

        foreach ($delayedShipments as $shipping) {
            try {
                $order = Order::find($shipping->order_id);
                if (! $order || ! $order->seller_id) {
                    continue;
                }

                $seller = Seller::find($order->seller_id);
                if (! $seller) {
                    continue;
                }

                // Calculate days since last update
                $lastUpdated = $shipping->last_updated ?? $shipping->updated_at;
                $daysSinceUpdate = Carbon::now()->diffInDays($lastUpdated);

                // Dispatch event to notify seller
                event(new ShippingDelayed(
                    $shipping->id,
                    $seller->id,
                    $daysSinceUpdate
                ));

                $count++;

                $this->info("Notified seller #{$seller->id} about delayed shipping #{$shipping->id} ({$daysSinceUpdate} days without update)");
            } catch (\Exception $e) {
                Log::error("Error processing delayed shipping #{$shipping->id}: ".$e->getMessage());
                $this->error("Error processing shipping #{$shipping->id}: ".$e->getMessage());
            }
        }

        $this->info("Processed {$count} delayed shipments out of {$delayedShipments->count()} found.");
    }
}
