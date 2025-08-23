<?php

namespace Database\Factories;

use App\Models\Carrier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Carrier>
 */
class CarrierFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Carrier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $carrierName = $this->faker->randomElement(['DHL', 'FedEx', 'UPS', 'Estafeta', 'Post', 'Express Delivery', 'Lightning Logistics', 'FastTrack', 'Global Shipping']);
        $code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $carrierName));

        return [
            'name' => $carrierName,
            'code' => $code,
            'api_key' => $this->faker->uuid(),
            'api_secret' => $this->faker->sha256(),
            'tracking_url_format' => 'https://track.'.strtolower($code).'.com/tracking?number={tracking_number}',
            'is_active' => true,
            'settings' => [
                'tracking_regex' => '/^[A-Z0-9]{10,15}$/',
                'supports_realtime_tracking' => $this->faker->boolean(80),
                'supports_address_validation' => $this->faker->boolean(60),
                'delivery_days' => $this->faker->numberBetween(1, 7),
                'max_package_weight' => $this->faker->numberBetween(20, 50),
                'delivery_zones' => [
                    'national' => true,
                    'international' => $this->faker->boolean(50),
                ],
            ],
        ];
    }

    /**
     * Configurar un transportista inactivo
     */
    public function inactive(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }

    /**
     * Configurar un transportista con soporte internacional
     */
    public function international(): Factory
    {
        return $this->state(function (array $attributes) {
            $settings = $attributes['settings'] ?? [];
            $settings['delivery_zones']['international'] = true;

            return [
                'settings' => $settings,
            ];
        });
    }

    /**
     * Configurar un transportista que soporta seguimiento en tiempo real
     */
    public function withRealtimeTracking(): Factory
    {
        return $this->state(function (array $attributes) {
            $settings = $attributes['settings'] ?? [];
            $settings['supports_realtime_tracking'] = true;

            return [
                'settings' => $settings,
            ];
        });
    }
}
