<?php

namespace App\Http\Controllers;

use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\SellerOrderRepositoryInterface;
use App\Events\OrderCompleted;
use App\Events\OrderStatusChanged;
use App\Events\ShippingStatusUpdated;
use App\Models\Seller;
use App\Models\Shipping;
use App\Models\User;
use App\Services\ConfigurationService;
use App\UseCases\Order\GetSellerOrderDetailUseCase;
use App\UseCases\Shipping\CreateShippingUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SellerOrderController extends Controller
{
    private $sellerOrderRepository;

    private $orderRepository;

    private $getSellerOrderDetailUseCase;

    private $createShippingUseCase;

    private $configService;

    public function __construct(
        SellerOrderRepositoryInterface $sellerOrderRepository,
        OrderRepositoryInterface $orderRepository,
        ConfigurationService $configService,
        ?GetSellerOrderDetailUseCase $getSellerOrderDetailUseCase = null,
        ?CreateShippingUseCase $createShippingUseCase = null
    ) {
        $this->sellerOrderRepository = $sellerOrderRepository;
        $this->orderRepository = $orderRepository;
        $this->configService = $configService;
        $this->getSellerOrderDetailUseCase = $getSellerOrderDetailUseCase;
        $this->createShippingUseCase = $createShippingUseCase;
        $this->middleware('jwt.auth');
        $this->middleware('seller');
    }

    /**
     * Obtener listado de órdenes del vendedor con filtros
     */
    public function show($id)
    {
        try {
            $userId = Auth::id();
            $seller = Seller::where('user_id', $userId)->first();

            if (! $seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no registrado como vendedor',
                ], 403);
            }

            $sellerId = $seller->id;

            // Buscar la orden del vendedor
            $sellerOrder = $this->sellerOrderRepository->findById($id);

            if (! $sellerOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido no encontrado',
                ], 404);
            }

            // Verificar que pertenezca al vendedor
            if ($sellerOrder->getSellerId() != $sellerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para ver este pedido',
                ], 403);
            }

            // Obtener orden principal e información relacionada
            $order = $this->orderRepository->findById($sellerOrder->getOrderId());
            $user = null;

            if ($order) {
                $user = User::find($order->getUserId());
            }

            // Obtener información de envío si existe
            $shipping = null;
            if ($order) {
                $shipping = Shipping::where('order_id', $order->getId())->first();
            }

            // ✅ CORREGIDO: Obtener items con información completa de manera más robusta
            $detailedItems = [];

            // Intentar múltiples métodos para obtener los items
            $sellerOrderModel = \App\Models\SellerOrder::with('items')->find($id);

            // Método 1 CORREGIDO: Usar OrderItems directamente (más confiable)
            $orderItems = \App\Models\OrderItem::where('order_id', $sellerOrder->getOrderId())
                ->where('seller_id', $sellerId)
                ->get();

            if ($orderItems && $orderItems->count() > 0) {
                foreach ($orderItems as $item) {
                    // ✅ DEBUGGING: Log de datos del item
                    Log::info('SellerOrder Item Debug', [
                        'item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'original_price' => $item->original_price,
                        'subtotal' => $item->subtotal,
                        'seller_id' => $item->seller_id,
                    ]);

                    // ✅ OBTENER DATOS REALES DEL PRODUCTO
                    $product = \App\Models\Product::find($item->product_id);
                    $productName = $product ? $product->name : ($item->product_name ?: 'Producto no disponible');
                    $productSku = $product ? $product->sku : ($item->product_sku ?: null);

                    // ✅ VERIFICAR SI LOS DATOS ESTÁN VACÍOS Y USAR FALLBACKS
                    $quantity = $item->quantity ?: 1; // Si quantity es 0, usar 1 por defecto
                    $price = $item->price ?: 0;
                    $originalPrice = $item->original_price ?: $price;

                    // Si el precio es 0, intentar obtener el precio del producto
                    if ($price <= 0 && $product) {
                        $originalPrice = $product->price ?: 0;
                        $price = $originalPrice; // Sin descuentos por ahora si no tenemos datos
                        Log::warning("Usando precio del producto para item {$item->id}", [
                            'product_price' => $product->price,
                            'item_price' => $item->price,
                        ]);
                    }

                    // ✅ USAR DATOS REALES DE LA BASE DE DATOS SIN INVENTAR
                    $detailedItems[] = [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $productName,
                        'product_sku' => $productSku,
                        'product_image' => $this->getProductImageById($item->product_id),
                        'quantity' => $item->quantity, // ✅ CANTIDAD REAL DE LA DB
                        'unit_price' => $item->price / $item->quantity, // ✅ PRECIO UNITARIO = total/cantidad
                        'total_price' => $item->price, // ✅ TOTAL REAL del item
                        'original_unit_price' => $item->original_price / $item->quantity, // ✅ PRECIO UNITARIO ORIGINAL
                        'subtotal' => $item->subtotal,
                        'volume_discount_percentage' => $item->volume_discount_percentage ?: 0,
                        'volume_savings' => $item->volume_savings ?: 0,
                        'discount_label' => $item->discount_label ?: null,
                        'total_savings' => $item->volume_savings ?: 0,
                        'has_volume_discount' => ($item->volume_discount_percentage ?: 0) > 0,
                        // ✅ CORREGIDO: Cálculo correcto del descuento del seller
                        'seller_discount_percentage' => $item->seller_discount_percentage ?? 0,
                        'seller_discount_amount' => (($originalPrice) * ($item->seller_discount_percentage ?? 0) / 100),
                        // ✅ CORREGIDO: Comisión solo sobre productos, NO sobre envío
                        'platform_commission_rate' => $this->configService->getConfig('platform.commission_rate', 10.0),
                        'platform_commission_amount' => ($price * ($this->configService->getConfig('platform.commission_rate', 10.0) / 100)),
                        'seller_net_earning_from_products' => ($price * (1 - ($this->configService->getConfig('platform.commission_rate', 10.0) / 100))),
                        'seller_id' => $item->seller_id ?: $sellerId,
                    ];
                }
            } elseif ($sellerOrderModel && $sellerOrderModel->items && $sellerOrderModel->items->count() > 0) {
                // FALLBACK: Si no encontramos OrderItems, usar SellerOrder items
                foreach ($sellerOrderModel->items as $item) {
                    // ✅ OBTENER DATOS REALES DEL PRODUCTO
                    $product = \App\Models\Product::find($item->product_id);
                    $productName = $product ? $product->name : ($item->product_name ?: 'Producto no disponible');
                    $productSku = $product ? $product->sku : ($item->product_sku ?: null);

                    // ✅ VERIFICAR SI LOS DATOS ESTÁN VACÍOS Y USAR FALLBACKS (MISMO FIX)
                    $quantity = $item->quantity ?: 1; // Si quantity es 0, usar 1 por defecto
                    $price = $item->price ?: 0;
                    $originalPrice = $item->original_price ?: $price;

                    // Si el precio es 0, intentar obtener el precio del producto
                    if ($price <= 0 && $product) {
                        $originalPrice = $product->price ?: 0;
                        $price = $originalPrice; // Sin descuentos por ahora si no tenemos datos
                        Log::warning("Usando precio del producto para SellerOrder item {$item->id}", [
                            'product_price' => $product->price,
                            'item_price' => $item->price,
                        ]);
                    }

                    $detailedItems[] = [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $productName, // ✅ NOMBRE REAL DEL PRODUCTO
                        'product_sku' => $productSku,
                        'product_image' => $this->getProductImageById($item->product_id),
                        'quantity' => $quantity, // ✅ CRÍTICO: Cantidad que debe enviar
                        'unit_price' => $quantity > 0 ? ($price / $quantity) : $price, // Precio unitario después de descuentos
                        'total_price' => $price, // Precio total del item (quantity * unit_price)
                        'original_unit_price' => $quantity > 0 ? ($originalPrice / $quantity) : $originalPrice, // Precio unitario original
                        'subtotal' => $item->subtotal ?: $price,
                        'volume_discount_percentage' => $item->volume_discount_percentage ?: 0,
                        'volume_savings' => $item->volume_savings ?: 0,
                        'discount_label' => $item->discount_label ?: null,
                        'total_savings' => $item->volume_savings ?: 0,
                        'has_volume_discount' => ($item->volume_discount_percentage ?: 0) > 0,
                        // ✅ CORREGIDO: Cálculo correcto del descuento del seller
                        'seller_discount_percentage' => $item->seller_discount_percentage ?? 0,
                        'seller_discount_amount' => (($originalPrice) * ($item->seller_discount_percentage ?? 0) / 100),
                        // ✅ CORREGIDO: Comisión solo sobre productos, NO sobre envío
                        'platform_commission_rate' => $this->configService->getConfig('platform.commission_rate', 10.0),
                        'platform_commission_amount' => ($price * ($this->configService->getConfig('platform.commission_rate', 10.0) / 100)),
                        'seller_net_earning_from_products' => ($price * (1 - ($this->configService->getConfig('platform.commission_rate', 10.0) / 100))),
                        'seller_id' => $item->seller_id ?: null,
                    ];
                }
            } else {
                // Método 2: FALLBACK - Buscar items por order_id que pertenezcan al seller
                $orderItems = \App\Models\OrderItem::where('order_id', $sellerOrder->getOrderId())
                    ->where('seller_id', $sellerId)
                    ->get();

                foreach ($orderItems as $item) {
                    $detailedItems[] = [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name ?: 'Producto no disponible',
                        'product_sku' => $item->product_sku ?: null,
                        'product_image' => $this->getProductImageById($item->product_id),
                        'quantity' => $item->quantity, // ✅ CRÍTICO: Cantidad que debe enviar
                        'unit_price' => ($item->price / $item->quantity), // Precio unitario después de descuentos
                        'total_price' => $item->price, // Precio total del item (quantity * unit_price)
                        'original_unit_price' => (($item->original_price ?: $item->price) / $item->quantity), // Precio unitario original
                        'subtotal' => $item->subtotal,
                        'volume_discount_percentage' => $item->volume_discount_percentage ?: 0,
                        'volume_savings' => $item->volume_savings ?: 0,
                        'discount_label' => $item->discount_label ?: null,
                        'total_savings' => method_exists($item, 'getTotalSavings') ? $item->getTotalSavings() : 0,
                        'has_volume_discount' => method_exists($item, 'hasVolumeDiscount') ? $item->hasVolumeDiscount() : false,
                        // ✅ CORREGIDO: Cálculo correcto del descuento del seller
                        'seller_discount_percentage' => $item->seller_discount_percentage ?? 0,
                        'seller_discount_amount' => (($item->original_price ?: $item->price) * ($item->seller_discount_percentage ?? 0) / 100),
                        // ✅ CORREGIDO: Comisión solo sobre productos, NO sobre envío
                        'platform_commission_rate' => $this->configService->getConfig('platform.commission_rate', 10.0),
                        'platform_commission_amount' => ($item->price * ($this->configService->getConfig('platform.commission_rate', 10.0) / 100)),
                        'seller_net_earning_from_products' => ($item->price * (1 - ($this->configService->getConfig('platform.commission_rate', 10.0) / 100))),
                        'seller_id' => $item->seller_id ?: null,
                    ];
                }
            }

            // ✅ NUEVO: Calcular distribución de envío para esta orden
            $shippingDistribution = $this->calculateShippingDistribution($sellerOrder->getOrderId());

            // ✅ CORREGIDO: Calcular resumen CORRECTO basado en datos reales de la DB
            $totalQuantity = 0;
            $totalOriginalAmount = 0;
            $totalFinalAmount = 0;
            $totalCommission = 0;
            $totalSellerEarnings = 0;

            foreach ($detailedItems as $item) {
                $qty = $item['quantity'];
                $totalProductPrice = $item['total_price']; // Total del producto (ya calculado)

                $totalQuantity += $qty;
                $totalOriginalAmount += ($item['original_unit_price'] * $qty);
                $totalFinalAmount += $totalProductPrice;

                // Comisión sobre el precio total final del producto
                $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);
                $itemCommission = $totalProductPrice * ($commissionRate / 100);
                $totalCommission += $itemCommission;

                // Ganancia del seller = precio total del producto - comisión
                $totalSellerEarnings += ($totalProductPrice - $itemCommission);
            }

            $orderSummary = [
                'total_items' => count($detailedItems),
                'total_quantity' => $totalQuantity,
                'total_original_amount' => $totalOriginalAmount,
                'total_final_amount' => $totalFinalAmount,
                'total_platform_commission' => $totalCommission,
                'total_seller_earnings_from_products' => $totalSellerEarnings,
                // ✅ Distribución de envío
                'shipping_distribution' => $shippingDistribution,
                'seller_total_earnings' => $totalSellerEarnings + ($shippingDistribution['seller_amount'] ?? 0),
            ];

            // Preparar datos de respuesta con items detallados
            $orderDetails = [
                'id' => $sellerOrder->getId(),
                'orderNumber' => $sellerOrder->getOrderNumber(),
                'orderDate' => $sellerOrder->getCreatedAt()->format('Y-m-d H:i:s'),
                'status' => $sellerOrder->getStatus(),
                'total' => $sellerOrder->getTotal(),
                'items' => $detailedItems,  // ✅ Items con información completa
                'shippingData' => $sellerOrder->getShippingData() ?: [],
                'customer' => [
                    'id' => $user ? $user->id : ($order ? $order->getUserId() : null),
                    'name' => $user ? $user->name : 'Cliente',
                    'email' => $user ? $user->email : 'sin@email.com',
                ],
                'shipping' => $shipping ? [
                    'id' => $shipping->id,
                    'tracking_number' => $shipping->tracking_number,
                    'status' => $shipping->status,
                    'carrier_name' => $shipping->carrier_name,
                    'estimated_delivery' => $shipping->estimated_delivery ? $shipping->estimated_delivery->format('Y-m-d H:i:s') : null,
                    'current_location' => $shipping->current_location,
                    'last_updated' => $shipping->last_updated ? $shipping->last_updated->format('Y-m-d H:i:s') : null,
                ] : null,
                'payment' => $order ? [
                    'method' => $order->getPaymentMethod(),
                    'status' => $order->getPaymentStatus(),
                    'payment_id' => $order->getPaymentId(),
                ] : null,

                // ✅ NUEVO: Resumen de la orden para el seller
                'order_summary' => $orderSummary,
            ];

            return response()->json([
                'success' => true,
                'data' => $orderDetails,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en SellerOrderController::show()', [
                'order_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el pedido: '.$e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        // Obtener seller_id del usuario autenticado
        $userId = Auth::id();
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no registrado como vendedor',
            ], 403);
        }

        $sellerId = $seller->id;

        $limit = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        // Obtener los filtros de la consulta
        $filters = [
            'status' => $request->input('status'),
            'payment_status' => $request->input('payment_status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        try {
            // Obtener las órdenes filtradas
            $sellerOrders = $this->sellerOrderRepository->getFilteredOrdersForSeller($sellerId, $filters, $limit, $offset);
            $totalCount = $this->sellerOrderRepository->countBySellerId($sellerId);

            // Formatear las órdenes para la respuesta
            $formattedOrders = [];
            foreach ($sellerOrders as $sellerOrder) {
                // Obtener la orden principal para información del usuario
                $mainOrder = $this->orderRepository->findById($sellerOrder->getOrderId());
                if (! $mainOrder) {
                    continue;
                }

                $userId = $mainOrder->getUserId();
                $user = User::find($userId);

                // ✅ CORREGIDO: Obtener items detallados para cada orden de manera robusta
                $detailedItems = [];
                $sellerOrderModel = \App\Models\SellerOrder::with('items')->find($sellerOrder->getId());

                // Método 1: Items directamente vinculados al seller_order_id
                if ($sellerOrderModel && $sellerOrderModel->items && $sellerOrderModel->items->count() > 0) {
                    foreach ($sellerOrderModel->items as $item) {
                        $detailedItems[] = [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name ?: 'Producto no disponible',
                            'product_sku' => $item->product_sku ?: null,
                            'product_image' => $this->getProductImageById($item->product_id),
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'original_price' => $item->original_price ?: $item->price,
                            'subtotal' => $item->subtotal,
                            'volume_discount_percentage' => $item->volume_discount_percentage ?: 0,
                            'volume_savings' => $item->volume_savings ?: 0,
                            'discount_label' => $item->discount_label ?: null,
                            // ✅ CORREGIDO: Cálculos para la lista de órdenes
                            'platform_commission_rate' => $this->configService->getConfig('platform.commission_rate', 10.0),
                            'platform_commission_amount' => ($item->price * ($this->configService->getConfig('platform.commission_rate', 10.0) / 100)),
                            'seller_net_earning_from_products' => ($item->price * (1 - ($this->configService->getConfig('platform.commission_rate', 10.0) / 100))),
                        ];
                    }
                } else {
                    // Método 2: FALLBACK - Buscar items por order_id que pertenezcan al seller
                    $orderItems = \App\Models\OrderItem::where('order_id', $sellerOrder->getOrderId())
                        ->where('seller_id', $sellerId)
                        ->get();

                    foreach ($orderItems as $item) {
                        $detailedItems[] = [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name ?: 'Producto no disponible',
                            'product_sku' => $item->product_sku ?: null,
                            'product_image' => $this->getProductImageById($item->product_id),
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'original_price' => $item->original_price ?: $item->price,
                            'subtotal' => $item->subtotal,
                            'volume_discount_percentage' => $item->volume_discount_percentage ?: 0,
                            'volume_savings' => $item->volume_savings ?: 0,
                            'discount_label' => $item->discount_label ?: null,
                            // ✅ CORREGIDO: Cálculos para la lista de órdenes
                            'platform_commission_rate' => $this->configService->getConfig('platform.commission_rate', 10.0),
                            'platform_commission_amount' => ($item->price * ($this->configService->getConfig('platform.commission_rate', 10.0) / 100)),
                            'seller_net_earning_from_products' => ($item->price * (1 - ($this->configService->getConfig('platform.commission_rate', 10.0) / 100))),
                        ];
                    }
                }

                // Formatear los datos para la respuesta
                $formattedOrders[] = [
                    'id' => $sellerOrder->getId(),
                    'orderNumber' => $sellerOrder->getOrderNumber(),
                    'date' => $sellerOrder->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
                    'customer' => [
                        'id' => $userId,
                        'name' => $user ? $user->name : 'Cliente',
                        'email' => $user ? $user->email : 'sin@email.com',
                    ],
                    'total' => $sellerOrder->getTotal(),
                    'items' => $detailedItems,  // ✅ Items con información completa
                    'status' => $sellerOrder->getStatus(),
                    'paymentStatus' => $sellerOrder->getPaymentStatus(),
                    'shippingAddress' => $sellerOrder->getShippingData(),

                    // ✅ CORREGIDO: Resumen rápido para la lista con comisiones y envío
                    'items_summary' => [
                        'total_items' => count($detailedItems),
                        'total_quantity' => array_sum(array_column($detailedItems, 'quantity')),
                        'total_platform_commission' => array_sum(array_column($detailedItems, 'platform_commission_amount')),
                        'total_seller_earnings_from_products' => array_sum(array_column($detailedItems, 'seller_net_earning_from_products')),
                        // Nota: La distribución de envío se calcula a nivel individual, no aquí
                    ],
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $formattedOrders,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => ceil($totalCount / $limit),
                    'totalItems' => $totalCount,
                    'itemsPerPage' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los pedidos: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de órdenes del vendedor
     */
    public function stats()
    {
        // Obtener seller_id del usuario autenticado
        $userId = Auth::id();
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no registrado como vendedor',
            ], 403);
        }

        $sellerId = $seller->id;

        try {
            // Obtener estadísticas
            $stats = $this->sellerOrderRepository->getSellerOrderStats($sellerId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar el estado de una orden
     */
    public function updateStatus(Request $request, $id)
    {
        $userId = Auth::id();
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no registrado como vendedor',
            ], 403);
        }

        $sellerId = $seller->id;

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled,completed,failed,returned,in_transit',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Estado inválido',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $sellerOrder = $this->sellerOrderRepository->findById($id);

            if (! $sellerOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada',
                ], 404);
            }

            if ($sellerOrder->getSellerId() != $sellerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para modificar esta orden',
                ], 403);
            }

            $previousStatus = $sellerOrder->getStatus();
            $newStatus = $request->input('status');

            // Actualizar el estado en SellerOrder
            $updated = $this->sellerOrderRepository->updateStatus($id, $newStatus);

            if (! $updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar el estado de la orden',
                ], 500);
            }

            // Actualizar el estado en la orden principal (Order)
            $order = $this->orderRepository->findById($sellerOrder->getOrderId());
            if ($order) {
                $order->setStatus($newStatus);

                // Agregar timestamps según el estado
                if ($newStatus === 'delivered') {
                    $order->delivered_at = now();
                } elseif ($newStatus === 'completed') {
                    $order->completed_at = now();
                }

                $this->orderRepository->save($order);
            }

            // Manejar lógica de envío
            if ($newStatus === 'shipped') {
                $this->handleShippedStatus($sellerOrder);
            } elseif ($newStatus === 'delivered') {
                $this->handleDeliveredStatus($sellerOrder);
            }

            // CORREGIDO: Solo disparar UN evento para la orden principal
            if ($order) {
                $orderPreviousStatus = $order->getStatus();
                event(new OrderStatusChanged($order->getId(), $orderPreviousStatus, $newStatus, 'main_order'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Estado de la orden actualizado correctamente',
                'data' => [
                    'id' => $sellerOrder->getId(),
                    'status' => $newStatus,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado de la orden: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manejar lógica cuando el estado cambia a "shipped"
     */
    private function handleShippedStatus($sellerOrder)
    {
        $shipping = Shipping::where('order_id', $sellerOrder->getOrderId())->first();

        if (! $shipping) {
            $shipping = new Shipping([
                'order_id' => $sellerOrder->getOrderId(),
                'tracking_number' => Shipping::generateTrackingNumber(),
                'status' => 'shipped',
                'current_location' => [
                    'lat' => 19.4326,
                    'lng' => -99.1332,
                    'address' => 'Centro de distribución principal',
                ],
                'estimated_delivery' => now()->addDays(5),
                'carrier_name' => 'Default Carrier',
            ]);
            $shipping->save();

            $shipping->addHistoryEvent(
                'shipped',
                $shipping->current_location,
                'Paquete enviado al destinatario'
            );
        }
    }

    /**
     * Manejar lógica cuando el estado cambia a "delivered"
     */
    private function handleDeliveredStatus($sellerOrder)
    {
        $shipping = Shipping::where('order_id', $sellerOrder->getOrderId())->first();
        if ($shipping) {
            $shipping->status = 'delivered';
            $shipping->delivered_at = now();
            $shipping->last_updated = now();
            $shipping->save();

            $shipping->addHistoryEvent(
                'delivered',
                $shipping->current_location,
                'Paquete entregado al destinatario'
            );
        }
    }

    /**
     * Actualizar la información de envío de una orden
     */
    /**
     * Actualizar la información de envío de una orden
     */
    public function updateShipping(Request $request, $id)
    {
        // Obtener seller_id del usuario autenticado
        $userId = Auth::id();
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no registrado como vendedor',
            ], 403);
        }

        $sellerId = $seller->id;

        // ✅ CORREGIDO: Validación flexible - tracking_number no siempre requerido
        $rules = [];

        // Si solo se actualiza el status, no requerir tracking_number
        if ($request->has('status') && ! $request->has('tracking_number')) {
            $rules['status'] = 'required|string|in:pending,processing,shipped,delivered,cancelled,completed,failed,returned,in_transit';
        } else {
            // Si se incluye información de shipping, validar campos completos
            $rules = [
                'tracking_number' => 'nullable|string|max:100',
                'shipping_company' => 'nullable|string|max:100',
                'estimated_delivery' => 'nullable|date',
                'notes' => 'nullable|string|max:500',
                'status' => 'nullable|string|in:pending,processing,shipped,delivered,cancelled,completed,failed,returned,in_transit',
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de envío inválidos',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            Log::info('🔄 INICIO updateShipping', [
                'seller_id' => $sellerId,
                'order_id' => $id,
                'request_data' => $request->all()
            ]);

            $sellerOrder = $this->sellerOrderRepository->findById($id);

            if (! $sellerOrder) {
                Log::warning('❌ Orden no encontrada', [
                    'seller_id' => $sellerId,
                    'order_id' => $id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada',
                ], 404);
            }

            // Verificar que la orden pertenezca al vendedor
            if ($sellerOrder->getSellerId() != $sellerId) {
                Log::warning('❌ Acceso denegado a orden', [
                    'seller_id' => $sellerId,
                    'order_id' => $id,
                    'order_seller_id' => $sellerOrder->getSellerId()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para modificar esta orden',
                ], 403);
            }

            Log::info('✅ Orden encontrada y verificada', [
                'seller_order_id' => $sellerOrder->getId(),
                'order_id' => $sellerOrder->getOrderId(),
                'current_status' => $sellerOrder->getStatus()
            ]);

            // ✅ NUEVO: Si solo se actualiza el status, usar el método updateStatus
            if ($request->has('status') && ! $request->hasAny(['tracking_number', 'shipping_company', 'estimated_delivery', 'notes'])) {
                $previousStatus = $sellerOrder->getStatus();
                $newStatus = $request->input('status');

                Log::info('🔄 ACTUALIZANDO SOLO STATUS', [
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus,
                    'seller_order_id' => $sellerOrder->getId()
                ]);

                // Actualizar el estado en SellerOrder
                $updated = $this->sellerOrderRepository->updateStatus($id, $newStatus);

                if (! $updated) {
                    Log::error('❌ Error al actualizar estado de SellerOrder', [
                        'seller_order_id' => $id,
                        'new_status' => $newStatus
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al actualizar el estado de la orden',
                    ], 500);
                }

                Log::info('✅ Estado de SellerOrder actualizado', [
                    'seller_order_id' => $id,
                    'status' => $newStatus
                ]);

                // Actualizar el estado en la orden principal (Order)
                $order = $this->orderRepository->findById($sellerOrder->getOrderId());
                if ($order) {
                    $orderPreviousStatus = $order->getStatus();
                    $order->setStatus($newStatus);

                    // 🔧 AGREGAR: Actualizar fechas según el estado
                    if ($newStatus === 'delivered') {
                        $order->delivered_at = now();
                    } elseif ($newStatus === 'completed') {
                        $order->completed_at = now();
                    }

                    $this->orderRepository->save($order);

                    // Disparar evento para la orden principal
                    event(new OrderStatusChanged($order->getId(), $orderPreviousStatus, $newStatus, 'main_order'));
                }

                // Disparar evento de cambio de estado para SellerOrder
                event(new OrderStatusChanged($sellerOrder->getId(), $previousStatus, $newStatus, 'seller_order'));

                return response()->json([
                    'success' => true,
                    'message' => 'Estado de la orden actualizado correctamente',
                    'data' => [
                        'id' => $sellerOrder->getId(),
                        'status' => $newStatus,
                    ],
                ]);
            }

            // ✅ FLUJO ORIGINAL: Actualizar información completa de envío
            $shippingInfo = $request->only(['tracking_number', 'shipping_company', 'estimated_delivery', 'notes']);

            Log::info('🔄 ACTUALIZANDO INFORMACIÓN DE ENVÍO', [
                'shipping_info' => $shippingInfo,
                'seller_order_id' => $sellerOrder->getId(),
                'current_status' => $sellerOrder->getStatus()
            ]);

            // Convertir fecha estimada de entrega
            if (isset($shippingInfo['estimated_delivery']) && ! empty($shippingInfo['estimated_delivery'])) {
                $shippingInfo['estimated_delivery'] = new \DateTime($shippingInfo['estimated_delivery']);
            } else {
                $shippingInfo['estimated_delivery'] = now()->addDays(5);
            }

            Log::info('📅 Fecha de entrega procesada', [
                'estimated_delivery' => $shippingInfo['estimated_delivery']->format('Y-m-d H:i:s')
            ]);

            // Actualizar la información de envío en SellerOrder
            $updated = $this->sellerOrderRepository->updateShippingInfo($id, $shippingInfo);

            if (! $updated) {
                Log::error('❌ Error al actualizar información de envío en SellerOrder', [
                    'seller_order_id' => $id,
                    'shipping_info' => $shippingInfo
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar la información de envío',
                ], 500);
            }

            Log::info('✅ Información de envío actualizada en SellerOrder', [
                'seller_order_id' => $id
            ]);

            // Buscar o crear el registro de envío correspondiente
            $order_id = $sellerOrder->getOrderId();
            $shipping = Shipping::where('order_id', $order_id)->first();
            $isNew = false;

            Log::info('🔍 BUSCANDO/CREANDO REGISTRO DE SHIPPING', [
                'order_id' => $order_id,
                'shipping_found' => $shipping ? true : false,
                'shipping_id' => $shipping ? $shipping->id : null,
                'shipping_status' => $shipping ? $shipping->status : null
            ]);

            if (! $shipping) {
                $isNew = true;
                Log::info('🆕 Creando nuevo registro de envío', [
                    'order_id' => $order_id
                ]);
                // Crear un nuevo envío
                $shipping = new Shipping;
                $shipping->order_id = $order_id;
                $shipping->status = 'processing';
                $shipping->current_location = [
                    'lat' => 19.4326,
                    'lng' => -99.1332,
                    'address' => 'Centro de distribución principal',
                ];
                $shipping->last_updated = now();
            } else {
                Log::info('📦 Utilizando registro de envío existente', [
                    'shipping_id' => $shipping->id,
                    'current_status' => $shipping->status
                ]);
            }

            // Actualizar los datos del envío solo si se proporcionaron
            if (isset($shippingInfo['tracking_number']) && ! empty($shippingInfo['tracking_number'])) {
                $shipping->tracking_number = $shippingInfo['tracking_number'];
            }

            if (isset($shippingInfo['shipping_company']) && ! empty($shippingInfo['shipping_company'])) {
                $shipping->carrier_name = $shippingInfo['shipping_company'];
            } elseif ($isNew) {
                $shipping->carrier_name = 'Default Carrier';
            }

            if (isset($shippingInfo['estimated_delivery'])) {
                $shipping->estimated_delivery = $shippingInfo['estimated_delivery'];
            } elseif ($isNew) {
                $shipping->estimated_delivery = now()->addDays(5);
            }

            // Guardar el envío
            $result = $shipping->save();

            // Agregar un evento al historial
            if ($result) {
                $eventType = $isNew ? 'processing' : $shipping->status;
                $shipping->addHistoryEvent(
                    $eventType,
                    $shipping->current_location,
                    $isNew ? 'Envío registrado en el sistema' : 'Información de envío actualizada'
                );
            }

            // Si se estableció un tracking number, actualizar el estado de la orden a "shipped" si corresponde
            if (isset($shippingInfo['tracking_number']) && ! empty($shippingInfo['tracking_number']) && $sellerOrder->getStatus() === 'processing') {
                $previousStatus = $sellerOrder->getStatus();

                Log::info('🚀 CAMBIANDO ESTADO A SHIPPED (por tracking number)', [
                    'seller_order_id' => $id,
                    'previous_status' => $previousStatus,
                    'tracking_number' => $shippingInfo['tracking_number']
                ]);

                $this->sellerOrderRepository->updateStatus($id, 'shipped');

                // Cambiar el estado del envío a "shipped"
                $previousShippingStatus = $shipping->status;
                $shipping->status = 'shipped';
                $shipping->save();

                Log::info('✅ Estados actualizados a shipped', [
                    'seller_order_id' => $id,
                    'shipping_id' => $shipping->id,
                    'previous_shipping_status' => $previousShippingStatus,
                    'new_shipping_status' => 'shipped'
                ]);

                // Registrar evento de envío
                $shipping->addHistoryEvent(
                    'shipped',
                    $shipping->current_location,
                    'Paquete enviado al destinatario'
                );

                // Disparar evento de cambio de estado
                event(new OrderStatusChanged($sellerOrder->getId(), $previousStatus, 'shipped', 'seller_order'));

                // Disparar evento para el envío
                event(new ShippingStatusUpdated(
                    $shipping->id,
                    $previousShippingStatus,
                    'shipped'
                ));
            }

            // Actualizar también la orden principal
            $order = $this->orderRepository->findById($sellerOrder->getOrderId());
            if ($order && $order->getStatus() === 'processing' && isset($shippingInfo['tracking_number']) && ! empty($shippingInfo['tracking_number'])) {
                $orderPreviousStatus = $order->getStatus();

                Log::info('🔄 ACTUALIZANDO ORDEN PRINCIPAL', [
                    'main_order_id' => $order->getId(),
                    'previous_status' => $orderPreviousStatus,
                    'new_status' => 'shipped'
                ]);

                $order->setStatus('shipped');
                $this->orderRepository->save($order);

                Log::info('✅ Orden principal actualizada', [
                    'main_order_id' => $order->getId(),
                    'status' => 'shipped'
                ]);

                // Disparar evento para la orden principal
                event(new OrderStatusChanged($order->getId(), $orderPreviousStatus, 'shipped',
                    'main_order'));
            } else {
                Log::info('⏭️ No se actualizó la orden principal', [
                    'main_order_found' => $order ? true : false,
                    'main_order_status' => $order ? $order->getStatus() : null,
                    'has_tracking' => isset($shippingInfo['tracking_number']) && !empty($shippingInfo['tracking_number'])
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Información de envío actualizada correctamente',
                'data' => [
                    'shipping_id' => $shipping->id,
                    'tracking_number' => $shipping->tracking_number,
                    'status' => $shipping->status,
                ],
            ]);
        } catch (\Exception $e) {
            // Log detallado del error
            Log::error('Error al actualizar información de envío: '.$e->getMessage()."\n".$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la información de envío: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Completar un pedido
     */
    public function complete($id)
    {
        $userId = Auth::id();
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no registrado como vendedor',
            ], 403);
        }

        $sellerId = $seller->id;

        try {
            $sellerOrder = $this->sellerOrderRepository->findById($id);

            if (! $sellerOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada',
                ], 404);
            }

            // Verificar que la orden pertenezca al vendedor
            if ($sellerOrder->getSellerId() != $sellerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para completar esta orden',
                ], 403);
            }

            // Verificar que el pedido esté en estado entregado (o pagado) para poder completarlo
            if (! in_array($sellerOrder->getStatus(), ['delivered', 'paid'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden completar pedidos que estén en estado entregado o pagado',
                ], 400);
            }

            // Cambiar estado de la orden a completado
            $previousStatus = $sellerOrder->getStatus();
            $updated = $this->sellerOrderRepository->updateStatus($id, 'completed');

            if (! $updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al completar la orden',
                ], 500);
            }

            // Actualizar el estado en la orden principal (Order)
            $order = $this->orderRepository->findById($sellerOrder->getOrderId());
            if ($order) {
                $orderPreviousStatus = $order->getStatus();
                $order->setStatus('completed');
                $this->orderRepository->save($order);

                // Disparar evento para la orden principal
                event(new OrderStatusChanged($order->getId(), $orderPreviousStatus, 'completed', 'main_order'));
                event(new OrderCompleted($order->getId()));
            }

            // Disparar eventos
            event(new OrderStatusChanged($id, $previousStatus, 'completed', 'seller_order'));
            event(new OrderCompleted($id, 'seller_order'));

            return response()->json([
                'success' => true,
                'message' => 'Pedido completado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al completar el pedido: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar un pedido
     */
    public function cancelOrder(Request $request, $id)
    {
        $userId = Auth::id();
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no registrado como vendedor',
            ], 403);
        }

        $sellerId = $seller->id;

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $sellerOrder = $this->sellerOrderRepository->findById($id);

            if (! $sellerOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada',
                ], 404);
            }

            // Verificar que la orden pertenezca al vendedor
            if ($sellerOrder->getSellerId() != $sellerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para cancelar esta orden',
                ], 403);
            }

            // Verificar que el pedido pueda ser cancelado
            if (! in_array($sellerOrder->getStatus(), ['pending', 'processing', 'paid'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cancelar un pedido que ya está en estado '.$sellerOrder->getStatus(),
                ], 400);
            }

            // Actualizar razón de cancelación en shipping_data
            $shippingData = $sellerOrder->getShippingData() ?: [];
            $shippingData['cancel_reason'] = $request->input('reason', '');
            $this->sellerOrderRepository->updateShippingInfo($id, $shippingData);

            // Cambiar estado de la orden
            $previousStatus = $sellerOrder->getStatus();
            $updated = $this->sellerOrderRepository->updateStatus($id, 'cancelled');

            if (! $updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al cancelar la orden',
                ], 500);
            }

            // Actualizar el estado en la orden principal (Order)
            $order = $this->orderRepository->findById($sellerOrder->getOrderId());
            if ($order) {
                $orderPreviousStatus = $order->getStatus();
                $order->setStatus('cancelled');
                $this->orderRepository->save($order);

                // Disparar evento para la orden principal
                event(new OrderStatusChanged($order->getId(), $orderPreviousStatus, 'cancelled',
                    'main_order'));
            }

            // Disparar evento de cambio de estado
            event(new OrderStatusChanged($id, $previousStatus, 'cancelled', 'seller_order'));

            return response()->json([
                'success' => true,
                'message' => 'Pedido cancelado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar el pedido: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener clientes del vendedor con sus compras
     */
    public function customers(Request $request)
    {
        $userId = Auth::id();
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no registrado como vendedor',
            ], 403);
        }

        $sellerId = $seller->id;
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        try {
            // Como esta funcionalidad probablemente esté implementada en OrderRepository,
            // podemos mantenerla allí y usarla aquí
            $customers = $this->orderRepository->getSellerCustomers($sellerId, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $customers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener pedidos con un producto específico
     */
    public function ordersWithProduct(Request $request, $productId)
    {
        $userId = Auth::id();
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no registrado como vendedor',
            ], 403);
        }

        $sellerId = $seller->id;
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        try {
            // Como esta funcionalidad probablemente esté implementada en OrderRepository,
            // podemos mantenerla allí y usarla aquí
            $orders = $this->orderRepository->getOrdersWithProduct($sellerId, $productId, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pedidos con el producto: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener pedidos pendientes de envío
     */
    public function awaitingShipment(Request $request)
    {
        $userId = Auth::id();
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no registrado como vendedor',
            ], 403);
        }

        $sellerId = $seller->id;
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        try {
            $orders = $this->orderRepository->getOrdersAwaitingShipment($sellerId, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pedidos pendientes de envío: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ NUEVA FUNCIÓN: Calcular distribución de envío para una orden
     */
    private function calculateShippingDistribution(int $orderId): array
    {
        try {
            // Obtener la orden principal para saber el costo de envío
            $order = $this->orderRepository->findById($orderId);
            if (! $order) {
                return ['seller_amount' => 0, 'platform_amount' => 0, 'total_cost' => 0];
            }

            // Obtener el costo de envío de la orden (asumiendo que está en el modelo)
            $shippingCost = $order->getShippingCost() ?? 0;

            if ($shippingCost <= 0) {
                return ['seller_amount' => 0, 'platform_amount' => 0, 'total_cost' => 0];
            }

            // Contar cuántos sellers únicos hay en esta orden
            $sellerCount = \App\Models\SellerOrder::where('order_id', $orderId)->distinct('seller_id')->count();

            $enabled = $this->configService->getConfig('shipping_distribution.enabled', true);

            if (! $enabled) {
                return [
                    'seller_amount' => 0,
                    'platform_amount' => $shippingCost,
                    'total_cost' => $shippingCost,
                    'seller_count' => $sellerCount,
                    'enabled' => false,
                ];
            }

            if ($sellerCount === 1) {
                // Un solo seller: recibe el porcentaje máximo configurado
                $percentage = $this->configService->getConfig('shipping_distribution.single_seller_max', 80.0);
                $sellerAmount = ($shippingCost * $percentage) / 100;
                $platformAmount = $shippingCost - $sellerAmount;

                return [
                    'seller_amount' => round($sellerAmount, 2),
                    'platform_amount' => round($platformAmount, 2),
                    'total_cost' => $shippingCost,
                    'seller_count' => $sellerCount,
                    'percentage' => $percentage,
                    'enabled' => true,
                ];
            } else {
                // Múltiples sellers: cada uno recibe el porcentaje configurado
                $percentageEach = $this->configService->getConfig('shipping_distribution.multiple_sellers_each', 40.0);
                $amountPerSeller = ($shippingCost * $percentageEach) / 100;
                $totalDistributed = $amountPerSeller * $sellerCount;
                $platformAmount = $shippingCost - $totalDistributed;

                return [
                    'seller_amount' => round($amountPerSeller, 2), // Lo que recibe ESTE seller
                    'platform_amount' => round($platformAmount, 2),
                    'total_cost' => $shippingCost,
                    'seller_count' => $sellerCount,
                    'percentage' => $percentageEach,
                    'total_distributed_to_all_sellers' => round($totalDistributed, 2),
                    'enabled' => true,
                ];
            }
        } catch (\Exception $e) {
            Log::error("Error calculating shipping distribution for order {$orderId}: ".$e->getMessage());

            return ['seller_amount' => 0, 'platform_amount' => 0, 'total_cost' => 0];
        }
    }

    /**
     * ✅ FUNCIÓN HELPER: Obtener imagen de producto por ID (igual que en el repositorio)
     */
    private function getProductImageById(int $productId): ?string
    {
        try {
            $product = \App\Models\Product::find($productId);

            if (! $product) {
                return null;
            }

            // Obtener atributos raw sin accessors
            $attributes = $product->getAttributes();

            // El campo images contiene un JSON string
            $imagesJson = $attributes['images'] ?? null;

            if (empty($imagesJson)) {
                return null;
            }

            // Decodificar el JSON
            $imagesArray = json_decode($imagesJson, true);

            if (! is_array($imagesArray) || empty($imagesArray)) {
                return null;
            }

            // Tomar la primera imagen
            $firstImage = $imagesArray[0];

            if (! is_array($firstImage)) {
                return null;
            }

            // Preferir thumbnail, luego original, luego cualquier otro
            $imagePath = $firstImage['thumbnail'] ?? $firstImage['original'] ?? $firstImage['small'] ?? $firstImage['medium'] ?? null;

            if (! $imagePath) {
                return null;
            }

            // Construir URL completa
            return asset('storage/'.$imagePath);

        } catch (\Exception $e) {
            Log::error("Error getting product image for ID {$productId}: ".$e->getMessage());

            return null;
        }
    }
}
