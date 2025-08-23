<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductStockUpdated
{
    use Dispatchable, SerializesModels;

    public int $productId;

    public int $oldStock;

    public int $newStock;

    /**
     * Create a new event instance.
     */
    public function __construct(int $productId, int $oldStock, int $newStock)
    {
        $this->productId = $productId;
        $this->oldStock = $oldStock;
        $this->newStock = $newStock;
    }
}
