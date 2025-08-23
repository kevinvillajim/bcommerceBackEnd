<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\DiscountCodeEntity;
use App\Domain\Repositories\DiscountCodeRepositoryInterface;
use App\Models\DiscountCode;
use Illuminate\Support\Str;

class EloquentDiscountCodeRepository implements DiscountCodeRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function create(DiscountCodeEntity $discountCode): DiscountCodeEntity
    {
        $model = new DiscountCode;
        $this->mapEntityToModel($discountCode, $model);
        $model->save();

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function update(DiscountCodeEntity $discountCode): DiscountCodeEntity
    {
        $model = DiscountCode::findOrFail($discountCode->getId());
        $this->mapEntityToModel($discountCode, $model);
        $model->save();

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?DiscountCodeEntity
    {
        $model = DiscountCode::find($id);

        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findByCode(string $code): ?DiscountCodeEntity
    {
        $model = DiscountCode::where('code', $code)->first();

        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findByFeedbackId(int $feedbackId): ?DiscountCodeEntity
    {
        $model = DiscountCode::where('feedback_id', $feedbackId)->first();

        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findActive(int $limit = 10, int $offset = 0): array
    {
        $models = DiscountCode::where('is_used', false)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
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
        $models = DiscountCode::where('is_used', true)
            ->orderBy('used_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function findByUserId(int $userId, int $limit = 10, int $offset = 0, bool $onlyActive = true): array
    {
        $query = DiscountCode::join('feedback', 'discount_codes.feedback_id', '=', 'feedback.id')
            ->where('feedback.user_id', $userId)
            ->select('discount_codes.*');

        if ($onlyActive) {
            $query->where(function ($q) {
                $q->whereNull('discount_codes.expires_at')
                    ->orWhere('discount_codes.expires_at', '>', now());
            })->where('discount_codes.is_used', false);
        }

        $models = $query->orderBy('discount_codes.created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function countByUserId(int $userId, bool $onlyActive = true): int
    {
        $query = DiscountCode::join('feedback', 'discount_codes.feedback_id', '=', 'feedback.id')
            ->where('feedback.user_id', $userId);

        if ($onlyActive) {
            $query->where(function ($q) {
                $q->whereNull('discount_codes.expires_at')
                    ->orWhere('discount_codes.expires_at', '>', now());
            })->where('discount_codes.is_used', false);
        }

        return $query->count();
    }

    /**
     * {@inheritDoc}
     */
    public function codeExists(string $code): bool
    {
        return DiscountCode::where('code', $code)->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function generateUniqueCode(int $length = 6): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while ($this->codeExists($code));

        return $code;
    }

    /**
     * Map a DiscountCode model to a DiscountCodeEntity.
     */
    private function mapModelToEntity(DiscountCode $model): DiscountCodeEntity
    {
        return new DiscountCodeEntity(
            $model->feedback_id,
            $model->code,
            $model->discount_percentage,
            $model->is_used,
            $model->used_by,
            $model->used_at ? $model->used_at->toDateTimeString() : null,
            $model->used_on_product_id,
            $model->expires_at ? $model->expires_at->toDateTimeString() : null,
            $model->id,
            $model->created_at ? $model->created_at->toDateTimeString() : null,
            $model->updated_at ? $model->updated_at->toDateTimeString() : null
        );
    }

    /**
     * Map a DiscountCodeEntity to a DiscountCode model.
     */
    private function mapEntityToModel(DiscountCodeEntity $entity, DiscountCode $model): void
    {
        $model->feedback_id = $entity->getFeedbackId();
        $model->code = $entity->getCode();
        $model->discount_percentage = $entity->getDiscountPercentage();
        $model->is_used = $entity->isUsed();
        $model->used_by = $entity->getUsedBy();

        if ($entity->getUsedAt()) {
            $model->used_at = $entity->getUsedAt();
        }

        $model->used_on_product_id = $entity->getUsedOnProductId();

        if ($entity->getExpiresAt()) {
            $model->expires_at = $entity->getExpiresAt();
        }
    }

    /**
     * Map a collection of DiscountCode models to an array of DiscountCodeEntities.
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
