<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceGenerated
{
    use Dispatchable, SerializesModels;

    public Invoice $invoice;

    /**
     * ✅ Evento que se dispara cuando se genera una nueva factura
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }
}
