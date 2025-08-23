<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\CategoryEntity;

interface CategoryRepositoryInterface
{
    /**
     * Encuentra una categoría por su ID
     */
    public function findById(int $id): ?CategoryEntity;

    /**
     * Encuentra una categoría por su slug
     */
    public function findBySlug(string $slug): ?CategoryEntity;

    /**
     * Encuentra todas las categorías
     */
    public function findAll(bool $onlyActive = true): array;

    /**
     * Encuentra categorías destacadas
     */
    public function findFeatured(int $limit = 10): array;

    /**
     * Encuentra categorías principales (sin padre)
     */
    public function findMainCategories(bool $onlyActive = true): array;

    /**
     * Encuentra subcategorías de una categoría padre
     */
    public function findSubcategories(int $parentId, bool $onlyActive = true): array;

    /**
     * Guarda una categoría (crea o actualiza)
     */
    public function save(CategoryEntity $category): CategoryEntity;

    /**
     * Elimina una categoría
     */
    public function delete(int $id): bool;

    /**
     * Cuenta el total de categorías
     */
    public function count(array $filters = []): int;

    /**
     * Crea una categoría a partir de un array de datos
     */
    public function createFromArray(array $data): CategoryEntity;

    /**
     * Actualiza una categoría a partir de un array de datos
     */
    public function updateFromArray(int $id, array $data): CategoryEntity;
}
