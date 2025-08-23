<?php

namespace App\Domain\Entities;

class OrderEntity
{
    private ?int $id;

    private int $userId;

    private ?int $sellerId;

    private array $items;

    private float $total;

    private string $status;

    private ?string $paymentId;

    private ?string $paymentMethod;

    private ?string $paymentStatus;

    private ?array $shippingData;

    private string $orderNumber;

    private ?array $sellerOrders = null;

    private ?\DateTime $createdAt;

    private ?\DateTime $updatedAt;

    // ✅ CAMPOS EXISTENTES: Descuentos por volumen
    private ?float $originalTotal;

    private float $volumeDiscountSavings;

    private bool $volumeDiscountsApplied;

    // 🔧 AGREGADO: Descuentos del vendedor
    private float $sellerDiscountSavings;

    // ✅ NUEVOS: Campos para desglose de precios
    private float $subtotalProducts;

    private float $ivaAmount;

    private float $shippingCost;

    private float $totalDiscounts;

    private bool $freeShipping;

    private ?float $freeShippingThreshold;

    private ?array $pricingBreakdown;

    // ✅ NUEVOS: Campos para código de descuento de feedback
    private ?string $feedbackDiscountCode;

    private float $feedbackDiscountAmount;

    private float $feedbackDiscountPercentage;

    // 🔧 AGREGADO: Detalles de pago
    private ?array $paymentDetails;

    public function __construct(
        int $userId,
        ?int $sellerId = null,
        array $items = [],
        float $total = 0.0,
        string $status = 'pending',
        ?string $paymentId = null,
        ?string $paymentMethod = null,
        ?string $paymentStatus = null,
        ?array $shippingData = null,
        ?string $orderNumber = null,
        ?int $id = null,
        ?\DateTime $createdAt = null,
        ?\DateTime $updatedAt = null,
        // Campos existentes de descuentos por volumen
        ?float $originalTotal = null,
        float $volumeDiscountSavings = 0.0,
        bool $volumeDiscountsApplied = false,
        // 🔧 AGREGADO: Descuentos del vendedor
        float $sellerDiscountSavings = 0.0,
        // ✅ NUEVOS: Campos de desglose de precios
        float $subtotalProducts = 0.0,
        float $ivaAmount = 0.0,
        float $shippingCost = 0.0,
        float $totalDiscounts = 0.0,
        bool $freeShipping = false,
        ?float $freeShippingThreshold = null,
        ?array $pricingBreakdown = null,
        // ✅ NUEVOS: Parámetros para códigos de descuento de feedback
        ?string $feedbackDiscountCode = null,
        float $feedbackDiscountAmount = 0.0,
        float $feedbackDiscountPercentage = 0.0,
        // 🔧 AGREGADO: Parámetro para payment_details
        ?array $paymentDetails = null
    ) {
        $this->userId = $userId;
        $this->sellerId = $sellerId;
        $this->items = $items;
        $this->total = $total;
        $this->status = $status;
        $this->paymentId = $paymentId;
        $this->paymentMethod = $paymentMethod;
        $this->paymentStatus = $paymentStatus;
        $this->shippingData = $shippingData;
        $this->id = $id;

        // Campos existentes de descuentos por volumen
        $this->originalTotal = $originalTotal;
        $this->volumeDiscountSavings = $volumeDiscountSavings;
        $this->volumeDiscountsApplied = $volumeDiscountsApplied;
        // 🔧 AGREGADO: Descuentos del vendedor
        $this->sellerDiscountSavings = $sellerDiscountSavings;

        // ✅ NUEVOS: Campos de desglose de precios
        $this->subtotalProducts = $subtotalProducts;
        $this->ivaAmount = $ivaAmount;
        $this->shippingCost = $shippingCost;
        $this->totalDiscounts = $totalDiscounts;
        $this->freeShipping = $freeShipping;
        $this->freeShippingThreshold = $freeShippingThreshold;
        $this->pricingBreakdown = $pricingBreakdown;

        // ✅ NUEVOS: Campos de códigos de descuento de feedback
        $this->feedbackDiscountCode = $feedbackDiscountCode;
        $this->feedbackDiscountAmount = $feedbackDiscountAmount;
        $this->feedbackDiscountPercentage = $feedbackDiscountPercentage;

        // 🔧 AGREGADO: Inicializar payment_details
        $this->paymentDetails = $paymentDetails;

        // Generar número de orden
        if ($orderNumber !== null) {
            $this->orderNumber = $orderNumber;
        } elseif ($id !== null) {
            $this->orderNumber = 'ORD-'.str_pad($id, 8, '0', STR_PAD_LEFT);
        } else {
            $this->orderNumber = 'ORD-'.uniqid().'-TMP';
        }

        $this->createdAt = $createdAt ?? new \DateTime;
        $this->updatedAt = $updatedAt ?? new \DateTime;
    }

    /**
     * ✅ ACTUALIZADO: Método para crear orden con pricing completo
     */
    public static function create(
        int $userId,
        ?int $sellerId = null,
        array $items = [],
        float $total = 0.0,
        string $status = 'pending',
        ?array $shippingData = null,
        // Campos existentes de descuentos por volumen
        ?float $originalTotal = null,
        float $volumeDiscountSavings = 0.0,
        bool $volumeDiscountsApplied = false,
        // 🔧 AGREGADO: Descuentos del vendedor
        float $sellerDiscountSavings = 0.0,
        // ✅ NUEVOS: Campos de pricing
        float $subtotalProducts = 0.0,
        float $ivaAmount = 0.0,
        float $shippingCost = 0.0,
        float $totalDiscounts = 0.0,
        bool $freeShipping = false,
        ?float $freeShippingThreshold = null,
        ?array $pricingBreakdown = null,
        // ✅ NUEVOS: Parámetros para códigos de descuento
        ?string $feedbackDiscountCode = null,
        float $feedbackDiscountAmount = 0.0,
        float $feedbackDiscountPercentage = 0.0,
        // 🔧 AGREGADO: payment_details
        ?array $paymentDetails = null
    ): self {
        return new self(
            $userId,
            $sellerId,
            $items,
            $total,
            $status,
            null, // paymentId
            null, // paymentMethod
            null, // paymentStatus
            $shippingData,
            null, // orderNumber
            null, // id
            null, // createdAt
            null, // updatedAt
            $originalTotal,
            $volumeDiscountSavings,
            $volumeDiscountsApplied,
            $sellerDiscountSavings,
            $subtotalProducts,
            $ivaAmount,
            $shippingCost,
            $totalDiscounts,
            $freeShipping,
            $freeShippingThreshold,
            $pricingBreakdown,
            $feedbackDiscountCode,
            $feedbackDiscountAmount,
            $feedbackDiscountPercentage,
            $paymentDetails
        );
    }

    /**
     * ✅ ACTUALIZADO: Método para reconstruir desde base de datos
     */
    public static function reconstitute(
        int $id,
        int $userId,
        ?int $sellerId,
        float $total,
        string $status,
        ?string $paymentId,
        ?string $paymentMethod,
        ?string $paymentStatus,
        ?array $shippingData,
        string $orderNumber,
        string $createdAt,
        string $updatedAt,
        array $items = [],
        // Campos existentes de descuentos por volumen
        ?float $originalTotal = null,
        float $volumeDiscountSavings = 0.0,
        bool $volumeDiscountsApplied = false,
        // ✅ NUEVOS: Campos de pricing
        float $subtotalProducts = 0.0,
        float $ivaAmount = 0.0,
        float $shippingCost = 0.0,
        float $totalDiscounts = 0.0,
        bool $freeShipping = false,
        ?float $freeShippingThreshold = null,
        ?array $pricingBreakdown = null,
        ?string $feedbackDiscountCode = null,
        float $feedbackDiscountAmount = 0.0,
        float $feedbackDiscountPercentage = 0.0
    ): self {
        return new self(
            $userId,
            $sellerId,
            $items,
            $total,
            $status,
            $paymentId,
            $paymentMethod,
            $paymentStatus,
            $shippingData,
            $orderNumber,
            $id,
            new \DateTime($createdAt),
            new \DateTime($updatedAt),
            $originalTotal,
            $volumeDiscountSavings,
            $volumeDiscountsApplied,
            $subtotalProducts,
            $ivaAmount,
            $shippingCost,
            $totalDiscounts,
            $freeShipping,
            $freeShippingThreshold,
            $pricingBreakdown,
            $feedbackDiscountCode,
            $feedbackDiscountAmount,
            $feedbackDiscountPercentage
        );
    }

    // ✅ GETTERS EXISTENTES...
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getSellerId(): ?int
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

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function getShippingData(): ?array
    {
        return $this->shippingData;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getSellerOrders(): ?array
    {
        return $this->sellerOrders;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    // Getters existentes de descuentos por volumen
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

    // ✅ NUEVOS GETTERS: Campos de pricing
    public function getSubtotalProducts(): float
    {
        return $this->subtotalProducts;
    }

    public function getIvaAmount(): float
    {
        return $this->ivaAmount;
    }

    public function getShippingCost(): float
    {
        return $this->shippingCost;
    }

    public function getTotalDiscounts(): float
    {
        return $this->totalDiscounts;
    }

    public function getFreeShipping(): bool
    {
        return $this->freeShipping;
    }

    public function getFreeShippingThreshold(): ?float
    {
        return $this->freeShippingThreshold;
    }

    public function getPricingBreakdown(): ?array
    {
        return $this->pricingBreakdown;
    }

    // ✅ NUEVOS GETTERS: Códigos de descuento de feedback
    public function getFeedbackDiscountCode(): ?string
    {
        return $this->feedbackDiscountCode;
    }

    public function getFeedbackDiscountAmount(): float
    {
        return $this->feedbackDiscountAmount;
    }

    public function getFeedbackDiscountPercentage(): float
    {
        return $this->feedbackDiscountPercentage;
    }

    // ✅ NUEVOS SETTERS: Campos de pricing
    public function setSubtotalProducts(float $subtotalProducts): void
    {
        $this->subtotalProducts = $subtotalProducts;
        $this->updateTimestamp();
    }

    public function setIvaAmount(float $ivaAmount): void
    {
        $this->ivaAmount = $ivaAmount;
        $this->updateTimestamp();
    }

    public function setShippingCost(float $shippingCost): void
    {
        $this->shippingCost = $shippingCost;
        $this->updateTimestamp();
    }

    public function setTotalDiscounts(float $totalDiscounts): void
    {
        $this->totalDiscounts = $totalDiscounts;
        $this->updateTimestamp();
    }

    public function setFreeShipping(bool $freeShipping): void
    {
        $this->freeShipping = $freeShipping;
        $this->updateTimestamp();
    }

    public function setFreeShippingThreshold(?float $freeShippingThreshold): void
    {
        $this->freeShippingThreshold = $freeShippingThreshold;
        $this->updateTimestamp();
    }

    public function setPricingBreakdown(?array $pricingBreakdown): void
    {
        $this->pricingBreakdown = $pricingBreakdown;
        $this->updateTimestamp();
    }

    // ✅ NUEVOS SETTERS: Códigos de descuento de feedback
    public function setFeedbackDiscountCode(?string $feedbackDiscountCode): void
    {
        $this->feedbackDiscountCode = $feedbackDiscountCode;
        $this->updateTimestamp();
    }

    public function setFeedbackDiscountAmount(float $feedbackDiscountAmount): void
    {
        $this->feedbackDiscountAmount = $feedbackDiscountAmount;
        $this->updateTimestamp();
    }

    public function setFeedbackDiscountPercentage(float $feedbackDiscountPercentage): void
    {
        $this->feedbackDiscountPercentage = $feedbackDiscountPercentage;
        $this->updateTimestamp();
    }

    /**
     * ✅ NUEVO: Método para establecer información de código de descuento de feedback
     */
    public function setFeedbackDiscountInfo(?string $code, float $amount, float $percentage): void
    {
        $this->feedbackDiscountCode = $code;
        $this->feedbackDiscountAmount = $amount;
        $this->feedbackDiscountPercentage = $percentage;
        $this->updateTimestamp();
    }

    /**
     * ✅ NUEVO: Método para establecer toda la información de pricing de una vez
     */
    public function setPricingInfo(array $pricingData): void
    {
        $this->subtotalProducts = $pricingData['subtotal_products'] ?? 0.0;
        $this->ivaAmount = $pricingData['iva_amount'] ?? 0.0;
        $this->shippingCost = $pricingData['shipping_cost'] ?? 0.0;
        $this->totalDiscounts = $pricingData['total_discounts'] ?? 0.0;
        $this->freeShipping = $pricingData['free_shipping'] ?? false;
        $this->freeShippingThreshold = $pricingData['free_shipping_threshold'] ?? null;
        $this->pricingBreakdown = $pricingData['pricing_breakdown'] ?? null;
        $this->updateTimestamp();
    }

    // ✅ MÉTODOS DE NEGOCIO ACTUALIZADOS

    /**
     * Obtener el monto facturado (productos + IVA, sin envío)
     */
    public function getBilledAmount(): float
    {
        return $this->subtotalProducts + $this->ivaAmount;
    }

    /**
     * Obtener el monto pagado (total con envío)
     */
    public function getPaidAmount(): float
    {
        return $this->total;
    }

    /**
     * Verificar si tiene envío gratuito
     */
    public function hasFreeShipping(): bool
    {
        return $this->freeShipping;
    }

    /**
     * Obtener porcentaje de IVA aplicado
     */
    public function getIvaPercentage(): float
    {
        if ($this->subtotalProducts <= 0) {
            return 0;
        }

        return round(($this->ivaAmount / $this->subtotalProducts) * 100, 2);
    }

    /**
     * Obtener porcentaje total de descuentos
     */
    public function getDiscountPercentage(): float
    {
        $originalAmount = $this->subtotalProducts + $this->totalDiscounts;
        if ($originalAmount <= 0) {
            return 0;
        }

        return round(($this->totalDiscounts / $originalAmount) * 100, 2);
    }

    // Métodos existentes...
    public function isPaid(): bool
    {
        return $this->paymentStatus === 'completed' || $this->paymentStatus === 'succeeded';
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped' || $this->status === 'delivered';
    }

    public function canBeCancelled(): bool
    {
        return $this->status === 'pending' || $this->status === 'processing';
    }

    public function hasMultipleSellers(): bool
    {
        return $this->sellerOrders !== null && count($this->sellerOrders) > 0;
    }

    // Setters existentes...
    public function setId(int $id): void
    {
        $this->id = $id;
        if ($this->orderNumber && strpos($this->orderNumber, '-TMP') !== false) {
            $this->orderNumber = 'ORD-'.str_pad($id, 8, '0', STR_PAD_LEFT);
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

    public function setPaymentInfo(?string $paymentId, ?string $paymentMethod, ?string $paymentStatus): void
    {
        $this->paymentId = $paymentId;
        $this->paymentMethod = $paymentMethod;
        $this->paymentStatus = $paymentStatus;
        $this->updateTimestamp();
    }

    public function setShippingData(?array $shippingData): void
    {
        $this->shippingData = $shippingData;
        $this->updateTimestamp();
    }

    public function setSellerOrders(?array $sellerOrders): void
    {
        $this->sellerOrders = $sellerOrders;
    }

    // 🔧 AGREGADO: Getter y setter para payment_details
    public function getPaymentDetails(): ?array
    {
        return $this->paymentDetails;
    }

    public function setPaymentDetails(?array $paymentDetails): void
    {
        $this->paymentDetails = $paymentDetails;
        $this->updateTimestamp();
    }

    // 🔧 AGREGADO: Getter y setter para seller discount savings
    public function getSellerDiscountSavings(): float
    {
        return $this->sellerDiscountSavings;
    }

    public function setSellerDiscountSavings(float $sellerDiscountSavings): void
    {
        $this->sellerDiscountSavings = $sellerDiscountSavings;
        $this->updateTimestamp();
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime;
    }

    public function recalculateTotal(): void
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        $this->total = $total;
    }

    /**
     * ✅ ACTUALIZADA: Convierte la entidad a array con campos de pricing
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'seller_id' => $this->sellerId,
            'total' => $this->total,
            'status' => $this->status,
            'payment_id' => $this->paymentId,
            'payment_method' => $this->paymentMethod,
            'payment_status' => $this->paymentStatus,
            'shipping_data' => $this->shippingData,
            'order_number' => $this->orderNumber,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
            'items' => array_map(fn ($item) => $item instanceof OrderItemEntity ? $item->toArray() : $item, $this->items),

            // Campos existentes de descuentos por volumen
            'original_total' => $this->originalTotal,
            'volume_discount_savings' => $this->volumeDiscountSavings,
            'volume_discounts_applied' => $this->volumeDiscountsApplied,
            // 🔧 AGREGADO: Descuentos del vendedor
            'seller_discount_savings' => $this->sellerDiscountSavings,

            // ✅ NUEVOS: Campos de pricing
            'subtotal_products' => $this->subtotalProducts,
            'iva_amount' => $this->ivaAmount,
            'shipping_cost' => $this->shippingCost,
            'total_discounts' => $this->totalDiscounts,
            'free_shipping' => $this->freeShipping,
            'free_shipping_threshold' => $this->freeShippingThreshold,
            'pricing_breakdown' => $this->pricingBreakdown,

            // ✅ NUEVOS: Campos de códigos de descuento de feedback
            'feedback_discount_code' => $this->feedbackDiscountCode,
            'feedback_discount_amount' => $this->feedbackDiscountAmount,
            'feedback_discount_percentage' => $this->feedbackDiscountPercentage,

            // 🔧 AGREGADO: payment_details en toArray
            'payment_details' => $this->paymentDetails,

            // Campos calculados
            'billed_amount' => $this->getBilledAmount(),
            'paid_amount' => $this->getPaidAmount(),
            'iva_percentage' => $this->getIvaPercentage(),
            'discount_percentage' => $this->getDiscountPercentage(),
            'has_free_shipping' => $this->hasFreeShipping(),
        ];
    }
}
