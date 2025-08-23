<?php

namespace Database\Factories;

use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Shipping;
use App\Models\ShippingRoutePoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShippingRoutePoint>
 */
class ShippingRoutePointFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ShippingRoutePoint::class;

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
            'Punto de transferencia',
            'Centro de clasificación',
            'Estación logística',
            'Oficina local de distribución',
            'Punto de entrega',
        ];

        return [
            'shipping_id' => Shipping::factory(),
            'latitude' => $lat,
            'longitude' => $lng,
            'address' => $this->faker->randomElement($addresses),
            'timestamp' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'status' => $status,
            'notes' => $this->faker->optional(0.7)->sentence(),
        ];
    }

    /**
     * Configurar un punto de ruta con coordenadas específicas
     */
    public function atCoordinates(float $lat, float $lng): Factory
    {
        return $this->state(function (array $attributes) use ($lat, $lng) {
            return [
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        });
    }

    /**
     * Configurar un punto de ruta con una dirección específica
     */
    public function atAddress(string $address): Factory
    {
        return $this->state(function (array $attributes) use ($address) {
            return [
                'address' => $address,
            ];
        });
    }

    /**
     * Configurar un punto de ruta con un estado específico
     */
    public function withStatus(string $status): Factory
    {
        return $this->state(function (array $attributes) use ($status) {
            return [
                'status' => $status,
            ];
        });
    }

    /**
     * Configurar un punto de ruta con un timestamp específico
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
