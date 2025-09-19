<?php

namespace App\UseCases\Accounting;

use App\Models\CreditNote;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateCreditNotePdfUseCase
{
    /**
     * Genera un PDF de una nota de crédito aprobada por el SRI
     */
    public function execute(CreditNote $creditNote, array $sriResponse = []): string
    {
        Log::info('Iniciando generación de PDF para nota de crédito', [
            'credit_note_id' => $creditNote->id,
            'credit_note_number' => $creditNote->credit_note_number,
        ]);

        try {
            // ✅ Validación: la nota de crédito debe estar aprobada por el SRI
            if ($creditNote->status !== CreditNote::STATUS_AUTHORIZED) {
                throw new Exception("No se puede generar PDF: la nota de crédito {$creditNote->credit_note_number} no está aprobada por el SRI");
            }

            // ✅ Cargar relaciones necesarias para el PDF
            $creditNote->load(['invoice', 'items', 'user']);

            // ✅ Preparar datos para la plantilla del PDF
            $pdfData = [
                'creditNote' => $creditNote,
                'invoice' => $creditNote->invoice,
                'user' => $creditNote->user,
                'items' => $creditNote->items,
                'sriResponse' => $sriResponse,
                'generatedAt' => now(),
            ];

            // ✅ Generar PDF usando una vista blade específica para notas de crédito
            $pdf = Pdf::loadView('credit-notes.pdf-template', $pdfData);

            // ✅ Configurar orientación y tamaño
            $pdf->setPaper('A4', 'portrait');

            // ✅ Generar nombre único para el archivo
            $fileName = "nota_credito_{$creditNote->credit_note_number}_{$creditNote->id}.pdf";
            $filePath = "credit_notes/{$fileName}";

            // ✅ Guardar el PDF en storage
            $pdfContent = $pdf->output();
            Storage::disk('public')->put($filePath, $pdfContent);

            // ✅ Actualizar la nota de crédito con la ruta del PDF
            Log::info('🔍 DEBUG: Antes de actualizar pdf_path en BD', [
                'credit_note_id' => $creditNote->id,
                'pdf_path_to_save' => $filePath,
                'current_pdf_path' => $creditNote->pdf_path,
            ]);

            $updateResult = $creditNote->update([
                'pdf_path' => $filePath,
            ]);

            Log::info('🔍 DEBUG: Después de actualizar pdf_path en BD', [
                'credit_note_id' => $creditNote->id,
                'update_result' => $updateResult,
                'pdf_path_after_update' => $creditNote->pdf_path,
            ]);

            Log::info('PDF de nota de crédito generado exitosamente', [
                'credit_note_id' => $creditNote->id,
                'pdf_path' => $filePath,
            ]);

            return $filePath;

        } catch (Exception $e) {
            Log::error('Error generando PDF de nota de crédito', [
                'credit_note_id' => $creditNote->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}