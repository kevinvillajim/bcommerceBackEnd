<?php

namespace App\Listeners;

use App\Events\InvoiceApproved;
use App\UseCases\Accounting\GenerateInvoicePdfUseCase;
use Illuminate\Support\Facades\Log;
use Exception;

class GeneratePdfFromInvoiceListener
{
    private GenerateInvoicePdfUseCase $generatePdfUseCase;

    public function __construct(GenerateInvoicePdfUseCase $generatePdfUseCase)
    {
        $this->generatePdfUseCase = $generatePdfUseCase;
    }

    /**
     * ✅ Maneja el evento InvoiceApproved generando el PDF de la factura
     */
    public function handle(InvoiceApproved $event): void
    {
        Log::info('🎯 LISTENER: GeneratePdfFromInvoiceListener ejecutándose', [
            'invoice_id' => $event->invoice->id,
            'invoice_number' => $event->invoice->invoice_number
        ]);

        try {
            // ✅ Verificar que la factura esté aprobada por el SRI
            if ($event->invoice->status !== $event->invoice::STATUS_APPROVED) {
                Log::warning('Factura no está en estado aprobado, saltando generación de PDF', [
                    'invoice_id' => $event->invoice->id,
                    'current_status' => $event->invoice->status
                ]);
                return;
            }

            // ✅ Verificar que no exista ya un PDF generado
            if (!empty($event->invoice->pdf_path)) {
                Log::info('PDF ya existe para esta factura, saltando generación', [
                    'invoice_id' => $event->invoice->id,
                    'existing_pdf_path' => $event->invoice->pdf_path
                ]);
                return;
            }

            // ✅ Generar el PDF
            $pdfPath = $this->generatePdfUseCase->execute($event->invoice, $event->sriResponse);

            Log::info('PDF de factura generado exitosamente', [
                'invoice_id' => $event->invoice->id,
                'invoice_number' => $event->invoice->invoice_number,
                'pdf_path' => $pdfPath
            ]);

        } catch (Exception $e) {
            Log::error('Error generando PDF de factura automáticamente', [
                'invoice_id' => $event->invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // ✅ No lanzar la excepción para no afectar el flujo
            // El error ya queda registrado en los logs para revisión
        }
    }
}