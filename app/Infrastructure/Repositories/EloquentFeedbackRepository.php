<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\FeedbackEntity;
use App\Domain\Repositories\FeedbackRepositoryInterface;
use App\Models\Feedback;

class EloquentFeedbackRepository implements FeedbackRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function create(FeedbackEntity $feedback): FeedbackEntity
    {
        $model = new Feedback;
        $this->mapEntityToModel($feedback, $model);
        $model->save();

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function update(FeedbackEntity $feedback): FeedbackEntity
    {
        $model = Feedback::findOrFail($feedback->getId());
        $this->mapEntityToModel($feedback, $model);
        $model->save();

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?FeedbackEntity
    {
        $model = Feedback::find($id);

        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findByUserId(int $userId, int $limit = 10, int $offset = 0): array
    {
        $models = Feedback::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function findBySellerId(int $sellerId, int $limit = 10, int $offset = 0): array
    {
        $models = Feedback::where('seller_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function findPending(int $limit = 10, int $offset = 0): array
    {
        $models = Feedback::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function findApproved(int $limit = 10, int $offset = 0): array
    {
        $models = Feedback::where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(int $limit = 10, int $offset = 0): array
    {
        $models = Feedback::orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function count(array $filters = []): int
    {
        $query = Feedback::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->count();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id): bool
    {
        $model = Feedback::find($id);

        if (! $model) {
            return false;
        }

        return $model->delete();
    }

    /**
     * Map a Feedback model to a FeedbackEntity.
     */
    private function mapModelToEntity(Feedback $model): FeedbackEntity
    {
        return new FeedbackEntity(
            $model->user_id,
            $model->title,
            $model->description,
            $model->seller_id,
            $model->type,
            $model->status,
            $model->admin_notes,
            $model->reviewed_by,
            $model->reviewed_at ? $model->reviewed_at->toDateTimeString() : null,
            $model->id,
            $model->created_at ? $model->created_at->toDateTimeString() : null,
            $model->updated_at ? $model->updated_at->toDateTimeString() : null
        );
    }

    /**
     * Map a FeedbackEntity to a Feedback model.
     */
    private function mapEntityToModel(FeedbackEntity $entity, Feedback $model): void
    {
        $model->user_id = $entity->getUserId();
        $model->seller_id = $entity->getSellerId();
        $model->title = $entity->getTitle();
        $model->description = $entity->getDescription();
        $model->type = $entity->getType();
        $model->status = $entity->getStatus();
        $model->admin_notes = $entity->getAdminNotes();
        $model->reviewed_by = $entity->getReviewedBy();

        if ($entity->getReviewedAt()) {
            $model->reviewed_at = $entity->getReviewedAt();
        }
    }

    /**
     * Map a collection of Feedback models to an array of FeedbackEntities.
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
