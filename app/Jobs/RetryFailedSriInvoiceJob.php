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
    public int $tries = 3; // ✅ 3 intentos por ronda (inmediatos con 5 segundos entre ellos)
    public int $backoff = 5; // ✅ 5 segundos entre intentos dentro de la misma ronda

    /**
     * ✅ Job para reintentar facturas fallidas del SRI
     * Sistema de 4 RONDAS de 3 intentos cada una:
     * - Ronda 1: 3 intentos inmediatos (5 segundos)
     * - Ronda 2: Job diferido 5min (3 intentos)  
     * - Ronda 3: Job diferido 15min (3 intentos)
     * - Ronda 4: Job diferido 30min (3 intentos)
     * - Total máximo: 12 reintentos
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
        $this->queue = 'sri-retries';
    }

    /**
     * ✅ Ejecuta una ronda de reintentos (3 intentos máximo por ronda)
     */
    public function handle(SriApiService $sriApiService): void
    {
        $currentRetryCount = $this->invoice->retry_count;
        $currentRound = $this->getCurrentRound($currentRetryCount);
        $attemptInRound = $this->getAttemptInCurrentRound($currentRetryCount);

        Log::info('🔄 EJECUTANDO REINTENTO SRI', [
            'invoice_id' => $this->invoice->id,
            'total_retry_count' => $currentRetryCount,
            'current_round' => $currentRound,
            'attempt_in_round' => $attemptInRound,
            'job_attempt' => $this->attempts(),
            'max_job_attempts' => $this->tries
        ]);

        try {
            // ✅ Verificar que la factura aún puede reintentarse
            if (!$this->invoice->canRetry()) {
                Log::warning('❌ FACTURA NO PUEDE REINTENTARSE - CANCELANDO JOB', [
                    'invoice_id' => $this->invoice->id,
                    'retry_count' => $currentRetryCount,
                    'status' => $this->invoice->status
                ]);
                return;
            }

            // ✅ Intentar reenvío (esto incrementará el retry_count automáticamente)
            $response = $sriApiService->retryInvoice($this->invoice);

            Log::info('✅ REINTENTO EXITOSO', [
                'invoice_id' => $this->invoice->id,
                'round' => $currentRound,
                'attempt_in_round' => $attemptInRound,
                'new_retry_count' => $this->invoice->retry_count,
                'sri_response' => $response
            ]);

        } catch (Exception $e) {
            Log::warning('⚠️ INTENTO FALLIDO', [
                'invoice_id' => $this->invoice->id,
                'round' => $currentRound,
                'attempt_in_round' => $attemptInRound,
                'job_attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            $this->invoice->refresh(); // Refrescar datos

            // ✅ Si aún tenemos intentos en esta ronda (job attempts < 3)
            if ($this->attempts() < $this->tries && $this->invoice->canRetry()) {
                Log::info('🔄 REINTENTANDO EN MISMA RONDA', [
                    'invoice_id' => $this->invoice->id,
                    'job_attempt' => $this->attempts() + 1,
                    'round' => $currentRound,
                    'next_backoff_seconds' => $this->backoff
                ]);
                
                // ✅ Laravel reintentará automáticamente con backoff de 5 segundos
                throw $e;
            }

            // ✅ Se agotaron los 3 intentos de esta ronda, programar siguiente ronda
            $this->scheduleNextRound($currentRound);
        }
    }

    /**
     * ✅ Programa la siguiente ronda de reintentos
     */
    private function scheduleNextRound(int $currentRound): void
    {
        $this->invoice->refresh();
        
        // ✅ Verificar si aún puede reintentarse después de los 3 intentos fallidos
        if (!$this->invoice->canRetry()) {
            Log::critical('❌ LÍMITE DE REINTENTOS ALCANZADO - ESTADO DEFINITIVO', [
                'invoice_id' => $this->invoice->id,
                'final_retry_count' => $this->invoice->retry_count,
                'final_status' => $this->invoice->status,
                'completed_rounds' => $currentRound
            ]);
            return;
        }

        // ✅ Calcular delay para la siguiente ronda
        $nextRound = $currentRound + 1;
        $delayMinutes = $this->getDelayForRound($nextRound);

        if ($delayMinutes !== null) {
            Log::info('📅 PROGRAMANDO SIGUIENTE RONDA', [
                'invoice_id' => $this->invoice->id,
                'current_round' => $currentRound,
                'next_round' => $nextRound,
                'delay_minutes' => $delayMinutes,
                'scheduled_at' => now()->addMinutes($delayMinutes)->toDateTimeString()
            ]);

            // ✅ Programar nueva instancia del job para la siguiente ronda
            self::dispatch($this->invoice)->delay(now()->addMinutes($delayMinutes));
        } else {
            Log::critical('❌ TODAS LAS RONDAS COMPLETADAS - MARCANDO COMO DEFINITIVAMENTE FALLIDA', [
                'invoice_id' => $this->invoice->id,
                'completed_rounds' => $currentRound,
                'final_retry_count' => $this->invoice->retry_count
            ]);

            // ✅ Marcar como definitivamente fallida
            $this->invoice->update(['status' => Invoice::STATUS_DEFINITIVELY_FAILED]);
        }
    }

    /**
     * ✅ Determina la ronda actual basada en el retry_count
     * Ronda 1: retry_count 1-3
     * Ronda 2: retry_count 4-6  
     * Ronda 3: retry_count 7-9
     * Ronda 4: retry_count 10-12
     */
    private function getCurrentRound(int $retryCount): int
    {
        if ($retryCount <= 3) return 1;
        if ($retryCount <= 6) return 2;
        if ($retryCount <= 9) return 3;
        if ($retryCount <= 12) return 4;
        return 5; // Excedido (no debería pasar)
    }

    /**
     * ✅ Determina qué intento dentro de la ronda actual
     */
    private function getAttemptInCurrentRound(int $retryCount): int
    {
        $remainder = $retryCount % 3;
        return $remainder === 0 ? 3 : $remainder;
    }

    /**
     * ✅ Obtiene el delay en minutos para cada ronda
     */
    private function getDelayForRound(int $round): ?int
    {
        $delays = [
            1 => 0,    // Ronda 1: Inmediata
            2 => 5,    // Ronda 2: 5 minutos
            3 => 15,   // Ronda 3: 15 minutos  
            4 => 30,   // Ronda 4: 30 minutos
        ];

        return $delays[$round] ?? null; // null = no más rondas
    }

    /**
     * ✅ Maneja fallos del job completo
     */
    public function failed(?Exception $exception): void
    {
        Log::critical('💀 JOB DE REINTENTO FALLÓ COMPLETAMENTE', [
            'invoice_id' => $this->invoice->id,
            'retry_count' => $this->invoice->retry_count,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString()
        ]);

        // ✅ En caso de fallo completo del job, intentar programar manualmente la siguiente ronda
        $this->invoice->refresh();
        
        if ($this->invoice->canRetry()) {
            Log::warning('🚨 RECUPERACIÓN: Programando siguiente ronda por fallo de job', [
                'invoice_id' => $this->invoice->id
            ]);
            
            // ✅ Programar con delay de recuperación (2 horas)
            self::dispatch($this->invoice)->delay(now()->addHours(2));
        }
    }

    /**
     * ✅ Comando estático para procesar todas las facturas fallidas
     */
    public static function retryAllFailedInvoices(): int
    {
        Log::info('🚀 INICIANDO PROCESAMIENTO MASIVO DE FACTURAS FALLIDAS');

        $failedInvoices = Invoice::retryable()->get();
        $processedCount = 0;

        foreach ($failedInvoices as $invoice) {
            try {
                self::dispatch($invoice);
                $processedCount++;

                Log::info('📤 JOB PROGRAMADO PARA FACTURA', [
                    'invoice_id' => $invoice->id,
                    'retry_count' => $invoice->retry_count,
                    'current_round' => (new self($invoice))->getCurrentRound($invoice->retry_count)
                ]);

            } catch (Exception $e) {
                Log::error('❌ ERROR PROGRAMANDO JOB', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('✅ PROCESAMIENTO MASIVO COMPLETADO', [
            'total_failed' => $failedInvoices->count(),
            'jobs_dispatched' => $processedCount
        ]);

        return $processedCount;
    }
}