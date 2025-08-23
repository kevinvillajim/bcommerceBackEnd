<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\AdminLogEntity;

interface AdminLogRepositoryInterface
{
    /**
     * Obtener todos los logs con filtros y paginación
     */
    public function findAll(array $filters = [], int $limit = 10, int $offset = 0): array;

    /**
     * Contar logs con filtros
     */
    public function count(array $filters = []): int;

    /**
     * Encontrar log por ID
     */
    public function findById(int $id): ?AdminLogEntity;

    /**
     * Crear un nuevo log
     */
    public function create(array $data): AdminLogEntity;

    /**
     * Eliminar log por ID
     */
    public function delete(int $id): bool;

    /**
     * Obtener estadísticas de logs
     */
    public function getStats(): array;

    /**
     * Limpiar logs antiguos
     */
    public function cleanupOldLogs(int $daysToKeep = 30, int $batchSize = 100): int;

    /**
     * Obtener logs recientes con límite
     */
    public function getRecent(int $limit = 50): array;

    /**
     * Buscar logs por evento específico
     */
    public function findByEventType(string $eventType, int $limit = 10): array;

    /**
     * Obtener logs críticos recientes
     */
    public function getCritical(int $hours = 24): array;
}
