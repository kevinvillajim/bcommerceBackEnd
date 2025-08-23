<?php

namespace App\Http\Controllers;

use App\Domain\Formatters\ProductFormatter;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Services\ConfigurationService;
use App\Services\PricingService;
use App\UseCases\Cart\AddItemToCartUseCase;
use App\UseCases\Cart\ApplyCartDiscountCodeUseCase;
use App\UseCases\Cart\EmptyCartUseCase;
use App\UseCases\Cart\GetCartUseCase;
use App\UseCases\Cart\RemoveItemFromCartUseCase;
use App\UseCases\Cart\UpdateCartItemUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    private AddItemToCartUseCase $addItemToCartUseCase;

    private RemoveItemFromCartUseCase $removeItemFromCartUseCase;

    private UpdateCartItemUseCase $updateCartItemUseCase;

    private GetCartUseCase $getCartUseCase;

    private EmptyCartUseCase $emptyCartUseCase;

    private ApplyCartDiscountCodeUseCase $applyCartDiscountCodeUseCase;

    private ProductFormatter $productFormatter;

    private PricingService $pricingService;

    private ConfigurationService $configService;

    public function __construct(
        AddItemToCartUseCase $addItemToCartUseCase,
        RemoveItemFromCartUseCase $removeItemFromCartUseCase,
        UpdateCartItemUseCase $updateCartItemUseCase,
        GetCartUseCase $getCartUseCase,
        EmptyCartUseCase $emptyCartUseCase,
        ApplyCartDiscountCodeUseCase $applyCartDiscountCodeUseCase,
        ProductFormatter $productFormatter,
        PricingService $pricingService,
        ConfigurationService $configService
    ) {
        $this->addItemToCartUseCase = $addItemToCartUseCase;
        $this->removeItemFromCartUseCase = $removeItemFromCartUseCase;
        $this->updateCartItemUseCase = $updateCartItemUseCase;
        $this->getCartUseCase = $getCartUseCase;
        $this->emptyCartUseCase = $emptyCartUseCase;
        $this->applyCartDiscountCodeUseCase = $applyCartDiscountCodeUseCase;
        $this->productFormatter = $productFormatter;
        $this->pricingService = $pricingService;
        $this->configService = $configService;

        $this->middleware('jwt.auth');
    }

    /**
     * ✅ REFACTORIZADO: Mostrar carrito usando PricingService centralizado
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $result = $this->getCartUseCase->execute($user->id);
            $cart = $result['cart'];

            if (! $cart || count($cart->getItems()) === 0) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'id' => $cart ? $cart->getId() : null,
                        'total' => 0,
                        'subtotal' => 0,
                        'iva_amount' => 0,
                        'total_discounts' => 0,
                        'items' => [],
                        'item_count' => 0,
                        'breakdown' => [],
                    ],
                ]);
            }

            // ✅ Preparar items para PricingService
            $cartItems = $this->prepareCartItemsForPricing($cart->getItems());

            // ✅ Usar PricingService para cálculos centralizados
            $pricingResult = $this->pricingService->calculateCheckoutTotals($cartItems);
            $totals = $pricingResult['totals'];
            $breakdown = $pricingResult['breakdown'];

            // ✅ Formatear items con información de descuentos
            $formattedItems = $this->formatCartItemsWithPricing($cart->getItems(), $pricingResult['processed_items']);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $cart->getId(),
                    'total' => $totals['final_total'],
                    'subtotal' => $totals['subtotal_products'],
                    'iva_amount' => $totals['iva_amount'],
                    'total_discounts' => $totals['total_discounts'],
                    'volume_discount_savings' => $totals['total_volume_discount'],
                    'volume_discounts_applied' => $totals['volume_discounts_applied'],
                    'items' => $formattedItems,
                    'item_count' => count($formattedItems),
                    'breakdown' => $breakdown,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error obteniendo carrito: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Preparar items del carrito para PricingService
     */
    private function prepareCartItemsForPricing(array $cartItems): array
    {
        $preparedItems = [];

        foreach ($cartItems as $item) {
            $product = $this->productFormatter->formatBasic($item->getProductId());

            $preparedItems[] = [
                'product_id' => $item->getProductId(),
                'seller_id' => $product['seller_id'] ?? null,
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice(),
                'base_price' => $product['price'] ?? $item->getPrice(),
                'discount_percentage' => $product['discount_percentage'] ?? 0,
                'attributes' => $item->getAttributes(),
            ];
        }

        return $preparedItems;
    }

    /**
     * ✅ NUEVO: Formatear items con información de pricing
     */
    private function formatCartItemsWithPricing(array $cartItems, array $pricedItems): array
    {
        $formattedItems = [];

        foreach ($cartItems as $index => $item) {
            $product = $this->productFormatter->formatBasic($item->getProductId());
            $pricedItem = $pricedItems[$index] ?? [];

            $formattedItems[] = [
                'id' => $item->getId(),
                'productId' => $item->getProductId(), // Include productId for frontend compatibility
                'product' => $product,
                'quantity' => $item->getQuantity(),
                'base_price' => $pricedItem['base_price'] ?? $item->getPrice(),
                'seller_discounted_price' => $pricedItem['seller_discounted_price'] ?? $item->getPrice(),
                'final_unit_price' => $pricedItem['final_unit_price'] ?? $item->getPrice(),
                'subtotal' => $pricedItem['subtotal'] ?? $item->getSubtotal(),
                'attributes' => $item->getAttributes(),

                // ✅ Información detallada de descuentos
                'pricing_info' => [
                    'seller_discount' => $pricedItem['seller_discount'] ?? 0,
                    'volume_discount_percentage' => $pricedItem['volume_discount'] ?? 0,
                    'volume_savings' => $pricedItem['volume_savings'] ?? 0,
                    'total_savings' => $pricedItem['total_savings'] ?? 0,
                    'volume_discount_label' => $pricedItem['volume_discount_label'] ?? null,
                ],
            ];
        }

        return $formattedItems;
    }

    /**
     * ✅ ACTUALIZADO: Obtener totales de checkout (con envío)
     */
    public function getCheckoutTotals(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = $request->user();
            if (! $user) {
                return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado'], 401);
            }

            $result = $this->getCartUseCase->execute($user->id);
            $cart = $result['cart'];

            if (! $cart || count($cart->getItems()) === 0) {
                return response()->json([
                    'status' => 'success',
                    'data' => ['total' => 0, 'breakdown' => []],
                ]);
            }

            // Preparar items y calcular totales completos con envío
            $cartItems = $this->prepareCartItemsForPricing($cart->getItems());
            $checkoutResult = $this->pricingService->calculateCheckoutTotals($cartItems);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'totals' => $checkoutResult['totals'],
                    'breakdown' => $checkoutResult['breakdown'],
                    'shipping_info' => $checkoutResult['totals']['shipping_info'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error calculando totales de checkout: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ ACTUALIZADO: Información de descuentos por volumen
     */
    public function getVolumeDiscountInfo(Request $request, int $productId): JsonResponse
    {
        try {
            $volumeDiscountsEnabled = $this->configService->getConfig('volume_discounts.enabled', true);

            if (! $volumeDiscountsEnabled) {
                return response()->json([
                    'status' => 'success',
                    'data' => ['enabled' => false, 'tiers' => []],
                ]);
            }

            $product = \App\Models\Product::find($productId);
            if (! $product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado',
                ], 404);
            }

            /** @var int $currentQuantity */
            $currentQuantity = (int) $request->input('quantity', 1);
            $basePrice = $product->final_price ?? $product->price;

            // Usar PricingService para obtener información de descuentos
            $cartItems = [[
                'product_id' => $productId,
                'seller_id' => $product->seller_id,
                'quantity' => $currentQuantity,
                'price' => $basePrice,
                'base_price' => $product->price,
                'discount_percentage' => $product->discount_percentage ?? 0,
            ]];

            $pricingResult = $this->pricingService->calculateCartTotals($cartItems);

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
                    'pricing_info' => $pricingResult,
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

    // ✅ MÉTODOS EXISTENTES SIN CAMBIOS
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $result = $this->addItemToCartUseCase->execute(
                $user->id,
                $request->input('product_id'),
                $request->input('quantity'),
                $request->input('attributes', [])
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Producto añadido al carrito',
                'data' => [
                    'cart_id' => $result['cart']->getId(),
                    'item_id' => $result['item']->getId(),
                    'item_count' => count($result['cart']->getItems()),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function removeItem(Request $request, $itemId): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $result = $this->removeItemFromCartUseCase->execute($user->id, (int) $itemId);

            return response()->json([
                'status' => 'success',
                'message' => 'Producto eliminado del carrito',
                'data' => [
                    'cart_id' => $result['cart']->getId(),
                    'item_count' => count($result['cart']->getItems()),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function updateItem(UpdateCartItemRequest $request, $itemId): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $result = $this->updateCartItemUseCase->execute(
                $user->id,
                (int) $itemId,
                $request->input('quantity')
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Carrito actualizado',
                'data' => [
                    'cart_id' => $result['cart']->getId(),
                    'item_count' => count($result['cart']->getItems()),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function empty(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $result = $this->emptyCartUseCase->execute($user->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Carrito vaciado',
                'data' => [
                    'cart_id' => $result['cart']->getId(),
                    'total' => 0,
                    'item_count' => 0,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validate a discount code for the cart
     */
    public function validateDiscountCode(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $code = $request->input('code');
            if (! $code) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Código de descuento requerido',
                ], 400);
            }

            // Get user's cart
            $result = $this->getCartUseCase->execute($user->id);
            $cart = $result['cart'];

            if (! $cart || count($cart->getItems()) === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El carrito está vacío',
                ], 400);
            }

            // Prepare cart items for validation
            $cartItems = $this->prepareCartItemsForPricing($cart->getItems());

            // Validate discount code
            $validationResult = $this->applyCartDiscountCodeUseCase->validateOnly($code, $cartItems, $user->id);

            return response()->json([
                'status' => $validationResult['success'] ? 'success' : 'error',
                'message' => $validationResult['message'],
                'data' => $validationResult['data'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating discount code: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al validar el código de descuento',
            ], 500);
        }
    }

    /**
     * Apply a discount code to the cart and get updated totals
     */
    public function applyDiscountCode(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $code = $request->input('code');
            if (! $code) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Código de descuento requerido',
                ], 400);
            }

            // Get user's cart
            $result = $this->getCartUseCase->execute($user->id);
            $cart = $result['cart'];

            if (! $cart || count($cart->getItems()) === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El carrito está vacío',
                ], 400);
            }

            // Prepare cart items for discount calculation
            $cartItems = $this->prepareCartItemsForPricing($cart->getItems());

            // Apply discount code
            $applyResult = $this->applyCartDiscountCodeUseCase->execute($code, $cartItems, $user->id);

            if (! $applyResult['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $applyResult['message'],
                ], 400);
            }

            // Format cart items for response
            $formattedItems = $this->formatCartItemsWithPricing($cart->getItems(), $applyResult['data']['processed_items']);

            return response()->json([
                'status' => 'success',
                'message' => $applyResult['message'],
                'data' => [
                    'cart' => [
                        'id' => $cart->getId(),
                        'total' => $applyResult['data']['totals']['final_total'],
                        'subtotal' => $applyResult['data']['totals']['subtotal_products'],
                        'iva_amount' => $applyResult['data']['totals']['iva_amount'],
                        'shipping_cost' => $applyResult['data']['totals']['shipping_cost'],
                        'total_discounts' => $applyResult['data']['totals']['total_discounts'],
                        'feedback_discount_amount' => $applyResult['data']['totals']['feedback_discount_amount'],
                        'feedback_discount_percentage' => $applyResult['data']['totals']['feedback_discount_percentage'],
                        'items' => $formattedItems,
                        'item_count' => count($formattedItems),
                        'breakdown' => $applyResult['data']['breakdown'],
                    ],
                    'discount_code' => $applyResult['data']['discount_code'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error applying discount code to cart: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al aplicar el código de descuento',
            ], 500);
        }
    }

    /**
     * Remove applied discount code from cart calculations
     */
    public function removeDiscountCode(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            // Get user's cart and return normal totals
            $result = $this->getCartUseCase->execute($user->id);
            $cart = $result['cart'];

            if (! $cart || count($cart->getItems()) === 0) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Código de descuento removido',
                    'data' => [
                        'total' => 0,
                        'breakdown' => [],
                    ],
                ]);
            }

            // Prepare items and calculate normal totals
            $cartItems = $this->prepareCartItemsForPricing($cart->getItems());
            $pricingResult = $this->pricingService->calculateCheckoutTotals($cartItems);
            $formattedItems = $this->formatCartItemsWithPricing($cart->getItems(), $pricingResult['processed_items']);

            return response()->json([
                'status' => 'success',
                'message' => 'Código de descuento removido',
                'data' => [
                    'cart' => [
                        'id' => $cart->getId(),
                        'total' => $pricingResult['totals']['final_total'],
                        'subtotal' => $pricingResult['totals']['subtotal_products'],
                        'iva_amount' => $pricingResult['totals']['iva_amount'],
                        'shipping_cost' => $pricingResult['totals']['shipping_cost'],
                        'total_discounts' => $pricingResult['totals']['total_discounts'],
                        'items' => $formattedItems,
                        'item_count' => count($formattedItems),
                        'breakdown' => $pricingResult['breakdown'],
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error removing discount code from cart: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al remover el código de descuento',
            ], 500);
        }
    }
}
