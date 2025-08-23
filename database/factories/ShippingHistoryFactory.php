<?php

namespace Database\Factories;

use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Shipping;
use App\Models\ShippingHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShippingHistory>
 */
class ShippingHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ShippingHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement([
            ShippingStatus::PENDING,
            ShippingStatus::PROCESSING,
            ShippingStatus::READY_FOR_PICKUP,
            ShippingStatus::PICKED_UP,
            ShippingStatus::IN_TRANSIT,
            ShippingStatus::OUT_FOR_DELIVERY,
            ShippingStatus::DELIVERED,
        ]);

        // Generar ubicación aleatoria en un radio cercano a Ciudad de México
        $baseLat = 19.4326;
        $baseLng = -99.1332;
        $lat = $baseLat + ($this->faker->randomFloat(4, -0.1, 0.1));
        $lng = $baseLng + ($this->faker->randomFloat(4, -0.1, 0.1));

        $addresses = [
            'Centro de distribución principal',
            'Almacén central',
            'Terminal de transporte',
            'Sucursal de entrega',
            'Centro logístico regional',
        ];

        $details = [
            ShippingStatus::PENDING => [
                'Pedido recibido, en espera de procesamiento',
                'Pedido registrado en el sistema',
                'Esperando procesamiento en almacén',
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

        $statusDetails = $details[$status] ?? ['Estado actualizado: '.$status];

        return [
            'shipping_id' => Shipping::factory(),
            'status' => $status,
            'location' => [
                'lat' => $lat,
                'lng' => $lng,
                'address' => $this->faker->randomElement($addresses),
            ],
            'details' => $this->faker->randomElement($statusDetails),
            'timestamp' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    /**
     * Configurar un registro de historial para un estado específico
     */
    public function withStatus(string $status): Factory
    {
        $details = [
            ShippingStatus::PENDING => [
                'Pedido recibido, en espera de procesamiento',
                'Pedido registrado en el sistema',
                'Esperando procesamiento en almacén',
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
            ShippingStatus::EXCEPTION => [
                'Problema durante la entrega',
                'No se pudo entregar el paquete',
                'Excepción en la ruta de entrega',
            ],
            ShippingStatus::RETURNED => [
                'Paquete devuelto al remitente',
                'Cliente rechazó el paquete',
                'Devolución procesada',
            ],
        ];

        $statusDetails = $details[$status] ?? ['Estado actualizado: '.$status];

        return $this->state(function (array $attributes) use ($status, $statusDetails) {
            return [
                'status' => $status,
                'details' => $this->faker->randomElement($statusDetails),
            ];
        });
    }

    /**
     * Configurar un registro de historial con un timestamp específico
     */
    public function atTime(string $timeString): Factory
    {
        return $this->state(function (array $attributes) use ($timeString) {
            return [
                'timestamp' => new \DateTime($timeString),
            ];
        });
    }
}
