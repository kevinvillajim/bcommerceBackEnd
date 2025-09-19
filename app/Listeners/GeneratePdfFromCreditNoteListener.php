<?php

namespace App\Listeners;

use App\Events\CreditNoteApproved;
use App\UseCases\Accounting\GenerateCreditNotePdfUseCase;
use Exception;
use Illuminate\Support\Facades\Log;

class GeneratePdfFromCreditNoteListener
{
    private GenerateCreditNotePdfUseCase $generateCreditNotePdfUseCase;

    public function __construct(GenerateCreditNotePdfUseCase $generateCreditNotePdfUseCase)
    {
        $this->generateCreditNotePdfUseCase = $generateCreditNotePdfUseCase;
    }

    /**
     * Maneja el evento CreditNoteApproved generando PDF automáticamente
     */
    public function handle(CreditNoteApproved $event): void
    {
        Log::info('🎯 LISTENER: GeneratePdfFromCreditNoteListener ejecutándose', [
            'credit_note_id' => $event->creditNote->id,
            'credit_note_number' => $event->creditNote->credit_note_number,
        ]);

        try {
            // Verificar que la nota de crédito esté aprobada por el SRI
            if ($event->creditNote->status !== $event->creditNote::STATUS_AUTHORIZED) {
                Log::warning('Nota de crédito no está en estado aprobado, saltando generación de PDF', [
                    'credit_note_id' => $event->creditNote->id,
                    'current_status' => $event->creditNote->status,
                ]);

                return;
            }

            // Verificar si ya existe un PDF
            if (!empty($event->creditNote->pdf_path)) {
                Log::info('PDF ya existe para esta nota de crédito', [
                    'credit_note_id' => $event->creditNote->id,
                    'pdf_path' => $event->creditNote->pdf_path,
                ]);

                return;
            }

            // Generar PDF usando nuestro UseCase dedicado
            $pdfPath = $this->generateCreditNotePdfUseCase->execute($event->creditNote);

            Log::info('PDF de nota de crédito generado exitosamente por listener', [
                'credit_note_id' => $event->creditNote->id,
                'credit_note_number' => $event->creditNote->credit_note_number,
                'pdf_path' => $pdfPath,
            ]);

        } catch (Exception $e) {
            Log::error('Error generando PDF de nota de crédito automáticamente', [
                'credit_note_id' => $event->creditNote->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

}