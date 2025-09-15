<?php

namespace App\Domain\Entities;

class SellerOrderEntity
{
    private ?int $id;

    private int $orderId;

    private int $sellerId;

    private array $items;

    private float $total;

    private string $status;

    private ?array $shippingData;

    private ?string $orderNumber;

    private ?\DateTime $createdAt;

    private ?\DateTime $updatedAt;

    // ✅ NUEVOS: Campos de descuentos por volumen
    private ?float $originalTotal;

    private float $volumeDiscountSavings;

    private bool $volumeDiscountsApplied;

    private float $shippingCost;

    // ✅ NUEVOS: Campos de pago
    private string $paymentStatus;

    private ?string $paymentMethod;

    public function __construct(
        int $orderId,
        int $sellerId,
        array $items = [],
        float $total = 0.0,
        string $status = 'pending',
        ?array $shippingData = null,
        ?string $orderNumber = null,
        ?int $id = null,
        ?\DateTime $createdAt = null,
        ?\DateTime $updatedAt = null,
        // ✅ NUEVOS: Parámetros de descuentos por volumen
        ?float $originalTotal = null,
        float $volumeDiscountSavings = 0.0,
        bool $volumeDiscountsApplied = false,
        float $shippingCost = 0.0,
        // ✅ NUEVOS: Parámetros de pago
        string $paymentStatus = 'pending',
        ?string $paymentMethod = null
    ) {
        $this->orderId = $orderId;
        $this->sellerId = $sellerId;
        $this->items = $items;
        $this->total = $total;
        $this->status = $status;
        $this->shippingData = $shippingData;
        $this->orderNumber = $orderNumber;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTime;
        $this->updatedAt = $updatedAt ?? new \DateTime;

        // ✅ NUEVOS: Campos de descuentos por volumen
        $this->originalTotal = $originalTotal;
        $this->volumeDiscountSavings = $volumeDiscountSavings;
        $this->volumeDiscountsApplied = $volumeDiscountsApplied;
        $this->shippingCost = $shippingCost;

        // ✅ NUEVOS: Campos de pago
        $this->paymentStatus = $paymentStatus;
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * ✅ ACTUALIZADO: Método para crear una nueva orden de vendedor con descuentos
     */
    public static function create(
        int $orderId,
        int $sellerId,
        array $items = [],
        float $total = 0.0,
        string $status = 'pending',
        ?array $shippingData = null,
        ?string $orderNumber = null,
        // ✅ NUEVOS: Parámetros de descuentos por volumen
        ?float $originalTotal = null,
        float $volumeDiscountSavings = 0.0,
        bool $volumeDiscountsApplied = false,
        // ✅ NUEVOS: Parámetros de pago
        string $paymentStatus = 'pending',
        ?string $paymentMethod = null
    ): self {
        // Extraer shipping cost de shippingData si está disponible
        $shippingCost = $shippingData['shipping_cost'] ?? 0.0;

        return new self(
            $orderId,
            $sellerId,
            $items,
            $total,
            $status,
            $shippingData,
            $orderNumber,
            null,
            null,
            null,
            $originalTotal,
            $volumeDiscountSavings,
            $volumeDiscountsApplied,
            $shippingCost,
            $paymentStatus,
            $paymentMethod
        );
    }

    /**
     * ✅ ACTUALIZADO: Método para reconstruir una orden de vendedor desde la base de datos
     */
    public static function reconstitute(
        int $id,
        int $orderId,
        int $sellerId,
        float $total,
        string $status,
        ?array $shippingData,
        ?string $orderNumber,
        string $createdAt,
        string $updatedAt,
        array $items = [],
        // ✅ NUEVOS: Parámetros de descuentos por volumen
        ?float $originalTotal = null,
        float $volumeDiscountSavings = 0.0,
        bool $volumeDiscountsApplied = false,
        float $shippingCost = 0.0,
        // ✅ NUEVOS: Parámetros de pago
        string $paymentStatus = 'pending',
        ?string $paymentMethod = null
    ): self {
        return new self(
            $orderId,
            $sellerId,
            $items,
            $total,
            $status,
            $shippingData,
            $orderNumber,
            $id,
            new \DateTime($createdAt),
            new \DateTime($updatedAt),
            $originalTotal,
            $volumeDiscountSavings,
            $volumeDiscountsApplied,
            $shippingCost,
            $paymentStatus,
            $paymentMethod
        );
    }

    // ✅ GETTERS EXISTENTES
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getSellerId(): int
    {
        return $this->sellerId;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getShippingData(): ?array
    {
        return $this->shippingData;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    // ✅ NUEVOS GETTERS: Campos de descuentos por volumen
    public function getOriginalTotal(): ?float
    {
        return $this->originalTotal;
    }

    public function getVolumeDiscountSavings(): float
    {
        return $this->volumeDiscountSavings;
    }

    public function getVolumeDiscountsApplied(): bool
    {
        return $this->volumeDiscountsApplied;
    }

    public function getShippingCost(): float
    {
        return $this->shippingCost;
    }

    // ✅ NUEVOS GETTERS: Campos de pago
    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    // ✅ SETTERS EXISTENTES
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setOrderId(int $orderId): void
    {
        $this->orderId = $orderId;
        $this->updateTimestamp();
    }

    public function setSellerId(int $sellerId): void
    {
        $this->sellerId = $sellerId;
        $this->updateTimestamp();
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
        $this->recalculateTotal();
        $this->updateTimestamp();
    }

    public function addItem(array $item): void
    {
        $this->items[] = $item;
        $this->recalculateTotal();
        $this->updateTimestamp();
    }

    public function removeItem(int $itemIndex): void
    {
        if (isset($this->items[$itemIndex])) {
            unset($this->items[$itemIndex]);
            $this->items = array_values($this->items); // Reindexar el array
            $this->recalculateTotal();
            $this->updateTimestamp();
        }
    }

    public function setTotal(float $total): void
    {
        $this->total = $total;
        $this->updateTimestamp();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->updateTimestamp();
    }

    public function setShippingData(?array $shippingData): void
    {
        $this->shippingData = $shippingData;
        // Actualizar shipping cost si viene en los datos
        if (isset($shippingData['shipping_cost'])) {
            $this->shippingCost = $shippingData['shipping_cost'];
        }
        $this->updateTimestamp();
    }

    public function setOrderNumber(string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
        $this->updateTimestamp();
    }

    // ✅ NUEVOS SETTERS: Campos de descuentos por volumen
    public function setVolumeDiscountInfo(?float $originalTotal, float $volumeDiscountSavings, bool $volumeDiscountsApplied): void
    {
        $this->originalTotal = $originalTotal;
        $this->volumeDiscountSavings = $volumeDiscountSavings;
        $this->volumeDiscountsApplied = $volumeDiscountsApplied;
        $this->updateTimestamp();
    }

    public function setShippingCost(float $shippingCost): void
    {
        $this->shippingCost = $shippingCost;
        $this->updateTimestamp();
    }

    // ✅ MÉTODOS DE NEGOCIO EXISTENTES
    public function isShipped(): bool
    {
        return $this->status === 'shipped' || $this->status === 'delivered';
    }

    public function canBeCancelled(): bool
    {
        return $this->status === 'pending' || $this->status === 'processing';
    }

    // ✅ NUEVOS MÉTODOS DE NEGOCIO
    public function hasVolumeDiscounts(): bool
    {
        return $this->volumeDiscountsApplied && $this->volumeDiscountSavings > 0;
    }

    public function getDiscountPercentage(): float
    {
        if (! $this->originalTotal || $this->originalTotal <= 0) {
            return 0;
        }

        return round(($this->volumeDiscountSavings / $this->originalTotal) * 100, 2);
    }

    public function getTotalWithShipping(): float
    {
        return $this->total + $this->shippingCost;
    }

    private function recalculateTotal(): void
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item['subtotal'] ?? ($item['price'] * $item['quantity']);
        }
        $this->total = $total;
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime;
    }

    /**
     * ✅ ACTUALIZADA: Convierte la entidad a un array con campos de descuentos por volumen
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'seller_id' => $this->sellerId,
            'total' => $this->total,
            'status' => $this->status,
            'shipping_data' => $this->shippingData,
            'order_number' => $this->orderNumber,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
            'items' => $this->items,

            // ✅ NUEVOS: Campos de descuentos por volumen
            'original_total' => $this->originalTotal,
            'volume_discount_savings' => $this->volumeDiscountSavings,
            'volume_discounts_applied' => $this->volumeDiscountsApplied,
            'shipping_cost' => $this->shippingCost,

            // ✅ NUEVOS: Campos de pago
            'payment_status' => $this->paymentStatus,
            'payment_method' => $this->paymentMethod,

            // Campos calculados
            'has_volume_discounts' => $this->hasVolumeDiscounts(),
            'discount_percentage' => $this->getDiscountPercentage(),
            'total_with_shipping' => $this->getTotalWithShipping(),
        ];
    }
}
