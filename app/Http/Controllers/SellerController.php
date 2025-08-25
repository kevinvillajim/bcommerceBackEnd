<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\UseCases\Seller\CreateSellerUseCase;
use App\UseCases\Seller\GetTopSellersUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SellerController extends Controller
{
    protected $createSellerUseCase;

    protected $getTopSellersUseCase;

    /**
     * Constructor
     */
    public function __construct(
        CreateSellerUseCase $createSellerUseCase,
        GetTopSellersUseCase $getTopSellersUseCase
    ) {
        $this->createSellerUseCase = $createSellerUseCase;
        $this->getTopSellersUseCase = $getTopSellersUseCase;
    }

    /**
     * Register as a seller
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'store_name' => 'required|string|min:3|max:100|unique:sellers,store_name',
                'description' => 'nullable|string|max:500',
            ]);

            $userId = Auth::id();

            // Check if user is already a seller
            $existingSeller = Seller::where('user_id', $userId)->first();
            if ($existingSeller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is already a seller',
                ], 400);
            }

            // Create seller
            $sellerEntity = $this->createSellerUseCase->execute(
                $userId,
                $request->store_name,
                $request->description
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Seller account created successfully. Pending approval.',
                'data' => $sellerEntity->toArray(),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error registering seller: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error registering seller: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get seller information (only for active sellers)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSellerInfo()
    {
        try {
            $userId = Auth::id();
            $seller = Seller::where('user_id', $userId)->first();

            if (! $seller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not registered as a seller',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'seller' => $seller,
                    'store_status' => $seller->status,
                    'verification_level' => $seller->verification_level,
                    'total_sales' => $seller->total_sales,
                    'average_rating' => $seller->getAverageRatingAttribute(),
                    'total_ratings' => $seller->getTotalRatingsAttribute(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting seller info: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching seller information',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get top sellers by rating
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopSellersByRating(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $sellers = $this->getTopSellersUseCase->executeByRating($limit);

            return response()->json([
                'status' => 'success',
                'data' => array_map(function ($seller) {
                    return $seller->toArray();
                }, $sellers),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting top sellers by rating: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching top sellers',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get top sellers by sales
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopSellersBySales(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $sellers = $this->getTopSellersUseCase->executeBySales($limit);

            return response()->json([
                'status' => 'success',
                'data' => array_map(function ($seller) {
                    return $seller->toArray();
                }, $sellers),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting top sellers by sales: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching top sellers',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get featured sellers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeaturedSellers(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $sellers = $this->getTopSellersUseCase->executeFeatured($limit);

            return response()->json([
                'status' => 'success',
                'data' => array_map(function ($seller) {
                    return $seller->toArray();
                }, $sellers),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting featured sellers: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching featured sellers',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene el seller_id a partir de un user_id
     *
     * @param  int  $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSellerIdByUserId($userId)
    {
        $seller = \App\Models\Seller::where('user_id', $userId)->first();

        if ($seller) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'seller_id' => $seller->id,
                ],
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Vendedor no encontrado para este usuario',
        ], 404);
    }

    /**
     * Obtiene la lista de todos los vendedores activos
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveSellers()
    {
        try {
            $sellers = Seller::where('status', 'active')
                ->with('user:id,name,email')
                ->get()
                ->map(function ($seller) {
                    return [
                        'seller_id' => $seller->id,
                        'user_id' => $seller->user_id,
                        'store_name' => $seller->store_name,
                        'user_name' => $seller->user->name ?? 'Sin nombre',
                        'verification_level' => $seller->verification_level,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $sellers,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener vendedores activos: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar la solicitud: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar información de la tienda del vendedor
     */
    /**
     * Actualizar información de la tienda del vendedor
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStoreInfo(Request $request)
    {
        try {
            $userId = Auth::id();

            // Verificar que el usuario sea un vendedor
            $seller = Seller::where('user_id', $userId)->first();

            if (! $seller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no registrado como vendedor',
                ], 404);
            }

            // Validar los datos de entrada
            $request->validate([
                'store_name' => 'sometimes|string|min:3|max:100|unique:sellers,store_name,'.$seller->id,
                'description' => 'sometimes|string|max:500',
            ]);

            $updated = false;

            // Actualizar solo los campos proporcionados
            if ($request->has('store_name') && $request->store_name !== null && $request->store_name !== '') {
                $seller->store_name = $request->store_name;
                $updated = true;
            }

            if ($request->has('description')) {
                $seller->description = $request->description; // Permitir descripción vacía
                $updated = true;
            }

            if (! $updated) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se proporcionaron datos válidos para actualizar',
                ], 400);
            }

            $seller->save();

            // Obtener el seller actualizado con relaciones
            $seller->load('user');

            Log::info('Información de tienda actualizada exitosamente', [
                'seller_id' => $seller->id,
                'user_id' => $userId,
                'updated_fields' => $request->only(['store_name', 'description']),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Información de tienda actualizada correctamente',
                'data' => [
                    'id' => $seller->id,
                    'store_name' => $seller->store_name,
                    'description' => $seller->description,
                    'status' => $seller->status,
                    'verification_level' => $seller->verification_level,
                    'updated_at' => $seller->updated_at->toISOString(),
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation error en updateStoreInfo', [
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error actualizando información de tienda: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno al actualizar información de tienda',
            ], 500);
        }
    }

    /**
     * Get seller dashboard data
     */
    public function dashboard()
    {
        try {
            $userId = Auth::id();
            $seller = Seller::where('user_id', $userId)->first();

            if (! $seller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no registrado como vendedor',
                ], 404);
            }

            $sellerId = $seller->id;

            // 1. Ventas totales (suma del total de todas las seller_orders)
            $totalSales = \App\Models\SellerOrder::where('seller_id', $sellerId)
                ->sum('total');

            // 2. Pedidos totales
            $totalOrders = \App\Models\SellerOrder::where('seller_id', $sellerId)
                ->count();

            // 3. Productos activos y total
            $totalProducts = \App\Models\Product::where('seller_id', $sellerId)
                ->count();
            $activeProducts = \App\Models\Product::where('seller_id', $sellerId)
                ->where('published', 1)
                ->where('status', 'active')
                ->count();

            // 4. Promedio de valoraciones del seller
            $avgRating = $seller->average_rating ?? 0;

            // 5. Pedidos pendientes
            $pendingOrders = \App\Models\SellerOrder::where('seller_id', $sellerId)
                ->where('status', 'pending')
                ->count();

            // 6. Pedidos recientes (últimos 5)
            $recentOrders = \App\Models\SellerOrder::where('seller_id', $sellerId)
                ->with(['order.user'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($sellerOrder) {
                    $user = $sellerOrder->order?->user;

                    return [
                        'id' => $sellerOrder->order_number,
                        'date' => $sellerOrder->created_at->format('Y-m-d'),
                        'customer' => $user ? $user->name : 'Cliente',
                        'total' => $sellerOrder->total,
                        'status' => $sellerOrder->status === 'completed' ? 'Completed' :
                                   ($sellerOrder->status === 'processing' ? 'Processing' :
                                   ($sellerOrder->status === 'shipped' ? 'Shipped' : ucfirst($sellerOrder->status))),
                    ];
                });

            // 7. Productos más vendidos (top 5)
            $topProducts = \App\Models\OrderItem::select(
                'product_id',
                'product_name',
                \Illuminate\Support\Facades\DB::raw('SUM(quantity) as sold'),
                \Illuminate\Support\Facades\DB::raw('SUM(subtotal) as revenue')
            )
                ->where('seller_id', $sellerId)
                ->groupBy('product_id', 'product_name')
                ->orderBy('sold', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->product_id,
                        'name' => $item->product_name,
                        'sold' => (int) $item->sold,
                        'revenue' => (float) $item->revenue,
                    ];
                });

            // Para vendedores INACTIVOS, mostrar todo en ceros (datos se ocultan, no se borran)
            if ($seller->status === 'inactive') {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'seller_status' => $seller->status,
                        'is_suspended' => true,
                        'is_inactive' => true,
                        'status_reason' => $seller->status_reason,
                        'stats' => [
                            'total_sales' => 0.0,
                            'total_orders' => 0,
                            'active_products' => 0,
                            'total_products' => 0,
                            'average_rating' => 0.0,
                            'pending_orders' => 0,
                        ],
                        'recent_orders' => [],
                        'top_products' => [],
                    ],
                ]);
            }

            // Para vendedores ACTIVOS y SUSPENDIDOS, mostrar datos reales
            return response()->json([
                'status' => 'success',
                'data' => [
                    'seller_status' => $seller->status, // Agregar status del vendedor
                    'is_suspended' => in_array($seller->status, ['suspended', 'inactive']),
                    'is_inactive' => $seller->status === 'inactive',
                    'status_reason' => $seller->status_reason ?? null,
                    'stats' => [
                        'total_sales' => (float) $totalSales,
                        'total_orders' => $totalOrders,
                        'active_products' => $activeProducts,
                        'total_products' => $totalProducts,
                        'average_rating' => (float) $avgRating,
                        'pending_orders' => $pendingOrders,
                    ],
                    'recent_orders' => $recentOrders,
                    'top_products' => $topProducts,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting seller dashboard: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos del dashboard',
            ], 500);
        }
    }
}
