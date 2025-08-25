<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\ProductEntity;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Events\ProductStockUpdated;
use App\Events\ProductUpdated;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function create(ProductEntity $product): ProductEntity
    {
        $model = new Product;
        $this->mapEntityToModel($product, $model);

        // Generar o verificar el slug
        if (empty($model->slug)) {
            $model->slug = $this->generateUniqueSlug($model->name);
        } else {
            // Si el slug ya está establecido, verificar que sea único
            $originalSlug = $model->slug;
            $counter = 1;

            // Mientras exista un producto con ese slug, generar uno nuevo
            while (Product::where('slug', $model->slug)->exists()) {
                $model->slug = $originalSlug.'-'.$counter++;
            }
        }

        $model->save();

        return $this->mapModelToEntity($model);
    }

    public function update(ProductEntity $product): ProductEntity
    {
        $model = Product::findOrFail($product->getId());

        // Guardar valores antiguos para posibles eventos
        $oldStock = $model->stock;
        $oldPrice = $model->price;

        // Detectar cambios
        $changes = [];
        if ($model->name != $product->getName()) {
            $changes['name'] = ['old' => $model->name, 'new' => $product->getName()];
        }
        if ($model->price != $product->getPrice()) {
            $changes['price'] = ['old' => $model->price, 'new' => $product->getPrice()];
        }
        if ($model->stock != $product->getStock()) {
            $changes['stock'] = ['old' => $model->stock, 'new' => $product->getStock()];
        }
        if ($model->discount_percentage != $product->getDiscountPercentage()) {
            $changes['discount_percentage'] = ['old' => $model->discount_percentage, 'new' => $product->getDiscountPercentage()];
        }
        if ($model->status != $product->getStatus()) {
            $changes['status'] = ['old' => $model->status, 'new' => $product->getStatus()];
        }
        if ($model->published != $product->isPublished()) {
            $changes['published'] = ['old' => $model->published, 'new' => $product->isPublished()];
        }

        $this->mapEntityToModel($product, $model);
        $model->save();

        // Disparar eventos si hay cambios significativos
        if (isset($changes['stock'])) {
            event(new ProductStockUpdated($model->id, $oldStock, $model->stock));
        }

        if (! empty($changes)) {
            event(new ProductUpdated($model->id, $changes));
        }

        return $this->mapModelToEntity($model);
    }

    public function updatePartial(int $id, array $data): bool
    {
        try {
            $product = Product::find($id);

            if (! $product) {
                return false;
            }

            // Solo actualizar campos que están en $data
            foreach ($data as $field => $value) {
                if (in_array($field, $product->getFillable()) || $product->hasAttribute($field)) {
                    $product->{$field} = $value;
                }
            }

            $product->updated_at = now();

            return $product->save();
        } catch (\Exception $e) {
            Log::error('Error en updatePartial: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Obtiene el valor total del inventario.
     */
    public function getTotalInventoryValue(): float
    {
        try {
            return Product::where('status', 'active')
                ->where('published', true)
                ->selectRaw('SUM(price * stock) as total_value')
                ->value('total_value') ?? 0.0;
        } catch (\Exception $e) {
            Log::error('Error calculando valor del inventario: '.$e->getMessage());

            return 0.0;
        }
    }

    /**
     * Busca productos por múltiples IDs de categorías.
     */
    public function findProductsByCategories(array $categoryIds, array $excludeIds = [], int $limit = 10): array
    {
        try {
            $query = Product::whereIn('category_id', $categoryIds)
                ->where('status', 'active')
                ->where('published', true)
                ->join('sellers', 'products.user_id', '=', 'sellers.user_id')
                ->where('sellers.status', 'active') // ✅ Solo vendedores ACTIVOS
                ->with('category'); // ✅ EAGER LOADING para evitar N+1

            if (! empty($excludeIds)) {
                $query->whereNotIn('id', $excludeIds);
            }

            $products = $query->inRandomOrder()
                ->limit($limit)
                ->get();

            return $products->map(function ($product) {
                return $this->mapModelToEntity($product); // ✅ Corregido: usar mapModelToEntity
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Error buscando productos por categorías: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Busca productos por categoría específica.
     */
    public function findProductsByCategory(int $categoryId, array $excludeIds = [], int $limit = 10): array
    {
        try {
            $query = Product::where('category_id', $categoryId)
                ->where('status', 'active')
                ->where('published', true)
                ->join('sellers', 'products.user_id', '=', 'sellers.user_id')
                ->where('sellers.status', 'active') // ✅ Solo vendedores ACTIVOS
                ->with('category'); // ✅ EAGER LOADING para evitar N+1

            if (! empty($excludeIds)) {
                $query->whereNotIn('id', $excludeIds);
            }

            $products = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return $products->map(function ($product) {
                return $this->mapModelToEntity($product); // ✅ Corregido: usar mapModelToEntity
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Error buscando productos por categoría: '.$e->getMessage());

            return [];
        }
    }

    public function findById(int $id): ?ProductEntity
    {
        $model = Product::find($id);

        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    public function findBySlug(string $slug): ?ProductEntity
    {
        $model = Product::where('slug', $slug)->first();

        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    public function delete(int $id): bool
    {
        $model = Product::find($id);

        if (! $model) {
            return false;
        }

        return $model->delete();
    }

    public function findByCategory(int $categoryId, int $limit = 10, int $offset = 0): array
    {
        $products = Product::where('category_id', $categoryId)
            ->where('published', true)
            ->where('status', 'active')
            ->with('category') // ✅ EAGER LOADING para evitar N+1
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($products);
    }

    public function search(string $term, array $filters = [], int $limit = 10, int $offset = 0): array
    {
        $query = Product::query()
            ->where('products.published', true)
            ->where('products.status', 'active')
            ->with('category') // ✅ SIEMPRE cargar relación de categoría para evitar N+1
            ->join('sellers', 'products.user_id', '=', 'sellers.user_id') // ✅ INNER JOIN para requerir seller
            ->where('sellers.status', 'active'); // ✅ Solo vendedores ACTIVOS en tienda pública

        // ✅ NUEVA FUNCIONALIDAD: Join opcional con ratings para productos sin rating calculado
        if (! empty($filters['calculate_ratings_from_table'])) {

            $query->leftJoin('ratings', function ($join) {
                $join->on('products.id', '=', 'ratings.product_id')
                    ->where('ratings.type', '=', 'user_to_product')
                    ->where('ratings.status', '=', 'approved');
            })
                ->select(
                    'products.*',
                    DB::raw('CASE 
                    WHEN products.rating > 0 THEN products.rating 
                    ELSE COALESCE(AVG(ratings.rating), 0) 
                END as calculated_rating'),
                    DB::raw('CASE 
                    WHEN products.rating_count > 0 THEN products.rating_count 
                    ELSE COUNT(CASE WHEN ratings.rating IS NOT NULL THEN 1 END) 
                END as calculated_rating_count')
                )
                ->groupBy('products.id');
        } else {
            // Si no se calculan ratings, solo seleccionar productos
            $query->select('products.*');
        }

        // Aplicar búsqueda por término
        if (! empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('products.name', 'like', "%{$term}%")
                    ->orWhere('products.description', 'like', "%{$term}%")
                    ->orWhere('products.short_description', 'like', "%{$term}%");

                // Búsqueda en tags si existe el término
                if (! empty($term)) {
                    $q->orWhereJsonContains('products.tags', $term);
                }
            });
        }

        // Filtro por categorías múltiples
        if (! empty($filters['category_ids']) && is_array($filters['category_ids'])) {
            $categoryOperator = $filters['category_operator'] ?? 'or';

            if ($categoryOperator === 'and') {
                // Todos las categorías deben coincidir
                foreach ($filters['category_ids'] as $categoryId) {
                    $query->where('products.category_id', $categoryId);
                }
            } else {
                // Cualquier categoría puede coincidir (OR)
                $query->whereIn('products.category_id', $filters['category_ids']);
            }
        }

        // Filtro por categoría individual
        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        // Rango de precios
        if (! empty($filters['price_min'])) {
            $query->where('products.price', '>=', $filters['price_min']);
        }

        if (! empty($filters['price_max'])) {
            $query->where('products.price', '<=', $filters['price_max']);
        }

        // Filtro por rating mínimo
        if (! empty($filters['rating'])) {
            $query->where('products.rating', '>=', $filters['rating']);
        }

        // Descuento mínimo
        if (! empty($filters['min_discount'])) {
            $query->where('products.discount_percentage', '>=', $filters['min_discount']);
        }

        // Productos destacados
        if (isset($filters['featured'])) {
            $query->where('products.featured', $filters['featured']);
        }

        // Filtro por vendedor
        if (! empty($filters['seller_id'])) {
            $query->where('products.user_id', $filters['seller_id']);
        }

        // Stock mínimo
        if (! empty($filters['stock_min'])) {
            $query->where('products.stock', '>=', $filters['stock_min']);
        }

        // Productos nuevos (últimos 30 días)
        if (! empty($filters['is_new'])) {
            $query->where('products.created_at', '>=', now()->subDays(30));
        }

        // Filtros por colores
        if (! empty($filters['colors']) && is_array($filters['colors'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['colors'] as $color) {
                    $q->orWhereJsonContains('products.colors', $color);
                }
            });
        }

        // Filtros por tamaños
        if (! empty($filters['sizes']) && is_array($filters['sizes'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['sizes'] as $size) {
                    $q->orWhereJsonContains('products.sizes', $size);
                }
            });
        }

        // Filtros por tags
        if (! empty($filters['tags']) && is_array($filters['tags'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['tags'] as $tag) {
                    $q->orWhereJsonContains('products.tags', $tag);
                }
            });
        }

        // Aplicar ordenamiento
        $this->applySorting($query, $filters);

        // ✅ OPTIMIZACIÓN: Aplicar paginación antes de cargar relaciones
        $products = $query->skip($offset)->take($limit)->get();

        // ✅ Si se calcularon ratings, usar siempre los valores calculados
        if (! empty($filters['calculate_ratings_from_table'])) {
            foreach ($products as $product) {
                // Usar siempre los valores calculados que ya priorizan correctamente
                $product->rating = round($product->calculated_rating ?? 0, 1);
                $product->rating_count = $product->calculated_rating_count ?? 0;
            }
        }

        return $this->mapModelsToEntities($products);
    }

    public function findAll(int $limit = 10, int $offset = 0): array
    {
        $products = Product::where('products.published', true)
            ->where('products.status', 'active')
            ->join('sellers', 'products.user_id', '=', 'sellers.user_id')
            ->where('sellers.status', 'active') // ✅ Solo vendedores ACTIVOS
            ->select('products.*')
            ->with('category') // ✅ EAGER LOADING para evitar N+1
            ->orderBy('products.featured', 'desc') // Productos destacados primero
            ->orderByRaw('CASE 
                WHEN sellers.is_featured = 1 AND (sellers.featured_expires_at IS NULL OR sellers.featured_expires_at > NOW()) 
                THEN 1 ELSE 0 END DESC') // Luego tiendas destacadas
            ->orderBy('products.created_at', 'desc') // Finalmente por fecha
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($products);
    }

    public function findBySeller(int $userId, int $limit = 10, int $offset = 0): array
    {
        $products = Product::where('user_id', $userId)
            ->with('category') // ✅ EAGER LOADING para evitar N+1
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($products);
    }

    public function findByTags(array $tags, int $limit = 10, int $offset = 0): array
    {
        $query = Product::query()
            ->where('published', true)
            ->where('status', 'active')
            ->with('category'); // ✅ EAGER LOADING para evitar N+1

        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }

        $products = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($products);
    }

    public function findFeatured(int $limit = 10, int $offset = 0): array
    {
        $products = Product::where(function ($query) {
            // Productos featured O productos de tiendas destacadas activas
            $query->where('products.featured', true)
                ->orWhereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('sellers')
                        ->whereColumn('sellers.user_id', 'products.user_id')
                        ->where('sellers.is_featured', true)
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('sellers.featured_expires_at')
                                ->orWhere('sellers.featured_expires_at', '>', DB::raw('NOW()'));
                        });
                });
        })
            ->where('products.published', true)
            ->where('products.status', 'active')
            ->join('sellers', 'products.user_id', '=', 'sellers.user_id')
            ->where('sellers.status', 'active') // ✅ Solo vendedores ACTIVOS
            ->select('products.*')
            ->with('category') // ✅ EAGER LOADING para evitar N+1
            ->orderBy('products.featured', 'desc') // Productos featured tienen prioridad absoluta
            ->orderByRaw('CASE 
                WHEN sellers.is_featured = 1 AND (sellers.featured_expires_at IS NULL OR sellers.featured_expires_at > NOW()) 
                THEN 1 ELSE 0 END DESC') // Luego tiendas destacadas
            ->orderBy('products.created_at', 'desc') // Finalmente por fecha
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($products);
    }

    public function incrementViewCount(int $id): bool
    {
        return Product::where('id', $id)->increment('view_count') > 0;
    }

    public function updateStock(int $id, int $quantity, string $operation = 'replace'): bool
    {
        return DB::transaction(function () use ($id, $quantity, $operation) {
            $product = Product::find($id);

            if (! $product) {
                return false;
            }

            $oldStock = $product->stock;

            switch ($operation) {
                case 'increase':
                    $product->stock += $quantity;
                    break;
                case 'decrease':
                    $product->stock = max(0, $product->stock - $quantity);
                    break;
                case 'replace':
                default:
                    $product->stock = $quantity;
                    break;
            }

            $saved = $product->save();

            if ($saved) {
                event(new ProductStockUpdated($id, $oldStock, $product->stock));
            }

            return $saved;
        });
    }

    public function count(array $filters = []): int
    {
        $query = Product::query();

        // Aplicar filtros básicos
        $query->where('products.published', $filters['published'] ?? true)
            ->where('products.status', $filters['status'] ?? 'active');

        // ✅ Join opcional con ratings PERO usando COUNT DISTINCT para evitar duplicados
        if (! empty($filters['calculate_ratings_from_table'])) {
            $query->leftJoin('ratings', function ($join) {
                $join->on('products.id', '=', 'ratings.product_id')
                    ->where('ratings.type', '=', 'user_to_product')
                    ->where('ratings.status', '=', 'approved');
            });

            // ✅ USAR COUNT DISTINCT para evitar que el GROUP BY afecte el conteo
            return $query->distinct('products.id')->count('products.id');
        }

        // Aplicar búsqueda por término si está en los filtros
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('products.name', 'like', "%{$term}%")
                    ->orWhere('products.description', 'like', "%{$term}%")
                    ->orWhere('products.short_description', 'like', "%{$term}%")
                    ->orWhereJsonContains('products.tags', $term);
            });
        }

        // Aplicar resto de filtros...
        $this->applyCountFilters($query, $filters);

        // ✅ Si estamos usando JOIN con ratings, usar COUNT DISTINCT, sino COUNT normal
        if (! empty($filters['calculate_ratings_from_table'])) {
            return $query->distinct('products.id')->count('products.id');
        } else {
            return $query->count();
        }
    }

    /**
     * Aplicar filtros comunes para el método count
     */
    private function applyCountFilters($query, array $filters): void
    {

        // Filtro por categorías múltiples
        if (! empty($filters['category_ids']) && is_array($filters['category_ids'])) {
            $categoryOperator = $filters['category_operator'] ?? 'or';

            if ($categoryOperator === 'and') {
                foreach ($filters['category_ids'] as $categoryId) {
                    $query->where('products.category_id', $categoryId);
                }
            } else {
                $query->whereIn('products.category_id', $filters['category_ids']);
            }
        }

        // Filtro por categoría individual
        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        // Rango de precios
        if (! empty($filters['price_min'])) {
            $query->where('products.price', '>=', $filters['price_min']);
        }

        if (! empty($filters['price_max'])) {
            $query->where('products.price', '<=', $filters['price_max']);
        }

        // Rating mínimo
        if (! empty($filters['rating'])) {
            $query->where('products.rating', '>=', $filters['rating']);
        }

        // Descuento mínimo
        if (! empty($filters['min_discount'])) {
            $query->where('products.discount_percentage', '>=', $filters['min_discount']);
        }

        // Productos destacados
        if (isset($filters['featured'])) {
            $query->where('products.featured', $filters['featured']);
        }

        // Filtro por vendedor
        if (! empty($filters['seller_id'])) {
            $query->where('products.user_id', $filters['seller_id']);
        }

        // Stock mínimo
        if (! empty($filters['stock_min'])) {
            $query->where('products.stock', '>=', $filters['stock_min']);
        }

        // Productos nuevos
        if (! empty($filters['is_new'])) {
            $query->where('products.created_at', '>=', now()->subDays(30));
        }

        // Filtros JSON
        if (! empty($filters['colors']) && is_array($filters['colors'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['colors'] as $color) {
                    $q->orWhereJsonContains('products.colors', $color);
                }
            });
        }

        if (! empty($filters['sizes']) && is_array($filters['sizes'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['sizes'] as $size) {
                    $q->orWhereJsonContains('products.sizes', $size);
                }
            });
        }

        if (! empty($filters['tags']) && is_array($filters['tags'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['tags'] as $tag) {
                    $q->orWhereJsonContains('products.tags', $tag);
                }
            });
        }

    }

    public function findPopularProducts(int $limit, array $excludeIds = []): array
    {
        $query = Product::whereNotIn('id', $excludeIds)
            ->where('published', true)
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->join('sellers', 'products.user_id', '=', 'sellers.user_id')
            ->where('sellers.status', 'active') // ✅ Solo vendedores ACTIVOS
            ->with('category') // ✅ EAGER LOADING para evitar N+1
            ->orderBy('rating', 'desc')
            ->orderBy('view_count', 'desc')
            ->orderBy('sales_count', 'desc');

        $products = $query->take($limit)->get();

        return $this->mapModelsToEntities($products);
    }

    public function findProductsBySearch(string $term, array $excludeIds = [], int $limit = 10): array
    {
        $query = Product::whereNotIn('id', $excludeIds)
            ->where('published', true)
            ->where('status', 'active')
            ->join('sellers', 'products.user_id', '=', 'sellers.user_id')
            ->where('sellers.status', 'active') // ✅ Solo vendedores ACTIVOS
            ->with('category') // ✅ EAGER LOADING para evitar N+1
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhereJsonContains('tags', $term);
            })
            ->orderBy('rating', 'desc');

        $products = $query->take($limit)->get();

        return $this->mapModelsToEntities($products);
    }

    public function findProductsByTags(array $tags, array $excludeIds = [], int $limit = 10): array
    {
        $query = Product::whereNotIn('id', $excludeIds)
            ->where('published', true)
            ->where('status', 'active')
            ->with('category'); // ✅ EAGER LOADING para evitar N+1

        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }

        $products = $query->orderBy('rating', 'desc')
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($products);
    }

    private function mapModelToEntity($model): ProductEntity
    {
        // ✅ PROCESAMIENTO CORREGIDO DE IMÁGENES - SIN LOGS EXCESIVOS
        $images = null;
        if ($model->images) {
            if (is_string($model->images)) {
                // Si viene como JSON string desde la base de datos
                $decodedImages = json_decode($model->images, true);
                if (is_array($decodedImages)) {
                    // Procesar tanto strings como objetos
                    $images = array_filter($decodedImages, function ($img) {
                        // Aceptar strings válidos O arrays/objetos válidos
                        return (is_string($img) && ! empty($img)) ||
                            (is_array($img) && ! empty($img));
                    });
                } else {
                    // Si no es JSON válido, tratarlo como imagen única
                    $images = [$model->images];
                }
            } elseif (is_array($model->images)) {
                // Si ya viene como array, procesar igual
                $images = array_filter($model->images, function ($img) {
                    return (is_string($img) && ! empty($img)) ||
                        (is_array($img) && ! empty($img));
                });
            }
        }

        // ✅ PROCESAMIENTO DE OTROS CAMPOS JSON (sin cambios)
        $colors = $this->decodeJsonField($model->colors);
        $sizes = $this->decodeJsonField($model->sizes);
        $tags = $this->decodeJsonField($model->tags);
        $attributes = $this->decodeJsonField($model->attributes);

        return new ProductEntity(
            userId: $model->user_id,
            categoryId: $model->category_id,
            name: $model->name,
            slug: $model->slug,
            description: $model->description,
            rating: (float) ($model->rating ?? 0),
            ratingCount: (int) ($model->rating_count ?? 0),
            price: (float) $model->price,
            stock: (int) $model->stock,
            sellerId: $model->seller_id,
            weight: $model->weight ? (float) $model->weight : null,
            width: $model->width ? (float) $model->width : null,
            height: $model->height ? (float) $model->height : null,
            depth: $model->depth ? (float) $model->depth : null,
            dimensions: $model->dimensions,
            colors: $colors,
            sizes: $sizes,
            tags: $tags,
            sku: $model->sku,
            attributes: $attributes,
            images: $images,
            featured: (bool) $model->featured,
            published: (bool) $model->published,
            status: $model->status ?? 'active',
            viewCount: (int) ($model->view_count ?? 0),
            salesCount: (int) ($model->sales_count ?? 0),
            discountPercentage: (float) ($model->discount_percentage ?? 0),
            shortDescription: $model->short_description,
            id: $model->id,
            createdAt: $model->created_at?->format('Y-m-d H:i:s'),
            updatedAt: $model->updated_at?->format('Y-m-d H:i:s')
        );
    }

    /**
     * Helper para decodificar campos JSON de manera segura
     */
    private function decodeJsonField($field): ?array
    {
        if (is_null($field)) {
            return null;
        }

        if (is_array($field)) {
            return $field;
        }

        if (is_string($field)) {
            $decoded = json_decode($field, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * Aplicar ordenamiento a la consulta
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applySorting($query, array $filters): void
    {
        // Auto-cleanup expired featured sellers (solo si no se ha hecho recientemente)
        $this->autoCleanupExpiredFeatured();

        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortDir = $filters['sortDir'] ?? 'desc';

        // Mapeo de nombres de columnas permitidos con prefijo de tabla
        $allowedSortColumns = [
            'created_at' => 'products.created_at',
            'updated_at' => 'products.updated_at',
            'price' => 'products.price',
            'name' => 'products.name',
            'rating' => 'products.rating',
            'view_count' => 'products.view_count',
            'sales_count' => 'products.sales_count',
            'stock' => 'products.stock',
            'discount_percentage' => 'products.discount_percentage',
            'featured' => 'products.featured',
        ];

        // Validar dirección de ordenamiento
        $validSortDirections = ['asc', 'desc'];
        if (! in_array(strtolower($sortDir), $validSortDirections)) {
            $sortDir = 'desc';
        }

        // Aplicar ordenamiento
        if (array_key_exists($sortBy, $allowedSortColumns)) {
            $columnName = $allowedSortColumns[$sortBy];

            // Ordenamientos especiales
            switch ($sortBy) {
                case 'featured':
                    // Productos destacados primero, luego tiendas destacadas activas, luego por fecha
                    $query->orderBy('products.featured', 'desc')
                        ->orderByRaw('CASE 
                            WHEN sellers.is_featured = 1 AND (sellers.featured_expires_at IS NULL OR sellers.featured_expires_at > NOW()) 
                            THEN 1 ELSE 0 END DESC')
                        ->orderBy('products.created_at', 'desc');
                    break;

                case 'rating':
                    // Por rating, pero también considerar número de valoraciones
                    $query->orderBy('products.rating', $sortDir)
                        ->orderBy('products.rating_count', 'desc');
                    break;

                case 'popularity':
                    // Ordenar por popularidad (vistas + ventas)
                    $query->orderByRaw('(products.view_count + products.sales_count * 2) '.strtoupper($sortDir));
                    break;

                default:
                    // Para todos los otros ordenamientos, siempre priorizar tiendas destacadas primero
                    $query->orderByRaw('CASE 
                            WHEN sellers.is_featured = 1 AND (sellers.featured_expires_at IS NULL OR sellers.featured_expires_at > NOW()) 
                            THEN 1 ELSE 0 END DESC')
                        ->orderBy($columnName, $sortDir);
                    break;
            }
        } else {
            // Ordenamiento por defecto: productos destacados > tiendas destacadas > fecha de creación
            $query->orderBy('products.featured', 'desc')
                ->orderByRaw('CASE 
                    WHEN sellers.is_featured = 1 AND (sellers.featured_expires_at IS NULL OR sellers.featured_expires_at > NOW()) 
                    THEN 1 ELSE 0 END DESC')
                ->orderBy('products.created_at', 'desc');
        }
    }

    private function mapEntityToModel(ProductEntity $entity, Product $model): void
    {
        // No asignar el ID si es un modelo nuevo (creación)
        if ($model->exists && $entity->getId() > 0) {
            $model->id = $entity->getId();
        }

        $model->user_id = $entity->getUserId();
        $model->category_id = $entity->getCategoryId();
        $model->name = $entity->getName();
        $model->slug = $entity->getSlug();
        $model->description = $entity->getDescription();

        // ✅ Corregido: Usar strings para campos decimales para evitar problemas de conversión
        $model->rating = $entity->getRating() !== null ? (string) $entity->getRating() : '0';
        $model->rating_count = $entity->getRatingCount();
        $model->price = (string) $entity->getPrice();
        $model->stock = $entity->getStock();

        // ✅ Corregido: Convertir a string para campos decimales opcionales
        $model->weight = $entity->getWeight() !== null ? (string) $entity->getWeight() : null;
        $model->width = $entity->getWidth() !== null ? (string) $entity->getWidth() : null;
        $model->height = $entity->getHeight() !== null ? (string) $entity->getHeight() : null;
        $model->depth = $entity->getDepth() !== null ? (string) $entity->getDepth() : null;

        $model->dimensions = $entity->getDimensions();
        $model->colors = $entity->getColors();
        $model->sizes = $entity->getSizes();
        $model->tags = $entity->getTags();
        $model->sku = $entity->getSku();
        $model->attributes = $entity->getAttributes();
        $model->images = $entity->getImages();
        $model->featured = $entity->isFeatured();
        $model->published = $entity->isPublished();
        $model->status = $entity->getStatus();
        $model->view_count = $entity->getViewCount();
        $model->sales_count = $entity->getSalesCount();

        // ✅ Corregido: Convertir a string para campo decimal
        $model->discount_percentage = $entity->getDiscountPercentage() !== null ? (string) $entity->getDiscountPercentage() : null;
        $model->short_description = $entity->getShortDescription();

        if ($entity->getSellerId() !== null) {
            $model->setAttribute('seller_id', $entity->getSellerId());
        }
    }

    private function mapModelsToEntities($models): array
    {
        $entities = [];

        foreach ($models as $model) {
            $entities[] = $this->mapModelToEntity($model);
        }

        return $entities;
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * Auto cleanup expired featured sellers (with cache to avoid excessive calls)
     */
    private function autoCleanupExpiredFeatured(): void
    {
        $cacheKey = 'last_featured_cleanup_products';
        $lastCleanup = \Cache::get($cacheKey);

        // Solo ejecutar cada 15 minutos
        if (! $lastCleanup || now()->diffInMinutes($lastCleanup) >= 15) {
            try {
                $updated = \App\Models\Seller::where('is_featured', true)
                    ->whereNotNull('featured_expires_at')
                    ->where('featured_expires_at', '<=', now())
                    ->update(['is_featured' => false]);

                if ($updated > 0) {
                    Log::info("Auto-cleaned {$updated} expired featured sellers", [
                        'cleanup_method' => 'product_repository',
                    ]);
                }

                \Cache::put($cacheKey, now(), now()->addMinutes(15));
            } catch (\Exception $e) {
                Log::error('Error in product repository auto-cleanup', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
