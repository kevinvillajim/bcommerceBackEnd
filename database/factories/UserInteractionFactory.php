<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserInteractionFactory extends Factory
{
    protected $model = UserInteraction::class;

    public function definition(): array
    {
        $interactionTypes = ['view_product', 'search', 'purchase', 'add_to_cart'];
        $interactionType = $this->faker->randomElement($interactionTypes);

        $metadata = [];

        if ($interactionType === 'search') {
            $searchTerms = [
                'celular usado',
                'celular nuevo',
                'laptop gaming',
                'auriculares bluetooth',
                'smartwatch',
                'tablet android',
                'cámara digital',
                'mochila inteligente',
                'teclado mecánico',
                'monitor 4k',
                'computadora',
                'calculadora',
            ];

            $metadata = [
                'search_term' => $this->faker->randomElement($searchTerms),
            ];
        } elseif ($interactionType === 'add_to_cart') {
            $metadata = [
                'quantity' => $this->faker->numberBetween(1, 5),
            ];
        }

        return [
            'user_id' => User::factory(),
            'interaction_type' => $interactionType,
            'item_id' => Product::factory(),
            'metadata' => $metadata,
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function search(): self
    {
        return $this->state(function (array $attributes) {
            $searchTerms = [
                'celular usado',
                'celular nuevo',
                'laptop gaming',
                'auriculares bluetooth',
                'smartwatch',
                'tablet android',
                'cámara digital',
                'mochila inteligente',
                'teclado mecánico',
                'monitor 4k',
                'computadora',
                'calculadora',
            ];

            return [
                'interaction_type' => 'search',
                'metadata' => [
                    'search_term' => $this->faker->randomElement($searchTerms),
                ],
            ];
        });
    }

    public function viewProduct(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'interaction_type' => 'view_product',
                'metadata' => [
                    'view_time' => $this->faker->numberBetween(5, 120),
                ],
            ];
        });
    }

    public function purchase(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'interaction_type' => 'purchase',
                'metadata' => [
                    'price' => $this->faker->randomFloat(2, 10, 1000),
                ],
            ];
        });
    }
}
