<?php

namespace App\UseCases\Shipping;

use App\Domain\Interfaces\ShippingTrackingInterface;
use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\Shipping;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateShippingUseCase
{
    private ShippingTrackingInterface $trackingService;

    public function __construct(ShippingTrackingInterface $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * Crear un nuevo envío para una orden
     *
     * @param  int  $orderId  ID de la orden
     * @param  array  $shippingData  Datos del envío
     * @return array Respuesta con el resultado de la operación
     */
    public function execute(int $orderId, array $shippingData): array
    {
        try {
            // Comenzar transacción
            DB::beginTransaction();

            // Buscar la orden
            /** @phpstan-ignore-next-line */
            $order = Order::findOrFail($orderId);

            // Verificar que la orden no tenga ya un envío
            if ($order->shipping()->exists()) {
                return [
                    'status' => 'error',
                    'message' => 'La orden ya tiene un envío asociado',
                ];
            }

            // Verificar que la orden está pagada
            if ($order->status !== 'paid') {
                return [
                    'status' => 'error',
                    'message' => 'No se puede crear un envío para una orden que no está pagada',
                ];
            }

            // Generar número de tracking
            $trackingNumber = Shipping::generateTrackingNumber();

            // Determinar transportista si se especificó
            $carrier = null;
            $carrierName = 'Default Carrier';

            if (! empty($shippingData['carrier_id'])) {
                $carrier = Carrier::find($shippingData['carrier_id']);
                if ($carrier) {
                    $carrierName = $carrier->name;
                }
            }

            // Calcular fecha estimada de entrega
            $estimatedDelivery = now()->addDays(5); // Valor por defecto

            if (
                ! empty($shippingData['origin_postal_code']) &&
                ! empty($shippingData['postal_code']) &&
                ! empty($carrier)
            ) {

                $weight = $this->calculateOrderWeight($order);

                $deliveryDate = $this->trackingService->estimateDeliveryDate(
                    $shippingData['origin_postal_code'],
                    $shippingData['postal_code'],
                    $carrier->code,
                    $weight
                );

                if ($deliveryDate) {
                    $estimatedDelivery = $deliveryDate;
                }
            }

            // Crear el registro de envío
            $shipping = new Shipping([
                'order_id' => $orderId,
                'tracking_number' => $trackingNumber,
                'status' => ShippingStatus::PENDING,
                'current_location' => [
                    'lat' => 19.4326,  // Coordenadas del almacén por defecto
                    'lng' => -99.1332,
                    'address' => 'Centro de distribución principal',
                ],
                'estimated_delivery' => $estimatedDelivery,
                'carrier_id' => $carrier ? $carrier->id : null,
                'carrier_name' => $carrierName,
                'last_updated' => now(),
            ]);

            $shipping->save();

            // Añadir el primer evento al historial
            $shipping->addHistoryEvent(
                ShippingStatus::PENDING,
                $shipping->current_location,
                'Pedido registrado en el sistema de envíos'
            );

            // Actualizar estado de la orden
            $order->shipping_status = ShippingStatus::PENDING;
            $order->save();

            // Confirmar transacción
            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Envío creado correctamente',
                'data' => [
                    'shipping_id' => $shipping->id,
                    'tracking_number' => $shipping->tracking_number,
                    'status' => $shipping->status,
                    'estimated_delivery' => $shipping->estimated_delivery->format('Y-m-d'),
                ],
            ];
        } catch (Exception $e) {
            // Deshacer cambios en caso de error
            DB::rollBack();

            Log::error('Error al crear envío: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Error al crear el envío: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Calcular el peso total de los productos en una orden
     */
    private function calculateOrderWeight(Order $order): float
    {
        $totalWeight = 0;

        foreach ($order->items as $item) {
            $weight = $item->product->weight ?? 0.5; // Peso por defecto si no está especificado
            $totalWeight += $weight * $item->quantity;
        }

        return max(0.1, $totalWeight); // Mínimo 100 gramos
    }
}
