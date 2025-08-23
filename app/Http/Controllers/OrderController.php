<?php

namespace App\Http\Controllers;

use App\Domain\Repositories\OrderRepositoryInterface;
use App\Events\OrderCreated;
use App\Http\Requests\OrderRequest;
use App\UseCases\Order\ConfirmOrderReceptionUseCase;
use App\UseCases\Order\CreateOrderUseCase;
use App\UseCases\Order\GetOrderDetailsUseCase;
use App\UseCases\Order\ReorderPreviousPurchaseUseCase;
use App\UseCases\Order\UserOrderStatsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    private $orderRepository;

    private $createOrderUseCase;

    private $getOrderDetailsUseCase;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ?CreateOrderUseCase $createOrderUseCase = null,
        ?GetOrderDetailsUseCase $getOrderDetailsUseCase = null
    ) {
        $this->orderRepository = $orderRepository;
        $this->createOrderUseCase = $createOrderUseCase;
        $this->getOrderDetailsUseCase = $getOrderDetailsUseCase;
        $this->middleware('jwt.auth');
    }

    /**
     * Mostrar listado de pedidos del usuario
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $page = $request->input('page', 1);

        // Si se proporciona página, calcular offset
        if ($request->has('page')) {
            $offset = ($page - 1) * $limit;
        }

        $orders = $this->orderRepository->getOrdersForUser($userId, $limit, $offset);

        // Transformar las entidades a formato para el frontend
        $formattedOrders = [];
        foreach ($orders as $order) {
            $formattedOrders[] = [
                'id' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'date' => $order->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
                'total' => $order->getTotal(),
                'status' => $order->getStatus(),
                'paymentStatus' => $order->getPaymentStatus(),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $formattedOrders,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($orders), // Idealmente deberíamos tener un método para contar total sin límites
            ],
        ]);
    }

    /**
     * 🔧 MEJORADO: Crear un nuevo pedido con notificación al vendedor
     */
    public function store(OrderRequest $request)
    {
        try {
            $orderData = $request->validated();
            $orderData['user_id'] = Auth::id();

            Log::info('🛍️ OrderController: Creando nueva orden', [
                'user_id' => $orderData['user_id'],
                'has_seller_id' => isset($orderData['seller_id']),
                'seller_id' => $orderData['seller_id'] ?? null,
                'total' => $orderData['total'] ?? null,
                'items_count' => count($orderData['items'] ?? []),
            ]);

            // Crear la orden
            $order = $this->createOrderUseCase->execute($orderData);

            Log::info('✅ Orden creada exitosamente', [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'seller_id' => $order->getSellerId(),
            ]);

            // 🔥 NUEVO: Disparar evento OrderCreated para notificar al vendedor
            Log::info('🚀 Disparando evento OrderCreated', [
                'order_id' => $order->getId(),
                'user_id' => $order->getUserId(),
                'seller_id' => $order->getSellerId(),
            ]);

            event(new OrderCreated(
                $order->getId(),
                $order->getUserId(),
                $order->getSellerId(),
                [
                    'order_number' => $order->getOrderNumber(),
                    'total' => $order->getTotal(),
                    'items' => $orderData['items'] ?? [],
                ]
            ));

            Log::info('✅ Evento OrderCreated disparado exitosamente');

            return response()->json([
                'success' => true,
                'message' => 'Pedido creado correctamente',
                'data' => [
                    'id' => $order->getId(),
                    'orderNumber' => $order->getOrderNumber(),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('❌ Error creando orden', [
                'user_id' => $orderData['user_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pedido: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver detalle de un pedido específico
     */
    public function show($id)
    {
        $userId = Auth::id();

        try {
            $order = $this->orderRepository->findById($id);

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido no encontrado',
                ], 404);
            }

            // Verificar que el pedido pertenezca al usuario autenticado
            if ($order->getUserId() != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para ver este pedido',
                ], 403);
            }

            // Obtener detalles completos del pedido
            $orderDetails = $this->orderRepository->getOrderDetails($id);

            return response()->json([
                'success' => true,
                'data' => $orderDetails,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el pedido: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reordenar un pedido anterior
     */
    public function reorder(int $id, Request $request, ReorderPreviousPurchaseUseCase $reorderUseCase): JsonResponse
    {
        try {
            $userId = Auth::id();
            $result = $reorderUseCase->execute($id, $userId);

            return response()->json([
                'status' => 'success',
                'message' => 'Pedido reordenado correctamente',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Confirmar la recepción de un pedido
     */
    public function confirmReception(int $id, Request $request, ConfirmOrderReceptionUseCase $confirmUseCase): JsonResponse
    {
        try {
            $userId = Auth::id();
            $confirmed = $confirmUseCase->execute($id, $userId);

            return response()->json([
                'status' => 'success',
                'message' => 'Recepción de pedido confirmada correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Obtener estadísticas de pedidos del usuario
     */
    public function userStats(Request $request, UserOrderStatsUseCase $statsUseCase): JsonResponse
    {
        try {
            $userId = Auth::id();
            $stats = $statsUseCase->execute($userId);

            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener estadísticas: '.$e->getMessage(),
            ], 500);
        }
    }
}
