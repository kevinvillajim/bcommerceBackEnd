<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductUpdated
{
    use Dispatchable, SerializesModels;

    public int $productId;

    public array $changes;

    /**
     * Create a new event instance.
     */
    public function __construct(int $productId, array $changes)
    {
        $this->productId = $productId;
        $this->changes = $changes;
    }
}
