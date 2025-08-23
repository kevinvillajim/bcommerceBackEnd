<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\UseCases\Accounting\GenerateInvoiceUseCase;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceListener
{
    private $generateInvoiceUseCase;

    public function __construct(GenerateInvoiceUseCase $generateInvoiceUseCase)
    {
        $this->generateInvoiceUseCase = $generateInvoiceUseCase;
    }

    public function handle(OrderCompleted $event)
    {
        // En lugar de llamar a un servicio de infraestructura, usamos directamente el caso de uso
        try {
            $this->generateInvoiceUseCase->execute($event->orderId);
            Log::info("Invoice generated for order #{$event->orderId}");
        } catch (\Exception $e) {
            Log::error("Failed to generate invoice for order #{$event->orderId}: ".$e->getMessage());
        }
    }
}
