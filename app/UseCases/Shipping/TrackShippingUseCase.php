<?php

namespace App\UseCases\Shipping;

use App\Domain\Interfaces\ShippingTrackingInterface;
use App\Models\Shipping;
use Exception;

class TrackShippingUseCase
{
    private ShippingTrackingInterface $trackingService;

    public function __construct(ShippingTrackingInterface $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * Obtener información de seguimiento de un envío
     *
     * @param  string  $trackingNumber  Número de seguimiento
     * @return array Respuesta con la información de seguimiento
     */
    public function execute(string $trackingNumber): array
    {
        try {
            // Validar el número de tracking
            if (! $this->trackingService->isValidTrackingNumber($trackingNumber)) {
                return [
                    'status' => 'error',
                    'message' => 'Número de seguimiento inválido',
                ];
            }

            // Obtener información de tracking
            $trackingInfo = $this->trackingService->getTrackingInfo($trackingNumber);

            if (! $trackingInfo['success']) {
                return [
                    'status' => 'error',
                    'message' => $trackingInfo['error'] ?? 'No se pudo obtener la información de seguimiento',
                ];
            }

            // Si la operación fue exitosa, devolver los datos
            return [
                'status' => 'success',
                'data' => $trackingInfo['data'],
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al realizar el seguimiento: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Obtener el historial completo de un envío
     *
     * @param  string  $trackingNumber  Número de seguimiento
     * @return array Respuesta con el historial de seguimiento
     */
    public function getShippingHistory(string $trackingNumber): array
    {
        try {
            // Buscar el envío por número de tracking
            $shipping = Shipping::where('tracking_number', $trackingNumber)->first();

            if (! $shipping) {
                return [
                    'status' => 'error',
                    'message' => 'Envío no encontrado',
                ];
            }

            // Obtener todo el historial
            $history = $shipping->history()
                ->orderBy('timestamp', 'asc')
                ->get()
                ->map(function ($entry) {
                    return [
                        'status' => $entry->status,
                        'description' => $entry->status_description,
                        'location' => $entry->location,
                        'details' => $entry->details,
                        'timestamp' => $entry->timestamp->format('Y-m-d H:i:s'),
                    ];
                });

            return [
                'status' => 'success',
                'data' => [
                    'tracking_number' => $trackingNumber,
                    'current_status' => $shipping->status,
                    'history' => $history,
                ],
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al obtener el historial: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Obtener la ruta completa de un envío para mostrar en mapa
     *
     * @param  string  $trackingNumber  Número de seguimiento
     * @return array Respuesta con los puntos de la ruta
     */
    public function getShippingRoute(string $trackingNumber): array
    {
        try {
            // Usar el servicio de tracking para obtener la ruta
            $routeInfo = $this->trackingService->getShippingRoute($trackingNumber);

            if (! $routeInfo['success']) {
                return [
                    'status' => 'error',
                    'message' => $routeInfo['error'] ?? 'No se pudo obtener la ruta del envío',
                ];
            }

            return [
                'status' => 'success',
                'data' => $routeInfo['data'],
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al obtener la ruta: '.$e->getMessage(),
            ];
        }
    }
}
