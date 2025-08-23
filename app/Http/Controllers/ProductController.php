<?php

namespace App\Http\Controllers;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Http\Requests\AdminPatchRequest;
use App\Http\Requests\ProductRequest;
use App\UseCases\Product\CreateProductUseCase;
use App\UseCases\Product\GetProductDetailsUseCase;
use App\UseCases\Product\IncrementProductViewUseCase;
use App\UseCases\Product\SearchProductsUseCase;
use App\UseCases\Product\UpdateProductUseCase;
use App\UseCases\Recommendation\GenerateRecommendationsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    private ProductFormatter $productFormatter;

    private CategoryRepositoryInterface $categoryRepository;

    private ProductRepositoryInterface $productRepository;

    public function __construct(
        ProductFormatter $productFormatter,
        CategoryRepositoryInterface $categoryRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->productFormatter = $productFormatter;
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * Muestra una lista de productos con soporte avanzado para filtros.
     */
    public function index(Request $request, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $term = $request->input('term', '');
        $limit = $request->input('limit', 12);
        $offset = $request->input('offset', 0);
        $page = $request->input('page', 1);
        $userId = Auth::id();

        // Si se proporciona página, calcular offset
        if ($request->has('page')) {
            $offset = ($page - 1) * $limit;
        }

        // Construir filtros a partir de parámetros de la petición
        $filters = $this->buildFiltersFromRequest($request);

        // Clave de caché basada en los parámetros
        $cacheKey = 'products_'.md5(json_encode([
            'term' => $term,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
            'page' => $page,
        ]));

        // Solo almacenar en caché si no hay usuario (para no afectar a las recomendaciones personalizadas)
        $result = $userId ? null : Cache::get($cacheKey);

        if (! $result) {
            $result = $searchProductsUseCase->execute($term, $filters, $limit, $offset, $userId);

            // Guardar en caché solo para usuarios no autenticados
            if (! $userId) {
                Cache::put($cacheKey, $result, 60 * 5); // 5 minutos
            }
        }

        // Añadir meta de paginación
        $result['meta']['page'] = $page;
        $result['meta']['pages'] = ceil($result['meta']['total'] / $limit);

        return response()->json($result);
    }

    /**
     * Maneja archivos de forma segura verificando métodos disponibles
     *
     * @param  mixed  $request
     */
    private function handleFiles($request): array
    {
        $files = [];

        try {
            Log::info('🔍 Analizando request para archivos', [
                'request_type' => get_class($request),
                'has_hasFile_method' => method_exists($request, 'hasFile'),
                'has_file_method' => method_exists($request, 'file'),
            ]);

            // Verificar que $request tiene los métodos necesarios
            if (method_exists($request, 'hasFile') && method_exists($request, 'file')) {

                Log::info('✅ Request tiene métodos necesarios');

                if ($request->hasFile('images')) {
                    $uploadedFiles = $request->file('images');

                    Log::info('📁 Archivos encontrados', [
                        'files_type' => gettype($uploadedFiles),
                        'is_array' => is_array($uploadedFiles),
                        'count' => is_array($uploadedFiles) ? count($uploadedFiles) : 1,
                    ]);

                    $files['images'] = $uploadedFiles;
                } else {
                    Log::info('❌ No hay archivos con clave "images"');
                }

                // Verificar también 'image' singular
                if ($request->hasFile('image')) {
                    $uploadedFile = $request->file('image');
                    Log::info('📁 Archivo singular encontrado', [
                        'file_type' => gettype($uploadedFile),
                    ]);

                    // Si no hay images pero sí image, convertir a array
                    if (empty($files['images'])) {
                        $files['images'] = [$uploadedFile];
                    }
                }
            } else {
                Log::warning('⚠️ Request no tiene métodos hasFile/file');
            }
        } catch (\Exception $e) {
            Log::error('❌ Error procesando archivos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        Log::info('📤 Resultado final de handleFiles', [
            'files_keys' => array_keys($files),
            'has_images' => ! empty($files['images']),
        ]);

        return $files;
    }

    /**
     * Search for products by term with advanced filtering.
     *
     * @return JsonResponse
     */
    public function search(?string $term, Request $request, SearchProductsUseCase $searchProductsUseCase)
    {
        if (empty($term)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'message' => 'No search term provided',
                    'total' => 0,
                    'count' => 0,
                ],
            ]);
        }

        $userId = Auth::id();
        $limit = $request->input('limit', 12);
        $offset = $request->input('offset', 0);
        $page = $request->input('page', 1);

        // Si se proporciona página, calcular offset
        if ($request->has('page')) {
            $offset = ($page - 1) * $limit;
        }

        // Construir filtros a partir de parámetros de la petición
        $filters = $this->buildFiltersFromRequest($request);

        // Clave de caché basada en los parámetros
        $cacheKey = 'products_search_'.md5($term.json_encode([
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]));

        // Solo almacenar en caché si no hay usuario (para no afectar a las recomendaciones personalizadas)
        $result = $userId ? null : Cache::get($cacheKey);

        if (! $result) {
            $result = $searchProductsUseCase->execute($term, $filters, $limit, $offset, $userId);

            // Guardar en caché solo para usuarios no autenticados
            if (! $userId) {
                Cache::put($cacheKey, $result, 60 * 5); // 5 minutos
            }
        }

        // Añadir meta de paginación y búsqueda
        $result['meta']['page'] = $page;
        $result['meta']['pages'] = ceil($result['meta']['total'] / $limit);
        $result['meta']['term'] = $term;

        return response()->json([
            'data' => $result['data'] ?? [],
            'meta' => $result['meta'] ?? [
                'total' => 0,
                'count' => 0,
                'term' => $term,
            ],
        ]);
    }

    /**
     * Muestra productos por categoría.
     */
    public function byCategory(int $categoryId, Request $request, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $limit = $request->input('limit', 12);
        $offset = $request->input('offset', 0);
        $page = $request->input('page', 1);
        $userId = Auth::id();
        $includeSubcategories = $request->input('includeSubcategories', false);

        // Si se proporciona página, calcular offset
        if ($request->has('page')) {
            $offset = ($page - 1) * $limit;
        }

        // Obtener la categoría para incluirla en la respuesta
        $category = $this->categoryRepository->findById($categoryId);
        if (! $category) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        // Si se incluyen subcategorías, obtener todos los IDs
        $categoryIds = [$categoryId];

        if ($includeSubcategories) {
            $subcategories = $this->categoryRepository->findSubcategories($categoryId, true);
            foreach ($subcategories as $subcategory) {
                $categoryIds[] = $subcategory->getId()->getValue();
            }
        }

        if ($includeSubcategories) {
            $filters = ['category_ids' => $categoryIds];
            $result = $searchProductsUseCase->execute('', $filters, $limit, $offset, $userId);
        } else {
            $result = $searchProductsUseCase->executeByCategory($categoryId, $limit, $offset, $userId);
        }

        // Añadir meta de paginación y categoría
        $result['meta']['page'] = $page;
        $result['meta']['pages'] = ceil($result['meta']['total'] / $limit);
        $result['meta']['category'] = $category->toArray();
        $result['meta']['includeSubcategories'] = $includeSubcategories;

        if ($includeSubcategories) {
            $result['meta']['categoryIds'] = $categoryIds;
        }

        return response()->json($result);
    }

    /**
     * Muestra productos por tags.
     */
    public function byTags(Request $request, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $tags = $request->input('tags', []);
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        if (empty($tags)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'message' => 'No tags provided',
                    'total' => 0,
                    'count' => 0,
                ],
            ]);
        }

        $limit = $request->input('limit', 12);
        $offset = $request->input('offset', 0);
        $page = $request->input('page', 1);
        $userId = Auth::id();

        // Si se proporciona página, calcular offset
        if ($request->has('page')) {
            $offset = ($page - 1) * $limit;
        }

        // Construir filtros adicionales
        $filters = $this->buildFiltersFromRequest($request);

        $result = $searchProductsUseCase->executeByTags($tags, $limit, $offset, $userId, $filters);

        // Añadir meta de paginación
        $result['meta']['page'] = $page;
        $result['meta']['pages'] = ceil($result['meta']['total'] / $limit);
        $result['meta']['tags'] = $tags;

        return response()->json($result);
    }

    /**
     * Muestra productos de un vendedor.
     */
    public function bySeller(int $sellerId, Request $request, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $filters = ['seller_id' => $sellerId];
        $limit = $request->input('limit', 12);
        $offset = $request->input('offset', 0);
        $page = $request->input('page', 1);
        $userId = Auth::id();

        // Si se proporciona página, calcular offset
        if ($request->has('page')) {
            $offset = ($page - 1) * $limit;
        }

        // Añadir filtros adicionales
        $additionalFilters = $this->buildFiltersFromRequest($request);
        $filters = array_merge($filters, $additionalFilters);

        $result = $searchProductsUseCase->execute('', $filters, $limit, $offset, $userId);

        // Añadir meta de paginación
        $result['meta']['page'] = $page;
        $result['meta']['pages'] = ceil($result['meta']['total'] / $limit);
        $result['meta']['seller_id'] = $sellerId;

        return response()->json($result);
    }

    /**
     * Muestra productos destacados.
     */
    public function featured(Request $request, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $filters = ['featured' => true];
        $limit = $request->input('limit', 12);
        $offset = $request->input('offset', 0);
        $page = $request->input('page', 1);
        $userId = Auth::id();

        // Si se proporciona página, calcular offset
        if ($request->has('page')) {
            $offset = ($page - 1) * $limit;
        }

        // Añadir filtros adicionales
        $additionalFilters = $this->buildFiltersFromRequest($request);
        $filters = array_merge($filters, $additionalFilters);

        // Clave de caché
        $cacheKey = 'products_featured_'.md5(json_encode([
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]));

        // Sólo almacenar en caché si no hay usuario
        $result = $userId ? null : Cache::get($cacheKey);

        if (! $result) {
            $result = $searchProductsUseCase->execute('', $filters, $limit, $offset, $userId);

            // Guardar en caché solo para usuarios no autenticados
            if (! $userId) {
                Cache::put($cacheKey, $result, 60 * 5); // 5 minutos
            }
        }

        // Añadir meta de paginación
        $result['meta']['page'] = $page;
        $result['meta']['pages'] = ceil($result['meta']['total'] / $limit);

        return response()->json($result);
    }

    /**
     * Obtiene productos con descuento.
     */
    public function discounted(Request $request, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $filters = ['min_discount' => 5]; // Al menos 5% de descuento
        $limit = $request->input('limit', 12);
        $offset = $request->input('offset', 0);
        $page = $request->input('page', 1);
        $userId = Auth::id();

        // Si se proporciona página, calcular offset
        if ($request->has('page')) {
            $offset = ($page - 1) * $limit;
        }

        // Añadir filtros adicionales
        $additionalFilters = $this->buildFiltersFromRequest($request);
        $filters = array_merge($filters, $additionalFilters);

        // Clave de caché
        $cacheKey = 'products_discounted_'.md5(json_encode([
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]));

        // Sólo almacenar en caché si no hay usuario
        $result = $userId ? null : Cache::get($cacheKey);

        if (! $result) {
            $result = $searchProductsUseCase->execute('', $filters, $limit, $offset, $userId);

            // Guardar en caché solo para usuarios no autenticados
            if (! $userId) {
                Cache::put($cacheKey, $result, 60 * 5); // 5 minutos
            }
        }

        // Añadir meta de paginación
        $result['meta']['page'] = $page;
        $result['meta']['pages'] = ceil($result['meta']['total'] / $limit);
        $result['meta']['min_discount'] = $filters['min_discount'];

        return response()->json($result);
    }

    /**
     * Obtiene nuevos productos (los más recientes).
     */
    public function newest(Request $request, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $filters = [
            'sortBy' => 'created_at',
            'sortDir' => 'desc',
        ];
        $limit = $request->input('limit', 12);
        $offset = $request->input('offset', 0);
        $userId = Auth::id();

        // Clave de caché
        $cacheKey = 'products_newest_'.$limit;

        // Sólo almacenar en caché si no hay usuario
        $result = $userId ? null : Cache::get($cacheKey);

        if (! $result) {
            $result = $searchProductsUseCase->execute('', $filters, $limit, $offset, $userId);

            // Guardar en caché solo para usuarios no autenticados
            if (! $userId) {
                Cache::put($cacheKey, $result, 60 * 5); // 5 minutos
            }
        }

        return response()->json($result);
    }

    /**
     * Obtiene productos populares (más vendidos y mejor valorados).
     */
    public function popular(Request $request, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $filters = [
            'sortBy' => 'sales_count',
            'sortDir' => 'desc',
        ];
        $limit = $request->input('limit', 12);
        $offset = $request->input('offset', 0);
        $userId = Auth::id();

        // Clave de caché
        $cacheKey = 'products_popular_'.$limit;

        // Sólo almacenar en caché si no hay usuario
        $result = $userId ? null : Cache::get($cacheKey);

        if (! $result) {
            $result = $searchProductsUseCase->execute('', $filters, $limit, $offset, $userId);

            // Guardar en caché solo para usuarios no autenticados
            if (! $userId) {
                Cache::put($cacheKey, $result, 60 * 5); // 5 minutos
            }
        }

        return response()->json($result);
    }

    /**
     * Almacena un nuevo producto.
     */
    public function store(ProductRequest $request, CreateProductUseCase $createProductUseCase): JsonResponse
    {
        $data = $request->validated();

        // Asignar explícitamente el user_id
        $data['user_id'] = Auth::id();

        // ✅ Usar método helper para manejo seguro de archivos
        $files = $this->handleFiles($request);

        $product = $createProductUseCase->execute($data, $files);

        // Limpiar caché
        $this->clearProductCache();

        return response()->json([
            'message' => 'Producto creado con éxito',
            'data' => $product->toArray(),
        ], 201);
    }

    /**
     * Muestra un producto específico.
     */
    public function show(int $id, GetProductDetailsUseCase $getProductDetailsUseCase): JsonResponse
    {
        $userId = Auth::id();

        // Intentar obtener de caché si no hay usuario autenticado
        $cacheKey = 'product_detail_'.$id;
        $product = $userId ? null : Cache::get($cacheKey);

        if (! $product) {
            $product = $getProductDetailsUseCase->execute($id, $userId);

            // Guardar en caché solo para usuarios no autenticados
            if (! $userId && $product) {
                Cache::put($cacheKey, $product, 60 * 10); // 10 minutos
            }
        }

        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // Formatear producto para respuesta
        $formattedProduct = $this->productFormatter->formatComplete($product);

        return response()->json([
            'data' => $formattedProduct,
        ]);
    }

    /**
     * Muestra un producto por su slug.
     */
    public function showBySlug(
        string $slug,
        GetProductDetailsUseCase $getProductDetailsUseCase,
        GenerateRecommendationsUseCase $generateRecommendationsUseCase
    ): JsonResponse {
        $userId = Auth::id();

        // Intentar obtener de caché si no hay usuario autenticado
        $cacheKey = 'product_slug_'.$slug;
        $product = $userId ? null : Cache::get($cacheKey);

        if (! $product) {
            $product = $getProductDetailsUseCase->executeBySlug($slug, $userId);

            // Guardar en caché solo para usuarios no autenticados
            if (! $userId && $product) {
                Cache::put($cacheKey, $product, 60 * 10); // 10 minutos
            }
        }

        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // Formatear producto para respuesta
        $formattedProduct = $this->productFormatter->formatComplete($product);

        $response = [
            'data' => $formattedProduct,
        ];

        // Si hay un usuario autenticado, agregar recomendaciones relacionadas
        if ($userId) {
            // Parámetros para recomendaciones
            $limit = 5;
            $excludeIds = [$product->getId()];
            $productTagsStr = json_encode($product->getTags() ?? []);

            // Intentar obtener recomendaciones de caché con namespace de usuario
            $recommendationCacheKey = "product_recommendations_{$userId}_{$product->getId()}_{$productTagsStr}_{$limit}";
            $recommendations = Cache::get($recommendationCacheKey);

            if (! $recommendations) {
                $recommendations = $generateRecommendationsUseCase->execute($userId, $limit);
                Cache::put($recommendationCacheKey, $recommendations, 60 * 15); // 15 minutos
            }

            $response['related_products'] = $recommendations;
        } else {
            // Para usuarios no autenticados, obtener productos relacionados por categoría
            $categoryId = $product->getCategoryId();
            $excludeIds = [$product->getId()];
            $limit = 5;

            // Intentar obtener de caché
            $relatedCacheKey = "product_related_by_category_{$categoryId}_".implode('_', $excludeIds)."_{$limit}";
            $relatedProducts = Cache::get($relatedCacheKey);

            if (! $relatedProducts) {
                $relatedProducts = $this->productRepository->findProductsByCategory($categoryId, $excludeIds, $limit);
                $relatedProducts = array_map(fn ($product) => $this->productFormatter->formatForApi($product), $relatedProducts);
                Cache::put($relatedCacheKey, $relatedProducts, 60 * 30); // 30 minutos
            }

            $response['related_products'] = $relatedProducts;
        }

        return response()->json($response);
    }

    /**
     * Actualiza un producto específico.
     */
    public function update(ProductRequest $request, int $id, UpdateProductUseCase $updateProductUseCase): JsonResponse
    {
        $data = $request->validated();

        // Verificar si el producto existe
        $repository = app(ProductRepositoryInterface::class);
        $product = $repository->findById($id);

        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // Asegurarnos de que el usuario es el propietario o un admin usando métodos existentes
        $user = Auth::user();
        if (! $user || ($product->getUserId() !== $user->id && ! $user->isAdmin())) {
            return response()->json(['message' => 'No autorizado para actualizar este producto'], 403);
        }

        // ✅ Usar método helper para manejo seguro de archivos
        $files = $this->handleFiles($request);

        $updatedProduct = $updateProductUseCase->execute($id, $data, $files);

        // Limpiar caché
        $this->clearProductCache($id, $product->getSlug());

        // Formatear el producto para la respuesta, incluyendo la categoría completa
        $formattedProduct = $this->productFormatter->formatComplete($updatedProduct);

        return response()->json([
            'message' => 'Producto actualizado con éxito',
            'data' => $formattedProduct,
        ]);
    }

    /**
     * Elimina un producto específico.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $repository = app(ProductRepositoryInterface::class);

        // Verificar que el usuario sea el dueño del producto o un admin
        $product = $repository->findById($id);

        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // ✅ Corregido: Usar métodos existentes de User.php
        $user = Auth::user();
        if (! $user || ($product->getUserId() !== $user->id && ! $user->isAdmin())) {
            return response()->json(['message' => 'No autorizado para eliminar este producto'], 403);
        }

        $success = $repository->delete($id);

        if (! $success) {
            return response()->json(['message' => 'Error al eliminar el producto'], 500);
        }

        // Limpiar caché
        $this->clearProductCache($id, $product->getSlug());

        return response()->json(['message' => 'Producto eliminado con éxito']);
    }

    /**
     * Incrementa el contador de vistas de un producto y registra la interacción.
     */
    public function incrementView(int $id, Request $request, IncrementProductViewUseCase $incrementProductViewUseCase): JsonResponse
    {
        $userId = Auth::id();
        $metadata = $request->input('metadata', []);

        // Registrar interacción mejorada si hay usuario autenticado
        if ($userId) {
            try {
                \App\Models\UserInteraction::track(
                    $userId,
                    'view_product',
                    $id,
                    $metadata
                );

                Log::info('🎯 [VIEW TRACKED] Interacción de vista registrada', [
                    'user_id' => $userId,
                    'product_id' => $id,
                    'metadata' => $metadata,
                ]);
            } catch (\Exception $e) {
                Log::error('❌ [VIEW TRACK ERROR] Error registrando interacción de vista', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'product_id' => $id,
                ]);
                // No fallar el request por esto
            }
        }

        $success = $incrementProductViewUseCase->execute($id, $userId, $metadata);

        if (! $success) {
            return response()->json(['message' => 'Error al incrementar vistas del producto'], 404);
        }

        return response()->json([
            'success' => true,
            'interaction_tracked' => $userId ? true : false,
        ]);
    }

    /**
     * Construye un array de filtros a partir de los parámetros de la petición.
     * Maneja tanto camelCase como snake_case para compatibilidad.
     */
    private function buildFiltersFromRequest(Request $request): array
    {
        $filters = [];

        // Manejo de categorías - soporta ambos formatos
        $categoryIds = $this->getParameterValue($request, ['categoryIds', 'category_ids']);
        if ($categoryIds) {
            // Si viene como string separado por comas, convertir a array
            if (is_string($categoryIds)) {
                $categoryIds = array_map('intval', array_filter(explode(',', $categoryIds)));
            }
            // Si es array de arrays (como category_ids[]=36&category_ids[]=34), aplanar
            if (is_array($categoryIds) && count($categoryIds) > 0) {
                $flattenedIds = [];
                foreach ($categoryIds as $id) {
                    if (is_array($id)) {
                        $flattenedIds = array_merge($flattenedIds, $id);
                    } else {
                        $flattenedIds[] = (int) $id;
                    }
                }
                $categoryIds = array_filter($flattenedIds);
            }

            if (! empty($categoryIds)) {
                $filters['category_ids'] = $categoryIds;
                $filters['category_operator'] = $this->getParameterValue($request, ['categoryOperator', 'category_operator'], 'or');
            }
        }

        // Categoría individual
        $categoryId = $this->getParameterValue($request, ['categoryId', 'category_id']);
        if ($categoryId && empty($filters['category_ids'])) {
            $filters['category_id'] = (int) $categoryId;
        }

        // Rango de precios
        $minPrice = $this->getParameterValue($request, ['minPrice', 'min_price']);
        if ($minPrice !== null) {
            $filters['price_min'] = (float) $minPrice;
        }

        $maxPrice = $this->getParameterValue($request, ['maxPrice', 'max_price']);
        if ($maxPrice !== null) {
            $filters['price_max'] = (float) $maxPrice;
        }

        // Rating mínimo
        $rating = $this->getParameterValue($request, ['rating']);
        if ($rating !== null) {
            $filters['rating'] = (int) $rating;
        }

        // Descuento mínimo
        $minDiscount = $this->getParameterValue($request, ['minDiscount', 'min_discount']);
        if ($minDiscount !== null) {
            $filters['min_discount'] = (float) $minDiscount;
        } else {
            $discount = $this->getParameterValue($request, ['discount']);
            if ($discount === 'true' || $discount === true) {
                $filters['min_discount'] = 5; // Por defecto 5% si solo se indica 'discount=true'
            }
        }

        // Ordenamiento
        $sortBy = $this->getParameterValue($request, ['sortBy', 'sort_by']);
        if ($sortBy) {
            $filters['sortBy'] = $sortBy;
            $filters['sortDir'] = $this->getParameterValue($request, ['sortDir', 'sort_dir'], 'desc');
        }

        // Colores
        $colors = $this->getParameterValue($request, ['colors']);
        if ($colors) {
            if (is_string($colors)) {
                $colors = array_map('trim', explode(',', $colors));
            }
            $filters['colors'] = $colors;
        }

        // Tamaños
        $sizes = $this->getParameterValue($request, ['sizes']);
        if ($sizes) {
            if (is_string($sizes)) {
                $sizes = array_map('trim', explode(',', $sizes));
            }
            $filters['sizes'] = $sizes;
        }

        // Tags
        $tags = $this->getParameterValue($request, ['tags']);
        if ($tags) {
            if (is_string($tags)) {
                $tags = array_map('trim', explode(',', $tags));
            }
            $filters['tags'] = $tags;
        }

        // Productos en stock
        $inStock = $this->getParameterValue($request, ['inStock', 'in_stock']);
        if ($inStock === 'true' || $inStock === true) {
            $filters['stock_min'] = 1;
        }

        // Flag de producto nuevo (menos de 30 días)
        $isNew = $this->getParameterValue($request, ['isNew', 'is_new']);
        if ($isNew === 'true' || $isNew === true) {
            $filters['is_new'] = true;
        }

        // Vendedor específico
        $sellerId = $this->getParameterValue($request, ['sellerId', 'seller_id']);
        if ($sellerId) {
            $filters['seller_id'] = (int) $sellerId;
        }

        // Featured products
        $featured = $this->getParameterValue($request, ['featured']);
        if ($featured !== null) {
            $filters['featured'] = $featured === 'true' || $featured === true;
        }

        // Por defecto solo productos publicados y activos
        $filters['published'] = true;
        $filters['status'] = 'active';

        // ✅ NUEVO: Parámetro para activar cálculo de ratings desde tabla ratings
        $calculateRatings = $this->getParameterValue($request, ['calculateRatingsFromTable', 'calculate_ratings_from_table']);
        if ($calculateRatings === 'true' || $calculateRatings === true) {
            $filters['calculate_ratings_from_table'] = true;
        }

        return $filters;
    }

    /**
     * Obtiene un valor de parámetro de la request soportando múltiples nombres.
     *
     * @param  mixed  $default
     * @return mixed
     */
    private function getParameterValue(Request $request, array $paramNames, $default = null)
    {
        foreach ($paramNames as $paramName) {
            if ($request->has($paramName)) {
                return $request->input($paramName);
            }
        }

        return $default;
    }

    /**
     * Limpia la caché relacionada con productos.
     *
     * @param  int|null  $productId  ID específico de producto si se desea limpiar solo esa caché
     * @param  string|null  $slug  Slug del producto si se desea limpiar esa caché específica
     */
    private function clearProductCache(?int $productId = null, ?string $slug = null): void
    {
        // Si se proporciona un ID, limpiar caché específica de ese producto
        if ($productId) {
            Cache::forget('product_detail_'.$productId);
        }

        // Si se proporciona un slug, limpiar caché específica de ese producto por slug
        if ($slug) {
            Cache::forget('product_slug_'.$slug);
        }

        // Patrones de caché a limpiar
        $patterns = [
            'products_*',
            'product_related_*',
            'product_recommendations_*',
            'featured_products_*',
            'newest_products_*',
            'popular_products_*',
            'discounted_products_*',
        ];

        // Limpiar caché por patrones
        foreach ($patterns as $pattern) {
            $cacheKeys = Cache::get($pattern, []);
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
        }
    }

    /**
     * Actualización parcial de un producto (para toggles y cambios rápidos).
     * Solo para administradores y vendedores propietarios.
     *
     * @param  Request  $request
     */
    public function partialUpdate(AdminPatchRequest $request, int $id): JsonResponse
    {
        // Los datos ya vienen validados y procesados correctamente
        $validated = $request->validated();

        // Verificar que el producto existe
        $repository = app(ProductRepositoryInterface::class);
        $product = $repository->findById($id);

        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // ✅ Verificar permisos: debe ser admin O el propietario del producto
        $user = Auth::user();
        if (! $user || ($product->getUserId() !== $user->id && ! $user->isAdmin())) {
            return response()->json([
                'message' => 'No tienes permisos para modificar este producto',
            ], 403);
        }

        try {
            Log::info("Actualizando producto {$id} con datos validados:", $validated);

            // ✅ USAR ELOQUENT DIRECTAMENTE para asegurar persistencia
            $model = \App\Models\Product::find($id);
            if (! $model) {
                return response()->json(['message' => 'Producto no encontrado en base de datos'], 404);
            }

            // Los datos ya vienen con los tipos correctos desde AdminPatchRequest
            foreach ($validated as $field => $value) {
                $model->$field = $value;
                Log::info("Actualizando campo {$field} a: ".var_export($value, true).' (tipo: '.gettype($value).')');
            }

            // Guardar explícitamente
            $saved = $model->save();

            if (! $saved) {
                Log::error("Error: No se pudo guardar el producto {$id}");

                return response()->json(['message' => 'Error al guardar el producto'], 500);
            }

            Log::info("Producto {$id} guardado exitosamente");

            // Obtener el producto actualizado
            $updatedProduct = $repository->findById($id);

            // Limpiar caché
            $this->clearProductCache($id, $updatedProduct->getSlug());

            // Formatear respuesta
            $responseData = [
                'id' => $updatedProduct->getId(),
                'name' => $updatedProduct->getName(),
                'slug' => $updatedProduct->getSlug(),
                'price' => $updatedProduct->getPrice(),
                'final_price' => $updatedProduct->getFinalPrice(),
                'rating' => $updatedProduct->getRating(),
                'rating_count' => $updatedProduct->getRatingCount(),
                'discount_percentage' => $updatedProduct->getDiscountPercentage(),
                'main_image' => $updatedProduct->getMainImage(),
                'category_id' => $updatedProduct->getCategoryId(),
                'category_name' => $updatedProduct->getCategory() ? $updatedProduct->getCategory()->getName() : null,
                'stock' => $updatedProduct->getStock(),
                'is_in_stock' => $updatedProduct->isInStock(),
                'featured' => (bool) $updatedProduct->isFeatured(),
                'published' => (bool) $updatedProduct->isPublished(),
                'status' => $updatedProduct->getStatus(),
                'tags' => $updatedProduct->getTags(),
                'seller_id' => $updatedProduct->getSellerId(),
                'images' => $updatedProduct->getImages(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
                'data' => $responseData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en partialUpdate de producto: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'message' => 'Error interno del servidor al actualizar el producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas de productos para administradores.
     */
    public function getAdminStats(): JsonResponse
    {
        try {
            $repository = app(ProductRepositoryInterface::class);

            // ✅ Solo administradores pueden ver estas estadísticas usando métodos existentes
            $user = Auth::user();
            if (! $user || ! $user->isAdmin()) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $stats = [
                'total_products' => $repository->count([]),
                'active_products' => $repository->count(['status' => 'active', 'published' => true]),
                'featured_products' => $repository->count(['featured' => true]),
                'out_of_stock' => $repository->count(['stock' => 0]),
                'low_stock' => $repository->count(['stock_max' => 10, 'stock_min' => 1]),
                'draft_products' => $repository->count(['status' => 'draft']),
                'unpublished_products' => $repository->count(['published' => false]),
                'products_with_discount' => $repository->count(['min_discount' => 1]),
                'products_this_month' => $repository->count([
                    'created_after' => now()->startOfMonth()->toDateString(),
                ]),
                'products_this_week' => $repository->count([
                    'created_after' => now()->startOfWeek()->toDateString(),
                ]),
            ];

            // Agregar valor total del inventario si es posible
            try {
                $stats['total_inventory_value'] = $repository->getTotalInventoryValue();
            } catch (\Exception $e) {
                $stats['total_inventory_value'] = 0;
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas de productos: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al obtener estadísticas',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Obtiene productos para la sección "Ofertas y tendencias".
     * ALEATORIO: Cada llamada devuelve productos completamente diferentes.
     */
    public function trendingAndOffers(Request $request, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $limit = $request->input('limit', 12);
        $userId = Auth::id();

        // SIN CACHE - siempre aleatorio
        $result = null;

        if (! $result) {
            try {
                $allProducts = collect();
                $productIds = [];

                // Configuración de pesos para diferentes tipos de productos
                $weights = [
                    'discounted' => 0.40,    // 40% productos con descuento
                    'trending' => 0.35,      // 35% productos más vendidos/populares
                    'recent_popular' => 0.25, // 25% productos recientes populares
                ];

                // 1. Productos con descuento (40%) - ALEATORIO
                $discountedLimit = max(1, intval($limit * $weights['discounted']));
                $discountedProducts = $searchProductsUseCase->execute('', [
                    'min_discount' => 10,
                    'sortBy' => 'discount_percentage',
                    'sortDir' => 'desc',
                    'published' => true,
                    'status' => 'active',
                    'stock_min' => 1, // NUNCA productos agotados
                ], $discountedLimit * 3, 0, $userId); // Obtener más para aleatoriedad

                if (isset($discountedProducts['data'])) {
                    // Mezclar y tomar solo los necesarios
                    $shuffled = collect($discountedProducts['data'])->shuffle()->take($discountedLimit);
                    foreach ($shuffled as $product) {
                        if (! in_array($product['id'], $productIds)) {
                            $product['recommendation_type'] = 'discounted';
                            $allProducts->push($product);
                            $productIds[] = $product['id'];
                        }
                    }
                }

                // 2. Productos más vendidos/populares (35%) - ALEATORIO
                $trendingLimit = max(1, intval($limit * $weights['trending']));
                $trendingProducts = $searchProductsUseCase->execute('', [
                    'sortBy' => 'sales_count',
                    'sortDir' => 'desc',
                    'published' => true,
                    'status' => 'active',
                    'stock_min' => 1, // NUNCA productos agotados
                ], $trendingLimit * 3 + count($productIds), 0, $userId);

                if (isset($trendingProducts['data'])) {
                    $availableProducts = collect($trendingProducts['data'])
                        ->filter(fn ($product) => ! in_array($product['id'], $productIds))
                        ->shuffle()
                        ->take($trendingLimit);

                    foreach ($availableProducts as $product) {
                        $product['recommendation_type'] = 'trending';
                        $allProducts->push($product);
                        $productIds[] = $product['id'];
                    }
                }

                // 3. Productos recientes populares (25%) - ALEATORIO
                $recentLimit = max(1, intval($limit * $weights['recent_popular']));
                $recentProducts = $searchProductsUseCase->execute('', [
                    'sortBy' => 'view_count',
                    'sortDir' => 'desc',
                    'published' => true,
                    'status' => 'active',
                    'stock_min' => 1, // NUNCA productos agotados
                    'created_after' => now()->subDays(30)->toDateString(),
                ], $recentLimit * 3 + count($productIds), 0, $userId);

                if (isset($recentProducts['data'])) {
                    $availableProducts = collect($recentProducts['data'])
                        ->filter(fn ($product) => ! in_array($product['id'], $productIds))
                        ->shuffle()
                        ->take($recentLimit);

                    foreach ($availableProducts as $product) {
                        $product['recommendation_type'] = 'recent_popular';
                        $allProducts->push($product);
                        $productIds[] = $product['id'];
                    }
                }

                // Completar con productos populares si no llegamos al límite
                while ($allProducts->count() < $limit) {
                    $fillProducts = $searchProductsUseCase->execute('', [
                        'sortBy' => 'rating',
                        'sortDir' => 'desc',
                        'published' => true,
                        'status' => 'active',
                        'stock_min' => 1, // NUNCA productos agotados
                    ], ($limit - $allProducts->count()) * 3 + count($productIds), 0, $userId);

                    if (! isset($fillProducts['data']) || empty($fillProducts['data'])) {
                        break;
                    }

                    $availableProducts = collect($fillProducts['data'])
                        ->filter(fn ($product) => ! in_array($product['id'], $productIds))
                        ->shuffle()
                        ->take($limit - $allProducts->count());

                    if ($availableProducts->isEmpty()) {
                        break;
                    }

                    foreach ($availableProducts as $product) {
                        $product['recommendation_type'] = 'popular_fill';
                        $allProducts->push($product);
                        $productIds[] = $product['id'];
                    }
                }

                // ALEATORIO: Mezclar productos finales completamente
                $finalProducts = $allProducts->shuffle()->take($limit)->values()->toArray();

                $result = [
                    'data' => $finalProducts,
                    'meta' => [
                        'total' => count($finalProducts),
                        'count' => count($finalProducts),
                        'weights_used' => $weights,
                        'products_by_type' => [
                            'discounted' => $allProducts->where('recommendation_type', 'discounted')->count(),
                            'trending' => $allProducts->where('recommendation_type', 'trending')->count(),
                            'recent_popular' => $allProducts->where('recommendation_type', 'recent_popular')->count(),
                            'popular_fill' => $allProducts->where('recommendation_type', 'popular_fill')->count(),
                        ],
                    ],
                ];

                // SIN CACHE - siempre aleatorio
            } catch (\Exception $e) {
                Log::error('Error generating trending and offers: '.$e->getMessage());

                // Fallback: usar productos populares mezclados
                $fallbackProducts = $searchProductsUseCase->execute('', [
                    'sortBy' => 'sales_count',
                    'sortDir' => 'desc',
                    'published' => true,
                    'status' => 'active',
                    'stock_min' => 1, // NUNCA productos agotados
                ], $limit * 3, 0, $userId);

                // Mezclar productos fallback
                $shuffledFallback = collect($fallbackProducts['data'] ?? [])->shuffle()->take($limit)->toArray();

                $result = [
                    'data' => $shuffledFallback,
                    'meta' => [
                        'total' => count($shuffledFallback),
                        'count' => count($shuffledFallback),
                        'fallback' => true,
                        'error' => 'Used fallback due to error',
                    ],
                ];
            }
        }

        return response()->json($result);
    }

    /**
     * Obtiene productos destacados (featured = 1) en orden aleatorio.
     * ALEATORIO: Cada llamada devuelve productos completamente diferentes.
     */
    public function featuredRandom(Request $request, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $limit = $request->input('limit', 6); // Por defecto 6 para ProductCards
        $userId = Auth::id();

        // SIN CACHE - siempre aleatorio
        $result = null;

        if (! $result) {
            try {
                // Obtener TODOS los productos featured activos CON STOCK
                $featuredProducts = $searchProductsUseCase->execute('', [
                    'featured' => true,
                    'published' => true,
                    'status' => 'active',
                    'stock_min' => 1, // NUNCA productos agotados
                ], 100, 0, $userId); // Obtener hasta 100 featured

                if (isset($featuredProducts['data']) && ! empty($featuredProducts['data'])) {
                    // ALEATORIO: Shuffle completamente aleatorio en cada llamada
                    $allFeatured = collect($featuredProducts['data']);
                    $shuffledFeatured = $allFeatured->shuffle()->take($limit)->values()->toArray();

                    $result = [
                        'data' => $shuffledFeatured,
                        'meta' => [
                            'total' => count($shuffledFeatured),
                            'count' => count($shuffledFeatured),
                            'total_featured_available' => $allFeatured->count(),
                            'type' => 'featured_random',
                        ],
                    ];
                } else {
                    // No hay productos featured, devolver array vacío
                    $result = [
                        'data' => [],
                        'meta' => [
                            'total' => 0,
                            'count' => 0,
                            'total_featured_available' => 0,
                            'message' => 'No featured products available',
                        ],
                    ];
                }

                // SIN CACHE - siempre aleatorio
            } catch (\Exception $e) {
                Log::error('Error getting random featured products: '.$e->getMessage());

                $result = [
                    'data' => [],
                    'meta' => [
                        'total' => 0,
                        'count' => 0,
                        'error' => 'Error retrieving featured products',
                    ],
                ];
            }
        }

        return response()->json($result);
    }

    /**
     * Obtiene recomendaciones personalizadas NO aleatorias basadas en comportamiento del usuario.
     * Utiliza el motor de recomendaciones avanzado para generar sugerencias determinísticas.
     */
    public function personalized(Request $request, GenerateRecommendationsUseCase $generateRecommendationsUseCase, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        // Límite de productos recomendados para optimizar performance
        $limit = min($request->input('limit', 10), 50); // Máximo 50 productos

        // ✅ VERIFICAR TOKEN MANUALMENTE para rutas públicas con autenticación opcional
        $userId = Auth::id(); // Usar Auth::id() primero
        $authHeader = $request->header('Authorization');

        // Si no hay usuario por Auth, intentar JWT manual
        if (! $userId && $authHeader && str_starts_with($authHeader, 'Bearer ')) {
            try {
                $token = str_replace('Bearer ', '', $authHeader);
                $jwtService = app(\App\Domain\Interfaces\JwtServiceInterface::class);

                if ($jwtService->validateToken($token)) {
                    $user = $jwtService->getUserFromToken($token);
                    if ($user && ! $user->isBlocked()) {
                        $userId = $user->id;
                    }
                }
            } catch (\Exception $e) {
            }
        }

        if ($userId) {
        } else {
        }

        // Determinar si es personalizado
        $isPersonalized = (bool) $userId;

        if ($userId) {
            // Usuario autenticado: usar motor de recomendaciones
            try {

                $recommendations = $generateRecommendationsUseCase->execute($userId, $limit);

                if (! empty($recommendations)) {
                    return response()->json([
                        'data' => $recommendations,
                        'meta' => [
                            'total' => count($recommendations),
                            'count' => count($recommendations),
                            'type' => 'personalized',
                            'personalized' => true, // ✅ SIEMPRE true para usuario autenticado
                            'user_id' => $userId,
                        ],
                    ]);
                }

                Log::warning('⚠️ [ENGINE EMPTY] Motor no devolvió recomendaciones, usando fallback');

            } catch (\Exception $e) {
                Log::error('❌ [ENGINE ERROR] Error en motor de recomendaciones: '.$e->getMessage());
            }

            // Fallback para usuario autenticado: productos basados en su historial
            $fallbackProducts = $this->getPersonalizedFallbackWithSearch($userId, $limit, $searchProductsUseCase);

            if (! empty($fallbackProducts)) {
                Log::info('✅ [FALLBACK] Usando fallback personalizado', ['count' => count($fallbackProducts)]);

                return response()->json([
                    'data' => $fallbackProducts,
                    'meta' => [
                        'total' => count($fallbackProducts),
                        'count' => count($fallbackProducts),
                        'type' => 'personalized_fallback',
                        'personalized' => true, // ✅ SIEMPRE true para usuario autenticado
                        'user_id' => $userId,
                    ],
                ]);
            }
        }

        // Usuario no autenticado o sin fallback: productos populares
        Log::info('🔓 [NO-AUTH] Usuario no autenticado, usando productos populares');

        $popularResult = $searchProductsUseCase->execute('', [
            'published' => true,
            'status' => 'active',
            'stock_min' => 1,
            'sortBy' => 'rating',
            'sortDir' => 'desc',
        ], $limit, 0, null);

        $formattedProducts = [];
        if (isset($popularResult['data'])) {
            foreach ($popularResult['data'] as $product) {
                $product['recommendation_type'] = 'intelligent';
                $formattedProducts[] = $product;
            }
        }

        Log::info('✅ [POPULAR] Devolviendo productos populares', ['count' => count($formattedProducts)]);

        return response()->json([
            'data' => $formattedProducts,
            'meta' => [
                'total' => count($formattedProducts),
                'count' => count($formattedProducts),
                'type' => 'popular',
                'personalized' => false, // Usuarios no autenticados siempre false
            ],
        ]);
    }

    /**
     * 📜 ORIGINAL COMENTADO: Método personalized original con validaciones complejas
     * Se conserva para referencia pero está comentado temporalmente
     */
    public function personalizedOriginalComplex(Request $request, GenerateRecommendationsUseCase $generateRecommendationsUseCase, SearchProductsUseCase $searchProductsUseCase): JsonResponse
    {
        $limit = $request->input('limit', 10);

        Log::info('🎥 [PERSONALIZED START] Iniciando productos personalizados', [
            'limit' => $limit,
            'request_headers' => $request->headers->all(),
        ]);

        // ✅ VERIFICAR TOKEN MANUALMENTE para rutas públicas con autenticación opcional
        $userId = null;
        $authHeader = $request->header('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            try {
                $token = str_replace('Bearer ', '', $authHeader);
                $jwtService = app(\App\Domain\Interfaces\JwtServiceInterface::class);

                if ($jwtService->validateToken($token)) {
                    $user = $jwtService->getUserFromToken($token);
                    if ($user && ! $user->isBlocked()) {
                        $userId = $user->id;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('⚠️ [AUTH] Error validando token: '.$e->getMessage());
            }
        }

        // Si no hay usuario autenticado, usar productos populares determinísticos
        if (! $userId) {
            Log::info('🔓 [NO-AUTH] Usuario no autenticado, usando popular determinístico');

            // ✅ USAR MISMO SearchProductsUseCase que otros endpoints para consistencia
            $popularResult = $searchProductsUseCase->execute('', [
                'published' => true,
                'status' => 'active',
                'stock_min' => 1,
                'sortBy' => 'rating',
                'sortDir' => 'desc',
            ], $limit, 0, null);

            Log::info('📈 [NO-AUTH] Popular result:', [
                'count' => isset($popularResult['data']) ? count($popularResult['data']) : 0,
                'sample_product' => $popularResult['data'][0] ?? null,
            ]);

            $formattedProducts = [];
            if (isset($popularResult['data'])) {
                foreach ($popularResult['data'] as $product) {
                    $product['recommendation_type'] = 'intelligent';
                    $formattedProducts[] = $product;
                }
            }

            Log::info('🎥 [NO-AUTH FINAL] Devolviendo productos para usuario no autenticado', [
                'count' => count($formattedProducts),
            ]);

            return response()->json([
                'data' => $formattedProducts,
                'meta' => [
                    'total' => count($formattedProducts),
                    'count' => count($formattedProducts),
                    'type' => 'popular_guest',
                    'personalized' => false,
                ],
            ]);
        }

        // CON CACHE para usuarios autenticados (determinístico)
        $cacheKey = "personalized_recommendations_{$userId}_{$limit}";
        $result = Cache::get($cacheKey);

        // ✅ VALIDACIÓN SUAVE: Verificar que el cache no esté corrupto
        if ($result && isset($result['data']) && ! empty($result['data'])) {
            $firstProduct = $result['data'][0] ?? null;
            if ($firstProduct && isset($firstProduct['status']) && $firstProduct['status'] === 'error') {
                Log::warning('🗑️ [CACHE CORRUPTED] Cache corrupto detectado, limpiando', [
                    'userId' => $userId,
                    'corrupted_product' => $firstProduct,
                ]);
                Cache::forget($cacheKey);
                $result = null;
            } elseif ($firstProduct && (! isset($firstProduct['id']) || $firstProduct['id'] <= 0 ||
                     ! isset($firstProduct['name']) || empty($firstProduct['name']))) {
                Log::warning('🗑️ [CACHE INVALID] Cache con productos inválidos detectado, limpiando', [
                    'userId' => $userId,
                    'invalid_product' => $firstProduct,
                ]);
                Cache::forget($cacheKey);
                $result = null;
            }
        }

        if (! $result) {
            try {
                Log::info('🎯 [GENERATE] Generando recomendaciones para usuario autenticado', [
                    'userId' => $userId,
                    'limit' => $limit,
                ]);

                // Usar el motor de recomendaciones REAL del sistema
                $recommendations = $generateRecommendationsUseCase->execute($userId, $limit);

                Log::info('📈 [GENERATE RESULT] Recomendaciones obtenidas:', [
                    'count' => count($recommendations),
                    'sample_recommendation' => $recommendations[0] ?? null,
                ]);

                if (! empty($recommendations)) {
                    // ✅ VALIDACIÓN SUAVE: Verificar que las recomendaciones no estén corruptas antes de cachear
                    $validRecommendations = array_filter($recommendations, function ($product) {
                        // Solo filtrar productos que obviamente están corruptos
                        return isset($product['id']) && $product['id'] > 0 &&
                               isset($product['name']) && ! empty($product['name']) &&
                               (! isset($product['status']) || $product['status'] !== 'error');
                    });

                    if (! empty($validRecommendations)) {
                        $result = [
                            'data' => array_values($validRecommendations), // Reindexar array
                            'meta' => [
                                'total' => count($validRecommendations),
                                'count' => count($validRecommendations),
                                'user_id' => $userId,
                                'type' => 'personalized_intelligent',
                                'personalized' => true,
                                'cache_duration' => '30 minutes',
                            ],
                        ];

                        // Cache por 30 minutos para determinismo
                        Cache::put($cacheKey, $result, 60 * 30);

                        Log::info('✅ [CACHE VALIDATED] Recomendaciones válidas cacheadas', [
                            'userId' => $userId,
                            'valid_count' => count($validRecommendations),
                            'original_count' => count($recommendations),
                        ]);
                    } else {
                        Log::warning('⚠️ [VALIDATION FAILED] Todas las recomendaciones están corruptas', [
                            'userId' => $userId,
                            'corrupted_recommendations' => $recommendations,
                        ]);
                        $result = null; // Forzar fallback
                    }

                } else {
                    Log::warning('⚠️ [FALLBACK] Sin recomendaciones del motor, usando fallback');

                    // Fallback: productos basados en categorías populares del usuario usando SearchProductsUseCase
                    $fallbackProducts = $this->getPersonalizedFallbackWithSearch($userId, $limit, $searchProductsUseCase);

                    Log::info('📈 [FALLBACK RESULT] Fallback completado:', [
                        'count' => count($fallbackProducts),
                        'sample_fallback' => $fallbackProducts[0] ?? null,
                    ]);

                    // ✅ VALIDACIÓN SUAVE: Verificar fallback antes de cachear
                    $validFallbackProducts = array_filter($fallbackProducts, function ($product) {
                        // Solo filtrar productos que obviamente están corruptos
                        return isset($product['id']) && $product['id'] > 0 &&
                                   isset($product['name']) && ! empty($product['name']) &&
                                   (! isset($product['status']) || $product['status'] !== 'error');
                    });

                    if (! empty($validFallbackProducts)) {
                        $result = [
                            'data' => array_values($validFallbackProducts),
                            'meta' => [
                                'total' => count($validFallbackProducts),
                                'count' => count($validFallbackProducts),
                                'user_id' => $userId,
                                'type' => 'personalized_fallback',
                                'personalized' => true,
                                'fallback' => true,
                            ],
                        ];

                        Cache::put($cacheKey, $result, 60 * 15); // 15 minutos para fallback
                    } else {
                        Log::error('❌ [FALLBACK CORRUPTED] Fallback también está corrupto', [
                            'userId' => $userId,
                            'corrupted_fallback' => $fallbackProducts,
                        ]);

                        // Último recurso: array vacío
                        $result = [
                            'data' => [],
                            'meta' => [
                                'total' => 0,
                                'count' => 0,
                                'user_id' => $userId,
                                'type' => 'emergency_empty',
                                'personalized' => false,
                                'error' => 'All data sources corrupted',
                            ],
                        ];
                    }
                }

            } catch (\Exception $e) {
                Log::error('❌ [ERROR] Error generando recomendaciones personalizadas', [
                    'error' => $e->getMessage(),
                    'userId' => $userId,
                    'trace' => $e->getTraceAsString(),
                ]);

                // Error fallback usando SearchProductsUseCase
                $errorFallback = $this->getPersonalizedFallbackWithSearch($userId, $limit, $searchProductsUseCase);

                // ✅ VALIDACIÓN SUAVE: Verificar error fallback
                $validErrorFallback = array_filter($errorFallback, function ($product) {
                    // Solo filtrar productos que obviamente están corruptos
                    return isset($product['id']) && $product['id'] > 0 &&
                           isset($product['name']) && ! empty($product['name']) &&
                           (! isset($product['status']) || $product['status'] !== 'error');
                });

                $result = [
                    'data' => array_values($validErrorFallback),
                    'meta' => [
                        'total' => count($validErrorFallback),
                        'count' => count($validErrorFallback),
                        'user_id' => $userId,
                        'type' => 'error_fallback',
                        'personalized' => false,
                        'error' => 'Used fallback due to error',
                    ],
                ];
            }
        } else {
            Log::info('📦 [CACHE HIT] Recomendaciones obtenidas de cache', [
                'userId' => $userId,
                'count' => isset($result['data']) ? count($result['data']) : 0,
            ]);

            // ✅ VALIDACIÓN SUAVE ADICIONAL: Verificar integridad del cache hit
            if (isset($result['data']) && ! empty($result['data'])) {
                $validCachedProducts = array_filter($result['data'], function ($product) {
                    // Solo filtrar productos que obviamente están corruptos
                    return isset($product['id']) && $product['id'] > 0 &&
                           isset($product['name']) && ! empty($product['name']) &&
                           (! isset($product['status']) || $product['status'] !== 'error');
                });

                if (count($validCachedProducts) !== count($result['data'])) {
                    Log::warning('🗑️ [CACHE PARTIAL CORRUPTION] Cache parcialmente corrupto, limpiando productos inválidos', [
                        'userId' => $userId,
                        'total_cached' => count($result['data']),
                        'valid_cached' => count($validCachedProducts),
                    ]);

                    $result['data'] = array_values($validCachedProducts);
                    $result['meta']['total'] = count($validCachedProducts);
                    $result['meta']['count'] = count($validCachedProducts);

                    // Actualizar cache con datos limpios
                    Cache::put($cacheKey, $result, 60 * 30);
                }
            }
        }

        Log::info('🎥 [PERSONALIZED END] Respuesta final:', [
            'userId' => $userId,
            'data_count' => isset($result['data']) ? count($result['data']) : 0,
            'sample_final_product' => $result['data'][0] ?? null,
        ]);

        return response()->json($result);
    }

    /**
     * 🚀 VERSION SIMPLE Y FUNCIONAL: Recomendaciones personalizadas sin validaciones complejas
     * Esta versión garantiza devolver productos sin importar qué
     */
    public function personalizedSimple(Request $request): JsonResponse
    {
        // Límite de productos recomendados para optimizar performance
        $limit = min($request->input('limit', 10), 50); // Máximo 50 productos

        Log::info('🚀 [SIMPLE] Iniciando recomendaciones personalizadas simples', ['limit' => $limit]);

        // ✅ VERIFICAR TOKEN MANUALMENTE
        $userId = null;
        $authHeader = $request->header('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            try {
                $token = str_replace('Bearer ', '', $authHeader);
                $jwtService = app(\App\Domain\Interfaces\JwtServiceInterface::class);

                if ($jwtService->validateToken($token)) {
                    $user = $jwtService->getUserFromToken($token);
                    if ($user && ! $user->isBlocked()) {
                        $userId = $user->id;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('⚠️ [SIMPLE AUTH] Error validando token: '.$e->getMessage());
            }
        }

        // PASO 1: Intentar motor de recomendaciones si hay usuario
        if ($userId) {
            try {
                $generateRecommendationsUseCase = app(\App\UseCases\Recommendation\GenerateRecommendationsUseCase::class);
                $recommendations = $generateRecommendationsUseCase->execute($userId, $limit);

                if (! empty($recommendations)) {
                    return response()->json([
                        'data' => $recommendations,
                        'meta' => [
                            'total' => count($recommendations),
                            'count' => count($recommendations),
                            'user_id' => $userId,
                            'type' => 'intelligent_engine',
                            'personalized' => true,
                        ],
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('❌ [SIMPLE ENGINE ERROR] Error en motor: '.$e->getMessage());
            }
        }

        // PASO 2: Fallback directo con productos de la base de datos
        Log::info('🔄 [SIMPLE FALLBACK] Usando fallback directo');

        try {
            $products = \App\Models\Product::where('status', 'active')
                ->where('published', true)
                ->where('stock', '>', 0)
                ->with('category')
                ->select([
                    'id', 'name', 'slug', 'price', 'rating', 'rating_count',
                    'discount_percentage', 'images', 'category_id', 'stock',
                    'featured', 'status', 'tags', 'seller_id', 'user_id',
                    'created_at', 'updated_at',
                ])
                ->orderBy('rating', 'desc')
                ->orderBy('view_count', 'desc')
                ->limit($limit)
                ->get();

            Log::info('📊 [SIMPLE DB] Productos obtenidos de BD:', [
                'count' => $products->count(),
                'sample' => $products->first() ? [
                    'id' => $products->first()->id,
                    'name' => $products->first()->name,
                    'rating' => $products->first()->rating,
                    'price' => $products->first()->price,
                ] : null,
            ]);

            if ($products->isNotEmpty()) {
                $productFormatter = app(\App\Domain\Formatters\ProductFormatter::class);
                $formattedProducts = [];

                foreach ($products as $product) {
                    try {
                        $formatted = $productFormatter->formatForApi($product);
                        $formatted['recommendation_type'] = 'intelligent';
                        $formattedProducts[] = $formatted;
                    } catch (\Exception $e) {
                        Log::error('❌ [SIMPLE FORMAT ERROR] Error formateando producto '.$product->id.': '.$e->getMessage());
                        // Continuar con el siguiente producto
                    }
                }

                Log::info('✅ [SIMPLE SUCCESS] Productos formateados:', [
                    'count' => count($formattedProducts),
                    'sample' => $formattedProducts[0] ?? null,
                ]);

                if (! empty($formattedProducts)) {
                    return response()->json([
                        'data' => $formattedProducts,
                        'meta' => [
                            'total' => count($formattedProducts),
                            'count' => count($formattedProducts),
                            'user_id' => $userId,
                            'type' => 'simple_fallback',
                            'personalized' => (bool) $userId,
                        ],
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('❌ [SIMPLE FALLBACK ERROR] Error en fallback: '.$e->getMessage());
        }

        // PASO 3: Último recurso - productos básicos SIN condiciones estrictas
        Log::warning('⚠️ [SIMPLE LAST RESORT] Usando último recurso sin condiciones estrictas');

        try {
            $basicProducts = \App\Models\Product::whereNotNull('id')
                ->with('category')
                ->select([
                    'id', 'name', 'slug', 'price', 'rating', 'rating_count',
                    'discount_percentage', 'images', 'category_id', 'stock',
                    'featured', 'status', 'tags', 'seller_id', 'user_id',
                    'created_at', 'updated_at',
                ])
                ->limit($limit)
                ->get();

            Log::info('🆘 [SIMPLE BASIC] Productos básicos obtenidos:', ['count' => $basicProducts->count()]);

            if ($basicProducts->isNotEmpty()) {
                $productFormatter = app(\App\Domain\Formatters\ProductFormatter::class);
                $formattedProducts = [];

                foreach ($basicProducts as $product) {
                    try {
                        $formatted = $productFormatter->formatForApi($product);
                        $formatted['recommendation_type'] = 'intelligent';
                        $formattedProducts[] = $formatted;
                    } catch (\Exception $e) {
                        Log::error('❌ [SIMPLE BASIC FORMAT ERROR] Error formateando producto básico '.$product->id.': '.$e->getMessage());
                    }
                }

                if (! empty($formattedProducts)) {
                    return response()->json([
                        'data' => $formattedProducts,
                        'meta' => [
                            'total' => count($formattedProducts),
                            'count' => count($formattedProducts),
                            'user_id' => $userId,
                            'type' => 'basic_emergency',
                            'personalized' => false,
                        ],
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('❌ [SIMPLE BASIC ERROR] Error en último recurso: '.$e->getMessage());
        }

        // Si llegamos aquí, realmente no hay productos en la BD
        Log::error('😱 [SIMPLE EMPTY] No se encontraron productos en la base de datos');

        return response()->json([
            'data' => [],
            'meta' => [
                'total' => 0,
                'count' => 0,
                'user_id' => $userId,
                'type' => 'empty_database',
                'personalized' => false,
                'error' => 'No products found in database',
            ],
        ]);
    }

    /**
     * 🔍 ENDPOINT DE DEBUG TEMPORAL: Ver qué está pasando con las recomendaciones
     * Este endpoint nos ayuda a debuggear el problema paso a paso
     */
    public function debugPersonalized(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);

        Log::info('🔍 [DEBUG START] Iniciando debug de recomendaciones personalizadas');

        // ✅ VERIFICAR TOKEN MANUALMENTE
        $userId = null;
        $authHeader = $request->header('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            try {
                $token = str_replace('Bearer ', '', $authHeader);
                $jwtService = app(\App\Domain\Interfaces\JwtServiceInterface::class);

                if ($jwtService->validateToken($token)) {
                    $user = $jwtService->getUserFromToken($token);
                    if ($user && ! $user->isBlocked()) {
                        $userId = $user->id;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('⚠️ [DEBUG AUTH] Error validando token: '.$e->getMessage());
            }
        }

        $debug = [
            'step' => 'init',
            'user_id' => $userId,
            'limit' => $limit,
            'auth_header_present' => ! empty($authHeader),
        ];

        if (! $userId) {
            Log::info('🔓 [DEBUG NO-AUTH] Usuario no autenticado, usando productos básicos');

            // Obtener productos básicos directamente de la base de datos
            $basicProducts = \App\Models\Product::where('status', 'active')
                ->where('published', true)
                ->where('stock', '>', 0)
                ->with('category')
                ->select([
                    'id', 'name', 'slug', 'price', 'rating', 'rating_count',
                    'discount_percentage', 'images', 'category_id', 'stock',
                    'featured', 'status', 'tags', 'seller_id', 'user_id',
                    'created_at', 'updated_at',
                ])
                ->limit($limit)
                ->get();

            $debug['step'] = 'no_auth_basic_products';
            $debug['basic_products_count'] = $basicProducts->count();
            $debug['basic_products_sample'] = $basicProducts->first() ? [
                'id' => $basicProducts->first()->id,
                'name' => $basicProducts->first()->name,
                'rating' => $basicProducts->first()->rating,
                'price' => $basicProducts->first()->price,
                'images' => $basicProducts->first()->images,
            ] : null;

            if ($basicProducts->isNotEmpty()) {
                $productFormatter = app(\App\Domain\Formatters\ProductFormatter::class);
                $formattedProducts = [];

                foreach ($basicProducts as $product) {
                    $formatted = $productFormatter->formatForApi($product);
                    $formatted['recommendation_type'] = 'debug_basic';
                    $formattedProducts[] = $formatted;
                }

                $debug['step'] = 'formatted_products';
                $debug['formatted_count'] = count($formattedProducts);
                $debug['formatted_sample'] = $formattedProducts[0] ?? null;

                return response()->json([
                    'data' => $formattedProducts,
                    'meta' => [
                        'total' => count($formattedProducts),
                        'count' => count($formattedProducts),
                        'type' => 'debug_no_auth',
                        'personalized' => false,
                    ],
                    'debug' => $debug,
                ]);
            }
        }

        // Para usuarios autenticados, probar el motor de recomendaciones
        if ($userId) {
            try {
                $generateRecommendationsUseCase = app(\App\UseCases\Recommendation\GenerateRecommendationsUseCase::class);
                $recommendations = $generateRecommendationsUseCase->execute($userId, $limit);

                $debug['step'] = 'recommendations_generated';
                $debug['recommendations_count'] = count($recommendations);
                $debug['recommendations_sample'] = $recommendations[0] ?? null;

                if (! empty($recommendations)) {
                    return response()->json([
                        'data' => $recommendations,
                        'meta' => [
                            'total' => count($recommendations),
                            'count' => count($recommendations),
                            'user_id' => $userId,
                            'type' => 'debug_recommendations',
                            'personalized' => true,
                        ],
                        'debug' => $debug,
                    ]);
                }

            } catch (\Exception $e) {
                $debug['step'] = 'recommendations_error';
                $debug['error'] = $e->getMessage();
                Log::error('❌ [DEBUG ERROR] Error en motor de recomendaciones: '.$e->getMessage());
            }
        }

        // Fallback: productos directos de base de datos
        $fallbackProducts = \App\Models\Product::where('status', 'active')
            ->where('published', true)
            ->where('stock', '>', 0)
            ->with('category')
            ->select([
                'id', 'name', 'slug', 'price', 'rating', 'rating_count',
                'discount_percentage', 'images', 'category_id', 'stock',
                'featured', 'status', 'tags', 'seller_id', 'user_id',
                'created_at', 'updated_at',
            ])
            ->orderBy('rating', 'desc')
            ->limit($limit)
            ->get();

        $debug['step'] = 'fallback_products';
        $debug['fallback_count'] = $fallbackProducts->count();
        $debug['fallback_sample'] = $fallbackProducts->first() ? [
            'id' => $fallbackProducts->first()->id,
            'name' => $fallbackProducts->first()->name,
            'rating' => $fallbackProducts->first()->rating,
            'images' => $fallbackProducts->first()->images,
        ] : null;

        if ($fallbackProducts->isNotEmpty()) {
            $productFormatter = app(\App\Domain\Formatters\ProductFormatter::class);
            $formattedProducts = [];

            foreach ($fallbackProducts as $product) {
                $formatted = $productFormatter->formatForApi($product);
                $formatted['recommendation_type'] = 'debug_fallback';
                $formattedProducts[] = $formatted;
            }

            return response()->json([
                'data' => $formattedProducts,
                'meta' => [
                    'total' => count($formattedProducts),
                    'count' => count($formattedProducts),
                    'user_id' => $userId,
                    'type' => 'debug_fallback',
                    'personalized' => (bool) $userId,
                ],
                'debug' => $debug,
            ]);
        }

        // Si llegamos aquí, no hay productos en la base de datos
        $debug['step'] = 'no_products_found';

        return response()->json([
            'data' => [],
            'meta' => [
                'total' => 0,
                'count' => 0,
                'error' => 'No products found in database',
            ],
            'debug' => $debug,
        ]);
    }

    /**
     * 🧹 ENDPOINT TEMPORAL: Limpiar cache corrupto de recomendaciones
     * Este endpoint limpia el cache corrupto y devuelve productos válidos
     * 📝 TODO: Remover este endpoint una vez que el problema esté resuelto
     */
    public function clearCorruptedCache(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');

            Log::info('🧹 [CACHE CLEANUP] Iniciando limpieza de cache corrupto', [
                'user_id' => $userId,
                'request_ip' => $request->ip(),
            ]);

            if ($userId) {
                // Limpiar cache específico del usuario
                $userCacheKeys = [
                    "personalized_recommendations_{$userId}_10",
                    "personalized_recommendations_{$userId}_12",
                    "personalized_recommendations_{$userId}_5",
                    "product_recommendations_{$userId}",
                    "user_profile_{$userId}",
                ];

                $clearedKeys = 0;
                foreach ($userCacheKeys as $key) {
                    if (Cache::forget($key)) {
                        $clearedKeys++;
                    }
                }

                Log::info('✅ [USER CACHE CLEARED] Cache de usuario limpiado', [
                    'user_id' => $userId,
                    'keys_cleared' => $clearedKeys,
                ]);
            } else {
                // Limpiar todo el cache
                Cache::flush();
                Log::info('💥 [FULL CACHE CLEARED] Cache completo limpiado');
            }

            return response()->json([
                'success' => true,
                'message' => 'Cache corrupto limpiado exitosamente',
                'user_id' => $userId,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [CACHE CLEANUP ERROR] Error limpiando cache: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error limpiando cache corrupto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fallback personalizado para cuando el motor de recomendaciones no tiene suficientes datos
     * Usa SearchProductsUseCase para mantener consistencia con otros endpoints
     */
    private function getPersonalizedFallbackWithSearch(int $userId, int $limit, SearchProductsUseCase $searchProductsUseCase): array
    {
        try {
            // Obtener categorías que el usuario ha visto/comprado
            $userInteractions = \App\Models\UserInteraction::where('user_id', $userId)
                ->where('interaction_type', 'view_product') // ✅ CORREGIDO para consistencia
                ->with('product:id,category_id')
                ->latest()
                ->take(50)
                ->get();

            $categoryIds = [];
            foreach ($userInteractions as $interaction) {
                if ($interaction->product && $interaction->product->category_id) {
                    $categoryIds[] = $interaction->product->category_id;
                }
            }

            $categoryIds = array_unique($categoryIds);

            if (! empty($categoryIds)) {
                // Productos de categorías que le interesan al usuario
                $personalizedResult = $searchProductsUseCase->execute('', [
                    'category_ids' => $categoryIds,
                    'published' => true,
                    'status' => 'active',
                    'stock_min' => 1,
                    'sortBy' => 'rating',
                    'sortDir' => 'desc',
                ], $limit, 0, $userId);
            } else {
                // Si no tiene historial, productos populares con buen rating
                $personalizedResult = $searchProductsUseCase->execute('', [
                    'published' => true,
                    'status' => 'active',
                    'stock_min' => 1,
                    'rating' => 4.0,
                    'sortBy' => 'sales_count',
                    'sortDir' => 'desc',
                ], $limit, 0, $userId);
            }

            $formattedProducts = [];
            if (isset($personalizedResult['data'])) {
                foreach ($personalizedResult['data'] as $product) {
                    $product['recommendation_type'] = 'intelligent';
                    $formattedProducts[] = $product;
                }
            }

            return $formattedProducts;

        } catch (\Exception $e) {
            Log::error('Error en fallback personalizado: '.$e->getMessage());

            // Último recurso: productos populares básicos usando SearchProductsUseCase
            try {
                $basicResult = $searchProductsUseCase->execute('', [
                    'published' => true,
                    'status' => 'active',
                    'stock_min' => 1,
                    'sortBy' => 'view_count',
                    'sortDir' => 'desc',
                ], $limit, 0, $userId);

                $result = [];
                if (isset($basicResult['data'])) {
                    foreach ($basicResult['data'] as $product) {
                        $product['recommendation_type'] = 'intelligent';
                        $result[] = $product;
                    }
                }

                return $result;
            } catch (\Exception $fallbackError) {
                Log::error('Error en último recurso de fallback: '.$fallbackError->getMessage());

                return [];
            }
        }
    }

    /**
     * Fallback personalizado para cuando el motor de recomendaciones no tiene suficientes datos
     *
     * @deprecated Usar getPersonalizedFallbackWithSearch en su lugar
     */
    private function getPersonalizedFallback(int $userId, int $limit): array
    {
        try {
            // Obtener categorías que el usuario ha visto/comprado
            $userInteractions = \App\Models\UserInteraction::where('user_id', $userId)
                ->where('interaction_type', 'view_product') // ✅ CORREGIDO para consistencia
                ->with('product:id,category_id')
                ->latest()
                ->take(50)
                ->get();

            $categoryIds = [];
            foreach ($userInteractions as $interaction) {
                if ($interaction->product && $interaction->product->category_id) {
                    $categoryIds[] = $interaction->product->category_id;
                }
            }

            $categoryIds = array_unique($categoryIds);

            if (! empty($categoryIds)) {
                // Productos de categorías que le interesan al usuario
                $personalizedProducts = $this->productRepository->search('', [
                    'category_ids' => $categoryIds,
                    'published' => true,
                    'status' => 'active',
                    'stock_min' => 1,
                    'sortBy' => 'rating',
                    'sortDir' => 'desc',
                ], $limit, 0);
            } else {
                // Si no tiene historial, productos populares con buen rating
                $personalizedProducts = $this->productRepository->search('', [
                    'published' => true,
                    'status' => 'active',
                    'stock_min' => 1,
                    'rating' => 4.0,
                    'sortBy' => 'sales_count',
                    'sortDir' => 'desc',
                ], $limit, 0);
            }

            $formattedProducts = [];
            foreach ($personalizedProducts as $product) {
                $formatted = $this->productFormatter->formatForApi($product);
                $formatted['recommendation_reason'] = empty($categoryIds) ? 'high_rated' : 'user_categories';
                $formattedProducts[] = $formatted;
            }

            return $formattedProducts;

        } catch (\Exception $e) {
            Log::error('Error en fallback personalizado: '.$e->getMessage());

            // Último recurso: productos populares básicos
            $basicProducts = $this->productRepository->search('', [
                'published' => true,
                'status' => 'active',
                'stock_min' => 1,
                'sortBy' => 'view_count',
                'sortDir' => 'desc',
            ], $limit, 0);

            $result = [];
            foreach ($basicProducts as $product) {
                $formatted = $this->productFormatter->formatForApi($product);
                $formatted['recommendation_reason'] = 'popular_basic';
                $result[] = $formatted;
            }

            return $result;
        }
    }
}
