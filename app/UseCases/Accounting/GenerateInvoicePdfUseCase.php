<?php

namespace App\UseCases\Accounting;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateInvoicePdfUseCase
{
    /**
     * Genera un PDF de una factura aprobada por el SRI
     */
    public function execute(Invoice $invoice, array $sriResponse = []): string
    {
        Log::info('Iniciando generación de PDF para factura', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);

        try {
            // ✅ Validación: la factura debe estar aprobada por el SRI
            if ($invoice->status !== Invoice::STATUS_AUTHORIZED) {
                throw new Exception("No se puede generar PDF: la factura {$invoice->invoice_number} no está aprobada por el SRI");
            }

            // ✅ Cargar relaciones necesarias para el PDF
            $invoice->load(['order.items.product', 'order.user']);

            // ✅ Preparar datos para la plantilla del PDF
            $pdfData = [
                'invoice' => $invoice,
                'order' => $invoice->order,
                'customer' => $invoice->order->user,
                'items' => $invoice->order->items,
                'sriResponse' => $sriResponse,
                'generatedAt' => now(),
            ];

            // ✅ Generar PDF usando una vista blade
            $pdf = Pdf::loadView('invoices.pdf-template', $pdfData);

            // ✅ Configurar orientación y tamaño
            $pdf->setPaper('A4', 'portrait');

            // ✅ Generar nombre único para el archivo
            $fileName = "invoice_{$invoice->invoice_number}_{$invoice->id}.pdf";
            $filePath = "invoices/{$fileName}";

            // ✅ Guardar el PDF en storage
            $pdfContent = $pdf->output();
            Storage::disk('public')->put($filePath, $pdfContent);

            // ✅ Actualizar la factura con la ruta del PDF
            Log::info('🔍 DEBUG: Antes de actualizar pdf_path en BD', [
                'invoice_id' => $invoice->id,
                'pdf_path_to_save' => $filePath,
                'current_pdf_path' => $invoice->pdf_path,
            ]);

            $updateResult = $invoice->update([
                'pdf_path' => $filePath,
                'pdf_generated_at' => now(),
            ]);

            Log::info('🔍 DEBUG: Después de actualizar pdf_path en BD', [
                'invoice_id' => $invoice->id,
                'update_result' => $updateResult,
                'pdf_path_after_update' => $invoice->pdf_path,
                'pdf_generated_at' => $invoice->pdf_generated_at,
            ]);

            Log::info('PDF de factura generado exitosamente', [
                'invoice_id' => $invoice->id,
                'pdf_path' => $filePath,
            ]);

            return $filePath;

        } catch (Exception $e) {
            Log::error('Error generando PDF de factura', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
