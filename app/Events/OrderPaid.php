<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid
{
    use Dispatchable, SerializesModels;

    public int $orderId;

    public int $sellerId;

    public float $total;

    /**
     * Create a new event instance.
     */
    public function __construct(int $orderId, int $sellerId, float $total)
    {
        $this->orderId = $orderId;
        $this->sellerId = $sellerId;
        $this->total = $total;
    }
}
