<?php

namespace App\Domain\ValueObjects;

/**
 * Value Object que representa el resultado de una validación de pago
 *
 * Unifica la respuesta de todos los validadores de pago para que
 * el PaymentProcessingService pueda procesarlos de manera consistente
 */
class PaymentResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly string $paymentMethod,
        public readonly string $validationType,
        public readonly array $metadata = [],
        public readonly ?string $errorMessage = null,
        public readonly ?string $errorCode = null
    ) {}

    /**
     * Crea un resultado exitoso
     */
    public static function success(
        string $transactionId,
        float $amount,
        string $paymentMethod,
        string $validationType,
        array $metadata = []
    ): self {
        return new self(
            isValid: true,
            transactionId: $transactionId,
            amount: $amount,
            paymentMethod: $paymentMethod,
            validationType: $validationType,
            metadata: $metadata
        );
    }

    /**
     * Crea un resultado fallido
     */
    public static function failure(
        string $paymentMethod,
        string $validationType,
        string $errorMessage,
        ?string $errorCode = null,
        array $metadata = []
    ): self {
        return new self(
            isValid: false,
            transactionId: '',
            amount: 0.0,
            paymentMethod: $paymentMethod,
            validationType: $validationType,
            metadata: $metadata,
            errorMessage: $errorMessage,
            errorCode: $errorCode
        );
    }

    /**
     * Convierte el resultado a array para serialización
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isValid,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod,
            'validation_type' => $this->validationType,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Verifica si el pago es válido
     */
    public function isSuccessful(): bool
    {
        return $this->isValid;
    }

    /**
     * Obtiene información completa del error (si existe)
     */
    public function getErrorInfo(): ?array
    {
        if ($this->isValid) {
            return null;
        }

        return [
            'message' => $this->errorMessage,
            'code' => $this->errorCode,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Alias para isSuccessful() - mantiene compatibilidad hacia atrás
     */
    public function isSuccess(): bool
    {
        return $this->isSuccessful();
    }
}
