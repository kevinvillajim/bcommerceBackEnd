<?php

namespace App\Domain\Repositories;

use App\Models\Shipping;
use Illuminate\Database\Eloquent\Collection;

interface ShippingRepositoryInterface
{
    /**
     * Obtener envío por ID
     */
    public function findById(int $id): ?Shipping;

    /**
     * Obtener envío por número de tracking
     */
    public function findByTrackingNumber(string $trackingNumber): ?Shipping;

    /**
     * Obtener todos los envíos de una orden
     */
    public function findByOrderId(int $orderId): Collection;

    /**
     * Obtener todos los envíos de un usuario
     */
    public function findByUserId(int $userId): Collection;

    /**
     * Crear un nuevo envío
     */
    public function create(array $data): Shipping;

    /**
     * Actualizar un envío existente
     */
    public function update(Shipping $shipping, array $data): Shipping;

    /**
     * Actualizar el estado de un envío
     */
    public function updateStatus(int $id, string $status, ?array $location = null, ?string $details = null): Shipping;

    /**
     * Obtener historial de un envío
     */
    public function getHistory(int $shippingId): Collection;

    /**
     * Obtener puntos de ruta de un envío
     */
    public function getRoutePoints(int $shippingId): Collection;
}
