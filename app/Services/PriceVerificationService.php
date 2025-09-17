<?php

namespace App\Services;

use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Services\PricingCalculatorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PriceVerificationService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private PricingCalculatorService $pricingService
    ) {}

    /**
     * Verifica que los precios del frontend coincidan con los calculados en el servidor
     * INCLUYE TODOS LOS TIPOS DE DESCUENTOS: seller, volumen, cupones, feedback
     */
    public function verifyItemPrices(array $items, int $userId, ?string $couponCode = null): bool
    {
        try {
            Log::info('🔍 SECURITY: Iniciando verificación de precios COMPLETA', [
                'items_count' => count($items),
                'user_id' => $userId,
                'has_coupon' => ! empty($couponCode),
            ]);

            // OPCIÓN 1: Verificar items individualmente (para casos sin cupones)
            if (empty($couponCode)) {
                foreach ($items as $index => $item) {
                    if (! $this->verifyItemPrice($item, $userId, $index)) {
                        return false;
                    }
                }
            } else {
                // OPCIÓN 2: Verificar carrito completo cuando hay cupones
                // porque los cupones afectan el cálculo total
                return $this->verifyCompleteCart($items, $userId, $couponCode);
            }

            Log::info('✅ SECURITY: Verificación de precios completada exitosamente');

            return true;

        } catch (\Exception $e) {
            Log::error('❌ SECURITY: Error verificando precios', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'items_count' => count($items),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Verifica el carrito completo cuando hay cupones o códigos de descuento
     */
    private function verifyCompleteCart(array $items, int $userId, ?string $couponCode = null): bool
    {
        try {
            // Calcular totales del servidor con TODOS los descuentos
            $serverTotals = $this->pricingService->calculateCartTotals($items, $userId, $couponCode);

            // Calcular total del cliente (suma de precios individuales)
            $clientTotal = 0;
            foreach ($items as $item) {
                $clientTotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
            }

            $serverItemsTotal = 0;
            if (isset($serverTotals['processed_items'])) {
                foreach ($serverTotals['processed_items'] as $serverItem) {
                    // CORRECCIÓN: Usar las keys correctas del PricingCalculatorService
                    $unitPrice = $serverItem['final_price'] ?? 0;
                    $quantity = $serverItem['quantity'] ?? 1;
                    $serverItemsTotal += $unitPrice * $quantity;
                }
            }

            // VALIDACIÓN ESTRICTA: CERO TOLERANCIA en totales
            $tolerance = 0.001; // Tolerancia mínima solo para redondeo de punto flotante
            $difference = abs($serverItemsTotal - $clientTotal);

            if ($difference > $tolerance) {
                Log::warning('🚨 SECURITY: Cart total tampering detected with coupon', [
                    'client_total' => $clientTotal,
                    'server_total' => $serverItemsTotal,
                    'difference' => $difference,
                    'coupon_code' => $couponCode,
                    'user_id' => $userId,
                ]);

                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('❌ SECURITY: Error verificando carrito completo', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'coupon_code' => $couponCode,
            ]);

            return false;
        }
    }

    /**
     * Verifica un item individual usando el PricingCalculatorService completo
     */
    private function verifyItemPrice(array $item, int $userId, int $index): bool
    {
        $productId = $item['product_id'] ?? null;
        $clientPrice = $item['price'] ?? 0;
        $quantity = $item['quantity'] ?? 1;

        if (! $productId) {
            Log::warning('🚨 SECURITY: Product ID missing', ['item_index' => $index]);

            return false;
        }

        // Obtener producto desde BD
        $product = $this->productRepository->findById($productId);
        if (! $product) {
            Log::warning('🚨 SECURITY: Product not found', [
                'product_id' => $productId,
                'item_index' => $index,
            ]);

            return false;
        }

        // IMPORTANTE: Usar PricingCalculatorService para cálculo completo con TODOS los descuentos
        // Crear array temporal solo para este item para obtener el precio correcto
        $sellerId = $item['seller_id'] ?? null;

        // Si no tenemos seller_id del item, obtenerlo del producto
        if (! $sellerId) {
            if (method_exists($product, 'getSellerId')) {
                $sellerId = $product->getSellerId();
            } elseif (isset($product->seller_id)) {
                $sellerId = $product->seller_id;
            } else {
                // Como último recurso, obtener desde BD
                $productFromDB = DB::table('products')->where('id', $productId)->first();
                $sellerId = $productFromDB->seller_id ?? 1; // Default seller si no existe
            }
        }

        $singleItemArray = [
            [
                'product_id' => $productId,
                'quantity' => $quantity,
                'seller_id' => (int) $sellerId,
                'price' => $product->getPrice(), // Agregar precio base del producto
                'subtotal' => $product->getPrice() * $quantity,
            ],
        ];

        // Calcular precio servidor usando el servicio completo (incluye descuentos volumen, seller, etc.)
        $serverCalculation = $this->pricingService->calculateCartTotals($singleItemArray, $userId);

        // El precio unitario correcto está en el cálculo del servicio
        $serverPrice = 0;
        if (isset($serverCalculation['processed_items']) && count($serverCalculation['processed_items']) > 0) {
            $calculatedItem = $serverCalculation['processed_items'][0];
            // CORRECCIÓN: Usar la key correcta que devuelve PricingCalculatorService
            $serverPrice = $calculatedItem['final_price'] ?? 0;
        }

        // Si no se puede calcular desde el servicio, usar cálculo básico como fallback
        if ($serverPrice <= 0) {
            $serverPrice = $this->calculateServerPrice($product, $quantity);
        }

        // VALIDACIÓN ESTRICTA: Tolerancia mínima para precios individuales
        $priceDifference = abs($serverPrice - $clientPrice);

        if ($priceDifference > 0.001) {
            Log::warning('🚨 SECURITY: Price tampering detected', [
                'product_id' => $productId,
                'product_name' => $product->getName(),
                'client_price' => $clientPrice,
                'server_price' => $serverPrice,
                'difference' => $priceDifference,
                'user_id' => $userId,
                'quantity' => $quantity,
                'item_index' => $index,
                'calculation_method' => $serverPrice > 0 ? 'PricingCalculatorService' : 'fallback',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Calcula el precio correcto desde el servidor
     */
    private function calculateServerPrice($product, int $quantity): float
    {
        $basePrice = $product->getPrice();
        $discountPercentage = $product->getDiscountPercentage();

        // Aplicar descuento del vendedor
        $discountAmount = $basePrice * ($discountPercentage / 100);
        $finalPrice = $basePrice - $discountAmount;

        return round($finalPrice, 2);
    }

    /**
     * Verifica totales calculados
     */
    public function verifyCalculatedTotals(array $items, array $clientTotals, int $userId, ?string $couponCode = null): bool
    {
        try {
            // Usar el servicio centralizado para calcular
            $serverTotals = $this->pricingService->calculateCartTotals($items, $userId, $couponCode);

            $tolerance = 0.001; // VALIDACIÓN ESTRICTA: Solo tolerancia de punto flotante

            $checks = [
                'final_total' => $serverTotals['final_total'] ?? 0,
                'subtotal_with_discounts' => $serverTotals['subtotal_with_discounts'] ?? 0,
                'iva_amount' => $serverTotals['iva_amount'] ?? 0,
                'shipping_cost' => $serverTotals['shipping_cost'] ?? 0,
            ];

            foreach ($checks as $field => $serverValue) {
                $clientValue = $clientTotals[$field] ?? 0;
                $difference = abs($serverValue - $clientValue);

                if ($difference > $tolerance) {
                    Log::warning('🚨 SECURITY: Total tampering detected', [
                        'field' => $field,
                        'client_value' => $clientValue,
                        'server_value' => $serverValue,
                        'difference' => $difference,
                        'user_id' => $userId,
                        'ip_address' => request()->ip(),
                    ]);

                    return false;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('❌ SECURITY: Error verificando totales', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return false;
        }
    }
}
