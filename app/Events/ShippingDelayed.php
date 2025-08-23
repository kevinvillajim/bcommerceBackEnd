<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShippingDelayed
{
    use Dispatchable, SerializesModels;

    public int $shippingId;

    public int $sellerId;

    public int $daysWithoutUpdate;

    /**
     * Create a new event instance.
     */
    public function __construct(int $shippingId, int $sellerId, int $daysWithoutUpdate)
    {
        $this->shippingId = $shippingId;
        $this->sellerId = $sellerId;
        $this->daysWithoutUpdate = $daysWithoutUpdate;
    }
}
