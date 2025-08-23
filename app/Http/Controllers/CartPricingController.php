<?php

namespace App\Http\Controllers;

use App\Domain\Formatters\ProductFormatter;
use App\Services\PricingService;
use App\UseCases\Cart\GetCartUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ✅ CORREGIDO: CartPricingController que USA el sistema de descuentos por volumen ya implementado
 * UBICACIÓN: app/Http/Controllers/CartPricingController.php
 */
class CartPricingController extends Controller
{
    private GetCartUseCase $getCartUseCase;

    private PricingService $pricingService;

    private ProductFormatter $productFormatter;

    public function __construct(
        GetCartUseCase $getCartUseCase,
        PricingService $pricingService,
        ProductFormatter $productFormatter
    ) {
        $this->getCartUseCase = $getCartUseCase;
        $this->pricingService = $pricingService;
        $this->productFormatter = $productFormatter;

        $this->middleware('jwt.auth');
    }

    /**
     * ✅ ENDPOINT PRINCIPAL: Obtener carrito con precios calculados usando el sistema existente
     * RUTA: GET /api/cart/with-pricing
     */
    public function getCartWithPricing(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            Log::info('🛒 CartPricingController: Obteniendo carrito con pricing existente', [
                'user_id' => $user->id,
            ]);

            $result = $this->getCartUseCase->execute($user->id);
            $cart = $result['cart'];

            if (! $cart || count($cart->getItems()) === 0) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'id' => $cart ? $cart->getId() : null,
                        'total' => 0,
                        'items' => [],
                        'item_count' => 0,
                        'pricing_info' => [
                            'subtotal_products' => 0,
                            'iva_amount' => 0,
                            'shipping_cost' => 0,
                            'total_discounts' => 0,
                            'volume_discount_savings' => 0,
                            'volume_discounts_applied' => false,
                            'free_shipping' => false,
                            'breakdown' => [],
                        ],
                    ],
                ]);
            }

            // ✅ USAR EL SISTEMA EXISTENTE: Preparar items para PricingService
            $cartItems = $this->prepareCartItemsForPricingService($cart->getItems());

            // ✅ USAR PricingService EXISTENTE que ya maneja descuentos por volumen
            $pricingResult = $this->pricingService->calculateCheckoutTotals($cartItems);
            $totals = $pricingResult['totals'];
            $processedItems = $pricingResult['processed_items'];

            // ✅ Formatear items con información completa de descuentos
            $formattedItems = $this->formatItemsWithVolumeDiscounts($cart->getItems(), $processedItems);

            Log::info('✅ CartPricingController: Precios calculados con descuentos por volumen', [
                'user_id' => $user->id,
                'items_count' => count($formattedItems),
                'subtotal' => $totals['subtotal_products'],
                'total_with_iva' => $totals['final_total'],
                'volume_savings' => $totals['total_volume_discount'],
                'volume_discounts_applied' => $totals['volume_discounts_applied'],
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $cart->getId(),
                    'total' => $totals['final_total'],
                    'items' => $formattedItems,
                    'item_count' => count($formattedItems),
                    'pricing_info' => [
                        'subtotal_products' => $totals['subtotal_products'],
                        'iva_amount' => $totals['iva_amount'],
                        'shipping_cost' => $totals['shipping_cost'],
                        'total_discounts' => $totals['total_discounts'],
                        'volume_discount_savings' => $totals['total_volume_discount'],
                        'volume_discounts_applied' => $totals['volume_discounts_applied'],
                        'free_shipping' => $totals['shipping_info']['free_shipping'],
                        'breakdown' => $pricingResult['breakdown'],
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error en CartPricingController: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al calcular precios del carrito: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ MÉTODO PRIVADO: Preparar items del carrito para PricingService (formato correcto)
     */
    private function prepareCartItemsForPricingService(array $cartItems): array
    {
        $preparedItems = [];

        foreach ($cartItems as $item) {
            $product = $this->productFormatter->formatBasic($item->getProductId());

            $preparedItems[] = [
                'product_id' => $item->getProductId(),
                'seller_id' => $product['seller_id'] ?? null,
                'quantity' => $item->getQuantity(),
                'price' => $product['final_price'] ?? $product['price'] ?? $item->getPrice(),
                'base_price' => $product['price'] ?? $item->getPrice(),
                'discount_percentage' => $product['discount_percentage'] ?? 0,
                'attributes' => $item->getAttributes(),
            ];
        }

        return $preparedItems;
    }

    /**
     * ✅ MÉTODO PRIVADO: Formatear items con información completa de descuentos por volumen
     * Esta estructura es exactamente lo que espera el CartPage.tsx
     */
    private function formatItemsWithVolumeDiscounts(array $cartItems, array $processedItems): array
    {
        $formattedItems = [];

        foreach ($cartItems as $index => $item) {
            $product = $this->productFormatter->formatBasic($item->getProductId());
            $pricedItem = $processedItems[$index] ?? [];

            // ✅ ESTRUCTURA ESPECÍFICA CON DESCUENTOS POR VOLUMEN APLICADOS
            $formattedItems[] = [
                'id' => $item->getId(),
                'productId' => $item->getProductId(),
                'quantity' => $item->getQuantity(),
                'price' => $pricedItem['base_price'] ?? $item->getPrice(),
                'subtotal' => $pricedItem['final_subtotal'] ?? $item->getSubtotal(),
                'attributes' => $item->getAttributes(),

                // ✅ CAMPOS ESPECÍFICOS PARA CARTPAGE con descuentos por volumen
                'final_price' => $pricedItem['final_unit_price'] ?? $item->getPrice(),
                'original_price' => $pricedItem['base_price'] ?? $item->getPrice(),
                'volume_discount_percentage' => $pricedItem['volume_discount_percentage'] ?? 0,
                'volume_savings' => $pricedItem['volume_savings_total'] ?? 0,
                'discount_label' => $pricedItem['volume_discount_label'] ?? null,
                'discounted_price' => $pricedItem['final_unit_price'] ?? $item->getPrice(),
                'total_savings' => $pricedItem['volume_savings_total'] ?? 0,

                // ✅ INFORMACIÓN DEL PRODUCTO (estructura que espera CartPage)
                'product' => array_merge($product, [
                    'final_price' => $pricedItem['final_unit_price'] ?? $product['price'] ?? $item->getPrice(),
                    'has_volume_discounts' => ($pricedItem['volume_discount_percentage'] ?? 0) > 0,
                    'stockAvailable' => $product['stock'] ?? 0,
                    'stock' => $product['stock'] ?? 0,
                    'is_in_stock' => ($product['stock'] ?? 0) > 0,
                    'sellerId' => $product['seller_id'] ?? null,
                    'seller_id' => $product['seller_id'] ?? null,
                    'main_image' => $product['main_image'] ?? $product['image'] ?? null,
                ]),
            ];
        }

        return $formattedItems;
    }

    /**
     * ✅ ENDPOINT SECUNDARIO: Recalcular precios cuando se actualiza el carrito
     * RUTA: POST /api/cart/update-pricing
     */
    public function updateCartPricing(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            Log::info('🔄 CartPricingController: Recalculando precios del carrito', [
                'user_id' => $user->id,
            ]);

            // Esto simplemente vuelve a calcular y devolver el carrito actualizado
            return $this->getCartWithPricing($request);

        } catch (\Exception $e) {
            Log::error('❌ Error recalculando precios del carrito: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al recalcular precios del carrito',
            ], 500);
        }
    }

    /**
     * ✅ MÉTODO PÚBLICO: Obtener información detallada de descuentos por volumen de un producto
     * RUTA: GET /api/volume-discounts/product/{productId}
     */
    public function getProductVolumeDiscountInfo(Request $request, int $productId): JsonResponse
    {
        try {
            $product = \App\Models\Product::find($productId);
            if (! $product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado',
                ], 404);
            }

            $currentQuantity = (int) $request->input('quantity', 1);
            $basePrice = $product->final_price ?? $product->price;

            // ✅ USAR SISTEMA EXISTENTE: VolumeDiscount para obtener información
            $availableDiscounts = \App\Models\VolumeDiscount::getDiscountTiers($productId);
            $applicableDiscount = \App\Models\VolumeDiscount::getDiscountForQuantity($productId, $currentQuantity);

            // Calcular pricing actual usando el sistema existente
            $pricingInfo = \App\Models\VolumeDiscount::calculateVolumePrice($productId, $basePrice, $currentQuantity);

            // Agregar información de precios a cada tier usando PricingService
            $tiersWithPricing = [];
            foreach ($availableDiscounts as $tier) {
                $tierPricing = \App\Models\VolumeDiscount::calculateVolumePrice($productId, $basePrice, $tier['quantity']);

                $tiersWithPricing[] = [
                    'quantity' => $tier['quantity'],
                    'discount' => $tier['discount'],
                    'label' => $tier['label'],
                    'price_per_unit' => $tierPricing['discounted_price'],
                    'total_price' => $tierPricing['discounted_price'] * $tier['quantity'],
                    'savings_per_unit' => $tierPricing['savings'],
                    'total_savings' => $tierPricing['savings'] * $tier['quantity'],
                    'is_current' => $tier['quantity'] <= $currentQuantity,
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'enabled' => true,
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'base_price' => $product->price,
                        'final_price' => $basePrice,
                    ],
                    'current_quantity' => $currentQuantity,
                    'current_pricing' => $pricingInfo,
                    'tiers' => $tiersWithPricing,
                    'applicable_discount' => $applicableDiscount ? [
                        'id' => $applicableDiscount->id,
                        'min_quantity' => $applicableDiscount->min_quantity,
                        'discount_percentage' => $applicableDiscount->discount_percentage,
                        'label' => $applicableDiscount->label,
                    ] : null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error obteniendo info de descuentos: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener información de descuentos por volumen',
            ], 500);
        }
    }
}
