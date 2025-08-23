<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\AdminLogEntity;
use App\Domain\Repositories\AdminLogRepositoryInterface;
use App\Models\AdminLog;
use Carbon\Carbon;

class EloquentAdminLogRepository implements AdminLogRepositoryInterface
{
    /**
     * Obtener todos los logs con filtros y paginación
     */
    public function findAll(array $filters = [], int $limit = 10, int $offset = 0): array
    {
        $query = AdminLog::with('user:id,name,email')->orderBy('created_at', 'desc');

        // Aplicar filtros
        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['status_code'])) {
            $query->where('status_code', $filters['status_code']);
        }

        if (! empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('message', 'LIKE', "%{$search}%")
                    ->orWhere('url', 'LIKE', "%{$search}%")
                    ->orWhere('event_type', 'LIKE', "%{$search}%");
            });
        }

        $models = $query->offset($offset)->limit($limit)->get();

        return $models->map(function ($model) {
            return $this->modelToEntity($model);
        })->toArray();
    }

    /**
     * Contar logs con filtros
     */
    public function count(array $filters = []): int
    {
        $query = AdminLog::query();

        // Aplicar los mismos filtros que en findAll
        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['status_code'])) {
            $query->where('status_code', $filters['status_code']);
        }

        if (! empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('message', 'LIKE', "%{$search}%")
                    ->orWhere('url', 'LIKE', "%{$search}%")
                    ->orWhere('event_type', 'LIKE', "%{$search}%");
            });
        }

        return $query->count();
    }

    /**
     * Encontrar log por ID
     */
    public function findById(int $id): ?AdminLogEntity
    {
        $model = AdminLog::with('user:id,name,email')->find($id);

        return $model ? $this->modelToEntity($model) : null;
    }

    /**
     * Crear un nuevo log
     */
    public function create(array $data): AdminLogEntity
    {
        $model = AdminLog::createLog($data);

        // Si el rate limiting evitó la creación, crear uno básico
        if (! $model) {
            $model = AdminLog::create(array_merge($data, [
                'error_hash' => md5(uniqid()),
                'created_at' => now(),
            ]));
        }

        return $this->modelToEntity($model->load('user:id,name,email'));
    }

    /**
     * Eliminar log por ID
     */
    public function delete(int $id): bool
    {
        return AdminLog::where('id', $id)->delete() > 0;
    }

    /**
     * Obtener estadísticas de logs
     */
    public function getStats(): array
    {
        return AdminLog::getStats();
    }

    /**
     * Limpiar logs antiguos
     */
    public function cleanupOldLogs(int $daysToKeep = 30, int $batchSize = 100): int
    {
        return AdminLog::cleanupOldLogs($daysToKeep, $batchSize);
    }

    /**
     * Obtener logs recientes con límite
     */
    public function getRecent(int $limit = 50): array
    {
        $models = AdminLog::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(function ($model) {
            return $this->modelToEntity($model);
        })->toArray();
    }

    /**
     * Buscar logs por evento específico
     */
    public function findByEventType(string $eventType, int $limit = 10): array
    {
        $models = AdminLog::with('user:id,name,email')
            ->where('event_type', $eventType)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(function ($model) {
            return $this->modelToEntity($model);
        })->toArray();
    }

    /**
     * Obtener logs críticos recientes
     */
    public function getCritical(int $hours = 24): array
    {
        $models = AdminLog::with('user:id,name,email')
            ->byCritical()
            ->where('created_at', '>', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(function ($model) {
            return $this->modelToEntity($model);
        })->toArray();
    }

    /**
     * Convertir modelo Eloquent a Entity
     */
    private function modelToEntity(AdminLog $model): AdminLogEntity
    {
        $userData = null;
        if ($model->user) {
            $userData = [
                'id' => $model->user->id,
                'name' => $model->user->name,
                'email' => $model->user->email,
            ];
        }

        return new AdminLogEntity(
            $model->id,
            $model->level,
            $model->event_type,
            $model->message,
            $model->context,
            $model->method,
            $model->url,
            $model->ip_address,
            $model->user_agent,
            $model->user_id,
            $model->status_code,
            $model->error_hash,
            Carbon::parse($model->created_at),
            $userData
        );
    }
}
