<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    public int $orderId;

    public int $userId;

    public ?int $sellerId;

    public array $orderData;

    /**
     * Create a new event instance.
     */
    public function __construct(int $orderId, int $userId, ?int $sellerId, array $orderData = [])
    {
        $this->orderId = $orderId;
        $this->userId = $userId;
        $this->sellerId = $sellerId;
        $this->orderData = $orderData;
    }
}
