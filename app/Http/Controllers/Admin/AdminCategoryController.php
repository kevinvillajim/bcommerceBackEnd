<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminCategoryController extends Controller
{
    /**
     * Lista categorías para admin - SÚPER SIMPLE con conteos reales
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->input('limit', 15);
            $offset = (int) $request->input('offset', 0);
            $searchTerm = $request->input('term', '');

            // ✅ CONSULTA DIRECTA - Solo datos necesarios con conteos reales
            $query = DB::table('categories as c')
                ->leftJoin(
                    DB::raw('(SELECT category_id, COUNT(*) as product_count 
                             FROM products 
                             WHERE deleted_at IS NULL 
                             GROUP BY category_id) as p'),
                    'c.id', '=', 'p.category_id'
                )
                ->leftJoin('categories as parent', 'c.parent_id', '=', 'parent.id')
                ->select([
                    'c.id',
                    'c.name',
                    'c.slug',
                    'c.description',
                    'c.parent_id',
                    'c.is_active',
                    'c.featured',
                    'c.order',
                    'c.image',
                    'c.created_at',
                    'parent.name as parent_name',
                    DB::raw('COALESCE(p.product_count, 0) as product_count'),
                ]);

            // Filtro de búsqueda
            if (! empty($searchTerm)) {
                $query->where('c.name', 'LIKE', "%{$searchTerm}%");
            }

            // Contar y paginar
            $total = $query->count();
            $categories = $query
                ->orderBy('c.order', 'asc')
                ->orderBy('c.name', 'asc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            // Formateo mínimo
            $formattedCategories = $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'parent_id' => $category->parent_id,
                    'parent_name' => $category->parent_name,
                    'is_active' => (bool) $category->is_active,
                    'featured' => (bool) $category->featured,
                    'order' => (int) $category->order,
                    'image' => $category->image,
                    'product_count' => (int) $category->product_count,
                    'created_at' => $category->created_at,
                ];
            });

            return response()->json([
                'data' => $formattedCategories,
                'meta' => [
                    'total' => $total,
                    'count' => $formattedCategories->count(),
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            Log::error('Error AdminCategoryController::index: '.$e->getMessage());

            return response()->json([
                'error' => 'Error obteniendo categorías',
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de categorías
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = [
                'total_categories' => DB::table('categories')->count(),
                'active_categories' => DB::table('categories')->where('is_active', true)->count(),
                'featured_categories' => DB::table('categories')->where('featured', true)->count(),
                'main_categories' => DB::table('categories')->whereNull('parent_id')->count(),
                'subcategories' => DB::table('categories')->whereNotNull('parent_id')->count(),
                'categories_with_products' => DB::table('categories')
                    ->join('products', 'categories.id', '=', 'products.category_id')
                    ->whereNull('products.deleted_at')
                    ->distinct('categories.id')
                    ->count('categories.id'),
                'empty_categories' => DB::table('categories as c')
                    ->leftJoin('products as p', function ($join) {
                        $join->on('c.id', '=', 'p.category_id')
                            ->whereNull('p.deleted_at');
                    })
                    ->whereNull('p.id')
                    ->count(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error AdminCategoryController::getStats: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error obteniendo estadísticas',
            ], 500);
        }
    }

    /**
     * Actualización parcial de categoría (toggles) - Usando AdminPatchRequest
     */
    public function partialUpdate(\App\Http\Requests\AdminPatchRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            Log::info("AdminCategoryController::partialUpdate - Iniciando actualización para categoría {$id}");
            Log::info('AdminCategoryController::partialUpdate - Datos validados:', $validated);

            $category = Category::find($id);
            if (! $category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Categoría no encontrada',
                ], 404);
            }

            // Validar que no se asigne como padre de sí misma
            if (isset($validated['parent_id']) && $validated['parent_id'] === $id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Una categoría no puede ser padre de sí misma',
                ], 422);
            }

            DB::transaction(function () use ($category, $validated) {
                foreach ($validated as $field => $value) {
                    $oldValue = $category->$field;
                    $category->$field = $value;
                    
                    Log::info("AdminCategoryController::partialUpdate - Actualizando {$field}:", [
                        'anterior' => $oldValue,
                        'nuevo' => $value,
                    ]);
                }
                $category->save();
            });

            // Refrescar el modelo
            $category->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Categoría actualizada exitosamente',
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'parent_id' => $category->parent_id,
                    'is_active' => (bool) $category->is_active,
                    'featured' => (bool) $category->featured,
                    'order' => (int) $category->order,
                    'updated_at' => $category->updated_at?->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error AdminCategoryController::partialUpdate: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error actualizando categoría',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Eliminar categoría
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $category = Category::find($id);
            if (! $category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Categoría no encontrada',
                ], 404);
            }

            // Verificar si tiene productos
            $productCount = DB::table('products')
                ->where('category_id', $id)
                ->whereNull('deleted_at')
                ->count();

            if ($productCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "No se puede eliminar. Tiene {$productCount} productos asignados.",
                ], 400);
            }

            // Verificar si tiene subcategorías
            $subcategoryCount = DB::table('categories')->where('parent_id', $id)->count();
            if ($subcategoryCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "No se puede eliminar. Tiene {$subcategoryCount} subcategorías.",
                ], 400);
            }

            $category->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Categoría eliminada exitosamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error AdminCategoryController::destroy: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error eliminando categoría',
            ], 500);
        }
    }

    /**
     * Obtener categorías para dropdown (principales)
     */
    public function getMainCategories(): JsonResponse
    {
        try {
            $categories = DB::table('categories')
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $categories,
            ]);

        } catch (\Exception $e) {
            Log::error('Error AdminCategoryController::getMainCategories: '.$e->getMessage());

            return response()->json([
                'error' => 'Error obteniendo categorías principales',
            ], 500);
        }
    }

    /**
     * Obtener detalles de una categoría específica con productos
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Obtener información de la categoría
            $category = DB::table('categories as c')
                ->leftJoin('categories as parent', 'c.parent_id', '=', 'parent.id')
                ->leftJoin(
                    DB::raw('(SELECT category_id, COUNT(*) as product_count 
                             FROM products 
                             WHERE deleted_at IS NULL 
                             GROUP BY category_id) as p'),
                    'c.id', '=', 'p.category_id'
                )
                ->where('c.id', $id)
                ->select([
                    'c.id',
                    'c.name',
                    'c.slug',
                    'c.description',
                    'c.parent_id',
                    'c.is_active',
                    'c.featured',
                    'c.order',
                    'c.image',
                    'c.created_at',
                    'c.updated_at',
                    'parent.name as parent_name',
                    DB::raw('COALESCE(p.product_count, 0) as product_count'),
                ])
                ->first();

            if (! $category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Categoría no encontrada',
                ], 404);
            }

            // Obtener subcategorías
            $subcategories = DB::table('categories')
                ->where('parent_id', $id)
                ->select('id', 'name', 'slug', 'is_active')
                ->orderBy('order', 'asc')
                ->orderBy('name', 'asc')
                ->get();

            // Obtener algunos productos de muestra (los primeros 5)
            $sampleProducts = DB::table('products')
                ->where('category_id', $id)
                ->whereNull('deleted_at')
                ->select('id', 'name', 'price', 'images', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    // Procesar imagen principal
                    $images = [];
                    if ($product->images) {
                        $decoded = json_decode($product->images, true);
                        $images = is_array($decoded) ? $decoded : [];
                    }

                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                        'main_image' => ! empty($images) ? $images[0] : null,
                        'created_at' => $product->created_at,
                    ];
                });

            $categoryData = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'parent_id' => $category->parent_id,
                'parent_name' => $category->parent_name,
                'is_active' => (bool) $category->is_active,
                'featured' => (bool) $category->featured,
                'order' => (int) $category->order,
                'image' => $category->image,
                'product_count' => (int) $category->product_count,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
                'subcategories' => $subcategories,
                'sample_products' => $sampleProducts,
                // ✅ Para redirección al ProductsPage con filtro
                'products_filter_url' => "/products?categoryId={$category->id}&categoryName=".urlencode($category->name),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $categoryData,
            ]);

        } catch (\Exception $e) {
            Log::error('Error AdminCategoryController::show: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error obteniendo detalles de categoría',
            ], 500);
        }
    }
}
