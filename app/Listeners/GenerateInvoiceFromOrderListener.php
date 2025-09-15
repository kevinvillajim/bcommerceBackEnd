<?php

namespace App\Listeners;

use App\Events\InvoiceGenerated;
use App\Events\OrderCreated;
use App\UseCases\Accounting\GenerateInvoiceFromOrderUseCase;
use Exception;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceFromOrderListener
{
    private GenerateInvoiceFromOrderUseCase $generateInvoiceUseCase;

    public function __construct(GenerateInvoiceFromOrderUseCase $generateInvoiceUseCase)
    {
        $this->generateInvoiceUseCase = $generateInvoiceUseCase;
    }

    /**
     * ✅ Maneja el evento OrderCreated generando automáticamente una factura
     */
    public function handle(OrderCreated $event): void
    {
        Log::info('🎯 LISTENER: GenerateInvoiceFromOrderListener ejecutándose', [
            'order_id' => $event->orderId,
            'user_id' => $event->userId,
        ]);

        // ✅ Cargar orden desde el orderId del evento
        $order = \App\Models\Order::find($event->orderId);

        if (! $order) {
            Log::error('Orden no encontrada para generar factura', [
                'order_id' => $event->orderId,
            ]);

            return;
        }

        Log::info('Procesando generación automática de factura', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'total_amount' => $order->total_amount,
        ]);

        try {
            // ✅ Solo generar facturas para órdenes con pago confirmado
            if ($order->payment_status !== 'completed') {
                Log::info('Orden sin pago confirmado, saltando generación de factura', [
                    'order_id' => $order->id,
                    'payment_status' => $order->payment_status,
                ]);

                return;
            }

            // ✅ Verificar que no exista ya una factura para esta orden
            $existingInvoice = $order->invoice;
            if ($existingInvoice) {
                Log::warning('✅ PROTECCIÓN ANTI-DUPLICADOS: Orden ya tiene factura, saltando generación', [
                    'order_id' => $order->id,
                    'existing_invoice_id' => $existingInvoice->id,
                    'existing_invoice_number' => $existingInvoice->invoice_number,
                    'existing_invoice_status' => $existingInvoice->status,
                    'protection_method' => 'order->invoice relationship',
                ]);

                return;
            }

            Log::info('🔍 VERIFICACIÓN: No hay factura existente para esta orden, procederemos a generar', [
                'order_id' => $order->id,
                'order_payment_status' => $order->payment_status,
                'invoice_relationship_loaded' => $order->relationLoaded('invoice'),
            ]);

            // ✅ Generar la factura
            $invoice = $this->generateInvoiceUseCase->execute($order);

            Log::info('Factura generada automáticamente', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'order_id' => $order->id,
            ]);

            // ✅ Disparar evento de factura generada para que sea enviada al SRI
            event(new InvoiceGenerated($invoice));

        } catch (Exception $e) {
            Log::error('Error generando factura automáticamente', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // ✅ No lanzar la excepción para no afectar el flujo de checkout
            // El error ya queda registrado en los logs para revisión
        }
    }
}
