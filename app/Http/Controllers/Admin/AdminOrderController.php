<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\SellerOrderRepositoryInterface;
use App\Events\OrderStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminOrderController extends Controller
{
    private $orderRepository;

    private $sellerOrderRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ?SellerOrderRepositoryInterface $sellerOrderRepository = null
    ) {
        $this->orderRepository = $orderRepository;
        $this->sellerOrderRepository = $sellerOrderRepository;

        $this->middleware('jwt.auth');
        $this->middleware('admin');
    }

    /**
     * Obtener lista de órdenes con filtros
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $limit = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        // Obtener filtros
        $filters = [
            'status' => $request->input('status'),
            'payment_status' => $request->input('paymentStatus'),
            'seller_id' => $request->input('sellerId'),
            'date_from' => $request->input('dateFrom'),
            'date_to' => $request->input('dateTo'),
            'search' => $request->input('search'),
        ];

        try {
            // Realizar consulta con filtros
            $orders = $this->getAllOrdersWithFilters($filters, $limit, $offset);
            $totalCount = $this->countAllOrders($filters);

            // Preparar response
            return response()->json([
                'success' => true,
                'data' => $orders,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => ceil($totalCount / $limit),
                    'totalItems' => $totalCount,
                    'itemsPerPage' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener órdenes: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener órdenes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalles de una orden específica
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $orderDetails = $this->orderRepository->getOrderDetails($id);

            if (empty($orderDetails)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $orderDetails,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles de orden: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalles de la orden: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar el estado de una orden
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,processing,paid,shipped,delivered,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Estado inválido',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $order = $this->orderRepository->findById($id);

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada',
                ], 404);
            }

            $previousStatus = $order->getStatus();
            $newStatus = $request->input('status');

            // Use specific updateStatus method to avoid affecting order items
            $updateResult = $this->orderRepository->updateStatus($order->getId(), $newStatus);

            if (!$updateResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar el estado de la orden',
                ], 500);
            }

            // Si existe repositorio de SellerOrder y es una orden multi-vendedor
            if ($this->sellerOrderRepository && $order->hasMultipleSellers()) {
                $sellerOrders = $this->sellerOrderRepository->findByOrderId($id);
                foreach ($sellerOrders as $sellerOrder) {
                    $this->sellerOrderRepository->updateStatus($sellerOrder->getId(), $newStatus);
                }
            }

            // Note: OrderStatusChanged event is already dispatched in updateStatus method

            // Actualizar shipping status si es necesario
            if ($newStatus === 'shipped' || $newStatus === 'delivered') {
                $this->updateShippingStatus($id, $newStatus);
            }

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente',
                'data' => [
                    'id' => $order->getId(),
                    'status' => $newStatus,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de orden: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado de la orden: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar una orden
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelOrder(Request $request, $id)
    {
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
            $reason = $request->input('reason', '');
            $success = $this->orderRepository->cancelOrder($id, $reason);

            if (! $success) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo cancelar la orden',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Orden cancelada correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cancelar orden: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la orden: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar información de envío
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateShipping(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'tracking_number' => 'nullable|string|max:100',
            'shipping_company' => 'nullable|string|max:100',
            'estimated_delivery' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de envío inválidos',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $shippingInfo = $request->only([
                'tracking_number',
                'shipping_company',
                'estimated_delivery',
                'notes',
            ]);

            $success = $this->orderRepository->updateShippingInfo($id, $shippingInfo);

            if (! $success) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo actualizar la información de envío',
                ], 400);
            }

            // Si se incluye tracking_number, crear o actualizar registro de envío
            if (isset($shippingInfo['tracking_number'])) {
                $this->createOrUpdateShipping($id, $shippingInfo);
            }

            return response()->json([
                'success' => true,
                'message' => 'Información de envío actualizada correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar información de envío: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar información de envío: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas generales de órdenes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderStats()
    {
        try {
            // Contar órdenes por estado
            $totalOrders = Order::count();
            $pendingOrders = Order::where('status', 'pending')->count();
            $processingOrders = Order::where('status', 'processing')->count();
            $shippedOrders = Order::where('status', 'shipped')->count();
            $deliveredOrders = Order::where('status', 'delivered')->count();
            $completedOrders = Order::where('status', 'completed')->count();
            $cancelledOrders = Order::where('status', 'cancelled')->count();

            // Calcular ventas totales (excluyendo canceladas)
            $totalSales = Order::whereNotIn('status', ['cancelled'])
                ->sum('total');

            return response()->json([
                'success' => true,
                'data' => [
                    'totalOrders' => $totalOrders,
                    'pendingOrders' => $pendingOrders,
                    'processingOrders' => $processingOrders,
                    'shippedOrders' => $shippedOrders,
                    'deliveredOrders' => $deliveredOrders,
                    'completedOrders' => $completedOrders,
                    'cancelledOrders' => $cancelledOrders,
                    'totalSales' => $totalSales,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de órdenes: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas de órdenes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Métodos privados auxiliares
     */

    /**
     * Obtener todas las órdenes con filtros aplicados
     */
    private function getAllOrdersWithFilters(array $filters, int $limit, int $offset): array
    {
        $query = Order::with(['user:id,name,email', 'items.product']);

        // Aplicar filtros
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Ordenar por fecha de creación descendente
        $query->orderBy('created_at', 'desc');

        // Paginar resultados
        $orders = $query->skip($offset)->take($limit)->get();

        $result = [];
        foreach ($orders as $order) {
            // Preparar items
            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Producto no disponible',
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                    'product_image' => $item->product ? $item->product->getMainImageUrl() : null,
                ];
            }

            // Formatear la orden para la respuesta
            $result[] = [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'seller_id' => $order->seller_id,
                'user_name' => $order->user->name ?? 'Usuario',
                'user_email' => $order->user->email ?? '',
                'order_number' => $order->order_number,
                'total' => $order->total,
                'status' => $order->status,
                'payment_id' => $order->payment_id,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'shipping_data' => $order->shipping_data,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $order->updated_at->format('Y-m-d H:i:s'),
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * Contar total de órdenes con filtros aplicados
     */
    private function countAllOrders(array $filters): int
    {
        $query = Order::query();

        // Aplicar filtros
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $query->count();
    }

    /**
     * Actualizar el estado del envío
     */
    private function updateShippingStatus(int $orderId, string $status): void
    {
        $shipping = Shipping::where('order_id', $orderId)->first();

        if (! $shipping) {
            return;
        }

        $previousStatus = $shipping->status;
        $shipping->status = $status;
        $shipping->last_updated = now();

        if ($status === 'delivered') {
            $shipping->delivered_at = now();
        }

        $shipping->save();

        // Registrar evento en historial
        $shipping->addHistoryEvent(
            $status,
            $shipping->current_location,
            "Estado actualizado a: {$status} por administrador"
        );
    }

    /**
     * Crear o actualizar registro de envío
     */
    private function createOrUpdateShipping(int $orderId, array $shippingInfo): void
    {
        $shipping = Shipping::where('order_id', $orderId)->first();
        $isNew = false;

        if (! $shipping) {
            $isNew = true;
            $shipping = new Shipping;
            $shipping->order_id = $orderId;
            $shipping->status = 'processing';
            $shipping->current_location = [
                'lat' => 0,
                'lng' => 0,
                'address' => 'Centro de distribución',
            ];
        }

        // Actualizar campos
        if (isset($shippingInfo['tracking_number'])) {
            $shipping->tracking_number = $shippingInfo['tracking_number'];
        }

        if (isset($shippingInfo['shipping_company'])) {
            $shipping->carrier_name = $shippingInfo['shipping_company'];
        }

        if (isset($shippingInfo['estimated_delivery'])) {
            $shipping->estimated_delivery = $shippingInfo['estimated_delivery'];
        }

        $shipping->last_updated = now();
        $shipping->save();

        // Registrar evento en historial
        $eventType = $isNew ? 'created' : 'updated';
        $shipping->addHistoryEvent(
            $eventType,
            $shipping->current_location,
            $isNew ? 'Envío registrado por administrador' : 'Información de envío actualizada por administrador'
        );
    }
}
