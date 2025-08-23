<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeedbackReviewed
{
    use Dispatchable, SerializesModels;

    public int $feedbackId;

    public int $adminId;

    public string $status;

    /**
     * Create a new event instance.
     */
    public function __construct(int $feedbackId, int $adminId, string $status)
    {
        $this->feedbackId = $feedbackId;
        $this->adminId = $adminId;
        $this->status = $status;
    }
}
