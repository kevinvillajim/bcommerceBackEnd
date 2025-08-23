<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeedbackCreated
{
    use Dispatchable, SerializesModels;

    public int $feedbackId;

    /**
     * Create a new event instance.
     */
    public function __construct(int $feedbackId)
    {
        $this->feedbackId = $feedbackId;
    }
}
