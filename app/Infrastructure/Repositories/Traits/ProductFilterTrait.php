<?php

namespace App\Infrastructure\Repositories\Traits;

use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait que proporciona métodos para filtrar productos
 * Útil para reutilizar la lógica de filtros en diferentes repositories
 */
trait ProductFilterTrait
{
    /**
     * Aplica filtros avanzados a una consulta de productos
     *
     * @param  Builder  $query  Consulta inicial
     * @param  array  $filters  Array asociativo de filtros
     * @return Builder Consulta con filtros aplicados
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        // Filtros de categoría
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        } elseif (isset($filters['category_ids'])) {
            $categoryIds = $filters['category_ids'];

            // Permite incluir todas las subcategorías de las categorías seleccionadas
            if (isset($filters['include_children']) && $filters['include_children']) {
                $allCategoryIds = [];
                foreach ($categoryIds as $categoryId) {
                    $category = Category::find($categoryId);
                    if ($category) {
                        $allCategoryIds = array_merge($allCategoryIds, $category->getAllDescendantIds());
                    }
                }
                $categoryIds = array_unique($allCategoryIds);
            }

            // Operador para categorías: OR (cualquier categoría) o AND (todas las categorías)
            $operator = $filters['category_operator'] ?? 'or';

            if ($operator === 'or') {
                $query->whereIn('category_id', $categoryIds);
            } else {
                // Para AND, necesitamos una lógica más compleja que no implementamos aquí
                // ya que un producto no puede estar en múltiples categorías a la vez
                $query->whereIn('category_id', $categoryIds);
            }
        }

        // Filtro de precio
        if (isset($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        // Filtro de descuento
        if (isset($filters['min_discount'])) {
            $query->where('discount_percentage', '>=', $filters['min_discount']);
        }

        // Filtro de rating
        if (isset($filters['rating'])) {
            $query->where('rating', '>=', $filters['rating']);
        }

        // Filtro por marca
        if (isset($filters['brand'])) {
            $brands = is_array($filters['brand']) ? $filters['brand'] : [$filters['brand']];
            $query->whereIn('brand', $brands);
        }

        // Filtro por vendedor
        if (isset($filters['seller_id'])) {
            $query->where('user_id', $filters['seller_id']);
        }

        // Filtros de estado
        if (isset($filters['published'])) {
            $query->where('published', $filters['published']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['featured'])) {
            $query->where('featured', $filters['featured']);
        }

        // Filtro de stock
        if (isset($filters['in_stock']) && $filters['in_stock']) {
            $query->where('stock', '>', 0);
        }

        if (isset($filters['stock_min'])) {
            $query->where('stock', '>=', $filters['stock_min']);
        }

        if (isset($filters['stock_max'])) {
            $query->where('stock', '<=', $filters['stock_max']);
        }

        // Filtro por colores
        if (isset($filters['colors'])) {
            $colors = $filters['colors'];
            if (! is_array($colors)) {
                $colors = [$colors];
            }

            foreach ($colors as $color) {
                $query->whereJsonContains('colors', $color);
            }
        }

        // Filtro por tamaños
        if (isset($filters['sizes'])) {
            $sizes = $filters['sizes'];
            if (! is_array($sizes)) {
                $sizes = [$sizes];
            }

            foreach ($sizes as $size) {
                $query->whereJsonContains('sizes', $size);
            }
        }

        // Filtro por tags
        if (isset($filters['tags'])) {
            $tags = $filters['tags'];
            if (! is_array($tags)) {
                $tags = [$tags];
            }

            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        // Filtro para productos nuevos (últimos 30 días)
        if (isset($filters['is_new']) && $filters['is_new']) {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        }

        // Filtro por disponibilidad
        if (isset($filters['available_now']) && $filters['available_now']) {
            $now = Carbon::now();
            $query->where(function ($q) use ($now) {
                $q->whereNull('available_from')
                    ->orWhere('available_from', '<=', $now);
            })->where(function ($q) use ($now) {
                $q->whereNull('available_until')
                    ->orWhere('available_until', '>=', $now);
            });
        }

        // Filtro por productos digitales (descargables)
        if (isset($filters['downloadable'])) {
            $query->where('downloadable', $filters['downloadable']);
        }

        // Filtro de búsqueda por término
        if (isset($filters['search_term']) && ! empty($filters['search_term'])) {
            $term = $filters['search_term'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('short_description', 'like', "%{$term}%")
                    ->orWhere('meta_keywords', 'like', "%{$term}%")
                    ->orWhere('model_number', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhereJsonContains('tags', $term);
            });
        }

        // Ordenamiento
        if (isset($filters['sortBy'])) {
            $sortBy = $filters['sortBy'];
            $sortDir = $filters['sortDir'] ?? 'desc';

            // Manejo de casos especiales de ordenamiento
            switch ($sortBy) {
                case 'price':
                    $query->orderBy('price', $sortDir);
                    break;

                case 'name':
                    $query->orderBy('name', $sortDir);
                    break;

                case 'rating':
                    $query->orderBy('rating', $sortDir);
                    break;

                case 'popularity':
                    $query->orderBy('view_count', $sortDir);
                    break;

                case 'sales':
                    $query->orderBy('sales_count', $sortDir);
                    break;

                case 'created_at':
                    $query->orderBy('created_at', $sortDir);
                    break;

                case 'featured':
                    // Productos destacados primero, luego ordenar por otro criterio
                    $query->orderBy('featured', 'desc')
                        ->orderBy('rating', 'desc');
                    break;

                default:
                    // Ordenamiento por defecto
                    $query->orderBy('created_at', 'desc');
                    break;
            }
        } else {
            // Ordenamiento por defecto
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }
}
