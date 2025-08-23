<?php

namespace App\Http\Controllers;

use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Shipping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para simular una API externa de envíos
 * Este controlador es solo para pruebas y desarrollo
 */
class ShippingAPISimulatorController extends Controller
{
    /**
     * Obtener información de un envío (simulando API externa)
     */
    public function getShippingInfo(Request $request, string $trackingNumber): JsonResponse
    {
        // Verificar en entorno de desarrollo/pruebas
        if (! app()->environment(['local', 'development', 'testing'])) {
            return response()->json([
                'error' => 'Esta API solo está disponible en entornos de desarrollo',
                'status' => 403,
            ], 403);
        }

        try {
            // Validar formato de tracking number
            if (! preg_match('/^[A-Z0-9]{6,20}$/', $trackingNumber)) {
                return response()->json([
                    'error' => 'Formato de número de tracking inválido',
                    'status' => 400,
                ], 400);
            }

            // Buscar envío en nuestra BD
            $shipping = Shipping::where('tracking_number', $trackingNumber)->first();

            if (! $shipping) {
                return response()->json([
                    'error' => 'Envío no encontrado',
                    'status' => 404,
                ], 404);
            }

            // Simular la respuesta de una API externa
            $response = [
                'tracking_number' => $shipping->tracking_number,
                'carrier' => $shipping->carrier_name,
                'status' => $shipping->status,
                'status_description' => ShippingStatus::getDescription($shipping->status),
                'last_updated' => $shipping->last_updated?->format('Y-m-d\TH:i:s\Z'),
                'estimated_delivery' => $shipping->estimated_delivery?->format('Y-m-d\TH:i:s\Z'),
                'delivered_at' => $shipping->delivered_at?->format('Y-m-d\TH:i:s\Z'),
                'current_location' => $shipping->current_location,
                'destination' => [
                    'address' => $shipping->address,
                    'city' => $shipping->city,
                    'state' => $shipping->state,
                    'country' => $shipping->country,
                    'postal_code' => $shipping->postal_code,
                ],
                'history' => $shipping->history()
                    ->orderBy('timestamp', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($event) {
                        return [
                            'status' => $event->status,
                            'location' => $event->location,
                            'details' => $event->details,
                            'timestamp' => $event->timestamp->format('Y-m-d\TH:i:s\Z'),
                        ];
                    }),
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error en el simulador de API de envíos: '.$e->getMessage());

            return response()->json([
                'error' => 'Error interno del servidor',
                'status' => 500,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Simular una actualización de estado automática
     */
    public function simulateStatusUpdate(Request $request): JsonResponse
    {
        // Verificar en entorno de desarrollo/pruebas
        if (! app()->environment(['local', 'development', 'testing'])) {
            return response()->json([
                'error' => 'Esta API solo está disponible en entornos de desarrollo',
                'status' => 403,
            ], 403);
        }

        // Validar API key
        $apiKey = $request->header('X-API-KEY');
        $configApiKey = config('services.shipping_api.key');

        if (empty($apiKey) || $apiKey !== $configApiKey) {
            return response()->json([
                'error' => 'API key inválida',
                'status' => 401,
            ], 401);
        }

        try {
            // Obtener los datos de la solicitud
            $trackingNumber = $request->input('tracking_number');

            if (! $trackingNumber) {
                return response()->json([
                    'error' => 'Número de tracking requerido',
                    'status' => 400,
                ], 400);
            }

            // Buscar el envío
            $shipping = Shipping::where('tracking_number', $trackingNumber)->first();

            if (! $shipping) {
                return response()->json([
                    'error' => 'Envío no encontrado',
                    'status' => 404,
                ], 404);
            }

            // Determinar el siguiente estado lógico
            $currentStatus = $shipping->status;
            $nextStatus = ShippingStatus::getNextStatus($currentStatus);

            if (! $nextStatus) {
                // Si ya está en estado final o no hay siguiente estado
                return response()->json([
                    'error' => 'No hay siguiente estado disponible',
                    'status' => 400,
                ], 400);
            }

            // Generar ubicación para la actualización
            $progress = 0;
            switch ($nextStatus) {
                case ShippingStatus::PROCESSING:
                    $progress = 0.1;
                    break;
                case ShippingStatus::READY_FOR_PICKUP:
                    $progress = 0.2;
                    break;
                case ShippingStatus::PICKED_UP:
                    $progress = 0.3;
                    break;
                case ShippingStatus::IN_TRANSIT:
                    $progress = 0.5;
                    break;
                case ShippingStatus::OUT_FOR_DELIVERY:
                    $progress = 0.8;
                    break;
                case ShippingStatus::DELIVERED:
                    $progress = 1.0;
                    break;
            }

            // Interpolar entre ubicación de origen y destino
            $originLat = 19.4326;
            $originLng = -99.1332;
            $destLat = 19.2964;
            $destLng = -99.1679;

            $lat = $originLat + ($destLat - $originLat) * $progress;
            $lng = $originLng + ($destLng - $originLng) * $progress;

            // Añadir algo de variación aleatoria
            $lat += (mt_rand(-10, 10) / 1000);
            $lng += (mt_rand(-10, 10) / 1000);

            $addresses = [
                'Centro de distribución principal',
                'Almacén central',
                'Terminal de transporte',
                'Sucursal de entrega',
                'Centro logístico regional',
            ];
            $address = $addresses[array_rand($addresses)];

            // Generar mensaje según el estado
            $detailMessages = [
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

            $statusMessages = $detailMessages[$nextStatus] ?? ['Estado actualizado: '.$nextStatus];
            $details = $statusMessages[array_rand($statusMessages)];

            // Crear datos de actualización
            $updateData = [
                'tracking_number' => $trackingNumber,
                'status' => $nextStatus,
                'current_location' => [
                    'lat' => $lat,
                    'lng' => $lng,
                    'address' => $address,
                ],
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                'details' => $details,
            ];

            // Enviar actualización a nuestra API
            $response = app(ShippingController::class)->externalStatusUpdate(
                new Request($updateData, [], [], [], [], ['HTTP_X-API-KEY' => $configApiKey])
            );

            // Verificar la respuesta
            $content = json_decode($response->getContent(), true);

            if ($response->getStatusCode() === 200 && isset($content['status']) && $content['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => 'Actualización simulada enviada correctamente',
                    'update' => $updateData,
                ]);
            } else {
                return response()->json([
                    'error' => 'Error al enviar la actualización simulada',
                    'status' => 500,
                    'response' => $content,
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en simulación de actualización: '.$e->getMessage());

            return response()->json([
                'error' => 'Error interno del servidor',
                'status' => 500,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Simular una serie de actualizaciones automáticas para llevar un envío de inicio a fin
     */
    public function simulateFullShippingCycle(Request $request, string $trackingNumber): JsonResponse
    {
        // Verificar en entorno de desarrollo/pruebas
        if (! app()->environment(['local', 'development', 'testing'])) {
            return response()->json([
                'error' => 'Esta API solo está disponible en entornos de desarrollo',
                'status' => 403,
            ], 403);
        }

        // Validar API key
        $apiKey = $request->header('X-API-KEY');
        $configApiKey = config('services.shipping_api.key');

        if (empty($apiKey) || $apiKey !== $configApiKey) {
            return response()->json([
                'error' => 'API key inválida',
                'status' => 401,
            ], 401);
        }

        try {
            // Buscar el envío
            $shipping = Shipping::where('tracking_number', $trackingNumber)->first();

            if (! $shipping) {
                return response()->json([
                    'error' => 'Envío no encontrado',
                    'status' => 404,
                ], 404);
            }

            // Verificar si el envío ya está en estado final
            if (ShippingStatus::isFinalStatus($shipping->status)) {
                return response()->json([
                    'error' => 'El envío ya está en estado final',
                    'status' => 400,
                ], 400);
            }

            // Definir todos los estados por los que pasará
            $states = [
                ShippingStatus::PENDING,
                ShippingStatus::PROCESSING,
                ShippingStatus::READY_FOR_PICKUP,
                ShippingStatus::PICKED_UP,
                ShippingStatus::IN_TRANSIT,
                ShippingStatus::OUT_FOR_DELIVERY,
                ShippingStatus::DELIVERED,
            ];

            // Encontrar el índice actual
            $currentIndex = array_search($shipping->status, $states);
            if ($currentIndex === false) {
                $currentIndex = 0;
            }

            // Generar actualizaciones para todos los estados restantes
            $updates = [];
            $startTimestamp = Carbon::now()->subHours(count($states) - $currentIndex - 1);

            for ($i = $currentIndex + 1; $i < count($states); $i++) {
                $status = $states[$i];
                $timestamp = (clone $startTimestamp)->addHours($i - $currentIndex - 1);

                // Solicitud para simular una actualización
                $simulateRequest = new Request([
                    'tracking_number' => $trackingNumber,
                ], [], [], [], [], ['HTTP_X-API-KEY' => $configApiKey]);

                // Ejecutar la simulación
                $response = $this->simulateStatusUpdate($simulateRequest);

                // Verificar respuesta
                $content = json_decode($response->getContent(), true);

                if ($response->getStatusCode() === 200 && isset($content['success']) && $content['success'] === true) {
                    $updates[] = $content['update'] ?? [];

                    // Esperar un poco entre actualizaciones
                    sleep(1);
                } else {
                    return response()->json([
                        'error' => 'Error en simulación de ciclo completo',
                        'status' => 500,
                        'last_response' => $content,
                        'updates_applied' => $updates,
                    ], 500);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Ciclo completo de envío simulado correctamente',
                'tracking_number' => $trackingNumber,
                'updates_applied' => count($updates),
                'final_status' => ShippingStatus::DELIVERED,
                'updates' => $updates,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en simulación de ciclo completo: '.$e->getMessage());

            return response()->json([
                'error' => 'Error interno del servidor',
                'status' => 500,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
