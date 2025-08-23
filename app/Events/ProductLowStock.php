<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductLowStock
{
    use Dispatchable, SerializesModels;

    public int $productId;

    public int $stock;

    /**
     * Create a new event instance.
     */
    public function __construct(int $productId, int $stock)
    {
        $this->productId = $productId;
        $this->stock = $stock;
    }
}
