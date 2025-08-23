<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat>
 */
class ChatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Conseguir un usuario cualquiera
        $user = User::inRandomOrder()->first();

        // Obtener un vendedor (usuario con tienda)
        $seller = User::whereHas('seller')->inRandomOrder()->first();

        // Obtener un producto del vendedor o cualquier producto
        $product = Product::where('seller_id', $seller->id ?? 0)
            ->inRandomOrder()
            ->first() ?? Product::inRandomOrder()->first();

        // Si no hay usuarios, vendedores o productos, crear valores por defecto
        if (! $user) {
            $user = User::factory()->create();
        }

        if (! $seller) {
            $seller = User::factory()->create(['role' => 'seller']);
        }

        if (! $product) {
            $product = Product::factory()->create(['seller_id' => $seller->id]);
        }

        return [
            'user_id' => $user->id,
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'status' => $this->faker->randomElement(['active', 'closed', 'archived']),
        ];
    }

    /**
     * Establecer el estado del chat como activo
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
            ];
        });
    }

    /**
     * Establecer el estado del chat como cerrado
     */
    public function closed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'closed',
            ];
        });
    }

    /**
     * Establecer el estado del chat como archivado
     */
    public function archived()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'archived',
            ];
        });
    }
}
