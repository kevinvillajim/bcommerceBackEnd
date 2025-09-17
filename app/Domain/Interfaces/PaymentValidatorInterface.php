<?php

namespace App\Domain\Interfaces;

use App\Domain\ValueObjects\PaymentResult;

/**
 * Interface para validadores de métodos de pago
 *
 * Cada método de pago (Datafast, Deuna, PayPal, etc.) implementa esta interface
 * para validar sus diferentes tipos de confirmación (widget, webhook, API, test, etc.)
 */
interface PaymentValidatorInterface
{
    /**
     * Valida datos de pago según el método específico
     *
     * @param  array  $paymentData  Datos del pago a validar
     * @return PaymentResult Resultado de la validación
     */
    public function validatePayment(array $paymentData): PaymentResult;

    /**
     * Obtiene el método de pago que maneja este validador
     *
     * @return string Nombre del método (datafast, deuna, paypal, etc.)
     */
    public function getPaymentMethod(): string;

    /**
     * Obtiene el tipo de validación que maneja este validador
     *
     * @return string Tipo de validación (widget, webhook, api, test, etc.)
     */
    public function getValidationType(): string;
}
