<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPatchRequest;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\UseCases\Product\CreateProductUseCase;
use App\UseCases\Product\UpdateProductUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminProductController extends Controller
{
    private ProductRepositoryInterface $productRepository;

    private ProductFormatter $productFormatter;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductFormatter $productFormatter
    ) {
        $this->productRepository = $productRepository;
        $this->productFormatter = $productFormatter;
    }

    /**
     * Lista productos admin - SÚPER SIMPLE solo datos necesarios
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->input('limit', 15);
            $offset = (int) $request->input('offset', 0);
            $searchTerm = $request->input('term', '');
            $categoryId = $request->input('categoryId');
            $sellerId = $request->input('sellerId');
            $status = $request->input('status');
            $published = $request->input('published');
            $inStock = $request->input('inStock');

            // ✅ CONSULTA COMPLETA - Incluyendo ratings, sales y seller info completa
            $query = DB::table('products as p')
                ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
                ->leftJoin('sellers as s', 'p.seller_id', '=', 's.id')
                ->leftJoin('users as u', 's.user_id', '=', 'u.id')
                ->whereNull('p.deleted_at')
                ->select([
                    'p.id',
                    'p.name',
                    'p.price',
                    'p.stock',
                    'p.images',
                    'p.featured',
                    'p.published',
                    'p.status',
                    'p.discount_percentage',
                    'p.created_at',
                    'p.seller_id as product_seller_id',
                    'p.user_id as product_user_id',
                    // Categoría
                    'c.name as category_name',
                    // Seller información completa
                    's.id as seller_db_id',
                    's.store_name as seller_name',
                    's.status as seller_status',
                    'u.id as user_db_id',
                    'u.name as user_name',
                    'u.email as user_email',
                    // Agregamos subconsultas para ratings y sales
                    DB::raw('(SELECT AVG(rating) FROM ratings r WHERE r.product_id = p.id AND r.status = "approved") as average_rating'),
                    DB::raw('(SELECT COUNT(*) FROM ratings r WHERE r.product_id = p.id AND r.status = "approved") as ratings_count'),
                    DB::raw('(SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi WHERE oi.product_id = p.id) as total_orders'),
                    DB::raw('(SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.id) as total_units_sold'),
                ]);

            // Filtros simples
            if (! empty($searchTerm)) {
                $query->where('p.name', 'LIKE', "%{$searchTerm}%");
            }

            if ($categoryId) {
                $query->where('p.category_id', $categoryId);
            }

            if ($sellerId) {
                $query->where('p.seller_id', $sellerId);
            }

            if ($status && $status !== 'all') {
                $query->where('p.status', $status);
            }

            if ($published !== null && $published !== 'all') {
                $query->where('p.published', (bool) $published);
            }

            if ($inStock !== null) {
                if ($inStock) {
                    $query->where('p.stock', '>', 0);
                } else {
                    $query->where('p.stock', '<=', 0);
                }
            }

            // Contar y paginar
            $total = $query->count();
            $products = $query
                ->orderBy('p.created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            // Formateo completo con ratings, sales y seller info
            $formattedProducts = $products->map(function ($product) {
                // Procesar imágenes
                $images = [];
                if ($product->images) {
                    $decoded = json_decode($product->images, true);
                    $images = is_array($decoded) ? $decoded : [];
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'stock' => (int) $product->stock,
                    'featured' => (bool) $product->featured,
                    'published' => (bool) $product->published,
                    'status' => $product->status,
                    'discount_percentage' => (float) $product->discount_percentage,
                    'main_image' => ! empty($images) ? $images[0] : null,
                    'created_at' => $product->created_at,
                    'is_in_stock' => $product->stock > 0,
                    'final_price' => $product->discount_percentage > 0
                        ? $product->price * (1 - $product->discount_percentage / 100)
                        : $product->price,

                    // ✅ Información completa de categoría
                    'category_name' => $product->category_name ?? 'Sin categoría',

                    // ✅ Información completa del seller - FORMATO COMPATIBLE CON FRONTEND
                    'seller_id' => $product->product_seller_id,
                    'seller' => $product->product_seller_id ? [
                        'id' => $product->product_seller_id,
                        'store_name' => $product->seller_name ?? 'Tienda desconocida',
                        'name' => $product->seller_name ?? 'Tienda desconocida',
                        'status' => $product->seller_status,
                    ] : null,
                    'user' => $product->product_user_id ? [
                        'id' => $product->product_user_id,
                        'name' => $product->user_name ?? 'Usuario desconocido',
                        'email' => $product->user_email,
                    ] : null,

                    // ✅ Información de ratings - MÚLTIPLES NOMBRES PARA COMPATIBILIDAD
                    'average_rating' => $product->average_rating ? round((float) $product->average_rating, 1) : 0,
                    'calculated_rating' => $product->average_rating ? round((float) $product->average_rating, 1) : 0,
                    'rating' => $product->average_rating ? round((float) $product->average_rating, 1) : 0,
                    'ratings_count' => (int) $product->ratings_count,
                    'calculated_rating_count' => (int) $product->ratings_count,
                    'rating_count' => (int) $product->ratings_count,
                    'ratingCount' => (int) $product->ratings_count,
                    'has_ratings' => $product->ratings_count > 0,

                    // ✅ Información de ventas - MÚLTIPLES NOMBRES PARA COMPATIBILIDAD
                    'total_orders' => (int) $product->total_orders,
                    'sales_count' => (int) $product->total_orders,
                    'total_units_sold' => (int) $product->total_units_sold,
                    'total_quantity_sold' => (int) $product->total_units_sold,
                    'has_sales' => $product->total_orders > 0,
                ];
            });

            return response()->json([
                'data' => $formattedProducts,
                'meta' => [
                    'total' => $total,
                    'count' => $formattedProducts->count(),
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            Log::error('Error AdminProductController::index: '.$e->getMessage());

            return response()->json([
                'error' => 'Error obteniendo productos',
            ], 500);
        }
    }

    /**
     * Crear producto como administrador
     */
    public function store(ProductRequest $request, CreateProductUseCase $createProductUseCase): JsonResponse
    {
        try {
            $data = $request->validated();

            // Si no se especifica user_id, usar el del admin actual
            if (! isset($data['user_id'])) {
                $data['user_id'] = Auth::id();
            }

            $files = $this->handleFiles($request);
            $product = $createProductUseCase->execute($data, $files);

            $formattedProduct = $this->productFormatter->formatComplete($product);

            return response()->json([
                'status' => 'success',
                'message' => 'Producto creado exitosamente',
                'data' => $formattedProduct,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error en AdminProductController::store: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Actualizar producto completamente como administrador
     */
    public function update(ProductRequest $request, int $id, UpdateProductUseCase $updateProductUseCase): JsonResponse
    {
        try {
            $data = $request->validated();
            $files = $this->handleFiles($request);

            $product = $updateProductUseCase->execute($id, $data, $files);

            if (! $product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado',
                ], 404);
            }

            $formattedProduct = $this->productFormatter->formatComplete($product);

            return response()->json([
                'status' => 'success',
                'message' => 'Producto actualizado exitosamente',
                'data' => $formattedProduct,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en AdminProductController::update: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * MÉTODO CRÍTICO: Actualización parcial para toggles
     */
    public function partialUpdate(AdminPatchRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            Log::info('AdminProductController::partialUpdate - Iniciando actualización', [
                'product_id' => $id,
                'validated_data' => $validated,
                'user_id' => Auth::id(),
            ]);

            // Usar transacción para garantizar consistencia
            $result = DB::transaction(function () use ($id, $validated) {
                // Buscar el producto usando Eloquent directamente
                $product = Product::find($id);

                if (! $product) {
                    throw new \Exception('Producto no encontrado');
                }

                Log::info('Producto encontrado - Estado antes de actualizar', [
                    'id' => $product->id,
                    'featured_before' => $product->featured,
                    'published_before' => $product->published,
                    'status_before' => $product->status,
                ]);

                // Actualizar solo los campos enviados
                foreach ($validated as $field => $value) {
                    $product->$field = $value;
                    Log::info("Actualizando campo: {$field} = ".var_export($value, true));
                }

                // Forzar actualización del timestamp
                $product->updated_at = now();

                // Guardar con verificación explícita
                $saved = $product->save();

                if (! $saved) {
                    throw new \Exception('No se pudo guardar el producto en la base de datos');
                }

                // Recargar desde la base de datos para verificar
                $product->refresh();

                Log::info('Producto actualizado - Estado después de guardar', [
                    'id' => $product->id,
                    'featured_after' => $product->featured,
                    'published_after' => $product->published,
                    'status_after' => $product->status,
                    'updated_at' => $product->updated_at,
                ]);

                return $product;
            });

            // Formatear respuesta con datos frescos de la base de datos
            $responseData = [
                'id' => $result->id,
                'name' => $result->name,
                'slug' => $result->slug,
                'price' => $result->price,
                'final_price' => $result->final_price,
                'rating' => $result->rating,
                'rating_count' => $result->rating_count,
                'discount_percentage' => $result->discount_percentage,
                'main_image' => $result->main_image,
                'category_id' => $result->category_id,
                'stock' => $result->stock,
                'is_in_stock' => $result->is_in_stock,
                'featured' => (bool) $result->featured,
                'published' => (bool) $result->published,
                'status' => $result->status,
                'tags' => $result->tags,
                'seller_id' => $result->seller_id,
                'images' => $result->images,
                'updated_at' => $result->updated_at,
            ];

            Log::info('AdminProductController::partialUpdate - Actualización exitosa', [
                'product_id' => $id,
                'response_data' => $responseData,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
                'data' => $responseData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en AdminProductController::partialUpdate', [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar producto: '.$e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Obtener información de impacto antes de eliminar producto
     */
    public function getDeletionImpact(int $id): JsonResponse
    {
        try {
            $product = Product::withTrashed()->find($id);

            if (! $product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado',
                ], 404);
            }

            // Contar todas las relaciones que serán eliminadas
            $impact = [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'is_soft_deleted' => $product->trashed(),
                ],
                'relations' => [
                    'cart_items' => [
                        'count' => DB::table('cart_items')->where('product_id', $id)->count(),
                        'description' => 'Productos en carritos de compras de usuarios',
                        'impact' => 'Los usuarios perderán este producto de sus carritos',
                    ],
                    'order_items' => [
                        'count' => DB::table('order_items')->where('product_id', $id)->count(),
                        'description' => 'Productos en pedidos ya realizados',
                        'impact' => 'Se perderá el historial de este producto en pedidos completados',
                        'orders_affected' => DB::table('order_items')
                            ->join('orders', 'order_items.order_id', '=', 'orders.id')
                            ->where('order_items.product_id', $id)
                            ->distinct()
                            ->count('orders.id'),
                    ],
                    'favorites' => [
                        'count' => DB::table('favorites')->where('product_id', $id)->count(),
                        'description' => 'Producto marcado como favorito',
                        'impact' => 'Los usuarios perderán este producto de sus favoritos',
                    ],
                    'ratings' => [
                        'count' => DB::table('ratings')->where('product_id', $id)->count(),
                        'description' => 'Calificaciones y reseñas del producto',
                        'impact' => 'Se perderán todas las calificaciones y comentarios',
                    ],
                    'volume_discounts' => [
                        'count' => DB::table('volume_discounts')->where('product_id', $id)->count(),
                        'description' => 'Descuentos por volumen configurados',
                        'impact' => 'Se eliminarán las reglas de descuento por cantidad',
                    ],
                    'user_interactions' => [
                        'count' => DB::table('user_interactions')->where('product_id', $id)->count(),
                        'description' => 'Interacciones de usuarios (vistas, clics, etc.)',
                        'impact' => 'Se perderá el historial de interacciones para recomendaciones',
                    ],
                ],
            ];

            // Calcular totales
            $totalRelations = array_sum(array_column($impact['relations'], 'count'));
            $hasOrderHistory = $impact['relations']['order_items']['count'] > 0;

            $impact['summary'] = [
                'total_relations' => $totalRelations,
                'has_order_history' => $hasOrderHistory,
                'severity' => $hasOrderHistory ? 'HIGH' : ($totalRelations > 0 ? 'MEDIUM' : 'LOW'),
                'warning_message' => $this->generateWarningMessage($impact['relations'], $hasOrderHistory),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $impact,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en AdminProductController::getDeletionImpact: '.$e->getMessage(), [
                'product_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener información de eliminación',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Generar mensaje de advertencia personalizado
     */
    private function generateWarningMessage(array $relations, bool $hasOrderHistory): string
    {
        $warnings = [];

        if ($relations['order_items']['count'] > 0) {
            $warnings[] = "• {$relations['order_items']['count']} elementos en pedidos realizados (afectará {$relations['order_items']['orders_affected']} pedidos)";
        }

        if ($relations['cart_items']['count'] > 0) {
            $warnings[] = "• {$relations['cart_items']['count']} productos en carritos de usuarios";
        }

        if ($relations['favorites']['count'] > 0) {
            $warnings[] = "• {$relations['favorites']['count']} marcados como favorito";
        }

        if ($relations['ratings']['count'] > 0) {
            $warnings[] = "• {$relations['ratings']['count']} calificaciones y reseñas";
        }

        if ($relations['volume_discounts']['count'] > 0) {
            $warnings[] = "• {$relations['volume_discounts']['count']} reglas de descuento por volumen";
        }

        if ($relations['user_interactions']['count'] > 0) {
            $warnings[] = "• {$relations['user_interactions']['count']} interacciones de usuarios";
        }

        if (empty($warnings)) {
            return 'Este producto no tiene relaciones. Se puede eliminar sin impacto.';
        }

        $message = "⚠️ ADVERTENCIA: Esta acción eliminará PERMANENTEMENTE:\n\n".implode("\n", $warnings);

        if ($hasOrderHistory) {
            $message .= "\n\n🚨 CRÍTICO: Se perderá el historial de pedidos. Esta acción NO se puede deshacer.";
        }

        $message .= "\n\n¿Estás seguro de que deseas continuar?";

        return $message;
    }

    /**
     * Eliminar producto como administrador (Hard Delete - Eliminación Permanente)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // ✅ CAMBIO: Buscar incluso productos soft-deleted para poder eliminarlos permanentemente
            $product = Product::withTrashed()->find($id);

            if (! $product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado',
                ], 404);
            }

            Log::info('AdminProductController::destroy - Eliminando producto PERMANENTEMENTE', [
                'product_id' => $id,
                'product_name' => $product->name,
                'admin_user' => Auth::id(),
                'was_soft_deleted' => $product->trashed(),
            ]);

            // ✅ ELIMINAR TODAS LAS REFERENCIAS ANTES DE ELIMINAR EL PRODUCTO
            DB::transaction(function () use ($id) {
                Log::info('Eliminando todas las referencias del producto', ['product_id' => $id]);

                // Eliminar referencias usando DB directo (funciona con soft-deleted)
                $deletedCarts = DB::table('cart_items')->where('product_id', $id)->delete();
                $deletedFavorites = DB::table('favorites')->where('product_id', $id)->delete();
                $deletedRatings = DB::table('ratings')->where('product_id', $id)->delete();
                $deletedVolume = DB::table('volume_discounts')->where('product_id', $id)->delete();
                $deletedInteractions = DB::table('user_interactions')->where('product_id', $id)->delete();

                // ⚠️ CRÍTICO: Eliminar order_items (historial de pedidos)
                $orderItemsCount = DB::table('order_items')->where('product_id', $id)->count();
                $deletedOrders = 0;
                if ($orderItemsCount > 0) {
                    Log::warning('Eliminando order_items - SE PERDERÁ HISTORIAL', [
                        'product_id' => $id,
                        'order_items_count' => $orderItemsCount,
                    ]);
                    $deletedOrders = DB::table('order_items')->where('product_id', $id)->delete();
                }

                Log::info('Referencias eliminadas', [
                    'product_id' => $id,
                    'cart_items' => $deletedCarts,
                    'favorites' => $deletedFavorites,
                    'ratings' => $deletedRatings,
                    'volume_discounts' => $deletedVolume,
                    'user_interactions' => $deletedInteractions,
                    'order_items' => $deletedOrders,
                ]);

                // Finalmente eliminar el producto con DB directo
                $result = DB::table('products')->where('id', $id)->delete();

                Log::info('Producto eliminado de la tabla', [
                    'product_id' => $id,
                    'result' => $result,
                ]);

                return $result;
            });

            $success = true;

            if (! $success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al eliminar el producto permanentemente',
                ], 500);
            }

            Log::info('AdminProductController::destroy - Producto eliminado PERMANENTEMENTE', [
                'product_id' => $id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Producto eliminado permanentemente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error en AdminProductController::destroy: '.$e->getMessage(), [
                'product_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de productos para admin
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = [
                'total_products' => $this->productRepository->count([]),
                'active_products' => $this->productRepository->count(['status' => 'active', 'published' => true]),
                'featured_products' => $this->productRepository->count(['featured' => true]),
                'published_products' => $this->productRepository->count(['published' => true]),
                'draft_products' => $this->productRepository->count(['status' => 'draft']),
                'out_of_stock' => $this->productRepository->count(['stock' => 0]),
                'products_with_discount' => $this->productRepository->count(['min_discount' => 1]),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en AdminProductController::getStats: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener estadísticas',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Construir filtros específicos para admin (sin restricciones de published/status)
     */
    private function buildAdminFiltersFromRequest(Request $request): array
    {
        $filters = [];

        // Búsqueda por término
        if ($request->filled('term')) {
            $filters['search'] = $request->input('term');
        }

        // Categoría
        if ($request->filled('categoryId')) {
            $filters['category_id'] = (int) $request->input('categoryId');
        }

        // Estado específico (sin valores por defecto)
        if ($request->filled('status')) {
            $filters['status'] = $request->input('status');
        }

        // Publicado específico (sin valores por defecto)
        if ($request->has('published')) {
            $filters['published'] = $request->boolean('published');
        }

        // Featured específico
        if ($request->has('featured')) {
            $filters['featured'] = $request->boolean('featured');
        }

        // Stock
        if ($request->filled('inStock')) {
            if ($request->boolean('inStock')) {
                $filters['stock_min'] = 1;
            } else {
                $filters['stock'] = 0;
            }
        }

        // Vendedor
        if ($request->filled('sellerId')) {
            $filters['seller_id'] = (int) $request->input('sellerId');
        }

        // Ordenamiento
        $sortBy = $request->input('sortBy', 'created_at');
        $sortDir = $request->input('sortDir', 'desc');
        $filters['sortBy'] = $sortBy;
        $filters['sortDir'] = $sortDir;

        Log::info('buildAdminFiltersFromRequest', [
            'request_params' => $request->all(),
            'built_filters' => $filters,
        ]);

        return $filters;
    }

    /**
     * Manejo seguro de archivos
     */
    private function handleFiles($request): array
    {
        $files = [];

        try {
            if (method_exists($request, 'hasFile') && method_exists($request, 'file')) {
                if ($request->hasFile('images')) {
                    $files['images'] = $request->file('images');
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error procesando archivos: '.$e->getMessage());
        }

        return $files;
    }

    /**
     * Obtener sellers básicos para dropdowns (solo lo esencial)
     */
    public function getSellersSimple(): JsonResponse
    {
        try {
            $sellers = DB::table('sellers as s')
                ->join('users as u', 's.user_id', '=', 'u.id')
                ->select([
                    's.id',
                    's.store_name',
                    'u.name as user_name',
                ])
                ->where('s.status', 'active')
                ->orderBy('s.store_name')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $sellers->map(function ($seller) {
                    return [
                        'id' => $seller->id,
                        'store_name' => $seller->store_name,
                        'name' => $seller->store_name, // Para compatibilidad con frontend
                        'user_name' => $seller->user_name,
                        'display_name' => "{$seller->store_name} ({$seller->user_name})",
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ AdminProductController: Error obteniendo sellers simples', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error obteniendo sellers',
            ], 500);
        }
    }

    /**
     * Obtener información completa de sellers (para listados detallados)
     */
    public function getSellers(): JsonResponse
    {
        try {
            $sellers = DB::table('sellers as s')
                ->join('users as u', 's.user_id', '=', 'u.id')
                ->select([
                    's.id',
                    's.store_name',
                    's.status',
                    'u.name as user_name',
                    'u.email as user_email',
                ])
                ->where('s.status', 'active')
                ->orderBy('s.store_name')
                ->get();

            return response()->json([
                'data' => $sellers->map(function ($seller) {
                    return [
                        'id' => $seller->id,
                        'store_name' => $seller->store_name,
                        'user_name' => $seller->user_name,
                        'email' => $seller->user_email,
                        'display_name' => "{$seller->store_name} ({$seller->user_name})",
                        'status' => $seller->status,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ AdminProductController: Error obteniendo sellers', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error obteniendo sellers',
            ], 500);
        }
    }
}
