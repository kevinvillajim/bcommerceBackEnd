<?php

namespace Database\Factories;

use App\Models\DiscountCode;
use App\Models\Feedback;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscountCode>
 */
class DiscountCodeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DiscountCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feedback_id' => Feedback::factory(),
            'code' => strtoupper(Str::random(6)),
            'discount_percentage' => 5.00,
            'is_used' => false,
            'used_by' => null,
            'used_at' => null,
            'used_on_product_id' => null,
            'expires_at' => now()->addDays(30),
        ];
    }

    /**
     * Indicate that the discount code is used.
     */
    public function used(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_used' => true,
                'used_by' => User::factory(),
                'used_at' => now()->subDays(rand(1, 10)),
                'used_on_product_id' => Product::factory(),
            ];
        });
    }

    /**
     * Indicate that the discount code is expired.
     */
    public function expired(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'expires_at' => now()->subDays(rand(1, 30)),
            ];
        });
    }
}
