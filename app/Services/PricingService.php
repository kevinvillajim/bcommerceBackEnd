<?php

namespace App\Services;

use App\Models\VolumeDiscount;

class PricingService
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * ✅ MÉTODO PRINCIPAL: Calcular totales de checkout con descuentos por volumen
     */
    public function calculateCheckoutTotals(array $cartItems): array
    {

        // 1. Procesar items con descuentos por volumen
        $processedItems = $this->processItemsWithVolumeDiscounts($cartItems);

        // 2. Agrupar items por seller
        $itemsBySeller = $this->groupItemsBySeller($processedItems);

        // 3. Calcular totales generales
        $totals = $this->calculateGeneralTotals($processedItems);

        // 4. Calcular breakdown detallado
        $breakdown = $this->calculatePricingBreakdown($processedItems, $totals);

        return [
            'totals' => $totals,
            'breakdown' => $breakdown,
            'items_by_seller' => $itemsBySeller,
            'processed_items' => $processedItems,
        ];
    }

    /**
     * ✅ PROCESAR ITEMS CON DESCUENTOS POR VOLUMEN
     */
    private function processItemsWithVolumeDiscounts(array $cartItems): array
    {
        $processedItems = [];

        foreach ($cartItems as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            $basePrice = $item['base_price'] ?? $item['price']; // Precio base del producto

            // ✅ FIXED: Aplicar descuentos en orden correcto según Excel
            // 1. PRIMERO aplicar descuento del SELLER al precio total
            $sellerDiscountPercentage = $item['discount_percentage'] ?? 0;
            $priceAfterSellerDiscount = $basePrice;
            $sellerDiscountPerUnit = 0;

            if ($sellerDiscountPercentage > 0) {
                $sellerDiscountPerUnit = $basePrice * ($sellerDiscountPercentage / 100);
                $priceAfterSellerDiscount = $basePrice - $sellerDiscountPerUnit;
            }

            // 2. LUEGO aplicar descuento por VOLUMEN sobre el subtotal con descuento de seller
            $subtotalAfterSeller = $priceAfterSellerDiscount * $quantity;
            $volumeDiscountAmount = 0;
            $volumeDiscountPercentage = 0;
            $volumeDiscountLabel = null;

            // Obtener descuento por volumen para esta cantidad
            $volumeDiscount = VolumeDiscount::getDiscountForQuantity($productId, $quantity);
            if ($volumeDiscount) {
                $volumeDiscountPercentage = $volumeDiscount['discount'];
                $volumeDiscountAmount = $subtotalAfterSeller * ($volumeDiscountPercentage / 100);
                $volumeDiscountLabel = $volumeDiscount['label'];
            }

            $subtotalAfterVolume = $subtotalAfterSeller - $volumeDiscountAmount;
            $finalPrice = $subtotalAfterVolume / $quantity;

            $processedItems[] = [
                'product_id' => $productId,
                'seller_id' => $item['seller_id'],
                'quantity' => $quantity,
                'base_price' => $basePrice,
                'volume_discount_percentage' => $volumeDiscountPercentage,
                'volume_discount_price' => $finalPrice,
                'volume_savings_per_item' => $volumeDiscountAmount / $quantity,
                'volume_savings_total' => $volumeDiscountAmount,
                'seller_discount_percentage' => $sellerDiscountPercentage,
                'seller_discount_amount' => $sellerDiscountPerUnit * $quantity,
                'final_price' => $finalPrice,
                'final_subtotal' => $subtotalAfterVolume,
                'volume_discount_label' => $volumeDiscountLabel,
                'attributes' => $item['attributes'] ?? [],
            ];

        }

        return $processedItems;
    }

    /**
     * ✅ AGRUPAR ITEMS POR SELLER
     */
    private function groupItemsBySeller(array $processedItems): array
    {
        $itemsBySeller = [];

        foreach ($processedItems as $item) {
            $sellerId = $item['seller_id'];

            if (! isset($itemsBySeller[$sellerId])) {
                $itemsBySeller[$sellerId] = [];
            }

            $itemsBySeller[$sellerId][] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['final_price'], // Precio final con descuentos aplicados
                'subtotal' => $item['final_subtotal'],
                'original_price' => $item['base_price'],
                'volume_discount_percentage' => $item['volume_discount_percentage'],
                'volume_savings' => $item['volume_savings_total'],
                'discount_label' => $item['volume_discount_label'],
            ];
        }

        return $itemsBySeller;
    }

    /**
     * ✅ CALCULAR TOTALES GENERALES
     */
    private function calculateGeneralTotals(array $processedItems): array
    {
        $subtotalProducts = 0;
        $subtotalOriginal = 0; // ✅ NUEVO: Subtotal sin descuentos
        $totalVolumeDiscount = 0;
        $totalSellerDiscount = 0;
        $volumeDiscountsApplied = false;

        foreach ($processedItems as $item) {
            $subtotalProducts += $item['final_subtotal'];
            $subtotalOriginal += $item['base_price'] * $item['quantity']; // ✅ PRECIO ORIGINAL
            $totalVolumeDiscount += $item['volume_savings_total'];
            $totalSellerDiscount += $item['seller_discount_amount'];

            if ($item['volume_discount_percentage'] > 0) {
                $volumeDiscountsApplied = true;
            }
        }

        // ✅ FIXED: Cálculos exactos para evitar errores de redondeo - IVA dinámico
        $taxRatePercentage = $this->configService->getConfig('payment.taxRate', 15.0);
        $ivaRate = $taxRatePercentage / 100; // Convertir % a decimal

        // Trabajar con centavos para precisión exacta
        $subtotalProductsCents = round($subtotalProducts * 100);
        $subtotalOriginalCents = round($subtotalOriginal * 100);
        $totalVolumeDiscountCents = round($totalVolumeDiscount * 100);
        $totalSellerDiscountCents = round($totalSellerDiscount * 100);

        $ivaAmountCents = round($subtotalProductsCents * $ivaRate);

        // Calcular costos de envío
        $shippingInfo = $this->calculateShippingCosts($subtotalProducts);
        $shippingCostCents = round($shippingInfo['shipping_cost'] * 100);

        // Total final en centavos
        $finalTotalCents = $subtotalProductsCents + $ivaAmountCents + $shippingCostCents;

        return [
            'subtotal_original' => $subtotalOriginalCents / 100,
            'subtotal_products' => $subtotalProductsCents / 100,
            'iva_amount' => $ivaAmountCents / 100,
            'shipping_cost' => $shippingCostCents / 100,
            'total_discounts' => ($totalVolumeDiscountCents + $totalSellerDiscountCents) / 100,
            'total_volume_discount' => $totalVolumeDiscountCents / 100,
            'total_seller_discount' => $totalSellerDiscountCents / 100, // ✅ ESTE CAMPO SIEMPRE EXISTE
            'final_total' => $finalTotalCents / 100,
            'shipping_info' => $shippingInfo,
            'volume_discounts_applied' => $volumeDiscountsApplied,
            'seller_totals' => $this->calculateSellerTotals($processedItems),
        ];
    }

    /**
     * ✅ CALCULAR COSTOS DE ENVÍO
     */
    private function calculateShippingCosts(float $subtotal): array
    {
        $shippingEnabled = $this->configService->getConfig('shipping.enabled', true);
        $freeThreshold = $this->configService->getConfig('shipping.free_threshold', 50.00);
        $defaultCost = $this->configService->getConfig('shipping.default_cost', 5.00);

        if (! $shippingEnabled) {
            return [
                'shipping_cost' => 0,
                'free_shipping' => true,
                'free_shipping_threshold' => null,
            ];
        }

        $freeShipping = $subtotal >= $freeThreshold;
        $shippingCost = $freeShipping ? 0 : $defaultCost;

        return [
            'shipping_cost' => $shippingCost,
            'free_shipping' => $freeShipping,
            'free_shipping_threshold' => $freeThreshold,
        ];
    }

    /**
     * ✅ CALCULAR TOTALES POR SELLER
     */
    private function calculateSellerTotals(array $processedItems): array
    {
        $sellerTotals = [];

        foreach ($processedItems as $item) {
            $sellerId = $item['seller_id'];

            if (! isset($sellerTotals[$sellerId])) {
                $sellerTotals[$sellerId] = [
                    'subtotal' => 0,
                    'volume_discount' => 0,
                    'seller_discount' => 0,
                ];
            }

            $sellerTotals[$sellerId]['subtotal'] += $item['final_subtotal'];
            $sellerTotals[$sellerId]['volume_discount'] += $item['volume_savings_total'];
            $sellerTotals[$sellerId]['seller_discount'] += $item['seller_discount_amount'];
        }

        return $sellerTotals;
    }

    /**
     * ✅ CALCULAR BREAKDOWN DETALLADO
     */
    private function calculatePricingBreakdown(array $processedItems, array $totals): array
    {
        // Calcular subtotal original (sin descuentos)
        $subtotalOriginal = 0;
        foreach ($processedItems as $item) {
            $subtotalOriginal += $item['base_price'] * $item['quantity'];
        }

        return [
            'subtotal_original' => round($subtotalOriginal, 2),
            'subtotal_con_descuentos' => round($totals['subtotal_products'], 2),
            'total_ahorrado' => round($subtotalOriginal - $totals['subtotal_products'], 2),
            'iva' => round($totals['iva_amount'], 2),
            'envio' => round($totals['shipping_cost'], 2),
            'facturado' => round($totals['subtotal_products'] + $totals['iva_amount'], 2), // Sin envío
            'pagado' => round($totals['final_total'], 2), // Con envío
            'ahorros_volumen' => round($totals['total_volume_discount'] ?? 0, 2), // ✅ FIXED: Fallback a 0 si no existe
            'ahorros_seller' => round($totals['total_seller_discount'] ?? 0, 2), // ✅ FIXED: Fallback a 0 si no existe
        ];
    }

    /**
     * ✅ VALIDAR ESTRUCTURA DE ITEMS DEL CARRITO
     */
    public function validateCartItems(array $cartItems): array
    {
        $validatedItems = [];

        foreach ($cartItems as $index => $item) {
            if (! isset($item['product_id']) || ! is_numeric($item['product_id'])) {
                throw new \InvalidArgumentException("Item {$index}: product_id es requerido y debe ser numérico");
            }

            if (! isset($item['quantity']) || ! is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                throw new \InvalidArgumentException("Item {$index}: quantity debe ser un número mayor a 0");
            }

            if (! isset($item['price']) || ! is_numeric($item['price']) || $item['price'] <= 0) {
                throw new \InvalidArgumentException("Item {$index}: price debe ser un número mayor a 0");
            }

            $validatedItems[] = [
                'product_id' => (int) $item['product_id'],
                'seller_id' => $item['seller_id'] ?? null,
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['price'],
                'base_price' => $item['base_price'] ?? (float) $item['price'],
                'discount_percentage' => $item['discount_percentage'] ?? 0,
                'attributes' => $item['attributes'] ?? [],
            ];
        }

        return $validatedItems;
    }
}
