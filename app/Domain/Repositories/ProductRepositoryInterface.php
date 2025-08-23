<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\ProductEntity;

interface ProductRepositoryInterface
{
    /**
     * Crea un nuevo producto
     */
    public function create(ProductEntity $product): ProductEntity;

    /**
     * Actualiza un producto existente
     */
    public function update(ProductEntity $product): ProductEntity;

    /**
     * Encuentra un producto por su ID
     */
    public function findById(int $id): ?ProductEntity;

    /**
     * Encuentra un producto por su slug
     */
    public function findBySlug(string $slug): ?ProductEntity;

    /**
     * Elimina un producto
     */
    public function delete(int $id): bool;

    /**
     * Actualiza parcialmente un producto (solo los campos especificados).
     */
    public function updatePartial(int $id, array $data): bool;

    /**
     * Obtiene el valor total del inventario.
     */
    public function getTotalInventoryValue(): float;

    /**
     * Busca productos por múltiples IDs de categorías.
     */
    public function findProductsByCategories(array $categoryIds, array $excludeIds = [], int $limit = 10): array;

    /**
     * Obtiene productos por categoría
     */
    public function findByCategory(int $categoryId, int $limit = 10, int $offset = 0): array;

    /**
     * Busca productos
     */
    public function search(string $term, array $filters = [], int $limit = 10, int $offset = 0): array;

    /**
     * Obtiene todos los productos
     */
    public function findAll(int $limit = 10, int $offset = 0): array;

    /**
     * Encuentra productos por vendedor
     */
    public function findBySeller(int $userId, int $limit = 10, int $offset = 0): array;

    /**
     * Encuentra productos por tags
     */
    public function findByTags(array $tags, int $limit = 10, int $offset = 0): array;

    /**
     * Encuentra productos destacados
     */
    public function findFeatured(int $limit = 10, int $offset = 0): array;

    /**
     * Incrementa el contador de vistas de un producto
     */
    public function incrementViewCount(int $id): bool;

    /**
     * Actualiza el inventario de un producto
     *
     * @param  string  $operation  Operación a realizar: 'increase', 'decrease' o 'replace'
     */
    public function updateStock(int $id, int $quantity, string $operation = 'replace'): bool;

    /**
     * Cuenta el total de productos
     */
    public function count(array $filters = []): int;

    /**
     * Encuentra productos populares.
     */
    public function findPopularProducts(int $limit, array $excludeIds = []): array;

    /**
     * Busca productos por categoría específica.
     */
    public function findProductsByCategory(int $categoryId, array $excludeIds = [], int $limit = 10): array;

    /**
     * Encuentra productos por tags.
     */
    public function findProductsByTags(array $tags, array $excludeIds = [], int $limit = 10): array;

    /**
     * Encuentra productos por término de búsqueda.
     */
    public function findProductsBySearch(string $term, array $excludeIds = [], int $limit = 10): array;
}
