<?php

namespace App\Infrastructure\Services;

use App\Domain\Interfaces\ShippingTrackingInterface;
use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Shipping;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Log;

class ShippingTrackingService implements ShippingTrackingInterface
{
    /**
     * Obtener información de seguimiento actualizada para un envío
     *
     * @param  string  $trackingNumber  Número de seguimiento
     * @param  string|null  $carrierCode  Código del transportista (opcional)
     * @return array Información de seguimiento
     */
    public function getTrackingInfo(string $trackingNumber, ?string $carrierCode = null): array
    {
        try {
            $query = Shipping::where('tracking_number', $trackingNumber);

            if ($carrierCode) {
                $query->where('carrier_name', $carrierCode);
            }

            $shipping = $query->first();

            if (! $shipping) {
                return [
                    'success' => false,
                    'error' => 'Número de seguimiento no encontrado',
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'tracking_number' => $shipping->tracking_number,
                    'status' => $shipping->status,
                    'current_location' => $shipping->current_location,
                    'estimated_delivery' => $shipping->estimated_delivery ? $shipping->estimated_delivery->format('Y-m-d H:i:s') : null,
                    'delivered_at' => $shipping->delivered_at ? $shipping->delivered_at->format('Y-m-d H:i:s') : null,
                    'carrier_name' => $shipping->carrier_name,
                    'destination' => [
                        'address' => $shipping->address,
                        'city' => $shipping->city,
                        'state' => $shipping->state,
                        'country' => $shipping->country,
                        'postal_code' => $shipping->postal_code,
                    ],
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener información de tracking: '.$e->getMessage());

            return [
                'success' => false,
                'error' => 'Error interno al obtener información de seguimiento',
            ];
        }
    }

    /**
     * Registrar una actualización en el estado de un envío
     *
     * @param  array  $data  Datos de la actualización de seguimiento
     * @return bool Si la actualización fue exitosa
     */
    public function updateShippingStatus(array $data): bool
    {
        try {
            // Verificar que los datos necesarios están presentes
            if (! isset($data['tracking_number']) || ! isset($data['status'])) {
                return false;
            }

            $shipping = Shipping::where('tracking_number', $data['tracking_number'])->first();

            if (! $shipping) {
                return false;
            }

            // Preparar ubicación si está presente
            $location = null;
            if (isset($data['location']) && is_array($data['location'])) {
                $location = $data['location'];
            }

            // Preparar detalles
            $details = $data['details'] ?? 'Actualización de estado a: '.$data['status'];

            // Preparar timestamp
            $timestamp = null;
            if (isset($data['timestamp'])) {
                $timestamp = is_string($data['timestamp'])
                    ? new DateTime($data['timestamp'])
                    : $data['timestamp'];
            }

            // Registrar el evento en el historial
            $shipping->addHistoryEvent(
                $data['status'],
                $location,
                $details,
                $timestamp
            );

            return true;
        } catch (Exception $e) {
            Log::error('Error al actualizar el estado del envío: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Obtener la ruta completa (todas las ubicaciones) de un envío
     *
     * @param  string  $trackingNumber  Número de seguimiento
     * @return array Lista de puntos de la ruta con coordenadas
     */
    public function getShippingRoute(string $trackingNumber): array
    {
        try {
            $shipping = Shipping::where('tracking_number', $trackingNumber)->first();

            if (! $shipping) {
                return [
                    'success' => false,
                    'error' => 'Número de seguimiento no encontrado',
                ];
            }

            $routePoints = $shipping->routePoints()
                ->orderBy('timestamp', 'asc')
                ->get()
                ->map(function ($point) {
                    return [
                        'lat' => $point->latitude,
                        'lng' => $point->longitude,
                        'address' => $point->address,
                        'timestamp' => $point->timestamp->format('Y-m-d H:i:s'),
                        'status' => $point->status,
                    ];
                })
                ->toArray();

            return [
                'success' => true,
                'data' => [
                    'tracking_number' => $shipping->tracking_number,
                    'route_points' => $routePoints,
                    'status' => $shipping->status,
                    'is_delivered' => $shipping->status === ShippingStatus::DELIVERED,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener ruta de envío: '.$e->getMessage());

            return [
                'success' => false,
                'error' => 'Error al obtener la ruta del envío',
            ];
        }
    }

    /**
     * Estimar el tiempo de entrega para un envío
     *
     * @param  string  $originPostalCode  Código postal de origen
     * @param  string  $destinationPostalCode  Código postal de destino
     * @param  string  $carrierCode  Código del transportista
     * @param  float  $weight  Peso en kg
     * @return \DateTime|null Fecha estimada de entrega
     */
    public function estimateDeliveryDate(
        string $originPostalCode,
        string $destinationPostalCode,
        string $carrierCode,
        float $weight
    ): ?\DateTime {
        // Implementación básica: estimamos de 3 a 5 días dependiendo del peso
        $baseDays = 3; // Base de días para entrega

        // Ajustar por peso
        if ($weight > 20) {
            $baseDays += 2; // Paquetes pesados tardan más
        } elseif ($weight > 10) {
            $baseDays += 1;
        }

        // Ajustar por transportista
        switch (strtoupper($carrierCode)) {
            case 'DHL':
            case 'FEDEX':
                $baseDays -= 1; // Servicios express
                break;
            case 'STANDARD':
                $baseDays += 1; // Servicio económico
                break;
        }

        // Garantizar un mínimo de 1 día
        $baseDays = max(1, $baseDays);

        // Generar fecha estimada de entrega
        $estimatedDate = new DateTime;
        $estimatedDate->modify("+{$baseDays} days");

        // Si cae en fin de semana, mover al siguiente día hábil
        $dayOfWeek = (int) $estimatedDate->format('N');
        if ($dayOfWeek >= 6) { // 6=sábado, 7=domingo
            $daysToAdd = 8 - $dayOfWeek; // Mover al lunes
            $estimatedDate->modify("+{$daysToAdd} days");
        }

        return $estimatedDate;
    }

    /**
     * Verificar si un número de seguimiento es válido
     *
     * @param  string  $trackingNumber  Número de seguimiento
     * @param  string|null  $carrierCode  Código del transportista (opcional)
     * @return bool Si el número de seguimiento es válido
     */
    public function isValidTrackingNumber(string $trackingNumber, ?string $carrierCode = null): bool
    {
        $query = Shipping::where('tracking_number', $trackingNumber);

        if ($carrierCode) {
            $query->where('carrier_name', $carrierCode);
        }

        return $query->exists();
    }
}
