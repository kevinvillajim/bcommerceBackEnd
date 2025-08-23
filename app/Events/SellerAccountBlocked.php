<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SellerAccountBlocked
{
    use Dispatchable, SerializesModels;

    public int $userId;

    public string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, string $reason)
    {
        $this->userId = $userId;
        $this->reason = $reason;
    }
}
