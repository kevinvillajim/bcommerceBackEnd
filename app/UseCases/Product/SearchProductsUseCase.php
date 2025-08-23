<?php

namespace App\UseCases\Product;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\UseCases\Recommendation\TrackUserInteractionsUseCase;
use Illuminate\Support\Facades\Log;

class SearchProductsUseCase
{
    private ProductRepositoryInterface $productRepository;

    private ?TrackUserInteractionsUseCase $trackUserInteractionsUseCase;

    private ProductFormatter $productFormatter;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductFormatter $productFormatter,
        ?TrackUserInteractionsUseCase $trackUserInteractionsUseCase = null
    ) {
        $this->productRepository = $productRepository;
        $this->productFormatter = $productFormatter;
        $this->trackUserInteractionsUseCase = $trackUserInteractionsUseCase;
    }

    /**
     * Ejecuta la búsqueda de productos
     */
    public function execute(
        string $term = '',
        array $filters = [],
        int $limit = 10,
        int $offset = 0,
        ?int $userId = null
    ): array {
        try {
            // Validar y sanitizar parámetros
            $limit = max(1, min(100, $limit)); // Entre 1 y 100
            $offset = max(0, $offset);

            // Preparar filtros para el repository
            $repositoryFilters = $this->prepareFiltersForRepository($filters, $term);

            // Realizar la búsqueda
            $products = $this->productRepository->search($term, $repositoryFilters, $limit, $offset);

            // Contar total de resultados para paginación
            $total = $this->productRepository->count($repositoryFilters);

            // Registrar la interacción de búsqueda si hay usuario
            if ($userId && $this->trackUserInteractionsUseCase && ! empty($term)) {
                $this->trackUserInteractionsUseCase->execute(
                    $userId,
                    'search',
                    0, // Sin ID de ítem específico
                    [
                        'term' => $term,
                        'filters' => $filters,
                        'results_count' => count($products),
                        'total_count' => $total,
                    ]
                );
            }

            // Formatear productos para la respuesta
            $productData = [];
            foreach ($products as $product) {
                $productData[] = $this->productFormatter->formatForApi($product);
            }

            return [
                'data' => $productData,
                'meta' => [
                    'total' => $total,
                    'count' => count($products),
                    'limit' => $limit,
                    'offset' => $offset,
                    'term' => $term,
                    'filters' => $filters,
                    'has_more' => ($offset + $limit) < $total,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Error en búsqueda de productos: '.$e->getMessage(), [
                'term' => $term,
                'filters' => $filters,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'count' => 0,
                    'limit' => $limit,
                    'offset' => $offset,
                    'term' => $term,
                    'filters' => $filters,
                    'error' => 'Error al realizar la búsqueda',
                    'has_more' => false,
                ],
            ];
        }
    }

    /**
     * Ejecuta la búsqueda por categoría
     */
    public function executeByCategory(
        int $categoryId,
        int $limit = 10,
        int $offset = 0,
        ?int $userId = null
    ): array {
        try {
            // Validar parámetros
            $limit = max(1, min(100, $limit));
            $offset = max(0, $offset);

            // Usar el método de búsqueda general con filtro de categoría
            $filters = ['category_id' => $categoryId];

            return $this->execute('', $filters, $limit, $offset, $userId);

        } catch (\Exception $e) {
            Log::error('Error en búsqueda por categoría: '.$e->getMessage(), [
                'category_id' => $categoryId,
                'user_id' => $userId,
            ]);

            return [
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'count' => 0,
                    'limit' => $limit,
                    'offset' => $offset,
                    'category_id' => $categoryId,
                    'error' => 'Error al buscar productos por categoría',
                    'has_more' => false,
                ],
            ];
        }
    }

    /**
     * Ejecuta la búsqueda por tags
     */
    public function executeByTags(
        array $tags,
        int $limit = 10,
        int $offset = 0,
        ?int $userId = null,
        array $additionalFilters = []
    ): array {
        try {
            // Validar parámetros
            $limit = max(1, min(100, $limit));
            $offset = max(0, $offset);

            if (empty($tags)) {
                return [
                    'data' => [],
                    'meta' => [
                        'total' => 0,
                        'count' => 0,
                        'limit' => $limit,
                        'offset' => $offset,
                        'tags' => $tags,
                        'message' => 'No se proporcionaron tags para buscar',
                        'has_more' => false,
                    ],
                ];
            }

            // Preparar filtros
            $filters = array_merge($additionalFilters, ['tags' => $tags]);

            // Ejecutar búsqueda
            $result = $this->execute('', $filters, $limit, $offset, $userId);

            // Agregar información específica de tags
            $result['meta']['tags'] = $tags;

            // Registrar interacción específica por tags
            if ($userId && $this->trackUserInteractionsUseCase) {
                $this->trackUserInteractionsUseCase->execute(
                    $userId,
                    'search_tags',
                    0,
                    [
                        'tags' => $tags,
                        'results_count' => $result['meta']['count'],
                        'total_count' => $result['meta']['total'],
                    ]
                );
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Error en búsqueda por tags: '.$e->getMessage(), [
                'tags' => $tags,
                'user_id' => $userId,
            ]);

            return [
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'count' => 0,
                    'limit' => $limit,
                    'offset' => $offset,
                    'tags' => $tags,
                    'error' => 'Error al buscar productos por tags',
                    'has_more' => false,
                ],
            ];
        }
    }

    /**
     * Prepara los filtros para el repository
     */
    private function prepareFiltersForRepository(array $filters, string $term = ''): array
    {
        $repositoryFilters = $filters;

        // Si hay término de búsqueda, agregarlo a los filtros
        if (! empty($term)) {
            $repositoryFilters['search'] = $term;
        }

        // Asegurar que siempre estén los filtros básicos
        $repositoryFilters['published'] = $repositoryFilters['published'] ?? true;
        $repositoryFilters['status'] = $repositoryFilters['status'] ?? 'active';

        // ✅ MAPEAR calculateRatingsFromTable para compatibilidad con frontend
        if (! empty($filters['calculateRatingsFromTable']) || ! empty($filters['calculate_ratings_from_table'])) {
            $repositoryFilters['calculate_ratings_from_table'] = true;
        }

        return $repositoryFilters;
    }

    /**
     * Calcula la información de paginación
     */
    private function calculatePagination(int $total, int $limit, int $offset): array
    {
        $currentPage = intval($offset / $limit) + 1;
        $totalPages = $total > 0 ? intval(ceil($total / $limit)) : 1;

        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'has_next' => $currentPage < $totalPages,
            'has_previous' => $currentPage > 1,
            'next_offset' => $currentPage < $totalPages ? $offset + $limit : null,
            'previous_offset' => $currentPage > 1 ? max(0, $offset - $limit) : null,
        ];
    }
}
