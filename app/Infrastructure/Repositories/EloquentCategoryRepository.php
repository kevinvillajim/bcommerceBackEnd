<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\CategoryEntity;
use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Domain\ValueObjects\CategoryId;
use App\Domain\ValueObjects\Slug;
use App\Models\Category;
use Illuminate\Support\Str;

class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?CategoryEntity
    {
        $model = Category::find($id);

        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findBySlug(string $slug): ?CategoryEntity
    {
        $model = Category::where('slug', $slug)->first();

        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(bool $onlyActive = true): array
    {
        $query = Category::query();

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        $categories = $query->orderBy('order', 'asc')->get();

        return $this->mapModelsToEntities($categories);
    }

    /**
     * {@inheritDoc}
     */
    public function findFeatured(int $limit = 10): array
    {
        $categories = Category::where('featured', true)
            ->where('is_active', true)
            ->orderBy('order', 'asc')
            ->limit($limit)
            ->get();

        return $this->mapModelsToEntities($categories);
    }

    /**
     * {@inheritDoc}
     */
    public function findMainCategories(bool $onlyActive = true): array
    {
        $query = Category::whereNull('parent_id');

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        $categories = $query->orderBy('order', 'asc')->get();

        return $this->mapModelsToEntities($categories);
    }

    /**
     * {@inheritDoc}
     */
    public function findSubcategories(int $parentId, bool $onlyActive = true): array
    {
        $query = Category::where('parent_id', $parentId);

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        $categories = $query->orderBy('order', 'asc')->get();

        return $this->mapModelsToEntities($categories);
    }

    /**
     * {@inheritDoc}
     */
    public function save(CategoryEntity $category): CategoryEntity
    {
        if ($category->getId()) {
            $model = Category::findOrFail($category->getId()->getValue());
        } else {
            $model = new Category;
        }

        $model->name = $category->getName();
        $model->slug = $category->getSlug()->getValue();
        $model->description = $category->getDescription();
        $model->parent_id = $category->getParentId() ? $category->getParentId()->getValue() : null;
        $model->icon = $category->getIcon();
        $model->image = $category->getImage();
        $model->order = $category->getOrder() ?? 0;
        $model->is_active = $category->isActive();
        $model->featured = $category->isFeatured();

        $model->save();

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id): bool
    {
        $model = Category::find($id);

        if (! $model) {
            return false;
        }

        return $model->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function count(array $filters = []): int
    {
        $query = Category::query();

        if (isset($filters['active'])) {
            $query->where('is_active', $filters['active']);
        }

        if (isset($filters['featured'])) {
            $query->where('featured', $filters['featured']);
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        return $query->count();
    }

    /**
     * {@inheritDoc}
     */
    public function createFromArray(array $data): CategoryEntity
    {
        // Generar slug si no existe
        if (! isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $categoryEntity = new CategoryEntity(
            $data['name'],
            new Slug($data['slug']),
            $data['description'] ?? null,
            isset($data['parent_id']) ? new CategoryId($data['parent_id']) : null,
            $data['icon'] ?? null,
            $data['image'] ?? null,
            $data['order'] ?? 0,
            $data['is_active'] ?? true,
            $data['featured'] ?? false
        );

        return $this->save($categoryEntity);
    }

    /**
     * {@inheritDoc}
     */
    public function updateFromArray(int $id, array $data): CategoryEntity
    {
        // Obtener la categoría actual
        $category = $this->findById($id);

        if (! $category) {
            throw new \Exception("Category with ID {$id} not found");
        }

        // Actualizar nombre si se proporciona
        if (isset($data['name'])) {
            $category->setName($data['name']);
        }

        // Actualizar slug si se proporciona o regenerar a partir del nombre
        if (isset($data['slug'])) {
            $category->setSlug($data['slug']);
        } elseif (isset($data['name'])) {
            $category->setSlug(Str::slug($data['name']));
        }

        // Actualizar otros campos si se proporcionan
        if (isset($data['description'])) {
            $category->setDescription($data['description']);
        }

        if (isset($data['parent_id'])) {
            $category->setParentId($data['parent_id']);
        }

        if (isset($data['icon'])) {
            $category->setIcon($data['icon']);
        }

        if (isset($data['image'])) {
            $category->setImage($data['image']);
        }

        if (isset($data['order'])) {
            $category->setOrder($data['order']);
        }

        if (isset($data['is_active'])) {
            if ($data['is_active']) {
                $category->activate();
            } else {
                $category->deactivate();
            }
        }

        if (isset($data['featured'])) {
            if ($data['featured']) {
                $category->markAsFeatured();
            } else {
                $category->unmarkAsFeatured();
            }
        }

        // Guardar los cambios
        return $this->save($category);
    }

    /**
     * Mapea un modelo Category a una entidad CategoryEntity
     */
    private function mapModelToEntity(Category $model): CategoryEntity
    {
        return new CategoryEntity(
            $model->name,
            new Slug($model->slug),
            $model->description,
            $model->parent_id ? new CategoryId($model->parent_id) : null,
            $model->icon,
            $model->image,
            $model->order,
            (bool) $model->is_active,
            (bool) $model->featured,
            new CategoryId($model->id),
            $model->created_at,
            $model->updated_at
        );
    }

    /**
     * Mapea una colección de modelos a un array de entidades
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     */
    private function mapModelsToEntities($models): array
    {
        $entities = [];

        foreach ($models as $model) {
            $entities[] = $this->mapModelToEntity($model);
        }

        return $entities;
    }
}
