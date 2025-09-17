<?php

namespace App\Domain\ValueObjects;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Value Object que representa los datos temporales de checkout validados
 *
 * ESTRUCTURA EXACTA COMPATIBLE CON:
 * - Frontend TypeScript CheckoutData interface
 * - ProcessCheckoutUseCase parametros esperados
 * - DatafastController formato actual
 */
class CheckoutData
{
    public function __construct(
        public readonly int $userId,
        public readonly array $shippingData,
        public readonly array $billingData,
        public readonly array $items,
        public readonly array $totals,
        public readonly string $sessionId,
        public readonly Carbon $validatedAt,
        public readonly Carbon $expiresAt,
        public readonly ?string $discountCode = null,
        public readonly ?array $discountInfo = null,
        public readonly ?array $metadata = []
    ) {
        $this->validateData();
    }

    /**
     * Crea CheckoutData desde array del frontend (EXACTO como TypeScript interface)
     */
    public static function fromArray(array $data): self
    {
        // Validar campos requeridos según interface TypeScript
        $required = ['userId', 'shippingData', 'billingData', 'items', 'totals', 'sessionId', 'validatedAt', 'expiresAt'];
        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Campo requerido faltante: {$field}");
            }
        }

        return new self(
            userId: (int) $data['userId'],
            shippingData: $data['shippingData'],
            billingData: $data['billingData'],
            items: $data['items'],
            totals: $data['totals'],
            sessionId: $data['sessionId'],
            validatedAt: Carbon::parse($data['validatedAt']),
            expiresAt: Carbon::parse($data['expiresAt']),
            discountCode: $data['discountCode'] ?? null,
            discountInfo: $data['discountInfo'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * OPTIMIZADO: Validación rápida y eficiente
     */
    private function validateData(): void
    {
        // Validaciones críticas básicas
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('userId inválido');
        }

        if (empty($this->sessionId) || empty($this->items)) {
            throw new InvalidArgumentException('sessionId e items son requeridos');
        }

        // Validar campos críticos para ProcessCheckoutUseCase
        $this->validateAddressData($this->shippingData, 'shippingData');
        $this->validateAddressData($this->billingData, 'billingData');
        $this->validateItems();
        $this->validateTotals();
    }

    /**
     * Valida datos de dirección (shipping/billing)
     */
    private function validateAddressData(array $data, string $type): void
    {
        $required = ['name', 'email', 'phone', 'street', 'city', 'country', 'identification'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("{$type}.{$field} requerido");
            }
        }
    }

    /**
     * Valida estructura de items
     */
    private function validateItems(): void
    {
        foreach ($this->items as $index => $item) {
            if (!isset($item['product_id'], $item['quantity'], $item['price'])) {
                throw new InvalidArgumentException("Item {$index} incompleto");
            }
        }
    }

    /**
     * Valida totales críticos
     */
    private function validateTotals(): void
    {
        $critical = ['final_total', 'subtotal_with_discounts', 'iva_amount', 'shipping_cost'];
        foreach ($critical as $field) {
            if (!isset($this->totals[$field])) {
                throw new InvalidArgumentException("totals.{$field} requerido");
            }
        }
    }

    /**
     * Verifica si el CheckoutData ha expirado
     */
    public function isExpired(): bool
    {
        return Carbon::now()->isAfter($this->expiresAt);
    }

    /**
     * Verifica si el CheckoutData es válido y no ha expirado
     */
    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Obtiene el total final
     */
    public function getFinalTotal(): float
    {
        return (float) $this->totals['final_total'];
    }

    /**
     * OPTIMIZADO: Transforma datos de dirección para ProcessCheckoutUseCase
     */
    public function getProcessCheckoutShippingData(): array
    {
        return $this->transformAddressData($this->shippingData);
    }

    /**
     * OPTIMIZADO: Transforma datos de facturación para ProcessCheckoutUseCase
     */
    public function getProcessCheckoutBillingData(): array
    {
        return $this->transformAddressData($this->billingData);
    }

    /**
     * Transforma datos de dirección (DRY principle)
     */
    private function transformAddressData(array $data): array
    {
        return [
            'name' => $data['name'],
            'street' => $data['street'],
            'city' => $data['city'],
            'state' => $data['state'] ?? $data['city'], // Ecuador no usa states
            'country' => $data['country'],
            'postal_code' => $data['postal_code'] ?? '',
            'phone' => $data['phone'],
            'identification' => $data['identification'], // ✅ CRÍTICO PARA SRI
        ];
    }

    /**
     * OPTIMIZADO: Items para ProcessCheckoutUseCase (solo campos necesarios)
     */
    public function getProcessCheckoutItems(): array
    {
        return array_map(fn($item) => [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'subtotal' => $item['subtotal'] ?? ($item['price'] * $item['quantity']),
        ], $this->items);
    }

    /**
     * OPTIMIZADO: Totales para ProcessCheckoutUseCase (solo campos críticos)
     */
    public function getProcessCheckoutTotals(): array
    {
        // Solo los 4 campos críticos que usa ProcessCheckoutUseCase
        return [
            'final_total' => $this->totals['final_total'],
            'subtotal_with_discounts' => $this->totals['subtotal_with_discounts'],
            'iva_amount' => $this->totals['iva_amount'],
            'shipping_cost' => $this->totals['shipping_cost'],
        ];
    }

    /**
     * Crea datos de pago seguros para ProcessCheckoutUseCase
     * Sin skip_price_verification para mantener seguridad
     */
    public function createBasePaymentData(string $paymentMethod, string $transactionId, string $paymentId): array
    {
        return [
            'method' => $paymentMethod,
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
            'amount' => $this->getFinalTotal(),
            'currency' => 'USD',
            // NO incluir skip_price_verification - violaba principios de seguridad
        ];
    }

    /**
     * OPTIMIZADO: Información de descuento
     */
    public function getDiscountInfo(): ?array
    {
        return $this->discountInfo;
    }

    /**
     * OPTIMIZADO: Verifica descuento aplicado
     */
    public function hasDiscount(): bool
    {
        return !empty($this->discountCode);
    }

    /**
     * NUEVO: Obtiene código de descuento
     */
    public function getDiscountCode(): ?string
    {
        return $this->discountCode;
    }

    /**
     * OPTIMIZADO: Array mínimo para logging (sin datos sensibles)
     */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'sessionId' => $this->sessionId,
            'validatedAt' => $this->validatedAt->toISOString(),
            'expiresAt' => $this->expiresAt->toISOString(),
            'isExpired' => $this->isExpired(),
            'finalTotal' => $this->getFinalTotal(),
            'itemsCount' => count($this->items),
            'hasDiscountCode' => $this->hasDiscount(),
            'shippingCity' => $this->shippingData['city'] ?? 'N/A',
            'billingCity' => $this->billingData['city'] ?? 'N/A',
        ];
    }

    /**
     * NUEVO: Array completo para CheckoutDataService storage
     */
    public function toStorageArray(): array
    {
        return [
            'userId' => $this->userId,
            'shippingData' => $this->shippingData,
            'billingData' => $this->billingData,
            'items' => $this->items,
            'totals' => $this->totals,
            'sessionId' => $this->sessionId,
            'validatedAt' => $this->validatedAt->toISOString(),
            'expiresAt' => $this->expiresAt->toISOString(),
            'discountCode' => $this->discountCode,
            'discountInfo' => $this->discountInfo,
            'metadata' => $this->metadata,
        ];
    }
}
