<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\AdminDiscountCodeEntity;
use App\Domain\Repositories\AdminDiscountCodeRepositoryInterface;
use App\Models\AdminDiscountCode;

class EloquentAdminDiscountCodeRepository implements AdminDiscountCodeRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function create(AdminDiscountCodeEntity $discountCode): AdminDiscountCodeEntity
    {
        $model = new AdminDiscountCode;
        $this->mapEntityToModel($discountCode, $model);
        $model->save();

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function update(AdminDiscountCodeEntity $discountCode): AdminDiscountCodeEntity
    {
        $model = AdminDiscountCode::findOrFail($discountCode->getId());
        $this->mapEntityToModel($discountCode, $model);
        $model->save();

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?AdminDiscountCodeEntity
    {
        $model = AdminDiscountCode::find($id);
        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findByCode(string $code): ?AdminDiscountCodeEntity
    {
        $model = AdminDiscountCode::where('code', $code)->first();
        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(array $filters = [], int $limit = 10, int $offset = 0): array
    {
        $query = AdminDiscountCode::query()->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['is_used'])) {
            if ($filters['is_used'] === true || $filters['is_used'] === 'used') {
                $query->used();
            } elseif ($filters['is_used'] === false || $filters['is_used'] === 'unused') {
                $query->unused();
            }
        }

        if (isset($filters['validity'])) {
            if ($filters['validity'] === 'valid') {
                $query->valid();
            } elseif ($filters['validity'] === 'expired') {
                $query->expired();
            }
        }

        if (isset($filters['percentage_range'])) {
            $query->byPercentageRange($filters['percentage_range']);
        }

        if (isset($filters['code'])) {
            $query->where('code', 'like', '%'.$filters['code'].'%');
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        $models = $query->skip($offset)->take($limit)->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function count(array $filters = []): int
    {
        $query = AdminDiscountCode::query();

        // Apply same filters as findAll
        if (isset($filters['is_used'])) {
            if ($filters['is_used'] === true || $filters['is_used'] === 'used') {
                $query->used();
            } elseif ($filters['is_used'] === false || $filters['is_used'] === 'unused') {
                $query->unused();
            }
        }

        if (isset($filters['validity'])) {
            if ($filters['validity'] === 'valid') {
                $query->valid();
            } elseif ($filters['validity'] === 'expired') {
                $query->expired();
            }
        }

        if (isset($filters['percentage_range'])) {
            $query->byPercentageRange($filters['percentage_range']);
        }

        if (isset($filters['code'])) {
            $query->where('code', 'like', '%'.$filters['code'].'%');
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->count();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id): bool
    {
        $model = AdminDiscountCode::find($id);
        if (! $model) {
            return false;
        }

        return $model->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function findValid(int $limit = 10, int $offset = 0): array
    {
        $models = AdminDiscountCode::valid()
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function findExpired(int $limit = 10, int $offset = 0): array
    {
        $models = AdminDiscountCode::expired()
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function findUsed(int $limit = 10, int $offset = 0): array
    {
        $models = AdminDiscountCode::used()
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $query = AdminDiscountCode::where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Map AdminDiscountCode model to AdminDiscountCodeEntity.
     */
    private function mapModelToEntity(AdminDiscountCode $model): AdminDiscountCodeEntity
    {
        return new AdminDiscountCodeEntity(
            $model->code,
            $model->discount_percentage,
            $model->expires_at ? $model->expires_at->toDateTimeString() : '',
            $model->created_by,
            $model->is_used,
            $model->used_by,
            $model->used_at ? $model->used_at->toDateTimeString() : null,
            $model->used_on_product_id,
            $model->description,
            $model->id,
            $model->created_at ? $model->created_at->toDateTimeString() : null,
            $model->updated_at ? $model->updated_at->toDateTimeString() : null
        );
    }

    /**
     * Map AdminDiscountCodeEntity to AdminDiscountCode model.
     */
    private function mapEntityToModel(AdminDiscountCodeEntity $entity, AdminDiscountCode $model): void
    {
        $model->code = $entity->getCode();
        $model->discount_percentage = $entity->getDiscountPercentage();
        $model->is_used = $entity->isUsed();
        $model->used_by = $entity->getUsedBy();
        $model->used_at = $entity->getUsedAt();
        $model->used_on_product_id = $entity->getUsedOnProductId();
        $model->expires_at = $entity->getExpiresAt();
        $model->description = $entity->getDescription();
        $model->created_by = $entity->getCreatedBy();
    }

    /**
     * Map collection of models to entities.
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
