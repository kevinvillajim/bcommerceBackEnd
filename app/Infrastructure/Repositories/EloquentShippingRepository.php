<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\ShippingRepositoryInterface;
use App\Models\Shipping;
use App\Models\ShippingHistory;
use App\Models\ShippingRoutePoint;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EloquentShippingRepository implements ShippingRepositoryInterface
{
    /**
     * Obtener envío por ID
     */
    public function findById(int $id): ?Shipping
    {
        /** @var Shipping|null $shipping */
        $shipping = Shipping::find($id);

        return $shipping;
    }

    /**
     * Obtener envío por número de tracking
     */
    public function findByTrackingNumber(string $trackingNumber): ?Shipping
    {
        return Shipping::query()->where('tracking_number', $trackingNumber)->first();
    }

    /**
     * Obtener todos los envíos de una orden
     */
    public function findByOrderId(int $orderId): Collection
    {
        return Shipping::query()->where('order_id', $orderId)->get();
    }

    /**
     * Obtener todos los envíos de un usuario
     */
    public function findByUserId(int $userId): Collection
    {
        return Shipping::query()->whereHas('order', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get();
    }

    /**
     * Crear un nuevo envío
     *
     * @throws Exception
     */
    public function create(array $data): Shipping
    {
        try {
            DB::beginTransaction();

            // Si no se proporciona un número de tracking, generamos uno
            if (empty($data['tracking_number'])) {
                $data['tracking_number'] = Shipping::generateTrackingNumber();
            }

            $shipping = Shipping::create($data);

            // Si hay datos de estado inicial, los registramos en el historial
            if (! empty($data['status'])) {
                $location = $data['current_location'] ?? null;
                $details = $data['details'] ?? 'Envío creado';

                $shipping->addHistoryEvent(
                    $data['status'],
                    $location,
                    $details
                );
            }

            DB::commit();

            return $shipping;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al crear envío: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar un envío existente
     */
    public function update(Shipping $shipping, array $data): Shipping
    {
        $shipping->update($data);

        return $shipping->fresh();
    }

    /**
     * Actualizar el estado de un envío
     *
     * @throws Exception
     */
    public function updateStatus(int $id, string $status, ?array $location = null, ?string $details = null): Shipping
    {
        try {
            $shipping = $this->findById($id);

            if (! $shipping) {
                throw new Exception("Envío no encontrado con ID: {$id}");
            }

            $shipping->addHistoryEvent($status, $location, $details);

            return $shipping->fresh();
        } catch (Exception $e) {
            Log::error('Error al actualizar estado de envío: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener historial de un envío
     */
    public function getHistory(int $shippingId): Collection
    {
        return ShippingHistory::query()->where('shipping_id', $shippingId)
            ->orderBy('timestamp', 'desc')
            ->get();
    }

    /**
     * Obtener puntos de ruta de un envío
     */
    public function getRoutePoints(int $shippingId): Collection
    {
        return ShippingRoutePoint::query()->where('shipping_id', $shippingId)
            ->orderBy('timestamp', 'asc')
            ->get();
    }
}
