<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\NotificationEntity;
use App\Domain\Repositories\NotificationRepositoryInterface;
use App\Models\Notification;

class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function create(NotificationEntity $notification): NotificationEntity
    {
        $model = new Notification;
        $model->user_id = $notification->getUserId();
        $model->type = $notification->getType();
        $model->title = $notification->getTitle();
        $model->message = $notification->getMessage();
        $model->data = json_encode($notification->getData());
        $model->read = $notification->isRead();
        $model->read_at = $notification->getReadAt() ? $notification->getReadAt()->format('Y-m-d H:i:s') : null;
        $model->save();

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?NotificationEntity
    {
        $model = Notification::find($id);

        if (! $model) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $models = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function findUnreadByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $models = Notification::where('user_id', $userId)
            ->where('read', false)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapModelsToEntities($models);
    }

    /**
     * {@inheritDoc}
     */
    public function countUnreadByUserId(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('read', false)
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function markAsRead(int $id): bool
    {
        $notification = Notification::find($id);

        if (! $notification) {
            return false;
        }

        $notification->read = true;
        $notification->read_at = now();

        return $notification->save();
    }

    /**
     * {@inheritDoc}
     */
    public function markAllAsRead(int $userId): bool
    {
        return Notification::where('user_id', $userId)
            ->where('read', false)
            ->update([
                'read' => true,
                'read_at' => now(),
            ]) > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id): bool
    {
        $notification = Notification::find($id);

        if (! $notification) {
            return false;
        }

        return $notification->delete();
    }

    /**
     * Map a model to an entity
     */
    private function mapModelToEntity(Notification $model): NotificationEntity
    {
        // ✅ FIX: Manejar data que ya es array o string JSON
        $data = is_array($model->data) ? $model->data : (json_decode($model->data, true) ?? []);
        $readAt = $model->read_at ? new \DateTime($model->read_at) : null;
        $createdAt = new \DateTime($model->created_at);

        return new NotificationEntity(
            $model->user_id,
            $model->type,
            $model->title,
            $model->message,
            $data,
            $model->read,
            $model->id,
            $readAt,
            $createdAt
        );
    }

    /**
     * Map multiple models to entities
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
