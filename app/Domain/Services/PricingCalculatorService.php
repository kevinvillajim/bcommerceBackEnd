<?php

namespace App\Domain\Services;

use App\Domain\Repositories\ProductRepositoryInterface;
use App\Services\ConfigurationService;
use App\UseCases\Cart\ApplyCartDiscountCodeUseCase;
use Illuminate\Support\Facades\Log;

/**
 * 🧮 SERVICIO CENTRALIZADO DE CÁLCULOS DE PRICING
 * 
 * Este servicio es la ÚNICA fuente de verdad para todos los cálculos de pricing
 * en el sistema. Garantiza consistencia entre todos los flujos (Checkout, Deuna, Datafast).
 * 
 * SECUENCIA DE CÁLCULOS:
 * 1. Precio base (del producto)
 * 2. Descuento seller (% configurado en producto)  
 * 3. Descuento volumen (desde BD dinámica)
 * 4. Cupón descuento (5% sobre subtotal - OPCIONAL)
 * 5. Envío (configuración dinámica BD)
 * 6. IVA 15% (sobre subtotal + envío)
 */
class PricingCalculatorService
{
    private ProductRepositoryInterface $productRepository;
    private ConfigurationService $configService;
    private ApplyCartDiscountCodeUseCase $applyCartDiscountCodeUseCase;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ConfigurationService $configService,
        ApplyCartDiscountCodeUseCase $applyCartDiscountCodeUseCase
    ) {
        $this->productRepository = $productRepository;
        $this->configService = $configService;
        $this->applyCartDiscountCodeUseCase = $applyCartDiscountCodeUseCase;
    }

    /**
     * 🎯 MÉTODO PRINCIPAL: Calcular totales completos del carrito
     * 
     * @param array $cartItems Items del carrito en formato estándar
     * @param int $userId ID del usuario (para cupones)
     * @param string|null $couponCode Código de cupón opcional
     * @return array Resultado completo con todos los cálculos
     */
    public function calculateCartTotals(
        array $cartItems, 
        int $userId,
        ?string $couponCode = null
    ): array {
        
        Log::info('🧮 PricingCalculatorService - INICIANDO cálculos centralizados', [
            'items_count' => count($cartItems),
            'user_id' => $userId,
            'coupon_code' => $couponCode,
        ]);

        // PASO 1: Procesar items individuales con descuentos seller + volumen
        $processedItems = $this->processItemsWithDiscounts($cartItems);
        
        // PASO 2: Calcular subtotales básicos
        $subtotalData = $this->calculateSubtotals($processedItems);
        
        // PASO 3: Aplicar cupón de descuento si existe
        $couponData = $this->applyCouponDiscount($processedItems, $subtotalData, $userId, $couponCode);
        
        // PASO 4: Calcular envío con configuración dinámica de BD
        $shippingData = $this->calculateShipping($couponData['subtotal_after_coupon']);
        
        // PASO 5: Calcular IVA sobre (subtotal + envío)
        $taxData = $this->calculateTax($couponData['subtotal_after_coupon'], $shippingData['shipping_cost']);
        
        // PASO 6: Ensamblar resultado final
        $result = $this->assembleResult($processedItems, $subtotalData, $couponData, $shippingData, $taxData);

        Log::info('✅ PricingCalculatorService - CÁLCULOS COMPLETADOS', [
            'original_subtotal' => $result['subtotal_original'],
            'final_total' => $result['final_total'],
            'total_discounts' => $result['total_discounts'],
            'iva_amount' => $result['iva_amount'],
            'shipping_cost' => $result['shipping_cost'],
        ]);

        return $result;
    }

    /**
     * 🔧 PASO 1: Procesar items con descuentos seller + volumen dinámico
     */
    private function processItemsWithDiscounts(array $cartItems): array
    {
        $processedItems = [];
        
        foreach ($cartItems as $item) {
            $productId = $item['product_id'] ?? $item['productId'] ?? null;
            $quantity = $item['quantity'] ?? 0;
            
            if (!$productId || $quantity <= 0) {
                throw new \Exception("Item inválido: product_id={$productId}, quantity={$quantity}");
            }
            
            // Obtener producto de BD
            $product = $this->productRepository->findById($productId);
            if (!$product) {
                throw new \Exception("Producto {$productId} no encontrado");
            }
            
            // Calcular pricing completo del item
            $pricing = $this->calculateItemPricing(
                $product->getPrice(),
                $product->getDiscountPercentage() ?? 0,
                $quantity,
                $product->getSellerId()
            );
            
            $processedItems[] = [
                'product_id' => $productId,
                'seller_id' => $product->getSellerId(),
                'quantity' => $quantity,
                'original_price' => $product->getPrice(),
                'seller_discount_percentage' => $product->getDiscountPercentage() ?? 0,
                'seller_discounted_price' => $pricing['seller_discounted_price'],
                'volume_discount_percentage' => $pricing['volume_discount_percentage'],
                'final_price' => $pricing['final_price'],
                'seller_discount_amount' => $pricing['seller_discount_amount'],
                'volume_discount_amount' => $pricing['volume_discount_amount'],
                'total_discount_amount' => $pricing['total_discount_amount'],
                'subtotal' => $pricing['final_price'] * $quantity, // Sin redondeo - frontend manejará
            ];
        }
        
        return $processedItems;
    }

    /**
     * 🧮 Calcular pricing individual de un item con descuentos dinámicos
     */
    private function calculateItemPricing(
        float $originalPrice,
        float $sellerDiscountPercentage,
        int $quantity,
        int $sellerId
    ): array {
        
        // PASO 1: Aplicar descuento del seller
        $sellerDiscountAmount = $originalPrice * ($sellerDiscountPercentage / 100);
        $sellerDiscountedPrice = $originalPrice - $sellerDiscountAmount;

        // PASO 2: Obtener descuento por volumen dinámico desde BD
        $volumeDiscountPercentage = $this->getVolumeDiscountPercentageFromDB($quantity);
        $volumeDiscountAmount = $sellerDiscountedPrice * $volumeDiscountPercentage; // ✅ CORREGIDO: Ya viene como decimal

        // PASO 3: Precio final
        $finalPrice = $sellerDiscountedPrice - $volumeDiscountAmount;

        // PASO 4: Total de descuentos
        $totalDiscountAmount = $sellerDiscountAmount + $volumeDiscountAmount;

        return [
            'seller_discounted_price' => $sellerDiscountedPrice, // Sin redondeo - frontend manejará
            'volume_discount_percentage' => $volumeDiscountPercentage,
            'final_price' => $finalPrice, // Sin redondeo - frontend manejará
            'seller_discount_amount' => $sellerDiscountAmount, // Sin redondeo - frontend manejará
            'volume_discount_amount' => $volumeDiscountAmount, // Sin redondeo - frontend manejará  
            'total_discount_amount' => $totalDiscountAmount, // Sin redondeo - frontend manejará
        ];
    }

    /**
     * 🎯 CORREGIDO: Obtener descuento por volumen desde BD (dinámico)
     */
    private function getVolumeDiscountPercentageFromDB(int $quantity): float
    {
        // ✅ COMPLETAMENTE DINÁMICO: Verificar que esté habilitado desde BD
        $enabled = $this->configService->getConfig('volume_discounts.enabled');
        
        if ($enabled === null) {
            throw new \Exception('Configuración volume_discounts.enabled requerida en BD');
        }
        
        if (!$enabled) {
            return 0.0;
        }
        
        // ✅ COMPLETAMENTE DINÁMICO: Obtener tiers SOLO desde BD, sin fallback hardcoded
        $defaultTiers = $this->configService->getConfig('volume_discounts.default_tiers');
        
        // 🔧 CORREGIDO: Verificar si ya es array o si es string JSON
        if (is_array($defaultTiers)) {
            $tiers = $defaultTiers;
        } elseif (is_string($defaultTiers)) {
            $tiers = json_decode($defaultTiers, true);
        } else {
            $tiers = null;
        }
        
        if (!is_array($tiers) || empty($tiers)) {
            Log::error('❌ Volume discount tiers no disponibles en BD - Sistema requiere configuración válida', [
                'tiers' => $defaultTiers, 
                'type' => gettype($defaultTiers)
            ]);
            throw new \Exception('Sistema requiere configuración válida de descuentos por volumen en BD');
        }
        
        // Ordenar tiers de menor a mayor cantidad para aplicar el tier más alto disponible
        usort($tiers, function($a, $b) {
            return ($a['quantity'] ?? 0) - ($b['quantity'] ?? 0);
        });
        
        // Encontrar el tier aplicable (el más alto que califica)
        $applicableTier = null;
        foreach ($tiers as $tier) {
            if ($quantity >= ($tier['quantity'] ?? 0)) {
                $applicableTier = $tier;
            }
        }
        
        if ($applicableTier) {
            return (float) ($applicableTier['discount'] ?? 0) / 100; // ✅ CORREGIDO: Convertir porcentaje a decimal
        }
        
        return 0.0;
    }

    // ❌ ELIMINADO: No más fallbacks hardcoded - Todo debe venir de BD

    /**
     * 📊 PASO 2: Calcular subtotales básicos
     */
    private function calculateSubtotals(array $processedItems): array
    {
        $subtotalOriginal = 0;
        $subtotalWithDiscounts = 0;
        $totalSellerDiscounts = 0;
        $totalVolumeDiscounts = 0;

        foreach ($processedItems as $item) {
            $itemOriginalTotal = $item['original_price'] * $item['quantity'];
            $itemDiscountedTotal = $item['final_price'] * $item['quantity'];
            $itemSellerDiscounts = $item['seller_discount_amount'] * $item['quantity'];
            $itemVolumeDiscounts = $item['volume_discount_amount'] * $item['quantity'];

            $subtotalOriginal += $itemOriginalTotal;
            $subtotalWithDiscounts += $itemDiscountedTotal;
            $totalSellerDiscounts += $itemSellerDiscounts;
            $totalVolumeDiscounts += $itemVolumeDiscounts;
        }

        return [
            'subtotal_original' => $subtotalOriginal, // Sin redondeo - frontend manejará
            'subtotal_with_discounts' => $subtotalWithDiscounts, // Sin redondeo - frontend manejará
            'seller_discounts' => $totalSellerDiscounts, // Sin redondeo - frontend manejará
            'volume_discounts' => $totalVolumeDiscounts, // Sin redondeo - frontend manejará
            'total_discounts' => $totalSellerDiscounts + $totalVolumeDiscounts, // Sin redondeo - frontend manejará
        ];
    }

    /**
     * 🎫 PASO 3: Aplicar cupón de descuento (opcional)
     */
    private function applyCouponDiscount(
        array $processedItems, 
        array $subtotalData, 
        int $userId, 
        ?string $couponCode
    ): array {
        
        if (!$couponCode) {
            return [
                'subtotal_after_coupon' => $subtotalData['subtotal_with_discounts'],
                'coupon_discount' => 0,
                'coupon_info' => null,
            ];
        }

        try {
            // Convertir items al formato esperado por ApplyCartDiscountCodeUseCase
            $cartItemsForCoupon = $this->convertItemsForCouponValidation($processedItems);
            
            $discountResult = $this->applyCartDiscountCodeUseCase->execute($couponCode, $cartItemsForCoupon, $userId);
            
            if ($discountResult['success']) {
                $discountInfo = $discountResult['data']['discount_code'];
                $discountAmount = $discountInfo['discount_amount'] ?? 0;
                
                return [
                    'subtotal_after_coupon' => $subtotalData['subtotal_with_discounts'] - $discountAmount, // Sin redondeo - frontend manejará
                    'coupon_discount' => $discountAmount, // Sin redondeo - frontend manejará
                    'coupon_info' => $discountInfo,
                ];
            } else {
                throw new \Exception('Cupón inválido: ' . ($discountResult['message'] ?? 'Error desconocido'));
            }
        } catch (\Exception $e) {
            Log::error('Error aplicando cupón de descuento', [
                'coupon_code' => $couponCode,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 🚚 PASO 4: Calcular envío con configuración dinámica de BD
     */
    private function calculateShipping(float $subtotal): array
    {
        // ✅ COMPLETAMENTE DINÁMICO: Sin valores por defecto hardcoded
        $enabled = $this->configService->getConfig('shipping.enabled');
        $freeThreshold = $this->configService->getConfig('shipping.free_threshold');
        $defaultCost = $this->configService->getConfig('shipping.default_cost');
        
        // Validar que la configuración existe
        if ($enabled === null || $freeThreshold === null || $defaultCost === null) {
            throw new \Exception('Configuración de envío requerida no encontrada en BD');
        }

        if (!$enabled) {
            return [
                'shipping_cost' => 0,
                'free_shipping' => true,
                'free_shipping_threshold' => 0,
            ];
        }

        $freeShipping = $subtotal >= $freeThreshold;
        $shippingCost = $freeShipping ? 0 : $defaultCost;

        return [
            'shipping_cost' => $shippingCost, // Sin redondeo - frontend manejará
            'free_shipping' => $freeShipping,
            'free_shipping_threshold' => $freeThreshold,
        ];
    }

    /**
     * 🏷️ PASO 5: Calcular IVA dinámico desde configuración sobre (subtotal + envío)
     */
    private function calculateTax(float $subtotal, float $shippingCost): array
    {
        // ✅ COMPLETAMENTE DINÁMICO: Con fallback seguro 15% para Ecuador
        $taxRatePercentage = $this->configService->getConfig('payment.taxRate', 15.0);
        
        // Log para debug en caso de problemas
        \Log::info('PricingCalculatorService: Tax rate obtenido', [
            'tax_rate_percentage' => $taxRatePercentage,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost
        ]);
        
        $taxRate = $taxRatePercentage / 100; // Convertir % a decimal
        
        $taxableAmount = $subtotal + $shippingCost;
        $taxAmount = $taxableAmount * $taxRate;

        return [
            'taxable_amount' => $taxableAmount, // Sin redondeo - frontend manejará
            'tax_amount' => $taxAmount, // Sin redondeo - frontend manejará
            'tax_rate' => $taxRatePercentage,
        ];
    }

    /**
     * 🔧 PASO 6: Ensamblar resultado final
     */
    private function assembleResult(
        array $processedItems,
        array $subtotalData,
        array $couponData,
        array $shippingData,
        array $taxData
    ): array {
        
        $finalTotal = $couponData['subtotal_after_coupon'] + $shippingData['shipping_cost'] + $taxData['tax_amount']; // Sin redondeo - frontend manejará
        $totalDiscounts = $subtotalData['total_discounts'] + $couponData['coupon_discount']; // Sin redondeo - frontend manejará

        return [
            // Items procesados
            'processed_items' => $processedItems,
            
            // Subtotales
            'subtotal_original' => $subtotalData['subtotal_original'],
            'subtotal_with_discounts' => $subtotalData['subtotal_with_discounts'],
            'subtotal_after_coupon' => $couponData['subtotal_after_coupon'],
            
            // Descuentos desglosados
            'seller_discounts' => $subtotalData['seller_discounts'],
            'volume_discounts' => $subtotalData['volume_discounts'],
            'coupon_discount' => $couponData['coupon_discount'],
            'total_discounts' => $totalDiscounts, // Sin redondeo - frontend manejará
            
            // Envío e IVA
            'shipping_cost' => $shippingData['shipping_cost'],
            'free_shipping' => $shippingData['free_shipping'],
            'free_shipping_threshold' => $shippingData['free_shipping_threshold'],
            'iva_amount' => $taxData['tax_amount'],
            'tax_rate' => $taxData['tax_rate'],
            
            // Total final
            'final_total' => round($finalTotal, 2),
            
            // Información adicional
            'coupon_info' => $couponData['coupon_info'],
            'volume_discounts_applied' => $subtotalData['volume_discounts'] > 0,
            
            // Para compatibilidad con ProcessCheckoutUseCase
            'pricing_breakdown' => [
                'subtotal' => $couponData['subtotal_after_coupon'],
                'subtotal_final' => $taxData['taxable_amount'],
                'tax' => $taxData['tax_amount'],
                'shipping' => $shippingData['shipping_cost'],
                'total' => round($finalTotal, 2),
                'final_total' => round($finalTotal, 2),
                'subtotal_original' => $subtotalData['subtotal_original'],
                'seller_discounts' => $subtotalData['seller_discounts'],
                'volume_discounts' => $subtotalData['volume_discounts'],
                'total_discounts' => $totalDiscounts, // Sin redondeo - frontend manejará
                'free_shipping' => $shippingData['free_shipping'],
                'free_shipping_threshold' => $shippingData['free_shipping_threshold'],
                'tax_rate' => $taxData['tax_rate'],
            ],
        ];
    }

    /**
     * Convertir items para validación de cupones
     */
    private function convertItemsForCouponValidation(array $processedItems): array
    {
        $cartItems = [];
        foreach ($processedItems as $item) {
            $cartItems[] = [
                'product_id' => $item['product_id'],
                'seller_id' => $item['seller_id'],
                'quantity' => $item['quantity'],
                'price' => $item['final_price'],
                'base_price' => $item['original_price'],
                'discount_percentage' => $item['seller_discount_percentage'],
                'attributes' => [],
            ];
        }
        return $cartItems;
    }
}