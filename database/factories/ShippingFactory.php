<?php

namespace Database\Factories;

use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shipping>
 */
class ShippingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Shipping::class;

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

        return [
            'order_id' => function () {
                return Order::factory()->create()->id;
            },
            'tracking_number' => 'TR'.$this->faker->numerify('##########'),
            'status' => $status,
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'country' => $this->faker->country(),
            'postal_code' => $this->faker->postcode(),
            'phone' => $this->faker->phoneNumber(),
            'current_location' => [
                'lat' => $lat,
                'lng' => $lng,
                'address' => $this->faker->randomElement($addresses),
            ],
            'estimated_delivery' => $this->faker->dateTimeBetween('+1 day', '+7 days'),
            'delivered_at' => $status === ShippingStatus::DELIVERED ? $this->faker->dateTimeBetween('-3 days', 'now') : null,
            'carrier_name' => $this->faker->randomElement(['DHL', 'FedEx', 'UPS', 'Estafeta', 'Correos']),
            'last_updated' => now(),
        ];
    }

    /**
     * Indicate that the shipping is in a pending state.
     */
    public function pending(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ShippingStatus::PENDING,
                'delivered_at' => null,
            ];
        });
    }

    /**
     * Indicate that the shipping is in transit.
     */
    public function inTransit(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ShippingStatus::IN_TRANSIT,
                'delivered_at' => null,
            ];
        });
    }

    /**
     * Indicate that the shipping is out for delivery.
     */
    public function outForDelivery(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ShippingStatus::OUT_FOR_DELIVERY,
                'delivered_at' => null,
            ];
        });
    }

    /**
     * Indicate that the shipping is delivered.
     */
    public function delivered(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ShippingStatus::DELIVERED,
                'delivered_at' => now(),
            ];
        });
    }

    /**
     * Indicate that the shipping has an exception.
     */
    public function withException(string $exceptionType = ShippingStatus::EXCEPTION): Factory
    {
        return $this->state(function (array $attributes) use ($exceptionType) {
            return [
                'status' => $exceptionType,
                'delivered_at' => null,
            ];
        });
    }
}
