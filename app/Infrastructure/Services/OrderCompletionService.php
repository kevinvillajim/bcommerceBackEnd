<?php

namespace App\Infrastructure\Services;

use App\UseCases\Accounting\GenerateInvoiceUseCase;
use Illuminate\Support\Facades\Log;

class OrderCompletionService
{
    private $generateInvoiceUseCase;

    public function __construct(GenerateInvoiceUseCase $generateInvoiceUseCase)
    {
        $this->generateInvoiceUseCase = $generateInvoiceUseCase;
    }

    /**
     * Genera automáticamente una factura cuando se completa una orden
     */
    public function handleOrderCompletion(int $orderId): void
    {
        try {
            $invoice = $this->generateInvoiceUseCase->execute($orderId);
            Log::info("Invoice #{$invoice->invoiceNumber} generated automatically for order #{$orderId}");
        } catch (\Exception $e) {
            Log::error("Failed to generate invoice for order #{$orderId}: ".$e->getMessage());
        }
    }
}
