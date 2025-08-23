<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public int $orderId;

    public string $previousStatus;

    public string $currentStatus;

    public string $orderType;

    /**
     * Create a new event instance.
     */
    public function __construct(int $orderId, string $previousStatus, string $currentStatus, string $orderType)
    {
        $this->orderId = $orderId;
        $this->previousStatus = $previousStatus;
        $this->currentStatus = $currentStatus;
        $this->orderType = $orderType;
    }
}
