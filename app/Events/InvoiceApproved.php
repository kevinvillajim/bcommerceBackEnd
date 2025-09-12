<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceApproved
{
    use Dispatchable, SerializesModels;

    public Invoice $invoice;
    public array $sriResponse;

    /**
     * ✅ Evento que se dispara cuando el SRI aprueba una factura
     */
    public function __construct(Invoice $invoice, array $sriResponse = [])
    {
        $this->invoice = $invoice;
        $this->sriResponse = $sriResponse;
    }
}