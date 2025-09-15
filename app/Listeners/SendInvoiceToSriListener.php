<?php

namespace App\Listeners;

use App\Events\InvoiceGenerated;
use App\Jobs\RetryFailedSriInvoiceJob;
use App\Services\SriApiService;
use Exception;
use Illuminate\Support\Facades\Log;

class SendInvoiceToSriListener
{
    private SriApiService $sriApiService;

    public function __construct(SriApiService $sriApiService)
    {
        $this->sriApiService = $sriApiService;
    }

    /**
     * ✅ Maneja el evento InvoiceGenerated enviando la factura al SRI
     */
    public function handle(InvoiceGenerated $event): void
    {
        $invoice = $event->invoice;

        Log::info('Procesando envío automático de factura al SRI', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
        ]);

        try {
            // ✅ Solo enviar facturas en estado DRAFT
            if ($invoice->status !== $invoice::STATUS_DRAFT) {
                Log::warning('Factura no está en estado DRAFT, saltando envío al SRI', [
                    'invoice_id' => $invoice->id,
                    'current_status' => $invoice->status,
                ]);

                return;
            }

            // ✅ Actualizar estado a "enviando al SRI"
            $invoice->update(['status' => $invoice::STATUS_SENT_TO_SRI]);

            // ✅ Enviar al SRI
            $response = $this->sriApiService->sendInvoice($invoice);

            Log::info('Factura enviada exitosamente al SRI', [
                'invoice_id' => $invoice->id,
                'sri_response' => $response,
            ]);

        } catch (Exception $e) {
            Log::error('Error enviando factura al SRI automáticamente', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // ✅ El SriApiService ya maneja el marcado como fallida

            // ✅ Refrescar la factura para obtener el estado actualizado
            $invoice->refresh();

            // ✅ Programar reintento asincrónico si es posible
            if ($invoice->canRetry()) {
                Log::info('Programando reintento asincrónico para factura fallida', [
                    'invoice_id' => $invoice->id,
                    'retry_count' => $invoice->retry_count,
                ]);

                RetryFailedSriInvoiceJob::dispatch($invoice);
            } else {
                Log::warning('Factura no puede reintentarse, se mantendrá como fallida', [
                    'invoice_id' => $invoice->id,
                    'retry_count' => $invoice->retry_count ?? 0,
                    'status' => $invoice->status,
                ]);
            }

            // ✅ No lanzar excepción para no afectar el flujo
        }
    }
}
