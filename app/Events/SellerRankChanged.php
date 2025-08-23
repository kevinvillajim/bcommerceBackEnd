<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SellerRankChanged
{
    use Dispatchable, SerializesModels;

    public int $sellerId;

    public string $oldRank;

    public string $newRank;

    /**
     * Create a new event instance.
     */
    public function __construct(int $sellerId, string $oldRank, string $newRank)
    {
        $this->sellerId = $sellerId;
        $this->oldRank = $oldRank;
        $this->newRank = $newRank;
    }
}
