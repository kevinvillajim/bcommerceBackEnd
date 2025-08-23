<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\UseCases\Shipping\CreateShippingUseCase;
use App\UseCases\Shipping\TrackShippingUseCase;
use App\UseCases\Shipping\UpdateShippingStatusUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{
    private TrackShippingUseCase $trackShippingUseCase;

    private UpdateShippingStatusUseCase $updateShippingStatusUseCase;

    private CreateShippingUseCase $createShippingUseCase;

    public function __construct(
        TrackShippingUseCase $trackShippingUseCase,
        UpdateShippingStatusUseCase $updateShippingStatusUseCase,
        CreateShippingUseCase $createShippingUseCase
    ) {
        $this->trackShippingUseCase = $trackShippingUseCase;
        $this->updateShippingStatusUseCase = $updateShippingStatusUseCase;
        $this->createShippingUseCase = $createShippingUseCase;
    }

    /**
     * Método para rastrear un envío a través de su número de tracking
     */
    public function track(string $trackingNumber): JsonResponse
    {
        return $this->trackShipment($trackingNumber);
    }

    /**
     * Obtener información de seguimiento de un envío
     */
    public function trackShipment(string $trackingNumber): JsonResponse
    {
        try {
            $result = $this->trackShippingUseCase->execute($trackingNumber);

            return response()->json([
                'status' => $result['status'],
                'data' => $result['data'] ?? null,
                'message' => $result['message'] ?? null,
            ], $result['status'] === 'success' ? 200 : 400);
        } catch (\Exception $e) {
            Log::error('Error en track shipment: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Obtener historial completo de un envío
     */
    public function getShippingHistory(string $trackingNumber): JsonResponse
    {
        try {
            $result = $this->trackShippingUseCase->getShippingHistory($trackingNumber);

            return response()->json([
                'status' => $result['status'],
                'data' => $result['data'] ?? null,
                'message' => $result['message'] ?? null,
            ], $result['status'] === 'success' ? 200 : 400);
        } catch (\Exception $e) {
            Log::error('Error en shipping history: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Obtener ruta de envío para mostrar en mapa
     */
    public function getShippingRoute(string $trackingNumber): JsonResponse
    {
        try {
            $result = $this->trackShippingUseCase->getShippingRoute($trackingNumber);

            return response()->json([
                'status' => $result['status'],
                'data' => $result['data'] ?? null,
                'message' => $result['message'] ?? null,
            ], $result['status'] === 'success' ? 200 : 400);
        } catch (\Exception $e) {
            Log::error('Error en shipping route: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Actualizar estado de un envío (endpoint interno)
     */
    public function updateShippingStatus(Request $request): JsonResponse
    {
        try {
            // Validar los datos de entrada
            $validated = $request->validate([
                'shipping_id' => 'required|integer',
                'status' => 'required|string',
                'location' => 'nullable|array',
                'details' => 'nullable|string',
            ]);

            // Preparar datos para el use case
            $data = [
                'shipping_id' => $validated['shipping_id'],
                'status' => $validated['status'],
                'current_location' => $validated['location'] ?? null,
                'details' => $validated['details'] ?? null,
            ];

            $result = $this->updateShippingStatusUseCase->execute($data);

            return response()->json([
                'status' => $result['status'],
                'message' => $result['message'] ?? null,
                'data' => $result['data'] ?? null,
            ], $result['status'] === 'success' ? 200 : 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en update shipping status: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Endpoint para recibir actualizaciones desde API externa de transportistas
     */
    public function externalStatusUpdate(Request $request): JsonResponse
    {
        try {
            // Verificar API key para autenticación
            $apiKey = $request->header('X-API-KEY');
            $configApiKey = config('services.shipping_api.key');

            if (empty($apiKey) || $apiKey !== $configApiKey) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Acceso no autorizado',
                ], 401);
            }

            // Procesar la actualización
            $data = $request->all();
            $result = $this->updateShippingStatusUseCase->execute($data);

            if ($result['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Shipping status updated successfully',
                    'tracking_number' => $data['tracking_number'] ?? null,
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'] ?? 'Error updating shipping status',
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error en external status update: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Simular eventos de envío para pruebas
     */
    public function simulateShippingEvents(Request $request, string $trackingNumber): JsonResponse
    {
        // Verificar que estamos en entorno de desarrollo o testing
        if (! app()->environment(['local', 'development', 'testing'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Esta funcionalidad solo está disponible en entornos de desarrollo',
            ], 403);
        }

        try {
            $days = $request->input('days', 5);
            $result = $this->updateShippingStatusUseCase->simulateShippingEvents($trackingNumber, $days);

            return response()->json($result, $result['status'] === 'success' ? 200 : 400);
        } catch (\Exception $e) {
            Log::error('Error en simulate shipping events: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Obtener listado de envíos para administradores
     */
    public function getAdminShippingsList(Request $request): JsonResponse
    {
        try {
            // Obtener parámetros de filtrado y paginación
            $status = $request->input('status');
            $carrier = $request->input('carrier');
            $dateFrom = $request->input('dateFrom');
            $dateTo = $request->input('dateTo');
            $search = $request->input('search');
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);

            // Consultar la base de datos usando Eloquent
            $query = \App\Models\Shipping::query();

            // Aplicar filtros si existen
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            if ($carrier && $carrier !== 'all') {
                $query->where('carrier_name', $carrier);
            }

            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('tracking_number', 'like', "%{$search}%")
                        ->orWhereHas('order', function ($orderQuery) use ($search) {
                            $orderQuery->where('order_number', 'like', "%{$search}%");
                        });
                });
            }

            // Ordenar por fecha de creación descendente por defecto
            $query->orderBy('created_at', 'desc');

            // Cargar relaciones necesarias
            $query->with(['order', 'order.user']);

            // Paginar resultados
            $shippings = $query->paginate($limit, ['*'], 'page', $page);

            // Obtener datos para la respuesta
            $data = $shippings->items();

            // Añadir información adicional si es necesaria
            foreach ($data as &$shipping) {
                // Asegurarse de que la ubicación actual esté en el formato correcto
                if (is_string($shipping->current_location)) {
                    $shipping->current_location = json_decode($shipping->current_location, true);
                }

                // Añadir información del cliente si está disponible
                if ($shipping->order && $shipping->order->user) {
                    $shipping->user_name = $shipping->order->user->name;
                    $shipping->user_id = $shipping->order->user->id;
                } else {
                    $shipping->user_name = 'Cliente';
                    $shipping->user_id = null;
                }
            }

            return response()->json([
                'data' => $data,
                'pagination' => [
                    'currentPage' => $shippings->currentPage(),
                    'totalPages' => $shippings->lastPage(),
                    'totalItems' => $shippings->total(),
                    'itemsPerPage' => $shippings->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener listado de envíos: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener listado de envíos: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalles de un envío específico
     */
    public function getAdminShippingDetail($id): JsonResponse
    {
        try {
            // Buscar el envío por ID
            $shipping = \App\Models\Shipping::with(['order', 'order.user'])->find($id);

            if (! $shipping) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Envío no encontrado',
                ], 404);
            }

            // Asegurarse de que current_location sea un objeto
            if (is_string($shipping->current_location)) {
                $shipping->current_location = json_decode($shipping->current_location, true);
            }

            // Obtener historial de este envío
            $history = $shipping->history()->orderBy('created_at', 'desc')->get();

            // Añadir información del cliente
            if ($shipping->order && $shipping->order->user) {
                $shipping->user_name = $shipping->order->user->name;
                $shipping->user_id = $shipping->order->user->id;
            } else {
                $shipping->user_name = 'Cliente';
                $shipping->user_id = null;
            }

            // Crear un array para incluir el historial junto con los datos del envío
            $shippingData = $shipping->toArray();
            $shippingData['history'] = $history;

            return response()->json([
                'status' => 'success',
                'data' => $shippingData,
            ]);
        } catch (\Exception $e) {
            Log::error("Error al obtener detalles del envío {$id}: ".$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener detalles del envío: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener listado de envíos para el vendedor autenticado
     */
    public function getSellerShippingsList(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $seller = Seller::where('user_id', $user->id)->first();

            if (! $seller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no es vendedor autorizado',
                ], 403);
            }

            // Obtener parámetros de filtrado y paginación
            $status = $request->input('status');
            $carrier = $request->input('carrier');
            $dateFrom = $request->input('dateFrom');
            $dateTo = $request->input('dateTo');
            $search = $request->input('search');
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);

            // 🔧 SOLUCIÓN: Consultar envíos seleccionando explícitamente el ID de la orden
            $query = \App\Models\Shipping::query()
                ->join('orders', 'shippings.order_id', '=', 'orders.id')
                ->where('orders.seller_id', $seller->id)
                ->select([
                    'shippings.*',
                    'orders.id as real_order_id', // Seleccionar explícitamente el ID real de la orden
                    'orders.order_number',
                    'orders.shipping_data',
                ]);

            // Aplicar filtros
            if ($status && $status !== 'all') {
                $query->where('shippings.status', $status);
            }

            if ($carrier && $carrier !== 'all') {
                $query->where('shippings.carrier_name', $carrier);
            }

            if ($dateFrom) {
                $query->whereDate('shippings.created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('shippings.created_at', '<=', $dateTo);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('shippings.tracking_number', 'like', "%{$search}%")
                        ->orWhere('orders.order_number', 'like', "%{$search}%");
                });
            }

            // Ordenar por fecha de creación descendente
            $query->orderBy('shippings.created_at', 'desc');

            // Cargar relaciones necesarias
            $query->with(['order', 'order.user']);

            // Paginar resultados
            $shippings = $query->paginate($limit, ['*'], 'page', $page);

            // Preparar datos para la respuesta
            $data = $shippings->items();
            foreach ($data as $index => &$shipping) {

                // Asegurar formato correcto de ubicación
                if (is_string($shipping->current_location)) {
                    $shipping->current_location = json_decode($shipping->current_location, true);
                }

                // 🔧 MÉTODO 1: Usar real_order_id si existe
                if (isset($shipping->real_order_id)) {
                    $shipping->orderId = $shipping->real_order_id;
                }
                // 🔧 MÉTODO 2: Usar order_id de la relación si existe
                elseif ($shipping->order && $shipping->order->id) {
                    $shipping->orderId = $shipping->order->id;
                }
                // 🔧 MÉTODO 3: Usar order_id del shipping como fallback
                else {
                    $shipping->orderId = $shipping->order_id;
                }

                // Añadir información del cliente
                if ($shipping->order && $shipping->order->user) {
                    $shipping->user_name = $shipping->order->user->name;
                    $shipping->user_id = $shipping->order->user->id;
                    // Ya tenemos order_number del SELECT, pero podemos usarlo desde la relación también
                    if (! isset($shipping->order_number) && $shipping->order) {
                        $shipping->order_number = $shipping->order->order_number;
                    }
                }

                // Añadir dirección de envío desde shipping_data
                if ($shipping->order && $shipping->order->shipping_data) {
                    $shippingData = is_string($shipping->order->shipping_data)
                        ? json_decode($shipping->order->shipping_data, true)
                        : $shipping->order->shipping_data;

                    $shipping->shipping_address = [
                        $shippingData['address'] ?? '',
                        $shippingData['city'] ?? '',
                        $shippingData['state'] ?? '',
                        $shippingData['country'] ?? '',
                    ];
                    $shipping->shipping_address = implode(', ', array_filter($shipping->shipping_address));
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $shippings->currentPage(),
                    'last_page' => $shippings->lastPage(),
                    'total' => $shippings->total(),
                    'per_page' => $shippings->perPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener listado de envíos del vendedor: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener listado de envíos',
            ], 500);
        }
    }

    /**
     * Obtener detalles de un envío específico del vendedor
     */
    public function getSellerShippingDetail($id): JsonResponse
    {
        try {
            // Obtener el seller_id del usuario autenticado
            $user = Auth::user();
            $seller = Seller::where('user_id', $user->id)->first();

            if (! $seller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no es vendedor autorizado',
                ], 403);
            }

            // Buscar el envío que pertenezca al vendedor
            $shipping = \App\Models\Shipping::with(['order', 'order.user'])
                ->join('orders', 'shippings.order_id', '=', 'orders.id')
                ->where('orders.seller_id', $seller->id)
                ->where('shippings.id', $id)
                ->select('shippings.*')
                ->first();

            if (! $shipping) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Envío no encontrado',
                ], 404);
            }

            // Formato de respuesta igual que getAdminShippingDetail
            if (is_string($shipping->current_location)) {
                $shipping->current_location = json_decode($shipping->current_location, true);
            }

            $history = $shipping->history()->orderBy('created_at', 'desc')->get();

            if ($shipping->order && $shipping->order->user) {
                $shipping->user_name = $shipping->order->user->name;
                $shipping->user_id = $shipping->order->user->id;
                $shipping->order_number = $shipping->order->order_number;
            }

            $shippingData = $shipping->toArray();
            $shippingData['history'] = $history;

            return response()->json([
                'status' => 'success',
                'data' => $shippingData,
            ]);

        } catch (\Exception $e) {
            Log::error("Error al obtener detalles del envío {$id} del vendedor: ".$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener detalles del envío',
            ], 500);
        }
    }

    /**
     * Actualizar estado de envío del vendedor
     */
    public function updateSellerShippingStatus(Request $request, $id): JsonResponse
    {
        try {
            // Obtener el seller_id del usuario autenticado
            $user = Auth::user();
            $seller = Seller::where('user_id', $user->id)->first();

            if (! $seller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no es vendedor autorizado',
                ], 403);
            }

            // Buscar el envío que pertenezca al vendedor
            $shipping = \App\Models\Shipping::join('orders', 'shippings.order_id', '=', 'orders.id')
                ->where('orders.seller_id', $seller->id)
                ->where('shippings.id', $id)
                ->select('shippings.*')
                ->first();

            if (! $shipping) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Envío no encontrado',
                ], 404);
            }

            // Validar datos
            $validated = $request->validate([
                'status' => 'required|string',
                'location' => 'nullable|array',
                'details' => 'nullable|string',
            ]);

            // Usar el use case existente
            $data = [
                'shipping_id' => $shipping->id,
                'status' => $validated['status'],
                'current_location' => $validated['location'] ?? null,
                'details' => $validated['details'] ?? null,
            ];

            $result = $this->updateShippingStatusUseCase->execute($data);

            return response()->json($result, $result['status'] === 'success' ? 200 : 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de envío del vendedor: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar estado de envío',
            ], 500);
        }
    }
}
