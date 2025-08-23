<?php

namespace App\Http\Controllers;

use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\RatingRepositoryInterface;
use App\Events\RatingCreated;
use App\Http\Requests\StoreRatingRequest;
use App\Models\Rating;
use App\Models\Seller;
use App\UseCases\Rating\GetPendingRatingsUseCase;
use App\UseCases\Rating\RateProductUseCase;
use App\UseCases\Rating\RateSellerUseCase;
use App\UseCases\Rating\RateUserUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    private RateSellerUseCase $rateSellerUseCase;

    private RateProductUseCase $rateProductUseCase;

    private RateUserUseCase $rateUserUseCase;

    private RatingRepositoryInterface $ratingRepository;

    private OrderRepositoryInterface $orderRepository;

    /**
     * Constructor
     */
    public function __construct(
        RateSellerUseCase $rateSellerUseCase,
        RateProductUseCase $rateProductUseCase,
        RateUserUseCase $rateUserUseCase,
        RatingRepositoryInterface $ratingRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->rateSellerUseCase = $rateSellerUseCase;
        $this->rateProductUseCase = $rateProductUseCase;
        $this->rateUserUseCase = $rateUserUseCase;
        $this->ratingRepository = $ratingRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Mostrar detalles de una valoración específica
     */
    public function show(int $id)
    {
        try {
            $userId = Auth::id();

            // 🔧 NUEVO: Cargar valoración con TODAS las relaciones necesarias
            $rating = Rating::with([
                'user:id,name,avatar,email',
                'product:id,name,images,price,status',
                'seller:id,store_name,user_id,status',
                'order:id,order_number,status,total,created_at',
            ])->find($id);

            if (! $rating) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Valoración no encontrada',
                ], 404);
            }

            // Verificar permisos: solo el autor, el vendedor afectado o admin
            $canView = false;

            // Si es el autor de la valoración
            if ($rating->user_id === $userId) {
                $canView = true;
            }

            // Si es el vendedor que recibió la valoración
            if ($rating->seller_id) {
                $seller = Seller::where('user_id', $userId)->first();
                if ($seller && $seller->id === $rating->seller_id) {
                    $canView = true;
                }
            }

            // Los admins pueden ver todo (se verifica en middleware si está presente)
            $user = Auth::user();
            if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
                $canView = true;
            }

            if (! $canView) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No tienes permiso para ver esta valoración',
                ], 403);
            }

            // 🔧 NUEVO: Preparar datos enriquecidos para el frontend
            $ratingData = [
                'id' => $rating->id,
                'rating' => $rating->rating,
                'title' => $rating->title,
                'comment' => $rating->comment,
                'type' => $rating->type,
                'status' => $rating->status,
                'user_id' => $rating->user_id,
                'seller_id' => $rating->seller_id,
                'product_id' => $rating->product_id,
                'order_id' => $rating->order_id,
                'created_at' => $rating->created_at,
                'updated_at' => $rating->updated_at,
                'is_verified_purchase' => $rating->is_verified_purchase ?? false,

                // 🔧 RELACIONES ENRIQUECIDAS
                'user' => $rating->user ? [
                    'id' => $rating->user->id,
                    'name' => $rating->user->name,
                    'avatar' => $rating->user->avatar,
                ] : null,

                'product' => $rating->product ? [
                    'id' => $rating->product->id,
                    'name' => $rating->product->name,
                    'image' => is_array($rating->product->images) && count($rating->product->images) > 0
                        ? $rating->product->images[0]
                        : (is_string($rating->product->images)
                            ? (json_decode($rating->product->images, true)[0] ?? null)
                            : null),
                    'price' => $rating->product->price,
                    'status' => $rating->product->status,
                ] : null,

                'seller' => $rating->seller ? [
                    'id' => $rating->seller->id,
                    'store_name' => $rating->seller->store_name,
                    'user_id' => $rating->seller->user_id,
                    'status' => $rating->seller->status,
                ] : null,

                'order_details' => $rating->order ? [
                    'id' => $rating->order->id,
                    'order_number' => $rating->order->order_number,
                    'status' => $rating->order->status,
                    'total' => $rating->order->total,
                    'created_at' => $rating->order->created_at,
                ] : null,

                // Respuesta del vendedor si existe
                'seller_response' => $rating->seller_response ? [
                    'id' => $rating->seller_response->id,
                    'text' => $rating->seller_response->response_text,
                    'created_at' => $rating->seller_response->created_at,
                ] : null,
            ];

            return response()->json([
                'status' => 'success',
                'data' => $ratingData,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al obtener valoración: '.$e->getMessage(), [
                'rating_id' => $id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la valoración: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getOrderRatings(int $orderId)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Verificar que la orden existe y pertenece al usuario
            $order = $this->orderRepository->findById($orderId);

            if (! $order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Orden no encontrada',
                ], 404);
            }

            // Verificar permisos: admin, comprador, o vendedor de la orden
            $canView = false;
            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                $canView = true;
            } elseif ($order->getUserId() === $user->id) {
                $canView = true;
            } else {
                // Verificar si el usuario es un vendedor relacionado con esta orden
                $seller = $user->seller; // Asume que la relación 'seller' existe en el modelo User
                if ($seller) {
                    if ($order->getSellerId() === $seller->id) {
                        $canView = true;
                    } elseif (method_exists($order, 'hasMultipleSellers') && $order->hasMultipleSellers()) {
                        $sellerOrders = $order->getSellerOrders();
                        if ($sellerOrders !== null) {
                            foreach ($sellerOrders as $sellerOrder) {
                                // Asumiendo que sellerOrder es un objeto o array con seller_id
                                $sellerOrderId = is_array($sellerOrder) ? $sellerOrder['seller_id'] : $sellerOrder->seller_id;
                                if ($sellerOrderId === $seller->id) {
                                    $canView = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if (! $canView) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No tienes permiso para ver las valoraciones de esta orden',
                ], 403);
            }

            // 🔧 CORREGIDO: Obtener valoraciones CON todas las relaciones necesarias
            $ratings = Rating::with([
                'user:id,name,avatar',
                'product:id,name,images,price',
                'seller:id,store_name,user_id',
            ])
                ->where('order_id', $orderId)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $ratings->toArray(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al obtener valoraciones de la orden: '.$e->getMessage(), [
                'order_id' => $orderId,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener valoraciones de la orden: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rate a seller
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function rateSeller(StoreRatingRequest $request)
    {
        try {
            $userId = Auth::id();
            $sellerId = $request->get('seller_id');
            $productId = $request->get('product_id'); // ✅ AGREGAR ESTA LÍNEA
            $rating = $request->get('rating');
            $orderId = $request->get('order_id');
            $title = $request->get('title');
            $comment = $request->get('comment');

            // Validación adicional de orden
            if ($orderId) {
                $order = $this->orderRepository->findById($orderId);

                if (! $order) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Orden no encontrada',
                    ], 404);
                }

                if ($order->getUserId() !== $userId) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No tienes permiso para valorar esta orden',
                    ], 403);
                }

                if (! in_array($order->getStatus(), ['completed', 'delivered', 'shipped'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Solo puedes valorar órdenes completadas, entregadas o enviadas',
                    ], 400);
                }

                // Verificar que el vendedor está relacionado con esta orden
                $orderHasSeller = false;

                if ($order->getSellerId() === $sellerId) {
                    $orderHasSeller = true;
                } elseif ($order->hasMultipleSellers()) {
                    $sellerOrders = $order->getSellerOrders();
                    if ($sellerOrders !== null) {
                        foreach ($sellerOrders as $sellerOrder) {
                            if ($sellerOrder->seller_id === $sellerId) {
                                $orderHasSeller = true;
                                break;
                            }
                        }
                    }
                }

                if (! $orderHasSeller) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Este vendedor no está relacionado con la orden especificada',
                    ], 400);
                }

                // ✅ VALIDAR EL PRODUCT_ID SI SE PROPORCIONA
                if ($productId) {
                    $orderHasProduct = $this->orderRepository->orderContainsProduct($orderId, $productId);
                    if (! $orderHasProduct) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Este producto no está en la orden especificada',
                        ], 400);
                    }
                }
            }

            $ratingEntity = $this->rateSellerUseCase->execute(
                $userId,
                $sellerId,
                $rating,
                $orderId,
                $title,
                $comment,
                $productId // ✅ PASAR EL PRODUCT_ID
            );

            // 🔧 CORREGIDO: Usar el tipo correcto del rating entity
            event(new RatingCreated($ratingEntity->getId(), $ratingEntity->getType()));

            return response()->json([
                'status' => 'success',
                'message' => 'Vendedor valorado correctamente',
                'data' => $ratingEntity->toArray(),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ha ocurrido un error al valorar al vendedor: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rate a product
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Rate a product
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function rateProduct(StoreRatingRequest $request)
    {
        try {
            $userId = Auth::id();
            $productId = $request->get('product_id');
            $rating = $request->get('rating');
            $orderId = $request->get('order_id');
            $title = $request->get('title');
            $comment = $request->get('comment');

            // Validación adicional de orden
            if ($orderId) {
                $order = $this->orderRepository->findById($orderId);

                if (! $order) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Orden no encontrada',
                    ], 404);
                }

                if ($order->getUserId() !== $userId) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No tienes permiso para valorar esta orden',
                    ], 403);
                }

                if (! in_array($order->getStatus(), ['completed', 'delivered', 'shipped'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Solo puedes valorar órdenes completadas, entregadas o enviadas',
                    ], 400);
                }

                $orderHasProduct = $this->orderRepository->orderContainsProduct($orderId, $productId);

                if (! $orderHasProduct) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Este producto no está en la orden especificada',
                    ], 400);
                }
            }

            $ratingEntity = $this->rateProductUseCase->execute(
                $userId,
                $productId,
                $rating,
                $orderId,
                $title,
                $comment
            );

            // 🔧 CORREGIDO: Usar el tipo correcto del rating entity
            event(new RatingCreated($ratingEntity->getId(), $ratingEntity->getType()));

            return response()->json([
                'status' => 'success',
                'message' => 'Producto valorado correctamente',
                'data' => $ratingEntity->toArray(),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ha ocurrido un error al valorar el producto: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rate a user (for sellers only)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function rateUser(StoreRatingRequest $request)
    {
        try {
            $sellerId = Auth::user()->seller->id;
            $userId = $request->input('user_id');
            $rating = $request->input('rating');
            $orderId = $request->input('order_id');
            $title = $request->input('title');
            $comment = $request->input('comment');

            $ratingEntity = $this->rateUserUseCase->execute(
                $sellerId,
                $userId,
                $rating,
                $orderId,
                $title,
                $comment
            );

            // 🔧 CORREGIDO: Usar el tipo correcto del rating entity
            event(new RatingCreated($ratingEntity->getId(), $ratingEntity->getType()));

            return response()->json([
                'status' => 'success',
                'message' => 'User rated successfully',
                'data' => $ratingEntity->toArray(),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while rating the user',
            ], 500);
        }
    }

    /**
     * Get ratings for a seller
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSellerRatings(Request $request, int $sellerId)
    {
        try {
            $limit = $request->get('limit', 10);
            $offset = $request->get('offset', 0);

            $ratings = $this->ratingRepository->getSellerRatings($sellerId, $limit, $offset);
            $averageRating = $this->ratingRepository->getAverageSellerRating($sellerId);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'average_rating' => $averageRating,
                    'ratings' => array_map(function ($rating) {
                        return $rating->toArray();
                    }, $ratings),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching seller ratings',
            ], 500);
        }
    }

    /**
     * Get ratings for a product
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductRatings(Request $request, int $productId)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);

            // 🔧 CORREGIDO: Usar Eloquent con JOIN para obtener datos de usuario
            $ratings = Rating::with(['user:id,name,avatar'])
                ->where('product_id', $productId)
                ->where('type', 'user_to_product')
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $averageRating = $this->ratingRepository->getAverageProductRating($productId);
            $totalRatings = Rating::where('product_id', $productId)
                ->where('type', 'user_to_product')
                ->where('status', 'approved')
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'average_rating' => $averageRating,
                    'ratings' => $ratings->toArray(),
                ],
                'meta' => [
                    'total' => $totalRatings,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($totalRatings / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener valoraciones del producto: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ratings given by the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyGivenRatings(Request $request)
    {
        try {
            $userId = Auth::id();
            $limit = $request->get('limit', 10);
            $offset = $request->get('offset', 0);

            $ratings = $this->ratingRepository->getUserGivenRatings($userId, $limit, $offset);

            return response()->json([
                'status' => 'success',
                'data' => array_map(function ($rating) {
                    return $rating->toArray();
                }, $ratings),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching your ratings',
            ], 500);
        }
    }

    /**
     * Get ratings received by the authenticated user (if seller)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyReceivedRatings(Request $request)
    {
        try {
            $userId = Auth::id();
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            $status = $request->get('status');

            // 🔧 NUEVO: Obtener valoraciones CON todas las relaciones para vendedores
            $query = Rating::with([
                'user:id,name,avatar',
                'product:id,name,images,price',
                'seller:id,store_name',
                'order:id,order_number,status,created_at',
            ])
                ->where('type', 'user_to_seller');

            // Obtener el seller del usuario actual
            $seller = Seller::where('user_id', $userId)->first();
            if (! $seller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No eres un vendedor registrado',
                ], 403);
            }

            $query->where('seller_id', $seller->id);

            // Filtrar por estado si se especifica
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            // Obtener resultados paginados
            $ratings = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // Contar total
            $total = Rating::where('type', 'user_to_seller')
                ->where('seller_id', $seller->id)
                ->when($status && $status !== 'all', function ($q) use ($status) {
                    return $q->where('status', $status);
                })
                ->count();

            // Calcular estadísticas
            $averageRating = Rating::where('type', 'user_to_seller')
                ->where('seller_id', $seller->id)
                ->where('status', 'approved')
                ->avg('rating') ?? 0;

            // Contar por rating
            $ratingCounts = [];
            for ($i = 1; $i <= 5; $i++) {
                $ratingCounts[(string) $i] = Rating::where('type', 'user_to_seller')
                    ->where('seller_id', $seller->id)
                    ->where('status', 'approved')
                    ->where('rating', $i)
                    ->count();
            }

            return response()->json([
                'status' => 'success',
                'data' => $ratings->toArray(),
                'meta' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage),
                    'average_rating' => round($averageRating, 2),
                    'rating_counts' => $ratingCounts,
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al obtener valoraciones recibidas: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener valoraciones recibidas: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a rating (admin only)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        try {
            $success = $this->ratingRepository->delete($id);

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Rating deleted successfully',
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rating not found',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while deleting the rating',
            ], 500);
        }
    }

    /**
     * Obtener productos y vendedores pendientes de valoración
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingRatings(Request $request, GetPendingRatingsUseCase $getPendingRatingsUseCase)
    {
        try {
            $userId = Auth::id();
            $includeRated = $request->get('include_rated', false);

            $result = $getPendingRatingsUseCase->execute($userId, $includeRated);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener valoraciones pendientes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validar que una orden existe, pertenece al usuario, y está relacionada con el vendedor/producto
     *
     * @param  int  $orderId  ID de la orden a validar
     * @param  int  $userId  ID del usuario actual
     * @param  int|null  $sellerId  ID del vendedor (opcional)
     * @param  int|null  $productId  ID del producto (opcional)
     * @return array Array con el resultado de la validación ['isValid' => bool, 'message' => string, 'code' => int]
     */
    private function validateOrderForRating(int $orderId, int $userId, ?int $sellerId = null, ?int $productId = null): array
    {
        // Verificar que la orden existe
        $order = $this->orderRepository->findById($orderId);

        if (! $order) {
            return [
                'isValid' => false,
                'message' => 'Orden no encontrada',
                'code' => 404,
            ];
        }

        // Verificar que la orden pertenece al usuario
        if ($order->getUserId() !== $userId) {
            return [
                'isValid' => false,
                'message' => 'No tienes permiso para valorar esta orden',
                'code' => 403,
            ];
        }

        // Verificar que la orden está completada, entregada o enviada
        if (! in_array($order->getStatus(), ['completed', 'delivered', 'shipped'])) {
            return [
                'isValid' => false,
                'message' => 'Solo puedes valorar órdenes completadas, entregadas o enviadas',
                'code' => 400,
            ];
        }

        // Si se proporciona un ID de vendedor, verificar que está relacionado con la orden
        if ($sellerId !== null) {
            $orderHasSeller = false;

            if ($order->getSellerId() === $sellerId) {
                $orderHasSeller = true;
            } elseif (method_exists($order, 'hasMultipleSellers') && $order->hasMultipleSellers()) {
                // Verificar en las órdenes de vendedor
                $sellerOrders = $order->getSellerOrders();
                if ($sellerOrders !== null) {
                    foreach ($sellerOrders as $sellerOrder) {
                        if ($sellerOrder->seller_id === $sellerId) {
                            $orderHasSeller = true;
                            break;
                        }
                    }
                }
            }

            if (! $orderHasSeller) {
                return [
                    'isValid' => false,
                    'message' => 'Este vendedor no está relacionado con la orden especificada',
                    'code' => 400,
                ];
            }
        }

        // Si se proporciona un ID de producto, verificar que está en la orden
        if ($productId !== null) {
            $orderHasProduct = $this->orderRepository->orderContainsProduct($orderId, $productId);

            if (! $orderHasProduct) {
                return [
                    'isValid' => false,
                    'message' => 'Este producto no está en la orden especificada',
                    'code' => 400,
                ];
            }
        }

        // Si llegamos aquí, todo está bien
        return [
            'isValid' => true,
            'message' => 'Validación exitosa',
            'code' => 200,
        ];
    }
}
