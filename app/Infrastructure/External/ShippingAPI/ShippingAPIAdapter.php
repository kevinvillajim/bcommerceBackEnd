<?php

namespace App\Infrastructure\External\ShippingAPI;

use App\Domain\Interfaces\ShippingTrackingInterface;
use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Carrier;
use App\Models\Shipping;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ShippingAPIAdapter implements ShippingTrackingInterface
{
    /**
     * URL base de la API de envíos (configurable en .env)
     */
    protected string $apiBaseUrl;

    /**
     * Clave de API para autenticación
     */
    protected string $apiKey;

    public function __construct()
    {
        $this->apiBaseUrl = config('services.shipping_api.url', 'https://api.shipping-service.example.com');
        $this->apiKey = config('services.shipping_api.key', 'test_api_key');
    }

    /**
     * {@inheritdoc}
     */
    public function getTrackingInfo(string $trackingNumber, ?string $carrierCode = null): array
    {
        try {
            // Verificar primero si tenemos la información en nuestra base de datos
            $shipping = Shipping::where('tracking_number', $trackingNumber)->first();

            if (! $shipping) {
                throw new Exception("Número de seguimiento no encontrado: {$trackingNumber}");
            }

            // En un entorno real, podríamos consultar la API externa para obtener actualizaciones
            // Para esta implementación de ejemplo, solo devolvemos lo que tenemos en nuestra BD

            $result = [
                'tracking_number' => $shipping->tracking_number,
                'carrier' => $shipping->carrier_name ?? 'Default Carrier',
                'status' => $shipping->status,
                'status_description' => ShippingStatus::getDescription($shipping->status),
                'current_location' => $shipping->current_location,
                'estimated_delivery' => $shipping->estimated_delivery?->format('Y-m-d H:i:s'),
                'delivered_at' => $shipping->delivered_at?->format('Y-m-d H:i:s'),
                'last_updated' => $shipping->last_updated?->format('Y-m-d H:i:s'),
                'destination' => [
                    'address' => $shipping->address,
                    'city' => $shipping->city,
                    'state' => $shipping->state,
                    'country' => $shipping->country,
                    'postal_code' => $shipping->postal_code,
                ],
                'is_delivered' => $shipping->status === ShippingStatus::DELIVERED,
                'is_exception' => ShippingStatus::isException($shipping->status),
                'history' => $shipping->history()
                    ->orderBy('timestamp', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($history) {
                        return [
                            'status' => $history->status,
                            'description' => $history->status_description,
                            'location' => $history->location,
                            'details' => $history->details,
                            'timestamp' => $history->timestamp->format('Y-m-d H:i:s'),
                        ];
                    })
                    ->toArray(),
            ];

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener información de seguimiento: '.$e->getMessage());

            return [
                'success' => false,
                'error' => 'No se pudo obtener la información de seguimiento: '.$e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateShippingStatus(array $data): bool
    {
        try {
            // Validar que tenemos los datos mínimos necesarios
            if (! isset($data['tracking_number']) || ! isset($data['status'])) {
                throw new Exception('Datos de actualización incompletos');
            }

            // Verificar que el estado es válido
            if (! ShippingStatus::isValid($data['status'])) {
                throw new Exception('Estado de envío inválido: '.$data['status']);
            }

            // Buscar el envío por número de tracking
            $shipping = Shipping::where('tracking_number', $data['tracking_number'])->first();

            if (! $shipping) {
                throw new Exception('Envío no encontrado: '.$data['tracking_number']);
            }

            // Si el envío ya está en un estado final, validar si se puede actualizar
            if (ShippingStatus::isFinalStatus($shipping->status)) {
                // Solo permitir actualizar desde DELIVERED a RETURNED o EXCEPTION
                if (
                    $shipping->status === ShippingStatus::DELIVERED &&
                    ($data['status'] === ShippingStatus::RETURNED || ShippingStatus::isException($data['status']))
                ) {
                    // Permitir actualización (devolución o problema posterior a entrega)
                } else {
                    throw new Exception('No se puede actualizar un envío que ya está en estado final: '.$shipping->status);
                }
            }

            // Preparar la información de ubicación
            $location = null;
            if (isset($data['current_location'])) {
                $location = $data['current_location'];
            }

            // Preparar el timestamp
            $timestamp = isset($data['timestamp'])
                ? Carbon::parse($data['timestamp'])
                : now();

            // Actualizar el estado y agregar al historial
            $shipping->addHistoryEvent(
                $data['status'],
                $location,
                $data['details'] ?? null,
                $timestamp
            );

            return true;
        } catch (Exception $e) {
            Log::error('Error al actualizar estado de envío: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getShippingRoute(string $trackingNumber): array
    {
        try {
            // Buscar el envío por número de tracking
            $shipping = Shipping::where('tracking_number', $trackingNumber)->first();

            if (! $shipping) {
                throw new Exception('Envío no encontrado: '.$trackingNumber);
            }

            // Obtener todos los puntos de ruta
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
                        'notes' => $point->notes,
                    ];
                })
                ->toArray();

            return [
                'success' => true,
                'data' => [
                    'tracking_number' => $trackingNumber,
                    'route_points' => $routePoints,
                    'status' => $shipping->status,
                    'is_delivered' => $shipping->status === ShippingStatus::DELIVERED,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener ruta de envío: '.$e->getMessage());

            return [
                'success' => false,
                'error' => 'No se pudo obtener la ruta de envío: '.$e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function estimateDeliveryDate(
        string $originPostalCode,
        string $destinationPostalCode,
        string $carrierCode,
        float $weight
    ): ?\DateTime {
        try {
            // En un entorno real, consultaríamos la API del transportista
            // Para este ejemplo, simulamos un cálculo básico

            // Verificar el transportista
            $carrier = Carrier::getByCode($carrierCode);

            if (! $carrier) {
                throw new Exception('Transportista no encontrado: '.$carrierCode);
            }

            // Simulación simple: Entrega en 3-7 días según peso y distancia
            $today = new \DateTime;

            // Simular variación por código postal (última cifra)
            $originVariation = intval(substr($originPostalCode, -1));
            $destVariation = intval(substr($destinationPostalCode, -1));

            // Calcular días base y añadir variaciones
            $baseDays = 3;
            $weightFactor = min(3, ceil($weight / 10)); // Máximo 3 días extra por peso
            $distanceFactor = abs($originVariation - $destVariation) % 3; // 0-2 días por "distancia"

            $totalDays = $baseDays + $weightFactor + $distanceFactor;

            // Considerar fin de semana (no hay entregas)
            $deliveryDate = clone $today;
            $deliveryDate->modify("+{$totalDays} weekdays");

            return $deliveryDate;
        } catch (Exception $e) {
            Log::error('Error al estimar fecha de entrega: '.$e->getMessage());

            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isValidTrackingNumber(string $trackingNumber, ?string $carrierCode = null): bool
    {
        // Validación básica: formato de tracking number
        if (empty($trackingNumber)) {
            return false;
        }

        // Si es un tracking number interno (comenzando con TR)
        if (strpos($trackingNumber, 'TR') === 0) {
            return strlen($trackingNumber) === 14; // TR + 6 dígitos de timestamp + 4 dígitos random
        }

        // Para transportistas externos, cada uno podría tener su propio formato
        if ($carrierCode) {
            $carrier = Carrier::getByCode($carrierCode);

            if ($carrier && ! empty($carrier->settings['tracking_regex'])) {
                return preg_match($carrier->settings['tracking_regex'], $trackingNumber) === 1;
            }
        }

        // Validación básica por defecto
        return preg_match('/^[A-Z0-9]{6,20}$/', $trackingNumber) === 1;
    }

    /**
     * Simular eventos de actualización para un envío (para pruebas)
     *
     * @param  Shipping  $shipping  Envío a actualizar
     * @param  int  $days  Número de días para la simulación
     * @return array Eventos generados
     */
    public function simulateShippingEvents(Shipping $shipping, int $days = 5): array
    {
        $events = [];
        $currentStatus = $shipping->status;
        $startDate = now()->subDays($days);

        // Preparar coordenadas de origen y destino para simular ruta
        $originLat = 19.4326;
        $originLng = -99.1332;
        $destinationLat = 19.2964;
        $destinationLng = -99.1679;

        // Definir estados para simular
        $statuses = [
            ShippingStatus::PENDING,
            ShippingStatus::PROCESSING,
            ShippingStatus::READY_FOR_PICKUP,
            ShippingStatus::PICKED_UP,
            ShippingStatus::IN_TRANSIT,
            ShippingStatus::OUT_FOR_DELIVERY,
            ShippingStatus::DELIVERED,
        ];

        // Si el envío ya está en estado final, no simulamos más eventos
        if (ShippingStatus::isFinalStatus($currentStatus)) {
            return $events;
        }

        // Encontrar índice actual en la secuencia de estados
        $currentIndex = array_search($currentStatus, $statuses);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        // Distribuir los días disponibles entre los estados restantes
        $remainingStatuses = count($statuses) - $currentIndex;
        $eventsPerDay = max(1, ceil($remainingStatuses / $days));

        // Generar eventos
        $eventDate = clone $startDate;
        for ($i = $currentIndex; $i < count($statuses); $i++) {
            $status = $statuses[$i];

            // Calcular coordenadas interpoladas para simular movimiento
            $progress = $i / count($statuses);
            $lat = $originLat + ($destinationLat - $originLat) * $progress;
            $lng = $originLng + ($destinationLng - $originLng) * $progress;

            // Añadir algo de variación aleatoria
            $lat += (mt_rand(-10, 10) / 1000);
            $lng += (mt_rand(-10, 10) / 1000);

            // Incrementar fecha para cada evento
            $eventDate->addHours(24 / $eventsPerDay);

            // Generar detalles según el estado
            $details = $this->generateEventDetails($status);

            // Crear evento
            $event = [
                'tracking_number' => $shipping->tracking_number,
                'status' => $status,
                'current_location' => [
                    'lat' => $lat,
                    'lng' => $lng,
                    'address' => $this->generateAddressForCoordinates($lat, $lng),
                ],
                'timestamp' => clone $eventDate,
                'details' => $details,
            ];

            $events[] = $event;

            // Si llegamos al estado final, terminamos
            if ($status === ShippingStatus::DELIVERED) {
                break;
            }
        }

        return $events;
    }

    /**
     * Generar texto de detalles para un evento según su estado
     */
    private function generateEventDetails(string $status): string
    {
        $details = [
            ShippingStatus::PENDING => [
                'Pedido recibido, en espera de procesamiento',
                'Pedido registrado en el sistema',
                'Pedido pendiente de procesamiento',
            ],
            ShippingStatus::PROCESSING => [
                'Pedido en procesamiento en el almacén',
                'Preparando paquete para envío',
                'Verificando inventario y embalaje',
            ],
            ShippingStatus::READY_FOR_PICKUP => [
                'Paquete listo para ser recogido por el transportista',
                'En espera de recogida por el servicio de mensajería',
                'Paquete embalado y etiquetado',
            ],
            ShippingStatus::PICKED_UP => [
                'Paquete recogido por el transportista',
                'Paquete en posesión del servicio de mensajería',
                'Iniciando ruta de distribución',
            ],
            ShippingStatus::IN_TRANSIT => [
                'Paquete en tránsito hacia su destino',
                'Movimiento entre centros de distribución',
                'En ruta hacia la ciudad de destino',
            ],
            ShippingStatus::OUT_FOR_DELIVERY => [
                'Paquete en ruta para entrega final',
                'Salida para entrega en la dirección del destinatario',
                'Entrega programada para hoy',
            ],
            ShippingStatus::DELIVERED => [
                'Paquete entregado al destinatario',
                'Entrega completada, firmado por: Cliente',
                'Entrega exitosa en la dirección indicada',
            ],
        ];

        $options = $details[$status] ?? ['Estado actualizado: '.$status];

        return $options[array_rand($options)];
    }

    /**
     * Generar una dirección ficticia para unas coordenadas
     */
    private function generateAddressForCoordinates(float $lat, float $lng): string
    {
        $addresses = [
            'Centro de distribución principal',
            'Almacén central',
            'Terminal de transporte',
            'Centro logístico regional',
            'Oficina de distribución local',
            'Centro de clasificación',
            'Sucursal de entrega',
            'Estación de transferencia',
            'Centro operativo de paquetería',
            'Punto de consolidación',
        ];

        return $addresses[mt_rand(0, count($addresses) - 1)];
    }
}
