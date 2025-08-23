<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent
{
    use Dispatchable, SerializesModels;

    public int $messageId;

    public int $chatId;

    public int $senderId;

    /**
     * Create a new event instance.
     */
    public function __construct(int $messageId, int $chatId, int $senderId)
    {
        $this->messageId = $messageId;
        $this->chatId = $chatId;
        $this->senderId = $senderId;
    }
}
