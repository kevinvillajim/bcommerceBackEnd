<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductPriceChanged
{
    use Dispatchable, SerializesModels;

    public int $productId;

    public float $oldPrice;

    public float $newPrice;

    /**
     * Create a new event instance.
     */
    public function __construct(int $productId, float $oldPrice, float $newPrice)
    {
        $this->productId = $productId;
        $this->oldPrice = $oldPrice;
        $this->newPrice = $newPrice;
    }
}
