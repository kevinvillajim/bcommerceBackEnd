<?php

namespace App\Listeners;

use App\Events\InvoiceApproved;
use App\Services\Mail\MailManager;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendInvoiceEmailListener
{
    private MailManager $mailManager;

    public function __construct(MailManager $mailManager)
    {
        $this->mailManager = $mailManager;
    }

    /**
     * ✅ Maneja el evento InvoiceApproved enviando email con PDF adjunto
     */
    public function handle(InvoiceApproved $event): void
    {
        Log::info('🎯 LISTENER: SendInvoiceEmailListener ejecutándose', [
            'invoice_id' => $event->invoice->id,
            'invoice_number' => $event->invoice->invoice_number,
        ]);

        try {
            // ✅ PROTECCIÓN ANTI-DUPLICADOS: Verificar si ya se envió el email
            if ($event->invoice->email_sent_at !== null) {
                Log::info('✅ PROTECCIÓN ANTI-DUPLICADOS: Email de factura ya fue enviado previamente', [
                    'invoice_id' => $event->invoice->id,
                    'invoice_number' => $event->invoice->invoice_number,
                    'sent_at' => $event->invoice->email_sent_at,
                    'protection_method' => 'email_sent_at timestamp'
                ]);
                return; // BLOQUEAR segundo envío
            }

            // ✅ Verificar que la factura esté aprobada por el SRI
            if ($event->invoice->status !== $event->invoice::STATUS_AUTHORIZED) {
                Log::warning('Factura no está en estado aprobado, saltando envío de email', [
                    'invoice_id' => $event->invoice->id,
                    'current_status' => $event->invoice->status,
                ]);

                return;
            }

            // ✅ Refrescar el modelo para obtener el pdf_path actualizado
            $event->invoice->refresh();

            // ✅ Verificar que exista un PDF generado
            if (empty($event->invoice->pdf_path)) {
                Log::warning('No hay PDF disponible para esta factura, saltando envío de email', [
                    'invoice_id' => $event->invoice->id,
                    'pdf_path' => $event->invoice->pdf_path,
                    'debug_invoice_fields' => [
                        'status' => $event->invoice->status,
                        'created_at' => $event->invoice->created_at,
                        'updated_at' => $event->invoice->updated_at,
                    ]
                ]);

                return;
            }

            // ✅ Verificar que el archivo PDF realmente exista
            $pdfExists = Storage::disk('public')->exists($event->invoice->pdf_path);
            if (! $pdfExists) {
                Log::error('El archivo PDF no existe en storage', [
                    'invoice_id' => $event->invoice->id,
                    'pdf_path' => $event->invoice->pdf_path,
                ]);

                return;
            }

            // ✅ Cargar datos del usuario asociado a la factura
            $user = $event->invoice->user;
            if (! $user) {
                Log::error('No se pudo encontrar el usuario asociado a la factura', [
                    'invoice_id' => $event->invoice->id,
                    'user_id' => $event->invoice->user_id,
                ]);

                return;
            }

            // ✅ Enviar el email con PDF adjunto
            $emailSent = $this->mailManager->sendInvoiceEmail(
                $user,
                $event->invoice,
                $event->invoice->pdf_path
            );

            if ($emailSent) {
                // ✅ MARCAR TIMESTAMP: Solo cuando el email se envía exitosamente
                $event->invoice->update(['email_sent_at' => now()]);

                Log::info('✅ Email de factura enviado exitosamente y timestamp marcado', [
                    'invoice_id' => $event->invoice->id,
                    'invoice_number' => $event->invoice->invoice_number,
                    'customer_email' => $event->invoice->customer_email,
                    'pdf_path' => $event->invoice->pdf_path,
                    'email_sent_at' => now(),
                ]);
            } else {
                Log::error('❌ Error enviando email de factura - timestamp NO marcado', [
                    'invoice_id' => $event->invoice->id,
                    'invoice_number' => $event->invoice->invoice_number,
                    'reason' => 'Email failed to send'
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error enviando email de factura automáticamente', [
                'invoice_id' => $event->invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // ✅ No lanzar la excepción para no afectar el flujo
            // El error ya queda registrado en los logs para revisión
        }
    }
}
