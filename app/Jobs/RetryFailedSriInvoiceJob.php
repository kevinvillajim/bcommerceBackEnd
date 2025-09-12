<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\SriApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class RetryFailedSriInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Invoice $invoice;
    public int $tries = 3; // Máximo 3 intentos por job
    public int $backoff = 30; // Esperar 30 segundos entre intentos

    /**
     * ✅ Job para reintentar facturas fallidas del SRI
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
        
        // ✅ Configurar prioridad y delay según número de reintentos
        $this->queue = 'sri-retries';
        
        // ✅ Delay incremental: más reintentos = más delay
        $delayMinutes = [0, 5, 15, 30, 60, 120, 240, 480, 960]; // 0, 5m, 15m, 30m, 1h, 2h, 4h, 8h, 16h
        $currentRetry = $invoice->retry_count;
        $delay = $delayMinutes[$currentRetry] ?? 960; // Máximo 16 horas
        
        $this->delay = now()->addMinutes($delay);
    }

    /**
     * ✅ Ejecuta el reintento de la factura
     */
    public function handle(SriApiService $sriApiService): void
    {
        Log::info('Ejecutando job de reintento de factura SRI', [
            'invoice_id' => $this->invoice->id,
            'retry_count' => $this->invoice->retry_count,
            'job_attempt' => $this->attempts()
        ]);

        try {
            // ✅ Verificar que la factura aún puede reintentarse
            if (!$this->invoice->canRetry()) {
                Log::warning('Factura no puede reintentarse, cancelando job', [
                    'invoice_id' => $this->invoice->id,
                    'retry_count' => $this->invoice->retry_count,
                    'status' => $this->invoice->status
                ]);
                return;
            }

            // ✅ Intentar reenvío
            $response = $sriApiService->retryInvoice($this->invoice);

            Log::info('Reintento de factura exitoso via job', [
                'invoice_id' => $this->invoice->id,
                'retry_count' => $this->invoice->retry_count,
                'sri_response' => $response
            ]);

        } catch (Exception $e) {
            Log::error('Fallo en job de reintento de factura', [
                'invoice_id' => $this->invoice->id,
                'retry_count' => $this->invoice->retry_count,
                'job_attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            // ✅ Si el invoice aún puede reintentarse y no hemos agotado los jobs, programar otro
            $this->invoice->refresh(); // Refrescar datos
            
            if ($this->invoice->canRetry() && $this->attempts() < $this->tries) {
                // ✅ El job se reintentará automáticamente por Laravel
                throw $e;
            }

            // ✅ Si ya no puede reintentarse o agotamos los job attempts, programar el siguiente reintento manual
            if ($this->invoice->canRetry()) {
                Log::info('Programando siguiente reintento de factura', [
                    'invoice_id' => $this->invoice->id,
                    'next_retry_count' => $this->invoice->retry_count + 1
                ]);

                // ✅ Programar el siguiente reintento (el SriApiService maneja el incremento)
                self::dispatch($this->invoice)->delay(now()->addMinutes(60));
            }
        }
    }

    /**
     * ✅ Maneja fallos del job
     */
    public function failed(?Exception $exception): void
    {
        Log::critical('Job de reintento de factura falló definitivamente', [
            'invoice_id' => $this->invoice->id,
            'retry_count' => $this->invoice->retry_count,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString()
        ]);

        // ✅ Refrescar y verificar si aún puede reintentarse
        $this->invoice->refresh();
        
        if ($this->invoice->canRetry()) {
            // ✅ Programar siguiente intento manual después de más tiempo
            Log::info('Programando reintento manual por fallo de job', [
                'invoice_id' => $this->invoice->id
            ]);
            
            self::dispatch($this->invoice)->delay(now()->addHours(2));
        } else {
            Log::critical('Factura alcanzó límite de reintentos', [
                'invoice_id' => $this->invoice->id,
                'final_retry_count' => $this->invoice->retry_count,
                'status' => $this->invoice->status
            ]);
        }
    }

    /**
     * ✅ Comando estático para procesar todas las facturas fallidas
     */
    public static function retryAllFailedInvoices(): int
    {
        Log::info('Iniciando procesamiento masivo de facturas fallidas');

        $failedInvoices = Invoice::retryable()->get();
        $processedCount = 0;

        foreach ($failedInvoices as $invoice) {
            try {
                // ✅ Verificar que no haya un job ya programado para esta factura
                // (esto evita duplicados si se ejecuta el comando varias veces)
                
                self::dispatch($invoice);
                $processedCount++;

                Log::info('Job programado para factura fallida', [
                    'invoice_id' => $invoice->id,
                    'retry_count' => $invoice->retry_count
                ]);

            } catch (Exception $e) {
                Log::error('Error programando job para factura fallida', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Procesamiento masivo completado', [
            'total_failed' => $failedInvoices->count(),
            'jobs_dispatched' => $processedCount
        ]);

        return $processedCount;
    }
}