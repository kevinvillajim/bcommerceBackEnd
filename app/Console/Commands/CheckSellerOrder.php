<?php

namespace App\Console\Commands;

use App\Models\SellerOrder;
use Illuminate\Console\Command;

class CheckSellerOrder extends Command
{
    protected $signature = 'check:seller-order';

    protected $description = 'Check the latest seller order fields';

    public function handle()
    {
        $sellerOrder = SellerOrder::latest()->first();

        if ($sellerOrder) {
            $this->info('✅ ÚLTIMO SELLER ORDER:');
            $this->line("ID: {$sellerOrder->id}");
            $this->line("Order ID: {$sellerOrder->order_id}");
            $this->line("Seller ID: {$sellerOrder->seller_id}");
            $this->line('Original Total: '.($sellerOrder->original_total ?? 'NULL'));
            $this->line("Volume Discount Savings: {$sellerOrder->volume_discount_savings}");
            $this->line('Volume Discounts Applied: '.($sellerOrder->volume_discounts_applied ? 'true' : 'false'));
            $this->line("Shipping Cost: {$sellerOrder->shipping_cost}");
            $this->line("Payment Method: {$sellerOrder->payment_method}");
            $this->line("Created At: {$sellerOrder->created_at}");
        } else {
            $this->error('❌ No se encontró ningún SellerOrder');
        }
    }
}
