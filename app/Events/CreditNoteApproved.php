<?php

namespace App\Events;

use App\Models\CreditNote;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditNoteApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CreditNote $creditNote;
    public array $sriResponse;

    /**
     * Create a new event instance.
     */
    public function __construct(CreditNote $creditNote, array $sriResponse = [])
    {
        $this->creditNote = $creditNote;
        $this->sriResponse = $sriResponse;
    }
}