<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\NotificationEntity;

interface NotificationRepositoryInterface
{
    /**
     * Crear una nueva notificación
     */
    public function create(NotificationEntity $notification): NotificationEntity;

    /**
     * Buscar notificaciones por ID
     */
    public function findById(int $id): ?NotificationEntity;

    /**
     * Obtener notificaciones de un usuario
     */
    public function findByUserId(int $userId, int $limit = 20, int $offset = 0): array;

    /**
     * Obtener notificaciones no leídas de un usuario
     */
    public function findUnreadByUserId(int $userId, int $limit = 20, int $offset = 0): array;

    /**
     * Contar notificaciones no leídas de un usuario
     */
    public function countUnreadByUserId(int $userId): int;

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead(int $id): bool;

    /**
     * Marcar todas las notificaciones de un usuario como leídas
     */
    public function markAllAsRead(int $userId): bool;

    /**
     * Eliminar una notificación
     */
    public function delete(int $id): bool;
}
