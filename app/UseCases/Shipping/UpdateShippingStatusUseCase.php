<?php

namespace App\UseCases\Shipping;

use App\Domain\Interfaces\ShippingTrackingInterface;
use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Shipping;
use Exception;
use Illuminate\Support\Facades\Log;

class UpdateShippingStatusUseCase
{
    private ShippingTrackingInterface $trackingService;

    public function __construct(ShippingTrackingInterface $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * Actualizar el estado de un envío
     *
     * @param  array  $data  Datos de la actualización
     * @return array Respuesta con el resultado de la operación
     */
    public function execute(array $data): array
    {
        try {
            // Validar datos de entrada
            if (isset($data['shipping_id'])) {
                // Actualización por ID de envío
                return $this->updateByShippingId($data);
            } elseif (isset($data['tracking_number'])) {
                // Actualización por número de tracking
                return $this->updateByTrackingNumber($data);
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Se requiere shipping_id o tracking_number para actualizar el estado',
                ];
            }
        } catch (Exception $e) {
            Log::error('Error en UpdateShippingStatusUseCase: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Error interno al actualizar el estado del envío',
            ];
        }
    }

    /**
     * Actualizar por ID de envío
     */
    private function updateByShippingId(array $data): array
    {
        try {
            $shippingId = $data['shipping_id'];
            $newStatus = $data['status'];

            // Validar que el estado es válido
            if (! ShippingStatus::isValid($newStatus)) {
                return [
                    'status' => 'error',
                    'message' => 'Estado de envío inválido: '.$newStatus,
                ];
            }

            // Buscar el envío
            $shipping = Shipping::find($shippingId);
            if (! $shipping) {
                return [
                    'status' => 'error',
                    'message' => 'Envío no encontrado con ID: '.$shippingId,
                ];
            }

            // Verificar si el envío ya está en un estado final
            if (ShippingStatus::isFinalStatus($shipping->status)) {
                // Permitir más transições desde estados finales para el seller
                $allowedTransitions = [
                    ShippingStatus::DELIVERED => [
                        ShippingStatus::RETURNED,
                        ShippingStatus::FAILED,
                        ShippingStatus::IN_TRANSIT, // Para correcciones
                        ShippingStatus::SHIPPED,    // Para correcciones
                        ShippingStatus::PENDING,    // Para correcciones
                        ShippingStatus::READY_TO_SHIP, // Para correcciones
                    ],
                    ShippingStatus::RETURNED => [
                        ShippingStatus::DELIVERED,  // Reentregado
                        ShippingStatus::FAILED,
                        ShippingStatus::PENDING,    // Para correcciones
                    ],
                    ShippingStatus::CANCELLED => [
                        ShippingStatus::PENDING,    // Para reactivar
                    ],
                ];

                $currentStatus = $shipping->status;
                $allowedFromCurrent = $allowedTransitions[$currentStatus] ?? [];

                // También permitir estados de excepción desde cualquier estado final
                if (ShippingStatus::isException($newStatus)) {
                    $allowedFromCurrent[] = $newStatus;
                }

                if (! in_array($newStatus, $allowedFromCurrent)) {
                    return [
                        'status' => 'error',
                        'message' => 'No se puede cambiar de '.ShippingStatus::getDescription($currentStatus).' a '.ShippingStatus::getDescription($newStatus),
                    ];
                }
            }

            // Preparar ubicación si se proporciona
            $location = $data['current_location'] ?? null;
            $details = $data['details'] ?? 'Estado actualizado a: '.ShippingStatus::getDescription($newStatus);

            // Actualizar el estado del envío
            $shipping->addHistoryEvent(
                $newStatus,
                $location,
                $details
            );

            return [
                'status' => 'success',
                'message' => 'Estado de envío actualizado correctamente',
                'data' => [
                    'shipping_id' => $shippingId,
                    'new_status' => $newStatus,
                    'status_description' => ShippingStatus::getDescription($newStatus),
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error al actualizar por shipping_id: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar por número de tracking
     */
    private function updateByTrackingNumber(array $data): array
    {
        try {
            // Validar datos mínimos requeridos
            if (! isset($data['tracking_number']) || ! isset($data['status'])) {
                return [
                    'status' => 'error',
                    'message' => 'Datos incompletos. Se requiere tracking_number y status.',
                ];
            }

            // Verificar que el número de tracking es válido
            if (! $this->trackingService->isValidTrackingNumber($data['tracking_number'])) {
                return [
                    'status' => 'error',
                    'message' => 'Número de seguimiento inválido',
                ];
            }

            // Verificar que el estado es válido
            if (! ShippingStatus::isValid($data['status'])) {
                return [
                    'status' => 'error',
                    'message' => 'Estado de envío inválido: '.$data['status'],
                ];
            }

            // Actualizar el estado usando el servicio de tracking
            $success = $this->trackingService->updateShippingStatus($data);

            if ($success) {
                return [
                    'status' => 'success',
                    'message' => 'Estado de envío actualizado correctamente',
                    'tracking_number' => $data['tracking_number'],
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'No se pudo actualizar el estado del envío',
                ];
            }
        } catch (Exception $e) {
            Log::error('Error al actualizar por tracking_number: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Simular eventos de envío para pruebas
     *
     * @param  string  $trackingNumber  Número de seguimiento
     * @param  int  $days  Días que durará la simulación
     * @return array Respuesta con el resultado de la operación
     */
    public function simulateShippingEvents(string $trackingNumber, int $days = 5): array
    {
        try {
            // Verificar que el número de tracking es válido
            if (! $this->trackingService->isValidTrackingNumber($trackingNumber)) {
                return [
                    'status' => 'error',
                    'message' => 'Número de seguimiento inválido',
                ];
            }

            $shipping = Shipping::where('tracking_number', $trackingNumber)->first();

            if (! $shipping) {
                return [
                    'status' => 'error',
                    'message' => 'Envío no encontrado',
                ];
            }

            // Verificar que no esté en estado final
            if (ShippingStatus::isFinalStatus($shipping->status)) {
                return [
                    'status' => 'error',
                    'message' => 'El envío ya está en estado final',
                ];
            }

            // Secuencia típica de estados
            $statusSequence = [
                ShippingStatus::PENDING => 'Pedido registrado en el sistema',
                ShippingStatus::PROCESSING => 'Pedido en procesamiento en almacén',
                ShippingStatus::READY_FOR_PICKUP => 'Paquete listo para ser recogido',
                ShippingStatus::PICKED_UP => 'Paquete recogido por transportista',
                ShippingStatus::IN_TRANSIT => 'Paquete en tránsito hacia destino',
                ShippingStatus::OUT_FOR_DELIVERY => 'Paquete en ruta para entrega final',
                ShippingStatus::DELIVERED => 'Paquete entregado al destinatario',
            ];

            // Ubicaciones simuladas (transición del almacén al destino)
            $locationSequence = [
                [
                    'lat' => 19.4326,
                    'lng' => -99.1332,
                    'address' => 'Centro de distribución principal',
                ],
                [
                    'lat' => 19.3910,
                    'lng' => -99.2837,
                    'address' => 'Centro de distribución secundario',
                ],
                [
                    'lat' => 19.2964,
                    'lng' => -99.1679,
                    'address' => 'Terminal de transporte',
                ],
                [
                    'lat' => 19.4050,
                    'lng' => -99.1726,
                    'address' => 'Sucursal de entrega',
                ],
                [
                    'lat' => 19.3560,
                    'lng' => -99.0730,
                    'address' => 'Punto de entrega',
                ],
            ];

            // Distribuir eventos a lo largo de los días
            $hoursPerDay = 24;
            $totalHours = $days * $hoursPerDay;
            $events = count($statusSequence);
            $hoursPerEvent = $totalHours / $events;

            // Fecha de inicio (ahora - días * 24h)
            $startDate = now()->subHours($totalHours);

            // Generar eventos
            $eventIndex = 0;
            foreach ($statusSequence as $status => $description) {
                // Calcular timestamp para este evento
                $timestamp = (clone $startDate)->addHours($eventIndex * $hoursPerEvent);

                // Seleccionar ubicación según el índice de evento
                $locationIndex = min($eventIndex, count($locationSequence) - 1);
                $location = $locationSequence[$locationIndex];

                // Registrar evento
                $shipping->addHistoryEvent(
                    $status,
                    $location,
                    $description,
                    $timestamp
                );

                $eventIndex++;
            }

            return [
                'status' => 'success',
                'message' => 'Simulación de eventos de envío creada correctamente',
                'tracking_number' => $trackingNumber,
                'events_count' => count($statusSequence),
            ];
        } catch (Exception $e) {
            Log::error('Error al simular eventos de envío: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Error interno al simular eventos de envío',
            ];
        }
    }
}
