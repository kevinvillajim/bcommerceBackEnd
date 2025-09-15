<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderItemBreakdownController extends Controller
{
    /**
     * Obtener el desglose detallado de descuentos para los items de una orden
     */
    public function getOrderItemsBreakdown(int $orderId): JsonResponse
    {
        $userId = Auth::id();

        // Verificar que la orden existe y pertenece al usuario
        $order = Order::find($orderId);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada',
            ], 404);
        }

        if ($order->user_id != $userId) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        // Obtener todos los items de la orden con el producto completo
        // Incluir también los items de seller_orders si existen
        $orderItems = OrderItem::where('order_id', $orderId)
            ->orWhereHas('sellerOrder', function ($query) use ($orderId) {
                $query->where('order_id', $orderId);
            })
            ->with(['product' => function ($query) {
                $query->select('id', 'name', 'price', 'discount_percentage', 'images');
            }])
            ->get();

        $itemsWithBreakdown = [];

        // Obtener datos del pricing_breakdown que ya tiene todos los cálculos correctos
        $pricingBreakdown = [];
        if ($order->pricing_breakdown) {
            $pricingBreakdown = is_string($order->pricing_breakdown)
                ? json_decode($order->pricing_breakdown, true)
                : $order->pricing_breakdown;
        }

        // Verificar si se usó cupón
        $couponPercentage = 0;
        if (isset($pricingBreakdown['feedback_discount']) && $pricingBreakdown['feedback_discount'] > 0) {
            // Calcular porcentaje del cupón desde los datos reales
            $subtotalBeforeCoupon = $pricingBreakdown['subtotal_with_discounts'] ?? 0;
            if ($subtotalBeforeCoupon > 0) {
                $couponPercentage = round(($pricingBreakdown['feedback_discount'] / $subtotalBeforeCoupon) * 100, 2);
            }
        }

        foreach ($orderItems as $item) {
            $product = $item->product;

            if (! $product) {
                continue;
            }

            // Obtener el precio original REAL del producto desde la BD
            $originalPrice = floatval($product->price);
            $quantity = $item->quantity;
            $sellerDiscountPercentage = floatval($product->discount_percentage ?? 0);

            // ✅ CALCULAR PRECIOS PASO A PASO (mismo flujo que PricingCalculatorService)

            // Paso 1: Precio después de descuento seller
            $sellerDiscountAmount = $originalPrice * ($sellerDiscountPercentage / 100);
            $priceAfterSeller = $originalPrice - $sellerDiscountAmount;

            // Paso 2: Descuento por volumen (usando misma lógica que backend)
            $volumeDiscountPercentage = $this->getVolumeDiscountForQuantity($quantity);
            $volumeDiscountAmount = $priceAfterSeller * $volumeDiscountPercentage;
            $finalPricePerUnit = $priceAfterSeller - $volumeDiscountAmount;

            // Calcular subtotal y ahorros
            $subtotalAfterDiscounts = $finalPricePerUnit * $quantity;
            $totalVolumeDiscount = $volumeDiscountAmount * $quantity;
            $totalSellerDiscount = $sellerDiscountAmount * $quantity;
            $totalSavings = $totalSellerDiscount + $totalVolumeDiscount;

            $itemsWithBreakdown[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product->name,
                'product_image' => $product->images[0]['thumbnail'] ?? null,
                'quantity' => $quantity,
                // ✅ CAMPOS QUE NECESITA EL FRONTEND
                'original_unit_price' => round($originalPrice, 2),
                'unit_price' => round($finalPricePerUnit, 2),
                'total_price' => round($subtotalAfterDiscounts, 2),
                'total_savings' => round($totalSavings, 2),
                'seller_discount_percentage' => $sellerDiscountPercentage,
                'volume_discount_percentage' => $volumeDiscountPercentage * 100, // Como porcentaje para display
                'breakdown_steps' => [
                    [
                        'step' => 1,
                        'label' => 'Precio original',
                        'price_per_unit' => round($originalPrice, 2),
                        'percentage' => 0,
                        'is_discount' => false,
                    ],
                    [
                        'step' => 2,
                        'label' => "Descuento del seller ({$sellerDiscountPercentage}%)",
                        'price_per_unit' => round($priceAfterSeller, 2),
                        'percentage' => $sellerDiscountPercentage,
                        'is_discount' => true,
                    ],
                    [
                        'step' => 3,
                        'label' => 'Descuento por volumen ('.round($volumeDiscountPercentage * 100, 1).'%)',
                        'price_per_unit' => round($finalPricePerUnit, 2),
                        'percentage' => $volumeDiscountPercentage * 100,
                        'is_discount' => true,
                    ],
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'items' => $itemsWithBreakdown,
                'has_coupon' => false,
                'coupon_percentage' => 0,
            ],
        ]);
    }

    /**
     * ✅ NUEVO: Obtener descuento por volumen usando configuración dinámica
     * Misma lógica que PricingCalculatorService para garantizar consistencia
     */
    private function getVolumeDiscountForQuantity(int $quantity): float
    {
        $configService = app()->make('App\Services\ConfigurationService');

        // Verificar si está habilitado
        $enabled = $configService->getConfig('volume_discounts.enabled');
        if (! $enabled) {
            return 0.0;
        }

        // Obtener tiers dinámicos
        $defaultTiers = $configService->getConfig('volume_discounts.default_tiers');

        if (is_array($defaultTiers)) {
            $tiers = $defaultTiers;
        } elseif (is_string($defaultTiers)) {
            $tiers = json_decode($defaultTiers, true);
        } else {
            return 0.0;
        }

        if (! is_array($tiers) || empty($tiers)) {
            return 0.0;
        }

        // Ordenar tiers de menor a mayor cantidad
        usort($tiers, function ($a, $b) {
            return ($a['quantity'] ?? 0) - ($b['quantity'] ?? 0);
        });

        // Encontrar el tier más alto que califica
        $applicableTier = null;
        foreach ($tiers as $tier) {
            if ($quantity >= ($tier['quantity'] ?? 0)) {
                $applicableTier = $tier;
            }
        }

        if ($applicableTier) {
            return (float) ($applicableTier['discount'] ?? 0) / 100; // Convertir porcentaje a decimal
        }

        return 0.0;
    }
}
