<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SellerStrikeAdded
{
    use Dispatchable, SerializesModels;

    public int $strikeId;

    public int $userId;

    /**
     * Create a new event instance.
     */
    public function __construct(int $strikeId, int $userId)
    {
        $this->strikeId = $strikeId;
        $this->userId = $userId;
    }
}
