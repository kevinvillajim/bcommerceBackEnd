<?php

namespace App\Domain\Interfaces;

interface PaymentGatewayInterface
{
    /**
     * Procesa un pago
     *
     * @param  array  $paymentData  Datos del pago (método, información de tarjeta, etc.)
     * @param  float  $amount  Monto a pagar
     * @return array Resultado del proceso de pago
     */
    public function processPayment(array $paymentData, float $amount): array;

    /**
     * Reembolsa un pago
     *
     * @param  string  $paymentId  ID del pago original
     * @param  float  $amount  Monto a reembolsar (opcional, si es null se reembolsa el total)
     * @return array Resultado del reembolso
     */
    public function refundPayment(string $paymentId, ?float $amount = null): array;

    /**
     * Verifica el estado de un pago
     *
     * @param  string  $paymentId  ID del pago
     * @return array Información del estado del pago
     */
    public function checkPaymentStatus(string $paymentId): array;
}
