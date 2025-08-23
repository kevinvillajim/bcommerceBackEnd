<?php

namespace App\Http\Controllers;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Http\Requests\AdminPatchRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    private CategoryRepositoryInterface $categoryRepository;

    private ProductRepositoryInterface $productRepository;

    private ProductFormatter $productFormatter;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        ProductRepositoryInterface $productRepository,
        ProductFormatter $productFormatter
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
        $this->productFormatter = $productFormatter;
    }

    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $onlyActive = filter_var($request->query('active', true), FILTER_VALIDATE_BOOLEAN);
        $featured = filter_var($request->query('featured', false), FILTER_VALIDATE_BOOLEAN);
        $withCounts = filter_var($request->query('withCounts', false), FILTER_VALIDATE_BOOLEAN);
        $withChildren = filter_var($request->query('withChildren', false), FILTER_VALIDATE_BOOLEAN);

        // Cache key based on query parameters
        $cacheKey = 'categories_'.md5(json_encode([
            'active' => $onlyActive,
            'featured' => $featured,
            'withCounts' => $withCounts,
            'withChildren' => $withChildren,
        ]));

        // Get from cache or database
        $categories = Cache::remember($cacheKey, 60 * 30, function () use ($onlyActive, $featured, $withCounts, $withChildren) {
            $categories = $featured
                ? $this->categoryRepository->findFeatured()
                : $this->categoryRepository->findAll($onlyActive);

            if ($withChildren) {
                $categories = array_map(function ($category) use ($onlyActive) {
                    $subcategories = $this->categoryRepository->findSubcategories(
                        $category->getId()->getValue(),
                        $onlyActive
                    );

                    $categoryArray = $category->toArray();
                    $categoryArray['subcategories'] = array_map(fn ($subcat) => $subcat->toArray(), $subcategories);

                    return $categoryArray;
                }, $categories);
            } else {
                $categories = array_map(fn ($category) => $category->toArray(), $categories);
            }

            // Add product counts if requested
            if ($withCounts) {
                foreach ($categories as &$category) {
                    $categoryId = $category['id'];
                    $category['product_count'] = $this->productRepository->count(['category_id' => $categoryId]);
                }
            }

            return $categories;
        });

        return response()->json([
            'data' => $categories,
            'meta' => [
                'total' => count($categories),
                'active_only' => $onlyActive,
                'featured_only' => $featured,
            ],
        ]);
    }


    /**
     * Get main categories (those without parent).
     */
    public function mainCategories(Request $request): JsonResponse
    {
        $onlyActive = filter_var($request->query('active', true), FILTER_VALIDATE_BOOLEAN);
        $withCounts = filter_var($request->query('withCounts', false), FILTER_VALIDATE_BOOLEAN);

        // Cache results for 30 minutes
        $cacheKey = 'main_categories_'.($onlyActive ? 'active' : 'all').'_'.($withCounts ? 'with_counts' : 'no_counts');

        $categories = Cache::remember($cacheKey, 60 * 30, function () use ($onlyActive, $withCounts) {
            $mainCategories = $this->categoryRepository->findMainCategories($onlyActive);
            $result = [];

            foreach ($mainCategories as $category) {
                $categoryData = $category->toArray();

                // Add product counts if requested
                if ($withCounts) {
                    $categoryData['product_count'] = $this->productRepository->count([
                        'category_id' => $category->getId()->getValue(),
                    ]);
                }

                // Get subcategories
                $subcategories = $this->categoryRepository->findSubcategories(
                    $category->getId()->getValue(),
                    $onlyActive
                );

                $categoryData['subcategories'] = array_map(function ($subcat) use ($withCounts) {
                    $subcatData = $subcat->toArray();

                    if ($withCounts) {
                        $subcatData['product_count'] = $this->productRepository->count([
                            'category_id' => $subcat->getId()->getValue(),
                        ]);
                    }

                    return $subcatData;
                }, $subcategories);

                $result[] = $categoryData;
            }

            return $result;
        });

        return response()->json([
            'data' => $categories,
            'meta' => [
                'total' => count($categories),
                'active_only' => $onlyActive,
            ],
        ]);
    }

    /**
     * Get subcategories of a specific category.
     */
    public function subcategories(int $categoryId, Request $request): JsonResponse
    {
        $onlyActive = filter_var($request->query('active', true), FILTER_VALIDATE_BOOLEAN);
        $withCounts = filter_var($request->query('withCounts', false), FILTER_VALIDATE_BOOLEAN);

        // Check if the parent category exists
        $parentCategory = $this->categoryRepository->findById($categoryId);
        if (! $parentCategory) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        // Get subcategories
        $subcategories = $this->categoryRepository->findSubcategories($categoryId, $onlyActive);

        $subcategoriesData = array_map(function ($subcat) use ($withCounts) {
            $subcatData = $subcat->toArray();

            if ($withCounts) {
                $subcatData['product_count'] = $this->productRepository->count([
                    'category_id' => $subcat->getId()->getValue(),
                ]);
            }

            return $subcatData;
        }, $subcategories);

        return response()->json([
            'data' => $subcategoriesData,
            'meta' => [
                'total' => count($subcategoriesData),
                'parent_category' => $parentCategory->toArray(),
                'active_only' => $onlyActive,
            ],
        ]);
    }

    /**
     * Get category by slug.
     */
    public function getBySlug(string $slug, Request $request): JsonResponse
    {
        $withSubcategories = filter_var($request->query('withSubcategories', true), FILTER_VALIDATE_BOOLEAN);
        $withProducts = filter_var($request->query('withProducts', false), FILTER_VALIDATE_BOOLEAN);
        $productsLimit = $request->query('productsLimit', 8);

        // Cache key
        $cacheKey = 'category_slug_'.$slug.'_'.
            ($withSubcategories ? 'with_subcats' : 'no_subcats').'_'.
            ($withProducts ? "with_{$productsLimit}_products" : 'no_products');

        $categoryData = Cache::remember($cacheKey, 15 * 60, function () use ($slug, $withSubcategories, $withProducts, $productsLimit) {
            $category = $this->categoryRepository->findBySlug($slug);

            if (! $category) {
                return null;
            }

            $categoryData = $category->toArray();

            // Add subcategories if requested
            if ($withSubcategories) {
                $subcategories = $this->categoryRepository->findSubcategories(
                    $category->getId()->getValue(),
                    true
                );

                $categoryData['subcategories'] = array_map(fn ($subcat) => $subcat->toArray(), $subcategories);
            }

            // Add products if requested
            if ($withProducts) {
                $products = $this->productRepository->findByCategory(
                    $category->getId()->getValue(),
                    $productsLimit,
                    0
                );

                $categoryData['products'] = array_map(
                    fn ($product) => $this->productFormatter->formatForApi($product),
                    $products
                );
            }

            // Add parent category if exists
            if ($category->getParentId()) {
                $parentCategory = $this->categoryRepository->findById($category->getParentId()->getValue());
                if ($parentCategory) {
                    $categoryData['parent_category'] = $parentCategory->toArray();
                }
            }

            return $categoryData;
        });

        if (! $categoryData) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        return response()->json([
            'data' => $categoryData,
        ]);
    }

    /**
     * Get products from a specific category.
     */
    public function products(int $categoryId, Request $request): JsonResponse
    {
        // Check if the category exists
        $category = $this->categoryRepository->findById($categoryId);
        if (! $category) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        // Get pagination parameters
        $limit = $request->query('limit', 12);
        $offset = $request->query('offset', 0);
        $page = $request->query('page', 1);

        // If page is provided, calculate offset
        if ($request->has('page')) {
            $offset = ($page - 1) * $limit;
        }

        // Get filtering and sorting parameters
        $includeSubcategories = filter_var($request->query('includeSubcategories', false), FILTER_VALIDATE_BOOLEAN);
        $sortBy = $request->query('sortBy', 'created_at');
        $sortDir = $request->query('sortDir', 'desc');
        $minPrice = $request->query('minPrice');
        $maxPrice = $request->query('maxPrice');
        $priceRange = null;

        if ($minPrice !== null && $maxPrice !== null) {
            $priceRange = [
                'min' => (float) $minPrice,
                'max' => (float) $maxPrice,
            ];
        }

        // Build filters array
        $filters = [
            'published' => true,
            'is_active' => true,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ];

        if ($priceRange) {
            $filters['price_min'] = $priceRange['min'];
            $filters['price_max'] = $priceRange['max'];
        }

        // If includeSubcategories is true, get all subcategory IDs
        $categoryIds = [$categoryId];

        if ($includeSubcategories) {
            $subcategories = $this->categoryRepository->findSubcategories($categoryId, true);
            foreach ($subcategories as $subcategory) {
                $categoryIds[] = $subcategory->getId()->getValue();
            }

            $filters['category_ids'] = $categoryIds;
        } else {
            $filters['category_id'] = $categoryId;
        }

        // Get products with filters
        $products = $this->productRepository->search('', $filters, $limit, $offset);

        // Count total products matching the filters
        $total = $this->productRepository->count($filters);

        // Format products for API
        $formattedProducts = array_map(
            fn ($product) => $this->productFormatter->formatForApi($product),
            $products
        );

        return response()->json([
            'data' => $formattedProducts,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'page' => $page,
                'pages' => ceil($total / $limit),
                'category' => $category->toArray(),
                'includeSubcategories' => $includeSubcategories,
                'categoryIds' => $categoryIds,
            ],
        ]);
    }

    /**
     * Get featured categories with their products.
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 5); // Limit of featured categories
        $productsPerCategory = $request->query('productsLimit', 4); // Products per category

        // Cache key
        $cacheKey = 'featured_categories_'.$limit.'_with_'.$productsPerCategory.'_products';

        $featuredCategories = Cache::remember($cacheKey, 60 * 15, function () use ($limit, $productsPerCategory) {
            $categories = $this->categoryRepository->findFeatured($limit);

            $result = [];
            foreach ($categories as $category) {
                $categoryData = $category->toArray();

                // Get products for this category
                $products = $this->productRepository->findByCategory(
                    $category->getId()->getValue(),
                    $productsPerCategory,
                    0
                );

                $categoryData['products'] = array_map(
                    fn ($product) => $this->productFormatter->formatForApi($product),
                    $products
                );

                $result[] = $categoryData;
            }

            return $result;
        });

        return response()->json([
            'data' => $featuredCategories,
            'meta' => [
                'total' => count($featuredCategories),
            ],
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        // Only allowed for admins, already checked in request
        $data = $request->validated();

        // Use the repository to create
        $categoryEntity = $this->categoryRepository->createFromArray($data);

        // Clear cache
        $this->clearCategoryCache();

        return response()->json([
            'message' => 'Categoría creada con éxito',
            'data' => $categoryEntity->toArray(),
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(int $id): JsonResponse
    {
        $category = $this->categoryRepository->findById($id);

        if (! $category) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        return response()->json([
            'data' => $category->toArray(),
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        // Only allowed for admins, already checked in request
        $category = $this->categoryRepository->findById($id);

        if (! $category) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $data = $request->validated();

        // Update entity using repository
        $updatedCategory = $this->categoryRepository->updateFromArray($id, $data);

        // Clear cache
        $this->clearCategoryCache();

        return response()->json([
            'message' => 'Categoría actualizada con éxito',
            'data' => $updatedCategory->toArray(),
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(int $id): JsonResponse
    {
        // Only allowed for admins
        $category = $this->categoryRepository->findById($id);

        if (! $category) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        // Check if there are products in this category
        $productCount = $this->productRepository->count(['category_id' => $id]);
        if ($productCount > 0) {
            return response()->json([
                'message' => 'No se puede eliminar una categoría que contiene productos',
                'product_count' => $productCount,
            ], 400);
        }

        // Check if there are subcategories
        $subcategories = $this->categoryRepository->findSubcategories($id);
        if (count($subcategories) > 0) {
            return response()->json([
                'message' => 'No se puede eliminar una categoría que contiene subcategorías',
                'subcategory_count' => count($subcategories),
            ], 400);
        }

        $deleted = $this->categoryRepository->delete($id);

        if (! $deleted) {
            return response()->json(['message' => 'Error al eliminar la categoría'], 500);
        }

        // Clear cache
        $this->clearCategoryCache();

        return response()->json(['message' => 'Categoría eliminada con éxito']);
    }

    /**
     * Clear category-related cache.
     */
    private function clearCategoryCache()
    {
        try {
            Cache::flush();
            Log::info('Cache de categorías limpiado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al limpiar cache: '.$e->getMessage());
            $keys = ['categories_main', 'categories_featured', 'categories_tree', 'categories_all'];
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }
}
