<?php

namespace App\Listeners;

use App\Events\CreditNoteApproved;
use App\Services\Mail\MailManager;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendCreditNoteEmailListener
{
    private MailManager $mailManager;

    public function __construct(MailManager $mailManager)
    {
        $this->mailManager = $mailManager;
    }

    /**
     * Maneja el evento CreditNoteApproved enviando email con PDF adjunto
     */
    public function handle(CreditNoteApproved $event): void
    {
        Log::info('🎯 LISTENER: SendCreditNoteEmailListener ejecutándose', [
            'credit_note_id' => $event->creditNote->id,
            'credit_note_number' => $event->creditNote->credit_note_number,
        ]);

        try {
            // ✅ PROTECCIÓN ANTI-DUPLICADOS: Verificar si ya se envió el email
            if ($event->creditNote->email_sent_at !== null) {
                Log::info('✅ PROTECCIÓN ANTI-DUPLICADOS: Email de nota de crédito ya fue enviado previamente', [
                    'credit_note_id' => $event->creditNote->id,
                    'credit_note_number' => $event->creditNote->credit_note_number,
                    'sent_at' => $event->creditNote->email_sent_at,
                    'protection_method' => 'email_sent_at timestamp'
                ]);
                return; // BLOQUEAR segundo envío
            }

            // Verificar que la nota de crédito esté aprobada por el SRI
            if ($event->creditNote->status !== $event->creditNote::STATUS_AUTHORIZED) {
                Log::warning('Nota de crédito no está en estado aprobado, saltando envío de email', [
                    'credit_note_id' => $event->creditNote->id,
                    'current_status' => $event->creditNote->status,
                ]);

                return;
            }

            // Refrescar el modelo para obtener el pdf_path actualizado
            $event->creditNote->refresh();

            // Verificar que exista un PDF generado
            if (empty($event->creditNote->pdf_path)) {
                Log::warning('No hay PDF disponible para esta nota de crédito, saltando envío de email', [
                    'credit_note_id' => $event->creditNote->id,
                    'pdf_path' => $event->creditNote->pdf_path,
                ]);

                return;
            }

            // Verificar que el archivo PDF realmente exista
            $pdfExists = Storage::disk('public')->exists($event->creditNote->pdf_path);
            if (!$pdfExists) {
                Log::error('El archivo PDF no existe en storage', [
                    'credit_note_id' => $event->creditNote->id,
                    'pdf_path' => $event->creditNote->pdf_path,
                ]);

                return;
            }

            // Cargar datos del usuario asociado a la nota de crédito
            $user = $event->creditNote->user;
            if (!$user) {
                Log::error('No se pudo encontrar el usuario asociado a la nota de crédito', [
                    'credit_note_id' => $event->creditNote->id,
                    'user_id' => $event->creditNote->user_id,
                ]);

                return;
            }

            // Enviar el email con PDF adjunto
            $emailSent = $this->mailManager->sendCreditNoteEmail(
                $user,
                $event->creditNote,
                $event->creditNote->pdf_path
            );

            if ($emailSent) {
                // ✅ MARCAR TIMESTAMP: Solo cuando el email se envía exitosamente
                $event->creditNote->update(['email_sent_at' => now()]);

                Log::info('✅ Email de nota de crédito enviado exitosamente y timestamp marcado', [
                    'credit_note_id' => $event->creditNote->id,
                    'credit_note_number' => $event->creditNote->credit_note_number,
                    'customer_email' => $event->creditNote->customer_email,
                    'pdf_path' => $event->creditNote->pdf_path,
                    'email_sent_at' => now(),
                ]);
            } else {
                Log::error('❌ Error enviando email de nota de crédito - timestamp NO marcado', [
                    'credit_note_id' => $event->creditNote->id,
                    'credit_note_number' => $event->creditNote->credit_note_number,
                    'reason' => 'Email failed to send'
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error enviando email de nota de crédito automáticamente', [
                'credit_note_id' => $event->creditNote->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // No lanzar la excepción para no afectar el flujo
            // El error ya queda registrado en los logs para revisión
        }
    }
}