<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RatingCreated
{
    use Dispatchable, SerializesModels;

    public int $ratingId;

    public string $type;

    /**
     * Create a new event instance.
     */
    public function __construct(int $ratingId, string $type)
    {
        $this->ratingId = $ratingId;
        $this->type = $type;
    }
}
