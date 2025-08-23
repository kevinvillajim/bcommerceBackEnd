<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShippingStatusUpdated
{
    use Dispatchable, SerializesModels;

    public int $shippingId;

    public string $previousStatus;

    public string $currentStatus;

    /**
     * Create a new event instance.
     */
    public function __construct(int $shippingId, string $previousStatus, string $currentStatus)
    {
        $this->shippingId = $shippingId;
        $this->previousStatus = $previousStatus;
        $this->currentStatus = $currentStatus;
    }
}
